<?php
require_once '../../autoload.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === "POST") {

    if (!csrf_check()) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF Token']);
        exit;
    }
    $user = new Users($conn);
    $errors = [];

    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password']);

    $user->id = decrypt($_SESSION['id']);

    $original_current_password = $user->GetUserById();

    if (!password_verify($current_password, $original_current_password['password'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect Password!']);
        exit;
    }

    // PASSWORD

    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
        exit;
    }
    if ($confirm_password !== $new_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    //Atleast One Number
    if (!preg_match('/\d/', $new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one number']);
        exit;
    }

    //Atleast One Uppercase Letter
    if (!preg_match('/[A-Z]/', $new_password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter']);
        exit;
    }

    //Update Password
    $user->password = password_hash($new_password, PASSWORD_DEFAULT);
    $result = $user->UpdatePassword();

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change password']);
    }

}