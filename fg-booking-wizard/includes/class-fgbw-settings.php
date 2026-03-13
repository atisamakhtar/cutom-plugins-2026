<?php
if (!defined('ABSPATH')) exit;

class FGBW_Settings {
    public function init(): void {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function menu(): void {
        add_options_page(
            'FG Booking Wizard',
            'FG Booking Wizard',
            'manage_options',
            'fgbw-settings',
            [$this, 'render']
        );
    }

    public function register_settings(): void {
        register_setting('fgbw_settings_group', 'fgbw_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [],
        ]);

        add_settings_section('fgbw_api', 'API Keys', '__return_false', 'fgbw-settings');

        add_settings_field('google_places_key', 'Google Places API Key (frontend)', [$this, 'field_google'], 'fgbw-settings', 'fgbw_api');
        add_settings_field('aviationstack_key', 'Aviationstack API Key (server-only)', [$this, 'field_aviation'], 'fgbw-settings', 'fgbw_api');
        add_settings_field('admin_email', 'Admin Notification Email', [$this, 'field_admin_email'], 'fgbw-settings', 'fgbw_api');
        add_settings_field('email_logo_url', 'Email Logo URL', [$this, 'field_email_logo'], 'fgbw-settings', 'fgbw_api');

        add_settings_section('fgbw_emails', 'Email Templates', '__return_false', 'fgbw-settings');

        add_settings_field('email_customer_subject', 'Customer Subject', [$this, 'field_customer_subject'], 'fgbw-settings', 'fgbw_emails');
        add_settings_field('email_customer_body', 'Customer Body', [$this, 'field_customer_body'], 'fgbw-settings', 'fgbw_emails');

        add_settings_field('email_admin_subject', 'Admin Subject', [$this, 'field_admin_subject'], 'fgbw-settings', 'fgbw_emails');
        add_settings_field('email_admin_body', 'Admin Body', [$this, 'field_admin_body'], 'fgbw-settings', 'fgbw_emails');
    }

    public function sanitize_settings($input): array {
        $input = is_array($input) ? $input : [];

        $out = [];
        $out['google_places_key'] = sanitize_text_field($input['google_places_key'] ?? '');
        $out['aviationstack_key'] = sanitize_text_field($input['aviationstack_key'] ?? '');
        $out['admin_email'] = sanitize_email($input['admin_email'] ?? get_option('admin_email'));
        $out['email_logo_url'] = esc_url_raw($input['email_logo_url'] ?? '');

        // Email templates
        $out['email_customer_subject'] = sanitize_text_field($input['email_customer_subject'] ?? 'Your booking #{booking_id} is received');
        $out['email_admin_subject'] = sanitize_text_field($input['email_admin_subject'] ?? 'New booking #{booking_id} received');

        // Email body templates contain full HTML (DOCTYPE, head, inline styles, etc.)
        // wp_kses would strip those tags, silently corrupting the saved template and
        // causing resolve_template() to always fall back to the on-disk file.
        // We store the raw value here; only admins can reach this settings page
        // (manage_options capability is checked by WordPress before this runs),
        // so trusting their HTML input is safe — same as the built-in Custom HTML widget.
        $out['email_customer_body'] = $input['email_customer_body'] ?? '';
        $out['email_admin_body']    = $input['email_admin_body']    ?? '';

        return $out;
    }

    public function render(): void {
        if (!current_user_can('manage_options')) return;

        echo '<div class="wrap"><h1>FG Booking Wizard</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('fgbw_settings_group');
        do_settings_sections('fgbw-settings');
        submit_button();
        echo '</form></div>';
    }

    public function field_google(): void {
        $v = esc_attr(fgbw_get_option('google_places_key', ''));
        echo "<input type='text' class='regular-text' name='fgbw_settings[google_places_key]' value='{$v}' />";
        echo "<p class='description'>This key is exposed to frontend JS (Google requires it).</p>";
    }

    public function field_aviation(): void {
        $v = esc_attr(fgbw_get_option('aviationstack_key', ''));
        echo "<input type='password' class='regular-text' name='fgbw_settings[aviationstack_key]' value='{$v}' autocomplete='new-password' />";
        echo "<p class='description'>This key stays server-side only. All requests are proxied via WP AJAX.</p>";
    }

    public function field_admin_email(): void {
        $v = esc_attr(fgbw_get_option('admin_email', get_option('admin_email')));
        echo "<input type='email' class='regular-text' name='fgbw_settings[admin_email]' value='{$v}' />";
    }

    public function field_email_logo(): void {
        $v = esc_attr(fgbw_get_option('email_logo_url', ''));
        $default = 'https://optimusfleets.us/wp-content/uploads/2026/02/optimus-logo-orange.webp';
        echo "<input type='url' class='large-text' name='fgbw_settings[email_logo_url]' value='{$v}' placeholder='{$default}' />";
        echo "<p class='description'>Full URL to your logo image displayed in email footers. Leave blank to use the default Optimus Fleets logo. Recommended: PNG or WebP, transparent background, max 200px wide.</p>";
        if ($v) {
            echo "<p><img src='" . esc_url($v) . "' style='max-height:50px;margin-top:6px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#fff;' alt='Email logo preview' /></p>";
        }
    }

    public function field_customer_subject(): void {
        $v = esc_attr(fgbw_get_option('email_customer_subject', 'Your Reservation Was Successfully Submitted!'));
        echo "<input type='text' class='regular-text' name='fgbw_settings[email_customer_subject]' value='{$v}' />";
        $ph = '{booking_id} {name} {first_name} {last_name} {email} {phone} {trip_type_label} {order_type_label} {vehicle} {passenger_count} {pickup_date} {pickup_time} {pickup_location} {dropoff_location} {pickup_stops_html} {airline} {flight_number} {is_round_trip} {return_date} {return_time} {return_pickup_location} {return_dropoff_location} {return_airline} {return_flight_number} {carry_on} {checked} {oversize} {additional_note} {pickup_zip} {dropoff_zip}';
        echo "<p class='description'>Available placeholders: <code>" . implode('</code> <code>', explode(' ', $ph)) . "</code></p>";
    }

    public function field_customer_body(): void {
        // Show the current effective template: custom saved version if it is a
        // full HTML template, otherwise the on-disk file (same logic as sending).
        $stored = trim(fgbw_get_option('email_customer_body', ''));
        $is_custom = !empty($stored) && (
            stripos($stored, '<!DOCTYPE') !== false ||
            stripos($stored, '<table')    !== false ||
            stripos($stored, '{pickup_date}') !== false
        );
        $v = $is_custom ? $stored : file_get_contents(FGBW_PLUGIN_DIR . 'templates/emails/customer.php');
        echo "<textarea class='large-text' rows='20' name='fgbw_settings[email_customer_body]'>".esc_textarea($v)."</textarea>";
        echo "<p class='description'><strong>Tip:</strong> Clear this field and save to reset to the built-in HTML template. Full HTML (including DOCTYPE, inline styles) is preserved exactly as-is on save.</p>";
        $ph = '{booking_id} {name} {first_name} {last_name} {email} {phone} {trip_type_label} {order_type_label} {vehicle} {passenger_count} {pickup_date} {pickup_time} {pickup_location} {dropoff_location} {pickup_stops_html} {airline} {flight_number} {is_round_trip} {return_date} {return_time} {return_pickup_location} {return_dropoff_location} {return_airline} {return_flight_number} {carry_on} {checked} {oversize} {additional_note} {pickup_zip} {dropoff_zip}';
        echo "<p class='description'>Available placeholders: <code>" . implode('</code> <code>', explode(' ', $ph)) . "</code></p>";
    }

    public function field_admin_subject(): void {
        $v = esc_attr(fgbw_get_option('email_admin_subject', 'New Reservation Submitted - {name}'));
        echo "<input type='text' class='regular-text' name='fgbw_settings[email_admin_subject]' value='{$v}' />";
        $ph = '{booking_id} {name} {first_name} {last_name} {email} {phone} {trip_type_label} {order_type_label} {vehicle} {passenger_count} {pickup_date} {pickup_time} {pickup_location} {dropoff_location} {pickup_stops_html} {airline} {flight_number} {is_round_trip} {return_date} {return_time} {return_pickup_location} {return_dropoff_location} {return_airline} {return_flight_number} {carry_on} {checked} {oversize} {additional_note} {pickup_zip} {dropoff_zip}';
        echo "<p class='description'>Available placeholders: <code>" . implode('</code> <code>', explode(' ', $ph)) . "</code></p>";
    }

    public function field_admin_body(): void {
        $stored = trim(fgbw_get_option('email_admin_body', ''));
        $is_custom = !empty($stored) && (
            stripos($stored, '<!DOCTYPE') !== false ||
            stripos($stored, '<table')    !== false ||
            stripos($stored, '{pickup_date}') !== false
        );
        $v = $is_custom ? $stored : file_get_contents(FGBW_PLUGIN_DIR . 'templates/emails/admin.php');
        echo "<textarea class='large-text' rows='20' name='fgbw_settings[email_admin_body]'>".esc_textarea($v)."</textarea>";
        echo "<p class='description'><strong>Tip:</strong> Clear this field and save to reset to the built-in HTML template. Full HTML (including DOCTYPE, inline styles) is preserved exactly as-is on save.</p>";
        $ph = '{booking_id} {name} {first_name} {last_name} {email} {phone} {trip_type_label} {order_type_label} {vehicle} {passenger_count} {pickup_date} {pickup_time} {pickup_location} {dropoff_location} {pickup_stops_html} {airline} {flight_number} {is_round_trip} {return_date} {return_time} {return_pickup_location} {return_dropoff_location} {return_airline} {return_flight_number} {carry_on} {checked} {oversize} {additional_note} {pickup_zip} {dropoff_zip}';
        echo "<p class='description'>Available placeholders: <code>" . implode('</code> <code>', explode(' ', $ph)) . "</code></p>";
    }
}