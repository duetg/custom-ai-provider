<?php
/**
 * Custom Image Generation Provider
 *
 * @package CustomAiProvider\Provider
 */

namespace WordPress\CustomAiProvider\Provider;

use WordPress\AiClient\AiClient;
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
        $providerMetadataArgs = [
            'custom_image',
            __('Custom Image Generation', 'duetg-ai-connector'),
            ProviderTypeEnum::cloud(),
            null,
            RequestAuthenticationMethod::apiKey(),
            __('Image generation with custom OpenAI-compatible API provider', 'duetg-ai-connector'),
        ];

        // Provider logoPath support was added in 1.3.0.
        if (version_compare(AiClient::VERSION, '1.3.0', '>=')) {
            $providerMetadataArgs[] = dirname(__DIR__, 2) . '/assets/images/duetg-ai-connector.svg';
        }

        return new ProviderMetadata(...$providerMetadataArgs);
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
                // Always return true - users are responsible for their own API key validity
                return true;
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
