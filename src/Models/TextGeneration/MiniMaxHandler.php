<?php
/**
 * MiniMax Model Handler
 *
 * Handles MiniMax-M2, MiniMax-MoE, and other MiniMax models
 * that use non-standard response formats.
 *
 * @package CustomAiProvider\Models\TextGeneration
 */

namespace WordPress\CustomAiProvider\Models\TextGeneration;

/**
 * MiniMax Model Handler
 */
class MiniMaxHandler implements ModelHandlerInterface
{
    /**
     * MiniMax model name prefixes
     *
     * @var array<string>
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
            if (strpos($modelId, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Transform request parameters for MiniMax API
     *
     * @param array $params
     * @return array
     */
    public function transformRequest(array $params): array
    {
        // Parameters that MiniMax ignores
        $ignoredParams = ['presence_penalty', 'frequency_penalty', 'logit_bias'];

        // Parameters that might cause issues
        $problematicParams = ['tools', 'tool_choice', 'tool_calls'];

        // Remove problematic and ignored parameters for MiniMax
        foreach (array_merge($ignoredParams, $problematicParams) as $param) {
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
        // This fixes "The n parameter must be 1 when enable_thinking is true" error
        if (!isset($params['extra_body'])) {
            $params['extra_body'] = [];
        }
        $params['extra_body']['enable_thinking'] = false;
        $params['extra_body']['reasoning_split'] = false;

        return $params;
    }

    /**
     * Transform response data from MiniMax API
     *
     * @param array $response
     * @return array
     */
    public function transformResponse(array $response): array
    {
        if (!isset($response['choices']) || !is_array($response['choices'])) {
            return $response;
        }

        foreach ($response['choices'] as &$choice) {
            if (isset($choice['message']) && is_array($choice['message'])) {
                $choice['message'] = $this->transformMessage($choice['message']);
            }
        }

        return $response;
    }

    /**
     * Transform message data - handles reasoning_details and thinking tags
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

        // If reasoning_split didn't work (no reasoning_details), clean content using thinking tags
        if (!isset($message['reasoning_content']) && isset($message['content'])) {
            custom_ai_debug('MiniMaxHandler: Before cleaning', [
                'content_preview' => substr($message['content'], 0, 500)
            ]);

            $result = ThinkingTagHelper::clean($message['content']);

            custom_ai_debug('MiniMaxHandler: After cleaning', [
                'content' => $result['content'],
                'thinking_length' => strlen($result['thinking'])
            ]);

            $message['content'] = $result['content'];
            if (!empty($result['thinking'])) {
                $message['reasoning_content'] = $result['thinking'];
            }
        }

        // Handle 'reasoning' field (some MiniMax models)
        if (isset($message['reasoning']) && !isset($message['reasoning_content'])) {
            $message['reasoning_content'] = $message['reasoning'];
        }

        return $message;
    }
}
