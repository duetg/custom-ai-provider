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
use WordPress\CustomAiProvider\Models\TextGeneration\ThinkingTagHelper;
use WordPress\CustomAiProvider\Models\TextGeneration\ReviewNotesNormalizer;

/**
 * Custom Text Generation Model for OpenAI-compatible APIs
 *
 * This model allows connecting to any OpenAI-compatible text generation API,
 * such as Ollama, LM Studio, MiniMax, or other custom endpoints.
 */
class CustomTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    /**
     * Review Notes normalizer instance
     *
     * @var ReviewNotesNormalizer
     */
    private $reviewNotesNormalizer;

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
     * Get Review Notes normalizer instance
     *
     * @return ReviewNotesNormalizer
     */
    private function getReviewNotesNormalizer(): ReviewNotesNormalizer
    {
        if ($this->reviewNotesNormalizer === null) {
            $this->reviewNotesNormalizer = new ReviewNotesNormalizer();
        }
        return $this->reviewNotesNormalizer;
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
        // Get base URL from settings
        $base_url = $this->getBaseUrl();

        // Debug logging - log final request details (before removing response_format)
        custom_ai_debug('Request', [
            'path' => $path,
            'model' => $model_id,
            'url' => $base_url . '/' . ltrim($path, '/'),
            'had_response_format' => is_array($data) && isset($data['response_format']),
            'data_keys' => is_array($data) ? array_keys($data) : null,
        ]);

        if (is_array($data) && isset($data['response_format'])) {
            // Remove response_format for models that don't support it properly
            // This includes Kimi, Qwen, and others that don't properly handle JSON schema
            // These models will output plain text which the caller can parse
            unset($data['response_format']);
        }

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
        // Debug: log raw response data
        custom_ai_debug('Response choice[' . $index . ']', [
            'content' => isset($choiceData['message']['content']) ? substr($choiceData['message']['content'], 0, 500) : null,
            'finish_reason' => $choiceData['finish_reason'] ?? null,
        ]);

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
                $content = $choiceData['message']['content'] ?? '';

                // Always check for thinking tags in content (regardless of reasoning_content value)
                // and extract them to reasoning_content
                if (!empty($content)) {
                    $result = ThinkingTagHelper::clean($content);

                    // Always update content with cleaned version (regardless of whether thinking was found)
                    // This ensures any leading/trailing whitespace or thinking tags are removed
                    $choiceData['message']['content'] = $result['content'];
                    // Only update reasoning_content if we found thinking AND it's not already set
                    if (!empty($result['thinking']) && !isset($choiceData['message']['reasoning_content'])) {
                        $choiceData['message']['reasoning_content'] = $result['thinking'];
                    }
                }
            }
        }

        // Always copy message content fields to top level (for both handler and non-handler cases)
        if (isset($choiceData['message']) && is_array($choiceData['message'])) {
            // Copy content and reasoning_content to top level for easier access later
            // Always trim content to remove leading/trailing whitespace
            if (isset($choiceData['message']['content'])) {
                $choiceData['content'] = trim($choiceData['message']['content']);
            }
            if (isset($choiceData['message']['reasoning_content'])) {
                $choiceData['reasoning_content'] = trim($choiceData['message']['reasoning_content']);
            }
        }

        // Check if content is not valid JSON but looks like it should be JSON
        // Try to extract JSON from the text response - but ONLY for Review Notes requests
        // Check if the response contains keywords that indicate this is a Review Notes request
        if (isset($choiceData['content']) && is_string($choiceData['content'])) {
            $content = $choiceData['content'];

            custom_ai_debug('Checking JSON extraction', [
                'content_preview' => substr($content, 0, 200),
                'matches_json_pattern' => (bool) preg_match('/^\s*[\[{]/', $content),
            ]);

            // If the response looks like JSON (starts with [ or {), try to parse and normalize it
            // This handles Review Notes and other structured responses
            // Normal conversation responses are plain text and won't match this pattern
            if (preg_match('/^\s*[\[{]/', $content)) {
                custom_ai_debug('Detected JSON-like response, trying to extract and normalize');
                $json_extracted = $this->getReviewNotesNormalizer()->extractJsonFromText($content);
                if ($json_extracted !== null) {
                    $json_content = json_encode($json_extracted);
                    // Update both top-level and message content
                    $choiceData['content'] = $json_content;
                    if (isset($choiceData['message']) && is_array($choiceData['message'])) {
                        $choiceData['message']['content'] = $json_content;
                    }
                    custom_ai_debug('Extracted JSON from text', $json_content);
                }
            } else {
                custom_ai_debug('Content does not look like JSON, skipping extraction');
            }
        }

        return parent::parseResponseChoiceToCandidate($choiceData, $index);
    }

}
