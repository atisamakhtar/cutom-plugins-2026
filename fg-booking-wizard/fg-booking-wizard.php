<?php
/**
 * Plugin Name: FG Booking Wizard
 * Description: 3-step fleet booking wizard with one-way/round-trip segments, Google Places + Aviationstack proxy, and DB storage.
 * Version: 1.7.14
 * Author: FG
 * Text Domain: fgbw
 */

if (!defined('ABSPATH')) exit;

define('FGBW_VERSION', '1.7.14');
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

/**
 * One-time migration: clear any stale email body saved in the DB that matches
 * the old bare-bones template format (pre-v1.7.13). Runs once per site and
 * stamps a version flag so it never runs again.
 *
 * The old template started with a plain <h2> tag and had no DOCTYPE/table
 * structure. If that value is still sitting in fgbw_settings it would
 * override our new rich HTML template files every time, because
 * fgbw_get_option returns the DB value as non-empty. This migration wipes it
 * so resolve_template() falls through to the on-disk file.
 */
add_action('plugins_loaded', function () {
    $migrated_key = 'fgbw_email_template_migration_v1713';
    if (get_option($migrated_key)) {
        return; // Already ran.
    }

    $opts = get_option('fgbw_settings', []);
    $changed = false;

    foreach (['email_customer_body', 'email_admin_body'] as $key) {
        $saved = trim($opts[$key] ?? '');
        if (empty($saved)) continue;

        // Old bare template fingerprints — none of these appear in the new
        // full HTML template files so they are safe discriminators.
        $is_old_bare = (
            stripos($saved, '<h2>Booking Confirmed')  !== false ||
            stripos($saved, '<h2>New Booking:')        !== false ||
            // Catch any saved value that has NO DOCTYPE and NO <table at all —
            // the new templates both have extensive <table> structure.
            ( stripos($saved, '<!DOCTYPE') === false && stripos($saved, '<table') === false )
        );

        if ($is_old_bare) {
            $opts[$key] = ''; // Clear — resolve_template() will use the file.
            $changed = true;
        }
    }

    if ($changed) {
        update_option('fgbw_settings', $opts);
    }

    // Mark migration done regardless, so this never runs again.
    update_option($migrated_key, '1');
}, 5); // Priority 5 — runs before FGBW_Plugin::init() at default priority 10.
