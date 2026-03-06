# Custom AI Provider

**Contributors:** duetg
**Tags:** ai, openai, connector, anthropic, google, llm, gpt, claude, gemini
**Requires at least:** 7.0
**Tested up to:** 7.0
**Requires PHP:** 7.4
**License:** MIT
**License URI:** https://opensource.org/licenses/MIT

Connect WordPress to any OpenAI-compatible AI API provider.

## Description

Custom AI Provider allows WordPress to connect to any AI service that provides an OpenAI-compatible API, such as:

* [Ollama](https://ollama.ai/) (local AI)
* [LM Studio](https://lmstudio.ai/) (local AI)
* [MiniMax](https://www.minimax.io/)
* [Moonshot](https://www.moonshot.cn/)
* [DeepSeek](https://www.deepseek.com/)
* And any other OpenAI-compatible API provider

### Features

* Text generation with customizable Base URL and model
* Image generation support
* Works with any OpenAI-compatible API
* Simple configuration through WordPress admin

## Installation

1. Upload the `custom-ai-provider` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Connectors to configure your AI provider API key
4. (Optional) Go to Settings > Custom AI to customize Base URL and model

## Frequently Asked Questions

### How do I find the Base URL for my AI provider?

* Ollama (local): `http://localhost:11434/v1`
* LM Studio (local): `http://localhost:1234/v1`
* MiniMax: `https://api.minimax.chat/v1`
* Moonshot: `https://api.moonshot.cn/v1`
* DeepSeek: `https://api.deepseek.com/v1`
* Other providers: Check their documentation

### Do I need an API key?

Some providers require an API key, while local installations (like Ollama) may not. Leave the API key field empty if your provider doesn't require one.

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

### 0.1.0
* Initial release
* Support for text generation
* Support for image generation

## License

MIT License - see [LICENSE](LICENSE) file for details.
