
<?php
require_once '../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

verify_csrf();

$queueId = filter_input(INPUT_POST, 'queue_id', FILTER_VALIDATE_INT);
$status = $_POST['status'] ?? '';

if (!$queueId || !in_array($status, ['Completed', 'Cancelled'], true)) {
    set_flash('error', 'Invalid queue update.');
    redirect('dashboard.php');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, status, service_id
        FROM queues
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->execute([$queueId]);
    $queue = $stmt->fetch();

    if (!$queue || !in_array($queue['status'], ['Waiting', 'Serving'], true)) {
        throw new Exception('Queue cannot be updated.');
    }

    $stmt = $pdo->prepare("
        UPDATE queues
        SET status = ?
        WHERE id = ?
    ");
    $stmt->execute([$status, $queueId]);

    $stmt = $pdo->prepare("
        INSERT INTO queue_history (queue_id, status, changed_by)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$queueId, $status, current_user()['id']]);

    $pdo->commit();

    set_flash('success', 'Queue status updated successfully.');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Unable to update queue status.');
}

redirect('dashboard.php');
