<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [lokal_min_price id="123" days="30" show_date="1"
 *             decimals="0" decimal_sep="," thousands_sep=" " currency="zł"
 *             prefix="" suffix="" empty=""]
 *
 * Działanie:
 *  - Czyta repeater ACF: history_price (fields: price, time).
 *  - Szuka minimalnej ceny (w oknie ostatnich N dni; days="all" = cała historia).
 *  - Zwraca wartość TYLKO gdy min < current_price (bieżąca cena lokalu).
 *  - Jeśli brak danych lub min >= current → zwraca `empty` (domyślnie pusty string).
 */
class DGE_Shortcode_MinPrice
{
    public static function register()
    {
        add_shortcode('lokal_min_price', [__CLASS__, 'render']);
    }

    public static function render($atts)
    {
        $a = shortcode_atts([
            'id'            => 0,
            'days'          => '30',  // liczba lub "all"
            'show_date'     => '1',
            'decimals'      => 0,
            'decimal_sep'   => ',',
            'thousands_sep' => ' ',
            'currency'      => 'zł',
            'prefix'        => '',    // np. "min: "
            'suffix'        => '',    // np. " (promo)"
            'empty'         => '',    // co zwrócić, gdy brak wyniku
        ], $atts, 'lokal_min_price');

        $post_id = self::resolve_post_id($a['id']);
        if (!$post_id) return esc_html($a['empty']);

        // bieżąca cena
        $curr = self::to_float(get_post_meta($post_id, 'current_price', true));
        if ($curr === '') return esc_html($a['empty']);

        // okno czasu
        $use_all = strtolower(trim($a['days'])) === 'all';
        $now_ts  = current_time('timestamp');
        $from_ts = $use_all ? null : ($now_ts - (max(1, (int)$a['days']) * DAY_IN_SECONDS));

        // minimalna z historii
        $min_val = null;
        $min_dt  = '';

        $rows = [];
        if (function_exists('get_field')) {
            $acf_rows = get_field('history_price', $post_id);
            if (is_array($acf_rows)) $rows = $acf_rows;
        }

        foreach ($rows as $r) {
            $p = self::to_float($r['price'] ?? '');
            if ($p === '') continue;

            $ts = self::date_to_ts($r['time'] ?? '');
            if (!$use_all) {
                if ($ts === null) continue;               // bez daty pomiń w trybie okna
                if ($ts < $from_ts || $ts > $now_ts) continue;
            }
            if ($min_val === null || (float)$p < (float)$min_val) {
                $min_val = (float)$p;
                $min_dt  = self::normalize_iso_ymd($r['time'] ?? '');
            }
        }

        // nie znaleziono historii
        if ($min_val === null) return esc_html($a['empty']);

        // warunek: tylko jeśli min < current
        if (!self::is_less($min_val, (float)$curr)) {
            return esc_html($a['empty']);
        }

        // formatowanie
        $out = '';
        $out .= '<small style=" font-size: 14px; white-space: normal; font-weight: 200; line-height: 1.2; display: block;">';
        if ($a['prefix'] !== '') $out .= $a['prefix'];
        $out .= self::fmt($min_val, (int)$a['decimals'], $a['decimal_sep'], $a['thousands_sep'], $a['currency']);
        if ($a['show_date'] === '1' && $min_dt !== '') {
            $out .= ' (' . esc_html($min_dt) . ')';
        }
        if ($a['suffix'] !== '') $out .= $a['suffix'];
        $out .= '</small>';
        return $out;
    }

    /* ============ Helpers ============ */

    private static function resolve_post_id($maybe_id)
    {
        $maybe_id = (int)$maybe_id;
        if ($maybe_id > 0) return $maybe_id;
        global $post;
        return isset($post->ID) ? (int)$post->ID : 0;
    }

    /** '' → '' ; usuwa spacje/NBSP, przecinki → kropki; zwraca float|'' */
    private static function to_float($v)
    {
        if ($v === '' || $v === null) return '';
        $s = (string)$v;
        $s = str_replace(["\xC2\xA0", ' '], '', $s);
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float)$s : '';
    }

    /** Normalizacja YYYY-MM-DD z popularnych formatów; jeśli nie data → '' */
    private static function normalize_iso_ymd($v)
    {
        if ($v === null) return '';
        $v = trim((string)$v);
        if ($v === '') return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $v, $m)) return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        if (preg_match('/^(\d{2})[.\-\/](\d{2})[.\-\/](\d{4})$/', $v, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        $t = strtotime($v);
        return $t ? date('Y-m-d', $t) : '';
    }

    /** Na TS; jeśli nie data → null */
    private static function date_to_ts($v)
    {
        $iso = self::normalize_iso_ymd($v);
        if ($iso === '') return null;
        $t = strtotime($iso);
        return $t ?: null;
    }

    /** Porównanie z tolerancją */
    private static function is_less($a, $b, $eps = 0.00001)
    {
        return (($a + $eps) < $b);
    }

    /** Format liczby z walutą */
    private static function fmt($num, $dec, $dec_sep, $th_sep, $currency = '')
    {
        $s = number_format((float)$num, max(0, (int)$dec), $dec_sep, $th_sep);
        return $currency ? ($s . ' ' . $currency) : $s;
    }
}

add_action('init', ['DGE_Shortcode_MinPrice', 'register']);