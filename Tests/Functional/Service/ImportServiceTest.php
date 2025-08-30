<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Tests\Functional\Service;

use SUDHAUS7\Xlsimport\Domain\Dto\ImportJob;
use SUDHAUS7\Xlsimport\Service\ImportService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \SUDHAUS7\Xlsimport\Service\ImportService
 */
final class ImportServiceTest extends FunctionalTestCase
{
    protected array $configurationToUseInTestInstance = [];

    /**
     * @var non-empty-string[]
     */
    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
    ];

    /**
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'tests/test_example',
        'sudhaus7/xlsimport',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ImportTestFixture.csv');
        $backendUser =  $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * @test
     */
    public function deleteFlagRemovesData(): void
    {
        $jsonFile = $this->prepareJsonFile();
        $importJob = new ImportJob(
            'tt_address',
            $jsonFile,
            [],
            [],
            false,
            [],
            [],
            1,
            1,
            true
        );

        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->writeImport($importJob);

        $this->assertCSVDataSet(__DIR__ . '/../Fixtures/ImportTestFixtureAfterDelete.csv');
    }

    /**
     * @test
     */
    public function addEntriesWithoutDeletingPrependsEntries(): void
    {
        $jsonFile = $this->prepareJsonFile([
            [
                'Jane',
                'Doe',
                10,
            ],
            [
                'Walther',
                'White',
                11,
            ],
            [
                'Emily',
                'Stone',
                12,
            ],
        ]);
        $importJob = new ImportJob(
            'tt_address',
            $jsonFile,
            [
                0 => 'first_name',
                1 => 'last_name',
            ],
            [
                0 => '1',
                1 => '1',
                2 => '1',
            ],
            false,
            [],
            [],
            1,
            1,
            false
        );
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->prepareImport($importJob);
        $importService->writeImport($importJob);

        $this->assertCSVDataSet(__DIR__ . '/../Fixtures/ImportTestFixtureAddItems.csv');
    }

    /**
     * @test
     */
    public function updateEntriesWithUidGivenUpdates(): void
    {
        $jsonFile = $this->prepareJsonFile([
            [
                'Jane',
                'Doe',
                10,
            ],
            [
                'Walther',
                'White',
                11,
            ],
            [
                'Emily',
                'Stone',
                12,
            ],
        ]);
        $importJob = new ImportJob(
            'tt_address',
            $jsonFile,
            [
                0 => 'first_name',
                1 => 'last_name',
                2 => 'uid',
            ],
            [
                0 => '1',
                1 => '1',
                2 => '1',
            ],
            false,
            [],
            [],
            1,
            1,
            false
        );
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->prepareImport($importJob);
        $importService->writeImport($importJob);

        $this->assertCSVDataSet(__DIR__ . '/../Fixtures/ImportTestFixtureUpdateItems.csv');
    }

    /**
     * @test
     */
    public function addEntriesWithDeleteReplacesEntries(): void
    {
        $jsonFile = $this->prepareJsonFile([
            [
                'Jane',
                'Doe',
                10,
            ],
            [
                'Walther',
                'White',
                11,
            ],
            [
                'Emily',
                'Stone',
                12,
            ],
        ]);
        $importJob = new ImportJob(
            'tt_address',
            $jsonFile,
            [
                0 => 'first_name',
                1 => 'last_name',
            ],
            [
                0 => '1',
                1 => '1',
                2 => '1',
            ],
            false,
            [],
            [],
            1,
            1,
            true
        );
        $importService = GeneralUtility::makeInstance(ImportService::class);
        $importService->prepareImport($importJob);
        $importService->writeImport($importJob);

        $this->assertCSVDataSet(__DIR__ . '/../Fixtures/ImportTestFixtureReplaceItems.csv');
    }

    /**
     * Helper method for handling json files internally
     *
     * @param array<array-key, mixed> $data The JSON data for processing
     * @return string
     * @throws \JsonException
     */
    private function prepareJsonFile(array $data = []): string
    {
        $jsonData = json_encode($data, JSON_THROW_ON_ERROR);
        $tmpJsonFile = GeneralUtility::tempnam('xlsimport', '.json');
        GeneralUtility::writeFile($tmpJsonFile, $jsonData);
        return basename($tmpJsonFile);
    }
}
