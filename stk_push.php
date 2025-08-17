<?php
// stk_push.php

require_once __DIR__ . '/db.php'; // include DB connection

// Safaricom Daraja credentials
$consumerKey    = "gwKKdswVypVck5ikj0UYQCHVczXPfS22c5gOHWJUUrTlf6Gn";      // replace with your actual Consumer Key
$consumerSecret = "kHrEP4il1pXobIzTAVPc0StApKPOhmaGa986qW4sRwJoxdAGVYFHJ6o4PzaydYko";   // replace with your actual Consumer Secret
$businessShortCode = "5824516";              // replace with your Paybill or Till number
$passkey        = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";           // replace with your Daraja passkey
$callbackUrl    = "https://yourdomain.com/php/callback.php"; // update to your live callback URL

// Step 1: Get JSON input from frontend
$data = json_decode(file_get_contents("php://input"), true);
$phone = isset($data['phone']) ? $data['phone'] : null;
$amount = isset($data['amount']) ? $data['amount'] : null;

// Basic validation
if (!$phone || !$amount) {
  echo json_encode(["success" => false, "message" => "Phone number and amount are required"]);
  exit;
}

// Format phone number to 2547XXXXXXXX
$phone = preg_replace('/^0/', '254', $phone);

// Step 2: Generate access token
$credentials = base64_encode($consumerKey . ":" . $consumerSecret);
$ch = curl_init("https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic " . $credentials]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$accessToken = $result['access_token'] ?? null;

if (!$accessToken) {
  echo json_encode(["success" => false, "message" => "Failed to generate access token"]);
  exit;
}

// Step 3: Prepare STK push request
$timestamp = date("YmdHis");
$password  = base64_encode($businessShortCode . $passkey . $timestamp);

$stkRequest = [
  "BusinessShortCode" => $businessShortCode,
  "Password" => $password,
  "Timestamp" => $timestamp,
  "TransactionType" => "CustomerPayBillOnline",
  "Amount" => $amount,
  "PartyA" => $phone,
  "PartyB" => $businessShortCode,
  "PhoneNumber" => $phone,
  "CallBackURL" => $callbackUrl,
  "AccountReference" => "Donation",
  "TransactionDesc" => "Donation payment"
];

// Step 4: Send STK push request
$ch = curl_init("https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Content-Type: application/json",
  "Authorization: Bearer " . $accessToken
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stkRequest));
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

// Step 5: Insert into DB + Respond to frontend
if (isset($result['ResponseCode']) && $result['ResponseCode'] == "0") {
  $merchantRequestId = $result['MerchantRequestID'] ?? '';
  $checkoutRequestId = $result['CheckoutRequestID'] ?? '';

  // Save pending transaction
  $stmt = $pdo->prepare("INSERT INTO transactions 
        (merchant_request_id, checkout_request_id, status, amount, phone, result_code, result_desc) 
        VALUES (:mrid, :crid, 'pending', :amount, :phone, :rcode, :rdesc)
        ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP");

  $stmt->execute([
    ':mrid'  => $merchantRequestId,
    ':crid'  => $checkoutRequestId,
    ':amount' => (float)$amount,
    ':phone' => $phone,
    ':rcode' => (int)($result['ResponseCode'] ?? 0),
    ':rdesc' => $result['ResponseDescription'] ?? null,
  ]);

  echo json_encode([
    "success" => true,
    "message" => "STK Push sent successfully. Enter your M-Pesa PIN on your phone."
  ]);
} else {
  echo json_encode([
    "success" => false,
    "message" => $result['errorMessage'] ?? "Failed to initiate STK Push"
  ]);
}
