<?php

	/**
	* postToDIBS
	* Sends a set of parameters to a DIBS API function
	* @param string $paymentFunction The name of the target payment function, e.g. AuthorizeCard
	* @param array $params A set of parameters to be posted in key => value format
	* @return array
	*/
	function postToDIBS($paymentFunction, $params) {
	  //Create JSON string from array of key => values
	  $json_data = json_encode($params);
	   
	  //Set correct POST URL corresponding to the payment function requested
	  switch ($paymentFunction) {
	    case "AuthorizeCard":
	      $postUrl = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/AuthorizeCard";
	      break;
	    case "AuthorizeTicket":
	      $postUrl = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/AuthorizeTicket";
	      break;
	    case "CancelTransaction":
	      $postUrl = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/CancelTransaction";
	      break;
	    case "CaptureTransaction":
	      $postUrl = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/CaptureTransaction";
	      break;
	    case "CreateTicket":
	      $postUrl = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/CreateTicket";
	      break;
	    case "RefundTransaction":
	      $postUrl = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/RefundTransaction";
	      break;
	    case "Ping":
	      $postUrl = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/Ping";
	      break;
	    default:
	      echo("Wrong input paymentFunctions to postToDIBS");
	      $postUrl = null;
	  }
	   
	  //Use Curl to communicate with the server.
	  $ch = curl_init();
	  curl_setopt($ch, CURLOPT_URL,$postUrl);
	  curl_setopt($ch, CURLOPT_POST, 1);
	  curl_setopt($ch, CURLOPT_SSLVERSION, 3);
	  curl_setopt($ch, CURLOPT_POSTFIELDS, "request=" . $json_data);
	  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
	  $json_output = curl_exec ($ch);
	   
	  //Check for errors in the Curl operation
	  if (curl_errno($ch) != 0) {
	    error_log("Curl failed:");
	    error_log(curl_getinfo($ch));
	    error_log(curl_errno($ch));
	    error_log(curl_error($ch));
	  }
	  curl_close ($ch);
	   
	  //Convert JSON server output to array of key => values
	  $res = json_decode($json_output, true);
	   
	  return $res;
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
	function AuthorizeTicket($amount, $currency, $merchantId, $orderId, $ticketId, $K) {
	  //Create message array consisting of all input parameters
	  $message = array(
	    "merchantId" => $merchantId,
	    "amount" => $amount,
	    "currency" => $currency,
	    "orderId" => $orderId,
	    "ticketId" => $ticketId,
	  );
	   
	  //Calculate MAC value for request
	  $mac = calculateMac($message, $K);
	  $message["MAC"] = $mac;
	   
	  //Post to the DIBS system
	  $res = postToDIBS("AuthorizeTicket", $message);
	   
	  if ($res["status"] == "ACCEPT") {
	    //Payment accepted. Check $res["transactionId"] for transaction ID.
	    //Insert own code to update shop system
	  } else if ($res["status"] == "DECLINE") {
	    //Check $res["declineReason"] for more information.
	    //Insert own code to update shop system
	  } else {
	    //An error happened. Check $res["declineReason"] for more information.
	    //Insert own code to update shop system
	  }
	}