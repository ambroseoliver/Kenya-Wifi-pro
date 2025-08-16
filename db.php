<?php
// db.php
require_once __DIR__ . '/config.php';

try {
  $pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch (PDOException $e) {
  error_log("DB connection failed: " . $e->getMessage());
  http_response_code(500);
  die(json_encode(["success" => false, "message" => "DB connection error"]));
}
