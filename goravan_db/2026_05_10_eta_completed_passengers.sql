ALTER TABLE schedules
    ADD COLUMN estimated_arrival_at DATETIME NULL AFTER departure_time,
    MODIFY COLUMN arrived_at DATETIME NULL DEFAULT NULL;

ALTER TABLE bookings
    MODIFY COLUMN status ENUM('pending','approved','completed','rejected','cancelled') NOT NULL;

UPDATE schedules
SET estimated_arrival_at = COALESCE(estimated_arrival_at, DATE_ADD(CONCAT(departure_date, ' ', departure_time), INTERVAL 2 HOUR))
WHERE estimated_arrival_at IS NULL;

UPDATE schedules
SET arrived_at = NULL
WHERE trip_status <> 'arrived';

UPDATE schedules
SET arrived_at = COALESCE(estimated_arrival_at, NOW())
WHERE trip_status = 'arrived'
  AND (arrived_at IS NULL OR arrived_at < CONCAT(departure_date, ' ', departure_time));

UPDATE bookings b
INNER JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
SET b.status = 'completed',
    b.updated_at = NOW()
WHERE s.trip_status = 'arrived'
  AND b.status NOT IN ('rejected', 'cancelled', 'completed');
