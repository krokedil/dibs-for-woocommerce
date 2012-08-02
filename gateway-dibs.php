<?php
/*
Plugin Name: WooCommerce DIBS FlexWin Gateway
Plugin URI: http://woocommerce.com
Description: Extends WooCommerce. Provides a <a href="http://www.http://www.dibspayment.com/" target="_blank">DIBS</a> gateway for WooCommerce.
Version: 1.2
Author: Niklas Högefjord
Author URI: http://krokedil.com
*/

/*  Copyright 2011  Niklas Högefjord  (email : niklas@krokedil.se)

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

// Init DIBS Gateway after WooCommerce has loaded
add_action('plugins_loaded', 'init_dibs_gateway', 0);

function init_dibs_gateway() {

// If the WooCommerce payment gateway class is not available, do nothing
if ( !class_exists( 'woocommerce_payment_gateway' ) ) return;
	
class woocommerce_dibs extends woocommerce_payment_gateway {
		
	public function __construct() { 
		global $woocommerce;
		
        $this->id			= 'dibs';
        $this->icon 		= plugins_url(basename(dirname(__FILE__))."/images/dibs.png");
        $this->has_fields 	= false;
        $this->log 			= $woocommerce->logger();
        
        $this->liveurl 		= 'https://payment.architrade.com/paymentweb/start.action';
        
		// Load the form fields.
		$this->init_form_fields();
		
		// Load the settings.
		$this->init_settings();
		
		// Define user set variables
		$this->title 		= ( isset( $this->settings['title'] ) ) ? $this->settings['title'] : '';
		$this->description 	= ( isset( $this->settings['description'] ) ) ? $this->settings['description'] : '';
		$this->merchant_id 	= ( isset( $this->settings['merchant_id'] ) ) ? $this->settings['merchant_id'] : '';
		$this->key_1 		= html_entity_decode($this->settings['key_1']);
		$this->key_2 		= html_entity_decode($this->settings['key_2']);
		$this->language 	= ( isset( $this->settings['language'] ) ) ? $this->settings['language'] : '';
		$this->testmode		= ( isset( $this->settings['testmode'] ) ) ? $this->settings['testmode'] : '';	
		$this->debug		= ( isset( $this->settings['debug'] ) ) ? $this->settings['debug'] : '';
		
		// Actions
		add_action( 'init', array(&$this, 'check_callback') );
		add_action('valid-dibs-callback', array(&$this, 'successful_request') );
		add_action('woocommerce_receipt_dibs', array(&$this, 'receipt_page'));
		add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
		
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
							'label' => __( 'Enable DIBS FlexWin', 'woothemes' ), 
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
							'description' => __( 'Please enter your DIBS MD5 k1; this is needed in order to take payment.', 'woothemes' ), 
							'default' => ''
						),
			'key_2' => array(
							'title' => __( 'MD5 k2', 'woothemes' ), 
							'type' => 'text', 
							'description' => __( 'Please enter your DIBS MD5 k2; this is needed in order to take payment.', 'woothemes' ), 
							'default' => ''
						),
			'language' => array(
								'title' => __( 'Language', 'woothemes' ), 
								'type' => 'select',
								'options' => array('en'=>'English', 'da'=>'Danish', 'de'=>'German', 'es'=>'Spanish', 'fi'=>'Finnish', 'fo'=>'Faroese', 'fr'=>'French', 'it'=>'Italian', 'nl'=>'Dutch', 'no'=>'Norwegian', 'pl'=>'Polish (simplified)', 'sv'=>'Swedish', 'kl'=>'Greenlandic'),
								'description' => __( 'Set the language in which the page will be opened when the customer is redirected to DIBS.', 'woothemes' ), 
								'default' => 'sv'
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
    	<h3><?php _e('DIBS FlexWin', 'woothemes'); ?></h3>
    	<p><?php _e('DIBS FlexWin works by sending the user to <a href="http://www.dibspayment.com/">DIBS</a> to enter their payment information.', 'woothemes'); ?></p>
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
		
		$dibs_adr = $this->liveurl;		
		
		$args =
			array(
				// Merchant
				'merchant' => $this->merchant_id,
				
				// Session
				'lang' => $this->language,
				
				// Order
				'amount' => $order->order_total * 100,
				'orderid' => $order_id,
				'uniqueoid' => $order->order_key,
				'currency' => $this->dibs_currency[get_option('woocommerce_currency')],
				'ordertext' => 'Name: ' . $order->billing_first_name . ' ' . $order->billing_last_name . '. Address: ' . $order->billing_address_1 . ', ' . $order->billing_postcode . ' ' . $order->billing_city,
				
				// URLs
				// Callback URL doesn't work as in the other gateways. DIBS erase everyting after a '?' in a specified callback URL 
				'callbackurl' => home_url(),
				// Accept URL only works without problem if you check the box "Skip step 3 Payment approved" under ->Integration ->FlexWin in your DIBS account.
				'accepturl' => add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(get_option('woocommerce_thanks_page_id')))),
				'cancelurl' => urlencode($order->get_cancel_order_url()),
				
		);
		
		// Calculate key
		// http://tech.dibs.dk/dibs_api/other_features/md5-key_control/
		$key1 = $this->key_1;
		$key2 = $this->key_2;
		$merchant = $this->merchant_id;
		$orderid = $order_id;
		$currency = $this->dibs_currency[get_option('woocommerce_currency')];
		$amount = $order->order_total * 100;	
		$postvars = 'merchant=' . $merchant . '&orderid=' . $orderid . '&currency=' . $currency . '&amount=' . $amount;
		
		$args['md5key'] = MD5($key2 . MD5($key1 . $postvars));
		
		
		if( !empty($_SERVER['HTTP_CLIENT_IP']) ) {
			$args['ip'] = $_SERVER['HTTP_CLIENT_IP'];
		}
		
		
		if ( $this->testmode == 'yes' ) {
			$args['test'] = 'yes';
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
	
		if ( isset($_POST["transact"]) ) :
			
			$_POST = stripslashes_deep($_POST);
			do_action("valid-dibs-callback", $_POST);

		endif;
	}


	/**
	* Successful Payment!
	**/
	function successful_request( $posted ) {
		
		
		// Debug
		if ($this->debug=='yes') :
			
			foreach ( $posted as $key => $value ) {
				$tmp_log .= $key . '=' . $value . "\r\n";
			}
		
        	$this->log->add( 'dibs', 'Returning values from DIBS: ' . $tmp_log );	
        endif;


		if ( !empty($posted['orderid']) && is_numeric($posted['orderid']) ) {
			
			// Verify MD5 checksum
			// http://tech.dibs.dk/dibs_api/other_features/md5-key_control/	
			$key1 = $this->key_1;
			$key2 = $this->key_2;
			$vars = 'transact='. $posted['transact'] . '&amount=' . $posted['amount'] . '&currency=' . $posted['currency'];
			$md5 = MD5($key2 . MD5($key1 . $vars));
			
			$order = new woocommerce_order( (int) $posted['orderid'] );
			
			if($posted['authkey'] != $md5) {
				// MD5 check failed
				$order->add_order_note( __('MD5 check failed for Dibs callback with order_id: ', 'woocommerce') .$posted['orderid'] );
				exit();
			}	
		
			if ($order->order_key !== $posted['uniqueoid']) {
				// Unique ID check failed
				$order->add_order_note( __('Unique ID check failed for Dibs callback with order_id:', 'woocommerce') .$posted['orderid'] );
				exit;
			}
		
			if ($order->status !== 'completed') {
				$order->add_order_note( __('DIBS payment completed. DIBS transaction number: ', 'woocommerce') . $posted['transact'] );
				$order->payment_complete();
			
			}
			
			exit;
			
		}
		
	}
	
	
} // Close class woocommerce_dibs



} // Close init_dibs_gateway

/**
 * Add the gateway to WooCommerce
 **/
function add_dibs_gateway( $methods ) {
	$methods[] = 'woocommerce_dibs'; return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_dibs_gateway' );
