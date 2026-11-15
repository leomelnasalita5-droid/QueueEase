<?php
require_once 'includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = current_user();

$stmt = $pdo->prepare("
    SELECT q.id, q.queue_number, q.status, s.name AS service_name
    FROM queues q
    JOIN services s ON s.id = q.service_id
    WHERE q.user_id = ? AND q.queue_date = CURDATE()
    ORDER BY q.created_at DESC
");
$stmt->execute([$user['id']]);

echo json_encode(['queues' => $stmt->fetchAll()]);
