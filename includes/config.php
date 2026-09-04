<?php
/**
 * Database Configuration File
 * Communication System
 */

// Database Connection Details
define('DB_HOST', 'localhost');      // MySQL Host
define('DB_USER', 'root');           // MySQL Username
define('DB_PASS', '');               // MySQL Password
define('DB_NAME', 'communication_system'); // Database Name
define('DB_PORT', 3306);             // MySQL Port

// Application Settings
define('APP_NAME', 'Communication System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/communication'); // Your app URL
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

// File Upload Settings
define('UPLOAD_DIR', __DIR__ . '/uploads/documents/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_EXTENSIONS', array('pdf', 'docx', 'xlsx', 'doc', 'xls', 'ppt', 'pptx', 'txt'));
define('MYSQLDUMP_PATH', 'C:/xampp/mysql/bin/mysqldump.exe');
define('BACKUP_DIR', __DIR__ . '/../backups/database/');


// Error Reporting (Disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// MySQL Connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    // Check connection
    if ($conn->connect_error) {
        die("Database Connection Failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

/**
 * Function to execute a query
 */
function executeQuery($query, $params = array()) {
    global $conn;
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return array('success' => false, 'error' => $conn->error);
    }
    
    if (!empty($params)) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        return array(
            'success' => true,
            'statement' => $stmt
        );
    } else {
        return array(
            'success' => false,
            'error' => $stmt->error
        );
    }
}

/**
 * Function to fetch single row
 */
function fetchRow($query, $params = array()) {
    $result = executeQuery($query, $params);
    
    if ($result['success']) {
        $resultSet = $result['statement']->get_result();
        return $resultSet->fetch_assoc();
    }
    
    return null;
}

/**
 * Function to fetch all rows
 */
function fetchAll($query, $params = array()) {
    $result = executeQuery($query, $params);
    
    if ($result['success']) {
        $resultSet = $result['statement']->get_result();
        $data = array();
        
        while ($row = $resultSet->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    return array();
}

/**
 * Function to count rows
 */
function countRows($query, $params = array()) {
    $result = executeQuery($query, $params);
    
    if ($result['success']) {
        $resultSet = $result['statement']->get_result();
        return $resultSet->num_rows;
    }
    
    return 0;
}

/**
 * Function to insert data
 */
function insertData($table, $data) {
    global $conn;
    
    $columns = array_keys($data);
    $values = array_values($data);
    $placeholders = array_fill(0, count($values), '?');
    
    $query = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return array('success' => false, 'error' => $conn->error);
    }
    
    $types = '';
    foreach ($values as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        return array(
            'success' => true,
            'insert_id' => $conn->insert_id,
            'affected_rows' => $stmt->affected_rows
        );
    } else {
        return array(
            'success' => false,
            'error' => $stmt->error
        );
    }
}

/**
 * Function to update data
 */
function updateData($table, $data, $where) {
    global $conn;
    
    $setClause = array();
    $values = array();
    $types = '';
    
    foreach ($data as $column => $value) {
        $setClause[] = "$column = ?";
        $values[] = $value;
        
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    $whereClause = array();
    foreach ($where as $column => $value) {
        $whereClause[] = "$column = ?";
        $values[] = $value;
        
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    $query = "UPDATE $table SET " . implode(',', $setClause) . " WHERE " . implode(' AND ', $whereClause);
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return array('success' => false, 'error' => $conn->error);
    }
    
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        return array(
            'success' => true,
            'affected_rows' => $stmt->affected_rows
        );
    } else {
        return array(
            'success' => false,
            'error' => $stmt->error
        );
    }
}

/**
 * Function to delete data
 */
function deleteData($table, $where) {
    global $conn;
    
    $whereClause = array();
    $values = array();
    $types = '';
    
    foreach ($where as $column => $value) {
        $whereClause[] = "$column = ?";
        $values[] = $value;
        
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    $query = "DELETE FROM $table WHERE " . implode(' AND ', $whereClause);
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        return array('success' => false, 'error' => $conn->error);
    }
    
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        return array(
            'success' => true,
            'affected_rows' => $stmt->affected_rows
        );
    } else {
        return array(
            'success' => false,
            'error' => $stmt->error
        );
    }
}

/**
 * Function to escape input
 */
function escape($string) {
    global $conn;
    return $conn->real_escape_string($string);
}
function getSetting($key, $default = null)
{
    $setting = fetchRow(
        "SELECT setting_value
         FROM settings
         WHERE setting_key=?",
        [$key]
    );

    if (!$setting) {
        return $default;
    }

    return $setting['setting_value'];
}

function setSetting($key, $value, $userId = null)
{
    $exists = fetchRow(
        "SELECT id
         FROM settings
         WHERE setting_key=?",
        [$key]
    );

    if ($exists) {

        executeQuery(

            "UPDATE settings

            SET

            setting_value=?,

            updated_by=?

            WHERE setting_key=?",

            [

                $value,

                $userId,

                $key

            ]

        );

    } else {

        executeQuery(

            "INSERT INTO settings

            (

            setting_key,

            setting_value,

            updated_by

            )

            VALUES

            (

            ?,

            ?,

            ?

            )",

            [

                $key,

                $value,

                $userId

            ]

        );

    }

    return true;
}
?>