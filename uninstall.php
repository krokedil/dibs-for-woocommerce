<?php
/**
 * WooCommerce DIBS Gateway
 * By Niklas Högefjord (niklas@krokedil.se)
 *
 * Uninstall - removes all DIBS options from DB when user deletes the plugin via WordPress backend.
 * @since 0.3
 **/

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

delete_option( 'woocommerce_dibs_settings' );
delete_option( 'woocommerce_dibs_invoice_settings' );