<?php
require_once '../../autoload.php';

header('Content-Type: application/json');

if (empty($_SESSION['is_login'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$rawScheduleId = trim($_GET['schedule_id'] ?? '');
$scheduleId = (int) decrypt($rawScheduleId);
if (!$scheduleId && ctype_digit($rawScheduleId)) {
    $scheduleId = (int) $rawScheduleId;
}

if (!$scheduleId) {
    echo json_encode(['success' => false, 'message' => 'Invalid schedule ID.']);
    exit;
}

try {
    $sched = new Schedules($conn);
    $availability = $sched->GetSeatAvailability($scheduleId);

    if (empty($availability)) {
        echo json_encode(['success' => false, 'message' => 'Schedule not found or no longer available.']);
        exit;
    }

    $schedule = $availability['schedule'];
    $seats = array_map(function ($seat) {
        return [
            'seat_id' => encrypt((string) $seat['seat_id_pk']),
            'seat_number' => $seat['seat_number'],
            'seat_row' => (int) $seat['seat_row'],
            'seat_col' => (int) $seat['seat_col'],
            'is_booked' => (bool) $seat['is_booked'],
        ];
    }, $availability['seats']);

    echo json_encode([
        'success' => true,
        'seats' => $seats,
        'schedule' => [
            'schedule_id' => encrypt((string) $schedule['schedule_id_pk']),
            'origin' => $schedule['origin'],
            'destination' => $schedule['destination'],
            'stops' => $schedule['stops'] ?? [],
            'departure_date' => $schedule['departure_date'],
            'departure_time' => $schedule['departure_time'],
            'estimated_arrival_at' => $schedule['estimated_arrival_at'],
            'arrived_at' => $schedule['arrived_at'],
            'fare' => (float) $schedule['fare'],
            'van_model' => $schedule['van_model'],
            'van_plate' => $schedule['van_plate'],
            'van_capacity' => (int) $schedule['van_capacity'],
        ],
    ]);
} catch (PDOException $e) {
    error_log('[GetSeats] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to load seat availability.']);
}
