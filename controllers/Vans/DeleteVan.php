<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

if (!csrf_check()) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token.'
    ]);
    exit;
}

$van_id = (int) ($_POST['van_id'] ?? 0);

if (!$van_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid van.'
    ]);
    exit;
}

$van = new Vans($conn);
$van->id = $van_id;

$result = $van->DeleteVan();

echo json_encode([
    'success' => $result['success'],
    'message' => $result['message'] ?? ($result['success']
        ? 'Van deleted successfully.'
        : 'Failed to delete van.')
]);
exit;
