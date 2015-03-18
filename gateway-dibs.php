<?php
/*
Plugin Name: WooCommerce DIBS FlexWin Gateway
Plugin URI: http://woocommerce.com
Description: Extends WooCommerce. Provides a <a href="http://www.http://www.dibspayment.com/" target="_blank">DIBS</a> gateway for WooCommerce.
Version: 2.0.1
Author: Krokedil
Author URI: http://krokedil.com
*/

/*  Copyright 2011-2014  Krokedil Produktionsbyrå AB  (email : info@krokedil.se)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
    
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), 'a76c47dcf644f3ca7264357776c7da58', '18602' );

// Init DIBS Gateway after WooCommerce has loaded
add_action('plugins_loaded', 'init_dibs_gateway', 0);

function init_dibs_gateway() {

	// If the WooCommerce payment gateway class is not available, do nothing
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
	class WC_Gateway_Dibs extends WC_Payment_Gateway {
		
		public function __construct() { 
			global $woocommerce;
			
			// Currency
			$this->selected_currency = get_woocommerce_currency();
			
		}
		    
	} // Close class WC_Gateway_Dibs

	
	/**
	 * Localisation
	 */
	load_plugin_textdomain( 'woocommerce-gateway-dibs', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	
	/*
	 * Constants
	 */
	 
	// Plugin Folder Path
	if ( ! defined( 'WC_DIBS_PLUGIN_DIR' ) ) {
		define( 'WC_DIBS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	}

	// Plugin Folder URL
	if ( ! defined( 'WC_DIBS_PLUGIN_URL' ) ) {
		define( 'WC_DIBS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}
			
	/**
	 * Include the WooCommerce Compatibility Utility class
	 * The purpose of this class is to provide a single point of compatibility functions for dealing with supporting multiple versions of WooCommerce (currently 2.0.x and 2.1)
	 */
	require_once 'classes/class-wc-dibs-compatibility.php';
	
	// Include our Dibs credit card class
	require_once 'class-dibs-cc.php';

	// Include our Dibs Invoice class
	require_once 'class-dibs-invoice.php';
	
	// Check if we should include the Dibs manual modification class
	if ( defined( 'WC_DIBS_DEBUG' ) && true === WC_DIBS_DEBUG ) {
		require_once( 'includes/class-wc-dibs-manual-modification.php' );
	}

	

} // Close init_dibs_gateway

/**
 * Add the gateway to WooCommerce
 **/
function add_dibs_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Dibs_CC';
	$methods[] = 'WC_Gateway_Dibs_Invoice';
	return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_dibs_gateway' );




/**
 *  Class for DIBS callback, since DIBS strips everything after ? in the callback url.
 * @class 		WC_Gateway_Dibs_Extra
 * @since		1.3.3
 *
 **/

class WC_Gateway_Dibs_Extra {
	
	public function __construct() {
		
		
		// Actions
		add_action('init', array(&$this, 'check_callback'), 20);
		
		// Add Invoice fee via the new Fees API
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'calculate_totals' ), 10, 1 );

		// Capture payment when order is set to Completed
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture_order_on_completion' ), 10, 1 );
		
	}

	
	/**
	* Check for DIBS Response
	**/
	function check_callback() {
		
		// Cancel order POST
		if ( strpos($_SERVER["REQUEST_URI"], 'woocommerce/dibscancel') !== false) {
			
			header("HTTP/1.1 200 Ok");
			
			$callback = new WC_Gateway_Dibs_CC;
			$callback->cancel_order(stripslashes_deep($_REQUEST));
			return;
		}
			
		// Check for IPN callback (dibscallback)
		if ( ( strpos($_SERVER["REQUEST_URI"], 'woocommerce/dibscallback') !== false )  )  {

			header("HTTP/1.1 200 Ok");
			
			// The IPN callback and buyer-return-to-shop callback can be fired at the same time causing multiple payment_complete() calls.
			// Let's pause this callback.
			sleep(2);
			
			$callback = new WC_Gateway_Dibs_CC;
			$callback->successful_request( stripslashes_deep($_REQUEST) );

		} // End if
		
		// Check for buyer-return-to-shop callback
		if ( ( strpos($_SERVER["REQUEST_URI"], 'woocommerce/dibsaccept') !== false )  )  {

			header("HTTP/1.1 200 Ok");
			
			$callback = new WC_Gateway_Dibs_CC;
			$callback->successful_request( stripslashes_deep($_REQUEST) );

		} // End if
		
	} // End function check_callback()
	
	
	
	/**
	 * Calculate totals on checkout form.
	 **/
	 
	public function calculate_totals( $totals ) {
    	global $woocommerce;
		if(is_checkout() || defined('WOOCOMMERCE_CHECKOUT') ) {
		
			$available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
		
			$current_gateway = '';
			if ( ! empty( $available_gateways ) ) {
				// Chosen Method
				if ( isset( $woocommerce->session->chosen_payment_method ) && isset( $available_gateways[ $woocommerce->session->chosen_payment_method ] ) ) {
					$current_gateway = $available_gateways[ $woocommerce->session->chosen_payment_method ];
				} elseif ( isset( $available_gateways[ get_option( 'woocommerce_default_gateway' ) ] ) ) {
            		$current_gateway = $available_gateways[ get_option( 'woocommerce_default_gateway' ) ];
				} else {
            		$current_gateway =  current( $available_gateways );

				}
			
			}
		
			if($current_gateway->id=='dibs_invoice'){
		
        		$current_gateway_id = $current_gateway -> id;
        		$this->add_fee_to_cart();
			
			}
		} // End if is checkout
		return $totals;
	}

	
		
	/**
	 * Add the invoice fee to the cart if DIBS Invoice is selected payment method and if invoice fee is used.
	 **/
	 function add_fee_to_cart() {
		 global $woocommerce;
		 	
		 $invoice_fee = new WC_Gateway_Dibs_Invoice;
		 $this->invoice_fee_id = $invoice_fee->get_dibs_invoice_fee_product();
		 
		 if ( $this->invoice_fee_id > 0 ) {
		 	$product = get_product($this->invoice_fee_id);
		 
		 	if ( $product ) :
		 	
		 		// Is this a taxable product?
		 		if ( $product->is_taxable() ) {
		 			$product_tax = true;
		 		} else {
			 		$product_tax = false;
			 	}
    	   		
			 	$woocommerce->cart->add_fee($product->get_title(),$product->get_price_excluding_tax(),$product_tax,$product->get_tax_class());
    	    
			endif;
		} // End if invoice_fee_id > 0
		
	} // End function add_fee_to_cart
	
	/**
	 * Capture payment in DIBS if option is enabled
	 * @link    http://tech.dibspayment.com/D2/Integrate/DPW/API/Payment_functions/CaptureTransaction
	 */
	function capture_order_on_completion( $order_id ) {
		
		if ( is_object( $order_id ) ) {
			$order_id = $order_id->id;
		}
		
		$dibs_cc = new WC_Gateway_Dibs_CC;
		$order = new WC_Order( $order_id );

		// Check if capture on completed option is selected
		if ( 'complete' == $dibs_cc->get_capturenow() ) {

			// Check if DIBS transaction number exists
			if ( get_post_meta( $order_id, '_dibs_transaction_no', true ) ) {
				
				// Check if payment has already been captured
				if ( 'yes' != get_post_meta( $order_id, '_dibs_order_captured', true ) ) {				
					$merchant_id = $dibs_cc->get_merchant_id();

					require_once( 'dibs-subscriptions.php' );
					require_once( 'calculateMac.php' );
					
					// Refund request parameters
					$params = array	(
						'merchantId'    => $merchant_id,
						'transactionId' => get_post_meta( $order_id, '_dibs_transaction_no', true ),
						'amount'        => get_post_meta( $order_id, '_order_total', true ) * 100,
					);

					// Calculate the MAC for the form key-values to be posted to DIBS.
					$MAC = calculateMac( $params, $dibs_cc->key_hmac );
					
					// Add MAC to the $params array
					$params['MAC'] = $MAC;

					$response = postToDIBS( 'CaptureTransaction', $params );
					$this->log->add( 'dibs', 'Capture response: ' . var_export($response, true) );
			  		if ( isset( $response['status'] ) && ( $response['status'] == "ACCEPT" ) ) {
						add_post_meta( $order_id, '_dibs_order_captured', 'yes' );
						$order->add_order_note( __( 'DIBS transaction captured.', 'woocommerce-gateway-dibs' ) );
					} elseif ( ! empty($response['wp_remote_note'] ) ) {
						// WP remote post problem
						$order->add_order_note( sprintf( __( 'DIBS transaction capture failed. WP Remote post problem: %s.', 'woocommerce-gateway-dibs' ), $response['wp_remote_note'] ) );
					} else {
						// DIBS capture problem
						$order->add_order_note( sprintf( __( 'DIBS transaction capture failed. Decline reason: %s.', 'woocommerce-gateway-dibs'), $response['declineReason'] ) );
					}

				}

			}

		}

	}	

} // End class WC_Gateway_Dibs_Extra

$wc_gateway_dibs_extra = new WC_Gateway_Dibs_Extra;