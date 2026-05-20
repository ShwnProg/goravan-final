<?php
class Schedules
{
    private $conn = null;
    private $table = "schedules";

    public $id;
    public $route_id;
    public $driver_id;
    public $van_id;
    public $departure_date;
    public $departure_time;
    public $estimated_arrival_at;
    public $trip_status;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->EnsureTripStatusSchema();
    }

    public function GetAllSchedules(): array
    {
        $this->ApplyAutomaticArrivals();

        $stmt = $this->conn->prepare("
            SELECT 
                s.*,
                CONCAT(r.origin, ' → ', r.destination) as route_display,
                r.origin,
                r.destination,
                d.full_name as driver_name,
                d.license_number as driver_license,
                v.plate_number as van_plate,
                v.model as van_model,
                v.capacity as van_capacity,
                (
                    SELECT COUNT(*)
                    FROM seats seat_count
                    WHERE seat_count.van_id_fk = v.van_id_pk
                ) AS total_seats,
                (
                    SELECT COUNT(DISTINCT booked.seat_id_fk)
                    FROM bookings booked
                    WHERE booked.schedule_id_fk = s.schedule_id_pk
                      AND booked.status NOT IN ('rejected', 'cancelled')
                ) AS booked_seats,
                (
                    SELECT COUNT(*)
                    FROM seats free_seats
                    WHERE free_seats.van_id_fk = v.van_id_pk
                      AND free_seats.seat_id_pk NOT IN (
                          SELECT booked_free.seat_id_fk
                          FROM bookings booked_free
                          WHERE booked_free.schedule_id_fk = s.schedule_id_pk
                            AND booked_free.status NOT IN ('rejected', 'cancelled')
                      )
                ) AS available_seats,
                (
                    SELECT COUNT(*)
                    FROM bookings b
                    WHERE b.schedule_id_fk = s.schedule_id_pk
                      AND b.status = 'pending'
                ) as pending_bookings_count
            FROM {$this->table} s
            LEFT JOIN routes r ON s.route_id_fk = r.route_id_pk
            LEFT JOIN drivers d ON s.driver_id_fk = d.driver_id_pk
            LEFT JOIN vans v ON s.van_id_fk = v.van_id_pk
            ORDER BY
                CASE s.trip_status
                    WHEN 'not_departed' THEN 0
                    WHEN 'boarding' THEN 0
                    WHEN 'departed' THEN 1
                    WHEN 'arrived' THEN 2
                    WHEN 'completed' THEN 3
                    WHEN 'cancelled' THEN 4
                    ELSE 4
                END,
                s.created_at DESC
        ");
        $stmt->execute();
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $seatStmt = $this->conn->prepare("
            SELECT * FROM seats ORDER BY van_id_fk, seat_row ASC, seat_col ASC
        ");
        $seatStmt->execute();
        $allSeats = $seatStmt->fetchAll(PDO::FETCH_ASSOC);
        $groupedSeats = [];
        foreach ($allSeats as $seat) {
            $groupedSeats[$seat['van_id_fk']][] = $seat;
        }

        foreach ($schedules as &$sch) {
            $sch['van_seats'] = $groupedSeats[$sch['van_id_fk']] ?? [];
        }

        return $this->AttachStopsToSchedules($schedules);
    }

    public function GetScheduleByID(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                s.*,
                r.origin, r.destination, r.fare as route_fare,
                d.full_name, d.license_number, d.contact_number,
                v.plate_number, v.model, v.capacity
            FROM {$this->table} s
            LEFT JOIN routes r ON s.route_id_fk = r.route_id_pk
            LEFT JOIN drivers d ON s.driver_id_fk = d.driver_id_pk
            LEFT JOIN vans v ON s.van_id_fk = v.van_id_pk
            WHERE s.schedule_id_pk = :id
        ");
        $stmt->execute([':id' => $this->id]);
        $sch = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sch)
            return [];

        $seatStmt = $this->conn->prepare("
            SELECT * FROM seats WHERE van_id_fk = :van_id ORDER BY seat_row, seat_col
        ");
        $seatStmt->execute([':van_id' => $sch['van_id_fk']]);
        $sch['van_seats'] = $seatStmt->fetchAll(PDO::FETCH_ASSOC);

        return [$sch];
    }

    public function HasVanConflict(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT schedule_id_pk FROM {$this->table}
            WHERE van_id_fk = :van_id
              AND departure_date = :date
              AND departure_time = :time
              AND schedule_id_pk != :id
        ");
        $stmt->execute([
            ':van_id' => $this->van_id,
            ':date' => $this->departure_date,
            ':time' => $this->departure_time,
            ':id' => $this->id ?: 0
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public function HasDriverConflict(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT schedule_id_pk FROM {$this->table}
            WHERE driver_id_fk = :driver_id
              AND departure_date = :date
              AND departure_time = :time
              AND schedule_id_pk != :id
        ");
        $stmt->execute([
            ':driver_id' => $this->driver_id,
            ':date' => $this->departure_date,
            ':time' => $this->departure_time,
            ':id' => $this->id ?: 0
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public function AddSchedule(): array
    {
        try {
            if (!$this->ResourcesAreActive()) {
                return [
                    'success' => false,
                    'message' => 'Only active routes, vans, and drivers can be assigned to new schedules.'
                ];
            }

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table}
                    (route_id_fk, driver_id_fk, van_id_fk, departure_date, departure_time, estimated_arrival_at, trip_status)
                VALUES
                    (:route_id, :driver_id, :van_id, :date, :time, :eta, :status)
            ");
            $stmt->execute([
                ':route_id' => $this->route_id,
                ':driver_id' => $this->driver_id,
                ':van_id' => $this->van_id,
                ':date' => $this->departure_date,
                ':time' => $this->departure_time,
                ':eta' => $this->estimated_arrival_at,
                ':status' => $this->trip_status
            ]);

            $this->conn->commit();
            return ['success' => true, 'id' => $this->conn->lastInsertId()];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log('[Schedules::AddSchedule] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to add schedule. Please check the details and try again.'];
        }
    }

    public function EditSchedule(): array
    {
        try {
            $currentStatus = $this->getCurrentStatus();
            if (!$currentStatus) {
                return ['success' => false, 'message' => 'Schedule not found.'];
            }

            if (!$this->ResourcesCanBeAssignedToExistingSchedule()) {
                return [
                    'success' => false,
                    'message' => 'Only active routes, vans, and drivers can be newly assigned to a schedule.'
                ];
            }

            if ($this->trip_status !== $currentStatus && $this->HasPendingBookings()) {
                return [
                    'success' => false,
                    'message' => 'Resolve pending bookings before changing this schedule status.'
                ];
            }

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET route_id_fk  = :route_id,
                    driver_id_fk = :driver_id,
                    van_id_fk    = :van_id,
                    departure_date = :date,
                    departure_time = :time,
                    estimated_arrival_at = :eta,
                    trip_status  = :status,
                    departed_at = CASE
                        WHEN :status2 = 'departed' AND :current_status <> 'departed' THEN NOW()
                        WHEN :status3 IN ('departed', 'arrived', 'completed') THEN departed_at
                        ELSE NULL
                    END,
                    arrived_at = CASE
                        WHEN :status4 = 'arrived' AND :current_status2 <> 'arrived' THEN NOW()
                        WHEN :status5 IN ('arrived', 'completed') THEN arrived_at
                        ELSE NULL
                    END,
                    completed_at = CASE
                        WHEN :status6 = 'completed' AND :current_status3 <> 'completed' THEN NOW()
                        WHEN :status7 = 'completed' THEN completed_at
                        ELSE NULL
                    END,
                    updated_at   = NOW()
                WHERE schedule_id_pk = :id
            ");
            $stmt->execute([
                ':id' => $this->id,
                ':route_id' => $this->route_id,
                ':driver_id' => $this->driver_id,
                ':van_id' => $this->van_id,
                ':date' => $this->departure_date,
                ':time' => $this->departure_time,
                ':eta' => $this->estimated_arrival_at,
                ':status' => $this->trip_status,
                ':status2' => $this->trip_status,
                ':status3' => $this->trip_status,
                ':status4' => $this->trip_status,
                ':status5' => $this->trip_status,
                ':status6' => $this->trip_status,
                ':status7' => $this->trip_status,
                ':current_status' => $currentStatus,
                ':current_status2' => $currentStatus,
                ':current_status3' => $currentStatus
            ]);

            if ($this->trip_status === 'cancelled') {
                $this->CancelBookingsForSchedule((int) $this->id);
            } elseif ($this->trip_status === 'completed') {
                $this->CompleteBookingsForSchedule((int) $this->id);
            }

            $this->conn->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log('[Schedules::EditSchedule] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update schedule. Please check the details and try again.'];
        }
    }

    public function DeleteSchedule(): array
    {
        try {
            if (!$this->id) {
                return ['success' => false, 'message' => 'Invalid schedule ID.'];
            }

            $currentStatus = $this->getCurrentStatus();
            if (!$currentStatus) {
                return ['success' => false, 'message' => 'Schedule not found.'];
            }

            $currentStatus = $this->NormalizeTripStatus((string) $currentStatus);
            $blockedDeleteMessages = [
                'not_departed' => [
                    'title' => 'Cancel first',
                    'message' => 'Cancel this schedule first before deleting it.'
                ],
                'departed' => [
                    'title' => 'Cannot delete departed schedule',
                    'message' => 'Cannot delete departed schedule. This trip is already in progress. Complete the trip first instead of deleting it.'
                ],
                'arrived' => [
                    'title' => 'Cannot delete arrived schedule',
                    'message' => 'Cannot delete arrived schedule. This trip has already arrived and should be completed instead.'
                ],
                'completed' => [
                    'title' => 'Cannot delete completed schedule',
                    'message' => 'Cannot delete completed schedule. Completed trips are kept for history and reports.'
                ],
            ];

            if (isset($blockedDeleteMessages[$currentStatus])) {
                return [
                    'success' => false,
                    'title' => $blockedDeleteMessages[$currentStatus]['title'],
                    'message' => $blockedDeleteMessages[$currentStatus]['message']
                ];
            }

            if ($currentStatus !== 'cancelled') {
                return [
                    'success' => false,
                    'title' => 'Cannot delete schedule',
                    'message' => 'This schedule cannot be deleted in its current status.'
                ];
            }

            $bookingCount = $this->CountBookingsForSchedule((int) $this->id);
            if ($bookingCount > 0) {
                return [
                    'success' => false,
                    'title' => 'Cannot delete schedule',
                    'message' => 'This schedule is already cancelled and cannot be deleted.'
                ];
            }

            $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE schedule_id_pk = :id");
            $stmt->execute([':id' => $this->id]);

            return [
                'success' => $stmt->rowCount() > 0,
                'message' => $stmt->rowCount() > 0 ? 'Schedule deleted successfully.' : 'Schedule not found.'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Unable to delete this schedule because it is still linked to other records.',
                'error' => $e->getMessage()
            ];
        }
    }

    public function CancelScheduleByAdmin(): array
    {
        try {
            if (!$this->id) {
                return ['success' => false, 'message' => 'Invalid schedule ID.'];
            }

            $currentStatus = $this->getCurrentStatus();
            if (!$currentStatus) {
                return ['success' => false, 'message' => 'Schedule not found.'];
            }

            if (!in_array($currentStatus, ['not_departed', 'boarding'], true)) {
                return [
                    'success' => false,
                    'message' => 'Only schedules that have not departed can be cancelled by admin.'
                ];
            }

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET trip_status = 'cancelled',
                    departed_at = NULL,
                    arrived_at = NULL,
                    completed_at = NULL,
                    updated_at = NOW()
                WHERE schedule_id_pk = :id
            ");
            $stmt->execute([':id' => $this->id]);

            $this->CancelBookingsForSchedule((int) $this->id);

            $this->conn->commit();

            return ['success' => true, 'message' => 'Schedule cancelled successfully.'];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[Schedules::CancelScheduleByAdmin] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to cancel schedule. Please try again.'];
        }
    }

    public function canUpdateStatus(string $newStatus): bool
    {
        if (!$this->id)
            return false;

        $current = $this->getCurrentStatus();
        if (!$current)
            return false;

        if ($newStatus !== $current && $this->HasPendingBookings()) {
            return false;
        }

        $transitions = [
            'boarding' => ['departed', 'cancelled'],
            'not_departed' => ['departed', 'cancelled'],
            'departed' => ['arrived'],
            'arrived' => ['completed'],
            'completed' => [],
            'cancelled' => []
        ];

        return in_array($newStatus, $transitions[$current] ?? []);
    }

    private function getCurrentStatus(): ?string
    {
        $stmt = $this->conn->prepare("
            SELECT trip_status FROM {$this->table} WHERE schedule_id_pk = :id
        ");
        $stmt->execute([':id' => $this->id]);
        return $stmt->fetchColumn() ?: null;
    }

    public function HasPendingBookings(): bool
    {
        if (!$this->id) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM bookings
            WHERE schedule_id_fk = :schedule_id
              AND status = 'pending'
        ");
        $stmt->execute([':schedule_id' => $this->id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function CountBookingsForSchedule(int $scheduleId): int
    {
        if (!$scheduleId) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM bookings
            WHERE schedule_id_fk = :schedule_id
        ");
        $stmt->execute([':schedule_id' => $scheduleId]);
        return (int) $stmt->fetchColumn();
    }

    private function ResourcesAreActive(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM routes r
            INNER JOIN drivers d ON d.driver_id_pk = :driver_id AND d.status = 'active'
            INNER JOIN vans v ON v.van_id_pk = :van_id AND v.status = 'active'
            WHERE r.route_id_pk = :route_id
              AND r.is_active = 1
        ");
        $stmt->execute([
            ':route_id' => $this->route_id,
            ':driver_id' => $this->driver_id,
            ':van_id' => $this->van_id,
        ]);
        return (int) $stmt->fetchColumn() === 1;
    }

    private function ResourcesCanBeAssignedToExistingSchedule(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT route_id_fk, driver_id_fk, van_id_fk
            FROM {$this->table}
            WHERE schedule_id_pk = :id
        ");
        $stmt->execute([':id' => $this->id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            return false;
        }

        return $this->ResourceIsActiveOrUnchanged('routes', 'route_id_pk', 'is_active', 1, (int) $this->route_id, (int) $current['route_id_fk'])
            && $this->ResourceIsActiveOrUnchanged('drivers', 'driver_id_pk', 'status', 'active', (int) $this->driver_id, (int) $current['driver_id_fk'])
            && $this->ResourceIsActiveOrUnchanged('vans', 'van_id_pk', 'status', 'active', (int) $this->van_id, (int) $current['van_id_fk']);
    }

    private function ResourceIsActiveOrUnchanged(string $table, string $idColumn, string $statusColumn, $activeValue, int $newId, int $currentId): bool
    {
        if ($newId === $currentId) {
            return true;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM {$table}
            WHERE {$idColumn} = :id
              AND {$statusColumn} = :active_value
        ");
        $stmt->execute([
            ':id' => $newId,
            ':active_value' => $activeValue,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function UpdateStatus(): array
    {
        try {
            $currentStatus = $this->getCurrentStatus();
            if (!$currentStatus) {
                return ['success' => false, 'message' => 'Schedule not found.'];
            }

            if ($this->trip_status !== $currentStatus && $this->HasPendingBookings()) {
                return [
                    'success' => false,
                    'message' => 'Resolve pending bookings before changing this schedule status.'
                ];
            }

            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET trip_status = :status,
                    departed_at = CASE
                        WHEN :status2 = 'departed' AND :current_status <> 'departed' THEN NOW()
                        WHEN :status3 IN ('departed', 'arrived', 'completed') THEN departed_at
                        ELSE NULL
                    END,
                    arrived_at  = CASE
                        WHEN :status4 = 'arrived' AND :current_status2 <> 'arrived' THEN NOW()
                        WHEN :status5 IN ('arrived', 'completed') THEN arrived_at
                        ELSE NULL
                    END,
                    completed_at = CASE
                        WHEN :status6 = 'completed' AND :current_status3 <> 'completed' THEN NOW()
                        WHEN :status7 = 'completed' THEN completed_at
                        ELSE NULL
                    END,
                    updated_at  = NOW()
                WHERE schedule_id_pk = :id
            ");
            $stmt->execute([
                ':status' => $this->trip_status,
                ':status2' => $this->trip_status,
                ':status3' => $this->trip_status,
                ':status4' => $this->trip_status,
                ':status5' => $this->trip_status,
                ':status6' => $this->trip_status,
                ':status7' => $this->trip_status,
                ':current_status' => $currentStatus,
                ':current_status2' => $currentStatus,
                ':current_status3' => $currentStatus,
                ':id' => $this->id
            ]);
            if ($this->trip_status === 'completed') {
                $this->CompleteBookingsForSchedule((int) $this->id);
            } elseif ($this->trip_status === 'cancelled') {
                $this->CancelBookingsForSchedule((int) $this->id);
            }
            return ['success' => true];
        } catch (PDOException $e) {
            error_log('[Schedules::UpdateStatus] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update schedule status. Please try again.'];
        }
    }

    public function ApplyAutomaticArrivals(): void
    {
        try {
            $this->conn->beginTransaction();

            $this->conn->exec("
                UPDATE bookings b
                INNER JOIN {$this->table} s ON b.schedule_id_fk = s.schedule_id_pk
                SET b.status = 'cancelled',
                    b.updated_at = NOW()
                WHERE s.trip_status = 'cancelled'
                  AND b.status NOT IN ('rejected', 'cancelled', 'completed')
            ");

            $this->conn->commit();
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[Schedules::ApplyAutomaticArrivals] ' . $e->getMessage());
        }
    }

    private function CompleteBookingsForSchedule(int $scheduleId): void
    {
        if (!$scheduleId) {
            return;
        }

        $stmt = $this->conn->prepare("
        UPDATE bookings b
        LEFT JOIN payments p ON p.book_id_fk = b.book_id_pk
        SET b.status = 'completed',
            b.updated_at = NOW()
        WHERE b.schedule_id_fk = :schedule_id
          AND b.status = 'approved'
          AND COALESCE(p.status, '') NOT IN ('refunded', 'failed', 'cancelled')
    ");

        $stmt->execute([
            ':schedule_id' => $scheduleId
        ]);
    }
    private function CancelBookingsForSchedule(int $scheduleId): void
    {
        if (!$scheduleId) {
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE bookings
            SET status = 'cancelled',
                updated_at = NOW()
            WHERE schedule_id_fk = :schedule_id
              AND status NOT IN ('rejected', 'cancelled', 'completed')
        ");
        $stmt->execute([':schedule_id' => $scheduleId]);

        $cashPayments = $this->conn->prepare("
            UPDATE payments p
            INNER JOIN bookings b ON p.book_id_fk = b.book_id_pk
            SET p.status = 'cancelled',
                p.paid_at = NULL
            WHERE b.schedule_id_fk = :schedule_id
              AND p.status IN ('pending', 'unpaid', 'pending_cash', 'cash_unpaid')
        ");
        $cashPayments->execute([':schedule_id' => $scheduleId]);

        $paidPayments = $this->conn->prepare("
            UPDATE payments p
            INNER JOIN bookings b ON p.book_id_fk = b.book_id_pk
            SET p.status = 'refund_requested'
            WHERE b.schedule_id_fk = :schedule_id
              AND p.status = 'paid'
              AND p.payment_method <> 'cash'
        ");
        $paidPayments->execute([':schedule_id' => $scheduleId]);
    }
    public function GetAvailableSchedules(array $filters = [])
    {
        $this->ApplyAutomaticArrivals();

        $where = [
            "s.trip_status IN ('not_departed', 'boarding')",
            "r.is_active = 1",
            "d.status = 'active'",
            "v.status = 'active'",
            "CONCAT(s.departure_date, ' ', s.departure_time) >= NOW()"
        ];
        $params = [];

        if (!empty($filters['from'])) {
            $where[] = 'r.origin = :origin';
            $params[':origin'] = trim($filters['from']);
        }

        if (!empty($filters['to'])) {
            $where[] = 'r.destination = :destination';
            $params[':destination'] = trim($filters['to']);
        }

        if (!empty($filters['date'])) {
            $where[] = 's.departure_date = :departure_date';
            $params[':departure_date'] = trim($filters['date']);
        }

        $stmt = $this->conn->prepare("
            SELECT 
                s.schedule_id_pk,
                s.route_id_fk,
                s.van_id_fk,
                s.departure_date,
                s.departure_time,
                s.estimated_arrival_at,
                s.arrived_at,
                r.origin, r.destination, r.fare as route_fare,
                d.full_name, d.license_number, d.contact_number,
                v.plate_number, v.model, v.capacity,
                (
                    SELECT COUNT(*)
                    FROM seats seat_count
                    WHERE seat_count.van_id_fk = v.van_id_pk
                ) AS total_seats,
                (
                    SELECT COUNT(DISTINCT booked.seat_id_fk)
                    FROM bookings booked
                    WHERE booked.schedule_id_fk = s.schedule_id_pk
                      AND booked.status NOT IN ('rejected', 'cancelled')
                ) AS booked_seats,
                (
                    SELECT COUNT(*)
                    FROM seats seat_count
                    WHERE seat_count.van_id_fk = v.van_id_pk
                ) - (
                    SELECT COUNT(DISTINCT booked.seat_id_fk)
                    FROM bookings booked
                    WHERE booked.schedule_id_fk = s.schedule_id_pk
                      AND booked.status NOT IN ('rejected', 'cancelled')
                ) AS available_seats
            FROM {$this->table} s
            INNER JOIN routes r ON s.route_id_fk = r.route_id_pk
            INNER JOIN drivers d ON s.driver_id_fk = d.driver_id_pk
            INNER JOIN vans v ON s.van_id_fk = v.van_id_pk
            WHERE " . implode(' AND ', $where) . "
            HAVING available_seats > 0
            ORDER BY s.departure_date ASC, s.departure_time ASC
        ");
        $stmt->execute($params);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->AttachStopsToSchedules($schedules);
    }

    public function GetAvailableLocationOptions(): array
    {
        $stmt = $this->conn->prepare("
            SELECT DISTINCT r.origin, r.destination
            FROM {$this->table} s
            INNER JOIN routes r ON s.route_id_fk = r.route_id_pk
            INNER JOIN drivers d ON s.driver_id_fk = d.driver_id_pk
            INNER JOIN vans v ON s.van_id_fk = v.van_id_pk
            WHERE s.trip_status IN ('not_departed', 'boarding')
              AND r.is_active = 1
              AND d.status = 'active'
              AND v.status = 'active'
              AND CONCAT(s.departure_date, ' ', s.departure_time) >= NOW()
              AND (
                  SELECT COUNT(*)
                  FROM seats seat_count
                  WHERE seat_count.van_id_fk = v.van_id_pk
              ) > (
                  SELECT COUNT(DISTINCT booked.seat_id_fk)
                  FROM bookings booked
                  WHERE booked.schedule_id_fk = s.schedule_id_pk
                    AND booked.status NOT IN ('rejected', 'cancelled')
              )
            ORDER BY r.origin ASC, r.destination ASC
        ");
        $stmt->execute();

        $origins = [];
        $destinationsByOrigin = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $origin = (string) ($row['origin'] ?? '');
            $destination = (string) ($row['destination'] ?? '');
            if ($origin === '' || $destination === '') {
                continue;
            }
            $origins[$origin] = $origin;
            $destinationsByOrigin[$origin][$destination] = $destination;
        }

        foreach ($destinationsByOrigin as &$destinations) {
            $destinations = array_values($destinations);
            sort($destinations);
        }
        unset($destinations);
        $origins = array_values($origins);
        sort($origins);

        return [
            'origins' => $origins,
            'destinations_by_origin' => $destinationsByOrigin,
        ];
    }

    public function AttachStopsToSchedules(array $schedules): array
    {
        if (empty($schedules)) {
            return [];
        }

        $routeIds = array_values(array_unique(array_map(fn($s) => (int) $s['route_id_fk'], $schedules)));
        $stopsByRoute = $this->GetStopsByRouteIds($routeIds);

        foreach ($schedules as &$schedule) {
            $schedule['stops'] = $stopsByRoute[(int) $schedule['route_id_fk']] ?? [];
        }

        return $schedules;
    }

    public function GetStopsByRouteIds(array $routeIds): array
    {
        $routeIds = array_values(array_unique(array_filter(array_map('intval', $routeIds))));
        if (empty($routeIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($routeIds), '?'));
        $stmt = $this->conn->prepare("
            SELECT route_id_fk, stop_name
            FROM route_stops
            WHERE route_id_fk IN ($placeholders)
            ORDER BY route_id_fk ASC, stop_order ASC
        ");
        $stmt->execute($routeIds);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $stop) {
            $grouped[(int) $stop['route_id_fk']][] = $stop['stop_name'];
        }

        return $grouped;
    }

    public function GetSeatAvailability(int $scheduleId): array
    {
        $this->ApplyAutomaticArrivals();

        $stmt = $this->conn->prepare("
            SELECT
                s.schedule_id_pk,
                s.route_id_fk,
                s.van_id_fk,
                s.departure_date,
                s.departure_time,
                s.estimated_arrival_at,
                s.arrived_at,
                r.origin,
                r.destination,
                r.fare,
                v.model AS van_model,
                v.plate_number AS van_plate,
                v.capacity AS van_capacity
            FROM {$this->table} s
            INNER JOIN routes r ON s.route_id_fk = r.route_id_pk
            INNER JOIN drivers d ON s.driver_id_fk = d.driver_id_pk
            INNER JOIN vans v ON s.van_id_fk = v.van_id_pk
            WHERE s.schedule_id_pk = :schedule_id
              AND s.trip_status IN ('not_departed', 'boarding')
              AND r.is_active = 1
              AND d.status = 'active'
              AND v.status = 'active'
              AND CONCAT(s.departure_date, ' ', s.departure_time) >= NOW()
            LIMIT 1
        ");
        $stmt->execute([':schedule_id' => $scheduleId]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$schedule) {
            return [];
        }

        $stopsByRoute = $this->GetStopsByRouteIds([(int) $schedule['route_id_fk']]);
        $schedule['stops'] = $stopsByRoute[(int) $schedule['route_id_fk']] ?? [];

        $bookedStmt = $this->conn->prepare("
            SELECT seat_id_fk
            FROM bookings
            WHERE schedule_id_fk = :schedule_id
              AND status NOT IN ('rejected', 'cancelled')
        ");
        $bookedStmt->execute([':schedule_id' => $scheduleId]);
        $bookedIds = array_flip(array_map('intval', $bookedStmt->fetchAll(PDO::FETCH_COLUMN)));

        $seatStmt = $this->conn->prepare("
            SELECT seat_id_pk, seat_number, seat_row, seat_col
            FROM seats
            WHERE van_id_fk = :van_id
            ORDER BY seat_row ASC, seat_col ASC
        ");
        $seatStmt->execute([':van_id' => $schedule['van_id_fk']]);

        $seats = array_map(function ($seat) use ($bookedIds) {
            $seatId = (int) $seat['seat_id_pk'];
            return [
                'seat_id_pk' => $seatId,
                'seat_number' => $seat['seat_number'],
                'seat_row' => (int) $seat['seat_row'],
                'seat_col' => (int) $seat['seat_col'],
                'is_booked' => isset($bookedIds[$seatId]),
            ];
        }, $seatStmt->fetchAll(PDO::FETCH_ASSOC));

        $hasAvailableSeat = array_filter($seats, fn($seat) => empty($seat['is_booked']));
        if (!$hasAvailableSeat) {
            return [];
        }

        return [
            'schedule' => $schedule,
            'seats' => $seats,
        ];
    }

    public function GetDriverTripsByUser(int $driverUserId): array
    {
        if (!$driverUserId) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                s.*,
                r.origin,
                r.destination,
                r.fare AS route_fare,
                CONCAT(r.origin, ' -> ', r.destination) AS route_display,
                d.full_name AS driver_name,
                d.status AS driver_status,
                v.plate_number AS van_plate,
                v.model AS van_model,
                v.capacity AS van_capacity,
                (
                    SELECT COUNT(*)
                    FROM bookings b
                    WHERE b.schedule_id_fk = s.schedule_id_pk
                      AND b.status IN ('approved', 'completed')
                ) AS approved_bookings_count,
                (
                    SELECT COUNT(DISTINCT b.seat_id_fk)
                    FROM bookings b
                    WHERE b.schedule_id_fk = s.schedule_id_pk
                      AND b.status NOT IN ('rejected', 'cancelled')
                ) AS booked_seats
            FROM {$this->table} s
            INNER JOIN drivers d ON s.driver_id_fk = d.driver_id_pk
            INNER JOIN routes r ON s.route_id_fk = r.route_id_pk
            INNER JOIN vans v ON s.van_id_fk = v.van_id_pk
            WHERE d.user_id_fk = :driver_user_id
            ORDER BY
                CASE
                    WHEN s.trip_status IN ('not_departed', 'boarding') THEN 0
                    WHEN s.trip_status = 'departed' THEN 1
                    WHEN s.trip_status = 'arrived' THEN 2
                    WHEN s.trip_status = 'completed' THEN 3
                    ELSE 4
                END,
                s.departure_date ASC,
                s.departure_time ASC
        ");
        $stmt->execute([':driver_user_id' => $driverUserId]);
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($trips as &$trip) {
            $trip['trip_status'] = $this->NormalizeTripStatus((string) ($trip['trip_status'] ?? 'not_departed'));
            $trip['passengers'] = $this->GetPassengersForSchedule((int) $trip['schedule_id_pk']);
        }

        return $this->AttachStopsToSchedules($trips);
    }

    public function GetDriverTripStats(int $driverUserId): array
    {
        $trips = $this->GetDriverTripsByUser($driverUserId);
        $today = date('Y-m-d');
        $stats = [
            'today' => 0,
            'upcoming' => 0,
            'completed' => 0,
            'active' => 0,
        ];
        $nowTs = time();

        foreach ($trips as $trip) {
            $status = $trip['trip_status'] ?? '';
            $date = $trip['departure_date'] ?? '';
            $departureTs = strtotime(trim($date . ' ' . ($trip['departure_time'] ?? '00:00:00')));

            if ($date === $today && !in_array($status, ['cancelled', 'completed'], true)) {
                $stats['today']++;
            }
            if (!in_array($status, ['cancelled', 'completed'], true) && $departureTs !== false && $departureTs > $nowTs) {
                $stats['upcoming']++;
            }
            if ($status === 'completed') {
                $stats['completed']++;
            }
            if (in_array($status, ['departed', 'arrived'], true)
                || ($status === 'not_departed' && $departureTs !== false && $departureTs <= $nowTs)) {
                $stats['active']++;
            }
        }

        return $stats;
    }

    public function GetPassengersForSchedule(int $scheduleId): array
    {
        if (!$scheduleId) {
            return [];
        }

        $userNameExpr = $this->UserDisplayNameExpression('u');
        $stmt = $this->conn->prepare("
            SELECT
                b.book_id_pk,
                b.reference_code,
                b.status,
                {$userNameExpr} AS user_name,
                seats.seat_id_pk,
                seats.seat_number,
                seats.seat_row,
                seats.seat_col,
                p.notes AS payment_notes
            FROM bookings b
            LEFT JOIN users u ON b.user_id_fk = u.user_id_pk
            LEFT JOIN seats ON b.seat_id_fk = seats.seat_id_pk
            LEFT JOIN payments p ON p.book_id_fk = (
                SELECT MIN(b2.book_id_pk)
                FROM bookings b2
                WHERE b2.reference_code = b.reference_code
            )
            WHERE b.schedule_id_fk = :schedule_id
              AND b.status IN ('approved', 'completed')
            ORDER BY seats.seat_row ASC, seats.seat_col ASC, b.book_id_pk ASC
        ");
        $stmt->execute([':schedule_id' => $scheduleId]);

        $passengers = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $notes = [];
            if (!empty($row['payment_notes'])) {
                $decoded = json_decode((string) $row['payment_notes'], true);
                $notes = is_array($decoded) ? $decoded : [];
            }

            $passenger = $this->PassengerFromNotes($notes, (int) $row['seat_id_pk'], (string) $row['seat_number']);
            $passengers[] = [
                'seat_id' => (int) $row['seat_id_pk'],
                'seat_number' => $row['seat_number'] ?: '-',
                'name' => $passenger['name'] ?: ($row['user_name'] ?: 'Passenger'),
                'type' => $passenger['type'] ?: 'regular',
                'reference_code' => $row['reference_code'],
                'booking_status' => $row['status'],
            ];
        }

        return $passengers;
    }

    public function UpdateTripStatusByDriver(int $scheduleId, int $driverUserId, string $newStatus): array
    {
        $newStatus = $this->NormalizeTripStatus($newStatus);
        $allowedTargets = ['departed', 'arrived', 'completed'];

        if (!$scheduleId || !$driverUserId || !in_array($newStatus, $allowedTargets, true)) {
            return ['success' => false, 'message' => 'Invalid trip status request.'];
        }

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                SELECT
                    s.schedule_id_pk,
                    s.departure_date,
                    s.departure_time,
                    s.trip_status,
                    d.status AS driver_status,
                    r.is_active AS route_active,
                    v.status AS van_status
                FROM {$this->table} s
                INNER JOIN drivers d ON s.driver_id_fk = d.driver_id_pk
                INNER JOIN routes r ON s.route_id_fk = r.route_id_pk
                INNER JOIN vans v ON s.van_id_fk = v.van_id_pk
                WHERE s.schedule_id_pk = :schedule_id
                  AND d.user_id_fk = :driver_user_id
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([
                ':schedule_id' => $scheduleId,
                ':driver_user_id' => $driverUserId,
            ]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$trip) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'You can only update trips assigned to you.'];
            }

            if (($trip['driver_status'] ?? '') !== 'active') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Your driver account is inactive. Please contact the admin.'];
            }

            if ((int) ($trip['route_active'] ?? 0) !== 1 || ($trip['van_status'] ?? '') !== 'active') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This trip is inactive. Please contact the admin.'];
            }

            $current = $this->NormalizeTripStatus((string) $trip['trip_status']);
            $transitions = [
                'not_departed' => 'departed',
                'departed' => 'arrived',
                'arrived' => 'completed',
            ];

            if (($transitions[$current] ?? null) !== $newStatus) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Trip status must be updated in order.'];
            }

            if ($current === 'not_departed') {
                $departureAt = strtotime(trim((string) ($trip['departure_date'] ?? '') . ' ' . (string) ($trip['departure_time'] ?? '00:00:00')));
                if ($departureAt && time() < $departureAt) {
                    $this->conn->rollBack();
                    return ['success' => false, 'message' => 'This trip cannot be marked departed before its scheduled departure time.'];
                }
            }

            if ($current === 'not_departed' && $this->CountApprovedBookingsForSchedule($scheduleId) < 1) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This trip has no approved passengers yet.'];
            }

            $update = $this->conn->prepare("
                UPDATE {$this->table}
                SET trip_status = :status,
                    departed_at = CASE
                        WHEN :status2 = 'departed' THEN COALESCE(departed_at, NOW())
                        ELSE departed_at
                    END,
                    arrived_at = CASE
                        WHEN :status3 = 'arrived' THEN COALESCE(arrived_at, NOW())
                        ELSE arrived_at
                    END,
                    completed_at = CASE
                        WHEN :status4 = 'completed' THEN COALESCE(completed_at, NOW())
                        ELSE completed_at
                    END,
                    updated_at = NOW()
                WHERE schedule_id_pk = :schedule_id
            ");
            $update->execute([
                ':status' => $newStatus,
                ':status2' => $newStatus,
                ':status3' => $newStatus,
                ':status4' => $newStatus,
                ':schedule_id' => $scheduleId,
            ]);

            if ($newStatus === 'completed') {
                $this->CompleteBookingsForSchedule($scheduleId);
            }

            $this->conn->commit();
            return ['success' => true, 'status' => $newStatus];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[Schedules::UpdateTripStatusByDriver] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update trip status right now.' . $e->getMessage()];
        }
    }

    public function NormalizeTripStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return $status === 'boarding' ? 'not_departed' : $status;
    }

    public function TripStatusLabel(string $status): string
    {
        return [
            'boarding' => 'Not Departed',
            'not_departed' => 'Not Departed',
            'departed' => 'Departed',
            'arrived' => 'Arrived',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ][$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    public function NextDriverTripStatus(string $status): ?string
    {
        $status = $this->NormalizeTripStatus($status);
        return [
            'not_departed' => 'departed',
            'departed' => 'arrived',
            'arrived' => 'completed',
        ][$status] ?? null;
    }

    private function CountApprovedBookingsForSchedule(int $scheduleId): int
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM bookings
            WHERE schedule_id_fk = :schedule_id
              AND status = 'approved'
        ");
        $stmt->execute([':schedule_id' => $scheduleId]);
        return (int) $stmt->fetchColumn();
    }

    private function PassengerFromNotes(array $notes, int $seatId, string $seatNumber): array
    {
        $passengers = is_array($notes['passengers'] ?? null) ? $notes['passengers'] : [];
        foreach ($passengers as $passenger) {
            $noteSeatId = (int) ($passenger['seat_id'] ?? 0);
            $noteSeatNumber = (string) ($passenger['seat_number'] ?? '');
            if (($noteSeatId && $noteSeatId === $seatId) || ($noteSeatNumber !== '' && $noteSeatNumber === $seatNumber)) {
                return [
                    'name' => trim((string) ($passenger['name'] ?? '')),
                    'type' => strtolower((string) ($passenger['type'] ?? 'regular')),
                ];
            }
        }

        return [
            'name' => trim((string) ($notes['passenger_name'] ?? '')),
            'type' => strtolower((string) ($notes['passenger_type'] ?? 'regular')),
        ];
    }

    private function EnsureTripStatusSchema(): void
    {
        try {
            $columnType = strtolower($this->GetColumnType('schedules', 'trip_status'));
            if ($columnType && (str_contains($columnType, 'boarding') || !str_contains($columnType, 'not_departed') || !str_contains($columnType, 'completed'))) {
                $this->conn->exec("
                    ALTER TABLE schedules
                    MODIFY COLUMN trip_status ENUM('boarding','not_departed','departed','arrived','completed','cancelled') NOT NULL DEFAULT 'boarding'
                ");
                $this->conn->exec("UPDATE schedules SET trip_status = 'not_departed' WHERE trip_status = 'boarding'");
                $this->conn->exec("
                    ALTER TABLE schedules
                    MODIFY COLUMN trip_status ENUM('not_departed','departed','arrived','completed','cancelled') NOT NULL DEFAULT 'not_departed'
                ");
            }

            if (!$this->ColumnExists('schedules', 'departed_at')) {
                $this->conn->exec("ALTER TABLE schedules ADD COLUMN departed_at DATETIME NULL AFTER trip_status");
            }

            if ($this->ColumnExists('schedules', 'arrived_at')) {
                $this->conn->exec("ALTER TABLE schedules MODIFY COLUMN arrived_at DATETIME NULL DEFAULT NULL");
            }

            if (!$this->ColumnExists('schedules', 'completed_at')) {
                $this->conn->exec("ALTER TABLE schedules ADD COLUMN completed_at DATETIME NULL AFTER arrived_at");
            }
        } catch (Throwable $e) {
            error_log('[Schedules::EnsureTripStatusSchema] ' . $e->getMessage());
        }
    }

    private function ColumnExists(string $table, string $column): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function GetColumnType(string $table, string $column): string
    {
        $stmt = $this->conn->prepare("
            SELECT COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);
        return (string) $stmt->fetchColumn();
    }

    private function UserDisplayNameExpression(string $alias): string
    {
        if ($this->ColumnExists('users', 'firstname') && $this->ColumnExists('users', 'lastname')) {
            return "TRIM(CONCAT(COALESCE({$alias}.firstname, ''), ' ', COALESCE({$alias}.lastname, '')))";
        }

        if ($this->ColumnExists('users', 'fullname')) {
            return "{$alias}.fullname";
        }

        return "''";
    }
}
?>
