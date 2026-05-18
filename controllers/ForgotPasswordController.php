<?php
require_once '../autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/auth/forgot_password.php');
    exit;
}

if (!csrf_check()) {
    $_SESSION['error'] = 'Invalid reset request. Please try again.';
    header('Location: ../views/auth/forgot_password.php');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Enter a valid email address.';
    header('Location: ../views/auth/forgot_password.php');
    exit;
}

if (strlen($password) < 8) {
    $_SESSION['error'] = 'New password must be at least 8 characters.';
    header('Location: ../views/auth/forgot_password.php');
    exit;
}

if ($password !== $confirmPassword) {
    $_SESSION['error'] = 'Passwords do not match.';
    header('Location: ../views/auth/forgot_password.php');
    exit;
}

$user = new Users($conn);
if (!$user->IsDuplicateEmail($email)) {
    $_SESSION['error'] = 'No account found with that email address.';
    header('Location: ../views/auth/forgot_password.php');
    exit;
}

$user->email = $email;
$user->password = password_hash($password, PASSWORD_DEFAULT);

$updated = $user->UpdatePasswordByEmail();
if ($updated === true) {
    $_SESSION['success'] = 'Password updated. You can now sign in.';
    header('Location: ../views/auth/login.php');
    exit;
}

$_SESSION['error'] = 'Unable to update password. Please try again.';
header('Location: ../views/auth/forgot_password.php');
exit;
