<?php
// =================================================================
// Session Management File (session.php)
// This file manages user sessions, logs in users, checks authentication,
// and protects pages from unauthorized access.
// =================================================================

// 1. Start the session if it hasn't been started already
// session_start() must be called at the very top of any PHP page that uses $_SESSION variables.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is logged in.
 * If not logged in, redirects them to the login page.
 * This is used to protect user dashboard pages.
 */
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        // Redirect to the login page at the root directory
        header("Location: ../login.php");
        exit(); // Stop further script execution
    }
}

/**
 * Checks if the logged-in user is an Administrator.
 * If not an admin, redirects them to the user dashboard or login page.
 * This is used to protect administrative directories.
 */
function check_admin() {
    // First, make sure they are logged in
    check_login();
    
    // Check if the role stored in session is 'admin'
    if ($_SESSION['user_role'] !== 'admin') {
        // Not authorized! Redirect to standard user dashboard with an error message
        header("Location: ../user/dashboard.php?error=unauthorized");
        exit();
    }
}

/**
 * Helper function to safely get currently logged-in user info.
 */
function get_logged_in_user() {
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
            'id_card' => $_SESSION['user_id_card']
        ];
    }
    return null;
}
?>
