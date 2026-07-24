<?php
// =================================================================
// User Registration Page (register.php)
// Allows students and staff to create a portal account. Includes form
// validation, secure image file upload, and password hashing.
// =================================================================

// 1. Include dependencies
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// If already logged in, redirect them directly to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: user/dashboard.php");
    exit();
}

$error_msg = "";
$success_msg = "";

// 2. Process Registration Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize user inputs to protect against XSS
    $full_name = sanitize_input($_POST['full_name']);
    $student_staff_id = sanitize_input($_POST['student_staff_id']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize_input($_POST['role']);
    
    // Validate required fields
    if (empty($full_name) || empty($student_staff_id) || empty($email) || empty($phone) || empty($password) || empty($role)) {
        $error_msg = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $error_msg = "Passwords do not match. Please re-enter.";
    } elseif (strlen($password) < 6) {
        $error_msg = "Password must be at least 6 characters long.";
    } else {
        // Handle optional profile picture upload
        $profile_picture = "default_avatar.png"; // Fallback image if none uploaded
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploaded_pic = upload_image($_FILES['profile_picture'], 'assets/uploads/', 'default_avatar.png');
            if ($uploaded_pic !== false) {
                $profile_picture = $uploaded_pic;
            } else {
                $error_msg = "Failed to upload profile picture. Please make sure the size is under 2MB and format is JPG/PNG.";
            }
        }
        
        // If there are no errors up to this point, proceed with registration
        if (empty($error_msg)) {
            // Call register helper from auth.php
            $reg_result = register_user($conn, $full_name, $student_staff_id, $email, $phone, $password, $role, $profile_picture);
            
            if ($reg_result === true) {
                $success_msg = "Account created successfully! You can now log in.";
                // Clear post variables so they don't pre-populate the form
                $_POST = array();
            } else {
                // If registration failed due to duplicate entry or other db error
                $error_msg = $reg_result;
            }
        }
    }
}

$base_path = "";
include_once 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card width-medium">
        <div class="auth-header">
            <h2>Create Your Account</h2>
            <p>Register as a student or staff member to start posting lost and found items on campus.</p>
        </div>

        <!-- Alerts -->
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?> <a href="login.php" class="alert-link text-underline">Login here</a>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <!-- Important: enctype="multipart/form-data" is REQUIRED for file uploads -->
        <form action="register.php" method="POST" enctype="multipart/form-data" class="auth-form form-grid">
            
            <div class="form-group span-2">
                <label for="full_name"><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                <input type="text" id="full_name" name="full_name" placeholder="e.g. Jane Smith" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="student_staff_id"><i class="fas fa-id-card"></i> ID Card Number <span class="required">*</span></label>
                <input type="text" id="student_staff_id" name="student_staff_id" placeholder="e.g. STU-12345 or STF-987" required value="<?php echo isset($_POST['student_staff_id']) ? htmlspecialchars($_POST['student_staff_id']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="role"><i class="fas fa-user-tag"></i> Role / Position <span class="required">*</span></label>
                <select id="role" name="role" required>
                    <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
                    <option value="staff" <?php echo (isset($_POST['role']) && $_POST['role'] === 'staff') ? 'selected' : ''; ?>>Staff Member</option>
                    <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" placeholder="name@school.edu" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="phone"><i class="fas fa-phone"></i> Phone Number <span class="required">*</span></label>
                <input type="tel" id="phone" name="phone" placeholder="e.g. +1 555-0199" required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password <span class="required">*</span></label>
                <input type="password" id="password" name="password" placeholder="At least 6 characters" required>
            </div>

            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
            </div>

            <div class="form-group span-2">
                <label for="profile_picture"><i class="fas fa-camera"></i> Profile Picture (Optional)</label>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="file-input-styled">
                <small class="text-muted">Allowed formats: JPG, PNG. Max size: 2MB.</small>
            </div>

            <div class="form-group span-2 flex-center">
                <button type="submit" class="btn btn-primary btn-large btn-full mt-1">
                    <i class="fas fa-user-plus"></i> Complete Registration
                </button>
            </div>
        </form>

        <div class="auth-footer">
            <p>Already have an account? <a href="login.php" class="text-blue">Log in here</a></p>
        </div>
    </div>
</div>

<?php
mysqli_close($conn);
include_once 'includes/footer.php';
?>
