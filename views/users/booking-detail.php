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
    $passengerSummary = implode(', ', array_map(function ($passenger) {
        $name = trim((string) ($passenger['name'] ?? 'Passenger'));
        $seat = trim((string) ($passenger['seat_number'] ?? ''));
        $type = ucfirst((string) ($passenger['type'] ?? 'regular'));
        return ($seat ? $seat . ': ' : '') . $name . ' (' . $type . ')';
    }, $paymentNotes['passengers']));
}
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
                <div class="u-bk-route"><?= htmlspecialchars($booking['route_display']) ?></div>
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
                    <div><span>Route</span><strong><?= htmlspecialchars($booking['route_display']) ?></strong></div>
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
                    <div class="u-passenger-table-wrap">
                        <table class="u-passenger-table">
                            <thead>
                                <tr>
                                    <th>Seat</th>
                                    <th>Passenger Name</th>
                                    <th>Passenger Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($passengers as $passenger): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($passenger['seat_number'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($passenger['name'] ?? $booking['passenger_name'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars(ucfirst($passenger['type'] ?? 'regular')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="u-detail-grid">
                        <div><span>Passenger</span><strong><?= htmlspecialchars($passengerSummary) ?></strong></div>
                        <div><span>Passenger type</span><strong><?= ucfirst($booking['passenger_type'] ?? 'regular') ?></strong></div>
                    </div>
                <?php endif; ?>
                <div class="u-detail-grid">
                    <div><span>Contact number</span><strong><?= htmlspecialchars($booking['contact_number'] ?? '-') ?></strong></div>
                </div>
            </section>

            <section class="u-detail-section">
                <h2>Payment Information</h2>
                <div class="u-detail-grid">
                    <div><span>Status</span><strong><?= ucfirst($booking['payment_status'] ?? 'pending') ?></strong></div>
                    <div><span>Method</span><strong><?= ucfirst($booking['payment_method'] ?? '-') ?></strong></div>
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
