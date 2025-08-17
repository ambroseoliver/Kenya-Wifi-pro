<?php
// confirmation.php
require_once __DIR__ . '/db.php'; // DB connection

// Get raw JSON input from Safaricom
$data = file_get_contents("php://input");
$transaction = json_decode($data, true);

// Log raw data (optional for debugging)
file_put_contents("confirmation_log.txt", $data . PHP_EOL, FILE_APPEND);

if ($transaction) {
  $stmt = $pdo->prepare("INSERT INTO mpesa_transactions 
        (transaction_type, trans_id, trans_time, trans_amount, business_shortcode, bill_ref_number, 
         invoice_number, org_account_balance, third_party_trans_id, msisdn, first_name, middle_name, last_name) 
        VALUES (:type, :transid, :transtime, :amount, :shortcode, :billref, 
         :invoice, :balance, :thirdpartyid, :msisdn, :fname, :mname, :lname)");

  $stmt->execute([
    ':type'       => $transaction['TransactionType'] ?? null,
    ':transid'    => $transaction['TransID'] ?? null,
    ':transtime'  => $transaction['TransTime'] ?? null,
    ':amount'     => $transaction['TransAmount'] ?? null,
    ':shortcode'  => $transaction['BusinessShortCode'] ?? null,
    ':billref'    => $transaction['BillRefNumber'] ?? null,
    ':invoice'    => $transaction['InvoiceNumber'] ?? null,
    ':balance'    => $transaction['OrgAccountBalance'] ?? null,
    ':thirdpartyid' => $transaction['ThirdPartyTransID'] ?? null,
    ':msisdn'     => $transaction['MSISDN'] ?? null,
    ':fname'      => $transaction['FirstName'] ?? null,
    ':mname'      => $transaction['MiddleName'] ?? null,
    ':lname'      => $transaction['LastName'] ?? null,
  ]);
}

// Response back to M-Pesa
header('Content-Type: application/json');
echo json_encode([
  "ResultCode" => 0,
  "ResultDesc" => "Success"
]);
