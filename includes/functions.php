<?php
// =================================================================
// Helper Functions File (functions.php)
// This file contains reusable PHP functions for input sanitization,
// status badges formatting, statistics retrieval, and image upload.
// =================================================================

/**
 * Sanitizes user input to prevent Cross-Site Scripting (XSS) attacks.
 * htmlspecialchars() converts HTML tags into plain text representations.
 * Example: "<script>" becomes "&lt;script&gt;"
 */
function sanitize_input($data) {
    $data = trim($data);             // Remove leading/trailing whitespaces
    $data = stripslashes($data);     // Remove backslashes
    $data = htmlspecialchars($data); // Encode HTML tags
    return $data;
}

/**
 * Safe database escaping to prevent SQL Injection.
 * Always use prepared statements, but this is a helper for extra safety.
 */
function escape_string($conn, $string) {
    return mysqli_real_escape_string($conn, $string);
}

/**
 * Returns HTML for a beautifully styled badge representing Item Type (Lost or Found)
 */
function get_type_badge($item_type) {
    if (strtolower($item_type) === 'lost') {
        return '<span class="badge badge-lost"><i class="fas fa-search-plus"></i> Lost</span>';
    } else {
        return '<span class="badge badge-found"><i class="fas fa-hand-holding-heart"></i> Found</span>';
    }
}

/**
 * Returns HTML for a styled badge representing the item status (Open, claimed, recovered, archived)
 */
function get_status_badge($status) {
    $status = strtolower($status);
    switch ($status) {
        case 'Open':
            return '<span class="status-badge status-active"><i class="fas fa-bullhorn"></i> Open</span>';
        case 'claimed':
            return '<span class="status-badge status-claimed"><i class="fas fa-check-circle"></i> Claimed</span>';
        case 'recovered':
            return '<span class="status-badge status-recovered"><i class="fas fa-handshake"></i> Recovered</span>';
        case 'archived':
            return '<span class="status-badge status-archived"><i class="fas fa-archive"></i> Archived</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}

/**
 * Formats MySQL timestamp or date into a user-friendly, human-readable format.
 * Example: "2026-07-18" becomes "Jul 18, 2026"
 */
function format_date($date_string) {
    return date("M d, Y", strtotime($date_string));
}

/**
 * Securely handles item/profile image uploads.
 * Renames the file to prevent conflicts and verifies standard mime types.
 * Returns the new filename if successful, or false on error.
 */
function upload_image($file_array, $target_dir, $default_image = 'default.png') {
    // If no file was uploaded, or there is an upload error
    if (!isset($file_array) || $file_array['error'] !== UPLOAD_ERR_OK) {
        return $default_image;
    }

    $file_name = $file_array['name'];
    $file_tmp = $file_array['tmp_name'];
    $file_size = $file_array['size'];
    
    // 1. Check file extension
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_extensions)) {
        return false; // Extension not allowed
    }
    
    // 2. Check file size (limit to 2MB)
    if ($file_size > 2 * 1024 * 1024) {
        return false; // File too large
    }
    
    // 3. Generate a unique file name to avoid overwriting existing files
    // Example: "img_607e15a9c3d42.png"
    $new_file_name = "img_" . uniqid() . "." . $file_ext;
    $target_file = $target_dir . $new_file_name;
    
    // 4. Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // 5. Move the temporary uploaded file to the target directory
    if (move_uploaded_file($file_tmp, $target_file)) {
        return $new_file_name;
    }
    
    return false;
}

/**
 * Utility function to query count from database tables.
 * Used for administrative reports and dashboards.
 */
function get_count($conn, $query, $params = [], $types = '') {
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_row($result);
        mysqli_stmt_close($stmt);
        return $row ? (int)$row[0] : 0;
    }
    return 0;
}
?>
