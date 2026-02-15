<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// If you want to drop table on uninstall, uncomment.
// global $wpdb;
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}fg_bookings");

delete_option('fgbw_settings');