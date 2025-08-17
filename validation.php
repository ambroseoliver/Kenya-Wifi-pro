<?php
// validation.php
require_once __DIR__ . '/db.php';

// Get input
$data = file_get_contents("php://input");
$transaction = json_decode($data, true);

// Log request (optional)
file_put_contents("validation_log.txt", $data . PHP_EOL, FILE_APPEND);

// Always accept (for now)
header('Content-Type: application/json');
echo json_encode([
  "ResultCode" => 0,
  "ResultDesc" => "Accepted"
]);

