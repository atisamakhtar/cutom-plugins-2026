<?php
if (!defined('ABSPATH')) exit;

class FGBW_Shortcode {
    public function init(): void {
        add_shortcode('fg_booking_form', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets(): void {
        // CSS
        wp_register_style('fgbw-booking', FGBW_PLUGIN_URL . 'assets/css/booking-wizard.css', [], FGBW_VERSION);

        // Vendor (CDN) - for production you can bundle locally later.
        wp_register_style('fgbw-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
        wp_register_script('fgbw-select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);

        wp_register_style('fgbw-flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
        wp_register_script('fgbw-flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);

        // Main JS
        wp_register_script('fgbw-wizard', FGBW_PLUGIN_URL . 'assets/js/booking-wizard.js', ['jquery', 'fgbw-select2', 'fgbw-flatpickr'], FGBW_VERSION, true);
    }

    public function render($atts = [], $content = ''): string {
        wp_enqueue_style('fgbw-booking');
        wp_enqueue_style('fgbw-select2');
        wp_enqueue_style('fgbw-flatpickr');

        wp_enqueue_script('fgbw-select2');
        wp_enqueue_script('fgbw-flatpickr');
        wp_enqueue_script('fgbw-wizard');

        $config = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fgbw_nonce'),
            'googlePlacesKey' => fgbw_get_option('google_places_key', ''),
            'dateFormat' => 'Y-m-d h:i K',
            'orderTypeGroups' => $this->order_type_groups(),
            'vehicles' => $this->vehicles(),
        ];

        wp_localize_script('fgbw-wizard', 'FGBW', $config);

        ob_start();
        include FGBW_PLUGIN_DIR . 'templates/booking-wizard.php';
        return (string)ob_get_clean();
    }

    private function order_type_groups(): array {
        // Return a single optgroup with the requested service labels.
        return [
            [
                'label' => 'Services',
                'options' => [
                    ['id' => 'amtrak_transportation', 'text' => 'Amtrak Transportation'],
                    ['id' => 'airport_transportation', 'text' => 'Airport Transportation'],
                    ['id' => 'corporate_transportation', 'text' => 'Corporate Transportation'],
                    ['id' => 'cruise_terminal_transportation', 'text' => 'Cruise Terminal Transportation'],
                    ['id' => 'event_transportation', 'text' => 'Event Transportation'],
                    ['id' => 'funeral_transportation', 'text' => 'Funeral Transportation'],
                    ['id' => 'hourly_transportation', 'text' => 'Hourly Transportation'],
                    ['id' => 'point_to_point_services', 'text' => 'Point to Point Services'],
                    ['id' => 'prom_transportation', 'text' => 'Prom Transportation'],
                    ['id' => 'sprinter_van_services', 'text' => 'Sprinter Van Services'],
                    ['id' => 'wedding_transportation', 'text' => 'Wedding Transportation'],
                ],
            ],
        ];
    }

    private function vehicles(): array {
        $default = [
            ['id' => 'cadillac_escalade', 'name' => 'Cadillac Escalade', 'desc' => 'Luxury SUV', 'seats' => 6],
            ['id' => 'suburban_lt', 'name' => 'Suburban LT', 'desc' => 'Full-size SUV', 'seats' => 7],
            ['id' => 'sprinter_van', 'name' => 'Mercedes Sprinter Van', 'desc' => 'Group Van', 'seats' => 12],
        ];
        return apply_filters('fgbw_vehicles', $default);
    }
}