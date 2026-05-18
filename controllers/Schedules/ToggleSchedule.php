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
$schedule_id = (int) ($_POST['schedule_id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');

/* -- VALIDATION ------------------------------------------------------------- */
if (!$schedule_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid schedule ID.'
    ]);
    exit;
}

if ($new_status === 'boarding') {
    $new_status = 'not_departed';
}

if (!in_array($new_status, ['not_departed', 'cancelled'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status.'
    ]);
    exit;
}

/* -- UPDATE STATUS ---------------------------------------------------------- */
$schedule = new Schedules($conn);
$schedule->id = $schedule_id;
$schedule->trip_status = $new_status;

if ($new_status !== 'cancelled') {
    echo json_encode([
        'success' => false,
        'message' => 'Trip movement is controlled by the assigned driver.'
    ]);
    exit;
}

if (!$schedule->canUpdateStatus($new_status)) {
    echo json_encode([
        'success' => false,
        'message' => 'Only not departed schedules can be cancelled from admin.'
    ]);
    exit;
}

$result = $schedule->UpdateStatus();

/* -- RESPONSE --------------------------------------------------------------- */
echo json_encode([
    'success' => $result['success'],
    'message' => $result['success']
        ? 'Schedule status updated successfully.'
        : ($result['message'] ?? 'Unable to update schedule status. Please try again.')
]);

exit;
?>

