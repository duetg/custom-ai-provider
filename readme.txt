=== Custom AI Provider ===
Contributors: duetg
Tags: ai, openai, gpt, artificial-intelligence, connector
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 0.2.0

Connect WordPress AI Client to any OpenAI-compatible AI API provider.

== Description ==

Custom AI Provider allows WordPress AI Client to connect to any AI service that provides an OpenAI-compatible API, such as:

* Ollama (local AI)
* LM Studio (local AI)
* MiniMax
* Moonshot
* DeepSeek
* SiliconFlow
* And any other OpenAI-compatible API provider

= WordPress AI Experiments Integration =

This plugin is compatible with the official [WordPress AI Experiments plugin](https://wordpress.org/plugins/ai/) (version 0.6.0+). When installed together, you can use your custom API provider with WordPress's built-in AI features:

* **Alt Text Generation** - Generates descriptive alt text for images using AI vision models (requires VLM model)
* **Content Summarization** - Summarizes long-form content into digestible overviews
* **Excerpt Generation** - Generates excerpt suggestions from content
* **Image Generation** - Generate featured images and inline images using AI
* **Image Prompt Generation** - Generates a prompt from post content that can be used to generate an image
* **Review Notes** - Reviews post content block-by-block and adds Notes with suggestions for Accessibility, Readability, Grammar, and SEO
* **Title Generation** - Generates title suggestions from content

= Model Capabilities =

**All Models** (text generation endpoint):
* Content Summarization
* Excerpt Generation
* Image Prompt Generation
* Review Notes
* Title Generation

**Vision-Language Models (VLM)** additionally support:
* Alt Text Generation (analyzes image content)

**Image Generation Endpoint**:
* Image Generation (featured images and inline images)

Note: Only Alt Text Generation requires a VLM. All other features work with a standard text-only model.

== Requirements ==

* PHP 7.4 or higher
* WordPress 7.0 or higher (uses built-in Connectors API)
* (Optional) WordPress AI Experiments plugin 0.6.0+ for enhanced integration

== Installation ==

1. Upload the `custom-ai-provider` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your API key at Settings > Connectors
4. Go to Settings > Custom AI to configure your Base URL and model
5. (Optional) Go to Tools > Test AI to verify your configuration

== Frequently Asked Questions ==

= Does this plugin work without WordPress 7.0? =

No, this plugin requires WordPress 7.0 or higher because it uses the built-in Connectors API for API key management.

= Why does Review Notes show all suggestions with the same prefix like [READABILITY]? =

When using Review Notes through the official WordPress AI Client plugin, you may notice that all suggestions appear with the same prefix (e.g., `[READABILITY]`), regardless of the actual review type (Accessibility, Grammar, SEO, etc.).

**This is not a bug.** The root cause is:

1. The WordPress AI Client's system prompt asks the AI model to return suggestions with a `review_type` field, but does not explicitly require the model to always populate this field with the correct type
2. When the model does not return a `review_type` in its JSON response, this plugin defaults the field to `"readability"` to ensure compatibility (otherwise WordPress AI Client would receive malformed data)
3. WordPress AI Client then displays this `review_type` value as the suggestion prefix

In practice, this means the suggestion **text** itself is accurate (the AI still analyzes accessibility, grammar, SEO, etc.), but the **label** shown may not reflect the actual review type. This is a limitation of the system prompt used by WordPress AI Client, not this plugin. The plugin correctly forwards the model's response and fills in a required field when the model omits it.

= How do I find the Base URL for my AI provider? =

* Ollama (local): `http://localhost:11434/v1`
* LM Studio (local): `http://localhost:1234/v1`
* MiniMax: `https://api.minimax.io/v1`
* Moonshot: `https://api.moonshot.ai/v1`
* DeepSeek: `https://api.deepseek.com/v1`
* SiliconFlow: `https://api.siliconflow.cn/v1`
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

== Screenshots ==

1. Custom AI settings screen - Configure Base URL and model for text and image generation.
2. Connectors screen - Manage API keys for your AI provider.
3. Test AI screen - Verify your AI configuration and test text/image generation.

== Changelog ==

= 0.2.0 =
* Added compatibility with WordPress AI Experiments plugin (0.6.0+)
* Added Alt Text Generation support (requires VLM model)
* Added Image Prompt Generation support
* Added Review Notes feature
* Added Title Generation support
* Added Content Summarization support
* Added Excerpt Generation support
* Added thinking/reasoning support for models like DeepSeek, Qwen, MiniMax, Kimi
* Improved JSON response parsing for better compatibility
* Added debug logging (controlled via WP_DEBUG)

= 0.1.0 =
* Initial release
* Support for text generation
* Support for image generation

== Upgrade Notice ==

= 0.2.0 =
This version adds full compatibility with WordPress AI Experiments plugin (0.6.0+). Upgrade to use AI features like Alt Text Generation, Content Summarization, Review Notes, and more.
