<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request."
    ]);

    exit;
}


/*
 * ==========================================================
 * INPUT
 * ==========================================================
 */

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

if ($name === '') {

    echo json_encode([
        "success" => false,
        "message" => "Department name is required."
    ]);

    exit;
}


/*
 * ==========================================================
 * CHECK DUPLICATE DEPARTMENT
 * ==========================================================
 */

$exists = fetchRow(

    "SELECT id
     FROM departments
     WHERE name=?
     LIMIT 1",

    [
        $name
    ]

);

if ($exists) {

    echo json_encode([
        "success" => false,
        "message" => "Department already exists."
    ]);

    exit;
}


/*
 * ==========================================================
 * VALIDATE DEPARTMENT HEAD
 * ==========================================================
 *
 * Only active managers/admins/administrators
 * can be assigned as department heads.
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
 * CREATE DEPARTMENT
 * ==========================================================
 */

$result = insertData(

    "departments",

    [

        "name" => $name,

        "description" => $description,

        "head_id" => $headId > 0
            ? $headId
            : null,

        "status" => $status

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

$departmentId = $result['insert_id'];

insertData(

    "activity_logs",

    [

        "user_id" =>
            $user['id'],

        "department_id" =>
            $departmentId,

        "activity" =>
            "Created department " . $name,

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
        "Department created successfully."

]);

