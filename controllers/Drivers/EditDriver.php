<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

if (!csrf_check()) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$driver_id = (int)($_POST['driver_id'] ?? 0);

if (!$driver_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid driver ID']);
    exit;
}

$full_name      = trim($_POST['full_name'] ?? '');
$license_number = strtoupper(trim($_POST['license_number'] ?? ''));
$contact_number = trim($_POST['contact_number'] ?? '');
$email          = strtolower(trim($_POST['email'] ?? ''));
$status         = $_POST['status'] ?? 'active';

if (!$full_name || !$license_number || !$contact_number || !$email) {
    echo json_encode(['success' => false, 'message' => 'All fields required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid driver email address']);
    exit;
}

if (!preg_match('/^[A-Z0-9\-]{3,30}$/', $license_number)) {
    echo json_encode(['success' => false, 'message' => 'Invalid license format']);
    exit;
}

if (!preg_match('/^09\d{9}$/', $contact_number)) {
    echo json_encode(['success' => false, 'message' => 'Invalid contact number']);
    exit;
}

if (!in_array($status, ['active', 'inactive'])) {
    $status = 'active';
}

$driver = new Drivers($conn);
$driver->id = $driver_id;
$driver->full_name = $full_name;
$driver->license_number = $license_number;
$driver->contact_number = $contact_number;
$driver->email = $email;
$driver->password = '';
$driver->status = $status;

$current = $driver->GetDriverByID();

if ($current &&
    $current['full_name'] === $full_name &&
    $current['license_number'] === $license_number &&
    $current['contact_number'] === $contact_number &&
    ($current['login_email'] ?? '') === $email &&
    $current['status'] === $status
) {
    echo json_encode([
        'no_changes' => true,
        'message' => 'No changes were made'
    ]);
    exit;
}

if ($driver->IsLicenseExistExcept()) {
    echo json_encode(['success' => false, 'message' => 'License already exists']);
    exit;
}

$result = $driver->EditDriver();

echo json_encode([
    'success' => $result['success'],
    'message' => $result['success']
        ? 'Driver updated successfully.'
        : ($result['message'] ?? 'Unable to update record. Please check the details and try again.')
]);

exit;
