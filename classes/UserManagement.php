<?php
class UserManagement
{
    private $conn;
    private $table      = 'users';
    private $veri_table = 'verification_documents';

    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $contact_number;
    public $birthdate;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /* ── READ ─────────────────────────────────── */

    public function GetAllUsers(): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                u.user_id_pk,
                CONCAT(u.firstname, ' ', u.lastname) as fullname,
                u.email,
                u.contact_number,
                u.birthdate,
                u.created_at,
                (
                    SELECT COALESCE(v2.status, 'pending')
                    FROM {$this->veri_table} v2
                    WHERE v2.user_id_fk = u.user_id_pk
                    ORDER BY v2.submitted_at DESC, v2.document_id_pk DESC
                    LIMIT 1
                ) AS latest_verification_status,
                (
                    SELECT v3.submitted_at
                    FROM {$this->veri_table} v3
                    WHERE v3.user_id_fk = u.user_id_pk
                      AND (v3.status = 'pending' OR v3.status IS NULL)
                    ORDER BY v3.submitted_at ASC, v3.document_id_pk ASC
                    LIMIT 1
                ) AS first_pending_at,
                COUNT(v.document_id_pk) AS document_count,
                SUM(CASE WHEN v.status = 'pending' OR v.status IS NULL THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN v.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                SUM(CASE WHEN v.status = 'approved' THEN 1 ELSE 0 END) AS approved_count
            FROM {$this->table} u
            LEFT JOIN {$this->veri_table} v ON u.user_id_pk = v.user_id_fk
            WHERE u.role = 'user'
            GROUP BY u.user_id_pk
            ORDER BY
                CASE
                    WHEN (
                        SELECT COALESCE(v4.status, 'pending')
                        FROM {$this->veri_table} v4
                        WHERE v4.user_id_fk = u.user_id_pk
                        ORDER BY v4.submitted_at DESC, v4.document_id_pk DESC
                        LIMIT 1
                    ) = 'pending' THEN 0
                    ELSE 1
                END ASC,
                first_pending_at ASC,
                u.created_at DESC
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['verification_status'] = $this->resolveStatusFromCounts(
                (int) $row['document_count'],
                (int) $row['pending_count'],
                (int) $row['rejected_count'],
                (int) $row['approved_count'],
                $row['latest_verification_status'] ?? null
            );
        }

        return $rows;
    }

    public function GetUserByID(): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                u.user_id_pk,
                CONCAT(u.firstname, ' ', u.lastname) as fullname,
                u.email,
                u.contact_number,
                u.birthdate,
                u.created_at,
                (
                    SELECT COALESCE(v2.status, 'pending')
                    FROM {$this->veri_table} v2
                    WHERE v2.user_id_fk = u.user_id_pk
                    ORDER BY v2.submitted_at DESC, v2.document_id_pk DESC
                    LIMIT 1
                ) AS latest_verification_status,
                COUNT(v.document_id_pk) AS document_count,
                SUM(CASE WHEN v.status = 'pending' OR v.status IS NULL THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN v.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                SUM(CASE WHEN v.status = 'approved' THEN 1 ELSE 0 END) AS approved_count
            FROM {$this->table} u
            LEFT JOIN {$this->veri_table} v ON u.user_id_pk = v.user_id_fk
            WHERE u.user_id_pk = :id AND u.role = 'user'
            GROUP BY u.user_id_pk
        ");
        $stmt->execute([':id' => $this->id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) return [];

        $rows[0]['verification_status'] = $this->resolveStatusFromCounts(
            (int) $rows[0]['document_count'],
            (int) $rows[0]['pending_count'],
            (int) $rows[0]['rejected_count'],
            (int) $rows[0]['approved_count'],
            $rows[0]['latest_verification_status'] ?? null
        );

        return $rows;
    }

    public function GetVerificationDocuments(): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                document_id_pk,
                document_type,
                file_path,
                COALESCE(status, 'pending') AS status,
                submitted_at,
                reviewed_at
            FROM {$this->veri_table}
            WHERE user_id_fk = :user_id
            ORDER BY submitted_at DESC
        ");
        $stmt->execute([':user_id' => $this->id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function IsEmailExist(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT 1 FROM {$this->table}
            WHERE LOWER(email) = LOWER(:email) LIMIT 1
        ");
        $stmt->execute([':email' => $this->email]);
        return (bool) $stmt->fetchColumn();
    }

    public function IsEmailExistExcept(): bool
    {
        $stmt = $this->conn->prepare("
            SELECT 1 FROM {$this->table}
            WHERE LOWER(email) = LOWER(:email) AND user_id_pk != :id LIMIT 1
        ");
        $stmt->execute([':email' => $this->email, ':id' => $this->id]);
        return (bool) $stmt->fetchColumn();
    }

    /* ── CREATE ───────────────────────────────── */

    // public function AddUser(): array
    // {
    //     try {
    //         $stmt = $this->conn->prepare("
    //             INSERT INTO {$this->table}
    //                 (fullname, email, contact_number, birthdate, role, created_at)
    //             VALUES (:fullname, :email, :contact_number, :birthdate, 'user', NOW())
    //         ");
    //         $stmt->execute([
    //             ':fullname'       => $this->fullname,
    //             ':email'          => $this->email,
    //             ':contact_number' => $this->contact_number,
    //             ':birthdate'      => $this->birthdate,
    //         ]);
    //         return ['success' => true, 'id' => (int) $this->conn->lastInsertId()];
    //     } catch (PDOException $e) {
    //         return ['success' => false, 'error' => $e->getMessage()];
    //     }
    // }

    /* ── UPDATE ───────────────────────────────── */

    public function EditUser(): array
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE {$this->table}
                SET firstname      = :firstname,
                    lastname       = :lastname,
                    email          = :email,
                    contact_number = :contact_number,
                    birthdate      = :birthdate
                WHERE user_id_pk = :id
            ");
            $stmt->execute([
                ':firstname'      => $this->first_name,
                ':lastname'       => $this->last_name,
                ':email'          => $this->email,
                ':contact_number' => $this->contact_number,
                ':birthdate'      => $this->birthdate,
                ':id'             => $this->id,
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /* ── DELETE ───────────────────────────────── */

    public function DeleteUser(): array
    {
        try {
            $this->conn->beginTransaction();

            $this->conn->prepare("DELETE FROM {$this->veri_table} WHERE user_id_fk = :id")
                       ->execute([':id' => $this->id]);

            $this->conn->prepare("DELETE FROM {$this->table} WHERE user_id_pk = :id")
                       ->execute([':id' => $this->id]);

            $this->conn->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /* ── DOCUMENT ACTIONS ─────────────────────── */

    public function ApproveDocument(int $doc_id): array
    {
        return $this->setDocStatus($doc_id, 'approved');
    }

    public function RejectDocument(int $doc_id): array
    {
        return $this->setDocStatus($doc_id, 'rejected');
    }

    /* ── PRIVATE ──────────────────────────────── */

    private function setDocStatus(int $doc_id, string $status): array
    {
        try {
            if ($status === 'approved') {
                $docStmt = $this->conn->prepare("
                    SELECT vd.document_type, u.birthdate
                    FROM {$this->veri_table} vd
                    INNER JOIN {$this->table} u ON vd.user_id_fk = u.user_id_pk
                    WHERE vd.document_id_pk = :id
                    LIMIT 1
                ");
                $docStmt->execute([':id' => $doc_id]);
                $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
                if (!$doc) {
                    return ['success' => false, 'error' => 'Document not found.'];
                }

                $typeError = Verification::ValidateTypeForBirthdate((string) ($doc['document_type'] ?? ''), $doc['birthdate'] ?? null);
                if ($typeError) {
                    return ['success' => false, 'error' => $typeError];
                }
            }

            $stmt = $this->conn->prepare("
                UPDATE {$this->veri_table}
                SET status      = :status,
                    reviewed_at = NOW()
                WHERE document_id_pk = :id
            ");
            $stmt->execute([
                ':status'   => $status,
                ':id'       => $doc_id,
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Determine verification status based on document counts:
     * - 0 documents → "no_submission"
     * - Any rejected → "rejected"
     * - Any pending → "pending"
     * - All approved → "approved"
     */
    private function resolveStatusFromCounts(int $total, int $pending, int $rejected, int $approved, ?string $latest = null): string
    {
        if ($total === 0) {
            return 'no_submission';
        }
        if (in_array($latest, ['pending', 'approved', 'rejected'], true)) {
            return $latest;
        }
        return $pending > 0 ? 'pending' : ($rejected > 0 ? 'rejected' : 'approved');
    }
}
