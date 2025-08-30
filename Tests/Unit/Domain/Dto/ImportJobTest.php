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
        self::assertSame($expectedTable, $importJob->getTable());
        self::assertSame($expectedJsonFile, $importJob->getJsonFile());
        self::assertSame($expectedFieldMapping, $importJob->getFieldMapping());
        self::assertSame($expectedDatasetMapping, $importJob->getDatasetMapping());
        self::assertSame($expectedPasswordOverride, $importJob->isPasswordOverride());
        self::assertSame($expectedPasswordFields, $importJob->getPasswordFields());
        self::assertSame($expectedDefaultValues, $importJob->getDefaultValues());
        self::assertSame($expectedPid, $importJob->getPid());
        self::assertSame($expectedUserId, $importJob->getUserId());
        self::assertSame($expectedDeleteExisting, $importJob->isDeleteExisting());
    }
}
