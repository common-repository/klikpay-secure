<?php

/**
 * Add new Invoiced status for woocommerce
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
add_action( 'init', 'register_klikpay_order_statuses' );
function register_klikpay_order_statuses() {
    register_post_status( 'wc-invoiced', array(
        'label'                     => _x( 'Invoiced', 'Order status', 'klikpay-payments-woo' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Invoiced <span class="count">(%s)</span>', 'Invoiced<span class="count">(%s)</span>', 'klikpay-payments-woo' )
    ) );
}

add_filter( 'wc_order_statuses', 'klikpay_wc_order_statuses' );

// Register in wc_order_statuses.
function klikpay_wc_order_statuses( $order_statuses ) {
    $order_statuses['wc-invoiced'] = _x( 'Invoiced', 'Order status', 'klikpay-payments-woo' );
    return $order_statuses;
}

// Add custom order status
function klikpay_ref_register_order_status() {
    register_post_status( 'wc-refuspay', array(
        'label'                     => __( 'Paiement refusé', 'woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Paiement refusé <span class="count">(%s)</span>', 'Paiement refusé <span class="count">(%s)</span>', 'woocommerce' )
    ) );
}
add_action( 'init', 'klikpay_ref_register_order_status' );

// Add custom order status to list of order statuses
function klikpay_add_order_statuses( $order_statuses ) {
    $new_order_statuses = array();
 
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
 
        if ( 'wc-cancelled' === $key ) {
            $new_order_statuses['wc-refuspay'] = __( 'Paiement refusé', 'woocommerce' );
        }
    }
 
    return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'klikpay_add_order_statuses' );

?>