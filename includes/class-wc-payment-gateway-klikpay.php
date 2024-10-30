<?php

/**
 * klikpay Payments Gateway.
 *
 * Provides a klikpay Mobile Payments Payment Gateway.
 *
 * @class       WC_Gateway_Klikpay
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce/Classes/Payment
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
const AUTHORIZATION_ENDPOINT = 'https://pay.oxlin.io/v1/orders/';
const TOKEN_ENDPOINT         = 'https://pay.oxlin.io/token/';

//------------------
class WC_Gateway_Klikpay extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		// Setup general properties.
		$this->klikpay_setup_properties();

		// Load the settings.
		$this->klikpay_init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->api_key            = $this->get_option( 'api_key' );
		$this->widget_id          = $this->get_option( 'widget_id' );
		$this->IBAN_key           = $this->get_option( 'IBAN_key' );
		$this->IBAN_name          = $this->get_option( 'IBAN_name' );
		$this->instructions       = $this->get_option( 'instructions' );
		$this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
		$this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'klikpay_thankyou_page' ) );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'klikpay_change_payment_complete_order_status' ), 10, 3 );

		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'klikpay_email_instructions' ), 10, 3 );
		
		// Add custom condition when saving payment gateway settings
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'klikpay_save_payment_gateway_settings'));
		add_action( 'wp_enqueue_scripts', array( $this, 'klikpay_enqueue_styles' ) );
	}
	public function klikpay_enqueue_styles() {
		// Use plugins_url() to get the correct URL to your CSS file
		$plugin_url = plugins_url( 'klikpay-styles.css', __FILE__ );
	
		// Enqueue your CSS
		wp_enqueue_style( 'klikpay-style', $plugin_url );
	}
	
	public function klikpay_save_payment_gateway_settings() {

		//Faire les call api
		//---------------OAuth2 pour klikpay.fr---------------------------------
		$client_IDklik = $this->get_option('api_key');
		$clientSecret_klik = $this->get_option('widget_id');

		//--------------API REQUEST TO CHECK AVAIABLE----------------------------
		// Prepare the request parameters
        $params = array(
            "client_id" => $client_IDklik,
            "client_secret" => $clientSecret_klik,
            "grant_type" => "client_credentials" // Make sure to include the grant type
        );

        // Set the request URL
        $tokenurl = "https://klikpay.fr/wp-json/myplugin/v1/iban";

        // Make the request using WordPress HTTP API
        $response = wp_safe_remote_post($tokenurl, array(
            'body' => $params
        ));
        
        // Check if the request was successful
        if (is_wp_error($response)) {
            // Handle error
			echo '<div class="notice notice-error"><p>Error: ' . esc_html($response->get_error_message()). '</p></div>';
        } else {
            // Parse the response
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // Check if the response contains an error message
            if (isset($data['error'])) {
                echo '<div class="notice notice-error"><p> API Error: ' . esc_html($data['error']) . '</p></div>';
            } else {
				// Check if the keys exist before accessing them
				$API_IBAN_key = isset($data['IBAN']) ? $data['IBAN'] : '';
				$API_IBAN_name = isset($data['IBAN_name']) ? $data['IBAN_name'] : '';
				$APIstatus = isset($data['active']) ? $data['active'] : '';
            }
        }

		//---------------------------------------------------
		// Set the enabled value based on the IBAN key value
		$enabled = ($APIstatus === '1') ? 'yes' : 'no';
		if ($APIstatus === '0'){
			echo '<div class="notice notice-error"><p>Votre compte est inactif, veuillez contacter le support sur dashboard.klikpay.fr dans l\'onglet support</p></div>';
			$this->settings['enabled'] = 'no';
		}elseif($APIstatus === '1'){
			// Update the enabled setting
			if($this->get_option('enabled') == 'yes'){
				$this->settings['enabled'] = $enabled;
			}
			$this->settings['IBAN_key'] = $API_IBAN_key;
			$this->settings['IBAN_name'] = $API_IBAN_name;
		}else{
			echo '<div class="notice notice-error"><p>Vos identifiants ne sont pas correct, veuillez contacter le support sur dashboard.klikpay.fr dans l\'onglet support</p></div>';
			$this->settings['enabled'] = 'no';
		}
		$this->settings['description'] =  '<section>
		<p>
			<b>Payez par virement instantané depuis votre banque en toute sécurité.</b>
		</p>
	
		<p><i>Comment ça marche ?</i></p>
	
		<div class="f-steps">
			<div class="f-step-wrapper">
				<div class="f-step-icon f-step-1-icon">
					<div class="f-step-img f-step-1-img"></div>
				</div>
				<p>Sélectionnez votre banque</p>
			</div>
			<div class="f-step-wrapper">
				<div class="f-step-icon f-step-2-icon">
					<div class="f-step-img f-step-2-img"></div>
				</div>
				<p>Identifiez-vous</p>
			</div>
			<div class="f-step-wrapper">
				<div class="f-step-icon f-step-3-icon">
					<div class="f-step-img f-step-3-img"></div>
				</div>
				<p>Validez la transaction</p>
			</div>
		</div>
	</section>';
		// Update the settings in the database
		update_option('woocommerce_' . $this->id . '_settings', $this->settings);
	
	}
	/**
	 * Setup general properties for the gateway.
	 */
	protected function klikpay_setup_properties() {
		$this->id                 = 'klikpay';
		$this->icon               = apply_filters( 'woocommerce_klikpay_icon', plugins_url('../assets/icon.png', __FILE__ ) );
		$this->method_title       = __( 'Klikpay secure', 'klikpay-payments-woo' );
		$this->api_key            = __( 'Add API Key', 'klikpay-payments-woo' );
		$this->widget_id          = __( 'Add Widget ID', 'klikpay-payments-woo' );
		$this->IBAN_key           = __( 'Add IBAN Key', 'klikpay-payments-woo' );
		$this->IBAN_name           = __( 'Add IBAN name', 'klikpay-payments-woo' );
		$this->method_description = __( 'Paiements par klikpay secure.', 'klikpay-payments-woo' );
		$this->has_fields         = false;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function klikpay_init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __( 'Activer/Désactiver', 'klikpay-payments-woo' ),
				'label'       => __( 'Activer Klikpay sécure', 'klikpay-payments-woo' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
				//'custom_attributes' => array(
				//	'disabled' => 'disabled',
				//),
			),
			'title'              => array(
				'title'       => __( 'Titre', 'klikpay-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Déscription visible par le client lors de sélection des paiements', 'klikpay-payments-woo' ),
				'default'     => __( 'Virement instantané', 'klikpay-payments-woo' ),
				'desc_tip'    => true,
				'custom_attributes' => array( 'readonly' => 'readonly' ), 
			),
			'api_key'             => array(
				'title'       => __( 'Client ID', 'klikpay-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Ajouter votre clé API', 'klikpay-payments-woo' ),
				'desc_tip'    => true,
			),
			'widget_id'           => array(
				'title'       => __( 'Client secret', 'klikpay-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Ajouter le client secret', 'klikpay-payments-woo' ),
				'desc_tip'    => true,
			),
			'IBAN_key'        => array(
				'title'       => __( 'IBAN de votre magasin', 'klikpay-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Votre IBAN', 'klikpay-payments-woo' ),
				'desc_tip'    => true,
				'custom_attributes' => array( 'readonly' => 'readonly' ),
			),
			'IBAN_name'        => array(
				'title'       => __( 'Nom du bénificiaire (IBAN)', 'klikpay-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Nom de votre IBAN', 'klikpay-payments-woo' ),
				'desc_tip'    => true,
				'custom_attributes' => array( 'readonly' => 'readonly' ),
			),
			'description'        => array(
				'title'       => __( 'Déscription', 'klikpay-payments-woo' ),
				'type'    => 'title',
				//'description' => __( 'Déscription visible par le client sur le site', 'klikpay-payments-woo' ),
				//'default'     => __( 'Payez en virements instantanés avec Klikpay', 'klikpay-payments-woo' ),
				'default'     => '<section>
				<p>
					<b>Payez par virement instantané depuis votre banque en toute sécurité.</b>
				</p>
			
				<p><i>Comment ça marche ?</i></p>
			
				<div class="f-steps">
					<div class="f-step-wrapper">
						<div class="f-step-icon f-step-1-icon">
							<div class="f-step-img f-step-1-img"></div>
						</div>
						<p>Sélectionnez votre banque</p>
					</div>
					<div class="f-step-wrapper">
						<div class="f-step-icon f-step-2-icon">
							<div class="f-step-img f-step-2-img"></div>
						</div>
						<p>Identifiez-vous</p>
					</div>
					<div class="f-step-wrapper">
						<div class="f-step-icon f-step-3-icon">
							<div class="f-step-img f-step-3-img"></div>
						</div>
						<p>Validez la transaction</p>
					</div>
				</div>
			</section>',
				'desc_tip'    => true,
			),
			'instructions'       => array(
				'title'       => __( '', 'klikpay-payments-woo' ),
				'type'        => 'hidden',
				//'description' => __( 'Instructions qui seront ajoutées à la page de remerciements.', 'klikpay-payments-woo' ),
				'default'     => __( 'Klikpay paiements avant livraison.', 'klikpay-payments-woo' ),
				'desc_tip'    => false,
			),
			'enable_for_methods' => array(
				'title'             => __( 'Activer pour le mode de livraison', 'klikpay-payments-woo' ),
				'type'              => 'multiselect',
				'class'             => 'wc-enhanced-select',
				'css'               => 'width: 400px;',
				'default'           => '',
				'description'       => __( 'Si KlikPay est uniquement disponible pour certaines méthodes, configurez-le ici. Laissez vide pour l\'activer pour toutes les méthodes.', 'klikpay-payments-woo' ),
				'options'           => $this->klikpay_load_shipping_method_options(),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-placeholder' => __( 'Selectionner la méthode de livraison', 'klikpay-payments-woo' ),
				),
			),
			'enable_for_virtual' => array(
				'title'   => __( 'Accepter les commandes virtuelles', 'klikpay-payments-woo' ),
				'label'   => __( 'Accepter klikpay pour les commandes virtuelles', 'klikpay-payments-woo' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
		);
	}


	/**
	 * Check If The Gateway Is Available For Use.
	 *
	 * @return bool
	 */
	public function klikpay_is_available() {
		$order          = null;
		$needs_shipping = false;

		// Test if shipping is needed first.
		if ( WC()->cart && WC()->cart->needs_shipping() ) {
			$needs_shipping = true;
		} elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = wc_get_order( $order_id );

			// Test if order needs shipping.
			if ( 0 < count( $order->get_items() ) ) {
				foreach ( $order->get_items() as $item ) {
					$_product = $item->get_product();
					if ( $_product && $_product->needs_shipping() ) {
						$needs_shipping = true;
						break;
					}
				}
			}
		}

		$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

		// Virtual order, with virtual disabled.
		if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
			return false;
		}

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
		if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
			$order_shipping_items            = is_object( $order ) ? $order->get_shipping_methods() : false;
			$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

			if ( $order_shipping_items ) {
				$canonical_rate_ids = $this->klikpay_get_canonical_order_shipping_item_rate_ids( $order_shipping_items );
			} else {
				$canonical_rate_ids = $this->klikpay_get_canonical_package_rate_ids( $chosen_shipping_methods_session );
			}

			if ( ! count( $this->klikpay_get_matching_rates( $canonical_rate_ids ) ) ) {
				return false;
			}
		}

		return parent::klikpay_is_available();
	}

	/**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	private function klikpay_is_accessing_settings() {
		if ( is_admin() ) {
			// phpcs:disable WordPress.Security.NonceVerification
			if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['section'] ) || 'klikpay' !== $_REQUEST['section'] ) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification

			return true;
		}

		return false;
	}

	/**
	 * Loads all of the shipping method options for the enable_for_methods field.
	 *
	 * @return array
	 */
	private function klikpay_load_shipping_method_options() {
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if ( ! $this->klikpay_is_accessing_settings() ) {
			return array();
		}

		$data_store = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();

		foreach ( $raw_zones as $raw_zone ) {
			$zones[] = new WC_Shipping_Zone( $raw_zone );
		}

		$zones[] = new WC_Shipping_Zone( 0 );

		$options = array();
		foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

			$options[ $method->get_method_title() ] = array();

			// Translators: %1$s shipping method name.
			$options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'klikpay-payments-woo' ), $method->get_method_title() );

			foreach ( $zones as $zone ) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

					if ( $shipping_method_instance->id !== $method->id ) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf( __( '%1$s (#%2$s)', 'klikpay-payments-woo' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf( __( '%1$s &ndash; %2$s', 'klikpay-payments-woo' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'klikpay-payments-woo' ), $option_instance_title );

					$options[ $method->get_method_title() ][ $option_id ] = $option_title;
				}
			}
		}

		return $options;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
	 * @return array $canonical_rate_ids    Rate IDs in a canonical format.
	 */
	private function klikpay_get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

		$canonical_rate_ids = array();

		foreach ( $order_shipping_items as $order_shipping_item ) {
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
	 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
	 */
	private function klikpay_get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

		$shipping_packages  = WC()->shipping()->get_packages();
		$canonical_rate_ids = array();

		if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
			foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
				if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
					$chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
					$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
				}
			}
		}

		return $canonical_rate_ids;
	}

	/**
	 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
	 *
	 * @since  3.4.0
	 *
	 * @param array $rate_ids Rate ids to check.
	 * @return boolean
	 */
	private function klikpay_get_matching_rates( $rate_ids ) {
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique( array_merge( array_intersect( $this->enable_for_methods, $rate_ids ), array_intersect( $this->enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( $order->get_total() > 0 ) {
			$this->klikpay_payment_processing();
		} else {
			$order->payment_complete();
		}

		//---------------OAuth2 pour klikpay.fr---------------------------------
		$client_IDklik = $this->api_key;
		$clientSecret_klik = $this->widget_id;

		//---------- API CHECK---------------
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
                $APIstatus = $data['active'];
            }
        }

		//----A REMETTRE !!!!!!!!!!-----------
		//--------Is account activated---------
		if ($data['active'] == 0) {
			 //Set the enabled value based on the IBAN key value
			$enabled = 'no';
		
			// Update the enabled setting
			$this->settings['enabled'] = $enabled;
		
			// Update the settings in the database
			update_option('woocommerce_' . $this->id . '_settings', $this->settings);
		
			// Return thankyou redirect.
			return array(
				'result'   => 'failed',
				'redirect' => get_return_url( $order ),
			);
		}

		//---------------------------------------------------

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	private function klikpay_payment_processing() {

	}

	/**
	 * Output for the order received page.
	 */
	public function klikpay_thankyou_page($order_id) {
		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
		}
		//getorder
		$order = wc_get_order( $order_id );

		//---------------OAuth2 pour klikpay.fr---------------------------------
		$client_IDklik = $this->api_key;
		$clientSecret_klik = $this->widget_id;

		//---------------OAuth2 pour klikpay.fr---------------------------------
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
        
        // Check if the request was successful
        if (is_wp_error($response)) {
            // Handle error
            echo "Error: " . esc_html($response->get_error_message());
        } else {
            // Parse the response
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
			$api_key = $data['clientID_klik'];
			$widget_id = $data['clientSecret_klik'];
			$APIstatus = $data['active'];
        }

		
		//--------Is account activated---------
		if ($data['active'] == 0) {
			// Set the enabled value based on the IBAN key value
			$enabled = 'no';
		
			// Update the enabled setting
			$this->settings['enabled'] = $enabled;
		
			// Update the settings in the database
			update_option('woocommerce_' . $this->id . '_settings', $this->settings);

			wc_update_order_status($order->get_id(), 'failed', 'Order set to failed on the thank you page.', true);
			exit();
		}

		$clientID = $data['clientID_klik'];
		$clientSecret=$data['clientSecret_klik'];
		//---------------------------------------------------

		//------------variables-------------------------------------
		$orderA=$order;
		$total =  $order->get_total() ;
		$IBAN = $this->IBAN_key;
		
		//------------Information sur l'ordre woocommerce----------------------
		// Get an instance of the WC_Order Object from the Order ID (if required)
		$order = wc_get_order( $order_id );
		// Get the Customer ID (User ID)
		$customer_id = $order->get_customer_id(); // Or $order->get_user_id();	
		// Customer billing information details
		$billing_first_name = $order->get_billing_first_name();
		$billing_last_name  = $order->get_billing_last_name();
		//nom prenom du client
		$nomPrenom = $billing_last_name . " " . $billing_first_name;
		//Nom du shop
		$beneficiaire = $this->IBAN_name;
		//creation du label
		$labelpaiement = "Commande : " . strval($order_id) . " sur " . strval(get_bloginfo( 'name' )) . " - Client : " . $customer_id;
		//lien de redirection
		$thankyoukey=$order->get_order_key();
		//if ( is_user_logged_in() ) {
		//	$paymentcompletelink = strval(get_site_url() . '/my-account/view-order/' . $order_id . '/');
		//  } else {
		//	$paymentcompletelink = strval(get_site_url() . '/checkout/order-received/');
		//  }
		if ( is_user_logged_in() ) {		
			$woocommerce_version = defined('WC_VERSION') ? WC_VERSION : '0'; // Get WooCommerce version
			$current_locale = get_locale();
		
			if (class_exists('WooCommerce') && version_compare($woocommerce_version, '8.0', '<')) {
				// WordPress version is less than 6.2
				if ($current_locale === 'fr_FR') {
					// Language is set to French
					$paymentcompletelink = strval(get_site_url() . '/mon-compte/view-order/' . $order_id . '/');
				} else {
					// Default link for other languages
					$paymentcompletelink = strval(get_site_url() . '/my-account/view-order/' . $order_id . '/');
				}
			} else {
				// WordPress version is 6.2 or newer
				$paymentcompletelink = strval(get_site_url() . '/my-account/view-order/' . $order_id . '/');
			}
		} else {
			$paymentcompletelink = strval(get_site_url() . '/checkout/order-received/');
		}
		$email = $order->get_billing_email();
		//---------------OAuth2---------------------------------
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
		}

		//--------------------------------
        //Generate Body for POST order
        $params = array(
            "redirect_url" => $paymentcompletelink,
            "amount" => $total,
            "currency" => "EUR",
			"email" => $email,
            "beneficiary" => array(
                "schema" => "SEPA",
                "iban" => $IBAN,
                "name" => $beneficiaire
            ),
            "label" => $labelpaiement                          
        );
		$params = json_encode($params);
        //Get access token Linxo
		$accessToken = "Bearer " . $token_data['access_token'];
        //Generate header for POST/GET
        $header = array( 
            'Content-Type' => 'application/json;charset=utf-8',
            'Authorization' =>  $accessToken
        );
		//-------------POST-------------
		$lienlinxo = "https://pay.oxlin.io/v1/orders/" ;
		$order_response = wp_safe_remote_post($lienlinxo, array(
			'headers' => $header,
			'body' => $params
		));

		// Check if the order request was successful
		if (is_wp_error($order_response)) {
			// Handle error
			echo "Order Request Error: " . esc_html($order_response->get_error_message());
		} else {
			// Parse the order response
			$order_body = wp_remote_retrieve_body($order_response);
			$order_data = json_decode($order_body, true);

		}
		//--------------------------
        //POST order
		//redirect to Linxo payment page-------------
        $url = strval($order_data['auth_url']);
		$idpaiement = strval($order_data['id']);
		//---ajoute le ID de paiement linkxo a la commande----- 
		update_post_meta( $order_id, 'id_paiement_linxo',$idpaiement);

		//---redirection vers linxo
		wp_redirect($url,301);
		exit(); 
	}


	/**
	 * Change payment complete order status to completed for klikpay orders.
	 *
	 * @since  3.1.0
	 * @param  string         $status Current order status.
	 * @param  int            $order_id Order ID.
	 * @param  WC_Order|false $order Order object.
	 * @return string
	 */
	public function klikpay_change_payment_complete_order_status( $status, $order_id = 0, $order = false ) {
		if ( $order && 'klikpay' === $order->get_payment_method() ) {
			$status = 'completed';
		}
		return $status;
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function klikpay_email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
		}
	}
	public function klikpay_unableSettings() {
		// Set the enabled value based on the IBAN key value
		$enabled = 'no';
		
		// Update the enabled setting
		$this->settings['enabled'] = $enabled;
	
		// Update the settings in the database
		update_option('woocommerce_' . $this->id . '_settings', $this->settings);
	

	}
}