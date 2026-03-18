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

        // ── PDF generation ────────────────────────────────────────────────────
        // Generate after a successful DB insert; never blocks the booking response.
        // The returned $pdf_url is a signed, server-side download link — it is
        // injected into the admin email only; the customer never sees it.
        // Generate PDF — returns ['filepath'=>..., 'url'=>...] or false.
        // Passed to send_admin() for attachment. Never sent to customer.
        $pdf_result = false;
        try {
            $price      = (float)( $payload['price'] ?? 0.0 );
            $pdf_result = FGBW_PDF::generate( $booking_id, $payload, $price );
        } catch ( \Throwable $e ) {
            error_log( '[FGBW PDF] Caught exception during generate: ' . $e->getMessage() );
        }

        FGBW_Email::send_customer( $booking_id, $payload );
        FGBW_Email::send_admin(    $booking_id, $payload, $pdf_result );

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
        // Ensure enough execution time for up to 2 API calls
        @set_time_limit(60);

        $this->verify_nonce();
        global $wpdb;

        $airline_iata  = sanitize_text_field($_POST['airline_iata']  ?? '');
        $flight_number = sanitize_text_field($_POST['flight_number'] ?? '');
        $flight_date   = sanitize_text_field($_POST['flight_date']   ?? '');
        $airport_iata  = strtoupper(sanitize_text_field($_POST['airport_iata'] ?? ''));

        if (!$airline_iata || !$flight_number) {
            wp_send_json_error(['message' => 'Missing flight info.']);
        }

        $settings = get_option('fgbw_settings', []);
        $api_key  = $settings['aviationstack_key'] ?? '';
        if (!$api_key) {
            wp_send_json_error(['message' => 'Flight lookup is not configured.']);
        }

        $flight_code    = strtoupper($airline_iata . $flight_number);
        $has_valid_date = ($flight_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $flight_date));
        // flightsFuture endpoint ONLY works for dates MORE THAN 7 days ahead.
        // For dates within 7 days (including today/past), use /flights endpoint.
        $is_future_date = ($has_valid_date && $flight_date > date('Y-m-d', strtotime('+7 days')));
        $flight         = null;
        $date_matched   = false;
        $debug          = ['strategy' => 'none', 'attempts' => []];

        // ── Helper: look up airport name from local DB ────────────────────────
        $airport_name = function(string $iata) use ($wpdb): string {
            if (!$iata) return '';
            $t = $wpdb->prefix . 'fg_airports';
            $n = $wpdb->get_var($wpdb->prepare("SELECT airport_name FROM {$t} WHERE iata_code = %s LIMIT 1", $iata));
            return $n ? sanitize_text_field($n) : $iata;
        };

        // ── Helper: normalise /flightsFuture record → /flights structure ──────
        $normalise_future = function(array $item) use ($flight_date, $airport_name): array {
            $dep = $item['departure'] ?? [];
            $arr = $item['arrival']   ?? [];
            $aln = $item['airline']   ?? [];
            $flt = $item['flight']    ?? [];
            $dep_iso = $flight_date . 'T' . ($dep['scheduledTime'] ?? '00:00') . ':00+00:00';
            $arr_iso = $flight_date . 'T' . ($arr['scheduledTime'] ?? '00:00') . ':00+00:00';
            $dep_iata = strtoupper($dep['iataCode'] ?? '');
            $arr_iata = strtoupper($arr['iataCode'] ?? '');
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
                    'airport'   => $airport_name($dep_iata),
                    'iata'      => $dep_iata,
                    'terminal'  => strtoupper($dep['terminal'] ?? ''),
                    'gate'      => strtoupper($dep['gate']     ?? ''),
                    'scheduled' => $dep_iso,
                    'estimated' => $dep_iso,
                    'actual'    => null,
                ],
                'arrival' => [
                    'airport'   => $airport_name($arr_iata),
                    'iata'      => $arr_iata,
                    'terminal'  => strtoupper($arr['terminal'] ?? ''),
                    'gate'      => strtoupper($arr['gate']     ?? ''),
                    'scheduled' => $arr_iso,
                    'estimated' => $arr_iso,
                    'actual'    => null,
                ],
            ];
        };

        // ── Helper: find best matching leg from /flightsFuture data[] ─────────
        $find_future_leg = function(array $data) use ($flight_code, $airport_iata, $normalise_future): ?array {
            $legs = [];
            foreach ($data as $item) {
                $code = strtoupper($item['flight']['iataNumber'] ?? $item['flight']['iata'] ?? '');
                if ($code === $flight_code) $legs[] = $item;
            }
            if (empty($legs)) return null;
            // Filter to legs arriving at user's airport
            $arr_legs = array_filter($legs, fn($l) => strtoupper($l['arrival']['iataCode'] ?? '') === $airport_iata);
            $candidates = !empty($arr_legs) ? array_values($arr_legs) : $legs;
            // Sort by earliest departure time — always pick the first scheduled flight
            usort($candidates, fn($a, $b) => strcmp(
                $a['departure']['scheduledTime'] ?? '00:00',
                $b['departure']['scheduledTime'] ?? '00:00'
            ));
            return $normalise_future($candidates[0]);
        };

        // ── Helper: make one API GET call with 15s timeout ──────────────────
        // Debug logger — only writes when WP_DEBUG is enabled
        $log = function(string $msg) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FGBW] ' . $msg);
            }
        };

        $api_get = function(string $url) use (&$debug, $log): ?array {
            $debug['attempts'][] = preg_replace('/access_key=[^&]+/', 'access_key=***', $url);
            $resp = wp_remote_get($url, ['timeout' => 10]);
            if (is_wp_error($resp)) {
                $log('WP_Error: ' . $resp->get_error_message());
                return null;
            }
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            return is_array($body) ? $body : null;
        };

        // ════════════════════════════════════════════════════════════════════
        // STRATEGY A — Future date: /flightsFuture with date-range search
        // ════════════════════════════════════════════════════════════════════
        // Flights don't operate every day (weekday schedules). If the exact date
        // has no matching flight, try the day before and day after to find the
        // One clean call: flightsFuture filtered by airline + airport + exact date.
        // NO date-range loop — extra calls cause nginx 60s timeout on busy airports.
        // Maximum: 1 call for Strategy A. Falls through to Strategy B if not found.
        $nearest_date = '';

        if ($has_valid_date && $is_future_date && $airport_iata) {
            $debug['strategy'] = 'A_future';
            $body_a = $api_get('http://api.aviationstack.com/v1/flightsFuture?' . http_build_query([
                'access_key'   => $api_key,
                'iataCode'     => $airport_iata,
                'type'         => 'arrival',
                'date'         => $flight_date,
                'airline_iata' => $airline_iata,
            ]));
            if (!empty($body_a['data'])) {
$legs = [];
                foreach ($body_a['data'] as $item) {
                    $flt = $item['flight'] ?? [];
                    // Try all possible field names flightsFuture may use:
                    // iataNumber (e.g. "ua2019"), iata (e.g. "UA2019"),
                    // number (e.g. "2019") — prefix with airline_iata for comparison
                    $code_full   = strtoupper($flt['iataNumber'] ?? $flt['iata'] ?? '');
                    $code_number = strtoupper($airline_iata . ($flt['number'] ?? ''));
                    if ($code_full === $flight_code || $code_number === $flight_code) {
                        $legs[] = $item;
                    }
                }
                if (!empty($legs)) {
                    $arr_legs   = array_filter($legs, fn($l) => strtoupper($l['arrival']['iataCode'] ?? '') === $airport_iata);
                    $candidates = !empty($arr_legs) ? array_values($arr_legs) : $legs;
                    usort($candidates, fn($a, $b) => strcmp(
                        $a['departure']['scheduledTime'] ?? '00:00',
                        $b['departure']['scheduledTime'] ?? '00:00'
                    ));
                    $best = $candidates[0];
                    $dep  = $best['departure'] ?? [];
                    $arr  = $best['arrival']   ?? [];
                    $aln  = $best['airline']   ?? [];
                    $flt  = $best['flight']    ?? [];
                    $di   = strtoupper($dep['iataCode'] ?? '');
                    $ai   = strtoupper($arr['iataCode'] ?? '');
                    $flight = [
                        'flight_status' => 'scheduled',
                        'airline'   => ['name' => ucwords(strtolower($aln['name'] ?? '')), 'iata' => strtoupper($aln['iataCode'] ?? '')],
                        'flight'    => ['iata' => strtoupper($flt['iataNumber'] ?? $flt['number'] ?? '')],
                        'departure' => ['airport'=>$airport_name($di),'iata'=>$di,'terminal'=>strtoupper($dep['terminal']??''),'gate'=>strtoupper($dep['gate']??''),'scheduled'=>$flight_date.'T'.($dep['scheduledTime']??'00:00').':00+00:00','estimated'=>$flight_date.'T'.($dep['scheduledTime']??'00:00').':00+00:00','actual'=>null],
                        'arrival'   => ['airport'=>$airport_name($ai),'iata'=>$ai,'terminal'=>strtoupper($arr['terminal']??''),'gate'=>strtoupper($arr['gate']??''),'scheduled'=>$flight_date.'T'.($arr['scheduledTime']??'00:00').':00+00:00','estimated'=>$flight_date.'T'.($arr['scheduledTime']??'00:00').':00+00:00','actual'=>null],
                    ];
                    $date_matched = true;
                    $nearest_date = $flight_date;
                }
            }
        }

        // ════════════════════════════════════════════════════════════════════
        // STRATEGY B — Today/past date OR future fallback: /flights?flight_date
        // ════════════════════════════════════════════════════════════════════
        if (!$date_matched && $has_valid_date) {
            $debug['strategy'] = $debug['strategy'] === 'none' ? 'B_dated' : 'B_fallback';
            // Add arr_iata to get ONLY the leg arriving at the user's airport.
            // Without this, AA2961 returns multiple legs (MCO→PHL AND PHL→XXX).
            // With arr_iata=PHL: API returns exactly the MCO→PHL leg — no ambiguity.
            // Use only flight_iata + flight_date — arr_iata is not reliably
            // supported across all Aviationstack plan levels and can cause errors.
            // We filter by airport client-side below.
            $body_b = $api_get('http://api.aviationstack.com/v1/flights?' . http_build_query([
                'access_key'  => $api_key,
                'flight_iata' => $flight_code,
                'flight_date' => $flight_date,
                'limit'       => 10,
            ]));
            if (!empty($body_b['data'])) {
                // Filter to records matching the requested date
                $dated = array_filter($body_b['data'], function($item) use ($flight_date) {
                    $dd = substr($item['departure']['scheduled'] ?? '', 0, 10);
                    $ad = substr($item['arrival']['scheduled']   ?? '', 0, 10);
                    return $dd === $flight_date || $ad === $flight_date;
                });
                if (!empty($dated)) {
                    // Among date-matched records, prefer arrival at user airport, then earliest
                    $dated = array_values($dated);
                    $at_airport = array_filter($dated, function($item) use ($airport_iata) {
                        return strtoupper($item['arrival']['iata'] ?? '') === $airport_iata;
                    });
                    $candidates = !empty($at_airport) ? array_values($at_airport) : $dated;
                    usort($candidates, fn($a, $b) => strcmp(
                        $a['departure']['scheduled'] ?? '',
                        $b['departure']['scheduled'] ?? ''
                    ));
                    $flight = $candidates[0];
                    $date_matched = true;
                } else {
                    $flight = $body_b['data'][0];
                }
            }
        }

        // ════════════════════════════════════════════════════════════════════
        // STRATEGY C — Paginated broad search: scans up to 200 /flights records
        // ════════════════════════════════════════════════════════════════════
        // /flights holds real-time + near-future scheduled records, sorted by
        // date ascending. UA2019 has 192 records total — the requested future
        // date may be in the second page (offset=100). We scan page 1 first;
        // if the date is not found AND there are more records, fetch page 2.
        if (!$flight) {
            $debug['strategy'] = 'C_nodatE';

            $scan_records = function(array $data) use ($flight_date, $airport_iata, $has_valid_date): ?array {
                // Priority 1: date + airport match
                foreach ($data as $item) {
                    $dep_d = substr($item['departure']['scheduled'] ?? '', 0, 10);
                    $arr_d = substr($item['arrival']['scheduled']   ?? '', 0, 10);
                    $arr_i = strtoupper($item['arrival']['iata']    ?? '');
                    if (($dep_d === $flight_date || $arr_d === $flight_date)
                        && (!$airport_iata || $arr_i === $airport_iata)) {
                        return $item;
                    }
                }
                // Priority 2: date only
                if ($has_valid_date) {
                    foreach ($data as $item) {
                        $dep_d = substr($item['departure']['scheduled'] ?? '', 0, 10);
                        $arr_d = substr($item['arrival']['scheduled']   ?? '', 0, 10);
                        if ($dep_d === $flight_date || $arr_d === $flight_date) {
                            return $item;
                        }
                    }
                }
                return null;
            };

            // Page 1 — first 100 records
            $body_c1 = $api_get('http://api.aviationstack.com/v1/flights?' . http_build_query([
                'access_key'  => $api_key,
                'flight_iata' => $flight_code,
                'limit'       => 100,
                'offset'      => 0,
            ]));
            $total_available = $body_c1['pagination']['total'] ?? 0;
            $log('Strategy C page1: ' . count($body_c1['data'] ?? []) . ' records of ' . $total_available . ', scanning for date=' . $flight_date);

            $found = null;
            if (!empty($body_c1['data'])) {
                $found = $scan_records($body_c1['data']);
            }

            // Page 2 — records 101-200, only if page 1 had no date match and more exist
            if (!$found && $total_available > 100) {
                $body_c2 = $api_get('http://api.aviationstack.com/v1/flights?' . http_build_query([
                    'access_key'  => $api_key,
                    'flight_iata' => $flight_code,
                    'limit'       => 100,
                    'offset'      => 100,
                ]));
                if (!empty($body_c2['data'])) {
                    $found = $scan_records($body_c2['data']);
                    // Merge for fallback selection below
                    if (!empty($body_c1['data'])) {
                        $all_c = array_merge($body_c1['data'], $body_c2['data']);
                    } else {
                        $all_c = $body_c2['data'];
                    }
                }
            }
            $all_c = $all_c ?? ($body_c1['data'] ?? []);

            if ($found) {
                $flight = $found;
            } elseif ($airport_iata && !empty($all_c)) {
                // Fallback: best airport match
                foreach ($all_c as $item) {
                    if (strtoupper($item['arrival']['iata'] ?? '') === $airport_iata) {
                        $flight = $item; break;
                    }
                }
            }
            if (!$flight && !empty($all_c)) {
                $flight = $all_c[0];
            }

            // Set date_matched if the final result is for the requested date
            if ($flight && $has_valid_date) {
                $dep_d = substr($flight['departure']['scheduled'] ?? '', 0, 10);
                $arr_d = substr($flight['arrival']['scheduled']   ?? '', 0, 10);
                if ($dep_d === $flight_date || $arr_d === $flight_date) {
                    $date_matched = true;
                }
            }
        }

        if (!$flight) {
            wp_send_json_error(['message' => 'Flight not found. Please check the airline and flight number.']);
        }

        // ── Build response fields ─────────────────────────────────────────────
        $dep = $flight['departure'] ?? [];
        $arr = $flight['arrival']   ?? [];

        $dep_time = $dep['estimated'] ?? $dep['actual'] ?? $dep['scheduled'] ?? '';
        $arr_time = $arr['estimated'] ?? $arr['actual'] ?? $arr['scheduled'] ?? '';

        // For future/scheduled flights both estimated and scheduled are the same ISO string —
        // status should show SCHEDULED not ESTIMATED in that case
        $dep_status = !empty($dep['actual'])
            ? 'ACTUAL'
            : (!empty($dep['estimated']) && $dep['estimated'] !== ($dep['scheduled'] ?? '')
                ? 'ESTIMATED'
                : 'SCHEDULED');
        $arr_status = !empty($arr['actual'])
            ? 'ACTUAL'
            : (!empty($arr['estimated']) && $arr['estimated'] !== ($arr['scheduled'] ?? '')
                ? 'ESTIMATED'
                : 'SCHEDULED');

        $updated_raw  = $flight['updated'] ?? ($flight['last_updated'] ?? '');
        $last_updated = '';
        if ($updated_raw) {
            $ts = strtotime($updated_raw);
            $last_updated = (date('Y-m-d', $ts) === date('Y-m-d'))
                ? 'Today at ' . date('g:i A', $ts)
                : date('M j, Y g:i A', $ts);
        }

        $log('strategy=' . $debug['strategy'] . ' date_matched=' . ($date_matched ? 'true' : 'false')
            . ' flight=' . $flight_code . ' date=' . $flight_date . ' calls=' . count($debug['attempts']));

        wp_send_json_success([
            'date_matched'           => $date_matched,
            'nearest_date'           => $nearest_date ?? '',
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