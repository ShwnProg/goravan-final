<?php
class UserNotifications
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function GetUserNotifications(int $userId, int $limit = 20): array
    {
        $items = array_merge(
            $this->bookingStatusNotifications($userId),
            $this->tripReminderNotifications($userId),
            $this->tripMovementNotifications($userId),
            $this->verificationNotifications($userId),
            $this->paymentNotifications($userId),
            $this->newScheduleNotifications($userId)
        );

        usort($items, function ($a, $b) {
            return strtotime($b['time']) <=> strtotime($a['time']);
        });

        return array_slice($items, 0, $limit);
    }

    private function bookingStatusNotifications(int $userId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    MIN(b.book_id_pk) AS book_id_pk,
                    b.reference_code,
                    CASE
                        WHEN SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 'pending'
                        WHEN SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) > 0 THEN 'approved'
                        WHEN SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
                        ELSE 'cancelled'
                    END AS status,
                    MAX(b.updated_at) AS event_time,
                    CONCAT(COALESCE(r.origin, 'Route'), ' -> ', COALESCE(r.destination, 'Destination')) AS route_display,
                    s.departure_date,
                    s.departure_time
                FROM bookings b
                LEFT JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
                LEFT JOIN routes r ON s.route_id_fk = r.route_id_pk
                WHERE b.user_id_fk = :user_id
                  AND b.status IN ('approved', 'rejected', 'cancelled')
                GROUP BY b.reference_code, r.origin, r.destination, s.departure_date, s.departure_time
                ORDER BY MAX(b.updated_at) DESC
                LIMIT 12
            ");
            $stmt->execute([':user_id' => $userId]);

            return array_map(function ($row) {
                $status = strtolower($row['status'] ?? '');
                $labels = [
                    'approved' => ['Booking approved', 'fa-solid fa-circle-check', 'booking approved'],
                    'rejected' => ['Booking rejected', 'fa-solid fa-circle-xmark', 'booking rejected'],
                    'cancelled' => ['Booking cancelled', 'fa-solid fa-ban', 'booking cancelled'],
                ];
                $meta = $labels[$status] ?? ['Booking updated', 'fa-solid fa-ticket', 'booking'];

                $tripTime = trim(($row['departure_date'] ?? '') . ' ' . ($row['departure_time'] ?? ''));
                $message = trim(($row['reference_code'] ?? '') . ' - ' . ($row['route_display'] ?? 'Your trip'));
                if ($tripTime !== '') {
                    $message .= ' on ' . $this->formatTripTime($tripTime);
                }

                return $this->item(
                    'booking-' . $row['reference_code'] . '-' . $status,
                    'booking',
                    $meta[0],
                    $message,
                    $row['event_time'],
                    $meta[1],
                    'booking-detail.php?id=' . urlencode(encrypt((string) $row['book_id_pk'])),
                    $meta[2]
                );
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            error_log('[UserNotifications::bookingStatusNotifications] ' . $e->getMessage());
            return [];
        }
    }

    private function tripReminderNotifications(int $userId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    MIN(b.book_id_pk) AS book_id_pk,
                    b.reference_code,
                    CONCAT(COALESCE(r.origin, 'Route'), ' -> ', COALESCE(r.destination, 'Destination')) AS route_display,
                    s.departure_date,
                    s.departure_time,
                    CONCAT(s.departure_date, ' ', s.departure_time) AS event_time
                FROM bookings b
                INNER JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
                INNER JOIN routes r ON s.route_id_fk = r.route_id_pk
                WHERE b.user_id_fk = :user_id
                  AND b.status = 'approved'
                  AND s.trip_status IN ('not_departed', 'boarding')
                  AND CONCAT(s.departure_date, ' ', s.departure_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
                GROUP BY b.reference_code, r.origin, r.destination, s.departure_date, s.departure_time
                ORDER BY event_time ASC
                LIMIT 5
            ");
            $stmt->execute([':user_id' => $userId]);

            return array_map(function ($row) {
                return $this->item(
                    'reminder-' . $row['reference_code'],
                    'reminder',
                    'Trip reminder',
                    ($row['route_display'] ?? 'Your trip') . ' starts ' . $this->formatTripTime($row['event_time']),
                    date('Y-m-d H:i:s'),
                    'fa-solid fa-clock',
                    'booking-detail.php?id=' . urlencode(encrypt((string) $row['book_id_pk'])),
                    'reminder'
                );
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            error_log('[UserNotifications::tripReminderNotifications] ' . $e->getMessage());
            return [];
        }
    }

    private function verificationNotifications(int $userId): array
    {
        try {
            $reasonSelect = $this->columnExists('verification_documents', 'rejection_reason')
                ? ', rejection_reason'
                : ', NULL AS rejection_reason';

            $stmt = $this->conn->prepare("
                SELECT document_id_pk, document_type, status, COALESCE(reviewed_at, submitted_at) AS event_time $reasonSelect
                FROM verification_documents
                WHERE user_id_fk = :user_id
                  AND status IN ('approved', 'rejected')
                ORDER BY COALESCE(reviewed_at, submitted_at) DESC, document_id_pk DESC
                LIMIT 8
            ");
            $stmt->execute([':user_id' => $userId]);

            return array_map(function ($row) {
                $approved = $row['status'] === 'approved';
                $type = ucfirst($row['document_type'] ?? 'Document');
                $message = $approved
                    ? $type . ' verification approved.'
                    : $type . ' verification rejected.' . (!empty($row['rejection_reason']) ? ' ' . $row['rejection_reason'] : '');

                return $this->item(
                    'verification-' . $row['document_id_pk'] . '-' . $row['status'],
                    'verification',
                    $approved ? 'Verification approved' : 'Verification rejected',
                    $message,
                    $row['event_time'],
                    $approved ? 'fa-solid fa-id-card-clip' : 'fa-solid fa-triangle-exclamation',
                    'profile.php',
                    $approved ? 'verification approved' : 'verification rejected'
                );
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            error_log('[UserNotifications::verificationNotifications] ' . $e->getMessage());
            return [];
        }
    }

    private function tripMovementNotifications(int $userId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    MIN(b.book_id_pk) AS book_id_pk,
                    s.schedule_id_pk,
                    b.reference_code,
                    s.trip_status,
                    s.departed_at,
                    s.arrived_at,
                    s.completed_at,
                    CONCAT(COALESCE(r.origin, 'Route'), ' -> ', COALESCE(r.destination, 'Destination')) AS route_display
                FROM bookings b
                INNER JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
                INNER JOIN routes r ON s.route_id_fk = r.route_id_pk
                WHERE b.user_id_fk = :user_id
                  AND b.status IN ('approved', 'completed')
                  AND s.trip_status IN ('departed', 'arrived', 'completed')
                GROUP BY s.schedule_id_pk, s.trip_status, s.departed_at, s.arrived_at, s.completed_at, r.origin, r.destination
                ORDER BY COALESCE(s.completed_at, s.arrived_at, s.departed_at) DESC
                LIMIT 10
            ");
            $stmt->execute([':user_id' => $userId]);

            return array_map(function ($row) {
                $status = strtolower($row['trip_status'] ?? '');
                $meta = [
                    'departed' => ['Your trip has departed.', 'fa-solid fa-route', $row['departed_at'] ?? null],
                    'arrived' => ['Your trip has arrived.', 'fa-solid fa-location-dot', $row['arrived_at'] ?? null],
                    'completed' => ['Your trip is completed.', 'fa-solid fa-flag-checkered', $row['completed_at'] ?? null],
                ][$status] ?? ['Your trip was updated.', 'fa-solid fa-route', null];

                return $this->item(
                    'trip-' . $row['schedule_id_pk'] . '-' . $status,
                    'trip',
                    ucwords(str_replace('_', ' ', $status ?: 'Trip update')),
                    $meta[0] . ' ' . ($row['route_display'] ?? ''),
                    $meta[2] ?: date('Y-m-d H:i:s'),
                    $meta[1],
                    'booking-detail.php?id=' . urlencode(encrypt((string) $row['book_id_pk'])),
                    'trip status'
                );
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            error_log('[UserNotifications::tripMovementNotifications] ' . $e->getMessage());
            return [];
        }
    }

    private function paymentNotifications(int $userId): array
    {
        try {
            $timeColumn = $this->columnExists('payments', 'paid_at') ? 'COALESCE(p.paid_at, p.created_at)' : 'COALESCE(p.reviewed_at, p.uploaded_at)';
            $amountSelect = $this->columnExists('payments', 'amount') ? 'p.amount' : 'NULL AS amount';
            $statusFilter = $this->columnExists('payments', 'paid_at') ? "p.status IN ('paid', 'approved', 'refund_requested', 'refunded')" : "p.status = 'approved'";

            $stmt = $this->conn->prepare("
                SELECT
                    p.payment_id_pk,
                    p.status,
                    $amountSelect,
                    $timeColumn AS event_time,
                    b.reference_code,
                    CONCAT(COALESCE(r.origin, 'Route'), ' -> ', COALESCE(r.destination, 'Destination')) AS route_display
                FROM payments p
                INNER JOIN bookings b ON p.book_id_fk = b.book_id_pk
                LEFT JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
                LEFT JOIN routes r ON s.route_id_fk = r.route_id_pk
                WHERE b.user_id_fk = :user_id
                  AND $statusFilter
                ORDER BY event_time DESC
                LIMIT 8
            ");
            $stmt->execute([':user_id' => $userId]);

            return array_map(function ($row) {
                $amount = $row['amount'] !== null ? ' Payment: PHP ' . number_format((float) $row['amount'], 2) . '.' : '';
                $status = strtolower($row['status'] ?? 'paid');
                $title = [
                    'refund_requested' => 'Refund requested',
                    'refunded' => 'Payment refunded',
                ][$status] ?? 'Payment successful';
                $icon = [
                    'refund_requested' => 'fa-solid fa-rotate-left',
                    'refunded' => 'fa-solid fa-circle-check',
                ][$status] ?? 'fa-solid fa-receipt';
                $tone = [
                    'refund_requested' => 'payment reminder',
                    'refunded' => 'payment successful',
                ][$status] ?? 'payment successful';
                return $this->item(
                    'payment-' . $row['payment_id_pk'] . '-' . $status,
                    'payment',
                    $title,
                    ($row['reference_code'] ?? 'Booking') . ' - ' . ($row['route_display'] ?? 'Trip') . '.' . $amount,
                    $row['event_time'],
                    $icon,
                    'my-payments.php',
                    $tone
                );
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            error_log('[UserNotifications::paymentNotifications] ' . $e->getMessage());
            return [];
        }
    }

    private function newScheduleNotifications(int $userId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    s.route_id_fk,
                    MAX(s.created_at) AS event_time,
                    COUNT(*) AS schedule_count,
                    CONCAT(COALESCE(r.origin, 'Route'), ' -> ', COALESCE(r.destination, 'Destination')) AS route_display,
                    r.origin,
                    r.destination
                FROM schedules s
                INNER JOIN routes r ON s.route_id_fk = r.route_id_pk
                WHERE s.trip_status IN ('not_departed', 'boarding')
                  AND CONCAT(s.departure_date, ' ', s.departure_time) >= NOW()
                  AND s.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                  AND s.route_id_fk IN (
                      SELECT DISTINCT bs.route_id_fk
                      FROM bookings b
                      INNER JOIN schedules bs ON b.schedule_id_fk = bs.schedule_id_pk
                      WHERE b.user_id_fk = :user_id
                  )
                GROUP BY s.route_id_fk, r.origin, r.destination
                ORDER BY event_time DESC
                LIMIT 3
            ");
            $stmt->execute([':user_id' => $userId]);

            return array_map(function ($row) {
                $count = max(1, (int) ($row['schedule_count'] ?? 1));
                $route = $row['route_display'] ?? 'your route';
                $message = $count === 1
                    ? 'A new schedule is available for ' . $route . '.'
                    : $count . ' new schedules are available for ' . $route . '.';
                $url = 'schedule.php?from=' . urlencode((string) ($row['origin'] ?? '')) . '&to=' . urlencode((string) ($row['destination'] ?? ''));

                return $this->item(
                    'schedule-route-' . $row['route_id_fk'],
                    'schedule',
                    'New schedules available',
                    $message,
                    $row['event_time'],
                    'fa-solid fa-calendar-plus',
                    $url,
                    'new schedule'
                );
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Throwable $e) {
            error_log('[UserNotifications::newScheduleNotifications] ' . $e->getMessage());
            return [];
        }
    }

    private function item(string $id, string $type, string $title, string $message, ?string $time, string $icon, string $url, string $tone): array
    {
        $time = $time ?: date('Y-m-d H:i:s');
        return [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'time' => date('c', strtotime($time)),
            'time_label' => date('M j, Y g:i A', strtotime($time)),
            'icon' => $icon,
            'url' => $url,
            'tone' => $tone,
        ];
    }

    private function formatTripTime(string $value): string
    {
        $ts = strtotime($value);
        return $ts ? date('M j, g:i A', $ts) : $value;
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
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
        } catch (Throwable $e) {
            return false;
        }
    }
}
?>
