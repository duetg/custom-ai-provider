<?php
/**
 * Admin test page for Custom AI Provider
 *
 * @package CustomAiProvider\Admin
 */

namespace WordPress\CustomAiProvider\Admin;

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
     * Render the test page
     */
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $result = null;
        $error = null;
        $provider_type = isset($_POST['provider_type']) ? $_POST['provider_type'] : 'text';
        $prompt = isset($_POST['prompt']) ? $_POST['prompt'] : '';

        // Handle form submission
        if (isset($_POST['test_submit']) && check_admin_referer('custom_ai_test_action')) {
            if (empty($prompt)) {
                $error = 'Please enter a prompt.';
            } else {
                try {
                    $registry = AiClient::defaultRegistry();

                    if ($provider_type === 'text') {
                        if (!$registry->hasProvider('custom_text')) {
                            $error = 'Text provider not registered.';
                        } elseif (!$registry->isProviderConfigured('custom_text')) {
                            $error = 'Text provider not configured. Please add API key in Settings > Connectors.';
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
                            $error = 'Image provider not registered.';
                        } elseif (!$registry->isProviderConfigured('custom_image')) {
                            $error = 'Image provider not configured. Please add API key in Settings > Connectors.';
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
                    $error = $e->getMessage();
                }
            }
        }

        // Get current settings
        $text_base_url = get_option(Settings::TEXT_BASE_URL_OPTION, '') ?: Settings::DEFAULT_TEXT_BASE_URL;
        $text_model = CustomTextProvider::getModelId();
        $image_base_url = get_option(Settings::IMAGE_BASE_URL_OPTION, '') ?: Settings::DEFAULT_IMAGE_BASE_URL;
        $image_model = CustomImageProvider::getModelId();

        ?>
        <script>
        function updatePromptPlaceholder() {
            var select = document.getElementById('provider_type');
            var textarea = document.getElementById('prompt');
            if (select.value === 'text') {
                textarea.placeholder = '<?php echo esc_js(__('Enter your text prompt...', 'custom-ai-provider')); ?>';
            } else {
                textarea.placeholder = '<?php echo esc_js(__('Describe the image you want to generate...', 'custom-ai-provider')); ?>';
            }
        }
        </script>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2><?php _e('Provider Status', 'custom-ai-provider'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Type', 'custom-ai-provider'); ?></th>
                            <th>Base URL</th>
                            <th><?php _e('Model', 'custom-ai-provider'); ?></th>
                            <th>API Key <?php _e('Status', 'custom-ai-provider'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php _e('Text Generation', 'custom-ai-provider'); ?></strong></td>
                            <td><?php echo esc_html($text_base_url); ?></td>
                            <td><?php echo esc_html($text_model); ?></td>
                            <td>
                                <?php
                                $text_api_key = get_option(Settings::TEXT_API_KEY_OPTION, '');

                                if (!empty($text_api_key)) {
                                    echo '<span style="color: green;">&#10004; ' . __('Configured', 'custom-ai-provider') . '</span>';
                                } else {
                                    echo '<span style="color: red;">&#10008; ' . __('Not Configured', 'custom-ai-provider') . '</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Image Generation', 'custom-ai-provider'); ?></strong></td>
                            <td><?php echo esc_html($image_base_url); ?></td>
                            <td><?php echo esc_html($image_model); ?></td>
                            <td>
                                <?php
                                $image_api_key = get_option(Settings::IMAGE_API_KEY_OPTION, '');

                                if (!empty($image_api_key)) {
                                    echo '<span style="color: green;">&#10004; ' . __('Configured', 'custom-ai-provider') . '</span>';
                                } else {
                                    echo '<span style="color: red;">&#10008; ' . __('Not Configured', 'custom-ai-provider') . '</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2><?php _e('Test AI', 'custom-ai-provider'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('custom_ai_test_action'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Provider Type', 'custom-ai-provider'); ?></th>
                            <td>
                                <select name="provider_type" id="provider_type" onchange="updatePromptPlaceholder()">
                                    <option value="text" <?php selected($provider_type, 'text'); ?>><?php _e('Text Generation', 'custom-ai-provider'); ?></option>
                                    <option value="image" <?php selected($provider_type, 'image'); ?>><?php _e('Image Generation', 'custom-ai-provider'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="prompt"><?php _e('Prompt', 'custom-ai-provider'); ?></label>
                            </th>
                            <td>
                                <textarea
                                    name="prompt"
                                    id="prompt"
                                    rows="4"
                                    class="large-text"
                                    placeholder="<?php echo $provider_type === 'text' ? __('Enter your text prompt...', 'custom-ai-provider') : __('Describe the image you want to generate...', 'custom-ai-provider'); ?>"
                                ><?php echo esc_textarea($prompt); ?></textarea>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('Generate', 'custom-ai-provider'), 'primary', 'test_submit', false); ?>
                </form>

                <?php if ($error): ?>
                    <div class="notice notice-error" style="margin-top: 20px;">
                        <p><strong><?php _e('Error', 'custom-ai-provider'); ?>:</strong> <?php echo esc_html($error); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($result): ?>
                    <div class="notice notice-success" style="margin-top: 20px;">
                        <p><strong><?php _e('Success!', 'custom-ai-provider'); ?></strong></p>
                        <?php if ($provider_type === 'text'): ?>
                            <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto; max-height: 300px;"><?php echo esc_html($result->toText()); ?></pre>
                        <?php else: ?>
                            <?php
                            $files = $result->toImageFiles();
                            if (!empty($files)):
                                foreach ($files as $file): ?>
                                    <div style="margin-top: 10px;">
                                        <img src="<?php echo esc_url($file->getUrl()); ?>" style="max-width: 500px; height: auto;" />
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h2><?php _e('How to Use', 'custom-ai-provider'); ?></h2>
                <p><?php _e('To use this provider in your code:', 'custom-ai-provider'); ?></p>
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
