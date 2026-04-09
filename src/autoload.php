<?php
/**
 * Autoloader for DuetG AI Connector plugin
 *
 * @package CustomAiProvider
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug logging helper - only logs when DUETGAICON_DEBUG is enabled
 *
 * @param string $message The message to log
 * @param mixed $data Optional data to include (lazy JSON encoding, only when debug is enabled)
 * @param int $maxDataLength Maximum length for data serialization (0 = no limit)
 */
if (!function_exists('duetgaicon_debug')) {
    function duetgaicon_debug($message, $data = null, $maxDataLength = 0) {
        // Only log when DUETGAICON_DEBUG is explicitly enabled
        if (!defined('DUETGAICON_DEBUG') || !DUETGAICON_DEBUG) {
            return;
        }

        // Sanitize URLs in data to prevent sensitive info leakage
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value) && preg_match('/^https?:\/\//i', $value)) {
                    $data[$key] = preg_replace(
                        '/([?&])(api_key|key|token|secret|auth|password|passwd)=[^&]*/i',
                        '$1$2=[REDACTED]',
                        $value
                    );
                }
            }
        }

        $log_message = 'DuetG AI Connector: ' . $message;
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
