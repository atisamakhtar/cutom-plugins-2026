<?php
if (!defined('ABSPATH')) exit;

/**
 * FGBW_Admin
 * Registers a top-level WordPress admin menu ("FG Booking Wizard")
 * with two sub-tabs: Settings and Bookings.
 *
 * The old Settings entry under Settings → FG Booking Wizard still works
 * (kept for backwards compatibility), but the primary UI lives here.
 */
class FGBW_Admin {

    public function init(): void {
        add_action('admin_menu',            [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init',            [$this, 'register_settings']);
        add_action('wp_ajax_fgbw_export_bookings', [$this, 'export_csv']);
    }

    /* ---------------------------------------------------------------
     * Menu
     * ------------------------------------------------------------- */

    public function register_menu(): void {
        add_menu_page(
            'FG Booking Wizard',
            'FG Bookings',
            'manage_options',
            'fgbw-admin',
            [$this, 'render_page'],
            'dashicons-calendar-alt',
            25
        );

        add_submenu_page(
            'fgbw-admin',
            'Bookings',
            'Bookings',
            'manage_options',
            'fgbw-admin',
            [$this, 'render_page']
        );

        add_submenu_page(
            'fgbw-admin',
            'Settings',
            'Settings',
            'manage_options',
            'fgbw-admin-settings',
            [$this, 'render_settings_page']
        );
    }

    /* ---------------------------------------------------------------
     * Assets
     * ------------------------------------------------------------- */

    public function enqueue_assets(string $hook): void {
        if (!in_array($hook, ['toplevel_page_fgbw-admin', 'fg-bookings_page_fgbw-admin-settings'], true)) return;
        wp_enqueue_style('fgbw-admin', FGBW_PLUGIN_URL . 'assets/css/fgbw-admin.css', [], FGBW_VERSION);
    }

    /* ---------------------------------------------------------------
     * Settings registration (same data as before, just new page slug)
     * ------------------------------------------------------------- */

    public function register_settings(): void {
        register_setting('fgbw_settings_group', 'fgbw_settings', [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default'           => [],
        ]);
    }

    public function sanitize_settings($input): array {
        $input = is_array($input) ? $input : [];
        $out   = [];

        $out['google_places_key']      = sanitize_text_field($input['google_places_key']      ?? '');
        $out['aviationstack_key']      = sanitize_text_field($input['aviationstack_key']      ?? '');
        $out['admin_email']            = sanitize_email($input['admin_email']                 ?? get_option('admin_email'));
        $out['email_customer_subject'] = sanitize_text_field($input['email_customer_subject'] ?? 'Your booking #{booking_id} is received');
        $out['email_admin_subject']    = sanitize_text_field($input['email_admin_subject']    ?? 'New booking #{booking_id} received');

        $allowed                       = wp_kses_allowed_html('post');
        $out['email_customer_body']    = wp_kses($input['email_customer_body'] ?? '', $allowed);
        $out['email_admin_body']       = wp_kses($input['email_admin_body']    ?? '', $allowed);

        return $out;
    }

    /* ---------------------------------------------------------------
     * Settings page render
     * ------------------------------------------------------------- */

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) return;
        $saved = get_option('fgbw_settings', []);

        $g   = esc_attr($saved['google_places_key']      ?? '');
        $av  = esc_attr($saved['aviationstack_key']      ?? '');
        $ae  = esc_attr($saved['admin_email']            ?? get_option('admin_email'));
        $cs  = esc_attr($saved['email_customer_subject'] ?? 'Your booking #{booking_id} is received');
        $cb  = esc_textarea($saved['email_customer_body'] ?? file_get_contents(FGBW_PLUGIN_DIR . 'templates/emails/customer.php'));
        $as  = esc_attr($saved['email_admin_subject']    ?? 'New booking #{booking_id} received');
        $ab  = esc_textarea($saved['email_admin_body']   ?? file_get_contents(FGBW_PLUGIN_DIR . 'templates/emails/admin.php'));
        ?>
        <div class="wrap fgbw-admin-wrap">
            <h1 class="fgbw-admin-title">
                <span class="dashicons dashicons-calendar-alt"></span>
                FG Booking Wizard — Settings
            </h1>

            <?php settings_errors('fgbw_settings_group'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('fgbw_settings_group'); ?>
                <input type="hidden" name="option_page" value="fgbw_settings_group">
                <input type="hidden" name="action" value="update">
                <?php wp_nonce_field('fgbw_settings_group-options'); ?>

                <!-- API Keys -->
                <div class="fgbw-card">
                    <h2 class="fgbw-card-title">
                        <span class="dashicons dashicons-admin-network"></span> API Keys
                    </h2>
                    <table class="form-table fgbw-form-table">
                        <tr>
                            <th>Google Places API Key <span class="fgbw-badge fgbw-badge--blue">Frontend</span></th>
                            <td>
                                <input type="text" class="regular-text" name="fgbw_settings[google_places_key]" value="<?php echo $g; ?>" />
                                <p class="description">Exposed to frontend JS — required for address autocomplete.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Aviationstack API Key <span class="fgbw-badge fgbw-badge--green">Server-only</span></th>
                            <td>
                                <input type="password" class="regular-text" name="fgbw_settings[aviationstack_key]" value="<?php echo $av; ?>" autocomplete="new-password" />
                                <p class="description">Never exposed to the frontend — all flight lookups are server-proxied.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Admin Notification Email</th>
                            <td>
                                <input type="email" class="regular-text" name="fgbw_settings[admin_email]" value="<?php echo $ae; ?>" />
                                <p class="description">Where new booking alerts are sent.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Email Templates -->
                <div class="fgbw-card">
                    <h2 class="fgbw-card-title">
                        <span class="dashicons dashicons-email-alt"></span> Email Templates
                    </h2>
                    <p class="fgbw-placeholders-note">
                        <strong>Available placeholders:</strong>
                        <code>{booking_id}</code> <code>{name}</code> <code>{trip_type}</code>
                        <code>{order_type}</code> <code>{vehicle}</code> <code>{passenger_count}</code>
                        <code>{pickup_summary}</code> <code>{return_summary}</code>
                    </p>

                    <h3 class="fgbw-section-subtitle">Customer Email</h3>
                    <table class="form-table fgbw-form-table">
                        <tr>
                            <th>Subject</th>
                            <td><input type="text" class="large-text" name="fgbw_settings[email_customer_subject]" value="<?php echo $cs; ?>" /></td>
                        </tr>
                        <tr>
                            <th>Body <span class="fgbw-badge fgbw-badge--gray">HTML allowed</span></th>
                            <td><textarea class="large-text code" rows="10" name="fgbw_settings[email_customer_body]"><?php echo $cb; ?></textarea></td>
                        </tr>
                    </table>

                    <h3 class="fgbw-section-subtitle">Admin Email</h3>
                    <table class="form-table fgbw-form-table">
                        <tr>
                            <th>Subject</th>
                            <td><input type="text" class="large-text" name="fgbw_settings[email_admin_subject]" value="<?php echo $as; ?>" /></td>
                        </tr>
                        <tr>
                            <th>Body <span class="fgbw-badge fgbw-badge--gray">HTML allowed</span></th>
                            <td><textarea class="large-text code" rows="10" name="fgbw_settings[email_admin_body]"><?php echo $ab; ?></textarea></td>
                        </tr>
                    </table>
                </div>

                <div class="fgbw-save-row">
                    <?php submit_button('Save Settings', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------
     * Bookings page render
     * ------------------------------------------------------------- */

    public function render_page(): void {
        if (!current_user_can('manage_options')) return;

        global $wpdb;
        $table = $wpdb->prefix . 'fg_bookings';

        /* ---- Filters ---- */
        $order_type  = sanitize_text_field($_GET['order_type']  ?? '');
        $trip_type   = sanitize_text_field($_GET['trip_type']   ?? '');
        $date_from   = sanitize_text_field($_GET['date_from']   ?? '');
        $date_to     = sanitize_text_field($_GET['date_to']     ?? '');
        $search      = sanitize_text_field($_GET['search']      ?? '');
        $paged       = max(1, intval($_GET['paged'] ?? 1));
        $per_page    = 20;
        $offset      = ($paged - 1) * $per_page;

        /* ---- Build WHERE ---- */
        $where  = ['1=1'];
        $params = [];

        if ($order_type) {
            $where[]  = 'order_type = %s';
            $params[] = $order_type;
        }
        if ($trip_type) {
            $where[]  = 'trip_type = %s';
            $params[] = $trip_type;
        }
        if ($date_from) {
            $where[]  = 'DATE(created_at) >= %s';
            $params[] = $date_from;
        }
        if ($date_to) {
            $where[]  = 'DATE(created_at) <= %s';
            $params[] = $date_to;
        }
        if ($search) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = '(name LIKE %s OR email LIKE %s OR phone LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);

        /* ---- Count & fetch ---- */
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total     = $params
            ? (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$params))
            : (int) $wpdb->get_var($count_sql);

        $data_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $all_params = array_merge($params, [$per_page, $offset]);
        $bookings = $wpdb->get_results($wpdb->prepare($data_sql, ...$all_params), ARRAY_A);

        /* ---- Distinct order types for filter dropdown ---- */
        $order_types = $wpdb->get_col("SELECT DISTINCT order_type FROM {$table} WHERE order_type != '' ORDER BY order_type ASC");

        $total_pages = (int) ceil($total / $per_page);

        /* ---- Base filter URL ---- */
        $base_url = admin_url('admin.php?page=fgbw-admin');
        $filter_url = $base_url
            . ($order_type ? '&order_type=' . urlencode($order_type) : '')
            . ($trip_type  ? '&trip_type='  . urlencode($trip_type)  : '')
            . ($date_from  ? '&date_from='  . urlencode($date_from)  : '')
            . ($date_to    ? '&date_to='    . urlencode($date_to)    : '')
            . ($search     ? '&search='     . urlencode($search)     : '');

        ?>
        <div class="wrap fgbw-admin-wrap">
            <h1 class="fgbw-admin-title">
                <span class="dashicons dashicons-calendar-alt"></span>
                FG Booking Wizard — Bookings
                <a href="<?php echo esc_url($filter_url . '&fgbw_export=1'); ?>" class="page-title-action fgbw-export-btn">
                    <span class="dashicons dashicons-download"></span> Export CSV
                </a>
            </h1>

            <!-- Filters Bar -->
            <div class="fgbw-filters-bar">
                <form method="get" action="<?php echo esc_url($base_url); ?>" class="fgbw-filters-form">
                    <input type="hidden" name="page" value="fgbw-admin" />

                    <div class="fgbw-filter-group">
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Name, email, phone…" class="fgbw-filter-input" />
                    </div>

                    <div class="fgbw-filter-group">
                        <label>Order Type</label>
                        <select name="order_type" class="fgbw-filter-select">
                            <option value="">All</option>
                            <?php foreach ($order_types as $ot): ?>
                                <option value="<?php echo esc_attr($ot); ?>" <?php selected($order_type, $ot); ?>>
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $ot))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fgbw-filter-group">
                        <label>Trip Type</label>
                        <select name="trip_type" class="fgbw-filter-select">
                            <option value="">All</option>
                            <option value="one_way"    <?php selected($trip_type, 'one_way'); ?>>One Way</option>
                            <option value="round_trip" <?php selected($trip_type, 'round_trip'); ?>>Round Trip</option>
                        </select>
                    </div>

                    <div class="fgbw-filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="fgbw-filter-input" />
                    </div>

                    <div class="fgbw-filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="fgbw-filter-input" />
                    </div>

                    <div class="fgbw-filter-actions">
                        <button type="submit" class="button button-primary">Filter</button>
                        <a href="<?php echo esc_url($base_url); ?>" class="button">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Stats row -->
            <div class="fgbw-stats-row">
                <div class="fgbw-stat-pill">
                    <span class="fgbw-stat-num"><?php echo number_format($total); ?></span>
                    <span class="fgbw-stat-label"><?php echo ($order_type || $trip_type || $date_from || $date_to || $search) ? 'Matching Bookings' : 'Total Bookings'; ?></span>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="fgbw-stat-pill">
                    <span class="fgbw-stat-num">Page <?php echo $paged; ?> / <?php echo $total_pages; ?></span>
                    <span class="fgbw-stat-label">Pagination</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Bookings Table -->
            <div class="fgbw-card fgbw-card--table">
                <?php if (empty($bookings)): ?>
                    <div class="fgbw-empty-state">
                        <span class="dashicons dashicons-clipboard"></span>
                        <p>No bookings found<?php echo ($order_type || $trip_type || $date_from || $date_to || $search) ? ' matching the current filters.' : ' yet.'; ?></p>
                    </div>
                <?php else: ?>
                <div class="fgbw-table-scroll">
                <table class="fgbw-table widefat striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Trip Type</th>
                            <th>Order Type</th>
                            <th>Route</th>
                            <th>Pax</th>
                            <th>Vehicle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b):
                            $pickup_data = json_decode($b['pickup_json'] ?? '{}', true);
                            $from = self::loc_label($pickup_data['pickup'] ?? null);
                            $to   = self::loc_label($pickup_data['dropoff'] ?? null);
                            $date = $b['created_at'] ? date('M j, Y g:i a', strtotime($b['created_at'])) : '—';
                            $trip_label = $b['trip_type'] === 'round_trip' ? 'Round Trip' : 'One Way';
                            $trip_badge = $b['trip_type'] === 'round_trip' ? 'fgbw-badge--blue' : 'fgbw-badge--gray';
                        ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($b['booking_id']); ?></strong></td>
                            <td class="fgbw-td-date"><?php echo esc_html($date); ?></td>
                            <td>
                                <div class="fgbw-customer-cell">
                                    <strong><?php echo esc_html($b['name']); ?></strong>
                                    <a href="mailto:<?php echo esc_attr($b['email']); ?>" class="fgbw-td-email"><?php echo esc_html($b['email']); ?></a>
                                    <span class="fgbw-td-phone"><?php echo esc_html($b['phone']); ?></span>
                                </div>
                            </td>
                            <td><span class="fgbw-badge <?php echo $trip_badge; ?>"><?php echo esc_html($trip_label); ?></span></td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $b['order_type']))); ?></td>
                            <td class="fgbw-td-route">
                                <?php if ($from): ?>
                                <span class="fgbw-route-from"><?php echo esc_html($from); ?></span>
                                <span class="fgbw-route-arrow">→</span>
                                <span class="fgbw-route-to"><?php echo esc_html($to ?: '—'); ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="fgbw-td-center"><?php echo intval($b['passenger_count']); ?></td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $b['vehicle'] ?: '—'))); ?></td>
                            <td>
                                <button type="button"
                                    class="button fgbw-detail-btn"
                                    data-booking='<?php echo esc_attr(wp_json_encode([
                                        'id'         => $b['booking_id'],
                                        'name'       => $b['name'],
                                        'email'      => $b['email'],
                                        'phone'      => $b['phone'],
                                        'created_at' => $date,
                                        'trip_type'  => $trip_label,
                                        'order_type' => ucwords(str_replace('_', ' ', $b['order_type'])),
                                        'vehicle'    => $b['vehicle'] ?: '—',
                                        'passengers' => $b['passenger_count'],
                                        'from'       => $from ?: '—',
                                        'to'         => $to ?: '—',
                                        'pickup_json'=> $b['pickup_json'],
                                        'return_json'=> $b['return_json'],
                                    ])); ?>'>
                                    View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="fgbw-pagination">
                    <?php
                    for ($p = 1; $p <= $total_pages; $p++) {
                        $url = $filter_url . '&paged=' . $p;
                        $active = ($p === $paged) ? ' fgbw-page-active' : '';
                        echo '<a href="' . esc_url($url) . '" class="fgbw-page-btn' . $active . '">' . $p . '</a>';
                    }
                    ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detail Modal -->
        <div id="fgbw-detail-modal" class="fgbw-modal-overlay" style="display:none;">
            <div class="fgbw-modal-box">
                <div class="fgbw-modal-header">
                    <h2 id="fgbw-modal-title">Booking Details</h2>
                    <button type="button" class="fgbw-modal-close" id="fgbw-modal-close">&times;</button>
                </div>
                <div class="fgbw-modal-content" id="fgbw-modal-content"></div>
            </div>
        </div>

        <script>
        (function($){
            // Detail modal
            $(document).on('click', '.fgbw-detail-btn', function(){
                const b = $(this).data('booking');
                let pickup = {}, ret = {};
                try { pickup = JSON.parse(b.pickup_json || '{}'); } catch(e){}
                try { ret    = JSON.parse(b.return_json || '{}'); } catch(e){}

                const row = (label, val) => val
                    ? `<tr><th>${label}</th><td>${val}</td></tr>`
                    : '';

                let html = `
                    <table class="fgbw-detail-table">
                        ${row('Booking ID', '#' + b.id)}
                        ${row('Date', b.created_at)}
                        ${row('Customer', b.name)}
                        ${row('Email', `<a href="mailto:${b.email}">${b.email}</a>`)}
                        ${row('Phone', b.phone)}
                        ${row('Trip Type', b.trip_type)}
                        ${row('Order Type', b.order_type)}
                        ${row('Vehicle', b.vehicle)}
                        ${row('Passengers', b.passengers)}
                        ${row('Pick-Up', b.from)}
                        ${row('Drop-Off', b.to)}
                    </table>`;

                if (pickup.datetime) {
                    html += `<h4 style="margin:16px 0 6px">Pickup Segment</h4>
                        <table class="fgbw-detail-table">
                            ${row('Date / Time', pickup.datetime)}
                            ${row('From', b.from)}
                            ${row('To', b.to)}
                        </table>`;
                }

                if (ret && ret.datetime) {
                    const retFrom = fgbwLocLabel(ret.pickup);
                    const retTo   = fgbwLocLabel(ret.dropoff);
                    html += `<h4 style="margin:16px 0 6px">Return Segment</h4>
                        <table class="fgbw-detail-table">
                            ${row('Date / Time', ret.datetime)}
                            ${row('From', retFrom)}
                            ${row('To', retTo)}
                        </table>`;
                }

                $('#fgbw-modal-title').text('Booking #' + b.id);
                $('#fgbw-modal-content').html(html);
                $('#fgbw-detail-modal').fadeIn(150);
            });

            function fgbwLocLabel(loc) {
                if (!loc) return '';
                if (loc.mode === 'address' && loc.address) return loc.address.formatted_address || '';
                if (loc.mode === 'airport' && loc.airport) return (loc.airport.airport_name || '') + ' (' + (loc.airport.iata_code || '') + ')';
                return '';
            }

            $('#fgbw-modal-close, #fgbw-detail-modal').on('click', function(e){
                if ($(e.target).is('#fgbw-detail-modal') || $(e.target).is('#fgbw-modal-close')) {
                    $('#fgbw-detail-modal').fadeOut(150);
                }
            });

            // ESC key closes modal
            $(document).on('keydown', function(e){
                if (e.key === 'Escape') $('#fgbw-detail-modal').fadeOut(150);
            });
        })(jQuery);
        </script>
        <?php

        // Handle CSV export
        if (!empty($_GET['fgbw_export'])) {
            $this->export_csv_inline($where_sql, $params);
        }
    }

    /* ---------------------------------------------------------------
     * Location label helper
     * ------------------------------------------------------------- */

    private static function loc_label(?array $loc): string {
        if (!$loc) return '';
        $mode = $loc['mode'] ?? '';
        if ($mode === 'address') {
            return $loc['address']['formatted_address'] ?? '';
        }
        if ($mode === 'airport') {
            $a = $loc['airport'] ?? [];
            return trim(($a['airport_name'] ?? '') . ' (' . ($a['iata_code'] ?? '') . ')');
        }
        return '';
    }

    /* ---------------------------------------------------------------
     * CSV Export
     * ------------------------------------------------------------- */

    private function export_csv_inline(string $where_sql, array $params): void {
        global $wpdb;
        $table = $wpdb->prefix . 'fg_bookings';

        $sql  = "SELECT booking_id, created_at, name, email, phone, trip_type, order_type, passenger_count, vehicle FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC";
        $rows = $params
            ? $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);

        $filename = 'fgbw-bookings-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Date', 'Name', 'Email', 'Phone', 'Trip Type', 'Order Type', 'Passengers', 'Vehicle']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['booking_id'],
                $r['created_at'],
                $r['name'],
                $r['email'],
                $r['phone'],
                $r['trip_type'],
                $r['order_type'],
                $r['passenger_count'],
                $r['vehicle'],
            ]);
        }
        fclose($out);
        exit;
    }
}
