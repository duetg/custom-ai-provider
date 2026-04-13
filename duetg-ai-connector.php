<?php
/**
 * Plugin Name: DuetG AI Connector
 * Description: Connect WordPress AI Client to any OpenAI-compatible AI API provider
 * Version: 0.2.3
 * Author: DuetG
 * Author URI: https://github.com/duetg/duetg-ai-connector
 * License: GPL-2.0-or-later
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Text Domain: duetg-ai-connector
 *
 * @package CustomAiProvider
 */

namespace WordPress\CustomAiProvider;

use WordPress\CustomAiProvider\Settings\Settings;
use WordPress\CustomAiProvider\Admin\Admin;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Add custom image model to preferred models list
 *
 * @param array $models
 * @return array
 */
function duetgaicon_preferred_image_models_filter(array $models): array
{
    $image_model = Settings::getImageModel();
    if (!empty($image_model)) {
        $models[] = ['custom_image', $image_model];
    }
    return $models;
}
add_filter('wpai_preferred_image_models', __NAMESPACE__ . '\\duetgaicon_preferred_image_models_filter', PHP_INT_MAX);

/**
 * Add custom text model to preferred vision models list
 *
 * @param array $models
 * @return array
 */
function duetgaicon_preferred_vision_models_filter(array $models): array
{
    $text_model = Settings::getTextModel();
    if (!empty($text_model)) {
        $models[] = ['custom_text', $text_model];
    }
    return $models;
}
add_filter('wpai_preferred_vision_models', __NAMESPACE__ . '\\duetgaicon_preferred_vision_models_filter', PHP_INT_MAX);

/**
 * Increase timeout for AI API requests only
 *
 * @param array $args
 * @param string $url
 * @return array
 */
function duetgaicon_http_request_args_filter(array $args, string $url): array
{
    $ai_base_urls = [
        Settings::getTextBaseUrl(),
        Settings::getImageBaseUrl(),
    ];

    foreach ($ai_base_urls as $base) {
        if (!empty($base) && strpos($url, rtrim($base, '/')) === 0) {
            $args['timeout'] = 300; // 5 minutes timeout for AI requests
            break;
        }
    }

    return $args;
}
add_filter('http_request_args', __NAMESPACE__ . '\\duetgaicon_http_request_args_filter', 10, 2);

/**
 * Add custom text model to preferred text generation models list
 *
 * @param array $models
 * @return array
 */
function duetgaicon_preferred_text_models_filter(array $models): array
{
    $text_model = Settings::getTextModel();
    if (!empty($text_model)) {
        $models[] = ['custom_text', $text_model];
    }
    return $models;
}
add_filter('wpai_preferred_text_models', __NAMESPACE__ . '\\duetgaicon_preferred_text_models_filter', PHP_INT_MAX);

/**
 * Register our connectors with WordPress Connector Registry
 * This is needed because WordPress 7.0+ auto-discovery doesn't set setting_name properly
 *
 * @param WP_Connector_Registry $registry Connector registry instance
 */
function duetgaicon_register_connectors(\WP_Connector_Registry $registry): void
{
    // Register Custom Text connector - use 'custom_text' to match AI Client provider ID
    if (!$registry->is_registered('custom_text')) {
        $registry->register('custom_text', array(
            'name'        => __('DuetG Text (Custom)', 'duetg-ai-connector'),
            'description' => __('Connect to any OpenAI-compatible text generation API.', 'duetg-ai-connector'),
            'type'        => 'ai_provider',
            'authentication' => array(
                'method'          => 'api_key',
                'credentials_url' => 'https://github.com/duetg/duetg-ai-connector#setup',
                'setting_name'    => 'connectors_ai_duetgaicon_text_api_key',
            ),
        ));
    }

    // Register Custom Image connector - use 'custom_image' to match AI Client provider ID
    if (!$registry->is_registered('custom_image')) {
        $registry->register('custom_image', array(
            'name'        => __('DuetG Image (Custom)', 'duetg-ai-connector'),
            'description' => __('Connect to any OpenAI-compatible image generation API.', 'duetg-ai-connector'),
            'type'        => 'ai_provider',
            'authentication' => array(
                'method'          => 'api_key',
                'credentials_url' => 'https://github.com/duetg/duetg-ai-connector#setup',
                'setting_name'    => 'connectors_ai_duetgaicon_image_api_key',
            ),
        ));
    }
}
add_action('wp_connectors_init', __NAMESPACE__ . '\\duetgaicon_register_connectors');

/**
 * Register the connector to WordPress AI system
 */
function register_connector(): void
{
    if (!class_exists('WordPress\AiClient\AiClient')) {
        if (defined('DUETGAICON_DEBUG') && DUETGAICON_DEBUG) {
            error_log('DuetG AI Connector: AiClient class does not exist');
        }
        return;
    }

    // Initialize model handlers (autoloaded via PSR-4)
    \WordPress\CustomAiProvider\Models\TextGeneration\ModelHandlerRegistry::init();

    $registry = \WordPress\AiClient\AiClient::defaultRegistry();

    if (!$registry->hasProvider('custom_text')) {
        try {
            $registry->registerProvider(\WordPress\CustomAiProvider\Provider\CustomTextProvider::class);
            if (defined('DUETGAICON_DEBUG') && DUETGAICON_DEBUG) {
                error_log('DuetG AI Connector: custom_text provider registered successfully');
            }
        } catch (\Throwable $e) {
            if (defined('DUETGAICON_DEBUG') && DUETGAICON_DEBUG) {
                error_log('DuetG AI Connector: Failed to register custom_text provider: ' . $e->getMessage());
            }
        }
    }

    if (!$registry->hasProvider('custom_image')) {
        try {
            $registry->registerProvider(\WordPress\CustomAiProvider\Provider\CustomImageProvider::class);
            if (defined('DUETGAICON_DEBUG') && DUETGAICON_DEBUG) {
                error_log('DuetG AI Connector: custom_image provider registered successfully');
            }
        } catch (\Throwable $e) {
            if (defined('DUETGAICON_DEBUG') && DUETGAICON_DEBUG) {
                error_log('DuetG AI Connector: Failed to register custom_image provider: ' . $e->getMessage());
            }
        }
    }

    // Debug: log provider status
    if (defined('DUETGAICON_DEBUG') && DUETGAICON_DEBUG) {
        error_log('DuetG AI Connector: has custom_text=' . ($registry->hasProvider('custom_text') ? 'true' : 'false'));
        error_log('DuetG AI Connector: has custom_image=' . ($registry->hasProvider('custom_image') ? 'true' : 'false'));
    }

    Settings::pass_api_keys_to_ai_client();
}
add_action('init', __NAMESPACE__ . '\\register_connector', 5);

/**
 * Initialize settings
 */
function init_settings(): void
{
    Settings::init();
}
add_action('admin_init', __NAMESPACE__ . '\\init_settings');

/**
 * Add admin menu pages
 */
function add_admin_menu(): void
{
    add_options_page(
        __('DuetG AI Connector', 'duetg-ai-connector'),
        __('Custom AI', 'duetg-ai-connector'),
        'manage_options',
        'duetg-ai-connector',
        __NAMESPACE__ . '\\render_settings_page'
    );

    add_management_page(
        __('Test AI', 'duetg-ai-connector'),
        __('Test AI', 'duetg-ai-connector'),
        'manage_options',
        'duetg-ai-connector-test',
        __NAMESPACE__ . '\\render_test_page'
    );
}
add_action('admin_menu', __NAMESPACE__ . '\\add_admin_menu');

/**
 * Render the settings page
 */
function render_settings_page(): void
{
    Admin::render_settings_page();
}

/**
 * Render the test page
 */
function render_test_page(): void
{
    // Check if WordPress AI Client is available
    if (!class_exists('WordPress\AiClient\AiClient')) {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Test AI', 'duetg-ai-connector') . '</h1>';
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('DuetG AI Connector requires WordPress 7.0 or higher.', 'duetg-ai-connector');
        echo '</p></div>';
        echo '</div>';
        return;
    }

    \WordPress\CustomAiProvider\Admin\TestPage::render();
}

/**
 * Plugin activation
 */
function activate(): void
{
    // TODO: Add plugin activation tasks (e.g., flush rewrite rules, set default options)
}
register_activation_hook(__FILE__, __NAMESPACE__ . '\\activate');

/**
 * Plugin deactivation
 */
function deactivate(): void
{
    // TODO: Add plugin deactivation tasks (e.g., clear caches, cleanup temporary data)
}
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\deactivate');

/**
 * Add action links to plugin list
 *
 * @param array $links Existing plugin action links.
 * @return array Modified links array.
 */
function duetgaicon_provider_action_links(array $links): array {
    // Test AI link
    $test_ai_link = '<a href="' . admin_url('tools.php?page=duetg-ai-connector-test') . '">'
        . esc_html__('Test AI', 'duetg-ai-connector')
        . '</a>';
    array_unshift($links, $test_ai_link);

    // Custom AI settings link
    $custom_ai_link = '<a href="' . admin_url('options-general.php?page=duetg-ai-connector') . '">'
        . esc_html__('Custom AI', 'duetg-ai-connector')
        . '</a>';
    array_unshift($links, $custom_ai_link);

    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), __NAMESPACE__ . '\\duetgaicon_provider_action_links');
