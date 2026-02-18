<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$user = current_user();

$receiver_id = (int)($_POST['receiver_id'] ?? 0);
$subject = trim($_POST['subject'] ?? '');
$body = trim($_POST['body'] ?? '');

if ($receiver_id <= 0 || $body === '') {
  header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/inplace/dashboard.php'));
  exit;
}

$stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?,?,?,?)");
$stmt->execute([$user['id'], $receiver_id, $subject, $body]);

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/inplace/dashboard.php'));
exit;