=== Custom AI Provider ===
Contributors: duetg
Tags: ai, openai, gpt, artificial-intelligence, connector, llm, openai-compatible, custom-provider
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress AI Client to any OpenAI-compatible AI API provider.

== Description ==

Custom AI Provider allows WordPress AI Client to connect to any AI service that provides an OpenAI-compatible API, such as:

* Ollama (local AI)
* LM Studio (local AI)
* MiniMax
* Moonshot
* DeepSeek
* And any other OpenAI-compatible API provider

== Requirements ==

* PHP 7.4 or higher
* For WordPress 6.9, the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) package must be installed
* For WordPress 7.0 and above, no additional changes are required

== Installation ==

1. Upload the `custom-ai-provider` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your API key:
   * For WordPress 7.0+: Go to Settings > Connectors
   * For WordPress 6.9: Set via `CUSTOM_AI_PROVIDER_TEXT_API_KEY` and `CUSTOM_AI_PROVIDER_IMAGE_API_KEY` constants or environment variables
4. Go to Settings > Custom AI to configure your Base URL and model
5. (Optional) Go to Settings > Test AI to verify your configuration

== Frequently Asked Questions ==

= Does this plugin work without the PHP AI Client? =

No, this plugin requires the PHP AI Client plugin to be installed and activated. It provides the custom OpenAI-compatible implementation that the PHP AI Client uses.

For WordPress 7.0 and above, the PHP AI Client is built-in. For WordPress 6.9, you need to install the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) Composer package.

= How do I find the Base URL for my AI provider? =

* Ollama (local): `http://localhost:11434/v1`
* LM Studio (local): `http://localhost:1234/v1`
* MiniMax: `https://api.minimax.io/v1`
* Moonshot: `https://api.moonshot.ai/v1`
* DeepSeek: `https://api.deepseek.com/v1`
* Other providers: Check their documentation

= Do I need an API key? =

Some providers require an API key. For local installations (like Ollama) that don't require authentication, you can enter any dummy string (e.g., "not-required") as the API key.

= How do I use this in my code? =

    use WordPress\AiClient\AiClient;

    $registry = AiClient::defaultRegistry();

    // Text Generation
    $model = $registry->getProviderModel('custom_text', 'gpt-4');
    $result = $model->generateTextResult([
        new \WordPress\AiClient\Messages\DTO\UserMessage([
            new \WordPress\AiClient\Messages\DTO\MessagePart('Your prompt here')
        ])
    ]);
    echo $result->toText();

    // Image Generation
    $model = $registry->getProviderModel('custom_image', 'dall-e-3');
    $result = $model->generateImageResult([
        new \WordPress\AiClient\Messages\DTO\UserMessage([
            new \WordPress\AiClient\Messages\DTO\MessagePart('Your prompt here')
        ])
    ]);
    $files = $result->toImageFiles();

== Changelog ==

= 0.1.0 =
* Initial release
* Support for text generation
* Support for image generation
