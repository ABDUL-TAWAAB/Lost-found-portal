<?php
// =================================================================
// Logout Logic Script (logout.php)
// This file destroys the active session and logs the user out.
// Once completed, it redirects the user back to the homepage.
// =================================================================

// 1. Initialize the session.
// If session is not started, we start it so that we can destroy it.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Unset all session variables.
$_SESSION = array();

// 3. If it's desired to kill the session cookie, we destroy the cookie itself.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
// 4. Finally, destroy the session.
session_destroy();
// 5. Redirect the user back to the public homepage
header("Location: index.php?msg=loggedout");
exit();
?>
