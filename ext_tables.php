<?php

defined('TYPO3') || die();

(static function () {
    $typo3Version = new \TYPO3\CMS\Core\Information\Typo3Version();

    /**
     * @deprecated will be removed with removal of v11 support
     * registration for v12 @see Configuration/Backend/Modules.php
     * Core Change: @see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Feature-96733-NewBackendModuleRegistrationAPI.html
     * @see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Deprecation-96903-DeprecateOldModuleTemplateAPI.html
     */
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
