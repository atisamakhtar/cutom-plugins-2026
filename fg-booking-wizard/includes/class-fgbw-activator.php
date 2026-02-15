<?php
if (!defined('ABSPATH')) exit;

require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-db.php';

class FGBW_Activator {
    public static function activate() {
        FGBW_DB::create_tables();
    }
    public static function deactivate() {
        // Intentionally no drop to preserve data.
    }
}