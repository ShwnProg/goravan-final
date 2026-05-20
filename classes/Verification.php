<?php
class Verification
{
    private $conn = null;
    private $table = 'verification_documents';

    public $user_id_fk;
    public $type;
    public $document;
    public $status;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ── Core: insert new verification record (used for all submission types)
    public function AddDocuments()
    {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO $this->table (user_id_fk, document_type, file_path, submitted_at, status)
                 VALUES (:id, :type, :path, :submitted_at, :status)"
            );
            $stmt->execute([
                ':id' => $this->user_id_fk,
                ':type' => $this->type,
                ':path' => $this->document,
                ':status' => $this->status,
                ':submitted_at' => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Returns the most recent verification record for the user, plus the latest
     * approved record when one exists. An approved record remains the active
     * discount basis while a newer update is pending or rejected.
     */
    public function GetVerficationStatus()
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT
                    COALESCE(latest.status, 'pending') AS status,
                    latest.document_type,
                    latest.rejection_reason,
                    approved.document_type AS approved_document_type,
                    approved.reviewed_at AS approved_reviewed_at
                 FROM $this->table
                 latest
                 LEFT JOIN $this->table approved
                    ON approved.document_id_pk = (
                        SELECT a.document_id_pk
                        FROM $this->table a
                        WHERE a.user_id_fk = :approved_id
                          AND a.status = 'approved'
                        ORDER BY a.reviewed_at DESC, a.submitted_at DESC, a.document_id_pk DESC
                        LIMIT 1
                    )
                 WHERE latest.user_id_fk = :id
                 ORDER BY latest.submitted_at DESC, latest.document_id_pk DESC
                 LIMIT 1"
            );
            $stmt->execute([
                ':id' => $this->user_id_fk,
                ':approved_id' => $this->user_id_fk,
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            // Fallback: rejection_reason column may not exist yet
            try {
                $stmt2 = $this->conn->prepare(
                    "SELECT
                        COALESCE(latest.status, 'pending') AS status,
                        latest.document_type,
                        approved.document_type AS approved_document_type,
                        approved.reviewed_at AS approved_reviewed_at
                     FROM $this->table latest
                     LEFT JOIN $this->table approved
                        ON approved.document_id_pk = (
                            SELECT a.document_id_pk
                            FROM $this->table a
                            WHERE a.user_id_fk = :approved_id
                              AND a.status = 'approved'
                            ORDER BY a.reviewed_at DESC, a.submitted_at DESC, a.document_id_pk DESC
                            LIMIT 1
                        )
                     WHERE latest.user_id_fk = :id
                     ORDER BY latest.submitted_at DESC, latest.document_id_pk DESC
                     LIMIT 1"
                );
                $stmt2->execute([
                    ':id' => $this->user_id_fk,
                    ':approved_id' => $this->user_id_fk,
                ]);
                $result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                return $result2 ?: null;
            } catch (PDOException $e2) {
                return null;
            }
        }
    }

    /**
     * Returns true if the user currently has a pending verification.
     * Used by the controller to block duplicate submissions.
     */
    public function HasPendingVerification()
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) FROM $this->table
                 WHERE user_id_fk = :id AND (status = 'pending' OR status IS NULL)"
            );
            $stmt->execute([':id' => $this->user_id_fk]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function GetApprovedVerification()
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT document_type, status, reviewed_at, submitted_at
                 FROM $this->table
                 WHERE user_id_fk = :id AND status = 'approved'
                 ORDER BY reviewed_at DESC, submitted_at DESC, document_id_pk DESC
                 LIMIT 1"
            );
            $stmt->execute([':id' => $this->user_id_fk]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function HasApprovedVerification()
    {
        return $this->GetApprovedVerification() !== null;
    }

    public static function ValidateTypeForBirthdate(string $type, ?string $birthdate): ?string
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['regular', 'student', 'senior', 'pwd'], true)) {
            return 'Invalid passenger type selected.';
        }

        if (!$birthdate) {
            return 'Birthdate is required.';
        }

        try {
            $birth = new DateTimeImmutable($birthdate);
            $today = new DateTimeImmutable('today');
        } catch (Throwable $e) {
            return 'Invalid birthdate.';
        }

        if ($birth > $today) {
            return 'Birthdate cannot be in the future.';
        }

        $age = $birth->diff($today)->y;
        if ($age <= 0) {
            return 'Invalid birthdate.';
        }

        if ($type === 'student' && $age < 16) {
            return 'You must be at least 16 years old to verify as Student.';
        }

        if ($type === 'senior' && $age < 60) {
            return 'You must be 60+ to verify as Senior Citizen.';
        }

        return null;
    }
}
?>