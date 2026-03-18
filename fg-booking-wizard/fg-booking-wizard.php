<?php
/**
 * Plugin Name: FG Booking Wizard
 * Description: 3-step fleet booking wizard with one-way/round-trip segments, Google Places + Aviationstack proxy, and DB storage.
 * Version: 1.7.43
 * Author: FG
 * Text Domain: fgbw
 */

if (!defined('ABSPATH')) exit;

define('FGBW_VERSION', '1.7.44');
define('FGBW_PLUGIN_FILE', __FILE__);
define('FGBW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FGBW_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once FGBW_PLUGIN_DIR . 'includes/helpers.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-activator.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-plugin.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-pdf.php';

register_activation_hook(__FILE__, ['FGBW_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['FGBW_Activator', 'deactivate']);

add_action('plugins_loaded', function () {
    FGBW_Plugin::instance()->init();
});

// Ensure FGBW errors always reach the log file, even if WP_DEBUG is off.
if ( ! defined('WP_DEBUG_LOG') ) {
    define('WP_DEBUG_LOG', true);
}

// Initialise PDF storage directory — runs on both plugins_loaded and init
// to guarantee the upload_dir is set before any AJAX PDF generation call.
add_action('plugins_loaded', function () {
    FGBW_PDF::init();
}, 20);
add_action('init', function () {
    FGBW_PDF::init();
});

// Serve secure PDF downloads (token-protected — not publicly browsable).
add_action('init', function () {
    if ( isset( $_GET['fgbw_pdf'] ) ) {
        FGBW_PDF::serve_download();
    }
});

/**
 * Migration v1744: unconditionally clear email_customer_body and email_admin_body
 * from the DB. From v1.7.44 onwards, resolve_template() always reads from the
 * on-disk PHP file — DB-stored bodies are never used. This one-time cleanup
 * ensures any previously saved value (from the old settings textarea) is wiped
 * so it can never override the current on-disk template.
 *
 * Uses a new migration key so it runs even on sites that completed the earlier
 * v1713 migration.
 */
add_action('plugins_loaded', function () {
    if (get_option('fgbw_email_template_migration_v1744')) {
        return; // Already ran on this site.
    }

    $opts = get_option('fgbw_settings', []);

    // Wipe both body keys unconditionally — on-disk files are the sole source of truth.
    $opts['email_customer_body'] = '';
    $opts['email_admin_body']    = '';

    update_option('fgbw_settings', $opts);
    update_option('fgbw_email_template_migration_v1744', '1');

}, 5); // Priority 5 — before FGBW_Plugin::init() at priority 10.
