<?php
if (!defined('ABSPATH')) exit;

require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-settings.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-ajax.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-shortcode.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-email.php';

class FGBW_Plugin {
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function init(): void {
        (new FGBW_Settings())->init();
        (new FGBW_AJAX())->init();
        (new FGBW_Shortcode())->init();
    }
}