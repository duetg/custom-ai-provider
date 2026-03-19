<?php
/**
 * Plugin Name: Custom AI Provider
 * Description: Connect WordPress AI Client to any OpenAI-compatible AI API provider
 * Version: 0.2.0
 * Author: DuetG
 * Author URI: https://github.com/duetg/custom-ai-provider
 * License: GPL-2.0-or-later
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Text Domain: custom-ai-provider
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

// Register custom image model in WordPress AI's preferred image models list
// Must use very late priority to ensure WordPress AI plugin is fully loaded
add_filter('ai_experiments_preferred_image_models', function ($models) {
    // Always add custom_image provider, use default if not configured
    $image_model = get_option(\WordPress\CustomAiProvider\Settings\Settings::IMAGE_MODEL_OPTION, \WordPress\CustomAiProvider\Settings\Settings::DEFAULT_IMAGE_MODEL);
    if (!empty($image_model)) {
        $models[] = ['custom_image', $image_model];
    }
    return $models;
}, PHP_INT_MAX);

// Register custom text model in WordPress AI's preferred vision models list for alt text generation
// Must use very late priority to ensure WordPress AI plugin is fully loaded
add_filter('ai_experiments_preferred_vision_models', function ($models) {
    // Add custom_text provider for vision tasks (alt text generation)
    // All models registered as supporting vision, actual support depends on API
    $text_model = get_option(\WordPress\CustomAiProvider\Settings\Settings::TEXT_MODEL_OPTION, \WordPress\CustomAiProvider\Settings\Settings::DEFAULT_TEXT_MODEL);
    if (!empty($text_model)) {
        $models[] = ['custom_text', $text_model];
    }
    return $models;
}, PHP_INT_MAX);

// Increase timeout for AI requests only (not all WordPress HTTP requests)
add_filter('http_request_args', function ($args, $url) {
    // Only set long timeout for our configured AI API URLs
    $ai_base_urls = [
        get_option(\WordPress\CustomAiProvider\Settings\Settings::TEXT_BASE_URL_OPTION, ''),
        get_option(\WordPress\CustomAiProvider\Settings\Settings::IMAGE_BASE_URL_OPTION, ''),
    ];

    foreach ($ai_base_urls as $base) {
        if (!empty($base) && strpos($url, rtrim($base, '/')) === 0) {
            $args['timeout'] = 300; // 5 minutes timeout for AI requests
            break;
        }
    }

    return $args;
}, 10, 2);

// Register custom text model in WordPress AI's preferred text generation models list
add_filter('ai_experiments_preferred_models_for_text_generation', function ($models) {
    $text_model = get_option(\WordPress\CustomAiProvider\Settings\Settings::TEXT_MODEL_OPTION, \WordPress\CustomAiProvider\Settings\Settings::DEFAULT_TEXT_MODEL);
    custom_ai_debug('ai_experiments_preferred_models_for_text_generation filter', ['text_model' => $text_model]);
    if (!empty($text_model)) {
        $models[] = ['custom_text', $text_model];
    }
    custom_ai_debug('Preferred models', $models);
    return $models;
}, PHP_INT_MAX);

/**
 * Register the connector to WordPress AI system
 */
function register_connector(): void
{
    custom_ai_debug('register_connector called');

    if (!class_exists('WordPress\AiClient\AiClient')) {
        custom_ai_debug('AiClient class not found');
        return;
    }

    // Initialize model handlers (autoloaded via PSR-4)
    \WordPress\CustomAiProvider\Models\TextGeneration\ModelHandlerRegistry::init();

    $registry = \WordPress\AiClient\AiClient::defaultRegistry();

    if (!$registry->hasProvider('custom_text')) {
        $registry->registerProvider(\WordPress\CustomAiProvider\Provider\CustomTextProvider::class);
        custom_ai_debug('Registered custom_text provider');
    } else {
        custom_ai_debug('custom_text provider already exists');
    }

    if (!$registry->hasProvider('custom_image')) {
        $registry->registerProvider(\WordPress\CustomAiProvider\Provider\CustomImageProvider::class);
        custom_ai_debug('Registered custom_image provider');
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
        __('Custom AI Provider', 'custom-ai-provider'),
        __('Custom AI', 'custom-ai-provider'),
        'manage_options',
        'custom-ai-provider',
        __NAMESPACE__ . '\\render_settings_page'
    );

    add_management_page(
        __('Test AI', 'custom-ai-provider'),
        __('Test AI', 'custom-ai-provider'),
        'manage_options',
        'custom-ai-provider-test',
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
        echo '<h1>' . esc_html__('Test AI', 'custom-ai-provider') . '</h1>';
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Custom AI Provider requires WordPress 7.0 or higher.', 'custom-ai-provider');
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
}
register_activation_hook(__FILE__, __NAMESPACE__ . '\\activate');

/**
 * Plugin deactivation
 */
function deactivate(): void
{
}
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\deactivate');
