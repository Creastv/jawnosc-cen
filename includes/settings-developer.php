<?php
if (!defined('ABSPATH')) exit;

class DGE_Settings
{
    const OPT = 'dge_developer_data';

    /** Domyślne wartości opcji (tylko dane stałe dewelopera) */
    public static function defaults()
    {
        return [
            // Identyfikacja dewelopera
            'dev_name'        => '',
            'dev_legal_form'  => '',
            'dev_krs'         => '',
            'dev_ceidg'       => '',
            'dev_nip'         => '',
            'dev_regon'       => '',

            // Kontakt ogólny
            'dev_phone'       => '',
            'dev_email'       => '',
            'dev_fax'         => '',
            'dev_www'         => '',

            // Adres siedziby / głównego miejsca wykonywania działalności
            'dev_addr_woj'    => '',
            'dev_addr_powiat' => '',
            'dev_addr_gmina'  => '',
            'dev_addr_city'   => '',
            'dev_addr_street' => '',
            'dev_addr_no'     => '',
            'dev_addr_local'  => '',
            'dev_addr_zip'    => '',
        ];
    }

    /** Pobierz opcje (z domyślnymi) */
    public static function get()
    {
        $opt = get_option(self::OPT, []);
        return wp_parse_args($opt, self::defaults());
    }

    /** Inicjalizacja rejestracji ustawień */
    public static function init()
    {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /** Rejestracja strony ustawień i pól (tylko dane dewelopera) */
    public static function register_settings()
    {
        register_setting('dge_dev_group', self::OPT);

        add_settings_section(
            'dge_dev_main',
            __('Dane dewelopera do eksportu CSV', 'dge'),
            function () {
                echo '<p>Uzupełnij stałe dane dewelopera. Dane zależne od inwestycji (sprzedaż, lokalizacja przedsięwzięcia, kontakt, prospekt) ustawiasz teraz w taksonomii <strong>„inwestycje”</strong>.</p>';
            },
            'dge_dev'
        );

        // Klucze → Etykiety
        $fields = [
            'dev_name'        => 'Nazwa dewelopera',
            'dev_legal_form'  => 'Forma prawna dewelopera',
            'dev_krs'         => 'Nr KRS',
            'dev_ceidg'       => 'Nr wpisu do CEiDG',
            'dev_nip'         => 'Nr NIP',
            'dev_regon'       => 'Nr REGON',
            'dev_phone'       => 'Nr telefonu',
            'dev_email'       => 'Adres poczty elektronicznej',
            'dev_fax'         => 'Nr faxu',
            'dev_www'         => 'Adres strony internetowej dewelopera',

            'dev_addr_woj'    => 'Województwo adresu siedziby/głównego miejsca wykonywania działalności',
            'dev_addr_powiat' => 'Powiat adresu siedziby/głównego miejsca wykonywania działalności',
            'dev_addr_gmina'  => 'Gmina adresu siedziby/głównego miejsca wykonywania działalności',
            'dev_addr_city'   => 'Miejscowość adresu siedziby/głównego miejsca wykonywania działalności',
            'dev_addr_street' => 'Ulica adresu siedziby/głównego miejsca wykonywania działalności',
            'dev_addr_no'     => 'Nr nieruchomości (siedziba)',
            'dev_addr_local'  => 'Nr lokalu (siedziba)',
            'dev_addr_zip'    => 'Kod pocztowy (siedziba)',
        ];

        foreach ($fields as $key => $label) {
            add_settings_field(
                $key,
                esc_html($label),
                [__CLASS__, 'render_text_input'],
                'dge_dev',
                'dge_dev_main',
                ['key' => $key]
            );
        }
    }

    /** Render pojedynczego inputa tekstowego */
    public static function render_text_input($args)
    {
        $key = $args['key'];
        $opt = self::get();
        printf(
            '<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" />',
            esc_attr(self::OPT),
            esc_attr($key),
            esc_attr($opt[$key] ?? '')
        );
    }

    /** Render strony „Dane gov → Dane dewelopera” (formularz) */
    public static function render_page()
    {
?>
<div class="wrap">
    <h1><?php esc_html_e('Dane dewelopera', 'dge'); ?></h1>
    <form method="post" action="options.php">
        <?php
                settings_fields('dge_dev_group');
                do_settings_sections('dge_dev');
                submit_button();
                ?>
    </form>
</div>
<?php
    }
}

DGE_Settings::init();