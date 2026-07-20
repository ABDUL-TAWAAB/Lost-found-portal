<?php
// =================================================================
// User & Admin Profile Page (user/profile.php)
// Allows logged-in users and admins to view and edit their profiles,
// upload a profile picture, and change their password.
// =================================================================

// 1. Include dependencies
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// 2. Protect page - redirect to login.php if not logged in
check_login();

// 3. Retrieve currently logged in user info
$session_user = get_logged_in_user();
$user_id = $session_user['id'];

$error_msg = "";
$success_msg = "";

// 4. Fetch latest user details from database to ensure fresh data
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
$user_data = null;

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if (!$user_data) {
    // Fallback to session details if database fetch fails
    $user_data = [
        'full_name' => $session_user['name'],
        'student_staff_id' => $session_user['id_card'],
        'email' => $session_user['email'],
        'phone' => '',
        'role' => $session_user['role'],
        'profile_picture' => 'default_avatar.png'
    ];
}

// 5. Handle Profile Edit Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize user inputs
    $full_name = sanitize_input($_POST['full_name']);
    $student_staff_id = sanitize_input($_POST['student_staff_id']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate required fields
    if (empty($full_name) || empty($student_staff_id) || empty($email) || empty($phone)) {
        $error_msg = "Please fill in all required fields (Full Name, Student/Staff ID, Email, Phone Number).";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Please enter a valid email address.";
    } else {
        // Check uniqueness of Email and Student/Staff ID (excluding current user)
        $uniq_query = "SELECT id FROM users WHERE (email = ? OR student_staff_id = ?) AND id != ?";
        $uniq_stmt = mysqli_prepare($conn, $uniq_query);
        $is_unique = true;
        
        if ($uniq_stmt) {
            mysqli_stmt_bind_param($uniq_stmt, "ssi", $email, $student_staff_id, $user_id);
            mysqli_stmt_execute($uniq_stmt);
            mysqli_stmt_store_result($uniq_stmt);
            if (mysqli_stmt_num_rows($uniq_stmt) > 0) {
                $is_unique = false;
                $error_msg = "The Email Address or Student/Staff ID is already registered to another account.";
            }
            mysqli_stmt_close($uniq_stmt);
        }
        
        if ($is_unique) {
            // Check password change request
            $update_password = false;
            $hashed_password = "";
            if (!empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    $error_msg = "New passwords do not match. Please re-enter them.";
                } else if (strlen($new_password) < 6) {
                    $error_msg = "New password must be at least 6 characters long.";
                } else {
                    $update_password = true;
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                }
            }
            
            // Check image upload
            $profile_pic = $user_data['profile_picture'];
            $image_updated = false;
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $uploaded = upload_image($_FILES['profile_pic'], '../assets/uploads/', $user_data['profile_picture']);
                if ($uploaded !== false) {
                    $profile_pic = $uploaded;
                    $image_updated = true;
                } else {
                    $error_msg = "Failed to upload profile picture. Ensure the image is under 2MB and formatted in JPG/PNG/GIF.";
                }
            }
            
            // If no errors so far, execute the update
            if (empty($error_msg)) {
                if ($update_password) {
                    $update_query = "UPDATE users SET full_name = ?, student_staff_id = ?, email = ?, phone = ?, password = ?, profile_picture = ? WHERE id = ?";
                    $up_stmt = mysqli_prepare($conn, $update_query);
                    if ($up_stmt) {
                        mysqli_stmt_bind_param($up_stmt, "ssssssi", $full_name, $student_staff_id, $email, $phone, $hashed_password, $profile_pic, $user_id);
                        $success = mysqli_stmt_execute($up_stmt);
                        mysqli_stmt_close($up_stmt);
                    }
                } else {
                    $update_query = "UPDATE users SET full_name = ?, student_staff_id = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?";
                    $up_stmt = mysqli_prepare($conn, $update_query);
                    if ($up_stmt) {
                        mysqli_stmt_bind_param($up_stmt, "sssssi", $full_name, $student_staff_id, $email, $phone, $profile_pic, $user_id);
                        $success = mysqli_stmt_execute($up_stmt);
                        mysqli_stmt_close($up_stmt);
                    }
                }
                
                if (isset($success) && $success) {
                    $success_msg = "Your profile has been updated successfully!";
                    
                    // Update session variables in real-time
                    $_SESSION['user_name'] = $full_name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_id_card'] = $student_staff_id;
                    
                    // Refresh database values for displaying
                    $user_data['full_name'] = $full_name;
                    $user_data['student_staff_id'] = $student_staff_id;
                    $user_data['email'] = $email;
                    $user_data['phone'] = $phone;
                    $user_data['profile_picture'] = $profile_pic;
                } else if (empty($error_msg)) {
                    $error_msg = "Failed to update profile record in database.";
                }
            }
        }
    }
}

// Fetch counts for sidebar badges
$claims_count = get_count($conn, "SELECT COUNT(*) FROM claims c JOIN items i ON c.item_id = i.id WHERE i.user_id = ? AND c.owner_response = 'pending'", [$user_id], "i");
$unread_msg_count = get_count($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0", [$user_id], "i");

$base_path = "../";
include_once '../includes/header.php';
?>

<div class="dashboard-layout">
    
    <!-- Sidebar Panel -->
    <aside class="dash-sidebar">
        <div class="sidebar-profile">
            <!-- Profile Avatar with fallback check -->
            <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($user_data['profile_picture']); ?>" alt="Avatar" class="sidebar-avatar" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
            <h4><?php echo htmlspecialchars($user_data['full_name']); ?></h4>
            <p><?php echo htmlspecialchars($user_data['student_staff_id']); ?></p>
            <span class="badge-role mt-1"><?php echo ucfirst($user_data['role']); ?></span>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="profile.php" class="sidebar-link active"><i class="fas fa-user-circle"></i> Profile Settings</a>
            <a href="my-items.php" class="sidebar-link"><i class="fas fa-list-ul"></i> My Reports</a>
            <a href="add-item.php" class="sidebar-link"><i class="fas fa-plus-circle"></i> Post New Item</a>
            <a href="claims.php" class="sidebar-link"><i class="fas fa-gavel"></i> Manage Claims <?php echo $claims_count > 0 ? "<span class='text-green'>($claims_count)</span>" : ""; ?></a>
            <a href="messages.php" class="sidebar-link"><i class="fas fa-envelope"></i> Message Box <?php echo $unread_msg_count > 0 ? "<span class='text-blue'>($unread_msg_count)</span>" : ""; ?></a>
        </nav>
    </aside>

    <!-- Main Workspace -->
    <main class="dash-main">
        
        <div class="spec-table-card">
            <div class="dash-header" style="border-bottom: 1px solid var(--light-gray); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                <h3><i class="fas fa-user-edit text-blue"></i> Profile Settings</h3>
                <p style="color: var(--medium-gray); font-size: 0.9rem; margin-top: 0.25rem;">View and update your personal information, profile avatar, and account password.</p>
            </div>

            <!-- Alerts -->
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                    <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>

            <!-- Edit Form -->
            <form action="profile.php" method="POST" enctype="multipart/form-data" class="auth-form" style="max-width: 100%; padding: 0;">
                
                <div style="display: flex; flex-direction: column; gap: 2rem;">
                    
                    <!-- Avatar Upload and Display Row -->
                    <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; background: #f8fafc; padding: 1.25rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($user_data['profile_picture']); ?>" alt="Profile Picture" style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid var(--blue); box-shadow: var(--shadow-sm);" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
                        <div>
                            <h4 style="margin: 0 0 0.25rem 0; font-size: 1.1rem; color: var(--dark-gray);">Profile Picture</h4>
                            <p style="margin: 0 0 0.75rem 0; font-size: 0.85rem; color: var(--medium-gray);">Upload a new square avatar image (JPG, PNG or GIF, max 2MB).</p>
                            <input type="file" name="profile_pic" id="profile_pic" accept="image/*" style="font-size: 0.85rem;">
                        </div>
                    </div>

                    <!-- Personal Details -->
                    <div>
                        <h4 style="margin-bottom: 1rem; color: var(--blue); border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;"><i class="far fa-address-card"></i> Personal Information</h4>
                        
                        <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="full_name"><i class="fas fa-user"></i> Full Name <span class="text-danger">*</span></label>
                                <input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($user_data['full_name']); ?>" placeholder="Enter your full name">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="student_staff_id"><i class="fas fa-id-card"></i> Student/Staff ID <span class="text-danger">*</span></label>
                                <input type="text" id="student_staff_id" name="student_staff_id" required value="<?php echo htmlspecialchars($user_data['student_staff_id']); ?>" placeholder="e.g. STU-12345 or STF-98765">
                            </div>
                        </div>

                        <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="email"><i class="fas fa-envelope"></i> Email Address <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($user_data['email']); ?>" placeholder="e.g. yourname@school.edu">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="phone"><i class="fas fa-phone"></i> Phone Number <span class="text-danger">*</span></label>
                                <input type="text" id="phone" name="phone" required value="<?php echo htmlspecialchars($user_data['phone']); ?>" placeholder="e.g. 0771234567">
                            </div>
                        </div>
                    </div>

                    <!-- Change Password (Optional) -->
                    <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 1.25rem; border-radius: 8px;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #b45309;"><i class="fas fa-key"></i> Security Settings</h4>
                        <p style="margin: 0 0 1rem 0; font-size: 0.85rem; color: #78350f;">Leave the password fields blank if you do not wish to change your password.</p>
                        
                        <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="new_password" style="color: #78350f;"><i class="fas fa-lock"></i> New Password</label>
                                <input type="password" id="new_password" name="new_password" placeholder="Min. 6 characters" style="border-color: #fcd34d;">
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="confirm_password" style="color: #78350f;"><i class="fas fa-lock"></i> Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat your new password" style="border-color: #fcd34d;">
                            </div>
                        </div>
                    </div>

                    <!-- Role (Read Only) -->
                    <div style="background: #f1f5f9; padding: 1rem; border-radius: 8px; border: 1px solid #cbd5e1; display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-weight: 500; color: #475569;"><i class="fas fa-shield-alt"></i> Account Role Level:</span>
                        <span class="badge-role" style="font-size: 0.9rem; padding: 0.35rem 0.85rem; background: var(--blue); color: #fff; border-radius: 4px; font-weight: 600;"><?php echo ucfirst($user_data['role']); ?></span>
                    </div>

                    <!-- Submit Button -->
                    <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="dashboard.php" class="btn btn-outline" style="padding: 0.75rem 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>

                </div>

            </form>
        </div>

    </main>
</div>

<?php
mysqli_close($conn);
include_once '../includes/footer.php';
?>
