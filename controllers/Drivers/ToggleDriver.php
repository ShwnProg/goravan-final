<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

if (!csrf_check()) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$driver_id = (int)($_POST['driver_id'] ?? 0);
$status    = $_POST['status'] ?? '';

if (!$driver_id || !in_array($status, ['active', 'inactive'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$driver = new Drivers($conn);
$driver->id = $driver_id;
$driver->status = $status;

$result = $driver->ToggleDriver();

echo json_encode([
    'success' => $result['success'],
    'message' => $result['success']
        ? 'Driver status updated successfully.'
        : ($result['message'] ?? 'Unable to update driver status. Please try again.')
]);

exit;
