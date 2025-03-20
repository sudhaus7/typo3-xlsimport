<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Controller;

use InvalidArgumentException;
use JsonException;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Worksheet\RowCellIterator;
use PhpOffice\PhpSpreadsheet\Worksheet\RowIterator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SUDHAUS7\Xlsimport\Domain\Dto\ImportJob;
use SUDHAUS7\Xlsimport\Service\ImportService;
use SUDHAUS7\Xlsimport\Utility\AccessUtility;
use TYPO3\CMS\Backend\Form\Exception\AccessDeniedTableModifyException;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Recordlist\Controller\AccessDeniedException;

final class DataSheetImportController
{
    protected ModuleTemplateFactory $templateFactory;

    protected IconFactory $iconFactory;

    protected LanguageService $languageService;

    /**
     * @var StandaloneView
     * Needed for TYPO3 v11 only
     * @deprecated will be removed with removal of v11 support
     */
    protected StandaloneView $view;

    protected Typo3Version $typo3Version;

    public function __construct(
        ModuleTemplateFactory $templateFactory,
        IconFactory $iconFactory,
        Typo3Version $typo3Version,
        LanguageServiceFactory $languageServiceFactory
    ) {
        $this->templateFactory = $templateFactory;
        $this->iconFactory = $iconFactory;
        $this->languageService = $languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER']);
        $this->typo3Version = $typo3Version;
    }

    /**
     * @throws \TYPO3\CMS\Backend\Exception\AccessDeniedException
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $action = (string)($request->getQueryParams()['action'] ?? $request->getParsedBody()['action'] ?? 'index');

        /**
         * Define allowed actions
         */
        if (!in_array($action, ['index', 'import', 'upload'], true)) {
            return new HtmlResponse('Action not allowed', 400);
        }

        $pageId = $this->checkAccessForPage($request);

        $moduleTemplate = $this->templateFactory->create($request);

        if ($this->typo3Version->getMajorVersion() <= 11) {
            $this->view = GeneralUtility::makeInstance(StandaloneView::class);
            $this->view->setPartialRootPaths([
                'EXT:xlsimport/Resources/Private/Partials/',
            ]);
            $this->view->setTemplateRootPaths([
                'EXT:xlsimport/Resources/Private/Templates/Core11/',
            ]);
            $this->view->setLayoutRootPaths([
                'EXT:xlsimport/Resources/Private/Layouts/',
                ]);
            $this->view->getRenderingContext()->setControllerAction($action);
            $this->view->getRenderingContext()->setControllerName('DataSheetImport');
        }

        $this->setDocHeader($action, $moduleTemplate, $pageId);

        /**
         * Call the passed in action
         */
        return $this->{$action . 'Action'}($pageId, $moduleTemplate, $request);
    }

    private function indexAction(
        int $pageId,
        ModuleTemplate $moduleTemplate,
        ServerRequestInterface $request
    ): ResponseInterface {
        $pageTS = BackendUtility::getPagesTSconfig($pageId);
        $tempTables = [];
        if (isset($pageTS['module.']['tx_xlsimport.']['settings.']['allowedTables'])) {
            $tempTables = GeneralUtility::trimExplode(
                ',',
                $pageTS['module.']['tx_xlsimport.']['settings.']['allowedTables']
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
        $allowedTables = $this->checkTableAndAccessAllowed($tempTables, $pageId);

        $assignedValues = [
            'page' => $pageId,
            'allowedTables' => $allowedTables,
        ];

        $moduleTemplate->assignMultiple($assignedValues);
        return $moduleTemplate->renderResponse('DataSheetImport/Index');
    }

    /**
     * @throws \TYPO3\CMS\Core\Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws AccessDeniedTableModifyException
     * @throws Exception
     * @throws RouteNotFoundException
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws JsonException
     */
    private function uploadAction(
        int $pageId,
        ModuleTemplate $moduleTemplate,
        ServerRequestInterface $request
    ): ResponseInterface {
        /** @var array{
         *     deleteRecords?: string,
         *     encoding?: string,
         *     table: non-empty-string,
         *     retry?: string,
         *     jsonFile?: string
         * } $args
         */
        $args = $request->getParsedBody();
        $encoding = (bool)($args['encoding'] ?? false);
        $deleteRecords = (bool)($args['deleteRecords'] ?? false);
        $table = $args['table'];
        $retry = (bool)($args['retry'] ?? false);
        $jsonFile = $args['jsonFile'] ?? '';

        if (!AccessUtility::isAllowedTable($table, $pageId)) {
            throw new AccessDeniedTableModifyException(
                sprintf('You are not allowed to modify table "%s" on page "%d"', $table, $pageId),
                1705161348919
            );
        }

        $files = $request->getUploadedFiles();

        // we didn't receive a retry and the file upload failed. Redirect to index
        if (count($files) !== 1 && !$retry) {
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:error.file.uploadFailed.message'),
                $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:error.file.uploadFailed.header'),
                ContextualFeedbackSeverity::ERROR,
                true
            );
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $messageQueue->enqueue($message);
            $uri = GeneralUtility::makeInstance(UriBuilder::class)
                ->buildUriFromRoute('web_xlsimport', ['id' => $pageId]);
            return new RedirectResponse($uri);
        }

        $file = array_shift($files);

        if (!$retry) {
            $uploadedFile = GeneralUtility::tempnam('xlsimport');
            $file->moveTo($uploadedFile);
            $prepareList = $this->prepareFileForImport($uploadedFile, $encoding);
            GeneralUtility::unlink_tempfile($uploadedFile);
            $jsonData = json_encode($prepareList, JSON_THROW_ON_ERROR);
            $tmpJsonFile = GeneralUtility::tempnam('xlsimport', '.json');
            GeneralUtility::writeFile($tmpJsonFile, $jsonData);
            $jsonFile = basename($tmpJsonFile);
        }

        try {
            $list = $this->loadDataFromJsonFile($jsonFile);
        } catch ( JsonException|FileDoesNotExistException $e) {
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:error.jsonFile.message'),
                $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:error.jsonFile.header'),
                ContextualFeedbackSeverity::WARNING,
                true
            );
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $messageQueue->enqueue($message);
            $uri = GeneralUtility::makeInstance(UriBuilder::class)
                ->buildUriFromRoute('web_xlsimport');
            return new RedirectResponse($uri);
        }

        // load TCA field settings
        [
            'fields' => $fields,
            'hasPasswordField' => $hasPasswordField,
            'passwordFields' => $passwordFields
        ] = $this->prepareTableFromTca($table);

        $assignedValues = [
            'fields' => $fields,
            'deleteRecords' => $deleteRecords,
            'data' => $list,
            'jsonFile' => basename($jsonFile),
            'page' => $pageId,
            'table' => $table,
            'hasPasswordField' => $hasPasswordField,
            'passwordFields' => $passwordFields,
            'addInlineSettings' => [
                'FormEngine' => [
                    'formName' => 'importData',
                ],
            ],
        ];


        /** @var PageRenderer $pageRenderer */
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $javaScriptRenderer = $pageRenderer->getJavaScriptRenderer();
        $javaScriptRenderer->addJavaScriptModuleInstruction(
            JavaScriptModuleInstruction::create('@sudhaus7/xlsimport/import-count.js')
        );
        $moduleTemplate->assignMultiple($assignedValues);
        return $moduleTemplate->renderResponse('DataSheetImport/Upload');
    }

    private function importAction(
        int $pageId,
        ModuleTemplate $moduleTemplate,
        ServerRequestInterface $request
    ): ResponseInterface {
        /** @var array{
         *     table: string,
         *      jsonFile: string,
         *      fields: array<int, string>,
         *      dataset: array<int, string>,
         *      passwordOverride?: string,
         *      passwordFields?: string[],
         *     defaultValues?: array<string, string>,
         *     deleteRecords: string
         * } $args
         */
        $args = $request->getParsedBody();
        $table = $args['table'];
        $jsonFile = $args['jsonFile'];
        $fieldMapping = $args['fields'];
        $datasetMapping = $args['dataset'];
        $deleteRecords = (bool)$args['deleteRecords'];
        $passwordOverride = (bool)($args['passwordOverride'] ?? false);
        $passwordFields = $args['passwordFields'] ?? [];
        // @todo preparation of adding default values to all datasets
        // look at old importAction, array $overrides
        $defaultValues = $args['defaultValues'] ?? [];

        $beUserId = GeneralUtility::makeInstance(Context::class)
            ->getPropertyFromAspect('backend.user', 'id');

        // Unset all fields not assigned to a TCA field.
        // @todo Can we do the check in other place?
        // How can we fake the current Backend User
        // while importing on CLI to get the correct rights?
        // We have to do the check HERE, as we can't really fake
        // the backend user in an async import job and the fields
        // have to be evaluated to avoid errors in DataHandler
        // during import.
        foreach ($fieldMapping as $key => $field) {
            if (empty($field) || !AccessUtility::isAllowedField($table, $field)) {
                unset($fieldMapping[$key]);
            }
        }

        // if fieldlist is empty from now, return data to importer and create flashMessage
        if (empty($fieldMapping)) {
            $message = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:warning.fieldlist.empty.message'),
                $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:warning.fieldlist.empty.header'),
                ContextualFeedbackSeverity::WARNING,
                true
            );
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $messageQueue->enqueue($message);
            $uri = GeneralUtility::makeInstance(UriBuilder::class)
                ->buildUriFromRoute(
                    'web_xlsimport',
                    [
                        'id' => $pageId,
                        'retry' => true,
                        'jsonFile' => $jsonFile,
                        'table' => $table,
                    ]
                );
            return new RedirectResponse($uri);
        }

        $importJob = new ImportJob(
            $table,
            $jsonFile,
            $fieldMapping,
            $datasetMapping,
            $passwordOverride,
            $passwordFields,
            $defaultValues,
            $pageId,
            $beUserId,
            $deleteRecords
        );

        $importService = GeneralUtility::makeInstance(ImportService::class);

        if (!$importService->isImportAllowed($importJob->getTable())) {
            $uri = GeneralUtility::makeInstance(UriBuilder::class)
                ->buildUriFromRoute('web_xlsimport', ['id' => $pageId]);
            return new RedirectResponse($uri);
        }

        $importService->prepareImport($importJob);
        $importService->writeImport($importJob);

        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:success'),
            $this->languageService->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:complete'),
            ContextualFeedbackSeverity::OK,
            true
        );

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->enqueue($message);

        $uri = GeneralUtility::makeInstance(UriBuilder::class)
            ->buildUriFromRoute('web_xlsimport', ['id' => $pageId]);
        return new RedirectResponse($uri);
    }

    /**
     * Check if table has TCA definition
     * and user is allowed to edit table on this page
     * for each table and return array with allowed tables
     *
     * @param string[] $possibleTables
     * @return string[]
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

    private function setDocHeader(
        string $active,
        ModuleTemplate $moduleTemplate,
        int $pageId
    ): void {
        $pageInfo = BackendUtility::readPageAccess(
            $pageId,
            $GLOBALS['BE_USER']->getPagePermsClause(Permission::PAGE_SHOW)
        );
        if ($pageInfo !== false) {
            $moduleTemplate->getDocHeaderComponent()->setMetaInformation($pageInfo);
        }
    }

    /**
     * @return int
     * @throws \TYPO3\CMS\Backend\Exception\AccessDeniedException
     */
    private function checkAccessForPage(ServerRequestInterface $request): int
    {
        $pageIdString = ($request->getQueryParams()['id'] ?? $request->getParsedBody()['id'] ?? 0);

        $pageId = (int)$pageIdString;

        if (!AccessUtility::checkAccessOnPage($pageId, Permission::PAGE_EDIT)) {
            throw new \TYPO3\CMS\Backend\Exception\AccessDeniedException(
                'You are not allowed to manipulate records on this page',
                1705150307860
            );
        }

        return $pageId;
    }

    /**
     * @return array{
     *     fields: array<array-key, mixed>,
     *     hasPasswordField: bool,
     *     passwordFields: string[]
     * }
     */
    private function prepareTableFromTca(string $table): array
    {
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
            $tca = array_merge(
                $uidConfig,
                $pidConfig,
                $GLOBALS['TCA'][$table]['columns']
            );
        }

        $hasPasswordField = false;
        $passwordFields = [];

        foreach ($tca as $field => &$column) {
            if (!AccessUtility::isAllowedField($table, $field)) {
                unset($tca[$field]);
            } else {
                try {
                    $label = $this->languageService->sL($column['label']);
                } catch ( InvalidArgumentException $e) {
                    $label = $column['label'];
                }
                if (empty($label)) {
                    $label = '[' . $field . ']';
                }
                if ($field === 'uid') {
                    $label = $this
                        ->languageService
                        ->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:uid');
                }
                if ($field === 'pid') {
                    $label = $this
                        ->languageService
                        ->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:pid');
                }
                $column['label'] = $label;

                if (
                    isset($column['config']['eval'])
                    && in_array(
                        'password',
                        GeneralUtility::trimExplode(
                            ',',
                            $column['config']['eval']
                        )
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

        return [
            'fields' => $fields,
            'hasPasswordField' => $hasPasswordField,
            'passwordFields' => $passwordFields,
        ];
    }

    /**
     * @return array<array-key, mixed>
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
     * @throws FileDoesNotExistException
     * @throws JsonException
     * @return array<array-key, mixed>
     */
    private function loadDataFromJsonFile(string $jsonFileName): array
    {
        $jsonFile = $this->buildJsonFileName($jsonFileName);
        if (!is_file($jsonFile)) {
            throw new FileDoesNotExistException(
                'The JSON file saved temporarily was not found',
                1705162066162
            );
        }
        return json_decode(file_get_contents($jsonFile) ?: '', true, 512, JSON_THROW_ON_ERROR);
    }

    private function buildJsonFileName(string $jsonFileName): string
    {
        $temporaryPath = Environment::getVarPath() . '/transient/';
        return sprintf('%s%s', $temporaryPath, $jsonFileName);
    }
}
