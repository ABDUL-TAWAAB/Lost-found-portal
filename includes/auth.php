<?php
// =================================================================
// Authentication Logic File (auth.php)
// This file contains functions to authenticate (login) and register users,
// utilizing secure procedural mysqli and PHP password hashing.
// =================================================================

require_once 'db.php';
require_once 'session.php';

/**
 * Registers a new user.
 * Returns true if successful, or an error message string if failed.
 */
function register_user($conn, $full_name, $student_staff_id, $email, $phone, $password, $role, $profile_picture) {
    // 1. Validate that email and student_staff_id are unique
    $check_query = "SELECT id FROM users WHERE email = ? OR student_staff_id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    
    if (!$stmt) {
        return "Database prepared statement error: " . mysqli_error($conn);
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $email, $student_staff_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    
    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        return "Registration failed. Either Email or ID Card Number is already registered.";
    }
    mysqli_stmt_close($stmt);
    
    // 2. Hash the password securely using password_hash() with default bcrypt algorithm
    // This makes sure passwords are not stored in plaintext inside the database!
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // 3. Insert user details into the database
    $insert_query = "INSERT INTO users (full_name, student_staff_id, email, phone, password, role, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert_query);
    
    if (!$stmt) {
        return "Database execution error: " . mysqli_error($conn);
    }
    
    mysqli_stmt_bind_param($stmt, "sssssss", $full_name, $student_staff_id, $email, $phone, $hashed_password, $role, $profile_picture);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return true; // Successfully registered!
    } else {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return "Failed to insert user record: " . $error;
    }
}

/**
 * Authenticates a user trying to log in.
 * If successful, starts a session and returns true.
 * If failed, returns a string error message.
 */
function login_user($conn, $email, $password) {
    // 1. Fetch user by email
    $query = "SELECT id, full_name, student_staff_id, email, password, role FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        return "Database prepared statement error: " . mysqli_error($conn);
    }
    
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Check if the user exists
    if ($user = mysqli_fetch_assoc($result)) {
        // 2. Verify password using password_verify()
        // This function checks if the plaintext password matches the hashed password in the DB.
        if (password_verify($password, $user['password'])) {
            // Login successful!
            // Start session and store user info in $_SESSION variables.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_id_card'] = $user['student_staff_id'];
            
            mysqli_stmt_close($stmt);
            return true;
        }
    }
    
    mysqli_stmt_close($stmt);
    return "Invalid email address or password.";
}
?>
