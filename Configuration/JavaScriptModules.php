<?php

declare(strict_types=1);

return [
    'dependencies' => ['backend'],
    'imports' => [
        '@sudhaus7/xlsimport/' => [
            'path' => 'EXT:xlsimport/Resources/Public/JavaScript/',
            'exclude' => [
                'EXT:xlsimport/Resources/Public/JavaScript/Importer.js',
            ],
        ],
    ],
];
