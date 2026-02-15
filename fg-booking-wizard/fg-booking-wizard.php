<?php
/**
 * Plugin Name: FG Booking Wizard
 * Description: 3-step fleet booking wizard with one-way/round-trip segments, Google Places + Aviationstack proxy, and DB storage.
 * Version: 1.0.0
 * Author: FG
 * Text Domain: fgbw
 */

if (!defined('ABSPATH')) exit;

define('FGBW_VERSION', '1.0.0');
define('FGBW_PLUGIN_FILE', __FILE__);
define('FGBW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FGBW_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once FGBW_PLUGIN_DIR . 'includes/helpers.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-activator.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-plugin.php';

register_activation_hook(__FILE__, ['FGBW_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['FGBW_Activator', 'deactivate']);

add_action('plugins_loaded', function () {
    FGBW_Plugin::instance()->init();
});