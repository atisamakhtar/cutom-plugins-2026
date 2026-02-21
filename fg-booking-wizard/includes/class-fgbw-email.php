<?php
if (!defined('ABSPATH')) exit;

/**
 * Hook into wp_mail_failed to catch ALL mail errors regardless of SMTP plugin.
 * Logs to WordPress debug log when WP_DEBUG + WP_DEBUG_LOG are enabled.
 */
add_action('wp_mail_failed', function(WP_Error $error) {
    error_log('[FGBW Email] wp_mail_failed: ' . implode(', ', $error->get_error_messages()));
});

class FGBW_Email {
    private static function placeholders(int $booking_id, array $payload): array {
        $name = sanitize_text_field($payload['name'] ?? '');
        $email = sanitize_email($payload['email'] ?? '');
        $phone = sanitize_text_field($payload['phone'] ?? '');
        $trip_type = sanitize_text_field($payload['trip_type'] ?? '');
        $order_type = sanitize_text_field($payload['order_type'] ?? '');
        $vehicle = sanitize_text_field($payload['vehicle'] ?? '');

        // Extract first and last name from full name
        $name_parts = explode(' ', trim($name), 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $name_parts[1] ?? '';

        $trip = $payload['trip'] ?? [];
        $pickup = $trip['pickup'] ?? [];
        $return = $trip['return'] ?? [];

        $pickup_summary = self::segment_summary($pickup);
        $return_summary = ($trip_type === 'round_trip') ? self::segment_summary($return) : '';

        $passenger_count = (string) ((int)($pickup['passenger_count'] ?? 1));

        return [
            '{booking_id}' => (string)$booking_id,
            '{name}' => $name,
            '{first_name}' => $first_name,
            '{last_name}' => $last_name,
            '{email}' => $email,
            '{phone}' => $phone,
            '{trip_type}' => $trip_type,
            '{order_type}' => $order_type,
            '{pickup_summary}' => $pickup_summary,
            '{return_summary}' => $return_summary,
            '{vehicle}' => $vehicle,
            '{passenger_count}' => $passenger_count,
        ];
    }

    private static function segment_summary(array $seg): string {
        $dt = sanitize_text_field($seg['datetime'] ?? '');
        $pickup = self::loc_summary($seg['pickup'] ?? null);
        $dropoff = self::loc_summary($seg['dropoff'] ?? null);
        $stops = $seg['stops'] ?? [];
        $stops_txt = '';
        if (is_array($stops) && !empty($stops)) {
            $parts = [];
            foreach ($stops as $s) $parts[] = self::loc_summary($s);
            $stops_txt = 'Stops: ' . implode(' → ', array_filter($parts)) . "\n";
        }

        return trim("Date/Time: {$dt}\nPick-Up: {$pickup}\n{$stops_txt}Drop-Off: {$dropoff}");
    }

    private static function loc_summary($loc): string {
        if (!is_array($loc)) return '';
        $mode = $loc['mode'] ?? '';
        if ($mode === 'address') {
            return sanitize_text_field($loc['address']['formatted_address'] ?? '');
        }
        if ($mode === 'airport') {
            $a = $loc['airport'] ?? [];
            $name = sanitize_text_field($a['airport_name'] ?? '');
            $iata = sanitize_text_field($a['iata_code'] ?? '');
            return trim("{$name} ({$iata})");
        }
        return '';
    }

    private static function apply_placeholders(string $content, array $ph): string {
        return strtr($content, $ph);
    }

    /**
     * Log email results to WordPress debug log.
     * Enable by adding to wp-config.php:
     *   define('WP_DEBUG', true);
     *   define('WP_DEBUG_LOG', true);
     */
    private static function log(string $msg): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FGBW Email] ' . $msg);
        }
    }

    public static function send_customer(int $booking_id, array $payload): void {
        $to = sanitize_email($payload['email'] ?? '');

        self::log("send_customer called. Booking #{$booking_id}. To: '{$to}'");

        if (!$to) {
            self::log("send_customer ABORTED: empty or invalid customer email.");
            return;
        }

        $ph   = self::placeholders($booking_id, $payload);
        $subj = fgbw_get_option('email_customer_subject', 'Your booking #{booking_id} is received');
        $body = fgbw_get_option('email_customer_body', file_get_contents(FGBW_PLUGIN_DIR . 'templates/emails/customer.php'));

        $subj = self::apply_placeholders($subj, $ph);
        $body = self::apply_placeholders($body, $ph);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        self::log("send_customer calling wp_mail(). Subject: '{$subj}'");

        $result = wp_mail($to, $subj, $body, $headers);

        if ($result) {
            self::log("send_customer wp_mail() returned TRUE for {$to}. Check spam folder if not received.");
        } else {
            global $phpmailer;
            $error = isset($phpmailer) ? $phpmailer->ErrorInfo : 'Unknown error — phpmailer not available';
            self::log("send_customer wp_mail() FAILED for {$to}. Error: {$error}");
        }
    }

    public static function send_admin(int $booking_id, array $payload): void {
        $to = fgbw_get_option('admin_email', get_option('admin_email'));
        $to = sanitize_email($to);

        self::log("send_admin called. Booking #{$booking_id}. To: '{$to}'");

        if (!$to) {
            self::log("send_admin ABORTED: empty or invalid admin email.");
            return;
        }

        $ph   = self::placeholders($booking_id, $payload);
        $subj = fgbw_get_option('email_admin_subject', 'New booking #{booking_id} received');
        $body = fgbw_get_option('email_admin_body', file_get_contents(FGBW_PLUGIN_DIR . 'templates/emails/admin.php'));

        $subj = self::apply_placeholders($subj, $ph);
        $body = self::apply_placeholders($body, $ph);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        self::log("send_admin calling wp_mail(). Subject: '{$subj}'");

        $result = wp_mail($to, $subj, $body, $headers);

        if ($result) {
            self::log("send_admin wp_mail() returned TRUE for {$to}. Check spam folder if not received.");
        } else {
            global $phpmailer;
            $error = isset($phpmailer) ? $phpmailer->ErrorInfo : 'Unknown error — phpmailer not available';
            self::log("send_admin wp_mail() FAILED for {$to}. Error: {$error}");
        }
    }
}