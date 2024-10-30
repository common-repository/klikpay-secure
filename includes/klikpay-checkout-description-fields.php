<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
add_action( 'woocommerce_admin_order_data_after_billing_address', 'klikpay_order_data_after_billing_address', 10, 1 );


function klikpay_order_data_after_billing_address( $order ) {
    
    $payment_id = get_post_meta( $order->get_id(), 'id_paiement_linxo', true );

    if ( $payment_id ) {
        $sanitized_payment_id = sanitize_text_field( $payment_id );
        echo '<p><strong>' . esc_html__( 'Paiement ID', 'klikpay-payments-woo' ) . '</strong><br>' . esc_html( $payment_id ) . '</p>';
    }
}