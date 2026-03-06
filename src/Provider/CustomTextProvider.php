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
            __('Custom Text Generation', 'custom-ai-provider'),
            ProviderTypeEnum::cloud(),
            null,
            RequestAuthenticationMethod::apiKey(),
            __('Text generation with custom OpenAI-compatible API provider', 'custom-ai-provider')
        );
    }

    /**
     * Get base URL for the API
     *
     * Priority: 1. wp-config.php constant, 2. database option, 3. default
     *
     * @return string
     */
    protected static function baseUrl(): string
    {
        // 1. Check wp-config.php constant first
        if (defined('CUSTOM_AI_TEXT_BASE_URL') && !empty(CUSTOM_AI_TEXT_BASE_URL)) {
            return rtrim(CUSTOM_AI_TEXT_BASE_URL, '/');
        }

        // 2. Then check database option
        $base_url = get_option(Settings::TEXT_BASE_URL_OPTION, '');
        if (!empty($base_url)) {
            return rtrim($base_url, '/');
        }

        // 3. Default fallback
        return 'http://localhost:11434/v1';
    }

    /**
     * Get the model ID
     *
     * Priority: 1. wp-config.php constant, 2. database option, 3. default
     *
     * @return string
     */
    public static function getModelId(): string
    {
        // 1. Check wp-config.php constant first
        if (defined('CUSTOM_AI_TEXT_MODEL') && !empty(CUSTOM_AI_TEXT_MODEL)) {
            return CUSTOM_AI_TEXT_MODEL;
        }

        // 2. Then check database option
        $model = get_option(Settings::TEXT_MODEL_OPTION, '');
        if (!empty($model)) {
            return $model;
        }

        // 3. Default fallback
        return Settings::DEFAULT_TEXT_MODEL;
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
                // Check if base URL is configured (from constant or option)
                if (defined('CUSTOM_AI_TEXT_BASE_URL') && !empty(CUSTOM_AI_TEXT_BASE_URL)) {
                    return true;
                }
                $base_url = get_option(Settings::TEXT_BASE_URL_OPTION, '');
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
        return new CustomTextModelMetadataDirectory();
    }
}
