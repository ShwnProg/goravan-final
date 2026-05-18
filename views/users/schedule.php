<?php
require_once '../../autoload.php';

if (!isset($_SESSION['is_login'])) {
    header('Location: ../auth/login.php');
    exit;
}

ob_start();
$title = 'Schedule';
$active_page = 'schedule';
$page_css = '../../assets/css/user-schedule.css';
$page_js = '../../assets/js/user-schedule.js';

$userId = (int) decrypt($_SESSION['id']);
$um = new Users($conn);
$um->id = $userId;
$user = $um->GetUserById();

$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$date = trim($_GET['date'] ?? '');

$verif = new Verification($conn);
$verif->user_id_fk = $userId;
$verification = $verif->GetApprovedVerification();
$verifiedType = $verification['document_type'] ?? '';

$sched = new Schedules($conn);
$availableLocations = $sched->GetAvailableLocationOptions();
$availableOrigins = $availableLocations['origins'] ?? [];
$availableDestinationsByOrigin = $availableLocations['destinations_by_origin'] ?? [];
$results = $sched->GetAvailableSchedules([
    'from' => $from,
    'to' => $to,
    'date' => $date,
]);

$passengerName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
$contactNumber = $user['contact_number'] ?? '';
$verifiedPassengerType = $verifiedType === 'senior' ? 'senior' : ($verifiedType ?: 'regular');
$cashFee = 0.0;
try {
    $cashFeeColumn = $conn->query("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'settings'
          AND COLUMN_NAME = 'cash_handling_fee'
    ")->fetchColumn();
    if ((int) $cashFeeColumn > 0) {
        $cashFee = max(0.0, (float) $conn->query("SELECT cash_handling_fee FROM settings LIMIT 1")->fetchColumn());
    }
} catch (Throwable $e) {
    $cashFee = 0.0;
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    window.GV_DISCOUNTS = <?= json_encode(discounts) ?>;
    window.GV_VERIFIED_BONUS = <?= $verifiedType ? '2' : '0' ?>;
    window.GV_VERIFIED_TYPE = <?= json_encode($verifiedPassengerType) ?>;
    window.GV_AVAILABLE_DESTINATIONS = <?= json_encode($availableDestinationsByOrigin) ?>;
    window.GV_CASH_FEE = <?= json_encode($cashFee) ?>;
</script>

<div class="u-body mobile-view">
    <div class="u-sec">
        <div class="u-search-card">
            <form class="u-srow" action="" method="GET">
                <div class="u-sf">
                    <label for="from">From</label>
                    <select id="from" name="from" class="ss" data-placeholder="Select origin">
                        <option value="">Select origin</option>
                        <?php foreach ($availableOrigins as $name): ?>
                            <option value="<?= htmlspecialchars($name) ?>" <?= $from === $name ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="u-s-sep"><i class="fa-solid fa-arrow-right"></i></div>
                <div class="u-sf">
                    <label for="to">To</label>
                    <select id="to" name="to" class="ss" data-placeholder="Select destination">
                        <option value="">Select destination</option>
                        <?php
                        $destinationOptions = $from && isset($availableDestinationsByOrigin[$from])
                            ? $availableDestinationsByOrigin[$from]
                            : array_values(array_unique(array_merge(...array_values($availableDestinationsByOrigin ?: [[]]))));
                        sort($destinationOptions);
                        ?>
                        <?php foreach ($destinationOptions as $name): ?>
                            <option value="<?= htmlspecialchars($name) ?>" <?= $to === $name ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="u-sf">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?= htmlspecialchars($date) ?>"
                        min="<?= date('Y-m-d') ?>">
                </div>
                <button type="submit" class="u-sbtn">
                    <i class="fa-solid fa-magnifying-glass"></i> Search
                </button>
            </form>
        </div>
    </div>

    <div class="u-sec">
        <div class="u-sec-head">
            <h2 class="u-sec-title">Available Schedules</h2>
            <?php if (!empty($results)): ?>
                <span style="font-size: 12px; color: var(--u-muted);">
                    <?= count($results) ?> result(s)
                </span>
            <?php endif; ?>
        </div>

        <?php if (!empty($results)): ?>
            <div class="u-schedule-grid">
                <?php foreach ($results as $schedule):
                    $stops = $schedule['stops'] ?? [];
                    $availableSeats = (int) $schedule['available_seats'];
                    $encryptedScheduleId = encrypt((string) $schedule['schedule_id_pk']);
                    $arrival = $schedule['estimated_arrival_at']
                        ? date('g:i A', strtotime($schedule['estimated_arrival_at']))
                        : date('g:i A', strtotime($schedule['departure_date'] . ' ' . $schedule['departure_time'] . ' +2 hours'));
                    ?>
                    <div class="u-schedule-card" data-schedule-id="<?= htmlspecialchars($encryptedScheduleId) ?>" data-available-seats="<?= $availableSeats ?>">
                        <div class="u-schedule-header">
                            <div class="u-schedule-time">
                                <div class="u-schedule-dep">
                                    <?= date('g:i A', strtotime($schedule['departure_time'])) ?>
                                </div>
                                <div class="u-schedule-arr">Est. <?= htmlspecialchars($arrival) ?></div>
                            </div>
                            <div class="u-schedule-route">
                                <div class="u-route-line">
                                    <div class="u-schedule-origin"><?= htmlspecialchars($schedule['origin']) ?></div>
                                    <div class="u-schedule-arrow"><i class="fa-solid fa-arrow-right"></i></div>
                                    <div class="u-schedule-dest"><?= htmlspecialchars($schedule['destination']) ?></div>
                                </div>
                                <?php if (!empty($stops)): ?>
                                    <div class="u-route-stops" aria-label="Route stops">
                                        <?php foreach ($stops as $stop): ?>
                                            <span class="u-route-stop-pill">via <?= htmlspecialchars($stop) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="u-schedule-meta">
                            <div class="u-schedule-info">
                                <i class="fa-solid fa-van-shuttle"></i>
                                <span><?= htmlspecialchars($schedule['model'] ?? 'Standard Van') ?></span>
                            </div>
                            <div class="u-schedule-info">
                                <i class="fa-solid fa-chair"></i>
                                <span class="u-available-count"><?= $availableSeats ?> seat<?= $availableSeats === 1 ? '' : 's' ?> available</span>
                            </div>
                            <div class="u-schedule-info">
                                <i class="fa-regular fa-calendar"></i>
                                <span><?= date('M j, Y', strtotime($schedule['departure_date'])) ?></span>
                            </div>
                        </div>
                        <div class="u-schedule-footer">
                            <div class="u-schedule-price">
                                <span class="u-price-label">per seat</span>
                                <span class="u-price-value">&#8369;<?= number_format((float) $schedule['route_fare'], 2) ?></span>
                            </div>
                            <button class="u-book-btn"
                                type="button"
                                data-schedule-id="<?= htmlspecialchars($encryptedScheduleId) ?>"
                                <?= $availableSeats < 1 ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-ticket"></i> Book Now
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="u-empty-state">
                <i class="fa-solid fa-calendar-xmark"></i>
                <p>No available schedules found for your selected route and date.</p>
                <p>Try another date or nearby route.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="bookingModalLabel">Book Your Trip</h5>
                    <span class="modal-subtitle">Choose seats, passenger details, and payment in one flow.</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bookingForm">
                    <?= csrf_field() ?>
                </form>

                <div class="booking-progress" aria-label="Booking progress">
                    <div class="progress-step active" data-step="1">
                        <span class="progress-dot">1</span><span class="progress-label">Route</span>
                    </div>
                    <div class="progress-step" data-step="2">
                        <span class="progress-dot">2</span><span class="progress-label">Seats</span>
                    </div>
                    <div class="progress-step" data-step="3">
                        <span class="progress-dot">3</span><span class="progress-label">Passenger</span>
                    </div>
                    <div class="progress-step" data-step="4">
                        <span class="progress-dot">4</span><span class="progress-label">Payment</span>
                    </div>
                    <div class="progress-step" data-step="5">
                        <span class="progress-dot">5</span><span class="progress-label">Confirm</span>
                    </div>
                </div>
                <div class="booking-mobile-step" id="mobileStepLabel">Step 1 of 5 - Route</div>

                <section class="booking-step active" data-step="1">
                    <div class="booking-grid">
                        <div class="booking-card">
                            <h6>Route Preview</h6>
                            <div class="booking-map" id="bookingRouteMap"></div>
                            <div class="booking-route-meta">
                                <div class="booking-meta-row"><span>Route</span><strong id="routeName">-</strong></div>
                                <div class="booking-meta-row"><span>Date & time</span><strong id="routeDateTime">-</strong></div>
                                <div class="booking-meta-row"><span>Van</span><strong id="routeVan">-</strong></div>
                                <div class="booking-meta-row"><span>Base fare</span><strong id="routeFare">-</strong></div>
                            </div>
                        </div>
                        <aside class="booking-side">
                            <h6>Stops</h6>
                            <div class="booking-stop-list" id="bookingStopList"></div>
                        </aside>
                    </div>
                    <div class="booking-actions">
                        <button type="button" class="u-btn u-btn-primary" data-booking-next>
                            Next <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </section>

                <section class="booking-step" data-step="2">
                    <div class="seat-toolbar">
                        <div>
                            <div class="seat-counter" id="seatCounter">0 seat(s) selected</div>
                            <div class="seat-availability" id="seatAvailability">- available</div>
                        </div>
                        <div class="seat-selected-list" id="seatSelectedList">No seats selected</div>
                    </div>
                    <div class="van-seat-viewer">
                        <div class="vsv-windshield">
                            <i class="fas fa-car-side"></i><span>FRONT</span>
                        </div>
                        <div class="vsv-grid" id="bookingSeatGrid"></div>
                        <div class="vsv-legend">
                            <span class="vsv-legend-item vsv-driver-dot">Driver</span>
                            <span class="vsv-legend-item vsv-available-dot">Available</span>
                            <span class="vsv-legend-item vsv-selected-dot">Selected</span>
                            <span class="vsv-legend-item vsv-occupied-dot">Booked</span>
                        </div>
                    </div>
                    <div class="seat-fare-live" id="seatFareLive">0 seats x &#8369;0.00 = &#8369;0.00</div>
                    <div class="booking-actions">
                        <button type="button" class="u-btn u-btn-secondary" data-booking-back>
                            <i class="fa-solid fa-arrow-left"></i> Back
                        </button>
                        <button type="button" class="u-btn u-btn-primary" data-booking-next>
                            Next <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </section>

                <section class="booking-step" data-step="3">
                    <div class="booking-form-grid">
                        <div class="u-form-group full">
                            <label for="passengerName">Passenger Name</label>
                            <input type="text" id="passengerName" value="<?= htmlspecialchars($passengerName) ?>"
                                data-default="<?= htmlspecialchars($passengerName) ?>" required>
                        </div>
                        <div class="u-form-group">
                            <label for="contactNumber">Contact Number</label>
                            <input type="tel" id="contactNumber" value="<?= htmlspecialchars($contactNumber) ?>"
                                data-default="<?= htmlspecialchars($contactNumber) ?>"
                                placeholder="09XX XXX XXXX" required>
                        </div>
                        <div class="u-form-group full">
                            <?php if ($verifiedType): ?>
                                <span class="verified-badge">Verified +2% bonus</span>
                            <?php else: ?>
                                <span class="note-badge">Verify your account in Profile to get discounts</span>
                            <?php endif; ?>
                        </div>
                        <div class="u-form-group full">
                            <label>Passenger Type Per Seat</label>
                            <span class="note-badge">Discounted companion types such as Student, Senior Citizen, and PWD require a valid ID upon boarding. If no valid proof is presented, the passenger must pay the regular fare difference.</span>
                            <div class="passenger-seat-list" id="passengerSeatList"></div>
                        </div>
                    </div>

                    <div class="fare-breakdown">
                        <div class="summary-row"><span>Base fare</span><strong id="baseFareBreakdown">&#8369;0.00</strong></div>
                        <div class="summary-row"><span>Discount</span><strong id="discountBreakdown">-&#8369;0.00</strong></div>
                        <div class="summary-row fare-total"><span>Total</span><strong id="totalBreakdown">&#8369;0.00</strong></div>
                    </div>

                    <div class="booking-actions">
                        <button type="button" class="u-btn u-btn-secondary" data-booking-back>
                            <i class="fa-solid fa-arrow-left"></i> Back
                        </button>
                        <button type="button" class="u-btn u-btn-primary" data-booking-next>
                            Next <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </section>

                <section class="booking-step" data-step="4">
                    <div class="booking-grid">
                        <div class="booking-card">
                            <h6>Payment Method</h6>
                            <div class="payment-grid">
                                <button type="button" class="payment-card" data-method="gcash" style="--method-color: var(--u-accent);">
                                    <i class="fa-solid fa-mobile-screen-button"></i><span>GCash</span><small>Pay using mobile wallet</small>
                                </button>
                                <button type="button" class="payment-card" data-method="paymaya" style="--method-color: var(--u-info);">
                                    <i class="fa-regular fa-credit-card"></i><span>PayMaya</span><small>Pay using Maya wallet</small>
                                </button>
                                <button type="button" class="payment-card" data-method="card" style="--method-color: var(--u-success);">
                                    <i class="fa-solid fa-credit-card"></i><span>Card</span><small>Debit or credit card</small>
                                </button>
                                <button type="button" class="payment-card" data-method="cash" style="--method-color: #64748b;">
                                    <i class="fa-solid fa-money-bill-1-wave"></i><span>Cash</span><small>Pay on-site</small>
                                </button>
                            </div>

                            <div class="payment-panel" data-panel="gcash" hidden>
                                <div class="u-form-group">
                                    <label for="paymentPhone">GCash/PayMaya Number</label>
                                    <input type="tel" id="paymentPhone" placeholder="09XX XXX XXXX">
                                </div>
                                <p class="payment-note">This is a demo - no real transaction will occur.</p>
                            </div>

                            <div class="payment-panel" data-panel="paymaya" hidden>
                                <div class="u-form-group">
                                    <label for="paymentPhonePaymaya">GCash/PayMaya Number</label>
                                    <input type="tel" id="paymentPhonePaymaya" placeholder="09XX XXX XXXX">
                                </div>
                                <p class="payment-note">This is a demo - no real transaction will occur.</p>
                            </div>

                            <div class="payment-panel" data-panel="card" hidden>
                                <div class="card-fields">
                                    <div class="u-form-group full">
                                        <label for="cardNumber">Card Number</label>
                                        <input type="tel" id="cardNumber" placeholder="XXXX XXXX XXXX XXXX">
                                    </div>
                                    <div class="u-form-group full">
                                        <label for="cardholderName">Cardholder Name</label>
                                        <input type="text" id="cardholderName">
                                    </div>
                                    <div class="u-form-group">
                                        <label for="cardExpiry">Expiry</label>
                                        <input type="tel" id="cardExpiry" placeholder="MM/YY">
                                    </div>
                                    <div class="u-form-group">
                                        <label for="cardCvv">CVV</label>
                                        <input type="password" id="cardCvv" placeholder="123" maxlength="4">
                                    </div>
                                </div>
                            </div>

                            <div class="payment-panel" data-panel="cash" hidden>
                                <div class="cash-info">
                                    <i class="fa-solid fa-circle-info"></i>
                                    <span>Cash bookings are paid personally/on-site after approval and are not refundable online.</span>
                                </div>
                            </div>

                        </div>

                        <aside class="booking-side order-summary">
                            <h6>Order Summary</h6>
                            <div class="fare-breakdown">
                                <div class="summary-row"><span>Route</span><strong id="summaryRoute">-</strong></div>
                                <div class="summary-row"><span>Date</span><strong id="summaryDate">-</strong></div>
                                <div class="summary-row"><span>Seats</span><strong id="summarySeats">-</strong></div>
                                <div class="summary-row"><span>Passenger</span><strong id="summaryPassengerType">Regular</strong></div>
                                <div class="summary-row"><span>Discount</span><strong id="summaryDiscount">-&#8369;0.00</strong></div>
                                <div class="summary-row"><span>Subtotal</span><strong id="summarySubtotal">&#8369;0.00</strong></div>
                                <div class="summary-row"><span id="summaryFeeLabel">Convenience fee</span><strong id="summaryFee">&#8369;0.00</strong></div>
                                <div class="summary-row summary-total"><span>Grand Total</span><strong id="summaryGrandTotal">&#8369;0.00</strong></div>
                            </div>
                        </aside>
                    </div>

                    <div class="booking-actions">
                        <button type="button" class="u-btn u-btn-secondary" data-booking-back>
                            <i class="fa-solid fa-arrow-left"></i> Back
                        </button>
                        <button type="button" class="u-btn u-btn-primary" id="confirmPayBtn">
                            Submit Booking Request <i class="fa-solid fa-lock"></i>
                        </button>
                    </div>
                </section>

                <section class="booking-step" data-step="5">
                    <div class="receipt-wrap">
                        <div class="receipt-check"><i class="fa-solid fa-check"></i></div>
                        <div class="receipt-title">Booking Request Submitted</div>
                        <div class="receipt-ref">
                            <span id="receiptReference">GV-0000-00000</span>
                            <button type="button" class="copy-ref" id="copyRefBtn" title="Copy reference">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                        <div class="receipt-details">
                            <div class="receipt-row"><span>Route</span><strong id="receiptRoute">-</strong></div>
                            <div class="receipt-row"><span>Date & time</span><strong id="receiptDate">-</strong></div>
                            <div class="receipt-row"><span>Seats</span><strong id="receiptSeats">-</strong></div>
                            <div class="receipt-row"><span>Passenger</span><strong id="receiptPassenger">-</strong></div>
                            <div class="receipt-row"><span>Payment</span><strong id="receiptPaymentMethod">-</strong></div>
                            <div class="receipt-row"><span>Amount for review</span><strong id="receiptAmount">&#8369;0.00</strong></div>
                        </div>
                        <div class="booking-actions">
                            <a href="my-bookings.php" class="u-btn u-btn-primary">View My Bookings</a>
                            <button type="button" class="u-btn u-btn-secondary" id="bookAnotherBtn">Book Another Trip</button>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout/user_layout.php';
?>
