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

        // Email templates
        $out['email_customer_subject'] = sanitize_text_field($input['email_customer_subject'] ?? 'Your booking #{booking_id} is received');
        $out['email_admin_subject'] = sanitize_text_field($input['email_admin_subject'] ?? 'New booking #{booking_id} received');

        // Allow basic HTML
        $allowed = wp_kses_allowed_html('post');
        $out['email_customer_body'] = wp_kses($input['email_customer_body'] ?? '', $allowed);
        $out['email_admin_body'] = wp_kses($input['email_admin_body'] ?? '', $allowed);

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

    public function field_customer_subject(): void {
        $v = esc_attr(fgbw_get_option('email_customer_subject', 'Your booking #{booking_id} is received'));
        echo "<input type='text' class='regular-text' name='fgbw_settings[email_customer_subject]' value='{$v}' />";
        $ph = '{booking_id} {name} {first_name} {last_name} {email} {phone} {trip_type} {order_type} {vehicle} {passenger_count} {pickup_summary} {return_summary} {carry_on} {checked} {oversize} {pickup_zip} {dropoff_zip} {return_pickup_zip} {return_dropoff_zip} {pickup_stops_zips} {return_stops_zips}';
        echo "<p class='description'>Available placeholders: <code>" . implode('</code> <code>', explode(' ', $ph)) . "</code></p>";
    }

    public function field_customer_body(): void {
        $v = fgbw_get_option('email_customer_body', file_get_contents(FGBW_PLUGIN_DIR . 'templates/emails/customer.php'));
        echo "<textarea class='large-text' rows='10' name='fgbw_settings[email_customer_body]'>".esc_textarea($v)."</textarea>";
        $ph = '{booking_id} {name} {first_name} {last_name} {email} {phone} {trip_type} {order_type} {vehicle} {passenger_count} {pickup_summary} {return_summary} {carry_on} {checked} {oversize} {pickup_zip} {dropoff_zip} {return_pickup_zip} {return_dropoff_zip} {pickup_stops_zips} {return_stops_zips}';
        echo "<p class='description'>Available placeholders: <code>" . implode('</code> <code>', explode(' ', $ph)) . "</code></p>";
    }

    public function field_admin_subject(): void {
        $v = esc_attr(fgbw_get_option('email_admin_subject', 'New booking #{booking_id} received'));
        echo "<input type='text' class='regular-text' name='fgbw_settings[email_admin_subject]' value='{$v}' />";
        $ph = '{booking_id} {name} {first_name} {last_name} {email} {phone} {trip_type} {order_type} {vehicle} {passenger_count} {pickup_summary} {return_summary} {carry_on} {checked} {oversize} {pickup_zip} {dropoff_zip} {return_pickup_zip} {return_dropoff_zip} {pickup_stops_zips} {return_stops_zips}';
        echo "<p class='description'>Available placeholders: <code>" . implode('</code> <code>', explode(' ', $ph)) . "</code></p>";
    }

    public function field_admin_body(): void {
        $v = fgbw_get_option('email_admin_body', file_get_contents(FGBW_PLUGIN_DIR . 'templates/emails/admin.php'));
        echo "<textarea class='large-text' rows='10' name='fgbw_settings[email_admin_body]'>".esc_textarea($v)."</textarea>";
        $ph = '{booking_id} {name} {first_name} {last_name} {email} {phone} {trip_type} {order_type} {vehicle} {passenger_count} {pickup_summary} {return_summary} {carry_on} {checked} {oversize} {pickup_zip} {dropoff_zip} {return_pickup_zip} {return_dropoff_zip} {pickup_stops_zips} {return_stops_zips}';
        echo "<p class='description'>Available placeholders: <code>" . implode('</code> <code>', explode(' ', $ph)) . "</code></p>";
    }
}