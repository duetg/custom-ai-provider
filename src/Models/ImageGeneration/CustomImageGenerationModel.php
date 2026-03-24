<?php
/**
 * Custom Image Generation Model
 *
 * @package CustomAiProvider\Models\ImageGeneration
 */

namespace WordPress\CustomAiProvider\Models\ImageGeneration;

use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleImageGenerationModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\CustomAiProvider\Settings\Settings;

/**
 * Custom Image Generation Model for OpenAI-compatible APIs
 *
 * This model allows connecting to any OpenAI-compatible image generation API,
 * such as Ollama, Stable Diffusion endpoints, or other custom image APIs.
 */
class CustomImageGenerationModel extends AbstractOpenAiCompatibleImageGenerationModel
{
    /**
     * Get the model ID to use for API requests
     *
     * @return string
     */
    protected function getModelId(): string
    {
        $model = Settings::getImageModel();
        if (!empty($model)) {
            return $model;
        }

        // Fallback to metadata ID if not setting
        return $this->metadata()->getId();
    }

    /**
     * Get the base URL for API requests
     *
     * @return string
     */
    private function getBaseUrl(): string
    {
        return rtrim(Settings::getImageBaseUrl(), '/');
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

    /**
     * Prepare generate image parameters
     *
     * Override to force b64_json format for compatibility with all OpenAI-compatible APIs
     * (e.g., SiliconFlow, Ollama, etc.)
     *
     * @param array $prompt The prompt messages
     * @return array The prepared parameters
     */
    protected function prepareGenerateImageParams(array $prompt): array
    {
        $params = parent::prepareGenerateImageParams($prompt);

        // Force b64_json format - some providers ignore the response_format parameter
        // and always return URL, but we need base64 for WordPress AI compatibility
        $params['response_format'] = 'b64_json';

        return $params;
    }

    /**
     * Parse response choice to candidate
     *
     * Override to handle URL responses by downloading and converting to base64.
     * Some providers (like SiliconFlow) may ignore the response_format parameter
     * and always return a URL instead of base64.
     *
     * @param array $choiceData The choice data from API response
     * @param int $index The index of the choice
     * @param string $expectedMimeType Expected MIME type
     * @return Candidate The parsed candidate
     */
    protected function parseResponseChoiceToCandidate(array $choiceData, int $index, string $expectedMimeType = 'image/png'): Candidate
    {
        // First, try the standard way (parent class logic)
        if (isset($choiceData['url']) && is_string($choiceData['url'])) {
            // Provider returned URL - check if we can use it directly
            // If b64_json was requested but URL was returned, try to convert
            $imageFile = $this->createFileFromUrl($choiceData['url'], $expectedMimeType);
        } elseif (isset($choiceData['b64_json']) && is_string($choiceData['b64_json'])) {
            // Provider returned base64 - use it directly
            $imageFile = new File($choiceData['b64_json'], $expectedMimeType);
        } else {
            // No url or b64_json - throw error like parent
            throw new \WordPress\AiClient\Providers\Http\Exception\ResponseException(
                sprintf('Invalid image data at index %d: must contain url or b64_json', (int) $index)
            );
        }

        $parts = [new MessagePart($imageFile)];
        $message = new Message(MessageRoleEnum::model(), $parts);
        return new Candidate($message, FinishReasonEnum::stop());
    }

    /**
     * Create a File from URL, converting to base64 if necessary
     *
     * Some providers return URLs even when b64_json is requested.
     * This method attempts to convert URLs to base64 for WordPress AI compatibility.
     *
     * @param string $url The image URL
     * @param string $expectedMimeType Expected MIME type
     * @return File The file object
     */
    private function createFileFromUrl(string $url, string $expectedMimeType): File
    {
        // Try to fetch the image and convert to base64
        $response = wp_remote_get($url);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            if (!empty($body)) {
                $base64 = base64_encode($body);
                return new File($base64, $expectedMimeType);
            }
        }

        // If we couldn't fetch, return as URL file (might work if URL is accessible)
        return new File($url, $expectedMimeType);
    }
}
