<?php
/**
 * Model Handlers - Special handling for specific AI models
 *
 * @package CustomAiProvider\Models\TextGeneration
 */

namespace WordPress\CustomAiProvider\Models\TextGeneration;

/**
 * Interface for model-specific handlers
 */
interface ModelHandlerInterface
{
    /**
     * Check if this handler applies to the given model
     *
     * @param string $modelId
     * @return bool
     */
    public function applies(string $modelId): bool;

    /**
     * Transform request parameters for this model
     *
     * @param array $params
     * @return array
     */
    public function transformRequest(array $params): array;

    /**
     * Transform response data from this model
     *
     * @param array $response
     * @return array
     */
    public function transformResponse(array $response): array;
}

/**
 * MiniMax Model Handler
 *
 * Handles MiniMax-M2, MiniMax-MoE, and other MiniMax models
 * that use non-standard response formats.
 */
class MiniMaxHandler implements ModelHandlerInterface
{
    /**
     * MiniMax model name prefixes
     */
    private const MINIMAX_PREFIXES = [
        'MiniMax-',
    ];

    /**
     * Check if this handler applies to the given model
     *
     * @param string $modelId
     * @return bool
     */
    public function applies(string $modelId): bool
    {
        foreach (self::MINIMAX_PREFIXES as $prefix) {
            if (stripos($modelId, $prefix) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Transform request parameters for MiniMax
     *
     * @param array $params
     * @return array
     */
    public function transformRequest(array $params): array
    {
        // Parameters that MiniMax ignores
        $ignored_params = ['presence_penalty', 'frequency_penalty', 'logit_bias'];

        // Parameters that might cause issues
        $problematic_params = ['tools', 'tool_choice', 'tool_calls'];

        // Remove problematic parameters for MiniMax
        foreach ($problematic_params as $param) {
            unset($params[$param]);
        }

        // Remove ignored parameters
        foreach ($ignored_params as $param) {
            unset($params[$param]);
        }

        // Ensure temperature is in valid range (0.0, 1.0]
        if (isset($params['temperature'])) {
            $temp = floatval($params['temperature']);
            if ($temp <= 0 || $temp > 1) {
                $params['temperature'] = 1.0;
            }
        }

        // Disable thinking/reasoning by default to support multiple candidates (n > 1)
        // This fixes "The n parameter must be 1 when enable_thinking is true" error for models like Qwen, MiniMax, etc.
        if (!isset($params['extra_body'])) {
            $params['extra_body'] = [];
        }
        $params['extra_body']['enable_thinking'] = false;
        $params['extra_body']['reasoning_split'] = false;

        return $params;
    }

    /**
     * Transform response data from MiniMax
     *
     * @param array $response
     * @return array
     */
    public function transformResponse(array $response): array
    {
        if (isset($response['choices']) && is_array($response['choices'])) {
            foreach ($response['choices'] as &$choice) {
                if (isset($choice['message']) && is_array($choice['message'])) {
                    $choice['message'] = $this->transformMessage($choice['message']);
                }
            }
        }

        return $response;
    }

    /**
     * Transform message data
     *
     * @param array $message
     * @return array
     */
    private function transformMessage(array $message): array
    {
        // Check for reasoning_details (when reasoning_split=true worked)
        if (isset($message['reasoning_details']) && is_array($message['reasoning_details'])) {
            $reasoningTexts = [];
            foreach ($message['reasoning_details'] as $reasoning) {
                if (isset($reasoning['text']) && is_string($reasoning['text'])) {
                    $reasoningTexts[] = $reasoning['text'];
                }
            }
            if (!empty($reasoningTexts)) {
                // Map to reasoning_content for WordPress to recognize as thinking
                $message['reasoning_content'] = implode("\n\n", $reasoningTexts);
            }
            // Remove reasoning_details so parent doesn't process it
            unset($message['reasoning_details']);
        }

        // If reasoning_split didn't work (no reasoning_details), clean content using <thinking> tags
        if (!isset($message['reasoning_content']) && isset($message['content'])) {
            custom_ai_debug('MiniMaxHandler: Before cleaning', ['content_preview' => substr($message['content'], 0, 500)]);
            $result = $this->cleanContentByThinkingTags($message['content']);
            custom_ai_debug('MiniMaxHandler: After cleaning', ['content' => $result['content'], 'thinking_length' => strlen($result['thinking'])]);
            $message['content'] = $result['content'];
            if (!empty($result['thinking'])) {
                $message['reasoning_content'] = $result['thinking'];
            }
        }

        return $message;
    }

    /**
     * Clean content by extracting thinking using <think> tags
     *
     * @param string $content
     * @return array ['content' => string, 'thinking' => string]
     */
    private function cleanContentByThinkingTags(string $content): array
    {
        // Support Chinese and English closing tags
        // Chinese: </think> (may appear as <\/think> in JSON)
        // English: </thinking> (may appear as <\/thinking> in JSON)
        $closeTags = ['</think>', '</thinking>', '<\/think>', '<\/thinking>'];

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
        $thinking = substr($content, 0, $closePos);
        $thinking = trim($thinking);

        // Everything after the closing tag is the actual content
        $cleanContent = substr($content, $closePos + strlen($foundCloseTag));
        $cleanContent = ltrim($cleanContent);
        $cleanContent = trim($cleanContent);

        return [
            'content' => $cleanContent,
            'thinking' => $thinking
        ];
    }
}


/**
 * Model Handler Registry
 *
 * Manages registered handlers for different models
 */
class ModelHandlerRegistry
{
    /**
     * Registered handlers
     *
     * @var ModelHandlerInterface[]
     */
    private static array $handlers = [];

    /**
     * Register a model handler
     *
     * @param ModelHandlerInterface $handler
     * @return void
     */
    public static function register(ModelHandlerInterface $handler): void
    {
        self::$handlers[] = $handler;
    }

    /**
     * Get handler for a specific model
     *
     * @param string $modelId
     * @return ModelHandlerInterface|null
     */
    public static function getHandler(string $modelId): ?ModelHandlerInterface
    {
        foreach (self::$handlers as $handler) {
            if ($handler->applies($modelId)) {
                return $handler;
            }
        }
        return null;
    }

    /**
     * Initialize default handlers
     *
     * @return void
     */
    public static function init(): void
    {
        // Register default handlers
        // Add new handlers here as needed
        self::register(new MiniMaxHandler());
    }
}
