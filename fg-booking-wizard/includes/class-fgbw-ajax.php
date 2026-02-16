<?php
if (!defined('ABSPATH')) exit;

require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-db.php';
require_once FGBW_PLUGIN_DIR . 'includes/class-fgbw-email.php';

class FGBW_AJAX
{
    public function init(): void
    {
        // Public
        add_action('wp_ajax_nopriv_fgbw_search_airports', [$this, 'search_airports']);
        add_action('wp_ajax_fgbw_search_airports', [$this, 'search_airports']);

        add_action('wp_ajax_nopriv_fgbw_search_airlines', [$this, 'search_airlines']);
        add_action('wp_ajax_fgbw_search_airlines', [$this, 'search_airlines']);

        add_action('wp_ajax_nopriv_fgbw_validate_flight', [$this, 'validate_flight']);
        add_action('wp_ajax_fgbw_validate_flight', [$this, 'validate_flight']);

        add_action('wp_ajax_nopriv_fgbw_submit_booking', [$this, 'submit_booking']);
        add_action('wp_ajax_fgbw_submit_booking', [$this, 'submit_booking']);

        add_action('wp_ajax_fgbw_fetch_flight', [$this, 'fetch_flight_details']);
        add_action('wp_ajax_nopriv_fgbw_fetch_flight', [$this, 'fetch_flight_details']);
    }

    private function verify_nonce(): void
    {
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'fgbw_nonce')) {
            wp_send_json_error(['message' => 'Invalid security token.'], 403);
        }
    }

    private function aviation_key(): string
    {
        $k = (string) fgbw_get_option('aviationstack_key', '');
        return trim($k);
    }

    private function aviation_get(string $endpoint, array $query): array
    {
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

    public function search_airports()
    {
        $this->verify_nonce();

        global $wpdb;
        $table = $wpdb->prefix . 'fg_airports';

        $q = sanitize_text_field($_POST['q'] ?? '');

        if (strlen($q) < 2) {
            wp_send_json_success(['items' => []]);
        }

        // If exactly 3 letters → prioritize IATA
        if (preg_match('/^[A-Za-z]{3}$/', $q)) {

            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table
             WHERE iata_code = %s
             AND airport_type IN ('large_airport','medium_airport')
             LIMIT 10",
                    strtoupper($q)
                ),
                ARRAY_A
            );
        } else {

            $like = '%' . $wpdb->esc_like($q) . '%';

            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table
             WHERE
             (airport_name LIKE %s
              OR city LIKE %s)
             AND airport_type IN ('large_airport','medium_airport')
             AND iata_code != ''
             LIMIT 10",
                    $like,
                    $like
                ),
                ARRAY_A
            );
        }

        wp_send_json_success(['items' => $results]);
    }

    // public function search_airlines(): void
    // {
    //     $this->verify_nonce();
    //     $q = sanitize_text_field(wp_unslash($_POST['q'] ?? ''));
    //     if (mb_strlen($q) < 2) wp_send_json_success(['items' => []]);

    //     $res = $this->aviation_get('airlines', ['search' => $q, 'limit' => 10]);
    //     if (!$res['ok']) wp_send_json_error(['message' => $res['message']], 400);

    //     $items = $res['data']['data'] ?? [];
    //     $mapped = array_map(function ($a) {
    //         return [
    //             'iata_code' => sanitize_text_field($a['iata_code'] ?? ''),
    //             'icao_code' => sanitize_text_field($a['icao_code'] ?? ''),
    //             'airline_name' => sanitize_text_field($a['airline_name'] ?? ''),
    //         ];
    //     }, $items);

    //     wp_send_json_success(['items' => $mapped]);
    // }

    public function search_airlines()
    {

        $this->verify_nonce();

        global $wpdb;
        $table = $wpdb->prefix . 'fg_airlines';

        $q = sanitize_text_field($_POST['q'] ?? '');

        if (strlen($q) < 2) {
            wp_send_json_success(['items' => []]);
        }

        // If 2-letter IATA code search
        if (preg_match('/^[A-Za-z]{2}$/', $q)) {

            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table
                 WHERE iata_code = %s
                 AND active = 1
                 LIMIT 10",
                    strtoupper($q)
                ),
                ARRAY_A
            );
        } else {

            $like = '%' . $wpdb->esc_like($q) . '%';

            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table
                 WHERE airline_name LIKE %s
                 AND active = 1
                 LIMIT 10",
                    $like
                ),
                ARRAY_A
            );
        }

        wp_send_json_success(['items' => $results]);
    }

    public function validate_flight()
    {

        $this->verify_nonce();

        $airline_iata = sanitize_text_field($_POST['airline_iata'] ?? '');
        $flight_number = sanitize_text_field($_POST['flight_number'] ?? '');

        if (!$airline_iata || !$flight_number) {
            wp_send_json_success(['valid' => false]);
        }

        // $api_key = get_option('fgbw_aviationstack_key');
        $settings = get_option('fgbw_settings', []);
        $api_key  = $settings['aviationstack_key'] ?? '';

        if (!$api_key) {
            wp_send_json_error(['message' => 'API key missing']);
        }

        $flight_code = strtoupper($airline_iata . $flight_number);

        $response = wp_remote_get(
            "http://api.aviationstack.com/v1/flights?access_key={$api_key}&flight_iata={$flight_code}"
        );

        if (is_wp_error($response)) {
            wp_send_json_success(['valid' => false]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['data'][0])) {
            wp_send_json_success(['valid' => false]);
        }

        $flight = $body['data'][0];

        wp_send_json_success([
            'valid' => true,
            'airline' => $flight['airline']['name'],
            'flight_iata' => $flight['flight']['iata'],
            'status' => $flight['flight_status'],
            'departure_airport' => $flight['departure']['airport'],
            'departure_iata' => $flight['departure']['iata'],
            'departure_time' => $flight['departure']['scheduled'],
            'arrival_airport' => $flight['arrival']['airport'],
            'arrival_iata' => $flight['arrival']['iata'],
            'arrival_time' => $flight['arrival']['scheduled'],
        ]);
    }

    public function submit_booking()
    {
        check_ajax_referer('fgbw_nonce', 'nonce');

        // BUG 1 FIX: The JS sends payload as JSON.stringify(), so we must decode it first.
        // Previously the raw JSON string was used directly as an array, making all data empty.
        $raw     = stripslashes( $_POST['payload'] ?? '' );
        $payload = json_decode( $raw, true );

        if ( ! is_array( $payload ) || empty( $payload ) ) {
            wp_send_json_error( [ 'message' => 'Invalid payload' ] );
        }

        // BUG 2 FIX: The JS places name/email/phone at the ROOT of the payload object
        // (payload.name, payload.email, payload.phone), NOT nested inside payload.contact.
        // Previously the code looked for $payload['contact']['name'] which never existed.
        $name  = sanitize_text_field( $payload['name']  ?? '' );
        $email = sanitize_email(      $payload['email'] ?? '' );
        $phone = sanitize_text_field( $payload['phone'] ?? '' );

        $trip   = $payload['trip']   ?? [];
        $pickup = $trip['pickup']    ?? [];
        $return = $trip['return']    ?? null;

        $row = [
            'name'             => $name,
            'email'            => $email,
            'phone'            => $phone,
            'trip_type'        => sanitize_text_field( $payload['trip_type']  ?? '' ),
            'order_type'       => sanitize_text_field( $payload['order_type'] ?? '' ),
            'passenger_count'  => intval( $pickup['passenger_count'] ?? 1 ),
            'pickup_json'      => wp_json_encode( $pickup ),
            'return_json'      => wp_json_encode( $return ),
            'vehicle'          => sanitize_text_field( $payload['vehicle'] ?? '' ),
            'full_payload_json'=> wp_json_encode( $payload ),
        ];

        $booking_id = FGBW_DB::insert_booking( $row );

        if ( ! $booking_id ) {
            wp_send_json_error( [ 'message' => 'Database insert failed' ] );
        }

        // BUG 3 FIX: Pass the full decoded $payload (not the flat $row) to FGBW_Email,
        // which needs the nested trip/pickup/dropoff structure for its placeholders.
        // Also call the proper HTML email methods instead of the plain-text fallback.
        FGBW_Email::send_customer( $booking_id, $payload );
        FGBW_Email::send_admin(    $booking_id, $payload );

        wp_send_json_success( [
            'booking_id' => $booking_id,
        ] );
    }

    // public function submit_booking(): void
    // {
    //     $this->verify_nonce();

    //     $hp = sanitize_text_field(wp_unslash($_POST['company_hp'] ?? ''));
    //     if ($hp) wp_send_json_error(['message' => 'Spam detected.'], 400);

    //     $raw = wp_unslash($_POST['payload'] ?? '');
    //     $payload = json_decode($raw, true);
    //     if (!is_array($payload)) wp_send_json_error(['message' => 'Invalid payload.'], 400);

    //     // Prevent duplicates via token transient
    //     $token = sanitize_text_field($payload['submission_token'] ?? '');
    //     if (!$token) wp_send_json_error(['message' => 'Missing submission token.'], 400);

    //     $tok_key = 'fgbw_tok_' . md5($token);
    //     if (get_transient($tok_key)) {
    //         wp_send_json_error(['message' => 'Duplicate submission detected.'], 409);
    //     }
    //     set_transient($tok_key, 1, 10 * MINUTE_IN_SECONDS);

    //     // Sanitize
    //     $trip_type = sanitize_text_field($payload['trip_type'] ?? '');
    //     $order_type = sanitize_text_field($payload['order_type'] ?? '');
    //     $vehicle = sanitize_text_field($payload['vehicle'] ?? '');

    //     $name = fgbw_sanitize_text($payload['name'] ?? '');
    //     $email = fgbw_sanitize_email($payload['email'] ?? '');
    //     $phone = fgbw_sanitize_phone($payload['phone'] ?? '');

    //     if (!$name || !$email || !$phone) {
    //         wp_send_json_error(['message' => 'Contact fields are required.'], 400);
    //     }
    //     if (!in_array($trip_type, ['one_way', 'round_trip'], true)) {
    //         wp_send_json_error(['message' => 'Invalid trip type.'], 400);
    //     }
    //     if (!$order_type) wp_send_json_error(['message' => 'Order type is required.'], 400);
    //     if (!$vehicle) wp_send_json_error(['message' => 'Vehicle is required.'], 400);

    //     $trip = $payload['trip'] ?? [];
    //     $pickup = $trip['pickup'] ?? null;
    //     $return = $trip['return'] ?? null;

    //     // Server-side validation (minimal but strict enough)
    //     $pickup_count = isset($pickup['passenger_count']) ? (int)$pickup['passenger_count'] : 0;
    //     if ($pickup_count < 1) wp_send_json_error(['message' => 'Passenger count invalid.'], 400);

    //     if (empty($pickup['datetime'])) wp_send_json_error(['message' => 'Pickup datetime required.'], 400);

    //     if ($trip_type === 'round_trip') {
    //         $ret_count = isset($return['passenger_count']) ? (int)$return['passenger_count'] : 0;
    //         if ($ret_count < 1) wp_send_json_error(['message' => 'Return passenger count invalid.'], 400);
    //         if (empty($return['datetime'])) wp_send_json_error(['message' => 'Return datetime required.'], 400);
    //     }

    //     $full_payload_json = fgbw_json_encode($payload);
    //     $pickup_json = fgbw_json_encode($pickup);
    //     $return_json = $trip_type === 'round_trip' ? fgbw_json_encode($return) : null;

    //     $booking_id = FGBW_DB::insert_booking([
    //         'created_at' => current_time('mysql'),
    //         'name' => $name,
    //         'email' => $email,
    //         'phone' => $phone,
    //         'trip_type' => $trip_type,
    //         'order_type' => $order_type,
    //         'passenger_count' => $pickup_count, // stored as primary count (pickup). Return is in return_json.
    //         'pickup_json' => $pickup_json,
    //         'return_json' => $return_json,
    //         'vehicle' => $vehicle,
    //         'full_payload_json' => $full_payload_json,
    //     ]);

    //     if (!$booking_id) wp_send_json_error(['message' => 'DB insert failed.'], 500);

    //     // Emails
    //     FGBW_Email::send_customer($booking_id, $payload);
    //     FGBW_Email::send_admin($booking_id, $payload);

    //     wp_send_json_success(['booking_id' => $booking_id]);
    // }

    // private function send_emails($booking_id, $payload)
    // {

    //     $settings = get_option('fgbw_settings', []);

    //     $admin_email = $settings['admin_email'] ?? get_option('admin_email');

    //     $customer_email = sanitize_email($payload['contact']['email'] ?? '');

    //     $subject_admin = "New Booking Received - {$booking_id}";
    //     $subject_customer = "Your Booking Request - {$booking_id}";

    //     $body = "
    //     Booking ID: {$booking_id}

    //     Trip Type: {$payload['trip_type']}
    //     Order Type: {$payload['order_type']}
    //     Vehicle: {$payload['vehicle']}

    //     Thank you.
    //     ";

    //     wp_mail($admin_email, $subject_admin, $body);
    //     if ($customer_email) {
    //         wp_mail($customer_email, $subject_customer, $body);
    //     }
    // }

    // send_emails() removed — FGBW_Email::send_customer() and ::send_admin() are called directly.


    public function fetch_flight_details()
    {

        $this->verify_nonce();

        $airline_iata = sanitize_text_field($_POST['airline_iata'] ?? '');
        $flight_number = sanitize_text_field($_POST['flight_number'] ?? '');

        if (!$airline_iata || !$flight_number) {
            wp_send_json_error(['message' => 'Missing flight info']);
        }

        // $api_key = get_option('fgbw_aviationstack_key');

        $settings = get_option('fgbw_settings', []);
        $api_key  = $settings['aviationstack_key'] ?? '';

        $flight_code = strtoupper($airline_iata . $flight_number);

        $response = wp_remote_get(
            "http://api.aviationstack.com/v1/flights?access_key={$api_key}&flight_iata={$flight_code}"
        );

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'API request failed']);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['data'][0])) {
            wp_send_json_error(['message' => 'Flight not found']);
        }

        $flight = $body['data'][0];

        wp_send_json_success([
            'airline' => $flight['airline']['name'],
            'flight_iata' => $flight['flight']['iata'],
            'status' => $flight['flight_status'],
            'departure_airport' => $flight['departure']['airport'],
            'departure_iata' => $flight['departure']['iata'],
            'departure_time' => $flight['departure']['scheduled'],
            'arrival_airport' => $flight['arrival']['airport'],
            'arrival_iata' => $flight['arrival']['iata'],
            'arrival_time' => $flight['arrival']['scheduled'],
        ]);
    }
}
