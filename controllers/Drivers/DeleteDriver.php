<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

if (!csrf_check()) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]);
    exit;
}

$driver_id = (int)($_POST['driver_id'] ?? 0);

if (!$driver_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid driver ID'
    ]);
    exit;
}

$driver = new Drivers($conn);
$driver->id = $driver_id;

$result = $driver->DeleteDriver();

echo json_encode([
    'success' => $result['success'],
    'message' => $result['message'] ?? ($result['success']
        ? 'Driver deleted successfully.'
        : 'Failed to delete driver.')
]);

exit;
