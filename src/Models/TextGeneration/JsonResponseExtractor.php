<?php
/**
 * JSON Response Extractor
 *
 * Handles extraction and parsing of JSON responses from AI models.
 * Used by ReviewNotesNormalizer to handle various JSON formats returned by models.
 *
 * @package CustomAiProvider\Models\TextGeneration
 */

namespace WordPress\CustomAiProvider\Models\TextGeneration;

/**
 * Extracts and parses JSON from AI text responses
 */
class JsonResponseExtractor
{
    /**
     * Extract JSON from text response and normalize to Review Notes format
     *
     * Many models don't properly support response_format, so they output plain text
     * that contains JSON-like content. This tries to extract valid JSON.
     *
     * @param string $text
     * @param ReviewNotesNormalizer $normalizer Normalizer to use for post-processing
     * @return array|null Normalized suggestions array or null if extraction failed
     */
    public function extract(string $text, ReviewNotesNormalizer $normalizer): ?array
    {
        // Try direct JSON decode first
        $decoded = json_decode($text, true);
        $jsonError = json_last_error();
        if (is_array($decoded) && $jsonError === JSON_ERROR_NONE) {
            return $normalizer->normalize($decoded);
        }

        // Try to find JSON in the text using balanced brace counting
        // This handles nested JSON correctly unlike non-greedy regex
        $jsonStart = null;
        $braceCount = 0;
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];

            // Handle escape sequences
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            // Handle string boundaries
            if ($char === '"' && !$escaped) {
                $inString = !$inString;
                continue;
            }

            // Skip if inside string
            if ($inString) {
                continue;
            }

            // Find opening brace
            if ($char === '{' && $jsonStart === null) {
                $jsonStart = $i;
                $braceCount = 1;
                continue;
            }

            // Track brace depth
            if ($jsonStart !== null) {
                if ($char === '{') {
                    $braceCount++;
                } elseif ($char === '}') {
                    $braceCount--;
                    if ($braceCount === 0) {
                        // Found complete JSON object
                        $jsonStr = substr($text, $jsonStart, $i - $jsonStart + 1);
                        $decoded = json_decode($jsonStr, true);
                        if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                            return $normalizer->normalize($decoded);
                        }
                        // Not valid JSON, continue searching
                        $jsonStart = null;
                    }
                }
            }
        }

        // Try to find JSON array [...] using balanced bracket counting
        $arrStart = null;
        $bracketCount = 0;
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"' && !$escaped) {
                $inString = !$inString;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '[' && $arrStart === null) {
                $arrStart = $i;
                $bracketCount = 1;
                continue;
            }

            if ($arrStart !== null) {
                if ($char === '[') {
                    $bracketCount++;
                } elseif ($char === ']') {
                    $bracketCount--;
                    if ($bracketCount === 0) {
                        $jsonStr = substr($text, $arrStart, $i - $arrStart + 1);
                        $decoded = json_decode($jsonStr, true);
                        if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                            return $normalizer->normalize(['suggestions' => $decoded]);
                        }
                        $arrStart = null;
                    }
                }
            }
        }

        // Try to find JSON in markdown code blocks
        if (preg_match('/```json\s*([\s\S]*?)```/', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                return $normalizer->normalize(['suggestions' => $decoded]);
            }
        }

        // Try to reconstruct fragmented JSON (e.g., split across lines with [READABILITY] prefix)
        $reconstructed = $this->reconstructFragmentedJson($text);
        if ($reconstructed !== null) {
            $decoded = json_decode($reconstructed, true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                return $normalizer->normalize($decoded);
            }
        }

        // FALLBACK: If JSON parsing fails, try to extract suggestions from plain text
        return $this->extractFromPlainText($text, $normalizer);
    }

    /**
     * Reconstruct fragmented JSON where key-value pairs are split across lines
     *
     * Handles format like:
     *   [TAG] "suggestion": "Replace the heading..."
     *   [TAG] "priority": 2
     *
     * Supports any tag like [READABILITY], [GRAMMAR], [ACCESSIBILITY, SEO], etc.
     * Only the first tag is used as review_type.
     *
     * @param string $text
     * @return string|null Reconstructed JSON string or null if not recognized
     */
    private function reconstructFragmentedJson(string $text): ?string
    {
        // Check if text has the fragmented pattern (any [TAG] "key": pattern)
        if (!preg_match('/^\s*\[[^\]]+\]\s*"[^"]+"\s*:/im', $text)) {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        $objects = [];
        $currentObject = null;
        $currentText = '';

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Skip lines that look like they end a previous value (contain trailing comma)
            if (preg_match('/^[^"]*"[^"]*,\s*$/', $line)) {
                // This line ends with a comma, it's a continuation - strip the comma and add to current text
                $currentText .= ' ' . trim($line, ',');
                continue;
            }

            // Try to match [TAG] "key": "value" or [TAG] "key": value
            $pattern = '/^\[([^\]]+)\]\s*"([^"]+)"\s*:\s*(.+)/i';
            if (preg_match($pattern, $line, $matches)) {
                $tag = trim($matches[1]);
                $key = trim($matches[2]);
                $value = trim($matches[3], '",');

                // Extract first tag as review_type (e.g., "ACCESSIBILITY, SEO" -> "ACCESSIBILITY")
                $reviewType = strtolower(explode(',', $tag)[0]);

                // If we have accumulated text, add it as suggestion to current object
                if (!empty($currentText) && $currentObject !== null) {
                    $currentObject['text'] = $currentText;
                    $currentText = '';
                }

                // If this is a priority field and we have a current object, set it
                if (strtolower($key) === 'priority' && $currentObject !== null) {
                    $currentObject['priority'] = intval($value);
                    continue;
                }

                // Save previous object
                if ($currentObject !== null) {
                    $objects[] = $currentObject;
                }

                // Start new object
                $currentObject = ['review_type' => $reviewType];
                if (strtolower($key) === 'suggestion' || strtolower($key) === 'issue' || strtolower($key) === 'content') {
                    $currentText = $value;
                } else {
                    $currentObject[$key] = $value;
                }
            }
        }

        // Handle last accumulated text
        if (!empty($currentText) && $currentObject !== null) {
            $currentObject['text'] = $currentText;
        }

        // Don't forget the last object
        if ($currentObject !== null) {
            $objects[] = $currentObject;
        }

        if (empty($objects)) {
            return null;
        }

        // Post-process objects: handle nested JSON and set defaults
        $processedObjects = [];
        foreach ($objects as $obj) {
            // Set default priority if not set
            if (!isset($obj['priority'])) {
                $obj['priority'] = 1;
            }
            // Ensure text field exists
            if (isset($obj['suggestion'])) {
                $obj['text'] = $obj['suggestion'];
                unset($obj['suggestion']);
            }
            if (isset($obj['issue'])) {
                $obj['text'] = $obj['issue'];
                unset($obj['issue']);
            }
            if (isset($obj['content'])) {
                $obj['text'] = $obj['content'];
                unset($obj['content']);
            }
            // Handle nested JSON in text field (model sometimes returns JSON inside JSON)
            if (isset($obj['text']) && is_string($obj['text'])) {
                $nestedJson = json_decode($obj['text'], true);
                if (is_array($nestedJson) && json_last_error() === JSON_ERROR_NONE) {
                    // Check if text contains a single suggestion object
                    if (isset($nestedJson['suggestion']) || isset($nestedJson['issue']) || isset($nestedJson['content']) || isset($nestedJson['text'])) {
                        $obj['text'] = $nestedJson['suggestion'] ?? $nestedJson['issue'] ?? $nestedJson['content'] ?? $nestedJson['text'] ?? '';
                        if (isset($nestedJson['priority']) && is_numeric($nestedJson['priority'])) {
                            $obj['priority'] = intval($nestedJson['priority']);
                        }
                        if (isset($nestedJson['review_type'])) {
                            $obj['review_type'] = $nestedJson['review_type'];
                        }
                    } elseif (isset($nestedJson[0]) && is_array($nestedJson[0])) {
                        // text contains an array of suggestions
                        foreach ($nestedJson as $nestedItem) {
                            $newObj = [
                                'review_type' => $nestedItem['review_type'] ?? $obj['review_type'] ?? 'readability',
                                'text' => $nestedItem['suggestion'] ?? $nestedItem['issue'] ?? $nestedItem['content'] ?? $nestedItem['text'] ?? '',
                                'priority' => $nestedItem['priority'] ?? $obj['priority'] ?? 1
                            ];
                            $processedObjects[] = $newObj;
                        }
                        continue;
                    } elseif (isset($nestedJson[0]) && is_string($nestedJson[0])) {
                        // text contains an array of strings
                        foreach ($nestedJson as $str) {
                            if (is_string($str) && !empty(trim($str))) {
                                $processedObjects[] = [
                                    'review_type' => $obj['review_type'] ?? 'readability',
                                    'text' => trim($str),
                                    'priority' => $obj['priority'] ?? 1
                                ];
                            }
                        }
                        continue;
                    }
                }
                // Clean up tag prefixes like [SEO], [ACCESSIBILITY], etc. from text
                $obj['text'] = $this->cleanTagPrefix($obj['text']);
            }
            $processedObjects[] = $obj;
        }
        $objects = $processedObjects;

        // If we have multiple objects, wrap in array with suggestions key
        if (count($objects) > 1) {
            return json_encode(['suggestions' => $objects]);
        }

        // Single object
        return json_encode($objects[0]);
    }

    /**
     * Extract suggestions from plain text response
     *
     * This is a fallback when JSON parsing fails. It tries to parse:
     * - Line-by-line suggestions
     * - Numbered lists (1., 2., etc.)
     * - Bullet points (-, *, •)
     *
     * @param string $text
     * @param ReviewNotesNormalizer $normalizer
     * @return array|null
     */
    private function extractFromPlainText(string $text, ReviewNotesNormalizer $normalizer): ?array
    {
        if (empty(trim($text))) {
            return null;
        }

        // Clean up the text first - remove thinking tags if present
        $text = ThinkingTagHelper::strip($text);
        $text = trim($text);

        // Skip if text is empty after cleanup
        if (empty($text)) {
            return null;
        }

        // Skip if text looks like an error message or empty response
        if (stripos($text, 'no suggestions') !== false ||
            stripos($text, 'no issues') !== false ||
            stripos($text, 'no problems') !== false ||
            stripos($text, '[]') !== false ||
            $text === '[]' ||
            $text === '{}') {
            return ['suggestions' => []];
        }

        // Try to handle fragmented JSON format before splitting by lines
        $reconstructed = $this->reconstructFragmentedJson($text);
        if ($reconstructed !== null) {
            $decoded = json_decode($reconstructed, true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                return $normalizer->normalize($decoded);
            }
        }

        // Split by newlines
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $suggestions = [];
        $pendingSuggestion = null; // Track suggestion awaiting priority

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Remove common list prefixes: 1., 2., -, *, •, etc.
            $cleanLine = preg_replace('/^[\d]+[\.\)]\s*/', '', $line);
            $cleanLine = preg_replace('/^[-*•]\s*/', '', $cleanLine);
            $cleanLine = trim($cleanLine);

            // Skip if line is too short or looks like a header/label
            if (strlen($cleanLine) < 10) {
                continue;
            }

            // Skip common non-suggestion patterns
            if (stripos($cleanLine, 'suggestions') !== false && stripos($cleanLine, ':') === false) {
                continue;
            }

            // Detect priority-only lines (including [TAG] "priority": 2 pattern)
            if (preg_match('/^\[[^\]]+\]\s*"priority"\s*:\s*(\d+)$/i', $cleanLine, $priorityMatches) ||
                preg_match('/^priority\s*:\s*(\d+)$/i', $cleanLine, $priorityMatches) ||
                preg_match('/^priority\s+(\d+)$/i', $cleanLine, $priorityMatches) ||
                preg_match('/^\[priority\s*:\s*(\d+)\]$/i', $cleanLine, $priorityMatches)) {
                if ($pendingSuggestion !== null) {
                    $pendingSuggestion['priority'] = intval($priorityMatches[1]);
                    $suggestions[] = $pendingSuggestion;
                    $pendingSuggestion = null;
                }
                continue;
            }

            // Extract priority using extractPriorityFromText
            $extracted = $this->extractPriorityFromText($cleanLine);
            $cleanLine = $extracted['text'];
            $priority = $extracted['priority'];

            // Skip if line is empty after cleanup
            if (empty($cleanLine)) {
                continue;
            }

            // If we have a pending suggestion with default priority, save it with extracted priority
            if ($pendingSuggestion !== null) {
                $pendingSuggestion['priority'] = $priority;
                $suggestions[] = $pendingSuggestion;
                $pendingSuggestion = null;
            }

            // Check if this looks like it might have a priority on the next line
            // (starts with [TAG] "suggestion": or similar pattern)
            if (preg_match('/^\[([^\]]+)\]\s*"suggestion"\s*:/i', $cleanLine, $tagMatches) ||
                preg_match('/^\[([^\]]+)\]\s*"issue"\s*:/i', $cleanLine, $tagMatches) ||
                preg_match('/^\[([^\]]+)\]\s*"content"\s*:/i', $cleanLine, $tagMatches)) {
                // Extract the first tag as review_type
                $reviewType = strtolower(explode(',', $tagMatches[1])[0]);
                // Extract the actual text value
                if (preg_match('/:\s*"(.+)"/', $cleanLine, $textMatches)) {
                    $pendingSuggestion = [
                        'review_type' => $reviewType,
                        'text' => $textMatches[1],
                        'priority' => 1 // default, will be updated if priority line follows
                    ];
                    continue;
                }
            }

            $suggestions[] = [
                'review_type' => 'readability',
                'text' => $cleanLine,
                'priority' => $priority
            ];
        }

        // Don't forget the pending suggestion if exists
        if ($pendingSuggestion !== null) {
            $suggestions[] = $pendingSuggestion;
        }

        if (empty($suggestions)) {
            // If no lines extracted, treat the entire text as one suggestion
            if (strlen($text) > 10) {
                $suggestions[] = [
                    'review_type' => 'readability',
                    'text' => $text,
                    'priority' => 1
                ];
            }
        }

        return empty($suggestions) ? null : ['suggestions' => $suggestions];
    }

    /**
     * Clean tag prefixes from text (e.g., [SEO], [ACCESSIBILITY], [READABILITY], ["SEO"])
     *
     * When model returns text with embedded tags like "[SEO] Some suggestion",
     * this removes the tag prefix and returns clean text.
     *
     * @param string $text
     * @return string
     */
    public function cleanTagPrefix(string $text): string
    {
        // Remove leading [TAG] pattern where TAG is uppercase letters, possibly with comma
        // e.g., "[SEO]", "[ACCESSIBILITY, SEO]", "[READABILITY]", "["SEO"]"
        $text = preg_replace('/^\[\"?[A-Z][A-Z,\s]*\"?\]\s*/i', '', $text);
        return trim($text);
    }

    /**
     * Extract priority from text if explicitly labeled
     *
     * Matches: "[Priority: 1]", "(priority: 1)", "Priority: 1", "[1]"
     * Does NOT match "(1)" or "(2)" as those are likely part of content
     *
     * @param string $text
     * @return array ['text' => string, 'priority' => int]
     */
    public function extractPriorityFromText(string $text): array
    {
        $priority = 1;

        if (preg_match('/\[Priority:\s*(\d+)\]/i', $text, $matches) ||
            preg_match('/^\(priority:\s*(\d+)\)$/i', $text, $matches) ||
            preg_match('/\bPriority:\s*(\d+)$/i', $text, $matches) ||
            preg_match('/^\[(\d+)\]$/', $text, $matches)) {
            $priority = intval($matches[1]);
            $text = preg_replace('/\[Priority:\s*\d+\]/i', '', $text);
            $text = preg_replace('/^\(priority:\s*\d+\)$/i', '', $text);
            $text = preg_replace('/\bPriority:\s*\d+$/i', '', $text);
            $text = preg_replace('/^\[\d+\]$/', '', $text);
            $text = trim($text);
        }

        return ['text' => $text, 'priority' => $priority];
    }
}
