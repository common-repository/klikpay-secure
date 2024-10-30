<?php

//code pour afficher et traiter les ordres en cours
// Add a new menu item to the WooCommerce admin dashboard
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
add_action('init', 'klikpay_webhook_receiver');
//---------------------------------------------------------
//-------------code for the webhook------------------------
//---------------------------------------------------------
// Handle the webhook notification
function klikpay_webhook_receiver() {
    if (isset($_GET['resource_type']) && isset($_GET['resource_id'])) { //ajouter un if type = order ?????????????????
        // Extract the resource type and ID parameters from the webhook URL
        $resource_type = sanitize_text_field($_GET['resource_type']);
        $resource_id = sanitize_text_field($_GET['resource_id']);
		
        // Include the class-wc-payment-gateway-klikpay.php file
        include_once plugin_dir_path( __FILE__ ) . '/class-wc-payment-gateway-klikpay.php';


        // Create an instance of the WC_Gateway_Klikpay class to access the api_key variable
        $klikpay_gateway = new WC_Gateway_Klikpay();

        //---------------OAuth2 pour klikpay.fr---------------------------------
 		$client_IDklik = $klikpay_gateway->api_key;
		$clientSecret_klik = $klikpay_gateway->widget_id;

		//---------------------------------------------------
        // Prepare the request parameters
        $params = array(
            "client_id" => $client_IDklik,
            "client_secret" => $clientSecret_klik,
            "grant_type" => "client_credentials" // Make sure to include the grant type
        );

        // Set the request URL
        $tokenurl = "https://klikpay.fr/wp-json/myplugin/v1/tokenauth";

        // Make the request using WordPress HTTP API
        $response = wp_safe_remote_post($tokenurl, array(
            'body' => $params
        ));
        print_r($response);
        // Check if the request was successful
        if (is_wp_error($response)) {
            // Handle error
            echo "Error: " . esc_html($response->get_error_message());
        } else {
            // Parse the response
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // Check if the response contains an error message
            if (isset($data['error'])) {
                echo "API Error: " . esc_html($data['error']);
            } else {
                $api_key = $data['clientID_klik'];
                $widget_id = $data['clientSecret_klik'];
                $activeAPI = $data['active'];
            }
        }
        //---------------OAuth2---------------------------------
        //-------------------------------------------------------------------------------
        // Prepare the access token request parameters
        $token_params = array(
            "client_id" => $api_key,
            "client_secret" => $widget_id,
            "grant_type" => "client_credentials"
        );

        // Set the token endpoint URL
        $token_endpoint = TOKEN_ENDPOINT; // Replace with the actual endpoint URL

        // Make the access token request using WordPress HTTP API
        $token_response = wp_safe_remote_post($token_endpoint, array(
            'body' => $token_params
        ));

        // Check if the access token request was successful
        if (is_wp_error($token_response)) {
            // Handle error
            echo "Access Token Request Error: " . esc_html($token_response->get_error_message());
        } else {
            // Parse the access token response
            $token_body = wp_remote_retrieve_body($token_response);
            $token_data = json_decode($token_body, true);

            // Check if the response contains an error message
            if (isset($token_data['error'])) {
                echo "Access Token API Error: " . esc_html($token_data['error']);
            } else {
                $access_token = "Bearer " . $token_data['access_token'];

                // Generate headers for GET request
                $headers = array(
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Authorization' => $access_token
                );

                // GET order
                $lienlinxo = "https://pay.oxlin.io/v1/reporting/orders/" . $resource_id;
                $order_response = wp_safe_remote_get($lienlinxo, array(
                    'headers' => $headers
                ));

                // Check if the order request was successful
                if (is_wp_error($order_response)) {
                    // Handle error
                    echo "Order Request Error: " . esc_html($order_response->get_error_message());
                } else {
                    // Parse the order response
                    $order_body = wp_remote_retrieve_body($order_response);
                    $order_data = json_decode($order_body, true);

                    // Get the order status
                    $response = $order_data['order_status'];

                }
            }
        }


        //------MAJ du statut de la commande--------------------------------
        // Get all orders using the WC_Order_Query class
        $order_query = new WC_Order_Query( array(
            'limit' => -1,
            'status' => array( 'pending','on-hold'),
            'return' => 'ids',
        ) );
        $orders = $order_query->get_orders();

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            $payment_id = $order->get_meta('id_paiement_linxo');
            if (!empty($payment_id)) { //condition - > paiement klikpay
                if($payment_id==$resource_id){
                    // Update the order status to "completed" if the status is "pending payment"
                    if ($response=='FAILED') {
                        $order->update_status('failed');
                    }elseif($response=='REJECTED'){
                        $order->update_status('refuspay'); 
                    } elseif ($response=='CLOSED') {
                        $order->update_status('processing'); 
                    } elseif ($response=='EXPIRED') {
                        $order->update_status('failed');
                    }elseif ($response=='AUTHORIZED') {
                        $order->update_status('on-hold');
                    }
                    // Save the changes.
                    $order->save();
                }
            }
        }

        //-----------------------------------------------------------------------------------------------
        // Send a response back to the API provider to confirm that you received the webhook notification
        http_response_code(200);
        echo 'Webhook received for resource type: ' . esc_html($resource_type) . ', resource ID: ' . esc_html($resource_id);
       echo $activeAPI;
        //--------Is account activated---------
		if ($activeAPI == 0) {
			$klikpay_gateway->klikpay_unableSettings();
		}
        exit;
    }
}