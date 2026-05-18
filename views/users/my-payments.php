<?php
require_once '../../autoload.php';

if (!isset($_SESSION['is_login'])) {
    header('Location: ../auth/login.php');
    exit;
}

ob_start();
$title = 'Payments';
$active_page = 'payments';
$page_css = '../../assets/css/user-payments.css';
$page_js = '../../assets/js/user-payments.js';
$userId = (int) decrypt($_SESSION['id']);
?>

<div class="u-body">
    <input type="hidden" id="paymentsUserId" value="<?= htmlspecialchars(encrypt((string) $userId)) ?>">
    <input type="hidden" id="paymentsCsrfToken" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">

    <section class="pay-header-card">
        <div>
            <span class="pay-eyebrow">Payment History</span>
            <h1>Receipts</h1>
        </div>
        <?= vanny_mascot('phoneBooking', 'medium', 'pay-header-vanny', 'Vanny with mobile booking') ?>
        <div class="pay-stat-grid">
            <div class="pay-stat">
                <span>Total spent</span>
                <strong id="totalSpent">&#8369;0.00</strong>
            </div>
            <div class="pay-stat">
                <span>Trips paid</span>
                <strong id="totalTrips">0</strong>
            </div>
            <div class="pay-stat">
                <span>Last payment</span>
                <strong id="lastPayment">-</strong>
            </div>
        </div>
    </section>

    <section class="pay-filter-bar" aria-label="Payment filters">
        <label class="pay-field pay-field-search" for="paymentSearch">
            <span>Search</span>
            <div class="pay-search">
                <i class="fa-solid fa-search"></i>
                <input type="search" id="paymentSearch" placeholder="Reference, route, method">
            </div>
        </label>
        <label class="pay-field" for="paymentStatusFilter">
            <span>Status</span>
            <select id="paymentStatusFilter">
                <option value="">All</option>
                <option value="pending">Pending</option>
                <option value="paid">Paid</option>
                <option value="refund_requested">Refund Requested</option>
                <option value="refunded">Refunded</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </label>
        <label class="pay-field" for="paymentDateFrom">
            <span>Date from</span>
            <input type="date" id="paymentDateFrom">
        </label>
        <label class="pay-field" for="paymentDateTo">
            <span>Date to</span>
            <input type="date" id="paymentDateTo">
        </label>
        <button type="button" class="pay-clear-filter" id="paymentClearFilters">
            <i class="fa-solid fa-rotate-left"></i>
            <span>Clear</span>
        </button>
    </section>
    <div class="pay-filter-feedback" id="paymentFilterFeedback" aria-live="polite"></div>

    <section class="payment-list" id="paymentList">
        <div class="payment-empty">
            <?= vanny_mascot('running', 'medium', 'payment-empty-vanny', 'Vanny is loading payments') ?>
            <p>Vanny is loading payments...</p>
        </div>
    </section>
</div>

<div class="modal fade" id="paymentDetailModal" tabindex="-1" aria-labelledby="paymentDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content pay-modal">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="paymentDetailTitle">Payment Receipt</h5>
                    <span class="modal-subtitle">Complete booking and payment details.</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="receipt-print" id="receiptPrintArea">
                    <div class="receipt-head">
                        <img src="../../images/logo.png"
                             alt="GoraVan"
                             class="receipt-logo"
                             data-light-logo="../../images/logo.png"
                             data-dark-logo="../../images/logo_white.png">
                        <div>
                            <strong>GoraVan</strong>
                            <span>Official Receipt</span>
                        </div>
                    </div>
                    <div class="receipt-ref-line">
                        <span>Reference</span>
                        <strong id="detailReference">-</strong>
                    </div>
                    <div class="receipt-lines">
                        <div><span>Route</span><strong id="detailRoute">-</strong></div>
                        <div><span>Date & time</span><strong id="detailDate">-</strong></div>
                        <div><span>Seats</span><strong id="detailSeats">-</strong></div>
                        <div><span>Passenger</span><strong id="detailPassenger">-</strong></div>
                        <div><span>Passenger type</span><strong id="detailPassengerType">-</strong></div>
                        <div><span>Payment method</span><strong id="detailMethod">-</strong></div>
                        <div><span>Payment reference</span><strong id="detailPaymentRef">-</strong></div>
                        <div><span>Status</span><strong id="detailStatus">-</strong></div>
                        <div class="receipt-total"><span>Amount</span><strong id="detailAmount">&#8369;0.00</strong></div>
                    </div>
                    <div class="receipt-refund-notes" id="detailRefundNotes" hidden></div>
                </div>
                <div class="pay-modal-actions">
                    <button type="button" class="u-btn u-btn-secondary refund-trigger" id="requestRefundBtn" hidden>
                        <i class="fa-solid fa-rotate-left"></i> Request Refund
                    </button>
                    <button type="button" class="u-btn u-btn-secondary cancel-refund-trigger" id="cancelRefundRequestBtn" hidden>
                        <i class="fa-solid fa-ban"></i> Cancel Refund Request
                    </button>
                    <button type="button" class="u-btn u-btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="u-btn u-btn-primary" id="downloadReceiptBtn">
                        <i class="fa-solid fa-download"></i> Download Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="refundRequestModal" tabindex="-1" aria-labelledby="refundRequestTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pay-modal refund-modal">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="refundRequestTitle">Request Refund</h5>
                    <span class="modal-subtitle">Tell us why this booking should be refunded.</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="refundPaymentId" value="">
                <div class="pay-form-group">
                    <label for="refundReason">Reason</label>
                    <select id="refundReason">
                        <option value="change_of_plans">Change of plans</option>
                        <option value="duplicate_booking">Duplicate booking</option>
                        <option value="schedule_issue">Schedule issue</option>
                        <option value="payment_issue">Payment issue</option>
                        <option value="other">Other reason</option>
                    </select>
                </div>
                <div class="pay-form-group">
                    <label for="refundCustomNote">Additional note</label>
                    <textarea id="refundCustomNote" rows="4" placeholder="Optional details for admin review"></textarea>
                </div>
                <div class="pay-modal-actions">
                    <button type="button" class="u-btn u-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="u-btn u-btn-primary" id="submitRefundRequestBtn">
                        <i class="fa-solid fa-paper-plane"></i> Submit Request
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout/user_layout.php';
?>
