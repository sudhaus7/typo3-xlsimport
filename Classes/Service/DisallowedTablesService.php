<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Service;

final class DisallowedTablesService
{
    /**
     * @var string[]
     */
    public static $disallowedTcaTables = [
        'sys_log',
        'sys_history',
        'sys_file',
        'sys_language',
        'sys_note',
        'sys_news',
        'sys_file_reference',
    ];
}
