<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth(['student']);

$user = current_user();
$placement_id = (int)($_POST['placement_id'] ?? 0);
$doc_type = $_POST['doc_type'] ?? 'interim_report';

if ($placement_id <= 0 || empty($_FILES['report_file']['name'])) {
  header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/inplace/student/reports.php'));
  exit;
}

$allowed = ['application/pdf'];
if ($_FILES['report_file']['error'] !== UPLOAD_ERR_OK) {
  header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/inplace/student/reports.php'));
  exit;
}

$mime = mime_content_type($_FILES['report_file']['tmp_name']);
if (!in_array($mime, $allowed, true)) {
  header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/inplace/student/reports.php'));
  exit;
}

$uploadDir = __DIR__ . '/../assets/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

$safeName = time() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['report_file']['name']);
$destRel = "assets/uploads/" . $safeName;
$destAbs = __DIR__ . '/../' . $destRel;

move_uploaded_file($_FILES['report_file']['tmp_name'], $destAbs);

$stmt = $pdo->prepare("
  INSERT INTO documents (placement_id, uploaded_by, doc_type, file_name, file_path, file_size, status)
  VALUES (?,?,?,?,?,?, 'pending')
");
$stmt->execute([
  $placement_id,
  $user['id'],
  ($doc_type === 'final_report' ? 'final_report' : 'interim_report'),
  $_FILES['report_file']['name'],
  $destRel,
  (string)$_FILES['report_file']['size']
]);

header("Location: /inplace/student/reports.php");
exit;