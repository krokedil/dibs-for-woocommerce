<?php
/*
 * Plugin Name: DIBS for WooCommerce
 * Plugin URI: https://krokedil.se/produkt/dibs/
 * Description: Extends WooCommerce. Provides a <a href="https://www.dibspayment.com/" target="_blank">DIBS</a> gateway for WooCommerce.
 * Version: 2.5.1
 * Author: Krokedil
 * Author URI: https://krokedil.se
 * Text Domain: dibs-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 3.2.0
*/

/*  Copyright 2011-2017  Krokedil Produktionsbyrå AB  (email : info@krokedil.se)

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

// Plugin Folder Path
if ( ! defined( 'WC_DIBS_PLUGIN_DIR' ) ) {
	define( 'WC_DIBS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin Folder URL
if ( ! defined( 'WC_DIBS_PLUGIN_URL' ) ) {
	define( 'WC_DIBS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
// Plugin version
define( 'WC_DIBS_VERSION', '2.5.1' );

require_once( plugin_dir_path( __FILE__ ) . 'includes/class-dibs-extra.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-dibs-masterpass-functions.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-dibs-admin-notices.php' );

// Init DIBS Gateway after WooCommerce has loaded
add_action( 'plugins_loaded', 'init_dibs_gateway', 0 );

function init_dibs_gateway() {
	// If the WooCommerce payment gateway class is not available, do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}


	class WC_Gateway_Dibs extends WC_Payment_Gateway {

		public function __construct() {
			$this->selected_currency = get_woocommerce_currency();
		}

	} // Close class WC_Gateway_Dibs

	// Localisation
	load_plugin_textdomain( 'dibs-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	// Include our Dibs factory class
	require_once( WC_DIBS_PLUGIN_DIR . 'includes/gateways/class-dibs-factory.php' );
	
	// Include our Dibs credit card class
	require_once( WC_DIBS_PLUGIN_DIR . 'includes/gateways/class-dibs-cc.php' );

	// Include our Dibs Invoice class
	require_once( WC_DIBS_PLUGIN_DIR . 'includes/gateways/class-dibs-invoice.php' );

	// Include our Dibs Mobile Pay class
	require_once( WC_DIBS_PLUGIN_DIR . 'includes/gateways/class-dibs-mobilepay.php' );
	
	// Include our Dibs MasterPass class
	require_once( WC_DIBS_PLUGIN_DIR . 'includes/gateways/class-dibs-masterpass.php' );

	// Check if we should include the Dibs manual modification class
	if ( defined( 'WC_DIBS_DEBUG' ) && true === WC_DIBS_DEBUG ) {
		require_once( WC_DIBS_PLUGIN_DIR . 'includes/class-wc-dibs-manual-modification.php' );
	}
}

/**
 * Add the gateway to WooCommerce
 **/
add_filter( 'woocommerce_payment_gateways', 'add_dibs_gateway' );
function add_dibs_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Dibs_CC';
	$methods[] = 'WC_Gateway_Dibs_Invoice';
	$methods[] = 'WC_Gateway_Dibs_MobilePay';
	$methods[] = 'WC_Gateway_Dibs_MasterPass_New';

	return $methods;
}