<?php
/**
 * Plugin Name: Custom AI Provider
 * Description: Connect WordPress to any OpenAI-compatible AI API provider
 * Version: 0.1.0
 * Author: DuetG
 * Author URI: https://duetg.com
 * License: GPL-2.0-or-later
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Text Domain: custom-ai-provider
 *
 * @package CustomAiProvider
 */

namespace WordPress\CustomAiProvider;

use WordPress\AiClient\AiClient;
use WordPress\CustomAiProvider\Provider\CustomTextProvider;
use WordPress\CustomAiProvider\Provider\CustomImageProvider;
use WordPress\CustomAiProvider\Settings\Settings;
use WordPress\CustomAiProvider\Admin\Admin;
use WordPress\CustomAiProvider\Admin\TestPage;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Load plugin text domain for translations
 */
function load_custom_ai_provider_textdomain(): void
{
    $plugin_dir = dirname(plugin_basename(__FILE__));
    $lang_dir = WP_CONTENT_DIR . '/plugins/' . $plugin_dir . '/languages';

    load_textdomain(
        'custom-ai-provider',
        $lang_dir . '/custom-ai-provider-zh_CN.mo'
    );

    // Also try loading default MO
    load_plugin_textdomain(
        'custom-ai-provider',
        false,
        $plugin_dir . '/languages'
    );
}
add_action('plugins_loaded', __NAMESPACE__ . '\\load_custom_ai_provider_textdomain');

/**
 * Register the connector to WordPress AI system
 */
function register_connector(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if (!$registry->hasProvider('custom_text')) {
        $registry->registerProvider(CustomTextProvider::class);
    }

    if (!$registry->hasProvider('custom_image')) {
        $registry->registerProvider(CustomImageProvider::class);
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

    add_options_page(
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
    TestPage::render();
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
