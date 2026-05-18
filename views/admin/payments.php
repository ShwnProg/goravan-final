<?php
require_once "../../autoload.php";

if (empty($_SESSION['is_login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$title   = 'Payments';
$page_js = '../../assets/js/payments-js.js';

ob_start();

$payObj   = new Payments($conn);
$payments = $payObj->GetAllPayments();

$paymentDates = array_values(array_unique(array_filter(array_map(
    fn($p) => !empty($p['paid_at'])
        ? substr((string) $p['paid_at'], 0, 10)
        : (!empty($p['created_at']) ? substr((string) $p['created_at'], 0, 10) : ''),
    $payments
))));
rsort($paymentDates);

$paymentDisplayStatus = function (array $payment): string {
    $status = strtolower((string) ($payment['status'] ?? 'pending'));
    $bookingStatus = strtolower((string) ($payment['booking_status'] ?? ''));

    return $bookingStatus === 'rejected' ? 'rejected' : $status;
};

$paymentGroupMeta = function (string $displayStatus): array {
    $groups = [
        'pending' => ['key' => 'pending', 'label' => 'Pending Payments', 'icon' => 'fas fa-clock', 'hint' => 'Waiting for approval'],
        'pending_cash' => ['key' => 'pending', 'label' => 'Pending Cash Payments', 'icon' => 'fas fa-money-bill-1-wave', 'hint' => 'Pay on-site'],
        'cash_unpaid' => ['key' => 'pending', 'label' => 'Pending Cash Payments', 'icon' => 'fas fa-money-bill-1-wave', 'hint' => 'Pay on-site'],
        'unpaid' => ['key' => 'pending', 'label' => 'Unpaid Payments', 'icon' => 'fas fa-clock', 'hint' => 'Waiting for payment'],
        'failed' => ['key' => 'failed', 'label' => 'Failed Payments', 'icon' => 'fas fa-triangle-exclamation', 'hint' => 'Payment failed'],
        'paid' => ['key' => 'paid', 'label' => 'Paid Payments', 'icon' => 'fas fa-circle-check', 'hint' => 'Completed payments'],
        'rejected' => ['key' => 'rejected', 'label' => 'Rejected Payments', 'icon' => 'fas fa-circle-xmark', 'hint' => 'Booking rejected'],
        'refund_requested' => ['key' => 'refund', 'label' => 'Refund Requests', 'icon' => 'fas fa-rotate-left', 'hint' => 'Needs admin review'],
        'cancelled' => ['key' => 'cancelled', 'label' => 'Cancelled Payments', 'icon' => 'fas fa-ban', 'hint' => 'Inactive payments'],
        'refunded' => ['key' => 'refunded', 'label' => 'Refunded Payments', 'icon' => 'fas fa-receipt', 'hint' => 'Refund completed'],
    ];

    return $groups[$displayStatus] ?? ['key' => 'other', 'label' => 'Other Payments', 'icon' => 'fas fa-credit-card', 'hint' => 'Other statuses'];
};
?>

<div class="toolbar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="payment-search" placeholder="Search payments…">
    </div>
    <div class="admin-date-filters" data-filter-scope="payments">
        <label>
            <span>Method</span>
            <select id="payment-method-filter">
                <option value="">All methods</option>
                <option value="gcash">GCash</option>
                <option value="paymaya">PayMaya</option>
                <option value="card">Card</option>
                <option value="cash">Cash</option>
            </select>
        </label>
        <label><span>From</span><input type="date" id="payment-date-from"></label>
        <label><span>To</span><input type="date" id="payment-date-to"></label>
        <button type="button" class="filter-btn ghost" id="payment-date-clear">Clear</button>
    </div>
</div>
<div class="admin-status-tabs" id="payment-status-tabs" aria-label="Payment status filters">
    <button type="button" class="active" data-status="pending">Pending Payments</button>
    <button type="button" data-status="pending_cash">Cash Pending</button>
    <button type="button" data-status="paid">Paid Payments</button>
    <button type="button" data-status="refund_requested">Refund Requests</button>
    <button type="button" data-status="refunded">Refunded Payments</button>
    <button type="button" data-status="cancelled">Cancelled Payments</button>
    <button type="button" data-status="rejected">Rejected Payments</button>
    <button type="button" data-status="">All Payments</button>
</div>
<input type="hidden" id="payment-status-filter" value="pending">

<input type="hidden" id="page-csrf-token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">

<div class="payments-card">
    <div class="payments-card-header">
        <h2>
            <i class="fas fa-credit-card" style="margin-right:7px;color:var(--color-accent)"></i>
            <span id="payment-view-title">Pending Payments</span>
        </h2>
        <span id="payment-count"></span>
    </div>
    <div class="payments-table-wrap">
        <table class="payments-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Booking Ref</th>
                    <th>Passenger</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Paid At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="payments-tbody">
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i class="fas fa-credit-card"></i>
                                <p>No payments yet.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $currentGroup = '';
                    $paymentIndex = 0;
                    foreach ($payments as $p):
                        $status        = strtolower((string) ($p['status'] ?? 'pending'));
                        $displayStatus = $paymentDisplayStatus($p);
                        $group         = $paymentGroupMeta($displayStatus);
                        $encId         = encrypt((string) $p['payment_id_pk']);
                        $filterDate    = !empty($p['paid_at']) ? $p['paid_at'] : ($p['created_at'] ?? '');

                        if ($currentGroup !== $group['key']):
                            $currentGroup = $group['key'];
                    ?>
                        <tr class="payment-group-row" data-group-key="<?= htmlspecialchars($group['key'], ENT_QUOTES) ?>">
                            <td colspan="9">
                                <div class="payment-group-label">
                                    <i class="<?= htmlspecialchars($group['icon'], ENT_QUOTES) ?>"></i>
                                    <span><?= htmlspecialchars($group['label']) ?></span>
                                    <small><?= htmlspecialchars($group['hint']) ?></small>
                                </div>
                            </td>
                        </tr>
                    <?php
                        endif;
                        $paymentIndex++;
                    ?>
                        <tr class="payment-row status-<?= htmlspecialchars($displayStatus, ENT_QUOTES) ?>"
                            data-id="<?= htmlspecialchars($encId, ENT_QUOTES) ?>"
                            data-group-key="<?= htmlspecialchars($group['key'], ENT_QUOTES) ?>"
                            data-booking-ref="<?= htmlspecialchars($p['booking_ref'] ?? 'N/A', ENT_QUOTES) ?>"
                            data-booking-status="<?= htmlspecialchars($p['booking_status'] ?? '', ENT_QUOTES) ?>"
                            data-user-name="<?= htmlspecialchars($p['user_name'] ?? 'N/A', ENT_QUOTES) ?>"
                            data-user-email="<?= htmlspecialchars($p['user_email'] ?? '', ENT_QUOTES) ?>"
                            data-user-phone="<?= htmlspecialchars($p['user_phone'] ?? '', ENT_QUOTES) ?>"
                            data-amount="<?= number_format((float) $p['amount'], 2, '.', '') ?>"
                            data-method="<?= htmlspecialchars($p['payment_method'] ?? 'N/A', ENT_QUOTES) ?>"
                            data-ref="<?= htmlspecialchars($p['payment_reference'] ?? 'N/A', ENT_QUOTES) ?>"
                            data-status="<?= htmlspecialchars($displayStatus, ENT_QUOTES) ?>"
                            data-payment-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>"
                            data-processed-by="<?= htmlspecialchars($p['processed_by_name'] ?? '', ENT_QUOTES) ?>"
                            data-seats-count="<?= (int) ($p['seats_count'] ?? 1) ?>"
                            data-seat-numbers="<?= htmlspecialchars($p['seat_numbers'] ?? '', ENT_QUOTES) ?>"
                            data-paid-at="<?= htmlspecialchars($p['paid_at'] ?? '', ENT_QUOTES) ?>"
                            data-created="<?= htmlspecialchars($p['created_at'] ?? '', ENT_QUOTES) ?>"
                            data-filter-date="<?= htmlspecialchars($filterDate, ENT_QUOTES) ?>"
                            data-notes="<?= htmlspecialchars($p['notes'] ?? '', ENT_QUOTES) ?>"
                            data-route="<?= htmlspecialchars($p['route_display'] ?? 'N/A', ENT_QUOTES) ?>">

                            <td class="text-muted-sm"><?= $paymentIndex ?></td>

                            <td>
                                <div class="booking-ref-display">
                                    <i class="fas fa-ticket-alt" style="color:#9ca3af;font-size:11px"></i>
                                    <span>
                                        <span class="ref-code"><?= htmlspecialchars($p['booking_ref'] ?? 'N/A') ?></span>
                                        <small><?= (int) ($p['seats_count'] ?? 1) ?> seat<?= (int) ($p['seats_count'] ?? 1) === 1 ? '' : 's' ?></small>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="user-info">
                                    <span class="name"><?= htmlspecialchars($p['user_name'] ?? 'Unknown') ?></span>
                                    <span class="email text-muted-sm"><?= htmlspecialchars($p['user_email'] ?? '') ?></span>
                                </div>
                            </td>

                            <td>
                                <span class="amount-display">
                                    <i class="fas fa-peso-sign" style="font-size:11px;color:var(--color-accent)"></i>
                                    <?= number_format((float) $p['amount'], 2) ?>
                                </span>
                            </td>

                            <td>
                                <span class="payment-method-badge">
                                    <?= ucfirst(htmlspecialchars($p['payment_method'] ?? 'N/A')) ?>
                                </span>
                            </td>

                            <td>
                                <span class="text-muted-sm" title="<?= htmlspecialchars($p['payment_reference'] ?? '') ?>">
                                    <?= htmlspecialchars(mb_substr($p['payment_reference'] ?? 'N/A', 0, 15)) ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?= htmlspecialchars($displayStatus) ?>">
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $displayStatus))) ?>
                                </span>
                            </td>

                            <td class="text-muted-sm">
                                <?= $p['paid_at'] ? date('M d, Y g:i A', strtotime($p['paid_at'])) : '—' ?>
                            </td>

                            <td>
                                <div class="row-actions">
                                    <button class="icon-btn view" title="View Details"
                                        data-id="<?= htmlspecialchars($encId, ENT_QUOTES) ?>"
                                        data-booking-ref="<?= htmlspecialchars($p['booking_ref'] ?? 'N/A', ENT_QUOTES) ?>"
                                        data-booking-status="<?= htmlspecialchars($p['booking_status'] ?? '', ENT_QUOTES) ?>"
                                        data-user-name="<?= htmlspecialchars($p['user_name'] ?? 'N/A', ENT_QUOTES) ?>"
                                        data-user-email="<?= htmlspecialchars($p['user_email'] ?? '', ENT_QUOTES) ?>"
                                        data-user-phone="<?= htmlspecialchars($p['user_phone'] ?? '', ENT_QUOTES) ?>"
                                        data-amount="<?= number_format((float) $p['amount'], 2, '.', '') ?>"
                                        data-method="<?= htmlspecialchars($p['payment_method'] ?? 'N/A', ENT_QUOTES) ?>"
                                        data-ref="<?= htmlspecialchars($p['payment_reference'] ?? 'N/A', ENT_QUOTES) ?>"
                                        data-status="<?= htmlspecialchars($displayStatus, ENT_QUOTES) ?>"
                                        data-payment-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>"
                                        data-processed-by="<?= htmlspecialchars($p['processed_by_name'] ?? '', ENT_QUOTES) ?>"
                                        data-seats-count="<?= (int) ($p['seats_count'] ?? 1) ?>"
                                        data-seat-numbers="<?= htmlspecialchars($p['seat_numbers'] ?? '', ENT_QUOTES) ?>"
                                        data-paid-at="<?= htmlspecialchars($p['paid_at'] ?? '', ENT_QUOTES) ?>"
                                        data-created="<?= htmlspecialchars($p['created_at'] ?? '', ENT_QUOTES) ?>"
                                        data-notes="<?= htmlspecialchars($p['notes'] ?? '', ENT_QUOTES) ?>"
                                        data-route="<?= htmlspecialchars($p['route_display'] ?? 'N/A', ENT_QUOTES) ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($status === 'refund_requested'): ?>
                                        <button class="icon-btn refund-review" title="Review Refund"
                                            data-id="<?= htmlspecialchars($encId, ENT_QUOTES) ?>"
                                            data-booking-ref="<?= htmlspecialchars($p['booking_ref'] ?? 'N/A', ENT_QUOTES) ?>"
                                            data-user-name="<?= htmlspecialchars($p['user_name'] ?? 'N/A', ENT_QUOTES) ?>"
                                            data-amount="<?= number_format((float) $p['amount'], 2, '.', '') ?>"
                                            data-notes="<?= htmlspecialchars($p['notes'] ?? '', ENT_QUOTES) ?>">
                                            <i class="fas fa-rotate-left"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (in_array($status, ['pending_cash', 'cash_unpaid', 'unpaid', 'pending'], true)): ?>
                                        <button class="icon-btn paid" title="Mark as Paid"
                                            data-id="<?= htmlspecialchars($encId, ENT_QUOTES) ?>"
                                            data-method="<?= htmlspecialchars($p['payment_method'] ?? '', ENT_QUOTES) ?>">
                                            <i class="fas fa-circle-check"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="refundReviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rmodal">
            <div class="rmodal-header">
                <div class="rmodal-icon"><i class="fas fa-rotate-left"></i></div>
                <div>
                    <h6 class="rmodal-title">Review Refund</h6>
                    <p class="rmodal-sub" id="refund-review-sub">Refund request details</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="rmodal-body">
                <input type="hidden" id="refund-review-payment-id" value="">
                <div class="refund-request-box" id="refund-request-summary">No refund note found.</div>
                <div class="refund-review-grid">
                    <label>
                        <span>Decision</span>
                        <select id="refund-review-decision">
                            <option value="approve">Approve refund</option>
                            <option value="reject">Reject refund</option>
                        </select>
                    </label>
                    <label>
                        <span>Admin note</span>
                        <select id="refund-review-reason">
                            <option value="valid_request">Valid refund request</option>
                            <option value="duplicate_payment">Duplicate payment</option>
                            <option value="schedule_issue">Schedule issue</option>
                            <option value="policy_not_met">Policy not met</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label class="span-full">
                        <span>Custom note</span>
                        <textarea id="refund-review-custom" rows="4" placeholder="Optional internal note"></textarea>
                    </label>
                </div>
            </div>
            <div class="rmodal-footer">
                <button type="button" class="rbtn rbtn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rbtn rbtn-primary" id="submit-refund-review">Save Review</button>
            </div>
        </div>
    </div>
</div>

<!-- VIEW PAYMENT DETAILS MODAL (read-only) -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content rmodal">
            <div class="rmodal-header">
                <div class="rmodal-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <div>
                    <h6 class="rmodal-title">Payment Details</h6>
                    <p class="rmodal-sub">Transaction information</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="rmodal-body">
                <div class="payment-details-viewer">

                    <div class="pdv-section">
                        <h6 class="pdv-section-title">Booking Information</h6>
                        <div class="pdv-info-grid">
                            <div class="pdv-info-item">
                                <span class="pdv-label">Booking Reference</span>
                                <span class="pdv-value" id="view-booking-ref">—</span>
                            </div>
                            <div class="pdv-info-item">
                                <span class="pdv-label">Route</span>
                                <span class="pdv-value" id="view-route">—</span>
                            </div>
                        </div>
                    </div>

                    <div class="pdv-section">
                        <h6 class="pdv-section-title">Passenger Information</h6>
                        <div class="pdv-info-grid">
                            <div class="pdv-info-item">
                                <span class="pdv-label">Name</span>
                                <span class="pdv-value" id="view-user-name">—</span>
                            </div>
                            <div class="pdv-info-item">
                                <span class="pdv-label">Email</span>
                                <span class="pdv-value" id="view-user-email">—</span>
                            </div>
                            <div class="pdv-info-item">
                                <span class="pdv-label">Phone</span>
                                <span class="pdv-value" id="view-user-phone">—</span>
                            </div>
                        </div>
                    </div>

                    <div class="pdv-section">
                        <h6 class="pdv-section-title">Payment Information</h6>
                        <div class="pdv-info-grid">
                            <div class="pdv-info-item">
                                <span class="pdv-label">Amount</span>
                                <span class="pdv-value" id="view-amount">—</span>
                            </div>
                            <div class="pdv-info-item">
                                <span class="pdv-label">Method</span>
                                <span class="pdv-value" id="view-method">—</span>
                            </div>
                            <div class="pdv-info-item">
                                <span class="pdv-label">Payment Reference</span>
                                <span class="pdv-value" id="view-payment-ref" style="word-break:break-all">—</span>
                            </div>
                            <div class="pdv-info-item">
                                <span class="pdv-label">Status</span>
                                <span class="pdv-value" id="view-status-badge">—</span>
                            </div>
                        </div>
                    </div>

                    <div class="pdv-section">
                        <h6 class="pdv-section-title">Transaction History</h6>
                        <div class="pdv-info-grid">
                            <div class="pdv-info-item">
                                <span class="pdv-label">Created At</span>
                                <span class="pdv-value" id="view-created">—</span>
                            </div>
                            <div class="pdv-info-item">
                                <span class="pdv-label">Paid At</span>
                                <span class="pdv-value" id="view-paid-at">—</span>
                            </div>
                        </div>
                    </div>

                    <div class="pdv-section">
                        <h6 class="pdv-section-title">Notes</h6>
                        <div class="pdv-notes" id="view-notes">No notes</div>
                    </div>

                </div>
            </div>
            <div class="rmodal-footer">
                <button type="button" class="rbtn rbtn-ghost" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout/admin_layout.php';
?>
