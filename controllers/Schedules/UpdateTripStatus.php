<?php
require_once '../../autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    $_SESSION['error'] = 'Invalid CSRF token.';
    header('Location: ../../views/admin/schedules.php');
    exit;
}

$schedule_id = (int) ($_POST['schedule_id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');

if ($new_status === 'boarding') {
    $new_status = 'not_departed';
}

if (!$schedule_id || !in_array($new_status, ['not_departed', 'cancelled'])) {
    $_SESSION['error'] = 'Invalid parameters.';
    header('Location: ../../views/admin/schedules.php');
    exit;
}

if ($new_status !== 'cancelled') {
    $_SESSION['error'] = 'Trip movement is controlled by the assigned driver.';
    header('Location: ../../views/admin/schedules.php');
    exit;
}

$schedule = new Schedules($conn);
$schedule->id = $schedule_id;
$result = $schedule->CancelScheduleByAdmin();

$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? ($result['message'] ?? 'Schedule cancelled successfully.')
    : ($result['message'] ?? 'Unable to update schedule status. Please try again.');

header('Location: ../../views/admin/schedules.php');
exit;
?>

