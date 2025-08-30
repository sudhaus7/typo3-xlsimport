<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Tests\Unit\Domain\Dto;

use SUDHAUS7\Xlsimport\Domain\Dto\ImportJob;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ImportJobTest extends UnitTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function createdDtoHasCorrectValues(): void
    {
        $expectedTable = 'test_table';
        $expectedJsonFile = 'test.json';
        $expectedFieldMapping = ['name', 'email'];
        $expectedDatasetMapping = ['user', 'admin'];
        $expectedPasswordOverride = true;
        $expectedPasswordFields = ['password'];
        $expectedDefaultValues = ['field' => 'default'];
        $expectedPid = 1;
        $expectedUserId = 1;
        $expectedDeleteExisting = false;
        $importJob = new ImportJob(
            $expectedTable,
            $expectedJsonFile,
            $expectedFieldMapping,
            $expectedDatasetMapping,
            $expectedPasswordOverride,
            $expectedPasswordFields,
            $expectedDefaultValues,
            $expectedPid,
            $expectedUserId,
            $expectedDeleteExisting
        );
        $this->assertSame($expectedTable, $importJob->getTable());
        $this->assertSame($expectedJsonFile, $importJob->getJsonFile());
        $this->assertSame($expectedFieldMapping, $importJob->getFieldMapping());
        $this->assertSame($expectedDatasetMapping, $importJob->getDatasetMapping());
        $this->assertSame($expectedPasswordOverride, $importJob->isPasswordOverride());
        $this->assertSame($expectedPasswordFields, $importJob->getPasswordFields());
        $this->assertSame($expectedDefaultValues, $importJob->getDefaultValues());
        $this->assertSame($expectedPid, $importJob->getPid());
        $this->assertSame($expectedUserId, $importJob->getUserId());
        $this->assertSame($expectedDeleteExisting, $importJob->isDeleteExisting());
    }
}
