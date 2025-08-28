<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [lokal_accessories id="123" label="Przynależności" tag="button" class="btn btn-secondary"]
 *
 * Założenia:
 * - „Przynależności” to inne wpisy CPT 'lokale', powiązane przez meta 'accessory_unit_ids' (tablica ID).
 * - Tabela: Nazwa | Typ lokalu | Cena całkowita | Cena za m²
 * - BEZ historii cen.
 * - Modal, CSS i JS wstrzykiwane w wp_footer (tuż przed </body>) — tylko jeśli shortcode coś wyrenderował.
 */
class DGE_Shortcode_Accessories
{
    const NONCE_ACTION = 'dge_acc_nonce';
    const MODAL_ID     = 'dge-acc-modal';

    private static $shortcode_used = false;

    public static function register()
    {
        add_shortcode('lokal_accessories', [__CLASS__, 'render']);
        add_action('wp_ajax_dge_acc_fetch',        [__CLASS__, 'ajax_fetch']);
        add_action('wp_ajax_nopriv_dge_acc_fetch', [__CLASS__, 'ajax_fetch']);
        add_action('wp_footer', [__CLASS__, 'render_modal_container'], 9999);
    }

    /** Render przycisku otwierającego popup (tylko gdy są jakiekolwiek przynależności) */
    public static function render($atts)
    {
        $a = shortcode_atts([
            'id'    => 0,
            'label' => 'Przynależności',
            'tag'   => 'button',            // button|a|span|div
            'class' => 'dge-acc-trigger',   // dodatkowe klasy
        ], $atts, 'lokal_accessories');

        $post_id = self::resolve_post_id($a['id']);
        if (!$post_id) return '';

        // Sprawdź czy są przynależności (opublikowane)
        $acc_ids = get_post_meta($post_id, 'accessory_unit_ids', true);
        if (!is_array($acc_ids)) $acc_ids = [];
        $acc_ids = array_values(array_filter(array_unique(array_map('intval', $acc_ids)), function ($id) {
            return $id > 0 && get_post_status($id) === 'publish';
        }));

        if (empty($acc_ids)) {
            // Brak przynależności → nic nie pokazuj
            return '';
        }

        // Mamy co pokazać → ustaw flagę, aby wstrzyknąć modal w stopce
        self::$shortcode_used = true;

        $tag   = self::safe_tag($a['tag']);
        $class = trim('dge-acc-trigger ' . (string)$a['class']);
        $nonce = wp_create_nonce(self::NONCE_ACTION . '|' . $post_id);

        $attrs = [
            'class'       => $class,
            'data-post'   => (int)$post_id,
            'data-nonce'  => $nonce,
            'data-target' => '#' . self::MODAL_ID,
        ];

        $attr_html = '';
        foreach ($attrs as $k => $v) $attr_html .= ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';

        return sprintf('<%1$s%2$s>%3$s</%1$s>', esc_html($tag), $attr_html, esc_html($a['label']));
    }

    /** AJAX: zwraca HTML tabeli przynależności */
    public static function ajax_fetch()
    {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $nonce   = isset($_POST['nonce'])   ? sanitize_text_field($_POST['nonce']) : '';

        if ($post_id <= 0 || ! wp_verify_nonce($nonce, self::NONCE_ACTION . '|' . $post_id)) {
            wp_send_json_error(['message' => 'Błędny token bezpieczeństwa.'], 403);
        }

        // Lista ID przynależności
        $acc_ids = get_post_meta($post_id, 'accessory_unit_ids', true);
        if (!is_array($acc_ids)) $acc_ids = [];

        // Unikalne, istniejące, opublikowane
        $acc_ids = array_values(array_filter(array_unique(array_map('intval', $acc_ids)), function ($id) {
            return $id > 0 && get_post_status($id) === 'publish';
        }));

        ob_start(); ?>
<div class="dge-acc-wrap">
    <h3>Przynależności</h3>
    <?php if (empty($acc_ids)) : ?>
    <p>Brak przypisanych przynależności.</p>
    <?php else : ?>
    <div class="dge-acc-table-wrap">
        <table class="dge-acc-table">
            <thead>
                <tr>
                    <th>Nazwa przynależności</th>
                    <th>Typ lokalu</th>
                    <th>Metraż</th>
                    <th>Cena całkowita</th>
                    <th>Cena za m²</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($acc_ids as $aid) :
                                $title = get_the_title($aid);
                                $types = wp_get_post_terms($aid, 'typ-lokalu', ['fields' => 'names']);
                                $type_name = is_wp_error($types) ? '' : implode(', ', (array)$types);
                                $metraz = get_post_meta($aid, 'powierzchnia', true);
                                $price_total = self::fmt_money(self::to_float(get_post_meta($aid, 'current_price', true)), 0);
                                $price_m2    = self::fmt_money(self::to_float(get_post_meta($aid, 'current_price_per_m2', true)), 2);
                            ?>
                <tr>
                    <td><?php echo esc_html($title); ?></td>
                    <td><?php echo $type_name !== '' ? esc_html($type_name) : '—'; ?></td>
                    <td><?php echo $metraz !== '' ? esc_html($metraz . ' m²') : '—'; ?></td>
                    <td><?php echo $price_total !== '' ? esc_html($price_total . ' zł') : '—'; ?></td>
                    <td><?php echo $price_m2    !== '' ? esc_html($price_m2    . ' zł/m²') : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /** Modal + CSS + JS wstrzyknięte przed </body> (tylko jeśli shortcode został użyty i coś wyrenderował) */
    public static function render_modal_container()
    {
        if (!self::$shortcode_used) return; ?>
<!-- Modal: Przynależności -->
<div id="<?php echo esc_attr(self::MODAL_ID); ?>" class="dge-acc-modal" aria-hidden="true" style="display:none">
    <div class="dge-acc-backdrop" data-dge-close></div>
    <div class="dge-acc-dialog" role="dialog" aria-modal="true" aria-labelledby="dge-acc-title">
        <a type="button" class="dge-acc-close" data-dge-close aria-label="Zamknij">×</a>
        <div class="dge-acc-content">
            <!-- ładowane AJAXem -->
        </div>
    </div>
</div>

<style>
.dge-acc-modal {
    position: fixed;
    inset: 0;
    z-index: 99999
}

.dge-acc-modal[aria-hidden="true"] {
    display: none
}

.dge-acc-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, .5)
}

.dge-acc-dialog {
    position: relative;
    margin: 5vh auto;
    max-width: 960px;
    background: #fff;

    padding: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, .2)
}

.dge-acc-close {
    color: red;
    position: absolute !important;
    right: -10px;
    top: -10px;
    background-color: #e2e2e2 !important;
    width: 22px;
    height: 22px;
    border-radius: 100%;
    text-align: center;
    font-weight: 900;
    display: inline-block;
    vertical-align: middle;
    font-size: 14px;
    cursor: pointer;
}

.dge-acc-table {
    width: 100%;
    border-collapse: collapse
}

.dge-acc-table th,
.dge-acc-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    text-align: left;
    vertical-align: top
}

.dge-acc-table th {
    font-weight: 600;
    white-space: nowrap
}

.dge-acc-table-wrap {
    max-height: 65vh;
    overflow: auto
}

.dge-acc-trigger {
    cursor: pointer
}
</style>

<script>
(function() {
    function openModal(el) {
        if (!el) return;
        el.style.display = 'block';
        el.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(el) {
        if (!el) return;
        el.setAttribute('aria-hidden', 'true');
        el.style.display = 'none';
        document.body.style.overflow = '';
    }

    function qs(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }

    // Otwieranie popupa i ładowanie AJAX
    document.addEventListener('click', function(e) {
        var t = e.target.closest('.dge-acc-trigger');
        if (!t) return;
        e.preventDefault();

        var postId = t.getAttribute('data-post');
        var nonce = t.getAttribute('data-nonce');
        var modal = qs('#<?php echo esc_js(self::MODAL_ID); ?>');
        if (!modal) return;

        var content = modal.querySelector('.dge-acc-content');
        if (content) {
            content.innerHTML = '<p style="text-align:center;">Ładowanie…</p>';
        }
        openModal(modal);

        var fd = new FormData();
        fd.append('action', 'dge_acc_fetch');
        fd.append('post_id', postId);
        fd.append('nonce', nonce);

        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
            .then(r => r.json())
            .then(function(res) {
                if (!content) return;
                if (res && res.success && res.data && res.data.html) {
                    content.innerHTML = res.data.html;
                } else {
                    content.innerHTML = '<p>Nie udało się pobrać przynależności.</p>';
                }
            })
            .catch(function() {
                if (content) content.innerHTML = '<p>Błąd połączenia.</p>';
            });
    });

    // Zamknięcie modala (krzyżyk, backdrop, ESC)
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-dge-close]')) {
            var modal = e.target.closest('.dge-acc-modal');
            closeModal(modal);
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var modal = qs('#<?php echo esc_js(self::MODAL_ID); ?>');
            if (modal && modal.getAttribute('aria-hidden') === 'false') closeModal(modal);
        }
    });
})();
</script>
<?php
    }

    /* ================= Helpers ================= */

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

    private static function safe_tag($tag)
    {
        $tag = trim((string)$tag);
        return preg_match('/^[a-z0-9\-]+$/i', $tag) ? $tag : 'span';
    }
}

add_action('init', ['DGE_Shortcode_Accessories', 'register']);