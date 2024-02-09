<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Tests\Functional\Service;

use SUDHAUS7\Xlsimport\Domain\Dto\ImportJob;
use SUDHAUS7\Xlsimport\Service\ImportService;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * @covers \SUDHAUS7\Xlsimport\Service\ImportService
 */
final class ImportServiceTest extends FunctionalTestCase
{
    /**
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'friendsoftypo3/tt-address',
        'sudhaus7/xlsimport',
    ];

    /**
     * @var array<array-key, array<int|string>>
     */
    protected array $importData = [
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
    ];

    protected function setUp(): void
    {
        $this->configurationToUseInTestInstance = array_merge(
            $this->configurationToUseInTestInstance,
            require __DIR__ . '/../Fixtures/LocalConfiguration.php'
        );

        parent::setUp();

        // @todo actually this is needed, as the Label UserFunc is called even if record is deleted and the function doesn't check for deleted record
        // @see https://github.com/FriendsOfTYPO3/tt_address/issues/513
        unset($GLOBALS['TCA']['tt_address']['ctrl']['label_userFunc']);

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/ImportTestFixture.csv');
        $this->setUpBackendUser(1);
        Bootstrap::initializeLanguageObject();
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
        $jsonFile = $this->prepareJsonFile($this->importData);
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
        $jsonFile = $this->prepareJsonFile($this->importData);
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
        $jsonFile = $this->prepareJsonFile($this->importData);
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
