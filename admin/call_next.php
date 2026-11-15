
<?php
require_once '../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

verify_csrf();

$serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);

if (!$serviceId) {
    set_flash('error', 'Invalid office selected.');
    redirect('dashboard.php');
}

try {
    $pdo->beginTransaction();

    // Complete the currently serving queue.
    $stmt = $pdo->prepare("
        SELECT id FROM queues
        WHERE service_id = ?
        AND queue_date = CURDATE()
        AND status = 'Serving'
        FOR UPDATE
    ");
    $stmt->execute([$serviceId]);
    $current = $stmt->fetch();

    if ($current) {
        $stmt = $pdo->prepare("
            UPDATE queues
            SET status = 'Completed'
            WHERE id = ?
        ");
        $stmt->execute([$current['id']]);

        $stmt = $pdo->prepare("
            INSERT INTO queue_history (queue_id, status, changed_by)
            VALUES (?, 'Completed', ?)
        ");
        $stmt->execute([$current['id'], current_user()['id']]);
    }

    // Find the next waiting student.
    $stmt = $pdo->prepare("
        SELECT id FROM queues
        WHERE service_id = ?
        AND queue_date = CURDATE()
        AND status = 'Waiting'
        ORDER BY queue_number ASC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$serviceId]);
    $next = $stmt->fetch();

    if ($next) {
        $stmt = $pdo->prepare("
            UPDATE queues
            SET status = 'Serving'
            WHERE id = ?
        ");
        $stmt->execute([$next['id']]);

        $stmt = $pdo->prepare("
            INSERT INTO queue_history (queue_id, status, changed_by)
            VALUES (?, 'Serving', ?)
        ");
        $stmt->execute([$next['id'], current_user()['id']]);

        set_flash('success', 'Next student has been called.');
    } else {
        set_flash('success', 'No waiting students in this office.');
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Unable to call the next student.');
}

redirect('dashboard.php?service=' . $serviceId);
