<?php
/**
 * Plugin Name: ThueAPI – Tạo VietQR thanh toán tự động!
 * Plugin URI: https://thueapi.com
 * Version: 1.2.0
 * Description: Tạo VietQR giúp thanh toán tự động cho các đơn hàng bằng hình thức chuyển khoản tại Việt Nam như: Vietcombank, Techcombank, ACB, Momo, MBBank, TPBank, VPBank...
 * Author: #CODETAY
 * Author URI: http://codetay.com
 * Tested up to: 6.4.2
 * WC tested up to: 8.4.0
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined('ABSPATH') or exit('Code your dream');

if (! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

add_action('plugins_loaded', function () {
    require_once plugin_basename('classes/wc-thueapi.php');
    require_once plugin_basename('classes/thueapi-react-blocks.php');
}, 11);

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('thueapi', plugin_dir_url(__FILE__).'assets/js/app.js', [], '1.0.0', true);
    wp_enqueue_style('thueapi', plugin_dir_url(__FILE__).'assets/css/app.css', [], '1.0.0', 'all');
});

add_action('admin_enqueue_scripts', function () {
    wp_enqueue_script('thueapi', plugin_dir_url(__FILE__).'assets/js/app.admin.js', [], '1.0.0', true);
    wp_enqueue_style('thueapi', plugin_dir_url(__FILE__).'assets/css/app.admin.css', [], '1.0.0', 'all');
});

add_action('init', function () {
    register_post_status('wc-over-paid', [
        'label' => 'Thanh toán dư',
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Thanh toán dư (%s)', 'Thanh toán dư (%s)'),
    ]);

    register_post_status('wc-less-paid', [
        'label' => 'Thanh toán thiếu',
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Thanh toán thiếu (%s)', 'Thanh toán thiếu (%s)'),
    ]);

    add_filter('wc_order_statuses', function ($orderStatuses) {
        $newOrderStatuses = [];

        foreach ($orderStatuses as $key => $status) {
            $newOrderStatuses[$key] = $status;
        }

        return array_merge($newOrderStatuses, [
            'wc-over-paid' => __('Thanh toán dư'),
            'wc-less-paid' => __('Thanh toán thiếu'),
        ]);
    });
});

add_action('before_woocommerce_init', function () {
    if (class_exists(FeaturesUtil::class)) {
        FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = ThueAPI_Gateway::class;

        return $gateways;
    });
});

add_action('woocommerce_blocks_loaded', function () {
    add_action(
        'woocommerce_blocks_checkout_block_registration',
        function ($integration_registry) {
            $integration_registry->register(new ThueAPI_React_Blocks());
        }
    );
});

add_filter('plugin_action_links_'.plugin_basename(__FILE__), function ($links) {
    $actionLinks = [
        'premium_plugins' => sprintf('<a href="https://codetay.com"  target="_blank" style="color: #e64a19; font-weight: bold; font-size: 108%%;" title="%s">%s</a>', __('Premium Plugins'), __('Premium Plugins')),
        'settings' => sprintf('<a href="%s" title="%s">%s</a>', admin_url('admin.php?page=wc-settings&tab=checkout&section=thueapi'), __('Thiết lập'), __('Thiết lập')),
    ];

    return array_merge($actionLinks, $links);
});
