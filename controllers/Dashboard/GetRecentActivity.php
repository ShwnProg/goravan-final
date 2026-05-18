<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

if (empty($_SESSION['is_login']) || empty($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access only.'
    ]);
    exit;
}

try {
    $dashboard = new Dashboard($conn);
    echo json_encode([
        'success' => true,
        'data' => $dashboard->GetRecentActivity(4)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load recent activity.'
    ]);
}

exit;
?>
