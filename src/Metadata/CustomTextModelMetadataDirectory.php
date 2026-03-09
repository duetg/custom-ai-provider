<?php
/**
 * Custom Text Model Metadata Directory
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
 * Custom Model Metadata Directory for Text Generation
 */
class CustomTextModelMetadataDirectory implements ModelMetadataDirectoryInterface
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
            [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()],
            [
                new SupportedOption(OptionEnum::inputModalities()),
                new SupportedOption(OptionEnum::outputModalities()),
                new SupportedOption(OptionEnum::maxTokens()),
                new SupportedOption(OptionEnum::temperature()),
                new SupportedOption(OptionEnum::topP()),
                new SupportedOption(OptionEnum::stopSequences()),
                new SupportedOption(OptionEnum::systemInstruction()),
                new SupportedOption(OptionEnum::functionDeclarations()),
                new SupportedOption(OptionEnum::webSearch()),
            ]
        );
    }

    private function getConfiguredModelId(): string
    {
        $model = get_option(Settings::TEXT_MODEL_OPTION, '');
        if (!empty($model)) {
            return $model;
        }
        return Settings::DEFAULT_TEXT_MODEL;
    }
}
