<?php
require_once __DIR__ . '/db.php';

$stmt = $pdo->query("SELECT NOW() AS now_time");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: text/plain');
echo "DB OK. Server time: " . $row['now_time'] . PHP_EOL;
