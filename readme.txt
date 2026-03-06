=== Custom AI Provider ===
Contributors: duetg
Tags: ai, openai, connector, anthropic, google, llm, gpt, claude, gemini
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to any OpenAI-compatible AI API provider.

== Description ==

Custom AI Provider allows WordPress to connect to any AI service that provides an OpenAI-compatible API, such as:

* Ollama (local AI)
* LM Studio (local AI)
* MiniMax
* Moonshot
* DeepSeek
* And any other OpenAI-compatible API provider

== Installation ==

1. Upload the `custom-ai-provider` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Connectors to configure your AI provider

== Frequently Asked Questions ==

= How do I find the Base URL for my AI provider? =

* Ollama (local): `http://localhost:11434/v1`
* LM Studio (local): `http://localhost:1234/v1`
* Other providers: Check their documentation

= Do I need an API key? =

Some providers require an API key, while local installations (like Ollama) may not.
Leave the API key field empty if your provider doesn't require one.

== Changelog ==

= 0.1.0 =
* Initial release
* Support for text generation
* Support for image generation
