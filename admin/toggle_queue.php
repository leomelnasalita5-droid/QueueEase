<?php
require_once '../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

verify_csrf();

$serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (!$serviceId || !in_array($action, ['open', 'close'], true)) {
    set_flash('error', 'Invalid queue control request.');
    redirect('dashboard.php');
}

try {
    $pdo->beginTransaction();

    // Lock the office so queue operations cannot overlap.
    $stmt = $pdo->prepare("
        SELECT id
        FROM services
        WHERE id = ?
        FOR UPDATE
    ");
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

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM queues
        WHERE service_id = ?
          AND queue_date = CURDATE()
    ");
    $stmt->execute([$serviceId]);
    $used = (int) $stmt->fetchColumn();

    if ($action === 'close') {
        $stmt = $pdo->prepare("
            UPDATE queue_controls
            SET is_open = 0
            WHERE service_id = ? AND queue_date = CURDATE()
        ");
        $stmt->execute([$serviceId]);

        $pdo->commit();
        set_flash('success', 'Office queue is now CLOSED. Existing students can still be served.');

    } else {
        if ((int) $control['is_open'] === 1) {
            throw new Exception('This office queue is already OPEN.');
        }

        if ($used >= (int) $control['daily_limit']) {
            throw new Exception('Cannot reopen: this office has reached its daily limit of 100 tickets.');
        }

        // Do not start another cycle while anyone is still waiting or being served.
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM queues
            WHERE service_id = ?
              AND queue_date = CURDATE()
              AND status IN ('Waiting', 'Serving')
        ");
        $stmt->execute([$serviceId]);
        $activeTickets = (int) $stmt->fetchColumn();

        if ($activeTickets > 0) {
            throw new Exception(
                'Cannot reopen yet. Please finish or cancel all waiting and serving tickets first.'
            );
        }

        // All previous tickets are finished. Start a new numbering cycle.
        $stmt = $pdo->prepare("
            UPDATE queue_controls
            SET is_open = 1,
                cycle_number = cycle_number + 1
            WHERE service_id = ? AND queue_date = CURDATE()
        ");
        $stmt->execute([$serviceId]);

        $pdo->commit();
        set_flash('success', 'Office queue reopened. The new cycle will start at Q-001.');
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    set_flash('error', $e->getMessage());
}

redirect('dashboard.php?service_id=' . $serviceId);
