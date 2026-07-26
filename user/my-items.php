<?php
// =================================================================
// My Reported Items Page (user/my-items.php)
// Displays a list of all posts (lost & found) reported by this user.
// Allows deleting their own posts with safe ownership checks.
// =================================================================

// 1. Include dependencies
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// 2. Protect page - redirect to login.php if not logged in
check_login();

$user = get_logged_in_user();
$user_id = $user['id'];

$error_msg = "";
$success_msg = "";

// 3. Handle Item Deletion Request
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Safety check: verify item exists and belongs to currently logged-in user!
    $check_query = "SELECT image FROM items WHERE id = ? AND user_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    
    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, "ii", $delete_id, $user_id);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            // Retrieve image name to delete file from disk if desired
            mysqli_stmt_bind_result($check_stmt, $image_to_delete);
            mysqli_stmt_fetch($check_stmt);
            mysqli_stmt_close($check_stmt);
            
            // Delete the file from directory if it isn't default
            if ($image_to_delete !== 'default_item.jpg' && !empty($image_to_delete)) {
                $file_path = '../assets/uploads/' . $image_to_delete;
                if (file_exists($file_path)) {
                    unlink($file_path); // Delete image from server
                }
            }
            
            // Delete from database
            $delete_query = "DELETE FROM items WHERE id = ?";
            $del_stmt = mysqli_prepare($conn, $delete_query);
            mysqli_stmt_bind_param($del_stmt, "i", $delete_id);
            
            if (mysqli_stmt_execute($del_stmt)) {
                $success_msg = "Item report was deleted successfully.";
            } else {
                $error_msg = "Failed to delete database record: " . mysqli_stmt_error($del_stmt);
            }
            mysqli_stmt_close($del_stmt);
            
        } else {
            mysqli_stmt_close($check_stmt);
            $error_msg = "Error: Item not found, or you do not have permission to delete this post.";
        }
    }
}

// 4. Fetch all items reported by this user
$list_query = "SELECT i.*, c.category_name 
               FROM items i 
               JOIN categories c ON i.category_id = c.id 
               WHERE i.user_id = ? 
               ORDER BY i.created_at DESC";
$stmt = mysqli_prepare($conn, $list_query);
$my_items = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $my_items[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Fetch counts for sidebar badges
$claims_count = get_count($conn, "SELECT COUNT(*) FROM claims c JOIN items i ON c.item_id = i.id WHERE i.user_id = ? AND c.owner_response = 'pending'", [$user_id], "i");
$unread_msg_count = get_count($conn, "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0", [$user_id], "i");

$base_path = "../";
include_once '../includes/header.php';
?>

<div class="dashboard-layout">
    
    <!-- Sidebar -->
    <aside class="dash-sidebar">
        <div class="sidebar-profile">
            <i class="fas fa-user-circle text-blue" style="font-size: 5rem; margin-bottom: 0.75rem;"></i>
            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
            <p><?php echo htmlspecialchars($user['id_card']); ?></p>
            <span class="badge-role mt-1"><?php echo ucfirst($user['role']); ?></span>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile Settings</a>
            <a href="my-items.php" class="sidebar-link active"><i class="fas fa-list-ul"></i> My Reports</a>
            <a href="add-item.php" class="sidebar-link"><i class="fas fa-plus-circle"></i> Post New Item</a>
            <a href="claims.php" class="sidebar-link"><i class="fas fa-gavel"></i> Manage Claims <?php echo $claims_count > 0 ? "<span class='text-green'>($claims_count)</span>" : ""; ?></a>
            <a href="messages.php" class="sidebar-link"><i class="fas fa-envelope"></i> Message Box <?php echo $unread_msg_count > 0 ? "<span class='text-blue'>($unread_msg_count)</span>" : ""; ?></a>
        </nav>
    </aside>

    <!-- Content workspace -->
    <main class="dash-main">
        <div class="dash-header">
            <h2>My Reported Items</h2>
            <a href="add-item.php" class="btn btn-primary"><i class="fas fa-plus"></i> Post New Report</a>
        </div>

        <!-- Alerts -->
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <!-- Items Table Card -->
        <div class="spec-table-card">
            <?php if (count($my_items) > 0): ?>
                <div class="table-responsive">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Date Lost/Found</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_items as $item): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $base_path; ?>assets/uploads/<?php echo $item['image']; ?>" alt="Preview" class="table-thumbnail" onerror="this.src='./assets/uploads/default_item.jpg';">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                    </td>
                                    <td><?php echo get_type_badge($item['item_type']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo format_date($item['date_lost_found']); ?></td>
                                    <td><?php echo get_status_badge($item['status']); ?></td>
                                    <td>
                                        <div class="action-buttons-group">
                                            <!-- View public page -->
                                            <a href="<?php echo $base_path; ?>item.php?id=<?php echo $item['id']; ?>" class="btn btn-outline" style="padding: 0.35rem 0.7rem; font-size: 0.85rem;" title="View Details"><i class="far fa-eye"></i></a>
                                            <!-- Edit button -->
                                            <a href="edit-item.php?id=<?php echo $item['id']; ?>" class="btn btn-primary" style="padding: 0.35rem 0.7rem; font-size: 0.85rem;" title="Edit Report"><i class="far fa-edit"></i></a>
                                            <!-- Delete button with javascript confirmation check -->
                                            <a href="my-items.php?delete=<?php echo $item['id']; ?>" class="btn btn-danger confirm-delete" style="padding: 0.35rem 0.7rem; font-size: 0.85rem;" title="Delete Report" data-message="Are you sure you want to delete '<?php echo htmlspecialchars($item['title']); ?>'? This action cannot be undone."><i class="far fa-trash-alt"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center" style="padding: 3rem 1rem; color: var(--medium-gray);">
                    <i class="fas fa-clipboardempty" style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.5;"></i>
                    <h3>No Reports Yet</h3>
                    <p>You have not reported any lost or found items on the portal. Once you report an item, it will show up here.</p>
                    <a href="add-item.php" class="btn btn-primary mt-1"><i class="fas fa-plus"></i> Post Report Now</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php
mysqli_close($conn);
include_once '../includes/footer.php';
?>
