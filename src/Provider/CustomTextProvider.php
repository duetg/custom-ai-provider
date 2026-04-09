<?php
/**
 * Custom Text Generation Provider
 *
 * @package CustomAiProvider\Provider
 */

namespace WordPress\CustomAiProvider\Provider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\CustomAiProvider\Settings\Settings;
use WordPress\CustomAiProvider\Models\TextGeneration\CustomTextGenerationModel;
use WordPress\CustomAiProvider\Metadata\CustomTextModelMetadataDirectory;

/**
 * Custom Text Generation Provider for OpenAI-compatible APIs
 */
class CustomTextProvider extends AbstractApiProvider
{
    /**
     * Create provider metadata
     *
     * @return ProviderMetadata
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'custom_text',
            __('Custom Text Generation', 'duetg-ai-connector'),
            ProviderTypeEnum::cloud(),
            null,
            RequestAuthenticationMethod::apiKey(),
            __('Text generation with custom OpenAI-compatible API provider', 'duetg-ai-connector')
        );
    }

    /**
     * Get base URL for the API
     *
     * @return string
     */
    protected static function baseUrl(): string
    {
        return rtrim(Settings::getTextBaseUrl(), '/');
    }

    /**
     * Get the model ID
     *
     * @return string
     */
    public static function getModelId(): string
    {
        return Settings::getTextModel();
    }

    /**
     * Create a model instance
     *
     * @param ModelMetadata $modelMetadata
     * @param ProviderMetadata $providerMetadata
     * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface {
        return new CustomTextGenerationModel($modelMetadata, $providerMetadata);
    }

    /**
     * Create provider availability checker
     *
     * @return \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface
     */
    protected static function createProviderAvailability(): \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface
    {
        return new class implements \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface {
            public function isConfigured(): bool
            {
                // Check if API key is configured
                return !empty(Settings::get_text_api_key());
            }
        };
    }

    /**
     * Create model metadata directory
     *
     * @return \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface
     */
    protected static function createModelMetadataDirectory(): \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface
    {
        return new CustomTextModelMetadataDirectory();
    }
}
