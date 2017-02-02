<?php

/**
 *  Class for DIBS callback, since DIBS strips everything after ? in the callback url.
 * @class        WC_Gateway_Dibs_Extra
 * @since        1.3.3
 *
 **/
class WC_Gateway_Dibs_Extra {

	public function __construct() {
		// Actions
		add_action( 'init', array( &$this, 'check_callback' ), 20 );

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
		if ( strpos( $_SERVER["REQUEST_URI"], 'woocommerce/dibscancel' ) !== false ) {

			header( "HTTP/1.1 200 Ok" );

			$callback = new WC_Gateway_Dibs_CC;
			$callback->cancel_order( stripslashes_deep( $_REQUEST ) );

			return;
		}

		// Check for IPN callback (dibscallback)
		if ( ( strpos( $_SERVER["REQUEST_URI"], 'woocommerce/dibscallback' ) !== false ) ) {
			header( "HTTP/1.1 200 Ok" );

			// The IPN callback and buyer-return-to-shop callback can be fired at the same time causing multiple payment_complete() calls.
			// Let's pause this callback.
			sleep( 2 );

			$callback = new WC_Gateway_Dibs_CC;
			$callback->successful_request( stripslashes_deep( $_REQUEST ) );
		}

		// Check for buyer-return-to-shop callback
		if ( ( strpos( $_SERVER["REQUEST_URI"], 'woocommerce/dibsaccept' ) !== false ) ) {
			header( "HTTP/1.1 200 Ok" );

			$callback = new WC_Gateway_Dibs_CC;
			$callback->successful_request( stripslashes_deep( $_REQUEST ) );
		}

	}

	/**
	 * Calculate totals on checkout page.
	 *
	 * @param $totals
	 *
	 * @return mixed
	 */
	public function calculate_totals( $totals ) {
		global $woocommerce;
		if ( is_checkout() || defined( 'WOOCOMMERCE_CHECKOUT' ) ) {

			$available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();

			$current_gateway = '';
			if ( ! empty( $available_gateways ) ) {
				// Chosen Method
				if ( isset( $woocommerce->session->chosen_payment_method ) && isset( $available_gateways[ $woocommerce->session->chosen_payment_method ] ) ) {
					$current_gateway = $available_gateways[ $woocommerce->session->chosen_payment_method ];
				} elseif ( isset( $available_gateways[ get_option( 'woocommerce_default_gateway' ) ] ) ) {
					$current_gateway = $available_gateways[ get_option( 'woocommerce_default_gateway' ) ];
				} else {
					$current_gateway = current( $available_gateways );
				}
			}

			if ( $current_gateway->id == 'dibs_invoice' ) {

				$current_gateway_id = $current_gateway->id;
				$this->add_fee_to_cart();
			}
		} // End if is checkout
		return $totals;
	}

	/**
	 * Add the invoice fee to the cart if DIBS Invoice is selected payment method
	 * and if invoice fee is used.
	 */
	function add_fee_to_cart() {
		global $woocommerce;

		$invoice_fee          = new WC_Gateway_Dibs_Invoice;
		$this->invoice_fee_id = $invoice_fee->get_dibs_invoice_fee_product();

		if ( $this->invoice_fee_id > 0 ) {
			$product = get_product( $this->invoice_fee_id );

			if ( $product ) :

				// Is this a taxable product?
				if ( $product->is_taxable() ) {
					$product_tax = true;
				} else {
					$product_tax = false;
				}

				$woocommerce->cart->add_fee( $product->get_title(), $product->get_price_excluding_tax(), $product_tax, $product->get_tax_class() );

			endif;
		}
	}

	/**
	 * Capture payment in DIBS if option is enabled
	 *
	 * @param $order_id int
	 * @link  http://tech.dibspayment.com/D2_capturecgi
	 */
	function capture_order_on_completion( $order_id ) {
		if ( is_object( $order_id ) ) {
			$order_id = $order_id->id;
		}

		$dibs_cc = new WC_Gateway_Dibs_CC;
		$order   = new WC_Order( $order_id );

		// Check if capture on completed option is selected
		if ( 'complete' == $dibs_cc->get_capturenow() ) {
			// Check if DIBS transaction number exists
			if ( get_post_meta( $order_id, '_dibs_transaction_no', true ) ) {

				// Check if payment has already been captured
				if ( 'yes' != get_post_meta( $order_id, '_dibs_order_captured', true ) ) {

					$merchant_id = $dibs_cc->get_merchant_id();
					$key1        = $dibs_cc->get_key_1();
					$key2        = $dibs_cc->get_key_2();
					$transact    = get_post_meta( $order_id, '_dibs_transaction_no', true );
					$amount      = get_post_meta( $order_id, '_order_total', true ) * 100;

					$postvars = 'merchant=' . $merchant_id . '&orderid=' . $order->get_order_number() . '&transact=' . $transact . '&amount=' . $amount;
					$md5key   = MD5( $key2 . MD5( $key1 . $postvars ) );

					require_once( WC_DIBS_PLUGIN_DIR . 'includes/dibs-api-functions.php' );

					// Capture parameters
					$params = array(
						'amount'   => $amount,
						'md5key'   => $md5key,
						'merchant' => $merchant_id,
						'orderid'  => $order->get_order_number(),
						'transact' => $transact
					);

					// Post request to DIBS
					$response = postToDIBS( 'CaptureTransaction', $params, false );

					if ( isset( $response['status'] ) && ( $response['status'] == "ACCEPT" || $response['status'] == "ACCEPTED" ) ) {
						add_post_meta( $order_id, '_dibs_order_captured', 'yes' );
						$order->add_order_note( __( 'DIBS transaction captured.', 'dibs-for-woocommerce' ) );
					} elseif ( ! empty( $response['wp_remote_note'] ) ) {
						// WP remote post problem
						$order->add_order_note( sprintf( __( 'DIBS transaction capture failed. WP Remote post problem: %s.', 'dibs-for-woocommerce' ), $response['wp_remote_note'] ) );
					} else {
						// DIBS capture problem
						$order->add_order_note( sprintf( __( 'DIBS transaction capture failed. Decline reason: %s.', 'dibs-for-woocommerce' ), $response['declineReason'] ) );
					}
				}
			}
		}
	}

}
$wc_gateway_dibs_extra = new WC_Gateway_Dibs_Extra;