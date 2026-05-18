<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

/* -- CSRF CHECK ------------------------------------------------------------- */
if (!csrf_check()) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token.'
    ]);
    exit;
}

/* -- INPUTS ----------------------------------------------------------------- */
$routeId = (int)($_POST['route_id'] ?? 0);

/* -- VALIDATION ------------------------------------------------------------- */
if (!$routeId) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid route.'
    ]);
    exit;
}

/* -- DELETE ----------------------------------------------------------------- */
$route     = new Routes($conn);
$route->id = $routeId;
$result    = $route->DeleteRoute();

/* -- RESPONSE --------------------------------------------------------------- */
echo json_encode([
    'success' => $result['success'],
    'message' => $result['message'] ?? ($result['success']
        ? 'Route deleted successfully.'
        : 'Failed to delete route.')
]);

exit;
?>
