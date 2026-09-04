<?php

class Permission
{
    private static $permissions = null;
    private static $user = null;


    /*
    |--------------------------------------------------------------------------
    | LOAD CURRENT USER + PERMISSIONS
    |--------------------------------------------------------------------------
    */

    private static function load()
    {
        if (self::$permissions !== null) {
            return;
        }

        self::$user = Auth::getCurrentUser();


        /*
         * No authenticated user.
         */

        if (!self::$user) {

            self::$permissions = [
                'can_view'    => 0,
                'can_edit'    => 0,
                'can_approve' => 0,
                'can_delete'  => 0,
                'can_share'   => 0
            ];

            return;
        }


        /*
         * Load permission record.
         */

        self::$permissions = fetchRow(

            "SELECT
                can_view,
                can_edit,
                can_approve,
                can_delete,
                can_share
             FROM permissions
             WHERE user_id = ?
             LIMIT 1",

            [
                (int)self::$user['id']
            ]

        );


        /*
         * No permission record means
         * no special permissions.
         */

        if (!self::$permissions) {

            self::$permissions = [
                'can_view'    => 0,
                'can_edit'    => 0,
                'can_approve' => 0,
                'can_delete'  => 0,
                'can_share'   => 0
            ];

        }

    }


    /*
    |--------------------------------------------------------------------------
    | ROLE
    |--------------------------------------------------------------------------
    */

    private static function getRole()
    {
        self::load();

        return strtolower(
            trim(
                (string)(
                    self::$user['role'] ?? 'user'
                )
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN
    |--------------------------------------------------------------------------
    */

    public static function isAdmin()
    {
        $role = self::getRole();

        return in_array(
            $role,
            [
                'admin',
                'administrator'
            ],
            true
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MANAGER
    |--------------------------------------------------------------------------
    */

    public static function isManager()
    {
        $role = self::getRole();

        return (
            $role === 'manager'
            ||
            str_contains($role, 'manager')
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN OR MANAGER
    |--------------------------------------------------------------------------
    */

    public static function isAdminOrManager()
    {
        return (
            self::isAdmin()
            ||
            self::isManager()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DEPARTMENT
    |--------------------------------------------------------------------------
    */

    public static function getDepartmentId()
    {
        self::load();

        if (!self::$user) {
            return 0;
        }


        $departmentId = (int)(
            self::$user['department_id'] ?? 0
        );


        /*
         * If the authenticated user object
         * does not contain department_id,
         * load it directly from users.
         */

        if ($departmentId <= 0) {

            $row = fetchRow(

                "SELECT department_id
                 FROM users
                 WHERE id = ?
                 LIMIT 1",

                [
                    (int)self::$user['id']
                ]

            );

            $departmentId = (int)(
                $row['department_id'] ?? 0
            );

        }


        return $departmentId;
    }


    /*
    |--------------------------------------------------------------------------
    | MANAGE USER PERMISSIONS
    |--------------------------------------------------------------------------
    */

    public static function canManageUser($targetUserId)
    {
        self::load();

        $targetUserId = (int)$targetUserId;


        if ($targetUserId <= 0) {
            return false;
        }


        /*
         * Admin can manage everyone.
         */

        if (self::isAdmin()) {
            return true;
        }


        /*
         * Manager can manage only
         * users in their own department.
         */

        if (self::isManager()) {

            $managerDepartmentId =
                self::getDepartmentId();


            if ($managerDepartmentId <= 0) {
                return false;
            }


            $target = fetchRow(

                "SELECT department_id
                 FROM users
                 WHERE id = ?
                 LIMIT 1",

                [
                    $targetUserId
                ]

            );


            if (!$target) {
                return false;
            }


            $targetDepartmentId = (int)(
                $target['department_id'] ?? 0
            );


            return (
                $targetDepartmentId ===
                $managerDepartmentId
            );

        }


        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public static function canView()
    {
        self::load();


        /*
         * Admin always has full access.
         */

        if (self::isAdmin()) {
            return true;
        }


        return (bool)(
            self::$permissions['can_view'] ?? 0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public static function canEdit()
    {
        self::load();


        /*
         * Admin always has full access.
         */

        if (self::isAdmin()) {
            return true;
        }


        return (bool)(
            self::$permissions['can_edit'] ?? 0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */

    public static function canApprove()
    {
        self::load();


        /*
         * Admin always has full access.
         */

        if (self::isAdmin()) {
            return true;
        }


        return (bool)(
            self::$permissions['can_approve'] ?? 0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public static function canDelete()
    {
        self::load();


        /*
         * Admin always has full access.
         */

        if (self::isAdmin()) {
            return true;
        }


        return (bool)(
            self::$permissions['can_delete'] ?? 0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SHARE
    |--------------------------------------------------------------------------
    */

    public static function canShare()
    {
        self::load();


        /*
         * Admin always has full access.
         */

        if (self::isAdmin()) {
            return true;
        }


        return (bool)(
            self::$permissions['can_share'] ?? 0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPLOAD
    |--------------------------------------------------------------------------
    |
    | Upload is intentionally NOT permission controlled.
    |
    | Everyone can upload.
    |
    */

    public static function canUpload()
    {
        return true;
    }

}
