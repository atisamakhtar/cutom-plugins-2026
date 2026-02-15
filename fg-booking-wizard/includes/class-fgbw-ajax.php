<?php
if (!defined('ABSPATH')) exit;

require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-db.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-email.php';

class FGBW_AJAX {
    public function init(): void {
        // Public
        add_action('wp_ajax_nopriv_fgbw_search_airports', [$this, 'search_airports']);
        add_action('wp_ajax_fgbw_search_airports', [$this, 'search_airports']);

        add_action('wp_ajax_nopriv_fgbw_search_airlines', [$this, 'search_airlines']);
        add_action('wp_ajax_fgbw_search_airlines', [$this, 'search_airlines']);

        add_action('wp_ajax_nopriv_fgbw_validate_flight', [$this, 'validate_flight']);
        add_action('wp_ajax_fgbw_validate_flight', [$this, 'validate_flight']);

        add_action('wp_ajax_nopriv_fgbw_submit_booking', [$this, 'submit_booking']);
        add_action('wp_ajax_fgbw_submit_booking', [$this, 'submit_booking']);
    }

    private function verify_nonce(): void {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'fgbw_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token.'], 403);
        }
    }

    private function aviation_key(): string {
        $k = (string) fgbw_get_option('aviationstack_key', '');
        return trim($k);
    }

    private function aviation_get(string $endpoint, array $query): array {
        $key = $this->aviation_key();
        if (!$key) return ['ok' => false, 'message' => 'Aviationstack key not configured.'];

        $base = 'http://api.aviationstack.com/v1/';
        $query['access_key'] = $key;

        $url = add_query_arg($query, $base . ltrim($endpoint, '/'));

        $cache_key = 'fgbw_av_' . md5($url);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $resp = wp_remote_get($url, ['timeout' => 12]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'message' => $resp->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            $out = ['ok' => false, 'message' => 'Aviationstack error.', 'raw' => $data];
            set_transient($cache_key, $out, 30);
            return $out;
        }

        $out = ['ok' => true, 'data' => $data];
        set_transient($cache_key, $out, 60);
        return $out;
    }

    public function search_airports(): void {
        $this->verify_nonce();
        $q = sanitize_text_field(wp_unslash($_POST['q'] ?? ''));
        if (mb_strlen($q) < 2) wp_send_json_success(['items' => []]);

        // Aviationstack endpoints vary by plan.
        // Many plans support /airports?search=...
        $res = $this->aviation_get('airports', ['search' => $q, 'limit' => 10]);

        if (!$res['ok']) wp_send_json_error(['message' => $res['message']], 400);

        $items = $res['data']['data'] ?? [];
        $mapped = array_map(function ($a) {
            return [
                'iata_code' => sanitize_text_field($a['iata_code'] ?? ''),
                'icao_code' => sanitize_text_field($a['icao_code'] ?? ''),
                'airport_name' => sanitize_text_field($a['airport_name'] ?? ''),
                'country_name' => sanitize_text_field($a['country_name'] ?? ''),
                'city' => sanitize_text_field($a['city'] ?? ''),
            ];
        }, $items);

        wp_send_json_success(['items' => $mapped]);
    }

    public function search_airlines(): void {
        $this->verify_nonce();
        $q = sanitize_text_field(wp_unslash($_POST['q'] ?? ''));
        if (mb_strlen($q) < 2) wp_send_json_success(['items' => []]);

        $res = $this->aviation_get('airlines', ['search' => $q, 'limit' => 10]);
        if (!$res['ok']) wp_send_json_error(['message' => $res['message']], 400);

        $items = $res['data']['data'] ?? [];
        $mapped = array_map(function ($a) {
            return [
                'iata_code' => sanitize_text_field($a['iata_code'] ?? ''),
                'icao_code' => sanitize_text_field($a['icao_code'] ?? ''),
                'airline_name' => sanitize_text_field($a['airline_name'] ?? ''),
            ];
        }, $items);

        wp_send_json_success(['items' => $mapped]);
    }

    public function validate_flight(): void {
        $this->verify_nonce();

        $airport_iata = sanitize_text_field(wp_unslash($_POST['airport_iata'] ?? ''));
        $airline_iata = sanitize_text_field(wp_unslash($_POST['airline_iata'] ?? ''));
        $flight_number = sanitize_text_field(wp_unslash($_POST['flight_number'] ?? ''));

        // Optional validation (depends on plan / endpoints)
        // We keep it permissive: return success even if endpoint not available.
        if (!$airport_iata || !$flight_number) {
            wp_send_json_success(['valid' => true]);
        }

        // Attempt "flights" endpoint if available
        $res = $this->aviation_get('flights', [
            'flight_iata' => $flight_number,
            'airline_iata' => $airline_iata,
            'limit' => 1,
        ]);

        if (!$res['ok']) {
            wp_send_json_success(['valid' => true, 'note' => 'Validation skipped']);
        }

        $items = $res['data']['data'] ?? [];
        wp_send_json_success(['valid' => !empty($items)]);
    }

    public function submit_booking(): void {
        $this->verify_nonce();

        $hp = sanitize_text_field(wp_unslash($_POST['company_hp'] ?? ''));
        if ($hp) wp_send_json_error(['message' => 'Spam detected.'], 400);

        $raw = wp_unslash($_POST['payload'] ?? '');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) wp_send_json_error(['message' => 'Invalid payload.'], 400);

        // Prevent duplicates via token transient
        $token = sanitize_text_field($payload['submission_token'] ?? '');
        if (!$token) wp_send_json_error(['message' => 'Missing submission token.'], 400);

        $tok_key = 'fgbw_tok_' . md5($token);
        if (get_transient($tok_key)) {
            wp_send_json_error(['message' => 'Duplicate submission detected.'], 409);
        }
        set_transient($tok_key, 1, 10 * MINUTE_IN_SECONDS);

        // Sanitize
        $trip_type = sanitize_text_field($payload['trip_type'] ?? '');
        $order_type = sanitize_text_field($payload['order_type'] ?? '');
        $vehicle = sanitize_text_field($payload['vehicle'] ?? '');

        $name = fgbw_sanitize_text($payload['name'] ?? '');
        $email = fgbw_sanitize_email($payload['email'] ?? '');
        $phone = fgbw_sanitize_phone($payload['phone'] ?? '');

        if (!$name || !$email || !$phone) {
            wp_send_json_error(['message' => 'Contact fields are required.'], 400);
        }
        if (!in_array($trip_type, ['one_way', 'round_trip'], true)) {
            wp_send_json_error(['message' => 'Invalid trip type.'], 400);
        }
        if (!$order_type) wp_send_json_error(['message' => 'Order type is required.'], 400);
        if (!$vehicle) wp_send_json_error(['message' => 'Vehicle is required.'], 400);

        $trip = $payload['trip'] ?? [];
        $pickup = $trip['pickup'] ?? null;
        $return = $trip['return'] ?? null;

        // Server-side validation (minimal but strict enough)
        $pickup_count = isset($pickup['passenger_count']) ? (int)$pickup['passenger_count'] : 0;
        if ($pickup_count < 1) wp_send_json_error(['message' => 'Passenger count invalid.'], 400);

        if (empty($pickup['datetime'])) wp_send_json_error(['message' => 'Pickup datetime required.'], 400);

        if ($trip_type === 'round_trip') {
            $ret_count = isset($return['passenger_count']) ? (int)$return['passenger_count'] : 0;
            if ($ret_count < 1) wp_send_json_error(['message' => 'Return passenger count invalid.'], 400);
            if (empty($return['datetime'])) wp_send_json_error(['message' => 'Return datetime required.'], 400);
        }

        $full_payload_json = fgbw_json_encode($payload);
        $pickup_json = fgbw_json_encode($pickup);
        $return_json = $trip_type === 'round_trip' ? fgbw_json_encode($return) : null;

        $booking_id = FGBW_DB::insert_booking([
            'created_at' => current_time('mysql'),
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'trip_type' => $trip_type,
            'order_type' => $order_type,
            'passenger_count' => $pickup_count, // stored as primary count (pickup). Return is in return_json.
            'pickup_json' => $pickup_json,
            'return_json' => $return_json,
            'vehicle' => $vehicle,
            'full_payload_json' => $full_payload_json,
        ]);

        if (!$booking_id) wp_send_json_error(['message' => 'DB insert failed.'], 500);

        // Emails
        FGBW_Email::send_customer($booking_id, $payload);
        FGBW_Email::send_admin($booking_id, $payload);

        wp_send_json_success(['booking_id' => $booking_id]);
    }
}