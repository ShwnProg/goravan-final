<?php
require_once '../../autoload.php';

require_role('driver');

function driver_profile_redirect(bool $success, string $message): void
{
    $_SESSION[$success ? 'success' : 'error'] = $message;
    header('Location: ../../views/driver/profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    driver_profile_redirect(false, 'Invalid request method.');
}

if (!csrf_check()) {
    driver_profile_redirect(false, 'Invalid or expired CSRF token. Please refresh and try again.');
}

$userId = (int) decrypt($_SESSION['id'] ?? '');
if (!$userId) {
    driver_profile_redirect(false, 'Driver session expired. Please log in again.');
}

$action = strtolower(trim($_POST['action'] ?? ''));
$driverObj = new Drivers($conn);

if ($action === 'profile') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = preg_replace('/\D+/', '', $_POST['contact_number'] ?? '');

    if (strlen($fullName) < 2) {
        driver_profile_redirect(false, 'Please enter your full name.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        driver_profile_redirect(false, 'Please enter a valid email address.');
    }

    if (!preg_match('/^09\d{9}$/', $contact)) {
        driver_profile_redirect(false, 'Use a Philippine mobile number like 0912 345 6789.');
    }

    $result = $driverObj->UpdateOwnProfile($userId, $fullName, $email, $contact);
    driver_profile_redirect((bool) ($result['success'] ?? false), $result['message'] ?? 'Unable to update profile.');
}

if ($action === 'password') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        driver_profile_redirect(false, 'Please complete all password fields.');
    }

    if ($newPassword !== $confirmPassword) {
        driver_profile_redirect(false, 'New password and confirmation do not match.');
    }

    if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
        driver_profile_redirect(false, 'New password must be at least 8 characters and include an uppercase letter and a number.');
    }

    if ($currentPassword === $newPassword) {
        driver_profile_redirect(false, 'New password must be different from the current password.');
    }

    $result = $driverObj->ChangeOwnPassword($userId, $currentPassword, $newPassword);
    driver_profile_redirect((bool) ($result['success'] ?? false), $result['message'] ?? 'Unable to change password.');
}

driver_profile_redirect(false, 'Invalid profile action.');
