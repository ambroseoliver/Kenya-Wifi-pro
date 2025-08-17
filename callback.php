<?php
// callback.php

// 1. Capture the raw response from Safaricom
$data = file_get_contents('php://input');
$logFile = __DIR__ . "/mpesa_callback_log.json";

// Save raw data for debugging (append with timestamp + newline for readability)
file_put_contents($logFile, date("Y-m-d H:i:s") . " " . $data . PHP_EOL, FILE_APPEND);

// Decode the JSON
$callbackData = json_decode($data, true);

// 2. Database connection
$servername = "localhost";
$username   = "root"; // change if you set a different MySQL user
$password   = "";     // change if you set a password
$dbname     = "kenyawifi_pro";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  error_log("DB Connection failed: " . $conn->connect_error);
  http_response_code(500);
  exit("Database Connection failed");
}

// 3. Extract payment info if present
if (isset($callbackData["Body"]["stkCallback"])) {
  $stkCallback = $callbackData["Body"]["stkCallback"];

  $resultCode        = $stkCallback["ResultCode"];
  $resultDesc        = $stkCallback["ResultDesc"];
  $merchantRequestID = $stkCallback["MerchantRequestID"];
  $checkoutRequestID = $stkCallback["CheckoutRequestID"];

  if ($resultCode == 0 && isset($stkCallback["CallbackMetadata"]["Item"])) {
    // Successful payment
    $callbackItems = $stkCallback["CallbackMetadata"]["Item"];

    // Initialize values
    $amount = $mpesaReceiptNumber = $transactionDate = $phoneNumber = null;

    // Loop through items safely (avoids relying on index positions)
    foreach ($callbackItems as $item) {
      switch ($item["Name"]) {
        case "Amount":
          $amount = $item["Value"];
          break;
        case "MpesaReceiptNumber":
          $mpesaReceiptNumber = $item["Value"];
          break;
        case "TransactionDate":
          $transactionDate = $item["Value"];
          break;
        case "PhoneNumber":
          $phoneNumber = $item["Value"];
          break;
      }
    }

    // Format transaction date
    if ($transactionDate) {
      $formattedDate = DateTime::createFromFormat("YmdHis", $transactionDate)->format("Y-m-d H:i:s");
    } else {
      $formattedDate = date("Y-m-d H:i:s");
    }

    // Insert into DB
    $stmt = $conn->prepare("
            INSERT INTO payments (phone_number, amount, mpesa_receipt_number, transaction_date, status) 
            VALUES (?, ?, ?, ?, 'Success')
        ");
    $stmt->bind_param("sdss", $phoneNumber, $amount, $mpesaReceiptNumber, $formattedDate);

    if (!$stmt->execute()) {
      error_log("DB insert failed: " . $stmt->error);
    }
    $stmt->close();
  } else {
    // Payment failed/cancelled
    $phoneNumber = "Unknown";
    if (isset($stkCallback["CallbackMetadata"]["Item"])) {
      foreach ($stkCallback["CallbackMetadata"]["Item"] as $item) {
        if ($item["Name"] === "PhoneNumber") {
          $phoneNumber = $item["Value"];
        }
      }
    }

    $stmt = $conn->prepare("INSERT INTO payments (phone_number, amount, status) VALUES (?, 0, 'Failed')");
    $stmt->bind_param("s", $phoneNumber);
    $stmt->execute();
    $stmt->close();
  }
}

$conn->close();

// Always return 200 OK so Safaricom knows callback was received
http_response_code(200);
echo json_encode(["ResultCode" => 0, "ResultDesc" => "Callback received successfully"]);
