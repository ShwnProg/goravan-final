<?php
require_once '../../autoload.php';

$title = 'Upcoming Trips';
$page_css = '../../assets/css/driver-dashboard.css';

$driverUserId = (int) decrypt($_SESSION['id']);
$driverObj = new Drivers($conn);
$driver = $driverObj->GetDriverByUserId($driverUserId);

$scheduleObj = new Schedules($conn);
$trips = $driver ? $scheduleObj->GetDriverTripsByUser($driverUserId) : [];
$nowTs = time();
$upcomingTrips = array_values(array_filter($trips, function (array $trip) use ($nowTs): bool {
    $status = (string) ($trip['trip_status'] ?? '');
    $departureTs = strtotime(trim((string) ($trip['departure_date'] ?? '') . ' ' . (string) ($trip['departure_time'] ?? '00:00:00')));

    return !in_array($status, ['departed', 'arrived', 'completed', 'cancelled'], true)
        && $departureTs !== false
        && $departureTs > $nowTs;
}));

$formatDate = function (?string $date): string {
    return $date ? date('M j, Y', strtotime($date)) : '-';
};
$formatTime = function (?string $time): string {
    return $time ? date('g:i A', strtotime($time)) : '-';
};
$formatDateTime = function (?string $value): string {
    return $value ? date('M j, Y g:i A', strtotime($value)) : '-';
};

ob_start();
?>

<div class="driver-page">
    <?php if (!$driver): ?>
        <section class="driver-empty-state">
            <?= vanny_mascot('error', 'medium', 'driver-empty-vanny', 'Vanny error') ?>
            <h2>Driver account not linked</h2>
            <p>Please ask the administrator to link this login to a driver record.</p>
        </section>
    <?php else: ?>
        <section class="driver-welcome">
            <div>
                <span class="driver-eyebrow">Assigned Schedules</span>
                <h1>Upcoming Trips</h1>
                <p>Future trips stay here until their departure time. Status updates unlock on the dashboard when the trip is due.</p>
            </div>
            <?= vanny_mascot('waiting', 'medium', 'driver-welcome-vanny', 'Vanny waiting for upcoming trips') ?>
            <div class="driver-license">
                <span>Driver</span>
                <strong><?= htmlspecialchars($driver['full_name'] ?? 'Driver') ?></strong>
            </div>
        </section>

        <?php if (empty($upcomingTrips)): ?>
            <section class="driver-empty-state">
                <?= vanny_mascot('waiting', 'medium', 'driver-empty-vanny', 'Vanny waiting for schedules') ?>
                <h2>No upcoming trips</h2>
                <p>Future schedules assigned to you will appear here.</p>
            </section>
        <?php else: ?>
            <section class="driver-upcoming-list">
                <?php foreach ($upcomingTrips as $trip): ?>
                    <article class="driver-upcoming-card">
                        <div>
                            <span class="driver-trip-date"><?= htmlspecialchars($formatDate($trip['departure_date'] ?? null)) ?></span>
                            <h2>
                                <?= htmlspecialchars($trip['origin'] ?? 'Origin') ?>
                                <i class="fas fa-arrow-right route-arrow-icon"></i>
                                <?= htmlspecialchars($trip['destination'] ?? 'Destination') ?>
                            </h2>
                            <?php if (!empty($trip['stops'])): ?>
                                <p>via <?= htmlspecialchars(implode(', ', $trip['stops'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="driver-upcoming-meta">
                            <div><span>Departure</span><strong><?= htmlspecialchars($formatTime($trip['departure_time'] ?? null)) ?></strong></div>
                            <div><span>Arrival</span><strong><?= htmlspecialchars($formatDateTime($trip['estimated_arrival_at'] ?? null)) ?></strong></div>
                            <div><span>Van</span><strong><?= htmlspecialchars(($trip['van_model'] ?? 'Van') . ' - ' . ($trip['van_plate'] ?? '-')) ?></strong></div>
                            <div><span>Booked Seats</span><strong><?= (int) ($trip['approved_bookings_count'] ?? 0) ?></strong></div>
                        </div>
                        <span class="driver-action-note">Status updates are available on the dashboard once this trip reaches departure time.</span>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include '../layout/driver_layout.php';
?>
