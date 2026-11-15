<?php
require_once '../includes/auth.php';
require_admin();

$pageTitle = 'Admin Dashboard';
$base = '../';

$services = $pdo->query("SELECT * FROM services ORDER BY name")->fetchAll();

$selected = filter_input(INPUT_GET, 'service_id', FILTER_VALIDATE_INT);

if (!$selected && $services) {
    $selected = (int) $services[0]['id'];
}

$validServiceIds = array_map(
    fn($service) => (int) $service['id'],
    $services
);

if (!in_array($selected, $validServiceIds, true)) {
    $selected = $services ? (int) $services[0]['id'] : 0;
}

$counts = [
    'Waiting' => 0,
    'Serving' => 0,
    'Completed' => 0,
    'Cancelled' => 0
];

$queues = [];
$used = 0;
$dailyLimit = 100;
$isOpen = false;
$cycleNumber = 1;
$activeTickets = 0;

if ($selected) {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO queue_controls
        (service_id, queue_date, is_open, daily_limit, cycle_number)
        VALUES (?, CURDATE(), 1, 100, 1)
    ");
    $stmt->execute([$selected]);

    $stmt = $pdo->prepare("
        SELECT is_open, daily_limit, cycle_number
        FROM queue_controls
        WHERE service_id = ? AND queue_date = CURDATE()
    ");
    $stmt->execute([$selected]);
    $control = $stmt->fetch();

    $dailyLimit = (int) $control['daily_limit'];
    $isOpen = (bool) $control['is_open'];
    $cycleNumber = (int) $control['cycle_number'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM queues
        WHERE service_id = ? AND queue_date = CURDATE()
    ");
    $stmt->execute([$selected]);
    $used = (int) $stmt->fetchColumn();

    if ($used >= $dailyLimit && $isOpen) {
        $pdo->prepare("
            UPDATE queue_controls
            SET is_open = 0
            WHERE service_id = ? AND queue_date = CURDATE()
        ")->execute([$selected]);

        $isOpen = false;
    }

    $countStmt = $pdo->prepare("
        SELECT status, COUNT(*) AS total
        FROM queues
        WHERE service_id = ? AND queue_date = CURDATE()
        GROUP BY status
    ");
    $countStmt->execute([$selected]);

    foreach ($countStmt->fetchAll() as $count) {
        $counts[$count['status']] = (int) $count['total'];
    }

    $activeTickets = $counts['Waiting'] + $counts['Serving'];

    $queueStmt = $pdo->prepare("
        SELECT q.*, u.name
        FROM queues q
        JOIN users u ON q.user_id = u.id
        WHERE q.service_id = ? AND q.queue_date = CURDATE()
        ORDER BY
            q.cycle_number DESC,
            CASE q.status
                WHEN 'Serving' THEN 1
                WHEN 'Waiting' THEN 2
                WHEN 'Completed' THEN 3
                WHEN 'Cancelled' THEN 4
            END,
            q.queue_number ASC
    ");
    $queueStmt->execute([$selected]);
    $queues = $queueStmt->fetchAll();
}

require_once '../includes/header.php';
?>

<section class="page-heading">
    <h1>Admin Dashboard</h1>
    <p>Manage student queues and school office services.</p>
</section>

<form method="GET" class="filter-form">
    <label for="service_id">Select School Office</label>
    <select name="service_id" id="service_id" onchange="this.form.submit()">
        <?php foreach ($services as $service): ?>
            <option
                value="<?= (int) $service['id'] ?>"
                <?= $selected === (int) $service['id'] ? 'selected' : '' ?>
            >
                <?= clean($service['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($selected): ?>
    <div class="card">
        <h2>Office Queue Controls</h2>

        <p>
            Queue status:
            <strong><?= $isOpen ? 'OPEN' : 'CLOSED' ?></strong>
        </p>

        <p>
            Current queue cycle:
            <strong><?= $cycleNumber ?></strong>
        </p>

        <p>
            Tickets issued today:
            <strong><?= $used ?> / <?= $dailyLimit ?></strong>
        </p>

        <p>
            Students still waiting or being served:
            <strong><?= $activeTickets ?></strong>
        </p>

        <div class="admin-actions">
            <?php if ($isOpen): ?>
                <form action="toggle_queue.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= clean(csrf_token()) ?>">
                    <input type="hidden" name="service_id" value="<?= $selected ?>">
                    <input type="hidden" name="action" value="close">
                    <button type="submit" class="btn">CLOSE OFFICE QUEUE</button>
                </form>

            <?php elseif ($used < $dailyLimit): ?>

                <?php if ($activeTickets > 0): ?>
                    <p>
                        Finish or cancel all waiting and serving tickets
                        before opening the next queue cycle.
                    </p>
                <?php endif; ?>

                <form action="toggle_queue.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= clean(csrf_token()) ?>">
                    <input type="hidden" name="service_id" value="<?= $selected ?>">
                    <input type="hidden" name="action" value="open">

                    <button
                        type="submit"
                        class="btn"
                        <?= $activeTickets > 0 ? 'disabled' : '' ?>
                    >
                        OPEN OFFICE QUEUE
                    </button>
                </form>

            <?php else: ?>
                <p>This office has reached its daily ticket limit.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="grid stats-grid">
    <div class="card stat-card">
        <h3>Waiting</h3>
        <p><?= $counts['Waiting'] ?></p>
    </div>

    <div class="card stat-card">
        <h3>Serving</h3>
        <p><?= $counts['Serving'] ?></p>
    </div>

    <div class="card stat-card">
        <h3>Completed</h3>
        <p><?= $counts['Completed'] ?></p>
    </div>

    <div class="card stat-card">
        <h3>Cancelled</h3>
        <p><?= $counts['Cancelled'] ?></p>
    </div>
</div>

<?php if ($selected): ?>
    <div class="admin-actions">
        <form action="call_next.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= clean(csrf_token()) ?>">
            <input type="hidden" name="service_id" value="<?= $selected ?>">
            <button type="submit" class="btn">Call Next Student</button>
        </form>
    </div>
<?php else: ?>
    <p>No school offices are available.</p>
<?php endif; ?>

<h2>Today's Queue List</h2>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Cycle</th>
                <th>Queue Number</th>
                <th>Student</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php if (!$queues): ?>
                <tr>
                    <td colspan="5">No queues for this office today.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($queues as $queue): ?>
                    <tr>
                        <td><?= (int) $queue['cycle_number'] ?></td>

                        <td>
                            Q-<?= str_pad(
                                $queue['queue_number'],
                                3,
                                '0',
                                STR_PAD_LEFT
                            ) ?>
                        </td>

                        <td><?= clean($queue['name']) ?></td>

                        <td>
                            <span class="status <?= strtolower($queue['status']) ?>">
                                <?= clean($queue['status']) ?>
                            </span>
                        </td>

                        <td>
                            <?php if (in_array($queue['status'], ['Waiting', 'Serving'], true)): ?>
                                <form action="update_queue.php" method="POST" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= clean(csrf_token()) ?>">
                                    <input type="hidden" name="queue_id" value="<?= (int) $queue['id'] ?>">
                                    <input type="hidden" name="service_id" value="<?= $selected ?>">

                                    <select name="status">
                                        <option value="Completed">Completed</option>
                                        <option value="Cancelled">Cancelled</option>
                                    </select>

                                    <button type="submit" class="btn btn-small">
                                        Update
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="muted">No action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>
