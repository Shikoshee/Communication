<?php

/*
|--------------------------------------------------------------------------
| CREATE USER API
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

header("Content-Type: application/json; charset=UTF-8");

Auth::protect();


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function jsonResponse($success, $message, $extra = [])
{
    /*
     * Remove anything accidentally printed by included files/functions.
     */
    if (ob_get_level() > 0) {
        ob_clean();
    }

    echo json_encode(
        array_merge(
            [
                "success" => $success,
                "message" => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Start output buffering
|--------------------------------------------------------------------------
*/

ob_start();


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    jsonResponse(
        false,
        "Invalid request method."
    );

}


/*
|--------------------------------------------------------------------------
| READ INPUT
|--------------------------------------------------------------------------
*/

$firstName = trim(
    $_POST['first_name'] ?? ''
);

$lastName = trim(
    $_POST['last_name'] ?? ''
);

$username = trim(
    $_POST['username'] ?? ''
);

$email = trim(
    $_POST['email'] ?? ''
);

$departmentId = (int)(
    $_POST['department_id'] ?? 0
);

$role = strtolower(
    trim($_POST['role'] ?? 'user')
);

$status = strtolower(
    trim($_POST['status'] ?? 'active')
);


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if ($firstName === '') {

    jsonResponse(
        false,
        "First name is required."
    );

}


if ($lastName === '') {

    jsonResponse(
        false,
        "Last name is required."
    );

}


if ($username === '') {

    jsonResponse(
        false,
        "Username is required."
    );

}


if ($email === '') {

    jsonResponse(
        false,
        "Email address is required."
    );

}


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    jsonResponse(
        false,
        "Please enter a valid email address."
    );

}


if ($departmentId <= 0) {

    jsonResponse(
        false,
        "Please select a department."
    );

}


/*
|--------------------------------------------------------------------------
| ROLE
|--------------------------------------------------------------------------
*/

$allowedRoles = [
    'user',
    'manager',
    'admin'
];

if (!in_array($role, $allowedRoles, true)) {

    jsonResponse(
        false,
        "Invalid role selected."
    );

}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'active',
    'inactive',
    'locked'
];

if (!in_array($status, $allowedStatuses, true)) {

    jsonResponse(
        false,
        "Invalid account status."
    );

}


/*
|--------------------------------------------------------------------------
| CHECK USERNAME
|--------------------------------------------------------------------------
*/

$existingUsername = fetchRow(
    "
    SELECT id
    FROM users
    WHERE username = ?
    LIMIT 1
    ",
    [$username]
);

if ($existingUsername) {

    jsonResponse(
        false,
        "Username already exists."
    );

}


/*
|--------------------------------------------------------------------------
| CHECK EMAIL
|--------------------------------------------------------------------------
*/

$existingEmail = fetchRow(
    "
    SELECT id
    FROM users
    WHERE email = ?
    LIMIT 1
    ",
    [$email]
);

if ($existingEmail) {

    jsonResponse(
        false,
        "Email address already exists."
    );

}


/*
|--------------------------------------------------------------------------
| CHECK DEPARTMENT
|--------------------------------------------------------------------------
*/

$department = fetchRow(
    "
    SELECT id
    FROM departments
    WHERE id = ?
    LIMIT 1
    ",
    [$departmentId]
);

if (!$department) {

    jsonResponse(
        false,
        "Selected department does not exist."
    );

}


/*
|--------------------------------------------------------------------------
| TEMPORARY PASSWORD
|--------------------------------------------------------------------------
*/

$tempPassword = "Password@123";


/*
|--------------------------------------------------------------------------
| PASSWORD HASH
|--------------------------------------------------------------------------
*/

$hashedPassword = password_hash(
    $tempPassword,
    PASSWORD_DEFAULT
);

if ($hashedPassword === false) {

    jsonResponse(
        false,
        "Unable to generate the user password."
    );

}


/*
|--------------------------------------------------------------------------
| USER DATA
|--------------------------------------------------------------------------
*/

$data = [

    "first_name" => $firstName,

    "last_name" => $lastName,

    "username" => $username,

    "email" => $email,

    "department_id" => $departmentId,

    "role" => $role,

    "status" => $status,

    "password" => $hashedPassword

];


/*
|--------------------------------------------------------------------------
| INSERT
|--------------------------------------------------------------------------
*/

try {

    $result = insertData(
        "users",
        $data
    );

} catch (Throwable $e) {

    /*
     * Write the real error to PHP's error log.
     *
     * It will NOT be sent to the browser.
     */

    error_log(
        "CREATE USER ERROR: " .
        $e->getMessage()
    );

    jsonResponse(
        false,
        "Unable to create the user. Please try again."
    );

}


/*
|--------------------------------------------------------------------------
| CHECK INSERT RESULT
|--------------------------------------------------------------------------
*/

if (!is_array($result)) {

    error_log(
        "CREATE USER ERROR: insertData() did not return an array."
    );

    jsonResponse(
        false,
        "Unable to create the user."
    );

}


if (empty($result['success'])) {

    $databaseError =
        $result['error']
        ?? "Unknown database error.";

    /*
     * Log database details.
     */
    error_log(
        "CREATE USER DATABASE ERROR: " .
        $databaseError
    );

    /*
     * Do not expose SQL/database internals
     * to the browser.
     */

    jsonResponse(
        false,
        "Unable to create the user. The username or email may already exist."
    );

}


/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

jsonResponse(
    true,
    "User created successfully.",
    [
        "temporary_password" => $tempPassword
    ]
);
