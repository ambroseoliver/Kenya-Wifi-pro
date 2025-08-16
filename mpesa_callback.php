<?php
header('Content-Type: application/json');
require_once 'config.php';

// Database connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die(json_encode(['success' => false]));
}

// Get callback data
$callbackData = json_decode(file_get_contents('php://input'), true);

if (isset($callbackData['Body']['stkCallback'])) {
    $callback = $callbackData['Body']['stkCallback'];
    $checkoutRequestID = $callback['CheckoutRequestID'];
    $resultCode = $callback['ResultCode'];
    
    if ($resultCode == 0) {
        // Successful payment
        $metadata = $callback['CallbackMetadata']['Item'];
        $amount = $metadata[0]['Value'];
        $mpesaReceipt = $metadata[1]['Value'];
        $phoneNumber = $metadata[4]['Value'];
        
        // Update transaction
        $stmt = $pdo->prepare("UPDATE transactions SET 
            status = 'completed',
            mpesa_receipt = ?,
            transaction_date = NOW(),
            updated_at = NOW()
            WHERE transaction_id = ?");
        $stmt->execute([$mpesaReceipt, $checkoutRequestID]);
    } else {
        // Failed payment
        $stmt = $pdo->prepare("UPDATE transactions SET 
            status = 'failed',
            updated_at = NOW()
            WHERE transaction_id = ?");
        $stmt->execute([$checkoutRequestID]);
    }
}

echo json_encode(['success' => true]);