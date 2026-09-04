<?php
/**
 * Authentication Handler
 * Communication System
 */

session_start();

require_once 'config.php';
require_once 'Permission.php';
class Auth {
    
    /**
     * Login user
     */
    public static function login($username, $password) {
        global $conn;
        
        // Validate input
        if (empty($username) || empty($password)) {
            return array(
                'success' => false,
                'message' => 'Username and password are required'
            );
        }
        
        // Check user exists
        $query = "SELECT 
            id,
            username,
            email,
            first_name,
            last_name,
            password,
            role,
            department_id,
            status,
            must_change_password
          FROM users 
          WHERE username = ? 
          AND status = 'active'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            return array(
                'success' => false,
                'message' => 'Invalid username or password'
            );
        }
        
        // Verify password
if (!password_verify($password, $user['password'])) {
    return [
        'success' => false,
        'message' => 'Invalid username or password'
    ];
}
        
        // Create session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['department_id'] = $user['department_id'];
$_SESSION['must_change_password'] = $user['must_change_password'];
$_SESSION['logged_in'] = true;
        
        // Update last login
        $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('i', $user['id']);
        $updateStmt->execute();
        
        // Log activity
        self::logActivity($user['id'], 'User logged in', null, null, 'login');
        
        return array(
    'success' => true,
    'message' => 'Login successful',
    'must_change_password' => $user['must_change_password'],
            'user' => array(
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'role' => $user['role']
            )
        );
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        $user_id = $_SESSION['user_id'] ?? null;
        
        if ($user_id) {
            self::logActivity($user_id, 'User logged out', null, null, 'logout');
        }
        
        // Destroy session
        session_destroy();
        
        return array(
            'success' => true,
            'message' => 'Logout successful'
        );
    }
    
    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current user
     */
    public static function getCurrentUser() {
        if (!self::isAuthenticated()) {
            return null;
        }
        
        return array(
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'first_name' => $_SESSION['first_name'],
            'last_name' => $_SESSION['last_name'],
            'role' => $_SESSION['role'],
            'department_id' => $_SESSION['department_id']
        );
    }
    
    /**
     * Check user role
     */
    public static function hasRole($role) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        return $_SESSION['role'] === $role;
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return self::hasRole('admin');
    }
    
    /**
     * Check if user is manager
     */
    public static function isManager() {
        return self::hasRole('manager');
    }
    
    /**
     * Check multiple roles
     */
    public static function hasAnyRole($roles) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        return in_array($_SESSION['role'], $roles);
    }
    
    /**
     * Register new user (admin only)
     */
    public static function register($username, $email, $password, $first_name, $last_name, $department_id, $role = 'user') {
        global $conn;
        
        // Validate input
        if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
            return array(
                'success' => false,
                'message' => 'All fields are required'
            );
        }
        
        // Check if username exists
        $checkQuery = "SELECT id FROM users WHERE username = ? OR email = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param('ss', $username, $email);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows > 0) {
            return array(
                'success' => false,
                'message' => 'Username or email already exists'
            );
        }
        
        // Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);        
        // Insert user
        $query = "INSERT INTO users (username, email, password, first_name, last_name, department_id, role, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sssssss', $username, $email, $hashedPassword, $first_name, $last_name, $department_id, $role);
        
        if ($stmt->execute()) {
            return array(
                'success' => true,
                'message' => 'User registered successfully',
                'user_id' => $conn->insert_id
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Registration failed: ' . $stmt->error
            );
        }
    }
    
    /**
     * Change password
     */
    public static function changePassword($user_id, $current_password, $new_password) {
        global $conn;
        
        // Get current password
        $query = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            return array(
                'success' => false,
                'message' => 'User not found'
            );
        }
        
        // Verify current password
if (!password_verify($current_password, $user['password'])) {            return array(
                'success' => false,
                'message' => 'Current password is incorrect'
            );
        }
        
        // Update password
$newHashedPassword = password_hash($new_password, PASSWORD_DEFAULT);        $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('si', $newHashedPassword, $user_id);
        
        if ($updateStmt->execute()) {
            self::logActivity($user_id, 'Password changed', null, null, 'edit');
            
            return array(
                'success' => true,
                'message' => 'Password changed successfully'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Failed to change password'
            );
        }
    }
    
    /**
     * Log activity
     */
    public static function logActivity($user_id, $activity, $document_id = null, $department_id = null, $activity_type = 'view') {
        global $conn;
        
        $query = "INSERT INTO activity_logs (user_id, activity, document_id, department_id, activity_type) 
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('isiss', $user_id, $activity, $document_id, $department_id, $activity_type);
        
        return $stmt->execute();
    }
    
    /**
     * Protect page (redirect if not authenticated)
     */
    public static function protect() {
        if (!self::isAuthenticated()) {
            header('Location: ' . APP_URL . '/index.php');
            exit();
        }
    }
    
    /**
     * Protect admin page
     */
    public static function protectAdmin() {
        self::protect();
        
        if (!self::isAdmin()) {
            header('HTTP/1.0 403 Forbidden');
            die('Access Denied: Admin privileges required');
        }
    }
}

?>