<?php
/**
 * Class for DIBS MasterPass.
 *
 * @class       WC_Gateway_Dibs_MasterPass_New
 * @            We add New to the class name to avoid errors if someone is using the old MasterPass plugin at the same time.
 * @since       2.4
 **/

class WC_Gateway_Dibs_MasterPass_New extends WC_Gateway_Dibs_Factory {

	public function __construct() {

		parent::__construct();

		$this->id                = 'dibs_masterpass';
		$this->name              = 'MasterPass';
		$this->order_button_text = $this->get_order_button_text();
		$this->has_fields        = false;
		$this->log               = new WC_Logger();
		$this->method_title      = __( 'MasterPass', 'dibs-for-woocommerce' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->title           = ( isset( $this->settings['title'] ) ) ? $this->settings['title'] : '';
		$this->enabled         = ( isset( $this->settings['enabled'] ) ) ? $this->settings['enabled'] : '';
		$this->description     = ( isset( $this->settings['description'] ) ) ? $this->settings['description'] : '';
		$this->merchant_id     = ( isset( $this->settings['merchant_id'] ) ) ? $this->settings['merchant_id'] : '';
		$this->key_hmac        = ( isset( $this->settings['key_hmac'] ) ) ? $this->settings['key_hmac'] : '';
		$this->key_1           = html_entity_decode( $this->settings['key_1'] );
		$this->key_2           = html_entity_decode( $this->settings['key_2'] );
		$this->card_visa       = ( isset( $this->settings['card_visa'] ) ) ? $this->settings['card_visa'] : '';
		$this->card_mastercard = ( isset( $this->settings['card_mastercard'] ) ) ? $this->settings['card_mastercard'] : '';
		$this->card_amex       = ( isset( $this->settings['card_amex'] ) ) ? $this->settings['card_amex'] : '';
		$this->card_diners     = ( isset( $this->settings['card_diners'] ) ) ? $this->settings['card_diners'] : '';
		$this->card_maestro    = ( isset( $this->settings['card_maestro'] ) ) ? $this->settings['card_maestro'] : '';

		$this->display_cart_page_button       = ( isset( $this->settings['display_cart_page_button'] ) ) ? $this->settings['display_cart_page_button'] : '';
		$this->display_cart_page_button_img   = ( isset( $this->settings['display_cart_page_button_img'] ) ) ? $this->settings['display_cart_page_button_img'] : '';
		$this->display_pp_button              = ( isset( $this->settings['display_pp_button'] ) ) ? $this->settings['display_pp_button'] : '';
		$this->display_pp_button_img          = ( isset( $this->settings['display_pp_button_img'] ) ) ? $this->settings['display_pp_button_img'] : '';
		$this->display_cart_widget_button     = ( isset( $this->settings['display_cart_widget_button'] ) ) ? $this->settings['display_cart_widget_button'] : '';
		$this->display_cart_widget_button_img = ( isset( $this->settings['display_cart_widget_button_img'] ) ) ? $this->settings['display_cart_widget_button_img'] : '';
		$this->push_cancellation              = ( isset( $this->settings['push_cancellation'] ) ) ? $this->settings['push_cancellation'] : '';
		$this->api_username                   = ( isset( $this->settings['api_username'] ) ) ? $this->settings['api_username'] : '';
		$this->api_password                   = ( isset( $this->settings['api_password'] ) ) ? $this->settings['api_password'] : '';
		$this->testmode                       = ( isset( $this->settings['testmode'] ) ) ? $this->settings['testmode'] : '';
		$this->debug                          = ( isset( $this->settings['debug'] ) ) ? $this->settings['debug'] : '';

		if ( 'yes' == $this->testmode ) {
			$this->testmode = true;
		} else {
			$this->testmode = false;
		}

		// Actions
		add_action( 'woocommerce_checkout_init', array( $this, 'masterpass_validate' ) );
		add_action( 'woocommerce_checkout_init', array( $this, 'check_payment_method_visibility' ) );
		add_action( 'woocommerce_checkout_init', array( $this, 'check_customer_login_form_visibility' ) );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Address info from MasterPass
		add_filter( 'woocommerce_form_field_args', array( $this, 'override_checkout_fields' ), 10, 3 );
		add_filter( 'default_checkout_billing_country', array( $this, 'maybe_change_default_checkout_billing_country' ) );
		add_filter( 'default_checkout_billing_postcode', array( $this, 'maybe_change_default_checkout_billing_postcode' ) );

		add_action( 'woocommerce_receipt_dibs_masterpass', array( $this, 'receipt_page' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ), 1 );

		// Clear sessions
		add_action( 'woocommerce_thankyou', array( $this, 'clear_mp_sessions' ) );

		// Refund support
		$this->supports = array(
			'refunds',
		);

	}


	/*
	 * Get different text on the Place order button depending on if a mpinit exist or not.
	 * 'Proceed to MasterPass' will be displayed if a customer chooses to pay with MasterPass from Checkout page.
	 *
	 */
	function get_order_button_text() {
		if ( is_admin() ) {
			return __( 'Place order', 'woocommerce' );
		} else {
			if ( WC()->session ) {
				if ( WC()->session->get( 'dibs_wallet_mp_validate_respons' ) ) {
					return __( 'Place order', 'woocommerce' );
				} else {
					return __( 'Proceed to MasterPass', 'dibs-for-woocommerce' );
				}
			} else {
				return __( 'Place order', 'woocommerce' );
			}
		}
	}


	/*
	 * Disable all payment methods except DIBS MasterPass if session dibs_wallet_mp_selected exist.
	 *
	 *
	 */
	function filter_gateways( $gateways ) {
		global $woocommerce;
		foreach ( $gateways as $gateway ) {
			if ( method_exists( WC()->session, 'get' ) ) {
				$dibs_wallet_mp_selected = WC()->session->get( 'dibs_wallet_mp_selected' );
				if ( $dibs_wallet_mp_selected && 'dibs_masterpass' != $gateway->id ) {
					unset( $gateways[ $gateway->id ] );
				}
			}
		}

		return $gateways;

	}


	/*
	 * Change Default Contry in checkout if user is not logged in and we have a country reported from MasterPass
	 *
	 *
	 */
	function maybe_change_default_checkout_billing_country( $country ) {

		if ( true == WC()->session->get( 'dibs_wallet_mp_validate_respons' ) && ! ( is_user_logged_in() ) ) {
			$mp_validate_respons = WC()->session->get( 'dibs_wallet_mp_validate_respons' );
			if ( $mp_validate_respons['shippingAddress']['Country'] ) {
				$country = $mp_validate_respons['shippingAddress']['Country'];
			} elseif ( $mp_validate_respons['Contact']['Country'] ) {
				$country = $mp_validate_respons['Contact']['Country'];
			}
		}
		return $country;

	}

	/*
	 * Change Default Postcode in checkout if user is not logged in and we have a country reported from MasterPass
	 *
	 *
	 */
	function maybe_change_default_checkout_billing_postcode( $postcode ) {

		if ( true == WC()->session->get( 'dibs_wallet_mp_validate_respons' ) && ! ( is_user_logged_in() ) ) {
			$mp_validate_respons = WC()->session->get( 'dibs_wallet_mp_validate_respons' );
			if ( $mp_validate_respons['shippingAddress']['PostalCode'] ) {
				$postcode = $mp_validate_respons['shippingAddress']['PostalCode'];
			}
		}
		return $postcode;

	}


	/**
	 * get_icon function.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon_html = '<img src="https://cdn.dibspayment.com/logo/checkout/single/horiz/DIBS_checkout_single_MasterPass.png" alt="DIBS - Payments made easy" style="max-width:115px"/>';

		return apply_filters( 'woocommerce_dibs_masterpass_icon', $icon_html );
	}



	/**
	 * Check if this gateway is enabled and available in the user's country
	 */

	function is_available() {

		global $woocommerce;

		if ( $this->enabled == 'yes' ) :

			// Checkout form check
			// if ( empty( WC()->session->get( 'dibs_wallet_mp_validate_respons' ) ) ) {
				// return false;
			// }
			return true;

		endif;

		return false;

	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	function init_form_fields() {

		$this->form_fields = array(
			'enabled'                        => array(
				'title'   => __( 'Enable/Disable', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable MasterPass', 'dibs-for-woocommerce' ),
				'default' => 'no',
			),
			'title'                          => array(
				'title'       => __( 'Title', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'dibs-for-woocommerce' ),
				'default'     => __( 'MasterPass', 'dibs-for-woocommerce' ),
			),
			'description'                    => array(
				'title'       => __( 'Description', 'dibs-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'dibs-for-woocommerce' ),
				'default'     => __( 'Buy with MasterPass.', 'dibs-for-woocommerce' ),
			),
			'merchant_id'                    => array(
				'title'       => __( 'Merchant ID', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Please enter your DIBS MasterPass Merchant ID.', 'dibs-for-woocommerce' ),
				'default'     => '',
			),
			'key_hmac'                       => array(
				'title'       => __( 'HMAC Key (k)', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Please enter your DIBS HMAC Key (k).', 'dibs-for-woocommerce' ),
				'default'     => '',
			),
			'key_1'                          => array(
				'title'       => __( 'MD5 k1', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Please enter your DIBS MD5 k1; this is only needed when processing refunds via DIBS.', 'dibs-for-woocommerce' ),
				'default'     => '',
				'placeholder' => __( 'Optional', 'dibs-for-woocommerce' ),
			),
			'key_2'                          => array(
				'title'       => __( 'MD5 k2', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Please enter your DIBS MD5 k2; this is only needed when processing refunds via DIBS.', 'dibs-for-woocommerce' ),
				'default'     => '',
				'placeholder' => __( 'Optional', 'dibs-for-woocommerce' ),
			),
			'section_cards'                  => array(
				'title'       => __( 'Active Cards', 'dibs-for-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			),
			'card_visa'                      => array(
				'title'   => __( 'Visa', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable payment with Visa', 'dibs-for-woocommerce' ),
				'default' => 'yes',
			),
			'card_mastercard'                => array(
				'title'   => __( 'MasterCard', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable payment with MasterCard', 'dibs-for-woocommerce' ),
				'default' => 'yes',
			),
			'card_amex'                      => array(
				'title'   => __( 'Amex', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable payment with Amex', 'dibs-for-woocommerce' ),
				'default' => 'no',
			),
			'card_diners'                    => array(
				'title'   => __( 'Diners', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable payment with Diners', 'dibs-for-woocommerce' ),
				'default' => 'no',
			),
			'card_maestro'                   => array(
				'title'   => __( 'Maestro', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable payment with Maestro', 'dibs-for-woocommerce' ),
				'default' => 'no',
			),
			'section_buttons'                => array(
				'title'       => __( 'Display MasterPass Button', 'dibs-for-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			),
			'display_cart_page_button'       => array(
				'title'   => __( 'Display on cart page', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Display MasterPass buy button on cart page.', 'dibs-for-woocommerce' ),
				'default' => 'yes',
			),
			'display_cart_page_button_img'   => array(
				'title'       => __( 'Image width', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The width of the cart page buy button.', 'dibs-for-woocommerce' ),
				'default'     => '185',
			),
			'display_pp_button'              => array(
				'title'   => __( 'Display on product page', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Display MasterPass buy button on product pages.', 'dibs-for-woocommerce' ),
				'default' => 'no',
			),
			'display_pp_button_img'          => array(
				'title'       => __( 'Image width', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The width of the Product page buy button.', 'dibs-for-woocommerce' ),
				'default'     => '125',
			),
			'display_cart_widget_button'     => array(
				'title'   => __( 'Display in cart widget', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Display MasterPass buy button in cart widget.', 'dibs-for-woocommerce' ),
				'default' => 'no',
			),
			'display_cart_widget_button_img' => array(
				'title'       => __( 'Image width', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The width of the cart widget buy button.', 'dibs-for-woocommerce' ),
				'default'     => '140',
			),
			'order_settings_title'           => array(
				'title' => __( 'Order management settings', 'dibs-for-woocommerce' ),
				'type'  => 'title',
			),
			'push_cancellation'              => array(
				'title'   => __( 'DIBS order cancellation', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Cancel MasterPass order automatically in DIBS when WooCommerce order is cancelled.', 'dibs-for-woocommerce' ),
				'default' => 'no',
			),
			'section_api_settings'           => array(
				'title'       => __( 'API Credentials', 'dibs-for-woocommerce' ),
				'type'        => 'title',
				'description' => sprintf( __( 'Enter your DIBS API user credentials to process refunds via DIBS. Learn how to access your DIBS API Credentials %1$shere%2$s.', 'dibs-for-woocommerce' ), '<a href="http://docs.krokedil.com/documentation/dibs-for-woocommerce/#4" target="_top">', '</a>' ),
			),
			'api_username'                   => array(
				'title'       => __( 'API Username', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API credentials from DIBS.', 'dibs-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Optional', 'dibs-for-woocommerce' ),
			),
			'api_password'                   => array(
				'title'       => __( 'API Password', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API credentials from DIBS.', 'dibs-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Optional', 'dibs-for-woocommerce' ),
			),
			'section_testmode'               => array(
				'title'       => __( 'Test Mode Settings', 'dibs-for-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			),
			'testmode'                       => array(
				'title'   => __( 'Test Mode', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Test Mode. Read more about the <a href="http://tech.dibs.dk/10_step_guide/your_own_test/" target="_blank">DIBS test process here</a>.', 'dibs-for-woocommerce' ),
				'default' => 'yes',
			),
			'debug'                          => array(
				'title'   => __( 'Debug', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging (<code>woocommerce/logs/dibs.txt</code>)', 'dibs-for-woocommerce' ),
				'default' => 'no',
			),
		);

	} // End init_form_fields()


	/**
	 * receipt_page
	 **/
	function receipt_page( $order ) {

		echo '<p>' . __( 'Thank you for your order.', 'dibs-for-woocommerce' ) . '</p>';

	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		?>
		<h3><?php _e( 'MasterPass via DIBS', 'dibs-for-woocommerce' ); ?></h3>
		<p style="background: #ffcaca; padding: 1em; font-size: 1.1em; border: 0px solid #8c8f94; border-left:10px solid #cc0000;">
		<strong>Please note that this plugin will be retired early 2022.</strong> <br/>To continue to use Nets (previously DIBS) as your payment provider you need to upgrade to <a href="https://krokedil.com/product/nets-easy/" target="_blank">Nets Easy for WooCommerce</a>.<br/>If you don't you won't be able to accept payments when Nets D2 is closed down. <a href="https://www.nets.eu/payments/online" target="_blank">Get in touch with Nets</a> to upgrade your account. <br/>If you need help transitioning from the Nets D2 plugin to Nets Easy you can <a href="https://krokedil.com/contact/" target="_blank">contact Krokedil</a> - the developer team behind the plugins.
		</p>
		<?php
		$checkout_page_id = wc_get_page_id( 'checkout' );
		$checkout_url     = '';
		// Check if there is a checkout page
		if ( $checkout_page_id ) {
			// Get the permalink
			$checkout_url = get_permalink( $checkout_page_id );
			// Force SSL if needed
			if ( is_ssl() || 'yes' === get_option( 'woocommerce_force_ssl_checkout' ) ) {
				$checkout_url = str_replace( 'http:', 'https:', $checkout_url );
			}
			// Allow filtering of checkout URL
			$checkout_url = apply_filters( 'woocommerce_get_checkout_url', $checkout_url );
			echo '<h4>Callback URL to send to DIBS/MasterPass</h4>';
			echo '<p><pre>' . $checkout_url . '</pre></p>';
		}
		?>
		<table class="form-table">
			<?php
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			?>
		</table><!--/.form-table-->
		<?php
	} // End admin_options()


	/**
	 * There are no payment fields for dibs, but we want to show the description if set.
	 **/
	function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wptexturize( $this->description ) );
		}

		if ( true == WC()->session->get( 'dibs_wallet_mp_validate_respons' ) ) {
			$mp_validate_respons = WC()->session->get( 'dibs_wallet_mp_validate_respons' );
			echo $mp_validate_respons['cardBrandName'] . ' ' . $mp_validate_respons['maskedCardNumber'];
		}
		?>
		<p><a href="#" rel="external" onclick="window.open('<?php echo $this->get_read_more_url(); ?>', '_blank', 'width=650,height=750,scrollbars=yes'); return false;"><?php echo $this->get_read_more_text(); ?></a></p>
		<?php
		/*
		if( WC()->session->get( 'dibs_wallet_mp_selected' )  ) {
			echo '<p><a href="' . get_the_permalink() . '?view-pm=all">' . __('Other payment options', 'dibs-for-woocommerce') . '</a></p>';
		}
		*/
	}


	/**
	 * Should we unset dibs_wallet_mp_selected and show all available payment methods in checkout?
	 **/
	function check_payment_method_visibility() {
		/*
		echo '<pre>';
		print_r(WC()->session->get( 'dibs_wallet_mp_validate_respons' ));
		echo '</pre>';
		*/
		if ( isset( $_GET['view-pm'] ) && 'all' == $_GET['view-pm'] ) {
			WC()->session->__unset( 'dibs_wallet_mp_selected' );
		}
	}



	/**
	 * Should we hide customer login form in checkout?
	 **/
	function check_customer_login_form_visibility() {
		if ( isset( $_GET['mpstatus'] ) && 'success' == $_GET['mpstatus'] ) {
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
		}
	}



	/**
	 * Display MasterPass payment button on cart page
	 **/
	public function single_masterpass_button_mpinit() {

		if ( $this->enabled == 'no' ) {
			return;
		}

		$postback = $this->mpinit();

		if ( 'ACCEPTED' == $postback['MpInitResponse']['status'] ) {

			// Store walletSessionId as WC session value
			WC()->session->set( 'dibs_wallet_session_id', $postback['MpInitResponse']['walletSessionId'] );

			// Debug
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs-mp', 'Receiving values from DIBS (single_masterpass_button_mpinit): ' . var_export( $postback, true ) );

			}

			wp_redirect( $postback['MpInitResponse']['redirectUrl'] );

		} else {
			if ( $this->debug == 'yes' ) {
				echo '<pre>';
				print_r( $postback );
				echo '</pre>';
				// wc_print_notice( $postback['MpInitResponse']['declineReason'], 'error');
			}
		}
	}



	/**
	 * Display MasterPass payment button on cart page
	 **/
	public function cart_masterpass_button_mpinit() {

		if ( $this->enabled == 'no' ) {
			return;
		}

		$postback = $this->mpinit();

		if ( 'ACCEPTED' == $postback['MpInitResponse']['status'] ) {

			// Store walletSessionId as WC session value
			WC()->session->set( 'dibs_wallet_session_id', $postback['MpInitResponse']['walletSessionId'] );

			// Debug
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs-mp', 'Receiving values from DIBS (cart_masterpass_button_mpinit): ' . var_export( $postback, true ) );

			}

			wp_redirect( $postback['MpInitResponse']['redirectUrl'] );

		} else {
			if ( $this->debug == 'yes' ) {
				echo '<pre>';
				print_r( $postback );
				echo '</pre>';
				// wc_print_notice( $postback['MpInitResponse']['declineReason'], 'error');
			}
		}
	}


	/**
	 * flatten_array function.
	 *
	 * @since 1.0
	 * @return array
	 */
	public function flatten_array( $array ) {
		$out = array();
		$i   = 0;
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$sub_key   = '';
				$sub_value = '';

				foreach ( $value as $ub_key => $sub_value ) {

					$out[ $ub_key . '-' . $i ] = $sub_value;

				}
			} else {
				$out[ $key ] = $value;
			}
			$i++;
		}

		// Sort by both key and value
		ksort( $out );
		asort( $out );

		return $out;
	}

	/**
	 * Make a mpinit call to DIBS and return the result
	 **/
	public function mpinit( $from_checkout_page = false ) {
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$url = 'https://payment.architrade.com/cgi-ssl/mpinit.cgi';

		$data = array(
			'merchantId'              => $this->merchant_id,
			'acceptableCards'         => $this->get_available_cards(),
			'shippingLocationProfile' => $this->get_available_shipping_countries(),
			'currencyCode'            => get_woocommerce_currency(),
			'test'                    => $this->testmode,
			'cartContent'             => $this->process_cart_contents(),
		);

		// Shipping in MasterPass window?
		if ( WC()->cart->needs_shipping() ) {
			$data['suppressShippingAddress'] = false;
		} else {
			$data['suppressShippingAddress'] = true;
		}
		if ( true === $from_checkout_page ) {
			$data['suppressShippingAddress'] = true;
		}

		// HMAC
		$formKeyValues = $data;

		// Prepare cart content array
		$flatten_cart_content = $this->flatten_array( $formKeyValues['cartContent'] );
		unset( $formKeyValues['cartContent'] );
		unset( $formKeyValues['shippingAddress'] );

		// Switch bool to string for hmac calculation
		$converted_suppressShippingAddress        = json_encode( $formKeyValues['suppressShippingAddress'] );
		$formKeyValues['suppressShippingAddress'] = $converted_suppressShippingAddress;
		$converted_test                           = json_encode( $formKeyValues['test'] );
		$formKeyValues['test']                    = $converted_test;

		// Sort the posted values
		ksort( $formKeyValues );

		// Combine the two arrays
		$formKeyValues = array_merge( $flatten_cart_content, $formKeyValues );

		require_once WC_DIBS_PLUGIN_DIR . 'includes/calculateMac.php';
		$logfile = '';

		// Calculate the MAC for the form key-values to be posted to DIBS.
		$MAC = calculateMac( $formKeyValues, $this->key_hmac, $logfile );

		// Add MAC to the $data array
		$data['MAC'] = $MAC;

		$content = json_encode( $data );

		// Debug
		if ( $this->debug == 'yes' ) {
			$this->log->add( 'dibs-mp', 'Sending values to DIBS (mpinit): ' . json_encode( $data, JSON_PRETTY_PRINT ) );
		}

		/*
		$json_string = json_encode($data, JSON_PRETTY_PRINT);
		echo '<pre>';
		print_r($json_string);
		echo '</pre>';
		die();
		*/
		$response = wp_remote_post(
			$url,
			array(
				'method'      => 'POST',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(
					'Content-Type' => 'application/json;charset=utf-8',
				),
				'body'        => array( 'request' => $content ),
				'cookies'     => array(),
			)
		);

		return json_decode( ltrim( $response['body'], 'response=' ), true );

	}


	/**
	 * Formats cart contents for DIBS.
	 *
	 * Checks if WooCommerce cart is empty. If it is, there's no reason to proceed.
	 *
	 * @since
	 * @access public
	 *
	 * @return array $cart_contents Formatted array ready for DIBS.
	 */
	public function process_cart_contents() {
		global $woocommerce;
		$woocommerce->cart->calculate_shipping();
		$woocommerce->cart->calculate_totals();
		$cart      = array();
		$item_loop = 0;

		// We need to keep track of order total, in case a smart coupon exceeds it
		// $order_total = 0;
		foreach ( $woocommerce->cart->get_cart() as $cart_item ) {
			if ( $cart_item['quantity'] ) {
				$_product = wc_get_product( $cart_item['product_id'] );
				/*
				$item_name            = $this->get_item_name( $cart_item );
				$item_price           = $this->get_item_price( $cart_item );
				 $item_quantity        = $this->get_item_quantity( $cart_item );
				$item_reference       = $this->get_item_reference( $_product );
				$item_tax_amount      = $this->get_item_tax_amount( $cart_item );
				*/
				$cart[ $item_loop ]['Description'] = htmlentities( $this->get_item_name( $cart_item ) );
				$cart[ $item_loop ]['Quantity']    = $this->get_item_quantity( $cart_item );
				$cart[ $item_loop ]['Value']       = $this->get_item_price( $cart_item );
				// $cart[$item_loop]['vatAmount']    = $this->get_item_tax_amount( $cart_item );
				$cart[ $item_loop ]['ImageURL'] = $this->get_image_src( $cart_item['product_id'] );
				// $items[$item_loop]['productId']   = $this->get_item_reference( $_product );
				$item_loop++;
			}
		}

		// Shipping
		if ( WC()->cart->shipping_total > 0 ) {
			$shipping_price                    = WC()->cart->shipping_total + WC()->cart->shipping_tax_total;
			$shipping_price                    = number_format( $shipping_price, 2, '.', '' );
			$shipping_price                    = $shipping_price * 100;
			$shipping_tax_amount               = number_format( WC()->cart->shipping_tax_total, 2, '.', '' );
			$shipping_tax_amount               = $shipping_tax_amount * 100;
			$cart[ $item_loop ]['Description'] = htmlentities( $this->get_shipping_name() );
			$cart[ $item_loop ]['Quantity']    = 1;
			$cart[ $item_loop ]['Value']       = $shipping_price;
			// $cart[$item_loop]['vatAmount']        = $shipping_tax_amount;
			$cart[ $item_loop ]['ImageURL'] = 'http://demo.com/image.gif';
		}

		return $cart;
	}


	/**
	 * Get product image src.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  string $product_id     Product id.
	 * @return string   $img_src        Src to thumbnail or WooCommerce placeholder image.
	 */
	public function get_image_src( $product_id ) {
		$img_src = '';
		if ( has_post_thumbnail( $product_id ) ) {
			$img_src = wp_get_attachment_url( get_post_thumbnail_id( $product_id ) );
		} else {
			$img_src = wc_placeholder_img_src();
		}
		return $img_src;
	}

	/**
	 * Calculate item tax amount.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  array $cart_item       Cart item.
	 * @return integer $item_tax_amount Item tax amount.
	 */
	public function get_item_tax_amount( $cart_item ) {
		$item_tax_amount = number_format( $cart_item['line_tax'], 2, '.', '' );
		$item_tax_amount = $item_tax_amount * 100 / $cart_item['quantity'];
		return $item_tax_amount;
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  array  $cart_item     Cart item.
	 * @param  object $_product      Product object.
	 * @return integer $item_tax_rate Item tax percentage formatted for Klarna.
	 */
	public function get_item_tax_rate( $cart_item, $_product ) {
		// We manually calculate the tax percentage here
		if ( $_product->is_taxable() && $cart_item['line_subtotal_tax'] > 0 ) {
			// Calculate tax rate
			$item_tax_rate = round( $cart_item['line_subtotal_tax'] / $cart_item['line_subtotal'], 2 ) * 100;
		} else {
			$item_tax_rate = 00;
		}

		return intval( $item_tax_rate . '00' );
	}

	/**
	 * Get cart item name.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  array $cart_item Cart item.
	 * @return string $item_name Cart item name.
	 */
	public function get_item_name( $cart_item ) {
		$cart_item_data = $cart_item['data'];
		$item_name      = $cart_item_data->post->post_title;

		// Append item meta to the title, if it exists
		if ( isset( $cart_item['item_meta'] ) ) {
			$item_meta = new WC_Order_Item_Meta( $cart_item['item_meta'] );
			if ( $meta = $item_meta->display( true, true ) ) {
				$item_name .= ' (' . $meta . ')';
			}
		}

		return strip_tags( $item_name );
	}


	/**
	 * Get cart item price.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  array $cart_item  Cart item.
	 * @return integer $item_price Cart item price.
	 */
	public function get_item_price( $cart_item ) {

		$item_price_including_tax = $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'];
		$item_price               = number_format( $item_price_including_tax, 2, '.', '' );
		$item_price               = $item_price * 100 / $cart_item['quantity'];

		return $item_price;
	}

	/**
	 * Get cart item quantity.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  array $cart_item     Cart item.
	 * @return integer $item_quantity Cart item quantity.
	 */
	public function get_item_quantity( $cart_item ) {
		return (int) $cart_item['quantity'];
	}

	/**
	 * Get cart item reference.
	 *
	 * Returns SKU or product ID.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @param  object $product        Product object.
	 * @return string $item_reference Cart item reference.
	 */
	public function get_item_reference( $_product ) {
		$item_reference = '';

		if ( $_product->get_sku() ) {
			$item_reference = $_product->get_sku();
		} elseif ( $_product->variation_id ) {
			$item_reference = $_product->variation_id;
		} else {
			$item_reference = $_product->id;
		}

		return strval( $item_reference );
	}


	/**
	 * Get shipping method name.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return string $shipping_name Name for selected shipping method.
	 */
	public function get_shipping_name() {
		global $woocommerce;

		$shipping_packages = $woocommerce->shipping->get_packages();
		foreach ( $shipping_packages as $i => $package ) {
			$chosen_method = isset( $woocommerce->session->chosen_shipping_methods[ $i ] ) ? $woocommerce->session->chosen_shipping_methods[ $i ] : '';

			if ( '' != $chosen_method ) {
				$package_rates = $package['rates'];
				foreach ( $package_rates as $rate_key => $rate_value ) {
					if ( $rate_key == $chosen_method ) {
						$shipping_name = __( 'Shipping:', 'dibs-for-woocommerce' ) . ' ' . $rate_value->label;
					}
				}
			}
		}

		if ( ! isset( $shipping_name ) ) {
			$shipping_name = __( 'Shipping', 'dibs-for-woocommerce' );
		}

		return $shipping_name;
	}


	/**
	 * get_read_more_url function.
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
	 * get_read_more_text function.
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
	 * get_available_cards function.
	 *
	 * @return string
	 */
	public function get_available_cards() {

		$available_cards = '';

		if ( 'yes' == $this->card_visa ) {
			$available_cards .= 'visa,';
		}
		if ( 'yes' == $this->card_mastercard ) {
			$available_cards .= 'master,';
		}
		if ( 'yes' == $this->card_amex ) {
			$available_cards .= 'amex,';
		}
		if ( 'yes' == $this->card_diners ) {
			$available_cards .= 'diners,';
		}
		if ( 'yes' == $this->card_maestro ) {
			$available_cards .= 'maestro,';
		}

		$available_cards = rtrim( $available_cards, ',' );
		return apply_filters( 'woocommerce_dibs_masterpass_available_cards', $available_cards );
	}



	/**
	 * get_available_shipping_countries function.
	 *
	 * @return array
	 */
	public function get_available_shipping_countries() {

		$mp_shipping_countries = null;

		if ( get_option( 'woocommerce_ship_to_countries' ) !== 'specific' ) {
			$mp_shipping_countries = 'all';
		} else {
			$available_shipping_countries = array_keys( WC()->countries->get_shipping_countries() );

			// Denmark
			if ( in_array( 'DK', $available_shipping_countries ) ) {
				$mp_shipping_countries .= 'DK';
			}
			// Finland
			if ( in_array( 'FI', $available_shipping_countries ) ) {
				$mp_shipping_countries .= 'FI';
			}
			// Norway
			if ( in_array( 'NO', $available_shipping_countries ) ) {
				$mp_shipping_countries .= 'NO';
			}
			// Sweden
			if ( in_array( 'SE', $available_shipping_countries ) ) {
				$mp_shipping_countries .= 'SE';
			}
		}

		return $mp_shipping_countries;
	}



	/**
	 * Validate payment data sent from MasterPass on checkout page
	 **/
	public function masterpass_validate() {

		$url    = 'https://payment.architrade.com/cgi-ssl/mpvalidate.cgi';
		$posted = $_REQUEST;
		if ( isset( $posted['mpstatus'] ) && 'success' == $posted['mpstatus'] && ! ( WC()->session->get( 'dibs_wallet_mp_validate_respons' ) ) ) {

			$data = array(
				'merchantId'          => $this->merchant_id,
				'walletSessionId'     => WC()->session->get( 'dibs_wallet_session_id' ),
				'oAuthToken'          => $posted['oauth_token'],
				'oAuthVerifier'       => $posted['oauth_verifier'],
				'checkoutResourceUrl' => $posted['checkout_resource_url'],
			);

			// HMAC
			$formKeyValues = $data;
			// Sort the values
			ksort( $formKeyValues );
			require_once WC_DIBS_PLUGIN_DIR . 'includes/calculateMac.php';
			$logfile = '';
			// Calculate the MAC for the form key-values to be posted to DIBS.
			$MAC = calculateMac( $formKeyValues, $this->key_hmac, $logfile );

			// Add MAC to the $args array
			$data['MAC'] = $MAC;
			/*
			$json_string = json_encode($data, JSON_PRETTY_PRINT);
			echo 'Send data<pre>';
			print_r( $json_string );
			echo '</pre>';
			*/

			// Debug
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs-mp', 'Sending values to DIBS (mpvalidate): ' . json_encode( $data, JSON_PRETTY_PRINT ) );
			}

			$response = wp_remote_post(
				$url,
				array(
					'method'      => 'POST',
					'timeout'     => 45,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => true,
					'headers'     => array(
						'Content-Type' => 'application/vnd.api+json',
					),
					'body'        => array( 'request' => json_encode( $data ) ),
					'cookies'     => array(),
				)
			);

			// Check for errors
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				wc_print_notice( $error_message, 'error' );
				$postback = array();
			} else {
				$postback = ltrim( $response['body'], 'response=' );
				$postback = utf8_encode( $postback );
				$postback = json_decode( $postback, true );
			}

			// Debug
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs-mp', 'Receiving values from DIBS (mpvalidate): ' . var_export( $postback, true ) );
			}

			if ( 'ACCEPTED' == $postback['MpValidateResponse']['status'] ) {
				WC()->session->set( 'dibs_wallet_mp_validate_respons', $postback['MpValidateResponse'] );
				WC()->session->set( 'dibs_wallet_mp_selected', 'yes' );
				WC()->session->set( 'chosen_payment_method', 'dibs_masterpass' );
			}

			// Is this MP purchase done from checkout page - set order to processing and forward the customer to the thankyou page
			if ( WC()->session->get( 'dibs_wallet_mp_from_checkout_page' ) && 'ACCEPTED' == $postback['MpValidateResponse']['status'] ) {

				$order_id = WC()->session->get( 'dibs_wallet_mp_from_checkout_page' );
				$order    = wc_get_order( $order_id );

				$auth_postback = $this->mpauthorize( $order_id );

				// Check so the connection was ok
				if ( is_wp_error( $auth_postback ) ) {
					$error_message = $auth_postback->get_error_message();
					wc_print_notice( $error_message, 'error' );
					exit;
				}

				switch ( $auth_postback['MpAuthorizeResponse']['status'] ) {
					case 'ERROR':
						$order->update_status( 'failed', sprintf( __( 'MasterPass payment %1$s not approved. Status %2$s.', 'dibs-for-woocommerce' ), $auth_postback['MpAuthorizeResponse']['status'], $postback['MpAuthorizeResponse']['declineReason'] ) );
						WC()->session->__unset( 'dibs_wallet_mp_validate_respons' );
						WC()->session->__unset( 'dibs_wallet_session_id' );
						WC()->session->__unset( 'dibs_wallet_mp_selected' );
						WC()->session->__unset( 'dibs_wallet_mp_from_checkout_page' );
					case 'DECLINED':
						$order->update_status( 'failed', sprintf( __( 'MasterPass payment %1$s not approved. Status %2$s.', 'dibs-for-woocommerce' ), $auth_postback['MpAuthorizeResponse']['status'], $postback['MpAuthorizeResponse']['declineReason'] ) );
						WC()->session->__unset( 'dibs_wallet_mp_validate_respons' );
						WC()->session->__unset( 'dibs_wallet_session_id' );
						WC()->session->__unset( 'dibs_wallet_mp_selected' );
						WC()->session->__unset( 'dibs_wallet_mp_from_checkout_page' );
					case 'ACCEPTED':
						// Store Transaction number as post meta
						add_post_meta( $order_id, 'dibs_transaction_no', $auth_postback['MpAuthorizeResponse']['transactionId'] );

						// Order completed
						$order->add_order_note( sprintf( __( 'MasterPass payment completed. DIBS transaction number: %s.', 'dibs-for-woocommerce' ), $auth_postback['MpAuthorizeResponse']['transactionId'] ) );

						// Payment complete
						$order->payment_complete( $auth_postback['MpAuthorizeResponse']['transactionId'] );

						// Remove cart
						WC()->cart->empty_cart();

						// Unset sessions
						WC()->session->__unset( 'dibs_wallet_mp_validate_respons' );
						WC()->session->__unset( 'dibs_wallet_session_id' );
						WC()->session->__unset( 'dibs_wallet_mp_selected' );
						WC()->session->__unset( 'dibs_wallet_mp_from_checkout_page' );
					default:
						// No action
						break;
				}

				// Redirect
				$redirect_url = $order->get_checkout_order_received_url();
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}
	}


	/**
	 * Authorize payment with DIBS
	 **/
	public function mpauthorize( $order_id ) {
		$url   = 'https://payment.architrade.com/cgi-ssl/mpauthorize.cgi';
		$order = wc_get_order( $order_id );

		$data = array(
			'merchantId'      => $this->merchant_id,
			'amount'          => $order->order_total * 100,
			'clientIp'        => get_post_meta( $order->get_id(), '_customer_ip_address', true ),
			'walletSessionId' => WC()->session->get( 'dibs_wallet_session_id' ),
			'orderId'         => ltrim( $order->get_order_number(), '#' ),
		);

		// HMAC
		$formKeyValues = $data;
		// Sort the values
		ksort( $formKeyValues );
		require_once WC_DIBS_PLUGIN_DIR . 'includes/calculateMac.php';
		$logfile = '';
		// Calculate the MAC for the form key-values to be posted to DIBS.
		$MAC = calculateMac( $formKeyValues, $this->key_hmac, $logfile );

		// Add MAC to the $args array
		$data['MAC'] = $MAC;

		// Debug
		if ( $this->debug == 'yes' ) {
			$this->log->add( 'dibs-mp', 'Sending values to DIBS (mpauthorize): ' . json_encode( $data, JSON_PRETTY_PRINT ) );
		}

		$response = wp_remote_post(
			$url,
			array(
				'method'      => 'POST',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array(
					'Content-Type' => 'application/vnd.api+json',
				),
				'body'        => array( 'request' => json_encode( $data ) ),
				'cookies'     => array(),
			)
		);

		if ( is_wp_error( $response ) ) {
			$postback = $response;
		} else {
			$postback = ltrim( $response['body'], 'response=' );
			$postback = utf8_encode( $postback );
			$postback = json_decode( $postback, true );
		}

		// Debug
		if ( $this->debug == 'yes' ) {
			$this->log->add( 'dibs-mp', 'Receiving values from DIBS (mpauthorize): ' . var_export( $postback, true ) );
		}

		return $postback;
	}


	/**
	 * Override checkout fields with address data received from MatserPass
	 **/
	function override_checkout_fields( $args, $key, $value ) {
		if ( ! is_admin() ) {
			if ( true == WC()->session->get( 'dibs_wallet_mp_validate_respons' ) ) {
				$mp_validate_respons = WC()->session->get( 'dibs_wallet_mp_validate_respons' );

				if ( 'billing_first_name' == $key ) {
					$args['default'] = $mp_validate_respons['Contact']['FirstName'];
				}
				if ( 'billing_last_name' == $key ) {
					$args['default'] = $mp_validate_respons['Contact']['LastName'];
				}
				if ( 'billing_postcode' == $key ) {
					$args['default'] = $mp_validate_respons['shippingAddress']['PostalCode'];
				}
				if ( 'billing_address_1' == $key ) {
					$args['default'] = $mp_validate_respons['shippingAddress']['Line1'];
				}
				if ( 'billing_city' == $key ) {
					$args['default'] = $mp_validate_respons['shippingAddress']['City'];
				}
				if ( 'billing_email' == $key ) {
					$args['default'] = $mp_validate_respons['Contact']['EmailAddress'];
				}
				if ( 'billing_phone' == $key ) {
					$args['default'] = $mp_validate_respons['Contact']['PhoneNumber'];
				}

				if ( 'shipping_first_name' == $key ) {
					$args['default'] = $mp_validate_respons['Contact']['FirstName'];
				}
				if ( 'shipping_last_name' == $key ) {
					$args['default'] = $mp_validate_respons['Contact']['LastName'];
				}
				if ( 'shipping_postcode' == $key ) {
					$args['default'] = $mp_validate_respons['shippingAddress']['PostalCode'];
				}
				if ( 'shipping_address_1' == $key ) {
					$args['default'] = $mp_validate_respons['shippingAddress']['Line1'];
				}
				if ( 'shipping_address_2' == $key ) {
					$args['default'] = $mp_validate_respons['shippingAddress']['Line2'];
				}
				if ( 'shipping_city' == $key ) {
					$args['default'] = $mp_validate_respons['shippingAddress']['City'];
				}
			}
		}
		return $args;
	}


	/**
	 * Process the payment and return the result
	 **/
	function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( false == WC()->session->get( 'dibs_wallet_mp_validate_respons' ) ) {
			$postback = $this->mpinit( true );

			if ( 'ACCEPTED' == $postback['MpInitResponse']['status'] ) {

				// Store walletSessionId as WC session value
				WC()->session->set( 'dibs_wallet_session_id', $postback['MpInitResponse']['walletSessionId'] );

				// Store order id in a session to use when customer returns from MP so we don't need to go via the checkout page again.
				WC()->session->set( 'dibs_wallet_mp_from_checkout_page', $order_id );

				// Debug
				if ( $this->debug == 'yes' ) {
					$this->log->add( 'dibs-mp', 'Receiving values from DIBS (mpinit in process_payment): ' . var_export( $postback, true ) );

				}
				return array(
					'result'   => 'success',
					'redirect' => $postback['MpInitResponse']['redirectUrl'],
				);

			} else {
				if ( $this->debug == 'yes' ) {
					/*
					echo '<pre>';
					print_r($postback);
					echo '</pre>';
					**/
					wc_print_notice( $postback['MpInitResponse']['declineReason'], 'error' );
				}
			}
		} else {
			$url = 'https://payment.architrade.com/cgi-ssl/mpauthorize.cgi';

			$data = array(
				'merchantId'      => $this->merchant_id,
				'amount'          => $order->order_total * 100,
				'clientIp'        => get_post_meta( $order->get_id(), '_customer_ip_address', true ),
				'walletSessionId' => WC()->session->get( 'dibs_wallet_session_id' ),
				'orderId'         => ltrim( $order->get_order_number(), '#' ),
			);

			// HMAC
			$formKeyValues = $data;
			// Sort the values
			ksort( $formKeyValues );
			require_once WC_DIBS_PLUGIN_DIR . 'includes/calculateMac.php';
			$logfile = '';
			// Calculate the MAC for the form key-values to be posted to DIBS.
			$MAC = calculateMac( $formKeyValues, $this->key_hmac, $logfile );

			// Add MAC to the $args array
			$data['MAC'] = $MAC;
			/*
			$json_string = json_encode($data, JSON_PRETTY_PRINT);
			echo 'Send data<pre>';
			print_r( $json_string );
			echo '</pre>';
			die();
			*/
			// Debug
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs-mp', 'Sending values to DIBS (mpauthorize): ' . json_encode( $data, JSON_PRETTY_PRINT ) );
			}

			$response = wp_remote_post(
				$url,
				array(
					'method'      => 'POST',
					'timeout'     => 45,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => true,
					'headers'     => array(
						'Content-Type' => 'application/vnd.api+json',
					),
					'body'        => array( 'request' => json_encode( $data ) ),
					'cookies'     => array(),
				)
			);

			$postback = ltrim( $response['body'], 'response=' );
			$postback = utf8_encode( $postback );
			$postback = json_decode( $postback, true );

			// Debug
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs-mp', 'Receiving values from DIBS (mpauthorize): ' . var_export( $postback, true ) );
			}

			$redirect_url = $order->get_checkout_order_received_url();
			switch ( $postback['MpAuthorizeResponse']['status'] ) {
				case 'ERROR':
					wc_add_notice( var_export( $postback, true ), 'error' );
					$order->update_status( 'failed', sprintf( __( 'MasterPass payment %1$s not approved. Status %2$s.', 'dibs-for-woocommerce' ), $postback['MpAuthorizeResponse']['status'], $postback['MpAuthorizeResponse']['declineReason'] ) );
					WC()->session->__unset( 'dibs_wallet_mp_validate_respons' );
					WC()->session->__unset( 'dibs_wallet_session_id' );
					WC()->session->__unset( 'dibs_wallet_mp_selected' );
					return;
				case 'DECLINED':
					wc_add_notice( $postback['MpAuthorizeResponse']['declineReason'], 'error' );
					$order->update_status( 'failed', sprintf( __( 'MasterPass payment %1$s not approved. Status %2$s.', 'dibs-for-woocommerce' ), $postback['MpAuthorizeResponse']['status'], $postback['MpAuthorizeResponse']['declineReason'] ) );
					WC()->session->__unset( 'dibs_wallet_mp_validate_respons' );
					WC()->session->__unset( 'dibs_wallet_session_id' );
					WC()->session->__unset( 'dibs_wallet_mp_selected' );
					return;
				case 'ACCEPTED':
					// Store Transaction number as post meta
					add_post_meta( $order_id, 'dibs_transaction_no', $postback['MpAuthorizeResponse']['transactionId'] );

					// Order completed
					$order->add_order_note( sprintf( __( 'MasterPass payment completed. DIBS transaction number: %s.', 'dibs-for-woocommerce' ), $postback['MpAuthorizeResponse']['transactionId'] ) );

					// Payment complete
					$order->payment_complete( $postback['MpAuthorizeResponse']['transactionId'] );

					// Remove cart
					WC()->cart->empty_cart();

					// Unset sessions
					WC()->session->__unset( 'dibs_wallet_mp_validate_respons' );
					WC()->session->__unset( 'dibs_wallet_session_id' );
					WC()->session->__unset( 'dibs_wallet_mp_selected' );

					// Return thank you redirect
					return array(
						'result'   => 'success',
						'redirect' => $redirect_url,
					);
				default:
					// No action
					break;
			}

			/*
			echo '<pre>';
			print_r($response);
			echo '</pre>';
			die();
			*/
		}

	} // End function


	public function clear_mp_sessions() {
		WC()->session->__unset( 'dibs_wallet_mp_validate_respons' );
		WC()->session->__unset( 'dibs_wallet_session_id' );
		WC()->session->__unset( 'dibs_wallet_mp_selected' );
	}


	/**
	 * Can the order be refunded via Dibs?
	 *
	 * @param  WC_Order $order
	 *
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		return $order && $order->get_transaction_id();
	}

	/**
	 * Process a refund if supported
	 *
	 * @param   int    $order_id
	 * @param   float  $amount
	 * @param   string $reason
	 *
	 * @return  bool|wp_error True or false based on success, or a WP_Error object
	 * @link    http://tech.dibspayment.com/D2_refundcgi
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			$this->log->add( 'Refund Failed: No transaction ID.' );
			$order->add_order_note( __( 'Refund Failed: No transaction ID.', 'dibs-for-woocommerce' ) );

			return false;
		}

		if ( ! $this->api_username || ! $this->api_password ) {
			$order->add_order_note( __( 'Refund Failed: Missing API Credentials.', 'dibs-for-woocommerce' ) );

			return false;
		}

		require_once '../dibs-api-functions.php';

		$amount_smallest_unit = $amount * 100;
		$transact             = $order->get_transaction_id();
		$merchant_id          = $this->merchant_id;
		$postvars             = 'merchant=' . $merchant_id . '&orderid=' . $order->get_order_number() . '&transact=' . $transact . '&amount=' . $amount_smallest_unit;
		$md5key               = MD5( $this->key_2 . MD5( $this->key_1 . $postvars ) );

		// Refund parameters
		$params = array(
			'amount'    => $amount_smallest_unit,
			'currency'  => $order->get_currency(),
			'md5key'    => $md5key,
			'merchant'  => $merchant_id,
			'orderid'   => $order->get_order_number(),
			'textreply' => 'yes',
			'transact'  => $transact,
		);

		$response = postToDIBS( 'RefundTransaction', $params, false, $this->api_username, $this->api_password );

		// WP remote post problem
		if ( is_wp_error( $response ) ) {
			$refund_note = sprintf( __( 'DIBS refund failed. WP Remote post problem: %s.', 'dibs-for-woocommerce' ), $response['wp_remote_note'] );

			$order->add_order_note( $refund_note );
			return false;
		}

		if ( isset( $response['status'] ) && ( $response['status'] == 'ACCEPT' || $response['status'] == 'ACCEPTED' ) ) {
			// Refund OK
			$refund_note = sprintf( __( '%s refunded successfully via DIBS.', 'dibs-for-woocommerce' ), wc_price( $amount ) );
			if ( '' != $reason ) {
				$refund_note .= sprintf( __( ' Reason: %s.', 'dibs-for-woocommerce' ), $reason );
			}

			$order->add_order_note( $refund_note );

			// Maybe change status to Refunded
			if ( $order->order_total == $amount ) {
				$order->update_status( 'refunded' );
			}
			return true;

		} else {

			// Refund problem
			$order->add_order_note( sprintf( __( 'DIBS refund failed. Decline reason: %s.', 'dibs-for-woocommerce' ), $response['message'] ) );
			return false;
		}

	}


} // End class WC_Gateway_Dibs_MasterPass_New
