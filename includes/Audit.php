<?php

class Audit
{

    public static function log(
        $action,
        $entityType,
        $entityId = null,
        $oldValue = null,
        $newValue = null
    )
    {

        $user = Auth::getCurrentUser();

        executeQuery(

            "INSERT INTO audit_logs
            (
                user_id,
                action,
                entity_type,
                entity_id,
                old_value,
                new_value,
                ip_address,
                user_agent
            )

            VALUES
            (?,?,?,?,?,?,?,?)",

            [

                $user ? $user['id'] : null,

                $action,

                $entityType,

                $entityId,

                $oldValue,

                $newValue,

                $_SERVER['REMOTE_ADDR'] ?? null,

                $_SERVER['HTTP_USER_AGENT'] ?? null

            ]

        );

    }

}