
<?php
require_once __DIR__ . '/auth.php';

$user = current_user();
$flash = get_flash();
$base = $base ?? '';
$pageTitle = $pageTitle ?? 'QueueEase';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($pageTitle) ?> | QueueEase</title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="container nav-container">
        <a class="logo" href="<?= $base ?>index.php">QueueEase</a>

        <nav>
            <?php if ($user): ?>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= $base ?>admin/dashboard.php">Admin</a>
                <?php else: ?>
                    <a href="<?= $base ?>dashboard.php">Dashboard</a>
                    <a href="<?= $base ?>history.php">History</a>
                <?php endif; ?>

                <form action="<?= $base ?>logout.php" method="POST" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= clean(csrf_token()) ?>">
                    <button type="submit" class="nav-button">Logout</button>
                </form>
            <?php else: ?>
                <a href="<?= $base ?>login.php">Login</a>
                <a href="<?= $base ?>register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container">
    <?php if ($flash): ?>
        <div class="alert <?= clean($flash['type']) ?>">
            <?= clean($flash['message']) ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
