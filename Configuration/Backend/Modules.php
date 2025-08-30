<?php

declare(strict_types=1);

use SUDHAUS7\Xlsimport\Controller\DataSheetImportController;

/**
 * registration for v11 @see ext_tables.php
 * Core Change: @see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Feature-96733-NewBackendModuleRegistrationAPI.html
 * @see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Deprecation-96903-DeprecateOldModuleTemplateAPI.html
 */
return [
    'web_xlsimport' => [
        'parent' => 'web',
        'position' => ['bottom'],
        'access' => 'user',
        'workspaces' => '*',
        'path' => '/module/web/xlsimport',
        'labels' => [
            'title' => 'LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:module_name',
            'description' => 'LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:description',
            'shortDescription' => 'LLL:EXT:xlsimport/Resources/Private/Language/locallang.xlf:shortDescription',
        ],
        'iconIdentifier' => 'mimetypes-excel',
        'navigationComponent' => '@typo3/backend/tree/page-tree-element',
        'routes' => [
            '_default' => [
                'target' => DataSheetImportController::class . '::handleRequest',
            ],
        ],
    ],
];
