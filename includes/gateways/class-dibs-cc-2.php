<?php
/**
 * Class WC_Gateway_Dibs_CC
 */
class WC_Gateway_Dibs_CC_2 extends WC_Gateway_Dibs_Factory {

	/**
	 * WC_Gateway_Dibs_CC constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->id           = 'dibs_2';
		$this->name         = 'DIBS Debit card - activate for Sweden';
		$this->method_title = 'DIBS Debit card - activate for Sweden';
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
		$this->calcfee                = ( isset( $this->settings['calcfee'] ) ) ? $this->settings['calcfee'] : '';
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
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
			'refunds',
		);

		// Subscriptions
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action(
				'woocommerce_scheduled_subscription_payment_' . $this->id,
				array(
					$this,
					'scheduled_subscription_payment',
				),
				10,
				2
			);
			add_action(
				'woocommerce_subscription_failing_payment_method_updated_' . $this->id,
				array(
					$this,
					'update_failing_payment_method',
				),
				10,
				2
			);
		}

		// Actions
		add_action( 'woocommerce_receipt_dibs_2', array( $this, 'receipt_page' ) );
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

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
			$this->enabled = 'no';
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
				'title'   => __( 'Enable/Disable', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable DIBS', 'dibs-for-woocommerce' ),
				'default' => 'yes',
			),
			'title'                    => array(
				'title'       => __( 'Title', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'dibs-for-woocommerce' ),
				'default'     => __( 'DIBS - Debit card', 'dibs-for-woocommerce' ),
			),
			'description'              => array(
				'title'       => __( 'Description', 'dibs-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'dibs-for-woocommerce' ),
				'default'     => __( 'Pay via DIBS using credit card or bank transfer.', 'dibs-for-woocommerce' ),
			),
			'merchant_id'              => array(
				'title'       => __( 'DIBS Merchant ID', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Please enter your DIBS Merchant ID; this is needed in order to take payment.', 'dibs-for-woocommerce' ),
				'default'     => '',
			),
			'key_1'                    => array(
				'title'       => __( 'MD5 k1', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Please enter your DIBS MD5 k1; this is only needed when using Flexwin as the payment method.', 'dibs-for-woocommerce' ),
				'default'     => '',
			),
			'key_2'                    => array(
				'title'       => __( 'MD5 k2', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Please enter your DIBS MD5 k2; this is only needed when using Flexwin as the payment method.', 'dibs-for-woocommerce' ),
				'default'     => '',
			),
			'language'                 => array(
				'title'       => __( 'Language', 'dibs-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'wp' => 'WordPress site Language',
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
				'description' => __( 'Set the language in which the page will be opened when the customer is redirected to DIBS.', 'dibs-for-woocommerce' ),
				'default'     => 'wp',
			),
			'alternative_icon'         => array(
				'title'       => __( 'Alternative payment icon', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Add the URL to an alternative payment icon that the user sees during checkout. Leave blank to use the default image. Alternative payment method logos can be found <a href="%s" target="_blank">here</a>.', 'dibs-for-woocommerce' ), 'http://tech.dibspayment.com/logos#check-out-logos' ),
				'default'     => '',
			),
			'alternative_icon_width'   => array(
				'title'       => __( 'Icon width', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The width of the Alternative payment icon.', 'dibs-for-woocommerce' ),
				'default'     => '',
			),
			'capturenow'               => array(
				'title'       => __( 'DIBS transaction capture', 'dibs-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'yes'      => __( 'On Purchase', 'dibs-for-woocommerce' ),
					'complete' => __( 'On order completion', 'dibs-for-woocommerce' ),
					'no'       => __( 'No', 'dibs-for-woocommerce' ),
				),
				'description' => __( 'If On Purchase is selected the order amount is immediately transferred from the customer’s account to the shop’s account.', 'dibs-for-woocommerce' ),
				'default'     => 'no',
			),
			'calcfee'                  => array(
				'title'       => __( 'Calcfee', 'dibs-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'If this box is checked, the charge from the acquirer due to the transaction will automatically be calculated and affixed.', 'dibs-for-woocommerce' ),
				'description' => __( 'NOTE: To use this parameter you need to contact DIBS Support with the fees you have at your acquirer, as they need to be entered into their system.', 'dibs-for-woocommerce' ),
				'default'     => 'no',
			),
			'decorator'                => array(
				'title'       => __( 'Decorator', 'dibs-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'responsive' => __( 'Responsive', 'dibs-for-woocommerce' ),
					'default'    => __( 'Default', 'dibs-for-woocommerce' ),
					'basal'      => __( 'Basal', 'dibs-for-woocommerce' ),
					'rich'       => __( 'Rich', 'dibs-for-woocommerce' ),
					''           => __( 'None', 'dibs-for-woocommerce' ),
				),
				'description' => __( 'Specifies which of the pre-built decorators to use (when using Flexwin as the payment method). This will override the customer specific decorator, if one has been uploaded.', 'dibs-for-woocommerce' ),
				'default'     => 'responsive',
			),
			'api_settings_title'       => array(
				'title'       => __( 'API Credentials', 'dibs-for-woocommerce' ),
				'type'        => 'title',
				'description' => sprintf( __( 'Enter your DIBS API user credentials to process refunds via DIBS. Learn how to access your DIBS API Credentials %1$shere%2$s.', 'dibs-for-woocommerce' ), '<a href="http://docs.krokedil.com/documentation/dibs-for-woocommerce/#4" target="_top">', '</a>' ),
			),
			'api_username'             => array(
				'title'       => __( 'API Username', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API credentials from DIBS.', 'dibs-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Optional', 'dibs-for-woocommerce' ),
			),
			'api_password'             => array(
				'title'       => __( 'API Password', 'dibs-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Get your API credentials from DIBS.', 'dibs-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Optional', 'dibs-for-woocommerce' ),
			),
			'test_mode_settings_title' => array(
				'title' => __( 'Test Mode Settings', 'dibs-for-woocommerce' ),
				'type'  => 'title',
			),
			'testmode'                 => array(
				'title'   => __( 'Test Mode', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable DIBS Test Mode. Read more about the <a href="http://tech.dibs.dk/10_step_guide/your_own_test/" target="_blank">DIBS test process here</a>.', 'dibs-for-woocommerce' ),
				'default' => 'yes',
			),
			'debug'                    => array(
				'title'   => __( 'Debug', 'dibs-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging (<code>woocommerce/logs/dibs.txt</code>)', 'dibs-for-woocommerce' ),
				'default' => 'no',
			),
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
		<h3><?php _e( 'DIBS', 'dibs-for-woocommerce' ); ?></h3>
		<p style="background: #ffcaca; padding: 1em; font-size: 1.1em; border: 0px solid #8c8f94; border-left:10px solid #cc0000;">
		<strong>Please note that this plugin will be retired early 2022.</strong> <br/>To continue to use Nets (previously DIBS) as your payment provider you need to upgrade to <a href="https://krokedil.com/product/nets-easy/" target="_blank">Nets Easy for WooCommerce</a>.<br/>If you don't you won't be able to accept payments when Nets D2 is closed down. <a href="https://www.nets.eu/payments/online" target="_blank">Get in touch with Nets</a> to upgrade your account. <br/>If you need help transitioning from the Nets D2 plugin to Nets Easy you can <a href="https://krokedil.com/contact/" target="_blank">contact Krokedil</a> - the developer team behind the plugins.
		</p>
		<p>
		<?php printf( __( 'Documentation <a href="%s" target="_blank">can be found here</a>.', 'dibs-for-woocommerce' ), 'http://docs.krokedil.com/documentation/dibs-for-woocommerce/' ); ?>
		</p>
		<table class="form-table">
		<?php
		if ( isset( $this->dibs_currency[ $this->selected_currency ] ) ) {
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
		} else {
			?>
			<tr valign="top">
			<th scope="row" class="titledesc">DIBS disabled</th>
			<td class="forminp">
			<fieldset>
			<legend class="screen-reader-text"><span>DIBS disabled</span></legend>
			<?php _e( 'DIBS does not support your store currency.', 'dibs-for-woocommerce' ); ?><br>
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
			$icon_src   = 'https://cdn.dibspayment.com/logo/checkout/combo/horiz/DIBS_checkout_kombo_horizontal_04.png';
			$icon_width = '145';
		}
		$icon_html = '<img src="' . $icon_src . '" alt="DIBS - Payments made easy" style="max-width:' . $icon_width . 'px"/>';

		return apply_filters( 'wc_dibs_icon_html', $icon_html );
	}

	/**
	 * Check if this gateway is enabled and available in user's country.
	 *
	 * @return bool
	 */
	function is_available() {
		if ( $this->enabled == 'yes' ) :
			return true;
			endif;

		return false;
	}

	/**
	 * Show receipt page.
	 *
	 * @param $order
	 */
	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with DIBS.', 'dibs-for-woocommerce' ) . '</p>';
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
		// ELEC, FISC, MPO_NETS, MPO_EULI, REMCARD, WOCO
		$paytypes = apply_filters( 'woocommerce_dibs_paytypes', 'AAK,ACCEPT,ACK,AKK,AMEX,BHBC,CCK,DAELLS,DIN,DK,VISA,EWORLD,FCC,FCK,FFK,FINX(SE),DISC,FLEGCARD,FSC,GIT,GSC,HEME,HEMP,HEMTX,HMK,HNYBORG,HSC,HTX,IBC,IKEA,ISHBY,JCB,JEM_FIX,KAUPBK,LFBBK,LIC(DK),LIC(SE),LOPLUS,MC,MEDM,MERLIN,MGNGC,MTRO,MYHC,NSBK,OESBK,Q8SK,REB,SEMCARD,ROEDCEN,S/T,SBSBK,SEB_KOBK,SEBSBK,SHB_KB,SILV_ERHV,SILV_PRIV,STARTOUR,TLK,TUBC,VEKO,VISA,AAL,ABN,AKTIA,BAX,CC,DBSE_A,DNFI_A,DNB,ECRED,ELV,FSB,GIRO_A,HNS,ING,NDB,OKO,P24_A,PAGOC,paypal,RBS,SEB,SEB_AC,SHB,SOLO,SOLOFI,TAP,ELEC,FISC,MPO_NETS,MPO_EULI,REMCARD,WOCO' );

		if ( ! empty( $paytypes ) ) {
			$args['paytype'] = $paytypes;
		}

		// What kind of payment is this - subscription payment or regular payment.
		if ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order_id, array( 'parent', 'resubscribe', 'switch', 'renewal' ) ) ) {

			if ( WC_Subscriptions_Order::get_total_initial_payment( $order ) == 0 ) {
				$price = 0;
			} else {
				$price = WC_Subscriptions_Order::get_total_initial_payment( $order );
				// Subscription payment
				$args['maketicket'] = '1';
			}

				// Price
				$args['amount'] = $price * 100;

		} else {

			// Price
			$args['amount'] = $order->get_total() * 100;

			// Instant capture if selected in settings
			if ( $this->capturenow == 'yes' ) {
				$args['capturenow'] = 'yes';
			}
		}

			// Order number
			$tmp_order_id = $order->get_order_number();
			$prefix       = 'n°'; // Strip n° (french translation)

		if ( substr( $tmp_order_id, 0, strlen( $prefix ) ) == $prefix ) {
			$tmp_order_id = substr( $tmp_order_id, strlen( $prefix ) );
		}

			$args['orderid'] = ltrim( $tmp_order_id, '#' ); // Strip #

			// Store the sent order number if it differs from order_id
		if ( $tmp_order_id !== $order_id ) {
			update_post_meta( $order_id, '_dibs_sent_order_id', $tmp_order_id );
		}

			// Language
		if ( 'wp' == $this->dibs_language ) {
			// Get ISO language code
			$iso_code     = explode( '_', get_locale() );
			$args['lang'] = $iso_code[0];
		} else {
			$args['lang'] = $this->dibs_language;
		}

			// Layout
		if ( ! empty( $this->decorator ) ) {
			$args['decorator'] = $this->decorator;
		}

			$args['ordertext'] = 'Name: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '. Address: ' . $order->get_billing_address_1() . ', ' . $order->get_billing_postcode() . ' ' . $order->get_billing_city();

			// Callback URL doesn't work as in the other gateways. DIBS erase everything
			// after a '?' in a specified callback URL
			$args['callbackurl'] = apply_filters( 'woocommerce_dibs_cc_callbackurl', trailingslashit( get_site_url( null, '/woocommerce/dibscallback' ) ) );
			$args['accepturl']   = trailingslashit( get_site_url( null, '/woocommerce/dibsaccept' ) );
			$args['cancelurl']   = trailingslashit( get_site_url( null, '/woocommerce/dibscancel' ) );

			// Testmode
		if ( 'yes' == $this->testmode ) {
			$args['test'] = 'yes';
		}

			// Calcfee
		if ( 'yes' == $this->calcfee ) {
			$args['calcfee'] = 'yes';
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

			// Check if this is a subscription payment method change or the subscription contains a free trial period.
		if ( class_exists( 'WC_Subscriptions_Order' ) && 0 == $args['amount'] ) {
			$args['preauth'] = 'true';
			$args['amount']  = 1;
			$args['md5key']  = MD5( $key2 . MD5( $key1 . 'transact=12345678&preauth=true&currency=123' ) );
			unset( $args['md5key'] );
		}

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

			wc_enqueue_js(
				'
			jQuery("body").block({
					message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to DIBS to make payment.', 'dibs-for-woocommerce' ) ) . '",
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
		'
			);

		// Print out and send the form
		return '<form action="' . $dibs_adr . '" method="post" id="dibs_cc_payment_form">
				' . $fields . '
				<input type="submit" class="button-alt" id="submit_dibs_cc_payment_form" value="' . __( 'Pay via dibs', 'dibs-for-woocommerce' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'dibs-for-woocommerce' ) . '</a>
			</form>';
	}

} // End class
