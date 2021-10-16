<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}
(function () {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Xlsimport',
        'web',
        'tx_Xlsimport',
        'bottom',
        [
            \SUDHAUS7\Xlsimport\Controller\XlsimportController::class => 'index,upload,import',
        ],
        [
            'access' => 'user,group',
            'icon' => 'EXT:xlsimport/Resources/Public/Icons/xlsdown.svg',
            'labels' => 'LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:module_name',
        ]
    );
})();
