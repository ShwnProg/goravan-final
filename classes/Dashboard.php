<?php
class Dashboard
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ── BOOKING SUMMARY ───────────────────────────────────────────────────────
    public function GetBookingSummary(): array
    {
        $stmt = $this->conn->query("
            SELECT
                COUNT(*)                                                   AS total_bookings,
                COALESCE(SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END), 0) AS pending,
                COALESCE(SUM(CASE WHEN status = 'approved'  THEN 1 ELSE 0 END), 0) AS approved,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) AS completed,
                COALESCE(SUM(CASE WHEN status = 'rejected'  THEN 1 ELSE 0 END), 0) AS rejected,
                COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled
            FROM bookings
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ── SCHEDULE SUMMARY ──────────────────────────────────────────────────────
    public function GetScheduleSummary(): array
    {
        $stmt = $this->conn->query("
            SELECT
                COUNT(*)                                                               AS total_schedules,
                COALESCE(SUM(CASE WHEN trip_status != 'cancelled' THEN 1 ELSE 0 END), 0) AS active_schedules,
                COALESCE(SUM(CASE WHEN trip_status IN ('not_departed', 'boarding') THEN 1 ELSE 0 END), 0) AS not_departed,
                COALESCE(SUM(CASE WHEN trip_status IN ('not_departed', 'boarding') THEN 1 ELSE 0 END), 0) AS boarding,
                COALESCE(SUM(CASE WHEN trip_status = 'departed'  THEN 1 ELSE 0 END), 0) AS departed,
                COALESCE(SUM(CASE WHEN trip_status = 'arrived'   THEN 1 ELSE 0 END), 0) AS arrived,
                COALESCE(SUM(CASE WHEN trip_status = 'completed' THEN 1 ELSE 0 END), 0) AS completed,
                COALESCE(SUM(CASE WHEN trip_status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled,
                COALESCE(SUM(CASE WHEN trip_status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_trips
            FROM schedules
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ── TOTAL USERS ───────────────────────────────────────────────────────────
    public function GetTotalUsers(): int
    {
        return (int) $this->conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    }

    public function GetPaymentSummary(): array
    {
        if (!$this->ColumnExists('payments', 'amount')) {
            return $this->EmptyPaymentSummary();
        }

        try {
            $paidDate = $this->ColumnExists('payments', 'paid_at') ? 'paid_at' : 'NULL';
            $createdDate = $this->ColumnExists('payments', 'created_at') ? 'created_at' : 'NULL';
            $dateBasis = "COALESCE($paidDate, $createdDate)";

            $stmt = $this->conn->query("
                SELECT
                    COUNT(*) AS total_payments,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) AS paid,
                    COALESCE(SUM(CASE WHEN status = 'refund_requested' THEN 1 ELSE 0 END), 0) AS refund_requested,
                    COALESCE(SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END), 0) AS refunded,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS total_revenue,
                    COALESCE(SUM(CASE WHEN status = 'paid' AND DATE($dateBasis) = CURDATE() THEN amount ELSE 0 END), 0) AS revenue_today,
                    COALESCE(SUM(CASE WHEN status = 'paid' AND $dateBasis >= CURDATE() - INTERVAL 6 DAY THEN amount ELSE 0 END), 0) AS revenue_week
                FROM payments
            ");
            return array_merge($this->EmptyPaymentSummary(), $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
        } catch (PDOException $e) {
            error_log('[Dashboard::GetPaymentSummary] ' . $e->getMessage());
            return $this->EmptyPaymentSummary();
        }
    }

    public function GetVerificationSummary(): array
    {
        try {
            $stmt = $this->conn->query("
                SELECT
                    COUNT(*) AS total_documents,
                    COALESCE(SUM(CASE WHEN status = 'pending' OR status IS NULL THEN 1 ELSE 0 END), 0) AS pending,
                    COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved,
                    COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected
                FROM verification_documents
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total_documents' => 0,
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
            ];
        } catch (PDOException $e) {
            error_log('[Dashboard::GetVerificationSummary] ' . $e->getMessage());
            return [
                'total_documents' => 0,
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
            ];
        }
    }

    public function GetFleetSummary(): array
    {
        try {
            $stmt = $this->conn->query("
                SELECT
                    (SELECT COUNT(*) FROM vans) AS total_vans,
                    (SELECT COUNT(*) FROM vans WHERE status = 'active') AS active_vans,
                    (SELECT COUNT(*) FROM drivers) AS total_drivers,
                    (SELECT COUNT(*) FROM drivers WHERE status = 'active') AS active_drivers,
                    (SELECT COUNT(*) FROM routes) AS total_routes,
                    (SELECT COUNT(*) FROM routes WHERE is_active = 1) AS active_routes
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('[Dashboard::GetFleetSummary] ' . $e->getMessage());
            return [];
        }
    }

    public function GetMissingScheduleDates(int $days = 7): array
    {
        $days = max(1, min(30, $days));

        try {
            $stmt = $this->conn->prepare("
                SELECT DISTINCT departure_date
                FROM schedules
                WHERE trip_status NOT IN ('cancelled')
                  AND departure_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $days DAY)
                ORDER BY departure_date ASC
            ");
            $stmt->execute();
            $scheduled = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

            $missing = [];
            for ($i = 0; $i <= $days; $i++) {
                $date = date('Y-m-d', strtotime('+' . $i . ' day'));
                if (!isset($scheduled[$date])) {
                    $missing[] = $date;
                }
            }

            return $missing;
        } catch (PDOException $e) {
            error_log('[Dashboard::GetMissingScheduleDates] ' . $e->getMessage());
            return [];
        }
    }

    // ── SEATS BOOKED (approved bookings only) ────────────────────────────────
    public function GetSeatsBooked(): int
    {
        return (int) $this->conn->query("
            SELECT COUNT(*) FROM bookings WHERE status = 'approved'
        ")->fetchColumn();
    }

    // ── BOOKINGS BY STATUS (for pie/doughnut chart) ───────────────────────────
    public function GetBookingsByStatus(): array
    {
        $stmt = $this->conn->query("
            SELECT status, COUNT(*) AS total
            FROM bookings
            GROUP BY status
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── DAILY BOOKINGS — last 7 days (for bar chart) ─────────────────────────
    public function GetDailyBookings(): array
    {
        $stmt = $this->conn->query("
            SELECT DATE(created_at) AS date, COUNT(*) AS total
            FROM bookings
            WHERE created_at >= CURDATE() - INTERVAL 6 DAY
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── RECENT BOOKINGS — last 5 ─────────────────────────────────────────────
    public function GetRecentBookings(): array
    {
        $stmt = $this->conn->query("
            SELECT
                b.reference_code,
                b.status,
                b.created_at,
                u.firstname                               AS passenger,
                CONCAT(r.origin, ' → ', r.destination)  AS route_display
            FROM bookings b
            LEFT JOIN users     u ON b.user_id_fk     = u.user_id_pk
            LEFT JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
            LEFT JOIN routes    r ON s.route_id_fk    = r.route_id_pk
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function GetRecentActivity(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));

        $stmt = $this->conn->prepare("
            SELECT *
            FROM (
                SELECT
                    'booking' AS type,
                    CONCAT('Booking ', b.reference_code, ' is ', b.status) AS title,
                    CONCAT(COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.firstname, ''), ' ', COALESCE(u.lastname, ''))), ''), 'Passenger'), ' - ', COALESCE(CONCAT(r.origin, ' → ', r.destination), 'Route unavailable')) AS detail,
                    b.updated_at AS event_time,
                    CASE b.status
                        WHEN 'pending' THEN '#F97316'
                        WHEN 'approved' THEN '#16a34a'
                        WHEN 'rejected' THEN '#ef4444'
                        WHEN 'cancelled' THEN '#9ca3af'
                        WHEN 'completed' THEN '#2563eb'
                        ELSE '#64748b'
                    END AS color
                FROM bookings b
                LEFT JOIN users u ON b.user_id_fk = u.user_id_pk
                LEFT JOIN schedules s ON b.schedule_id_fk = s.schedule_id_pk
                LEFT JOIN routes r ON s.route_id_fk = r.route_id_pk

                UNION ALL

                SELECT
                    'schedule' AS type,
                    CONCAT('Schedule marked ', s.trip_status) AS title,
                    CONCAT(COALESCE(r.origin, 'Origin'), ' → ', COALESCE(r.destination, 'Destination')) AS detail,
                    COALESCE(s.updated_at, s.created_at) AS event_time,
                    CASE s.trip_status
                        WHEN 'not_departed' THEN '#F97316'
                        WHEN 'boarding' THEN '#F97316'
                        WHEN 'departed' THEN '#2563eb'
                        WHEN 'arrived' THEN '#16a34a'
                        WHEN 'completed' THEN '#16a34a'
                        WHEN 'cancelled' THEN '#9ca3af'
                        ELSE '#64748b'
                    END AS color
                FROM schedules s
                LEFT JOIN routes r ON s.route_id_fk = r.route_id_pk

                UNION ALL

                SELECT
                    'payment' AS type,
                    CONCAT('Payment ', p.status) AS title,
                    CONCAT(COALESCE(b.reference_code, 'Booking'), ' - ', COALESCE(p.payment_method, 'payment method')) AS detail,
                    COALESCE(p.paid_at, p.created_at) AS event_time,
                    CASE p.status
                        WHEN 'pending' THEN '#F97316'
                        WHEN 'paid' THEN '#16a34a'
                        WHEN 'rejected' THEN '#ef4444'
                        WHEN 'refund_requested' THEN '#2563eb'
                        WHEN 'refunded' THEN '#64748b'
                        ELSE '#64748b'
                    END AS color
                FROM payments p
                LEFT JOIN bookings b ON p.book_id_fk = b.book_id_pk

            ) activity
            WHERE event_time IS NOT NULL
            ORDER BY event_time DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(function ($row) {
            return [
                'type' => $row['type'],
                'title' => $row['title'],
                'detail' => $row['detail'],
                'time' => $this->FormatRelativeTime($row['event_time']),
                'timestamp' => $row['event_time'],
                'color' => $row['color'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function FormatRelativeTime(?string $time): string
    {
        if (!$time) {
            return 'Unknown time';
        }

        $timestamp = strtotime($time);
        if (!$timestamp) {
            return 'Unknown time';
        }

        $diff = max(0, time() - $timestamp);
        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return $minutes . ' min ago';
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours . ' hr' . ($hours === 1 ? '' : 's') . ' ago';
        }

        return date('M d, g:i A', $timestamp);
    }

    public function GetTotalPending()
    {
        $summary = $this->GetVerificationSummary();
        return (int) ($summary['pending'] ?? 0);
    }

    private function ColumnExists(string $table, string $column): bool
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
        } catch (PDOException $e) {
            error_log('[Dashboard::ColumnExists] ' . $e->getMessage());
            return false;
        }
    }

    private function EmptyPaymentSummary(): array
    {
        return [
            'total_payments' => 0,
            'pending' => 0,
            'paid' => 0,
            'refund_requested' => 0,
            'refunded' => 0,
            'total_revenue' => 0,
            'revenue_today' => 0,
            'revenue_week' => 0,
        ];
    }
}
?>
