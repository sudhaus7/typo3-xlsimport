<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Event;

/**
 * ManipulateRelationsEvent adds the possibility for manipulating relations
 * within the data structure. Be aware of what you are doing here, as the data
 * array will not be checked anymore before processed in DataHandler.
 * For a clean DataHandler structure
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Typo3CoreEngine/Database/Index.html#data-array
 */
final class ManipulateRelationsEvent
{
    /**
     * The currently processed table for inserting/updating records
     * @var string
     */
    private string $table;

    /**
     * The insertData array for DataHandler usage
     * @see https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Typo3CoreEngine/Database/Index.html#data-array
     * @var array<string, array<int|string, array<string, mixed>>>
     */
    private array $insertData;

    /**
     * @param string $table
     * @param array<string, array<int|string, array<string, mixed>>> $insertData
     */
    public function __construct(string $table, array $insertData)
    {
        $this->table = $table;
        $this->insertData = $insertData;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return array<string, array<int|string, array<string, mixed>>>
     */
    public function getInsertData(): array
    {
        return $this->insertData;
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $insertData
     */
    public function setInsertData(array $insertData): void
    {
        $this->insertData = $insertData;
    }
}
