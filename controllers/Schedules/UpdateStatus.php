<?php
require_once '../../autoload.php';

// -- Auth guard ----------------------------------------------------------------
if (empty($_SESSION['is_login'])) {
    header('Location: ../../views/auth/login.php');
    exit;
}

// -- Method + CSRF -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    $_SESSION['error'] = 'Invalid request or CSRF token.';
    header('Location: ../../views/admin/schedules.php');
    exit;
}

// -- Validate input ------------------------------------------------------------
$schedule_id = (int) ($_POST['schedule_id'] ?? 0);
$new_status  = trim($_POST['status'] ?? '');
$valid       = ['not_departed', 'boarding', 'cancelled'];

if (!$schedule_id || !in_array($new_status, $valid, true)) {
    $_SESSION['error'] = 'Invalid parameters.';
    header('Location: ../../views/admin/schedules.php');
    exit;
}

if ($new_status === 'boarding') {
    $new_status = 'not_departed';
}

if ($new_status !== 'cancelled') {
    $_SESSION['error'] = 'Trip movement is controlled by the assigned driver.';
    header('Location: ../../views/admin/schedules.php');
    exit;
}

// -- Transition guard ----------------------------------------------------------
$schedule     = new Schedules($conn);
$schedule->id = $schedule_id;

if ($schedule->HasPendingBookings()) {
    $_SESSION['error'] = 'Resolve pending bookings before changing this schedule status.';
    header('Location: ../../views/admin/schedules.php');
    exit;
}

if (!$schedule->canUpdateStatus($new_status)) {
    $_SESSION['error'] = 'Invalid status transition.';
    header('Location: ../../views/admin/schedules.php');
    exit;
}

// -- Update --------------------------------------------------------------------
$schedule->trip_status = $new_status;
$result = $schedule->UpdateStatus();

$_SESSION[$result['success'] ? 'success' : 'error'] = $result['success']
    ? 'Status updated successfully.'
    : ($result['message'] ?? 'Unable to update schedule status. Please try again.');

header('Location: ../../views/admin/schedules.php');
exit;
?>
