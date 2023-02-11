<?php

declare(strict_types=1);

namespace SUDHAUS7\Xlsimport\Property\TypeConverter;

use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\AbstractTypeConverter;

class UploadedFileConverter extends AbstractTypeConverter
{
    /**
     * @var array<string>
     */
    protected $sourceTypes = ['array'];

    protected $targetType = UploadedFile::class;

    protected $priority = 30;

    /**
     * @inheritDoc
     */
    public function convertFrom(
        $source,
        string $targetType,
        array $convertedChildProperties = [],
        PropertyMappingConfigurationInterface $configuration = null
    ) {
        $uploadedFile = new UploadedFile($source['tmp_name'], $source['size'], $source['error'], $source['name'], $source['type']);

        return $uploadedFile->getError() === UPLOAD_ERR_OK ? $uploadedFile : null;
    }
}
