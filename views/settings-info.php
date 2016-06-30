<h4>Get started</h4>
<ul>
	<li><a href="http://docs.krokedil.com/documentation/dibs-d2-masterpass-for-woocommerce/" target="_blank">Documentation</a></li>
</ul>

<?php 
$checkout_page_id = wc_get_page_id( 'checkout' );
$checkout_url = '';
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
	echo '<h4>Callback URL to send to MasterPass</h4>';
	echo '<p><pre>' . $checkout_url . '</pre></p>';
}
?>

<h4>Support</h4>
<p>If you have any questions, register a support ticket at <a href="http://www.dibs.se/kundsupport" target="_blank">dibs.se/kundsupport</a> and we will help you.</p>
