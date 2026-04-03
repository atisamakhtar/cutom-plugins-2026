<?php
if (!defined('ABSPATH')) exit;

function fgbw_get_option(string $key, $default = '') {
    $opts = get_option('fgbw_settings', []);
    return $opts[$key] ?? $default;
}

/**
 * Plugin-level logger.
 *
 * Writes to the PHP error log (wp-content/debug.log) when either:
 *   - WP_DEBUG is true (standard WordPress debug mode), OR
 *   - The plugin's own "debug_logging" setting is enabled.
 *
 * Using the plugin setting lets you capture API diagnostics in production
 * without enabling site-wide WP_DEBUG (which has security implications).
 */
function fgbw_log(string $msg): void {
    $plugin_debug = (bool) fgbw_get_option('debug_logging', false);
    if ($plugin_debug || (defined('WP_DEBUG') && WP_DEBUG)) {
        error_log('[FGBW] ' . $msg);
    }
}

function fgbw_sanitize_text($v): string {
    return sanitize_text_field(wp_unslash((string)$v));
}

function fgbw_sanitize_email($v): string {
    return sanitize_email(wp_unslash((string)$v));
}

function fgbw_sanitize_phone($v): string {
    $v = wp_unslash((string)$v);
    $v = preg_replace('/[^\d\+\-\(\)\s]/', '', $v);
    return trim($v);
}

function fgbw_json_encode($data): string {
    return wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}