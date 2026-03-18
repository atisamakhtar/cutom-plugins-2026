<?php
/**
 * FGBW_PDF — Generates a reservation PDF matching the Optimus Fleets reference design.
 *
 * Uses the bundled FPDF library (includes/fpdf/fpdf.php) — public domain, zero dependencies.
 * No Composer install required. Works on PHP 8.3, nginx, any host.
 *
 * Storage: wp-content/uploads/fgbw-pdfs/booking-{id}-{timestamp}.pdf
 * Security: HMAC-SHA256 signed download URL — file is never directly accessible.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'FPDF' ) ) {
    require_once FGBW_PLUGIN_DIR . 'includes/fpdf/fpdf.php';
}

class FGBW_PDF {

    private static string $upload_dir = '';
    private static string $upload_url = '';

    public static function init(): void
    {
        // Safe to call multiple times — always refreshes the path.
        $up = wp_upload_dir();
        self::$upload_dir = trailingslashit( $up['basedir'] ) . 'fgbw-pdfs';
        self::$upload_url = trailingslashit( $up['baseurl'] ) . 'fgbw-pdfs';

        if ( ! file_exists( self::$upload_dir ) ) {
            wp_mkdir_p( self::$upload_dir );
        }

        // Apache / LiteSpeed: deny direct HTTP access to PDF files.
        $ht = self::$upload_dir . '/.htaccess';
        if ( ! file_exists( $ht ) ) {
            file_put_contents( $ht, "Options -Indexes\n<FilesMatch \"\\.pdf\$\">\n  Order Allow,Deny\n  Deny from all\n</FilesMatch>\n" );
        }

        // Nginx: prevent directory listing.
        $idx = self::$upload_dir . '/index.php';
        if ( ! file_exists( $idx ) ) {
            file_put_contents( $idx, '<?php // silence' );
        }

        error_log( '[FGBW PDF] init() — upload_dir: ' . self::$upload_dir . ' writable: ' . ( is_writable( self::$upload_dir ) ? 'yes' : 'NO' ) );
    }

    /**
     * Generate a PDF for a booking.
     * Returns an array ['filepath'=>..., 'url'=>...] on success, or false on failure.
     * The filepath is used to attach the PDF to the admin email.
     * The url is a signed download link (kept for future use / admin panel).
     */
    public static function generate( int $booking_id, array $payload, float $price = 0.0 )
    {
        // Verify upload directory is ready and writable.
        if ( empty( self::$upload_dir ) ) {
            error_log( '[FGBW PDF] upload_dir not set — did FGBW_PDF::init() run?' );
            return false;
        }
        if ( ! is_writable( self::$upload_dir ) ) {
            error_log( '[FGBW PDF] upload_dir not writable: ' . self::$upload_dir );
            return false;
        }

        try {
            error_log( '[FGBW PDF] Starting PDF generation for booking #' . $booking_id );
            $pdf      = self::build_pdf( $booking_id, $payload, $price );
            $filename = 'booking-' . $booking_id . '-' . time() . '.pdf';
            $filepath = self::$upload_dir . '/' . $filename;
            $pdf->Output( 'F', $filepath );

            if ( ! file_exists( $filepath ) ) {
                error_log( '[FGBW PDF] Output() ran but file not created: ' . $filepath );
                return false;
            }

            error_log( '[FGBW PDF] PDF created successfully: ' . $filepath . ' (' . filesize( $filepath ) . ' bytes)' );
            update_option( 'fgbw_pdf_' . $booking_id, [ 'filename' => $filename, 'created_at' => current_time( 'mysql' ) ], false );
            return [
                'filepath' => $filepath,
                'url'      => self::signed_url( $booking_id, $filename ),
            ];
        } catch ( \Throwable $e ) {
            error_log( '[FGBW PDF] Generation FAILED for booking #' . $booking_id . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
            return false;
        }
    }

    public static function serve_download(): void
    {
        $booking_id = absint( $_GET['fgbw_pdf']   ?? 0 );
        $token      = sanitize_text_field( $_GET['fgbw_token'] ?? '' );
        if ( ! $booking_id || ! $token ) return;
        $stored = get_option( 'fgbw_pdf_' . $booking_id );
        if ( ! $stored ) wp_die( 'PDF not found.', 404 );
        if ( ! hash_equals( self::make_token( $booking_id, $stored['filename'] ), $token ) ) wp_die( 'Invalid token.', 403 );
        $filepath = self::$upload_dir . '/' . $stored['filename'];
        if ( ! file_exists( $filepath ) ) wp_die( 'File not found.', 404 );
        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="' . esc_attr( $stored['filename'] ) . '"' );
        header( 'Content-Length: ' . filesize( $filepath ) );
        readfile( $filepath );
        exit;
    }

    private static function make_token( int $id, string $fn ): string
    {
        return hash_hmac( 'sha256', $id . '|' . $fn, wp_salt( 'auth' ) );
    }

    private static function signed_url( int $id, string $fn ): string
    {
        return add_query_arg( [ 'fgbw_pdf' => $id, 'fgbw_token' => self::make_token( $id, $fn ) ], home_url( '/' ) );
    }

    private static function loc_label( ?array $loc ): string
    {
        if ( ! is_array( $loc ) ) return '';
        $mode = $loc['mode'] ?? '';
        if ( $mode === 'address' ) return sanitize_text_field( $loc['address']['formatted_address'] ?? $loc['_rawText'] ?? '' );
        if ( $mode === 'airport' ) {
            $a = $loc['airport'] ?? [];
            return trim( sanitize_text_field( $a['airport_name'] ?? '' ) . ' (' . sanitize_text_field( $a['iata_code'] ?? '' ) . ')' );
        }
        return '';
    }

    private static function loc_full( ?array $loc ): string
    {
        $label = self::loc_label( $loc );
        $zip   = sanitize_text_field( $loc['zip'] ?? '' );
        return $label && $zip ? "$label, $zip" : $label;
    }

    private static function flight_info( ?array $loc ): array
    {
        $e = [ 'airline' => '', 'iata' => '', 'flight' => '', 'no_flight' => false ];
        if ( ! is_array( $loc ) || ( $loc['mode'] ?? '' ) !== 'airport' ) return $e;
        $a = $loc['airline'] ?? null;
        return [
            'airline'   => is_array( $a ) ? sanitize_text_field( $a['airline_name'] ?? '' ) : '',
            'iata'      => is_array( $a ) ? sanitize_text_field( $a['iata_code']    ?? '' ) : '',
            'flight'    => sanitize_text_field( $loc['flight'] ?? '' ),
            'no_flight' => ! empty( $loc['no_flight_info'] ),
        ];
    }

    private static function svc_label( string $t ): string
    {
        return match ( $t ) {
            'airport_pickup', 'airport_dropoff' => 'Airport Transportation',
            'point_to_point' => 'Point to Point',
            'hourly'         => 'Hourly Charter',
            default          => ucwords( str_replace( '_', ' ', $t ) ) ?: 'N/A',
        };
    }

    private static function rgb( string $hex ): array
    {
        $hex = ltrim( $hex, '#' );
        return [ hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ];
    }

private static function build_pdf( int $booking_id, array $payload, float $price ): FPDF
    {
        // ── Extract booking data ──────────────────────────────────────────────
        $name       = sanitize_text_field( $payload['name']  ?? '' );
        $email      = sanitize_email(      $payload['email'] ?? '' );
        $phone      = sanitize_text_field( $payload['phone'] ?? '' );
        $trip_type  = sanitize_text_field( $payload['trip_type']  ?? '' );
        $order_type = sanitize_text_field( $payload['order_type'] ?? '' );
        $is_round   = ( $trip_type === 'round_trip' );

        $trip   = $payload['trip'] ?? [];
        $pickup = $trip['pickup']  ?? [];
        $return = $trip['return']  ?? [];

        $pickup_dt   = sanitize_text_field( $pickup['datetime'] ?? '' );
        $pickup_date = $pickup_dt ? date( 'F j, Y', strtotime( $pickup_dt ) ) : '---';
        $pickup_time = $pickup_dt ? date( 'g:i A',  strtotime( $pickup_dt ) ) : '---';
        $pickup_loc  = self::loc_full( $pickup['pickup']  ?? null ) ?: '---';
        $dropoff_loc = self::loc_full( $pickup['dropoff'] ?? null ) ?: '---';
        $pax         = (int)( $pickup['passenger_count'] ?? 1 );

        $pu_fi   = self::flight_info( $pickup['pickup']  ?? null );
        $do_fi   = self::flight_info( $pickup['dropoff'] ?? null );
        $al_name = $pu_fi['airline'] ?: $do_fi['airline'];
        $al_iata = $pu_fi['iata']    ?: $do_fi['iata'];
        $fl_num  = $pu_fi['flight']  ?: $do_fi['flight'];
        $no_fl   = $pu_fi['no_flight'] || $do_fi['no_flight'];
        $al_lbl  = $al_name ? "$al_name ($al_iata)" : ( $al_iata ?: '---' );
        $fl_lbl  = $no_fl ? 'Not provided' : ( $fl_num ?: '---' );

        $ret_dt     = sanitize_text_field( $return['datetime'] ?? '' );
        $ret_date   = ( $is_round && $ret_dt ) ? date( 'F j, Y', strtotime( $ret_dt ) ) : 'N/A';
        $ret_time   = ( $is_round && $ret_dt ) ? date( 'g:i A',  strtotime( $ret_dt ) ) : 'N/A';
        $ret_pu_loc = $is_round ? ( self::loc_full( $return['pickup']  ?? null ) ?: '---' ) : 'N/A';
        $ret_do_loc = $is_round ? ( self::loc_full( $return['dropoff'] ?? null ) ?: '---' ) : 'N/A';

        $rpa       = $is_round ? self::flight_info( $return['pickup']  ?? null ) : [];
        $rda       = $is_round ? self::flight_info( $return['dropoff'] ?? null ) : [];
        $r_al_name = ( $rpa['airline'] ?? '' ) ?: ( $rda['airline'] ?? '' );
        $r_al_iata = ( $rpa['iata']    ?? '' ) ?: ( $rda['iata']    ?? '' );
        $r_fl      = ( $rpa['flight']  ?? '' ) ?: ( $rda['flight']  ?? '' );
        $r_no_fl   = ( $rpa['no_flight'] ?? false ) || ( $rda['no_flight'] ?? false );
        $r_al_lbl  = $r_al_name ? "$r_al_name ($r_al_iata)" : ( $r_al_iata ?: '---' );
        $r_fl_lbl  = $r_no_fl ? 'Not provided' : ( $r_fl ?: '---' );

        $svc       = self::svc_label( $order_type );
        $price_str = $price > 0 ? '$' . number_format( $price, 2 ) : '';

        $out_stops = [];
        foreach ( ( $pickup['stops'] ?? [] ) as $s ) {
            $lbl = self::loc_full( $s ); if ( $lbl ) $out_stops[] = $lbl;
        }
        $ret_stops = [];
        if ( $is_round ) {
            foreach ( ( $return['stops'] ?? [] ) as $s ) {
                $lbl = self::loc_full( $s ); if ( $lbl ) $ret_stops[] = $lbl;
            }
        }

        // Personal message — hardcoded per requirements
        $note = 'Please confirm the trip. Thank you.';

        // Full policy sections (verbatim from approved content)
        $policies = [
            [
                'title' => 'Important Note',
                'body'  => 'Please note that Optimus Fleets is not responsible for any lost, stolen, or forgotten personal items left behind in the vehicle. We kindly ask all passengers to double-check their belongings before exiting the vehicle.',
                'box'   => true,
            ],
            [
                'title' => 'Child Car Seat Policy',
                'body'  => 'Customers are solely responsible for providing and properly installing their own child car seats in accordance with all applicable state laws. Optimus Fleets LLC does not supply, install, or secure car seats, and our chauffeurs are not permitted to assist with installation. By traveling with us, the customer acknowledges that they assume full responsibility for the installation, use, and safety of any child restraint system and agrees to hold Optimus Fleets LLC harmless for any issues arising from the customer\'s installation or use of the car seat.',
                'box'   => false,
            ],
            [
                'title' => 'Reservation Accuracy',
                'body'  => 'Please check this reservation confirmation and make sure all the information is correct. If there is anything that needs to be changed, please call us immediately at (856)-443-3401. It is your responsibility to review your confirmation and ensure that all details are correct. Should you find a discrepancy, please notify our office immediately.',
                'box'   => false,
            ],
            [
                'title' => 'Cancellation Policy',
                'body'  => 'A charge equal to the total trip cost will be applied for any cancellation. A minimum of 48 hours notice is required prior to the scheduled pick-up time for Sedans & SUVs. A minimum of 7 days notice is required for all Sprinter Vans, Vinterra\'s, Stretch Limousines, Minibuses, Coaches, and all other vehicles. Cancellation Policies are Subject to Change.' . "\n" . 'For New Jersey Only: Cancellation of Airport Transfer for Sedans and SUVs will be 12 hours prior. If you cancel before this time period there will be a 5% billable Credit Card Processing Fee.',
                'box'   => false,
            ],
            [
                'title' => 'Vehicle Conduct & Fees',
                'body'  => 'Smoking is NOT permitted in any of our vehicles. A $500-$750.00 minimum charge will be applied if passengers smoke in any vehicle. Animals must always remain in a carrier. A $500 cleanup fee WILL BE CHARGED to the credit card on file.' . "\n" . 'Any trip between 10:59 PM - 5:59 AM will be charged additional fees ranging $25-$40 depending on the time and route. All extra stops are subject to additional charges; the amount depends on distance and wait time for each stop.',
                'box'   => false,
            ],
            [
                'title' => 'Airport Procedures',
                'body'  => 'Option 1 - Curbside Pick-Up (Default): Our dispatch and chauffeurs track flights in real time. Your chauffeur will contact you once you land. If not contacted within 10 minutes of landing, call/text our 24/7 dispatch at (856)-443-3401.' . "\n" . 'Option 2 - Inside Pick-Up / Meet & Greet ($25 Fee): Must be ordered prior to pick-up time. Chauffeur will hold a sign near Baggage Claim 4. Must be scheduled for the exact arrival time; no exceptions. Defaults to curbside if flight info is not provided.',
                'box'   => false,
            ],
            [
                'title' => 'Multiple Travelers on Different Flights',
                'body'  => 'Notification is always required if passengers are traveling on separate flights. The requested pick-up time must correspond to the last arriving passenger\'s scheduled flight. If Inside Pick-Up is requested for the first arriving passenger, additional wait time fees will apply.',
                'box'   => false,
            ],
            [
                'title' => 'Wait & Travel Time',
                'body'  => 'Travel time for NJ Zone 4 locations is computed door-to-door from vehicle arrival to drop-off. Additional travel time applies beyond the 50-mile Zone 4 area. Special Event Transfers are charged at a 2-hour minimum plus travel time.' . "\n" . 'Airport transfers: Wheels Down - up to 45 minutes after arrival is no extra charge; additional time billed in 15-minute increments. Non-airport pick-ups: first 15 minutes past scheduled time is free; after that, 25% of base rate per 15 minutes.',
                'box'   => false,
            ],
            [
                'title' => 'Deposit Policy & No-Show',
                'body'  => 'Out-of-state or over-the-road charters require a 50% deposit upon reservation and 50% balance due 7 days prior. Customer failure to appear will incur a 100% No-Show fee. If you cannot locate your chauffeur, call (856)-443-3401 before making alternate arrangements to avoid a No-Show charge. We reserve the right to terminate a trip for unruly or unsafe passenger behavior at any time.',
                'box'   => false,
            ],
            [
                'title' => 'Child Safety - Georgia Law',
                'body'  => 'Georgia law requires children under 8 to be in a car seat or booster. We can provide child car seats and boosters on request at $30.00 each.',
                'box'   => false,
            ],
            [
                'title' => 'Winter Weather Advisory',
                'body'  => 'Optimus Fleets LLC will not take risks with our clients, chauffeurs, or vehicles in severe, unsafe road conditions. We will continually monitor conditions with the D.O.T. and advise if changes or cancellations are necessary.',
                'box'   => false,
            ],
            [
                'title' => 'Grace Period Policy',
                'body'  => 'At Optimus Fleets, we understand that unforeseen circumstances such as traffic or road issues can cause delays. In such cases, we may require a grace period of 25-30 minutes to ensure that we provide you with the best service possible. We appreciate your understanding and patience.',
                'box'   => false,
            ],
        ];

        // ── FPDF setup ────────────────────────────────────────────────────────
        $pdf = new FPDF( 'P', 'mm', 'Letter' );
        $pdf->SetMargins( 0, 0, 0 );
        $pdf->SetAutoPageBreak( false ); // We control page breaks manually
        $pdf->SetTitle( 'Optimus Fleets Reservation #' . $booking_id );
        $pdf->SetAuthor( 'Optimus Fleets LLC' );
        $pdf->SetCreator( 'FG Booking Wizard' );
        $pdf->AddPage();

        $pw   = $pdf->GetPageWidth();   // 215.9 mm (Letter)
        $ph   = $pdf->GetPageHeight();  // 279.4 mm (Letter)
        $lm   = 12;
        $rm   = 12;
        $cw   = $pw - $lm - $rm;
        $y    = 0;
        $page = 1;

        // Footer height — reserved at bottom of each page
        $fh = 14;
        // Bottom safe limit — stop drawing body content here
        $bottom = $ph - $fh - 4;

        // ── Colour palette ────────────────────────────────────────────────────
        $navy   = self::rgb( '#0f172a' );
        $orange = self::rgb( '#FD8B48' );
        $dgrey  = self::rgb( '#374151' );
        $lgrey  = self::rgb( '#6b7280' );
        $rowalt = self::rgb( '#f1f5f9' );

        $setFill = fn( $c ) => $pdf->SetFillColor( $c[0], $c[1], $c[2] );
        $setTxt  = fn( $c ) => $pdf->SetTextColor( $c[0], $c[1], $c[2] );

        // ── Helper: draw footer on current page then start new page ──────────
        $draw_footer = function( float $fy ) use ( $pdf, $pw, $ph, $fh, $navy, $orange, $setFill, $setTxt ) {
            $setFill( $navy );
            $pdf->Rect( 0, $fy, $pw, $ph - $fy + 1, 'F' );
            $pdf->SetFont( 'helvetica', 'B', 8 ); $setTxt( $orange );
            $pdf->SetXY( 0, $fy + 1.5 ); $pdf->Cell( $pw, 4, 'Optimus Fleets LLC', 0, 2, 'C' );
            $pdf->SetFont( 'helvetica', '', 7 ); $setTxt( $orange );
            $pdf->SetXY( 0, $fy + 6.5 ); $pdf->Cell( $pw, 4, 'www.optimusfleets.us', 0, 2, 'C' );
            $pdf->SetFont( 'helvetica', '', 6.5 ); $setTxt( [ 156, 163, 175 ] );
            $pdf->SetXY( 0, $fy + 11 ); $pdf->Cell( $pw, 4, '(856)-443-3401  |  Available 24/7', 0, 0, 'C' );
        };
        $new_page = function() use ( $pdf, $pw, $ph, $fh, $navy, $orange, $setFill, $setTxt, $draw_footer, &$y, &$page, &$bottom ) {
            $draw_footer( $ph - $fh );
            $pdf->AddPage();
            $page++;
            $y = 10;
            $bottom = $ph - $fh - 4;
        };

        // ── Helper: check space, add page if needed ───────────────────────────
        $need = function( float $h ) use ( &$y, $bottom, $new_page ) {
            if ( $y + $h > $bottom ) $new_page();
        };

        // ── Helper: detail row ────────────────────────────────────────────────
        $detail_row = function( string $label, string $value, float $rx, float $ry, float $rw, bool $alt ) use ( $pdf, $setFill, $setTxt, $rowalt, $lgrey, $dgrey ): float {
            $lw = 38;
            $vw = $rw - $lw - 1;
            if ( $alt ) {
                $setFill( $rowalt );
                $pdf->Rect( $rx, $ry, $rw, 8, 'F' );
            }
            $pdf->SetFont( 'helvetica', '', 7.5 );
            $setTxt( $lgrey );
            $pdf->SetXY( $rx + 1, $ry + 1 );
            $pdf->Cell( $lw, 6, $label, 0, 0, 'L' );
            $pdf->SetFont( 'helvetica', 'B', 8.5 );
            $setTxt( $dgrey );
            $pdf->SetXY( $rx + $lw + 1, $ry + 1 );
            $before = $pdf->GetY();
            $pdf->MultiCell( $vw, 4, $value, 0, 'L' );
            return max( 8, $pdf->GetY() - $ry + 1 );
        };

        // ── Helper: section heading ───────────────────────────────────────────
        $section_heading = function( string $title ) use ( $pdf, $lm, $cw, $orange, $navy, $setFill, $setTxt, &$y ) {
            $y += 4;
            $pdf->SetFont( 'helvetica', 'B', 9 );
            $setTxt( $navy );
            $pdf->SetXY( $lm, $y );
            $pdf->Cell( $cw, 5, strtoupper( $title ), 0, 2, 'L' );
            $y += 5;
            $setFill( $orange );
            $pdf->Rect( $lm, $y, $cw, 0.8, 'F' );
            $y += 2.5;
        };

        // ═════════════════════════════════════════════════════════════════════
        // PAGE 1
        // ═════════════════════════════════════════════════════════════════════

        // ── HEADER ────────────────────────────────────────────────────────────
        $setFill( $navy ); $pdf->Rect( 0, 0, $pw, 22, 'F' );
        $logo_path = FGBW_PLUGIN_DIR . 'assets/images/email-logo.png';
        $logo_ok   = false;
        if ( file_exists( $logo_path ) ) {
            try { $pdf->Image( $logo_path, $lm, 2, 26, 0 ); $logo_ok = true; } catch ( \Throwable $e ) {}
        }
        // Logo on left, text always starts at $lm (left-aligned)
        $text_x = $logo_ok ? $lm + 29 : $lm;
        $pdf->SetFont( 'helvetica', 'B', 14 ); $setTxt( [ 255, 255, 255 ] );
        $pdf->SetXY( $lm, 4 );
        $pdf->Cell( $pw - ( $lm * 2 ), 8, 'OPTIMUS FLEETS LLC', 0, 0, 'L' );
        $pdf->SetFont( 'helvetica', '', 7.5 ); $setTxt( $orange );
        $pdf->SetXY( $lm, 13 );
        $pdf->Cell( $pw - ( $lm * 2 ), 5, 'LUXURY LIMO / CHARTER SERVICES', 0, 0, 'L' );
        $y = 22;

        // Orange rule
        $setFill( $orange ); $pdf->Rect( 0, $y, $pw, 2.5, 'F' ); $y += 2.5;

        // Reservation band
        $pdf->SetFillColor( 30, 41, 59 ); $pdf->Rect( 0, $y, $pw, 9, 'F' );
        $pdf->SetFont( 'helvetica', 'B', 9.5 ); $setTxt( [ 255, 255, 255 ] );
        $pdf->SetXY( $lm, $y + 1.5 ); $pdf->Cell( 35, 6, 'RESERVATION', 0, 0, 'L' );
        $setTxt( $orange ); $pdf->Cell( 50, 6, '#' . $booking_id, 0, 0, 'L' );
        $y += 9;

        // Customer card
        $card_h = 20;
        $setFill( $orange ); $pdf->Rect( $lm, $y + 3, 2.5, $card_h - 2, 'F' );
        $pdf->SetFont( 'helvetica', 'B', 12 ); $setTxt( $navy );
        $pdf->SetXY( $lm + 5, $y + 3.5 );
        $pdf->Cell( $cw - 6, 7, $name, 0, 2, 'L' );
        $pdf->SetFont( 'helvetica', '', 8 ); $setTxt( $lgrey );
        $pdf->SetXY( $lm + 5, $y + 11 );
        $pdf->Cell( $cw - 6, 5, $email . ( $phone ? '  |  ' . $phone : '' ), 0, 0, 'L' );


        $y += $card_h;

        // Personal message — hardcoded
        $pdf->SetFont( 'helvetica', 'B', 7.5 ); $setTxt( $lgrey );
        $pdf->SetXY( $lm + 5, $y ); $pdf->Cell( 35, 4, 'Personal Message:', 0, 0, 'L' );
        $pdf->SetFont( 'helvetica', 'I', 8 ); $setTxt( $dgrey );
        $pdf->SetXY( $lm + 5, $y + 4 ); $pdf->MultiCell( $cw - 6, 4, $note, 0, 'L' );
        $y = $pdf->GetY() + 2;

        // ── ROUTING ───────────────────────────────────────────────────────────
        $section_heading( 'Passenger & Routing Information' );

        $col_w = ( $cw - 4 ) / 2;
        $ox    = $lm;
        $rx2   = $lm + $col_w + 4;
        $sy    = $y;

        $pdf->SetFillColor( 30, 41, 59 ); $pdf->Rect( $ox, $sy, $col_w, 7.5, 'F' );
        $pdf->SetFont( 'helvetica', 'B', 8 ); $setTxt( [ 255, 255, 255 ] );
        $pdf->SetXY( $ox + 2, $sy + 1.5 ); $pdf->Cell( $col_w - 4, 5, 'OUTBOUND', 0, 0, 'L' );
        $setFill( $orange ); $pdf->Rect( $rx2, $sy, $col_w, 7.5, 'F' );
        $setTxt( [ 255, 255, 255 ] );
        $pdf->SetXY( $rx2 + 2, $sy + 1.5 ); $pdf->Cell( $col_w - 4, 5, 'RETURN', 0, 0, 'L' );
        $y = $sy + 8;

        $out_rows = [
            [ 'Service Type',     $svc ],
            [ 'Pick-Up Date',     $pickup_date ],
            [ 'Pick-Up Time',     $pickup_time ],
            [ 'Pick-Up Location', $pickup_loc ],
        ];
        foreach ( $out_stops as $i => $s ) $out_rows[] = [ 'Stop ' . ( $i + 1 ), $s ];
        $out_rows[] = [ 'Drop-Off',   $dropoff_loc ];
        $out_rows[] = [ 'Passengers', (string)$pax ];
        $out_rows[] = [ 'Airline',    $al_lbl ];
        $out_rows[] = [ 'Flight No.', $fl_lbl ];

        $ret_rows = [
            [ 'Return Date', $ret_date ],
            [ 'Return Time', $ret_time ],
            [ 'Pick-Up',     $ret_pu_loc ],
        ];
        foreach ( $ret_stops as $i => $s ) $ret_rows[] = [ 'Stop ' . ( $i + 1 ), $s ];
        $ret_rows[] = [ 'Drop-Off',   $ret_do_loc ];
        $ret_rows[] = [ 'Airline',    $r_al_lbl ];
        $ret_rows[] = [ 'Flight No.', $r_fl_lbl ];

        $max = max( count( $out_rows ), count( $ret_rows ) );
        for ( $i = 0; $i < $max; $i++ ) {
            $alt = ( $i % 2 === 1 );
            $ry  = $y;
            $h_o = 8; $h_r = 8;
            if ( isset( $out_rows[$i] ) ) $h_o = $detail_row( $out_rows[$i][0], $out_rows[$i][1], $ox,  $ry, $col_w, $alt );
            if ( isset( $ret_rows[$i] ) ) $h_r = $detail_row( $ret_rows[$i][0], $ret_rows[$i][1], $rx2, $ry, $col_w, $alt );
            $y += max( $h_o, $h_r );
        }
        $y += 1;

        // ── Check if agreement fits on same page, only add page if truly needed
        // Remaining space on page 1 after routing table
        $remaining = $bottom - $y;
        if ( $remaining < 40 ) {
            $new_page();
        }

        // ── STANDARD RENTAL AGREEMENT ─────────────────────────────────────────
        $section_heading( 'Standard Rental Agreement' );

        foreach ( $policies as $pol ) {
            $title = $pol['title'];
            $body  = $pol['body'];
            $is_box = $pol['box'];

            // Estimate height needed
            $lines_est = ceil( strlen( $body ) / 90 ) + 1;
            $est_h     = $is_box ? $lines_est * 3.8 + 14 : $lines_est * 3.8 + 8;
            $need( $est_h );

            if ( $is_box ) {
                // Orange-border important box
                $pdf->SetFillColor( 255, 247, 237 );
                // Measure body height first
                $pdf->SetFont( 'helvetica', '', 7.5 );
                // Use a temp measure approach — just render with enough space
                $box_y = $y;
                $setFill( [ 255, 247, 237 ] );
                $pdf->Rect( $lm, $box_y, $cw, 4, 'F' ); // placeholder, will be overdrawn
                $setFill( $orange ); $pdf->Rect( $lm, $box_y, 2.5, 4, 'F' );
                $pdf->SetFont( 'helvetica', 'B', 8 ); $setTxt( $orange );
                $pdf->SetXY( $lm + 5, $box_y + 1.5 );
                $pdf->Cell( 40, 4, strtoupper( $title ), 0, 0, 'L' );
                $y = $box_y + 7;
                $pdf->SetFont( 'helvetica', '', 7.5 ); $setTxt( $dgrey );
                $pdf->SetXY( $lm + 5, $y );
                $pdf->MultiCell( $cw - 8, 3.6, $body, 0, 'L' );
                $box_end = $pdf->GetY() + 2;
                // Draw the background rect now we know the height
                $box_real_h = $box_end - $box_y;
                $pdf->SetFillColor( 255, 247, 237 );
                // Re-fill (can't truly go back in FPDF, so the text is already rendered — just add side border)
                $setFill( $orange ); $pdf->Rect( $lm, $box_y, 2.5, $box_real_h, 'F' );
                $y = $box_end + 2;
            } else {
                $pdf->SetFont( 'helvetica', 'B', 8 ); $setTxt( $navy );
                $pdf->SetXY( $lm, $y );
                $pdf->Cell( $cw, 5, $title, 0, 2, 'L' );
                $y += 5;
                $pdf->SetFont( 'helvetica', '', 7.5 ); $setTxt( $dgrey );
                $pdf->SetXY( $lm, $y );
                $pdf->MultiCell( $cw, 3.6, $body, 0, 'L' );
                $y = $pdf->GetY() + 3;
            }
        }

        // ── SIGNATURE + PRICE + DATE ──────────────────────────────────────────
        $need( 22 );
        $y += 3;
        $pdf->SetDrawColor( 200, 200, 200 ); $pdf->SetLineWidth( 0.3 );
        $pdf->Line( $lm, $y, $pw - $rm, $y ); $y += 4;

        $pdf->SetFont( 'helvetica', '', 8 ); $setTxt( $dgrey );

        // Row: Authorized Signature + Price + Date  (three fields on one line)
        $pdf->SetXY( $lm, $y );
        $pdf->Cell( 46, 5, 'Authorized Signature:', 0, 0, 'L' );
        $pdf->SetDrawColor( 160, 160, 160 );
        $pdf->Line( $lm + 46, $y + 4, $lm + 90, $y + 4 );   // signature blank

        $pdf->SetXY( $lm + 95, $y );
        $pdf->Cell( 14, 5, 'Price:', 0, 0, 'L' );
        $pdf->Line( $lm + 109, $y + 4, $lm + 140, $y + 4 );  // price blank

        $pdf->SetXY( $lm + 145, $y );
        $pdf->Cell( 10, 5, 'Date:', 0, 0, 'L' );
        $pdf->Line( $lm + 155, $y + 4, $pw - $rm, $y + 4 );  // date blank

        $y += 12;

        // ── FOOTER ON LAST PAGE ───────────────────────────────────────────────
        $fy = max( $y + 2, $ph - $fh );
        $draw_footer( $fy );

        return $pdf;
    }
}
