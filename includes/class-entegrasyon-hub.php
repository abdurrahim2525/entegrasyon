<?php

namespace EntegrasyonHub;

if (! defined('ABSPATH')) {
    exit;
}

class Plugin
{
    private const OPTION_KEY = 'entegrasyon_hub_marketplaces';

    /** @var array<string, string> */
    private static array $marketplaces = [
        'trendyol'   => 'Trendyol',
        'amazon'     => 'Amazon',
        'etsy'       => 'Etsy',
        'ozon'       => 'Ozon',
        'hepsiburada'=> 'Hepsiburada',
        'pazarama'   => 'Pazarama',
        'idefix'     => 'İdefix',
    ];

    public static function init(): void
    {
        if (! class_exists('WooCommerce')) {
            add_action('admin_notices', [self::class, 'missingWooCommerceNotice']);
            return;
        }

        add_action('admin_menu', [self::class, 'registerAdminPage']);
        add_action('admin_init', [self::class, 'registerSettings']);

        add_filter('woocommerce_product_data_tabs', [self::class, 'registerProductTab']);
        add_action('woocommerce_product_data_panels', [self::class, 'renderProductFields']);
        add_action('woocommerce_process_product_meta', [self::class, 'saveProductFields']);
    }

    public static function missingWooCommerceNotice(): void
    {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Entegrasyon Hub eklentisi için WooCommerce etkin olmalıdır.', 'entegrasyon-hub');
        echo '</p></div>';
    }

    public static function registerAdminPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Pazaryeri Entegrasyon', 'entegrasyon-hub'),
            __('Pazaryeri Entegrasyon', 'entegrasyon-hub'),
            'manage_woocommerce',
            'entegrasyon-hub',
            [self::class, 'renderAdminPage']
        );
    }

    public static function registerSettings(): void
    {
        register_setting('entegrasyon_hub_settings', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitizeSettings'],
            'default' => [],
        ]);

        add_settings_section(
            'entegrasyon_hub_marketplaces_section',
            __('Pazaryeri Bağlantıları', 'entegrasyon-hub'),
            static function (): void {
                echo '<p>' . esc_html__('Her pazaryeri için API bilgilerinizi girin. İlk adımda sadece saklama yapılır, otomatik gönderim roadmap aşamasındadır.', 'entegrasyon-hub') . '</p>';
            },
            'entegrasyon_hub_settings'
        );

        foreach (self::$marketplaces as $slug => $label) {
            add_settings_field(
                "{$slug}_api_key",
                sprintf(__('%s API Key', 'entegrasyon-hub'), $label),
                [self::class, 'renderApiKeyField'],
                'entegrasyon_hub_settings',
                'entegrasyon_hub_marketplaces_section',
                ['slug' => $slug]
            );

            add_settings_field(
                "{$slug}_seller_id",
                sprintf(__('%s Seller ID', 'entegrasyon-hub'), $label),
                [self::class, 'renderSellerField'],
                'entegrasyon_hub_settings',
                'entegrasyon_hub_marketplaces_section',
                ['slug' => $slug]
            );
        }
    }

    /** @param mixed $settings */
    public static function sanitizeSettings($settings): array
    {
        if (! is_array($settings)) {
            return [];
        }

        $clean = [];
        foreach (self::$marketplaces as $slug => $_label) {
            $clean[$slug]['api_key'] = sanitize_text_field($settings[$slug]['api_key'] ?? '');
            $clean[$slug]['seller_id'] = sanitize_text_field($settings[$slug]['seller_id'] ?? '');
        }

        return $clean;
    }

    /** @param array<string, string> $args */
    public static function renderApiKeyField(array $args): void
    {
        $options = get_option(self::OPTION_KEY, []);
        $slug = $args['slug'];
        $value = $options[$slug]['api_key'] ?? '';

        printf(
            '<input type="password" class="regular-text" name="%1$s[%2$s][api_key]" value="%3$s" autocomplete="off" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($slug),
            esc_attr($value)
        );
    }

    /** @param array<string, string> $args */
    public static function renderSellerField(array $args): void
    {
        $options = get_option(self::OPTION_KEY, []);
        $slug = $args['slug'];
        $value = $options[$slug]['seller_id'] ?? '';

        printf(
            '<input type="text" class="regular-text" name="%1$s[%2$s][seller_id]" value="%3$s" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($slug),
            esc_attr($value)
        );
    }

    public static function renderAdminPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Pazaryeri Entegrasyon Merkezi', 'entegrasyon-hub') . '</h1>';

        echo '<form method="post" action="options.php">';
        settings_fields('entegrasyon_hub_settings');
        do_settings_sections('entegrasyon_hub_settings');
        submit_button(__('Ayarları Kaydet', 'entegrasyon-hub'));
        echo '</form>';

        echo '<hr />';
        echo '<h2>' . esc_html__('Ürün Operasyon Paneli', 'entegrasyon-hub') . '</h2>';
        echo '<p>' . esc_html__('Aşağıda WooCommerce ürünlerinizin stok/kargo/fatura özetini tek panelde görebilirsiniz.', 'entegrasyon-hub') . '</p>';
        self::renderProductTable();

        echo '</div>';
    }

    public static function renderProductTable(): void
    {
        $products = wc_get_products([
            'limit' => 25,
            'status' => ['publish', 'draft'],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Ürün', 'entegrasyon-hub') . '</th>';
        echo '<th>' . esc_html__('SKU', 'entegrasyon-hub') . '</th>';
        echo '<th>' . esc_html__('Stok', 'entegrasyon-hub') . '</th>';
        echo '<th>' . esc_html__('Kargo Sınıfı', 'entegrasyon-hub') . '</th>';
        echo '<th>' . esc_html__('Fatura Profili', 'entegrasyon-hub') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($products)) {
            echo '<tr><td colspan="5">' . esc_html__('Henüz ürün bulunamadı.', 'entegrasyon-hub') . '</td></tr>';
        }

        foreach ($products as $product) {
            $invoice = get_post_meta($product->get_id(), '_eh_invoice_profile', true);

            echo '<tr>';
            echo '<td><a href="' . esc_url(get_edit_post_link($product->get_id())) . '">' . esc_html($product->get_name()) . '</a></td>';
            echo '<td>' . esc_html($product->get_sku() ?: '-') . '</td>';
            echo '<td>' . esc_html((string) $product->get_stock_quantity()) . '</td>';
            echo '<td>' . esc_html($product->get_shipping_class() ?: '-') . '</td>';
            echo '<td>' . esc_html($invoice ?: '-') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * @param array<string, mixed> $tabs
     * @return array<string, mixed>
     */
    public static function registerProductTab(array $tabs): array
    {
        $tabs['eh_marketplace'] = [
            'label'  => __('Pazaryeri SEO', 'entegrasyon-hub'),
            'target' => 'eh_marketplace_data',
            'class'  => [],
            'priority' => 80,
        ];

        return $tabs;
    }

    public static function renderProductFields(): void
    {
        echo '<div id="eh_marketplace_data" class="panel woocommerce_options_panel">';

        woocommerce_wp_text_input([
            'id' => '_eh_seo_title',
            'label' => __('Pazaryeri SEO Başlığı', 'entegrasyon-hub'),
            'desc_tip' => true,
            'description' => __('Marketplace ürün başlığı için optimize edilmiş başlık.', 'entegrasyon-hub'),
        ]);

        woocommerce_wp_textarea_input([
            'id' => '_eh_seo_description',
            'label' => __('Pazaryeri Açıklaması', 'entegrasyon-hub'),
            'description' => __('Trendyol, Amazon, Etsy vb. için uzun açıklama.', 'entegrasyon-hub'),
        ]);

        woocommerce_wp_text_input([
            'id' => '_eh_image_override',
            'label' => __('Harici Görsel URL', 'entegrasyon-hub'),
            'desc_tip' => true,
            'description' => __('Pazaryerine gönderimde kullanılacak alternatif görsel URL.', 'entegrasyon-hub'),
        ]);

        woocommerce_wp_text_input([
            'id' => '_eh_cargo_template',
            'label' => __('Kargo Şablonu', 'entegrasyon-hub'),
            'desc_tip' => true,
            'description' => __('Kargo desi, süre ve şirket kuralı için kısa etiket.', 'entegrasyon-hub'),
        ]);

        woocommerce_wp_text_input([
            'id' => '_eh_invoice_profile',
            'label' => __('Fatura Profili', 'entegrasyon-hub'),
            'desc_tip' => true,
            'description' => __('E-fatura/e-arşiv gibi profil adı.', 'entegrasyon-hub'),
        ]);

        echo '</div>';
    }

    public static function saveProductFields(int $postId): void
    {
        $map = [
            '_eh_seo_title',
            '_eh_seo_description',
            '_eh_image_override',
            '_eh_cargo_template',
            '_eh_invoice_profile',
        ];

        foreach ($map as $field) {
            if (! isset($_POST[$field])) {
                continue;
            }

            update_post_meta($postId, $field, sanitize_textarea_field(wp_unslash((string) $_POST[$field])));
        }
    }
}
