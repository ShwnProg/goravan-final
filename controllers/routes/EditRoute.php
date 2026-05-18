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
$routeId     = (int)   ($_POST['route_id']    ?? 0);
$origin      = trim(   ($_POST['origin']       ?? ''));
$destination = trim(   ($_POST['destination']  ?? ''));
$fare        = (float) ($_POST['fare']         ?? 0);
$status      = (int)   ($_POST['is_active']    ?? 1);

$stops = array_values(array_filter(
    array_map('trim', $_POST['stops'] ?? []),
    fn($s) => $s !== ''
));

/* ── VALIDATION ───────────────────────────────────────────────────────────── */
if (!$routeId || !$origin || !$destination) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields.'
    ]);
    exit;
}

if ($fare <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Fare must be a positive number.'
    ]);
    exit;
}

if ($origin === $destination) {
    echo json_encode([
        'success' => false,
        'message' => 'Origin and destination cannot be the same.'
    ]);
    exit;
}

/* ── LOAD ROUTE ───────────────────────────────────────────────────────────── */
$route = new Routes($conn);
$route->id = $routeId;
$route_info = $route->GetRouteByID();

if (empty($route_info)) {
    echo json_encode([
        'success' => false,
        'message' => 'Route not found.'
    ]);
    exit;
}

$existing      = $route_info[0];
$existingStops = array_column($existing['stops'], 'stop_name');

/* ── NO CHANGES CHECK ─────────────────────────────────────────────────────── */
$sameOrigin      = strtolower($existing['origin'])      === strtolower($origin);
$sameDestination = strtolower($existing['destination']) === strtolower($destination);
$sameFare        = (float) $existing['fare']            === $fare;
$sameStatus      = (int)   $existing['is_active']       === $status;
$sameStops       = array_map('strtolower', $existingStops)
                   === array_map('strtolower', $stops);

if ($sameOrigin && $sameDestination && $sameFare && $sameStatus && $sameStops) {
    echo json_encode([
        'no_changes' => true,
        'message' => 'No changes were made.'
    ]);
    exit;
}

/* ── DUPLICATE CHECK ──────────────────────────────────────────────────────── */
$routeSignatureChanged = !$sameOrigin || !$sameDestination || !$sameStops;

$route->origin      = $origin;
$route->destination = $destination;
$route->stops       = $stops;

if ($routeSignatureChanged && $route->IsRouteExist()) {
    echo json_encode([
        'success' => false,
        'message' => 'That route already exists.'
    ]);
    exit;
}

/* ── UPDATE ───────────────────────────────────────────────────────────────── */
$route->status = $status;
$route->fare   = $fare;
$result        = $route->EditRoute();

/* ── RESPONSE ─────────────────────────────────────────────────────────────── */
if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Route updated successfully.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Unable to update record. Please check the details and try again.'
    ]);
}

exit;
?>
