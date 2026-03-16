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

        if ( strlen( $q ) < 2 ) {
            wp_send_json_success( [ 'items' => [] ] );
        }

        // No airport_type filter — all types (large, medium, small, local,
        // seaplane_base, etc.) are included. Only the iata_code != '' guard
        // remains so every result is selectable and identifiable downstream.

        // Exact 2–3 letter IATA code search — highest priority, fastest path.
        if ( preg_match( '/^[A-Za-z]{2,3}$/', $q ) ) {

            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table}
                     WHERE iata_code = %s
                       AND iata_code != ''
                     ORDER BY
                       CASE airport_type
                         WHEN 'large_airport'  THEN 1
                         WHEN 'medium_airport' THEN 2
                         WHEN 'small_airport'  THEN 3
                         ELSE 4
                       END
                     LIMIT 10",
                    strtoupper( $q )
                ),
                ARRAY_A
            );

            // If exact IATA match found, return immediately — no need for
            // the broader name/city search below.
            if ( ! empty( $results ) ) {
                wp_send_json_success( [ 'items' => $results ] );
            }
        }

        // Full-text search across airport_name, city, AND iata_code so that
        // partial IATA input (e.g. "JF" → JFK) and city/name queries both work.
        $like = '%' . $wpdb->esc_like( $q ) . '%';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE (
                     airport_name LIKE %s
                  OR city         LIKE %s
                  OR iata_code    LIKE %s
                 )
                   AND iata_code != ''
                 ORDER BY
                   CASE airport_type
                     WHEN 'large_airport'  THEN 1
                     WHEN 'medium_airport' THEN 2
                     WHEN 'small_airport'  THEN 3
                     ELSE 4
                   END,
                   airport_name ASC
                 LIMIT 10",
                $like, $like, $like
            ),
            ARRAY_A
        );

        wp_send_json_success( [ 'items' => $results ] );
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

        // Backend validation: phone is required only (no format restriction)
        if ( ! $phone ) {
            wp_send_json_error( [ 'message' => 'Phone number is required.' ] );
        }

        // Backend validation for email
        if ( ! $email || ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'Invalid email address.' ] );
        }

        $trip   = $payload['trip']   ?? [];
        $pickup = $trip['pickup']    ?? [];
        $return = $trip['return']    ?? null;
        
        // Backend validation: Prevent past date bookings
        if ( ! empty( $pickup['datetime'] ) ) {
            $pickup_time = strtotime( $pickup['datetime'] );
            if ( $pickup_time && $pickup_time < time() ) {
                wp_send_json_error( [ 'message' => 'Pickup time cannot be in the past.' ] );
            }
        }
        
        if ( $return && ! empty( $return['datetime'] ) ) {
            $return_time = strtotime( $return['datetime'] );
            if ( $return_time && $return_time < time() ) {
                wp_send_json_error( [ 'message' => 'Return time cannot be in the past.' ] );
            }
        }

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

        $airline_iata  = sanitize_text_field($_POST['airline_iata']  ?? '');
        $flight_number = sanitize_text_field($_POST['flight_number'] ?? '');
        $flight_date   = sanitize_text_field($_POST['flight_date']   ?? '');
        // airport_iata: the 3-letter IATA code of the pickup airport (e.g. "PHL").
        // Required by /flightsFuture which uses the airport as its primary filter.
        $airport_iata  = strtoupper(sanitize_text_field($_POST['airport_iata'] ?? ''));

        if (!$airline_iata || !$flight_number) {
            wp_send_json_error(['message' => 'Missing flight info']);
        }

        $settings = get_option('fgbw_settings', []);
        $api_key  = $settings['aviationstack_key'] ?? '';

        if (!$api_key) {
            wp_send_json_error(['message' => 'Flight lookup is not configured.']);
        }

        $flight_code    = strtoupper($airline_iata . $flight_number);
        $has_valid_date = ($flight_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $flight_date));
        $is_future_date = ($has_valid_date && $flight_date > date('Y-m-d'));

        /**
         * Helper: scan a data[] array and find the record whose departure OR
         * arrival scheduled date (YYYY-MM-DD prefix of ISO timestamp) matches
         * $target_date. Returns the matching record or null.
         */
        $find_by_date = function(array $data, string $target_date): ?array {
            foreach ($data as $item) {
                $dep_date = substr($item['departure']['scheduled'] ?? '', 0, 10);
                $arr_date = substr($item['arrival']['scheduled']   ?? '', 0, 10);
                if ($dep_date === $target_date || $arr_date === $target_date) {
                    return $item;
                }
            }
            return null;
        };

        /**
         * Normalise a /flightsFuture record into the same structure as /flights.
         *
         * /flightsFuture differs significantly:
         *  - All strings are lowercase (codes, names, terminals, gates)
         *  - departure.iataCode / arrival.iataCode  (not .iata)
         *  - scheduledTime = "HH:MM" only — no date, no timezone
         *  - No airport name field (only IATA/ICAO codes)
         *  - airline.iataCode, flight.iataNumber (not .iata)
         *  - No flight_status (these are always scheduled future flights)
         */
        $normalise_future = function(array $item) use ($flight_date): array {
            $dep = $item['departure'] ?? [];
            $arr = $item['arrival']   ?? [];
            $aln = $item['airline']   ?? [];
            $flt = $item['flight']    ?? [];

            // Build full ISO datetime strings from date + scheduledTime ("HH:MM")
            $dep_time_str = isset($dep['scheduledTime'])
                ? $flight_date . 'T' . $dep['scheduledTime'] . ':00+00:00'
                : '';
            $arr_time_str = isset($arr['scheduledTime'])
                ? $flight_date . 'T' . $arr['scheduledTime'] . ':00+00:00'
                : '';

            return [
                'flight_status' => 'scheduled',
                'airline' => [
                    'name' => ucwords(strtolower($aln['name'] ?? '')),
                    'iata' => strtoupper($aln['iataCode'] ?? ''),
                ],
                'flight' => [
                    'iata' => strtoupper($flt['iataNumber'] ?? $flt['number'] ?? ''),
                ],
                'departure' => [
                    'airport'   => strtoupper($dep['iataCode'] ?? ''), // no name available
                    'iata'      => strtoupper($dep['iataCode'] ?? ''),
                    'terminal'  => strtoupper($dep['terminal'] ?? ''),
                    'gate'      => strtoupper($dep['gate']     ?? ''),
                    'scheduled' => $dep_time_str,
                    'estimated' => $dep_time_str,
                    'actual'    => null,
                ],
                'arrival' => [
                    'airport'   => strtoupper($arr['iataCode'] ?? ''), // no name available
                    'iata'      => strtoupper($arr['iataCode'] ?? ''),
                    'terminal'  => strtoupper($arr['terminal'] ?? ''),
                    'gate'      => strtoupper($arr['gate']     ?? ''),
                    'scheduled' => $arr_time_str,
                    'estimated' => $arr_time_str,
                    'actual'    => null,
                ],
            ];
        };

        $flight       = null;
        $date_matched = false;

        // ── Strategy A: FUTURE date → /flightsFuture endpoint ──────────────
        // /flightsFuture requires:
        //   iataCode = 3-letter AIRPORT code (e.g. "PHL") — NOT the flight number
        //   type     = arrival (user's airport is their pickup point — flight arrives there)
        //   date     = YYYY-MM-DD
        // We then filter the returned list to find our specific flight number.
        if ($has_valid_date && $is_future_date && $airport_iata) {
            // Use 'arrival' because the user's airport is where the flight arrives
            // (they're getting picked up FROM the airport after landing)
            foreach (['arrival', 'departure'] as $fut_type) {
                if ($date_matched) break;
                $fut_url = 'http://api.aviationstack.com/v1/flightsFuture?' . http_build_query([
                    'access_key' => $api_key,
                    'iataCode'   => $airport_iata,  // 3-letter airport code e.g. "PHL"
                    'type'       => $fut_type,
                    'date'       => $flight_date,
                ]);
                $resp_fut = wp_remote_get($fut_url, ['timeout' => 15]);
                $raw_key  = ($fut_type === 'arrival') ? 'raw_fut_dep' : 'raw_fut_arr';
                if (is_wp_error($resp_fut)) {
                    $$raw_key = 'wp_error: ' . $resp_fut->get_error_message();
                    continue;
                }
                $$raw_key = wp_remote_retrieve_body($resp_fut);
                $body_fut = json_decode($$raw_key, true);
                if (!empty($body_fut['data'])) {
                    // Find the specific flight by matching the flight number
                    $fut_flight = null;
                    foreach ($body_fut['data'] as $item) {
                        $item_flight = strtoupper(
                            $item['flight']['iataNumber'] ?? $item['flight']['iata'] ?? ''
                        );
                        if ($item_flight === $flight_code) {
                            $fut_flight = $item;
                            break;
                        }
                    }
                    if ($fut_flight) {
                        $flight       = $normalise_future($fut_flight);
                        $date_matched = true;
                    }
                    // else: airport matched but flight not in list, try other type
                }
            }
        }
        // Initialise raw debug vars if Strategy A was skipped
        if (!isset($raw_fut_dep)) $raw_fut_dep = $airport_iata ? 'no_airport_iata_sent' : 'skipped_not_future';
        if (!isset($raw_fut_arr)) $raw_fut_arr = 'skipped';

        // ── Strategy B: /flights with flight_date — runs for today/past, OR
        // if Strategy A failed (flightsFuture unavailable on this plan).
        if (!$date_matched && $has_valid_date) {
            // Attempt B1: exact date
            $b1_url  = 'http://api.aviationstack.com/v1/flights?' . http_build_query([
                'access_key'  => $api_key,
                'flight_iata' => $flight_code,
                'flight_date' => $flight_date,
                'limit'       => 10,
            ]);
            $resp_b1 = wp_remote_get($b1_url, ['timeout' => 15]);
            if (!is_wp_error($resp_b1)) {
                $raw_b1  = wp_remote_retrieve_body($resp_b1);
                $body_b1 = json_decode($raw_b1, true);
                error_log('[FGBW] /flights B1 response for ' . $flight_code . ' ' . $flight_date . ': ' . substr($raw_b1, 0, 500));
                if (!empty($body_b1['data'])) {
                    $m = $find_by_date($body_b1['data'], $flight_date);
                    $flight       = $m ?? $body_b1['data'][0];
                    $date_matched = !!$m;
                }
            }
            // Attempt B2: day-before (red-eye / overnight departures)
            if (!$date_matched) {
                $day_before  = date('Y-m-d', strtotime($flight_date . ' -1 day'));
                $resp_b2 = wp_remote_get(
                    'http://api.aviationstack.com/v1/flights?' . http_build_query([
                        'access_key'  => $api_key,
                        'flight_iata' => $flight_code,
                        'flight_date' => $day_before,
                        'limit'       => 10,
                    ]),
                    ['timeout' => 15]
                );
                if (!is_wp_error($resp_b2)) {
                    $body_b2 = json_decode(wp_remote_retrieve_body($resp_b2), true);
                    if (!empty($body_b2['data'])) {
                        $m2 = $find_by_date($body_b2['data'], $flight_date);
                        if ($m2) { $flight = $m2; $date_matched = true; }
                    }
                }
            }
        }

        // ── Strategy C: no-date fallback — works on any plan ─────────────────
        // Used when: no date given, or all date-specific attempts returned nothing.
        if (!$flight) {
            $resp_c = wp_remote_get(
                'http://api.aviationstack.com/v1/flights?' . http_build_query([
                    'access_key'  => $api_key,
                    'flight_iata' => $flight_code,
                    'limit'       => 10,
                ]),
                ['timeout' => 15]
            );
            if (is_wp_error($resp_c)) {
                wp_send_json_error(['message' => 'Unable to reach the flight data service. Please try again.']);
            }
            $raw_c  = wp_remote_retrieve_body($resp_c);
            $body_c = json_decode($raw_c, true);
            if (!empty($body_c['data'])) {
                if ($has_valid_date) {
                    $m3           = $find_by_date($body_c['data'], $flight_date);
                    $flight       = $m3 ?? $body_c['data'][0];
                    $date_matched = !!$m3;
                } else {
                    $flight = $body_c['data'][0];
                }
            }
        }

        if (!$flight) {
            error_log('[FGBW] Flight lookup failed — ' . $flight_code
                . ($has_valid_date ? ' date=' . $flight_date : '') );
            wp_send_json_error(['message' => 'Flight not found. Please check the airline and flight number.']);
        }

        // Prefer estimated times over scheduled when available
        $dep = $flight['departure'] ?? [];
        $arr = $flight['arrival']   ?? [];

        $dep_time = $dep['estimated'] ?? $dep['actual'] ?? $dep['scheduled'] ?? '';
        $arr_time = $arr['estimated'] ?? $arr['actual'] ?? $arr['scheduled'] ?? '';

        // Status labels: use "estimated" label when actual times differ from schedule
        $dep_status = !empty($dep['actual'])    ? 'ACTUAL'
                    : (!empty($dep['estimated']) ? 'ESTIMATED'
                    : 'SCHEDULED');
        $arr_status = !empty($arr['actual'])    ? 'ACTUAL'
                    : (!empty($arr['estimated']) ? 'ESTIMATED'
                    : 'SCHEDULED');

        // last_updated — format as "Today at H:i A" when same day, else date string
        $updated_raw = $flight['updated'] ?? ($flight['last_updated'] ?? '');
        $last_updated = '';
        if ($updated_raw) {
            $ts       = strtotime($updated_raw);
            $today    = date('Y-m-d');
            $upd_date = date('Y-m-d', $ts);
            $last_updated = ($upd_date === $today)
                ? 'Today at ' . date('g:i A', $ts)
                : date('M j, Y g:i A', $ts);
        }

        // The card shows the status-label time (estimated/actual/scheduled)
        // as the bold large time, matching the reference site behaviour.
        $debug_info = [
            'requested_date' => $flight_date,
            'is_future'      => $is_future_date,
            'date_matched'   => $date_matched,
            'dep_scheduled'  => $dep['scheduled']  ?? '',
            'arr_scheduled'  => $arr['scheduled']  ?? '',
            'fut_dep_raw'    => isset($raw_fut_dep) ? substr($raw_fut_dep, 0, 600) : 'not_tried',
            'fut_arr_raw'    => isset($raw_fut_arr) ? substr($raw_fut_arr, 0, 600) : 'not_tried',
            'b1_raw'         => isset($raw_b1)      ? substr($raw_b1,      0, 600) : 'not_tried',
            'c_raw'          => isset($raw_c)       ? substr($raw_c,       0, 600) : 'not_tried',
        ];
        // Write directly to wp-content/debug.log bypassing error_log routing
        @file_put_contents(
            WP_CONTENT_DIR . '/debug.log',
            '[' . date('Y-m-d H:i:s') . '] [FGBW] ' . json_encode($debug_info) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        wp_send_json_success([
            'date_matched'           => $date_matched,
            '_debug'                 => $debug_info,
            'airline'                => $flight['airline']['name']  ?? '',
            'flight_iata'            => $flight['flight']['iata']   ?? $flight_code,
            'status'                 => $flight['flight_status']    ?? '',
            'departure_airport'      => $dep['airport']             ?? '',
            'departure_iata'         => $dep['iata']                ?? '',
            'departure_time'         => $dep_time,
            'departure_scheduled'    => $dep['scheduled']           ?? '',
            'departure_terminal'     => $dep['terminal']            ?? '',
            'departure_gate'         => $dep['gate']                ?? '',
            'departure_status_label' => $dep_status,
            'arrival_airport'        => $arr['airport']             ?? '',
            'arrival_iata'           => $arr['iata']                ?? '',
            'arrival_time'           => $arr_time,
            'arrival_scheduled'      => $arr['scheduled']           ?? '',
            'arrival_terminal'       => $arr['terminal']            ?? '',
            'arrival_gate'           => $arr['gate']                ?? '',
            'arrival_status_label'   => $arr_status,
            'last_updated'           => $last_updated,
        ]);
    }
}
