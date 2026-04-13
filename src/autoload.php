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
 * Debug logging helper - wraps WordPress\CustomAiProvider\Helper::debug()
 *
 * @param string $message The message to log
 * @param mixed $data Optional data to include (lazy JSON encoding, only when debug is enabled)
 * @param int $maxDataLength Maximum length for data serialization (0 = no limit)
 */
if (!function_exists('duetgaicon_debug')) {
    function duetgaicon_debug($message, $data = null, $maxDataLength = 0)
    {
        Helper::debug($message, $data, $maxDataLength);
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
