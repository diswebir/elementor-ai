<?php
/**
 * Plugin Name: AI Elementor Agent
 * Description: A secure, approval-driven AI agent for planning and building Elementor draft pages.
 * Version: 0.1.3
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: diswebir
 * Text Domain: ai-elementor-agent
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('AIEA_VERSION', '0.1.3');
define('AIEA_FILE', __FILE__);
define('AIEA_DIR', plugin_dir_path(__FILE__));
define('AIEA_URL', plugin_dir_url(__FILE__));
define('AIEA_BASENAME', plugin_basename(__FILE__));

$autoload = AIEA_DIR . 'vendor/autoload.php';
if (!is_readable($autoload)) {
    add_action('admin_notices', static function (): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>'
            . esc_html__('AI Elementor Agent needs its Composer dependencies. Run composer install before activating it.', 'ai-elementor-agent')
            . '</p></div>';
    });

    return;
}

require_once $autoload;

register_activation_hook(AIEA_FILE, [\AIEA\Database\Installer::class, 'activate']);
register_deactivation_hook(AIEA_FILE, [\AIEA\Database\Installer::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    \AIEA\Core\Plugin::instance()->boot();
}, 20);