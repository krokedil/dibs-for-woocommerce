<?php

/**
* postToDIBS
* Sends a set of parameters to a DIBS API function
* @param string $paymentFunction The name of the target payment function, e.g. AuthorizeCard
* @param array $params A set of parameters to be posted in key => value format
* @return array
*/
function postToDIBS( $paymentFunction, $params ) {
	// Create JSON string from array of key => values
	$json_data = json_encode( $params );

	// Set correct POST URL corresponding to the payment function requested
	switch ( $paymentFunction ) {
		case 'AuthorizeCard':
			$postUrl = 'https://api.dibspayment.com/merchant/v1/JSON/Transaction/AuthorizeCard';
			break;
		case 'AuthorizeTicket':
			$postUrl = 'https://api.dibspayment.com/merchant/v1/JSON/Transaction/AuthorizeTicket';
			break;
		case 'CancelTransaction':
			$postUrl = 'https://api.dibspayment.com/merchant/v1/JSON/Transaction/CancelTransaction';
			break;
		case 'CaptureTransaction':
			$postUrl = 'https://api.dibspayment.com/merchant/v1/JSON/Transaction/CaptureTransaction';
			break;
		case 'CreateTicket':
			$postUrl = 'https://api.dibspayment.com/merchant/v1/JSON/Transaction/CreateTicket';
			break;
		case 'RefundTransaction':
			$postUrl = 'https://api.dibspayment.com/merchant/v1/JSON/Transaction/RefundTransaction';
			break;
		case 'Ping':
			$postUrl = 'https://api.dibspayment.com/merchant/v1/JSON/Transaction/Ping';
			break;
		default:
			echo( 'Wrong input paymentFunctions to postToDIBS' );
			$postUrl = null;
	}

	$response = wp_remote_post( $postUrl, array(
		'method'      => 'POST',
		'timeout'     => 45,
		'redirection' => 5,
		'httpversion' => '1.0',
		'blocking'    => true,
		'headers'     => array(),
		'body'        => array( 'request' => $json_data ),
		'cookies'     => array()
	) );
	
	$post_back = array();
	
	if ( is_wp_error( $response ) ) {
		$post_back['wp_remote_note'] = sprintf( __( 'Error: %s', 'woocommerce' ), $response->get_error_message() );
	} else {
		$post_back = json_decode($response['body'], true);
	}
	
	return $post_back;
}
	
	
/**
* AuthorizeTicket
* Makes a new authorization on an existing ticket using the AuthorizeTicket JSON service
* @param int @amount The amount of the purchase in smallest unit
* @param string @currency The currency either in numeric or string format (e.g. 208/DKK)
* @param int @merchantId DIBS Merchant ID / customer number
* @param string @orderId The shops order ID for the purchase
* @param string @ticketId The ticket number on which the authorization should be done
* @param string @K The secret HMAC key from DIBS Admin
*/
function AuthorizeTicket( $amount, $currency, $merchantId, $orderId, $ticketId, $K ) {
	// Create message array consisting of all input parameters
	$message = array(
		'merchantId' => $merchantId,
		'amount'     => $amount,
		'currency'   => $currency,
		'orderId'    => $orderId,
		'ticketId'   => $ticketId,
	);

	// Calculate MAC value for request
	$mac = calculateMac($message, $K);
	$message['MAC'] = $mac;

	// Post to the DIBS system
	$res = postToDIBS( 'AuthorizeTicket', $message );

	if ( $res['status'] == 'ACCEPT' ) {
		// Payment accepted. Check $res["transactionId"] for transaction ID.
		// Insert own code to update shop system
	} else if ( $res['status'] == 'DECLINE' ) {
		// Check $res["declineReason"] for more information.
		// Insert own code to update shop system
	} else {
		// An error happened. Check $res["declineReason"] for more information.
		// Insert own code to update shop system
	}
}


/**
* RefundTransaction
* Refunds a previously captured transaction using the RefundTransaction JSON service
* @param int @amount The amount of the capture in smallest unit
* @param int @merchantId DIBS Merchant ID / customer number
* @param string @transactionId The ticket number on which the authorization should be done
* @param string @K The secret HMAC key from DIBS Admin
*/
function RefundTransaction( $amount, $merchantId, $transactionId, $K ) {
	// Create message array consisting of all input parameters
	$message = array(
		'amount'        => $amount,
		'merchantId'    => $merchantId,
		'transactionId' => $transactionId,
	);

	// Calculate MAC value for request
	$mac = calculateMac($message, $K);
	$message['MAC'] = $mac;

	// Post to the DIBS system
	$res = postToDIBS( 'RefundTransaction', $message );

	if ( $res['status'] == 'ACCEPT' ) {
		// Refund accepted
		// Insert own code to update shop system
	} else if ( $res['status'] == 'DECLINE' ) {
		// Check $res["declineReason"] for more information.
		// Insert own code to update shop system
	} else {
		// An error happened. Check $res["declineReason"] for more information.
		// Insert own code to update shop system
	}
}