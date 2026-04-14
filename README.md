# DuetG AI Connector

Connect WordPress AI Client to any OpenAI-compatible AI API provider.

## Description

DuetG AI Connector allows WordPress AI Client to connect to any AI service that provides an OpenAI-compatible API, such as:

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
* Compatible with WordPress AI plugin

## WordPress AI Integration

When installed together with the official [WordPress AI plugin](https://wordpress.org/plugins/ai/), you can use your custom API provider with WordPress's built-in AI features:

- **Alt Text Generation** - Generates descriptive alt text for images using AI vision models (requires VLM model)
- **Content Classification** - AI-powered suggestions for post tags and categories based on content analysis
- **Content Summarization** - Summarizes long-form content into digestible overviews
- **Excerpt Generation** - Generates excerpt suggestions from content
- **Image Generation** - Generate featured images and inline images using AI
- **Image Prompt Generation** - Generates a prompt from post content that can be used to generate an image
- **Meta Description Generation** - Generates meta description suggestions and integrates with various SEO plugins
- **Review Notes** - Reviews post content block-by-block and adds Notes with suggestions for Accessibility, Readability, Grammar, and SEO
- **Title Generation** - Generates title suggestions from content

### Model Capabilities

**All Models** (text generation endpoint):
- Content Classification
- Content Summarization
- Excerpt Generation
- Image Prompt Generation
- Meta Description Generation
- Review Notes
- Title Generation

**Vision-Language Models (VLM)** additionally support:
- Alt Text Generation (analyzes image content)

**Image Generation Endpoint**:
- Image Generation (featured images and inline images)

> **Note:** Only Alt Text Generation requires a VLM. All other features work with a standard text-only model.

## Requirements

* PHP 7.4 or higher
* WordPress 7.0 or higher (uses built-in Connectors API)
* (Optional) WordPress AI plugin for enhanced integration

## Installation

1. Upload the `duetg-ai-connector` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your API key at **Settings > Connectors**
4. Go to **Settings > Custom AI** to configure your Base URL and model
5. (Optional) Go to **Tools > Test AI** to verify your configuration

## Frequently Asked Questions

### How do I enable debug logging?

To enable debug logging, add the following to your `wp-config.php`:

```php
define('DUETGAICON_DEBUG', true);
```

When enabled, debug information will be written to your server's debug log (usually `wp-content/debug.log`). This includes:
* Request/response details for AI API calls
* Provider registration status
* Model handler information

**Note**: Disable debug logging in production environments to avoid performance impact and log file growth.

### Does this plugin work without WordPress 7.0?

No, this plugin requires WordPress 7.0 or higher because it uses the built-in Connectors API for API key management.

### Why do the number of suggestions and notes sometimes not match?

When using Review Notes, you may notice that the number of suggestions returned by the AI does not exactly match the number of notes displayed in the editor.

**This is expected behavior** and has two causes:

1. **Multi-category suggestions**: Some AI models return a single suggestion that applies to multiple review categories (e.g., `review_type: "seo, accessibility"`). The plugin preserves these as-is, so one suggestion may appear under multiple note categories in WordPress AI Client.

2. **Model response format**: The AI model controls the number of suggestions it returns, and WordPress AI Client determines how to display and categorize them. The plugin correctly forwards the model's response without modifying the count.

If you need more consistent results, consider using a model that reliably returns structured JSON with distinct suggestions per category.

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

### Why do local reasoning/thinking models sometimes timeout?

Local reasoning models (like Gemma 4, QwQ, etc.) running on Ollama generate long "thinking" chains before producing their final answer. This process can take 30-60 seconds or more, which can trigger cURL's low speed limit timeout (30 seconds by default).

**Cloud models generally work well** - most cloud API providers (DeepSeek, MiniMax, Moonshot, etc.) respond quickly without timeout issues. If a cloud model frequently times out, it may have unusually long thinking chains - try switching to a different model.

**Recommended solutions for local models:**

1. **Use non-reasoning models** for local AI features. For Ollama, models like `qwen2.5:7b`, `llama3.2:3b`, or `phi3` work well without the timeout issue.

2. **Configure Ollama to keep models loaded:**
   ```bash
   export OLLAMA_KEEP_ALIVE=-1  # Keep model in memory
   ```

If using reasoning models, be aware that WordPress AI features may be slower or timeout. The thinking behavior is controlled by the model, not by the plugin.

### How do I use a local AI provider (like Ollama or LM Studio)?

By default, WordPress blocks requests to localhost and private IP addresses for security (SSRF protection). If you're using a local AI provider, you can disable this protection by adding to your `wp-config.php`:

```php
define('DUETGAICON_ALLOW_LOCAL_URLS', true);
```

**Warning**: Disabling SSRF protection allows requests to private/local IPs. Only enable this if you trust your local AI provider and your server is not directly accessible from the internet.

This setting applies to both text and image models when using local AI providers.

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

### 0.3.0
* Renamed plugin to DuetG AI Connector (from Custom AI Provider)
* Added Content Classification feature (AI-powered suggestions for post tags and categories)
* Added Meta Description Generation feature
* Added WordPress Connector Registry integration for WordPress 7.0+ compatibility
* Added multi-provider response format normalization for MiniMax, Kimi, GLM, and Tencent Hunyuan
* Added automatic JSON keyword injection for DashScope (qwen/glm) models
* Improved JSON extraction using balanced brace counting instead of non-greedy regex
* Added detailed debug logging with URL sanitization
* Added Network Connectivity Test feature to Test AI page for debugging local Ollama connections
* Expanded DUETGAICON_ALLOW_LOCAL_URLS to apply to entire plugin (text and image models)
* Added FAQ section explaining local reasoning model timeout issues
* Removed WordPress AI version requirements from documentation
* Cleaned up debug logging code in CustomTextGenerationModel

### 0.2.3
* Fixed OutputNotEscaped error for image URL in TestPage.php

### 0.2.2
* Fixed namespace declaration order in Settings.php (moved before ABSPATH check)
* Fixed JS file version to use JS file's own mtime instead of plugin.php
* Added function_exists() wrapper to custom_ai_debug() to prevent conflicts
* Fixed duplicate docblock comment in ThinkingTagHelper
* Fixed typo in CustomImageGenerationModel comment ("if not setting" → "if not set")
* Added URL sanitization in debug logs to filter sensitive params (api_key, token, etc.)
* Changed JSON regex from greedy to non-greedy matching for better accuracy
* Fixed dirname level bug in TestPage.php (dirname level 3 → 2)
* Fixed array_map key preservation bug in debug logging
* Inlined URL sanitization logic to avoid nested function definition

### 0.2.1
* Fixed missing resource version in wp_enqueue_script()
* Fixed unsescaped output in test page
* Added direct file access protection to Settings.php
* Added SSRF protection for image URLs (blocks localhost/private IPs by default)
* Added CUSTOM_AI_ALLOW_LOCAL_URLS constant to enable local image URLs when needed
* Updated to WordPress AI plugin (removed "Experiments" branding)

### 0.2.0
* Added compatibility with WordPress AI plugin (0.6.0+)
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
