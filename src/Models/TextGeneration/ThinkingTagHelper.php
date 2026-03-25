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
     * Clean content by extracting thinking tags
     *
     * Extracts content within <think>/<thinking> tags and returns
     * both the clean content and the extracted thinking text.
     *
     * Supports both Chinese and English thinking tags:
     * - Chinese: <think> </think>
     * - English: <thinking> </thinking>
     *
     * Uses preg_match_all to correctly handle multiple thinking blocks.
     *
     * @param string $content The raw content potentially containing thinking tags
     * @return array{content: string, thinking: string}
     */
    public static function clean(string $content): array
    {
        // Use preg_match_all to extract all thinking blocks
        // This handles multiple thinking paragraphs correctly
        if (preg_match_all('#<think(?:ing)?>\s*([\s\S]*?)</think(?:ing)?>#', $content, $matches)) {
            $allThinking = implode("\n\n", array_map('trim', $matches[1]));
            $cleanContent = self::strip($content);
            return [
                'content' => $cleanContent,
                'thinking' => $allThinking
            ];
        }

        // No thinking tags found, return trimmed original content
        return [
            'content' => trim($content),
            'thinking' => ''
        ];
    }

    /**
     * Remove thinking tags from text using regex (for inline cleanup)
     *
     * Unlike clean(), this method removes all <think>...
</think>

 blocks
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
