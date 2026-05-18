<?php
require_once __DIR__ . '/../autoload.php';

header('Content-Type: application/json');

// Auth guard
if (empty($_SESSION['is_login'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// Parse JSON body
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// CSRF check
if (empty($data['csrf_token']) || $data['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Security token mismatch. Please refresh and try again.']);
    exit;
}

$adminId = decrypt($_SESSION['id']);

// Validate decryption worked
if ($adminId === false || !is_numeric($adminId)) {
    error_log('SettingsController: Decryption failed for id: ' . ($_SESSION['id'] ?? 'empty'));
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$adminId = (int)$adminId;
$admin   = new Admin($conn);
$admin->id = $adminId;

// Route actions
try {
    switch ($data['action']) {

        case 'update_profile':
            handleUpdateProfile($conn, $adminId, $data);
            break;

        case 'change_password':
            handleChangePassword($conn, $adminId, $admin, $data);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            break;
    }
} catch (Exception $e) {
    error_log('SettingsController error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    exit;
}

// Update profile handler

function handleUpdateProfile(PDO $conn, int $adminId, array $data): void
{
    $firstName = trim($data['first_name'] ?? '');
    $lastName  = trim($data['last_name']  ?? '');
    $email     = trim($data['email']      ?? '');
    $contact   = trim($data['contact_number'] ?? '');

    if ($firstName === '' || $lastName === '') {
        echo json_encode(['success' => false, 'message' => 'First and last name are required.']);
        return;
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
        return;
    }

    // Check email uniqueness (excluding current user)
    $checkStmt = $conn->prepare("SELECT user_id_pk FROM users WHERE email = :email AND user_id_pk != :id");
    $checkStmt->execute([':email' => $email, ':id' => $adminId]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'That email is already in use by another account.']);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE users
        SET firstname = :firstname, lastname = :lastname, email = :email, contact_number = :contact
        WHERE user_id_pk = :id AND role = 'admin'
    ");

    $ok = $stmt->execute([
        ':firstname' => $firstName,
        ':lastname'  => $lastName,
        ':email'     => $email,
        ':contact'   => $contact,
        ':id'        => $adminId,
    ]);

    if ($ok) {
        $updated = $stmt->rowCount();
        if ($updated > 0) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes were made.']);
        }
    } else {
        error_log('Profile update failed for admin ' . $adminId);
        echo json_encode(['success' => false, 'message' => 'Failed to update profile. Please try again.']);
    }
}

function handleChangePassword(PDO $conn, int $adminId, Admin $admin, array $data): void
{
    $current = $data['current_password'] ?? '';
    $new     = $data['new_password']     ?? '';
    $confirm = $data['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
        return;
    }

    if (strlen($new) < 8) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
        return;
    }

    if ($new !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
        return;
    }

    // Fetch current hashed password
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id_pk = :id AND role = 'admin'");
    $stmt->execute([':id' => $adminId]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        return;
    }

    $hash  = password_hash($new, PASSWORD_DEFAULT);
    $upStmt = $conn->prepare("UPDATE users SET password = :password WHERE user_id_pk = :id AND role = 'admin'");
    $ok     = $upStmt->execute([':password' => $hash, ':id' => $adminId]);

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change password. Please try again.']);
    }
}