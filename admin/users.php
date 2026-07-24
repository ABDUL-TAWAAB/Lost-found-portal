<?php
// =================================================================
// Admin User Accounts Management (admin/users.php)
// Allows administrators to view registered accounts, modify user roles
// (e.g., promote student to admin), view activity, or delete accounts.
// =================================================================

// 1. Include dependencies
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// 2. Protect page - enforce admin privilege
check_admin();

// 3. Retrieve logged-in admin
$admin = get_logged_in_user();
$admin_id = (int)$admin['id'];

$success_msg = "";
$error_msg = "";

// 4. Handle POST actions (Update User Role or Delete Account)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'update_role') {
        $target_user_id = (int)$_POST['user_id'];
        $new_role = sanitize_input($_POST['role']);
        
        $role_query = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $role_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $new_role, $target_user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "User role successfully updated to '" . ucfirst($new_role) . "'.";
            } else {
                $error_msg = "Failed to update user role.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'delete_user') {
        $target_user_id = (int)$_POST['user_id'];
        
        if ($target_user_id === $admin_id) {
            $error_msg = "You cannot delete your own administrator account while logged in!";
        } else {
            $del_query = "DELETE FROM users WHERE id = ?";
            $stmt = mysqli_prepare($conn, $del_query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $target_user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success_msg = "User account successfully removed.";
                } else {
                    $error_msg = "Failed to delete user account.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// 5. Search user accounts
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT u.*, 
               (SELECT COUNT(*) FROM items WHERE user_id = u.id) as item_count,
               (SELECT COUNT(*) FROM claims WHERE claimant_id = u.id) as claim_count
        FROM users u
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($search_query)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.student_staff_id LIKE ? OR u.phone LIKE ?)";
    $term = "%" . $search_query . "%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= "ssss";
}

$sql .= " ORDER BY u.id DESC";

$stmt = mysqli_prepare($conn, $sql);
$user_list = [];
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $user_list[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Profile picture for admin sidebar
$profile_pic = "default_avatar.png";
$pic_query = "SELECT profile_picture FROM users WHERE id = ?";
$pic_stmt = mysqli_prepare($conn, $pic_query);
if ($pic_stmt) {
    mysqli_stmt_bind_param($pic_stmt, "i", $admin_id);
    mysqli_stmt_execute($pic_stmt);
    $pic_res = mysqli_stmt_get_result($pic_stmt);
    if ($p_row = mysqli_fetch_assoc($pic_res)) {
        $profile_pic = $p_row['profile_picture'];
    }
    mysqli_stmt_close($pic_stmt);
}

$total_pending_claims = get_count($conn, "SELECT COUNT(*) FROM claims WHERE owner_response = 'pending'");

$base_path = "../";
include_once '../includes/header.php';
?>

<div class="dashboard-layout">
    
    <!-- Admin Sidebar Panel -->
    <aside class="dash-sidebar">
        <div class="sidebar-profile">
            <img src="<?php echo $base_path; ?>assets/uploads/<?php echo $profile_pic; ?>" alt="Admin Avatar" class="sidebar-avatar" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
            <h4><?php echo htmlspecialchars($admin['name']); ?></h4>
            <p><?php echo htmlspecialchars($admin['email']); ?></p>
            <span class="badge-role mt-1" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a;"><i class="fas fa-user-shield"></i> Administrator</span>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> Overview Dashboard</a>
            <a href="items.php" class="sidebar-link"><i class="fas fa-boxes"></i> All Items / Reports</a>
            <a href="claims.php" class="sidebar-link"><i class="fas fa-file-signature"></i> Claims Management <?php echo $total_pending_claims > 0 ? "<span class='text-gold'>($total_pending_claims)</span>" : ""; ?></a>
            <a href="users.php" class="sidebar-link active"><i class="fas fa-users-cog"></i> User Accounts</a>
            <a href="../user/profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile Settings</a>
            <a href="../user/add-item.php" class="sidebar-link"><i class="fas fa-plus-circle"></i> Post Admin Notice</a>
        </nav>
    </aside>

    <!-- Main Workspace -->
    <main class="dash-main">
        
        <div class="dash-header" style="margin-bottom: 1rem;">
            <div>
                <h2><i class="fas fa-users-cog text-blue"></i> Registered User Directory</h2>
                <p>Manage student, staff, and administrator user accounts across the portal.</p>
            </div>
            <a href="../register.php" class="btn btn-outline" style="background: #fff;"><i class="fas fa-user-plus"></i> Register New User</a>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success mt-1"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger mt-1"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="spec-table-card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
            <form action="users.php" method="GET" style="display: flex; gap: 1rem; align-items: center;">
                <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by user name, email, student/staff ID..." style="flex: 1; padding: 0.6rem 1rem; border: 1px solid var(--border-gray); border-radius: 8px; font-size: 0.92rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.25rem;"><i class="fas fa-search"></i> Search Users</button>
                <?php if (!empty($search_query)): ?>
                    <a href="users.php" class="btn btn-outline" style="padding: 0.6rem 1rem;" title="Clear Search"><i class="fas fa-undo"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <!-- User Accounts Table -->
        <div class="spec-table-card">
            <?php if (count($user_list) > 0): ?>
                <div class="table-responsive">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Avatar</th>
                                <th>User Name</th>
                                <th>user ID</th>
                                <th>Contact Details</th>
                                <th>Current Role</th>
                                <th>Activity</th>
                                <th>Change Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_list as $u): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($u['profile_picture'] ?? 'default_avatar.png'); ?>" alt="Avatar" class="table-thumbnail" style="border-radius: 50%; width: 42px; height: 42px;" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($u['full_name']); ?></strong>
                                        <?php if ((int)$u['id'] === $admin_id): ?>
                                            <span style="font-size: 0.7rem; background: #dbeafe; color: #1e40af; padding: 0.15rem 0.4rem; border-radius: 4px; margin-left: 0.3rem;">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($u['student_staff_id']); ?></code></td>
                                    <td style="font-size: 0.85rem;">
                                        <small><i class="fas fa-envelope text-blue"></i> <?php echo htmlspecialchars($u['email']); ?></small>
                                        <br><small class="text-muted"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($u['phone']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a;"><i class="fas fa-user-shield"></i> Admin</span>
                                        <?php elseif ($u['role'] === 'staff'): ?>
                                            <span class="badge badge-green"><i class="fas fa-chalkboard-teacher"></i> Staff</span>
                                        <?php else: ?>
                                            <span class="badge badge-blue"><i class="fas fa-user-graduate"></i> Student</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><strong><?php echo $u['item_count']; ?></strong> Posts</small> | 
                                        <small><strong><?php echo $u['claim_count']; ?></strong> Claims</small>
                                    </td>
                                    <td>
                                        <form action="users.php" method="POST">
                                            <input type="hidden" name="action_type" value="update_role">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <select name="role" onchange="this.form.submit()" style="padding: 0.25rem 0.4rem; font-size: 0.8rem; border-radius: 6px; border: 1px solid var(--border-gray);" <?php echo ((int)$u['id'] === $admin_id) ? 'disabled' : ''; ?>>
                                                <option value="student" <?php echo $u['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                                <option value="staff" <?php echo $u['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                                <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ((int)$u['id'] !== $admin_id): ?>
                                            <form action="users.php" method="POST" onsubmit="return confirm('Are you sure you want to delete account for <?php echo htmlspecialchars($u['full_name']); ?>?');">
                                                <input type="hidden" name="action_type" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="btn btn-danger" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" title="Delete User Account"><i class="far fa-trash-alt"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.8rem;"><i class="fas fa-lock"></i> Protected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center" style="padding: 3rem 1rem; color: var(--medium-gray);">
                    <i class="fas fa-users-slash" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.4;"></i>
                    <h4>No user accounts found</h4>
                    <p>No user records matched your search query.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php
mysqli_close($conn);
include_once '../includes/footer.php';
?>
