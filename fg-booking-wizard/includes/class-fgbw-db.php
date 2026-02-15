<?php
if (!defined('ABSPATH')) exit;

class FGBW_DB
{
    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'fg_bookings';
    }

    public static function create_tables(): void
    {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            booking_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(60) NOT NULL,
            trip_type VARCHAR(30) NOT NULL,
            order_type VARCHAR(120) NOT NULL,
            passenger_count INT UNSIGNED NOT NULL,
            pickup_json LONGTEXT NULL,
            return_json LONGTEXT NULL,
            vehicle VARCHAR(120) NOT NULL,
            full_payload_json LONGTEXT NOT NULL,
            PRIMARY KEY (booking_id),
            KEY created_at (created_at),
            KEY email (email)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    public static function insert_booking(array $row): int
    {
        global $wpdb;

        $table = self::table_name();
        $defaults = [
            'created_at' => current_time('mysql'),
            'pickup_json' => null,
            'return_json' => null,
        ];
        $row = array_merge($defaults, $row);

        $ok = $wpdb->insert($table, $row, [
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s'
        ]);

        if (!$ok) return 0;
        return (int)$wpdb->insert_id;
    }

    public static function create_airports_table(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'fg_airports';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    airport_name VARCHAR(255),
    city VARCHAR(255),
    country VARCHAR(255),
    iata_code VARCHAR(10),
    icao_code VARCHAR(10),
    airport_type VARCHAR(50),
    lat DECIMAL(10,6),
    lng DECIMAL(10,6),
    KEY iata (iata_code),
    KEY city (city),
    KEY type (airport_type)
)";

        dbDelta($sql);
    }

    public static function create_airlines_table(): void
    {

        global $wpdb;

        $table = $wpdb->prefix . 'fg_airlines';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        airline_name VARCHAR(255),
        iata_code VARCHAR(10),
        icao_code VARCHAR(10),
        country VARCHAR(255),
        active TINYINT DEFAULT 1,
        KEY iata (iata_code),
        KEY name (airline_name)
    ) $charset_collate;";

        dbDelta($sql);
    }
}
