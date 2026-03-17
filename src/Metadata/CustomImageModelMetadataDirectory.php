<?php
/**
 * Custom Image Model Metadata Directory
 *
 * @package CustomAiProvider\Metadata
 */

namespace WordPress\CustomAiProvider\Metadata;

use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\CustomAiProvider\Settings\Settings;

/**
 * Custom Model Metadata Directory for Image Generation
 */
class CustomImageModelMetadataDirectory implements ModelMetadataDirectoryInterface
{
    public function listModelMetadata(): array
    {
        $modelId = $this->getConfiguredModelId();
        return [$this->getModelMetadata($modelId)];
    }

    public function hasModelMetadata(string $modelId): bool
    {
        return true;
    }

    public function getModelMetadata(string $modelId): ModelMetadata
    {
        $metadata = new ModelMetadata(
            $modelId,
            $modelId,
            [CapabilityEnum::imageGeneration()],
            [
                new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
                new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::image()]]),
                new SupportedOption(OptionEnum::outputMediaOrientation(), [
                    MediaOrientationEnum::square(),
                    MediaOrientationEnum::landscape(),
                    MediaOrientationEnum::portrait(),
                ]),
                new SupportedOption(OptionEnum::outputMediaAspectRatio(), ['1:1', '16:9', '9:16', '4:3', '3:4']),
                new SupportedOption(OptionEnum::outputFileType()),
                new SupportedOption(OptionEnum::candidateCount()),
            ]
        );
        return $metadata;
    }

    private function getConfiguredModelId(): string
    {
        $model = get_option(Settings::IMAGE_MODEL_OPTION, '');
        if (!empty($model)) {
            return $model;
        }
        return Settings::DEFAULT_IMAGE_MODEL;
    }
}
