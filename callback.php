<?php
// callback.php

// 1. Capture the raw response from Safaricom
$data = file_get_contents('php://input');
$logFile = "mpesa_callback_log.json";

// Save raw data for debugging
file_put_contents($logFile, $data, FILE_APPEND);

// Decode the JSON
$callbackData = json_decode($data, true);

// 2. Database connection
$servername = "localhost";
$username = "root"; // change if you set a different MySQL user
$password = "";     // change if you set a password
$dbname = "kenyawifi_pro";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Database Connection failed: " . $conn->connect_error);
}

// 3. Extract payment info if present
if (isset($callbackData["Body"]["stkCallback"])) {
  $stkCallback = $callbackData["Body"]["stkCallback"];

  $resultCode = $stkCallback["ResultCode"];
  $resultDesc = $stkCallback["ResultDesc"];
  $merchantRequestID = $stkCallback["MerchantRequestID"];
  $checkoutRequestID = $stkCallback["CheckoutRequestID"];

  if ($resultCode == 0) {
    // Successful payment
    $callbackItems = $stkCallback["CallbackMetadata"]["Item"];

    $amount = $callbackItems[0]["Value"]; // Amount
    $mpesaReceiptNumber = $callbackItems[1]["Value"]; // Mpesa receipt
    $transactionDate = $callbackItems[3]["Value"]; // Transaction date (YYYYMMDDHHMMSS)
    $phoneNumber = $callbackItems[4]["Value"]; // Phone number

    // Format transaction date
    $formattedDate = DateTime::createFromFormat("YmdHis", $transactionDate)->format("Y-m-d H:i:s");

    // Insert into DB
    $stmt = $conn->prepare("INSERT INTO payments (phone_number, amount, mpesa_receipt_number, transaction_date, status) VALUES (?, ?, ?, ?, 'Success')");
    $stmt->bind_param("sdss", $phoneNumber, $amount, $mpesaReceiptNumber, $formattedDate);

    if ($stmt->execute()) {
      http_response_code(200);
    } else {
      error_log("DB insert failed: " . $stmt->error);
    }
    $stmt->close();
  } else {
    // Payment failed/cancelled
    $stmt = $conn->prepare("INSERT INTO payments (phone_number, amount, status) VALUES (?, 0, 'Failed')");
    $phoneNumber = isset($callbackItems[4]["Value"]) ? $callbackItems[4]["Value"] : "Unknown";
    $stmt->bind_param("s", $phoneNumber);
    $stmt->execute();
    $stmt->close();
  }
}

$conn->close();
