<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Utility;

use function is_array;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\PageDoktypeRegistry;
use TYPO3\CMS\Core\Schema\Capability\RootLevelCapability;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\Exception\UndefinedSchemaException;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
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

    /**
     * Same as DataHandler::isTableAllowedForThisPage
     *
     * the signature for DataHandler::isTableAllowedForThisPage has changed
     * from public to protected, so in order to have this functionality here
     * the algorithm has been copied here
     *
     * @see DataHandler::isTableAllowedForThisPage
     * @param string $tableName
     * @param int $pageId
     *
     * @return bool
     * @throws UndefinedSchemaException
     */
    private static function checkTableIsAllowedOnPage(string $tableName, int $pageId): bool
    {
        $tcaSchemeFactory = GeneralUtility::makeInstance(TcaSchemaFactory::class);

        $dataHandler = self::$dataHandler ?? GeneralUtility::makeInstance(DataHandler::class);
        $schema = $tcaSchemeFactory->get($tableName);
        /** @var RootLevelCapability $rootLevelCapability */
        $rootLevelCapability = $schema->getCapability(TcaSchemaCapability::RestrictionRootLevel);

        if ($tableName !== 'pages' && $rootLevelCapability->getRootLevelType() !== RootLevelCapability::TYPE_BOTH && ($rootLevelCapability->getRootLevelType() xor !$tableName)) {
            return false;
        }
        $allowed = false;
        // Check root-level
        if (!$pageId) {
            if ($dataHandler->admin || $rootLevelCapability->shallIgnoreRootLevelRestriction()) {
                $allowed = true;
            }
            return $allowed;
        }
        // Check non-root-level
        $page = BackendUtility::getRecord('pages', $pageId);
        if (is_array($page) && isset($page['doktype'])) {
            return GeneralUtility::makeInstance(PageDoktypeRegistry::class)
                                 ->isRecordTypeAllowedForDoktype($tableName, (int)$page['doktype']);
        }
        return false;
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
        /** @phpstan-ignore argument.type */
        return self::getBackendUser()->doesUserHaveAccess($pageRow, $permissions);
    }

    public static function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
