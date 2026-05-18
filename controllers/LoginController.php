<?php
require_once '../autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/auth/login.php');
    exit;
}

if (!csrf_check()) {
    $_SESSION['error'] = 'Your session expired. Please try signing in again.';
    header('Location: ../views/auth/login.php');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

$_SESSION['old_login'] = ['email' => $email];

if (!empty($_SESSION['login_locked_until']) && time() < (int) $_SESSION['login_locked_until']) {
    $_SESSION['error'] = 'Too many sign in attempts. Please wait a minute and try again.';
    header('Location: ../views/auth/login.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    $_SESSION['error'] = 'Invalid email or password.';
    header('Location: ../views/auth/login.php');
    exit;
}

$user = new Users($conn);
$user->email = $email;
$role = $user->GetRole();

if ($role === 'admin') {
    $auth = new Admin($conn);
    $auth->email = $email;
    $auth->password = $password;
    $result = $auth->AuthenticateAdmin();
    $redirect = '../views/admin/index.php';
} elseif ($role === 'user') {
    $user->password = $password;
    $result = $user->AuthenticateUser();
    $redirect = '../views/users/index.php';
} elseif ($role === 'driver') {
    $user->password = $password;
    $result = $user->AuthenticateByRole('driver');
    $redirect = '../views/driver/index.php';
} else {
    $result = ['is_login' => false, 'id' => null, 'error' => 'Invalid email or password.'];
    $redirect = '../views/auth/login.php';
}

if (!empty($result['is_login'])) {
    session_regenerate_id(true);
    $_SESSION['id'] = encrypt((string) $result['id']);
    $_SESSION['is_login'] = true;
    $_SESSION['role'] = $role;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['old_login']);
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

    if ($role === 'admin') {
        $_SESSION['success'] = 'Login successful.';
    }

    header('Location: ' . $redirect);
    exit;
}

$_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
if ($_SESSION['login_attempts'] >= 5) {
    $_SESSION['login_locked_until'] = time() + 60;
    $_SESSION['login_attempts'] = 0;
}

$_SESSION['error'] = $result['error'] ?? 'Invalid email or password.';
header('Location: ../views/auth/login.php');
exit;
?>
