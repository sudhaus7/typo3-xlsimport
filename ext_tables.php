<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}
(function() {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'SUDHAUS7.Xlsimport',
        'web',
        'tx_Xlsimport',
        'bottom',
        [
            'Xlsimport' => 'index,upload,import',
        ],
        [
            'access' => 'user,group',
            'icon' => 'EXT:xlsimport/Resources/Public/Icons/xlsdown.svg',
            'labels' => 'LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:module_name',
        ]
    );
})();
