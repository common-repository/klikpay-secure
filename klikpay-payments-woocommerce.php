<?php
/**
 * Plugin Name: Klikpay secure
 * Plugin URI: https://klikpay.fr
 * Author: klikpay.fr
 * Author URI: https://klikpay.fr/comment-ca-marche/
 * Description: Virements sécurisés par klikpay.
 * Version: 1.0.2
 * License: GPL2
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: klikpay-payments-woo
 * 
 * Class WC_Gateway_Klikpay file.
 *
 * @package WooCommerce\Klikpay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_action( 'plugins_loaded', 'klikpay_payment_init', 11 );
add_filter( 'woocommerce_payment_gateways', 'add_to_woo_klikpay_payment_gateway');
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'klikpay_apd_settings_link' );

function klikpay_payment_init() {
    if( class_exists( 'WC_Payment_Gateway' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-payment-gateway-klikpay.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/klikpay-order-statuses.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/klikpay-checkout-description-fields.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/klikpay-orders-treatment.php';
	}
}

function add_to_woo_klikpay_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Klikpay';
    return $gateways;
}

function klikpay_apd_settings_link( array $links ) {
    $url = get_admin_url() . "admin.php?page=wc-settings&tab=checkout&section=klikpay";
    $settings_link = '<a href="' . $url . '">' . __('Paramètres', 'textdomain') . '</a>';
      $links[] = $settings_link;
    return $links;
  }



