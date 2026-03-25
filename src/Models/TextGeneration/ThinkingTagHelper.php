<?php
/**
 * Thinking Tag Helper
 *
 * Utility class for extracting and cleaning thinking/reasoning tags
 * from AI model responses. Supports both Chinese and English tags.
 *
 * @package CustomAiProvider\Models\TextGeneration
 */

namespace WordPress\CustomAiProvider\Models\TextGeneration;

/**
 * Helper class for cleaning thinking tags from AI response content
 */
class ThinkingTagHelper
{
    /**
     * Supported closing tags for thinking content
     *
     * @var array<string>
     */
    private const CLOSE_TAGS = ['</think>', '</thinking>', '<\\/think>', '<\\/thinking>'];

    /**
     * Clean content by extracting thinking tags
     *
     * Extracts content within <think>/<thinking> tags and returns
     * both the clean content and the extracted thinking text.
     *
     * Supports both Chinese and English thinking tags:
     * - Chinese: <think> </think>
     * - English: <thinking> </thinking>
     *
     * @param string $content The raw content potentially containing thinking tags
     * @return array{content: string, thinking: string}
     */
    public static function clean(string $content): array
    {
        // Find the first closing tag
        $closePos = false;
        $foundCloseTag = '';
        foreach (self::CLOSE_TAGS as $closeTag) {
            $pos = strpos($content, $closeTag);
            if ($pos !== false && ($closePos === false || $pos < $closePos)) {
                $closePos = $pos;
                $foundCloseTag = $closeTag;
            }
        }

        // If no closing tag found, return trimmed original content
        if ($closePos === false) {
            return [
                'content' => trim($content),
                'thinking' => ''
            ];
        }

        // Everything before the closing tag is thinking content
        // (could be after opening tag, or could be raw thinking if API removed opening tag)
        $thinking = trim(substr($content, 0, $closePos));

        // Remove opening tags from thinking content if present
        $thinking = preg_replace('#^<think(?:ing)?>\s*#', '', $thinking);

        // Everything after the closing tag is the actual content
        $cleanContent = trim(substr($content, $closePos + strlen($foundCloseTag)));

        return [
            'content' => $cleanContent,
            'thinking' => $thinking
        ];
    }

    /**
     * Remove thinking tags from text using regex (for inline cleanup)
     *
     * Unlike clean(), this method removes all <think>...</think> blocks
     * entirely without extracting. Useful for cleaning plain text.
     *
     * @param string $text
     * @return string Cleaned text
     */
    public static function strip(string $text): string
    {
        $text = preg_replace('#<think(?:ing)?>\s*[\s\S]*?</think(?:ing)?>#', '', $text);
        return trim($text);
    }
}
