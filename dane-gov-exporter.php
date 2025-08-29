<?php

/**
 * Plugin Name: Dane.gov.pl Exporter lokali
 * Description: Eksport CSV zgodny z jawnością cen.
 * Version:     2.3.1
 * Author:      Roial.pl
 */

if (!defined('ABSPATH')) exit;

/* ===================== Stałe i includes ===================== */
define('DGE_PLUGIN_FILE', __FILE__);
define('DGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DGE_PLUGIN_URL', plugin_dir_url(__FILE__));

define('DGE_MENU_SLUG', 'dane-gov');
define('DGE_SUB_DEVELOPER_SLUG', 'dge-developer');
define('DGE_SUB_EXPORT_SLUG', 'dge-export');

/* Moduły */
require_once DGE_PLUGIN_DIR . 'includes/settings-developer.php';
require_once DGE_PLUGIN_DIR . 'includes/acf-fields-lokale.php';
if (file_exists(DGE_PLUGIN_DIR . 'includes/acf-fields-tax-inwestycje.php')) {
    require_once DGE_PLUGIN_DIR . 'includes/acf-fields-tax-inwestycje.php';
}
require_once DGE_PLUGIN_DIR . 'includes/relationship-sync.php';
require_once __DIR__ . '/includes/shortcodes.php';
require_once __DIR__ . '/includes/shortcode-history.php';
require_once __DIR__ . '/includes/shortcode-min-price.php';
require_once __DIR__ . '/includes/shortcode-accessories.php';
require_once __DIR__ . '/includes/shortcode-accessories-list.php';

/* ===================== Exporter CSV ===================== */
final class Dane_Gov_Exporter
{
    const CRON_HOOK       = 'dane_gov_exporter_daily';
    const FILENAME_LATEST = 'lokale-dane-gov.csv';
    const FILENAME_PREFIX = 'lokale-dane-gov-';

    /** Katalog docelowy na pliki eksportu w /dane w katalogu głównym WP */
    public static function target_dir()
    {
        return trailingslashit(ABSPATH) . 'dane/';
    }

    public function __construct()
    {
        add_action(self::CRON_HOOK, [$this, 'run_export']);
        register_activation_hook(DGE_PLUGIN_FILE, [__CLASS__, 'activate']);
        register_deactivation_hook(DGE_PLUGIN_FILE, [__CLASS__, 'deactivate']);
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('dane-gov export', [$this, 'cli_export']);
        }
    }

    // public static function activate()
    // {
    //     // Zaplanuj CRON na 02:00
    //     if (!wp_next_scheduled(self::CRON_HOOK)) {
    //         $now = current_time('timestamp');
    //         $today_2 = mktime(2, 0, 0, date('m', $now), date('d', $now), date('Y', $now));
    //         $first = ($now < $today_2) ? $today_2 : $today_2 + DAY_IN_SECONDS;
    //         wp_schedule_event($first, 'daily', self::CRON_HOOK);
    //     }

    //     // Upewnij się, że katalog /dane istnieje
    //     $dir = self::target_dir();
    //     if (!file_exists($dir)) {
    //         wp_mkdir_p($dir);
    //     }
    //     // Spróbuj zabezpieczyć i przygotować katalog
    //     if (is_dir($dir) && is_writable(dirname($dir))) {
    //         if (!file_exists($dir . 'index.html')) {
    //             @file_put_contents($dir . 'index.html', '');
    //         }
    //         // opcjonalnie zablokuj listing (jeśli używasz Apache i chcesz)
    //         if (!file_exists($dir . '.htaccess')) {
    //             @file_put_contents($dir . '.htaccess', "Options -Indexes\n");
    //         }
    //     }
    // }
    public static function activate()
    {
        // Upewnij się, że katalog /dane istnieje
        $dir = self::target_dir();
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        // Spróbuj zabezpieczyć i przygotować katalog
        if (is_dir($dir) && is_writable(dirname($dir))) {
            if (!file_exists($dir . 'index.html')) {
                @file_put_contents($dir . 'index.html', '');
            }
            if (!file_exists($dir . '.htaccess')) {
                @file_put_contents($dir . '.htaccess', "Options -Indexes\n");
            }
        }
    }


    public static function deactivate()
    {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) wp_unschedule_event($ts, self::CRON_HOOK);
    }

    public function cli_export()
    {
        $res = $this->run_export();
        if (is_wp_error($res)) \WP_CLI::error($res->get_error_message());
        \WP_CLI::success('Eksport zapisany: ' . $res);
    }

    /** Główny eksport — pełen szablon CSV. Puste wartości -> 'x'. */
    public function run_export()
    {
        // Docelowy katalog /dane/ w głównym katalogu WP
        $dir = self::target_dir();

        // Utwórz katalog, jeśli nie istnieje
        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                return new \WP_Error('mkdir_failed', 'Nie udało się utworzyć katalogu: ' . $dir);
            }
            @file_put_contents($dir . 'index.html', '');
        }

        // Sprawdź uprawnienia do zapisu
        if (!is_dir($dir) || !is_writable($dir)) {
            return new \WP_Error('no_write', 'Brak uprawnień do zapisu w katalogu: ' . $dir);
        }

        // ★ ZMIANA: dodana kolumna z ceną za m² przynależności
        $columns = [
            'Nazwa dewelopera',
            'Forma prawna dewelopera',
            'Nr KRS',
            'Nr wpisu do CEiDG',
            'Nr NIP',
            'Nr REGON',
            'Nr telefonu',
            'Adres poczty elektronicznej',
            'Nr faxu',
            'Adres strony internetowej dewelopera',
            'Województwo adresu siedziby/głównego miejsca wykonywania działalności gospodarczej dewelopera',
            'Powiat adresu siedziby/głównego miejsca wykonywania działalności gospodarczej dewelopera',
            'Gmina adresu siedziby/głównego miejsca wykonywania działalności gospodarczej dewelopera',
            'Miejscowość adresu siedziby/głównego miejsca wykonywania działalności gospodarczej dewelopera',
            'Ulica adresu siedziby/głównego miejsca wykonywania działalności gospodarczej dewelopera',
            'Nr nieruchomości adresu siedziby/głównego miejsca wykonywania działalności gospodarczej dewelopera',
            'Nr lokalu adresu siedziby/głównego miejsca wykonywania działalności gospodarczej dewelopera',
            'Kod pocztowy adresu siedziby/głównego miejsca wykonywania działalności gospodarczej dewelopera',

            'Województwo adresu lokalu, w którym prowadzona jest sprzedaż',
            'Powiat adresu lokalu, w którym prowadzona jest sprzedaż',
            'Gmina adresu lokalu, w którym prowadzona jest sprzedaż',
            'Miejscowość adresu lokalu, w którym prowadzona jest sprzedaż',
            'Ulica adresu lokalu, w którym prowadzona jest sprzedaż',
            'Nr nieruchomości adresu lokalu, w którym prowadzona jest sprzedaż',
            'Nr lokalu adresu lokalu, w którym prowadzona jest sprzedaż',
            'Kod pocztowy adresu lokalu, w którym prowadzona jest sprzedaż',
            'Dodatkowe lokalizacje, w których prowadzona jest sprzedaż',
            'Sposób kontaktu nabywcy z deweloperem',

            'Województwo lokalizacji przedsięwzięcia deweloperskiego lub zadania inwestycyjnego',
            'Powiat lokalizacji przedsięwzięcia deweloperskiego lub zadania inwestycyjnego',
            'Gmina lokalizacji przedsięwzięcia deweloperskiego lub zadania inwestycyjnego',
            'Miejscowość lokalizacji przedsięwzięcia deweloperskiego lub zadania inwestycyjnego',
            'Ulica lokalizacji przedsięwzięcia deweloperskiego lub zadania inwestycyjnego',
            'Nr nieruchomości lokalizacji przedsięwzięcia deweloperskiego lub zadania inwestycyjnego',
            'Kod pocztowy lokalizacji przedsięwzięcia deweloperskiego lub zadania inwestycyjnego',

            'Rodzaj nieruchomości: lokal mieszkalny, dom jednorodzinny',
            'Nr lokalu lub domu jednorodzinnego nadany przez dewelopera',

            'Cena m 2 powierzchni użytkowej lokalu mieszkalnego / domu jednorodzinnego [zł]',
            'Data od której cena obowiązuje cena m 2 powierzchni użytkowej lokalu mieszkalnego / domu jednorodzinnego',
            'Cena lokalu mieszkalnego lub domu jednorodzinnego będących przedmiotem umowy stanowiąca iloczyn ceny m2 oraz powierzchni [zł]',
            'Data od której cena obowiązuje cena lokalu mieszkalnego lub domu jednorodzinnego będących przedmiotem umowy stanowiąca iloczyn ceny m2 oraz powierzchni',
            'Cena lokalu mieszkalnego lub domu jednorodzinnego uwzględniająca cenę lokalu stanowiącą iloczyn powierzchni oraz metrażu i innych składowych ceny, o których mowa w art. 19a ust. 1 pkt 1), 2) lub 3) [zł]',
            'Data od której obowiązuje cena lokalu mieszkalnego lub domu jednorodzinnego uwzględniająca cenę lokalu stanowiącą iloczyn powierzchni oraz metrażu i innych składowych ceny, o których mowa w art. 19a ust. 1 pkt 1), 2) lub 3)',

            'Rodzaj części nieruchomości będących przedmiotem umowy',
            'Oznaczenie części nieruchomości nadane przez dewelopera',
            'Cena części nieruchomości, będących przedmiotem umowy [zł]',
            'Data od której obowiązuje cena części nieruchomości, będących przedmiotem umowy',

            // Przynależności
            'Rodzaj pomieszczeń przynależnych, o których mowa w art. 2 ust. 4 ustawy z dnia 24 czerwca 1994 r. o własności lokali',
            'Oznaczenie pomieszczeń przynależnych, o których mowa w art. 2 ust. 4 ustawy z dnia 24 czerwca 1994 r. o własności lokali',
            'Wyszczególnienie cen pomieszczeń przynależnych, o których mowa w art. 2 ust. 4 ustawy z dnia 24 czerwca 1994 r. o własności lokali [zł]',
            // ★ ZMIANA: nowa kolumna
            'Wyszczególnienie cen za m2 pomieszczeń przynależnych [zł/m2]',
            'Data od której obowiązuje cena wyszczególnionych pomieszczeń przynależnych, o których mowa w art. 2 ust. 4 ustawy z dnia 24 czerwca 1994 r. o własności lokali',

            // Prawa niezbędne
            'Wyszczególnienie praw niezbędnych do korzystania z lokalu mieszkalnego lub domu jednorodzinnego',
            'Wartość praw niezbędnych do korzystania z lokalu mieszkalnego lub domu jednorodzinnego [zł]',
            'Data od której obowiązuje cena wartości praw niezbędnych do korzystania z lokalu mieszkalnego lub domu jednorodzinnego',

            // Inne świadczenia pieniężne
            'Wyszczególnienie rodzajów innych świadczeń pieniężnych, które nabywca zobowiązany jest spełnić na rzecz dewelopera w wykonaniu umowy przenoszącej własność',
            'Wartość innych świadczeń pieniężnych, które nabywca zobowiązany jest spełnić na rzecz dewelopera w wykonaniu umowy przenoszącej własność [zł]',
            'Data od której obowiązuje cena wartości innych świadczeń pieniężnych, które nabywca zobowiązany jest spełnić na rzecz dewelopera w wykonaniu umowy przenoszącej własność',

            'Adres strony internetowej, pod którym dostępny jest prospekt informacyjny',
        ];

        $today       = date('Y-m-d', current_time('timestamp'));
        $file_daily  = $dir . self::FILENAME_PREFIX . $today . '.csv';
        $file_latest = $dir . self::FILENAME_LATEST;

        $q = new \WP_Query([
            'post_type'      => 'lokale',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ]);

        $fh = fopen($file_daily, 'w');
        if (!$fh) return new \WP_Error('open_failed', 'Nie udało się otworzyć pliku do zapisu.');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $columns, ',');

        $opt = class_exists('DGE_Settings') ? DGE_Settings::get() : [];

        while ($q->have_posts()) {
            $q->the_post();
            $post_id = get_the_ID();

            // ACF/meta helper
            $acf = function_exists('get_field')
                ? function ($k) use ($post_id) {
                    $v = get_field($k, $post_id);
                    return is_scalar($v) ? $v : '';
                }
                : function ($k) use ($post_id) {
                    return get_post_meta($post_id, $k, true);
                };

            /* ====== inwestycja (taxonomy: inwestycje) ====== */
            $inv_term = null;
            $inv_terms = get_the_terms($post_id, 'inwestycje');
            if (!is_wp_error($inv_terms) && !empty($inv_terms)) $inv_term = array_shift($inv_terms);

            $term_acf = function ($field, $default = '') use ($inv_term) {
                if (!$inv_term) return $default;
                $ctx1 = "{$inv_term->taxonomy}_{$inv_term->term_id}";
                if (function_exists('get_field')) {
                    $v = get_field($field, $ctx1);
                    if ($v !== null && $v !== '') return $v;
                    $v = get_field($field, "term_{$inv_term->term_id}");
                    return $v !== null ? $v : $default;
                }
                $v = get_term_meta($inv_term->term_id, $field, true);
                return ($v !== '') ? $v : $default;
            };

            /* ====== Deweloper (global) ====== */
            $dev = wp_parse_args($opt, [
                'dev_name' => '',
                'dev_legal_form' => '',
                'dev_krs' => '',
                'dev_ceidg' => '',
                'dev_nip' => '',
                'dev_regon' => '',
                'dev_phone' => '',
                'dev_email' => '',
                'dev_fax' => '',
                'dev_www' => '',
                'dev_addr_woj' => '',
                'dev_addr_powiat' => '',
                'dev_addr_gmina' => '',
                'dev_addr_city' => '',
                'dev_addr_street' => '',
                'dev_addr_no' => '',
                'dev_addr_local' => '',
                'dev_addr_zip' => '',
            ]);

            /* ====== Sprzedaż + przedsięwzięcie (z inwestycji) ====== */
            // sprzedaż
            $sales_woj    = $term_acf('sales_woj');
            $sales_powiat = $term_acf('sales_powiat');
            $sales_gmina  = $term_acf('sales_gmina');
            $sales_city   = $term_acf('sales_city');
            $sales_street = $term_acf('sales_street');
            $sales_no     = $term_acf('sales_no');
            $sales_local  = $term_acf('sales_local');
            $sales_zip    = $term_acf('sales_zip');
            $sales_extra  = $term_acf('sales_extra_locations');
            $contact_method = $term_acf('contact_method');

            // przedsięwzięcie
            $proj_woj    = $term_acf('proj_woj');
            $proj_powiat = $term_acf('proj_powiat');
            $proj_gmina  = $term_acf('proj_gmina');
            $proj_city   = $term_acf('proj_city');
            $proj_street = $term_acf('proj_street');
            $proj_no     = $term_acf('proj_no');
            $proj_zip    = $term_acf('proj_zip');

            // prospekt
            $prospekt_url = $term_acf('prospekt_url');

            /* ====== Rodzaj, nazwa/oznaczenie lokalu, ceny ====== */
            $typ_slugs   = wp_get_post_terms($post_id, 'typ-lokalu', ['fields' => 'slugs']);
            $rodzaj_nier = self::map_property_type($typ_slugs);

            // „Nr lokalu …” = dokładnie tytuł CPT
            $nr_lokalu = get_the_title($post_id);

            $cena_m2      = self::num($acf('current_price_per_m2'));
            $cena_m2_data = $acf('price_valid_from') ?: '';

            $pow_m2       = self::num($acf('powierzchnia') !== '' ? $acf('powierzchnia') : $acf('metraz'));

            $cena_iloczyn      = ($cena_m2 !== '' && $pow_m2 !== '') ? number_format((float)$cena_m2 * (float)$pow_m2, 2, '.', '') : '';
            $cena_iloczyn_data = $cena_m2_data ?: '';

            $cena_laczna      = self::num($acf('current_price'));
            $cena_laczna_data = $acf('price_valid_from') ?: '';

            /* ====== Części nieruchomości (puste/x) ====== */
            $czesc_rodzaj = '';
            $czesc_ozn    = '';
            $czesc_cena   = '';
            $czesc_data   = '';

            /* ====== PRZYNALEŻNOŚCI – z accessory_unit_ids ====== */
            $acc_kind = [];     // rodzaje
            $acc_label = [];    // oznaczenia (tytuły CPT)
            $acc_price = [];    // ceny
            $acc_price_m2 = []; // ★ ZMIANA: ceny za m2
            $acc_date  = [];    // daty

            $acc_ids = function_exists('get_field')
                ? get_field('accessory_unit_ids', $post_id, false)
                : get_post_meta($post_id, 'accessory_unit_ids', true);
            if (!is_array($acc_ids)) $acc_ids = (array)$acc_ids;

            foreach ($acc_ids as $aid) {
                $aid = (int)$aid;
                if ($aid <= 0) continue;

                // rodzaj (nazwy terminów tax 'typ-lokalu')
                $a_terms_names = wp_get_post_terms($aid, 'typ-lokalu', ['fields' => 'names']);
                $rodzaj = (!is_wp_error($a_terms_names) && !empty($a_terms_names)) ? implode('|', $a_terms_names) : '';

                // oznaczenie = tytuł CPT przynależności
                $ozn = get_the_title($aid);

                // cena
                $a_cena_raw = get_post_meta($aid, 'current_price', true);
                $a_cena     = ($a_cena_raw === '' || $a_cena_raw === null)
                    ? ''
                    : number_format((float) str_replace(',', '.', str_replace(["\xC2\xA0", ' '], '', $a_cena_raw)), 2, '.', '');

                // ★ ZMIANA: cena za m2
                $a_cena_m2_raw = get_post_meta($aid, 'current_price_per_m2', true);
                $a_cena_m2     = ($a_cena_m2_raw === '' || $a_cena_m2_raw === null)
                    ? ''
                    : number_format((float) str_replace(',', '.', str_replace(["\xC2\xA0", ' '], '', $a_cena_m2_raw)), 2, '.', '');

                // data obowiązywania
                $a_data_raw = get_post_meta($aid, 'price_valid_from', true);
                if ($a_data_raw && preg_match('/^\d{4}-\d{2}-\d{2}$/', $a_data_raw)) {
                    $a_data = $a_data_raw;
                } else {
                    $a_data = $a_data_raw ? date('Y-m-d', strtotime($a_data_raw)) : '';
                }

                if ($rodzaj !== '')    $acc_kind[]     = $rodzaj;
                if ($ozn    !== '')    $acc_label[]    = $ozn;
                if ($a_cena !== '')    $acc_price[]    = $a_cena;
                if ($a_cena_m2 !== '') $acc_price_m2[] = $a_cena_m2; // ★ ZMIANA
                if ($a_data !== '')    $acc_date[]     = $a_data;
            }

            /* ====== Prawa/Świadczenia (z inwestycji) ====== */
            $rights_desc = $term_acf('rights_desc');
            $rights_val  = self::dec_or_empty(self::num($term_acf('rights_value')));
            $rights_date = self::date_or_empty($term_acf('rights_date'));

            $other_desc  = $term_acf('other_payments_desc');
            $other_val   = self::dec_or_empty(self::num($term_acf('other_payments_value')));
            $other_date  = self::date_or_empty($term_acf('other_payments_date'));

            /* ====== Wiersz CSV ====== */
            $row = [
                // Deweloper
                $dev['dev_name'],
                $dev['dev_legal_form'],
                $dev['dev_krs'],
                $dev['dev_ceidg'],
                $dev['dev_nip'],
                $dev['dev_regon'],
                $dev['dev_phone'],
                $dev['dev_email'],
                $dev['dev_fax'],
                $dev['dev_www'],
                $dev['dev_addr_woj'],
                $dev['dev_addr_powiat'],
                $dev['dev_addr_gmina'],
                $dev['dev_addr_city'],
                $dev['dev_addr_street'],
                $dev['dev_addr_no'],
                $dev['dev_addr_local'],
                $dev['dev_addr_zip'],

                // Sprzedaż
                $sales_woj,
                $sales_powiat,
                $sales_gmina,
                $sales_city,
                $sales_street,
                $sales_no,
                $sales_local,
                $sales_zip,
                $sales_extra,
                $contact_method,

                // Przedsięwzięcie
                $proj_woj,
                $proj_powiat,
                $proj_gmina,
                $proj_city,
                $proj_street,
                $proj_no,
                $proj_zip,

                // Rodzaj + numer (nazwa lokalu = tytuł CPT)
                $rodzaj_nier,
                $nr_lokalu,

                // Ceny
                self::dec_or_empty($cena_m2),
                self::date_or_empty($cena_m2_data),

                self::dec_or_empty($cena_iloczyn),
                self::date_or_empty($cena_iloczyn_data),

                self::dec_or_empty($cena_laczna),
                self::date_or_empty($cena_laczna_data),

                // Części nieruchomości
                '',
                '',
                '',
                '',

                // Przynależności
                self::join_or_empty($acc_kind),
                self::join_or_empty($acc_label),
                self::join_or_empty($acc_price),
                // ★ ZMIANA: nowa kolumna (cena za m2)
                self::join_or_empty($acc_price_m2),
                self::join_or_empty($acc_date),

                // Prawa
                $rights_desc,
                $rights_val,
                $rights_date,

                // Inne świadczenia
                $other_desc,
                $other_val,
                $other_date,

                // Prospekt
                $prospekt_url,
            ];

            $row = array_map([__CLASS__, 'sanitize_csv'], $row);
            $row = array_map([__CLASS__, 'x_if_empty'], $row);

            fputcsv($fh, $row, ',');
        }
        wp_reset_postdata();

        fclose($fh);
        @copy($file_daily, $file_latest);
        @chmod($file_daily, 0644);
        @chmod($file_latest, 0644);

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[Dane_Gov_Exporter] Zapisano: ' . $file_latest);
        }
        return $file_latest;
    }

    /* ===================== Helpers ===================== */
    private static function map_property_type($slugs)
    {
        if (is_wp_error($slugs) || empty($slugs)) return 'lokal mieszkalny';
        foreach ((array)$slugs as $s) {
            $s = sanitize_title($s);
            if (in_array($s, ['dom', 'dom-jednorodzinny', 'jednorodzinny'], true)) return 'dom jednorodzinny';
            if (in_array($s, ['lokal', 'mieszkanie', 'lokal-mieszkalny'], true)) return 'lokal mieszkalny';
        }
        return 'lokal mieszkalny';
    }
    private static function num($v)
    {
        if ($v === '' || $v === null) return '';
        $v = str_replace(["\xC2\xA0", ' '], '', (string)$v);
        $v = str_replace(',', '.', $v);
        return is_numeric($v) ? (float)$v : '';
    }
    private static function dec_or_empty($v)
    {
        if ($v === '' || $v === null) return '';
        return number_format((float)$v, 2, '.', '');
    }
    private static function date_or_empty($v)
    {
        if (!$v) return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        $t = strtotime($v);
        return $t ? date('Y-m-d', $t) : '';
    }
    private static function join_or_empty($arr)
    {
        if (!is_array($arr)) return '';
        $arr = array_filter(array_map('trim', $arr), function ($v) {
            return $v !== '';
        });
        return empty($arr) ? '' : implode(' | ', $arr);
    }
    private static function sanitize_csv($v)
    {
        if ($v === null) return '';
        $v = (string)$v;
        $v = preg_replace('/\r\n|\r|\n/', ' ', $v);
        return trim($v);
    }
    /** Puste → 'x' */
    private static function x_if_empty($v)
    {
        $v = (string)$v;
        return ($v === '' ? 'x' : $v);
    }
}
new Dane_Gov_Exporter();

/* ===================== Panel admin: „Dane gov” ===================== */
add_action('admin_menu', function () {
    add_menu_page(
        __('Dane gov', 'dge'),
        __('Dane gov', 'dge'),
        'manage_options',
        DGE_MENU_SLUG,
        function () {
            echo '<div class="wrap"><h1>Dane gov</h1><p>Skorzystaj z podstron: <strong>Dane dewelopera</strong> i <strong>Eksport</strong>.</p></div>';
        },
        'dashicons-database-export',
        58
    );
    add_submenu_page(
        DGE_MENU_SLUG,
        __('Dane dewelopera', 'dge'),
        __('Dane dewelopera', 'dge'),
        'manage_options',
        DGE_SUB_DEVELOPER_SLUG,
        ['DGE_Settings', 'render_page']
    );
    add_submenu_page(
        DGE_MENU_SLUG,
        __('Eksport', 'dge'),
        __('Eksport', 'dge'),
        'manage_options',
        DGE_SUB_EXPORT_SLUG,
        function () {
            if (isset($_POST['dge_do_export'])) {
                $exp = new Dane_Gov_Exporter();
                $res = $exp->run_export();
                if (is_wp_error($res)) {
                    echo '<div class="error"><p><strong>Błąd:</strong> ' . esc_html($res->get_error_message()) . '</p></div>';
                } else {
                    echo '<div class="updated"><p>Eksport zapisany: <code>' . esc_html($res) . '</code></p></div>';
                }
            }
?>
        <div class="wrap">
            <h1>Eksport lokali do dane.gov.pl</h1>
            <form method="post">
                <?php submit_button('Eksportuj teraz', 'primary', 'dge_do_export'); ?>
            </form>
            <p>Eksport dzienny uruchamia się automatycznie o 02:00. Pliki znajdziesz w katalogu <code>/dane/</code> w głównym
                katalogu WordPressa.</p>
        </div>
<?php
        }
    );
});
