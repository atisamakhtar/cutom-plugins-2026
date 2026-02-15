<?php
if (!defined('ABSPATH')) exit;

function fgbw_get_option(string $key, $default = '') {
    $opts = get_option('fgbw_settings', []);
    return $opts[$key] ?? $default;
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