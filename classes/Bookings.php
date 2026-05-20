<?php
class Bookings
{
    private $conn = null;
    private $table = "bookings";

    public $id;
    public $user_id;
    public $schedule_id;
    public $seat_id;
    public $reference_code;
    public $status;
    // public $payment_deadline;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->EnsureModernBookingStatusColumn();
    }

    // ── READ ──────────────────────────────────────────────────────

    public function GetAllBookings(): array
    {
        $this->SyncAutomaticArrivals();

        $stmt = $this->conn->prepare("
            SELECT
                MIN(b.book_id_pk) as book_id_pk,
                b.reference_code,
                CASE
                    WHEN SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 'pending'
                    WHEN SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) > 0 THEN 'approved'
                    WHEN SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) > 0 THEN 'completed'
                    WHEN SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
                    ELSE 'cancelled'
                END as status,
                MIN(b.created_at) as created_at,
                MAX(b.updated_at) as updated_at,
                COUNT(*) as seats_count,
                CONCAT(u.firstname, ' ', u.lastname) as user_name,
                u.email as user_email,
                u.contact_number as user_phone,
                CONCAT(r.origin, ' -> ', r.destination) as route_display,
                r.origin,
                r.destination,
                s.departure_date,
                s.departure_time,
                s.trip_status as schedule_status,
                d.full_name as driver_name,
                v.plate_number as van_plate,
                v.model as van_model,
                v.capacity as van_capacity,
                GROUP_CONCAT(seats.seat_number ORDER BY seats.seat_row ASC, seats.seat_col ASC SEPARATOR ', ') as seat_numbers,
                p.amount as payment_amount,
                p.payment_method,
                p.payment_reference,
                p.status as payment_status,
                p.paid_at,
                p.notes as payment_notes
            FROM {$this->table} b
            LEFT JOIN users u ON b.user_id_fk = u.user_id_pk
            LEFT JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
            LEFT JOIN routes r ON s.route_id_fk = r.route_id_pk
            LEFT JOIN drivers d ON s.driver_id_fk = d.driver_id_pk
            LEFT JOIN vans v ON s.van_id_fk = v.van_id_pk
            LEFT JOIN seats ON b.seat_id_fk = seats.seat_id_pk
            LEFT JOIN payments p ON p.book_id_fk = (
                SELECT MIN(b2.book_id_pk)
                FROM bookings b2
                WHERE b2.reference_code = b.reference_code
            )
            GROUP BY b.reference_code, u.firstname, u.lastname, u.email, u.contact_number,
                     r.origin, r.destination, s.departure_date, s.departure_time, s.trip_status,
                     d.full_name, v.plate_number, v.model, v.capacity, p.amount, p.payment_method,
                     p.payment_reference, p.status, p.paid_at, p.notes
            ORDER BY
                CASE
                    WHEN SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 0
                    WHEN SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) > 0 THEN 1
                    WHEN SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) > 0 THEN 2
                    WHEN SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 3
                    ELSE 4
                END ASC,
                MIN(b.created_at) DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function GetBookingGroupByID(int $bookingId): array
    {
        $stmt = $this->conn->prepare("
            SELECT reference_code
            FROM {$this->table}
            WHERE book_id_pk = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $bookingId]);
        $reference = $stmt->fetchColumn();

        if (!$reference) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM {$this->table}
            WHERE reference_code = :reference_code
            ORDER BY book_id_pk ASC
        ");
        $stmt->execute([':reference_code' => $reference]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function GetBookingByID(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                b.*,
                CONCAT(u.firstname, ' ', u.lastname) as user_name,
                u.email as user_email,
                u.contact_number as user_phone,
                CONCAT(r.origin, ' → ', r.destination) as route_display,
                r.origin,
                r.destination,
                r.fare as route_fare,
                s.departure_date,
                s.departure_time,
                s.estimated_arrival_at,
                s.trip_status as schedule_status,
                s.arrived_at,
                d.full_name as driver_name,
                v.plate_number as van_plate,
                v.model as van_model,
                v.capacity as van_capacity,
                seats.seat_number,
                seats.seat_row,
                seats.seat_col
            FROM {$this->table} b
            LEFT JOIN users u ON b.user_id_fk = u.user_id_pk
            LEFT JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
            LEFT JOIN routes r ON s.route_id_fk = r.route_id_pk
            LEFT JOIN drivers d ON s.driver_id_fk = d.driver_id_pk
            LEFT JOIN vans v ON s.van_id_fk = v.van_id_pk
            LEFT JOIN seats ON b.seat_id_fk = seats.seat_id_pk
            WHERE b.book_id_pk = :id
        ");
        $stmt->execute([':id' => $this->id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $result ?: [];
    }

    public function IsSeatAlreadyBooked(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT book_id_pk FROM {$this->table}
            WHERE seat_id_fk = :seat_id
              AND schedule_id_fk = :schedule_id
              AND status NOT IN ('rejected', 'cancelled')
              AND book_id_pk != :id
        ");
        $stmt->execute([
            ':seat_id' => $this->seat_id,
            ':schedule_id' => $this->schedule_id,
            ':id' => $this->id ?: 0
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public function IsReferenceCodeExist(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT book_id_pk FROM {$this->table}
            WHERE reference_code = :code
        ");
        $stmt->execute([':code' => $this->reference_code]);
        return (bool) $stmt->fetchColumn();
    }

    // CREATE 

    public function AddBooking(): array
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table}
                    (user_id_fk, schedule_id_fk, seat_id_fk, reference_code, status)
                VALUES
                    (:user_id, :schedule_id, :seat_id, :ref_code, :status)
            ");
            $stmt->execute([
                ':user_id' => $this->user_id,
                ':schedule_id' => $this->schedule_id,
                ':seat_id' => $this->seat_id,
                ':ref_code' => $this->reference_code,
                ':status' => $this->status
                // ':deadline' => $this->payment_deadline
            ]);

            return ['success' => true, 'id' => (int) $this->conn->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function CreateUserBookingWithPayment(array $data): array
    {
        $this->SyncAutomaticArrivals();

        $userId = (int) $data['user_id'];
        $scheduleId = (int) $data['schedule_id'];
        $seatIds = array_values(array_unique(array_map('intval', $data['seat_ids'])));
        $passengerName = trim($data['passenger_name']);
        $contactNumber = trim($data['contact_number']);
        $passengerType = strtolower(trim($data['passenger_type']));
        $passengerNames = $data['passenger_names'] ?? [];
        $passengerTypes = $data['passenger_types'] ?? [];
        $paymentMethod = strtolower(trim($data['payment_method']));
        $paymentReference = trim($data['payment_reference']);
        $clientTotal = (float) $data['total_amount'];

        try {
            $this->conn->beginTransaction();

            $scheduleStmt = $this->conn->prepare("
                SELECT s.schedule_id_pk, s.van_id_fk, r.fare, r.origin, r.destination
                FROM schedules s
                INNER JOIN routes r ON s.route_id_fk = r.route_id_pk
                INNER JOIN drivers d ON s.driver_id_fk = d.driver_id_pk
                INNER JOIN vans v ON s.van_id_fk = v.van_id_pk
                WHERE s.schedule_id_pk = :schedule_id
                  AND s.trip_status IN ('not_departed', 'boarding')
                  AND CONCAT(s.departure_date, ' ', s.departure_time) >= NOW()
                  AND r.is_active = 1
                  AND d.status = 'active'
                  AND v.status = 'active'
                LIMIT 1
            ");
            $scheduleStmt->execute([':schedule_id' => $scheduleId]);
            $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);

            if (!$schedule) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'This schedule is no longer available.'];
            }

            $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
            $seatStmt = $this->conn->prepare("
                SELECT seat_id_pk, seat_number
                FROM seats
                WHERE van_id_fk = ?
                  AND seat_id_pk IN ($placeholders)
                ORDER BY seat_row ASC, seat_col ASC
            ");
            $seatStmt->execute(array_merge([(int) $schedule['van_id_fk']], $seatIds));
            $validSeats = $seatStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($validSeats) !== count($seatIds)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'One or more selected seats are invalid for this van.'];
            }

            $validSeatsById = [];
            foreach ($validSeats as $seat) {
                $validSeatsById[(int) $seat['seat_id_pk']] = $seat;
            }
            $validSeats = array_map(fn($seatId) => $validSeatsById[$seatId], $seatIds);

            $bookedStmt = $this->conn->prepare("
                SELECT seat_id_fk
                FROM bookings
                WHERE schedule_id_fk = ?
                  AND seat_id_fk IN ($placeholders)
                  AND status NOT IN ('rejected', 'cancelled')
                FOR UPDATE
            ");
            $bookedStmt->execute(array_merge([$scheduleId], $seatIds));
            if (!empty($bookedStmt->fetchAll(PDO::FETCH_COLUMN))) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Sorry, one of your selected seats was just booked. Please choose another seat.'];
            }

            $baseFare = (float) $schedule['fare'];
            $baseTotal = 0.0;
            $discountAmount = 0.0;
            $verifiedType = $this->GetApprovedPassengerType($userId);
            $verifiedBonus = $verifiedType ? 2.0 : 0.0;
            $allowedTypes = ['regular', 'student', 'senior', 'pwd'];
            $discounts = defined('discounts') ? constant('discounts') : ['student' => 10, 'senior' => 15, 'pwd' => 20];
            $passengers = [];

            foreach ($validSeats as $index => $seat) {
                $declaredType = strtolower(trim((string) ($passengerTypes[$index] ?? $passengerType)));
                if (!in_array($declaredType, $allowedTypes, true)) {
                    $declaredType = 'regular';
                }

                $isMainPassenger = $index === 0;
                $type = ($isMainPassenger && $verifiedType) ? $verifiedType : $declaredType;

                $name = trim((string) ($passengerNames[$index] ?? $passengerName));
                if ($name === '') {
                    $name = $passengerName;
                }

                $baseRate = (float) ($discounts[$type] ?? 0);
                $bonusRate = ($verifiedType && $baseRate > 0) ? $verifiedBonus : 0.0;
                $totalRate = $baseRate > 0 ? $baseRate + $bonusRate : 0.0;
                $seatDiscount = round($baseFare * ($totalRate / 100), 2);
                $idRequired = $type !== 'regular' && !($isMainPassenger && $verifiedType);

                $baseTotal += $baseFare;
                $discountAmount += $seatDiscount;
                $passengers[] = [
                    'seat_id' => (int) $seat['seat_id_pk'],
                    'seat_number' => $seat['seat_number'],
                    'name' => $name,
                    'type' => $type,
                    'declared_passenger_type' => $declaredType,
                    'is_main_passenger' => $isMainPassenger,
                    'id_verification_required' => $idRequired,
                    'id_verification_status' => $idRequired ? 'pending' : ($isMainPassenger && $verifiedType ? 'verified' : 'not_required'),
                    'fare_difference_status' => 'none',
                    'base_fare' => $baseFare,
                    'discount_rate' => $totalRate,
                    'discount_amount' => $seatDiscount,
                    'fare_after_discount' => round(max(0, $baseFare - $seatDiscount), 2),
                ];
            }

            $discountAmount = round($discountAmount, 2);
            $subtotal = round(max(0, $baseTotal - $discountAmount), 2);
            $cashFee = $this->IsCashPaymentMethod($paymentMethod) ? $this->GetCashHandlingFee() : 0.0;
            $totalAmount = round($subtotal + $cashFee, 2);

            if (abs($clientTotal - $totalAmount) > 0.50) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Fare changed while booking. Please review the fare and try again.'];
            }

            $referenceCode = $this->GenerateReferenceCode();
            $bookingIds = [];
            $bookingStmt = $this->conn->prepare("
                INSERT INTO bookings
                    (user_id_fk, schedule_id_fk, seat_id_fk, reference_code, status, created_at, updated_at)
                VALUES
                    (:user_id, :schedule_id, :seat_id, :reference_code, 'pending', NOW(), NOW())
            ");

            foreach ($validSeats as $seat) {
                $bookingStmt->execute([
                    ':user_id' => $userId,
                    ':schedule_id' => $scheduleId,
                    ':seat_id' => (int) $seat['seat_id_pk'],
                    ':reference_code' => $referenceCode,
                ]);
                $bookingIds[] = (int) $this->conn->lastInsertId();
            }

            $notes = json_encode([
                'passenger_name' => $passengerName,
                'contact_number' => $contactNumber,
                'passenger_type' => $passengers[0]['type'] ?? $passengerType,
                'passengers' => $passengers,
                'seats_count' => count($seatIds),
                'seat_numbers' => array_values(array_map(fn($s) => $s['seat_number'], $validSeats)),
                'base_fare' => $baseFare,
                'base_total' => $baseTotal,
                'verified_passenger_type' => $verifiedType ?: 'regular',
                'verified_bonus_rate' => $verifiedBonus,
                'verified_bonus_applies_to_all_seats' => (bool) $verifiedType,
                'discount_rate' => count($passengers) === 1 ? (float) $passengers[0]['discount_rate'] : null,
                'discount_amount' => $discountAmount,
                'convenience_fee' => 0,
                'cash_fee' => $cashFee,
                'subtotal' => $subtotal,
                'boarding_verification_note' => 'Discounted unverified passengers and companions require valid ID upon boarding.',
                'route_origin' => $schedule['origin'],
                'route_destination' => $schedule['destination'],
            ]);

            $paidOnlineMethods = ['gcash', 'paymaya', 'card'];
            $paymentStatus = in_array($paymentMethod, $paidOnlineMethods, true) ? 'paid' : 'pending_cash';
            $paidAt = $paymentStatus === 'paid' ? date('Y-m-d H:i:s') : null;

            $paymentStmt = $this->conn->prepare("
                INSERT INTO payments
                    (book_id_fk, amount, payment_method, payment_reference, status, paid_at, created_at, notes)
                VALUES
                    (:book_id, :amount, :method, :reference, :status, :paid_at, NOW(), :notes)
            ");
            $paymentStmt->execute([
                ':book_id' => $bookingIds[0],
                ':amount' => $totalAmount,
                ':method' => $paymentMethod,
                ':reference' => $paymentReference,
                ':status' => $paymentStatus,
                ':paid_at' => $paidAt,
                ':notes' => $notes,
            ]);

            $this->conn->commit();

            return [
                'success' => true,
                'message' => $paymentStatus === 'paid'
                    ? 'Payment recorded. Your booking is pending admin approval.'
                    : 'Booking request submitted. Please pay cash on-site after approval.',
                'reference_code' => $referenceCode,
                'booking_id' => $bookingIds[0],
                'total_amount' => number_format($totalAmount, 2, '.', ''),
            ];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[Bookings::CreateUserBookingWithPayment] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to complete booking right now.'];
        }
    }

    public function GenerateReferenceCode(): string
    {
        do {
            $code = 'GV-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM bookings WHERE reference_code = :code");
            $stmt->execute([':code' => $code]);
        } while ((int) $stmt->fetchColumn() > 0);

        return $code;
    }

    // ── UPDATE STATUS ─────────────────────────────────────────────

    public function UpdateStatus(): array
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET status = :status, updated_at = NOW()
                WHERE book_id_pk = :id
            ");
            $stmt->execute([
                ':status' => $this->status,
                ':id' => $this->id
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function UpdateStatusByReferenceCode(string $referenceCode): array
    {
        try {
            $this->EnsureModernPaymentStatusColumn();
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET status = :status, updated_at = NOW()
                WHERE reference_code = :reference_code
            ");
            $stmt->execute([
                ':status' => $this->status,
                ':reference_code' => $referenceCode,
            ]);

            if (in_array($this->status, ['rejected', 'cancelled'], true)) {
                $pay = $this->conn->prepare("
                UPDATE payments p
                INNER JOIN {$this->table} b ON p.book_id_fk = b.book_id_pk
                SET p.status = CASE
                            WHEN p.status = 'paid' AND p.payment_method <> 'cash' THEN 'refund_requested'
                            WHEN p.payment_method = 'cash' THEN 'cancelled'
                            ELSE 'cancelled'
                        END,
                        p.paid_at = CASE
                            WHEN p.status = 'paid' AND p.payment_method <> 'cash' THEN p.paid_at
                            ELSE NULL
                        END
                    WHERE b.reference_code = :reference_code
                      AND p.status NOT IN ('refund_requested', 'refunded')
                ");
                $pay->execute([':reference_code' => $referenceCode]);
            }

            $this->conn->commit();
            return ['success' => true, 'affected' => $stmt->rowCount()];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── DELETE ────────────────────────────────────────────────────

    public function DeleteBooking(): array
    {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM {$this->table} WHERE book_id_pk = :id
            ");
            $stmt->execute([':id' => $this->id]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── HELPER: Get all statuses ──────────────────────────────────

    private function EnsureModernPaymentStatusColumn(): void
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT DATA_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'payments'
                  AND COLUMN_NAME = 'status'
                LIMIT 1
            ");
            $stmt->execute();
            if (strtolower((string) $stmt->fetchColumn()) === 'enum') {
                $this->conn->exec("ALTER TABLE payments MODIFY status VARCHAR(32) NOT NULL DEFAULT 'pending'");
            }
        } catch (Throwable $e) {
            error_log('[Bookings::EnsureModernPaymentStatusColumn] ' . $e->getMessage());
        }
    }

    private function EnsureModernBookingStatusColumn(): void
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT DATA_TYPE, COLUMN_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'bookings'
                  AND COLUMN_NAME = 'status'
                LIMIT 1
            ");
            $stmt->execute();
            $column = $stmt->fetch(PDO::FETCH_ASSOC);
            $type = strtolower((string) ($column['COLUMN_TYPE'] ?? ''));
            if (($column && strtolower((string) ($column['DATA_TYPE'] ?? '')) === 'enum')
                && (!str_contains($type, 'completed') || !str_contains($type, 'departed') || !str_contains($type, 'arrived'))) {
                $this->conn->exec("
                    ALTER TABLE bookings
                    MODIFY status ENUM('pending','approved','departed','arrived','completed','rejected','cancelled') NOT NULL DEFAULT 'pending'
                ");
            }
        } catch (Throwable $e) {
            error_log('[Bookings::EnsureModernBookingStatusColumn] ' . $e->getMessage());
        }
    }

    private function GetCashHandlingFee(): float
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'settings'
                  AND COLUMN_NAME = 'cash_handling_fee'
            ");
            $stmt->execute();
            if ((int) $stmt->fetchColumn() < 1) {
                return 10.0;
            }

            $fee = $this->conn->query("SELECT cash_handling_fee FROM settings LIMIT 1")->fetchColumn();
            $configuredFee = (float) ($fee === false || $fee === null ? 10 : $fee);
            return round($configuredFee > 0 ? $configuredFee : 10, 2);
        } catch (Throwable $e) {
            return 10.0;
        }
    }

    private function IsCashPaymentMethod(string $method): bool
    {
        return in_array(strtolower(trim($method)), ['cash', 'cash-on-site', 'pending_cash'], true);
    }

    public static function GetAllStatuses(): array
    {
        return ['pending', 'approved', 'departed', 'arrived', 'completed', 'rejected', 'cancelled'];
    }

    public static function GetStatusColor(string $status): string
    {
        $colors = [
            'pending' => '#f97316',
            'approved' => '#16a34a',
            'completed' => '#2563eb',
            'rejected' => '#ef4444',
            'cancelled' => '#6b7280'
        ];
        return $colors[$status] ?? '#9ca3af';
    }

    private function GetApprovedPassengerType(int $userId): string
    {
        $stmt = $this->conn->prepare("
            SELECT document_type
            FROM verification_documents
            WHERE user_id_fk = :user_id
              AND status = 'approved'
            ORDER BY reviewed_at DESC, submitted_at DESC, document_id_pk DESC
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);
        $type = strtolower((string) $stmt->fetchColumn());
        return in_array($type, ['student', 'senior', 'pwd'], true) ? $type : '';
    }

    private function SyncAutomaticArrivals(): void
    {
        try {
            $this->conn->beginTransaction();

            $this->conn->exec("
                UPDATE {$this->table} b
                INNER JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
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
            error_log('[Bookings::SyncAutomaticArrivals] ' . $e->getMessage());
        }
    }

    // public static function IsPaymentExpired(string $deadline): bool
    // {
    //     return strtotime($deadline) < time();
    // }

    public function GetUpcomingTripByUser()
    {
        $this->SyncAutomaticArrivals();

        $stmt = $this->conn->prepare("
            SELECT 
                MIN(b.book_id_pk) as book_id_pk,
                b.reference_code,
                CASE
                    WHEN SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 'pending'
                    WHEN SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) > 0 THEN 'approved'
                    WHEN SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) > 0 THEN 'completed'
                    WHEN SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
                    ELSE 'cancelled'
                END as status,
                MIN(b.created_at) as created_at,
                CONCAT(r.origin, ' → ', r.destination) as route_display,
                r.origin,
                r.destination,
                s.departure_date,
                s.departure_time,
                s.trip_status as schedule_status,
                COUNT(*) as seats_count,
                GROUP_CONCAT(seats.seat_number ORDER BY seats.seat_row ASC, seats.seat_col ASC SEPARATOR ', ') as seat_numbers
            FROM {$this->table} b
            LEFT JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
            LEFT JOIN routes r ON s.route_id_fk = r.route_id_pk
            LEFT JOIN seats ON b.seat_id_fk = seats.seat_id_pk
            WHERE b.user_id_fk = :user_id
              AND b.status = 'approved'
              AND CONCAT(s.departure_date, ' ', s.departure_time) > NOW()
            GROUP BY b.reference_code, r.origin, r.destination, s.departure_date, s.departure_time, s.trip_status
            ORDER BY CONCAT(s.departure_date, ' ', s.departure_time) ASC
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $this->id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function GetRecentBookingsByUser($limit = 3)
    {
        $this->SyncAutomaticArrivals();

        $stmt = $this->conn->prepare("
            SELECT 
                MIN(b.book_id_pk) as book_id_pk,
                b.reference_code,
                CASE
                    WHEN SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 'pending'
                    WHEN SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) > 0 THEN 'approved'
                    WHEN SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) > 0 THEN 'completed'
                    WHEN SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
                    ELSE 'cancelled'
                END as status,
                MIN(b.created_at) as created_at,
                CONCAT(r.origin, ' → ', r.destination) as route_display,
                r.origin,
                r.destination,
                s.departure_date,
                s.departure_time,
                s.trip_status as schedule_status,
                COUNT(*) as seats_count,
                GROUP_CONCAT(seats.seat_number ORDER BY seats.seat_row ASC, seats.seat_col ASC SEPARATOR ', ') as seat_numbers
            FROM {$this->table} b
            LEFT JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
            LEFT JOIN routes r ON s.route_id_fk = r.route_id_pk
            LEFT JOIN seats ON b.seat_id_fk = seats.seat_id_pk
            WHERE b.user_id_fk = :user_id
            GROUP BY b.reference_code, r.origin, r.destination, s.departure_date, s.departure_time, s.trip_status
            ORDER BY MIN(b.created_at) DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function GetUserStats()
    {
        $this->SyncAutomaticArrivals();

        $stmt = $this->conn->prepare("
            SELECT 
                COALESCE(COUNT(*), 0) as total,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) as upcoming,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed,
                COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled
            FROM (
                SELECT
                    b.reference_code,
                    CASE
                        WHEN SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 'pending'
                        WHEN SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) > 0 THEN 'approved'
                        WHEN SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) > 0 THEN 'completed'
                        WHEN SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
                        ELSE 'cancelled'
                    END as status,
                    MAX(s.trip_status) as schedule_status,
                    CONCAT(s.departure_date, ' ', s.departure_time) as trip_datetime
                FROM {$this->table} b
                LEFT JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
                WHERE b.user_id_fk = :user_id
                GROUP BY b.reference_code, s.departure_date, s.departure_time
            ) grouped_bookings
        ");
        $stmt->execute([':user_id' => $this->id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function GetBookingsByUserFiltered()
    {
        $this->SyncAutomaticArrivals();

        $query = "
            SELECT 
                MIN(b.book_id_pk) as book_id_pk,
                b.reference_code,
                CASE
                    WHEN SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 'pending'
                    WHEN SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) > 0 THEN 'approved'
                    WHEN SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) > 0 THEN 'completed'
                    WHEN SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
                    ELSE 'cancelled'
                END as status,
                MIN(b.created_at) as created_at,
                CONCAT(r.origin, ' → ', r.destination) as route_display,
                r.origin,
                r.destination,
                s.departure_date,
                s.departure_time,
                s.trip_status as schedule_status,
                COUNT(*) as seats_count,
                GROUP_CONCAT(seats.seat_number ORDER BY seats.seat_row ASC, seats.seat_col ASC SEPARATOR ', ') as seat_numbers
            FROM {$this->table} b
            LEFT JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
            LEFT JOIN routes r ON s.route_id_fk = r.route_id_pk
            LEFT JOIN seats ON b.seat_id_fk = seats.seat_id_pk
            WHERE b.user_id_fk = :user_id
        ";

        if ($this->status === 'upcoming') {
            $query .= " AND b.status = 'approved' AND s.trip_status <> 'arrived'";
        } elseif ($this->status === 'completed') {
            $query .= " AND b.status = 'completed'";
        } elseif ($this->status === 'pending') {
            $query .= " AND b.status = 'pending'";
        } elseif ($this->status === 'cancelled') {
            $query .= " AND b.status = 'cancelled'";
        }

        $query .= "
            GROUP BY b.reference_code, r.origin, r.destination, s.departure_date, s.departure_time, s.trip_status
            ORDER BY
                CASE
                    WHEN SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 0
                    WHEN SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) > 0 THEN 1
                    WHEN SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) > 0 THEN 2
                    WHEN SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 3
                    ELSE 4
                END ASC,
                MIN(b.created_at) DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $this->id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function GetUserBookingGroupByID(int $bookingId, int $userId): array
    {
        $this->SyncAutomaticArrivals();

        $refStmt = $this->conn->prepare("
            SELECT reference_code
            FROM {$this->table}
            WHERE book_id_pk = :booking_id AND user_id_fk = :user_id
            LIMIT 1
        ");
        $refStmt->execute([
            ':booking_id' => $bookingId,
            ':user_id' => $userId,
        ]);
        $reference = $refStmt->fetchColumn();

        if (!$reference) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                MIN(b.book_id_pk) as book_id_pk,
                b.reference_code,
                CASE
                    WHEN SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 'pending'
                    WHEN SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) > 0 THEN 'approved'
                    WHEN SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) > 0 THEN 'completed'
                    WHEN SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
                    ELSE 'cancelled'
                END as status,
                MIN(b.created_at) as created_at,
                MAX(b.updated_at) as updated_at,
                CONCAT(r.origin, ' → ', r.destination) as route_display,
                r.origin,
                r.destination,
                r.fare as route_fare,
                s.departure_date,
                s.departure_time,
                s.estimated_arrival_at,
                s.trip_status as schedule_status,
                s.arrived_at,
                d.full_name as driver_name,
                v.plate_number as van_plate,
                v.model as van_model,
                COUNT(*) as seats_count,
                GROUP_CONCAT(seats.seat_number ORDER BY seats.seat_row ASC, seats.seat_col ASC SEPARATOR ', ') as seat_numbers,
                GROUP_CONCAT(CONCAT(seats.seat_number, ' (Row ', seats.seat_row, ', Col ', seats.seat_col, ')') ORDER BY seats.seat_row ASC, seats.seat_col ASC SEPARATOR ', ') as seat_positions,
                p.amount as payment_amount,
                p.payment_method,
                p.payment_reference,
                p.status as payment_status,
                p.notes as payment_notes
            FROM {$this->table} b
            LEFT JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
            LEFT JOIN routes r ON s.route_id_fk = r.route_id_pk
            LEFT JOIN drivers d ON s.driver_id_fk = d.driver_id_pk
            LEFT JOIN vans v ON s.van_id_fk = v.van_id_pk
            LEFT JOIN seats ON b.seat_id_fk = seats.seat_id_pk
            LEFT JOIN payments p ON p.book_id_fk = (
                SELECT MIN(b2.book_id_pk)
                FROM bookings b2
                WHERE b2.reference_code = b.reference_code AND b2.user_id_fk = :payment_user_id
            )
            WHERE b.user_id_fk = :user_id AND b.reference_code = :reference_code
            GROUP BY b.reference_code, r.origin, r.destination, r.fare, s.departure_date,
                     s.departure_time, s.estimated_arrival_at, s.trip_status, s.arrived_at, d.full_name, v.plate_number,
                     v.model, p.amount, p.payment_method, p.payment_reference, p.status, p.notes
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':payment_user_id' => $userId,
            ':reference_code' => $reference,
        ]);

        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            return [];
        }

        $notes = [];
        if (!empty($booking['payment_notes'])) {
            $decoded = json_decode($booking['payment_notes'], true);
            if (is_array($decoded)) {
                $notes = $decoded;
            }
        }

        $booking['passenger_name'] = $notes['passenger_name'] ?? '';
        $booking['contact_number'] = $notes['contact_number'] ?? '';
        $booking['passenger_type'] = $notes['passenger_type'] ?? 'regular';
        $booking['discount_amount'] = $notes['discount_amount'] ?? 0;
        $booking['convenience_fee'] = $notes['convenience_fee'] ?? 0;
        $booking['cash_fee'] = $notes['cash_fee'] ?? 0;
        $booking['base_total'] = $notes['base_total'] ?? 0;
        $booking['subtotal'] = $notes['subtotal'] ?? 0;

        return $booking;
    }
}
