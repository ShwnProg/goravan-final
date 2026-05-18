<?php
require_once '../../autoload.php';

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function driver_trip_response(bool $success, string $message, array $extra = []): void
{
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
        exit;
    }

    $_SESSION[$success ? 'success' : 'error'] = $message;
    header('Location: ../../views/driver/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    driver_trip_response(false, 'Invalid request method.');
}

if (!csrf_check()) {
    driver_trip_response(false, 'Invalid or expired CSRF token. Please refresh and try again.');
}

$driverUserId = (int) decrypt($_SESSION['id']);
$rawScheduleId = trim($_POST['schedule_id'] ?? '');
$scheduleId = (int) decrypt($rawScheduleId);
if (!$scheduleId && ctype_digit($rawScheduleId)) {
    $scheduleId = (int) $rawScheduleId;
}

$newStatus = strtolower(trim($_POST['status'] ?? ''));

$schedules = new Schedules($conn);
$result = $schedules->UpdateTripStatusByDriver($scheduleId, $driverUserId, $newStatus);

driver_trip_response(
    !empty($result['success']),
    !empty($result['success'])
        ? 'Trip marked as ' . $schedules->TripStatusLabel($result['status'] ?? $newStatus) . '.'
        : ($result['message'] ?? 'Unable to update trip status.'),
    ['status' => $result['status'] ?? $newStatus]
);
