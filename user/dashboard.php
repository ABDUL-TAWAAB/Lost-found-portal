<?php
// =================================================================
// User Dashboard (user/dashboard.php)
// The central page for registered users. Shows quick stats, greeting,
// and summarizes their recent lost/found posts.
// =================================================================

// 1. Include dependencies
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// 2. Protect page - redirect to login.php if not logged in
check_login();

// 3. Retrieve currently logged in user info
$user = get_logged_in_user();
$user_id = $user['id'];

// 4. Fetch User Stats
// Total items reported by this user
$user_items_count = get_count($conn, "SELECT COUNT(*) FROM items WHERE user_id = ?", [$user_id], "i");
// Total pending claims on found items posted by this user
$claims_count = get_count($conn, "SELECT COUNT(*) FROM claims c JOIN items i ON c.item_id = i.id WHERE i.user_id = ? AND c.owner_response = 'pending'", [$user_id], "i");
// Unread messages count for this user
$unread_msg_count = get_count($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0", [$user_id], "i");

// 5. Fetch user's 3 most recent posts to show in dashboard table
$my_recent_query = "SELECT i.*, c.category_name 
                    FROM items i 
                    JOIN categories c ON i.category_id = c.id 
                    WHERE i.user_id = ? 
                    ORDER BY i.created_at DESC 
                    LIMIT 3";
$stmt = mysqli_prepare($conn, $my_recent_query);
$my_recent_items = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $my_recent_items[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// 6. Fetch profile picture from database to show in sidebar
$pic_query = "SELECT profile_picture FROM users WHERE id = ?";
$pic_stmt = mysqli_prepare($conn, $pic_query);
$profile_pic = "default_avatar.png";
if ($pic_stmt) {
    mysqli_stmt_bind_param($pic_stmt, "i", $user_id);
    mysqli_stmt_execute($pic_stmt);
    $pic_res = mysqli_stmt_get_result($pic_stmt);
    if ($pic_row = mysqli_fetch_assoc($pic_res)) {
        $profile_pic = $pic_row['profile_picture'];
    }
    mysqli_stmt_close($pic_stmt);
}

$base_path = "../"; // We are in user/ folder, so base is up one directory
include_once '../includes/header.php';
?>

<div class="dashboard-layout">
    
    <!-- Sidebar Panel -->
    <aside class="dash-sidebar">
        <div class="sidebar-profile">
            <!-- Profile Avatar with fallback check -->
            <img src="<?php echo $base_path; ?>assets/uploads/<?php echo $profile_pic; ?>" alt="Avatar" class="sidebar-avatar" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
            <p><?php echo htmlspecialchars($user['id_card']); ?></p>
            <span class="badge-role mt-1"><?php echo ucfirst($user['role']); ?></span>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="sidebar-link active"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile Settings</a>
            <a href="my-items.php" class="sidebar-link"><i class="fas fa-list-ul"></i> My Reports</a>
            <a href="add-item.php" class="sidebar-link"><i class="fas fa-plus-circle"></i> Post New Item</a>
            <a href="claims.php" class="sidebar-link"><i class="fas fa-gavel"></i> Manage Claims <?php echo $claims_count > 0 ? "<span class='text-green'>($claims_count)</span>" : ""; ?></a>
            <a href="messages.php" class="sidebar-link"><i class="fas fa-envelope"></i> Message Box <?php echo $unread_msg_count > 0 ? "<span class='text-blue'>($unread_msg_count)</span>" : ""; ?></a>
        </nav>
    </aside>

    <!-- Main Dashboard Workspace -->
    <main class="dash-main">
        
        <!-- Welcome Greeting Panel -->
        <div class="item-desc-card bg-light-blue" style="border-color: #bfdbfe; margin-bottom: 0;">
            <h2 class="text-blue">Hello, <?php echo htmlspecialchars($user['name']); ?>!</h2>
            <p>Welcome to your School Lost & Found dashboard. From here, you can manage your items, track incoming claims, and coordinate exchanges with other students.</p>
        </div>

        <!-- Dashboard Stat Cards Row -->
        <section class="stats-container" style="margin-bottom: 0;">
            <div class="stat-card">
                <div class="stat-icon bg-light-blue text-blue"><i class="fas fa-bullhorn"></i></div>
                <div class="stat-info">
                    <h3><?php echo $user_items_count; ?></h3>
                    <p>Total Items Reported</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-light-green text-green"><i class="fas fa-file-invoice"></i></div>
                <div class="stat-info">
                    <h3><?php echo $claims_count; ?></h3>
                    <p>Pending Claims To Review</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-light-gold text-gold"><i class="fas fa-bell"></i></div>
                <div class="stat-info">
                    <h3><?php echo $unread_msg_count; ?></h3>
                    <p>Unread Messages</p>
                </div>
            </div>
        </section>

        <!-- Recent Activity Section -->
        <div class="spec-table-card">
            <div class="dash-header" style="border: none; padding-bottom: 0; margin-bottom: 1rem;">
                <h3><i class="far fa-clock text-blue"></i> Your Recent Reports</h3>
                <a href="my-items.php" class="btn btn-outline"><i class="fas fa-list"></i> View All My Items</a>
            </div>

            <?php if (count($my_recent_items) > 0): ?>
                <div class="table-responsive">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Date Reported</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_recent_items as $item): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $base_path; ?>assets/uploads/<?php echo $item['image']; ?>" alt="Pic" class="table-thumbnail" onerror="this.src='../assets/uploads/default_item.jpg';">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($item['title']); ?></strong></td>
                                    <td><?php echo get_type_badge($item['item_type']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo format_date($item['date_lost_found']); ?></td>
                                    <td><?php echo get_status_badge($item['status']); ?></td>
                                    <td>
                                        <div class="action-buttons-group">
                                            <a href="<?php echo $base_path; ?>item.php?id=<?php echo $item['id']; ?>" class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" title="View public page"><i class="far fa-eye"></i></a>
                                            <a href="edit-item.php?id=<?php echo $item['id']; ?>" class="btn btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" title="Edit report"><i class="far fa-edit"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center" style="padding: 2rem; color: var(--medium-gray);">
                    <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>You have not reported any lost or found items yet.</p>
                    <a href="add-item.php" class="btn btn-primary mt-1"><i class="fas fa-plus"></i> Post Your First Report</a>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php
mysqli_close($conn);
include_once '../includes/footer.php';
?>
