<?php
/**
 * Login Page - Communication System
 * Uses database authentication
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

// If already logged in, redirect to dashboard
if (Auth::isAuthenticated()) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (!empty($username) && !empty($password)) {
        $result = Auth::login($username, $password);
        
        if ($result['success']) {


    if($result['must_change_password']){

        header("Location: change-password.php");
        exit();

    }


    $success_message = "Login successful! Redirecting...";
    header('Refresh: 2; url=dashboard.php');

} else {
            $error_message = $result['message'];
        }
    } else {
        $error_message = "Please enter both username and password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Communication System | Login</title>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login.css">

    <!-- Font Awesome -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .demo-credentials {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
        }

        .demo-credentials h4 {
            margin-top: 0;
            color: #004085;
        }

        .demo-credentials p {
            margin: 5px 0;
            color: #0c5460;
        }

        .demo-credentials code {
            background-color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>

</head>

<body>

    <div class="login-container">

        <div class="login-box">

            <div class="logo">
                <i class="fa-solid fa-comments"></i>
                <h1>Communication</h1>
                <p>Document & Department Communication System</p>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="loginForm">

                <div class="input-group">
                    <label>Username</label>
                    <div class="input-field">
                        <i class="fa fa-user"></i>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter Username"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            required
                            autocomplete="username">
                    </div>

                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="input-field">
                        <i class="fa fa-lock"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter Password"
                            required
                            autocomplete="current-password">
                        <i
                            class="fa-solid fa-eye toggle-password"
                            id="togglePassword"></i>
                    </div>
                </div>

                <button type="submit">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Login
                </button>

            </form>

          

    <!-- JavaScript for password toggle -->
    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Missing Fields',
                    text: 'Please enter both username and password'
                });
            }
        });
    </script>

</body>

</html>