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
 * such as Ollama, LM Studio, or other custom endpoints.
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
        $model = get_option(Settings::TEXT_MODEL_OPTION, '');
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
        $base_url = get_option(Settings::TEXT_BASE_URL_OPTION, '');
        if (!empty($base_url)) {
            return rtrim($base_url, '/');
        }
        return 'http://localhost:11434/v1';
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

        // Get base URL from settings
        $base_url = $this->getBaseUrl();

        return new Request($method, $base_url . '/' . ltrim($path, '/'), $headers, $data);
    }
}
