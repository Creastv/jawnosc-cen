<?php
if (!defined('ABSPATH')) exit;

/* ============================================================
 *  ACF: JEDNA GRUPA PÓL (Inwestycja + Historia cen + Przynależności)
 *  UWAGA: Adres itp. pobieramy z taksonomii "inwestycje" (term meta),
 *         więc tutaj zostawiamy TYLKO wybór inwestycji.
 * ============================================================ */
if (function_exists('acf_add_local_field_group')) :

    acf_add_local_field_group(array(
        'key'   => 'group_danegov_all_in_one',
        'title' => 'Dane do dane.gov.pl + Historia cen + Przynależności',
        'fields' => array(

            /* --- TAB 1: INWESTYCJA (TAKSONOMIA) --- */
            array(
                'key' => 'field_dg_tab_addr',
                'label' => 'Adres inwestycji / budynku',
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
            ),
            // tylko wybór inwestycji (taksonomia)
            array(
                'key' => 'field_dg_investment_term',
                'label' => 'Inwestycja',
                'name' => 'inwestycje_term',
                'type' => 'taxonomy',
                'taxonomy' => 'inwestycje',   // upewnij się, że to właściwy slug
                'field_type' => 'select',      // select (pojedynczy wybór)
                'allow_null' => 0,
                'add_term' => 1,               // możliwość dodania nowego termu
                'save_terms' => 1,             // zapisze do obiektu posta
                'load_terms' => 1,             // wczyta aktualny wybór
                'return_format' => 'id',       // wygodnie w eksporcie
                'ui' => 1,
                'instructions' => 'Wybierz inwestycję. Adres i inne dane zostaną pobrane z metadanych taksonomii.',
                'wrapper' => array('width' => '33.333'),
            ),

            /* --- TAB 2: HISTORIA CEN --- */
            array(
                'key' => 'field_hist_tab',
                'label' => 'Historia cen (bieżące + zmiany)',
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array(
                'key' => 'field_dg_price_valid_from',
                'label' => 'Data, od której cena obowiązuje',
                'name' => 'price_valid_from',
                'type' => 'date_picker',
                'display_format' => 'Y-m-d',
                'return_format'  => 'd-m-Y',
                'wrapper' => array('width' => '33.333'),
                'instructions' => 'Format: RRRR-MM-DD',
            ),
            array(
                'key' => 'field_6894ba1019a92',
                'label' => 'Aktualna cena lokalu',
                'name' => 'current_price',
                'type' => 'text',
                'wrapper' => array('width' => '33.333'),
            ),
            array(
                'key' => 'field_6895a33122434',
                'label' => 'Aktualna cena za m2',
                'name' => 'current_price_per_m2',
                'type' => 'text',
                'wrapper' => array('width' => '33.333'),
            ),

            array(
                'key' => 'field_68949a8b742cb',
                'label' => 'Historia cen (lista zmian)',
                'name' => 'history_price',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Dodaj wiersz',
                'rows_per_page' => 20,
                'sub_fields' => array(
                    array(
                        'key' => 'field_68949ab0742cc',
                        'label' => 'Cena',
                        'name' => 'price',
                        'type' => 'text',
                        'parent_repeater' => 'field_68949a8b742cb',
                    ),
                    array(
                        'key' => 'field_68949ab6742cd',
                        'label' => 'Data',
                        'name' => 'time',
                        'type' => 'date_picker',
                        'display_format' => 'Y-m-d',
                        'return_format'  => 'd-m-Y',
                        'wrapper' => array('width' => '33.333'),
                        'instructions' => 'Format: RRRR-MM-DD',
                        'parent_repeater' => 'field_68949a8b742cb',
                    ),
                    array(
                        'key' => 'field_6895a17561631',
                        'label' => 'Cena za m2',
                        'name' => 'price_per_m2',
                        'type' => 'text',
                        'parent_repeater' => 'field_68949a8b742cb',
                    ),
                ),
            ),
            /* --- TAB 3: POWIERZCHNIA --- */
            array(
                'key' => 'field_area_tab',
                'label' => 'Powierzchnia',
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array(
                'key' => 'field_dg_area_total',
                'label' => 'Powierzchnia całkowita',
                'name' => 'area_total',
                'type' => 'number',
                'wrapper' => array('width' => '33.333'),
                'instructions' => 'Podaj łączną powierzchnię lokalu.',
                'append' => 'm²',
                'min' => 0,
                'step' => 0.01,
            ),


            /* --- TAB 4: PRZYNALEŻNOŚCI (WYBÓR INNYCH LOKALI) --- */
            array(
                'key' => 'field_acc_tab',
                'label' => 'Przynależności',
                'name' => '',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array(
                'key' => 'field_acc_relationship',
                'label' => 'Powiązane lokale jako przynależności',
                'name' => 'accessory_unit_ids',
                'type' => 'relationship',
                'post_type' => array('lokale'),
                'return_format' => 'id',
                'filters' => array('search', 'post_type', 'taxonomy'),
                'elements' => array('featured_image'),
                'min' => 0,
                'max' => 0,
                'ui'  => 1,
                // można zawęzić po taksonomii, np. tylko "miejsce-postojowe", "komorka-lokatorska":
                // 'taxonomy' => array('typ-lokalu:miejsce-postojowe','typ-lokalu:komorka-lokatorska','typ-lokalu:garaz'),
                'instructions' => 'Wybierz lokale-przynależności (np. miejsce postojowe, komórka) oznaczone w „typ-lokalu”.',
            ),



        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'lokale',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'show_in_rest' => 0,
        'description' => 'Wybierz inwestycję (taksonomia). Adres i inne dane ładowane są z meta termu. Historia cen i przynależności ustawiane per lokal.',
    ));

endif;
