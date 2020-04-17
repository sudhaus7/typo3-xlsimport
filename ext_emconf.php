<?php

$EM_CONF[$_EXTKEY] = [
	'title' => '(Sudhaus7) XLS Importer',
	'description' => 'A simple importer for table shown content in the database',
	'category' => 'module',
	'version' => '1.0.2',
	'state' => 'stable',
	'uploadfolder' => 1,
	'clearcacheonload' => 0,
	'author' => 'Markus Hofmann, Frank Berger & Daniel Simon',
	'author_email' => 'mhofmann@sudhaus7.de',
	'author_company' => 'Sudhaus7, ein Label der B-Factor GmbH',
	'constraints' => [
		'depends' => [
		    'typo3' => '6.2.0-10.4.99'
        ],
		'conflicts' => [
        ],
		'suggests' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'SUDHAUS7\\Xlsimport\\' => 'Classes'
        ]
    ],
];

