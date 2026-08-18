<?php

declare(strict_types=1);

if (!defined('AIEA_DIR')) {
    define('AIEA_DIR', dirname(__DIR__) . '/');
    define('AIEA_URL', 'https://example.test/wp-content/plugins/ai-elementor-ag/');
    define('AIEA_VERSION', '0.1.0-test');
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $flags = 0): string|false
    {
        return json_encode($value, $flags);
    }
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_readable($autoload)) {
    require_once $autoload;
}
