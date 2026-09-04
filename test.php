<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once "includes/config.php";
require_once "includes/auth.php";
require_once "includes/Permission.php";

Auth::protect();

echo "AUTH OK<br>";

$user = Auth::getCurrentUser();

$userId = (int)($user['id'] ?? 0);

$userRole = strtolower(
    trim($user['role'] ?? 'user')
);

$isAdmin = in_array(
    $userRole,
    ['admin', 'administrator'],
    true
);

echo "USER ID: " . $userId . "<br>";
echo "ROLE: " . htmlspecialchars($userRole) . "<br>";
echo "IS ADMIN: " . ($isAdmin ? 'YES' : 'NO') . "<br>";

echo "<hr>";

echo "Testing database query...<br>";

try {

    if ($isAdmin) {

        $documents = fetchAll(
            "
            SELECT
                d.id,
                d.title,
                d.deleted_at,
                d.deleted_by,

                CONCAT(
                    IFNULL(u.first_name, ''),
                    ' ',
                    IFNULL(u.last_name, '')
                ) AS owner_name,

                CONCAT(
                    IFNULL(du.first_name, ''),
                    ' ',
                    IFNULL(du.last_name, '')
                ) AS deleted_by_name,

                dept.name AS department_name

            FROM documents d

            LEFT JOIN users u
                ON u.id = d.uploaded_by

            LEFT JOIN users du
                ON du.id = d.deleted_by

            LEFT JOIN departments dept
                ON dept.id = d.department_id

            WHERE d.is_deleted = 1

            ORDER BY d.deleted_at DESC
            "
        );

    } else {

        $documents = fetchAll(
            "
            SELECT
                d.id,
                d.title,
                d.deleted_at,
                d.deleted_by,

                CONCAT(
                    IFNULL(u.first_name, ''),
                    ' ',
                    IFNULL(u.last_name, '')
                ) AS owner_name,

                CONCAT(
                    IFNULL(du.first_name, ''),
                    ' ',
                    IFNULL(du.last_name, '')
                ) AS deleted_by_name,

                dept.name AS department_name

            FROM documents d

            LEFT JOIN users u
                ON u.id = d.uploaded_by

            LEFT JOIN users du
                ON du.id = d.deleted_by

            LEFT JOIN departments dept
                ON dept.id = d.department_id

            WHERE d.is_deleted = 1
              AND d.deleted_by = ?

            ORDER BY d.deleted_at DESC
            ",
            [$userId]
        );

    }

    echo "QUERY WORKED!<br>";

    echo "<pre>";
    var_dump($documents);
    echo "</pre>";

} catch (Throwable $e) {

    echo "<h3>DATABASE ERROR</h3>";

    echo "<pre>";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine();
    echo "</pre>";
}

echo "<br>TEST FINISHED";