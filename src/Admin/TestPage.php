<?php
/**
 * Admin test page for DuetG AI Connector
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
use WordPress\CustomAiProvider\Helper;

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
        $plugin_url = plugin_dir_url(dirname(__DIR__, 2) . '/duetg-ai-connector.php');
        $js_url = $plugin_url . 'assets/js/test-page.js';

        wp_enqueue_script(
            'duetgaicon-test-page',
            $js_url,
            [],
            filemtime(dirname(__DIR__, 2) . '/assets/js/test-page.js'),
            true
        );

        // Pass localized data for placeholders
        wp_add_inline_script(
            'duetgaicon-test-page',
            'window.customAiTestPage = ' . json_encode([
                'textPlaceholder' => __('Enter your text prompt...', 'duetg-ai-connector'),
                'imagePlaceholder' => __('Describe the image you want to generate...', 'duetg-ai-connector'),
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
        if (isset($_POST['test_submit']) && check_admin_referer('duetgaicon_test_action')) {
            $provider_type = isset($_POST['provider_type']) ? sanitize_text_field(wp_unslash($_POST['provider_type'])) : 'text';
            $prompt = isset($_POST['prompt']) ? sanitize_text_field(wp_unslash($_POST['prompt'])) : '';

            if (empty($prompt)) {
                $error = __('Please enter a prompt.', 'duetg-ai-connector');
            } else {
                try {
                    $registry = AiClient::defaultRegistry();

                    if ($provider_type === 'text') {
                        if (!$registry->hasProvider('custom_text')) {
                            $error = __('Text provider not registered.', 'duetg-ai-connector');
                        } elseif (!$registry->isProviderConfigured('custom_text')) {
                            $error = __('Text provider not configured. Please add API key via Settings > Connectors.', 'duetg-ai-connector');
                        } elseif (Helper::isLocalUrl(Settings::getTextBaseUrl()) && (!defined('DUETGAICON_ALLOW_LOCAL_URLS') || !DUETGAICON_ALLOW_LOCAL_URLS)) {
                            $error = __('Your Base URL points to a local/private IP address. To use a local AI provider, add <code>define(\'DUETGAICON_ALLOW_LOCAL_URLS\', true);</code> to your wp-config.php.', 'duetg-ai-connector');
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
                            $error = __('Image provider not registered.', 'duetg-ai-connector');
                        } elseif (!$registry->isProviderConfigured('custom_image')) {
                            $error = __('Image provider not configured. Please add API key via Settings > Connectors.', 'duetg-ai-connector');
                        } elseif (Helper::isLocalUrl(Settings::getImageBaseUrl()) && (!defined('DUETGAICON_ALLOW_LOCAL_URLS') || !DUETGAICON_ALLOW_LOCAL_URLS)) {
                            $error = __('Your Base URL points to a local/private IP address. To use a local AI provider, add <code>define(\'DUETGAICON_ALLOW_LOCAL_URLS\', true);</code> to your wp-config.php.', 'duetg-ai-connector');
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
                    Helper::debug('Test page error', ['message' => $e->getMessage()]);
                    $error = __('An error occurred while processing the request. Check debug logs for details.', 'duetg-ai-connector');
                    if (defined('DUETGAICON_DEBUG') && DUETGAICON_DEBUG) {
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

        // Pre-process network test URL for form field
        // Input: use sanitize_text_field for PHPCS; output escaping happens at use site via esc_url()
        $test_url_raw = isset($_POST['test_url']) ? sanitize_text_field(wp_unslash($_POST['test_url'])) : '';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2><?php esc_html_e('Provider Status', 'duetg-ai-connector'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Type', 'duetg-ai-connector'); ?></th>
                            <th><?php esc_html_e('Base URL', 'duetg-ai-connector'); ?></th>
                            <th><?php esc_html_e('Model', 'duetg-ai-connector'); ?></th>
                            <th><?php esc_html_e('API Key Status', 'duetg-ai-connector'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('Text Generation', 'duetg-ai-connector'); ?></strong></td>
                            <td><?php echo esc_html($text_base_url); ?></td>
                            <td><?php echo esc_html($text_model); ?></td>
                            <td>
                                <?php
                                $text_api_key = self::get_api_key_status('text');

                                if ($text_api_key) {
                                    echo '<span style="color: green;">&#10004; ' . esc_html__('Configured', 'duetg-ai-connector') . '</span>';
                                } else {
                                    echo '<span style="color: red;">&#10008; ' . esc_html__('Not Configured', 'duetg-ai-connector') . '</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Image Generation', 'duetg-ai-connector'); ?></strong></td>
                            <td><?php echo esc_html($image_base_url); ?></td>
                            <td><?php echo esc_html($image_model); ?></td>
                            <td>
                                <?php
                                $image_api_key = self::get_api_key_status('image');

                                if ($image_api_key) {
                                    echo '<span style="color: green;">&#10004; ' . esc_html__('Configured', 'duetg-ai-connector') . '</span>';
                                } else {
                                    echo '<span style="color: red;">&#10008; ' . esc_html__('Not Configured', 'duetg-ai-connector') . '</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if (defined('DUETGAICON_ALLOW_LOCAL_URLS') && DUETGAICON_ALLOW_LOCAL_URLS): ?>
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2><?php esc_html_e('Network Connectivity Test', 'duetg-ai-connector'); ?></h2>
                <p><?php esc_html_e('Test if your WordPress environment can reach a specific URL. Useful for debugging local AI connections.', 'duetg-ai-connector'); ?></p>
                <form method="post" style="margin-top: 15px;">
                    <?php wp_nonce_field('duetgaicon_network_test_action'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="test_url"><?php esc_html_e('URL to Test', 'duetg-ai-connector'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="test_url" id="test_url" class="large-text" value="<?php echo $test_url_raw ? esc_url($test_url_raw) : 'http://127.0.0.1:11434/v1/models'; ?>" placeholder="http://127.0.0.1:11434/v1/models" />
                                <p class="description"><?php esc_html_e('Enter the full URL including path to test connectivity.', 'duetg-ai-connector'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(esc_html__('Test Connection', 'duetg-ai-connector'), 'primary', 'network_test_submit', false); ?>
                </form>

                <?php
                // Handle network test submission
                if (isset($_POST['network_test_submit']) && check_admin_referer('duetgaicon_network_test_action')) {
                    $test_url = isset($_POST['test_url']) ? esc_url_raw(wp_unslash($_POST['test_url'])) : '';

                    if (empty($test_url)) {
                        echo '<div class="notice notice-error" style="margin-top: 15px;"><p>' . esc_html__('Please enter a URL to test.', 'duetg-ai-connector') . '</p></div>';
                    } else {
                        // Note: This diagnostic tool intentionally bypasses SSRF protection to test local URLs.
                        // It is restricted to administrators only (manage_options capability) and protected by nonce.
                        // SSL verification is disabled for local URLs since local servers often use self-signed certificates.
                        $is_local_url = Helper::isLocalUrl($test_url);

                        $request_args = array(
                            'timeout' => 10,
                            'headers' => array(
                                'Accept' => 'application/json',
                            ),
                        );

                        // Only disable SSL verify for local URLs (self-signed certs common)
                        if ($is_local_url) {
                            $request_args['sslverify'] = false;
                        }

                        $response = wp_remote_get($test_url, $request_args);

                        if (is_wp_error($response)) {
                            echo '<div class="notice notice-error" style="margin-top: 15px;">';
                            echo '<p><strong>' . esc_html__('Connection Failed', 'duetg-ai-connector') . ':</strong> ' . esc_html($response->get_error_message()) . '</p>';
                            echo '<p><strong>' . esc_html__('Possible Cause', 'duetg-ai-connector') . ':</strong> ';
                            if (strpos($response->get_error_message(), 'A valid URL') !== false || strpos($response->get_error_message(), 'blocked') !== false) {
                                esc_html_e('WordPress SSRF protection is blocking this URL. Localhost/private IPs are blocked by default.', 'duetg-ai-connector');
                            } else {
                                esc_html_e('The server may be unreachable or the URL may be invalid.', 'duetg-ai-connector');
                            }
                            echo '</p></div>';
                        } else {
                            $code = wp_remote_retrieve_response_code($response);
                            $body = wp_remote_retrieve_body($response);

                            echo '<div class="notice notice-success" style="margin-top: 15px;">';
                            echo '<p><strong>' . esc_html__('Connection Successful!', 'duetg-ai-connector') . '</strong></p>';
                            echo '<p>' . esc_html__('HTTP Status Code', 'duetg-ai-connector') . ': ' . esc_html($code) . '</p>';

                            if ($code == 200) {
                                // Try to parse as JSON (Ollama models endpoint)
                                $data = json_decode($body, true);
                                if (isset($data['models']) && is_array($data['models'])) {
                                    echo '<p><strong>' . esc_html__('Available Models:', 'duetg-ai-connector') . '</strong></p>';
                                    echo '<ul style="margin: 10px 0;">';
                                    foreach ($data['models'] as $model) {
                                        echo '<li>' . esc_html($model['name'] ?? json_encode($model)) . '</li>';
                                    }
                                    echo '</ul>';
                                } else {
                                    echo '<p>' . esc_html__('Response Body Preview:', 'duetg-ai-connector') . '</p>';
                                    echo '<pre style="background: #f0f0f0; padding: 10px; max-height: 200px; overflow-y: auto;">' . esc_html(substr($body, 0, 500)) . '</pre>';
                                }
                            }
                            echo '</div>';
                        }
                    }
                }
                ?>
            </div>
            <?php endif; ?>

            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2><?php esc_html_e('Test AI', 'duetg-ai-connector'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('duetgaicon_test_action'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Provider Type', 'duetg-ai-connector'); ?></th>
                            <td>
                                <select name="provider_type" id="provider_type">
                                    <option value="text" <?php selected($provider_type, 'text'); ?>><?php esc_html_e('Text Generation', 'duetg-ai-connector'); ?></option>
                                    <option value="image" <?php selected($provider_type, 'image'); ?>><?php esc_html_e('Image Generation', 'duetg-ai-connector'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="prompt"><?php esc_html_e('Prompt', 'duetg-ai-connector'); ?></label>
                            </th>
                            <td>
                                <textarea
                                    name="prompt"
                                    id="prompt"
                                    rows="4"
                                    class="large-text"
                                    placeholder="<?php echo $provider_type === 'text' ? esc_attr__('Enter your text prompt...', 'duetg-ai-connector') : esc_attr__('Describe the image you want to generate...', 'duetg-ai-connector'); ?>"
                                ><?php echo esc_textarea($prompt); ?></textarea>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(esc_html__('Generate', 'duetg-ai-connector'), 'primary', 'test_submit', false); ?>
                </form>

                <?php if ($error): ?>
                    <div class="notice notice-error" style="margin-top: 20px;">
                        <p><strong><?php esc_html_e('Error', 'duetg-ai-connector'); ?>:</strong> <?php echo wp_kses_post($error); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($result): ?>
                    <div class="notice notice-success" style="margin-top: 20px;">
                        <p><strong><?php esc_html_e('Success!', 'duetg-ai-connector'); ?></strong></p>
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
                                            $url = esc_url($dataUri);
                                        }
                                    } else {
                                        $url = esc_url($file->getUrl());
                                    }
                                    if ($url): ?>
                                    <div style="margin-top: 10px;">
                                        <img src="<?php echo esc_url($url); ?>" style="max-width: 500px; height: auto; border: 1px solid #ccc;" />
                                    </div>
                                    <?php endif;
                                endforeach; ?>
                            <?php else: ?>
                                <p><?php echo esc_html__('No image files returned. Result type: ', 'duetg-ai-connector') . esc_html(get_class($result)); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2><?php esc_html_e('How to Use', 'duetg-ai-connector'); ?></h2>
                <p><?php esc_html_e('To use this provider in your code:', 'duetg-ai-connector'); ?></p>
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
