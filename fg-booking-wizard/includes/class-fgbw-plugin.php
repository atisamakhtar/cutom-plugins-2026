<?php
if (!defined('ABSPATH')) exit;

require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-settings.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-ajax.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-shortcode.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-email.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-airport-importer.php';

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

// add_action('admin_init', function() {

//     if (!current_user_can('manage_options')) return;

//     if (isset($_GET['fg_import_airports'])) {

//         $file = FGBW_PLUGIN_DIR . 'airports.csv';

//         FGBW_Airport_Importer::import_from_csv($file);

//         wp_die('Airports Imported Successfully');
//     }
// });

// add_action('admin_init', function() {

//     if (!current_user_can('manage_options')) return;

//     if (isset($_GET['fg_import_airlines'])) {

//         $file = FGBW_PLUGIN_DIR . 'airlines.dat';

//         FGBW_Airline_Importer::import_from_dat($file);

//         wp_die('Airlines Imported Successfully');
//     }
// });