<?php
class Payments
{
    private $conn;
    private $table = 'payments';

    public $id;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function GetAllPayments(): array
    {
        $this->EnsureModernStatusColumn();
        $this->SyncPaymentsWithBookings();

        $stmt = $this->conn->prepare("
            SELECT
                p.payment_id_pk,
                p.amount,
                p.payment_method,
                p.payment_reference,
                p.status,
                p.paid_at,
                p.created_at,
                p.processed_by,
                p.notes,
                b.reference_code                                                         AS booking_ref,
                b.status                                                                 AS booking_status,
                CONCAT(processor.firstname, ' ', processor.lastname)                    AS processed_by_name,
                COALESCE(bg.seats_count, 1)                                             AS seats_count,
                COALESCE(bg.seat_numbers, '')                                           AS seat_numbers,
                CONCAT(u.firstname, ' ', u.lastname)                                    AS user_name,
                u.email                                                                  AS user_email,
                u.contact_number                                                         AS user_phone,
                CONCAT(COALESCE(r.origin, 'N/A'), ' → ', COALESCE(r.destination, 'N/A')) AS route_display,
                s.departure_date
            FROM {$this->table} p
            LEFT JOIN bookings  b ON p.book_id_fk      = b.book_id_pk
            LEFT JOIN users     u ON b.user_id_fk       = u.user_id_pk
            LEFT JOIN users     processor ON p.processed_by = processor.user_id_pk
            LEFT JOIN schedules s ON b.schedule_id_fk   = s.schedule_id_pk
            LEFT JOIN routes    r ON s.route_id_fk      = r.route_id_pk
            LEFT JOIN (
                SELECT
                    b2.reference_code,
                    COUNT(*) AS seats_count,
                    GROUP_CONCAT(seats2.seat_number ORDER BY seats2.seat_row ASC, seats2.seat_col ASC SEPARATOR ', ') AS seat_numbers
                FROM bookings b2
                LEFT JOIN seats seats2 ON b2.seat_id_fk = seats2.seat_id_pk
                GROUP BY b2.reference_code
            ) bg ON bg.reference_code = b.reference_code
            ORDER BY p.created_at DESC
        ");
        $stmt->execute();
        return $this->sortAdminPayments($this->collapsePaymentRows($stmt->fetchAll(PDO::FETCH_ASSOC), 'booking_ref'));
    }

    public function GetPaymentByID(): array
    {
        if (!$this->id) return [];

        $this->EnsureModernStatusColumn();
        $this->SyncPaymentsWithBookings();

        $stmt = $this->conn->prepare("
            SELECT
                p.payment_id_pk,
                p.amount,
                p.payment_method,
                p.payment_reference,
                p.status,
                p.paid_at,
                p.created_at,
                p.processed_by,
                p.notes,
                b.reference_code                                                         AS booking_ref,
                b.status                                                                 AS booking_status,
                CONCAT(processor.firstname, ' ', processor.lastname)                    AS processed_by_name,
                CONCAT(u.firstname, ' ', u.lastname)                                    AS user_name,
                u.email                                                                  AS user_email,
                u.contact_number                                                         AS user_phone,
                CONCAT(COALESCE(r.origin, 'N/A'), ' → ', COALESCE(r.destination, 'N/A')) AS route_display,
                s.departure_date,
                s.departure_time
            FROM {$this->table} p
            LEFT JOIN bookings  b ON p.book_id_fk      = b.book_id_pk
            LEFT JOIN users     u ON b.user_id_fk       = u.user_id_pk
            LEFT JOIN users     processor ON p.processed_by = processor.user_id_pk
            LEFT JOIN schedules s ON b.schedule_id_fk   = s.schedule_id_pk
            LEFT JOIN routes    r ON s.route_id_fk      = r.route_id_pk
            WHERE p.payment_id_pk = :id
        ");
        $stmt->execute([':id' => $this->id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function GetPaymentsByUser(int $userId): array
    {
        $this->EnsureModernStatusColumn();
        $this->SyncPaymentsWithBookings();

        $stmt = $this->conn->prepare("
            SELECT
                p.payment_id_pk,
                p.amount,
                p.payment_method,
                p.payment_reference,
                p.status,
                p.paid_at,
                p.created_at,
                p.processed_by,
                p.notes,
                b.reference_code,
                b.status AS booking_status,
                CONCAT(processor.firstname, ' ', processor.lastname) AS processed_by_name,
                b.book_id_pk,
                CONCAT(r.origin, ' -> ', r.destination) AS route_display,
                r.origin,
                r.destination,
                s.departure_date,
                s.departure_time,
                COALESCE(bg.seats_count, 1) AS seats_count,
                COALESCE(bg.seat_numbers, seats.seat_number) AS seat_numbers
            FROM {$this->table} p
            INNER JOIN bookings b ON p.book_id_fk = b.book_id_pk
            LEFT JOIN users processor ON p.processed_by = processor.user_id_pk
            INNER JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
            INNER JOIN routes r ON s.route_id_fk = r.route_id_pk
            LEFT JOIN seats ON b.seat_id_fk = seats.seat_id_pk
            LEFT JOIN (
                SELECT
                    b2.reference_code,
                    COUNT(*) AS seats_count,
                    GROUP_CONCAT(seats2.seat_number ORDER BY seats2.seat_row ASC, seats2.seat_col ASC SEPARATOR ', ') AS seat_numbers
                FROM bookings b2
                LEFT JOIN seats seats2 ON b2.seat_id_fk = seats2.seat_id_pk
                WHERE b2.user_id_fk = :user_id_group
                GROUP BY b2.reference_code
            ) bg ON bg.reference_code = b.reference_code
            WHERE b.user_id_fk = :user_id
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([
            ':user_id_group' => $userId,
            ':user_id' => $userId,
        ]);

        return array_map([$this, 'normalizeUserPayment'], $this->collapsePaymentRows($stmt->fetchAll(PDO::FETCH_ASSOC), 'reference_code'));
    }

    private function collapsePaymentRows(array $rows, string $referenceKey): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $key = ($row[$referenceKey] ?? '') . '|' . ($row['payment_reference'] ?? '') . '|' . ($row['payment_method'] ?? '');
            if ($key === '||') {
                $key = 'payment-' . ($row['payment_id_pk'] ?? count($grouped));
            }

            if (!isset($grouped[$key])) {
                $grouped[$key] = $row;
                continue;
            }

            $current = $grouped[$key];
            if ($this->statusRank((string) ($row['status'] ?? '')) > $this->statusRank((string) ($current['status'] ?? ''))) {
                $row['amount'] = max((float) ($row['amount'] ?? 0), (float) ($current['amount'] ?? 0));
                $row['seats_count'] = max((int) ($row['seats_count'] ?? 1), (int) ($current['seats_count'] ?? 1));
                $row['seat_numbers'] = $row['seat_numbers'] ?: ($current['seat_numbers'] ?? '');
                $grouped[$key] = $row;
                continue;
            }

            $grouped[$key]['amount'] = max((float) ($current['amount'] ?? 0), (float) ($row['amount'] ?? 0));
            $grouped[$key]['seats_count'] = max((int) ($current['seats_count'] ?? 1), (int) ($row['seats_count'] ?? 1));
            if (empty($grouped[$key]['seat_numbers']) && !empty($row['seat_numbers'])) {
                $grouped[$key]['seat_numbers'] = $row['seat_numbers'];
            }
            if (empty($grouped[$key]['notes']) && !empty($row['notes'])) {
                $grouped[$key]['notes'] = $row['notes'];
            }
        }

        return array_values($grouped);
    }

    private function statusRank(string $status): int
    {
        return [
            'refund_requested' => 5,
            'refunded' => 4,
            'paid' => 3,
            'pending_cash' => 2,
            'cash_unpaid' => 2,
            'unpaid' => 2,
            'pending' => 2,
            'failed' => 1,
            'cancelled' => 1,
        ][$status] ?? 0;
    }

    private function sortAdminPayments(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            $rankA = $this->adminStatusSortRank($a);
            $rankB = $this->adminStatusSortRank($b);

            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            $timeA = strtotime((string) (($a['paid_at'] ?? '') ?: ($a['created_at'] ?? '') ?: ($a['departure_date'] ?? '') ?: '1970-01-01')) ?: 0;
            $timeB = strtotime((string) (($b['paid_at'] ?? '') ?: ($b['created_at'] ?? '') ?: ($b['departure_date'] ?? '') ?: '1970-01-01')) ?: 0;

            if ($timeA !== $timeB) {
                return $timeB <=> $timeA;
            }

            return ((int) ($b['payment_id_pk'] ?? 0)) <=> ((int) ($a['payment_id_pk'] ?? 0));
        });

        return $rows;
    }

    private function adminStatusSortRank(array $row): int
    {
        $status = strtolower((string) ($row['status'] ?? ''));
        $bookingStatus = strtolower((string) ($row['booking_status'] ?? ''));

        if (in_array($status, ['pending', 'pending_cash', 'cash_unpaid', 'unpaid'], true) && $bookingStatus !== 'rejected') return 1;
        if ($status === 'paid' && $bookingStatus !== 'rejected') return 2;
        if ($bookingStatus === 'rejected') return 3;
        if ($status === 'refund_requested') return 4;
        if ($status === 'cancelled') return 5;
        if ($status === 'refunded') return 6;

        return 7;
    }

    private function normalizeUserPayment(array $row): array
    {
        $notes = [];
        if (!empty($row['notes'])) {
            $decoded = json_decode($row['notes'], true);
            if (is_array($decoded)) {
                $notes = $decoded;
            }
        }

        return [
            'payment_id_pk' => (int) $row['payment_id_pk'],
            'book_id_pk' => (int) $row['book_id_pk'],
            'reference_code' => $row['reference_code'],
            'booking_status' => $row['booking_status'],
            'route_display' => $row['route_display'],
            'origin' => $row['origin'],
            'destination' => $row['destination'],
            'departure_date' => $row['departure_date'],
            'departure_time' => $row['departure_time'],
            'seats_count' => (int) ($notes['seats_count'] ?? $row['seats_count'] ?? 1),
            'seat_numbers' => $notes['seat_numbers'] ?? $row['seat_numbers'],
            'passenger_name' => $notes['passenger_name'] ?? '',
            'contact_number' => $notes['contact_number'] ?? '',
            'passenger_type' => $notes['passenger_type'] ?? 'regular',
            'passengers' => $notes['passengers'] ?? [],
            'discount_rate' => (float) ($notes['discount_rate'] ?? 0),
            'discount_amount' => (float) ($notes['discount_amount'] ?? 0),
            'convenience_fee' => (float) ($notes['convenience_fee'] ?? 0),
            'amount' => (float) $row['amount'],
            'payment_method' => $row['payment_method'],
            'payment_reference' => $row['payment_reference'],
            'status' => $row['status'],
            'paid_at' => $row['paid_at'],
            'created_at' => $row['created_at'],
            'processed_by' => $row['processed_by'] ?? null,
            'processed_by_name' => $row['processed_by_name'] ?? '',
            'notes' => $notes,
        ];
    }

    private function SyncPaymentsWithBookings(): void
    {
        $this->conn->exec("
            UPDATE {$this->table} p
            INNER JOIN bookings b ON p.book_id_fk = b.book_id_pk
            SET p.status = 'cancelled',
                p.paid_at = NULL
            WHERE b.status IN ('rejected', 'cancelled')
              AND p.status IN ('pending', 'unpaid', 'pending_cash', 'cash_unpaid')
        ");

        $this->conn->exec("
            UPDATE {$this->table} p
            INNER JOIN bookings b ON p.book_id_fk = b.book_id_pk
            SET p.status = 'refund_requested'
            WHERE b.status = 'cancelled'
              AND p.status = 'paid'
              AND p.payment_method <> 'cash'
        ");
    }

    public function UpdateStatus(string $status, array $note = [], ?int $processedBy = null): array
    {
        $allowedStatuses = ['unpaid', 'pending', 'paid', 'failed', 'cancelled', 'refund_requested', 'refunded', 'pending_cash', 'cash_unpaid'];
        if (!$this->id || !in_array($status, $allowedStatuses, true)) {
            return ['success' => false, 'message' => 'Invalid payment status.'];
        }

        $this->EnsureModernStatusColumn();

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                SELECT p.book_id_fk, p.payment_method, b.reference_code
                FROM {$this->table} p
                LEFT JOIN bookings b ON p.book_id_fk = b.book_id_pk
                WHERE p.payment_id_pk = :id
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':id' => $this->id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Payment not found.'];
            }

            if (($payment['payment_method'] ?? '') === 'cash' && in_array($status, ['refund_requested', 'refunded'], true)) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Cash payments cannot be refunded because no online payment was collected.'];
            }

            $notes = $this->mergeNotes($this->getPaymentNotes((int) $this->id), $note);

            $processedBy = $processedBy && $processedBy > 0 ? $processedBy : null;

            $update = $this->conn->prepare("
                UPDATE {$this->table}
                SET status = :status,
                    paid_at = CASE
                        WHEN :paid_status = 'paid' THEN COALESCE(paid_at, NOW())
                        WHEN :clear_status IN ('pending', 'cancelled', 'unpaid', 'pending_cash', 'cash_unpaid', 'failed') THEN NULL
                        ELSE paid_at
                    END,
                    processed_by = COALESCE(:processed_by, processed_by),
                    notes = :notes
                WHERE payment_id_pk = :id
            ");
            $update->execute([
                ':status' => $status,
                ':paid_status' => $status,
                ':clear_status' => $status,
                ':processed_by' => $processedBy,
                ':notes' => json_encode($notes),
                ':id' => $this->id,
            ]);

            if (in_array($status, ['cancelled', 'refunded'], true) && !empty($payment['reference_code'])) {
                $cancel = $this->conn->prepare("
                    UPDATE bookings
                    SET status = 'cancelled',
                        updated_at = NOW()
                    WHERE reference_code = :reference_code
                      AND status NOT IN ('completed', 'cancelled', 'rejected')
                ");
                $cancel->execute([':reference_code' => $payment['reference_code']]);
            }

            $this->conn->commit();
            return ['success' => true];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function RequestRefund(int $paymentId, int $userId, string $reason, string $customNote = ''): array
    {
        $this->EnsureModernStatusColumn();

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                SELECT p.payment_id_pk, p.payment_method, p.status, p.notes, b.reference_code, b.status AS booking_status
                FROM {$this->table} p
                INNER JOIN bookings b ON p.book_id_fk = b.book_id_pk
                WHERE p.payment_id_pk = :payment_id
                  AND b.user_id_fk = :user_id
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([
                ':payment_id' => $paymentId,
                ':user_id' => $userId,
            ]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Payment not found.'];
            }

            if (($payment['payment_method'] ?? '') === 'cash') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Cash bookings are not refundable online.'];
            }

            if ($payment['status'] !== 'paid') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only paid payments can be requested for refund.'];
            }

            if ($payment['booking_status'] !== 'approved') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only approved active bookings can be requested for refund.'];
            }

            $notes = $this->mergeNotes($this->decodeNotes($payment['notes'] ?? ''), [
                'type' => 'refund_requested',
                'actor' => 'user',
                'reason' => $reason,
                'user_note' => $customNote,
                'custom_note' => $customNote,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $updatePayment = $this->conn->prepare("
                UPDATE {$this->table}
                SET status = 'refund_requested',
                    notes = :notes
                WHERE payment_id_pk = :payment_id
            ");
            $updatePayment->execute([
                ':notes' => json_encode($notes),
                ':payment_id' => $paymentId,
            ]);

            $this->conn->commit();
            return ['success' => true, 'message' => 'Refund request submitted. Your booking remains active while admin reviews it.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => 'Unable to request refund right now.'];
        }
    }

    public function CancelRefundRequest(int $paymentId, int $userId): array
    {
        $this->EnsureModernStatusColumn();

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                SELECT p.payment_id_pk, p.status, p.notes, b.reference_code
                FROM {$this->table} p
                INNER JOIN bookings b ON p.book_id_fk = b.book_id_pk
                WHERE p.payment_id_pk = :payment_id
                  AND b.user_id_fk = :user_id
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([
                ':payment_id' => $paymentId,
                ':user_id' => $userId,
            ]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Payment not found.'];
            }

            if ($payment['status'] !== 'refund_requested') {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Only active refund requests can be cancelled.'];
            }

            $notes = $this->mergeNotes($this->decodeNotes($payment['notes'] ?? ''), [
                'type' => 'refund_cancelled',
                'actor' => 'user',
                'reason' => 'user_cancelled',
                'user_note' => '',
                'custom_note' => '',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $updatePayment = $this->conn->prepare("
                UPDATE {$this->table}
                SET status = 'paid',
                    paid_at = COALESCE(paid_at, created_at, NOW()),
                    notes = :notes
                WHERE payment_id_pk = :payment_id
            ");
            $updatePayment->execute([
                ':notes' => json_encode($notes),
                ':payment_id' => $paymentId,
            ]);

            $this->conn->commit();
            return ['success' => true, 'message' => 'Refund request cancelled.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => 'Unable to cancel refund request right now.'];
        }
    }

    public function ReviewRefund(string $decision, string $reason, string $customNote = '', ?int $processedBy = null): array
    {
        if (!$this->id || !in_array($decision, ['approve', 'reject'], true)) {
            return ['success' => false, 'message' => 'Invalid refund review.'];
        }

        $targetStatus = $decision === 'approve' ? 'refunded' : 'paid';
        return $this->UpdateStatus($targetStatus, [
            'type' => $decision === 'approve' ? 'refund_approved' : 'refund_rejected',
            'actor' => 'admin',
            'decision' => $decision,
            'reason' => $reason,
            'admin_note' => $customNote,
            'custom_note' => $customNote,
            'created_at' => date('Y-m-d H:i:s'),
        ], $processedBy);
    }

    private function getPaymentNotes(int $paymentId): array
    {
        $stmt = $this->conn->prepare("SELECT notes FROM {$this->table} WHERE payment_id_pk = :id LIMIT 1");
        $stmt->execute([':id' => $paymentId]);
        return $this->decodeNotes((string) $stmt->fetchColumn());
    }

    private function decodeNotes(string $raw): array
    {
        if ($raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : ['legacy_note' => $raw];
    }

    private function mergeNotes(array $notes, array $event): array
    {
        if (!$event) return $notes;
        $notes['refund_history'] = is_array($notes['refund_history'] ?? null) ? $notes['refund_history'] : [];
        $notes['refund_history'][] = $event;
        $notes['refund'] = $event;
        return $notes;
    }

    private function EnsureModernStatusColumn(): void
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT DATA_TYPE, COLUMN_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'payments'
                  AND COLUMN_NAME = 'status'
                LIMIT 1
            ");
            $stmt->execute();
            $column = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($column && strtolower($column['DATA_TYPE'] ?? '') === 'enum') {
                $this->conn->exec("ALTER TABLE {$this->table} MODIFY status VARCHAR(32) NOT NULL DEFAULT 'pending'");
            }
        } catch (Throwable $e) {
            error_log('[Payments::EnsureModernStatusColumn] ' . $e->getMessage());
        }
    }
}
