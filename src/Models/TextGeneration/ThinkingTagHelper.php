<?php
/**
 * Helper for handling thinking tags in AI responses
 *
 * @package DuetGAIConnector\Models\TextGeneration
 */

namespace WordPress\DuetGAIConnector\Models\TextGeneration;

/**
 * Helper class for cleaning thinking tags from AI responses
 *
 * Supports both Chinese and English thinking tag formats:
 * - Chinese: <think> </think>
 * - English: <thinking> </thinking>
 */
class ThinkingTagHelper
{
    /**
     * Clean thinking tags from content and extract thinking text
     *
     * Uses preg_match_all to correctly handle multiple thinking blocks.
     * Supports both Chinese and English thinking tags:
     * - Chinese: <think> </think>
     * - English: <thinking> </thinking>
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
     * Unlike clean(), this method removes all thinking blocks
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