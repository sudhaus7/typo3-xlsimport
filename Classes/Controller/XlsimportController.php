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
use Psr\Http\Message\ResponseInterface;
use SUDHAUS7\Xlsimport\Property\TypeConverter\UploadedFileConverter;
use SUDHAUS7\Xlsimport\Utility\AccessUtility;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Recordlist\Controller\AccessDeniedException;

/**
 * Class XlsimportController
 */
class XlsimportController extends ActionController
{
    protected LanguageService $languageService;

    protected ResourceFactory $resourceFactory;

    protected ModuleTemplateFactory $moduleTemplateFactory;

    public function __construct(
        ResourceFactory $resourceFactory,
        LanguageService $languageService,
        ModuleTemplateFactory $moduleTemplateFactory
    ) {
        $this->resourceFactory = $resourceFactory;
        $this->languageService = $languageService;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws AccessDeniedException
     */
    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $page = (int)GeneralUtility::_GET('id');

        if (!AccessUtility::checkAccessOnPage($page, Permission::PAGE_EDIT)) {
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
        $moduleTemplate
            ->setTitle('S7 XLS Importer')
            ->setModuleName('Importer')
            ->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    public function initializeUploadAction(): void
    {
        if ($this->arguments->hasArgument('file')) {
            $this->arguments
                ->getArgument('file')
                ->getPropertyMappingConfiguration()
                ->setTypeConverter(new UploadedFileConverter());
        }
    }

    /**
     * @throws Exception
     * @throws NoSuchArgumentException
     * @throws StopActionException
     * @throws DBALException
     * @throws AccessDeniedException
     */
    public function uploadAction(
        string $table,
        UploadedFile $file = null,
        bool $deleteRecords = false,
        bool $encoding = false,
        bool $retry = false,
        string $jsonData = ''
    ): ResponseInterface {
        // we didn't receive a retry and the file upload failed. Redirect to index
        if ($file === null && !$retry) {
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:error.file.uploadFailed.message'),
                $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:error.file.uploadFailed.header'),
                FlashMessage::ERROR,
                true
            );
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $messageQueue->enqueue($message);
            $this->redirect('index');
        }
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $page = (int)GeneralUtility::_GET('id');

        if (!AccessUtility::checkAccessOnPage($page, Permission::PAGE_EDIT)) {
            throw new AccessDeniedException(
                'You are not allowed on editing this page',
                1676074682764
            );
        }

        if ($deleteRecords && AccessUtility::isAllowedTable($table, $page)) {
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

        $fields = [];

        foreach ($tca as $field => $config) {
            $fields[] = [
                'type' => $field,
                'label' => $config['label'],
            ];
        }

        if (!$retry) {
            $uploadedFile = GeneralUtility::tempnam('xlsimport');
            $file->moveTo($uploadedFile);
            $list = $this->prepareFileForImport($uploadedFile, $encoding);
            GeneralUtility::unlink_tempfile($uploadedFile);
            $jsonData = json_encode($list);
            $fileName = GeneralUtility::tempnam('xlsimport', '.json');
            GeneralUtility::writeFile($fileName, $jsonData);
        } else {
            $list = $this->loadDataFromJsonFile($jsonData);
        }

        $assignedValues = [
            'fields' => $fields,
            'data' => $list,
            'jsonData' => basename($fileName ?? $jsonData),
            'page' => $page,
            'table' => $table,
            'hasPasswordField' => $hasPasswordField,
            'passwordFields' => $passwordFields,
            'addInlineSettings' => [
                'FormEngine' => [
                    'formName' => 'importData',
                ],
            ],
        ];
        $this->view->assignMultiple($assignedValues);
        $moduleTemplate
            ->setTitle('S7 XLS Import', LocalizationUtility::translate('prepare', 'xlsimport'))
            ->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @throws NoSuchArgumentException
     * @throws StopActionException
     * @throws JsonException
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function importAction(
        string $table,
        string $jsonData,
        array $fields,
        array $dataset,
        bool $passwordOverride = false,
        array $passwordFields = []
    ): void {
        $page = GeneralUtility::_GET('id');
        if (!$table || !array_key_exists($table, $GLOBALS['TCA'])) {
            $this->redirect('index');
            exit;
        }
        $overrides = [];
        if ($this->request->hasArgument('overrides')) {
            $overrides = $this->request->getArgument('overrides');
        }

        // load saved data from JSON
        $data = $this->loadDataFromJsonFile($jsonData);

        // remove all data, which should not be imported
        foreach ($data as $index => $value) {
            if (!array_key_exists($index, $dataset) || $dataset[$index] != 1) {
                unset($data[$index]);
            }
        }
        $imports = array_values($data);

        // unset all fields not assigned to a TCA field
        foreach ($fields as $key => $field) {
            if (empty($field)) {
                unset($fields[$key]);
            }
            if (!AccessUtility::isAllowedField($table, $field)) {
                unset($fields[$key]);
            }
        }

        // if fieldlist is empty from now, return data to importer and create flashMessage
        if (empty($fields)) {
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:warning.fieldlist.empty.message'),
                $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:warning.fieldlist.empty.header'),
                FlashMessage::WARNING,
                true
            );
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $messageQueue->enqueue($message);
            $this->redirect(
                'upload',
                null,
                null,
                [
                    'retry' => true,
                    'jsonData' => $jsonData,
                    'table' => $table,
                ]
            );
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
            }
            if (!isset($insertArray['pid'])) {
                $insertArray['pid'] = $page;
            }
            foreach ($overrides as $key => $override) {
                $insertArray[$key] = $override;
            }

            foreach ($passwordFields as $passwordField) {
                $insertArray[$passwordField] = md5(sha1(microtime()));
            }

            $inserts[$table][$update ? $import['uid'] : uniqid('NEW_', true)] = $insertArray;
        }
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][self::class]['Hooks'] ?? false)) {
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

        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:success'),
            $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:complete'),
            FlashMessage::OK,
            true
        );

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->enqueue($message);
        GeneralUtility::unlink_tempfile($this->buildJsonFileName($jsonData));
        $this->redirect('index');
    }

    /**
     * @return array
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    protected function prepareFileForImport(
        string $fileName,
        bool $encoding = false
    ): array {
        $aList = [];
        if (is_file($fileName)) {
            $inputFileType = IOFactory::identify($fileName);

            if ($inputFileType === 'Csv') {
                $oReader = new Csv();
                if ($encoding === true) {
                    $oReader->setInputEncoding('CP1252');
                }
            } else {
                $oReader = IOFactory::createReaderForFile($fileName);
            }

            if ($oReader->canRead($fileName)) {
                $xls = $oReader->load($fileName);
                $xls->setActiveSheetIndex(0);
                $sheet = $xls->getActiveSheet();
                $rowI = new RowIterator($sheet, 1);

                $rowcount = 1;
                $colcount = 1;
                foreach ($rowI as $row) {
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

                for ($y = 1; $y < $rowcount; $y++) {
                    for ($x = 1; $x <= $colcount; $x++) {
                        $aList[$y][$x] = $sheet->getCellByColumnAndRow($x, $y)->getValue();
                    }
                }

                unset($rowI, $sheet, $xls, $oReader);
            }
        }
        return $aList;
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
                $allowedTables[$possibleTable] = $this->languageService->sL($label) ?: $label;
            }
        }

        return $allowedTables;
    }

    private function loadDataFromJsonFile(string $jsonFileName): ?array
    {
        $jsonFile = $this->buildJsonFileName($jsonFileName);
        if (is_file($jsonFile)) {
            return json_decode(file_get_contents($jsonFile), true);
        }
        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:error.jsonFile.message'),
            $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:error.jsonFile.header'),
            FlashMessage::WARNING,
            true
        );
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->enqueue($message);
        $this->redirect('index');
        return null;
    }

    private function buildJsonFileName(string $jsonFileName): string
    {
        $temporaryPath = Environment::getVarPath() . '/transient/';
        return sprintf('%s%s', $temporaryPath, $jsonFileName);
    }
}
