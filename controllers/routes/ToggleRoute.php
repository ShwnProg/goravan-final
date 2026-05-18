<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

/* ── CSRF CHECK ───────────────────────────────────────────────────────────── */
if (!csrf_check()) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token.'
    ]);
    exit;
}

/* ── INPUTS ───────────────────────────────────────────────────────────────── */
$routeId  = (int)($_POST['route_id']  ?? 0);
$isActive = (int)($_POST['is_active'] ?? 0);

/* ── VALIDATION ───────────────────────────────────────────────────────────── */
if (!$routeId) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid route ID.'
    ]);
    exit;
}

if (!in_array($isActive, [0, 1])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status.'
    ]);
    exit;
}

/* ── UPDATE ───────────────────────────────────────────────────────────────── */
$route         = new Routes($conn);
$route->id     = $routeId;
$route->status = $isActive;
$result        = $route->ToggleRoute();

/* ── RESPONSE ─────────────────────────────────────────────────────────────── */
echo json_encode([
    'success' => $result['success'],
    'message' => $result['success']
        ? 'Route status updated successfully.'
        : ($result['message'] ?? 'Unable to update route status. Please try again.')
]);

exit;
?>
