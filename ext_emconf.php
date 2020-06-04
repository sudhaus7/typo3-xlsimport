<?php

$EM_CONF[$_EXTKEY] = [
	'title' => '(Sudhaus7) XLS Importer',
	'description' => 'A simple importer for table shown content in the database',
	'category' => 'module',
	'version' => '1.0.5',
	'state' => 'stable',
	'uploadfolder' => 1,
	'clearcacheonload' => 0,
	'author' => 'Markus Hofmann, Frank Berger & Daniel Simon',
	'author_email' => 'mhofmann@sudhaus7.de',
	'author_company' => 'Sudhaus7, ein Label der B-Factor GmbH',
	'constraints' => [
		'depends' => [
		    'typo3' => '7.6.0-10.4.99'
        ],
		'conflicts' => [
        ],
		'suggests' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'SUDHAUS7\\Xlsimport\\' => 'Classes',
            'ZipStream\\' => 'vendor/maennchen/zipstream-php/src',
            'Symfony\\Polyfill\\Mbstring\\' => 'vendor/symfony/polyfill-mbstring',
            'Psr\\SimpleCache\\' => 'vendor/psr/simple-cache/src',
            'Psr\\Http\\Message\\' => 'vendor/psr/http-message/src',
            'PhpOffice\\PhpSpreadsheet\\' => 'vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet',
            'MyCLabs\\Enum\\' => 'vendor/myclabs/php-enum/src',
            'Matrix\\' => 'vendor/markbaker/matrix/classes/src',
            'Complex\\' => 'vendor/markbaker/complex/classes/src',
        ]
    ],
];

