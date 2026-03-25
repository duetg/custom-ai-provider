<?php
/**
 * Custom Text Model Metadata Directory
 *
 * @package CustomAiProvider\Metadata
 */

namespace WordPress\CustomAiProvider\Metadata;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\CustomAiProvider\Settings\Settings;

/**
 * Custom Model Metadata Directory for Text Generation
 *
 * Note: All models are registered as supporting both text and image input modalities.
 * This allows users to try vision capabilities with any model. If the model doesn't
 * actually support vision, the API will return an error which will be handled gracefully.
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
        // Register as supporting both text and image input modalities
        // This allows vision capabilities to be attempted with any model
        // If model doesn't support vision, API will return an error
        $inputModalities = [
            [ModalityEnum::text()],
            [ModalityEnum::text(), ModalityEnum::image()],
        ];

        return new ModelMetadata(
            $modelId,
            $modelId,
            [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()],
            [
                new SupportedOption(OptionEnum::inputModalities(), $inputModalities),
                new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
                new SupportedOption(OptionEnum::outputMimeType(), ['application/json', 'text/plain']),
                new SupportedOption(OptionEnum::outputSchema(), null),
                new SupportedOption(OptionEnum::maxTokens()),
                new SupportedOption(OptionEnum::temperature()),
                new SupportedOption(OptionEnum::topP()),
                new SupportedOption(OptionEnum::stopSequences()),
                new SupportedOption(OptionEnum::systemInstruction()),
                new SupportedOption(OptionEnum::functionDeclarations()),
                new SupportedOption(OptionEnum::webSearch()),
                new SupportedOption(OptionEnum::candidateCount()),
            ]
        );
    }

    private function getConfiguredModelId(): string
    {
        return Settings::getTextModel();
    }
}
