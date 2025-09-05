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

            // --- TAB: Dane spółki ---
            array(
                'key' => 'inv_tab_company',
                'label' => 'Dane spółki',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array('key' => 'inv_company_dev_name',   'label' => 'Nazwa dewelopera', 'name' => 'company_developer_name', 'type' => 'text', 'wrapper' => ['width' => '50']),
            array('key' => 'inv_company_legal_form', 'label' => 'Forma prawna dewelopera', 'name' => 'company_legal_form', 'type' => 'text', 'wrapper' => ['width' => '50']),
            array('key' => 'inv_company_krs',        'label' => 'Nr KRS', 'name' => 'company_krs', 'type' => 'text', 'wrapper' => ['width' => '25']),
            array('key' => 'inv_company_ceidg',      'label' => 'Nr wpisu do CEiDG', 'name' => 'company_ceidg', 'type' => 'text', 'wrapper' => ['width' => '25']),
            array('key' => 'inv_company_nip',        'label' => 'Nr NIP', 'name' => 'company_nip', 'type' => 'text', 'wrapper' => ['width' => '25']),
            array('key' => 'inv_company_regon',      'label' => 'Nr REGON', 'name' => 'company_regon', 'type' => 'text', 'wrapper' => ['width' => '25']),
            array('key' => 'inv_company_phone',      'label' => 'Nr telefonu', 'name' => 'company_phone', 'type' => 'text', 'wrapper' => ['width' => '25']),
            array('key' => 'inv_company_email',      'label' => 'Adres poczty elektronicznej', 'name' => 'company_email', 'type' => 'email', 'wrapper' => ['width' => '25']),
            array('key' => 'inv_company_fax',        'label' => 'Nr faksu', 'name' => 'company_fax', 'type' => 'text', 'wrapper' => ['width' => '25']),
            array('key' => 'inv_company_www',        'label' => 'Adres strony internetowej dewelopera', 'name' => 'company_website', 'type' => 'url', 'wrapper' => ['width' => '25']),
            array('key' => 'inv_company_addr_woj',   'label' => 'Województwo adresu siedziby/głównego miejsca wykonywania działalności', 'name' => 'company_addr_woj', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_company_addr_powiat', 'label' => 'Powiat adresu siedziby/głównego miejsca wykonywania działalności', 'name' => 'company_addr_powiat', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_company_addr_gmina', 'label' => 'Gmina adresu siedziby/głównego miejsca wykonywania działalności', 'name' => 'company_addr_gmina', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_company_addr_city',  'label' => 'Miejscowość adresu siedziby/głównego miejsca wykonywania działalności', 'name' => 'company_addr_city', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_company_addr_street', 'label' => 'Ulica adresu siedziby/głównego miejsca wykonywania działalności', 'name' => 'company_addr_street', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_company_addr_no',    'label' => 'Nr nieruchomości (siedziba)', 'name' => 'company_addr_no', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_company_addr_local', 'label' => 'Nr lokalu (siedziba)', 'name' => 'company_addr_local', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_company_addr_zip',   'label' => 'Kod pocztowy (siedziba)', 'name' => 'company_addr_zip', 'type' => 'text', 'wrapper' => ['width' => '33.333']),

            // --- TAB: Lokal sprzedaży ---
            array(
                'key' => 'inv_tab_sales',
                'label' => 'Lokalizacja sprzedaży',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array('key' => 'inv_sales_woj',    'label' => 'Województwo (sprzedaż)', 'name' => 'sales_woj',    'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_powiat', 'label' => 'Powiat (sprzedaż)', 'name' => 'sales_powiat', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_gmina',  'label' => 'Gmina (sprzedaż)', 'name' => 'sales_gmina',  'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_city',   'label' => 'Miejscowość (sprzedaż)', 'name' => 'sales_city',   'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_street', 'label' => 'Ulica (sprzedaż)', 'name' => 'sales_street', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_no',     'label' => 'Nr nieruchomości (sprzedaż)', 'name' => 'sales_no', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_local',  'label' => 'Nr lokalu (sprzedaż)', 'name' => 'sales_local', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_zip',    'label' => 'Kod pocztowy (sprzedaż)', 'name' => 'sales_zip', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_sales_extra',  'label' => 'Dodatkowe lokalizacje sprzedaży', 'name' => 'sales_extra_locations', 'type' => 'textarea', 'rows' => 2),

            // --- TAB: Lokalizacja przedsięwzięcia ---
            array(
                'key' => 'inv_tab_proj',
                'label' => 'Lokalizacja przedsięwzięcia/zadania',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array('key' => 'inv_proj_woj',    'label' => 'Województwo (przedsięwzięcie)', 'name' => 'proj_woj',   'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_proj_powiat', 'label' => 'Powiat (przedsięwzięcie)', 'name' => 'proj_powiat', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_proj_gmina',  'label' => 'Gmina (przedsięwzięcie)', 'name' => 'proj_gmina', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_proj_city',   'label' => 'Miejscowość (przedsięwzięcie)', 'name' => 'proj_city',  'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_proj_street', 'label' => 'Ulica (przedsięwzięcie)', 'name' => 'proj_street', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_proj_no',     'label' => 'Nr nieruchomości (przedsięwzięcie)', 'name' => 'proj_no', 'type' => 'text', 'wrapper' => ['width' => '33.333']),
            array('key' => 'inv_proj_zip',    'label' => 'Kod pocztowy (przedsięwzięcie)', 'name' => 'proj_zip', 'type' => 'text', 'wrapper' => ['width' => '33.333']),

            // --- TAB: Kontakt/Prospekt ---
            array(
                'key' => 'inv_tab_contact',
                'label' => 'Kontakt / Prospekt',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array('key' => 'inv_contact_method', 'label' => 'Sposób kontaktu (np. tel/mail/formularz)', 'name' => 'contact_method', 'type' => 'text', 'wrapper' => ['width' => '50']),
            array('key' => 'inv_prospekt_url',   'label' => 'URL prospektu informacyjnego', 'name' => 'prospekt_url', 'type' => 'url', 'wrapper' => ['width' => '50']),

            // --- TAB: Prawa niezbędne do korzystania ---
            array(
                'key' => 'inv_tab_rights',
                'label' => 'Prawa niezbędne do korzystania',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array('key' => 'inv_rights_desc', 'label' => 'Wyszczególnienie praw', 'name' => 'rights_desc', 'type' => 'textarea', 'rows' => 2, 'wrapper' => ['width' => '50']),
            array('key' => 'inv_rights_value', 'label' => 'Wartość praw [PLN]', 'name' => 'rights_value', 'type' => 'number', 'step' => '0.01', 'min'  => 0, 'wrapper' => ['width' => '25']),
            array('key' => 'inv_rights_date', 'label' => 'Data, od której obowiązuje wartość', 'name' => 'rights_date', 'type' => 'date_picker', 'display_format' => 'Y-m-d', 'return_format'  => 'Y-m-d', 'wrapper' => ['width' => '25']),

            // --- TAB: Inne świadczenia pieniężne ---
            array(
                'key' => 'inv_tab_other_payments',
                'label' => 'Inne świadczenia pieniężne',
                'type' => 'tab',
                'placement' => 'top',
            ),
            array('key' => 'inv_other_desc', 'label' => 'Wyszczególnienie rodzajów świadczeń', 'name' => 'other_payments_desc', 'type' => 'textarea', 'rows' => 2, 'wrapper' => ['width' => '50']),
            array('key' => 'inv_other_value', 'label' => 'Wartość świadczeń [PLN]', 'name' => 'other_payments_value', 'type' => 'number', 'step' => '0.01', 'min'  => 0, 'wrapper' => ['width' => '25']),
            array('key' => 'inv_other_date', 'label' => 'Data, od której obowiązuje wartość', 'name' => 'other_payments_date', 'type' => 'date_picker', 'display_format' => 'Y-m-d', 'return_format'  => 'Y-m-d', 'wrapper' => ['width' => '25']),

        ),

        'location' => array(
            array(
                array(
                    'param' => 'taxonomy',
                    'operator' => '==',
                    'value' => 'inwestycje',
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
