<?php

defined('TYPO3_MODE') || die();

(static function () {
    $typo3Version = new \TYPO3\CMS\Core\Information\Typo3Version();

    if ($typo3Version->getMajorVersion() <= 11) {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
            'web',
            'xlsimport',
            'bottom',
            null,
            [
                'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
                'routeTarget' => \SUDHAUS7\Xlsimport\Controller\DataSheetImportController::class . '::handleRequest',
                'access' => 'user,group',
                'name' => 'web_xlsimport',
                'iconIdentifier' => 'mimetypes-excel',
                'labels' => 'LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:module_name',
            ]
        );
    }

})();
