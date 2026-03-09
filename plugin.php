<?php
/**
 * Plugin Name: Custom AI Provider
 * Description: Connect WordPress AI Client to any OpenAI-compatible AI API provider
 * Version: 0.1.0
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

/**
 * Register the connector to WordPress AI system
 */
function register_connector(): void
{
    if (!class_exists('WordPress\AiClient\AiClient')) {
        return;
    }

    require_once __DIR__ . '/src/Provider/CustomTextProvider.php';
    require_once __DIR__ . '/src/Provider/CustomImageProvider.php';

    $registry = \WordPress\AiClient\AiClient::defaultRegistry();

    if (!$registry->hasProvider('custom_text')) {
        $registry->registerProvider(\WordPress\CustomAiProvider\Provider\CustomTextProvider::class);
    }

    if (!$registry->hasProvider('custom_image')) {
        $registry->registerProvider(\WordPress\CustomAiProvider\Provider\CustomImageProvider::class);
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

    require_once __DIR__ . '/src/Admin/TestPage.php';
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
