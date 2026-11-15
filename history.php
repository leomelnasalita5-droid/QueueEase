
<?php
require_once 'includes/auth.php';
require_login();

$user = current_user();

if ($user['role'] === 'admin') {
    redirect('admin/dashboard.php');
}

$pageTitle = 'Queue History';
$base = '';

$stmt = $pdo->prepare("
    SELECT q.*, s.name AS service_name
    FROM queues q
    JOIN services s ON q.service_id = s.id
    WHERE q.user_id = ?
    ORDER BY q.created_at DESC
");
$stmt->execute([$user['id']]);
$history = $stmt->fetchAll();

include 'includes/header.php';
?>

<h1>My Queue History</h1>

<div class="card">
    <?php if ($history): ?>
        <table>
            <tr>
                <th>Office</th>
                <th>Queue Number</th>
                <th>Date</th>
                <th>Status</th>
            </tr>

            <?php foreach ($history as $queue): ?>
                <tr>
                    <td><?= clean($queue['service_name']) ?></td>
                    <td><?= $queue['queue_number'] ?></td>
                    <td><?= clean($queue['queue_date']) ?></td>
                    <td><?= clean($queue['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No queue history found.</p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
