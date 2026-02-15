<?php
if (!defined('ABSPATH')) exit;

class FGBW_Email {
    private static function placeholders(int $booking_id, array $payload): array {
        $name = sanitize_text_field($payload['name'] ?? '');
        $trip_type = sanitize_text_field($payload['trip_type'] ?? '');
        $order_type = sanitize_text_field($payload['order_type'] ?? '');
        $vehicle = sanitize_text_field($payload['vehicle'] ?? '');

        $trip = $payload['trip'] ?? [];
        $pickup = $trip['pickup'] ?? [];
        $return = $trip['return'] ?? [];

        $pickup_summary = self::segment_summary($pickup);
        $return_summary = ($trip_type === 'round_trip') ? self::segment_summary($return) : '';

        $passenger_count = (string) ((int)($pickup['passenger_count'] ?? 1));

        return [
            '{booking_id}' => (string)$booking_id,
            '{name}' => $name,
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

    public static function send_customer(int $booking_id, array $payload): void {
        $to = sanitize_email($payload['email'] ?? '');
        if (!$to) return;

        $ph = self::placeholders($booking_id, $payload);

        $subj = fgbw_get_option('email_customer_subject', 'Your booking #{booking_id} is received');
        $body = fgbw_get_option('email_customer_body', file_get_contents(FGBW_PLUGIN_DIR . 'templates/emails/customer.php'));

        $subj = self::apply_placeholders($subj, $ph);
        $body = self::apply_placeholders($body, $ph);

        wp_mail($to, $subj, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    public static function send_admin(int $booking_id, array $payload): void {
        $to = fgbw_get_option('admin_email', get_option('admin_email'));
        $to = sanitize_email($to);
        if (!$to) return;

        $ph = self::placeholders($booking_id, $payload);

        $subj = fgbw_get_option('email_admin_subject', 'New booking #{booking_id} received');
        $body = fgbw_get_option('email_admin_body', file_get_contents(FGBW_PLUGIN_DIR . 'templates/emails/admin.php'));

        $subj = self::apply_placeholders($subj, $ph);
        $body = self::apply_placeholders($body, $ph);

        wp_mail($to, $subj, $body, ['Content-Type: text/html; charset=UTF-8']);
    }
}