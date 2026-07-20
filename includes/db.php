<?php
// =================================================================
// Database Connection File (db.php)
// This file establishes a connection to the MySQL database server.
// It is included in other PHP scripts that need to query the database.
// =================================================================

// 1. Connection configuration variables
// XAMPP default settings:
$db_host = "localhost";      // Database server host (localhost for local machines)
$db_user = "root";           // Default database username in XAMPP
$db_pass = "";               // Default database password is empty in XAMPP
$db_name = "school_lost_found_portal";     // The name of our database

// 2. Establish a connection using the MySQLi procedural extension
// mysqli_connect() opens a new connection to the MySQL server.
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// 3. Error checking
// If the connection failed, we terminate the script execution and display the error.
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// 4. Set character encoding to UTF-8
// This ensures that special characters (emojis, accented characters) are stored and retrieved correctly.
mysqli_set_charset($conn, "utf8mb4");

// Note: For security in production, database credentials should be stored in environment variables,
// but for an academic project, setting them directly in this file is standard and easy to explain.
?>
