<?php
/**
 * Admin test page for Custom AI Provider
 *
 * @package CustomAiProvider\Admin
 */

namespace WordPress\CustomAiProvider\Admin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

use WordPress\AiClient\AiClient;
use WordPress\CustomAiProvider\Provider\CustomTextProvider;
use WordPress\CustomAiProvider\Provider\CustomImageProvider;
use WordPress\CustomAiProvider\Settings\Settings;

/**
 * Test page class
 */
class TestPage
{
    /**
     * Check if API key is configured in database
     *
     * @param string $type 'text' or 'image'
     * @return bool
     */
    private static function get_api_key_status(string $type): bool
    {
        $api_key = $type === 'text'
            ? Settings::get_text_api_key()
            : Settings::get_image_api_key();

        return !empty($api_key);
    }

    /**
     * Enqueue required scripts
     */
    public static function enqueue_scripts(): void
    {
        $plugin_url = plugin_dir_url(dirname(__DIR__, 2) . '/custom-ai-provider.php');
        $js_url = $plugin_url . 'assets/js/test-page.js';

        wp_enqueue_script(
            'custom-ai-test-page',
            $js_url,
            [],
            null,
            true
        );

        // Pass localized data for placeholders
        wp_add_inline_script(
            'custom-ai-test-page',
            'window.customAiTestPage = ' . json_encode([
                'textPlaceholder' => __('Enter your text prompt...', 'custom-ai-provider'),
                'imagePlaceholder' => __('Describe the image you want to generate...', 'custom-ai-provider'),
            ]) . ';',
            'before'
        );
    }

    /**
     * Render the test page
     */
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Enqueue scripts
        self::enqueue_scripts();

        $result = null;
        $error = null;
        $provider_type = 'text';
        $prompt = '';

        // Handle form submission
        if (isset($_POST['test_submit']) && check_admin_referer('custom_ai_test_action')) {
            $provider_type = isset($_POST['provider_type']) ? sanitize_text_field(wp_unslash($_POST['provider_type'])) : 'text';
            $prompt = isset($_POST['prompt']) ? sanitize_text_field(wp_unslash($_POST['prompt'])) : '';

            if (empty($prompt)) {
                $error = __('Please enter a prompt.', 'custom-ai-provider');
            } else {
                try {
                    $registry = AiClient::defaultRegistry();

                    if ($provider_type === 'text') {
                        if (!$registry->hasProvider('custom_text')) {
                            $error = __('Text provider not registered.', 'custom-ai-provider');
                        } elseif (!$registry->isProviderConfigured('custom_text')) {
                            $error = __('Text provider not configured. Please add API key via Settings > Connectors.', 'custom-ai-provider');
                        } else {
                            $model = $registry->getProviderModel('custom_text', CustomTextProvider::getModelId());
                            $result = $model->generateTextResult([
                                new \WordPress\AiClient\Messages\DTO\UserMessage([
                                    new \WordPress\AiClient\Messages\DTO\MessagePart($prompt)
                                ])
                            ]);
                        }
                    } else {
                        if (!$registry->hasProvider('custom_image')) {
                            $error = __('Image provider not registered.', 'custom-ai-provider');
                        } elseif (!$registry->isProviderConfigured('custom_image')) {
                            $error = __('Image provider not configured. Please add API key via Settings > Connectors.', 'custom-ai-provider');
                        } else {
                            $model = $registry->getProviderModel('custom_image', CustomImageProvider::getModelId());
                            $result = $model->generateImageResult([
                                new \WordPress\AiClient\Messages\DTO\UserMessage([
                                    new \WordPress\AiClient\Messages\DTO\MessagePart($prompt)
                                ])
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    custom_ai_debug('Test page error', ['message' => $e->getMessage()]);
                    $error = __('An error occurred while processing the request. Check debug logs for details.', 'custom-ai-provider');
                    if (defined('CUSTOM_AI_DEBUG') && CUSTOM_AI_DEBUG) {
                        $error .= ' ' . $e->getMessage();
                    }
                }
            }
        }

        // Get current settings using centralized methods
        $text_base_url = Settings::getTextBaseUrl();
        $text_model = CustomTextProvider::getModelId();
        $image_base_url = Settings::getImageBaseUrl();
        $image_model = CustomImageProvider::getModelId();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2><?php esc_html_e('Provider Status', 'custom-ai-provider'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Type', 'custom-ai-provider'); ?></th>
                            <th><?php esc_html_e('Base URL', 'custom-ai-provider'); ?></th>
                            <th><?php esc_html_e('Model', 'custom-ai-provider'); ?></th>
                            <th><?php esc_html_e('API Key Status', 'custom-ai-provider'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('Text Generation', 'custom-ai-provider'); ?></strong></td>
                            <td><?php echo esc_html($text_base_url); ?></td>
                            <td><?php echo esc_html($text_model); ?></td>
                            <td>
                                <?php
                                $text_api_key = self::get_api_key_status('text');

                                if ($text_api_key) {
                                    echo '<span style="color: green;">&#10004; ' . esc_html__('Configured', 'custom-ai-provider') . '</span>';
                                } else {
                                    echo '<span style="color: red;">&#10008; ' . esc_html__('Not Configured', 'custom-ai-provider') . '</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Image Generation', 'custom-ai-provider'); ?></strong></td>
                            <td><?php echo esc_html($image_base_url); ?></td>
                            <td><?php echo esc_html($image_model); ?></td>
                            <td>
                                <?php
                                $image_api_key = self::get_api_key_status('image');

                                if ($image_api_key) {
                                    echo '<span style="color: green;">&#10004; ' . esc_html__('Configured', 'custom-ai-provider') . '</span>';
                                } else {
                                    echo '<span style="color: red;">&#10008; ' . esc_html__('Not Configured', 'custom-ai-provider') . '</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2><?php esc_html_e('Test AI', 'custom-ai-provider'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('custom_ai_test_action'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Provider Type', 'custom-ai-provider'); ?></th>
                            <td>
                                <select name="provider_type" id="provider_type" onchange="updatePromptPlaceholder()">
                                    <option value="text" <?php selected($provider_type, 'text'); ?>><?php esc_html_e('Text Generation', 'custom-ai-provider'); ?></option>
                                    <option value="image" <?php selected($provider_type, 'image'); ?>><?php esc_html_e('Image Generation', 'custom-ai-provider'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="prompt"><?php esc_html_e('Prompt', 'custom-ai-provider'); ?></label>
                            </th>
                            <td>
                                <textarea
                                    name="prompt"
                                    id="prompt"
                                    rows="4"
                                    class="large-text"
                                    placeholder="<?php echo $provider_type === 'text' ? esc_attr__('Enter your text prompt...', 'custom-ai-provider') : esc_attr__('Describe the image you want to generate...', 'custom-ai-provider'); ?>"
                                ><?php echo esc_textarea($prompt); ?></textarea>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(esc_html__('Generate', 'custom-ai-provider'), 'primary', 'test_submit', false); ?>
                </form>

                <?php if ($error): ?>
                    <div class="notice notice-error" style="margin-top: 20px;">
                        <p><strong><?php esc_html_e('Error', 'custom-ai-provider'); ?>:</strong> <?php echo esc_html($error); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($result): ?>
                    <div class="notice notice-success" style="margin-top: 20px;">
                        <p><strong><?php esc_html_e('Success!', 'custom-ai-provider'); ?></strong></p>
                        <?php if ($provider_type === 'text'): ?>
                            <?php $text = $result->toText(); ?>
                            <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto; max-height: 300px;"><?php echo esc_html($text); ?></pre>
                        <?php else: ?>
                            <?php
                            $files = $result->toImageFiles();
                            if (!empty($files)):
                                foreach ($files as $file):
                                    // Validate Data URI format for inline images to prevent XSS
                                    $url = '';
                                    if ($file->isInline()) {
                                        $dataUri = $file->getDataUri();
                                        // Only allow safe image MIME types in data URIs
                                        if (preg_match('#^data:(image/(?:png|jpeg|gif|webp));base64,[A-Za-z0-9+/=]+$#', $dataUri)) {
                                            $url = $dataUri;
                                        }
                                    } else {
                                        $url = esc_url($file->getUrl());
                                    }
                                    if ($url): ?>
                                    <div style="margin-top: 10px;">
                                        <img src="<?php echo $url; ?>" style="max-width: 500px; height: auto; border: 1px solid #ccc;" />
                                    </div>
                                    <?php endif;
                                endforeach; ?>
                            <?php else: ?>
                                <p><?php echo esc_html__('No image files returned. Result type: ', 'custom-ai-provider') . esc_html(get_class($result)); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2><?php esc_html_e('How to Use', 'custom-ai-provider'); ?></h2>
                <p><?php esc_html_e('To use this provider in your code:', 'custom-ai-provider'); ?></p>
                <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">// Text Generation
$registry = AiClient::defaultRegistry();
$model = $registry->getProviderModel('custom_text', '<?php echo esc_html($text_model); ?>');
$result = $model->generateTextResult([
    new \WordPress\AiClient\Messages\DTO\UserMessage([
        new \WordPress\AiClient\Messages\DTO\MessagePart('Your prompt here')
    ])
]);
echo $result->toText();

// Image Generation
$model = $registry->getProviderModel('custom_image', '<?php echo esc_html($image_model); ?>');
$result = $model->generateImageResult([
    new \WordPress\AiClient\Messages\DTO\UserMessage([
        new \WordPress\AiClient\Messages\DTO\MessagePart('Your prompt here')
    ])
]);
$files = $result->toImageFiles();</pre>
            </div>
        </div>
        <?php
    }
}
