<?php
if (!defined('ABSPATH')) exit;

add_action('wp_mail_failed', function(WP_Error $error) {
    error_log('[FGBW Email] wp_mail_failed: ' . implode(', ', $error->get_error_messages()));
});

class FGBW_Email {

    private static function placeholders(int $booking_id, array $payload): array {
        $name  = sanitize_text_field($payload['name']  ?? '');
        $email = sanitize_email(     $payload['email'] ?? '');
        $phone = sanitize_text_field($payload['phone'] ?? '');
        $additional_note = sanitize_textarea_field($payload['additional_note'] ?? '');
        $name_parts = explode(' ', trim($name), 2);
        $first_name = $name_parts[0] ?? '';
        $last_name  = $name_parts[1] ?? '';

        // Email logo URL — from settings, falling back to the plugin-bundled
        // transparent WebP (assets/images/email-logo.webp).
        // The bundled file has a genuine transparent background (RGBA WebP) so it
        // renders cleanly on the dark #111827 footer without any background box.
        // PNG: universally supported by all email clients (Gmail, Outlook, Apple Mail).
        // Transparent background. 480x231px source displayed at 240px = 2x retina sharp.
        $default_logo = FGBW_PLUGIN_URL . 'assets/images/email-logo.png';
        $email_logo_url = fgbw_get_option('email_logo_url', $default_logo);

        $trip_type  = sanitize_text_field($payload['trip_type']  ?? '');
        $order_type = sanitize_text_field($payload['order_type'] ?? '');
        $vehicle    = sanitize_text_field($payload['vehicle']    ?? '');

        $luggage  = $payload['luggage'] ?? [];
        $carry_on = (string)((int)($luggage['carry_on'] ?? $luggage['carry'] ?? 0));
        $checked  = (string)((int)($luggage['checked']  ?? 0));
        $oversize = (string)((int)($luggage['oversize'] ?? 0));

        $trip   = $payload['trip']   ?? [];
        $pickup = $trip['pickup']    ?? [];
        $return = $trip['return']    ?? [];

        $pickup_datetime  = sanitize_text_field($pickup['datetime'] ?? '');
        $pickup_date      = $pickup_datetime ? date('F j, Y', strtotime($pickup_datetime)) : '';
        $pickup_time      = $pickup_datetime ? date('g:i A',  strtotime($pickup_datetime)) : '';
        $pickup_loc       = self::loc_label_with_zip($pickup['pickup']  ?? null);
        $dropoff_loc      = self::loc_label_with_zip($pickup['dropoff'] ?? null);
        $passenger_count  = (string)((int)($pickup['passenger_count'] ?? 1));

        $pu_apt  = self::flight_info($pickup['pickup']  ?? null);
        $do_apt  = self::flight_info($pickup['dropoff'] ?? null);
        $airline_name  = $pu_apt['airline']      ?: $do_apt['airline'];
        $airline_iata  = $pu_apt['airline_iata'] ?: $do_apt['airline_iata'];
        $flight_number = $pu_apt['flight']       ?: $do_apt['flight'];
        $no_flight     = $pu_apt['no_flight']    || $do_apt['no_flight'];
        $airline_label = $airline_name ? "{$airline_name} ({$airline_iata})" : ($airline_iata ?: '—');
        $flight_label  = $no_flight ? 'Not provided' : ($flight_number ?: '—');

        $pickup_zip  = self::loc_zip($pickup['pickup']  ?? null);
        $dropoff_zip = self::loc_zip($pickup['dropoff'] ?? null);
        $pickup_stops_html  = self::stops_html($pickup['stops']  ?? []);
        $pickup_stops_plain = self::stops_plain($pickup['stops'] ?? []);
        $pu_stop_zips = [];
        foreach (($pickup['stops'] ?? []) as $i => $s) {
            $z = self::loc_zip($s); if ($z) $pu_stop_zips[] = 'Stop '.($i+1).': '.$z;
        }

        $is_round = ($trip_type === 'round_trip');
        $ret_dt   = sanitize_text_field($return['datetime'] ?? '');
        $ret_date = ($is_round && $ret_dt) ? date('F j, Y', strtotime($ret_dt)) : '';
        $ret_time = ($is_round && $ret_dt) ? date('g:i A',  strtotime($ret_dt)) : '';
        $ret_pu   = $is_round ? self::loc_label_with_zip($return['pickup']  ?? null) : '';
        $ret_do   = $is_round ? self::loc_label_with_zip($return['dropoff'] ?? null) : '';
        $ret_pu_zip = $is_round ? self::loc_zip($return['pickup']  ?? null) : '';
        $ret_do_zip = $is_round ? self::loc_zip($return['dropoff'] ?? null) : '';

        $rpa = $is_round ? self::flight_info($return['pickup']  ?? null) : [];
        $rda = $is_round ? self::flight_info($return['dropoff'] ?? null) : [];
        $ret_airline_name  = ($rpa['airline']      ?? '') ?: ($rda['airline']      ?? '');
        $ret_airline_iata  = ($rpa['airline_iata'] ?? '') ?: ($rda['airline_iata'] ?? '');
        $ret_flight_number = ($rpa['flight']       ?? '') ?: ($rda['flight']       ?? '');
        $ret_no_flight     = ($rpa['no_flight']    ?? false) || ($rda['no_flight'] ?? false);
        $ret_airline_label = $ret_airline_name ? "{$ret_airline_name} ({$ret_airline_iata})" : ($ret_airline_iata ?: '—');
        $ret_flight_label  = $ret_no_flight ? 'Not provided' : ($ret_flight_number ?: '—');

        $ret_stops_html  = $is_round ? self::stops_html($return['stops']  ?? []) : '';
        $ret_stops_plain = $is_round ? self::stops_plain($return['stops'] ?? []) : '';
        $ret_stop_zips = [];
        if ($is_round) {
            foreach (($return['stops'] ?? []) as $i => $s) {
                $z = self::loc_zip($s); if ($z) $ret_stop_zips[] = 'Stop '.($i+1).': '.$z;
            }
        }

        $na = $is_round ? '—' : 'N/A';

        return [
            '{booking_id}'               => (string)$booking_id,
            '{trip_type}'                => $trip_type,
            '{trip_type_label}'          => self::label_trip_type($trip_type),
            '{order_type}'               => $order_type,
            '{order_type_label}'         => self::label_order_type($order_type),
            '{vehicle}'                  => $vehicle ?: '—',
            '{name}'                     => $name,
            '{first_name}'               => $first_name,
            '{last_name}'                => $last_name,
            '{email}'                    => $email,
            '{phone}'                    => $phone,
            '{additional_note}'          => nl2br(esc_html($additional_note)) ?: '—',
            '{pickup_date}'              => $pickup_date  ?: '—',
            '{pickup_time}'              => $pickup_time  ?: '—',
            '{pickup_datetime}'          => $pickup_datetime ?: '—',
            '{pickup_location}'          => esc_html($pickup_loc)  ?: '—',
            '{dropoff_location}'         => esc_html($dropoff_loc) ?: '—',
            '{pickup_zip}'               => $pickup_zip   ?: '—',
            '{dropoff_zip}'              => $dropoff_zip  ?: '—',
            '{passenger_count}'          => $passenger_count,
            '{airline}'                  => esc_html($airline_label),
            '{flight_number}'            => esc_html($flight_label),
            '{pickup_stops_html}'        => $pickup_stops_html,
            '{pickup_stops_plain}'       => esc_html($pickup_stops_plain),
            '{pickup_stops_zips}'        => implode(', ', $pu_stop_zips) ?: '—',
            '{carry_on}'                 => $carry_on,
            '{checked}'                  => $checked,
            '{oversize}'                 => $oversize,
            '{is_round_trip}'            => $is_round ? 'Yes' : 'No',
            '{return_date}'              => $ret_date ?: $na,
            '{return_time}'              => $ret_time ?: $na,
            '{return_pickup_location}'   => esc_html($ret_pu)  ?: $na,
            '{return_dropoff_location}'  => esc_html($ret_do)  ?: $na,
            '{return_pickup_zip}'        => $ret_pu_zip ?: $na,
            '{return_dropoff_zip}'       => $ret_do_zip ?: $na,
            '{return_airline}'           => esc_html($ret_airline_label),
            '{return_flight_number}'     => esc_html($ret_flight_label),
            '{return_stops_html}'        => $ret_stops_html,
            '{return_stops_plain}'       => esc_html($ret_stops_plain),
            '{return_stops_zips}'        => implode(', ', $ret_stop_zips) ?: $na,
            '{pickup_summary}'           => self::segment_summary($pickup),
            '{return_summary}'           => $is_round ? self::segment_summary($return) : '',
            '{email_logo_url}'           => esc_url($email_logo_url),
        ];
    }

    private static function label_trip_type(string $t): string {
        return match($t) {
            'one_way'    => 'One Way',
            'round_trip' => 'Round Trip',
            default      => ucwords(str_replace('_', ' ', $t)) ?: '—',
        };
    }

    private static function label_order_type(string $t): string {
        return match($t) {
            'airport_pickup'  => 'Airport Pick-Up',
            'airport_dropoff' => 'Airport Drop-Off',
            'point_to_point'  => 'Point to Point',
            'hourly'          => 'Hourly',
            default           => ucwords(str_replace('_', ' ', $t)) ?: '—',
        };
    }

    private static function stops_html(array $stops): string {
        if (empty($stops)) return '';
        $html = '';
        foreach ($stops as $i => $stop) {
            $n   = $i + 1;
            $lbl = esc_html(self::loc_label($stop));
            $zip = self::loc_zip($stop);
            $zip_str = $zip ? " <span style='color:#6b7280;font-size:13px;'>(ZIP: ".esc_html($zip).")</span>" : '';
            $html .= "<tr>
              <td style='padding:7px 16px 7px 0;color:#6b7280;font-size:14px;white-space:nowrap;vertical-align:top;width:100px;'>Stop {$n}</td>
              <td style='padding:7px 0;font-size:14px;color:#111827;vertical-align:top;'>{$lbl}{$zip_str}</td>
            </tr>";
        }
        return $html;
    }

    private static function stops_plain(array $stops): string {
        if (empty($stops)) return '';
        $lines = [];
        foreach ($stops as $i => $stop) {
            $lbl = self::loc_label($stop);
            $zip = self::loc_zip($stop);
            $lines[] = "Stop ".($i+1).": {$lbl}".($zip ? " (ZIP: {$zip})" : '');
        }
        return implode("\n", $lines);
    }

    private static function flight_info(?array $loc): array {
        $empty = ['airline'=>'','airline_iata'=>'','flight'=>'','no_flight'=>false];
        if (!is_array($loc) || ($loc['mode'] ?? '') !== 'airport') return $empty;
        $a = $loc['airline'] ?? null;
        return [
            'airline'      => is_array($a) ? sanitize_text_field($a['airline_name'] ?? '') : '',
            'airline_iata' => is_array($a) ? sanitize_text_field($a['iata_code']    ?? '') : '',
            'flight'       => sanitize_text_field($loc['flight'] ?? ''),
            'no_flight'    => !empty($loc['no_flight_info']),
        ];
    }

    private static function loc_label(?array $loc): string {
        if (!is_array($loc)) return '';
        $mode = $loc['mode'] ?? '';
        if ($mode === 'address') {
            return sanitize_text_field($loc['address']['formatted_address'] ?? $loc['_rawText'] ?? '');
        }
        if ($mode === 'airport') {
            $a = $loc['airport'] ?? [];
            $name = sanitize_text_field($a['airport_name'] ?? '');
            $iata = sanitize_text_field($a['iata_code']    ?? '');
            return trim("{$name} ({$iata})");
        }
        return '';
    }

    private static function loc_zip(?array $loc): string {
        if (!is_array($loc)) return '';
        return sanitize_text_field($loc['zip'] ?? '');
    }

    /** Location label with ZIP appended if present (for email display) */
    private static function loc_label_with_zip(?array $loc): string {
        $label = self::loc_label($loc);
        $zip   = self::loc_zip($loc);
        if ($label && $zip) {
            return $label . ' (ZIP: ' . $zip . ')';
        }
        return $label;
    }

    private static function segment_summary(array $seg): string {
        $dt      = sanitize_text_field($seg['datetime'] ?? '');
        $pickup  = self::loc_label($seg['pickup']  ?? null);
        $dropoff = self::loc_label($seg['dropoff'] ?? null);
        $stops   = $seg['stops'] ?? [];
        $stops_txt = '';
        if (!empty($stops)) {
            $parts = [];
            foreach ($stops as $s) $parts[] = self::loc_label($s);
            $stops_txt = 'Stops: ' . implode(' → ', array_filter($parts)) . "\n";
        }
        return trim("Date/Time: {$dt}\nPick-Up: {$pickup}\n{$stops_txt}Drop-Off: {$dropoff}");
    }

    private static function apply_placeholders(string $content, array $ph): string {
        return strtr($content, $ph);
    }

    private static function log(string $msg): void {
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('[FGBW Email] ' . $msg);
    }

    public static function send_customer(int $booking_id, array $payload): void {
        $to = sanitize_email($payload['email'] ?? '');
        self::log("send_customer called. Booking #{$booking_id}. To: '{$to}'");
        if (!$to) { self::log("send_customer ABORTED: empty/invalid email."); return; }

        $ph   = self::placeholders($booking_id, $payload);
        $subj = fgbw_get_option('email_customer_subject', 'Your Reservation Was Successfully Submitted!');
        $body = self::resolve_template('email_customer_body',
                    FGBW_PLUGIN_DIR . 'templates/emails/customer.php');

        $result = wp_mail($to, self::apply_placeholders($subj, $ph),
                          self::apply_placeholders($body, $ph),
                          ['Content-Type: text/html; charset=UTF-8']);
        self::log($result ? "send_customer OK → {$to}" : "send_customer FAILED → {$to}");
    }

    public static function send_admin(int $booking_id, array $payload): void {
        $to = sanitize_email(fgbw_get_option('admin_email', get_option('admin_email')));
        self::log("send_admin called. Booking #{$booking_id}. To: '{$to}'");
        if (!$to) { self::log("send_admin ABORTED: empty/invalid email."); return; }

        $ph   = self::placeholders($booking_id, $payload);
        $subj = fgbw_get_option('email_admin_subject', 'New Reservation Submitted - {name}');
        $body = self::resolve_template('email_admin_body',
                    FGBW_PLUGIN_DIR . 'templates/emails/admin.php');
        $result = wp_mail($to, self::apply_placeholders($subj, $ph),
                          self::apply_placeholders($body, $ph),
                          ['Content-Type: text/html; charset=UTF-8']);
        self::log($result ? "send_admin OK → {$to}" : "send_admin FAILED → {$to}");
    }

    /**
     * Resolve the email body to use.
     *
     * Priority:
     *   1. A custom body saved in plugin settings — BUT only if it looks like
     *      the current full HTML template (contains <!DOCTYPE or <table). This
     *      guards against the common case where an admin saved the Settings page
     *      while the old bare-bones template (<h2>Booking Confirmed…</h2>) was
     *      still in the textarea, which would then permanently override the new
     *      rich HTML file we ship.
     *   2. The on-disk template file — always current, always the rich layout.
     */
    private static function resolve_template(string $option_key, string $file_path): string {
        $stored = trim(fgbw_get_option($option_key, ''));

        // Treat as valid custom template only if it contains full HTML structure
        // markers. A bare old template (just <h2>…<pre>…) will not match and
        // we fall through to the file, which is exactly what we want.
        $looks_like_full_template = (
            stripos($stored, '<!DOCTYPE') !== false ||
            stripos($stored, '<table') !== false ||
            stripos($stored, '{pickup_date}') !== false
        );

        if (!empty($stored) && $looks_like_full_template) {
            return $stored;
        }

        // Fall back to the on-disk file template (the rich HTML layout).
        if (file_exists($file_path)) {
            return file_get_contents($file_path);
        }

        // Last resort: return stored value even if it looks old, rather than empty.
        return $stored;
    }
}
