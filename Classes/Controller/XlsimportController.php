<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Controller;

use Doctrine\DBAL\DBALException;
use InvalidArgumentException;
use JsonException;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Worksheet\RowCellIterator;
use PhpOffice\PhpSpreadsheet\Worksheet\RowIterator;
use SUDHAUS7\Xlsimport\Utility\AccessUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Recordlist\Controller\AccessDeniedException;

/**
 * Class XlsimportController
 */
class XlsimportController extends ActionController
{
    /**
     * Backend Template Container
     *
     * @var string
     */
    protected $defaultViewObjectName = BackendTemplateView::class;
    /**
     * @var LanguageService
     */
    protected LanguageService $languageService;

    /**
     * @var ResourceFactory
     */
    protected ResourceFactory $resourceFactory;

    public function __construct(
        ResourceFactory $resourceFactory,
        LanguageService $languageService
    )
    {
        $this->resourceFactory = $resourceFactory;
        $this->languageService = $languageService;
    }

    /**
     * XlsimportController constructor.
     */
    public function initializeObject(): void
    {
        GeneralUtility::makeInstance(PageRenderer::class)
            ->loadRequireJsModule('TYPO3/CMS/Xlsimport/Importer');
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws AccessDeniedException
     */
    public function indexAction(): void
    {
        $page = (int)GeneralUtility::_GET('id');
        $minimalPage = [
            'uid' => $page,
        ];

        if (!AccessUtility::getBackendUser()->doesUserHaveAccess($minimalPage, Permission::PAGE_EDIT)) {
            throw new AccessDeniedException(
                'You are not allowed to manipulate records on this page',
                1676071343135
            );
        }
        /**
         * @deprecated, don't use TypoScript module setup anymore
         * Use PageTSConfig or Extension setup instead
         * will be removed in future version
         */
        $tempTables = GeneralUtility::trimExplode(',', $this->settings['allowedTables']);

        $pageTS = BackendUtility::getPagesTSconfig($page);
        if (isset($pageTS['module.']['tx_xlsimport.']['settings.']['allowedTables'])) {
            $tempTables = array_merge(
                $tempTables,
                GeneralUtility::trimExplode(
                    ',',
                    $pageTS['module.']['tx_xlsimport.']['settings.']['allowedTables']
                )
            );
        }
        if ($extConfTempTables = GeneralUtility::makeInstance(
            ExtensionConfiguration::class
        )->get('xlsimport', 'tables')
        ) {
            $tempTables = array_merge(
                $tempTables,
                GeneralUtility::trimExplode(',', $extConfTempTables)
            );
        }
        $tempTables = array_unique($tempTables);
        $allowedTables = $this->checkTableAndAccessAllowed($tempTables, $page);

        $assignedValues = [
            'page' => $page,
            'allowedTables' => $allowedTables,
        ];
        $this->view->assignMultiple($assignedValues);
    }

    /**
     * Check if table has TCA definition
     * and user is allowed to edit table on this page
     * for each table and return array with allowed tables
     */
    private function checkTableAndAccessAllowed(array $possibleTables, int $pid): array
    {
        $allowedTables = [];
        foreach ($possibleTables as $possibleTable) {
            if (AccessUtility::isAllowedTable($possibleTable, $pid)) {
                $label = $GLOBALS['TCA'][$possibleTable]['ctrl']['title'];
                $allowedTables[$possibleTable] = $this->languageService->sL($label) ?: $label;            }
        }

        return $allowedTables;
    }

    /**
     * @throws Exception
     * @throws NoSuchArgumentException
     * @throws StopActionException
     * @throws DBALException
     * @throws AccessDeniedException
     */
    public function uploadAction(): void
    {
        $page = GeneralUtility::_GET('id');
        $tempPage = [
            'uid' => $page,
        ];
        if (!AccessUtility::getBackendUser()->doesUserHaveAccess($tempPage, Permission::PAGE_EDIT)) {
            throw new AccessDeniedException(
                'You are not allowed on editing this page',
                1676074682764
            );
        }

        $deleteOldRecords = (bool)$this->request->getArgument('deleteRecords');
        $file = $this->request->getArgument('file');
        $table = $this->request->getArgument('table');
        if ($deleteOldRecords && AccessUtility::isAllowedTable($table, $page)) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);

            $db = GeneralUtility::makeInstance(ConnectionPool::class);
            $builder = $db->getQueryBuilderForTable($table);
            $ids = $builder->select('*')->from($table)->where(
                $builder->expr()->eq('pid', $page)
            )->execute()->fetchAll();
            $cmd = [];
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $cmd[$table][$id['uid']]['delete'] = 1;
                }
                $dataHandler->start([], $cmd);
                $dataHandler->process_cmdmap();
            }
        }

        $uploadedFile = GeneralUtility::tempnam('xlsimport');
        GeneralUtility::upload_copy_move($file['tmp_name'], $uploadedFile);

        $list = $this->getList($uploadedFile);
        $uidConfig = [
            'uid' => [
                'label' => 'uid',
            ],
        ];
        $tca = array_merge($uidConfig, $GLOBALS['TCA'][$table]['columns']);

        if (!array_key_exists('pid', $GLOBALS['TCA'][$table]['columns'])) {
            $pidConfig = [
                'pid' => [
                    'label' => 'pid',
                ],
            ];
            $tca = array_merge($uidConfig, $pidConfig, $GLOBALS['TCA'][$table]['columns']);
        }

        $hasPasswordField = false;
        $passwordFields = [];

        foreach ($tca as $field => &$column) {
            if (!AccessUtility::isAllowedField($table, $field)) {
                unset($tca[$field]);
            } else {
                try {
                    $label = $this->languageService->sL($column['label']);
                } catch (InvalidArgumentException $e) {
                    $label = $column['label'];
                }
                if (empty($label)) {
                    $label = '[' . $field . ']';
                }
                if ($field === 'uid') {
                    $label = $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:uid');
                }
                if ($field === 'pid') {
                    $label = $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:pid');
                }
                $column['label'] = $label;

                if (
                    isset($column['config']['eval'])
                    && in_array(
                        'password',
                        GeneralUtility::trimExplode(',', $column['config']['eval'])
                    )
                ) {
                    $hasPasswordField = true;
                    $passwordFields[] = $field;
                }
            }
        }
        unset($column);

        $assignedValues = [
            'fields' => $tca,
            'data' => $list['data'],
            'page' => $page,
            'table' => $table,
            'hasPasswordField' => $hasPasswordField,
            'passwordFields' => implode(',', $passwordFields),
            'addInlineSettings' => [
                'FormEngine' => [
                    'formName' => 'importData',
                ],
            ],
        ];
        $this->view->assignMultiple($assignedValues);
    }

    /**
     * @throws NoSuchArgumentException
     * @throws StopActionException
     * @throws JsonException
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function importAction(): void
    {
        $page = GeneralUtility::_GET('id');
        $table = $this->request->getArgument('table');
        if (!$table || !array_key_exists($table, $GLOBALS['TCA'])) {
            $this->redirect('index');
            exit;
        }
        /** @var array $fields */
        $fields = $this->request->getArgument('fields');
        $overrides = [];
        if ($this->request->hasArgument('overrides')) {
            $overrides = $this->request->getArgument('overrides');
        }

        $passwordOverride = (bool)$this->request->getArgument('passwordOverride');
        $passwordFields = GeneralUtility::trimExplode(',', $this->request->getArgument('passwordFields'));

        /** @var array $imports */
        $imports = json_decode($this->request->getArgument('dataset'), true, 512, JSON_THROW_ON_ERROR);
        $a = [];
        foreach ($imports as $import) {
            $s = sprintf('%s=%s', $import['name'], urlencode($import['value']));
            $temp = [];
            parse_str($s, $temp);
            foreach ($temp['tx_xlsimport_web_xlsimporttxxlsimport']['dataset'] as $k => $v) {
                foreach ($v as $key => $value) {
                    $a[$k][$key] = $value;
                }
            }
        }
        $imports = $a;

        // unset all fields not assigned to a TCA field
        foreach ($fields as $key => $field) {
            if (empty($field)) {
                unset($fields[$key]);
            }
            if (!AccessUtility::isAllowedField($table, $field)) {
                unset($fields[$key]);
            }
        }
        // get override field and take a look inside fieldlist, if defined
        foreach ($overrides as $key => $override) {
            if (empty($override) | in_array($override, $fields, true)) {
                unset($overrides[$key]);
            }
        }

        if ($passwordOverride) {
            foreach ($passwordFields as $key => $passwordField) {
                if (in_array($passwordField, $fields, true)) {
                    unset($passwordFields[$key]);
                }
            }
        } else {
            $passwordFields = [];
        }

        $inserts = [
            $table => [],
        ];
        foreach ($imports as $import) {
            if ($import['import']) {
                $insertArray = [];
                $update = false;
                foreach ($fields as $key => $field) {
                    if ($field === 'uid') {
                        if (!empty($import[$key])) {
                            $update = true;
                        }
                    } else {
                        $insertArray[$field] = $import[$key];
                    }
                    if (!isset($insertArray['pid'])) {
                        $insertArray['pid'] = $page;
                    }
                }
                foreach ($overrides as $key => $override) {
                    $insertArray[$key] = $override;
                }

                foreach ($passwordFields as $passwordField) {
                    $insertArray[$passwordField] = md5(sha1(microtime()));
                }

                $inserts[$table][$update ? $import['uid'] : uniqid('NEW_', true)] = $insertArray;
            }
        }
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][self::class]['Hooks'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][self::class]['Hooks'] as $_classRef) {
                $hookObj = GeneralUtility::makeInstance($_classRef);
                if (method_exists($hookObj, 'manipulateRelations')) {
                    $hookObj->manipulateRelations($inserts, $table, $this);
                }
            }
        }
        /** @var DataHandler $tce */
        $tce = GeneralUtility::makeInstance(DataHandler::class);
        $tce->start($inserts, []);
        $tce->process_datamap();
        /** @var FlashMessage $message */
        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:success'),
            $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:complete'),
            FlashMessage::OK,
            true
        );
        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->enqueue($message);
        $this->redirect('index');
    }

    /**
     * @param string $fileName
     * @return array
     * @throws Exception
     * @throws NoSuchArgumentException
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    protected function getList(string $fileName): array
    {
        $aList = [];
        $aList['rows'] = 0;
        $aList['cols'] = 0;
        $aList['data'] = [];
        if (is_file($fileName)) {
            $inputFileType = IOFactory::identify($fileName);

            if ($inputFileType === 'Csv') {
                $oReader = new Csv();
                $encoding = (bool)$this->request->getArgument('encoding');

                if ($encoding === true) {
                    $oReader->setInputEncoding('CP1252');
                }
            } else {
                $oReader = IOFactory::createReaderForFile($fileName);
            }

            if ($oReader->canRead($fileName)) {
                //$oReader->getReadDataOnly();
                $xls = $oReader->load($fileName);
                $xls->setActiveSheetIndex(0);
                $sheet = $xls->getActiveSheet();
                $rowI = new RowIterator($sheet, 1);

                $rowcount = 1;
                $colcount = 1;
                foreach ($rowI as $k => $row) {
                    $rowcount++;
                    $cell = new RowCellIterator($sheet, 1, 'A');

                    $cell->setIterateOnlyExistingCells(true);

                    $tmpcolcount = 0;
                    foreach ($cell as $ck => $ce) {
                        $tmpcolcount++;
                    }
                    if ($tmpcolcount > $colcount) {
                        $colcount = $tmpcolcount;
                    }
                }

                $aList['rows'] = $rowcount;
                $aList['cols'] = $colcount;

                for ($y = 1; $y < $rowcount; $y++) {
                    for ($x = 1; $x <= $colcount; $x++) {
                        $aList['data'][$y][$x] = $sheet->getCellByColumnAndRow($x, $y)->getValue();
                    }
                }

                unset($rowI, $sheet, $xls, $oReader);
            }
        }
        return $aList;
    }
}
