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
        'MiniMax-M2',
        'MiniMax-MoE',
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
        // Remove parameters that MiniMax doesn't support or handles differently
        if (isset($params['response_format'])) {
            unset($params['response_format']);
        }

        // Set default values for MiniMax
        if (!isset($params['temperature'])) {
            $params['temperature'] = 0.7;
        }

        if (!isset($params['max_tokens'])) {
            $params['max_tokens'] = 2048;
        }

        // MiniMax uses 'role' instead of 'system' in messages
        // But we need to be careful - only transform system role in a way MiniMax understands
        // Actually, MiniMax API accepts standard OpenAI format, so no transformation needed here

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
        // MiniMax returns standard OpenAI-compatible response format
        // No transformation needed unless specific edge cases are discovered

        // However, if the response contains 'reasoning' field, we should extract it
        // Some MiniMax models include reasoning in a separate field
        if (isset($response['choices'][0]['reasoning'])) {
            $response['choices'][0]['reasoning_content'] = $response['choices'][0]['reasoning'];
        }

        return $response;
    }
}
