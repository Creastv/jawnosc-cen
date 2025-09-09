<?php
if (!defined('ABSPATH')) exit;

/**
 * Eksporter dla dane.gov.pl
 * Rozszerzony o: proj_www (taksonomia), status (lokal), cena całkowita (lokal + przynależności).
 */
final class Dane_Gov_Exporter
{
    const CRON_HOOK = 'dane_gov_exporter_daily';

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

    public static function activate()
    {
        $dir = self::target_dir();
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
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
        if (is_array($res)) {
            \WP_CLI::success('Wygenerowane pliki:');
            foreach ($res as $term_slug => $paths) {
                \WP_CLI::line(sprintf(' [%s]', $term_slug));
                foreach ($paths as $p) \WP_CLI::line('  - ' . $p);
            }
        } else {
            \WP_CLI::success('Eksport zakończony.');
        }
    }

    /**
     * Główny eksport: osobny CSV dla każdej inwestycji (tax 'inwestycje').
     * - Tworzy dwie kopie: dzienną (z datą) i "latest" (nadpisywaną).
     * Zwraca tablicę: [ term_slug => [file_daily, file_latest], ... ].
     */
    public function run_export()
    {
        $dir = self::target_dir();

        if (!file_exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                return new \WP_Error('mkdir_failed', 'Nie udało się utworzyć katalogu: ' . $dir);
            }
            @file_put_contents($dir . 'index.html', '');
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            return new \WP_Error('no_write', 'Brak uprawnień do zapisu w katalogu: ' . $dir);
        }

        $terms = get_terms([
            'taxonomy'   => 'inwestycje',
            'hide_empty' => true,
        ]);
        if (is_wp_error($terms)) {
            return new \WP_Error('terms_error', 'Nie udało się pobrać inwestycji: ' . $terms->get_error_message());
        }

        $generated = [];
        $today = date('Y-m-d', current_time('timestamp'));

        foreach ($terms as $term) {
            $term_id   = (int) $term->term_id;
            $term_name = (string) $term->name;
            $term_slug = sanitize_title($term_name);

            /* --- 1) Skan postów tej inwestycji (max liczba przynależności) --- */
            $q_scan = new \WP_Query([
                'post_type'      => 'lokale',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
                'fields'         => 'ids',
                'tax_query'      => [
                    [
                        'taxonomy' => 'inwestycje',
                        'field'    => 'term_id',
                        'terms'    => [$term_id],
                    ],
                ],
            ]);

            if (!$q_scan->have_posts()) {
                wp_reset_postdata();
                continue;
            }

            $max_acc  = 0;
            $post_ids = [];
            foreach ($q_scan->posts as $pid) {
                $pid = (int) $pid;
                $post_ids[] = $pid;
                $acc_ids = function_exists('get_field')
                    ? get_field('accessory_unit_ids', $pid, false)
                    : get_post_meta($pid, 'accessory_unit_ids', true);
                if (!is_array($acc_ids)) $acc_ids = (array) $acc_ids;

                $acc_count = 0;
                foreach ($acc_ids as $aid) {
                    $aid = (int) $aid;
                    if ($aid > 0) $acc_count++;
                }
                if ($acc_count > $max_acc) $max_acc = $acc_count;
            }
            wp_reset_postdata();

            /* --- 2) Nagłówki CSV (z dodatkowymi kolumnami) --- */
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

                // Nazwa inwestycji (nazwa termu) + NOWA kolumna WWW inwestycji
                'Nazwa inwestycji',
                'Adres strony internetowej inwestycji',

                'Rodzaj nieruchomości: lokal mieszkalny, dom jednorodzinny',
                'Typ lokalu (taksonomia)',
                'Nr lokalu lub domu jednorodzinnego nadany przez dewelopera',
                'Status',

                // Powierzchnia lokalu
                'Powierzchnia lokalu [m2]',

                // UPROSZCZONE CENY (3 kolumny w tej kolejności)
                'Cena lokalu mieszkalnego lub domu jednorodzinnego będących przedmiotem umowy stanowiąca iloczyn ceny m2 oraz powierzchni [zł]',
                'Cena m 2 powierzchni użytkowej lokalu mieszkalnego / domu jednorodzinnego [zł]',
                'Data od której cena obowiązuje',

                // SUMA: lokal + wszystkie przynależności
                'Cena całkowita mieszkania i przynależności [zł]',
            ];

            // Dynamiczne kolumny przynależności (6 kolumn na każdą sztukę: +Powierzchnia)
            $max_for_term = max(1, $max_acc);
            for ($i = 1; $i <= $max_for_term; $i++) {
                $columns[] = "Rodzaj pomieszczenia przynależnego #$i";
                $columns[] = "Oznaczenie pomieszczenia przynależnego #$i";
                $columns[] = "Powierzchnia pomieszczenia przynależnego #$i [m2]";
                $columns[] = "Cena pomieszczenia przynależnego #$i [zł]";
                $columns[] = "Cena za m2 pomieszczenia przynależnego #$i [zł/m2]";
                $columns[] = "Data ceny pomieszczenia przynależnego #$i";
            }

            // Prawa / Świadczenia / Prospekt
            $columns = array_merge($columns, [
                'Wyszczególnienie praw niezbędnych do korzystania z lokalu mieszkalnego lub domu jednorodzinnego',
                'Wartość praw niezbędnych do korzystania z lokalu mieszkalnego lub domu jednorodzinnego [zł]',
                'Data od której obowiązuje cena wartości praw niezbędnych do korzystania z lokalu mieszkalnego lub domu jednorodzinnego',

                'Wyszczególnienie rodzajów innych świadczeń pieniężnych, które nabywca zobowiązany jest spełnić na rzecz dewelopera w wykonaniu umowy przenoszącej własność',
                'Wartość innych świadczeń pieniężnych, które nabywca zobowiązany jest spełnić na rzecz dewelopera w wykonaniu umowy przenoszącej własność [zł]',
                'Data od której obowiązuje cena wartości innych świadczeń pieniężnych, które nabywca zobowiązany jest spełnić na rzecz dewelopera w wykonaniu umowy przenoszącej własność',

                'Adres strony internetowej, pod którym dostępny jest prospekt informacyjny',
            ]);

            /* --- 3) Nazwy plików (kopie): dzienny + latest --- */
            $filename_daily  = 'dane-' . $term_slug . '-' . $today . '.csv';
            $filename_latest = 'dane-' . $term_slug . '.csv';

            $file_daily  = $dir . $filename_daily;
            $file_latest = $dir . $filename_latest;

            /* --- 4) Zapis do pliku DZIENNEGO --- */
            $fh = fopen($file_daily, 'w');
            if (!$fh) {
                continue;
            }
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, $columns, ',');

            // helper do pobierania pól z termu tej inwestycji
            $term_acf = function ($field, $default = '') use ($term) {
                $ctx1 = "{$term->taxonomy}_{$term->term_id}";
                if (function_exists('get_field')) {
                    $v = get_field($field, $ctx1);
                    if ($v !== null && $v !== '') return $v;
                    $v = get_field($field, "term_{$term->term_id}");
                    return $v !== null ? $v : $default;
                }
                $v = get_term_meta($term->term_id, $field, true);
                return ($v !== '') ? $v : $default;
            };

            foreach ($post_ids as $post_id) {
                // ACF/meta helper dla posta
                $acf = function_exists('get_field')
                    ? function ($k) use ($post_id) {
                        $v = get_field($k, $post_id);
                        return is_scalar($v) ? $v : '';
                    }
                    : function ($k) use ($post_id) {
                        return get_post_meta($post_id, $k, true);
                    };

                // nazwa inwestycji (nazwa termu) i WWW inwestycji (ACF: proj_www)
                $inv_name = $term_name;
                $proj_www = $term_acf('proj_www');

                /* ====== Deweloper (z ACF taksonomii inwestycje) ====== */
                $dev = [
                    'dev_name'        => $term_acf('company_developer_name'),
                    'dev_legal_form'  => $term_acf('company_legal_form'),
                    'dev_krs'         => $term_acf('company_krs'),
                    'dev_ceidg'       => $term_acf('company_ceidg'),
                    'dev_nip'         => $term_acf('company_nip'),
                    'dev_regon'       => $term_acf('company_regon'),
                    'dev_phone'       => $term_acf('company_phone'),
                    'dev_email'       => $term_acf('company_email'),
                    'dev_fax'         => $term_acf('company_fax'),
                    'dev_www'         => $term_acf('company_website'),

                    'dev_addr_woj'    => $term_acf('company_addr_woj'),
                    'dev_addr_powiat' => $term_acf('company_addr_powiat'),
                    'dev_addr_gmina'  => $term_acf('company_addr_gmina'),
                    'dev_addr_city'   => $term_acf('company_addr_city'),
                    'dev_addr_street' => $term_acf('company_addr_street'),
                    'dev_addr_no'     => $term_acf('company_addr_no'),
                    'dev_addr_local'  => $term_acf('company_addr_local'),
                    'dev_addr_zip'    => $term_acf('company_addr_zip'),
                ];

                /* ====== Sprzedaż + przedsięwzięcie (z inwestycji) ====== */
                $sales_woj      = $term_acf('sales_woj');
                $sales_powiat   = $term_acf('sales_powiat');
                $sales_gmina    = $term_acf('sales_gmina');
                $sales_city     = $term_acf('sales_city');
                $sales_street   = $term_acf('sales_street');
                $sales_no       = $term_acf('sales_no');
                $sales_local    = $term_acf('sales_local');
                $sales_zip      = $term_acf('sales_zip');
                $sales_extra    = $term_acf('sales_extra_locations');
                $contact_method = $term_acf('contact_method');

                $proj_woj    = $term_acf('proj_woj');
                $proj_powiat = $term_acf('proj_powiat');
                $proj_gmina  = $term_acf('proj_gmina');
                $proj_city   = $term_acf('proj_city');
                $proj_street = $term_acf('proj_street');
                $proj_no     = $term_acf('proj_no');
                $proj_zip    = $term_acf('proj_zip');

                $prospekt_url = $term_acf('prospekt_url');

                /* ====== Rodzaj, typ lokalu, nazwa/oznaczenie lokalu, ceny i powierzchnia ====== */
                $typ_slugs   = wp_get_post_terms($post_id, 'typ-lokalu', ['fields' => 'slugs']);
                $rodzaj_nier = self::map_property_type($typ_slugs);

                $typ_names_arr = wp_get_post_terms($post_id, 'typ-lokalu', ['fields' => 'names']);
                $typ_lokalu    = (!is_wp_error($typ_names_arr) && !empty($typ_names_arr)) ? implode('|', $typ_names_arr) : '';

                $nr_lokalu = get_the_title($post_id);
                $status    = (string) $acf('status');

                // Powierzchnia lokalu
                $pow_m2 = self::num($acf('area_total'));
                if ($pow_m2 === '') {
                    $pow_m2 = self::num($acf('powierzchnia') !== '' ? $acf('powierzchnia') : $acf('metraz'));
                }

                $cena_m2      = self::num($acf('current_price_per_m2'));
                $cena_m2_data = $acf('price_valid_from') ?: '';

                $cena_iloczyn = ($cena_m2 !== '' && $pow_m2 !== '')
                    ? number_format((float)$cena_m2 * (float)$pow_m2, 2, '.', '')
                    : '';

                /* ====== PRZYNALEŻNOŚCI – per pozycja ====== */
                $acc_rows = [];
                $acc_total_price = 0.0;

                $acc_ids = function_exists('get_field')
                    ? get_field('accessory_unit_ids', $post_id, false)
                    : get_post_meta($post_id, 'accessory_unit_ids', true);
                if (!is_array($acc_ids)) $acc_ids = (array) $acc_ids;

                foreach ($acc_ids as $aid) {
                    $aid = (int) $aid;
                    if ($aid <= 0) continue;

                    $a_terms_names = wp_get_post_terms($aid, 'typ-lokalu', ['fields' => 'names']);
                    $rodzaj = (!is_wp_error($a_terms_names) && !empty($a_terms_names)) ? implode('|', $a_terms_names) : '';

                    $ozn = get_the_title($aid);

                    // powierzchnia przynależności (area_total)
                    $a_area_raw = get_post_meta($aid, 'area_total', true);
                    $a_area = ($a_area_raw === '' || $a_area_raw === null)
                        ? ''
                        : number_format((float) str_replace(',', '.', str_replace(["\xC2\xA0", ' '], '', $a_area_raw)), 2, '.', '');

                    $a_cena_raw = get_post_meta($aid, 'current_price', true);
                    $a_cena     = ($a_cena_raw === '' || $a_cena_raw === null)
                        ? ''
                        : number_format((float) str_replace(',', '.', str_replace(["\xC2\xA0", ' '], '', $a_cena_raw)), 2, '.', '');

                    // do sumy całkowitej
                    if ($a_cena !== '') {
                        $acc_total_price += (float) $a_cena;
                    }

                    $a_cena_m2_raw = get_post_meta($aid, 'current_price_per_m2', true);
                    $a_cena_m2     = ($a_cena_m2_raw === '' || $a_cena_m2_raw === null)
                        ? ''
                        : number_format((float) str_replace(',', '.', str_replace(["\xC2\xA0", ' '], '', $a_cena_m2_raw)), 2, '.', '');

                    $a_data_raw = get_post_meta($aid, 'price_valid_from', true);
                    if ($a_data_raw && preg_match('/^\d{4}-\d{2}-\d{2}$/', $a_data_raw)) {
                        $a_data = $a_data_raw;
                    } else {
                        $a_data = $a_data_raw ? date('Y-m-d', strtotime($a_data_raw)) : '';
                    }

                    $acc_rows[] = [$rodzaj, $ozn, $a_area, $a_cena, $a_cena_m2, $a_data];
                }

                // spłaszcz do komórek + padding do $max_for_term (6 kolumn na pozycję)
                $acc_cells = [];
                $acc_count = count($acc_rows);
                for ($i = 0; $i < $max_for_term; $i++) {
                    if ($i < $acc_count) {
                        $acc_cells = array_merge($acc_cells, $acc_rows[$i]);
                    } else {
                        $acc_cells = array_merge($acc_cells, ['', '', '', '', '', '']);
                    }
                }

                /* ====== Prawa/Świadczenia (z inwestycji) ====== */
                $rights_desc = $term_acf('rights_desc');
                $rights_val  = self::dec_or_empty(self::num($term_acf('rights_value')));
                $rights_date = self::date_or_empty($term_acf('rights_date'));

                $other_desc  = $term_acf('other_payments_desc');
                $other_val   = self::dec_or_empty(self::num($term_acf('other_payments_value')));
                $other_date  = self::date_or_empty($term_acf('other_payments_date'));

                /* ====== Cena całkowita = cena lokalu (iloczyn) + suma przynależności ====== */
                $cena_lokalu_float = ($cena_iloczyn !== '') ? (float) $cena_iloczyn : 0.0;
                $cena_calkowita = number_format($cena_lokalu_float + $acc_total_price, 2, '.', '');

                /* ====== Wiersz CSV ====== */
                $row = [
                    // Deweloper (z termu inwestycji)
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

                    // Nazwa inwestycji + WWW inwestycji
                    $inv_name,
                    $proj_www,

                    // Rodzaj + Typ lokalu + nr lokalu + Status
                    $rodzaj_nier,
                    $typ_lokalu,
                    $nr_lokalu,
                    $status,

                    // Powierzchnia lokalu
                    self::dec_or_empty($pow_m2),

                    // *** CENY (3 kolumny) ***
                    self::dec_or_empty($cena_iloczyn),   // 1) cena = iloczyn (m2 * metraż)
                    self::dec_or_empty($cena_m2),        // 2) cena m2
                    self::date_or_empty($cena_m2_data),  // 3) data od której cena obowiązuje

                    // *** Cena całkowita (lokal + przynależności) ***
                    self::dec_or_empty($cena_calkowita),
                ];

                // Przynależności (dynamiczne: + powierzchnia)
                $row = array_merge($row, $acc_cells);

                // Prawa / Inne świadczenia / Prospekt
                $row = array_merge($row, [
                    $rights_desc,
                    $rights_val,
                    $rights_date,

                    $other_desc,
                    $other_val,
                    $other_date,

                    $prospekt_url,
                ]);

                $row = array_map([__CLASS__, 'sanitize_csv'], $row);
                $row = array_map([__CLASS__, 'x_if_empty'], $row);

                fputcsv($fh, $row, ',');
            }

            fclose($fh);

            // 5) Kopia "latest"
            @copy($file_daily, $file_latest);
            @chmod($file_daily, 0644);
            @chmod($file_latest, 0644);

            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log(sprintf(
                    '[Dane_Gov_Exporter] Zapisano: %s (daily) i %s (latest) | inwestycja: %s | max przynależności: %d',
                    $file_daily,
                    $file_latest,
                    $term_slug,
                    $max_for_term
                ));
            }

            $generated[$term_slug] = [$file_daily, $file_latest];
        }

        return $generated;
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
        $v = str_replace(["\xC2\xA0", ' '], '', (string) $v);
        $v = str_replace(',', '.', $v);
        return is_numeric($v) ? (float) $v : '';
    }
    private static function dec_or_empty($v)
    {
        if ($v === '' || $v === null) return '';
        return number_format((float) $v, 2, '.', '');
    }
    private static function date_or_empty($v)
    {
        if (!$v) return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        $t = strtotime($v);
        return $t ? date('Y-m-d', $t) : '';
    }
    private static function sanitize_csv($v)
    {
        if ($v === null) return '';
        $v = (string) $v;
        $v = preg_replace('/\r\n|\r|\n/', ' ', $v);
        return trim($v);
    }
    /** Puste → 'x' */
    private static function x_if_empty($v)
    {
        $v = (string) $v;
        return ($v === '' ? 'x' : $v);
    }
}
new Dane_Gov_Exporter();
