<?php

require_once "../../autoload.php";
ob_start();
$title    = 'Dashboard';
$page_css = '../../assets/css/dashboard.css';
$page_js  = '../../assets/js/dashboard-js.js';

// FETCH  
$dash = new Dashboard($conn);
$verificationStats = $dash->GetVerificationSummary();
$verificationSummary = (int) ($verificationStats['pending'] ?? 0);
$bookingSummary = $dash->GetBookingSummary();   // total, pending, approved, completed, rejected, cancelled
$paymentSummary = $dash->GetPaymentSummary();
$totalUsers     = $dash->GetTotalUsers();
$schedSummary   = $dash->GetScheduleSummary();  // active_schedules + statuses
$fleetSummary   = $dash->GetFleetSummary();
$quickInsights  = $dash->GetQuickInsights();
$missingScheduleDates = $dash->GetMissingScheduleDates(7);
$recentBookings = $dash->GetRecentBookings();   // last 5
$dailyBookings  = $dash->GetDailyBookings();    // last 7 days


// $bookingsByStatus = $dash->GetBookingsByStatus();
// $seatsBooked      = $dash->GetSeatsBooked();

// Chart data 
$chartDailyLabels = array_column($dailyBookings, 'date');
$chartDailyData   = array_map('intval', array_column($dailyBookings, 'total'));

// Breakdown percentages
$rawTotalBookings = (int) ($bookingSummary['total_bookings'] ?? 0);
$totalBookings = max(1, $rawTotalBookings);
$breakdown = [
    ['label' => 'Pending',   'count' => (int) ($bookingSummary['pending'] ?? 0),   'color' => '#F97316'],
    ['label' => 'Approved',  'count' => (int) ($bookingSummary['approved'] ?? 0),  'color' => '#16a34a'],
    ['label' => 'Completed', 'count' => (int) ($bookingSummary['completed'] ?? 0), 'color' => '#2563eb'],
    ['label' => 'Cancelled', 'count' => (int) ($bookingSummary['cancelled'] ?? 0), 'color' => '#9ca3af'],
    ['label' => 'Rejected',  'count' => (int) ($bookingSummary['rejected'] ?? 0),  'color' => '#ef4444'],
];

$formatMoney = function ($amount): string {
    return 'PHP ' . number_format((float) $amount, 2);
};

$todaysTrips = (int) ($quickInsights['todays_trips'] ?? 0);
$pendingBookings = (int) ($bookingSummary['pending'] ?? 0);
$pendingInsightVerifications = (int) ($quickInsights['pending_verifications'] ?? $verificationSummary);
$revenueToday = (float) ($paymentSummary['revenue_today'] ?? 0);
$activeVans = (int) ($fleetSummary['active_vans'] ?? 0);
$activeDrivers = (int) ($fleetSummary['active_drivers'] ?? 0);
$hasPendingWork = $pendingBookings > 0 || $pendingInsightVerifications > 0;
$hasMissingSchedules = !empty($missingScheduleDates);
?>

<!--  KPI CARDS  -->
<?= vanny_message_card(
    'info',
    'Admin overview',
    'Review pending bookings, payments, schedules, and daily operations from one clean workspace.',
    'pointing',
    '',
    '',
    'admin-vanny-overview'
) ?>

<div class="db-top-row">

    <a class="db-stat db-stat--accent" href="bookings.php">
        <span class="db-stat__label">Total Bookings</span>
        <span class="db-stat__val"><?= number_format($rawTotalBookings) ?></span>
        <span class="db-stat__sub">All-time booking records</span>
    </a>

    <a class="db-stat db-stat--attention" href="bookings.php">
        <span class="db-stat__label">Pending Bookings</span>
        <span class="db-stat__val"><?= number_format((int) ($bookingSummary['pending'] ?? 0)) ?></span>
        <span class="db-stat__sub">Awaiting admin action</span>
    </a>

    <a class="db-stat db-stat--success" href="payments.php">
        <span class="db-stat__label">Total Revenue</span>
        <span class="db-stat__val db-stat__val--money"><?= $formatMoney($paymentSummary['total_revenue'] ?? 0) ?></span>
        <span class="db-stat__sub">Paid payments only</span>
    </a>

    <a class="db-stat db-stat--attention" href="users.php">
        <span class="db-stat__label">Pending Verifications</span>
        <span class="db-stat__val"><?= number_format($verificationSummary) ?></span>
        <span class="db-stat__sub">Requires admin review</span>
    </a>

    <a class="db-stat" href="schedules.php">
        <span class="db-stat__label">Active Schedules</span>
        <span class="db-stat__val"><?= number_format((int) ($schedSummary['active_schedules'] ?? 0)) ?></span>
        <span class="db-stat__sub">Not departed, departed, or arrived</span>
    </a>

    <a class="db-stat" href="users.php">
        <span class="db-stat__label">Total Users</span>
        <span class="db-stat__val"><?= number_format($totalUsers) ?></span>
        <span class="db-stat__sub">Registered passenger accounts</span>
    </a>

</div>

<?php if (!empty($missingScheduleDates)): ?>
    <div class="db-card schedule-warning-card">
        <div class="db-card__head">
            <span class="db-card__title">7-day schedule warning</span>
            <a class="db-pill" href="schedules.php">Create schedules</a>
        </div>
        <p class="db-mini-note">Schedules are missing for the next 7 days. Add trips for:</p>
        <div class="schedule-missing-days">
            <?php foreach ($missingScheduleDates as $missingDate): ?>
                <span><?= htmlspecialchars(date('M j', strtotime($missingDate))) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="db-summary-row">

    <div class="db-mini-card">
        <div class="db-card__head">
            <span class="db-card__title">Payment health</span>
            <span class="db-pill">All time</span>
        </div>
        <div class="db-metrics">
            <div class="db-metric">
                <span>Paid</span>
                <strong><?= number_format((int) ($paymentSummary['paid'] ?? 0)) ?></strong>
            </div>
            <div class="db-metric">
                <span>Pending</span>
                <strong><?= number_format((int) ($paymentSummary['pending'] ?? 0)) ?></strong>
            </div>
            <div class="db-metric">
                <span>Refunds</span>
                <strong><?= number_format((int) ($paymentSummary['refund_requested'] ?? 0)) ?></strong>
            </div>
        </div>
        <div class="db-mini-note">This week: <?= $formatMoney($paymentSummary['revenue_week'] ?? 0) ?></div>
    </div>

    <div class="db-mini-card">
        <div class="db-card__head">
            <span class="db-card__title">Schedule activity</span>
            <span class="db-pill">Trips</span>
        </div>
        <div class="db-metrics">
            <div class="db-metric">
                <span>Not Departed</span>
                <strong><?= number_format((int) ($schedSummary['not_departed'] ?? $schedSummary['boarding'] ?? 0)) ?></strong>
            </div>
            <div class="db-metric">
                <span>Departed</span>
                <strong><?= number_format((int) ($schedSummary['departed'] ?? 0)) ?></strong>
            </div>
            <div class="db-metric">
                <span>Arrived</span>
                <strong><?= number_format((int) ($schedSummary['arrived'] ?? 0)) ?></strong>
            </div>
        </div>
        <div class="db-mini-note">Completed: <?= number_format((int) ($schedSummary['completed'] ?? 0)) ?> - Cancelled: <?= number_format((int) ($schedSummary['cancelled'] ?? 0)) ?></div>
    </div>

    <div class="db-mini-card">
        <div class="db-card__head">
            <span class="db-card__title">Fleet readiness</span>
            <span class="db-pill">Active</span>
        </div>
        <div class="db-metrics">
            <div class="db-metric">
                <span>Vans</span>
                <strong><?= number_format((int) ($fleetSummary['active_vans'] ?? 0)) ?>/<?= number_format((int) ($fleetSummary['total_vans'] ?? 0)) ?></strong>
            </div>
            <div class="db-metric">
                <span>Drivers</span>
                <strong><?= number_format((int) ($fleetSummary['active_drivers'] ?? 0)) ?>/<?= number_format((int) ($fleetSummary['total_drivers'] ?? 0)) ?></strong>
            </div>
            <div class="db-metric">
                <span>Routes</span>
                <strong><?= number_format((int) ($fleetSummary['active_routes'] ?? 0)) ?>/<?= number_format((int) ($fleetSummary['total_routes'] ?? 0)) ?></strong>
            </div>
        </div>
        <div class="db-mini-note">Inactive records stay available for history.</div>
    </div>

</div>


<!--  MID ROW: BREAKDOWN + BAR CHART  -->
<div class="db-mid-row">

    <!-- Booking breakdown -->
    <div class="db-card">
        <div class="db-card__head">
            <span class="db-card__title">Booking breakdown</span>
            <span class="db-pill">All time</span>
        </div>
        <div class="db-seg-bars">
            <?php if ($rawTotalBookings === 0): ?>
                <div class="db-empty-inline">No booking records yet.</div>
            <?php else: ?>
            <?php foreach ($breakdown as $seg):
                $pct = round($seg['count'] / $totalBookings * 100);
            ?>
                <div class="db-seg">
                    <div class="db-seg__meta">
                        <span class="db-seg__name"><?= $seg['label'] ?></span>
                        <span class="db-seg__count">
                            <?= number_format($seg['count']) ?>
                            <span class="db-seg__sep">·</span>
                            <?= $pct ?>%
                        </span>
                    </div>
                    <div class="db-seg__track">
                        <div class="db-seg__fill"
                             style="width:<?= $pct ?>%;background:<?= $seg['color'] ?>">
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Daily bookings bar chart -->
    <div class="db-card">
        <div class="db-card__head">
            <span class="db-card__title">Bookings this week</span>
            <span class="db-pill">Last 7 days</span>
        </div>
        <div class="db-chart-wrap">
            <canvas id="chartDaily"></canvas>
        </div>
    </div>

</div>


<!--  BOTTOM ROW: TABLE + ACTIVITY  -->
<div class="db-bottom-row">

    <!-- Recent bookings -->
    <div class="db-tbl-card">
        <div class="db-tbl-head">
            <span class="db-card__title">Recent bookings</span>
            <span class="db-pill">Last 5</span>
        </div>
        <div class="db-tbl-wrap">
            <table class="db-table">
                <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Passenger</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentBookings)): ?>
                        <tr class="db-empty-row">
                            <td colspan="4">
                                <i class="fa-regular fa-folder-open"></i>
                                No bookings yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentBookings as $b): ?>
                            <tr>
                                <td>
                                    <span class="db-ref">
                                        <?= htmlspecialchars($b['reference_code']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($b['passenger'] ?? '—') ?></td>
                                <td>
                                    <span class="db-badge db-badge--<?= htmlspecialchars($b['status']) ?>">
                                        <?= ucfirst($b['status']) ?>
                                    </span>
                                </td>
                                <td class="db-muted">
                                    <?= date('M d', strtotime($b['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick insights -->
    <div class="db-card db-insights-card">
        <div class="db-card__head">
            <span class="db-card__title">Quick Insights</span>
            <span class="db-pill db-pill--live">Live</span>
        </div>
        <p class="db-insights-sub">Important operational updates for today.</p>
        <div class="db-insights-list">
            <a class="db-insight-row" href="schedules.php">
                <span class="db-insight__icon"><i class="fas fa-route"></i></span>
                <span class="db-insight__body">
                    <span class="db-insight__top">
                        <strong>Today's Operations</strong>
                        <span class="db-insight-pill <?= $todaysTrips > 0 ? 'is-good' : 'is-neutral' ?>"><?= $todaysTrips > 0 ? 'Active' : 'Quiet' ?></span>
                    </span>
                    <span><?= $todaysTrips > 0 ? 'You have ' . number_format($todaysTrips) . ' scheduled trip' . ($todaysTrips === 1 ? '' : 's') . ' today.' : 'No trips scheduled today.' ?></span>
                </span>
            </a>

            <div class="db-insight-row">
                <span class="db-insight__icon db-insight__icon--attention"><i class="fas fa-clipboard-check"></i></span>
                <span class="db-insight__body">
                    <span class="db-insight__top">
                        <strong>Pending Work</strong>
                        <span class="db-insight-pill <?= $hasPendingWork ? 'is-warning' : 'is-good' ?>"><?= $hasPendingWork ? 'Action Needed' : 'Good' ?></span>
                    </span>
                    <span>
                        <?php if ($hasPendingWork): ?>
                            <?= $pendingBookings > 0 ? number_format($pendingBookings) . ' booking' . ($pendingBookings === 1 ? '' : 's') . ' awaiting approval.' : '' ?>
                            <?= $pendingBookings > 0 && $pendingInsightVerifications > 0 ? ' ' : '' ?>
                            <?= $pendingInsightVerifications > 0 ? number_format($pendingInsightVerifications) . ' verification request' . ($pendingInsightVerifications === 1 ? '' : 's') . ' need review.' : '' ?>
                        <?php else: ?>
                            No pending approvals right now.
                        <?php endif; ?>
                    </span>
                    <?php if ($hasPendingWork): ?>
                        <span class="db-insight-actions">
                            <?php if ($pendingBookings > 0): ?><a href="bookings.php">Review Bookings</a><?php endif; ?>
                            <?php if ($pendingInsightVerifications > 0): ?><a href="users.php">Review Verifications</a><?php endif; ?>
                        </span>
                    <?php endif; ?>
                </span>
            </div>

            <a class="db-insight-row" href="payments.php">
                <span class="db-insight__icon db-insight__icon--success"><i class="fas fa-peso-sign"></i></span>
                <span class="db-insight__body">
                    <span class="db-insight__top">
                        <strong>Payment Status</strong>
                        <span class="db-insight-pill <?= $revenueToday > 0 ? 'is-good' : 'is-neutral' ?>"><?= $revenueToday > 0 ? 'Collected' : 'No Payments' ?></span>
                    </span>
                    <span><?= $revenueToday > 0 ? $formatMoney($revenueToday) . ' collected today.' : 'No paid payments recorded today.' ?></span>
                </span>
            </a>

            <a class="db-insight-row" href="vans.php">
                <span class="db-insight__icon"><i class="fas fa-van-shuttle"></i></span>
                <span class="db-insight__body">
                    <span class="db-insight__top">
                        <strong>Fleet Readiness</strong>
                        <span class="db-insight-pill <?= ($activeVans && $activeDrivers) ? 'is-good' : 'is-warning' ?>"><?= ($activeVans && $activeDrivers) ? 'Ready' : 'Check Fleet' ?></span>
                    </span>
                    <span><?= ($activeVans || $activeDrivers) ? number_format($activeVans) . ' active van' . ($activeVans === 1 ? '' : 's') . ' and ' . number_format($activeDrivers) . ' active driver' . ($activeDrivers === 1 ? '' : 's') . ' available.' : 'No active vans or drivers available.' ?></span>
                </span>
            </a>
            <div class="db-insight-row">
                <span class="db-insight__icon db-insight__icon--verify"><i class="fas fa-calendar-days"></i></span>
                <span class="db-insight__body">
                    <span class="db-insight__top">
                        <strong>Schedule Warning</strong>
                        <span class="db-insight-pill <?= $hasMissingSchedules ? 'is-warning' : 'is-good' ?>"><?= $hasMissingSchedules ? 'Warning' : 'Good' ?></span>
                    </span>
                    <span><?= $hasMissingSchedules ? 'Some dates in the next 7 days have no schedules.' : 'Schedules are prepared for the next 7 days.' ?></span>
                    <?php if ($hasMissingSchedules): ?>
                        <span class="db-insight-actions">
                            <a href="schedules.php">Create Schedules</a>
                        </span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

</div>


<!-- ── CHART DATA (PHP → JS) ─────────────────────────────────────────────────── -->
<script>
window.dailyLabels = <?= json_encode($chartDailyLabels) ?>;
window.dailyData   = <?= json_encode($chartDailyData)   ?>;
</script>


<?php
$content = ob_get_clean();
include '../layout/admin_layout.php';
?>
