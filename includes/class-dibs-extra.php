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
	 *
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

					require_once( WC_DIBS_PLUGIN_DIR . 'includes/dibs-subscriptions.php' );

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
						$order->add_order_note( __( 'DIBS transaction captured.', 'woocommerce-gateway-dibs' ) );
					} elseif ( ! empty( $response['wp_remote_note'] ) ) {
						// WP remote post problem
						$order->add_order_note( sprintf( __( 'DIBS transaction capture failed. WP Remote post problem: %s.', 'woocommerce-gateway-dibs' ), $response['wp_remote_note'] ) );
					} else {
						// DIBS capture problem
						$order->add_order_note( sprintf( __( 'DIBS transaction capture failed. Decline reason: %s.', 'woocommerce-gateway-dibs' ), $response['declineReason'] ) );
					}
				}
			}
		}
	}

}


$wc_gateway_dibs_extra = new WC_Gateway_Dibs_Extra;


/**
 *  Class for DIBS callback, since DIBS strips everything after ? in the callback url.
 * @class        WC_Gateway_Dibs_D2_MP_Extra
 * @since        1.3.3
 *
 **/
class WC_Gateway_Dibs_D2_MP_Extra {

	public function __construct() {

		$mp_settings                          = get_option( 'woocommerce_dibs_masterpass_settings' );
		$this->enabled                        = $mp_settings['enabled'];
		$this->display_cart_page_button       = $mp_settings['display_cart_page_button'];
		$this->display_cart_page_button_img   = $mp_settings['display_cart_page_button_img'];
		$this->display_pp_button              = $mp_settings['display_pp_button'];
		$this->display_pp_button_img          = $mp_settings['display_pp_button_img'];
		$this->display_cart_widget_button     = $mp_settings['display_cart_widget_button'];
		$this->display_cart_widget_button_img = $mp_settings['display_cart_widget_button_img'];

		// Actions
		add_action( 'template_redirect', array( $this, 'check_mp_purchase_from_product_page' ) );
		add_action( 'template_redirect', array( $this, 'check_mp_purchase_from_cart_page' ) );
		add_action( 'template_redirect', array( $this, 'check_mp_purchase_from_cart_widget' ) );

		// Display MasterPass button on single product page
		add_action( 'woocommerce_single_variation', array( $this, 'single_variable_masterpass_button' ), 30 );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'single_masterpass_button' ) );

		// Display MasterPass button in cart widget
		add_action( 'woocommerce_widget_shopping_cart_before_buttons', array(
			$this,
			'masterpass_button_cart_widget'
		) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// MasterPass button on cart page
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'masterpass_button_cart_page' ) );

		// Capture payment when order is set to Completed
		//add_action( 'woocommerce_order_status_completed', array( $this, 'capture_order_on_completion' ), 10, 1 );

		// Cancel DIBS transaction
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_transaction' ) );
	}

	/*
	 * Display MasterPass button on single variable product page
	 *
	 *
	 */
	public function single_variable_masterpass_button() {

		if ( 'yes' == $this->display_pp_button && 'yes' == $this->enabled && ! isset( $_REQUEST['add-to-cart'] ) ) {
			?>
			<div class="dibs-mp-pp variations_button">
				<p class="dibs-mp-pp-button">
					<button type="submit" name="mp_from_product_page" value="1"><img
							src="<?php echo $this->get_icon_url(); ?>"
							width="<?php echo $this->display_pp_button_img; ?>" alt="Buy with MasterPass"></button>
					<br/><a href="#" rel="external"
					        onclick="window.open('http://www.mastercard.com/mc_us/wallet/learnmore/en', '_blank', 'width=650,height=750,scrollbars=yes'); return false;">
						<small><?php echo $this->get_read_more_text(); ?></small>
					</a>
				</p>
			</div>
			<?php
		}
	}

	/*
	 * Display MasterPass button on single product page
	 *
	 *
	 */
	public function single_masterpass_button() {
		global $product;
		if ( $product->is_type( 'variable' ) ) {
			return;
		}
		if ( 'yes' == $this->display_pp_button && 'yes' == $this->enabled && ! isset( $_REQUEST['add-to-cart'] ) ) {
			?>
			<div class="dibs-mp-pp variations_button">
				<p class="dibs-mp-pp-button">
					<button type="submit" name="mp_from_product_page" value="1"><img
							src="<?php echo $this->get_icon_url(); ?>"
							width="<?php echo $this->display_pp_button_img; ?>" alt="Buy with MasterPass"></button>
					<br/><a href="#" rel="external"
					        onclick="window.open('<?php echo $this->get_read_more_url(); ?>', '_blank', 'width=650,height=750,scrollbars=yes'); return false;">
						<small><?php echo $this->get_read_more_text(); ?></small>
					</a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Display MasterPass button on cart page
	 **/
	function masterpass_button_cart_page() {

		if ( 'yes' == $this->display_cart_page_button && 'yes' == $this->enabled ) {
			?>
			<div class="dibs_brand_assets" style="margin: 0px;">
				<a href="<?php echo the_permalink() . '?mp_from_cart_page=1'; ?>"><img
						src="https://cdn.dibspayment.com/logo/checkout/single/horiz/DIBS_checkout_single_MasterPass.png"
						alt="DIBS - Payments made easy" width="185"/></a>

				<p><a href="#" rel="external"
				      onclick="window.open('<?php echo $this->get_read_more_url(); ?>', '_blank', 'width=650,height=750,scrollbars=yes'); return false;">
						<small><?php echo $this->get_read_more_text(); ?></small>
					</a></p>
			</div>
			<?php
		}
	}

	/**
	 * Display MasterPass button on cart widget
	 **/
	function masterpass_button_cart_widget() {

		if ( 'yes' == $this->display_cart_widget_button && 'yes' == $this->enabled ) {
			?>
			<p class="buttons dibs_brand_assets">
				<a href="<?php echo the_permalink() . '?mp_from_cart_widget=1'; ?>"><img
						src="https://cdn.dibspayment.com/logo/checkout/single/horiz/DIBS_checkout_single_MasterPass.png"
						alt="DIBS - Payments made easy"
						width="<?php echo $this->display_cart_widget_button_img; ?>"/></a><br/>
				<a href="#" rel="external"
				   onclick="window.open('<?php echo $this->get_read_more_url(); ?>', '_blank', 'width=650,height=750,scrollbars=yes'); return false;">
					<small><?php echo $this->get_read_more_text(); ?></small>
				</a>
			</p>
			<?php
		}
	}

	/**
	 * Check for MasterPass purchase directly from single product page
	 **/
	function check_mp_purchase_from_product_page() {

		if ( isset( $_POST['mp_from_product_page'] ) && '1' == woocommerce_clean( $_POST['mp_from_product_page'] ) ) {
			$callback = new WC_Gateway_Dibs_Masterpass;
			$callback->single_masterpass_button_mpinit();
		}
	} // End function check_mp_purchase_from_product_page()

	/**
	 * Check for MasterPass purchase from cart page
	 **/
	function check_mp_purchase_from_cart_page() {

		if ( isset( $_GET['mp_from_cart_page'] ) && '1' == woocommerce_clean( $_GET['mp_from_cart_page'] ) ) {
			$callback = new WC_Gateway_Dibs_Masterpass;
			$callback->cart_masterpass_button_mpinit();
		}
	}

	/**
	 * Check for MasterPass purchase from cart widget
	 **/
	function check_mp_purchase_from_cart_widget() {

		if ( isset( $_GET['mp_from_cart_widget'] ) && '1' == woocommerce_clean( $_GET['mp_from_cart_widget'] ) ) {
			$callback = new WC_Gateway_Dibs_Masterpass;
			$callback->cart_masterpass_button_mpinit();
		}
	}

	/**
	 * CSS MasterPass buy button on single product pages
	 */
	function enqueue_scripts() {
		//if ( 'yes' == $this->enabled && 'yes' == $this->display_cart_widget_button || ( is_product() && 'yes' == $this->display_pp_button ) ) {
		if ( 'yes' == $this->enabled ) {
			wp_enqueue_style( 'dibs-mp-style', WC_DIBS_PLUGIN_DIR . 'assets/css/style.css', array(), 1.22 );
		}
	}

	/**
	 * get_icon_url function.
	 *
	 * @return string
	 */
	public function get_icon_url() {
		$icon_url = 'https://cdn.dibspayment.com/logo/checkout/single/horiz/DIBS_checkout_single_MasterPass.png';

		return apply_filters( 'woocommerce_dibs_masterpass_icon_url', $icon_url );
	}

	/**
	 * get_icon function.
	 *
	 * @return string
	 */
	public function get_read_more_url() {
		// Get current customers selected language if this is a multi lanuage site
		$iso_code            = explode( '_', get_locale() );
		$this->shop_language = strtoupper( $iso_code[0] ); // Country ISO code (SE)

		if ( 'SV' == $this->shop_language ) {
			$read_more_url = 'http://www.mastercard.com/mc_us/wallet/learnmore/se';
		} else {
			$read_more_url = 'http://www.mastercard.com/mc_us/wallet/learnmore/en';
		}

		return apply_filters( 'woocommerce_dibs_masterpass_read_more_url', $read_more_url );
	}

	/**
	 * get_icon function.
	 *
	 * @return string
	 */
	public function get_read_more_text() {
		// Get current customers selected language if this is a multi lanuage site
		$iso_code            = explode( '_', get_locale() );
		$this->shop_language = strtoupper( $iso_code[0] ); // Country ISO code (SE)

		if ( 'SV' == $this->shop_language ) {
			$read_more_text = 'Läs mer';
		} else {
			$read_more_text = 'Learn more';
		}

		return apply_filters( 'woocommerce_dibs_masterpass_read_more_text', $read_more_text );
	}

	/**
	 * Capture payment in DIBS if option is enabled
	 * @link    http://tech.dibspayment.com/D2/Integrate/DPW/API/Payment_functions/CaptureTransaction
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

					require_once( 'dibs-subscriptions.php' );
					require_once( 'calculateMac.php' );

					// Refund request parameters
					$params = array(
						'merchantId'    => $merchant_id,
						'transactionId' => get_post_meta( $order_id, '_dibs_transaction_no', true ),
						'amount'        => get_post_meta( $order_id, '_order_total', true ) * 100,
					);

					// Calculate the MAC for the form key-values to be posted to DIBS.
					$MAC = calculateMac( $params, $dibs_cc->key_hmac );

					// Add MAC to the $params array
					$params['MAC'] = $MAC;

					$response = postToDIBS( 'CaptureTransaction', $params );

					if ( isset( $response['status'] ) && ( $response['status'] == "ACCEPT" ) ) {
						add_post_meta( $order_id, '_dibs_order_captured', 'yes' );
						$order->add_order_note( __( 'DIBS transaction captured.', 'woocommerce-gateway-dibs' ) );
					} elseif ( ! empty( $response['wp_remote_note'] ) ) {
						// WP remote post problem
						$order->add_order_note( sprintf( __( 'DIBS transaction capture failed. WP Remote post problem: %s.', 'woocommerce-gateway-dibs' ), $response['wp_remote_note'] ) );
					} else {
						// DIBS capture problem
						$order->add_order_note( sprintf( __( 'DIBS transaction capture failed. Decline reason: %s.', 'woocommerce-gateway-dibs' ), $response['declineReason'] ) );
					}
				}
			}
		}
	}

	/**
	 * Cancels an existing transaction using the CancelTransaction JSON service
	 *
	 * @param  $order_id WooCommerce order ID
	 *
	 * @return boolean
	 */
	function cancel_transaction( $order_id ) {
		$order                = wc_get_order( $order_id );
		$order_payment_method = $order->payment_method;

		// Do nothing if order's payment method doesn't allow automatic cancellation via DIBS
		$payment_method_option_name = 'woocommerce_' . $order_payment_method . '_settings';
		$payment_method_option      = get_option( $payment_method_option_name );
		if ( 'yes' != $payment_method_option['push_cancellation'] ) {
			return;
		}

		// Check if order was created using a DIBS payment method
		if ( 'dibs_masterpass' == $order_payment_method ) {
			$callback = new WC_Gateway_Dibs_Masterpass;
			// } elseif ( 'dibs_account_invoice' == $order_payment_method ) {
			// $callback = new WC_Gateway_Dibs_Account_Invoice;
		} else {
			return;
		}

		// Check if we have a DIBS transaction ID
		if ( $order->get_transaction_id() ) {
			$transaction_id = $order->get_transaction_id();
		} else {
			return;
		}

		// Make sure the order wasn't already cancelled
		if ( 'yes' == get_post_meta( $order->id, '_dibs_order_cancelled', true ) ) {
			return;
		}

		require_once( 'dibs-api-functions.php' );

		$merchant_id  = $callback->merchant_id;
		$key1         = $callback->key_1;
		$key2         = $callback->key_2;
		$api_username = $callback->api_username;
		$api_password = $callback->api_password;

		$postvars = 'merchant=' . $merchant_id . '&orderid=' . $order->get_order_number() . '&transact=' . $transaction_id;
		$md5key   = MD5( $key1 . MD5( $key2 . $postvars ) );

		// Refund parameters
		$params = array(
			'md5key'    => $md5key,
			'merchant'  => $merchant_id,
			'textreply' => 'yes',
			'transact'  => $transaction_id
		);

		$response = postToDIBS( 'CancelTransaction', $params, false, $api_username, $api_password );

		if ( $response['status'] == 'ACCEPTED' ) {
			// Cancel accepted
			$order->add_order_note( __( 'DIBS transaction cancelled.', 'woocommerce-gateway-dibs' ) );
			update_post_meta( $order->id, '_dibs_order_cancelled', 'yes' );

			return true;
		} else if ( $response['status'] == 'DECLINED' ) {
			// Cancellation problem
			$order->add_order_note( sprintf( __( 'DIBS transaction cancellation failed. Decline reason: %s.', 'woocommerce-gateway-dibs' ), $response['declineReason'] ) );

			return false;
		} else {
			// WP remote post problem
			$order->add_order_note( sprintf( __( 'DIBS transaction cancellation failed. WP Remote post problem: %s.', 'woocommerce-gateway-dibs' ), $response['wp_remote_note'] ) );

			return false;
		}
	}

} // End class WC_Gateway_Dibs_Extra
$wc_gateway_dibs_d2_mp_extra = new WC_Gateway_Dibs_D2_MP_Extra;