<?php
require_once "../../autoload.php";

$title    = 'Bookings';
$page_css = '../../assets/css/bookings.css';
$page_js  = '../../assets/js/bookings-js.js';

ob_start();

$bookingObj = new Bookings($conn);
$bookings   = $bookingObj->GetAllBookings();
$bookingDates = array_values(array_unique(array_filter(array_map(
    fn($b) => !empty($b['created_at']) ? substr((string) $b['created_at'], 0, 10) : '',
    $bookings
))));
rsort($bookingDates);
?>

<div class="toolbar">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="booking-search" placeholder="Search bookings...">
    </div>
    <input type="hidden" id="booking-filter-status" value="pending">
    <div class="admin-date-filters" data-filter-scope="bookings">
        <label>
            <span>Date</span>
            <select id="booking-date-select">
                <option value="">All dates</option>
                <?php foreach ($bookingDates as $recordDate): ?>
                    <option value="<?= htmlspecialchars($recordDate, ENT_QUOTES) ?>">
                        <?= date('M d, Y', strtotime($recordDate)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>From</span><input type="date" id="booking-date-from"></label>
        <label><span>To</span><input type="date" id="booking-date-to"></label>
        <button type="button" class="filter-btn ghost" id="booking-date-clear">Clear</button>
    </div>
</div>

<div class="admin-status-tabs" id="booking-status-tabs" aria-label="Booking status filters">
    <button type="button" class="active" data-status="pending">Pending Bookings</button>
    <button type="button" data-status="approved">Approved Bookings</button>
    <button type="button" data-status="completed">Completed Bookings</button>
    <button type="button" data-status="cancelled">Cancelled Bookings</button>
    <button type="button" data-status="rejected">Rejected Bookings</button>
    <button type="button" data-status="">All Bookings</button>
</div>

<?= csrf_field() ?>

<div class="bookings-wrapper">

    <!-- ── TABLE CARD ──────────────────────────────────────────────── -->
    <div class="bookings-card">
        <div class="bookings-card-header">
            <h2>
                <i class="fas fa-ticket-alt" style="margin-right:7px;color:var(--color-accent)"></i>
                <span id="booking-view-title">Pending Bookings</span>
            </h2>
            <span id="booking-count"></span>
        </div>
        <div class="bookings-table-wrap">
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Booking</th>
                        <th>Passenger</th>
                        <th>Trip</th>
                        <th>Seats</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bookings-tbody">
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <?= vanny_mascot('waiting', 'small', 'admin-empty-vanny', 'Vanny waiting for bookings') ?>
                                    <p>No bookings yet.</p>
                                </div>
                            </td>
                        </tr>
                <?php else: ?>
                        <?php
                        $bookingStatusGroups = [
                            'pending' => ['label' => 'Pending Bookings', 'icon' => 'fas fa-clock', 'hint' => 'Needs review'],
                            'approved' => ['label' => 'Approved Bookings', 'icon' => 'fas fa-circle-check', 'hint' => 'Ready for trip'],
                            'completed' => ['label' => 'Completed Bookings', 'icon' => 'fas fa-flag-checkered', 'hint' => 'Finished trips'],
                            'rejected' => ['label' => 'Rejected Bookings', 'icon' => 'fas fa-circle-xmark', 'hint' => 'Declined requests'],
                            'cancelled' => ['label' => 'Cancelled Bookings', 'icon' => 'fas fa-ban', 'hint' => 'Inactive bookings'],
                        ];
                        $currentGroup = '';
                        foreach ($bookings as $i => $b):
                            $group = $bookingStatusGroups[$b['status']] ?? ['label' => ucwords($b['status']) . ' Bookings', 'icon' => 'fas fa-ticket-alt', 'hint' => 'Other bookings'];
                            if ($currentGroup !== $b['status']):
                                $currentGroup = $b['status'];
                        ?>
                            <tr class="admin-status-group-row" data-group-key="<?= htmlspecialchars($currentGroup, ENT_QUOTES) ?>">
                                <td colspan="8">
                                    <div class="admin-status-group-label">
                                        <i class="<?= htmlspecialchars($group['icon'], ENT_QUOTES) ?>"></i>
                                        <span><?= htmlspecialchars($group['label']) ?></span>
                                        <small><?= htmlspecialchars($group['hint']) ?></small>
                                    </div>
                                </td>
                            </tr>
                        <?php
                            endif;
                            $seatNumbers = array_filter(array_map('trim', explode(',', $b['seat_numbers'] ?? '')));
                            $paymentStatus = strtolower($b['payment_status'] ?? 'pending');
                            $paymentLabel = ucwords(str_replace('_', ' ', $paymentStatus ?: 'pending'));
                            $paymentClass = $paymentStatus ?: 'pending';
                            $paymentAmount = isset($b['payment_amount']) ? (float) $b['payment_amount'] : 0;
                            $paymentMethod = $b['payment_method'] ? ucfirst($b['payment_method']) : 'No payment';
                            $notes = !empty($b['payment_notes']) ? json_decode($b['payment_notes'], true) : [];
                            $notes = is_array($notes) ? $notes : [];
                            $passengers = is_array($notes['passengers'] ?? null) ? $notes['passengers'] : [];
                            $passengerSummary = $passengers
                                ? implode(', ', array_map(fn($p) => ($p['seat_number'] ?? '-') . ': ' . ucfirst($p['type'] ?? 'regular'), $passengers))
                                : ucfirst($notes['passenger_type'] ?? 'regular');
                        ?>
                            <tr class="booking-row status-<?= htmlspecialchars($b['status'], ENT_QUOTES) ?>"
                                data-id="<?= (int) $b['book_id_pk'] ?>"
                                data-ref-code="<?= htmlspecialchars($b['reference_code'], ENT_QUOTES) ?>"
                                data-user-name="<?= htmlspecialchars($b['user_name'] ?? 'N/A', ENT_QUOTES) ?>"
                                data-user-email="<?= htmlspecialchars($b['user_email'] ?? 'N/A', ENT_QUOTES) ?>"
                                data-user-phone="<?= htmlspecialchars($b['user_phone'] ?? 'N/A', ENT_QUOTES) ?>"
                                data-route="<?= htmlspecialchars($b['route_display'] ?? 'N/A', ENT_QUOTES) ?>"
                                data-seat="<?= htmlspecialchars($b['seat_numbers'] ?? 'N/A', ENT_QUOTES) ?>"
                                data-seat-count="<?= (int) ($b['seats_count'] ?? 0) ?>"
                                data-status="<?= htmlspecialchars($b['status'], ENT_QUOTES) ?>"
                                data-payment="<?= htmlspecialchars($paymentLabel, ENT_QUOTES) ?>"
                                data-payment-method="<?= htmlspecialchars($paymentMethod, ENT_QUOTES) ?>"
                                data-payment-amount="<?= htmlspecialchars(number_format($paymentAmount, 2), ENT_QUOTES) ?>"
                                data-notes="<?= htmlspecialchars($b['payment_notes'] ?? '', ENT_QUOTES) ?>"
                                data-passenger-types="<?= htmlspecialchars($passengerSummary, ENT_QUOTES) ?>"
                                data-driver="<?= htmlspecialchars($b['driver_name'] ?? 'N/A', ENT_QUOTES) ?>"
                                data-van="<?= htmlspecialchars(($b['van_model'] ?? 'Van') . ' (' . ($b['van_plate'] ?? 'N/A') . ')', ENT_QUOTES) ?>"
                                data-departure="<?= date('M d, Y g:i A', strtotime($b['departure_date'] . ' ' . $b['departure_time'])) ?>"
                                data-created="<?= htmlspecialchars($b['created_at'], ENT_QUOTES) ?>">

                                <td class="text-muted-sm"><?= $i + 1 ?></td>
                                <td>
                                    <div class="booking-ref-stack">
                                        <span class="ref-code"><?= htmlspecialchars($b['reference_code']) ?></span>
                                        <small><?= date('M d, Y', strtotime($b['created_at'])) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="passenger-info">
                                        <span class="name"><?= htmlspecialchars($b['user_name'] ?? 'Unknown') ?></span>
                                        <span class="email text-muted-sm"><?= htmlspecialchars($passengerSummary) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="route-info trip-stack">
                                        <i class="fas fa-route" style="color:var(--color-accent);font-size:11px"></i>
                                        <span>
                                            <?= htmlspecialchars($b['origin'] ?? 'Origin') ?>
                                            <i class="fas fa-arrow-right route-arrow-icon"></i>
                                            <?= htmlspecialchars($b['destination'] ?? 'Destination') ?>
                                        </span>
                                        <small><?= date('M d, g:i A', strtotime($b['departure_date'] . ' ' . $b['departure_time'])) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="seat-chip-list">
                                        <?php if (empty($seatNumbers)): ?>
                                            <span class="seat-badge">N/A</span>
                                        <?php else: ?>
                                            <?php foreach (array_slice($seatNumbers, 0, 3) as $seatNumber): ?>
                                                <span class="seat-badge"><i class="fas fa-chair" style="font-size:10px"></i><?= htmlspecialchars($seatNumber) ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($seatNumbers) > 3): ?>
                                                <span class="seat-badge more">+<?= count($seatNumbers) - 3 ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><span class="payment-badge <?= htmlspecialchars($paymentClass) ?>"><?= htmlspecialchars($paymentLabel) ?></span></td>
                                <td><span class="badge <?= htmlspecialchars($b['status']) ?>"><?= ucfirst($b['status']) ?></span></td>
                                <td>
                                    <div class="row-actions">
                                        <button class="icon-btn view" title="View Details"><i class="fas fa-eye"></i></button>
                                        <?php if ($b['status'] === 'pending'): ?>
                                            <button class="icon-btn approve" title="Approve"><i class="fas fa-check"></i></button>
                                            <button class="icon-btn reject" title="Reject"><i class="fas fa-times"></i></button>
                                        <?php endif; ?>
                                        <?php if ($b['status'] === 'approved'): ?>
                                            <button class="icon-btn cancel" title="Cancel"><i class="fas fa-ban"></i></button>
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

</div><!-- /.bookings-wrapper -->

<!-- ── DETAILS MODAL ────────────────────────────────────────────────── -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">
                    <i class="fas fa-file-invoice" style="margin-right:8px;color:var(--color-accent)"></i>
                    Booking Details
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="details-grid">
                    <!-- Left column -->
                    <div class="details-col">
                        <div class="detail-section">
                            <h4 class="section-title">Booking Info</h4>
                            <div class="detail-row">
                                <span class="detail-label">Reference Code</span>
                                <span id="detail-ref-code" class="detail-value">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status</span>
                                <span id="detail-status" class="detail-value">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Created</span>
                                <span id="detail-created" class="detail-value">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Payment</span>
                                <span id="detail-payment" class="detail-value">-</span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h4 class="section-title">Notes or Remarks</h4>
                            <div class="detail-row">
                                <span class="detail-label">Notes</span>
                                <span id="detail-notes" class="detail-value">No booking notes available.</span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h4 class="section-title">Passenger Info</h4>
                            <div class="detail-row">
                                <span class="detail-label">Name</span>
                                <span id="detail-passenger-name" class="detail-value">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email</span>
                                <span id="detail-passenger-email" class="detail-value">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone</span>
                                <span id="detail-passenger-phone" class="detail-value">—</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right column -->
                    <div class="details-col">
                        <div class="detail-section">
                            <h4 class="section-title">Trip Info</h4>
                            <div class="detail-row">
                                <span class="detail-label">Route</span>
                                <span id="detail-route" class="detail-value">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Departure</span>
                                <span id="detail-departure" class="detail-value">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Driver</span>
                                <span id="detail-driver" class="detail-value">—</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Van</span>
                                <span id="detail-van" class="detail-value">—</span>
                            </div>
                        </div>

                        <div class="detail-section">
                            <h4 class="section-title">Seat Assignments</h4>
                            <div class="detail-row">
                                <span class="detail-label">Seats</span>
                                <div id="detail-seat" class="detail-value">—</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout/admin_layout.php';
?>
