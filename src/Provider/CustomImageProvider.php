<?php
/**
 * Custom Image Generation Provider
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
use WordPress\CustomAiProvider\Models\ImageGeneration\CustomImageGenerationModel;
use WordPress\CustomAiProvider\Metadata\CustomImageModelMetadataDirectory;

/**
 * Custom Image Generation Provider for OpenAI-compatible APIs
 */
class CustomImageProvider extends AbstractApiProvider
{
    /**
     * Create provider metadata
     *
     * @return ProviderMetadata
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'custom_image',
            __('Custom Image Generation', 'custom-ai-provider'),
            ProviderTypeEnum::cloud(),
            null,
            RequestAuthenticationMethod::apiKey(),
            __('Image generation with custom OpenAI-compatible API provider', 'custom-ai-provider')
        );
    }

    /**
     * Get base URL for the API
     *
     *
     * @return string
     */
    protected static function baseUrl(): string
    {
        // Check database option
        $base_url = get_option(Settings::IMAGE_BASE_URL_OPTION, '');
        if (!empty($base_url)) {
            return rtrim($base_url, '/');
        }

        // Default fallback
        return 'http://localhost:11434';
    }

    /**
     * Get the model ID
     *
     * @return string
     */
    public static function getModelId(): string
    {
        // Check database option
        $model = get_option(Settings::IMAGE_MODEL_OPTION, '');
        if (!empty($model)) {
            return $model;
        }

        // Default fallback
        return Settings::DEFAULT_IMAGE_MODEL;
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
        return new CustomImageGenerationModel($modelMetadata, $providerMetadata);
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
                // Check if base URL is configured in database
                $base_url = get_option(Settings::IMAGE_BASE_URL_OPTION, '');
                return !empty($base_url);
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
        return new CustomImageModelMetadataDirectory();
    }
}
