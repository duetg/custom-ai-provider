<?php
/**
 * Admin settings page for Custom AI Provider
 *
 * @package CustomAiProvider\Admin
 */

namespace WordPress\CustomAiProvider\Admin;

use WordPress\CustomAiProvider\Settings\Settings;

/**
 * Admin class for rendering the settings page
 */
class Admin
{
    /**
     * Initialize admin hooks
     */
    public static function init(): void
    {
        // Nothing to initialize - we use manual form handling
    }

    /**
     * Render the settings page
     */
    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings if form was submitted
        if (isset($_POST['custom_ai_save']) && check_admin_referer('custom_ai_save_action')) {
            if (isset($_POST[Settings::TEXT_BASE_URL_OPTION])) {
                update_option(Settings::TEXT_BASE_URL_OPTION, esc_url_raw($_POST[Settings::TEXT_BASE_URL_OPTION]));
            }
            if (isset($_POST[Settings::TEXT_MODEL_OPTION])) {
                update_option(Settings::TEXT_MODEL_OPTION, sanitize_text_field($_POST[Settings::TEXT_MODEL_OPTION]));
            }
            if (isset($_POST[Settings::IMAGE_BASE_URL_OPTION])) {
                update_option(Settings::IMAGE_BASE_URL_OPTION, esc_url_raw($_POST[Settings::IMAGE_BASE_URL_OPTION]));
            }
            if (isset($_POST[Settings::IMAGE_MODEL_OPTION])) {
                update_option(Settings::IMAGE_MODEL_OPTION, sanitize_text_field($_POST[Settings::IMAGE_MODEL_OPTION]));
            }
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        // Get current values or defaults
        $text_base_url = get_option(Settings::TEXT_BASE_URL_OPTION, '');
        $text_model = get_option(Settings::TEXT_MODEL_OPTION, '');
        $image_base_url = get_option(Settings::IMAGE_BASE_URL_OPTION, '');
        $image_model = get_option(Settings::IMAGE_MODEL_OPTION, '');

        // Use display defaults if empty
        $text_base_url_display = $text_base_url ?: Settings::DEFAULT_TEXT_BASE_URL;
        $text_model_display = $text_model ?: Settings::DEFAULT_TEXT_MODEL;
        $image_base_url_display = $image_base_url ?: Settings::DEFAULT_IMAGE_BASE_URL;
        $image_model_display = $image_model ?: Settings::DEFAULT_IMAGE_MODEL;

        // Check if using defaults (for notice display)
        $using_default_text = empty($text_base_url) && empty($text_model);
        $using_default_image = empty($image_base_url) && empty($image_model);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php
            // Check WordPress version - Connectors page only exists in 7.0 stable+
            // Exclude beta/RC/alpha versions as they may not have all features
            $wp_version = get_bloginfo('version');
            $is_prerelease = preg_match('/(beta|RC|alpha|a|b)/i', $wp_version);
            $is_wp70_stable = !$is_prerelease && version_compare($wp_version, '7.0', '>=');
            ?>
            <div class="notice notice-info">
                <p><strong><?php _e('API Key Configuration', 'custom-ai-provider'); ?></strong></p>
                <?php if ($is_wp70_stable): ?>
                    <p><?php _e('Please configure your API Key in the Settings > Connectors page. The API Key is required for the provider to be activated.', 'custom-ai-provider'); ?></p>
                <?php else: ?>
                    <p><?php _e('In WordPress versions below 7.0, please configure your API Key using a constant or environment variable:', 'custom-ai-provider'); ?></p>
                    <p><code>define('CUSTOM_AI_PROVIDER_TEXT_API_KEY', 'your-key');</code></p>
                    <p><code>define('CUSTOM_AI_PROVIDER_IMAGE_API_KEY', 'your-key');</code></p>
                    <p><?php _e('Or use environment variables:', 'custom-ai-provider'); ?></p>
                    <p><code>custom_ai_provider_text_api_key=your-key</code></p>
                    <p><code>custom_ai_provider_image_api_key=your-key</code></p>
                <?php endif; ?>
            </div>

            <?php if ($using_default_text): ?>
            <div class="notice notice-info">
                <p><?php _e('Text Generation is using default values (OpenAI). To use a different provider, enter your custom Base URL and Model Name below, then click Save.', 'custom-ai-provider'); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($using_default_image): ?>
            <div class="notice notice-info">
                <p><?php _e('Image Generation is using default values (OpenAI). To use a different provider, enter your custom Base URL and Model Name below, then click Save.', 'custom-ai-provider'); ?></p>
            </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('custom_ai_save_action'); ?>
                <input type="hidden" name="custom_ai_save" value="1">

                <h2><?php _e('Text Generation', 'custom-ai-provider'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr(Settings::TEXT_BASE_URL_OPTION); ?>">Base URL</label>
                        </th>
                        <td>
                            <input type="url"
                                name="<?php echo esc_attr(Settings::TEXT_BASE_URL_OPTION); ?>"
                                id="<?php echo esc_attr(Settings::TEXT_BASE_URL_OPTION); ?>"
                                value="<?php echo esc_attr($text_base_url_display); ?>"
                                class="regular-text"
                                placeholder="<?php echo esc_attr(Settings::DEFAULT_TEXT_BASE_URL); ?>">
                            <p class="description"><?php _e('The base URL for the text generation API. Default: https://api.openai.com/v1', 'custom-ai-provider'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr(Settings::TEXT_MODEL_OPTION); ?>"><?php _e('Model Name', 'custom-ai-provider'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                name="<?php echo esc_attr(Settings::TEXT_MODEL_OPTION); ?>"
                                id="<?php echo esc_attr(Settings::TEXT_MODEL_OPTION); ?>"
                                value="<?php echo esc_attr($text_model_display); ?>"
                                class="regular-text"
                                placeholder="<?php echo esc_attr(Settings::DEFAULT_TEXT_MODEL); ?>">
                            <p class="description"><?php _e('The model identifier. Default: gpt-4', 'custom-ai-provider'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php _e('Image Generation', 'custom-ai-provider'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr(Settings::IMAGE_BASE_URL_OPTION); ?>">Base URL</label>
                        </th>
                        <td>
                            <input type="url"
                                name="<?php echo esc_attr(Settings::IMAGE_BASE_URL_OPTION); ?>"
                                id="<?php echo esc_attr(Settings::IMAGE_BASE_URL_OPTION); ?>"
                                value="<?php echo esc_attr($image_base_url_display); ?>"
                                class="regular-text"
                                placeholder="<?php echo esc_attr(Settings::DEFAULT_IMAGE_BASE_URL); ?>">
                            <p class="description"><?php _e('The base URL for the image generation API. Default: https://api.openai.com/v1', 'custom-ai-provider'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr(Settings::IMAGE_MODEL_OPTION); ?>"><?php _e('Model Name', 'custom-ai-provider'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                name="<?php echo esc_attr(Settings::IMAGE_MODEL_OPTION); ?>"
                                id="<?php echo esc_attr(Settings::IMAGE_MODEL_OPTION); ?>"
                                value="<?php echo esc_attr($image_model_display); ?>"
                                class="regular-text"
                                placeholder="<?php echo esc_attr(Settings::DEFAULT_IMAGE_MODEL); ?>">
                            <p class="description"><?php _e('The model identifier. Default: dall-e-3', 'custom-ai-provider'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Changes', 'custom-ai-provider')); ?>
            </form>
        </div>
        <?php
    }
}
