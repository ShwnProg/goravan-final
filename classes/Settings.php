<?php
/**
 * Settings.php - Handles system configuration & admin profile updates
 * Extends Admin for profile operations + new settings table methods
 */

class Settings extends Admin
{
    private $table = 'settings'; // New table to be created
    public $system_name;
    public $timezone;
    public $date_format;
    public $logo_path;
    public $default_trip_status;
    public $max_van_capacity;
    public $booking_rules;
    public $session_timeout;

    public function __construct($db)
    {
        parent::__construct($db);
        $this->table = 'settings';
    }

    /**
     * Get all system settings (singleton pattern, first row)
     */
    public function GetSystemSettings()
    {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM `settings` LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Defaults if table empty
            return $row ?: [
                'system_name' => 'GoraVan Admin',
                'timezone' => 'Asia/Manila',
                'date_format' => 'd/m/Y',
                'logo_path' => 'images/logo.png',
                'default_trip_status' => 'confirmed',
                'max_van_capacity' => 14,
                'booking_rules' => '- Minimum 24h advance booking\n- Max 2 bookings per user per day\n- Children under 5 free w/ adult',
                'session_timeout' => 60
            ];
        } catch (PDOException $e) {
            // Table doesn't exist yet - return defaults
            return [
                'system_name' => 'GoraVan Admin',
                'timezone' => 'Asia/Manila',
                'date_format' => 'd/m/Y',
                'logo_path' => 'images/logo.png',
                'default_trip_status' => 'confirmed',
                'max_van_capacity' => 14,
                'booking_rules' => '- Minimum 24h advance booking\n- Max 2 bookings per user per day\n- Children under 5 free w/ adult',
                'session_timeout' => 60
            ];
        }
    }

    /**
     * Update system settings (upsert first row)
     */
    public function UpdateSystemSettings($data)
    {
        try {
            // Check if settings row exists
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM `settings`");
            $stmt->execute();
            $exists = $stmt->fetchColumn() > 0;

            if ($exists) {
                // UPDATE
                $stmt = $this->conn->prepare("
                    UPDATE `settings` 
                    SET system_name = :system_name, timezone = :timezone, date_format = :date_format,
                        logo_path = :logo_path, default_trip_status = :default_trip_status,
                        max_van_capacity = :max_van_capacity, booking_rules = :booking_rules,
                        session_timeout = :session_timeout
                ");
                return $stmt->execute($data);
            } else {
                // INSERT
                $stmt = $this->conn->prepare("
                    INSERT INTO `settings` (system_name, timezone, date_format, logo_path,
                        default_trip_status, max_van_capacity, booking_rules, session_timeout)
                    VALUES (:system_name, :timezone, :date_format, :logo_path,
                        :default_trip_status, :max_van_capacity, :booking_rules, :session_timeout)
                ");
                return $stmt->execute($data);
            }
        } catch (PDOException $e) {
            error_log("Settings update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update admin profile (uses parent Admin + Users table)
     */
    public function UpdateAdminProfile($fullname, $email, $contact_number)
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET fullname = :fullname, email = :email, contact_number = :contact_number 
                WHERE user_id_pk = :id AND role = 'admin'
            ");
            return $stmt->execute([
                ':fullname' => $fullname,
                ':email' => $email,
                ':contact_number' => $contact_number,
                ':id' => $this->id
            ]);
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Change admin password
     */
    public function ChangeAdminPassword($current_password, $new_password)
    {
        try {
            // Verify current
            $stmt = $this->conn->prepare("SELECT password FROM users WHERE user_id_pk = :id AND role = 'admin'");
            $stmt->execute([':id' => $this->id]);
            $current_hash = $stmt->fetchColumn();

            if (!password_verify($current_password, $current_hash)) {
                return false;
            }

            // Update
            $stmt = $this->conn->prepare("UPDATE users SET password = :password WHERE user_id_pk = :id AND role = 'admin'");
            return $stmt->execute([
                ':password' => password_hash($new_password, PASSWORD_DEFAULT),
                ':id' => $this->id
            ]);
        } catch (PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Backup database (basic export)
     */
    public function BackupDatabase()
    {
        try {
            $filename = 'goravan_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $output = fopen("uploads/backups/$filename", 'w');
            
            // Simple schema + data export
            $tables = ['users', 'vans', 'routes', 'schedules', 'bookings']; // core tables
            foreach ($tables as $table) {
                fwrite($output, $this->exportTable($table));
            }
            
            fclose($output);
            return "uploads/backups/$filename";
        } catch (Exception $e) {
            return false;
        }
    }

    private function exportTable($table)
    {
        $schema = $this->conn->query("SHOW CREATE TABLE `$table`")->fetch();
        $data = $this->conn->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        
        $sql = "-- Table: $table\n" . $schema['Create Table'] . ";\n\n";
        foreach ($data as $row) {
            $values = array_map(function($v) { return $this->conn->quote($v); }, $row);
            $sql .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
        }
        return $sql . "\n";
    }
}
?>

