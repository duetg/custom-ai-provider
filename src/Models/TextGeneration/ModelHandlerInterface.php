<?php
/**
 * Interface for model-specific handlers
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
