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
$schedule_id    = (int)   ($_POST['schedule_id']    ?? 0);
$route_id       = (int)   ($_POST['route_id']       ?? 0);
$driver_id      = (int)   ($_POST['driver_id']      ?? 0);
$van_id         = (int)   ($_POST['van_id']         ?? 0);
$departure_date = trim(    $_POST['departure_date']  ?? '');
$departure_time = trim(    $_POST['departure_time']  ?? '');
$eta_date       = trim(    $_POST['eta_date']        ?? '');
$eta_time       = trim(    $_POST['eta_time']        ?? '');
$posted_status  = trim(    $_POST['trip_status']     ?? '');

/* -- VALIDATION ------------------------------------------------------------- */
if (!$schedule_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid schedule ID.'
    ]);
    exit;
}

if (!$route_id || !$driver_id || !$van_id || !$departure_date || !$departure_time || !$eta_date || !$eta_time) {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required.'
    ]);
    exit;
}

$departureTimestamp = strtotime($departure_date . ' ' . $departure_time);
$etaTimestamp = strtotime($eta_date . ' ' . $eta_time);
if ($departureTimestamp === false || $etaTimestamp === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date/time format.'
    ]);
    exit;
}

if ($etaTimestamp < $departureTimestamp) {
    echo json_encode([
        'success' => false,
        'message' => 'ETA cannot be earlier than departure.'
    ]);
    exit;
}

/* -- LOAD SCHEDULE ---------------------------------------------------------- */
$schedule = new Schedules($conn);
$schedule->id = $schedule_id;

$current = $schedule->GetScheduleByID();

if (empty($current)) {
    echo json_encode([
        'success' => false,
        'message' => 'Schedule not found.'
    ]);
    exit;
}

$current = $current[0];
$currentStatus = $schedule->NormalizeTripStatus((string) ($current['trip_status'] ?? 'not_departed'));
$posted_status = $posted_status === 'boarding' ? 'not_departed' : $posted_status;
$trip_status = $currentStatus;

if ($posted_status !== '' && $posted_status !== $currentStatus) {
    if ($posted_status === 'cancelled' && $currentStatus === 'not_departed') {
        $trip_status = 'cancelled';
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Trip movement is controlled by the assigned driver.'
        ]);
        exit;
    }
}

$noChanges =
    (int)    $current['route_id_fk']    === $route_id       &&
    (int)    $current['driver_id_fk']   === $driver_id      &&
    (int)    $current['van_id_fk']      === $van_id         &&
             $current['departure_date'] === $departure_date &&
             $current['departure_time'] === $departure_time &&
             ($current['estimated_arrival_at'] ?? '') === date('Y-m-d H:i:s', $etaTimestamp) &&
             $currentStatus             === $trip_status;

if ($noChanges) {
    echo json_encode([
        'no_changes' => true,
        'message' => 'No changes were made.'
    ]);
    exit;
}

if (in_array($currentStatus, ['departed', 'arrived', 'completed'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Trips already in movement cannot be edited. The assigned driver controls trip progress.'
    ]);
    exit;
}

/* -- CONFLICT CHECK --------------------------------------------------------- */
$schedule->route_id       = $route_id;
$schedule->driver_id      = $driver_id;
$schedule->van_id         = $van_id;
$schedule->departure_date = $departure_date;
$schedule->departure_time = $departure_time;
$schedule->estimated_arrival_at = date('Y-m-d H:i:s', $etaTimestamp);
$schedule->trip_status    = $trip_status;

if ($schedule->HasVanConflict() || $schedule->HasDriverConflict()) {
    echo json_encode([
        'success' => false,
        'message' => 'Van or driver conflict at selected time.'
    ]);
    exit;
}

/* -- UPDATE ----------------------------------------------------------------- */
$result = $schedule->EditSchedule();

/* -- RESPONSE --------------------------------------------------------------- */
if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Schedule updated successfully.'
    ]);
} else {
    if (!empty($result['error'])) {
        error_log('[EditSchedule] ' . $result['error']);
    }
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Unable to update schedule. Please check the details and try again.'
    ]);
}

exit;
?>
