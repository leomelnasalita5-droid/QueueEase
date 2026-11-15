
q
<?php
require_once 'includes/auth.php';

$pageTitle = 'Home';
$base = '';

include 'includes/header.php';
?>

<section class="card">
    <h1>Welcome to QueueEase</h1>
    <p>Your convenient school office queue management system.</p>
    <p>Skip the confusion and manage your school office queue online.</p>

    <?php if (current_user()): ?>
        <a href="dashboard.php" class="btn">Go to Dashboard</a>
    <?php else: ?>
        <a href="register.php" class="btn">Get Started</a>
        <a href="login.php" class="btn">Login</a>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
