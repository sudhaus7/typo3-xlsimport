<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Domain\Dto;

/**
 * Class ImportJob
 *
 * Represents a job for importing data into a table.
 *
 * @internal only for usage within this extension, no public API
 */
final class ImportJob
{
    private string $table;

    private string $jsonFile;

    /**
     * @var array<int, string>
     */
    private array $fieldMapping;

    /**
     * @var array<int, string>
     */
    private array $datasetMapping;

    private bool $passwordOverride;

    /**
     * @var string[]
     */
    private array $passwordFields;

    /**
     * @var array<string, string>
     */
    private array $defaultValues;

    private int $pid;

    private int $userId;

    private bool $deleteExisting;

    /**
     * @var array<string, array<int|string, array<string, mixed>>>
     */
    private array $data = [];

    /**
     * ImportJob constructor.
     *
     * @param array<int, string> $fieldMapping
     * @param array<int, string> $datasetMapping
     * @param string[] $passwordFields
     * @param array<string, string> $defaultValues
     */
    public function __construct(
        string $table,
        string $jsonFile,
        array $fieldMapping,
        array $datasetMapping,
        bool $passwordOverride,
        array $passwordFields,
        array $defaultValues,
        int $pid,
        int $userId,
        bool $deleteExisting
    ) {
        $this->table = $table;
        $this->jsonFile = $jsonFile;
        $this->fieldMapping = $fieldMapping;
        $this->datasetMapping = $datasetMapping;
        $this->passwordOverride = $passwordOverride;
        $this->passwordFields = $passwordFields;
        $this->defaultValues = $defaultValues;
        $this->pid = $pid;
        $this->userId = $userId;
        $this->deleteExisting = $deleteExisting;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getJsonFile(): string
    {
        return $this->jsonFile;
    }

    /**
     * @return array<int, string>
     */
    public function getFieldMapping(): array
    {
        return $this->fieldMapping;
    }

    /**
     * @return array<int, string>
     */
    public function getDatasetMapping(): array
    {
        return $this->datasetMapping;
    }

    public function isPasswordOverride(): bool
    {
        return $this->passwordOverride;
    }

    /**
     * @return string[]
     */
    public function getPasswordFields(): array
    {
        return $this->passwordFields;
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultValues(): array
    {
        return $this->defaultValues;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function isDeleteExisting(): bool
    {
        return $this->deleteExisting;
    }

    /**
     * @return array<string, array<int|string, array<string, mixed>>>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
