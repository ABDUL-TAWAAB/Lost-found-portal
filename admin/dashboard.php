<?php
// =================================================================
// Admin Dashboard (admin/dashboard.php)
// The central control hub for administrators.
// Provides system-wide statistics, item moderation, claim verification,
// user management shortcuts, and real-time logs.
// =================================================================

// 1. Include dependencies
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// 2. Protect page - enforce admin privilege
check_admin();

// 3. Retrieve logged-in administrator info
$admin = get_logged_in_user();
$admin_id = (int)$admin['id'];

$success_msg = "";
$error_msg = "";

// 4. Handle quick admin actions on items (e.g. status change or delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'update_item_status') {
        $item_id = (int)$_POST['item_id'];
        $new_status = sanitize_input($_POST['status']);
        
        $update_query = "UPDATE items SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $new_status, $item_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Item status successfully updated to '" . ucfirst($new_status) . "'.";
            } else {
                $error_msg = "Failed to update item status.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'delete_item') {
        $item_id = (int)$_POST['item_id'];
        
        $del_query = "DELETE FROM items WHERE id = ?";
        $stmt = mysqli_prepare($conn, $del_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $item_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Item report successfully deleted by administrator.";
            } else {
                $error_msg = "Failed to delete item report.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// 5. Fetch High-Level System Metrics
$total_users = get_count($conn, "SELECT COUNT(*) FROM users");
$total_items = get_count($conn, "SELECT COUNT(*) FROM items");
$total_lost = get_count($conn, "SELECT COUNT(*) FROM items WHERE item_type = 'lost'");
$total_found = get_count($conn, "SELECT COUNT(*) FROM items WHERE item_type = 'found'");
$total_pending_claims = get_count($conn, "SELECT COUNT(*) FROM claims WHERE owner_response = 'pending'");
$total_resolved = get_count($conn, "SELECT COUNT(*) FROM items WHERE status = 'claimed' OR status = 'recovered'");

// 6. Fetch Recent Reports Log (5 most recent items)
$recent_items_query = "SELECT i.*, c.category_name, u.full_name as poster_name, u.email as poster_email 
                        FROM items i 
                        JOIN categories c ON i.category_id = c.id 
                        JOIN users u ON i.user_id = u.id 
                        ORDER BY i.created_at DESC LIMIT 5";
$recent_items = [];
$res = mysqli_query($conn, $recent_items_query);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $recent_items[] = $row;
    }
}

// 7. Fetch Recent Pending Claims (3 most recent pending claims)
$recent_claims_query = "SELECT c.*, i.title as item_title, i.image as item_pic, i.item_type,
                               u.full_name as claimant_name, u.email as claimant_email, o.full_name as owner_name
                        FROM claims c
                        JOIN items i ON c.item_id = i.id
                        JOIN users u ON c.claimant_id = u.id
                        JOIN users o ON i.user_id = o.id
                        WHERE c.owner_response = 'pending'
                        ORDER BY c.created_at DESC LIMIT 3";
$recent_claims = [];
$c_res = mysqli_query($conn, $recent_claims_query);
if ($c_res) {
    while ($c_row = mysqli_fetch_assoc($c_res)) {
        $recent_claims[] = $c_row;
    }
}

// Fetch Admin Profile Picture
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
            <a href="dashboard.php" class="sidebar-link active"><i class="fas fa-tachometer-alt"></i> Overview Dashboard</a>
            <a href="items.php" class="sidebar-link"><i class="fas fa-boxes"></i> All Items / Reports</a>
            <a href="claims.php" class="sidebar-link"><i class="fas fa-file-signature"></i> Claims Management <?php echo $total_pending_claims > 0 ? "<span class='text-gold'>($total_pending_claims)</span>" : ""; ?></a>
            <a href="users.php" class="sidebar-link"><i class="fas fa-users-cog"></i> User Accounts</a>
            <a href="../user/profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile Settings</a>
            <a href="../user/add-item.php" class="sidebar-link"><i class="fas fa-plus-circle"></i> Post Admin Notice</a>
        </nav>
    </aside>

    <!-- Main Workspace -->
    <main class="dash-main">
        
        <!-- Header Welcome Banner -->
        <div class="item-desc-card bg-light-blue" style="border-color: #bfdbfe; margin-bottom: 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 class="text-blue" style="margin-bottom: 0.25rem;"><i class="fas fa-user-shield"></i> System Administration Control Panel</h2>
                    <p style="margin: 0;">Overview of lost & found activities, pending verification claims, and portal users.</p>
                </div>
                <div>
                    <a href="items.php" class="btn btn-primary"><i class="fas fa-tasks"></i> Manage Reports</a>
                    <a href="users.php" class="btn btn-outline" style="background: #fff; margin-left: 0.5rem;"><i class="fas fa-users"></i> Manage Users</a>
                </div>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success mt-1"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger mt-1"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Stat Cards Grid -->
        <section class="stats-container" style="margin-bottom: 0;">
            <div class="stat-card">
                <div class="stat-icon bg-light-blue text-blue"><i class="fas fa-boxes"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_items; ?></h3>
                    <p>Total Items Reported</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-light-gold text-gold"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_pending_claims; ?></h3>
                    <p>Pending Verification Claims</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-light-green text-green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_resolved; ?></h3>
                    <p>Resolved / Claimed Items</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #f3e8ff; color: #7e22ce;"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_users; ?></h3>
                    <p>Registered Accounts</p>
                </div>
            </div>
        </section>

        <!-- Two Column Layout: Recent Claims & Admin Shortcuts -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
            
            <!-- Column 1: Pending Claims Action Box -->
            <div class="spec-table-card" style="margin: 0;">
                <div class="dash-header" style="border: none; padding-bottom: 0; margin-bottom: 1rem;">
                    <h3><i class="fas fa-gavel text-gold"></i> Pending Claims Requiring Review</h3>
                    <a href="claims.php" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.3rem 0.6rem;">View All</a>
                </div>

                <?php if (count($recent_claims) > 0): ?>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach ($recent_claims as $claim): ?>
                            <div style="padding: 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
                                    <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($claim['item_pic']); ?>" alt="Item" style="width: 44px; height: 44px; border-radius: 6px; object-fit: cover;" onerror="this.src='./assets/uploads/default_item.jpg';">
                                    <div style="min-width: 0;">
                                        <h5 style="margin: 0; font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($claim['item_title']); ?></h5>
                                        <p style="margin: 0; font-size: 0.78rem; color: var(--medium-gray);">Claimant: <strong><?php echo htmlspecialchars($claim['claimant_name']); ?></strong></p>
                                    </div>
                                </div>
                                <a href="claims.php?review_id=<?php echo $claim['id']; ?>" class="btn btn-primary" style="padding: 0.35rem 0.7rem; font-size: 0.8rem; white-space: nowrap;"><i class="fas fa-edit"></i> Review</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center" style="padding: 1.5rem; color: var(--medium-gray);">
                        <i class="fas fa-check-double text-green" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.6;"></i>
                        <p style="font-size: 0.9rem; margin: 0;">All claims have been reviewed!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Column 2: System Health & Breakdown -->
            <div class="spec-table-card" style="margin: 0;">
                <div class="dash-header" style="border: none; padding-bottom: 0; margin-bottom: 1rem;">
                    <h3><i class="fas fa-chart-pie text-blue"></i> Report Breakdown & Actions</h3>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.8rem; background: #eff6ff; border-radius: 6px;">
                        <span style="font-weight: 500; font-size: 0.9rem;"><i class="fas fa-search-plus text-red"></i> Lost Item Reports</span>
                        <strong class="text-red" style="font-size: 1.1rem;"><?php echo $total_lost; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.8rem; background: #f0fdf4; border-radius: 6px;">
                        <span style="font-weight: 500; font-size: 0.9rem;"><i class="fas fa-hand-holding-heart text-green"></i> Found Item Reports</span>
                        <strong class="text-green" style="font-size: 1.1rem;"><?php echo $total_found; ?></strong>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 0.5rem;">
                        <a href="items.php?type=lost" class="btn btn-outline" style="text-align: center; font-size: 0.82rem; padding: 0.5rem;"><i class="fas fa-list"></i> Filter Lost Items</a>
                        <a href="items.php?type=found" class="btn btn-outline" style="text-align: center; font-size: 0.82rem; padding: 0.5rem;"><i class="fas fa-list"></i> Filter Found Items</a>
                    </div>
                </div>
            </div>

        </div>

        <!-- System Items Moderation Log Table -->
        <div class="spec-table-card">
            <div class="dash-header" style="border: none; padding-bottom: 0; margin-bottom: 1rem;">
                <h3><i class="fas fa-list-alt text-blue"></i> Recent Portal Reports (Admin Moderation)</h3>
                <a href="items.php" class="btn btn-primary"><i class="fas fa-boxes"></i> View All Items</a>
            </div>

            <?php if (count($recent_items) > 0): ?>
                <div class="table-responsive">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Title & Category</th>
                                <th>Type</th>
                                <th>Reported By</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Change Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_items as $item): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($item['image']); ?>" alt="Thumbnail" class="table-thumbnail" onerror="this.src='../assets/uploads/default_item.jpg';">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['category_name']); ?></small>
                                    </td>
                                    <td><?php echo get_type_badge($item['item_type']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['poster_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['poster_email']); ?></small>
                                    </td>
                                    <td><?php echo format_date($item['date_lost_found']); ?></td>
                                    <td><?php echo get_status_badge($item['status']); ?></td>
                                    <td>
                                        <form action="dashboard.php" method="POST" style="display: flex; gap: 0.3rem;">
                                            <input type="hidden" name="action_type" value="update_item_status">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" style="padding: 0.25rem 0.4rem; font-size: 0.8rem; border-radius: 6px; border: 1px solid var(--border-gray);">
                                                <option value="Open" <?php echo $item['status'] === 'Open' ? 'selected' : ''; ?>>Open</option>
                                                <option value="Claimed" <?php echo $item['status'] === 'Claimed' ? 'selected' : ''; ?>>Claimed</option>
                                                <option value="Returned" <?php echo $item['status'] === 'Returned' ? 'selected' : ''; ?>>Returned</option>
                                                <option value="Closed" <?php echo $item['status'] === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="action-buttons-group" style="display: flex; flex-direction: column; gap: 0.3rem; align-items: flex-start">
                                            <a href="<?php echo $base_path; ?>item.php?id=<?php echo $item['id']; ?>" class="btn btn-outline" style="padding: 0.3rem 0.5rem; font-size: 0.8rem;" title="View Public Page"><i class="far fa-eye"></i></a>
                                            <a href="../user/edit-item.php?id=<?php echo $item['id']; ?>" class="btn btn-primary" style="padding: 0.3rem 0.5rem; font-size: 0.8rem;" title="Edit Report"><i class="far fa-edit"></i></a>
                                            <form action="dashboard.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this report as administrator?');" style="display: inline;">
                                                <input type="hidden" name="action_type" value="delete_item">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-danger" style="padding: 0.3rem 0.5rem; font-size: 0.8rem;" title="Delete Report"><i class="far fa-trash-alt"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center" style="padding: 2rem; color: var(--medium-gray);">
                    <p>No reports currently logged in the portal.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php
mysqli_close($conn);
include_once '../includes/footer.php';
?>
