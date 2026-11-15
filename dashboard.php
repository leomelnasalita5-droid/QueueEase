
<?php
require_once 'includes/auth.php';
require_login();

$user = current_user();

if ($user['role'] === 'admin') {
    redirect('admin/dashboard.php');
}

$pageTitle = 'Student Dashboard';
$base = '';

$services = $pdo->query("SELECT * FROM services ORDER BY name")->fetchAll();

$stmt = $pdo->prepare("
    SELECT q.*, s.name AS service_name
    FROM queues q
    JOIN services s ON q.service_id = s.id
    WHERE q.user_id = ? AND q.queue_date = CURDATE()
    AND q.status IN ('Waiting', 'Serving')
    ORDER BY q.created_at DESC
");
$stmt->execute([$user['id']]);
$activeQueues = $stmt->fetchAll();

include 'includes/header.php';
?>

<h1>Welcome, <?= clean($user['name']) ?>!</h1>
<h2>School Office Services</h2>

<div class="card">
    <?php foreach ($services as $service): ?>
        <h3><?= clean($service['name']) ?></h3>
        <p><?= clean($service['description']) ?></p>

        <form action="take_queue.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= clean(csrf_token()) ?>">
            <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
            <button type="submit">Get Queue Number</button>
        </form>
    <?php endforeach; ?>
</div>

<h2>Your Active Queues</h2>

<?php if ($activeQueues): ?>
    <?php foreach ($activeQueues as $queue): ?>
        <div class="card">
            <h3><?= clean($queue['service_name']) ?></h3>
            <p>Queue Number: <?= $queue['queue_number'] ?></p>
            <p>Status: <?= clean($queue['status']) ?></p>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p>You have no active queues today.</p>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
