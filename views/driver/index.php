<?php
require_once '../../autoload.php';

$title = 'Driver Dashboard';
$page_css = '../../assets/css/driver-dashboard.css';
$page_js = '../../assets/js/driver-dashboard.js';

$driverUserId = (int) decrypt($_SESSION['id']);
$driverObj = new Drivers($conn);
$driver = $driverObj->GetDriverByUserId($driverUserId);

$scheduleObj = new Schedules($conn);
$trips = $driver ? $scheduleObj->GetDriverTripsByUser($driverUserId) : [];
$stats = $driver ? $scheduleObj->GetDriverTripStats($driverUserId) : [
    'today' => 0,
    'upcoming' => 0,
    'completed' => 0,
    'active' => 0,
];
$now = date('Y-m-d H:i:s');
$dashboardTrips = array_values(array_filter($trips, function (array $trip) use ($now): bool {
    $status = (string) ($trip['trip_status'] ?? '');
    $dateTime = trim((string) ($trip['departure_date'] ?? '') . ' ' . (string) ($trip['departure_time'] ?? '00:00:00'));

    if (in_array($status, ['departed', 'arrived'], true)) {
        return true;
    }

    if (in_array($status, ['completed', 'cancelled'], true)) {
        return false;
    }

    return $dateTime <= $now;
}));
$completedTrips = array_values(array_filter($trips, function (array $trip): bool {
    return ($trip['trip_status'] ?? '') === 'completed';
}));
usort($completedTrips, function (array $a, array $b): int {
    $aTime = strtotime((string) ($a['completed_at'] ?? '')) ?: strtotime(trim((string) ($a['departure_date'] ?? '') . ' ' . (string) ($a['departure_time'] ?? '00:00:00'))) ?: 0;
    $bTime = strtotime((string) ($b['completed_at'] ?? '')) ?: strtotime(trim((string) ($b['departure_date'] ?? '') . ' ' . (string) ($b['departure_time'] ?? '00:00:00'))) ?: 0;
    return $bTime <=> $aTime;
});
$completedTrips = array_slice($completedTrips, 0, 5);

$formatDate = function (?string $date): string {
    return $date ? date('M j, Y', strtotime($date)) : '-';
};
$formatTime = function (?string $time): string {
    return $time ? date('g:i A', strtotime($time)) : '-';
};
$formatDateTime = function (?string $value): string {
    return $value ? date('M j, Y g:i A', strtotime($value)) : '-';
};
$groupPassengers = function (array $passengers): array {
    $grouped = [];
    foreach ($passengers as $passenger) {
        $name = trim((string) ($passenger['name'] ?? 'Passenger')) ?: 'Passenger';
        $ref = (string) ($passenger['reference_code'] ?? '');
        $key = $ref . '|' . strtolower($name);
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'name' => $name,
                'seats' => [],
            ];
        }
        $seat = trim((string) ($passenger['seat_number'] ?? ''));
        $type = strtolower((string) ($passenger['type'] ?? 'regular'));
        $seatKey = $seat . '|' . $type;
        if ($seat !== '' && !isset($grouped[$key]['seat_keys'][$seatKey])) {
            $grouped[$key]['seat_keys'][$seatKey] = true;
            $grouped[$key]['seats'][] = ['number' => $seat, 'type' => $type];
        }
    }

    return array_map(function ($group) {
        unset($group['seat_keys']);
        return $group;
    }, array_values($grouped));
};
$typeLabel = fn($type) => [
    'regular' => 'Regular',
    'student' => 'Student',
    'senior' => 'Senior Citizen',
    'pwd' => 'PWD',
][strtolower((string) $type)] ?? ucfirst((string) $type);

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
                <span class="driver-eyebrow">Driver Workboard</span>
                <h1>Welcome, <?= htmlspecialchars($driver['full_name']) ?></h1>
                <p>Trips appear here when their schedule time has arrived. Future assignments are in Upcoming Trips.</p>
            </div>
            <?= vanny_mascot('wave', 'medium', 'driver-welcome-vanny', 'Vanny welcomes the driver') ?>
            <div class="driver-license">
                <span>License</span>
                <strong><?= htmlspecialchars($driver['license_number'] ?? '-') ?></strong>
            </div>
        </section>

        <section class="driver-stat-grid" aria-label="Trip summary">
            <div class="driver-stat">
                <span>Today</span>
                <strong><?= (int) ($stats['today'] ?? 0) ?></strong>
                <small>Assigned trips</small>
            </div>
            <div class="driver-stat">
                <span>Upcoming</span>
                <strong><?= (int) ($stats['upcoming'] ?? 0) ?></strong>
                <small>Not completed</small>
            </div>
            <div class="driver-stat">
                <span>Active</span>
                <strong><?= (int) ($stats['active'] ?? 0) ?></strong>
                <small>Needs tracking</small>
            </div>
            <div class="driver-stat">
                <span>Completed</span>
                <strong><?= (int) ($stats['completed'] ?? 0) ?></strong>
                <small>Finished trips</small>
            </div>
        </section>

        <?php if (empty($dashboardTrips)): ?>
            <section class="driver-empty-state">
                <?= vanny_mascot('waiting', 'medium', 'driver-empty-vanny', 'Vanny waiting for assigned trips') ?>
                <h2>No trips to handle right now</h2>
                <p>Upcoming or in-progress schedules will appear here when there is something to drive.</p>
            </section>
        <?php else: ?>
            <div class="driver-section-heading">
                <div>
                    <span class="driver-eyebrow">Now and Next</span>
                    <h2>Trips to Handle</h2>
                </div>
                <small><?= count($dashboardTrips) ?> ready or in-progress schedule<?= count($dashboardTrips) === 1 ? '' : 's' ?></small>
            </div>
            <section class="driver-trip-list">
                <?php foreach ($dashboardTrips as $trip): ?>
                    <?php
                    $status = $scheduleObj->NormalizeTripStatus((string) ($trip['trip_status'] ?? 'not_departed'));
                    $nextStatus = $scheduleObj->NextDriverTripStatus($status);
                    $passengers = $groupPassengers($trip['passengers'] ?? []);
                    $approvedCount = (int) ($trip['approved_bookings_count'] ?? count($passengers));
                    $disableAction = $nextStatus === 'departed' && $approvedCount < 1;
                    ?>
                    <article class="driver-trip-card">
                        <div class="driver-trip-head">
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
                            <span class="driver-trip-badge <?= htmlspecialchars($status) ?>">
                                <?= htmlspecialchars($scheduleObj->TripStatusLabel($status)) ?>
                            </span>
                        </div>

                        <div class="driver-trip-meta">
                            <div><span>Departure</span><strong><?= htmlspecialchars($formatTime($trip['departure_time'] ?? null)) ?></strong></div>
                            <div><span>Arrival</span><strong><?= htmlspecialchars($formatDateTime($trip['estimated_arrival_at'] ?? null)) ?></strong></div>
                            <div><span>Van</span><strong><?= htmlspecialchars(($trip['van_model'] ?? 'Van') . ' - ' . ($trip['van_plate'] ?? '-')) ?></strong></div>
                            <div><span>Booked Seats</span><strong><?= $approvedCount ?></strong></div>
                        </div>

                        <div class="driver-trip-times">
                            <span>Departed: <?= htmlspecialchars($formatDateTime($trip['departed_at'] ?? null)) ?></span>
                            <span>Arrived: <?= htmlspecialchars($formatDateTime($trip['arrived_at'] ?? null)) ?></span>
                            <span>Completed: <?= htmlspecialchars($formatDateTime($trip['completed_at'] ?? null)) ?></span>
                        </div>

                        <div class="driver-passenger-section">
                            <div class="driver-section-title">
                                <i class="fas fa-users"></i>
                                <span>Passenger List</span>
                            </div>
                            <?php if (empty($passengers)): ?>
                                <div class="driver-passenger-empty">No approved passengers yet.</div>
                            <?php else: ?>
                                <div class="driver-passenger-table-wrap">
                                    <table class="driver-passenger-table">
                                        <thead>
                                            <tr>
                                                <th>Passenger Name</th>
                                                <th>Seats / Type</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($passengers as $passenger): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($passenger['name'] ?? 'Passenger') ?></td>
                                                    <td>
                                                        <div class="driver-seat-type-list">
                                                            <?php foreach (($passenger['seats'] ?? []) as $seat): ?>
                                                                <span class="driver-seat-type-chip">
                                                                    <?= htmlspecialchars($seat['number'] ?? '-') ?>
                                                                    <small><?= htmlspecialchars($typeLabel($seat['type'] ?? 'regular')) ?></small>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="driver-trip-actions">
                            <?php if ($nextStatus): ?>
                                <form class="driver-status-form" method="POST" action="../../controllers/driver/UpdateTripStatus.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="schedule_id" value="<?= htmlspecialchars(encrypt((string) $trip['schedule_id_pk'])) ?>">
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($nextStatus) ?>">
                                    <button type="submit" class="driver-action-btn" <?= $disableAction ? 'disabled' : '' ?>>
                                        <i class="fas fa-arrow-right"></i>
                                        Mark as <?= htmlspecialchars($scheduleObj->TripStatusLabel($nextStatus)) ?>
                                    </button>
                                    <?php if ($disableAction): ?>
                                        <span class="driver-action-note">At least one approved passenger is required before departure.</span>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span class="driver-complete-note">No further status update available.</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if (!empty($completedTrips)): ?>
            <section class="driver-completed-panel">
                <div class="driver-section-heading">
                    <div>
                        <span class="driver-eyebrow">History</span>
                        <h2>Recently Completed</h2>
                    </div>
                    <small>Latest <?= count($completedTrips) ?></small>
                </div>
                <div class="driver-completed-list">
                    <?php foreach ($completedTrips as $trip): ?>
                        <article class="driver-completed-item">
                            <div>
                                <strong>
                                    <?= htmlspecialchars($trip['origin'] ?? 'Origin') ?>
                                    <i class="fas fa-arrow-right route-arrow-icon"></i>
                                    <?= htmlspecialchars($trip['destination'] ?? 'Destination') ?>
                                </strong>
                                <span><?= htmlspecialchars($formatDate($trip['departure_date'] ?? null)) ?> at <?= htmlspecialchars($formatTime($trip['departure_time'] ?? null)) ?></span>
                            </div>
                            <small><?= (int) ($trip['approved_bookings_count'] ?? 0) ?> passenger<?= (int) ($trip['approved_bookings_count'] ?? 0) === 1 ? '' : 's' ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include '../layout/driver_layout.php';
?>
