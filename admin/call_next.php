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

    // Serialize calls for this office.
    $stmt = $pdo->prepare("SELECT id FROM services WHERE id = ? FOR UPDATE");
    $stmt->execute([$serviceId]);

    if (!$stmt->fetch()) {
        throw new Exception('Office not found.');
    }

    $stmt = $pdo->prepare("
        SELECT id FROM queues
        WHERE service_id = ? AND queue_date = CURDATE()
          AND status = 'Serving'
        FOR UPDATE
    ");
    $stmt->execute([$serviceId]);
    $current = $stmt->fetch();

    if ($current) {
        $pdo->prepare("
            UPDATE queues SET status = 'Completed' WHERE id = ?
        ")->execute([$current['id']]);

        $pdo->prepare("
            INSERT INTO queue_history (queue_id, status, changed_by)
            VALUES (?, 'Completed', ?)
        ")->execute([$current['id'], current_user()['id']]);
    }

    $stmt = $pdo->prepare("
        SELECT id FROM queues
        WHERE service_id = ? AND queue_date = CURDATE()
          AND status = 'Waiting'
        ORDER BY queue_number ASC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$serviceId]);
    $next = $stmt->fetch();

    if ($next) {
        $pdo->prepare("
            UPDATE queues SET status = 'Serving' WHERE id = ?
        ")->execute([$next['id']]);

        $pdo->prepare("
            INSERT INTO queue_history (queue_id, status, changed_by)
            VALUES (?, 'Serving', ?)
        ")->execute([$next['id'], current_user()['id']]);

        $message = 'Next student has been called.';
    } else {
        $message = 'No waiting students in this office.';
    }

    $pdo->commit();
    set_flash('success', $message);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Unable to call the next student: ' . $e->getMessage());
}

redirect('dashboard.php?service_id=' . $serviceId);
