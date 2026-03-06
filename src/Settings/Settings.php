<?php
/**
 * Settings for Custom AI Provider Connector
 *
 * @package CustomAiProvider\Settings
 */

namespace WordPress\CustomAiProvider\Settings;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Settings class for managing connector configuration
 */
class Settings
{
    // Default values
    public const DEFAULT_TEXT_BASE_URL = 'https://api.openai.com/v1';
    public const DEFAULT_TEXT_MODEL = 'gpt-4';
    public const DEFAULT_IMAGE_BASE_URL = 'https://api.openai.com/v1';
    public const DEFAULT_IMAGE_MODEL = 'dall-e-3';

    // Text Generation Settings
    public const TEXT_ENABLED_OPTION = 'connectors_ai_custom_text_enabled';
    public const TEXT_BASE_URL_OPTION = 'connectors_ai_custom_text_base_url';
    public const TEXT_MODEL_OPTION = 'connectors_ai_custom_text_model';
    public const TEXT_API_KEY_OPTION = 'connectors_ai_custom_text_api_key';

    // Image Generation Settings
    public const IMAGE_ENABLED_OPTION = 'connectors_ai_custom_image_enabled';
    public const IMAGE_BASE_URL_OPTION = 'connectors_ai_custom_image_base_url';
    public const IMAGE_MODEL_OPTION = 'connectors_ai_custom_image_model';
    public const IMAGE_API_KEY_OPTION = 'connectors_ai_custom_image_api_key';

    /**
     * Initialize settings
     */
    public static function init(): void
    {
        // Register settings for REST API
        self::register_settings();
    }

    /**
     * Register WordPress settings
     */
    private static function register_settings(): void
    {
        // Text Generation Settings
        register_setting('connectors', self::TEXT_ENABLED_OPTION, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
            'show_in_rest' => true,
        ]);

        register_setting('connectors', self::TEXT_BASE_URL_OPTION, [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => Settings::DEFAULT_TEXT_BASE_URL,
            'show_in_rest' => true,
        ]);

        register_setting('connectors', self::TEXT_MODEL_OPTION, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => Settings::DEFAULT_TEXT_MODEL,
            'show_in_rest' => true,
        ]);

        // Image Generation Settings
        register_setting('connectors', self::IMAGE_ENABLED_OPTION, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
            'show_in_rest' => true,
        ]);

        register_setting('connectors', self::IMAGE_BASE_URL_OPTION, [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => Settings::DEFAULT_IMAGE_BASE_URL,
            'show_in_rest' => true,
        ]);

        register_setting('connectors', self::IMAGE_MODEL_OPTION, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => Settings::DEFAULT_IMAGE_MODEL,
            'show_in_rest' => true,
        ]);
    }

    /**
     * Pass API keys to AI Client registry
     */
    public static function pass_api_keys_to_ai_client(): void
    {
        if (!class_exists(AiClient::class)) {
            return;
        }

        try {
            $registry = AiClient::defaultRegistry();

            // Text generation API key
            $text_api_key = get_option(self::TEXT_API_KEY_OPTION, '');
            if (!empty($text_api_key) && $registry->hasProvider('custom_text')) {
                $registry->setProviderRequestAuthentication(
                    'custom_text',
                    new ApiKeyRequestAuthentication($text_api_key)
                );
            }

            // Image generation API key
            $image_api_key = get_option(self::IMAGE_API_KEY_OPTION, '');
            if (!empty($image_api_key) && $registry->hasProvider('custom_image')) {
                $registry->setProviderRequestAuthentication(
                    'custom_image',
                    new ApiKeyRequestAuthentication($image_api_key)
                );
            }
        } catch (\Exception $e) {
            // Silently handle errors
        }
    }
}
