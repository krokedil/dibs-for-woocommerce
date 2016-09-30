<?php
/**
 * Class WC_Gateway_Dibs_MobilePay
 */
class WC_Gateway_Dibs_MobilePay extends WC_Gateway_Dibs_Factory {

	/**
	 * WC_Gateway_Dibs_MobilePay constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->id           = 'dibs_mobilepay';
		$this->name         = 'DIBS MobilePay';
		$this->method_title = 'DIBS MobilePay';
		$this->has_fields   = false;
		$this->log          = new WC_Logger();

		$this->flexwin_url = 'https://payment.architrade.com/paymentweb/start.action';

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

		// Supports
		$this->supports = array(
			'products',
			'refunds'
		);

		// Actions
		add_action( 'woocommerce_receipt_dibs_mobilepay', array( $this, 'receipt_page' ) );
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

		add_action( 'woocommerce_receipt_dibs_mobilepay', array( $this, 'receipt_page' ) );
	} // End construct
	
	
	/**
	 * Check if this gateway is enabled and available in the user's country
	 */
	function is_available() {
		if ( $this->enabled == "yes" ) {
			
			// Required fields check
			if ( empty( $this->merchant_id ) ) {
				return false;
			}
			// Checkout form check
			if ( isset( WC()->cart->total ) ) {
				// Only activate the payment gateway if the customers country is the same as the shop country ($this->dibs_country)
				if ( WC()->customer->get_country() == true && ( WC()->customer->get_country() != 'DK' && WC()->customer->get_country() != 'NO' ) ) {
					return false;
				}
			} // End Checkout form check
			return true;
		}
		return false;
	}


	/**
	 * Initialise Gateway Settings Form Fields
	 */
	function init_form_fields() {

		$this->form_fields = array(
			'enabled'                  => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-dibs' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable DIBS MobilePay', 'woocommerce-gateway-dibs' ),
				'default' => 'yes'
			),
			'title'                    => array(
				'title'       => __( 'Title', 'woocommerce-gateway-dibs' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-dibs' ),
				'default'     => __( 'DIBS MobilePay', 'woocommerce-gateway-dibs' )
			),
			'description'              => array(
				'title'       => __( 'Description', 'woocommerce-gateway-dibs' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-dibs' ),
				'default'     => __( "Pay via DIBS using MobilePay.", 'woocommerce-gateway-dibs' )
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
			'language'                 => array(
				'title'       => __( 'Language', 'woocommerce-gateway-dibs' ),
				'type'        => 'select',
				'options'     => array(
					'en' => 'English',
					'da' => 'Danish',
					'de' => 'German',
					'es' => 'Spanish',
					'fi' => 'Finnish',
					'fo' => 'Faroese',
					'fr' => 'French',
					'it' => 'Italian',
					'nl' => 'Dutch',
					'no' => 'Norwegian',
					'pl' => 'Polish (simplified)',
					'sv' => 'Swedish',
					'kl' => 'Greenlandic',
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
	 * Admin Panel Options.
	 * Options for bits like 'title' and availability on a country-by-country basis.
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		?>
		<h3><?php _e( 'DIBS MobilePay', 'woocommerce-gateway-dibs' ); ?></h3>
		<p>
			<?php printf( __( 'Documentation <a href="%s" target="_blank">can be found here</a>.', 'woocommerce-gateway-dibs' ), 'http://docs.woothemes.com/document/dibs/' ); ?>
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
	}
	
	/**
	 * Show receipt page.
	 *
	 * @param $order
	 */
	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with DIBS.', 'woocommerce-gateway-dibs' ) . '</p>';

		echo $this->generate_dibs_form( $order );
	}
		
	/**
	 * Generate the dibs button link
	 *
	 * @param $order_id
	 *
	 * @return string
	 */
	public function generate_dibs_form( $order_id ) {
		$order = wc_get_order( $order_id );

		$dibs_adr = $this->flexwin_url;

		$args = array(
			'merchant' => $this->merchant_id,
			'currency' => $this->dibs_currency[ $this->selected_currency ],
		);

		$paytypes = apply_filters( 'woocommerce_dibs_mobilepay_paytypes', 'MPO_Nets' );

		if ( ! empty( $paytypes ) ) {
			$args['paytype'] = $paytypes;
		}

		
		// Price
		$args['amount'] = $order->get_total() * 100;

		// Instant capture if selected in settings
		if ( $this->capturenow == 'yes' ) {
			$args['capturenow'] = 'yes';
		}
		

		// Order number
		$prefix       = 'n°'; // Strip n° (french translation)
		$tmp_order_id = $order->get_order_number();

		if ( substr( $tmp_order_id, 0, strlen( $prefix ) ) == $prefix ) {
			$tmp_order_id = substr( $tmp_order_id, strlen( $prefix ) );
		}

		$args['orderid'] = ltrim( $tmp_order_id, '#' ); // Strip #

		// Language
		$args['lang'] = $this->dibs_language;

		// Layout
		if ( ! empty( $this->decorator ) ) {
			$args['decorator'] = $this->decorator;
		}

		$args['ordertext'] = 'Name: ' . $order->billing_first_name . ' ' . $order->billing_last_name . '. Address: ' . $order->billing_address_1 . ', ' . $order->billing_postcode . ' ' . $order->billing_city;

		// Callback URL doesn't work as in the other gateways. DIBS erase everything
		// after a '?' in a specified callback URL
		$args['callbackurl'] = apply_filters( 'woocommerce_dibs_cc_callbackurl', trailingslashit( site_url( '/woocommerce/dibscallback' ) ) );
		$args['accepturl']   = trailingslashit( site_url( '/woocommerce/dibsaccept' ) );
		$args['cancelurl']   = trailingslashit( site_url( '/woocommerce/dibscancel' ) );

		// Testmode
		if ( $this->testmode == 'yes' ) {
			$args['test'] = 'yes';
		}

		// IP
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$args['ip'] = $_SERVER['HTTP_CLIENT_IP'];
		}

		// Calculate MD5 key
		// http://tech.dibs.dk/dibs_api/other_features/md5-key_control/
		$key1     = $this->key_1;
		$key2     = $this->key_2;
		$merchant = $this->merchant_id;
		// $orderid = $order_id;

		$currency = $this->dibs_currency[ $this->selected_currency ];
		$amount   = $order->get_total() * 100;
		$postvars = 'merchant=' . $merchant . '&orderid=' . $args['orderid'] . '&currency=' . $currency . '&amount=' . $amount;

		if ( ! isset( $args['maketicket'] ) ) {
			$args['md5key'] = MD5( $key2 . MD5( $key1 . $postvars ) );
		}

		/*
		$args['preauth'] = 'true';
		$args['md5key'] = MD5( $key2 . MD5( $key1 . 'transact=' . get_post_meta( $order_id, '_dibs_transact', true ) . '&preauth=true&currency=' . $currency ) );
		*/

		// Apply filters to the $args array
		$args = apply_filters( 'dibs_checkout_form', $args, 'dibs_cc', $order );

		// Prepare the form
		$fields  = '';
		$tmp_log = '';
		foreach ( $args as $key => $value ) {
			$fields .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';

			// Debug preparing
			$tmp_log .= $key . '=' . $value . "\r\n";
		}

		// Debug
		if ( $this->debug == 'yes' ) {
			$this->log->add( 'dibs', 'Sending values to DIBS: ' . $tmp_log );
		}

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
	 * Get gateway icon.
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
			$icon_src   = 'https://cdn.dibspayment.com/logo/checkout/single/horiz/DIBS_checkout_single_10.png';
			$icon_width = '98';
		}
		$icon_html = '<img src="' . $icon_src . '" alt="DIBS - Payments made easy" style="max-width:' . $icon_width . 'px"/>';

		return apply_filters( 'wc_dibs_icon_html', $icon_html );
	}

}