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
 * Debug logging helper - only logs when WP_DEBUG is enabled
 *
 * @param string $message The message to log
 * @param mixed $data Optional data to include (will be JSON encoded)
 */
function custom_ai_debug($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $log_message = 'Custom AI: ' . $message;
        if ($data !== null) {
            $log_message .= ' - ' . json_encode($data);
        }
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
