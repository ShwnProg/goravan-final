<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

function booking_response(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

if (empty($_SESSION['is_login'])) {
    booking_response(false, 'Unauthorized.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    booking_response(false, 'Invalid request method.');
}

if (!csrf_check()) {
    booking_response(false, 'Invalid or expired CSRF token. Please refresh and try again.');
}

$userId = (int) decrypt($_SESSION['id']);
$rawScheduleId = trim($_POST['schedule_id'] ?? '');
$scheduleId = (int) decrypt($rawScheduleId);
if (!$scheduleId && ctype_digit($rawScheduleId)) {
    $scheduleId = (int) $rawScheduleId;
}

$rawSeatIds = $_POST['seat_ids'] ?? [];
if (!is_array($rawSeatIds)) {
    $rawSeatIds = [$rawSeatIds];
}

$seatIds = [];
foreach ($rawSeatIds as $rawSeatId) {
    $seatId = (int) decrypt(trim((string) $rawSeatId));
    if (!$seatId && ctype_digit((string) $rawSeatId)) {
        $seatId = (int) $rawSeatId;
    }
    if ($seatId) {
        $seatIds[] = $seatId;
    }
}
$seatIds = array_values(array_unique($seatIds));

$passengerName = trim($_POST['passenger_name'] ?? '');
$contactNumber = preg_replace('/\D+/', '', $_POST['contact_number'] ?? '');
$passengerType = strtolower(trim($_POST['passenger_type'] ?? 'regular'));
$passengerNames = $_POST['passenger_names'] ?? [];
$passengerTypes = $_POST['passenger_types'] ?? [];
$paymentMethod = strtolower(trim($_POST['payment_method'] ?? ''));
$paymentReference = trim($_POST['payment_reference'] ?? '');
$seatsCount = (int) ($_POST['seats_count'] ?? 0);
$clientTotal = (float) ($_POST['total_amount'] ?? 0);

$allowedPassengerTypes = ['regular', 'student', 'senior', 'pwd'];
$allowedPaymentMethods = ['gcash', 'paymaya', 'card', 'cash'];

if (!$userId || !$scheduleId) {
    booking_response(false, 'Invalid booking request.');
}

if (empty($seatIds) || $seatsCount !== count($seatIds)) {
    booking_response(false, 'Please select at least one valid seat.');
}

if (strlen($passengerName) < 2) {
    booking_response(false, 'Passenger name is required.');
}

if (!preg_match('/^09\d{9}$/', $contactNumber)) {
    booking_response(false, 'Please enter a valid Philippine mobile number.');
}

if (!in_array($passengerType, $allowedPassengerTypes, true)) {
    booking_response(false, 'Invalid passenger type.');
}

if (!is_array($passengerNames)) {
    $passengerNames = [$passengerNames];
}
if (!is_array($passengerTypes)) {
    $passengerTypes = [$passengerTypes];
}

$passengerNames = array_values(array_map('trim', $passengerNames));
$passengerTypes = array_values(array_map(fn($type) => strtolower(trim((string) $type)), $passengerTypes));
foreach ($passengerTypes as $type) {
    if (!in_array($type, $allowedPassengerTypes, true)) {
        booking_response(false, 'Invalid passenger type.');
    }
}

$verifiedPassengerType = '';
$verif = new Verification($conn);
$verif->user_id_fk = $userId;
$approvedVerification = $verif->GetApprovedVerification();
if ($approvedVerification) {
    $verifiedPassengerType = strtolower((string) ($approvedVerification['document_type'] ?? ''));
    if (!in_array($verifiedPassengerType, ['student', 'senior', 'pwd'], true)) {
        $verifiedPassengerType = '';
    }
}

$passengerTypes = array_slice(array_pad($passengerTypes, count($seatIds), 'regular'), 0, count($seatIds));
$passengerNames = array_slice(array_pad($passengerNames, count($seatIds), $passengerName), 0, count($seatIds));

// The account owner is the first selected seat. Companions can declare their own type.
$mainPassengerType = $verifiedPassengerType ?: 'regular';
$passengerTypes[0] = $mainPassengerType;
$passengerType = $mainPassengerType;

foreach ($passengerTypes as $index => $type) {
    if (!in_array($type, $allowedPassengerTypes, true)) {
        $passengerTypes[$index] = 'regular';
    }
}

if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    booking_response(false, 'Invalid payment method.');
}

if ($paymentMethod !== 'cash' && $paymentReference === '') {
    booking_response(false, 'Payment reference is required.');
}

if ($paymentMethod === 'cash') {
    $paymentReference = 'cash-on-site';
}

$bookings = new Bookings($conn);
$result = $bookings->CreateUserBookingWithPayment([
    'user_id' => $userId,
    'schedule_id' => $scheduleId,
    'seat_ids' => $seatIds,
    'passenger_name' => $passengerName,
    'passenger_names' => $passengerNames,
    'contact_number' => $contactNumber,
    'passenger_type' => $passengerType,
    'passenger_types' => $passengerTypes,
    'payment_method' => $paymentMethod,
    'payment_reference' => $paymentReference,
    'total_amount' => $clientTotal,
]);

if (!$result['success']) {
    booking_response(false, $result['message'] ?? 'Unable to complete booking right now.');
}

booking_response(true, $result['message'], [
    'reference_code' => $result['reference_code'],
    'booking_id' => encrypt((string) $result['booking_id']),
    'total_amount' => $result['total_amount'],
    'seats_count' => (int) ($result['seats_count'] ?? count($seatIds)),
]);
