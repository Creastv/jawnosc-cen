<?php

/**
 * Plugin Name: Dane.gov.pl Exporter lokali
 * Description: Eksport CSV zgodny z jawnością cen — osobne pliki per inwestycja (latest + kopie dzienne), dynamiczne przynależności, uproszczone kolumny cen, powierzchnie area_total.
 * Version:     2.4.2
 * Author:      Roial.pl
 */

if (!defined('ABSPATH')) exit;

/* ===================== Stałe i includes ===================== */
define('DGE_PLUGIN_FILE', __FILE__);
define('DGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DGE_PLUGIN_URL', plugin_dir_url(__FILE__));

define('DGE_MENU_SLUG', 'dane-gov');
define('DGE_SUB_EXPORT_SLUG', 'dge-export');

/* Moduły (bez globalnych danych spółki) */
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
require __DIR__ . '/includes/admin-csv-tabel.php';


require_once __DIR__ . '/includes/export.php';
