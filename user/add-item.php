<?php
// =================================================================
// Post New Item Page (user/add-item.php)
// Allows logged-in users to report a lost or found item. Includes
// category querying, secure file upload, and prepared mysqli insertion.
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

// Pre-fill type from URL parameter (e.g. add-item.php?type=lost)
$default_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : "lost";
if ($default_type !== 'lost' && $default_type !== 'found') {
    $default_type = "lost";
}

// 3. Fetch categories from DB to fill the dropdown list
$cat_query = "SELECT * FROM categories ORDER BY category_name ASC";
$cat_result = mysqli_query($conn, $cat_query);

// 4. Handle Form Submission
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
    
    // Validate inputs
    if (empty($title) || empty($description) || empty($item_type) || $category_id <= 0 || empty($location) || empty($date_lost_found)) {
        $error_msg = "Please fill in all required fields marked with an asterisk (*).";
    } else {
        // Handle image upload
        $image_name = "default_item.png"; // Fallback image
        
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            // Upload to assets/uploads/ folder
            $uploaded = upload_image($_FILES['item_image'], '../assets/uploads/', 'default_item.png');
            if ($uploaded !== false) {
                $image_name = $uploaded;
            } else {
                $error_msg = "Failed to upload image. Please verify it is under 2MB and formatted in JPG/PNG/GIF.";
            }
        }
        
        // If there are no errors, insert into items table
        if (empty($error_msg)) {
            $insert_query = "INSERT INTO items (user_id, category_id, title, description, item_type, color, brand, location, date_lost_found, image, status) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Open')";
            $stmt = mysqli_prepare($conn, $insert_query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iissssssss", $user_id, $category_id, $title, $description, $item_type, $color, $brand, $location, $date_lost_found, $image_name);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_msg = "Your report has been successfully posted! Redirecting to your reports...";
                    // JavaScript redirect after 2 seconds
                    echo "<script>setTimeout(function(){ window.location.href = 'my-items.php'; }, 2000);</script>";
                } else {
                    $error_msg = "Database insert failed: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_msg = "Database prepared statement error: " . mysqli_error($conn);
            }
        }
    }
}

// Fetch counts for sidebar badge indicators
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
            <a href="my-items.php" class="sidebar-link"><i class="fas fa-list-ul"></i> My Reports</a>
            <a href="add-item.php" class="sidebar-link active"><i class="fas fa-plus-circle"></i> Post New Item</a>
            <a href="claims.php" class="sidebar-link"><i class="fas fa-gavel"></i> Manage Claims <?php echo $claims_count > 0 ? "<span class='text-green'>($claims_count)</span>" : ""; ?></a>
            <a href="messages.php" class="sidebar-link"><i class="fas fa-envelope"></i> Message Box <?php echo $unread_msg_count > 0 ? "<span class='text-blue'>($unread_msg_count)</span>" : ""; ?></a>
        </nav>
    </aside>

    <!-- Form Workspace -->
    <main class="dash-main">
        <div class="dash-header">
            <h2>Post New Lost or Found Item</h2>
        </div>

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
            <!-- Form with picture file support -->
            <form action="add-item.php" method="POST" enctype="multipart/form-data" class="auth-form form-grid">
                
                <div class="form-group span-2">
                    <label for="title">Item Title / Name <span class="required">*</span></label>
                    <input type="text" id="title" name="title" placeholder="e.g. Blue Jansport Backpack" required value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="item_type">Report Type <span class="required">*</span></label>
                    <select id="item_type" name="item_type" required>
                        <option value="lost" <?php echo $default_type === 'lost' ? 'selected' : ''; ?>>I Lost This Item</option>
                        <option value="found" <?php echo $default_type === 'found' ? 'selected' : ''; ?>>I Found This Item</option>
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
                            <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="color">Primary Color(Optional)</label>
                    <input type="text" id="color" name="color" placeholder="e.g. Black" value="<?php echo isset($_POST['color']) ? htmlspecialchars($_POST['color']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="brand">Brand / Model (Optional)</label>
                    <input type="text" id="brand" name="brand" placeholder="e.g. Apple" value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="location">Location Where Lost/Found <span class="required">*</span></label>
                    <input type="text" id="location" name="location" placeholder="e.g. Usted NLB GF1" required value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="date_lost_found">Date Lost/Found <span class="required">*</span></label>
                    <input type="date" id="date_lost_found" name="date_lost_found" required max="<?php echo date("Y-m-d"); ?>" value="<?php echo isset($_POST['date_lost_found']) ? htmlspecialchars($_POST['date_lost_found']) : date("Y-m-d"); ?>">
                </div>

                <div class="form-group span-2">
                    <label for="description">Item Detailed Description <span class="required">*</span></label>
                    <textarea id="description" name="description" rows="4" placeholder="Describe unique identifying markers, scratching, stickers, locked state, content of bags or folders etc. to help owners identify it..." required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                <div class="form-group span-2">
                    <label for="item_image">Upload Item Image (Optional)</label>
                    <input type="file" id="item_image" name="item_image" accept="image/*" class="file-input-styled">
                    <small class="text-muted">High-quality pictures help identify belongings. Max size: 2MB. Format: JPG, PNG.</small>
                </div>

                <div class="form-group span-2 flex-center">
                    <button type="submit" class="btn btn-primary btn-large btn-full mt-1">
                        Post Report Now
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
