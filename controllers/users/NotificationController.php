<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

if (empty($_SESSION['is_login']) || empty($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$action = $_GET['action'] ?? 'list';
if ($action !== 'list') {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

try {
    $userId = (int) decrypt($_SESSION['id']);
    $notifications = new UserNotifications($conn);
    $items = $notifications->GetUserNotifications($userId, 12);

    echo json_encode([
        'success' => true,
        'count' => count($items),
        'data' => $items,
    ]);
} catch (Throwable $e) {
    error_log('[UserNotificationController] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to load notifications.']);
}
?>
