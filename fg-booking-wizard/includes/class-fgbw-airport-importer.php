<?php
if (!defined('ABSPATH')) exit;

class FGBW_Airport_Importer
{

    public static function import_from_csv(string $file_path): void
    {

        global $wpdb;
        $table = $wpdb->prefix . 'fg_airports';

        if (!file_exists($file_path)) {
            return;
        }

        if (($handle = fopen($file_path, "r")) !== false) {

            // Skip header
            $header = fgetcsv($handle);

            while (($data = fgetcsv($handle)) !== false) {

                $wpdb->insert($table, [
                    'airport_name' => sanitize_text_field($data[3] ?? ''),
                    'city'         => sanitize_text_field($data[10] ?? ''),
                    'country'      => sanitize_text_field($data[8] ?? ''),
                    'iata_code'    => sanitize_text_field($data[13] ?? ''),
                    'icao_code'    => sanitize_text_field($data[12] ?? ''),
                    'airport_type' => sanitize_text_field($data[2] ?? ''), // IMPORTANT
                    'lat'          => floatval($data[4] ?? 0),
                    'lng'          => floatval($data[5] ?? 0),
                ]);
            }

            fclose($handle);
        }
    }
}

class FGBW_Airline_Importer {

    public static function import_from_dat(string $file_path): void {

        global $wpdb;
        $table = $wpdb->prefix . 'fg_airlines';

        if (!file_exists($file_path)) return;

        if (($handle = fopen($file_path, "r")) !== false) {

            while (($data = fgetcsv($handle, 1000, ",")) !== false) {

                $airline_name = trim($data[1] ?? '');
                $iata_code    = trim($data[3] ?? '');
                $icao_code    = trim($data[4] ?? '');
                $country      = trim($data[6] ?? '');
                $active       = ($data[7] ?? '') === 'Y' ? 1 : 0;

                // Skip airlines without IATA
                if (empty($iata_code)) continue;

                $wpdb->insert($table, [
                    'airline_name' => sanitize_text_field($airline_name),
                    'iata_code'    => sanitize_text_field($iata_code),
                    'icao_code'    => sanitize_text_field($icao_code),
                    'country'      => sanitize_text_field($country),
                    'active'       => $active,
                ]);
            }

            fclose($handle);
        }
    }
}