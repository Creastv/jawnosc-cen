<?php

/**
 * Plugin admin: Dane gov (Eksport + tabela CSV) + odchudzone ustawienia „Dane dewelopera”
 */
if (!defined('ABSPATH')) exit;

/* ============================================================
 * 1) DGE_Settings – odchudzona klasa (zachowuje kompatybilność)
 * ============================================================ */
if (!class_exists('DGE_Settings')) {
    class DGE_Settings
    {
        /** Zostawione dla kompatybilności – nieużywane. */
        const OPT = 'dge_developer_data';

        /** Zwraca pustą tablicę (kompatybilność z wcześniejszymi wywołaniami). */
        public static function get()
        {
            return [];
        }

        /** Inicjalizacja – tylko ukrycie zakładki w menu admina. */
        public static function init()
        {
            add_action('admin_menu', [__CLASS__, 'remove_menu_page'], 999);
        }

        /** Usuwa podmenu, jeśli istnieje i stałe są zdefiniowane. */
        public static function remove_menu_page()
        {
            if (defined('DGE_MENU_SLUG') && defined('DGE_SUB_DEVELOPER_SLUG')) {
                remove_submenu_page(DGE_MENU_SLUG, DGE_SUB_DEVELOPER_SLUG);
            }
        }

        // Puste stuby dla kompatybilności:
        public static function defaults()
        {
            return [];
        }
        public static function register_settings() {}
        public static function render_text_input($args) {}
        public static function render_page()
        {
            echo '<div class="wrap"><h1>Dane dewelopera</h1><p>Globalne dane spółki zostały wyłączone. Dane wypełniaj per inwestycja w taksonomii <strong>„inwestycje”</strong> (zakładka <em>Dane spółki</em>).</p></div>';
        }
    }
    DGE_Settings::init();
}

/* ============================================================
 * 2) Narzędzia CSV (/dane/) – bez duplikatów
 * ============================================================ */
if (!function_exists('dge_csv_get_dir_conf')) {
    function dge_csv_get_dir_conf()
    {
        $dir = wp_normalize_path(trailingslashit(ABSPATH) . 'dane');
        // Publiczny URL (lokalnie np. http://localhost/twojslug/dane/).
        // Na produkcji może pozostać nieużywany – wtedy korzystamy z "Pobierz".
        $url = rtrim(trailingslashit(site_url('/dane/')), '/');

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return ['dir' => $dir, 'url' => $url];
    }
}

if (!function_exists('dge_csv_format_bytes')) {
    function dge_csv_format_bytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $p = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $p = min($p, count($units) - 1);
        $v = $bytes / pow(1024, $p);
        return sprintf('%s %s', number_format_i18n($v, $p ? 2 : 0), $units[$p]);
    }
}

if (!function_exists('dge_csv_scan_files')) {
    function dge_csv_scan_files($search = '', $orderby = 'modified', $order = 'desc')
    {
        $conf = dge_csv_get_dir_conf();
        $dir  = $conf['dir'];
        if (!is_dir($dir) || !is_readable($dir)) return [];

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.csv');
        if (!$files) return [];

        $items = [];
        foreach ($files as $path) {
            $base = basename($path);
            if ($search && stripos($base, $search) === false) continue;
            $items[] = [
                'file'     => $base,
                'path'     => $path,
                'size'     => @filesize($path) ?: 0,
                'modified' => @filemtime($path) ?: 0,
            ];
        }

        usort($items, function ($a, $b) use ($orderby, $order) {
            $A = $a[$orderby] ?? 0;
            $B = $b[$orderby] ?? 0;
            if ($A == $B) return 0;
            $cmp = ($A < $B) ? -1 : 1;
            return strtolower($order) === 'asc' ? $cmp : -$cmp;
        });

        return $items;
    }
}

/* ============================================================
 * 3) Pobieranie i usuwanie plików (admin_post/admin_init)
 * ============================================================ */
add_action('admin_post_dge_csv_download', function () {
    if (!current_user_can('manage_options')) wp_die('Brak uprawnień.');
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'dge_csv_download')) wp_die('Błędny nonce.');

    $file = isset($_GET['file']) ? sanitize_text_field(wp_unslash($_GET['file'])) : '';
    if ($file === '') wp_die('Brak pliku.');

    $conf = dge_csv_get_dir_conf();
    $path = wp_normalize_path(trailingslashit($conf['dir']) . $file);

    if (strpos($path, $conf['dir']) !== 0) wp_die('Niedozwolona ścieżka.');
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'csv') wp_die('Niedozwolone rozszerzenie.');
    if (!file_exists($path) || !is_file($path)) wp_die('Plik nie istnieje.');

    // Uwaga: nie może być żadnego outputu przed nagłówkami!
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode(basename($path)) . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
});

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;

    // Pojedyncze usuwanie
    if (
        isset($_GET['page'], $_GET['action'], $_GET['file']) &&
        defined('DGE_SUB_EXPORT_SLUG') &&
        $_GET['page'] === DGE_SUB_EXPORT_SLUG &&
        $_GET['action'] === 'dge_csv_delete'
    ) {
        check_admin_referer('dge_csv_delete');
        $file = sanitize_text_field(wp_unslash($_GET['file']));
        $conf = dge_csv_get_dir_conf();
        $path = wp_normalize_path(trailingslashit($conf['dir']) . $file);

        if (
            strpos($path, $conf['dir']) === 0 &&
            strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv' &&
            file_exists($path) && is_file($path)
        ) {
            @unlink($path);
        }
        wp_safe_redirect(remove_query_arg(['action', 'file', '_wpnonce']));
        exit;
    }

    // Usuwanie zbiorcze
    if (
        isset($_POST['page'], $_POST['action'], $_POST['files']) &&
        defined('DGE_SUB_EXPORT_SLUG') &&
        $_POST['page'] === DGE_SUB_EXPORT_SLUG &&
        $_POST['action'] === 'dge_csv_bulk_delete'
    ) {
        check_admin_referer('dge_csv_bulk');

        $conf = dge_csv_get_dir_conf();
        foreach ((array) $_POST['files'] as $file) {
            $f = sanitize_text_field(wp_unslash($file));
            $path = wp_normalize_path(trailingslashit($conf['dir']) . $f);
            if (
                strpos($path, $conf['dir']) === 0 &&
                strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv' &&
                file_exists($path) && is_file($path)
            ) {
                @unlink($path);
            }
        }
        wp_safe_redirect(remove_query_arg(['action', 'files', '_wpnonce']));
        exit;
    }
});

/* ============================================================
 * 4) WP_List_Table – pojedyncza, poprawna deklaracja
 * ============================================================ */
if (is_admin() && !class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if (!class_exists('DGE_CSV_Table') && class_exists('WP_List_Table')) {
    class DGE_CSV_Table extends WP_List_Table
    {
        public function __construct()
        {
            parent::__construct([
                'singular' => 'dge_csv',
                'plural'   => 'dge_csvs',
                'ajax'     => false,
            ]);
        }

        public function get_columns()
        {
            return [
                'cb'       => '<input type="checkbox" />',
                'file'     => 'Plik',
                'size'     => 'Rozmiar',
                'modified' => 'Zmodyfikowano',
                'actions'  => 'Akcje',
            ];
        }

        protected function get_sortable_columns()
        {
            return [
                'file'     => ['file', false],
                'size'     => ['size', false],
                'modified' => ['modified', true],
            ];
        }

        protected function column_cb($item)
        {
            return sprintf('<input type="checkbox" name="files[]" value="%s" />', esc_attr($item['file']));
        }

        protected function column_file($item)
        {
            return esc_html($item['file']);
        }
        protected function column_size($item)
        {
            return esc_html(dge_csv_format_bytes($item['size']));
        }
        protected function column_modified($item)
        {
            return esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int)$item['modified']));
        }

        protected function column_actions($item)
        {
            $download_url = add_query_arg([
                'action' => 'dge_csv_download',
                'file'   => rawurlencode($item['file']),
                'nonce'  => wp_create_nonce('dge_csv_download'),
            ], admin_url('admin-post.php'));

            $conf = dge_csv_get_dir_conf();
            $view = '';
            if (!empty($conf['url'])) {
                $view = sprintf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a> | ',
                    esc_url(trailingslashit($conf['url']) . $item['file']),
                    __('Podgląd', 'dge')
                );
            }

            $download = sprintf('<a href="%s">%s</a>', esc_url($download_url), __('Pobierz', 'dge'));
            $copy     = sprintf('<button class="button button-small dge-copy" data-url="%s">%s</button>', esc_attr($download_url), __('Kopiuj URL', 'dge'));

            $del_url = wp_nonce_url(
                add_query_arg([
                    'page'   => defined('DGE_SUB_EXPORT_SLUG') ? DGE_SUB_EXPORT_SLUG : '',
                    'action' => 'dge_csv_delete',
                    'file'   => rawurlencode($item['file']),
                ], admin_url('admin.php')),
                'dge_csv_delete'
            );
            $delete  = sprintf('<a href="%s" class="dge-delete">%s</a>', esc_url($del_url), __('Usuń', 'dge'));

            return $view . $download . ' | ' . $copy . ' | ' . $delete;
        }

        public function get_bulk_actions()
        {
            return ['dge_csv_bulk_delete' => __('Usuń zaznaczone', 'dge')];
        }

        public function prepare_items()
        {
            $per_page = 20;
            $search   = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
            $orderby  = isset($_REQUEST['orderby']) ? sanitize_key($_REQUEST['orderby']) : 'modified';
            $order    = (isset($_REQUEST['order']) && in_array(strtolower($_REQUEST['order']), ['asc', 'desc'], true)) ? strtolower($_REQUEST['order']) : 'desc';

            $data = dge_csv_scan_files($search, $orderby, $order);
            $current_page = $this->get_pagenum();
            $total_items  = count($data);

            $data = array_slice($data, ($current_page - 1) * $per_page, $per_page);

            $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'file'];
            $this->items = $data;

            $this->set_pagination_args([
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => (int) ceil($total_items / $per_page),
            ]);
        }
    }
}

/* ============================================================
 * 5) Strony w menu: „Dane gov” + podstrona „Eksport”
 * ============================================================ */
add_action('admin_menu', function () {
    // Wymagane stałe powinny być zdefiniowane w głównym pliku pluginu.
    // Przykład:
    // if (!defined('DGE_MENU_SLUG'))        define('DGE_MENU_SLUG', 'dge-root');
    // if (!defined('DGE_SUB_EXPORT_SLUG'))  define('DGE_SUB_EXPORT_SLUG', 'dge-export');

    add_menu_page(
        __('Dane gov', 'dge'),
        __('Dane gov', 'dge'),
        'manage_options',
        DGE_MENU_SLUG,
        function () {
            echo '<div class="wrap"><h1>Dane gov</h1><p>Skorzystaj z podstrony: <strong>Eksport</strong>.</p></div>';
        },
        'dashicons-database-export',
        58
    );

    add_submenu_page(
        DGE_MENU_SLUG,
        __('Eksport', 'dge'),
        __('Eksport', 'dge'),
        'manage_options',
        DGE_SUB_EXPORT_SLUG,
        'dge_render_export_page'
    );
});

/* ============================================================
 * 6) Render podstrony „Eksport” (formularz + tabela CSV)
 * ============================================================ */
if (!function_exists('dge_render_export_page')) {
    function dge_render_export_page()
    {
        // Sekcja eksportu (Twoja logika exportera)
        if (isset($_POST['dge_do_export'])) {
            // Uwaga: upewnij się, że klasa Dane_Gov_Exporter jest załadowana przed tą stroną.
            $exp = new Dane_Gov_Exporter();
            $res = $exp->run_export();
            if (is_wp_error($res)) {
                echo '<div class="error"><p><strong>Błąd:</strong> ' . esc_html($res->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="updated"><p>Wygenerowane pliki:</p><ul>';
                if (is_array($res) && !empty($res)) {
                    foreach ($res as $slug => $files) {
                        echo '<li><strong>' . esc_html($slug) . '</strong><ul>';
                        foreach ($files as $p) {
                            echo '<li><code>' . esc_html($p) . '</code></li>';
                        }
                        echo '</ul></li>';
                    }
                } else {
                    echo '<li>Brak plików do wygenerowania.</li>';
                }
                echo '</ul></div>';
            }
        }

        $conf = dge_csv_get_dir_conf();
?>
        <div class="wrap">
            <h1>Eksport lokali do dane.gov.pl (per inwestycja)</h1>
            <form method="post">
                <?php submit_button('Eksportuj teraz', 'primary', 'dge_do_export'); ?>
            </form>
            <p>Pliki zapisywane są w katalogu <code>/dane/</code> w głównym katalogu WordPressa jako:
                <br><code>dane-{nazwa-inwestycji}.csv</code> (latest) oraz
                <code>dane-{nazwa-inwestycji}-YYYY-MM-DD.csv</code> (kopie dzienne).
            </p>
            <p>Dodane kolumny: <strong>Powierzchnia lokalu [m²]</strong> oraz dla każdej przynależności <strong>Powierzchnia
                    [m²]</strong>.</p>

            <hr>

            <h2>Pliki CSV w katalogu /dane/</h2>
            <p>Katalog: <code><?php echo esc_html($conf['dir']); ?></code>
                <?php if (!empty($conf['url'])): ?>
                    | URL: <a href="<?php echo esc_url($conf['url']); ?>" target="_blank"
                        rel="noopener"><?php echo esc_html($conf['url']); ?></a>
                <?php else: ?>
                    | URL: <em>brak publicznego adresu – użyj „Pobierz”</em>
                <?php endif; ?>
            </p>
            <?php
            if (class_exists('DGE_CSV_Table')) {
                $table = new DGE_CSV_Table();
                $table->prepare_items();

                echo '<form method="post">';
                wp_nonce_field('dge_csv_bulk');
                echo '<input type="hidden" name="page" value="' . esc_attr(defined('DGE_SUB_EXPORT_SLUG') ? DGE_SUB_EXPORT_SLUG : '') . '" />';
                $table->search_box(__('Szukaj plików', 'dge'), 'dge-csv-search');
                $table->display();
                echo '</form>';
            } else {
                echo '<div class="notice notice-error"><p>Brak klasy <code>DGE_CSV_Table</code>.</p></div>';
            }
            ?>
        </div>

        <script>
            // Kopiowanie i potwierdzanie usuwania
            document.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('dge-copy')) {
                    const url = e.target.getAttribute('data-url');
                    navigator.clipboard.writeText(url).then(() => {
                        e.target.textContent = 'Skopiowano!';
                        setTimeout(() => {
                            e.target.textContent = 'Kopiuj URL';
                        }, 1200);
                    });
                }
                if (e.target && e.target.classList.contains('dge-delete')) {
                    if (!confirm('Na pewno usunąć ten plik?')) {
                        e.preventDefault();
                    }
                }
            });
        </script>
<?php
    }
}

/* ============================================================
 * 7) Notice – katalog /dane/ niedostępny
 * ============================================================ */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    $screen = get_current_screen();
    if (!$screen) return;

    $targets = [];
    if (defined('DGE_MENU_SLUG'))       $targets[] = 'toplevel_page_' . DGE_MENU_SLUG;
    if (defined('DGE_MENU_SLUG') && defined('DGE_SUB_EXPORT_SLUG')) $targets[] = DGE_MENU_SLUG . '_page_' . DGE_SUB_EXPORT_SLUG;

    if (!in_array($screen->id, $targets, true)) return;

    $conf = dge_csv_get_dir_conf();
    if (!is_dir($conf['dir']) || !is_readable($conf['dir'])) {
        echo '<div class="notice notice-error"><p><strong>CSV:</strong> Katalog niedostępny: <code>' . esc_html($conf['dir']) . '</code>. Utwórz go i nadaj prawa (np. 755).</p></div>';
    }
});
