<?php


/**
 * Class WC_Gateway_Dibs_Factory
 */
class WC_Gateway_Dibs_Factory extends WC_Gateway_Dibs {

	/**
	 * There are no payment fields for dibs, but we want to show the description if set.
	 **/
	function payment_fields() {
		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Process successful payment.
	 *
	 * @param $posted
	 */
	function successful_request( $posted ) {
		// Debug
		if ( $this->debug == 'yes' ) :
			$tmp_log = '';

			foreach ( $posted as $key => $value ) {
				$tmp_log .= $key . '=' . $value . "\r\n";
			}

			$this->log->add( 'dibs', 'Returning values from DIBS: ' . $tmp_log );
		endif;

		// Flexwin callback
		if ( isset( $posted['transact'] ) && isset( $posted['orderid'] ) ) {
			// Verify MD5 checksum
			// http://tech.dibs.dk/dibs_api/other_features/md5-key_control/
			$key1 = $this->key_1;
			$key2 = $this->key_2;
			$vars = 'transact=' . $posted['transact'] . '&amount=' . $posted['amount'] . '&currency=' . $posted['currency'];
			$md5  = MD5( $key2 . MD5( $key1 . $vars ) );

			$order_id = $this->get_order_id( $posted['orderid'] );

			$order = wc_get_order( $order_id );

			// Prepare redirect url
			$redirect_url = $order->get_checkout_order_received_url();

			// WPML compatibility hack
			$lang_code = get_post_meta( $order_id, 'wpml_language', true );
			if ( $lang_code ) {
				$redirect_url = apply_filters( 'wpml_permalink', $redirect_url, $lang_code );
			}

			// Subscription payment method change or new subscription with a free trial
			if ( isset( $posted['preauth'] ) && 'true' == $posted['preauth'] && '13' == $posted['statuscode'] ) {
				update_post_meta( $order_id, '_dibs_ticket', $posted['transact'] );

				if ( wcs_order_contains_subscription( $order_id ) ) {

					// New subscription but with a free trial.
					// Multiple callbacks are sent from DIBS. Don't add an order note if we already have done this
					if ( $order->get_status() !== 'completed' || $order->get_status() !== 'processing' ) {
						$order->add_order_note( sprintf( __( 'DIBS subscription ticket number: %s.', 'dibs-for-woocommerce' ), $posted['transact'] ) );
						$order->payment_complete( $posted['transact'] );
					}
				} else {

					// Payment method change
					$order->add_order_note( sprintf( __( 'Payment method updated. DIBS subscription ticket number: %s.', 'dibs-for-woocommerce' ), $posted['transact'] ) );
					wc_add_notice( sprintf( __( 'Your card %s is now stored with DIBS and will be used for future subscription renewal payments.', 'dibs-for-woocommerce' ), $posted['cardnomask'] ), 'success' );

					// Change redirect url
					$redirect_url = get_permalink( wc_get_page_id( 'myaccount' ) );
				}

				// Store card details in the subscription
				if ( isset( $posted['cardnomask'] ) ) {
					update_post_meta( $order_id, '_dibs_cardnomask', $posted['cardnomask'] );
				}
				if ( isset( $posted['cardprefix'] ) ) {
					update_post_meta( $order_id, '_dibs_cardprefix', $posted['cardprefix'] );
				}
				if ( isset( $posted['cardexpdate'] ) ) {
					update_post_meta( $order_id, '_dibs_cardexpdate', $posted['cardexpdate'] );
				}

				wp_redirect( $redirect_url );
				exit;
			}

			// Should we add Ticket id? This might be returned in a separate callback
			if ( isset( $posted['ticket'] ) ) {
				update_post_meta( $order_id, '_dibs_ticket', $posted['ticket'] );
				$order->add_order_note( sprintf( __( 'DIBS subscription ticket number: %s.', 'dibs-for-woocommerce' ), $posted['ticket'] ) );
				if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
					$subs = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) );
					foreach ( $subs as $subscription ) {
						update_post_meta( $subscription->get_id(), '_dibs_ticket', $posted['ticket'] );
						update_post_meta( $subscription->get_id(), '_dibs_transact', $posted['transact'] );

						// Store card details in the subscription
						if ( isset( $posted['cardnomask'] ) ) {
							update_post_meta( $subscription->get_id(), '_dibs_cardnomask', $posted['cardnomask'] );
						}
						if ( isset( $posted['cardprefix'] ) ) {
							update_post_meta( $subscription->get_id(), '_dibs_cardprefix', $posted['cardprefix'] );
						}
						if ( isset( $posted['cardexpdate'] ) ) {
							update_post_meta( $subscription->get_id(), '_dibs_cardexpdate', $posted['cardexpdate'] );
						}
					}
				}
			}

			// Check order not already completed or processing
			// (to avoid multiple callbacks from DIBS - IPN & return-to-shop callback
			if ( $order->get_status() == 'completed' || $order->get_status() == 'processing' ) {
				if ( $this->debug == 'yes' ) {
					$this->log->add( 'dibs', 'Aborting, Order #' . $order_id . ' is already complete.' );
				}

				wp_redirect( $redirect_url );
				exit;
			}

			// Verify MD5
			if ( $posted['authkey'] != $md5 ) {
				// MD5 check failed
				$order->update_status( 'failed', sprintf( __( 'MD5 check failed. DIBS transaction ID: %s', 'dibs-for-woocommerce' ), strtolower( $posted['transaction'] ) ) );
			}

			// Set order status
			if ( $order->get_status() !== 'completed' || $order->get_status() !== 'processing' ) {
				switch ( strtolower( $posted['statuscode'] ) ) :
					case '2':
					case '5':
						// Order completed
						$order->add_order_note( __( 'DIBS payment completed. DIBS transaction number: ', 'dibs-for-woocommerce' ) . $posted['transact'] );
						// Transaction captured
						if ( $this->capturenow == 'yes' ) {
							update_post_meta( $order_id, '_dibs_order_captured', 'yes' );
							$order->add_order_note( __( 'DIBS transaction captured.', 'dibs-for-woocommerce' ) );
						}
						// Store Transaction number as post meta
						update_post_meta( $order_id, '_dibs_transaction_no', $posted['transact'] );

						if ( isset( $posted['ticket'] ) ) {
							update_post_meta( $order_id, '_dibs_ticket', $posted['ticket'] );
							$order->add_order_note( sprintf( __( 'DIBS subscription ticket number: %s.', 'dibs-for-woocommerce' ), $posted['ticket'] ) );
						}

						$order->payment_complete( $posted['transact'] );
						break;
					case '12':
						// Store Transaction number as post meta
						update_post_meta( $order_id, '_dibs_transaction_no', $posted['transact'] );

						if ( isset( $posted['ticket'] ) ) {
							update_post_meta( $order_id, '_dibs_ticket', $posted['ticket'] );
							$order->add_order_note( sprintf( __( 'DIBS subscription ticket number: %s.', 'dibs-for-woocommerce' ), $posted['ticket'] ) );
						}

						// Store card details
						if ( isset( $posted['cardnomask'] ) ) {
							update_post_meta( $order_id, '_dibs_cardnomask', $posted['cardnomask'] );
						}
						if ( isset( $posted['cardprefix'] ) ) {
							update_post_meta( $order_id, '_dibs_cardprefix', $posted['cardprefix'] );
						}
						if ( isset( $posted['cardexpdate'] ) ) {
							update_post_meta( $order_id, '_dibs_cardexpdate', $posted['cardexpdate'] );
						}
						$order->add_order_note( sprintf( __( 'DIBS Payment Pending. Check with DIBS for further information. DIBS transaction number: %s', 'dibs-for-woocommerce' ), $posted['transact'] ) );
						$order->payment_complete( $posted['transact'] );
						break;
					case '0':
					case '1':
					case '4':
					case '17':
						// Order failed
						$order->update_status( 'failed', sprintf( __( 'DIBS payment %1$s not approved. Status code %2$s.', 'dibs-for-woocommerce' ), strtolower( $posted['transaction'] ), $posted['statuscode'] ) );
						break;

					default:
						// No action
						break;

				endswitch;
			}

			// Return to Thank you page if this is a buyer-return-to-shop callback
			wp_redirect( $redirect_url );
			exit;
		} // End Flexwin callback
	}


	/**
	 * Gets the order ID. Checks to see if Sequential Order Numbers or Sequential Order
	 * Numbers Pro is enabled and, if yes, use order number set by them.
	 *
	 * @param $order_number
	 *
	 * @return mixed|void
	 */
	function get_order_id( $order_number ) {

		$order_id = false;

		// Check if we can find the order id by query orders by _dibs_sent_order_id (saved in the order during checkout process).
		$query_args = array(
			'fields'      => 'ids',
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_dibs_sent_order_id',
			'meta_value'  => $order_number,
		);
		$orders     = get_posts( $query_args );
		if ( ! empty( $orders ) ) {
			$order_id = $orders[0];
		}

		if ( empty( $order_id ) ) {

			// Get Order ID by order_number() if the Sequential Order Number plugin is installed
			if ( class_exists( 'WC_Seq_Order_Number' ) ) {
				global $wc_seq_order_number;
				$order_id = $wc_seq_order_number->find_order_by_order_number( $order_number );

				// Get Order ID by order_number() if the Sequential Order Number Pro plugin is installed
			} elseif ( class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
				$order_id = wc_seq_order_number_pro()->find_order_by_order_number( $order_number );
			}
		}

		// Check if order_id has been used as order number
		if ( empty( $order_id ) ) {
			$order = wc_get_order( $order_number );
			if ( is_object( $order ) ) {
				// Order ID used as order number, return it.
				$order_id = $order_number;

			}
		}

		// No order_id found, use the returned $order_number instead
		if ( empty( $order_id ) ) {
			$order_id = $order_number;
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs', 'ERROR. No order id found in get_order_id() function for order number ' . $order_number . ' returned from DIBS.' );
			}
		}

		return apply_filters( 'wc_dibs_get_order_id', $order_id );
	}

	/**
	 * Cancels an order.
	 *
	 * @param $posted
	 */
	function cancel_order( $posted ) {

		// Flexwin callback
		if ( isset( $posted['orderid'] ) ) {

			$order_id = $this->get_order_id( $posted['orderid'] );

			$order = wc_get_order( $order_id );

			if ( $order->get_id() == $order_id && $order->get_status() == 'pending' ) {

				// Cancel the order + restore stock.
				$order->update_status( 'cancelled', __( 'Order cancelled by customer.', 'dibs-for-woocommerce' ) );

				// Message
				wc_add_notice( __( 'Your order was cancelled.', 'dibs-for-woocommerce' ), 'error' );
			} elseif ( $order->get_status() != 'pending' ) {

				wc_add_notice( __( 'Your order is no longer pending and could not be cancelled. Please contact us if you need assistance.', 'dibs-for-woocommerce' ), 'error' );
			} else {

				wc_add_notice( __( 'Invalid order.', 'dibs-for-woocommerce' ), 'error' );
			}

			wp_safe_redirect( wc_get_cart_url() );
			exit;
		} // End Flexwin
	}

	/**
	 * Process a scheduled subscription payment.
	 *
	 * @param $amount_to_charge
	 * @param $order
	 */
	function scheduled_subscription_payment( $amount_to_charge, $order ) {
		// This function may get triggered multiple times because the class is instantiated one time per payment method (card, invoice & mobile pay). Only run it for card payments.
		// TODO: Restructure the classes so this doesn't happen.
		if ( 'dibs' != $this->id ) {
			return;
		}

		$result = $this->process_subscription_payment( $order, $amount_to_charge );

		$subscriptions = wcs_get_subscriptions_for_renewal_order( $order->get_id() );

		if ( false == $result ) {

			// Debug
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs', 'Scheduled subscription payment failed for order ' . $order->get_id() );
			}

			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_failed();
			}
		} else {

			// Debug
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs', 'Scheduled subscription payment succeeded for order ' . $order->get_id() );
			}

			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_complete( $result );
			}
		}
	}

	/**
	 * Process a subscription payment.
	 *
	 * @param string $order
	 * @param int    $amount
	 *
	 * @return bool
	 */
	function process_subscription_payment( $order = '', $amount = 0 ) {
		require_once WC_DIBS_PLUGIN_DIR . 'includes/dibs-api-functions.php';

		$dibs_ticket = '';

		$dibs_ticket = get_post_meta( $order->get_id(), '_dibs_ticket', true );
		// If the recurring token isn't stored in the subscription, grab it from parent order.
		if ( empty( $dibs_ticket ) ) {
			$dibs_ticket = get_post_meta( WC_Subscriptions_Renewal_Order::get_parent_order_id( $order->get_id() ), '_dibs_ticket', true );

			if ( ! empty( $dibs_ticket ) ) {
				update_post_meta( $order->get_id(), '_dibs_ticket', $dibs_ticket );
			}
		}

		$amount_smallest_unit = number_format( $amount, 2, '.', '' ) * 100;
		$postvars             = 'merchant=' . $this->merchant_id . '&orderid=' . $order->get_order_number() . '&ticket=' . $dibs_ticket . '&currency=' . $order->get_currency() . '&amount=' . $amount_smallest_unit;
		$md5key               = MD5( $this->key_2 . MD5( $this->key_1 . $postvars ) );

		$params = array(
			'amount'    => $amount_smallest_unit,
			'currency'  => $order->get_currency(),
			'md5key'    => $md5key,
			'merchant'  => $this->merchant_id,
			'orderid'   => $order->get_order_number(),
			'test'      => $this->testmode,
			'textreply' => 'yes',
			'ticket'    => $dibs_ticket,
		);

		if ( $this->capturenow == 'yes' ) {
			$params['capturenow'] = 'yes';
		}

		// Debug
		if ( $this->debug == 'yes' ) {
			$this->log->add( 'dibs', 'Process subscription payment params: ' . var_export( $params, true ) );
		}

		$response = postToDIBS( 'AuthorizeTicket', $params, false );

		if ( isset( $response['status'] ) && ( $response['status'] == 'ACCEPT' || $response['status'] == 'ACCEPTED' ) ) {
			// Payment ok
			$order->add_order_note( sprintf( __( 'DIBS subscription payment completed. Transaction Id: %s.', 'dibs-for-woocommerce' ), $response['transact'] ) );
			update_post_meta( $order->get_id(), '_dibs_transaction_no', $response['transact'] );
			update_post_meta( $order->get_id(), '_transaction_id', $response['transact'] );

			if ( $this->capturenow == 'yes' ) {
				add_post_meta( $order->get_id(), '_dibs_order_captured', 'yes' );
				$order->add_order_note( __( 'DIBS transaction captured.', 'dibs-for-woocommerce' ) );
			}

			return $response['transact'];
		} elseif ( ! empty( $response['wp_remote_note'] ) ) {
			// WP remote post problem
			$order->add_order_note( sprintf( __( 'DIBS subscription payment failed. WP Remote post problem: %s.', 'dibs-for-woocommerce' ), $response['wp_remote_note'] ) );

			return false;
		} else {
			// Payment problem
			$order->add_order_note( sprintf( __( 'DIBS subscription payment failed. Decline reason: %s.', 'dibs-for-woocommerce' ), $response['reason'] ) );

			// Debug
			if ( $this->debug == 'yes' ) {
				$this->log->add( 'dibs', 'DIBS subscription payment failed, received response: ' . var_export( $response, true ) );
			}

			return false;
		}
	}

	/**
	 * Update the customer token IDs for a subscription after a customer used DIBS to
	 * successfully complete the payment for an automatic renewal payment which had previously failed.
	 *
	 * @param $original_order
	 * @param $renewal_order
	 */
	function update_failing_payment_method( $original_order, $renewal_order ) {
		update_post_meta( $original_order->id, '_dibs_ticket', get_post_meta( $renewal_order->id, '_dibs_ticket', true ) );
	} // end function

	/**
	 * Process a refund if supported.
	 *
	 * @param int    $order_id
	 * @param null   $amount
	 * @param string $reason
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			$this->log->add( 'Refund Failed: No transaction ID.' );
			$order->add_order_note( __( 'Refund Failed: No transaction ID.', 'dibs-for-woocommerce' ) );

			return false;
		}

		if ( ! $this->api_username || ! $this->api_password ) {
			$order->add_order_note( __( 'Refund Failed: Missing API Credentials.', 'dibs-for-woocommerce' ) );

			return false;
		}

		require_once WC_DIBS_PLUGIN_DIR . 'includes/dibs-api-functions.php';

		$amount_smallest_unit = $amount * 100;
		$transact             = $order->get_transaction_id();
		$merchant_id          = $this->merchant_id;
		$postvars             = 'merchant=' . $merchant_id . '&orderid=' . $order->get_order_number() . '&transact=' . $transact . '&amount=' . $amount_smallest_unit;
		$md5key               = MD5( $this->key_2 . MD5( $this->key_1 . $postvars ) );

		// Refund parameters
		$params = array(
			'amount'    => $amount_smallest_unit,
			'currency'  => $order->get_currency(),
			'md5key'    => $md5key,
			'merchant'  => $merchant_id,
			'orderid'   => $order->get_order_number(),
			'textreply' => 'yes',
			'transact'  => $transact,
		);

		$response = postToDIBS( 'RefundTransaction', $params, false, $this->api_username, $this->api_password );

		// WP remote post problem
		if ( is_wp_error( $response ) ) {
			$refund_note = sprintf( __( 'DIBS refund failed. WP Remote post problem: %s.', 'dibs-for-woocommerce' ), $response['wp_remote_note'] );

			$order->add_order_note( $refund_note );

			return false;
		}

		if ( isset( $response['status'] ) && ( $response['status'] == 'ACCEPT' || $response['status'] == 'ACCEPTED' ) ) {
			// Refund OK
			$refund_note = sprintf( __( '%s refunded successfully via DIBS.', 'dibs-for-woocommerce' ), wc_price( $amount ) );
			if ( '' != $reason ) {
				$refund_note .= sprintf( __( ' Reason: %s.', 'dibs-for-woocommerce' ), $reason );
			}

			$order->add_order_note( $refund_note );

			// Maybe change status to Refunded
			if ( $order->get_total() == $amount ) {
				$order->update_status( 'refunded' );
			}

			return true;
		} else {
			// Refund problem
			$order->add_order_note( sprintf( __( 'DIBS refund failed. Decline reason: %s.', 'dibs-for-woocommerce' ), $response['message'] ) );

			return false;
		}
	}

	/**
	 * Checks if order can be refunded via DIBS.
	 *
	 * @param $order
	 *
	 * @return bool
	 */
	public function can_refund_order( $order ) {
		return $order && $order->get_transaction_id();
	}

	/**
	 * Returns merchant ID.
	 *
	 * @return string
	 */
	function get_merchant_id() {
		return $this->merchant_id;
	}

	/**
	 * Checks if orders should be captured as soon as checkout is completed.
	 *
	 * @return string
	 */
	function get_capturenow() {
		return $this->capturenow;
	}

	/**
	 * Returns MD5 key 1.
	 *
	 * @return string
	 */
	function get_key_1() {
		return $this->key_1;
	}

	/**
	 * Returns MD5 key 2.
	 *
	 * @return string
	 */
	function get_key_2() {
		return $this->key_2;
	}

} // End class
