<?php

$EM_CONF[$_EXTKEY] = [
    'title' => '(Sudhaus7) XLS Importer',
    'description' => 'A simple importer to import data into the database',
    'category' => 'module',
    'version' => '5.0.5',
    'state' => 'stable',
    'author' => 'Frank Berger, Markus Hofmann & Daniel Simon',
    'author_email' => 'fberger@sudhaus7.de',
    'author_company' => 'Sudhaus7, a label of B-Factor GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-12.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'SUDHAUS7\\Xlsimport\\' => 'Classes',
            'ZipStream\\' => 'Resources/Private/Php/maennchen/zipstream-php/src',
            'Symfony\\Polyfill\\Mbstring\\' => 'Resources/Private/Php/symfony/polyfill-mbstring',
            'Psr\\SimpleCache\\' => 'Resources/Private/Php/psr/simple-cache/src',
            'Psr\\Http\\Message\\' => 'Resources/Private/Php/psr/http-message/src',
            'PhpOffice\\PhpSpreadsheet\\' => 'Resources/Private/Php/phpoffice/phpspreadsheet/src/PhpSpreadsheet',
            'MyCLabs\\Enum\\' => 'Resources/Private/Php/myclabs/php-enum/src',
            'Matrix\\' => 'Resources/Private/Php/markbaker/matrix/classes/src',
            'Complex\\' => 'Resources/Private/Php/markbaker/complex/classes/src',
        ],
    ],
];
