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
		add_action( 'admin_notices', array( $this, 'display_shutdown_notice' ) );
	}

	/**
	 * Display plugin shotdown message.
	 */
	public function display_shutdown_notice() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( 'dashboard' !== $screen->id ) {
			return;
		}
		ob_start();
		?>
		<p style="background: #ffcaca; padding: 1em; font-size: 1.1em; border: 0px solid #8c8f94; border-left:10px solid #cc0000;">
			<strong>Please note that the <i>Nets D2 for WooCommerce</i> plugin will be retired early 2022.</strong> <br/>To continue to use Nets (previously DIBS) as your payment provider you need to upgrade to <a href="https://krokedil.com/product/nets-easy/" target="_blank">Nets Easy for WooCommerce</a>.<br/>If you don't you won't be able to accept payments when Nets D2 is closed down. <a href="https://www.nets.eu/payments/online" target="_blank">Get in touch with Nets</a> to upgrade your account. <br/>If you need help transitioning from the Nets D2 plugin to Nets Easy you can <a href="https://krokedil.com/contact/" target="_blank">contact Krokedil</a> - the developer team behind the plugins.
		</p>
		<?php
		echo ob_get_clean();
	}

}
$wc_gateway_dibs_admin_notices = new WC_Gateway_Dibs_Admin_Notices();
