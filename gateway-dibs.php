<?php
/*
Plugin Name: WooCommerce DIBS FlexWin Gateway
Plugin URI: http://woocommerce.com
Description: Extends WooCommerce. Provides a <a href="http://www.http://www.dibspayment.com/" target="_blank">DIBS</a> gateway for WooCommerce.
Version: 1.3.1
Author: Niklas Högefjord
Author URI: http://krokedil.com
*/

/*  Copyright 2011-2012  Niklas Högefjord  (email : niklas@krokedil.se)

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
		
        $this->id					= 'dibs';
        $this->icon 				= plugins_url(basename(dirname(__FILE__))."/images/dibs.png");
        $this->has_fields 			= false;
        $this->log 					= $woocommerce->logger();
        
        $this->flexwin_url 			= 'https://payment.architrade.com/paymentweb/start.action';
        $this->paymentwindow_url 	= 'https://sat1.dibspayment.com/dibspaymentwindow/entrypoint';
        
		// Load the form fields.
		$this->init_form_fields();
		
		// Load the settings.
		$this->init_settings();
		
		// Define user set variables
		$this->title 			= ( isset( $this->settings['title'] ) ) ? $this->settings['title'] : '';
		$this->description 		= ( isset( $this->settings['description'] ) ) ? $this->settings['description'] : '';
		$this->merchant_id 		= ( isset( $this->settings['merchant_id'] ) ) ? $this->settings['merchant_id'] : '';
		$this->key_1 			= html_entity_decode($this->settings['key_1']);
		$this->key_2 			= html_entity_decode($this->settings['key_2']);
		$this->key_hmac 		= html_entity_decode($this->settings['key_hmac']);
		$this->payment_method 	= ( isset( $this->settings['payment_method'] ) ) ? $this->settings['payment_method'] : '';
		$this->capturenow 		= ( isset( $this->settings['capturenow'] ) ) ? $this->settings['capturenow'] : '';
		$this->language 		= ( isset( $this->settings['language'] ) ) ? $this->settings['language'] : '';
		$this->testmode			= ( isset( $this->settings['testmode'] ) ) ? $this->settings['testmode'] : '';	
		$this->debug			= ( isset( $this->settings['debug'] ) ) ? $this->settings['debug'] : '';
		
		// Actions
		add_action( 'init', array(&$this, 'check_callback') );
		add_action('valid-dibs-callback', array(&$this, 'successful_request') );
		add_action('woocommerce_receipt_dibs', array(&$this, 'receipt_page'));
		
		/* 1.6.6 */
		add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
 
		/* 2.0.0 */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		
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
		if ( !isset($this->dibs_currency[get_option('woocommerce_currency')]) ) {
			$this->enabled = "no";
		} else {
			$this->enabled = $this->settings['enabled'];
		}
    } 


	/**
     * Initialise Gateway Settings Form Fields
     */
    function init_form_fields() {
    
    	$this->form_fields = array(
			'enabled' => array(
							'title' => __( 'Enable/Disable', 'woothemes' ), 
							'type' => 'checkbox', 
							'label' => __( 'Enable DIBS', 'woothemes' ), 
							'default' => 'yes'
						), 
			'title' => array(
							'title' => __( 'Title', 'woothemes' ), 
							'type' => 'text', 
							'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ), 
							'default' => __( 'DIBS', 'woothemes' )
						),
			'description' => array(
							'title' => __( 'Description', 'woothemes' ), 
							'type' => 'textarea', 
							'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ), 
							'default' => __("Pay via DIBS using credit card or bank transfer.", 'woothemes')
						),
			'merchant_id' => array(
							'title' => __( 'DIBS Merchant ID', 'woothemes' ), 
							'type' => 'text', 
							'description' => __( 'Please enter your DIBS Merchant ID; this is needed in order to take payment.', 'woothemes' ), 
							'default' => ''
						),
			'key_1' => array(
							'title' => __( 'MD5 k1', 'woothemes' ), 
							'type' => 'text', 
							'description' => __( 'Please enter your DIBS MD5 k1; this is only needed when using Flexwin as the payment method.', 'woothemes' ), 
							'default' => ''
						),
			'key_2' => array(
							'title' => __( 'MD5 k2', 'woothemes' ), 
							'type' => 'text', 
							'description' => __( 'Please enter your DIBS MD5 k2; this is only needed when using Flexwin as the payment method.', 'woothemes' ), 
							'default' => ''
						),
			'key_hmac' => array(
							'title' => __( 'HMAC Key (k)', 'woothemes' ), 
							'type' => 'text', 
							'description' => __( 'Please enter your DIBS HMAC Key (k); this is only needed when using Payment Window as the payment method.', 'woothemes' ), 
							'default' => ''
						),
			'payment_method' => array(
								'title' => __( 'Payment Method', 'woothemes' ), 
								'type' => 'select',
								'options' => array('flexwin'=>__( 'Flexwin', 'woothemes' ), 'paymentwindow'=>__( 'Payment Window', 'woothemes' )),
								'description' => __( 'Choose payment method integration.', 'woothemes' ),
								'default' => 'flexwin',
								),
			'language' => array(
								'title' => __( 'Language', 'woothemes' ), 
								'type' => 'select',
								'options' => array('en'=>'English', 'da'=>'Danish', 'de'=>'German', 'es'=>'Spanish', 'fi'=>'Finnish', 'fo'=>'Faroese', 'fr'=>'French', 'it'=>'Italian', 'nl'=>'Dutch', 'no'=>'Norwegian', 'pl'=>'Polish (simplified)', 'sv'=>'Swedish', 'kl'=>'Greenlandic'),
								'description' => __( 'Set the language in which the page will be opened when the customer is redirected to DIBS.', 'woothemes' ), 
								'default' => 'sv'
							),
			'capturenow' => array(
							'title' => __( 'Instant capture (capturenow)', 'woothemes' ), 
							'type' => 'checkbox', 
							'label' => __( 'If checked the order amount is immediately transferred from the customer’s account to the shop’s account. Contact DIBS when using this function.', 'woothemes' ), 
							'default' => 'no'
						),
			'testmode' => array(
							'title' => __( 'Test Mode', 'woothemes' ), 
							'type' => 'checkbox', 
							'label' => __( 'Enable DIBS Test Mode. Read more about the <a href="http://tech.dibs.dk/10_step_guide/your_own_test/" target="_blank">DIBS test process here</a>.', 'woothemes' ), 
							'default' => 'yes'
						),
			'debug' => array(
								'title' => __( 'Debug', 'woothemes' ), 
								'type' => 'checkbox', 
								'label' => __( 'Enable logging (<code>woocommerce/logs/dibs.txt</code>)', 'woothemes' ), 
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
    	<h3><?php _e('DIBS', 'woothemes'); ?></h3>
    	<p><?php _e('DIBS works by sending the user to <a href="http://www.dibspayment.com/">DIBS</a> to enter their payment information.', 'woothemes'); ?></p>
    	<table class="form-table">
    	<?php
    		if ( isset($this->dibs_currency[get_option('woocommerce_currency')]) ) {
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
			} else { ?>
				<tr valign="top">
				<th scope="row" class="titledesc">DIBS disabled</th>
				<td class="forminp">
				<fieldset><legend class="screen-reader-text"><span>DIBS disabled</span></legend>
				<?php _e('DIBS does not support your store currency.', 'woocommerce'); ?><br>
				</fieldset></td>
				</tr>
			<?php
			} // End check currency
    	?>
		</table><!--/.form-table-->
    	<?php
    } // End admin_options()

    
    /**
	 * There are no payment fields for dibs, but we want to show the description if set.
	 **/
    function payment_fields() {
    	if ($this->description) echo wpautop(wptexturize($this->description));
    }

   
	/**
	 * Generate the dibs button link
	 **/
    public function generate_dibs_form( $order_id ) {
		global $woocommerce;
		
		$order = &new woocommerce_order( $order_id );
		
		// Post the form to the right address
		if ($this->payment_method == 'paymentwindow') {
			$dibs_adr = $this->paymentwindow_url;		
		} else {
			$dibs_adr = $this->flexwin_url;
		}
		
		
		$args =
			array(
				// Merchant
				'merchant' => $this->merchant_id,
				
				// Order
				'amount' => $order->order_total * 100,
				
				// Currency
				'currency' => $this->dibs_currency[get_option('woocommerce_currency')],	
				
		);
		
		
		// Payment Method - Payment Window
		if ($this->payment_method == 'paymentwindow') {
				
			$args['orderID'] = $order_id;
					
			// Language
			$args['language'] = $this->language;
					
			//'uniqueoid' => $order->order_key,
			//'uniqueoid' => $order_id,
			
			//'ordertext' => 'Name: ' . $order->billing_first_name . ' ' . $order->billing_last_name . '. Address: ' . $order->billing_address_1 . ', ' . $order->billing_postcode . ' ' . $order->billing_city,
				
			// URLs
			// Callback URL doesn't work as in the other gateways. DIBS erase everyting after a '?' in a specified callback URL 
			//$args['callbackUrl'] = trailingslashit(home_url());
			$args['callbackUrl'] = site_url('/woocommerce/dibscallback');
			// Accept URL only works without problem if you check the box "Skip step 3 Payment approved" under ->Integration ->FlexWin in your DIBS account.
			$args['acceptReturnUrl'] = $this->get_return_url( $order );
			$args['cancelreturnurl'] = trailingslashit($order->get_cancel_order_url());
					
			// Address info
			$args['billingFirstName'] = $order->billing_first_name;
			$args['billingLastName'] = $order->billing_last_name;
			$args['billingAddress'] = $order->billing_address_1;
			$args['billingAddress2'] = $order->billing_address_2;
			$args['billingPostalPlace'] = $order->billing_city;
			$args['billingPostalCode'] = $order->billing_postcode;
			$args['billingEmail'] = $order->billing_email;
			$args['billingMobile'] = $order->billing_phone;
			
			// Testmode
			if ( $this->testmode == 'yes' ) {
				$args['test'] = '1';
			}
			
			// Instant capture
			if ( $this->capturenow == 'yes' ) {
				$args['capturenow'] = '1';
			}
			
			// HMAC
			$formKeyValues = $args;
			require_once('calculateMac.php');
			$logfile = '';
			// Calculate the MAC for the form key-values to be posted to DIBS.
  			$MAC = calculateMac($formKeyValues, $this->key_hmac, $logfile);
  			
  			// Add MAC to the $args array
  			$args['MAC'] = $MAC;
  			
		} else {
			
			// Payment Method - Flexwin
			
			//'orderid' => $order_id,
			$args['orderid'] = $order->get_order_number();
		
			// Language
			$args['lang'] =  $this->language;
			
			//'uniqueoid' => $order->order_key,
			$args['uniqueoid'] = $order_id;
			
			$args['ordertext'] = 'Name: ' . $order->billing_first_name . ' ' . $order->billing_last_name . '. Address: ' . $order->billing_address_1 . ', ' . $order->billing_postcode . ' ' . $order->billing_city;
				
			// URLs
			// Callback URL doesn't work as in the other gateways. DIBS erase everyting after a '?' in a specified callback URL 
			//$args['callbackurl'] = trailingslashit(home_url());
			$args['callbackUrl'] = site_url('/woocommerce/dibscallback');
			// Accept URL only works without problem if you check the box "Skip step 3 Payment approved" under ->Integration ->FlexWin in your DIBS account.
			$args['accepturl'] = add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id'))));
			$args['cancelurl'] = urlencode($order->get_cancel_order_url());
			
			// Testmode
			if ( $this->testmode == 'yes' ) {
				$args['test'] = 'yes';
			}
			
			// Instant capture
			if ( $this->capturenow == 'yes' ) {
				$args['capturenow'] = 'yes';
			}
			
			// IP
			if( !empty($_SERVER['HTTP_CLIENT_IP']) ) {
				$args['ip'] = $_SERVER['HTTP_CLIENT_IP'];
			}

			
			// MD5
			//var_dump($order->get_order_number());
			//die();
			// Calculate key
			// http://tech.dibs.dk/dibs_api/other_features/md5-key_control/
			$key1 = $this->key_1;
			$key2 = $this->key_2;
			$merchant = $this->merchant_id;
			//$orderid = $order_id;
			$orderid = $order->get_order_number();
			$currency = $this->dibs_currency[get_option('woocommerce_currency')];
			$amount = $order->order_total * 100;	
			$postvars = 'merchant=' . $merchant . '&orderid=' . $orderid . '&currency=' . $currency . '&amount=' . $amount;
		
			$args['md5key'] = MD5($key2 . MD5($key1 . $postvars));
		}
						
				
		
		// Prepare the form
		$fields = '';
		$tmp_log = '';
		foreach ($args as $key => $value) {
			$fields .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />';
			
			// Debug preparing
			$tmp_log .= $key . '=' . $value . "\r\n";
		}
		
		// Debug
		if ($this->debug=='yes') :	
        	$this->log->add( 'dibs', 'Sending values to DIBS: ' . $tmp_log );	
        endif;
		
		
		// Print out and send the form
		//var_dump($dibs_adr);
		//die();
		return '<form action="'.$dibs_adr.'" method="post" id="dibs_payment_form">
				' . $fields . '
				<input type="submit" class="button-alt" id="submit_dibs_payment_form" value="'.__('Pay via dibs', 'woothemes').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'woothemes').'</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{ 
								message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to dibs to make payment.', 'woothemes').'", 
								overlayCSS: 
								{ 
									background: "#fff", 
									opacity: 0.6 
								},
								css: { 
							        padding:        20, 
							        textAlign:      "center", 
							        color:          "#555", 
							        border:         "3px solid #aaa", 
							        backgroundColor:"#fff", 
							        cursor:         "wait",
							        lineHeight:		"32px"
							    } 
							});
						jQuery("#submit_dibs_payment_form").click();
					});
				</script>
			</form>';
		
	}


	/**
	 * Process the payment and return the result
	 **/
	function process_payment( $order_id ) {
		
		$order = &new woocommerce_order( $order_id );
		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
		);
		
	}

	
	/**
	 * receipt_page
	 **/
	function receipt_page( $order ) {
		
		echo '<p>'.__('Thank you for your order, please click the button below to pay with DIBS.', 'woothemes').'</p>';
		
		echo $this->generate_dibs_form( $order );
		
	}

	
	/**
	* Check for DIBS Response
	**/
	function check_callback() {
		// Check for both IPN callback (dibscallback) and buyer-return-to-shop callback (statuscode)
		//if ( ( strpos($_SERVER["REQUEST_URI"], 'woocommerce/dibscallback') !== false ) || isset($_GET['statuscode']) && isset($_GET['orderid']) ) {
		if ( ( strpos($_SERVER["REQUEST_URI"], 'woocommerce/dibscallback') !== false ) || ( ( isset($_GET['statuscode']) || isset($_GET['status']) ) && ( isset($_GET['orderid']) || isset($_GET['orderID']) ) ) ) {
			
			$this->log->add( 'dibs', 'Incoming callback from DIBS: ' );
			
			//$_POST = stripslashes_deep($_POST);
			header("HTTP/1.1 200 Ok");
			
			$tmp_log ='';
			
			foreach ( $_REQUEST as $key => $value ) {
				$tmp_log .= $key . '=' . $value . "\r\n";
			}
			$this->log->add( 'dibs', 'Returning values from DIBS: ' . $tmp_log );
			do_action("valid-dibs-callback", stripslashes_deep($_REQUEST));

		}
	}


	/**
	* Successful Payment!
	**/
	function successful_request( $posted ) {
		
		
		// Debug
		if ($this->debug=='yes') :
			
			$tmp_log = '';
			
			foreach ( $posted as $key => $value ) {
				$tmp_log .= $key . '=' . $value . "\r\n";
			}
		
        	$this->log->add( 'dibs', 'Returning values from DIBS: ' . $tmp_log );	
        endif;


		// Flexwin callback
		if ( isset($posted['transact']) && !empty($posted['uniqueoid']) && is_numeric($posted['uniqueoid']) ) {
			
			// Verify MD5 checksum
			// http://tech.dibs.dk/dibs_api/other_features/md5-key_control/	
			$key1 = $this->key_1;
			$key2 = $this->key_2;
			$vars = 'transact='. $posted['transact'] . '&amount=' . $posted['amount'] . '&currency=' . $posted['currency'];
			$md5 = MD5($key2 . MD5($key1 . $vars));
			
			$order_id = (int) $posted['uniqueoid'];
			
			$order = new woocommerce_order( $order_id );
			
			if($posted['authkey'] != $md5) {
				// MD5 check failed
				$order->update_status('failed', sprintf(__('MD5 check failed. DIBS transaction ID: %s', 'woocommerce'), strtolower($posted['transaction']) ) );
				
			}	
			
			
			if ($order->status !== 'completed') {
				switch (strtolower($posted['statuscode'])) :
	            	case '2' :
	            	case '5' :
	            	case '12' :
	            		// Order completed
	            		$order->add_order_note( __('DIBS payment completed. DIBS transaction number: ', 'woocommerce') . $posted['transact'] );
	            		$order->payment_complete();
	            	break;
	            	case '0' :
	            	case '1' :
	            	case '4' :
	            	case '17' :
	            		// Order failed
	            		$order->update_status('failed', sprintf(__('DIBS payment %s not approved. Status code %s.', 'woocommerce'), strtolower($posted['transaction']), $posted['statuscode'] ) );
	            	break;
	            	
	            	default:
	            	// No action
	            	break;
	            	
	            endswitch;
			
			}
			
			// Return to Thank you page if this is a buyer-return-to-shop callback
			wp_redirect( add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id')))) );
	            
			exit;
			
		} 
		
		// Payment Window callback
		if ( isset($posted["transaction"]) && !empty($posted['orderID']) && is_numeric($posted['orderID']) ) {	
				
			$order_id = $posted['orderID'];
			$this->log->add( 'dibs', 'Tjoho.' . $order_id );
			$order = new woocommerce_order( $order_id );
					
			if ( $order->status == 'completed' || $order->status == 'processing' ) {
				
				// Debug
				if ($this->debug=='yes') :
	        		$this->log->add( 'dibs', 'Second Payment window callback. Do nothing.' );			
				endif;
				
				exit;
				
			}
			
			// Verify HMAC
			require_once('calculateMac.php');
  			$MAC = calculateMac($posted, $this->key_hmac, $logfile);
  	
			if($posted['MAC'] != $MAC) {
				$order->add_order_note( __('HMAC check failed for Dibs callback with order_id: ', 'woocommerce') .$posted['transaction'] );
				exit;
			}
				
			switch (strtolower($posted['status'])) :
	            case 'accepted' :
	            case 'pending' :
	            
	            	// Order completed
					$order->add_order_note( sprintf(__('DIBS payment completed. DIBS transaction number: %s.', 'woocommerce'), $posted['transaction'] ));
					$order->payment_complete();
					
					
				break;
				
				case 'declined' :
				case 'error' :
				
					// Order failed
	                $order->update_status('failed', sprintf(__('Payment %s via IPN.', 'woocommerce'), strtolower($posted['transaction']) ) );
	                
	            break;
	            
	            default:
	            	// No action
	            break;
	        endswitch;
	        
	        // Return to Thank you page if this is a buyer-return-to-shop callback
			wp_redirect( add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id')))) );
			
			exit;
			
		}
		
	}
	
	
} // Close class WC_Gateway_Dibs



} // Close init_dibs_gateway

/**
 * Add the gateway to WooCommerce
 **/
function add_dibs_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Dibs'; return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_dibs_gateway' );
