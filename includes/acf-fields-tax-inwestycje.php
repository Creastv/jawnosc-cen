<?php
if (!defined('ABSPATH')) exit;

/**
 * ACF dla taksonomii "inwestycje"
 * Cel: przenieść dane per-inwestycja (sprzedaż, lokalizacja projektu, kontakt, prospekt)
 */
if (function_exists('acf_add_local_field_group')) :

    acf_add_local_field_group(array(
        'key'   => 'group_inwestycje_term_meta',
        'title' => 'Dane inwestycji (dane.gov.pl)',
        'fields' => array(

            // --- TAB: Lokal sprzedaży ---
            array(
                'key' => 'inv_tab_sales',
                'label' => 'Lokalizacja sprzedaży',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array('key' => 'inv_sales_woj', 'label' => 'Województwo (sprzedaż)', 'name' => 'sales_woj', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_powiat', 'label' => 'Powiat (sprzedaż)', 'name' => 'sales_powiat', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_gmina', 'label' => 'Gmina (sprzedaż)', 'name' => 'sales_gmina', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_city', 'label' => 'Miejscowość (sprzedaż)', 'name' => 'sales_city', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_street', 'label' => 'Ulica (sprzedaż)', 'name' => 'sales_street', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_no', 'label' => 'Nr nieruchomości (sprzedaż)', 'name' => 'sales_no', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_local', 'label' => 'Nr lokalu (sprzedaż)', 'name' => 'sales_local', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_zip', 'label' => 'Kod pocztowy (sprzedaż)', 'name' => 'sales_zip', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_extra', 'label' => 'Dodatkowe lokalizacje sprzedaży', 'name' => 'sales_extra_locations', 'type' => 'textarea', 'rows' => 2),

            // --- TAB: Lokalizacja przedsięwzięcia ---
            array(
                'key' => 'inv_tab_proj',
                'label' => 'Lokalizacja przedsięwzięcia/zadania',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array('key' => 'inv_proj_woj', 'label' => 'Województwo (przedsięwzięcie)', 'name' => 'proj_woj', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_proj_powiat', 'label' => 'Powiat (przedsięwzięcie)', 'name' => 'proj_powiat', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_proj_gmina', 'label' => 'Gmina (przedsięwzięcie)', 'name' => 'proj_gmina', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_proj_city', 'label' => 'Miejscowość (przedsięwzięcie)', 'name' => 'proj_city', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_proj_street', 'label' => 'Ulica (przedsięwzięcie)', 'name' => 'proj_street', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_proj_no', 'label' => 'Nr nieruchomości (przedsięwzięcie)', 'name' => 'proj_no', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_proj_zip', 'label' => 'Kod pocztowy (przedsięwzięcie)', 'name' => 'proj_zip', 'type' => 'text', 'wrapper' => ['width' => '33.333']),

            // --- TAB: Kontakt/Prospekt ---
            array(
                'key' => 'inv_tab_contact',
                'label' => 'Kontakt / Prospekt',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array('key' => 'inv_contact_method', 'label' => 'Sposób kontaktu (np. tel/mail/formularz)', 'name' => 'contact_method', 'type' => 'text', 'wrapper' => ['width' => '50']),
            array('key' => 'inv_prospekt_url', 'label' => 'URL prospektu informacyjnego', 'name' => 'prospekt_url', 'type' => 'url', 'wrapper' => ['width' => '50']),
            // --- TAB: Pomieszczenia przynależne (wyszczególnienie cen) ---
            array(
                'key' => 'inv_tab_accessories_detail',
                'label' => 'Pomieszczenia przynależne – wyszczególnienie',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array(
                'key' => 'inv_acc_detail_repeater',
                'label' => 'Pozycje (rodzaj/oznaczenie/cena/data)',
                'name' => 'acc_detail',
                'type' => 'repeater',
                'layout' => 'row',
                'button_label' => 'Dodaj pozycję',
                'sub_fields' => array(
                    array(
                        'key' => 'inv_acc_detail_kind',
                        'label' => 'Rodzaj (np. „komórka”, „garaż”)',
                        'name' => 'kind',
                        'type' => 'text',
                        'wrapper' => array('width' => '25'),
                    ),
                    array(
                        'key' => 'inv_acc_detail_label',
                        'label' => 'Oznaczenie (np. K1, MP-12)',
                        'name' => 'label',
                        'type' => 'text',
                        'wrapper' => array('width' => '25'),
                    ),
                    array(
                        'key' => 'inv_acc_detail_price',
                        'label' => 'Cena [PLN]',
                        'name' => 'price',
                        'type' => 'number',
                        'step' => '0.01',
                        'min'  => 0,
                        'wrapper' => array('width' => '25'),
                    ),
                    array(
                        'key' => 'inv_acc_detail_from',
                        'label' => 'Data, od której cena obowiązuje',
                        'name' => 'valid_from',
                        'type' => 'date_picker',
                        'display_format' => 'Y-m-d',
                        'return_format'  => 'Y-m-d',
                        'wrapper' => array('width' => '25'),
                    ),
                ),
            ),

            // --- TAB: Prawa niezbędne do korzystania ---
            array(
                'key' => 'inv_tab_rights',
                'label' => 'Prawa niezbędne do korzystania',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array(
                'key' => 'inv_rights_desc',
                'label' => 'Wyszczególnienie praw',
                'name' => 'rights_desc',
                'type' => 'textarea',
                'rows' => 2,
                'wrapper' => array('width' => '50'),
            ),
            array(
                'key' => 'inv_rights_value',
                'label' => 'Wartość praw [PLN]',
                'name' => 'rights_value',
                'type' => 'number',
                'step' => '0.01',
                'min'  => 0,
                'wrapper' => array('width' => '25'),
            ),
            array(
                'key' => 'inv_rights_date',
                'label' => 'Data, od której obowiązuje wartość',
                'name' => 'rights_date',
                'type' => 'date_picker',
                'display_format' => 'Y-m-d',
                'return_format'  => 'Y-m-d',
                'wrapper' => array('width' => '25'),
            ),

            // --- TAB: Inne świadczenia pieniężne ---
            array(
                'key' => 'inv_tab_other_payments',
                'label' => 'Inne świadczenia pieniężne',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array(
                'key' => 'inv_other_desc',
                'label' => 'Wyszczególnienie rodzajów świadczeń',
                'name' => 'other_payments_desc',
                'type' => 'textarea',
                'rows' => 2,
                'wrapper' => array('width' => '50'),
            ),
            array(
                'key' => 'inv_other_value',
                'label' => 'Wartość świadczeń [PLN]',
                'name' => 'other_payments_value',
                'type' => 'number',
                'step' => '0.01',
                'min'  => 0,
                'wrapper' => array('width' => '25'),
            ),
            array(
                'key' => 'inv_other_date',
                'label' => 'Data, od której obowiązuje wartość',
                'name' => 'other_payments_date',
                'type' => 'date_picker',
                'display_format' => 'Y-m-d',
                'return_format'  => 'Y-m-d',
                'wrapper' => array('width' => '25'),
            ),

        ),

        'location' => array(
            array(
                array(
                    'param' => 'taxonomy',
                    'operator' => '==',
                    'value' => 'inwestycje', // ← upewnij się, że to dokładny slug taksonomii
                ),
            ),
        ),
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement' => 'top',
        'active' => true,
        'show_in_rest' => 0,
        'description' => 'Dane per-inwestycja dla eksportu dane.gov.pl.',
    ));

endif;