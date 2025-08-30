<?php

/** @phpstan-ignore variable.undefined */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Functional test fixture extension',
    'description' => 'Acts as a example extension for functional tests',
    'category' => 'plugin',
    'author' => 'Frank Berger, Markus Hofmann & Daniel Simon',
    'author_email' => 'fberger@sudhaus7.de',
    'author_company' => 'Sudhaus7, a label of B-Factor GmbH',
    'version' => '1.0.0',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
