<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

/* ── CSRF CHECK ───────────────────────── */
if (!csrf_check()) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token.'
    ]);
    exit;
}

/* ── INPUTS ───────────────────────────── */
$plate_number = strtoupper(trim($_POST['plate_number'] ?? ''));
$model        = trim($_POST['model'] ?? '');
$capacity     = (int) ($_POST['capacity'] ?? 0);
$status       = trim($_POST['status'] ?? 'active');

/* ── VALIDATION ───────────────────────── */
if (!$plate_number) {
    echo json_encode(['success' => false, 'message' => 'Plate number is required.']);
    exit;
}

if (!preg_match('/^[A-Z0-9\- ]{3,20}$/', $plate_number)) {
    echo json_encode(['success' => false, 'message' => 'Invalid plate number format.']);
    exit;
}

if (!$model) {
    echo json_encode(['success' => false, 'message' => 'Van model is required.']);
    exit;
}

if ($capacity <= 0 || $capacity > 14) {
    echo json_encode(['success' => false, 'message' => 'Capacity must be between 1 and 14.']);
    exit;
}

if (!in_array($status, ['active', 'inactive'])) {
    $status = 'active';
}

/* ── SAVE ─────────────────────────────── */
$van               = new Vans($conn);
$van->plate_number = $plate_number;
$van->model        = $model;
$van->capacity     = $capacity;
$van->status       = $status;

if ($van->IsPlateExist()) {
    echo json_encode([
        'success' => false,
        'message' => 'A van with that plate number already exists.'
    ]);
    exit;
}

$result = $van->AddVan();

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Van added successfully.'
    ]);
} else {
    if (!empty($result['error'])) {
        error_log('[AddVan] ' . $result['error']);
    }
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Unable to add van. Please check the details and try again.'
    ]);
}

exit;
