<?php
// =================================================================
// Edit Reported Item Page (user/edit-item.php)
// Allows users to modify details of their own lost/found reports.
// Registered users can update status, modify descriptions, and swap images.
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

// Validate the item ID from URL
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($item_id <= 0) {
    header("Location: my-items.php");
    exit();
}

// 3. Verify item exists AND belongs to the logged-in user
$verify_query = "SELECT * FROM items WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $verify_query);
$item = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $item_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// If no matching item is found for this user, redirect back to list
if (!$item) {
    header("Location: my-items.php");
    exit();
}

// 4. Fetch categories from DB for the selection list
$cat_query = "SELECT * FROM categories ORDER BY category_name ASC";
$cat_result = mysqli_query($conn, $cat_query);

// 5. Handle Form Update Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize user inputs
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);
    $item_type = sanitize_input($_POST['item_type']);
    $category_id = (int)$_POST['category_id'];
    $color = sanitize_input($_POST['color']);
    $brand = sanitize_input($_POST['brand']);
    $location = sanitize_input($_POST['location']);
    $date_lost_found = sanitize_input($_POST['date_lost_found']);
    $status = sanitize_input($_POST['status']);
    
    // Validate inputs
    if (empty($title) || empty($description) || empty($item_type) || $category_id <= 0 || empty($location) || empty($date_lost_found) || empty($status)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        // Handle optional replacement image upload
        $image_name = $item['image']; // Default to current image
        
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            $uploaded = upload_image($_FILES['item_image'], '../assets/uploads/', 'default_item.png');
            if ($uploaded !== false) {
                // Delete previous non-default image from server disk to save space
                if ($item['image'] !== 'default_item.png' && !empty($item['image'])) {
                    $old_file_path = '../assets/uploads/' . $item['image'];
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
                $image_name = $uploaded; // Update name
            } else {
                $error_msg = "Failed to upload new image. Ensure it is under 2MB and formatted in JPG/PNG.";
            }
        }
        
        // Update database if no image upload error occurred
        if (empty($error_msg)) {
            $update_query = "UPDATE items 
                             SET category_id = ?, title = ?, description = ?, item_type = ?, color = ?, brand = ?, location = ?, date_lost_found = ?, image = ?, status = ? 
                             WHERE id = ? AND user_id = ?";
            $up_stmt = mysqli_prepare($conn, $update_query);
            
            if ($up_stmt) {
                mysqli_stmt_bind_param($up_stmt, "isssssssssii", $category_id, $title, $description, $item_type, $color, $brand, $location, $date_lost_found, $image_name, $status, $item_id, $user_id);
                
                if (mysqli_stmt_execute($up_stmt)) {
                    $success_msg = "Your report details were updated successfully! Redirecting...";
                    echo "<script>setTimeout(function(){ window.location.href = 'my-items.php'; }, 2000);</script>";
                } else {
                    $error_msg = "Database update failed: " . mysqli_stmt_error($up_stmt);
                }
                mysqli_stmt_close($up_stmt);
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
            <h2>Edit Report: <?php echo htmlspecialchars($item['title']); ?></h2>
            <a href="my-items.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to My Reports</a>
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

        <div class="spec-table-card">
            <form action="edit-item.php?id=<?php echo $item_id; ?>" method="POST" enctype="multipart/form-data" class="auth-form form-grid">
                
                <div class="form-group span-2">
                    <label for="title">Item Title / Name <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : htmlspecialchars($item['title']); ?>">
                </div>

                <div class="form-group">
                    <label for="item_type">Report Type <span class="required">*</span></label>
                    <select id="item_type" name="item_type" required>
                        <option value="lost" <?php echo $item['item_type'] === 'lost' ? 'selected' : ''; ?>>Lost Item</option>
                        <option value="found" <?php echo $item['item_type'] === 'found' ? 'selected' : ''; ?>>Found Item</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category_id">Category <span class="required">*</span></label>
                    <select id="category_id" name="category_id" required>
                        <option value="0">Select Category</option>
                        <?php 
                        mysqli_data_seek($cat_result, 0);
                        while ($cat = mysqli_fetch_assoc($cat_result)): 
                        ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo (int)$item['category_id'] === (int)$cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="color">Primary Color</label>
                    <input type="text" id="color" name="color" value="<?php echo isset($_POST['color']) ? htmlspecialchars($_POST['color']) : htmlspecialchars($item['color']); ?>">
                </div>

                <div class="form-group">
                    <label for="brand">Brand / Model</label>
                    <input type="text" id="brand" name="brand" value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : htmlspecialchars($item['brand']); ?>">
                </div>

                <div class="form-group">
                    <label for="location">Location Where Lost/Found <span class="required">*</span></label>
                    <input type="text" id="location" name="location" required value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : htmlspecialchars($item['location']); ?>">
                </div>

                <div class="form-group">
                    <label for="date_lost_found">Date Lost/Found <span class="required">*</span></label>
                    <input type="date" id="date_lost_found" name="date_lost_found" required max="<?php echo date("Y-m-d"); ?>" value="<?php echo isset($_POST['date_lost_found']) ? htmlspecialchars($_POST['date_lost_found']) : htmlspecialchars($item['date_lost_found']); ?>">
                </div>

                <div class="form-group">
                    <label for="status">Resolution Status <span class="required">*</span></label>
                    <select id="status" name="status" required>
                        <option value="Open" <?php echo $item['status'] === 'Open' ? 'selected' : ''; ?>>Open (Still Unresolved)</option>
                        <option value="claimed" <?php echo $item['status'] === 'claimed' ? 'selected' : ''; ?>>Claimed (Returned to Owner)</option>
                        <option value="recovered" <?php echo $item['status'] === 'recovered' ? 'selected' : ''; ?>>Recovered (Reunited)</option>
                        <option value="archived" <?php echo $item['status'] === 'archived' ? 'selected' : ''; ?>>Archived (Closed/Old)</option>
                    </select>
                </div>

                <div class="form-group span-2">
                    <label for="description">Detailed Description <span class="required">*</span></label>
                    <textarea id="description" name="description" rows="4" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : htmlspecialchars($item['description']); ?></textarea>
                </div>

                <!-- Picture layout with preview of current image -->
                <div class="form-group span-2" style="display: flex; flex-direction: row; align-items: center; gap: 2rem;">
                    <div class="current-image-preview">
                        <p style="font-size: 0.85rem; color: #4b5563; margin-bottom: 0.25rem;">Current Image:</p>
                        <img src="<?php echo $base_path; ?>assets/uploads/<?php echo $item['image']; ?>" alt="Current" class="table-thumbnail" style="width: 100px; height: 100px; border-radius: 8px;" onerror="this.src='https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&q=80&w=100'">
                    </div>
                    <div style="flex-grow: 1;">
                        <label for="item_image">Replace Image (Optional)</label>
                        <input type="file" id="item_image" name="item_image" accept="image/*" class="file-input-styled">
                        <small class="text-muted">Uploading a new picture will replace the current file. Max size: 2MB.</small>
                    </div>
                </div>

                <div class="form-group span-2 flex-center">
                    <button type="submit" class="btn btn-primary btn-large btn-full mt-1">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<?php
mysqli_close($conn);
include_once '../includes/footer.php';
?>
