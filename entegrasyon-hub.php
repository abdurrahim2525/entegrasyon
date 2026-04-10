<?php
/**
 * Plugin Name: Entegrasyon Hub for WooCommerce
 * Description: WooCommerce ürünlerini tek panelden pazaryerlerine hazırlamak için SEO, görsel, stok, kargo ve fatura alanları sağlar.
 * Version: 0.1.0
 * Author: Entegrasyon Team
 * Requires Plugins: woocommerce
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('ENTEGRASYON_HUB_FILE')) {
    define('ENTEGRASYON_HUB_FILE', __FILE__);
}

if (! defined('ENTEGRASYON_HUB_PATH')) {
    define('ENTEGRASYON_HUB_PATH', plugin_dir_path(__FILE__));
}

require_once ENTEGRASYON_HUB_PATH . 'includes/class-entegrasyon-hub.php';

add_action('plugins_loaded', static function () {
    \EntegrasyonHub\Plugin::init();
});
