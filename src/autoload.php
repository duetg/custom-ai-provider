<?php
/**
 * Autoloader for Custom AI Provider plugin
 *
 * @package CustomAiProvider
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug logging helper - only logs when CUSTOM_AI_DEBUG is enabled
 *
 * @param string $message The message to log
 * @param mixed $data Optional data to include (lazy JSON encoding, only when debug is enabled)
 * @param int $maxDataLength Maximum length for data serialization (0 = no limit)
 */
function custom_ai_debug($message, $data = null, $maxDataLength = 0) {
    // Only log when CUSTOM_AI_DEBUG is explicitly enabled
    if (!defined('CUSTOM_AI_DEBUG') || !CUSTOM_AI_DEBUG) {
        return;
    }

    $log_message = 'Custom AI: ' . $message;
    if ($data !== null) {
        // Lazy serialization - only encode when debug is enabled
        $encoded = json_encode($data);
        if ($maxDataLength > 0 && strlen($encoded) > $maxDataLength) {
            $encoded = substr($encoded, 0, $maxDataLength) . '...[truncated]';
        }
        $log_message .= ' - ' . $encoded;
    }
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    error_log($log_message);
}

spl_autoload_register(function ($class) {
    $prefix = 'WordPress\\CustomAiProvider\\';
    $base_dir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
