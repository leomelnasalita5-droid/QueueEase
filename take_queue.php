
<?php
require_once 'includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

verify_csrf();

$user = current_user();

if ($user['role'] !== 'student') {
    redirect('admin/dashboard.php');
}

$serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);

if (!$serviceId) {
    set_flash('error', 'Invalid office selected.');
    redirect('dashboard.php');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id FROM services
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->execute([$serviceId]);

    if (!$stmt->fetch()) {
        throw new Exception('Office not found.');
    }

    $stmt = $pdo->prepare("
        SELECT id FROM queues
        WHERE user_id = ? AND service_id = ?
        AND queue_date = CURDATE()
        AND status IN ('Waiting', 'Serving')
    ");
    $stmt->execute([$user['id'], $serviceId]);

    if ($stmt->fetch()) {
        throw new Exception('You already have an active queue for this office.');
    }

    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(queue_number), 0) + 1
        FROM queues
        WHERE service_id = ? AND queue_date = CURDATE()
    ");
    $stmt->execute([$serviceId]);
    $queueNumber = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO queues
        (user_id, service_id, queue_number, queue_date, status)
        VALUES (?, ?, ?, CURDATE(), 'Waiting')
    ");
    $stmt->execute([$user['id'], $serviceId, $queueNumber]);

    $queueId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO queue_history (queue_id, status, changed_by)
        VALUES (?, 'Waiting', ?)
    ");
    $stmt->execute([$queueId, $user['id']]);

    $pdo->commit();

    set_flash('success', 'Your queue number is ' . $queueNumber . '.');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    set_flash('error', $e->getMessage());
}

redirect('dashboard.php');
