
<?php
require_once '../includes/auth.php';

require_admin();

$pageTitle = 'Admin Dashboard';
$base = '../';

$services = $pdo->query(
    "SELECT * FROM services WHERE is_active = 1 ORDER BY service_name"
)->fetchAll();

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

if ($selected) {
    $countStmt = $pdo->prepare(
        "SELECT status, COUNT(*) AS total
         FROM queues
         WHERE service_id = ? AND queue_date = CURDATE()
         GROUP BY status"
    );

    $countStmt->execute([$selected]);

    foreach ($countStmt->fetchAll() as $count) {
        $counts[$count['status']] = (int) $count['total'];
    }

    $queueStmt = $pdo->prepare(
        "SELECT q.*, u.name
         FROM queues q
         JOIN users u ON q.user_id = u.id
         WHERE q.service_id = ?
           AND q.queue_date = CURDATE()
         ORDER BY
            CASE q.status
                WHEN 'Serving' THEN 1
                WHEN 'Waiting' THEN 2
                WHEN 'Completed' THEN 3
                WHEN 'Cancelled' THEN 4
            END,
            q.queue_number ASC"
    );

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
                <?= clean($service['service_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

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
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="service_id" value="<?= $selected ?>">
            <button type="submit" class="btn">Call Next Student</button>
        </form>
    </div>
<?php else: ?>
    <p>No active school offices are available.</p>
<?php endif; ?>

<h2>Today's Queue List</h2>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Queue Number</th>
                <th>Student</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>

        <tbody>
            <?php if (!$queues): ?>
                <tr>
                    <td colspan="4">No queues for this office today.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($queues as $queue): ?>
                    <tr>
                        <td>Q-<?= str_pad($queue['queue_number'], 3, '0', STR_PAD_LEFT) ?></td>
                        <td><?= clean($queue['name']) ?></td>
                        <td>
                            <span class="status <?= strtolower($queue['status']) ?>">
                                <?= clean($queue['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (in_array($queue['status'], ['Waiting', 'Serving'], true)): ?>
                                <form action="update_queue.php" method="POST" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
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
