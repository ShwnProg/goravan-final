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
$van_id       = (int)($_POST['van_id'] ?? 0);
$plate_number = strtoupper(trim($_POST['plate_number'] ?? ''));
$model        = trim($_POST['model'] ?? '');
$capacity     = (int)($_POST['capacity'] ?? 0);
$status       = trim($_POST['status'] ?? 'active');

/* ── VALIDATION ───────────────────────── */
if (!$van_id || !$plate_number || !$model) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields.'
    ]);
    exit;
}

if (!preg_match('/^[A-Z0-9\- ]{3,20}$/', $plate_number)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid plate number format.'
    ]);
    exit;
}

if ($capacity < 10 || $capacity > 14) {
    echo json_encode([
        'success' => false,
        'message' => 'Capacity must be between 10 and 14 seats.'
    ]);
    exit;
}

if (!in_array($status, ['active', 'inactive'])) {
    $status = 'active';
}

/* ── LOAD VAN ─────────────────────────── */
$van = new Vans($conn);
$van->id = $van_id;

$van_info = $van->GetVanByID();

if (empty($van_info)) {
    echo json_encode([
        'success' => false,
        'message' => 'Van not found.'
    ]);
    exit;
}

$existing = $van_info[0];

/* ── NO CHANGES CHECK ─────────────────── */
$samePlate    = strtolower($existing['plate_number']) === strtolower($plate_number);
$sameModel    = strtolower($existing['model']) === strtolower($model);
$sameCapacity = (int)$existing['capacity'] === $capacity;
$sameStatus   = $existing['status'] === $status;

if ($samePlate && $sameModel && $sameCapacity && $sameStatus) {
    echo json_encode([
        'no_changes' => true,
        'message' => 'No changes were made.'
    ]);
    exit;
}

/* ── DUPLICATE PLATE CHECK ────────────── */
if (!$samePlate && $van->IsPlateExistExcept()) {
    echo json_encode([
        'success' => false,
        'message' => 'A van with that plate number already exists.'
    ]);
    exit;
}

/* ── UPDATE ───────────────────────────── */
$van->plate_number = $plate_number;
$van->model        = $model;
$van->capacity     = $capacity;
$van->status       = $status;

$result = $van->EditVan();

/* ── RESPONSE ─────────────────────────── */
if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Van updated successfully.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Unable to update record. Please check the details and try again.'
    ]);
}

exit;
