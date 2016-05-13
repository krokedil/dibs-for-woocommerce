<?php

class WC_Gateway_Dibs_CC extends WC_Gateway_Dibs {

	/**
	 * Class for Dibs credit card payment.
	 *
	 */

	public function __construct() {
		global $woocommerce;

		parent::__construct();

		$this->id         = 'dibs';
		$this->has_fields = false;
		$this->log        = new WC_Logger();

		$this->flexwin_url       = 'https://payment.architrade.com/paymentweb/start.action';
		$this->paymentwindow_url = 'https://sat1.dibspayment.com/dibspaymentwindow/entrypoint';

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->title                  = ( isset( $this->settings['title'] ) ) ? $this->settings['title'] : '';
		$this->description            = ( isset( $this->settings['description'] ) ) ? $this->settings['description'] : '';
		$this->merchant_id            = ( isset( $this->settings['merchant_id'] ) ) ? $this->settings['merchant_id'] : '';
		$this->key_1                  = html_entity_decode( $this->settings['key_1'] );
		$this->key_2                  = html_entity_decode( $this->settings['key_2'] );
		$this->key_hmac               = html_entity_decode( $this->settings['key_hmac'] );
		$this->payment_method         = ( isset( $this->settings['payment_method'] ) ) ? $this->settings['payment_method'] : '';
		$this->pay_type_cards         = ( isset( $this->settings['pay_type_cards'] ) ) ? $this->settings['pay_type_cards'] : 'yes';
		$this->pay_type_netbanks      = ( isset( $this->settings['pay_type_netbanks'] ) ) ? $this->settings['pay_type_netbanks'] : 'yes';
		$this->pay_type_paypal        = ( isset( $this->settings['pay_type_paypal'] ) ) ? $this->settings['pay_type_paypal'] : '';
		$this->capturenow             = ( isset( $this->settings['capturenow'] ) ) ? $this->settings['capturenow'] : '';
		$this->decorator              = ( isset( $this->settings['decorator'] ) ) ? $this->settings['decorator'] : '';
		$this->language               = ( isset( $this->settings['language'] ) ) ? $this->settings['language'] : '';
		$this->alternative_icon       = ( isset( $this->settings['alternative_icon'] ) ) ? $this->settings['alternative_icon'] : '';
		$this->alternative_icon_width = ( isset( $this->settings['alternative_icon_width'] ) ) ? $this->settings['alternative_icon_width'] : '';
		$this->api_username           = ( isset( $this->settings['api_username'] ) ) ? $this->settings['api_username'] : '';
		$this->api_password           = ( isset( $this->settings['api_password'] ) ) ? $this->settings['api_password'] : '';
		$this->testmode               = ( isset( $this->settings['testmode'] ) ) ? $this->settings['testmode'] : '';
		$this->debug                  = ( isset( $this->settings['debug'] ) ) ? $this->settings['debug'] : '';


		// Apply filters for language
		$this->dibs_language = apply_filters( 'dibs_language', $this->language );

		// Subscription support
		$this->supports = array(
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'multiple_subscriptions',
			'refunds'
		);

		// Subscriptions
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
				$this,
				'scheduled_subscription_payment'
			), 10, 2 );
			add_action( 'woocommerce_subscriptions_changed_failing_payment_method_' . $this->id, array(
				$this,
				'update_failing_payment_method'
			), 10, 2 );
		}

		// Actions
		add_action( 'woocommerce_receipt_dibs', array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		// Dibs currency codes http://tech.dibs.dk/toolbox/currency_codes/
		$this->dibs_currency = array(
			'DKK' => '208', // Danish Kroner
			'EUR' => '978', // Euro
			'USD' => '840', // US Dollar $
			'GBP' => '826', // English Pound £
			'SEK' => '752', // Swedish Kroner
			'AUD' => '036', // Australian Dollar
			'CAD' => '124', // Canadian Dollar
			'ISK' => '352', // Icelandic Kroner
			'JPY' => '392', // Japanese Yen
			'NZD' => '554', // New Zealand Dollar
			'NOK' => '578', // Norwegian Kroner
			'CHF' => '756', // Swiss Franc
			'TRY' => '949', // Turkish Lire
		);

		// Check if the currency is supported
		if ( ! isset( $this->dibs_currency[ $this->selected_currency ] ) ) {
			$this->enabled = "no";
		} else {
			$this->enabled = $this->settings['enabled'];
		}

	} // End construct


	/**
	 * Initialise Gateway Settings Form Fields
	 */
	function init_form_fields() {

		$this->form_fields = array(
			'enabled'                  => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-dibs' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable DIBS', 'woocommerce-gateway-dibs' ),
				'default' => 'yes'
			),
			'title'                    => array(
				'title'       => __( 'Title', 'woocommerce-gateway-dibs' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-dibs' ),
				'default'     => __( 'DIBS', 'woocommerce-gateway-dibs' )
			),
			'description'              => array(
				'title'       => __( 'Description', 'woocommerce-gateway-dibs' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-dibs' ),
				'default'     => __( "Pay via DIBS using credit card or bank transfer.", 'woocommerce-gateway-dibs' )
			),
			'merchant_id'              => array(
				'title'       => __( 'DIBS Merchant ID', 'woocommerce-gateway-dibs' ),
				'type'        => 'text',
				'description' => __( 'Please enter your DIBS Merchant ID; this is needed in order to take payment.', 'woocommerce-gateway-dibs' ),
				'default'     => ''
			),
			'key_1'                    => array(
				'title'       => __( 'MD5 k1', 'woocommerce-gateway-dibs' ),
				'type'        => 'text',
				'description' => __( 'Please enter your DIBS MD5 k1; this is only needed when using Flexwin as the payment method.', 'woocommerce-gateway-dibs' ),
				'default'     => ''
			),
			'key_2'                    => array(
				'title'       => __( 'MD5 k2', 'woocommerce-gateway-dibs' ),
				'type'        => 'text',
				'description' => __( 'Please enter your DIBS MD5 k2; this is only needed when using Flexwin as the payment method.', 'woocommerce-gateway-dibs' ),
				'default'     => ''
			),
			'key_hmac'                 => array(
				'title'       => __( 'HMAC Key (k)', 'woocommerce-gateway-dibs' ),
				'type'        => 'text',
				'description' => __( 'Please enter your DIBS HMAC Key (k); this is only needed when using Payment Window as the payment method.', 'woocommerce-gateway-dibs' ),
				'default'     => ''
			),
			'payment_method'           => array(
				'title'       => __( 'Payment Method', 'woocommerce-gateway-dibs' ),
				'type'        => 'select',
				'options'     => array(
					'flexwin'       => __( 'Flexwin', 'woocommerce-gateway-dibs' ),
					'paymentwindow' => __( 'Payment Window', 'woocommerce-gateway-dibs' )
				),
				'description' => __( 'Choose payment method integration.', 'woocommerce-gateway-dibs' ),
				'default'     => 'flexwin',
			),
			'pay_type_cards'           => array(
				'title'       => __( 'Paytype - All Cards', 'woocommerce-gateway-dibs' ),
				'type'        => 'checkbox',
				'label'       => __( 'Include the paytype ALL_CARDS sent to DIBS.', 'woocommerce-gateway-dibs' ),
				'description' => __( 'This is used to control the payment methods available in the payment window (when using Payment Window as the payment method).', 'woocommerce-gateway-dibs' ),
				'default'     => 'yes'
			),
			'pay_type_netbanks'        => array(
				'title'       => __( 'Paytype - All Netbanks', 'woocommerce-gateway-dibs' ),
				'type'        => 'checkbox',
				'label'       => __( 'Include the paytype ALL_NETBANKS sent to DIBS.', 'woocommerce-gateway-dibs' ),
				'description' => __( 'This is used to control the payment methods available in the payment window (when using Payment Window as the payment method).', 'woocommerce-gateway-dibs' ),
				'default'     => 'yes'
			),
			'pay_type_paypal'          => array(
				'title'       => __( 'Paytype - PayPal', 'woocommerce-gateway-dibs' ),
				'type'        => 'checkbox',
				'label'       => __( 'Include the paytype PAYPAL sent to DIBS.', 'woocommerce-gateway-dibs' ),
				'description' => __( 'This is used to control the payment methods available in the payment window (when using Payment Window as the payment method).', 'woocommerce-gateway-dibs' ),
				'default'     => 'no'
			),
			'language'                 => array(
				'title'       => __( 'Language', 'woocommerce-gateway-dibs' ),
				'type'        => 'select',
				'options'     => array(
					'en'    => 'English',
					'da'    => 'Danish',
					'de'    => 'German',
					'es'    => 'Spanish',
					'fi'    => 'Finnish',
					'fo'    => 'Faroese (only Flexwin)',
					'fr'    => 'French',
					'it'    => 'Italian',
					'nl'    => 'Dutch',
					'no'    => 'Norwegian',
					'pl'    => 'Polish (simplified)',
					'sv'    => 'Swedish',
					'kl'    => 'Greenlandic (only Flexwin)',
					'pt_PT' => 'Portuguese (only Payment window)'
				),
				'description' => __( 'Set the language in which the page will be opened when the customer is redirected to DIBS.', 'woocommerce-gateway-dibs' ),
				'default'     => 'sv'
			),
			'alternative_icon'         => array(
				'title'       => __( 'Alternative payment icon', 'woocommerce-gateway-dibs' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Add the URL to an alternative payment icon that the user sees during checkout. Leave blank to use the default image. Alternative payment method logos can be found <a href="%s" target="_blank">here</a>.', 'woocommerce-gateway-dibs' ), 'http://tech.dibspayment.com/logos#check-out-logos' ),
				'default'     => ''
			),
			'alternative_icon_width'   => array(
				'title'       => __( 'Icon width', 'woocommerce-gateway-dibs-masterpass' ),
				'type'        => 'text',
				'description' => __( 'The width of the Alternative payment icon.', 'woocommerce-gateway-dibs-masterpass' ),
				'default'     => ''
			),
			'capturenow'               => array(
				'title'       => __( 'DIBS transaction capture', 'woocommerce-gateway-dibs' ),
				'type'        => 'select',
				'options'     => array(
					'yes'      => __( 'On Purchase', 'woocommerce-gateway-dibs' ),
					'complete' => __( 'On order completion', 'woocommerce-gateway-dibs' ),
					'no'       => __( 'No', 'woocommerce-gateway-dibs' )
				),
				'description' => __( 'If On Purchase is selected the order amount is immediately transferred from the customer’s account to the shop’s account.', 'woocommerce-gateway-dibs' ),
				'default'     => 'no'
			),
			'decorator'                => array(
				'title'       => __( 'Decorator', 'woocommerce-gateway-dibs' ),
				'type'        => 'select',
				'options'     => array(
					'responsive' => __( 'Responsive', 'woocommerce-gateway-dibs' ),
					'default'    => __( 'Default', 'woocommerce-gateway-dibs' ),
					'basal'      => __( 'Basal', 'woocommerce-gateway-dibs' ),
					'rich'       => __( 'Rich', 'woocommerce-gateway-dibs' ),
					''           => __( 'None', 'woocommerce-gateway-dibs' )
				),
				'description' => __( 'Specifies which of the pre-built decorators to use (when using Flexwin as the payment method). This will override the customer specific decorator, if one has been uploaded.', 'woocommerce-gateway-dibs' ),
				'default'     => 'responsive',
			),
			'api_settings_title'       => array(
				'title'       => __( 'API Credentials', 'woocommerce-gateway-dibs' ),
				'type'        => 'title',
				'description' => sprintf( __( 'Enter your DIBS API user credentials to process refunds via DIBS. Learn how to access your DIBS API Credentials %shere%s.', 'woocommerce-gateway-dibs' ), '<a href="https://docs.woothemes.com/document/dibs/" target="_top">', '</a>' ),
			),
			'api_username'             => array(
				'title'       => __( 'API Username', 'woocommerce-gateway-dibs' ),
				'type'        => 'text',
				'description' => __( 'Get your API credentials from DIBS.', 'woocommerce-gateway-dibs' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Optional', 'woocommerce-gateway-dibs' )
			),
			'api_password'             => array(
				'title'       => __( 'API Password', 'woocommerce-gateway-dibs' ),
				'type'        => 'text',
				'description' => __( 'Get your API credentials from DIBS.', 'woocommerce-gateway-dibs' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Optional', 'woocommerce-gateway-dibs' )
			),
			'test_mode_settings_title' => array(
				'title' => __( 'Test Mode Settings', 'woocommerce-gateway-dibs' ),
				'type'  => 'title',
			),
			'testmode'                 => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-dibs' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable DIBS Test Mode. Read more about the <a href="http://tech.dibs.dk/10_step_guide/your_own_test/" target="_blank">DIBS test process here</a>.', 'woocommerce-gateway-dibs' ),
				'default' => 'yes'
			),
			'debug'                    => array(
				'title'   => __( 'Debug', 'woocommerce-gateway-dibs' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging (<code>woocommerce/logs/dibs.txt</code>)', 'woocommerce-gateway-dibs' ),
				'default' => 'no'
			)
		);

	} // End init_form_fields()


	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		?>
		<h3><?php _e( 'DIBS', 'woocommerce-gateway-dibs' ); ?></h3>
		<p>
			<?php printf( __( 'Documentation <a href="%s" target="_blank">can be found here</a>.', 'woocommerce-gateway-dibs' ), 'http://docs.woothemes.com/document/dibs/' ); ?>
			<?php if ( class_exists( 'WC_Subscriptions_Order' ) ) { ?>
				<?php echo __( 'You must set payment method to "Payment Window" to allow recurring payments.', 'woocommerce-gateway-dibs' ); ?>
			<?php } ?>
		</p>
		<table class="form-table">
			<?php
			if ( isset( $this->dibs_currency[ $this->selected_currency ] ) ) {
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
			} else { ?>
				<tr valign="top">
					<th scope="row" class="titledesc">DIBS disabled</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>DIBS disabled</span></legend>
							<?php _e( 'DIBS does not support your store currency.', 'woocommerce-gateway-dibs' ); ?><br>
						</fieldset>
					</td>
				</tr>
				<?php
			} // End check currency
			?>
		</table><!--/.form-table-->
		<?php
	} // End admin_options()


	/**
	 * get_icon function.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon_html  = '';
		$icon_src   = '';
		$icon_width = '';
		if ( $this->alternative_icon ) {
			$icon_src   = $this->alternative_icon;
			$icon_width = $this->alternative_icon_width;
		} else {
			$icon_src   = 'https://cdn.dibspayment.com/logo/checkout/combo/horiz/DIBS_checkout_kombo_horizontal_04.png';
			$icon_width = '145';
		}
		$icon_html = '<img src="' . $icon_src . '" alt="DIBS - Payments made easy" style="max-width:' . $icon_width . 'px"/>';

		return apply_filters( 'wc_dibs_icon_html', $icon_html );
	}

	/**
	 * Check if this gateway is enabled and available in the user's country
	 */

	function is_available() {

		global $woocommerce;

		if ( $this->enabled == "yes" ) :

			// Checkout form check
			if ( class_exists( 'WC_Subscriptions_Order' ) ) {
				// Flexwin not available for subscription products
				if ( WC_Subscriptions_Cart::cart_contains_subscription() && $this->payment_method == 'flexwin' ) {
					return false;
				}
			}

			return true;

		endif;

		return false;
	}


	/**
	 * There are no payment fields for dibs, but we want to show the description if set.
	 **/
	function payment_fields() {
		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}
	}


	/**
	 * Generate the dibs button link
	 **/
	public function generate_dibs_form( $order_id ) {
		global $woocommerce;

		$order = WC_Dibs_Compatibility::wc_get_order( $order_id );

		// Post the form to the right address
		if ( $this->payment_method == 'paymentwindow' ) {
			$dibs_adr = $this->paymentwindow_url;
		} else {
			$dibs_adr = $this->flexwin_url;
		}


		$args = array(
			// Merchant
			'merchant' => $this->merchant_id,


			// Currency
			'currency' => $this->dibs_currency[ $this->selected_currency ],

		);


		// Payment Method - Payment Window
		if ( $this->payment_method == 'paymentwindow' ) {

			// Paytype
			$paytypes = '';
			if ( $this->pay_type_cards == 'yes' ) {
				$paytypes = 'ALL_CARDS';
			}
			if ( $this->pay_type_netbanks == 'yes' ) {
				$paytypes .= ',' . 'ALL_NETBANKS';
			}
			if ( $this->pay_type_paypal == 'yes' ) {
				$paytypes .= ',' . 'PAYPAL';
			}

			if ( ! empty( $paytypes ) ) {
				$args['paytype'] = apply_filters( 'woocommerce_dibs_paytypes', $paytypes );
			}

			// Order ID
			$prefix = 'n°'; // Strip n° (french translation)
			$tmp_order_id = $order->get_order_number();
			
			if (substr($tmp_order_id, 0, strlen($prefix)) == $prefix) {
			    $tmp_order_id = substr($tmp_order_id, strlen($prefix));
			}

			$args['orderId'] = ltrim( $tmp_order_id, '#'); // Strip #

			// Language
			if ( $this->dibs_language == 'no' ) {
				$this->dibs_language = 'nb';
			}

			$args['language'] = $this->dibs_language;

			// URLs
			// Callback URL doesn't work as in the other gateways. DIBS erase everyting after a '?' in a specified callback URL
			// We also need to make the callback url the accept/return url. If we use $this->get_return_url( $order ) the HMAC calculation doesn't add up
			$args['callbackUrl'] = apply_filters( 'woocommerce_dibs_cc_callbackurl', trailingslashit( site_url( '/woocommerce/dibscallback' ) ) );
			//$args['acceptReturnUrl'] = trailingslashit(site_url('/woocommerce/dibscallback'));

			//$args['acceptReturnUrl'] = preg_replace( '/\\?.*/', '', $this->get_return_url( $order ) );
			$args['acceptReturnUrl'] = trailingslashit( site_url( '/woocommerce/dibsaccept' ) );
			$args['cancelreturnurl'] = trailingslashit( site_url( '/woocommerce/dibscancel' ) );

			// Address info
			$args['billingFirstName']   = $order->billing_first_name;
			$args['billingLastName']    = $order->billing_last_name;
			$args['billingAddress']     = $order->billing_address_1;
			$args['billingAddress2']    = $order->billing_address_2;
			$args['billingPostalPlace'] = $order->billing_city;
			$args['billingPostalCode']  = $order->billing_postcode;
			$args['billingEmail']       = $order->billing_email;

			$search                = array( '.', ' ', '(', ')', '+', '-' );
			$args['billingMobile'] = str_replace( $search, '', $order->billing_phone );

			// Testmode
			if ( $this->testmode == 'yes' ) {
				$args['test'] = '1';
			}


			// What kind of payment is this - subscription payment or regular payment
			if ( class_exists( 'WC_Subscriptions_Order' ) && WC_Subscriptions_Order::order_contains_subscription( $order_id ) ) {

				// Subscription payment
				$args['createTicketAndAuth'] = '1';

				// Instant capture if selected in settings
				if ( $this->capturenow == 'yes' ) {
					$args['capturenow'] = '1';
				}

				if ( WC_Subscriptions_Order::get_total_initial_payment( $order ) == 0 ) {
					$price = 1;
				} else {
					$price = WC_Subscriptions_Order::get_total_initial_payment( $order );
				}

				// Price
				$args['amount'] = $price * 100;


			} else {

				// Regular payment

				// Instant capture if selected in settings
				if ( $this->capturenow == 'yes' ) {
					$args['capturenow'] = '1';
				}

				// Price
				$args['amount'] = $order->order_total * 100;
			}


			// Pass all order rows individually
			if ( 'rr' == 'notyet' ) {

				$args['oiTypes'] = 'UNITCODE;QUANTITY;DESCRIPTION;AMOUNT;VATAMOUNT;ITEMID';

				// Cart Contents
				$item_loop = 1;
				if ( sizeof( $order->get_items() ) > 0 ) : foreach ( $order->get_items() as $item ) :

					$tmp_sku = '';

					if ( function_exists( 'get_product' ) ) {

						// Version 2.0
						$_product = $order->get_product_from_item( $item );

						// Get SKU or product id
						if ( $_product->get_sku() ) {
							$tmp_sku = $_product->get_sku();
						} else {
							$tmp_sku = $_product->id;
						}

					} else {

						// Version 1.6.6
						$_product = new WC_Product( $item['id'] );

						// Get SKU or product id
						if ( $_product->get_sku() ) {
							$tmp_sku = $_product->get_sku();
						} else {
							$tmp_sku = $item['id'];
						}

					}

					if ( $_product->exists() && $item['qty'] ) :

						$tmp_product                  = 'st;' . $item['qty'] . ';' . $item['name'] . ';' . number_format( $order->get_item_total( $item, false ), 2, '.', '' ) * 100 . ';' . $order->get_item_tax( $item ) * 100 . ';' . $tmp_sku;
						$args[ 'oiRow' . $item_loop ] = $tmp_product;

						$item_loop ++;

					endif;
				endforeach; endif;


				// Shipping Cost
				if ( $order->get_shipping() > 0 ) :

					$tmp_shipping = '1' . ';' . __( 'Shipping cost', 'woocommerce-gateway-dibs' ) . ';' . $order->order_shipping * 100 . ';' . $order->order_shipping_tax * 100 . ';' . '0';

					$args[ 'oiRow' . $item_loop ] = $tmp_shipping;

					$item_loop ++;

				endif;


				// Discount
				if ( $order->get_order_discount() > 0 ) :

					$tmp_discount = '1' . ';' . __( 'Discount', 'woocommerce-gateway-dibs' ) . ';' . - $order->get_order_discount() . ';' . '0' . ';' . '0';

					$args[ 'oiRow' . $item_loop ] = $tmp_discount;

				endif;
			} // End if pass order rows individually

			// HMAC
			$formKeyValues = $args;
			require_once( 'calculateMac.php' );
			$logfile = '';
			// Calculate the MAC for the form key-values to be posted to DIBS.
			$MAC = calculateMac( $formKeyValues, $this->key_hmac, $logfile );

			// Add MAC to the $args array
			$args['MAC'] = $MAC;

		} else {

			// Payment Method - Flexwin

			// Paytype
			$paytypes = apply_filters( 'woocommerce_dibs_paytypes', '' );

			if ( ! empty( $paytypes ) ) {
				$args['paytype'] = $paytypes;
			}

			// Price
			$args['amount'] = $order->order_total * 100;

			//'orderid' => $order_id,
			$args['orderid'] = ltrim( $order->get_order_number(), '#' );

			// Language
			$args['lang'] = $this->dibs_language;

			// Layout
			if ( ! empty( $this->decorator ) ) {
				$args['decorator'] = $this->decorator;
			}

			//'uniqueoid' => $order->order_key,
			$args['uniqueoid'] = $order_id;

			$args['ordertext'] = 'Name: ' . $order->billing_first_name . ' ' . $order->billing_last_name . '. Address: ' . $order->billing_address_1 . ', ' . $order->billing_postcode . ' ' . $order->billing_city;

			// URLs
			// Callback URL doesn't work as in the other gateways. DIBS erase everyting after a '?' in a specified callback URL 
			$args['callbackurl'] = apply_filters( 'woocommerce_dibs_cc_callbackurl', trailingslashit( site_url( '/woocommerce/dibscallback' ) ) );
			$args['accepturl']   = trailingslashit( site_url( '/woocommerce/dibsaccept' ) );
			$args['cancelurl']   = trailingslashit( site_url( '/woocommerce/dibscancel' ) );

			// Testmode
			if ( $this->testmode == 'yes' ) {
				$args['test'] = 'yes';
			}

			// Instant capture
			if ( $this->capturenow == 'yes' ) {
				$args['capturenow'] = 'yes';
			}

			// IP
			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$args['ip'] = $_SERVER['HTTP_CLIENT_IP'];
			}


			// MD5
			// Calculate key
			// http://tech.dibs.dk/dibs_api/other_features/md5-key_control/
			$key1     = $this->key_1;
			$key2     = $this->key_2;
			$merchant = $this->merchant_id;
			//$orderid = $order_id;

			$orderid  = ltrim( $order->get_order_number(), '#' );
			$currency = $this->dibs_currency[ $this->selected_currency ];
			$amount   = $order->order_total * 100;
			$postvars = 'merchant=' . $merchant . '&orderid=' . $orderid . '&currency=' . $currency . '&amount=' . $amount;

			$args['md5key'] = MD5( $key2 . MD5( $key1 . $postvars ) );
		}


		// Apply filters to the $args array
		$args = apply_filters( 'dibs_checkout_form', $args, 'dibs_cc' );

		// Prepare the form
		$fields  = '';
		$tmp_log = '';
		foreach ( $args as $key => $value ) {
			$fields .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';

			// Debug preparing
			$tmp_log .= $key . '=' . $value . "\r\n";
		}

		// Debug
		if ( $this->debug == 'yes' ) :
			$this->log->add( 'dibs', 'Sending values to DIBS: ' . $tmp_log );
		endif;


		wc_enqueue_js( '
			jQuery("body").block({
					message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to DIBS to make payment.', 'woocommerce-gateway-dibs' ) ) . '",
					baseZ: 99999,
					overlayCSS:
					{
						background: "#fff",
						opacity: 0.6
					},
					css: {
				        padding:        "20px",
				        zindex:         "9999999",
				        textAlign:      "center",
				        color:          "#555",
				        border:         "3px solid #aaa",
				        backgroundColor:"#fff",
				        cursor:         "wait",
				        lineHeight:		"24px",
				    }
				});
			jQuery("#submit_dibs_cc_payment_form").click();
		' );

		// Print out and send the form

		return '<form action="' . $dibs_adr . '" method="post" id="dibs_cc_payment_form">
				' . $fields . '
				<input type="submit" class="button-alt" id="submit_dibs_cc_payment_form" value="' . __( 'Pay via dibs', 'woocommerce-gateway-dibs' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce-gateway-dibs' ) . '</a>
			</form>';

	}


	/**
	 * Process the payment and return the result
	 **/
	function process_payment( $order_id ) {

		$order = WC_Dibs_Compatibility::wc_get_order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true )
		);

	}


	/**
	 * receipt_page
	 **/
	function receipt_page( $order ) {

		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with DIBS.', 'woocommerce-gateway-dibs' ) . '</p>';

		echo $this->generate_dibs_form( $order );

	}


	/**
	 * Successful Payment!
	 **/
	function successful_request( $posted ) {

		// Debug
		if ( $this->debug == 'yes' ) :

			$tmp_log = '';

			foreach ( $posted as $key => $value ) {
				$tmp_log .= $key . '=' . $value . "\r\n";
			}

			$this->log->add( 'dibs', 'Returning values from DIBS: ' . $tmp_log );
		endif;


		// Flexwin callback
		if ( isset( $posted['transact'] ) && ! empty( $posted['uniqueoid'] ) && is_numeric( $posted['uniqueoid'] ) ) {

			// Verify MD5 checksum
			// http://tech.dibs.dk/dibs_api/other_features/md5-key_control/	
			$key1 = $this->key_1;
			$key2 = $this->key_2;
			$vars = 'transact=' . $posted['transact'] . '&amount=' . $posted['amount'] . '&currency=' . $posted['currency'];
			$md5  = MD5( $key2 . MD5( $key1 . $vars ) );

			$order_id = (int) $posted['uniqueoid'];

			$order = WC_Dibs_Compatibility::wc_get_order( $order_id );

			// Prepare redirect url
			$redirect_url = $order->get_checkout_order_received_url();

			// Check order not already completed or processing 
			// (to avoid multiple callbacks from DIBS - IPN & return-to-shop callback
			if ( $order->status == 'completed' || $order->status == 'processing' ) {

				if ( $this->debug == 'yes' ) {
					$this->log->add( 'dibs', 'Aborting, Order #' . $order_id . ' is already complete.' );
				}

				wp_redirect( $redirect_url );
				exit;
			}

			// Verify MD5
			if ( $posted['authkey'] != $md5 ) {
				// MD5 check failed
				$order->update_status( 'failed', sprintf( __( 'MD5 check failed. DIBS transaction ID: %s', 'woocommerce-gateway-dibs' ), strtolower( $posted['transaction'] ) ) );

			}

			// Set order status
			if ( $order->status !== 'completed' || $order->status !== 'processing' ) {
				switch ( strtolower( $posted['statuscode'] ) ) :
					case '2' :
					case '5' :
						// Order completed
						$order->add_order_note( __( 'DIBS payment completed. DIBS transaction number: ', 'woocommerce-gateway-dibs' ) . $posted['transact'] );
						// Transaction captured
						if ( $this->capturenow == 'yes' ) {
							add_post_meta( $order_id, '_dibs_order_captured', 'yes' );
							$order->add_order_note( __( 'DIBS transaction captured.', 'woocommerce-gateway-dibs' ) );
						}
						// Store Transaction number as post meta
						add_post_meta( $order_id, '_dibs_transaction_no', $posted['transact'] );
						$order->payment_complete( $posted['transact'] );
						break;
					case '12' :
						// Order completed
						$order->update_status( 'on-hold', sprintf( __( 'DIBS Payment Pending. Check with DIBS for further information. DIBS transaction number: %s', 'woocommerce-gateway-dibs' ), $posted['transact'] ) );
						// Store Transaction number as post meta
						add_post_meta( $order_id, '_dibs_transaction_no', $posted['transact'] );
						$order->payment_complete( $posted['transact'] );
						break;
					case '0' :
					case '1' :
					case '4' :
					case '17' :
						// Order failed
						$order->update_status( 'failed', sprintf( __( 'DIBS payment %s not approved. Status code %s.', 'woocommerce-gateway-dibs' ), strtolower( $posted['transaction'] ), $posted['statuscode'] ) );
						break;

					default:
						// No action
						break;

				endswitch;

			}


			// Return to Thank you page if this is a buyer-return-to-shop callback
			wp_redirect( $redirect_url );
			exit;

		} // End Flexwin callback


		// Payment Window callback
		if ( isset( $posted["transaction"] ) && ! empty( $posted['orderId'] ) ) {

			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs', 'Payment Window callback.' );
			}

			$order_id = $this->get_order_id( $posted['orderId'] );

			$order = WC_Dibs_Compatibility::wc_get_order( $order_id );

			// Prepare redirect url
			$redirect_url = $order->get_checkout_order_received_url();

			// Debug
			if ( $this->debug == 'yes' ) :
				$this->log->add( 'dibs', 'Order status: ' . $order->status );
			endif;

			// Check order not already completed or processing 
			// (to avoid multiple callbacks from DIBS - IPN & return-to-shop callback
			if ( $order->status == 'completed' || $order->status == 'processing' ) {

				if ( $this->debug == 'yes' ) {
					$this->log->add( 'dibs', 'Aborting, Order #' . $order_id . ' is already complete.' );
				}

				wp_redirect( $redirect_url );
				exit;
			}

			// Verify HMAC
			require_once( 'calculateMac.php' );
			$logfile = '';
			$MAC     = calculateMac( $posted, $this->key_hmac, $logfile );

			// Debug
			if ( $this->debug == 'yes' ) :
				$this->log->add( 'dibs', 'HMac check...' . json_encode( $posted ) );
			endif;

			if ( $posted['MAC'] != $MAC ) {
				//$order->add_order_note( __('HMAC check failed for Dibs callback with order_id: ', 'woocommerce-gateway-dibs') .$posted['transaction'] );
				$order->update_status( 'failed', sprintf( __( 'HMAC check failed for Dibs callback with order_id: %s.', 'woocommerce-gateway-dibs' ), strtolower( $posted['transaction'] ) ) );

				// Debug
				if ( $this->debug == 'yes' ) :
					$this->log->add( 'dibs', 'Calculated HMac: ' . $MAC );
				endif;

				wp_redirect( $redirect_url );
				exit;
			}

			switch ( strtolower( $posted['status'] ) ) :
				case 'accepted' :

					// Order completed
					$order->add_order_note( sprintf( __( 'DIBS payment completed. DIBS transaction number: %s.', 'woocommerce-gateway-dibs' ), $posted['transaction'] ) );
					// Transaction captured
					if ( $this->capturenow == 'yes' ) {
						add_post_meta( $order_id, '_dibs_order_captured', 'yes' );
						$order->add_order_note( __( 'DIBS transaction captured.', 'woocommerce-gateway-dibs' ) );
					}
					// Store Transaction number as post meta
					add_post_meta( $order_id, '_dibs_transaction_no', $posted['transaction'] );
					//add_post_meta( $order_id, '_transaction_id', $posted['transaction'] );

					if ( isset( $posted['ticket'] ) ) {
						add_post_meta( $order_id, '_dibs_ticket', $posted['ticket'] );
						$order->add_order_note( sprintf( __( 'DIBS subscription ticket number: %s.', 'woocommerce-gateway-dibs' ), $posted['ticket'] ) );
					}

					$order->payment_complete( $posted['transaction'] );

					break;

				case 'pending' :
					// On-hold until we sort this out with DIBS
					$order->update_status( 'on-hold', sprintf( __( 'DIBS Payment Pending. Check with DIBS for further information. DIBS transaction number: %s', 'woocommerce-gateway-dibs' ), $posted['transaction'] ) );
					// Store Transaction number as post meta
					add_post_meta( $order_id, '_dibs_transaction_no', $posted['transaction'] );
					add_post_meta( $order_id, '_transaction_id', $posted['transaction'] );

				case 'declined' :
				case 'error' :

					// Order failed
					$order->update_status( 'failed', sprintf( __( 'Payment %s via IPN.', 'woocommerce-gateway-dibs' ), strtolower( $posted['transaction'] ) ) );

					break;

				default:
					// No action
					break;
			endswitch;

			// Return to Thank you page if this is a buyer-return-to-shop callback
			wp_redirect( $redirect_url );
			exit;

		}

	}

	/**
	 * Cancel order
	 * We do this since DIBS doesn't like GET parameters in callback and cancel url's
	 **/

	function cancel_order( $posted ) {

		global $woocommerce;

		// Payment Window callback
		if ( isset( $posted['orderId'] ) ) {

			// Verify HMAC
			require_once( 'calculateMac.php' );
			$logfile = '';
			$MAC     = calculateMac( $posted, $this->key_hmac, $logfile );

			$order_id = $this->get_order_id( $posted['orderId'] );

			$order = WC_Dibs_Compatibility::wc_get_order( $order_id );


			if ( $posted['MAC'] == $MAC && $order->id == $order_id && $order->status == 'pending' ) {

				// Cancel the order + restore stock
				$order->cancel_order( __( 'Order cancelled by customer.', 'woocommerce-gateway-dibs' ) );

				// Message
				wc_add_notice( __( 'Your order was cancelled.', 'woocommerce-gateway-dibs' ), 'error' );

			} elseif ( $order->status != 'pending' ) {

				wc_add_notice( __( 'Your order is no longer pending and could not be cancelled. Please contact us if you need assistance.', 'woocommerce-gateway-dibs' ), 'error' );

			} else {

				wc_add_notice( __( 'Invalid order.', 'woocommerce-gateway-dibs' ), 'error' );

			}

			wp_safe_redirect( $woocommerce->cart->get_cart_url() );
			exit;

		} // End Payment Window


		// Flexwin callback
		if ( isset( $posted['uniqueoid'] ) && is_numeric( $posted['uniqueoid'] ) ) {

			$order_id = (int) $posted['uniqueoid'];

			$order = WC_Dibs_Compatibility::wc_get_order( $order_id );

			if ( $order->id == $order_id && $order->status == 'pending' ) {

				// Cancel the order + restore stock
				$order->cancel_order( __( 'Order cancelled by customer.', 'woocommerce-gateway-dibs' ) );

				// Message
				wc_add_notice( __( 'Your order was cancelled.', 'woocommerce-gateway-dibs' ), 'error' );

			} elseif ( $order->status != 'pending' ) {

				wc_add_notice( __( 'Your order is no longer pending and could not be cancelled. Please contact us if you need assistance.', 'woocommerce-gateway-dibs' ), 'error' );

			} else {

				wc_add_notice( __( 'Invalid order.', 'woocommerce-gateway-dibs' ), 'error' );

			}

			wp_safe_redirect( $woocommerce->cart->get_cart_url() );
			exit;

		} // End Flexwin

	} // End function cancel_order()


	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $order WC_Order The WC_Order object of the order which the subscription was purchased in.
	 * @param $product_id int The ID of the subscription product for which this payment relates.
	 *
	 * @access public
	 * @return void
	 */
	function scheduled_subscription_payment( $amount_to_charge, $order ) {

		$result = $this->process_subscription_payment( $order, $amount_to_charge );

		if ( false == $result ) {

			// Debug
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs', 'Scheduled subscription payment failed for order ' . $order->id );
			}

			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );

		} else {

			// Debug
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs', 'Scheduled subscription payment succeeded for order ' . $order->id );
			}

			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
			$order->payment_complete();

		}

	} // End function


	/**
	 * process_subscription_payment function.
	 *
	 * Since we use a Merchant handled subscription, we need to generate the
	 * recurrence request.
	 */

	function process_subscription_payment( $order = '', $amount = 0 ) {

		require_once( 'dibs-subscriptions.php' );
		require_once( 'calculateMac.php' );

		$dibs_ticket = get_post_meta( WC_Subscriptions_Renewal_Order::get_parent_order_id( $order->id ), '_dibs_ticket', true );

		$order_items = $order->get_items();

		$params = array(
			'merchantId' => $this->merchant_id,
			'currency'   => $order->get_order_currency(),
			'amount'     => number_format( $amount, 2, '.', '' ) * 100,
			'ticketId'   => $dibs_ticket,
			'orderId'    => $order->get_order_number(),
			// 'orderId' 	=> $order->id
		);

		// Calculate the MAC for the form key-values to be posted to DIBS.
		$MAC = calculateMac( $params, $this->key_hmac );

		// Add MAC to the $params array
		$params['MAC'] = $MAC;

		$response = postToDIBS( 'AuthorizeTicket', $params );

		if ( isset( $response['status'] ) && ( $response['status'] == "ACCEPT" || $response['status'] == "ACCEPTED" ) ) {

			// Payment ok
			$order->add_order_note( sprintf( __( 'DIBS subscription payment completed. Transaction Id: %s.', 'woocommerce-gateway-dibs' ), $response['transactionId'] ) );
			update_post_meta( $order->id, '_dibs_transaction_no', $response['transactionId'] );
			update_post_meta( $order->id, '_transaction_id', $response['transactionId'] );
			update_post_meta( $order->id, '_dibs_order_captured', 'no' );

			return $response['transactionId'];

		} elseif ( ! empty( $response['wp_remote_note'] ) ) {

			// WP remote post problem
			$order->add_order_note( sprintf( __( 'DIBS subscription payment failed. WP Remote post problem: %s.', 'woocommerce-gateway-dibs' ), $response['wp_remote_note'] ) );

			return false;

		} else {

			// Payment problem
			$order->add_order_note( sprintf( __( 'DIBS subscription payment failed. Decline reason: %s.', 'woocommerce-gateway-dibs' ), $response['declineReason'] ) );

			return false;

		}

	} // End function


	/**
	 * Update the customer token IDs for a subscription after a customer used the gateway to successfully complete the payment
	 * for an automatic renewal payment which had previously failed.
	 *
	 * @param WC_Order $original_order The original order in which the subscription was purchased.
	 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 *
	 * @return void
	 */
	function update_failing_payment_method( $original_order, $renewal_order ) {
		update_post_meta( $original_order->id, '_dibs_ticket', get_post_meta( $renewal_order->id, '_dibs_ticket', true ) );
	}


	/**
	 * Get the order ID. Check to see if SON and SONP is enabled and
	 *
	 * @global type $wc_seq_order_number
	 *
	 * @param type $order_number
	 *
	 * @return type
	 */
	private function get_order_id( $order_number ) {

		// Get Order ID by order_number() if the Sequential Order Number plugin is installed
		if ( class_exists( 'WC_Seq_Order_Number' ) ) {

			global $wc_seq_order_number;

			$order_id = $wc_seq_order_number->find_order_by_order_number( $order_number );

			if ( 0 === $order_id ) {
				$order_id = $order_number;
			}

			// Get Order ID by order_number() if the Sequential Order Number Pro plugin is installed
		} elseif ( class_exists( 'WC_Seq_Order_Number_Pro' ) ) {

			$order_id = wc_seq_order_number_pro()->find_order_by_order_number( $order_number );

			if ( 0 === $order_id ) {
				$order_id = $order_number;
			}

		} else {

			$order_id = $order_number;
		}

		return apply_filters( 'wc_dibs_get_order_id', $order_id );

	} // end function

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
	 * @param   int $order_id
	 * @param   float $amount
	 * @param   string $reason
	 *
	 * @return  bool|wp_error True or false based on success, or a WP_Error object
	 * @link    http://tech.dibspayment.com/D2_refundcgi
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			$this->log->add( 'Refund Failed: No transaction ID.' );
			$order->add_order_note( __( 'Refund Failed: No transaction ID.', 'woocommerce-gateway-dibs' ) );

			return false;
		}

		if ( ! $this->api_username || ! $this->api_password ) {
			$order->add_order_note( __( 'Refund Failed: Missing API Credentials.', 'woocommerce-gateway-dibs' ) );

			return false;
		}

		require_once( 'dibs-subscriptions.php' );

		$amount_smallest_unit = $amount * 100;
		$transact             = $order->get_transaction_id();
		$merchant_id          = $this->merchant_id;
		$postvars             = 'merchant=' . $merchant_id . '&orderid=' . $order->get_order_number() . '&transact=' . $transact . '&amount=' . $amount_smallest_unit;
		$md5key               = MD5( $this->key_2 . MD5( $this->key_1 . $postvars ) );

		// Refund parameters
		$params = array(
			'amount'    => $amount_smallest_unit,
			'currency'  => $order->get_order_currency(),
			'md5key'    => $md5key,
			'merchant'  => $merchant_id,
			'orderid'   => $order->get_order_number(),
			'textreply' => 'yes',
			'transact'  => $transact
		);

		$response = postToDIBS( 'RefundTransaction', $params, false, $this->api_username, $this->api_password );

		// WP remote post problem
		if ( is_wp_error( $response ) ) {
			$refund_note = sprintf( __( 'DIBS refund failed. WP Remote post problem: %s.', 'woocommerce-gateway-dibs' ), $response['wp_remote_note'] );

			$order->add_order_note( $refund_note );

			//$this->log->add( 'dibs', $refund_note );
			return false;
		}

		if ( isset( $response['status'] ) && ( $response['status'] == 'ACCEPT' || $response['status'] == 'ACCEPTED' ) ) {
			// Refund OK
			$refund_note = sprintf( __( '%s refunded successfully via DIBS.', 'woocommerce-gateway-dibs' ), wc_price( $amount ) );
			if ( '' != $reason ) {
				$refund_note .= sprintf( __( ' Reason: %s.', 'woocommerce-gateway-dibs' ), $reason );
			}

			$order->add_order_note( $refund_note );

			// Maybe change status to Refunded
			if ( $order->order_total == $amount ) {
				$order->update_status( 'refunded' );
			}

			return true;

		} else {

			// Refund problem
			$order->add_order_note( sprintf( __( 'DIBS refund failed. Decline reason: %s.', 'woocommerce-gateway-dibs' ), $response['message'] ) );

			return false;
		}

	}

	/**
	 * Returns merchant ID
	 */
	function get_merchant_id() {
		return $this->merchant_id;
	}

	/**
	 * Returns capturenow setting
	 */
	function get_capturenow() {
		return $this->capturenow;
	}

	/**
	 * Returns MD5 key 1
	 */
	function get_key_1() {
		return $this->key_1;
	}

	/**
	 * Returns MD5 key 2
	 */
	function get_key_2() {
		return $this->key_2;
	}
} // End class