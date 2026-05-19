<?php
class Drivers
{
    private $conn = null;
    private $table = "drivers";

    public $id;
    public $user_id;
    public $full_name;
    public $license_number;
    public $contact_number;
    public $status;
    public $email;
    public $password;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function GetAllDrivers(): array
    {
        $authSelect = $this->DriverAuthColumnExists()
            ? "u.email AS login_email, u.user_id_pk AS login_user_id"
            : "NULL AS login_email, NULL AS login_user_id";
        $authJoin = $this->DriverAuthColumnExists()
            ? "LEFT JOIN users u ON d.user_id_fk = u.user_id_pk"
            : "";

        $stmt = $this->conn->prepare("
            SELECT d.*, $authSelect
            FROM {$this->table} d
            $authJoin
            ORDER BY
                CASE WHEN d.status = 'active' THEN 0 ELSE 1 END,
                d.driver_id_pk DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function GetDriverByID(): array
    {
        $authSelect = $this->DriverAuthColumnExists()
            ? "u.email AS login_email, u.user_id_pk AS login_user_id"
            : "NULL AS login_email, NULL AS login_user_id";
        $authJoin = $this->DriverAuthColumnExists()
            ? "LEFT JOIN users u ON d.user_id_fk = u.user_id_pk"
            : "";

        $stmt = $this->conn->prepare("
            SELECT d.*, $authSelect
            FROM {$this->table} d
            $authJoin
            WHERE d.driver_id_pk = :id
        ");
        $stmt->execute([':id' => $this->id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function GetDriverByUserId(int $userId): array
    {
        if (!$userId || !$this->DriverAuthColumnExists()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT d.*, u.email AS login_email, u.contact_number AS login_contact_number
            FROM {$this->table} d
            INNER JOIN users u ON d.user_id_fk = u.user_id_pk
            WHERE u.user_id_pk = :user_id
              AND u.role = 'driver'
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function IsLicenseExist(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT driver_id_pk FROM {$this->table}
            WHERE UPPER(license_number) = UPPER(:license_number)
        ");
        $stmt->execute([':license_number' => $this->license_number]);
        return (bool) $stmt->fetchColumn();
    }

    public function IsLicenseExistExcept(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT driver_id_pk FROM {$this->table}
            WHERE UPPER(license_number) = UPPER(:license_number)
              AND driver_id_pk != :id
        ");
        $stmt->execute([
            ':license_number' => $this->license_number,
            ':id' => $this->id,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public function IsEmailExistExcept(int $exceptUserId = 0): bool
    {
        if (!$this->email) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT user_id_pk
            FROM users
            WHERE LOWER(email) = LOWER(:email)
              AND user_id_pk != :user_id
            LIMIT 1
        ");
        $stmt->execute([
            ':email' => $this->email,
            ':user_id' => $exceptUserId,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public function AddDriver(): array
    {
        try {
            $this->EnsureDriverAuthSchema();

            if ($this->email && $this->IsEmailExistExcept()) {
                return ['success' => false, 'message' => 'A user with that email already exists.'];
            }

            $this->conn->beginTransaction();

            $userId = null;
            if ($this->email && $this->password) {
                $name = $this->SplitName($this->full_name);
                if ($this->UserHasSplitNameColumns()) {
                    $userStmt = $this->conn->prepare("
                        INSERT INTO users (firstname, lastname, email, contact_number, password, role, created_at, birthdate)
                        VALUES (:firstname, :lastname, :email, :contact, :password, 'driver', NOW(), NULL)
                    ");
                    $userStmt->execute([
                        ':firstname' => $name['firstname'],
                        ':lastname' => $name['lastname'],
                        ':email' => $this->email,
                        ':contact' => $this->contact_number,
                        ':password' => password_hash($this->password, PASSWORD_DEFAULT),
                    ]);
                } else {
                    $userStmt = $this->conn->prepare("
                        INSERT INTO users (fullname, email, contact_number, password, role, created_at, birthdate)
                        VALUES (:fullname, :email, :contact, :password, 'driver', NOW(), NULL)
                    ");
                    $userStmt->execute([
                        ':fullname' => $this->full_name,
                        ':email' => $this->email,
                        ':contact' => $this->contact_number,
                        ':password' => password_hash($this->password, PASSWORD_DEFAULT),
                    ]);
                }
                $userId = (int) $this->conn->lastInsertId();
            }

            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table} (user_id_fk, full_name, license_number, contact_number, status)
                VALUES (:user_id, :full_name, :license_number, :contact_number, :status)
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':full_name' => $this->full_name,
                ':license_number' => strtoupper($this->license_number),
                ':contact_number' => $this->contact_number,
                ':status' => $this->status,
            ]);

            $this->conn->commit();
            return ['success' => true, 'id' => (int) $this->conn->lastInsertId()];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[Drivers::AddDriver] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to add driver. Please check the details and try again.'];
        }
    }

    public function EditDriver(): array
    {
        try {
            $current = $this->GetDriverByID();
            if (!$current) {
                return ['success' => false, 'message' => 'Driver not found.'];
            }

            $this->EnsureDriverAuthSchema();
            $currentUserId = (int) ($current['user_id_fk'] ?? $current['login_user_id'] ?? 0);

            if ($this->email && $this->IsEmailExistExcept($currentUserId)) {
                return ['success' => false, 'message' => 'A user with that email already exists.'];
            }

            if ($this->email && $currentUserId === 0 && !$this->password) {
                return ['success' => false, 'message' => 'Set a temporary password to create this driver login.'];
            }

            $this->conn->beginTransaction();

            if ($this->email) {
                $name = $this->SplitName($this->full_name);

                if ($currentUserId > 0) {
                    $sql = $this->UserHasSplitNameColumns()
                        ? "
                            UPDATE users
                            SET firstname = :firstname,
                                lastname = :lastname,
                                email = :email,
                                contact_number = :contact
                        "
                        : "
                            UPDATE users
                            SET fullname = :fullname,
                                email = :email,
                                contact_number = :contact
                        ";
                    $params = [
                        ':email' => $this->email,
                        ':contact' => $this->contact_number,
                        ':id' => $currentUserId,
                    ];
                    if ($this->UserHasSplitNameColumns()) {
                        $params[':firstname'] = $name['firstname'];
                        $params[':lastname'] = $name['lastname'];
                    } else {
                        $params[':fullname'] = $this->full_name;
                    }

                    $sql .= " WHERE user_id_pk = :id AND role = 'driver'";
                    $userStmt = $this->conn->prepare($sql);
                    $userStmt->execute($params);
                } elseif ($this->password) {
                    if ($this->UserHasSplitNameColumns()) {
                        $userStmt = $this->conn->prepare("
                            INSERT INTO users (firstname, lastname, email, contact_number, password, role, created_at, birthdate)
                            VALUES (:firstname, :lastname, :email, :contact, :password, 'driver', NOW(), NULL)
                        ");
                        $userStmt->execute([
                            ':firstname' => $name['firstname'],
                            ':lastname' => $name['lastname'],
                            ':email' => $this->email,
                            ':contact' => $this->contact_number,
                            ':password' => password_hash($this->password, PASSWORD_DEFAULT),
                        ]);
                    } else {
                        $userStmt = $this->conn->prepare("
                            INSERT INTO users (fullname, email, contact_number, password, role, created_at, birthdate)
                            VALUES (:fullname, :email, :contact, :password, 'driver', NOW(), NULL)
                        ");
                        $userStmt->execute([
                            ':fullname' => $this->full_name,
                            ':email' => $this->email,
                            ':contact' => $this->contact_number,
                            ':password' => password_hash($this->password, PASSWORD_DEFAULT),
                        ]);
                    }
                    $currentUserId = (int) $this->conn->lastInsertId();
                }
            }

            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET user_id_fk = :user_id,
                    full_name = :full_name,
                    license_number = :license_number,
                    contact_number = :contact_number,
                    status = :status
                WHERE driver_id_pk = :id
            ");
            $stmt->execute([
                ':user_id' => $currentUserId ?: null,
                ':full_name' => $this->full_name,
                ':license_number' => strtoupper($this->license_number),
                ':contact_number' => $this->contact_number,
                ':status' => $this->status,
                ':id' => $this->id,
            ]);

            $this->conn->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[Drivers::EditDriver] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update record. Please check the details and try again.'];
        }
    }

    public function UpdateOwnProfile(int $userId, string $fullName, string $email, string $contactNumber): array
    {
        try {
            $this->EnsureDriverAuthSchema();
            $current = $this->GetDriverByUserId($userId);
            if (!$current) {
                return ['success' => false, 'message' => 'Driver profile not found.'];
            }

            $this->email = $email;
            if ($this->IsEmailExistExcept($userId)) {
                return ['success' => false, 'message' => 'A user with that email already exists.'];
            }

            $name = $this->SplitName($fullName);

            $this->conn->beginTransaction();

            $driverStmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET full_name = :full_name,
                    contact_number = :contact
                WHERE user_id_fk = :user_id
            ");
            $driverStmt->execute([
                ':full_name' => $fullName,
                ':contact' => $contactNumber,
                ':user_id' => $userId,
            ]);

            if ($this->UserHasSplitNameColumns()) {
                $userStmt = $this->conn->prepare("
                    UPDATE users
                    SET firstname = :firstname,
                        lastname = :lastname,
                        email = :email,
                        contact_number = :contact
                    WHERE user_id_pk = :user_id
                      AND role = 'driver'
                ");
                $userStmt->execute([
                    ':firstname' => $name['firstname'],
                    ':lastname' => $name['lastname'],
                    ':email' => $email,
                    ':contact' => $contactNumber,
                    ':user_id' => $userId,
                ]);
            } else {
                $userStmt = $this->conn->prepare("
                    UPDATE users
                    SET fullname = :fullname,
                        email = :email,
                        contact_number = :contact
                    WHERE user_id_pk = :user_id
                      AND role = 'driver'
                ");
                $userStmt->execute([
                    ':fullname' => $fullName,
                    ':email' => $email,
                    ':contact' => $contactNumber,
                    ':user_id' => $userId,
                ]);
            }

            $this->conn->commit();
            return ['success' => true, 'message' => 'Profile updated successfully.'];
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[Drivers::UpdateOwnProfile] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update profile. Please try again.'];
        }
    }

    public function ChangeOwnPassword(int $userId, string $currentPassword, string $newPassword): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT password
                FROM users
                WHERE user_id_pk = :user_id
                  AND role = 'driver'
                LIMIT 1
            ");
            $stmt->execute([':user_id' => $userId]);
            $hash = (string) $stmt->fetchColumn();

            if ($hash === '') {
                return ['success' => false, 'message' => 'Driver login not found.'];
            }

            if (!password_verify($currentPassword, $hash)) {
                return ['success' => false, 'message' => 'Current password is incorrect.'];
            }

            $update = $this->conn->prepare("
                UPDATE users
                SET password = :password
                WHERE user_id_pk = :user_id
                  AND role = 'driver'
            ");
            $update->execute([
                ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':user_id' => $userId,
            ]);

            return ['success' => true, 'message' => 'Password changed successfully.'];
        } catch (Throwable $e) {
            error_log('[Drivers::ChangeOwnPassword] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to change password. Please try again.'];
        }
    }

    public function DeleteDriver(): array
    {
        try {
            if (!$this->id) {
                return ['success' => false, 'message' => 'Invalid driver ID.'];
            }

            if ($this->CountAssignedSchedules((int) $this->id) > 0) {
                return [
                    'success' => false,
                    'message' => 'This record cannot be deleted because it is already linked to existing schedules or bookings. You may deactivate it instead.'
                ];
            }

            $current = $this->GetDriverByID();
            $loginUserId = (int) ($current['user_id_fk'] ?? $current['login_user_id'] ?? 0);

            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE driver_id_pk = :id");
            $stmt->execute([':id' => $this->id]);

            if ($loginUserId > 0) {
                $deleteUser = $this->conn->prepare("DELETE FROM users WHERE user_id_pk = :id AND role = 'driver'");
                $deleteUser->execute([':id' => $loginUserId]);
            }

            $this->conn->commit();
            return [
                'success' => $stmt->rowCount() > 0,
                'message' => $stmt->rowCount() > 0 ? 'Driver deleted successfully.' : 'Driver not found.'
            ];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[Drivers::DeleteDriver] ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'This record cannot be deleted because it is already linked to existing schedules or bookings. You may deactivate it instead.'
            ];
        }
    }

    public function ToggleDriver(): array
    {
        try {
            if (!$this->DriverExists((int) $this->id)) {
                return ['success' => false, 'message' => 'Driver not found.'];
            }

            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET status = :status
                WHERE driver_id_pk = :id
            ");
            $stmt->execute([
                ':status' => $this->status,
                ':id' => $this->id,
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            error_log('[Drivers::ToggleDriver] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update driver status. Please try again.'];
        }
    }

    private function CountAssignedSchedules(int $driverId): int
    {
        if (!$driverId) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM schedules
            WHERE driver_id_fk = :id
        ");
        $stmt->execute([':id' => $driverId]);
        return (int) $stmt->fetchColumn();
    }

    private function DriverExists(int $driverId): bool
    {
        if (!$driverId) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM {$this->table}
            WHERE driver_id_pk = :id
        ");
        $stmt->execute([':id' => $driverId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function DriverAuthColumnExists(): bool
    {
        return $this->ColumnExists('drivers', 'user_id_fk');
    }

    private function EnsureDriverAuthSchema(): void
    {
        $roleColumn = $this->GetColumnType('users', 'role');
        if ($roleColumn && str_contains(strtolower($roleColumn), "enum(") && !str_contains($roleColumn, "'driver'")) {
            $this->conn->exec("ALTER TABLE users MODIFY role ENUM('user','admin','driver') NOT NULL DEFAULT 'user'");
        }

        if (!$this->ColumnExists('drivers', 'user_id_fk')) {
            $this->conn->exec("ALTER TABLE drivers ADD COLUMN user_id_fk INT UNSIGNED NULL AFTER driver_id_pk");
        }

        if (!$this->IndexExists('drivers', 'uk_drivers_user_id_fk')) {
            $this->conn->exec("ALTER TABLE drivers ADD UNIQUE KEY uk_drivers_user_id_fk (user_id_fk)");
        }

        if (!$this->ConstraintExists('drivers', 'fk_drivers_users_login')) {
            $this->conn->exec("
                ALTER TABLE drivers
                ADD CONSTRAINT fk_drivers_users_login
                FOREIGN KEY (user_id_fk) REFERENCES users(user_id_pk)
                ON DELETE SET NULL
            ");
        }
    }

    private function SplitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $firstname = array_shift($parts) ?: 'Driver';
        $lastname = trim(implode(' ', $parts));

        return [
            'firstname' => $firstname,
            'lastname' => $lastname !== '' ? $lastname : 'Account',
        ];
    }

    private function UserHasSplitNameColumns(): bool
    {
        return $this->ColumnExists('users', 'firstname') && $this->ColumnExists('users', 'lastname');
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

    private function IndexExists(string $table, string $index): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':index_name' => $index,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function ConstraintExists(string $table, string $constraint): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND CONSTRAINT_NAME = :constraint_name
        ");
        $stmt->execute([
            ':table_name' => $table,
            ':constraint_name' => $constraint,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
?>
