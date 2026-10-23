
<?php

/**
 * ApprovalRouter
 *
 * Centralized document approval and visibility rules.
 *
 * Supported pending statuses:
 * - pending
 * - pending_manager_approval
 * - pending_final_approval
 *
 * Access rules:
 * - Administrators can approve all pending, non-deleted documents.
 * - Managers can approve pending documents in their department.
 * - Managers can also approve documents explicitly shared with them.
 * - Other roles cannot approve documents through this router.
 */

class ApprovalRouter
{
    /**
     * Normalize a user role.
     */
    private static function normalizeRole($role)
    {
        return strtolower(trim((string) $role));
    }

    /**
     * Determine whether a role is an administrator.
     */
    private static function isAdminRole($role)
    {
        return in_array(
            self::normalizeRole($role),
            ['admin', 'administrator'],
            true
        );
    }

    /**
     * Determine whether a user account is active.
     */
    private static function isActiveUser($user)
    {
        if (!is_array($user)) {
            return false;
        }

        // Preserve compatibility if the users table has no status value.
        if (
            !isset($user['status']) ||
            trim((string) $user['status']) === ''
        ) {
            return true;
        }

        return strtolower(trim((string) $user['status'])) === 'active';
    }

    /**
     * Shared SQL condition for all pending approval stages.
     *
     * Keep the status comparison case-insensitive.
     */
    private static function pendingWhere()
    {
        return "
            LOWER(TRIM(COALESCE(d.status, ''))) IN (
                '',
                'pending',
                'pending_manager_approval',
                'pending_final_approval'
            )
        ";
    }

    /**
     * Retrieve a user by ID.
     */
    private static function getUser($userId)
    {
        $userId = (int) $userId;

        if ($userId <= 0) {
            return null;
        }

        return fetchRow(
            "SELECT
                id,
                role,
                department_id,
                status
             FROM users
             WHERE id = ?
             LIMIT 1",
            [$userId]
        );
    }

    /**
     * Retrieve a document by ID.
     *
     * Soft-deleted documents are not eligible for approval.
     */
    private static function getDocument($documentId)
    {
        $documentId = (int) $documentId;

        if ($documentId <= 0) {
            return null;
        }

        return fetchRow(
            "SELECT
                id,
                department_id,
                uploaded_by,
                status,
                is_deleted
             FROM documents
             WHERE id = ?
               AND COALESCE(is_deleted, 0) = 0
             LIMIT 1",
            [$documentId]
        );
    }

    /**
     * Check whether a document has a supported pending status.
     */
    private static function isPendingStatus($status)
    {
        return in_array(
            strtolower(trim((string) $status)),
            [
                '',
                'pending',
                'pending_manager_approval',
                'pending_final_approval'
            ],
            true
        );
    }

    /**
     * Get the IDs of users eligible to approve a document.
     *
     * Administrators:
     * - All active administrators.
     *
     * Managers:
     * - Active managers in the document's department.
     * - Active managers with whom the document is shared.
     *
     * Returns an array of unique integer user IDs.
     */
    public static function getApproversForDocument($documentId)
    {
        $document = self::getDocument($documentId);

        if (!$document || !self::isPendingStatus($document['status'])) {
            return [];
        }

        $approverIds = [];

        // Active administrators.
        $admins = fetchAll(
            "SELECT id
             FROM users
             WHERE LOWER(TRIM(COALESCE(status, ''))) = 'active'
               AND LOWER(TRIM(COALESCE(role, '')))
                   IN ('admin', 'administrator')"
        );

        foreach ($admins ?: [] as $admin) {
            $approverIds[] = (int) $admin['id'];
        }

        // Active managers belonging to the document's department.
        if (!empty($document['department_id'])) {
            $managers = fetchAll(
                "SELECT id
                 FROM users
                 WHERE LOWER(TRIM(COALESCE(status, ''))) = 'active'
                   AND LOWER(TRIM(COALESCE(role, ''))) = 'manager'
                   AND department_id = ?",
                [(int) $document['department_id']]
            );

            foreach ($managers ?: [] as $manager) {
                $approverIds[] = (int) $manager['id'];
            }
        }

        // Active managers the document has been shared with.
        $sharedManagers = fetchAll(
            "SELECT DISTINCT u.id
             FROM document_shares ds
             INNER JOIN users u
                 ON u.id = ds.user_id
             WHERE ds.document_id = ?
               AND LOWER(TRIM(COALESCE(u.status, ''))) = 'active'
               AND LOWER(TRIM(COALESCE(u.role, ''))) = 'manager'",
            [(int) $documentId]
        );

        foreach ($sharedManagers ?: [] as $manager) {
            $approverIds[] = (int) $manager['id'];
        }

        return array_values(
            array_unique($approverIds)
        );
    }

    /**
     * Get pending documents that the specified user can approve.
     *
     * Administrators see all pending, non-deleted documents.
     * Managers see pending documents from their department or
     * documents explicitly shared with them.
     */
    public static function getPendingDocumentsForUser($userId)
    {
        $userId = (int) $userId;
        $user = self::getUser($userId);

        if (!$user || !self::isActiveUser($user)) {
            return [];
        }

        $role = self::normalizeRole($user['role']);

        $sql = "
            SELECT DISTINCT
                d.id,
                d.title,
                d.file_type,
                d.file_path,
                d.created_at,
                d.updated_at,
                d.department_id,
                d.uploaded_by,
                d.status,
                dept.name AS department_name,
                CONCAT(
                    COALESCE(u.first_name, ''),
                    ' ',
                    COALESCE(u.last_name, '')
                ) AS owner_name
            FROM documents d
            LEFT JOIN departments dept
                ON dept.id = d.department_id
            LEFT JOIN users u
                ON u.id = d.uploaded_by
        ";

        $params = [];

        // Administrators can see every pending document.
        if (self::isAdminRole($role)) {
            $sql .= "
                WHERE " . self::pendingWhere() . "
                  AND COALESCE(d.is_deleted, 0) = 0
                ORDER BY d.created_at DESC, d.id DESC
            ";

            return fetchAll($sql) ?: [];
        }

        // Managers can see department documents and shared documents.
        if ($role === 'manager') {
            $sql .= "
                LEFT JOIN document_shares ds
                    ON ds.document_id = d.id
                   AND ds.user_id = ?

                WHERE " . self::pendingWhere() . "
                  AND COALESCE(d.is_deleted, 0) = 0
                  AND (
                      d.department_id = ?
                      OR ds.user_id = ?
                  )
                ORDER BY d.created_at DESC, d.id DESC
            ";

            $departmentId = (int) ($user['department_id'] ?? 0);

            $params = [
                $userId,
                $departmentId,
                $userId
            ];

            return fetchAll($sql, $params) ?: [];
        }

        // Ordinary users are not approvers under these rules.
        return [];
    }

    /**
     * Get approval statistics for a user.
     *
     * Pending includes all supported pending stages.
     * Approved and rejected count documents with those exact statuses.
     * Deleted documents are excluded from all statistics.
     */
    public static function getApprovalStats($userId)
    {
        $emptyStats = [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0
        ];

        $userId = (int) $userId;
        $user = self::getUser($userId);

        if (!$user || !self::isActiveUser($user)) {
            return $emptyStats;
        }

        $role = self::normalizeRole($user['role']);

        // Only administrators and managers receive approval statistics.
        if (!self::isAdminRole($role) && $role !== 'manager') {
            return $emptyStats;
        }

        $isAdmin = self::isAdminRole($role);
        $departmentId = (int) ($user['department_id'] ?? 0);

        $stats = [];

        $statuses = [
            'pending' => null,
            'approved' => 'approved',
            'rejected' => 'rejected'
        ];

        foreach ($statuses as $key => $status) {
            $sql = "
                SELECT COUNT(DISTINCT d.id) AS total
                FROM documents d
            ";

            $params = [];

            if (!$isAdmin) {
                $sql .= "
                    LEFT JOIN document_shares ds
                        ON ds.document_id = d.id
                       AND ds.user_id = ?
                ";

                $params[] = $userId;
            }

            $conditions = [
                "COALESCE(d.is_deleted, 0) = 0"
            ];

            if ($key === 'pending') {
                $conditions[] = self::pendingWhere();
            } else {
                $conditions[] = "
                    LOWER(TRIM(COALESCE(d.status, ''))) = ?
                ";

                $params[] = $status;
            }

            if (!$isAdmin) {
                $conditions[] = "
                    (
                        d.department_id = ?
                        OR ds.user_id = ?
                    )
                ";

                $params[] = $departmentId;
                $params[] = $userId;
            }

            $sql .= "
                WHERE " . implode("\n AND ", $conditions);

            $result = fetchRow($sql, $params);

            $stats[$key] = (int) ($result['total'] ?? 0);
        }

        return $stats;
    }

    /**
     * Determine whether a user may approve a particular document.
     *
     * The document must exist, must not be deleted, and must have
     * a supported pending status.
     */
    public static function canUserApproveDocument($userId, $documentId)
    {
        $userId = (int) $userId;
        $documentId = (int) $documentId;

        if ($userId <= 0 || $documentId <= 0) {
            return false;
        }

        $user = self::getUser($userId);

        if (!$user || !self::isActiveUser($user)) {
            return false;
        }

        $document = self::getDocument($documentId);

        if (!$document || !self::isPendingStatus($document['status'])) {
            return false;
        }

        $role = self::normalizeRole($user['role']);

        // Administrators can approve any pending, non-deleted document.
        if (self::isAdminRole($role)) {
            return true;
        }

        // Only managers can approve under the department/shared rules.
        if ($role !== 'manager') {
            return false;
        }

        $userDepartmentId = (int) ($user['department_id'] ?? 0);
        $documentDepartmentId = (int) ($document['department_id'] ?? 0);

        // The manager belongs to the document's department.
        if (
            $userDepartmentId > 0 &&
            $documentDepartmentId > 0 &&
            $userDepartmentId === $documentDepartmentId
        ) {
            return true;
        }

        // Alternatively, the document was explicitly shared with them.
        $shared = countRows(
            "SELECT id
             FROM document_shares
             WHERE document_id = ?
               AND user_id = ?",
            [$documentId, $userId]
        );

        return (int) $shared > 0;
    }
}

