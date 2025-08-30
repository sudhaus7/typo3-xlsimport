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
        private readonly string $table,
        private readonly string $jsonFile,
        private readonly array $fieldMapping,
        private readonly array $datasetMapping,
        private readonly bool $passwordOverride,
        private readonly array $passwordFields,
        private readonly array $defaultValues,
        private readonly int $pid,
        private readonly int $userId,
        private readonly bool $deleteExisting,
    ) {}

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
