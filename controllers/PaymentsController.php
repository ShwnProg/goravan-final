<?php
ob_start();
require_once __DIR__ . '/../autoload.php';

header('Content-Type: application/json');

function payment_json(array $payload): void
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
        payment_json(['success' => false, 'message' => 'Unauthorized.']);
    }

    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $payObj = new Payments($conn);
        $payments = array_map(function ($p) {
            $p['payment_id_pk'] = encrypt((string) $p['payment_id_pk']);
            return $p;
        }, $payObj->GetAllPayments());

        payment_json(['success' => true, 'data' => $payments]);
    }

    if ($action === 'get') {
        $paymentId = (int) decrypt(trim($_GET['payment_id'] ?? ''));
        if (!$paymentId) {
            payment_json(['success' => false, 'message' => 'Invalid payment ID.']);
        }

        $payObj = new Payments($conn);
        $payObj->id = $paymentId;
        $payment = $payObj->GetPaymentByID();

        if (empty($payment)) {
            payment_json(['success' => false, 'message' => 'Payment not found.']);
        }

        $payment['payment_id_pk'] = encrypt((string) $payment['payment_id_pk']);
        payment_json(['success' => true, 'data' => $payment]);
    }

    if ($action === 'update_status') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
            payment_json(['success' => false, 'message' => 'Invalid request or CSRF token.']);
        }

        $paymentId = (int) decrypt(trim($_POST['payment_id'] ?? ''));
        $status = strtolower(trim($_POST['status'] ?? ''));

        if (!$paymentId || !in_array($status, ['unpaid', 'pending', 'paid', 'failed', 'cancelled', 'refund_requested', 'refunded', 'pending_cash', 'cash_unpaid'], true)) {
            payment_json(['success' => false, 'message' => 'Invalid payment update.']);
        }

        $payObj = new Payments($conn);
        $payObj->id = $paymentId;
        $result = $payObj->UpdateStatus($status);

        payment_json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Payment status updated.' : ($result['message'] ?? 'Unable to update payment.'),
        ]);
    }

    if ($action === 'review_refund') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
            payment_json(['success' => false, 'message' => 'Invalid request or CSRF token.']);
        }

        $paymentId = (int) decrypt(trim($_POST['payment_id'] ?? ''));
        $decision = strtolower(trim($_POST['decision'] ?? ''));
        $reason = strtolower(trim($_POST['reason'] ?? ''));
        $customNote = trim($_POST['custom_note'] ?? '');
        $allowedReasons = ['valid_request', 'duplicate_payment', 'schedule_issue', 'policy_not_met', 'other'];

        if (!$paymentId || !in_array($decision, ['approve', 'reject'], true) || !in_array($reason, $allowedReasons, true)) {
            payment_json(['success' => false, 'message' => 'Invalid refund review.']);
        }

        $payObj = new Payments($conn);
        $payObj->id = $paymentId;
        $adminId = (int) decrypt($_SESSION['id'] ?? '');
        $result = $payObj->ReviewRefund($decision, $reason, $customNote, $adminId ?: null);

        payment_json([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Refund review saved.' : ($result['message'] ?? 'Unable to review refund.'),
            'status' => $decision === 'approve' ? 'refunded' : 'paid',
        ]);
    }

    payment_json(['success' => false, 'message' => 'Invalid action.']);
} catch (Throwable $e) {
    error_log('[PaymentsController] ' . $e->getMessage());
    payment_json(['success' => false, 'message' => 'Payment request failed. Please try again.']);
}
