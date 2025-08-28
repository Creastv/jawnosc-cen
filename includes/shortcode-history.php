<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [lokal_history id="123" label="Historia cen" class=""]
 * - Renderuje przycisk; modal renderowany globalnie w wp_footer (na dole strony).
 * - Dane (cena, data, cena za m²) ładowane AJAX-em z repeatera ACF: history_price.
 * - Jeśli lokal NIE ma aktualnej ceny i NIE ma historii, shortcode nie renderuje nic.
 */
class DGE_Shortcode_History
{
    const ACTION     = 'dge_fetch_price_history';
    const NONCE_NAME = 'dge_hist_nonce';

    private static $assets_enqueued = false;
    private static $modal_printed   = false;

    public static function register()
    {
        add_shortcode('lokal_history', [__CLASS__, 'render']);

        // AJAX (dla zalogowanych i niezalogowanych)
        add_action('wp_ajax_' . self::ACTION,        [__CLASS__, 'ajax_fetch']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [__CLASS__, 'ajax_fetch']);

        // Zawsze drukuj modal w stopce (raz)
        add_action('wp_footer', [__CLASS__, 'print_modal_at_footer'], 1000);

        // Zawsze zarejestruj i załaduj assets na froncie
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** Ładujemy CSS/JS na każdej stronie frontu (żeby działało także dla treści z AJAX-a) */
    public static function enqueue_assets()
    {
        if (is_admin() || self::$assets_enqueued) return;

        $ver  = '1.0.3';
        $base = plugin_dir_url(dirname(__FILE__));

        // CSS
        wp_register_style('dge-history-css', $base . 'assets/css/dge-history.css', [], $ver);

        // JS
        wp_register_script('dge-history-js', $base . 'assets/js/dge-history.js', ['jquery'], $ver, true);
        wp_localize_script('dge-history-js', 'DGE_HIST', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);

        // Enqueue na froncie zawsze (lekki skrypt / CSS)
        wp_enqueue_style('dge-history-css');
        wp_enqueue_script('dge-history-js');

        // Fallback inline, jeśli nie masz plików w /assets
        self::maybe_inline_assets();

        self::$assets_enqueued = true;
    }

    /** Shortcode: renderuje wyłącznie trigger */
    public static function render($atts, $content = '')
    {
        $a = shortcode_atts([
            'id'    => 0,
            'label' => 'Historia cen',
            'class' => '',
        ], $atts, 'lokal_history');

        $post_id = self::resolve_post_id($a['id']);
        if (!$post_id) return '';

        // sprawdź czy są jakiekolwiek dane (aktualna cena lub historia)
        $has_current = self::has_current_price($post_id);
        $has_history = self::has_history_rows($post_id);

        if (!$has_current && !$has_history) {
            return '';
        }

        // nonce per post
        $nonce = wp_create_nonce(self::NONCE_NAME . '|' . $post_id);

        // przycisk z danymi
        ob_start(); ?>
        <a href="#" class="dge-hist-trigger <?php echo esc_attr($a['class']); ?>" data-post="<?php echo esc_attr($post_id); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>" aria-haspopup="dialog" aria-controls="dge-hist-modal">
            <?php echo esc_html($a['label']); ?>
        </a>
    <?php
        return ob_get_clean();
    }

    /** Modal w stopce (raz na stronę) — bez warunkowania na użycie shortcode’u */
    public static function print_modal_at_footer()
    {
        if (self::$modal_printed || is_admin()) return;
        self::$modal_printed = true; ?>
        <div id="dge-hist-modal" class="dge-hist-modal" role="dialog" aria-modal="true" aria-hidden="true">
            <div class="dge-hist-backdrop" data-close></div>
            <div class="dge-hist-dialog" role="document">
                <a href="#" class="dge-hist-close" title="Zamknij" aria-label="Zamknij" data-close>×</a>
                <div class="dge-hist-body">
                    <div class="dge-hist-loading">
                        <img src="<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/img/loading.gif'; ?>"
                            alt="ładowanie" />
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    public static function ajax_fetch()
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $nonce   = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        if ($post_id <= 0 || !wp_verify_nonce($nonce, self::NONCE_NAME . '|' . $post_id)) {
            wp_send_json_error(['message' => 'Błędny token bezpieczeństwa.'], 403);
        }

        $rows = [];
        if (function_exists('get_field')) {
            $acf_rows = get_field('history_price', $post_id);
            if (is_array($acf_rows)) $rows = $acf_rows;
        }

        // Wstaw „Aktualna cena” jako pierwszy wiersz (jeśli istnieje)
        $current_total = get_post_meta($post_id, 'current_price', true);
        $current_m2    = get_post_meta($post_id, 'current_price_per_m2', true);
        $current_date  = get_post_meta($post_id, 'price_valid_from', true);

        if ($current_total !== '' || $current_m2 !== '') {
            $label_date = self::normalize_iso_ymd($current_date);
            if ($label_date === '' || $label_date === $current_date) {
                $label_date = date('Y-m-d', current_time('timestamp'));
            }
            array_unshift($rows, [
                'price'        => $current_total,
                'time'         => 'aktualna cena (' . $label_date . ')',
                'price_per_m2' => $current_m2,
            ]);
        }

        ob_start(); ?>
        <h3>Historia cen</h3>
        <?php if (empty($rows)) : ?>
            <p>Brak danych historycznych.</p>
        <?php else : ?>
            <div class="dge-hist-table-wrap">
                <table class="dge-hist-table">
                    <thead>
                        <tr>
                            <th>Cena całkowita</th>
                            <th>Data</th>
                            <th>Cena za m²</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r) :
                            $price    = self::format_money(self::to_float($r['price'] ?? ''), 2);
                            $date     = self::format_date($r['time'] ?? '');
                            $price_m2 = self::format_money(self::to_float($r['price_per_m2'] ?? ''), 2);
                        ?>
                            <tr>
                                <td><?php echo $price    !== '' ? esc_html($price)    : '—'; ?></td>
                                <td><?php echo $date     !== '' ? esc_html($date)     : '—'; ?></td>
                                <td><?php echo $price_m2 !== '' ? esc_html($price_m2) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
<?php endif;
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /* ================= Helpers ================= */

    private static function resolve_post_id($maybe_id)
    {
        $maybe_id = (int)$maybe_id;
        if ($maybe_id > 0) return $maybe_id;
        global $post;
        return isset($post->ID) ? (int)$post->ID : 0;
    }

    /** Czy istnieje jakakolwiek „aktualna cena” (total lub m2) */
    private static function has_current_price($post_id)
    {
        $curr_total = get_post_meta($post_id, 'current_price', true);
        $curr_m2    = get_post_meta($post_id, 'current_price_per_m2', true);
        return (trim((string)$curr_total) !== '' || trim((string)$curr_m2) !== '');
    }

    /** Czy istnieją jakiekolwiek wiersze w repeaterze historii */
    private static function has_history_rows($post_id)
    {
        if (!function_exists('get_field')) return false;
        $rows = get_field('history_price', $post_id);
        return is_array($rows) && !empty($rows);
    }

    private static function to_float($v)
    {
        if ($v === '' || $v === null) return '';
        $s = trim((string)$v);
        $s = str_replace(["\xC2\xA0", ' '], '', $s);
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float)$s : '';
    }

    private static function format_money($num, $dec = 2)
    {
        if ($num === '' || $num === null) return '';
        return number_format((float)$num, $dec, ',', ' ') . ' zł';
    }

    private static function format_date($v)
    {
        if ($v === null) return '';
        $v = trim((string)$v);
        if ($v === '') return '';
        if (stripos($v, 'aktualna cena') !== false) return $v;
        return self::normalize_iso_ymd($v);
    }

    private static function normalize_iso_ymd($v)
    {
        if ($v === null) return '';
        $v = trim((string)$v);
        if ($v === '') return '';

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $v, $m))
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        if (preg_match('/^(\d{2})[.\-\/](\d{2})[.\-\/](\d{4})$/', $v, $m))
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m))
            return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);

        $t = strtotime($v);
        return $t ? date('Y-m-d', $t) : $v;
    }

    /**
     * Fallback inline dla CSS/JS (jeśli nie masz plików w /assets).
     * Usuń tę metodę i enqueue pliki, gdy dodasz /assets.
     */
    private static function maybe_inline_assets()
    {
        // CSS
        if (!wp_style_is('dge-history-css', 'enqueued')) {
            wp_register_style('dge-history-inline-css', false);
            wp_enqueue_style('dge-history-inline-css');
        }
        $css = '
.dge-hist-trigger{cursor:pointer;background:none;border:none;margin:10px 0;padding:0;display:inline-flex;gap:8px;align-items:center;font-weight:500;font-size:14px;}
.dge-hist-modal{position:fixed;inset:0;display:none;z-index:9999}
.dge-hist-modal[aria-hidden="false"]{display:block}
.dge-hist-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.5)}
.dge-hist-dialog{position:relative;max-width:720px;margin:6vh auto;background:#fff;padding:16px;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,.2);z-index:1}
.dge-hist-close{position:absolute;top:8px;right:10px;font-size:20px;line-height:1;background:transparent;border:0;cursor:pointer;text-decoration:none}
.dge-hist-loading{padding:24px;text-align:center}
.dge-hist-table-wrap{overflow:auto;max-height:70vh}
.dge-hist-table{width:100%;border-collapse:collapse}
.dge-hist-table th,.dge-hist-table td{padding:8px 10px;border-bottom:1px solid #eee;text-align:left;white-space:nowrap}
.dge-hist-table thead th{position:sticky;top:0;background:#fafafa;z-index:2}
';
        wp_add_inline_style('dge-history-css', $css);

        // JS
        if (!wp_script_is('dge-history-js', 'enqueued')) {
            wp_register_script('dge-history-js', false, ['jquery'], null, true);
            wp_enqueue_script('dge-history-js');
        }
        $js = '
(function($){
    function openModal($m){ $m.attr("aria-hidden","false"); $("body").addClass("dge-hist-open"); }
    function closeModal($m){ $m.attr("aria-hidden","true");  $("body").removeClass("dge-hist-open"); }

    function fetchHistory($modal, postId, nonce){
        var $body = $modal.find(".dge-hist-body");
        $body.html(\'<div class="dge-hist-loading">Ładowanie…</div>\');
        $.post(DGE_HIST.ajax_url, {
            action: "' . self::ACTION . '",
            post_id: postId,
            nonce: nonce
        }).done(function(resp){
            if(resp && resp.success && resp.data && resp.data.html){
                $body.html(resp.data.html);
            } else {
                $body.html("<p>Nie udało się pobrać danych.</p>");
            }
        }).fail(function(){
            $body.html("<p>Błąd połączenia.</p>");
        });
    }

    // Delegowany handler — działa także dla elementów doładowanych przez AJAX
    $(document).on("click", ".dge-hist-trigger", function(e){
        e.preventDefault();
        var $btn   = $(this);
        var $modal = $("#dge-hist-modal");
        var postId = $btn.data("post");
        var nonce  = $btn.data("nonce");
        openModal($modal);
        fetchHistory($modal, postId, nonce);
    });

    $(document).on("click", "#dge-hist-modal [data-close], #dge-hist-modal .dge-hist-backdrop", function(e){
        e.preventDefault();
        closeModal($("#dge-hist-modal"));
    });

    $(document).on("keydown", function(e){
        if(e.key === "Escape"){
            closeModal($("#dge-hist-modal"));
        }
    });
})(jQuery);
';
        wp_add_inline_script('dge-history-js', $js);
    }
}

add_action('init', ['DGE_Shortcode_History', 'register']);
