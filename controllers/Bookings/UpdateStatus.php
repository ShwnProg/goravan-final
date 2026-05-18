<?php
require_once '../../autoload.php';

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function booking_response(bool $success, string $message, array $extra = []): void
{
    if (wants_json()) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
        exit;
    }

    $_SESSION[$success ? 'success' : 'error'] = $message;
    header('Location: ../../views/admin/bookings.php');
    exit;
}

if (!csrf_check()) {
    booking_response(false, 'Invalid or expired CSRF token. Please refresh and try again.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../views/admin/bookings.php');
    exit;
}

$booking_id = (int) ($_POST['booking_id'] ?? 0);
$new_status = trim(strtolower($_POST['status'] ?? ''));
$allowed = ['approved', 'rejected', 'cancelled'];

if (!$booking_id || !in_array($new_status, $allowed, true)) {
    booking_response(false, 'Invalid booking ID or status.');
}

$bookingObj = new Bookings($conn);
$rows = $bookingObj->GetBookingGroupByID($booking_id);

if (empty($rows)) {
    booking_response(false, 'Booking not found.');
}

$referenceCode = $rows[0]['reference_code'];
$currentStatuses = array_values(array_unique(array_column($rows, 'status')));
$isAllPending = count($currentStatuses) === 1 && $currentStatuses[0] === 'pending';
$isApprovable = count(array_diff($currentStatuses, ['pending', 'approved'])) === 0;
$isCancellable = count(array_diff($currentStatuses, ['pending', 'approved'])) === 0;

if ($new_status === 'approved' && !$isApprovable) {
    booking_response(false, 'Only pending or partially approved bookings can be approved.');
}

if ($new_status === 'rejected' && !$isAllPending) {
    booking_response(false, 'Only pending bookings can be rejected.');
}

if ($new_status === 'cancelled' && !$isCancellable) {
    booking_response(false, 'This booking cannot be cancelled.');
}

$bookingObj->status = $new_status;
$result = $bookingObj->UpdateStatusByReferenceCode($referenceCode);

if (!$result['success']) {
    booking_response(false, 'Unable to update booking status. Please try again.');
}

$message = 'Booking status updated successfully. Updated ' . count($rows) . ' seat(s).';
booking_response(true, $message, ['status' => $new_status, 'reference_code' => $referenceCode]);
