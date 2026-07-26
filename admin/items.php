<?php
// =================================================================
// Admin All Items Management (admin/items.php)
// Allows administrators to view, filter, moderate, update status,
// or delete any reported lost or found item across the portal.
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

// 4. Handle POST actions (Update status or Delete item)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'update_status') {
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
                $success_msg = "Item report successfully deleted.";
            } else {
                $error_msg = "Failed to delete item report.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// 5. Retrieve filter inputs
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Fetch Categories for dropdown
$categories = [];
$cat_res = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name ASC");
if ($cat_res) {
    while ($c = mysqli_fetch_assoc($cat_res)) {
        $categories[] = $c;
    }
}

// 6. Fetch Items List with filters
$sql = "SELECT i.*, c.category_name, u.full_name as poster_name, u.email as poster_email
        FROM items i
        JOIN categories c ON i.category_id = c.id
        JOIN users u ON i.user_id = u.id
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($search_query)) {
    $sql .= " AND (i.title LIKE ? OR i.description LIKE ? OR i.location LIKE ?)";
    $term = "%" . $search_query . "%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= "sss";
}

if (!empty($filter_type)) {
    $sql .= " AND i.item_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if (!empty($filter_status)) {
    $sql .= " AND i.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_category > 0) {
    $sql .= " AND i.category_id = ?";
    $params[] = $filter_category;
    $types .= "i";
}

$sql .= " ORDER BY i.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
$all_items = [];
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $all_items[] = $row;
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

$unread_msg_count = get_count($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0", [$admin_id], "i");
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
            <a href="items.php" class="sidebar-link active"><i class="fas fa-boxes"></i> All Items / Reports</a>
            <a href="claims.php" class="sidebar-link"><i class="fas fa-file-signature"></i> Claims Management <?php echo $total_pending_claims > 0 ? "<span class='text-gold'>($total_pending_claims)</span>" : ""; ?></a>
            <a href="users.php" class="sidebar-link"><i class="fas fa-users-cog"></i> User Accounts</a>
            <a href="../user/profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile Settings</a>
            <a href="../user/add-item.php" class="sidebar-link"><i class="fas fa-plus-circle"></i> Post Admin Notice</a>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="dash-main">
        
        <div class="dash-header" style="margin-bottom: 1rem;">
            <div>
                <h2><i class="fas fa-boxes text-blue"></i> All Portal Items & Reports</h2>
                <p>Manage, moderate, update status, or delete any reported item across the school portal.</p>
            </div>
            <a href="../user/add-item.php" class="btn btn-primary"><i class="fas fa-plus"></i> Post New Report</a>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success mt-1"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger mt-1"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Search & Filter Controls -->
        <div class="spec-table-card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
            <form action="items.php" method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: end;">
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--dark-charcoal); display: block; margin-bottom: 0.3rem;">Search Keywords</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Title, location..." style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-gray); border-radius: 6px; font-size: 0.9rem;">
                </div>

                <div>
                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--dark-charcoal); display: block; margin-bottom: 0.3rem;">Item Type</label>
                    <select name="type" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-gray); border-radius: 6px; font-size: 0.9rem;">
                        <option value="">All Types</option>
                        <option value="lost" <?php echo $filter_type === 'lost' ? 'selected' : ''; ?>>Lost Items</option>
                        <option value="found" <?php echo $filter_type === 'found' ? 'selected' : ''; ?>>Found Items</option>
                    </select>
                </div>

                <div>
                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--dark-charcoal); display: block; margin-bottom: 0.3rem;">Status</label>
                    <select name="status" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-gray); border-radius: 6px; font-size: 0.9rem;">
                        <option value="">All Statuses</option>
                        <option value="Open" <?php echo $filter_status === 'Open' ? 'selected' : ''; ?>>Open</option>
                        <option value="Claimed" <?php echo $filter_status === 'Claimed' ? 'selected' : ''; ?>>Claimed</option>
                        <option value="Returned" <?php echo $filter_status === 'Returned' ? 'selected' : ''; ?>>Returned</option>
                        <option value="Closed" <?php echo $filter_status === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>

                <div>
                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--dark-charcoal); display: block; margin-bottom: 0.3rem;">Category</label>
                    <select name="category" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border-gray); border-radius: 6px; font-size: 0.9rem;">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $filter_category === (int)$cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.55rem;"><i class="fas fa-filter"></i> Filter</button>
                    <a href="items.php" class="btn btn-outline" style="padding: 0.55rem;" title="Reset Filters"><i class="fas fa-redo"></i></a>
                </div>
            </form>
        </div>

        <!-- Items Table Card -->
        <div class="spec-table-card">
            <?php if (count($all_items) > 0): ?>
                <div class="table-responsive">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Title & Category</th>
                                <th>Type</th>
                                <th>Posted By</th>
                                <th>Location</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Quick Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_items as $item): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($item['image']); ?>" alt="Pic" class="table-thumbnail" onerror="this.src='<?php echo $base_path; ?>assets/uploads/default_item.jpg';">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['category_name']); ?></small>
                                    </td>
                                    <td><?php echo get_type_badge($item['item_type']); ?></td>
                                    <td>
                                        <strong style="font-size: 0.85rem;"><?php echo htmlspecialchars($item['poster_name']); ?></strong>
                                        <br><small class="text-muted" style="font-size: 0.85rem;"><?php echo htmlspecialchars($item['poster_email']); ?></small>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($item['location']); ?></small></td>
                                    <td><small><?php echo format_date($item['date_lost_found']); ?></small></td>
                                    <td><?php echo get_status_badge($item['status']); ?></td>
                                    <td>
                                        <form action="items.php" method="POST">
                                            <input type="hidden" name="action_type" value="update_status">
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
                                        <div class="action-buttons-group" style="display:flex; flex-direction: column; justify-content: baseline;
                                        align-items: flex-start; gap: 0.3rem;">
                                            <a href="<?php echo $base_path; ?>item.php?id=<?php echo $item['id']; ?>" class="btn btn-outline" style="padding: 0.3rem 0.5rem; font-size: 0.8rem;" title="View Page"><i class="far fa-eye"></i></a>
                                            <a href="../user/edit-item.php?id=<?php echo $item['id']; ?>" class="btn btn-primary" style="padding: 0.3rem 0.5rem; font-size: 0.8rem;" title="Edit Item"><i class="far fa-edit"></i></a>
                                            <form action="items.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this report?');" style="display: inline;">
                                                <input type="hidden" name="action_type" value="delete_item">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-danger" style="padding: 0.3rem 0.5rem; font-size: 0.8rem;" title="Delete Item"><i class="far fa-trash-alt"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center" style="padding: 3rem 1rem; color: var(--medium-gray);">
                    <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.4;"></i>
                    <h4>No items found</h4>
                    <p>No item reports matched your search or filter parameters.</p>
                    <a href="items.php" class="btn btn-outline mt-1"><i class="fas fa-undo"></i> Reset Search</a>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php
mysqli_close($conn);
include_once '../includes/footer.php';
?>
