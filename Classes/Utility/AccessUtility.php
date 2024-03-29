<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class AccessUtility
{
    protected static DataHandler $dataHandler;

    public static function isAllowedTable(string $possibleTable, int $pageId): bool
    {
        return array_key_exists($possibleTable, $GLOBALS['TCA'])
        && self::getBackendUser()->check('tables_modify', $possibleTable)
        && self::checkTableIsAllowedOnPage($possibleTable, $pageId);
    }

    public static function isAllowedField(string $table, string $fieldName): bool
    {
        $allowedFields = BackendUtility::getAllowedFieldsForTable($table);
        // Disallow PID, as the Backend module selects by choosing the site
        // in page tree. Allowing import with PID set will cause side effects.
        // Admins are allowed to do.
        $pidKey = array_search('pid', $allowedFields);
        if ($pidKey && !self::getBackendUser()->isAdmin()) {
            unset($allowedFields[$pidKey]);
        }
        return in_array($fieldName, $allowedFields);
    }
    private static function checkTableIsAllowedOnPage(string $tableName, int $pageId): bool
    {
        $dataHandler = self::$dataHandler ?? GeneralUtility::makeInstance(DataHandler::class);
        return $dataHandler->isTableAllowedForThisPage($pageId, $tableName);
    }

    /**
     * @todo: We have to handle root page separately
     */
    public static function checkAccessOnPage(int $pageId, int $permissions): bool
    {
        $pageRow = BackendUtility::getRecord('pages', $pageId);
        /**
         * if pageId is 0 (Root), getRecord gives null in $pageRow
         * In this case, we want to check, if user is admin.
         * That's why we bypass pageId 0 with null row
         */
        if ($pageRow === null && $pageId !== 0) {
            return false;
        }
        return self::getBackendUser()->doesUserHaveAccess($pageRow, $permissions);
    }

    public static function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
