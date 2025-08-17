<?php
// register_url.php

// Your credentials
$consumerKey = "gwKKdswVypVck5ikj0UYQCHVczXPfS22c5gOHWJUUrTlf6Gn"; // from Daraja
$consumerSecret = "kHrEP4il1pXobIzTAVPc0StApKPOhmaGa986qW4sRwJoxdAGVYFHJ6o4PzaydYko
"; // from Daraja
$shortCode = "5824516"; // Paybill/Till Number
$confirmationUrl = "https://yourdomain.com/php/confirmation.php";
$validationUrl = "https://yourdomain.com/php/validation.php";
$responseType = "Completed"; // Completed OR Cancelled

// Get access token
$credentials = base64_encode($consumerKey . ":" . $consumerSecret);
$url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
  echo "cURL Error #: " . $err;
} else {
  $result = json_decode($response);
  $access_token = $result->access_token;

  // Register URLs
  $url = 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';
  $curl_post_data = array(
    'ShortCode' => $shortCode,
    'ResponseType' => $responseType,
    'ConfirmationURL' => $confirmationUrl,
    'ValidationURL' => $validationUrl
  );

  $data_string = json_encode($curl_post_data);

  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array(
    'Content-Type:application/json',
    'Authorization:Bearer ' . $access_token
  ));

  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);

  $response = curl_exec($curl);
  $err = curl_error($curl);

  curl_close($curl);

  if ($err) {
    echo "cURL Error #: " . $err;
  } else {
    echo $response;
  }
}
