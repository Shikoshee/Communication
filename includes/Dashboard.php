<?php

class Dashboard
{

    /**
     * Normalize role.
     */
    private static function normalizeRole($role)
    {
        return strtolower(
            trim(
                (string)$role
            )
        );
    }


    /**
     * Determine whether role is admin.
     */
    private static function isAdminRole($role)
    {
        $role = self::normalizeRole($role);

        return in_array(
            $role,
            [
                'admin',
                'administrator'
            ],
            true
        );
    }


    /**
     * Dashboard statistics.
     *
     * USER:
     * - Own document activity
     * - Documents shared by/with them
     *
     * MANAGER:
     * - Department statistics
     *
     * ADMIN:
     * - Organization-wide statistics
     */
    public static function getStatistics(
        $userId,
        $role = 'user',
        $departmentId = null
    ) {

        $stats = [

            'total_documents'    => 0,
            'approved_documents' => 0,
            'pending_documents'  => 0,
            'rejected_documents' => 0,
            'shared_documents'   => 0,
            'received_documents' => 0,
            'users'              => 0,
            'departments'        => 0,
            'notifications'      => 0

        ];


        $role = self::normalizeRole($role);


        /*
         * ==========================================================
         * ADMIN
         * ==========================================================
         */

        if(self::isAdminRole($role)){

            $stats['total_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM documents"
                )['total'] ?? 0
            );


            $stats['approved_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM documents
                     WHERE status='approved'"
                )['total'] ?? 0
            );


            $stats['pending_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM documents
                     WHERE status='pending'"
                )['total'] ?? 0
            );


            $stats['rejected_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM documents
                     WHERE status='rejected'"
                )['total'] ?? 0
            );


            $stats['shared_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(DISTINCT document_id) AS total
                     FROM document_shares"
                )['total'] ?? 0
            );


            $stats['users'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM users
                     WHERE status='active'"
                )['total'] ?? 0
            );


            $stats['departments'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM departments"
                )['total'] ?? 0
            );

        }


        /*
         * ==========================================================
         * MANAGER
         * ==========================================================
         */

        elseif($role === 'manager'){

            /*
             * Never fall back to organization-wide statistics
             * when the manager has no department.
             */

            if((int)$departmentId <= 0){

                return $stats;

            }


            $departmentId = (int)$departmentId;


            /*
             * Department documents.
             */

            $stats['total_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM documents
                     WHERE department_id=?",
                    [$departmentId]
                )['total'] ?? 0
            );


            /*
             * Approved documents.
             */

            $stats['approved_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM documents
                     WHERE department_id=?
                     AND status='approved'",
                    [$departmentId]
                )['total'] ?? 0
            );


            /*
             * Pending documents.
             */

            $stats['pending_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM documents
                     WHERE department_id=?
                     AND status='pending'",
                    [$departmentId]
                )['total'] ?? 0
            );


            /*
             * Rejected documents.
             */

            $stats['rejected_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM documents
                     WHERE department_id=?
                     AND status='rejected'",
                    [$departmentId]
                )['total'] ?? 0
            );


            /*
             * Shared documents belonging to department.
             */

            $stats['shared_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(DISTINCT ds.document_id) AS total

                     FROM document_shares ds

                     INNER JOIN documents doc
                        ON doc.id=ds.document_id

                     WHERE doc.department_id=?",
                    [$departmentId]
                )['total'] ?? 0
            );


            /*
             * Active users in department.
             */

            $stats['users'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM users
                     WHERE department_id=?
                     AND status='active'",
                    [$departmentId]
                )['total'] ?? 0
            );


            /*
             * Manager is responsible for one department.
             */

            $stats['departments'] = 1;

        }


        /*
         * ==========================================================
         * REGULAR USER
         * ==========================================================
         */

        else {

            /*
             * My documents.
             */

            $stats['total_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM documents
                     WHERE uploaded_by=?",
                    [$userId]
                )['total'] ?? 0
            );


            /*
             * Documents approved by me.
             */

            $stats['approved_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM documents
                     WHERE reviewed_by=?
                     AND status='approved'",
                    [$userId]
                )['total'] ?? 0
            );


            /*
             * My pending documents.
             */

            $stats['pending_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM documents
                     WHERE uploaded_by=?
                     AND status='pending'",
                    [$userId]
                )['total'] ?? 0
            );


            /*
             * Documents rejected by me.
             */

            $stats['rejected_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(*) AS total
                     FROM documents
                     WHERE reviewed_by=?
                     AND status='rejected'",
                    [$userId]
                )['total'] ?? 0
            );


            /*
             * Documents shared by me.
             */

            $stats['shared_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(DISTINCT document_id) AS total
                     FROM document_shares
                     WHERE shared_by=?",
                    [$userId]
                )['total'] ?? 0
            );


            /*
             * Documents shared with me.
             */

            $stats['received_documents'] = (int)(
                fetchRow(
                    "SELECT COUNT(DISTINCT document_id) AS total
                     FROM document_shares
                     WHERE user_id=?",
                    [$userId]
                )['total'] ?? 0
            );

        }


        /*
         * ==========================================================
         * NOTIFICATIONS
         * ==========================================================
         */

        $stats['notifications'] = (int)(
            fetchRow(
                "SELECT COUNT(*) AS total
                 FROM notifications
                 WHERE user_id=?
                 AND is_read=0",
                [$userId]
            )['total'] ?? 0
        );


        return $stats;

    }


    /**
     * Documents by department.
     *
     * ADMIN:
     *     All departments.
     *
     * MANAGER:
     *     Their department only.
     */
    public static function getDepartmentActivity(
        $role = 'admin',
        $departmentId = null
    ){

        $role = self::normalizeRole($role);


        /*
         * Manager must have a department.
         */

        if($role === 'manager'){

            if((int)$departmentId <= 0){

                return [];

            }


            return fetchAll(

                "SELECT

                    d.name,

                    COUNT(doc.id) AS total

                 FROM departments d

                 LEFT JOIN documents doc
                    ON doc.department_id=d.id

                 WHERE d.id=?

                 GROUP BY d.id, d.name

                 ORDER BY d.name",

                [
                    (int)$departmentId
                ]

            );

        }


        /*
         * Admin.
         */

        if(self::isAdminRole($role)){

            return fetchAll(

                "SELECT

                    d.name,

                    COUNT(doc.id) AS total

                 FROM departments d

                 LEFT JOIN documents doc
                    ON doc.department_id=d.id

                 GROUP BY d.id, d.name

                 ORDER BY total DESC"

            );

        }


        return [];

    }


    /**
     * Document status chart.
     *
     * ADMIN:
     *     All documents.
     *
     * MANAGER:
     *     Their department only.
     */
    public static function getDocumentStatusChart(
        $role = 'admin',
        $departmentId = null
    ){

        $role = self::normalizeRole($role);


        if($role === 'manager'){

            if((int)$departmentId <= 0){

                return [];

            }


            return fetchAll(

                "SELECT

                    status,

                    COUNT(*) AS total

                 FROM documents

                 WHERE department_id=?

                 GROUP BY status

                 ORDER BY status",

                [
                    (int)$departmentId
                ]

            );

        }


        if(self::isAdminRole($role)){

            return fetchAll(

                "SELECT

                    status,

                    COUNT(*) AS total

                 FROM documents

                 GROUP BY status

                 ORDER BY status"

            );

        }


        return [];

    }


    /**
     * Monthly uploads.
     *
     * ADMIN:
     *     Organization-wide.
     *
     * MANAGER:
     *     Their department only.
     */
    public static function getMonthlyUploads(
        $role = 'admin',
        $departmentId = null
    ){

        $role = self::normalizeRole($role);


        if($role === 'manager'){

            if((int)$departmentId <= 0){

                return [];

            }


            return fetchAll(

                "SELECT

                    DATE_FORMAT(created_at,'%b') AS month,

                    COUNT(*) AS total

                 FROM documents

                 WHERE YEAR(created_at)=YEAR(CURDATE())

                 AND department_id=?

                 GROUP BY
                    MONTH(created_at),
                    DATE_FORMAT(created_at,'%b')

                 ORDER BY MONTH(created_at)",

                [
                    (int)$departmentId
                ]

            );

        }


        if(self::isAdminRole($role)){

            return fetchAll(

                "SELECT

                    DATE_FORMAT(created_at,'%b') AS month,

                    COUNT(*) AS total

                 FROM documents

                 WHERE YEAR(created_at)=YEAR(CURDATE())

                 GROUP BY
                    MONTH(created_at),
                    DATE_FORMAT(created_at,'%b')

                 ORDER BY MONTH(created_at)"

            );

        }


        return [];

    }


    /**
     * Recent documents.
     *
     * ADMIN:
     *     Organization-wide.
     *
     * MANAGER:
     *     Their department.
     *
     * USER:
     *     Documents concerning the user.
     */
    public static function getRecentDocuments(
        $userId,
        $role = 'user',
        $departmentId = null,
        $limit = 10
    ){

        $limit = (int)$limit;

        if($limit < 1){
            $limit = 10;
        }


        $role = self::normalizeRole($role);


        /*
         * ==========================================================
         * MANAGER
         * ==========================================================
         */

        if($role === 'manager'){

            if((int)$departmentId <= 0){

                return [];

            }


            return fetchAll(

                "SELECT

                    doc.id,

                    doc.title,

                    doc.status,

                    doc.created_at,

                    doc.updated_at,

                    d.name AS department,

                    CONCAT(
                        IFNULL(u.first_name,''),
                        ' ',
                        IFNULL(u.last_name,'')
                    ) AS uploaded_by

                 FROM documents doc

                 LEFT JOIN departments d
                    ON d.id=doc.department_id

                 LEFT JOIN users u
                    ON u.id=doc.uploaded_by

                 WHERE doc.department_id=?

                 ORDER BY
                    COALESCE(
                        doc.updated_at,
                        doc.created_at
                    ) DESC

                 LIMIT " . $limit,

                [
                    (int)$departmentId
                ]

            );

        }


        /*
         * ==========================================================
         * ADMIN
         * ==========================================================
         */

        if(self::isAdminRole($role)){

            return fetchAll(

                "SELECT

                    doc.id,

                    doc.title,

                    doc.status,

                    doc.created_at,

                    doc.updated_at,

                    d.name AS department,

                    CONCAT(
                        IFNULL(u.first_name,''),
                        ' ',
                        IFNULL(u.last_name,'')
                    ) AS uploaded_by

                 FROM documents doc

                 LEFT JOIN departments d
                    ON d.id=doc.department_id

                 LEFT JOIN users u
                    ON u.id=doc.uploaded_by

                 ORDER BY
                    COALESCE(
                        doc.updated_at,
                        doc.created_at
                    ) DESC

                 LIMIT " . $limit

            );

        }


        /*
         * ==========================================================
         * REGULAR USER
         * ==========================================================
         */

        return fetchAll(

            "SELECT DISTINCT

                doc.id,

                doc.title,

                doc.status,

                doc.created_at,

                doc.updated_at,

                d.name AS department,

                CONCAT(
                    IFNULL(u.first_name,''),
                    ' ',
                    IFNULL(u.last_name,'')
                ) AS uploaded_by

             FROM documents doc

             LEFT JOIN departments d
                ON d.id=doc.department_id

             LEFT JOIN users u
                ON u.id=doc.uploaded_by

             LEFT JOIN document_shares ds
                ON ds.document_id=doc.id

             WHERE

                doc.uploaded_by=?

                OR doc.reviewed_by=?

                OR ds.user_id=?

                OR ds.shared_by=?

             ORDER BY
                COALESCE(
                    doc.updated_at,
                    doc.created_at
                ) DESC

             LIMIT " . $limit,

            [
                $userId,
                $userId,
                $userId,
                $userId
            ]

        );

    }


    /**
     * Recent activity.
     *
     * USER:
     *     Personal activity.
     *
     * MANAGER:
     *     Activity from users in their department.
     *
     * ADMIN:
     *     Organization-wide activity.
     */
    public static function getRecentActivity(
        $userId,
        $limit = 10,
        $role = 'user',
        $departmentId = null
    ){

        $limit = (int)$limit;

        if($limit < 1){
            $limit = 10;
        }


        $role = self::normalizeRole($role);


        /*
         * ==========================================================
         * ADMIN
         * ==========================================================
         */

        if(self::isAdminRole($role)){

            return fetchAll(

                "SELECT

                    a.action,

                    a.entity_type,

                    a.entity_id,

                    a.created_at,

                    CONCAT(
                        IFNULL(u.first_name,''),
                        ' ',
                        IFNULL(u.last_name,'')
                    ) AS user_name

                 FROM audit_logs a

                 LEFT JOIN users u
                    ON u.id=a.user_id

                 ORDER BY a.created_at DESC

                 LIMIT " . $limit

            );

        }


        /*
         * ==========================================================
         * MANAGER
         * ==========================================================
         */

        if($role === 'manager'){

            if((int)$departmentId <= 0){

                return [];

            }


            return fetchAll(

                "SELECT

                    a.action,

                    a.entity_type,

                    a.entity_id,

                    a.created_at,

                    CONCAT(
                        IFNULL(u.first_name,''),
                        ' ',
                        IFNULL(u.last_name,'')
                    ) AS user_name

                 FROM audit_logs a

                 INNER JOIN users u
                    ON u.id=a.user_id

                 WHERE u.department_id=?

                 ORDER BY a.created_at DESC

                 LIMIT " . $limit,

                [
                    (int)$departmentId
                ]

            );

        }


        /*
         * ==========================================================
         * REGULAR USER
         * ==========================================================
         */

        return fetchAll(

            "SELECT

                a.action,

                a.entity_type,

                a.entity_id,

                a.created_at,

                CONCAT(
                    IFNULL(u.first_name,''),
                    ' ',
                    IFNULL(u.last_name,'')
                ) AS user_name

             FROM audit_logs a

             LEFT JOIN users u
                ON u.id=a.user_id

             WHERE a.user_id=?

             ORDER BY a.created_at DESC

             LIMIT " . $limit,

            [
                $userId
            ]

        );

    }


    /**
     * Pending approvals.
     *
     * ADMIN:
     *     All pending documents.
     *
     * MANAGER:
     *     Pending documents in their department.
     *
     * USER:
     *     Their own pending documents.
     */
    public static function getPendingApprovals(
        $userId,
        $role = 'user',
        $departmentId = null,
        $limit = 5
    ){

        $limit = (int)$limit;

        if($limit < 1){
            $limit = 5;
        }


        $role = self::normalizeRole($role);


        /*
         * ==========================================================
         * MANAGER
         * ==========================================================
         */

        if($role === 'manager'){

            if((int)$departmentId <= 0){

                return [];

            }


            return fetchAll(

                "SELECT

                    doc.id,

                    doc.title,

                    doc.status,

                    d.name AS department,

                    CONCAT(
                        IFNULL(u.first_name,''),
                        ' ',
                        IFNULL(u.last_name,'')
                    ) AS uploaded_by,

                    doc.created_at

                 FROM documents doc

                 LEFT JOIN departments d
                    ON d.id=doc.department_id

                 LEFT JOIN users u
                    ON u.id=doc.uploaded_by

                 WHERE

                    doc.status='pending'

                    AND doc.department_id=?

                 ORDER BY doc.created_at ASC

                 LIMIT " . $limit,

                [
                    (int)$departmentId
                ]

            );

        }


        /*
         * ==========================================================
         * ADMIN
         * ==========================================================
         */

        if(self::isAdminRole($role)){

            return fetchAll(

                "SELECT

                    doc.id,

                    doc.title,

                    doc.status,

                    d.name AS department,

                    CONCAT(
                        IFNULL(u.first_name,''),
                        ' ',
                        IFNULL(u.last_name,'')
                    ) AS uploaded_by,

                    doc.created_at

                 FROM documents doc

                 LEFT JOIN departments d
                    ON d.id=doc.department_id

                 LEFT JOIN users u
                    ON u.id=doc.uploaded_by

                 WHERE doc.status='pending'

                 ORDER BY doc.created_at ASC

                 LIMIT " . $limit

            );

        }


        /*
         * ==========================================================
         * REGULAR USER
         * ==========================================================
         */

        return fetchAll(

            "SELECT

                doc.id,

                doc.title,

                doc.status,

                d.name AS department,

                CONCAT(
                    IFNULL(u.first_name,''),
                    ' ',
                    IFNULL(u.last_name,'')
                ) AS uploaded_by,

                doc.created_at

             FROM documents doc

             LEFT JOIN departments d
                ON d.id=doc.department_id

             LEFT JOIN users u
                ON u.id=doc.uploaded_by

             WHERE

                doc.status='pending'

                AND doc.uploaded_by=?

             ORDER BY doc.created_at ASC

             LIMIT " . $limit,

            [
                $userId
            ]

        );

    }


    /**
     * Top uploaders.
     *
     * ADMIN:
     *     Organization-wide.
     *
     * MANAGER:
     *     Their department only.
     */
    public static function getTopUploaders(
        $role = 'admin',
        $departmentId = null
    ){

        $role = self::normalizeRole($role);


        /*
         * ==========================================================
         * MANAGER
         * ==========================================================
         */

        if($role === 'manager'){

            if((int)$departmentId <= 0){

                return [];

            }


            return fetchAll(

                "SELECT

                    CONCAT(
                        IFNULL(u.first_name,''),
                        ' ',
                        IFNULL(u.last_name,'')
                    ) AS name,

                    COUNT(doc.id) AS total

                 FROM users u

                 INNER JOIN documents doc
                    ON doc.uploaded_by=u.id

                 WHERE

                    u.department_id=?

                    AND doc.department_id=?

                 GROUP BY
                    u.id,
                    u.first_name,
                    u.last_name

                 ORDER BY total DESC

                 LIMIT 5",

                [
                    (int)$departmentId,
                    (int)$departmentId
                ]

            );

        }


        /*
         * ==========================================================
         * ADMIN
         * ==========================================================
         */

        if(self::isAdminRole($role)){

            return fetchAll(

                "SELECT

                    CONCAT(
                        IFNULL(u.first_name,''),
                        ' ',
                        IFNULL(u.last_name,'')
                    ) AS name,

                    COUNT(doc.id) AS total

                 FROM users u

                 INNER JOIN documents doc
                    ON doc.uploaded_by=u.id

                 GROUP BY
                    u.id,
                    u.first_name,
                    u.last_name

                 ORDER BY total DESC

                 LIMIT 5"

            );

        }


        return [];

    }


    /**
     * Unread notifications.
     */
    public static function getUnreadNotifications($userId)
    {

        $row = fetchRow(

            "SELECT COUNT(*) AS total

             FROM notifications

             WHERE user_id=?

             AND is_read=0",

            [
                $userId
            ]

        );


        return (int)(
            $row['total'] ?? 0
        );

    }


    /**
     * Storage used by signed-in user.
     */
    public static function getStorageUsage($userId)
    {

        $row = fetchRow(

            "SELECT

                COALESCE(
                    SUM(file_size),
                    0
                ) AS total

             FROM documents

             WHERE uploaded_by=?",

            [
                $userId
            ]

        );


        $size = (int)(
            $row['total'] ?? 0
        );


        if($size >= 1073741824){

            return round(
                $size / 1073741824,
                2
            ) . " GB";

        }


        if($size >= 1048576){

            return round(
                $size / 1048576,
                2
            ) . " MB";

        }


        if($size >= 1024){

            return round(
                $size / 1024,
                2
            ) . " KB";

        }


        return $size . " B";

    }

}
?>