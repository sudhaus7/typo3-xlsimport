<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Service;

use Doctrine\DBAL\DBALException;
use Psr\EventDispatcher\EventDispatcherInterface;
use SUDHAUS7\Xlsimport\Controller\XlsimportController;
use SUDHAUS7\Xlsimport\Domain\Dto\ImportJob;
use SUDHAUS7\Xlsimport\Event\ManipulateRelationsEvent;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * @internal This class is for internal usage and no Public API
 * Code can change in future versions
 */
final class ImportService
{
    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function isImportAllowed(string $table): bool
    {
        return array_key_exists($table, $GLOBALS['TCA']);
    }

    /**
     * @throws \JsonException
     * @throws FileDoesNotExistException
     */
    public function prepareImport(ImportJob $importJob): void
    {
        $insertData = [
            $importJob->getTable() => [],
        ];

        $importData = $this->loadDataFromJsonFile($importJob->getJsonFile());

        foreach ($importData as $importPosition => $data) {
            // cleanup array from not importable data lines
            if (
                !array_key_exists($importPosition, $importJob->getDatasetMapping())
                || $importJob->getDatasetMapping()[$importPosition] !== '1'
            ) {
                unset($importData[$importPosition]);
                continue;
            }
            $insertArray = [];
            $update = false;
            foreach ($importJob->getFieldMapping() as $position => $field) {
                if ($field === 'uid') {
                    if (!empty($data[$position])) {
                        $update = true;
                    }
                } else {
                    $insertArray[$field] = $data[$position];
                }
            }
            if (!isset($insertArray['pid'])) {
                $insertArray['pid'] = $importJob->getPid();
            }
            foreach ($importJob->getDefaultValues() as $fieldName => $defaultValue) {
                $insertArray[$fieldName] = $defaultValue;
            }

            foreach ($importJob->getPasswordFields() as $passwordField) {
                $insertArray[$passwordField] = md5(sha1(microtime()));
            }

            $identifier = $update ? $data['uid'] : StringUtility::getUniqueId('NEW');

            $insertData[$importJob->getTable()][$identifier] = $insertArray;
        }

        /** @deprecated Function call will be removed in 6.0 */
        $insertData = $this->callHook($insertData, $importJob->getTable());

        /** @var ManipulateRelationsEvent $manipulateRelationsEvent */
        $manipulateRelationsEvent = $this->eventDispatcher->dispatch(
            new ManipulateRelationsEvent($importJob->getTable(), $insertData)
        );

        $importJob->setData($manipulateRelationsEvent->getInsertData());
    }

    public function writeImport(ImportJob $importJob): void
    {
        $cmd = [];
        if ($importJob->isDeleteExisting()) {
            $cmd = $this->prepareRecordDeletebyJob($importJob);
        }
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($importJob->getData(), $cmd);
        $dataHandler->process_cmdmap();
        $dataHandler->process_datamap();
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $insertData
     * @param string $table
     * @return array<string, array<int|string, array<string, mixed>>>
     * @deprecated Using Hooks is deprecated since 5.0. Use Events instead
     */
    private function callHook(array $insertData, string $table): array
    {
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['SUDHAUS7\\Xlsimport\\Controller\\XlsimportController']['Hooks'])) {
            trigger_deprecation(
                'xlsimport',
                '5.0',
                sprintf(
                    'Using hooks for manipulating relations is deprecated and will be removed with version 6. Use "%s" instead.',
                    ManipulateRelationsEvent::class
                ),
            );
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['SUDHAUS7\\Xlsimport\\Controller\\XlsimportController']['Hooks'] as $_classRef) {
                $hookObj = GeneralUtility::makeInstance($_classRef);
                if (method_exists($hookObj, 'manipulateRelations')) {
                    $hookObj->manipulateRelations($insertData, $table, $this);
                }
            }
        }
        return $insertData;
    }

    /**
     * @throws FileDoesNotExistException
     * @throws \JsonException
     * @return array<array-key, mixed>
     */
    private function loadDataFromJsonFile(string $jsonFileName): array
    {
        $jsonFile = $this->buildJsonFileName($jsonFileName);
        if (!is_file($jsonFile)) {
            throw new FileDoesNotExistException(
                'The JSON file saved temporarily was not found',
                1705162066162
            );
        }
        return json_decode(file_get_contents($jsonFile) ?: '', true, 512, JSON_THROW_ON_ERROR);
    }

    private function buildJsonFileName(string $jsonFileName): string
    {
        $temporaryPath = Environment::getVarPath() . '/transient/';
        return sprintf('%s%s', $temporaryPath, $jsonFileName);
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws DBALException
     * @return array<string, array<int|string, array<string, int>>>
     */
    private function prepareRecordDeleteByJob(ImportJob $job): array
    {
        $db = GeneralUtility::makeInstance(ConnectionPool::class);
        $builder = $db->getQueryBuilderForTable($job->getTable());
        $ids = $builder->select('uid')->from($job->getTable())->where(
            $builder->expr()->eq('pid', $job->getPid())
        )->executeQuery()->fetchAllAssociative();

        $cmd = [];
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $cmd[$job->getTable()][$id['uid']]['delete'] = 1;
            }
        }

        return $cmd;
    }
}
