<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [lokal_accessories_list id="123" class=""]
 * Tabela inline: Nazwa | Cena | Cena za m² | Powierzchnia
 * - „Przynależności” to inne wpisy CPT 'lokale', powiązane meta 'accessory_unit_ids' (tablica ID).
 */
class DGE_Shortcode_Accessories_List
{
    public static function register()
    {
        add_shortcode('lokal_accessories_list', [__CLASS__, 'render']);
    }

    public static function render($atts)
    {
        $a = shortcode_atts([
            'id'    => 0,
            'class' => '', // dodatkowe klasy na wrapperze
        ], $atts, 'lokal_accessories_list');

        $post_id = self::resolve_post_id($a['id']);
        if (!$post_id) return '';

        // Pobierz listę przynależności z meta 'accessory_unit_ids'
        $acc_ids = get_post_meta($post_id, 'accessory_unit_ids', true);
        if (!is_array($acc_ids)) $acc_ids = [];

        // Przefiltruj: unikalne, >0, opublikowane
        $acc_ids = array_values(array_filter(array_unique(array_map('intval', $acc_ids)), function ($id) {
            return $id > 0 && get_post_status($id) === 'publish';
        }));

        if (empty($acc_ids)) {
            // nic nie pokazujemy, jeśli nie ma przynależności
            return '';
        }

        ob_start();
?>
        <div class="dge-acc-list <?php echo esc_attr($a['class']); ?>">
            <table class="dge-acc-list-table" style="font-size:10px">
                <thead>
                    <tr>
                        <th>Nazwa</th>
                        <th>Cena</th>
                        <th>Cena za m²</th>
                        <th>Powierzchnia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($acc_ids as $aid):
                        $title       = get_the_title($aid);

                        $price_total = self::fmt_money(self::to_float(get_post_meta($aid, 'current_price', true)), 0);
                        $price_m2    = self::fmt_money(self::to_float(get_post_meta($aid, 'current_price_per_m2', true)), 2);

                        // Zmień 'metraz' na 'powierzchnia', jeśli tak nazywasz pole
                        $area_raw    = get_post_meta($aid, 'metraz', true);
                        if ($area_raw === '' || $area_raw === null) {
                            $area_raw = get_post_meta($aid, 'powierzchnia', true);
                        }
                        $area        = self::fmt_area(self::to_float($area_raw));
                    ?>
                        <tr>
                            <td><?php echo esc_html($title); ?></td>
                            <td><?php echo $price_total !== '' ? esc_html($price_total . ' zł') : '—'; ?></td>
                            <td><?php echo $price_m2    !== '' ? esc_html($price_m2    . ' zł/m²') : '—'; ?></td>
                            <td><?php echo $area        !== '' ? esc_html($area        . ' m²') : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <style>
            .dge-acc-list-table {
                width: 100%;
                border-collapse: collapse
            }

            .dge-acc-list-table th,
            .dge-acc-list-table td {
                padding: 10px;
                border-bottom: 1px solid #eee;
                text-align: left
            }

            .dge-acc-list-table th {
                font-weight: 600;
                white-space: nowrap;
                background: #fafafa
            }
        </style>
<?php
        return ob_get_clean();
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

    private static function fmt_money($val, $dec = 0)
    {
        if ($val === '' || $val === null) return '';
        return number_format((float)$val, max(0, (int)$dec), ',', ' ');
    }

    private static function fmt_area($val, $dec = 2)
    {
        if ($val === '' || $val === null) return '';
        return number_format((float)$val, max(0, (int)$dec), ',', ' ');
    }
}

add_action('init', ['DGE_Shortcode_Accessories_List', 'register']);
