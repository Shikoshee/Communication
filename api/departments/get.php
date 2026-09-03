<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$id = (int)($_GET['id'] ?? 0);


if ($id <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid department ID."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| GET DEPARTMENT
|--------------------------------------------------------------------------
*/

$department = fetchRow(

    "SELECT
        d.id,
        d.name,
        d.description,
        d.head_id,
        d.status,
        d.created_at,
        d.updated_at,

        u.first_name AS head_first_name,
        u.last_name AS head_last_name,
        u.email AS head_email,
        u.phone AS head_phone,
        u.role AS head_role

     FROM departments d

     LEFT JOIN users u
        ON d.head_id = u.id

     WHERE d.id = ?

     LIMIT 1",

    [$id]

);


if (!$department) {

    echo json_encode([
        "success" => false,
        "message" => "Department not found."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "department" => [

        "id" =>
            (int)$department['id'],

        "name" =>
            (string)($department['name'] ?? ""),

        "description" =>
            (string)($department['description'] ?? ""),

        "head_id" =>
            !empty($department['head_id'])
                ? (int)$department['head_id']
                : null,

        "status" =>
            strtolower(
                trim(
                    (string)($department['status'] ?? "active")
                )
            ),

        "created_at" =>
            $department['created_at'] ?? null,

        "updated_at" =>
            $department['updated_at'] ?? null,

        "head" => [

            "id" =>
                !empty($department['head_id'])
                    ? (int)$department['head_id']
                    : null,

            "first_name" =>
                $department['head_first_name'] ?? "",

            "last_name" =>
                $department['head_last_name'] ?? "",

            "email" =>
                $department['head_email'] ?? "",

            "phone" =>
                $department['head_phone'] ?? "",

            "role" =>
                $department['head_role'] ?? ""

        ]

    ]

]);
