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
     * JSON response extractor instance
     *
     * @var JsonResponseExtractor
     */
    private $extractor;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->extractor = new JsonResponseExtractor();
    }

    /**
     * Get the JSON response extractor
     *
     * @return JsonResponseExtractor
     */
    public function getExtractor(): JsonResponseExtractor
    {
        return $this->extractor;
    }

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
            // Check if suggestions use "term" field (MiniMax format) instead of "text"
            foreach ($data['suggestions'] as $item) {
                if (isset($item['term']) || isset($item['category'])) {
                    return $this->normalizeTermBasedSuggestions($data['suggestions']);
                }
            }
            return $this->normalizeSuggestionsArray($data['suggestions']);
        }

        // Case 2b: Has "terms" key instead of "suggestions" (some models like Kimi)
        if (isset($data['terms']) && is_array($data['terms'])) {
            $terms = $data['terms'];
            // Handle nested array case: terms: [[{term:...}, {term:...}]]
            if (isset($terms[0]) && is_array($terms[0]) && isset($terms[0][0])) {
                $terms = $terms[0];
            }
            return $this->normalizeTermBasedSuggestions($terms);
        }

        // Case 2c: Has "suggested_terms" key (some models like GLM)
        if (isset($data['suggested_terms']) && is_array($data['suggested_terms'])) {
            return $this->normalizeTermBasedSuggestions($data['suggested_terms']);
        }

        // Case 3: Empty array
        if (empty($data)) {
            return ['suggestions' => []];
        }

        // Case 4: Direct array of suggestions with "term" field (GLM-4.7 style)
        // Check if it's an array where first element has "term" field
        if (is_array($data) && !empty($data) && isset($data[0]) && is_array($data[0]) && isset($data[0]['term'])) {
            return $this->normalizeTermBasedSuggestions($data);
        }

        // Case 5: Direct array of suggestions (no wrapper object)
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
        return $this->extractor->extract($text, $this);
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

        // Check if text field contains nested JSON (model sometimes returns JSON inside JSON)
        if (is_string($text)) {
            $nestedJson = json_decode($text, true);
            if (is_array($nestedJson) && json_last_error() === JSON_ERROR_NONE) {
                // If it's an array of suggestions, normalize each item
                if (isset($nestedJson[0]) && is_array($nestedJson[0])) {
                    $normalizedItems = [];
                    foreach ($nestedJson as $item) {
                        $normalizedItems[] = [
                            'review_type' => $item['review_type'] ?? $item['category'] ?? $review_type,
                            'text' => $item['suggestion'] ?? $item['issue'] ?? $item['content'] ?? $item['text'] ?? '',
                            'priority' => isset($item['priority']) ? intval($item['priority']) : $priority
                        ];
                    }
                    return ['suggestions' => $normalizedItems];
                }
                // If it's a single suggestion object, use its values
                if (isset($nestedJson['suggestion']) || isset($nestedJson['issue']) || isset($nestedJson['content']) || isset($nestedJson['text'])) {
                    $text = $nestedJson['suggestion'] ?? $nestedJson['issue'] ?? $nestedJson['content'] ?? $nestedJson['text'] ?? '';
                    $priority = $nestedJson['priority'] ?? $priority;
                    $review_type = $nestedJson['review_type'] ?? $nestedJson['category'] ?? $review_type;
                }
                // If it's an array of strings (not objects), create one suggestion per string
                if (isset($nestedJson[0]) && is_string($nestedJson[0])) {
                    $normalizedItems = [];
                    foreach ($nestedJson as $str) {
                        if (is_string($str) && !empty(trim($str))) {
                            $normalizedItems[] = [
                                'review_type' => $review_type,
                                'text' => $this->extractor->cleanTagPrefix(trim($str)),
                                'priority' => $priority
                            ];
                        }
                    }
                    if (!empty($normalizedItems)) {
                        return ['suggestions' => $normalizedItems];
                    }
                }
            } else {
                // Not nested JSON - clean tag prefix from plain text
                $text = $this->extractor->cleanTagPrefix($text);
            }
        }

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

        // Mix or all objects - normalize each item
        if ($hasObjectItems) {
            $normalized = [];
            foreach ($suggestions as $item) {
                if (is_array($item)) {
                    $normalizedItem = $this->normalizeObjectItem($item);
                    if ($normalizedItem !== null) {
                        $normalized[] = $normalizedItem;
                    }
                }
            }
            if (!empty($normalized)) {
                return ['suggestions' => $normalized];
            }
        }

        return ['suggestions' => []];
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
     * Normalize term-based suggestions (MiniMax taxonomy format)
     *
     * MiniMax returns suggestions like:
     * {"suggestions": [{"term": "Education", "confidence": 0.95}, {"term": "Obituary", "confidence": 0.9}]}
     *
     * This format IS the WordPress AI format, so just pass through with is_new field added.
     *
     * @param array $suggestions
     * @return array
     */
    private function normalizeTermBasedSuggestions(array $suggestions): array
    {
        $normalized = [];

        foreach ($suggestions as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Handle "term" field (MiniMax taxonomy format)
            if (isset($item['term'])) {
                $term = trim($item['term']);
                if (empty($term)) {
                    continue;
                }

                $confidence = isset($item['confidence']) ? floatval($item['confidence']) : 0.5;

                $normalizedItem = [
                    'term' => $term,
                    'confidence' => $confidence,
                    'is_new' => true, // MiniMax can't know if term exists, assume new
                ];

                // Preserve parent if present
                if (isset($item['parent'])) {
                    $normalizedItem['parent'] = trim($item['parent']);
                }

                $normalized[] = $normalizedItem;
                continue;
            }

            // Fallback: if no "term", try standard normalization
            $objNormalized = $this->normalizeObjectItem($item);
            if ($objNormalized !== null) {
                $normalized[] = $objNormalized;
            }
        }

        if (!empty($normalized)) {
            return ['suggestions' => $normalized];
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

        $extracted = $this->extractor->extractPriorityFromText($text);
        if (empty($extracted['text'])) {
            return null;
        }

        // Clean up tag prefixes like [SEO], [ACCESSIBILITY], etc. from text
        $text = $this->extractor->cleanTagPrefix($extracted['text']);

        return [
            'review_type' => 'readability',
            'text' => $text,
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

        // Clean up tag prefixes like [SEO], [ACCESSIBILITY], etc. from text
        $text = $this->extractor->cleanTagPrefix($text);

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
}
