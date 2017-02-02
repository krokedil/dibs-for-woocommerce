<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Returns error messages depending on 
 *
 * @class    WC_Gateway_Dibs_Admin_Notices
 * @version  2.4.
 * @package  WC_Gateway_Dibs/Classes
 * @category Class
 * @author   Krokedil
 */
class WC_Gateway_Dibs_Admin_Notices {
	
	/**
	 * WC_Gateway_Dibs_Admin_Notices constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'check_settings' ) );
	}
	
	public function check_settings() {
		add_action( 'admin_notices', array( $this, 'check_old_mp_plugin' ) );
	}
	
	/**
	 * Check if DIBS D2 MasterPass Gateway for WooCommerce is installed
	 */
	public function check_old_mp_plugin() {
		
		if( is_plugin_active('woocommerce-gateway-dibs-d2-masterpass/woocommerce-gateway-dibs-d2-masterpass.php') ) {
			echo '<div class="notice notice-error">';
			echo '<p>' . __( 'MasterPass is now available in the <em>DIBS for WooCommerce</em> extension. Please deactivate <em>	
DIBS D2 MasterPass Gateway for WooCommerce</em> to avoid issues.', 'dibs-for-woocommerce' ) . '</p>';
			echo '</div>';
		}
	}
	
}
$wc_gateway_dibs_admin_notices = new WC_Gateway_Dibs_Admin_Notices;