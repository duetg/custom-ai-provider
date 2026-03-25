# Custom AI Provider

Connect WordPress AI Client to any OpenAI-compatible AI API provider.

## Description

Custom AI Provider allows WordPress AI Client to connect to any AI service that provides an OpenAI-compatible API, such as:

* [Ollama](https://ollama.com/) (local AI)
* [LM Studio](https://lmstudio.ai/) (local AI)
* [MiniMax](https://www.minimax.io/)
* [Moonshot](https://www.moonshot.ai/)
* [DeepSeek](https://www.deepseek.com/)
* [SiliconFlow](https://siliconflow.cn/)
* And any other OpenAI-compatible API provider

### Features

* Text generation with customizable Base URL and model
* Image generation support
* Works with any OpenAI-compatible API
* Simple configuration through WordPress admin
* Compatible with WordPress AI Experiments plugin

## WordPress AI Experiments Integration

This plugin is compatible with the official [WordPress AI Experiments plugin](https://wordpress.org/plugins/ai/) (version 0.6.0+). When installed together, you can use your custom API provider with WordPress's built-in AI features:

- **Alt Text Generation** - Generates descriptive alt text for images using AI vision models (requires VLM model)
- **Content Summarization** - Summarizes long-form content into digestible overviews
- **Excerpt Generation** - Generates excerpt suggestions from content
- **Image Generation** - Generate featured images and inline images using AI
- **Image Prompt Generation** - Generates a prompt from post content that can be used to generate an image
- **Review Notes** - Reviews post content block-by-block and adds Notes with suggestions for Accessibility, Readability, Grammar, and SEO
- **Title Generation** - Generates title suggestions from content

### Model Capabilities

**Text-Only Models**:
- Content Summarization
- Excerpt Generation
- Image Prompt Generation (used with Image Generation to create featured images)
- Review Notes
- Title Generation

**Vision-Language Models (VLM)**:
- Content Summarization
- Excerpt Generation
- Image Prompt Generation
- Review Notes
- Title Generation
- Alt Text Generation

**Image Generation Models**:
- Image Generation (generate featured images and inline images)

> **Note:** Only Alt Text Generation requires a VLM to analyze images. All other features work with text-only models.

## Requirements

* PHP 7.4 or higher
* WordPress 7.0 or higher (uses built-in Connectors API)
* (Optional) WordPress AI Experiments plugin 0.6.0+ for enhanced integration

## Installation

1. Upload the `custom-ai-provider` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your API key at **Settings > Connectors**
4. Go to **Settings > Custom AI** to configure your Base URL and model
5. (Optional) Go to **Tools > Test AI** to verify your configuration

## Frequently Asked Questions

### Does this plugin work without WordPress 7.0?

No, this plugin requires WordPress 7.0 or higher because it uses the built-in Connectors API for API key management.

### Why does Review Notes show all suggestions with the same prefix like [READABILITY]?

When using Review Notes through the official WordPress AI Client plugin, you may notice that all suggestions appear with the same prefix (e.g., `[READABILITY]`), regardless of the actual review type (Accessibility, Grammar, SEO, etc.).

**This is not a bug.** The root cause is:

1. The WordPress AI Client's system prompt asks the AI model to return suggestions with a `review_type` field, but does not explicitly require the model to always populate this field with the correct type
2. When the model does not return a `review_type` in its JSON response, this plugin defaults the field to `"readability"` to ensure compatibility (otherwise WordPress AI Client would receive malformed data)
3. WordPress AI Client then displays this `review_type` value as the suggestion prefix

In practice, this means the suggestion **text** itself is accurate (the AI still analyzes accessibility, grammar, SEO, etc.), but the **label** shown may not reflect the actual review type. This is a limitation of the system prompt used by WordPress AI Client, not this plugin. The plugin correctly forwards the model's response and fills in a required field when the model omits it.

### How do I find the Base URL for my AI provider?

* Ollama (local): `http://localhost:11434/v1`
* LM Studio (local): `http://localhost:1234/v1`
* MiniMax: `https://api.minimax.io/v1`
* Moonshot: `https://api.moonshot.ai/v1`
* DeepSeek: `https://api.deepseek.com/v1`
* SiliconFlow: `https://api.siliconflow.cn/v1`
* Other providers: Check their documentation

### Do I need an API key?

Some providers require an API key. For local installations (like Ollama) that don't require authentication, you can enter any dummy string (e.g., "not-required") as the API key.

### How do I use this in my code?

```php
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
```

## Changelog

### 0.2.0
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

### 0.1.0
* Initial release
* Support for text generation
* Support for image generation

## License

GNU General Public License v2.0 or later - see [LICENSE](LICENSE) file for details.
