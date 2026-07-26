<?php
// =================================================================
// Header Navigation File (header.php)
// This file renders the head tag and top navigation bar. It dynamically
// adjusts based on whether a user is logged in and what role they have.
// Uses $base_path to resolve relative links from any subdirectory!
// =================================================================

// Default base path if not explicitly set in the page
if (!isset($base_path)) {
    $base_path = "";
}

// Start session if not started already
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Lost & Found Portal</title>
        <!-- Basic Icons -->
        <link href="https://cdn.boxicons.com/3.0.8/fonts/basic/boxicons.min.css" rel="stylesheet">
        <!-- Filled Icons -->
        <link href="https://cdn.boxicons.com/3.0.8/fonts/filled/boxicons-filled.min.css" rel="stylesheet">
        <!-- Brand Icons -->
        <link href="https://cdn.boxicons.com/3.0.8/fonts/brands/boxicons-brands.min.css" rel="stylesheet">

        <!-- Google Fonts: Poppins -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <!-- Font Awesome Icons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <!-- Custom Style CSS -->
        <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
</head>
<body>

    <!-- Header / Navigation Bar -->
    <header class="main-navbar">
        <div class="nav-container">
            <!-- Brand Logo -->
            <a href="<?php echo $base_path; ?>index.php" class="nav-logo">
                <i class="fas fa-search-location text-green"></i> 
                <span>Lost<span class="text-blue">&</span>Found</span>
            </a>

            <!-- Mobile Toggle Menu (Hamburger) -->
            <label for="nav-toggle" class="nav-toggle-label">
                <i class="bx bx-bell"></i>
                <!-- notification bell count here -->
                <i id="hamburger"  class="bx bx-menu-right"></i>
            </label>

            <!-- Navigation Links -->
            <nav class="nav-menu">
                <a href="<?php echo $base_path; ?>index.php" class="nav-link"></i> Home</a>
                <a href="<?php echo $base_path; ?>search.php" class="nav-link"></i> Browse items</a>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- If user is logged in, show dashboard and messages options -->
                    
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <a href="<?php echo $base_path; ?>admin/dashboard.php" class="nav-link nav-highlight"><i class="fas fa-tachometer-alt"></i> Admin Panel</a>
                    <?php else: ?>
                        <a href="<?php echo $base_path; ?>user/dashboard.php" class="nav-link nav-highlight"><i class="fas fa-th-large"></i> Dashboard</a>
                        <a href="<?php echo $base_path; ?>user/messages.php" class="nav-link"><i class="fas fa-envelope"></i> Messages</a>
                    <?php endif; ?>
                    
                    <a href="<?php echo $base_path; ?>user/profile.php" class="nav-link"><i class="fas fa-user-circle"></i> Profile</a>
                    
                    <!-- Logout button -->
                    <a href="<?php echo $base_path; ?>logout.php" class="nav-btn" onclick=";"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php else: ?>
                    <!-- If not logged in, show Login and Register links -->
                    <a href="<?php echo $base_path; ?>login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="<?php echo $base_path; ?>register.php" class="nav-btn "><i class="fas fa-user-plus"></i> Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Page Content Container starts here (will be closed in individual files) -->
    <main class="page-container">
