<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for MasterPass related functions that happens outside of the general payment gateway class.
 * @class 		WC_Gateway_Dibs_MasterPass_Functions
 * @since		2.4
 *
 **/

class WC_Gateway_Dibs_MasterPass_Functions {
	
	public function __construct() {
		
		$mp_settings = get_option( 'woocommerce_dibs_masterpass_settings' );
		$this->enabled = $mp_settings['enabled'];
		$this->display_cart_page_button = $mp_settings['display_cart_page_button'];
		$this->display_cart_page_button_img = $mp_settings['display_cart_page_button_img'];
		$this->display_pp_button = $mp_settings['display_pp_button'];
		$this->display_pp_button_img = $mp_settings['display_pp_button_img'];
		$this->display_cart_widget_button = $mp_settings['display_cart_widget_button'];
		$this->display_cart_widget_button_img = $mp_settings['display_cart_widget_button_img'];
		
		// Actions
		add_action('template_redirect', array( $this, 'check_mp_purchase_from_product_page') );
		add_action('template_redirect', array( $this, 'check_mp_purchase_from_cart_page') );
		add_action('template_redirect', array( $this, 'check_mp_purchase_from_cart_widget') );
		
		// Display MasterPass button on single product page
		add_action( 'woocommerce_single_variation', array( $this, 'single_variable_masterpass_button' ), 30 );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'single_masterpass_button' )) ;
		
		// Display MasterPass button in cart widget
		add_action( 'woocommerce_widget_shopping_cart_before_buttons', array( $this, 'masterpass_button_cart_widget' ) );
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// MasterPass button on cart page
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'masterpass_button_cart_page' ) );
		
		// Cancel DIBS transaction
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_transaction' ) );
		
	}

	
	/*
	 * Display MasterPass button on single variable product page
	 *
	 *
	 */
	public function single_variable_masterpass_button(){
		
		if( 'yes' == $this->display_pp_button && 'yes' == $this->enabled && !isset( $_REQUEST['add-to-cart'] ) ) {
			global $product;
			?>
			<div class="dibs-mp-pp variations_button">
				<p class="dibs-mp-pp-button">
					<button type="submit" formaction="/?mp_from_product_page=1" name="add-to-cart" class="mp-add-to-cart"><img src="<?php echo $this->get_icon_url();?>" width="<?php echo $this->display_pp_button_img;?>" alt="Buy with MasterPass"></button>
					<br/><a href="#" rel="external" onclick="window.open('http://www.mastercard.com/mc_us/wallet/learnmore/en', '_blank', 'width=650,height=750,scrollbars=yes'); return false;"><small><?php echo $this->get_read_more_text();?></small></a>
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
	public function single_masterpass_button(){
		global $product;
		if( $product->is_type( 'variable' ) ) {
			return;
		}
		if( 'yes' == $this->display_pp_button && 'yes' == $this->enabled && !isset( $_REQUEST['add-to-cart'] ) ) {
			?>
			<div class="dibs-mp-pp variations_button">
				<p class="dibs-mp-pp-button">
					<button type="submit" formaction="/?mp_from_product_page=1" name="add-to-cart" class="mp-add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>"><img src="<?php echo $this->get_icon_url();?>" width="<?php echo $this->display_pp_button_img;?>" alt="Buy with MasterPass"></button>
					<br/><a href="#" rel="external" onclick="window.open('<?php echo $this->get_read_more_url();?>', '_blank', 'width=650,height=750,scrollbars=yes'); return false;"><small><?php echo $this->get_read_more_text();?></small></a>
				</p>
			</div>
			<?php
		}
	}
	
	
	/**
	 * Display MasterPass button on cart page
	**/
	function masterpass_button_cart_page() {
		
		if( 'yes' == $this->display_cart_page_button && 'yes' == $this->enabled ) {
			?>
			<div class="dibs_brand_assets" style="margin: 0px;">
				<a href="<?php echo the_permalink() . '?mp_from_cart_page=1';?>"><img src="https://cdn.dibspayment.com/logo/checkout/single/horiz/DIBS_checkout_single_MasterPass.png" alt="DIBS - Payments made easy" width="185"/></a>
			
			<p><a href="#" rel="external" onclick="window.open('<?php echo $this->get_read_more_url();?>', '_blank', 'width=650,height=750,scrollbars=yes'); return false;"><small><?php echo $this->get_read_more_text();?></small></a></p>
			</div>
			<?php
		}

	}
	
	
	/**
	 * Display MasterPass button on cart widget
	**/
	function masterpass_button_cart_widget() {
		
		if( 'yes' == $this->display_cart_widget_button && 'yes' == $this->enabled ) {
			?>
				<p class="buttons dibs_brand_assets">
					<a href="<?php echo the_permalink() . '?mp_from_cart_widget=1';?>"><img src="https://cdn.dibspayment.com/logo/checkout/single/horiz/DIBS_checkout_single_MasterPass.png" alt="DIBS - Payments made easy" width="<?php echo $this->display_cart_widget_button_img;?>"/></a><br/>
					<a href="#" rel="external" onclick="window.open('<?php echo $this->get_read_more_url();?>', '_blank', 'width=650,height=750,scrollbars=yes'); return false;"><small><?php echo $this->get_read_more_text();?></small></a>
				</p>
			<?php
		}

	}
		
	
	/**
	 * Check for MasterPass purchase directly from single product page
	**/
	function check_mp_purchase_from_product_page() {
		
		if ( isset($_GET['mp_from_product_page']) && '1' == woocommerce_clean($_GET['mp_from_product_page']) ) {
			$callback = new WC_Gateway_Dibs_MasterPass_New;
			$callback->single_masterpass_button_mpinit();
		}

	} // End function check_mp_purchase_from_product_page()
	
	
	/**
	 * Check for MasterPass purchase from cart page
	**/
	function check_mp_purchase_from_cart_page() {
		
		if ( isset($_GET['mp_from_cart_page']) && '1' == woocommerce_clean($_GET['mp_from_cart_page']) ) {
			$callback = new WC_Gateway_Dibs_MasterPass_New;
			$callback->cart_masterpass_button_mpinit();
		}

	}
	
	
	/**
	 * Check for MasterPass purchase from cart widget
	**/
	function check_mp_purchase_from_cart_widget() {
		
		if ( isset($_GET['mp_from_cart_widget']) && '1' == woocommerce_clean($_GET['mp_from_cart_widget']) ) {
			$callback = new WC_Gateway_Dibs_MasterPass_New;
			$callback->cart_masterpass_button_mpinit();
		}

	}
	

	
	
	/**
 	 * CSS MasterPass buy button on single product pages
 	 */
	function enqueue_scripts() {
		//if ( 'yes' == $this->enabled && 'yes' == $this->display_cart_widget_button || ( is_product() && 'yes' == $this->display_pp_button ) ) {
		if ( 'yes' == $this->enabled ) {
			wp_enqueue_style( 'dibs-mp-style', WC_DIBS_PLUGIN_URL . 'assets/css/masterpass.css', array(), 1.22 );
			wp_register_script( 'dibs-mp-add-to-cart', WC_DIBS_PLUGIN_URL . 'assets/js/masterpass-add-to-cart.js', array( 'wc-add-to-cart-variation' ), WC_DIBS_VERSION );
			wp_enqueue_script( 'dibs-mp-add-to-cart' );
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
		$iso_code = explode( '_', get_locale() );
		$this->shop_language = strtoupper( $iso_code[0] ); // Country ISO code (SE)
		
		if( 'SV' == $this->shop_language ) {
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
		$iso_code = explode( '_', get_locale() );
		$this->shop_language = strtoupper( $iso_code[0] ); // Country ISO code (SE)
		
		if( 'SV' == $this->shop_language ) {
			$read_more_text = 'Läs mer';
		} else {
			$read_more_text = 'Learn more';
		}
		return apply_filters( 'woocommerce_dibs_masterpass_read_more_text', $read_more_text );
	}
	
	
		
	
	/**
	 * Cancels an existing transaction using the CancelTransaction JSON service
	 *
	 * @param  $order_id WooCommerce order ID
	 * @return boolean
	 */
	function cancel_transaction( $order_id ) {
		$order = wc_get_order( $order_id );
		$order_payment_method = $order->get_payment_method();

		// Do nothing if order's payment method doesn't allow automatic cancellation via DIBS
		$payment_method_option_name = 'woocommerce_' . $order_payment_method . '_settings';
		$payment_method_option = get_option( $payment_method_option_name );
		$push_cancellation = ( isset( $payment_method_option['push_cancellation'] ) ) ? $payment_method_option['push_cancellation'] : '';
		if ( 'yes' != $push_cancellation ) {
			return;
		}

		// Check if order was created using a DIBS payment method
		if ( 'dibs_masterpass' == $order_payment_method ) {
			$callback = new WC_Gateway_Dibs_MasterPass_New;
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
		if ( 'yes' == get_post_meta( $order->get_id(), '_dibs_order_cancelled', true ) ){
			return;
		}

		require_once( 'dibs-api-functions.php' );

		$merchant_id 	= $callback->merchant_id;
		$key1 			= $callback->key_1;
		$key2 			= $callback->key_2;
		$api_username	= $callback->api_username;
		$api_password	= $callback->api_password;
		
		$postvars             = 'merchant=' . $merchant_id . '&orderid=' . $order->get_order_number() . '&transact=' . $transaction_id;
		$md5key               = MD5( $key1 . MD5( $key2 . $postvars ) );

		
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
			$order->add_order_note(
				__(
					'DIBS transaction cancelled.',
					'dibs-for-woocommerce'
				)
			);
			update_post_meta( $order->get_id(), '_dibs_order_cancelled', 'yes' );

			return true;
		} else if ( $response['status'] == 'DECLINED' ) {
			// Cancellation problem
			$order->add_order_note(
				sprintf(
					__(
						'DIBS transaction cancellation failed. Decline reason: %s.',
						'dibs-for-woocommerce'
					),
					$response['declineReason']
				)
			);

			return false;
		} else {
			// WP remote post problem
			$order->add_order_note(
				sprintf(
					__(
						'DIBS transaction cancellation failed. WP Remote post problem: %s.',
						'dibs-for-woocommerce'
					),
					$response['wp_remote_note']
				)
			);

			return false;
		}
	}

} // End class WC_Gateway_Dibs_MasterPass_Functions

$wc_gateway_dibs_masterpass_functions = new WC_Gateway_Dibs_MasterPass_Functions;