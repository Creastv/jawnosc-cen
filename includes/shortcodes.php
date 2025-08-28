<?php
if (!defined('ABSPATH')) exit;

class DGE_Shortcodes
{
    public static function register()
    {
        add_shortcode('lokal_price',   [__CLASS__, 'sc_price']);
        add_shortcode('lokal_price_m2', [__CLASS__, 'sc_price_m2']);
    }

    /* ------------------ Shortcodes ------------------ */

    /**
     * [lokal_price id="123" decimals="0" thousands_sep=" " decimal_sep="," currency="zł" show_currency="1" raw="0" empty="—"]
     */
    public static function sc_price($atts, $content = '')
    {
        $a = shortcode_atts([
            'id'            => 0,
            'decimals'      => 0,
            'thousands_sep' => ' ',
            'decimal_sep'   => ',',
            'currency'      => 'zł',
            'show_currency' => '1',
            'raw'           => '0',   // 1 = zwróć gołą liczbę (float/string) bez formatowania
            'empty'         => '—',
        ], $atts, 'lokal_price');

        $post_id = self::resolve_post_id($a['id']);
        if (!$post_id) return esc_html($a['empty']);

        // Szanuj ACF: "Wyświetlać cenę"
        $show_price = self::get_meta($post_id, 'wyswietlac_cene');
        if ($show_price !== '' && (string)$show_price === '0') {
            return esc_html($a['empty']);
        }

        $val = self::get_meta($post_id, 'current_price');
        $num = self::to_float($val);
        if ($num === '') return esc_html($a['empty']);

        if ($a['raw'] === '1') {
            return esc_html((string)$num);
        }

        $formatted = self::format_number($num, (int)$a['decimals'], $a['decimal_sep'], $a['thousands_sep']);
        $out = apply_filters('dge_format_price', $formatted, $num, $post_id);
        if ($a['show_currency'] === '1' && $a['currency'] !== '') {
            $out .= '&nbsp;' . esc_html($a['currency']);
        }
        return $out;
    }

    /**
     * [lokal_price_m2 id="123" decimals="2" thousands_sep=" " decimal_sep="," currency="zł/m²" show_currency="1" raw="0" empty="—"]
     */
    public static function sc_price_m2($atts, $content = '')
    {
        $a = shortcode_atts([
            'id'            => 0,
            'decimals'      => 2,
            'thousands_sep' => ' ',
            'decimal_sep'   => ',',
            'currency'      => 'zł/m²',
            'show_currency' => '1',
            'raw'           => '0',
            'empty'         => '—',
        ], $atts, 'lokal_price_m2');

        $post_id = self::resolve_post_id($a['id']);
        if (!$post_id) return esc_html($a['empty']);

        $val = self::get_meta($post_id, 'current_price_per_m2');
        $num = self::to_float($val);
        if ($num === '') return esc_html($a['empty']);

        if ($a['raw'] === '1') {
            return esc_html((string)$num);
        }

        $formatted = self::format_number($num, (int)$a['decimals'], $a['decimal_sep'], $a['thousands_sep']);
        $out = apply_filters('dge_format_price_m2', $formatted, $num, $post_id);
        if ($a['show_currency'] === '1' && $a['currency'] !== '') {
            $out .= '&nbsp;' . esc_html($a['currency']);
        }
        return $out;
    }

    /* ------------------ Helpers ------------------ */

    /** Ustal ID wpisu: atrybut id albo bieżący global $post */
    private static function resolve_post_id($maybe_id)
    {
        $maybe_id = (int)$maybe_id;
        if ($maybe_id > 0) return $maybe_id;
        global $post;
        return isset($post->ID) ? (int)$post->ID : 0;
    }

    /** Pobierz meta/ACF; zwraca string ('' jeśli brak/array) */
    private static function get_meta($post_id, $key)
    {
        if (function_exists('get_field')) {
            $v = get_field($key, $post_id);
            if (is_scalar($v)) return (string)$v;
        }
        $v = get_post_meta($post_id, $key, true);
        return is_scalar($v) ? (string)$v : '';
    }

    /** Normalizacja liczby (usuń spacje/NBSP, przecinki → kropki). Zwraca float albo '' */
    private static function to_float($v)
    {
        if ($v === '' || $v === null) return '';
        $s = (string)$v;
        $s = str_replace(["\xC2\xA0", ' '], '', $s); // NBSP i zwykłe spacje
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float)$s : '';
    }

    /** Format liczby z własnymi separatorami */
    private static function format_number($num, $decimals = 2, $dec_sep = ',', $thousands_sep = ' ')
    {
        // number_format przyjmuje kropkę jako decimal separator dopiero w 3- i 4-argumentowej wersji
        $decimals = max(0, (int)$decimals);
        return number_format((float)$num, $decimals, $dec_sep, $thousands_sep);
    }
}

// auto-rejestracja
add_action('init', ['DGE_Shortcodes', 'register']);