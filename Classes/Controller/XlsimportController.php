<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: markus
 * Date: 06.02.18
 * Time: 11:28
 */

namespace SUDHAUS7\Xlsimport\Controller;

use TYPO3\CMS\Core\Page\PageRenderer;
use PhpOffice\PhpSpreadsheet\Worksheet\RowCellIterator;
use PhpOffice\PhpSpreadsheet\Worksheet\RowIterator;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\File\BasicFileUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use PhpOffice\PhpSpreadsheet\IOFactory;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Class XlsimportController
 * @package SUDHAUS7\Xlsimport\Controller
 */
class XlsimportController  extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * Backend Template Container
     *
     * @var string
     */
    protected $defaultViewObjectName = \TYPO3\CMS\Backend\View\BackendTemplateView::class;
    /**
     * @var LanguageService
     */
    protected $languageService;

    /**
     * XlsimportController constructor.
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function initializeObject()
    {
        $pageRenderer = $this->getPageRenderer();
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Xlsimport/Importer');

        if (!is_object($this->objectManager)) {
            $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        }
        $this->languageService = $this->objectManager->get(LanguageService::class);
    }

    public function indexAction() {
        $page = GeneralUtility::_GET('id');
        $tempTables = GeneralUtility::trimExplode(',',$this->settings['allowedTables']);
        $allowedTables = [];
        foreach ($tempTables as $tempTable) {
            if (array_key_exists($tempTable,$GLOBALS['TCA'])) {
                $label = $GLOBALS['TCA'][$tempTable]['ctrl']['title'];
                $allowedTables[$tempTable] = $this->getLang()->sL($label) ? $this->getLang()->sL($label) : $label;
            }
        }
        $assignedValues = [
            'page' => $page,
            'allowedTables' => $allowedTables
        ];
        $this->view->assignMultiple($assignedValues);
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function uploadAction() {
        $page =  GeneralUtility::_GET('id');
        if (!$page) {
            $this->redirect('index');
            exit;
        }
        $deleteOldRecords = (bool)$this->request->getArgument('deleteRecords');
        $file = $this->request->getArgument('file');
        $table = $this->request->getArgument('table');
        if ($deleteOldRecords) {
            /** @var DataHandler $tce */
            $tce = GeneralUtility::makeInstance(DataHandler::class);
            $ids = [];
            if (floatval(TYPO3_branch) < 8.7) {
                /** @var DatabaseConnection $db */
                $db = $GLOBALS['TYPO3_DB'];
                $ids = $db->exec_SELECTgetRows('*',$table,'pid='.$page);
            } else {
                $db = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class);
                $builder = $db->getQueryBuilderForTable($table);
                $ids = $builder->select('*')->from($table)->where(
                    $builder->expr()->eq('pid',$page)
                )->execute()->fetchAll();
            }
            $cmd = [];
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $cmd[$table][$id['uid']]['delete'] = 1;
                }
                $tce->start([],$cmd);
                $tce->process_datamap();
                $tce->process_cmdmap();
            }
        }
        $basicFileFunctions = $this->objectManager->get(BasicFileUtility::class);
        $newFile = $basicFileFunctions->getUniqueName(
            $file['name'],
            \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('uploads/tx_xlsimport/')
        );
        GeneralUtility::upload_copy_move($file['tmp_name'],$newFile);
        $list = $this->getList($newFile);
        $tca = $GLOBALS['TCA'][$table]['columns'];

        foreach ($tca as &$column) {
            try {
                $label = $this->languageService->sL($column['label']);
            } catch (\InvalidArgumentException $e) {
                $label = $column['label'];
            }
            $column['label'] = $label;
        }

        $assignedValues = [
            'fields' => $tca,
            'data' => $list['data'],
            'page' =>  $page,
            'table' => $table
        ];
        $this->view->assignMultiple($assignedValues);
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function importAction() {
        $page =  GeneralUtility::_GET('id');
        $table = $this->request->getArgument('table');
        if (!$page || !$table || !array_key_exists($table,$GLOBALS['TCA'])) {
            $this->redirect('index');
            exit;
        }
        /** @var array $fields */
        $fields = $this->request->getArgument('fields');
        /** @var array $imports */
        $imports = json_decode($this->request->getArgument('dataset'),true);
        $a = [];
        foreach ($imports as $import) {
            $s = sprintf('%s=%s',$import['name'],$import['value']);
            $temp = [];
            parse_str($s,$temp);
            foreach ($temp['tx_xlsimport_web_xlsimporttxxlsimport']['dataset'] as $k => $v) {
                foreach ($v as $key => $value) {
                    $a[$k][$key] = $value;
                }
            }
        }
        $imports = $a;
        $disallowedFields = [
            'pid','t3ver_oid','tstamp','crdate','cruser_id','hidden','deleted',
            't3ver_id','t3ver_wsid','t3ver_label','t3ver_state','t3ver_stage','t3ver_count',
            't3ver_tstamp','t3ver_move_id','t3_origuid'
        ];
        foreach ($fields as $key => $field) {
            if (empty($field)) {
                unset($fields[$key]);
            }
            if (in_array($field,$disallowedFields)) {
                unset($fields[$key]);
            }
        }
        $inserts = [
            $table => []
        ];
        foreach ($imports as $import) {
            if ($import['import']) {
                $insertArray = [];
                $update = false;
                foreach ($fields as $key => $field) {
                    if ($field == 'uid') {
                        if (!empty($import[$key])) {
                            $update = true;
                        }
                    } else {
                        $insertArray[$field] = $import[$key];
                    }
                    if (!$update) {
                        $insertArray['pid'] = $page;
                    }
                }
                $inserts[$table][$update ? $import['uid'] : uniqid('NEW_')] = $insertArray;
            }
        }
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][self::class]['Hooks'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][self::class]['Hooks'] as $_classRef) {
                $hookObj = GeneralUtility::makeInstance($_classRef);
                if (method_exists($hookObj,'manipulateRelations')) {
                    $hookObj->manipulateRelations($inserts,$table,$this);
                }
            }
        }
        /** @var DataHandler $tce */
        $tce = GeneralUtility::makeInstance(DataHandler::class);
        $tce->start($inserts,[]);
        $tce->process_datamap();
        $lang = $this->getLang();
        /** @var FlashMessage $message */
        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $lang->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:success'),
            $lang->sL('LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:complete'),
            FlashMessage::OK,
            true
        );
        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = $this->objectManager->get(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->enqueue($message);
        $this->redirect('index');
    }

    /**
     * @param $filename
     * @return array
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function getList($filename) {
        $aList = [];
        $aList['rows'] = 0;
        $aList['cols'] = 0;
        $aList['data'] = [];
        if (is_file($filename)) {


            $oReader = IOFactory::createReaderForFile($filename);
            if (is_object($oReader) && $oReader->canRead($filename)) {
                //$oReader->getReadDataOnly();
                $xls = $oReader->load($filename);
                $xls->setActiveSheetIndex(0);
                $sheet = $xls->getActiveSheet();
                $rowI = new RowIterator($sheet,1);

                $rowcount = 1;
                $colcount = 1;
                foreach($rowI as $k=>$row) {
                    $rowcount++;
                    $cell = new RowCellIterator($sheet,1,'A');

                    $cell->setIterateOnlyExistingCells(true);

                    $tmpcolcount = 0;
                    foreach ($cell as $ck=>$ce) {
                        $tmpcolcount++;
                    }
                    if ($tmpcolcount > $colcount) $colcount = $tmpcolcount;
                }

                $aList['rows'] = $rowcount;
                $aList['cols'] = $colcount;

                for ($y=1;$y<$rowcount;$y++) {

                    for($x=1;$x<=$colcount;$x++) {
                        $aList['data'][$y][$x] = $sheet->getCellByColumnAndRow($x,$y)->getValue();
                    }
                }

                unset($rowI);
                unset($sheet);
                unset($xls);
                unset($oReader);

            }
        }
        return ($aList);
    }

    /**
     * @return LanguageService
     */
    protected function getLang() {
        return $GLOBALS['LANG'];
    }

    /**
     * @return object|PageRenderer
     */
    protected function getPageRenderer()
    {
        return GeneralUtility::makeInstance(PageRenderer::class);
    }
}