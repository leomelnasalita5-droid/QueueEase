
<?php
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        set_flash('error', 'Enter a valid name and email. Password must be at least 8 characters.');
        redirect('register.php');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        set_flash('error', 'Email is already registered.');
        redirect('register.php');
    }

    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $name,
        $email,
        password_hash($password, PASSWORD_DEFAULT)
    ]);

    set_flash('success', 'Registration successful. You can now log in.');
    redirect('login.php');
}

$pageTitle = 'Register';
$base = '';
include 'includes/header.php';
?>

<div class="card">
    <h1>Student Registration</h1>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= clean(csrf_token()) ?>">

        <label>Full Name</label>
        <input type="text" name="name" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" minlength="8" required>

        <button type="submit">Register</button>
    </form>

    <p>Already registered? <a href="login.php">Login here</a></p>
</div>

<?php include 'includes/footer.php'; ?>
