<?php
class Vans
{
    private $conn  = null;
    private $table = "vans";

    public $id;
    public $plate_number;
    public $model;
    public $capacity;
    public $status;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ── READ ──────────────────────────────────────────────────

    public function GetAllVans(): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM {$this->table}
            ORDER BY
                CASE WHEN status = 'active' THEN 0 ELSE 1 END,
                van_id_pk DESC
        ");
        $stmt->execute();
        $vans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($vans)) return [];

        // Batch-load all seats grouped by van
        $allSeats = $this->conn->prepare("
            SELECT * FROM seats
            ORDER BY van_id_fk, seat_row ASC, seat_col ASC
        ");
        $allSeats->execute();
        $allSeats = $allSeats->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($allSeats as $seat) {
            $grouped[$seat['van_id_fk']][] = $seat;
        }

        foreach ($vans as &$v) {
            $v['seats'] = $grouped[$v['van_id_pk']] ?? [];
        }

        return $vans;
    }

    public function GetVanByID(): array
    {
        $stmt = $this->conn->prepare("
            SELECT * FROM {$this->table} WHERE van_id_pk = :id
        ");
        $stmt->execute([':id' => $this->id]);
        $vans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($vans)) return [];

        $seatStmt = $this->conn->prepare("
            SELECT * FROM seats
            WHERE van_id_fk = :id
            ORDER BY seat_row ASC, seat_col ASC
        ");
        $seatStmt->execute([':id' => $this->id]);
        $vans[0]['seats'] = $seatStmt->fetchAll(PDO::FETCH_ASSOC);

        return $vans;
    }

    public function IsPlateExist(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT van_id_pk FROM {$this->table}
            WHERE LOWER(plate_number) = LOWER(:plate_number)
        ");
        $stmt->execute([':plate_number' => $this->plate_number]);
        return (bool) $stmt->fetchColumn();
    }

    public function IsPlateExistExcept(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT van_id_pk FROM {$this->table}
            WHERE LOWER(plate_number) = LOWER(:plate_number)
              AND van_id_pk != :id
        ");
        $stmt->execute([
            ':plate_number' => $this->plate_number,
            ':id'           => $this->id,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    // ── CREATE ────────────────────────────────────────────────

    public function AddVan(): array
    {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table} (plate_number, model, capacity, status)
                VALUES (:plate_number, :model, :capacity, :status)
            ");
            $stmt->execute([
                ':plate_number' => strtoupper($this->plate_number),
                ':model'        => $this->model,
                ':capacity'     => $this->capacity,
                ':status'       => $this->status,
            ]);

            $vanId = (int) $this->conn->lastInsertId();
            $this->_generateSeats($vanId, $this->capacity);

            $this->conn->commit();
            return ['success' => true, 'id' => $vanId];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log('[Vans::AddVan] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to add van. Please check the details and try again.'];
        }
    }

    // ── UPDATE ────────────────────────────────────────────────

    public function EditVan(): array
    {
        try {
            $this->conn->beginTransaction();

            $current = $this->GetCurrentVanForUpdate();
            if (!$current) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Van not found.'];
            }

            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET plate_number = :plate_number,
                    model        = :model,
                    capacity     = :capacity,
                    status       = :status
                WHERE van_id_pk  = :id
            ");
            $stmt->execute([
                ':plate_number' => strtoupper($this->plate_number),
                ':model'        => $this->model,
                ':capacity'     => $this->capacity,
                ':status'       => $this->status,
                ':id'           => $this->id,
            ]);

            if ((int) $current['capacity'] !== (int) $this->capacity) {
                $seatResult = $this->SyncSeatsForCapacity((int) $this->id, (int) $this->capacity);
                if (!$seatResult['success']) {
                    $this->conn->rollBack();
                    return $seatResult;
                }
            }

            $this->conn->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log('[Vans::EditVan] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update record. Please check the details and try again.'];
        }
    }

    // ── DELETE ────────────────────────────────────────────────

    public function DeleteVan(): array
    {
        try {
            if (!$this->id) {
                return ['success' => false, 'message' => 'Invalid van.'];
            }

            if ($this->CountAssignedSchedules((int) $this->id) > 0 || $this->CountSeatBookings((int) $this->id) > 0) {
                return [
                    'success' => false,
                    'message' => 'This record cannot be deleted because it is already linked to existing schedules or bookings. You may deactivate it instead.'
                ];
            }

            $this->conn->beginTransaction();
            $this->_deleteSeats($this->id);

            $stmt = $this->conn->prepare("
                DELETE FROM {$this->table} WHERE van_id_pk = :id
            ");
            $stmt->execute([':id' => $this->id]);

            $this->conn->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log('[Vans::DeleteVan] ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'This record cannot be deleted because it is already linked to existing schedules or bookings. You may deactivate it instead.'
            ];
        }
    }

    // ── TOGGLE ────────────────────────────────────────────────

    public function ToggleVan(): array
    {
        try {
            if (!$this->VanExists((int) $this->id)) {
                return ['success' => false, 'message' => 'Van not found.'];
            }

            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET status = :status
                WHERE van_id_pk = :id
            ");
            $stmt->execute([
                ':status' => $this->status,
                ':id'     => $this->id,
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            error_log('[Vans::ToggleVan] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update van status. Please try again.'];
        }
    }

    // ── PRIVATE HELPERS ───────────────────────────────────────

    /**
     * Auto-generate seats for a van.
     * 2 columns always; rows = ceil(capacity / 2).
     * Labels: A1, A2, B1, B2 … up to capacity count.
     */
    private function _generateSeats(int $vanId, int $capacity): void
    {
        if ($capacity <= 0) return;

        $stmt = $this->conn->prepare("
            INSERT INTO seats (seat_number, seat_row, seat_col, van_id_fk)
            VALUES (:seat_number, :seat_row, :seat_col, :van_id)
        ");

        for ($index = 0; $index < $capacity; $index++) {
            $seat = $this->BuildSeatFromIndex($index);
            $stmt->execute([
                ':seat_number' => $seat['seat_number'],
                ':seat_row'    => $seat['seat_row'],
                ':seat_col'    => $seat['seat_col'],
                ':van_id'      => $vanId,
            ]);
        }
    }

    private function _deleteSeats(int $vanId): void
    {
        $stmt = $this->conn->prepare("DELETE FROM seats WHERE van_id_fk = :id");
        $stmt->execute([':id' => $vanId]);
    }

    private function CountAssignedSchedules(int $vanId): int
    {
        if (!$vanId) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM schedules
            WHERE van_id_fk = :id
        ");
        $stmt->execute([':id' => $vanId]);
        return (int) $stmt->fetchColumn();
    }

    private function GetCurrentVanForUpdate()
    {
        $stmt = $this->conn->prepare("
            SELECT van_id_pk, capacity
            FROM {$this->table}
            WHERE van_id_pk = :id
            FOR UPDATE
        ");
        $stmt->execute([':id' => $this->id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function SyncSeatsForCapacity(int $vanId, int $capacity): array
    {
        $seatStmt = $this->conn->prepare("
            SELECT seat_id_pk
            FROM seats
            WHERE van_id_fk = :id
            ORDER BY seat_row ASC, seat_col ASC, seat_id_pk ASC
        ");
        $seatStmt->execute([':id' => $vanId]);
        $seats = $seatStmt->fetchAll(PDO::FETCH_ASSOC);
        $currentCount = count($seats);

        if ($capacity > $currentCount) {
            $insert = $this->conn->prepare("
                INSERT INTO seats (seat_number, seat_row, seat_col, van_id_fk)
                VALUES (:seat_number, :seat_row, :seat_col, :van_id)
            ");

            for ($index = $currentCount; $index < $capacity; $index++) {
                $seat = $this->BuildSeatFromIndex($index);
                $insert->execute([
                    ':seat_number' => $seat['seat_number'],
                    ':seat_row' => $seat['seat_row'],
                    ':seat_col' => $seat['seat_col'],
                    ':van_id' => $vanId,
                ]);
            }

            return ['success' => true];
        }

        if ($capacity < $currentCount) {
            $excessSeats = array_slice($seats, $capacity);
            $excessIds = array_map(fn($seat) => (int) $seat['seat_id_pk'], $excessSeats);

            if (!empty($excessIds) && $this->CountBookingsForSeatIds($excessIds) > 0) {
                return [
                    'success' => false,
                    'message' => 'Unable to reduce capacity because one or more removed seats are linked to existing bookings. You may deactivate the van instead.'
                ];
            }

            if (!empty($excessIds)) {
                $placeholders = implode(',', array_fill(0, count($excessIds), '?'));
                $delete = $this->conn->prepare("DELETE FROM seats WHERE seat_id_pk IN ($placeholders)");
                $delete->execute($excessIds);
            }
        }

        return ['success' => true];
    }

    private function BuildSeatFromIndex(int $index): array
    {
        $letters = range('A', 'Z');
        $rowIndex = intdiv($index, 2);

        return [
            'seat_number' => ($letters[$rowIndex] ?? ('R' . ($rowIndex + 1))) . (($index % 2) + 1),
            'seat_row' => $rowIndex + 1,
            'seat_col' => ($index % 2) + 1,
        ];
    }

    private function CountBookingsForSeatIds(array $seatIds): int
    {
        $seatIds = array_values(array_unique(array_filter(array_map('intval', $seatIds))));
        if (empty($seatIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM bookings WHERE seat_id_fk IN ($placeholders)");
        $stmt->execute($seatIds);
        return (int) $stmt->fetchColumn();
    }

    private function CountSeatBookings(int $vanId): int
    {
        if (!$vanId) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM bookings b
            INNER JOIN seats s ON b.seat_id_fk = s.seat_id_pk
            WHERE s.van_id_fk = :id
        ");
        $stmt->execute([':id' => $vanId]);
        return (int) $stmt->fetchColumn();
    }

    private function VanExists(int $vanId): bool
    {
        if (!$vanId) {
            return false;
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE van_id_pk = :id");
        $stmt->execute([':id' => $vanId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
?>
