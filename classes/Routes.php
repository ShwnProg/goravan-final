<?php
class Routes
{
    private $conn = null;
    private $table = "routes";

    public $id;
    public $origin;
    public $destination;
    public $status;
    public $stops = [];
    public $fare;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // READ 
    public function GetAllRoutes(): array
    {
        $routes = $this->conn->prepare("
            SELECT * FROM $this->table
            ORDER BY is_active DESC, route_id_pk DESC
        ");

        $routes->execute();

        $routes = $routes->fetchAll(PDO::FETCH_ASSOC);

        if (empty($routes))
            return [];

        $allStops = $this->conn->prepare("
            SELECT * FROM route_stops ORDER BY route_id_fk, stop_order ASC
        ");
        $allStops->execute();

        $allStops = $allStops->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($allStops as $stop) {
            $grouped[$stop['route_id_fk']][] = $stop;
        }

        foreach ($routes as &$r) {
            $r['stops'] = $grouped[$r['route_id_pk']] ?? [];
        }

        return $routes;
    }
    public function GetRouteByID()
    {
        $routes = $this->conn->prepare("
            SELECT * FROM $this->table WHERE route_id_pk = :id
        ");

        $routes->execute([":id" => $this->id]);

        $routes = $routes->fetchAll(PDO::FETCH_ASSOC);

        if (empty($routes))
            return [];

        $allStops = $this->conn->prepare("
            SELECT * FROM route_stops WHERE route_id_fk = :id
        ");
        $allStops->execute([":id" => $this->id]);

        $allStops = $allStops->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($allStops as $stop) {
            $grouped[$stop['route_id_fk']][] = $stop;
        }

        foreach ($routes as &$r) {
            $r['stops'] = $grouped[$r['route_id_pk']] ?? [];
        }

        return $routes;
    }
    public function IsRouteExist(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT route_id_pk FROM $this->table
            WHERE LOWER(origin)      = LOWER(:origin)
              AND LOWER(destination) = LOWER(:destination)
        ");
        $stmt->execute([
            ':origin' => $this->origin,
            ':destination' => $this->destination,
        ]);
        $candidates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($candidates))
            return false;

        $incomingStops = array_map('strtolower', array_values($this->stops));

        $stopStmt = $this->conn->prepare("
            SELECT stop_name FROM route_stops
            WHERE route_id_fk = :id
            ORDER BY stop_order ASC
        ");

        foreach ($candidates as $candidateId) {
            $stopStmt->execute([':id' => $candidateId]);
            $existingStops = array_map('strtolower', $stopStmt->fetchAll(PDO::FETCH_COLUMN));

            if ($existingStops === $incomingStops) {
                return true;
            }
        }

        return false;
    }

    // CREATE 
    public function AddRoute(): array
    {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                INSERT INTO $this->table (origin, destination, is_active, fare)
                VALUES (:origin, :destination, :is_active,:fare)
            ");
            $stmt->execute([
                ':origin' => $this->origin,
                ':destination' => $this->destination,
                ':is_active' => $this->status,
                ':fare' => $this->fare,
            ]);

            $routeId = (int) $this->conn->lastInsertId();

            $this->_insertStops($routeId, $this->stops);

            $this->conn->commit();
            return ['success' => true, 'id' => $routeId];

        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log('[Routes::EditRoute] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update record. Please check the details and try again.'];
        }
    }

    // UPDATE 
    public function EditRoute(): array
    {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                UPDATE $this->table
                SET origin = :origin,
                    destination = :destination,
                    fare = :fare,
                    is_active = :is_active
                WHERE route_id_pk = :id
            ");
            $stmt->execute([
                ':origin' => $this->origin,
                ':destination' => $this->destination,
                ':fare' => $this->fare,
                ':is_active' => $this->status,
                ':id' => $this->id,
            ]);

            $this->_deleteStops($this->id);
            $this->_insertStops($this->id, $this->stops);

            $this->conn->commit();
            return ['success' => true];

        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log('[Routes::AddRoute] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to add route. Please check the details and try again.'];
        }
    }

    // DELETE 
    public function DeleteRoute(): array
    {
        try {
            if (!$this->id) {
                return ['success' => false, 'message' => 'Invalid route.'];
            }

            if ($this->CountAssignedSchedules((int) $this->id) > 0) {
                return [
                    'success' => false,
                    'message' => 'This record cannot be deleted because it is already linked to existing schedules or bookings. You may deactivate it instead.'
                ];
            }

            $this->conn->beginTransaction();

            $this->_deleteStops($this->id);

            $stmt = $this->conn->prepare("
                DELETE FROM $this->table WHERE route_id_pk = :id
            ");
            $stmt->execute([':id' => $this->id]);

            $this->conn->commit();
            return ['success' => true];

        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log('[Routes::DeleteRoute] ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'This record cannot be deleted because it is already linked to existing schedules or bookings. You may deactivate it instead.'
            ];
        }
    }

    // TOGGLE 
    public function ToggleRoute(): array
    {
        try {
            if (!$this->RouteExists((int) $this->id)) {
                return ['success' => false, 'message' => 'Route not found.'];
            }

            $stmt = $this->conn->prepare("
                UPDATE $this->table SET is_active = :status WHERE route_id_pk = :id
            ");
            $stmt->execute([
                ':status' => $this->status,
                ':id' => $this->id,
            ]);
            return ['success' => true];

        } catch (PDOException $e) {
            error_log('[Routes::ToggleRoute] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update route status. Please try again.'];
        }
    }

    // PRIVATE HELPERS 
    private function _insertStops(int $routeId, array $stops): void
    {
        if (empty($stops))
            return;

        $stmt = $this->conn->prepare("
            INSERT INTO route_stops (route_id_fk, stop_name, stop_order)
            VALUES (:route_id, :stop_name, :stop_order)
        ");

        foreach ($stops as $order => $name) {
            $stmt->execute([
                ':route_id' => $routeId,
                ':stop_name' => $name,
                ':stop_order' => $order + 1,
            ]);
        }
    }

    private function _deleteStops(int $routeId): void
    {
        $stmt = $this->conn->prepare("
            DELETE FROM route_stops WHERE route_id_fk = :id
        ");
        $stmt->execute([':id' => $routeId]);
    }

    private function CountAssignedSchedules(int $routeId): int
    {
        if (!$routeId) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM schedules
            WHERE route_id_fk = :id
        ");
        $stmt->execute([':id' => $routeId]);
        return (int) $stmt->fetchColumn();
    }

    private function RouteExists(int $routeId): bool
    {
        if (!$routeId) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM {$this->table}
            WHERE route_id_pk = :id
        ");
        $stmt->execute([':id' => $routeId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function GetActiveRoutes(){
        $stmt=$this->conn->prepare("SELECT * FROM routes WHERE is_active=1");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
