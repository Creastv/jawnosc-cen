<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [lokal_accessories id="123" label="Przynależności" tag="button" class="btn btn-secondary"]
 * - „Przynależności” to inne wpisy CPT 'lokale', powiązane przez meta 'accessory_unit_ids' (tablica ID).
 * - Tabela: Nazwa | Typ lokalu | Metraż | Cena całkowita | Cena za m²
 * - Modal, CSS i JS wstrzykiwane globalnie (wp_footer), żeby działało też dla treści z AJAX-a.
 */
class DGE_Shortcode_Accessories
{
    const NONCE_ACTION = 'dge_acc_nonce';
    const MODAL_ID     = 'dge-acc-modal';

    public static function register()
    {
        add_shortcode('lokal_accessories', [__CLASS__, 'render']);

        // AJAX
        add_action('wp_ajax_dge_acc_fetch',        [__CLASS__, 'ajax_fetch']);
        add_action('wp_ajax_nopriv_dge_acc_fetch', [__CLASS__, 'ajax_fetch']);

        // Zawsze dorzuć modal do stopki (raz)
        add_action('wp_footer', [__CLASS__, 'render_modal_container'], 9999);

        // Zawsze dołącz lekki CSS/JS na froncie (żeby działało też przy treściach z AJAX-a)
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** Globalne załadowanie frontowych assets (niewielkie, bezpieczne) */
    public static function enqueue_assets()
    {
        if (is_admin()) return;

        // Jeżeli masz fizyczne pliki, podmień ścieżki na /assets
        wp_register_style('dge-acc-css', false, [], '1.0.1');
        wp_enqueue_style('dge-acc-css');

        wp_register_script('dge-acc-js', false, [], '1.0.1', true);
        wp_enqueue_script('dge-acc-js');

        // CSS inline (lekki)
        $css = '
.dge-acc-modal{position:fixed;inset:0;z-index:99999;display:none}
.dge-acc-modal[aria-hidden="false"]{display:block}
.dge-acc-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.5)}
.dge-acc-dialog{position:relative;margin:5vh auto;max-width:960px;background:#fff;padding:16px;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,.2)}
.dge-acc-close{position:absolute;right:8px;top:8px;background:transparent;border:0;cursor:pointer;text-decoration:none;font-size:20px;line-height:1}
.dge-acc-table{width:100%;border-collapse:collapse}
.dge-acc-table th,.dge-acc-table td{padding:10px;border-bottom:1px solid #eee;text-align:left;vertical-align:top;white-space:nowrap}
.dge-acc-table-wrap{max-height:65vh;overflow:auto}
.dge-acc-trigger{cursor:pointer}
';
        wp_add_inline_style('dge-acc-css', $css);

        // JS inline (delegowane zdarzenia + fetch)
        $ajax_url = esc_url_raw(admin_url('admin-ajax.php'));
        $modal_id = esc_js(self::MODAL_ID);
        $js = "
(function(){
  function qs(s,c){return (c||document).querySelector(s);}
  function openM(m){ if(!m) return; m.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
  function closeM(m){ if(!m) return; m.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }

  // Delegowany klik (działa też dla treści doładowanych AJAX-em)
  document.addEventListener('click', function(e){
    var t = e.target.closest('.dge-acc-trigger');
    if(!t) return;
    e.preventDefault();
    var postId = t.getAttribute('data-post');
    var nonce  = t.getAttribute('data-nonce');
    var modal  = qs('#{$modal_id}');
    if(!modal) return;
    var content = modal.querySelector('.dge-acc-content');
    if(content){ content.innerHTML = '<p style=\"text-align:center;\">Ładowanie…</p>'; }
    openM(modal);

    var fd = new FormData();
    fd.append('action','dge_acc_fetch');
    fd.append('post_id', postId);
    fd.append('nonce', nonce);

    fetch('{$ajax_url}', { method:'POST', credentials:'same-origin', body:fd })
      .then(r=>r.json())
      .then(function(res){
        if(!content) return;
        if(res && res.success && res.data && res.data.html){
          content.innerHTML = res.data.html;
        } else {
          content.innerHTML = '<p>Nie udało się pobrać przynależności.</p>';
        }
      })
      .catch(function(){ if(content) content.innerHTML = '<p>Błąd połączenia.</p>'; });
  });

  // Zamknięcie modala (krzyżyk, backdrop, ESC)
  document.addEventListener('click', function(e){
    if(e.target.matches('[data-dge-close], .dge-acc-backdrop')){
    e.preventDefault();
      closeM(e.target.closest('.dge-acc-modal'));
    }
  });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){
      var m = qs('#{$modal_id}');
      if(m && m.getAttribute('aria-hidden')==='false') closeM(m);
    }
  });
})();
";
        wp_add_inline_script('dge-acc-js', $js);
    }

    /** Render przycisku (tylko gdy są jakiekolwiek przynależności) */
    public static function render($atts)
    {
        $a = shortcode_atts([
            'id'    => 0,
            'label' => 'Przynależności',
            'tag'   => 'button',
            'class' => 'dge-acc-trigger',
        ], $atts, 'lokal_accessories');

        $post_id = self::resolve_post_id($a['id']);
        if (!$post_id) return '';

        // opublikowane przynależności
        $acc_ids = get_post_meta($post_id, 'accessory_unit_ids', true);
        if (!is_array($acc_ids)) $acc_ids = [];
        $acc_ids = array_values(array_filter(array_unique(array_map('intval', $acc_ids)), function ($id) {
            return $id > 0 && get_post_status($id) === 'publish';
        }));
        if (empty($acc_ids)) return '';

        $tag   = self::safe_tag($a['tag']);
        // $class = trim('dge-acc-trigger ' . (string)$a['class']);
        $class = trim((string)$a['class']);
        if ($class === '' || strpos($class, 'dge-acc-trigger') === false) {
            $class = trim('dge-acc-trigger ' . $class);
        }
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

        $acc_ids = get_post_meta($post_id, 'accessory_unit_ids', true);
        if (!is_array($acc_ids)) $acc_ids = [];
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
                                $title       = get_the_title($aid);
                                $types       = wp_get_post_terms($aid, 'typ-lokalu', ['fields' => 'names']);
                                $type_name   = is_wp_error($types) ? '' : implode(', ', (array)$types);
                                $metraz      = get_post_meta($aid, 'powierzchnia', true);
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

    /** Modal w stopce (drukujemy zawsze, raz) */
    public static function render_modal_container()
    { ?>
        <div id="<?php echo esc_attr(self::MODAL_ID); ?>" class="dge-acc-modal" aria-hidden="true">
            <div class="dge-acc-backdrop" data-dge-close></div>
            <div class="dge-acc-dialog" role="dialog" aria-modal="true" aria-labelledby="dge-acc-title">
                <a href="#" class="dge-acc-close" data-dge-close aria-label="Zamknij">×</a>
                <div class="dge-acc-content">
                    <!-- ładowane AJAXem -->
                </div>
            </div>
        </div>
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

    private static function to_float($v)
    {
        if ($v === '' || $v === null) return '';
        $s = (string)$v;
        // BYŁO: str_replace([\"\xC2\xA0\", ' '], '', $s);
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
