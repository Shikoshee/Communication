<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();


/*
 * ==========================================================
 * INPUT
 * ==========================================================
 */

$id = (int)(
    $_POST['id'] ?? 0
);

$name = trim(
    $_POST['name'] ?? ''
);

$description = trim(
    $_POST['description'] ?? ''
);

$headId = (int)(
    $_POST['head_id'] ?? 0
);

$status = strtolower(
    trim(
        $_POST['status'] ?? 'active'
    )
);


/*
 * ==========================================================
 * VALIDATION
 * ==========================================================
 */

if ($id <= 0 || $name === '') {

    echo json_encode([
        "success" => false,
        "message" => "Invalid department."
    ]);

    exit;

}


/*
 * ==========================================================
 * CHECK DEPARTMENT
 * ==========================================================
 */

$department = fetchRow(

    "SELECT id
     FROM departments
     WHERE id=?
     LIMIT 1",

    [
        $id
    ]

);

if (!$department) {

    echo json_encode([
        "success" => false,
        "message" => "Department not found."
    ]);

    exit;

}


/*
 * ==========================================================
 * CHECK DUPLICATE NAME
 * ==========================================================
 */

$exists = fetchRow(

    "SELECT id
     FROM departments
     WHERE name=?
     AND id<>?
     LIMIT 1",

    [
        $name,
        $id
    ]

);

if ($exists) {

    echo json_encode([
        "success" => false,
        "message" => "Department name already exists."
    ]);

    exit;

}


/*
 * ==========================================================
 * VALIDATE DEPARTMENT HEAD
 * ==========================================================
 */

if ($headId > 0) {

    $head = fetchRow(

        "SELECT
            id,
            role,
            status

         FROM users

         WHERE id=?

         LIMIT 1",

        [
            $headId
        ]

    );


    if (!$head) {

        echo json_encode([
            "success" => false,
            "message" => "Selected department head was not found."
        ]);

        exit;

    }


    $headRole = strtolower(
        trim(
            (string)($head['role'] ?? '')
        )
    );


    if (
        !in_array(
            $headRole,
            [
                'manager',
                'admin',
                'administrator'
            ],
            true
        )
    ) {

        echo json_encode([
            "success" => false,
            "message" => "Only managers or administrators can be department heads."
        ]);

        exit;

    }


    if (
        strtolower(
            (string)($head['status'] ?? '')
        ) !== 'active'
    ) {

        echo json_encode([
            "success" => false,
            "message" => "The selected department head is not active."
        ]);

        exit;

    }

}


/*
 * ==========================================================
 * UPDATE
 * ==========================================================
 */

$result = updateData(

    "departments",

    [

        "name" =>
            $name,

        "description" =>
            $description,

        "head_id" =>
            $headId > 0
                ? $headId
                : null,

        "status" =>
            $status

    ],

    [

        "id" =>
            $id

    ]

);


if (!$result['success']) {

    echo json_encode([
        "success" => false,
        "message" => $result['error']
    ]);

    exit;

}


/*
 * ==========================================================
 * ACTIVITY LOG
 * ==========================================================
 */

insertData(

    "activity_logs",

    [

        "user_id" =>
            $user['id'],

        "department_id" =>
            $id,

        "activity" =>
            "Updated department " . $name,

        "activity_type" =>
            "edit"

    ]

);


/*
 * ==========================================================
 * RESPONSE
 * ==========================================================
 */

echo json_encode([

    "success" => true,

    "message" =>
        "Department updated successfully."

]);
