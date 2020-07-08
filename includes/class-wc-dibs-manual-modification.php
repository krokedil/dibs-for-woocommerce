<?php

/**
 * Class WC_Dibs_Manual_Modification
 */
class WC_Dibs_Manual_Modification {

	/**
	 * WC_Dibs_Manual_Modification constructor.
	 */
	public function __construct() {
		// Meta boxes
		add_action( 'add_meta_boxes', array( $this, 'dibs_transaction_metabox' ) );
		add_action( 'save_post', array( $this, 'save_metabox' ) );
	}

	/**
	 * DIBS Transaction no & ticket ID metabox.
	 *
	 * @param $post_type
	 */
	public function dibs_transaction_metabox( $post_type ) {
		add_meta_box(
			'wc_dibs_transaction_metabox', __( 'DIBS Order transaction details', 'dibs-for-woocommerce' ), array(
				$this,
				'render_transaction_meta_box_content',
			), 'shop_order', 'advanced', 'high'
		);
	}

	/**
	 * Render DIBS Transaction Meta Box content.
	 *
	 * @param $post WP_Post object.
	 */
	public function render_transaction_meta_box_content( $post ) {
		$order = wc_get_order( $post->ID );

		// Only display the metabox if DIBS is the used payment gateway
		if ( ! in_array( $order->get_payment_method(), array( 'dibs', 'dibs_2', 'dibs_3' ), true ) ) {
			return;
		}

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'wc_dibs_transaction_metabox', 'wc_dibs_transaction_metabox_nonce' );

		?>

		<div class="woocommerce_dibs_transaction_wrapper">
			<table cellpadding="0" cellspacing="0" class="woocommerce_order_items dibs_transaction_table">
				<thead>
				<tr>
					<th class="dibs-transaction"><?php _e( 'DIBS Transaction number', 'dibs-for-woocommerce' ); ?></th>
					<th class="dibs-ticket"><?php _e( 'DIBS Ticket number (for subscription payments)', 'dibs-for-woocommerce' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr>
					<td class="dibs-transaction"><input type="text" name="woocommerce_dibs_transaction"
														id="woocommerce_dibs_transaction"
														value="<?php echo esc_attr( get_post_meta( $post->ID, '_dibs_transaction_no', true ) ); ?>"/>
					</td>
					<td class="dibs-ticket"><input type="text" name="woocommerce_dibs_ticket"
												   id="woocommerce_dibs_ticket"
												   value="<?php echo esc_attr( get_post_meta( $post->ID, '_dibs_ticket', true ) ); ?>"/>
					</td>

				</tr>
				</tbody>
			</table>
		</div>

		<?php
	}

	/**
	 * Save the meta when the post is saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 *
	 * @return int
	 */
	public function save_metabox( $post_id ) {
		// We need to verify this came from the our screen and with proper authorization,
		// because save_post can be triggered at other times.
		// Check if our nonce is set.
		if ( ! isset( $_POST['wc_dibs_transaction_metabox_nonce'] ) ) {
			return $post_id;
		}

		$nonce = $_POST['wc_dibs_transaction_metabox_nonce'];

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce, 'wc_dibs_transaction_metabox' ) ) {
			return $post_id;
		}

		// If this is an autosave, our form has not been submitted,
		// so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check the user's permissions.
		if ( ! current_user_can( 'manage_woocommerce', $post_id ) ) {
			return $post_id;
		}

		/* OK, its safe for us to save the data now. */

		// Sanitize the user input.
		$dibs_ticket      = '';
		$dibs_transaction = '';
		$dibs_ticket      = sanitize_text_field( $_POST['woocommerce_dibs_ticket'] );
		$dibs_transaction = sanitize_text_field( $_POST['woocommerce_dibs_transaction'] );

		update_post_meta( $post_id, '_dibs_transaction_no', $dibs_transaction );
		update_post_meta( $post_id, '_transaction_id', $dibs_transaction );
		update_post_meta( $post_id, '_dibs_ticket', $dibs_ticket );
	}

}
$wc_dibs_manual_modification = new WC_Dibs_Manual_Modification();
