
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function require_login() {
    if (!isset($_SESSION['user'])) {
        redirect('login.php');
    }
}

function require_admin() {
    require_login();

    if ($_SESSION['user']['role'] !== 'admin') {
        redirect('../dashboard.php');
    }
}

function current_user() {
    return $_SESSION['user'] ?? null;
}
