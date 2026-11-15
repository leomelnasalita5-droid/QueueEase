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

    // Lock the office to prevent simultaneous ticket-number conflicts.
    $stmt = $pdo->prepare("SELECT id FROM services WHERE id = ? FOR UPDATE");
    $stmt->execute([$serviceId]);

    if (!$stmt->fetch()) {
        throw new Exception('Office not found.');
    }

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO queue_controls
        (service_id, queue_date, is_open, daily_limit, cycle_number)
        VALUES (?, CURDATE(), 1, 100, 1)
    ");
    $stmt->execute([$serviceId]);

    $stmt = $pdo->prepare("
        SELECT is_open, daily_limit, cycle_number
        FROM queue_controls
        WHERE service_id = ? AND queue_date = CURDATE()
        FOR UPDATE
    ");
    $stmt->execute([$serviceId]);
    $control = $stmt->fetch();

    if (!$control) {
        throw new Exception('Unable to load office queue settings.');
    }

    // The daily limit counts tickets from every cycle.
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM queues
        WHERE service_id = ? AND queue_date = CURDATE()
    ");
    $stmt->execute([$serviceId]);
    $used = (int) $stmt->fetchColumn();

    if ($used >= (int) $control['daily_limit']) {
        $pdo->prepare("
            UPDATE queue_controls
            SET is_open = 0
            WHERE service_id = ? AND queue_date = CURDATE()
        ")->execute([$serviceId]);

        throw new Exception('This office has reached its daily limit of 100 tickets.');
    }

    if (!(int) $control['is_open']) {
        throw new Exception('This office queue is CLOSED. Please try again later.');
    }

    // A student may only have one active ticket per office per day.
    $stmt = $pdo->prepare("
        SELECT id
        FROM queues
        WHERE user_id = ?
          AND service_id = ?
          AND queue_date = CURDATE()
          AND status IN ('Waiting', 'Serving')
        LIMIT 1
    ");
    $stmt->execute([$user['id'], $serviceId]);

    if ($stmt->fetch()) {
        throw new Exception('You already have an active queue for this office.');
    }

    $cycleNumber = (int) $control['cycle_number'];

    // Numbering restarts at 1 for each new cycle.
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(queue_number), 0) + 1
        FROM queues
        WHERE service_id = ?
          AND queue_date = CURDATE()
          AND cycle_number = ?
    ");
    $stmt->execute([$serviceId, $cycleNumber]);
    $queueNumber = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO queues
        (user_id, service_id, queue_number, queue_date, status, cycle_number)
        VALUES (?, ?, ?, CURDATE(), 'Waiting', ?)
    ");
    $stmt->execute([
        $user['id'],
        $serviceId,
        $queueNumber,
        $cycleNumber
    ]);

    $queueId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO queue_history (queue_id, status, changed_by)
        VALUES (?, 'Waiting', ?)
    ");
    $stmt->execute([$queueId, $user['id']]);

    // Automatically close when the total daily limit is reached.
    if ($used + 1 >= (int) $control['daily_limit']) {
        $pdo->prepare("
            UPDATE queue_controls
            SET is_open = 0
            WHERE service_id = ? AND queue_date = CURDATE()
        ")->execute([$serviceId]);
    }

    $pdo->commit();

    set_flash(
        'success',
        'Your queue number is Q-' . str_pad($queueNumber, 3, '0', STR_PAD_LEFT) . '.'
    );

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    set_flash('error', $e->getMessage());
}

redirect('dashboard.php');
