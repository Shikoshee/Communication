<?php

class ApprovalRouter
{
    /**
     * Get all users who should approve a document.
     *
     * Returns an array of user IDs.
     */
    public static function getApproversForDocument($documentId)
    {
        $document = fetchRow(
            "SELECT
                id,
                department_id,
                uploaded_by
             FROM documents
             WHERE id = ?",
            [$documentId]
        );

        if (!$document) {
            return [];
        }

        $approvers = [];

        /*
        |--------------------------------------------------------------------------
        | 1. ALL ADMINS
        |--------------------------------------------------------------------------
        */

        $admins = fetchAll(
            "SELECT id
             FROM users
             WHERE role IN ('admin', 'administrator')
             AND status = 'active'"
        );

        foreach ($admins ?: [] as $admin) {
            $approvers[] = (int)$admin['id'];
        }


        /*
        |--------------------------------------------------------------------------
        | 2. MANAGERS FROM DOCUMENT DEPARTMENT
        |--------------------------------------------------------------------------
        */

        if (!empty($document['department_id'])) {

            $departmentManagers = fetchAll(
                "SELECT id
                 FROM users
                 WHERE role = 'manager'
                 AND department_id = ?
                 AND status = 'active'",
                [$document['department_id']]
            );

            foreach ($departmentManagers ?: [] as $manager) {
                $approvers[] = (int)$manager['id'];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | 3. MANAGERS THE DOCUMENT WAS SHARED WITH
        |--------------------------------------------------------------------------
        */

        $sharedManagers = fetchAll(
            "SELECT DISTINCT ds.user_id
             FROM document_shares ds
             INNER JOIN users u
                ON u.id = ds.user_id
             WHERE ds.document_id = ?
             AND u.role = 'manager'
             AND u.status = 'active'",
            [$documentId]
        );

        foreach ($sharedManagers ?: [] as $manager) {
            $approvers[] = (int)$manager['user_id'];
        }


        /*
        |--------------------------------------------------------------------------
        | REMOVE DUPLICATES
        |--------------------------------------------------------------------------
        */

        return array_values(
            array_unique($approvers)
        );
    }


    /**
     * Get pending documents for a specific approver.
     */
    public static function getPendingDocumentsForUser($userId)
    {
        $user = fetchRow(
            "SELECT
                id,
                role,
                department_id
             FROM users
             WHERE id = ?",
            [$userId]
        );

        if (!$user) {
            return [];
        }

        $role = strtolower(
            trim($user['role'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | ADMINISTRATORS
        |--------------------------------------------------------------------------
        */

        if (
            $role === 'admin' ||
            $role === 'administrator'
        ) {

            return fetchAll(
                "SELECT
                    d.id,
                    d.title,
                    d.file_type,
                    d.file_path,
                    d.created_at,
                    d.department_id,
                    d.uploaded_by,

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

                 WHERE LOWER(TRIM(COALESCE(d.status, ''))) = 'pending'

                 ORDER BY d.created_at DESC"
            ) ?: [];
        }


        /*
        |--------------------------------------------------------------------------
        | MANAGERS
        |--------------------------------------------------------------------------
        */

        if ($role === 'manager') {

            return fetchAll(
                "SELECT DISTINCT

                    d.id,
                    d.title,
                    d.file_type,
                    d.file_path,
                    d.created_at,
                    d.department_id,
                    d.uploaded_by,

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

                 LEFT JOIN document_shares ds
                    ON ds.document_id = d.id

                 WHERE LOWER(TRIM(COALESCE(d.status, ''))) = 'pending'

                 AND (
                    d.department_id = ?
                    OR ds.user_id = ?
                 )

                 ORDER BY d.created_at DESC",

                [
                    $user['department_id'],
                    $userId
                ]
            ) ?: [];
        }


        /*
        |--------------------------------------------------------------------------
        | REGULAR USERS
        |--------------------------------------------------------------------------
        */

        return [];
    }


    /**
     * Get approval statistics.
     */
    public static function getApprovalStats($userId)
    {
        $user = fetchRow(
            "SELECT
                role,
                department_id
             FROM users
             WHERE id = ?",
            [$userId]
        );

        if (!$user) {
            return [
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0
            ];
        }

        $role = strtolower(
            trim($user['role'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | ADMIN STATISTICS
        |--------------------------------------------------------------------------
        */

        if (
            $role === 'admin' ||
            $role === 'administrator'
        ) {

            $pending = countRows(
                "SELECT id
                 FROM documents
                 WHERE LOWER(TRIM(COALESCE(status, ''))) = 'pending'"
            );

            $approved = countRows(
                "SELECT id
                 FROM documents
                 WHERE LOWER(TRIM(COALESCE(status, ''))) = 'approved'"
            );

            $rejected = countRows(
                "SELECT id
                 FROM documents
                 WHERE LOWER(TRIM(COALESCE(status, ''))) = 'rejected'"
            );

            return [
                'pending' => (int)$pending,
                'approved' => (int)$approved,
                'rejected' => (int)$rejected
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | MANAGER STATISTICS
        |--------------------------------------------------------------------------
        */

        if ($role === 'manager') {

            $pending = countRows(
                "SELECT DISTINCT d.id

                 FROM documents d

                 LEFT JOIN document_shares ds
                    ON ds.document_id = d.id

                 WHERE LOWER(TRIM(COALESCE(d.status, ''))) = 'pending'

                 AND (
                    d.department_id = ?
                    OR ds.user_id = ?
                 )",

                [
                    $user['department_id'],
                    $userId
                ]
            );


            $approved = countRows(
                "SELECT DISTINCT d.id

                 FROM documents d

                 LEFT JOIN document_shares ds
                    ON ds.document_id = d.id

                 WHERE LOWER(TRIM(COALESCE(d.status, ''))) = 'approved'

                 AND (
                    d.department_id = ?
                    OR ds.user_id = ?
                 )",

                [
                    $user['department_id'],
                    $userId
                ]
            );


            $rejected = countRows(
                "SELECT DISTINCT d.id

                 FROM documents d

                 LEFT JOIN document_shares ds
                    ON ds.document_id = d.id

                 WHERE LOWER(TRIM(COALESCE(d.status, ''))) = 'rejected'

                 AND (
                    d.department_id = ?
                    OR ds.user_id = ?
                 )",

                [
                    $user['department_id'],
                    $userId
                ]
            );


            return [
                'pending' => (int)$pending,
                'approved' => (int)$approved,
                'rejected' => (int)$rejected
            ];
        }


        return [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0
        ];
    }


    /**
     * Determine whether a user can approve a document.
     */
    public static function canUserApproveDocument($userId, $documentId)
    {
        $user = fetchRow(
            "SELECT
                role,
                department_id
             FROM users
             WHERE id = ?",
            [$userId]
        );

        if (!$user) {
            return false;
        }

        $role = strtolower(
            trim($user['role'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | ADMIN
        |--------------------------------------------------------------------------
        */

        if (
            $role === 'admin' ||
            $role === 'administrator'
        ) {
            return true;
        }


        /*
        |--------------------------------------------------------------------------
        | MANAGER
        |--------------------------------------------------------------------------
        */

        if ($role === 'manager') {

            $document = fetchRow(
                "SELECT department_id
                 FROM documents
                 WHERE id = ?",
                [$documentId]
            );

            if (!$document) {
                return false;
            }


            /*
            | Document belongs to manager's department
            */

            if (
                !empty($document['department_id']) &&
                $document['department_id'] ==
                $user['department_id']
            ) {
                return true;
            }


            /*
            | Document was directly shared with manager
            */

            $isShared = countRows(
                "SELECT id
                 FROM document_shares
                 WHERE document_id = ?
                 AND user_id = ?",
                [
                    $documentId,
                    $userId
                ]
            );

            return $isShared > 0;
        }


        return false;
    }
}