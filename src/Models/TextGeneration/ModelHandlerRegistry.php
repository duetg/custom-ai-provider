<?php
/**
 * Model Handler Registry
 *
 * Manages registered handlers for different models
 *
 * @package CustomAiProvider\Models\TextGeneration
 */

namespace WordPress\CustomAiProvider\Models\TextGeneration;

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
     * Whether handlers have been initialized
     *
     * @var bool
     */
    private static bool $initialized = false;

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
     * Initialize default handlers (only runs once)
     *
     * @return void
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // Register default handlers
        // Add new handlers here as needed
        self::register(new MiniMaxHandler());
    }

    /**
     * Reset registry state (for testing purposes)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$handlers = [];
        self::$initialized = false;
    }
}
