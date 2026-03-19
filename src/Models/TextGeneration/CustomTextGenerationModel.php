<?php
/**
 * Custom Text Generation Model
 *
 * @package CustomAiProvider\Models\TextGeneration
 */

namespace WordPress\CustomAiProvider\Models\TextGeneration;

use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\CustomAiProvider\Settings\Settings;

/**
 * Custom Text Generation Model for OpenAI-compatible APIs
 *
 * This model allows connecting to any OpenAI-compatible text generation API,
 * such as Ollama, LM Studio, MiniMax, or other custom endpoints.
 */
class CustomTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    /**
     * Get the model ID to use for API requests
     *
     * @return string
     */
    protected function getModelId(): string
    {
        $model = Settings::getTextModel();
        if (!empty($model)) {
            return $model;
        }

        // Fallback to metadata ID if no setting
        return $this->metadata()->getId();
    }

    /**
     * Get the base URL for API requests
     *
     * @return string
     */
    private function getBaseUrl(): string
    {
        return rtrim(Settings::getTextBaseUrl(), '/');
    }

    /**
     * Get model handler if available
     *
     * @return ModelHandlerInterface|null
     */
    private function getModelHandler(): ?ModelHandlerInterface
    {
        return ModelHandlerRegistry::getHandler($this->getModelId());
    }

    /**
     * Create a request object for the provider's API
     *
     * @param HttpMethodEnum $method
     * @param string $path
     * @param array $headers
     * @param mixed $data
     * @return Request
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        // Debug logging - log all requests
        custom_ai_debug('Request', ['path' => $path, 'model' => $this->getModelId()]);

        // Get model ID from settings
        $model_id = $this->getModelId();

        // If data is an array and has 'model' key, override with setting
        if (is_array($data) && isset($data['model'])) {
            $data['model'] = $model_id;
        }

        // Apply model-specific handler if available (e.g., MiniMax)
        $handler = $this->getModelHandler();
        if ($handler !== null && is_array($data)) {
            $data = $handler->transformRequest($data);
        } else {
            // For all other models, set n=1 to avoid "n parameter must be 1 when enable_thinking is true" error
            // This is required for models like Qwen, DeepSeek, etc. that have thinking enabled by default
            if (is_array($data) && (!isset($data['n']) || $data['n'] > 1)) {
                $data['n'] = 1;
            }
        }

        // Fix response_format for APIs that don't support JSON output properly
        // Many OpenAI-compatible APIs don't properly support response_format
        if (is_array($data) && isset($data['response_format'])) {
            $model_id = $this->getModelId();
            // Remove response_format for models that don't support it properly
            // This includes Kimi, Qwen, and others that don't properly handle JSON schema
            // These models will output plain text which the caller can parse
            unset($data['response_format']);
        }

        // Get base URL from settings
        $base_url = $this->getBaseUrl();

        return new Request($method, $base_url . '/' . ltrim($path, '/'), $headers, $data);
    }

    /**
     * Parse response choice to candidate
     *
     * Override to handle model-specific response formats
     *
     * @param array $choiceData
     * @param int $index
     * @return \WordPress\AiClient\Results\DTO\Candidate
     */
    protected function parseResponseChoiceToCandidate(array $choiceData, int $index): \WordPress\AiClient\Results\DTO\Candidate
    {
        // Debug logging - log response data
        custom_ai_debug('Response choice data (before handler)', $choiceData);

        // Apply model-specific handler if available
        $handler = $this->getModelHandler();
        if ($handler !== null) {
            $choiceData = $handler->transformResponse(['choices' => [$choiceData]]);
            if (isset($choiceData['choices'][0])) {
                $choiceData = $choiceData['choices'][0];
            }
        } else {
            // For models without a specific handler (e.g., DeepSeek on SiliconFlow),
            // clean thinking tags from content
            if (isset($choiceData['message']) && is_array($choiceData['message'])) {
                $message = $choiceData['message'];
                $content = $message['content'] ?? '';

                // Always check for thinking tags in content (regardless of reasoning_content value)
                // and extract them to reasoning_content
                if (!empty($content)) {
                    $result = $this->cleanContentByThinkingTags($content);
                    if (!empty($result['thinking'])) {
                        // Extract thinking content to reasoning_content
                        $choiceData['message']['reasoning_content'] = $result['thinking'];
                        $choiceData['message']['content'] = $result['content'];
                    }
                }
            }
        }

        // Always copy message content fields to top level (for both handler and non-handler cases)
        if (isset($choiceData['message']) && is_array($choiceData['message'])) {
            // Copy content and reasoning_content to top level for easier access later
            if (isset($choiceData['message']['content'])) {
                $choiceData['content'] = $choiceData['message']['content'];
            }
            if (isset($choiceData['message']['reasoning_content'])) {
                $choiceData['reasoning_content'] = $choiceData['message']['reasoning_content'];
            }
        }

        // Debug: log after handler
        custom_ai_debug('Response choice data (after handler)', ['content' => $choiceData['content'] ?? 'N/A', 'reasoning_content' => $choiceData['reasoning_content'] ?? 'N/A']);

        // Check if content is not valid JSON but looks like it should be JSON
        // Try to extract JSON from the text response - but ONLY for Review Notes requests
        // Check if the response contains keywords that indicate this is a Review Notes request
        if (isset($choiceData['content']) && is_string($choiceData['content'])) {
            $content = $choiceData['content'];

            // If the response looks like JSON (starts with [ or {), try to parse and normalize it
            // This handles Review Notes and other structured responses
            // Normal conversation responses are plain text and won't match this pattern
            if (preg_match('/^\s*[\[{]/', $content)) {
                custom_ai_debug('Detected JSON-like response, trying to extract and normalize');
                $json_extracted = $this->extractJsonFromText($content);
                if ($json_extracted !== null) {
                    $json_content = json_encode($json_extracted);
                    // Update both top-level and message content
                    $choiceData['content'] = $json_content;
                    if (isset($choiceData['message']) && is_array($choiceData['message'])) {
                        $choiceData['message']['content'] = $json_content;
                    }
                    custom_ai_debug('Extracted JSON from text', $json_content);
                }
            }
        }

        return parent::parseResponseChoiceToCandidate($choiceData, $index);
    }

    /**
     * Extract JSON from text response
     * Many models don't properly support response_format, so they output plain text
     * that contains JSON-like content. This tries to extract valid JSON.
     *
     * @param string $text
     * @return array|null
     */
    private function extractJsonFromText(string $text): ?array
    {
        custom_ai_debug('extractJsonFromText input', $text);

        // Try direct JSON decode first
        $decoded = json_decode($text, true);
        $jsonError = json_last_error();
        custom_ai_debug('json_decode result', ['decoded' => $decoded, 'error' => $jsonError . ' (' . json_last_error_msg() . ')']);
        if (is_array($decoded) && $jsonError === JSON_ERROR_NONE) {
            return $this->normalizeReviewNotesFormat($decoded);
        }

        // Try to find JSON in the text using regex
        // Look for {...} or [...] patterns
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            custom_ai_debug('Found JSON object via regex', ['length' => strlen($matches[0])]);
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                return $this->normalizeReviewNotesFormat($decoded);
            }
            custom_ai_debug('Regex JSON parse failed: ' . json_last_error_msg());
        }

        // Try to find JSON array [...]
        if (preg_match('/\[[\s\S]*\]/', $text, $matches)) {
            custom_ai_debug('Found JSON array via regex', ['length' => strlen($matches[0]), 'preview' => substr($matches[0], 0, 200)]);
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                return $this->normalizeReviewNotesFormat(['suggestions' => $decoded]);
            }
            custom_ai_debug('Regex JSON array parse failed: ' . json_last_error_msg());
        }

        // Try to find JSON in markdown code blocks
        if (preg_match('/```json\s*([\s\S]*?)```/', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                return $this->normalizeReviewNotesFormat(['suggestions' => $decoded]);
            }
        }

        // FALLBACK: If JSON parsing fails, try to extract suggestions from plain text
        // This handles cases where models return raw text instead of JSON
        $plainTextResult = $this->extractSuggestionsFromPlainText($text);
        if ($plainTextResult !== null) {
            custom_ai_debug('extractJsonFromText - extracted from plain text', $plainTextResult);
            return $plainTextResult;
        }

        custom_ai_debug('extractJsonFromText - no valid JSON found, returning null');
        return null;
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
    private function extractSuggestionsFromPlainText(string $text): ?array
    {
        if (empty(trim($text))) {
            return null;
        }

        custom_ai_debug('extractSuggestionsFromPlainText - input', $text);

        $suggestions = [];

        // Clean up the text first - remove thinking tags if present
        // Using # as delimiter to avoid issues with forward slashes in the pattern
        $text = preg_replace('#<think>[\s\S]*?</think>#', '', $text);
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
            custom_ai_debug('extractSuggestionsFromPlainText - text indicates no suggestions');
            return ['suggestions' => []];
        }

        // Split by newlines
        $lines = preg_split('/\r\n|\r|\n/', $text);

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

            // Skip lines that are purely priority indicators (e.g., "Priority: 2" or "priority: 2" on their own line)
            // These often appear as separate lines when AI returns multi-line suggestions
            if (preg_match('/^priority\s*:\s*\d+$/i', $cleanLine) ||
                preg_match('/^priority\s+\d+$/i', $cleanLine) ||
                preg_match('/^\[priority\s*:\s*\d+\]$/i', $cleanLine)) {
                // This is a standalone priority line - merge with previous suggestion if exists
                if (!empty($suggestions)) {
                    // Try to extract priority and update previous suggestion
                    if (preg_match('/(\d+)/', $cleanLine, $priorityMatches)) {
                        $suggestions[count($suggestions) - 1]['priority'] = intval($priorityMatches[1]);
                    }
                }
                continue;
            }

            // Extract priority if present in explicit formats only
            // Only match: "[Priority: 1]", "(priority: 1)", "Priority: 1", "[1]"
            // Do NOT match "(1)" which is likely part of the content
            $priority = 1;
            if (preg_match('/\[Priority:\s*(\d+)\]/i', $cleanLine, $matches) ||
                preg_match('/^\(priority:\s*(\d+)\)$/i', $cleanLine, $matches) ||
                preg_match('/\bPriority:\s*(\d+)$/i', $cleanLine, $matches) ||
                preg_match('/^\[\d+\]$/', $cleanLine, $matches)) {
                $priority = intval($matches[1]);
                // Remove priority from text
                $cleanLine = preg_replace('/\[Priority:\s*\d+\]/i', '', $cleanLine);
                $cleanLine = preg_replace('/^\(priority:\s*\d+\)$/i', '', $cleanLine);
                $cleanLine = preg_replace('/\bPriority:\s*\d+$/i', '', $cleanLine);
                $cleanLine = preg_replace('/^\[\d+\]$/', '', $cleanLine);
                $cleanLine = trim($cleanLine);
            }

            // Skip if line is empty after cleanup
            if (empty($cleanLine)) {
                continue;
            }

            $suggestions[] = [
                'review_type' => 'readability',
                'text' => $cleanLine,
                'priority' => $priority
            ];
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

        custom_ai_debug('extractSuggestionsFromPlainText - extracted ' . count($suggestions) . ' suggestions');

        return empty($suggestions) ? null : ['suggestions' => $suggestions];
    }

    /**
     * Normalize the response to Review Notes format
     * Review Notes expects: {"suggestions": [{"review_type": "...", "text": "...", "priority": 1}]}
     * But many models return: [{"content": "...", "priority": 1}] or [{"text": "...", "priority": 1}]
     *
     * @param array $data
     * @return array
     */
    private function normalizeReviewNotesFormat(array $data): array
    {
        custom_ai_debug('normalizeReviewNotesFormat input', $data);

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
            custom_ai_debug('normalizeReviewNotesFormat - empty array');
            return ['suggestions' => []];
        }

        // Case 4: Direct array of suggestions (no wrapper object)
        if (is_array($data) && !empty($data)) {
            return $this->normalizeDirectArray($data);
        }

        custom_ai_debug('normalizeReviewNotesFormat - returning original data');
        return $data;
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
            custom_ai_debug('normalizeReviewNotesFormat - converting string array');
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

        custom_ai_debug('normalizeReviewNotesFormat - already correct format');
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
        custom_ai_debug('normalizeReviewNotesFormat - processing array', ['count' => count($items)]);
        $suggestions = [];

        foreach ($items as $item) {
            $normalized = $this->normalizeSuggestionItem($item);
            if ($normalized !== null) {
                $suggestions[] = $normalized;
            }
        }

        if (!empty($suggestions)) {
            custom_ai_debug('normalizeReviewNotesFormat - suggestions count', ['count' => count($suggestions)]);
            return ['suggestions' => $suggestions];
        }

        return $items;
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
            custom_ai_debug('normalizeReviewNotesFormat - skipping empty text item');
            return null;
        }

        $priority = $normalized['priority'] ?? 1;
        $review_type = $normalized['review_type'] ?? $normalized['category'] ?? 'readability';

        custom_ai_debug('normalizeReviewNotesFormat - found text', $text);

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
     * Only matches: "[Priority: 1]", "(priority: 1)", "Priority: 1"
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
            preg_match('/\bPriority:\s*(\d+)$/i', $text, $matches)) {
            $priority = intval($matches[1]);
            $text = preg_replace('/\[Priority:\s*\d+\]/i', '', $text);
            $text = preg_replace('/^\(priority:\s*\d+\)$/i', '', $text);
            $text = preg_replace('/\bPriority:\s*\d+$/i', '', $text);
            $text = trim($text);
        }

        return ['text' => $text, 'priority' => $priority];
    }

    /**
     * Clean content by extracting thinking tags
     * Supports both Chinese and English thinking tags:
     * - Chinese: <think> </think>
     * - English: <thinking> </thinking>
     *
     * @param string $content
     * @return array ['content' => string, 'thinking' => string]
     */
    private function cleanContentByThinkingTags(string $content): array
    {
        // Support Chinese and English closing tags
        // Chinese: </think> (may appear as <\/think> in JSON)
        // English: </thinking> (may appear as <\/thinking> in JSON)
        $closeTags = ['</think>', '</thinking>', '<\\/think>', '<\\/thinking>'];

        // Find the first closing tag
        $closePos = false;
        $foundCloseTag = '';
        foreach ($closeTags as $closeTag) {
            $pos = strpos($content, $closeTag);
            if ($pos !== false && ($closePos === false || $pos < $closePos)) {
                $closePos = $pos;
                $foundCloseTag = $closeTag;
            }
        }

        // If no closing tag found, return original content
        if ($closePos === false) {
            return [
                'content' => $content,
                'thinking' => ''
            ];
        }

        // Everything before the closing tag is thinking content
        // (could be after opening tag, or could be raw thinking if API removed opening tag)
        $thinking = substr($content, 0, $closePos);
        $thinking = trim($thinking);

        // Everything after the closing tag is the actual content
        $cleanContent = substr($content, $closePos + strlen($foundCloseTag));
        $cleanContent = ltrim($cleanContent); // Remove leading whitespace/newlines
        $cleanContent = trim($cleanContent);

        return [
            'content' => $cleanContent,
            'thinking' => $thinking
        ];
    }
}
