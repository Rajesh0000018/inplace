<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth(); // allow student/tutor/admin if your auth supports it
$userId = authId();

$input = json_decode(file_get_contents('php://input'), true);
$toId  = (int)($input['to_id'] ?? 0);
$body  = trim((string)($input['body'] ?? ''));

if ($toId <= 0 || $body === '') {
    echo json_encode(['ok' => false, 'error' => 'Invalid message']);
    exit;
}

// IMPORTANT: your DB columns
$timeCol = 'sent_at';
$textCol = 'body';

$stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, `$textCol`, `$timeCol`, is_read) VALUES (?, ?, ?, NOW(), 0)");
$stmt->execute([$userId, $toId, $body]);

$id = (int)$pdo->lastInsertId();

echo json_encode([
    'ok' => true,
    'message' => [
        'id'   => $id,
        'body' => $body,
        'time' => date('g:i A')
    ]
]);