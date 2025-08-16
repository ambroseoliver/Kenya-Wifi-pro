<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Enable error logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'simba_wifi');
define('DB_USER', 'secure_user');
define('DB_PASS', 'strong_password');

// M-Pesa credentials (replace with your actual credentials)
define('MPESA_CONSUMER_KEY', 'your_consumer_key');
define('MPESA_CONSUMER_SECRET', 'your_consumer_secret');
define('MPESA_SHORTCODE', '174379'); // Sandbox: 174379, Production: Your Paybill
define('MPESA_PASSKEY', 'your_passkey');
define('MPESA_CALLBACK_URL', 'https://yourdomain.com/mpesa_callback.php');

// Create database connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]));
}

// Get and validate JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'Invalid JSON data'
    ]));
}

// Validate required fields
$requiredFields = ['phoneNumber', 'amount', 'package', 'accountReference'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        die(json_encode([
            'success' => false,
            'message' => "Missing required field: $field"
        ]));
    }
}

// Sanitize inputs
$phoneNumber = preg_replace('/[^0-9]/', '', $data['phoneNumber']);
$amount = (float)$data['amount'];
$package = htmlspecialchars($data['package'], ENT_QUOTES);
$accountReference = htmlspecialchars($data['accountReference'], ENT_QUOTES);

// Validate phone number format
if (!preg_match('/^254[17]\d{8}$/', $phoneNumber)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'Invalid phone number format'
    ]));
}

// Validate amount
if ($amount <= 0) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'Invalid amount'
    ]));
}

// Generate access token
$accessToken = generateAccessToken();
if (!$accessToken) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Failed to authenticate with M-Pesa'
    ]));
}

// Initiate STK push
$response = initiateSTKPush($accessToken, $phoneNumber, $amount, $accountReference);

if ($response && isset($response['ResponseCode']) && $response['ResponseCode'] == '0') {
    // Save transaction to database
    try {
        $stmt = $pdo->prepare("INSERT INTO transactions 
            (phone_number, package, amount, transaction_id, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([
            $phoneNumber,
            $package,
            $amount,
            $response['CheckoutRequestID']
        ]);
        
        echo json_encode([
            'success' => true,
            'transactionId' => $response['CheckoutRequestID'],
            'message' => 'Payment request initiated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save transaction'
        ]);
    }
} else {
    $error = $response['errorMessage'] ?? ($response['ResponseDescription'] ?? 'Payment failed');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $error
    ]);
}

function generateAccessToken() {
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $credentials],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('CURL error generating token: ' . curl_error($ch));
        return false;
    }
    
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function initiateSTKPush($accessToken, $phoneNumber, $amount, $accountReference) {
    $url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
    
    $payload = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phoneNumber,
        'PartyB' => MPESA_SHORTCODE,
        'PhoneNumber' => $phoneNumber,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $accountReference,
        'TransactionDesc' => 'WIFI Payment'
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('CURL error in STK push: ' . curl_error($ch));
        return ['errorMessage' => 'Network error connecting to M-Pesa'];
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode != 200) {
        error_log('M-Pesa API returned HTTP ' . $httpCode);
        return ['errorMessage' => 'M-Pesa service unavailable'];
    }
    
    return json_decode($response, true);
}