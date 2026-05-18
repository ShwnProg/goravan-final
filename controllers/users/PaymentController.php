<?php
ob_start();
require_once '../../autoload.php';

header('Content-Type: application/json');

function user_payment_json(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

try {
    if (empty($_SESSION['is_login'])) {
        user_payment_json(['success' => false, 'message' => 'Unauthorized.']);
    }

    $action = $_GET['action'] ?? 'list';
    $sessionUserId = (int) decrypt($_SESSION['id']);
    $requestedUser = 0;
    $requestedUserToken = trim($_GET['user_id'] ?? '');

    if ($requestedUserToken !== '') {
        $requestedUser = (int) decrypt($requestedUserToken);
    }

    if ($action === 'list' && $requestedUser && $requestedUser !== $sessionUserId) {
        user_payment_json(['success' => false, 'message' => 'You can only view your own payments.']);
    }

    $payObj = new Payments($conn);

    if ($action === 'request_refund') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
            user_payment_json(['success' => false, 'message' => 'Invalid request or CSRF token.']);
        }

        $paymentId = (int) decrypt(trim($_POST['payment_id'] ?? ''));
        $reason = strtolower(trim($_POST['reason'] ?? ''));
        $customNote = trim($_POST['custom_note'] ?? '');
        $allowedReasons = ['change_of_plans', 'duplicate_booking', 'schedule_issue', 'payment_issue', 'other'];

        if (!$paymentId || !in_array($reason, $allowedReasons, true)) {
            user_payment_json(['success' => false, 'message' => 'Invalid refund request.']);
        }

        $result = $payObj->RequestRefund($paymentId, $sessionUserId, $reason, $customNote);
        user_payment_json($result);
    }

    if ($action === 'cancel_refund') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
            user_payment_json(['success' => false, 'message' => 'Invalid request or CSRF token.']);
        }

        $paymentId = (int) decrypt(trim($_POST['payment_id'] ?? ''));

        if (!$paymentId) {
            user_payment_json(['success' => false, 'message' => 'Invalid refund cancellation.']);
        }

        $result = $payObj->CancelRefundRequest($paymentId, $sessionUserId);
        user_payment_json($result);
    }

    if ($action !== 'list') {
        user_payment_json(['success' => false, 'message' => 'Invalid action.']);
    }

    $payments = $payObj->GetPaymentsByUser($sessionUserId);

    $data = array_map(function ($row) {
        return [
            'payment_id' => encrypt((string) $row['payment_id_pk']),
            'booking_id' => encrypt((string) $row['book_id_pk']),
            'reference_code' => $row['reference_code'],
            'booking_status' => $row['booking_status'],
            'route_display' => $row['route_display'],
            'origin' => $row['origin'],
            'destination' => $row['destination'],
            'departure_date' => $row['departure_date'],
            'departure_time' => $row['departure_time'],
            'seats_count' => $row['seats_count'],
            'seat_numbers' => $row['seat_numbers'],
            'passenger_name' => $row['passenger_name'],
            'contact_number' => $row['contact_number'],
            'passenger_type' => $row['passenger_type'],
            'passengers' => $row['passengers'],
            'discount_rate' => $row['discount_rate'],
            'discount_amount' => $row['discount_amount'],
            'convenience_fee' => $row['convenience_fee'],
            'cash_fee' => $row['cash_fee'] ?? 0,
            'base_total' => $row['base_total'] ?? 0,
            'subtotal' => $row['subtotal'] ?? 0,
            'amount' => $row['amount'],
            'payment_method' => $row['payment_method'],
            'payment_reference' => $row['payment_reference'],
            'status' => $row['status'],
            'paid_at' => $row['paid_at'],
            'created_at' => $row['created_at'],
            'processed_by' => $row['processed_by'],
            'processed_by_name' => $row['processed_by_name'],
            'notes' => $row['notes'],
        ];
    }, $payments);

    user_payment_json(['success' => true, 'data' => $data]);
} catch (Throwable $e) {
    error_log('[UserPaymentController] ' . $e->getMessage());
    user_payment_json(['success' => false, 'message' => 'Payment request failed. Please try again.']);
}
