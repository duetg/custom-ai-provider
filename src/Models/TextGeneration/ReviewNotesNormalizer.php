<?php
/**
 * Review Notes Normalizer
 *
 * Handles normalization of AI responses to Review Notes format.
 * Review Notes expects: {"suggestions": [{"review_type": "...", "text": "...", "priority": 1}]}
 *
 * @package CustomAiProvider\Models\TextGeneration
 */

namespace WordPress\CustomAiProvider\Models\TextGeneration;

/**
 * Normalizes AI responses to Review Notes format
 */
class ReviewNotesNormalizer
{
    /**
     * Normalize a response to Review Notes format
     *
     * @param array $data
     * @return array
     */
    public function normalize(array $data): array
    {
        // Case 1: Single object like {"content": "...", "priority": 1}
        $singleObjectResult = $this->normalizeSingleObjectSuggestion($data);
        if ($singleObjectResult !== null) {
            return $singleObjectResult;
        }

        // Case 2: Already has "suggestions" key
        if (isset($data['suggestions']) && is_array($data['suggestions'])) {
            return $this->normalizeSuggestionsArray($data['suggestions']);
        }

        // Case 3: Empty array
        if (empty($data)) {
            return ['suggestions' => []];
        }

        // Case 4: Direct array of suggestions (no wrapper object)
        if (is_array($data) && !empty($data)) {
            return $this->normalizeDirectArray($data);
        }

        // Fallback: empty data
        return ['suggestions' => []];
    }

    /**
     * Extract JSON from text response
     *
     * Many models don't properly support response_format, so they output plain text
     * that contains JSON-like content. This tries to extract valid JSON.
     *
     * @param string $text
     * @return array|null
     */
    public function extractJsonFromText(string $text): ?array
    {
        // Try direct JSON decode first
        $decoded = json_decode($text, true);
        $jsonError = json_last_error();
        if (is_array($decoded) && $jsonError === JSON_ERROR_NONE) {
            return $this->normalize($decoded);
        }

        // Try to find JSON in the text using regex
        // Add length check to prevent ReDoS via catastrophic backtracking
        if (strlen($text) > 100000) {
            return null;
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                return $this->normalize($decoded);
            }
        }

        // Try to find JSON array [...]
        if (preg_match('/\[[\s\S]*\]/', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                return $this->normalize(['suggestions' => $decoded]);
            }
        }

        // Try to find JSON in markdown code blocks
        if (preg_match('/```json\s*([\s\S]*?)```/', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                return $this->normalize(['suggestions' => $decoded]);
            }
        }

        // Try to reconstruct fragmented JSON (e.g., split across lines with [READABILITY] prefix)
        $reconstructed = $this->reconstructFragmentedJson($text);
        if ($reconstructed !== null) {
            $decoded = json_decode($reconstructed, true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                return $this->normalize($decoded);
            }
        }

        // FALLBACK: If JSON parsing fails, try to extract suggestions from plain text
        return $this->extractSuggestionsFromPlainText($text);
    }

    /**
     * Reconstruct fragmented JSON where key-value pairs are split across lines
     *
     * Handles format like:
     *   [READABILITY] "suggestion": "Replace the heading..."
     *   [READABILITY] "priority": 2
     *
     * @param string $text
     * @return string|null Reconstructed JSON string or null if not recognized
     */
    private function reconstructFragmentedJson(string $text): ?string
    {
        // Check if text has the fragmented pattern
        if (!preg_match('/^\s*\[READABILITY\]\s*"[^"]+"\s*:/im', $text)) {
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

            // Try to match [READABILITY] "key": "value" or "key": "value"
            $pattern = '/^\[READABILITY\]\s*"([^"]+)"\s*:\s*(.+)/i';
            if (preg_match($pattern, $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2], '",');

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
                $currentObject = ['review_type' => 'readability'];
                if (strtolower($key) === 'suggestion' || strtolower($key) === 'issue' || strtolower($key) === 'content') {
                    $currentText = $value;
                } else {
                    $currentObject[$key] = $value;
                }
            } elseif (preg_match('/^\[READABILITY\]\s*"([^"]+)"\s*:\s*"([^"]*)/i', $line, $matches)) {
                // Handle "key": "value (possibly incomplete, value on next line)
                $key = trim($matches[1]);
                $value = trim($matches[2]);

                if (!empty($currentText) && $currentObject !== null) {
                    $currentObject['text'] = $currentText;
                    $currentText = '';
                }

                if (strtolower($key) === 'priority' && $currentObject !== null) {
                    $currentObject['priority'] = intval($value);
                    continue;
                }

                if ($currentObject !== null) {
                    $objects[] = $currentObject;
                }

                $currentObject = ['review_type' => 'readability'];
                $currentText = $value;
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

        // Set default priority if not set
        foreach ($objects as &$obj) {
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
        }

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
     * @return array|null
     */
    public function extractSuggestionsFromPlainText(string $text): ?array
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
                return $this->normalize($decoded);
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

            // Detect priority-only lines (including [READABILITY] "priority": 2 pattern)
            if (preg_match('/^\[READABILITY\]\s*"priority"\s*:\s*(\d+)$/i', $cleanLine, $priorityMatches) ||
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
            // (starts with [READABILITY] "suggestion": or similar pattern)
            if (preg_match('/^\[READABILITY\]\s*"suggestion"\s*:/i', $cleanLine) ||
                preg_match('/^\[READABILITY\]\s*"issue"\s*:/i', $cleanLine) ||
                preg_match('/^\[READABILITY\]\s*"content"\s*:/i', $cleanLine)) {
                // Extract the actual text value
                if (preg_match('/:\s*"(.+)"/', $cleanLine, $textMatches)) {
                    $pendingSuggestion = [
                        'review_type' => 'readability',
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
     * Check if data is a single suggestion object
     *
     * @param array $data
     * @return bool
     */
    private function isSingleSuggestionObject(array $data): bool
    {
        return isset($data['issue']) || isset($data['content'])
            || isset($data['text']) || isset($data['suggestion']);
    }

    /**
     * Normalize single object suggestion like {"content": "...", "priority": 1}
     *
     * @param array $data
     * @return array|null Normalized array or null if not a single object
     */
    private function normalizeSingleObjectSuggestion(array $data): ?array
    {
        if (!$this->isSingleSuggestionObject($data)) {
            return null;
        }

        $text = $data['issue'] ?? $data['content'] ?? $data['text'] ?? $data['suggestion'] ?? '';
        if (empty($text)) {
            return null;
        }

        $priority = $data['priority'] ?? 1;
        $review_type = $data['review_type'] ?? $data['category'] ?? 'readability';

        return [
            'suggestions' => [
                [
                    'review_type' => $review_type,
                    'text' => $text,
                    'priority' => $priority
                ]
            ]
        ];
    }

    /**
     * Normalize suggestions array - handles both string arrays and object arrays
     *
     * @param array $suggestions
     * @return array
     */
    private function normalizeSuggestionsArray(array $suggestions): array
    {
        $hasStringItems = false;
        $hasObjectItems = false;

        foreach ($suggestions as $item) {
            if (is_string($item)) {
                $hasStringItems = true;
            } elseif (is_array($item)) {
                $hasObjectItems = true;
            }
        }

        // All items are strings - convert to objects
        if ($hasStringItems && !$hasObjectItems) {
            return $this->normalizeStringArrayToObjects($suggestions);
        }

        // Mix or all objects - ensure priority is set
        if ($hasObjectItems) {
            foreach ($suggestions as &$item) {
                if (is_array($item) && !isset($item['priority'])) {
                    $item['priority'] = 1;
                }
            }
        }

        return ['suggestions' => $suggestions];
    }

    /**
     * Convert string array to suggestion objects
     *
     * @param array $suggestions
     * @return array
     */
    private function normalizeStringArrayToObjects(array $suggestions): array
    {
        $converted = [];
        foreach ($suggestions as $text) {
            if (is_string($text) && !empty(trim($text))) {
                $converted[] = [
                    'review_type' => 'readability',
                    'text' => trim($text),
                    'priority' => 1
                ];
            }
        }
        return ['suggestions' => $converted];
    }

    /**
     * Normalize direct array of suggestions (no wrapper object)
     *
     * @param array $items
     * @return array
     */
    private function normalizeDirectArray(array $items): array
    {
        $suggestions = [];

        foreach ($items as $item) {
            $normalized = $this->normalizeSuggestionItem($item);
            if ($normalized !== null) {
                $suggestions[] = $normalized;
            }
        }

        if (!empty($suggestions)) {
            return ['suggestions' => $suggestions];
        }

        return ['suggestions' => []];
    }

    /**
     * Normalize a single suggestion item (string or array)
     *
     * @param mixed $item
     * @return array|null Normalized suggestion or null to skip
     */
    private function normalizeSuggestionItem($item): ?array
    {
        // Handle string item
        if (is_string($item)) {
            return $this->normalizeStringItem($item);
        }

        // Handle array item
        if (is_array($item)) {
            return $this->normalizeObjectItem($item);
        }

        return null;
    }

    /**
     * Normalize a string suggestion item
     *
     * @param string $text
     * @return array|null
     */
    private function normalizeStringItem(string $text): ?array
    {
        $text = trim($text);
        if (empty($text)) {
            return null;
        }

        $extracted = $this->extractPriorityFromText($text);
        if (empty($extracted['text'])) {
            return null;
        }

        return [
            'review_type' => 'readability',
            'text' => $extracted['text'],
            'priority' => $extracted['priority']
        ];
    }

    /**
     * Normalize an object suggestion item
     *
     * @param array $item
     * @return array|null
     */
    private function normalizeObjectItem(array $item): ?array
    {
        // Handle ["text", priority] format
        if (isset($item[0]) && is_string($item[0]) && isset($item[1]) && is_numeric($item[1])) {
            $text = trim($item[0]);
            if (empty($text)) {
                return null;
            }
            return [
                'review_type' => 'readability',
                'text' => $text,
                'priority' => intval($item[1])
            ];
        }

        // Normalize keys (trim whitespace)
        $normalized = $this->normalizeItemKeys($item);

        // Extract text from various possible fields
        $text = $normalized['content'] ?? $normalized['text'] ?? $normalized['issue'] ?? $normalized['suggestion'] ?? '';
        if (empty($text)) {
            return null;
        }

        $priority = $normalized['priority'] ?? 1;
        $review_type = $normalized['review_type'] ?? $normalized['category'] ?? 'readability';

        return [
            'review_type' => $review_type,
            'text' => $text,
            'priority' => $priority
        ];
    }

    /**
     * Normalize array keys by trimming whitespace
     *
     * @param array $item
     * @return array
     */
    private function normalizeItemKeys(array $item): array
    {
        $normalized = [];
        foreach ($item as $key => $value) {
            $normalized[trim($key)] = $value;
        }
        return $normalized;
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
    private function extractPriorityFromText(string $text): array
    {
        $priority = 1;

        if (preg_match('/\[Priority:\s*(\d+)\]/i', $text, $matches) ||
            preg_match('/^\(priority:\s*(\d+)\)$/i', $text, $matches) ||
            preg_match('/\bPriority:\s*(\d+)$/i', $text, $matches) ||
            preg_match('/^\[\d+\]$/', $text, $matches)) {
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