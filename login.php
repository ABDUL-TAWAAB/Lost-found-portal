<?php
// =================================================================
// User Login Page (login.php)
// This page displays the login form and processes the login submission.
// It uses mysqli prepared statements and password_verify() via auth.php.
// =================================================================

// 1. Include dependencies
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// If already logged in, redirect them directly to dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit();
}

$error_msg = "";
$success_msg = "";

// Check if user has just logged out
if (isset($_GET['msg']) && $_GET['msg'] === 'loggedout') {
    $success_msg = "You have been logged out successfully.";
}

// 2. Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize user inputs to protect against XSS and clean data
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password']; // No need to sanitize passwords before checking hashes
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error_msg = "Please enter both email and password.";
    } else {
        // Call login helper from auth.php
        $login_result = login_user($conn, $email, $password);
        
        if ($login_result === true) {
            // Login successful! User details are stored in $_SESSION.
            // Redirect based on user role
            if ($_SESSION['user_role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: user/dashboard.php");
            }
            exit();
        } else {
            // Login failed, we display the error message returned from auth.php
            $error_msg = $login_result;
        }
    }
}

$base_path = "";
include_once 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Welcome Back!</h2>
            <p>Log in to access your dashboard, post items, and view claims.</p>
        </div>

        <!-- Success or Error Alert Boxes -->
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="login.php" method="POST" class="auth-form">
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                <input type="email" id="email" name="email" placeholder="e.g. name@school.edu" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your secret password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-large">
                <i class="fas fa-sign-in-alt"></i> Login Now
            </button>
        </form>

        <div class="auth-footer">
            <p>Don't have an account yet? <a href="register.php" class="text-blue">Register as student/staff</a></p>
            <p><small class="text-muted">Tip: Demo login: admin@school.edu | password123</small></p>
        </div>
    </div>
</div>

<?php
mysqli_close($conn);
include_once 'includes/footer.php';
?>
