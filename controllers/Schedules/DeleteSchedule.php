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

/* -- VALIDATION ------------------------------------------------------------- */
if (!$schedule_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid schedule ID.'
    ]);
    exit;
}

/* -- DELETE ----------------------------------------------------------------- */
$schedule       = new Schedules($conn);
$schedule->id   = $schedule_id;
$result         = $schedule->DeleteSchedule();

/* -- RESPONSE --------------------------------------------------------------- */
echo json_encode([
    'success' => $result['success'],
    'message' => $result['message'] ?? ($result['success']
        ? 'Schedule deleted successfully.'
        : 'Failed to delete schedule.')
]);

exit;
?>
