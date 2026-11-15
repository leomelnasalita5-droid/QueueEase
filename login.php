
<?php
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ];

        if ($user['role'] === 'admin') {
            redirect('admin/dashboard.php');
        }

        redirect('dashboard.php');
    }

    set_flash('error', 'Incorrect email or password.');
    redirect('login.php');
}

$pageTitle = 'Login';
$base = '';
include 'includes/header.php';
?>

<div class="card">
    <h1>Login</h1>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= clean(csrf_token()) ?>">

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit">Login</button>
    </form>

    <p>Don't have an account? <a href="register.php">Register</a></p>
</div>

<?php include 'includes/footer.php'; ?>
