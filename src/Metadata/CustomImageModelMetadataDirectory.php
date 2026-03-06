<?php
/**
 * Custom Image Model Metadata Directory
 *
 * @package CustomAiProvider\Metadata
 */

namespace WordPress\CustomAiProvider\Metadata;

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
        return [$this->getModelMetadata($this->getConfiguredModelId())];
    }

    public function hasModelMetadata(string $modelId): bool
    {
        return true;
    }

    public function getModelMetadata(string $modelId): ModelMetadata
    {
        return new ModelMetadata(
            $modelId,
            $modelId,
            [CapabilityEnum::imageGeneration()],
            [
                new SupportedOption(OptionEnum::outputMediaOrientation()),
                new SupportedOption(OptionEnum::outputMediaAspectRatio()),
                new SupportedOption(OptionEnum::outputFileType()),
            ]
        );
    }

    private function getConfiguredModelId(): string
    {
        if (defined('CUSTOM_AI_IMAGE_MODEL') && !empty(CUSTOM_AI_IMAGE_MODEL)) {
            return CUSTOM_AI_IMAGE_MODEL;
        }
        $model = get_option(Settings::IMAGE_MODEL_OPTION, '');
        if (!empty($model)) {
            return $model;
        }
        return 'stable-diffusion';
    }
}
