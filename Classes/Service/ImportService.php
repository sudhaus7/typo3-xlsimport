<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Service;

use Doctrine\DBAL\Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use SUDHAUS7\Xlsimport\Domain\Dto\ImportJob;
use SUDHAUS7\Xlsimport\Event\ManipulateRelationsEvent;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * @internal for internal usage only and not part of public API. Can change anytime.
 */
final class ImportService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function isImportAllowed(string $table): bool
    {
        return array_key_exists($table, $GLOBALS['TCA']) && is_array($GLOBALS['TCA'][$table]);
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
            $identifier = StringUtility::getUniqueId('NEW');
            foreach ($importJob->getFieldMapping() as $position => $field) {
                if ($field === 'uid') {
                    if (!empty($data[$position])) {
                        $identifier = $data[$position];
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
        if (count($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['SUDHAUS7\\Xlsimport\\Controller\\XlsimportController']['Hooks'] ?? []) > 0) {
            trigger_error(
                sprintf(
                    'Using hooks for manipulating relations is deprecated and will be removed with version 6. Use "%s" instead.',
                    ManipulateRelationsEvent::class
                ),
                E_USER_DEPRECATED
            );
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['SUDHAUS7\\Xlsimport\\Controller\\XlsimportController']['Hooks'] as $_classRef) {
                /** @phpstan-ignore argument.templateType */
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
     * @throws Exception
     * @return array<string, array<int|string, array<string, int>>>
     */
    private function prepareRecordDeleteByJob(ImportJob $job): array
    {
        $db = GeneralUtility::makeInstance(ConnectionPool::class);
        $builder = $db->getQueryBuilderForTable($job->getTable());
        $ids = $builder
            ->select('uid')
            ->from($job->getTable())
            ->where(
                $builder->expr()->eq('pid', $job->getPid())
            )
            ->executeQuery();

        $cmd = [];
        while ($id = $ids->fetchAssociative()) {
            $cmd[$job->getTable()][$id['uid']]['delete'] = 1;
        }

        return $cmd;
    }
}
