<?php
require_once '../../autoload.php';
if (!isset($_SESSION['is_login'])) { header('Location: ../auth/login.php'); exit; }

ob_start();
$title       = 'Booking Details';
$active_page = 'bookings';
$page_css    = '../../assets/css/user-bookings.css';
$page_js     = '../../assets/js/user-bookings.js';

$id = $_GET['id'] ?? null;
if (!$id) { header('Location: my-bookings.php'); exit; }

$userId = (int) decrypt($_SESSION['id']);
$bookingId = (int) decrypt($id);
if (!$bookingId) { header('Location: my-bookings.php'); exit; }

$bk = new Bookings($conn);
$booking = $bk->GetUserBookingGroupByID($bookingId, $userId);

if (!$booking) { header('Location: my-bookings.php'); exit; }

$scheduleObj = new Schedules($conn);
$scheduleStatus = $scheduleObj->NormalizeTripStatus(strtolower($booking['schedule_status'] ?? ''));
$departureAt = strtotime(trim(($booking['departure_date'] ?? '') . ' ' . ($booking['departure_time'] ?? '')));
$arrivedAt = !empty($booking['arrived_at']) ? strtotime($booking['arrived_at']) : false;
$estimatedArrivalAt = !empty($booking['estimated_arrival_at']) ? strtotime($booking['estimated_arrival_at']) : false;
$validArrivedAt = $arrivedAt && (!$departureAt || $arrivedAt >= $departureAt);
$displayArrivalAt = $validArrivedAt ? $booking['arrived_at'] : '';

$hasArrived = in_array($scheduleStatus, ['arrived', 'completed'], true) && !empty($displayArrivalAt);
$hasEstimatedArrival = !$hasArrived && $estimatedArrivalAt && !in_array($scheduleStatus, ['cancelled'], true);

$paymentNotes = [];
if (!empty($booking['payment_notes'])) {
    $decodedNotes = json_decode((string) $booking['payment_notes'], true);
    $paymentNotes = is_array($decodedNotes) ? $decodedNotes : [];
}
$refundHistory = is_array($paymentNotes['refund_history'] ?? null) ? $paymentNotes['refund_history'] : [];
if (!$refundHistory && !empty($paymentNotes['refund'])) {
    $refundHistory = [$paymentNotes['refund']];
}
$passengers = [];
$passengerSummary = $booking['passenger_name'] ?? '-';
if (!empty($paymentNotes['passengers']) && is_array($paymentNotes['passengers'])) {
    $passengers = $paymentNotes['passengers'];
}
$routeHtml = function (array $row) use ($paymentNotes): string {
    $origin = $row['origin'] ?? ($paymentNotes['route_origin'] ?? '');
    $destination = $row['destination'] ?? ($paymentNotes['route_destination'] ?? '');
    if ($origin || $destination) {
        return htmlspecialchars($origin ?: 'Origin') . ' <i class="fa-solid fa-arrow-right route-arrow-icon"></i> ' . htmlspecialchars($destination ?: 'Destination');
    }
    return htmlspecialchars((string) ($row['route_display'] ?? 'Route unavailable'));
};
$labelType = fn($type) => ucwords(str_replace('_', ' ', (string) ($type ?: 'regular')));
?>

<div class="u-body booking-detail-page">
    <div class="u-back-link">
        <a href="my-bookings.php" class="u-back-btn">
            <i class="fa-solid fa-arrow-left"></i> Back to My Bookings
        </a>
    </div>

    <div class="u-bk-detail u-detail-modern">
        <div class="u-bk-header">
            <div>
                <div class="u-bk-ref"><?= htmlspecialchars($booking['reference_code']) ?></div>
                <div class="u-bk-route"><?= $routeHtml($booking) ?></div>
                <div class="u-bk-subtle">Booked <?= date('M j, Y g:i A', strtotime($booking['created_at'])) ?></div>
            </div>
            <span class="u-badge <?= htmlspecialchars($booking['status']) ?>">
                <?= ucfirst($booking['status']) ?>
            </span>
        </div>

        <div class="u-detail-sections">
            <section class="u-detail-section">
                <h2>Booking Summary</h2>
                <div class="u-detail-grid">
                    <div><span>Reference</span><strong><?= htmlspecialchars($booking['reference_code']) ?></strong></div>
                    <div><span>Booking status</span><strong><?= ucfirst($booking['status']) ?></strong></div>
                    <div><span>Created</span><strong><?= date('M j, Y g:i A', strtotime($booking['created_at'])) ?></strong></div>
                </div>
            </section>

            <section class="u-detail-section">
                <h2>Trip Information</h2>
                <div class="u-detail-grid">
                    <div><span>Route</span><strong><?= $routeHtml($booking) ?></strong></div>
                    <div><span>Departure</span><strong><?= date('M j, Y', strtotime($booking['departure_date'])) ?> &middot; <?= date('g:i A', strtotime($booking['departure_time'])) ?></strong></div>
                    <div><span>Trip status</span><strong><?= htmlspecialchars($scheduleObj->TripStatusLabel($scheduleStatus)) ?></strong></div>
                    <div><span>Van</span><strong><?= htmlspecialchars($booking['van_model']) ?> (<?= htmlspecialchars($booking['van_plate']) ?>)</strong></div>
                    <div><span>Driver</span><strong><?= htmlspecialchars($booking['driver_name'] ?? 'Unassigned') ?></strong></div>
                    <div><span>Fare</span><strong>&#8369;<?= number_format((float) $booking['route_fare'], 2) ?> per seat</strong></div>
                    <?php if ($hasArrived): ?>
                        <div><span>Arrived at</span><strong><?= date('M j, Y g:i A', strtotime($displayArrivalAt)) ?></strong></div>
                    <?php elseif ($hasEstimatedArrival): ?>
                        <div><span>Estimated arrival</span><strong><?= date('M j, Y g:i A', strtotime($booking['estimated_arrival_at'])) ?></strong></div>
                    <?php endif; ?>
                </div>
                <div class="u-detail-seats">
                    <span><?= (int) $booking['seats_count'] ?> seat<?= (int) $booking['seats_count'] === 1 ? '' : 's' ?></span>
                    <?php foreach (explode(',', (string) ($booking['seat_numbers'] ?? '')) as $seatNumber): ?>
                        <?php if (trim($seatNumber) !== ''): ?>
                            <span class="u-seat-chip"><?= htmlspecialchars(trim($seatNumber)) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="u-detail-section">
                <h2>Passenger Information</h2>
                <?php if ($passengers): ?>
                    <div class="u-passenger-group">
                        <div class="u-detail-grid">
                            <div><span>Passenger</span><strong><?= htmlspecialchars($booking['passenger_name'] ?: ($passengers[0]['name'] ?? '-')) ?></strong></div>
                            <div><span>Contact number</span><strong><?= htmlspecialchars($booking['contact_number'] ?? '-') ?></strong></div>
                        </div>
                        <div class="u-seat-type-list">
                            <?php foreach ($passengers as $passenger): ?>
                                <span class="u-seat-type-chip">
                                    <i class="fa-solid fa-chair"></i>
                                    <?= htmlspecialchars($passenger['seat_number'] ?? '-') ?>
                                    <small><?= htmlspecialchars($labelType($passenger['type'] ?? 'regular')) ?></small>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="u-detail-grid">
                        <div><span>Passenger</span><strong><?= htmlspecialchars($passengerSummary) ?></strong></div>
                        <div><span>Passenger type</span><strong><?= ucfirst($booking['passenger_type'] ?? 'regular') ?></strong></div>
                        <div><span>Contact number</span><strong><?= htmlspecialchars($booking['contact_number'] ?? '-') ?></strong></div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="u-detail-section">
                <h2>Payment Information</h2>
                <div class="u-detail-grid">
                    <div><span>Status</span><strong><?= ucfirst($booking['payment_status'] ?? 'pending') ?></strong></div>
                    <div><span>Method</span><strong><?= ucfirst($booking['payment_method'] ?? '-') ?></strong></div>
                    <div><span>Base fare</span><strong>&#8369;<?= number_format((float) ($booking['base_total'] ?? 0), 2) ?></strong></div>
                    <div><span>Discount</span><strong>-&#8369;<?= number_format((float) ($booking['discount_amount'] ?? 0), 2) ?></strong></div>
                    <?php if ((float) ($booking['cash_fee'] ?? 0) > 0): ?>
                        <div><span>Cash handling fee</span><strong>&#8369;<?= number_format((float) $booking['cash_fee'], 2) ?></strong></div>
                    <?php endif; ?>
                    <div><span>Amount</span><strong>&#8369;<?= number_format((float) ($booking['payment_amount'] ?? 0), 2) ?></strong></div>
                    <div><span>Reference</span><strong><?= htmlspecialchars($booking['payment_reference'] ?? '-') ?></strong></div>
                </div>
            </section>

            <?php if ($refundHistory): ?>
            <section class="u-detail-section">
                <h2>Refund Information</h2>
                <div class="u-refund-timeline">
                    <?php foreach ($refundHistory as $event): ?>
                        <div class="u-refund-event">
                            <strong><?= htmlspecialchars(($event['actor'] ?? '') === 'admin' ? 'Admin response' : 'Your request') ?></strong>
                            <span>
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($event['decision'] ?? $event['type'] ?? 'refund update')))) ?>
                                <?php if (!empty($event['reason'])): ?>
                                    &middot; <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $event['reason']))) ?>
                                <?php endif; ?>
                            </span>
                            <?php if (!empty($event['admin_note']) || !empty($event['user_note']) || !empty($event['custom_note'])): ?>
                                <p><?= htmlspecialchars($event['admin_note'] ?? $event['user_note'] ?? $event['custom_note']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($event['created_at'])): ?>
                                <small><?= date('M j, Y g:i A', strtotime($event['created_at'])) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout/user_layout.php';
?>
