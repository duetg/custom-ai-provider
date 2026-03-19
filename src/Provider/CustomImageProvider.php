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
     * @return string
     */
    protected static function baseUrl(): string
    {
        return rtrim(Settings::getImageBaseUrl(), '/');
    }

    /**
     * Get the model ID
     *
     * @return string
     */
    public static function getModelId(): string
    {
        return Settings::getImageModel();
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
                // Check if base URL option is explicitly set (not using default)
                $base_url = get_option(Settings::IMAGE_BASE_URL_OPTION, null);
                return $base_url !== null && $base_url !== '';
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
