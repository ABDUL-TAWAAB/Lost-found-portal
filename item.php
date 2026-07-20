<?php
// =================================================================
// Item Details Page (item.php)
// Displays complete specifications for a single lost or found item.
// Registered users can submit claims (for found items) or send direct
// messages to coordinate reunions.
// =================================================================

// 1. Include dependencies
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

// 2. Validate the item ID from URL parameter
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($item_id <= 0) {
    header("Location: search.php");
    exit();
}

$error_msg = "";
$success_msg = "";

// 3. Handle Form Actions (Claims and Messages)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $action = sanitize_input($_POST['action']);
    $logged_user_id = $_SESSION['user_id'];
    
    // ACTION A: Submit a Claim
    if ($action === 'submit_claim') {
        $claim_message = sanitize_input($_POST['claim_message']);
        
        if (empty($claim_message)) {
            $error_msg = "Please write a supporting description of your claim.";
        } else {
            // Check if user already claimed this item
            $check_claim = "SELECT id FROM claims WHERE item_id = ? AND claimant_id = ?";
            $claim_stmt = mysqli_prepare($conn, $check_claim);
            mysqli_stmt_bind_param($claim_stmt, "ii", $item_id, $logged_user_id);
            mysqli_stmt_execute($claim_stmt);
            mysqli_stmt_store_result($claim_stmt);
            
            if (mysqli_stmt_num_rows($claim_stmt) > 0) {
                $error_msg = "You have already submitted a claim for this item.";
            } else {
                // Insert the new claim
                $insert_claim = "INSERT INTO claims (item_id, claimant_id, claim_message) VALUES (?, ?, ?)";
                $inst_stmt = mysqli_prepare($conn, $insert_claim);
                mysqli_stmt_bind_param($inst_stmt, "iis", $item_id, $logged_user_id, $claim_message);
                if (mysqli_stmt_execute($inst_stmt)) {
                    $success_msg = "Your claim was submitted successfully! The finder will review it.";
                } else {
                    $error_msg = "Failed to submit claim. Try again later.";
                }
                mysqli_stmt_close($inst_stmt);
            }
            mysqli_stmt_close($claim_stmt);
        }
    }
    
    // ACTION B: Send a Message
    elseif ($action === 'send_message') {
        $receiver_id = (int)$_POST['receiver_id'];
        $message_text = sanitize_input($_POST['message']);
        
        if (empty($message_text)) {
            $error_msg = "Message content cannot be empty.";
        } else {
            // Insert chat message
            $insert_msg = "INSERT INTO messages (sender_id, receiver_id, item_id, message) VALUES (?, ?, ?, ?)";
            $msg_stmt = mysqli_prepare($conn, $insert_msg);
            mysqli_stmt_bind_param($msg_stmt, "iiis", $logged_user_id, $receiver_id, $item_id, $message_text);
            if (mysqli_stmt_execute($msg_stmt)) {
                $success_msg = "Your message was sent successfully! Go to Messages tab to view the chat.";
            } else {
                $error_msg = "Failed to send message: " . mysqli_error($conn);
            }
            mysqli_stmt_close($msg_stmt);
        }
    }
}

// 4. Fetch the specific item details along with poster and category info
$item_query = "SELECT i.*, c.category_name, u.full_name as poster_name, u.email as poster_email, u.phone as poster_phone, u.profile_picture as poster_pic, u.role as poster_role
               FROM items i 
               JOIN categories c ON i.category_id = c.id 
               JOIN users u ON i.user_id = u.id 
               WHERE i.id = ?";
$stmt = mysqli_prepare($conn, $item_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $item_id);
    mysqli_stmt_execute($stmt);
    $item_result = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($item_result);
    mysqli_stmt_close($stmt);
}

// If item does not exist, redirect to browse
if (!$item) {
    header("Location: search.php");
    exit();
}

$base_path = "";
include_once 'includes/header.php';
?>

<!-- Breadcrumbs -->
<div class="breadcrumbs">
    <a href="index.php">Home</a> &gt; <a href="search.php">Search Items</a> &gt; <span><?php echo htmlspecialchars($item['title']); ?></span>
</div>

<!-- Main Item Layout Split Grid -->
<div class="item-detail-grid">
    
    <!-- Left Column: Item Specifications -->
    <div class="item-spec-panel">
        
        <!-- Image block -->
        <div class="detail-image-wrapper">
            <img src="assets/uploads/<?php echo !empty($item['image']) && $item['image'] !== 'default_item.png' ? $item['image'] : 'default_item.png'; ?>" alt="<?php echo $item['title']; ?>" class="detail-img" onerror="this.src='https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&q=80&w=800'">
            <div class="detail-type-tag">
                <?php echo get_type_badge($item['item_type']); ?>
            </div>
        </div>

        <!-- Item Specifications Table -->
        <div class="spec-table-card">
            <h3><i class="fas fa-info-circle"></i> Item Specifications</h3>
            <table class="details-table">
                <tr>
                    <th>Category:</th>
                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                </tr>
                <tr>
                    <th>Color:</th>
                    <td><?php echo !empty($item['color']) ? htmlspecialchars($item['color']) : '<em class="text-muted">Not specified</em>'; ?></td>
                </tr>
                <tr>
                    <th>Brand:</th>
                    <td><?php echo !empty($item['brand']) ? htmlspecialchars($item['brand']) : '<em class="text-muted">Not specified</em>'; ?></td>
                </tr>
                <tr>
                    <th>Location:</th>
                    <td><i class="fas fa-map-marker-alt text-red"></i> <?php echo htmlspecialchars($item['location']); ?></td>
                </tr>
                <tr>
                    <th>Date Lost/Found:</th>
                    <td><i class="far fa-calendar-alt"></i> <?php echo format_date($item['date_lost_found']); ?></td>
                </tr>
                <tr>
                    <th>Report Status:</th>
                    <td><?php echo get_status_badge($item['status']); ?></td>
                </tr>
                <tr>
                    <th>Date Posted:</th>
                    <td><?php echo format_date($item['created_at']); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Right Column: Title, Description & Action Center -->
    <div class="item-action-panel">
        
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

        <!-- Item Description Card -->
        <div class="item-desc-card">
            <h1 class="item-title"><?php echo htmlspecialchars($item['title']); ?></h1>
            <div class="desc-content">
                <h4>Description:</h4>
                <p><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
            </div>
        </div>

        <!-- Action Panel (Submitting claims or sending messages) -->
        <div class="contact-card">
            
            <?php if (!isset($_SESSION['user_id'])): ?>
                <!-- Prompt user to log in -->
                <div class="guest-prompt">
                    <h3>Want to contact the reporter?</h3>
                    <p>To submit a claim or message the person who posted this, you must be a registered member of our university lost & found portal.</p>
                    <div class="prompt-buttons">
                        <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Login Now</a>
                        <a href="register.php" class="btn btn-outline">Create Account</a>
                    </div>
                </div>
            <?php else: ?>
                
                <?php 
                $logged_id = $_SESSION['user_id']; 
                $is_owner = ($logged_id === (int)$item['user_id']);
                ?>

                <?php if ($is_owner): ?>
                    <!-- If currently logged in user is the owner of the post -->
                    <div class="owner-prompt text-center">
                        <div class="owner-avatar">
                            <i class="fas fa-user-circle text-blue icon-large"></i>
                        </div>
                        <h3>This is your post</h3>
                        <p class="text-muted">You posted this item on <?php echo format_date($item['created_at']); ?>. You can edit the details, change the status, or delete the post entirely from your dashboard.</p>
                        <div class="prompt-buttons justify-center">
                            <a href="user/edit-item.php?id=<?php echo $item['id']; ?>" class="btn btn-secondary"><i class="fas fa-edit"></i> Edit This Post</a>
                            <a href="user/my-items.php" class="btn btn-outline">My Reports</a>
                        </div>
                    </div>
                <?php else: ?>
                    
                    <!-- Display Poster profile card -->
                    <div class="poster-profile">
                        <img src="assets/uploads/<?php echo !empty($item['poster_pic']) ? $item['poster_pic'] : 'default_avatar.png'; ?>" alt="Poster" class="avatar-small" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
                        <div class="poster-info">
                            <h4>Reported by: <?php echo htmlspecialchars($item['poster_name']); ?></h4>
                            <span class="badge-role"><?php echo ucfirst($item['poster_role']); ?></span>
                        </div>
                    </div>

                    <!-- Display claims form if item is found and unresolved -->
                    <?php if ($item['item_type'] === 'found' && $item['status'] === 'Open'): ?>
                        <div class="claim-form-wrapper">
                            <h3><i class="fas fa-award text-gold"></i> Submit a Claim</h3>
                            <p class="text-muted">Is this item yours? Submit a claim with proof of ownership (e.g. describe contents inside, password of phone, or specific scratches) to request the finder to return it.</p>
                            
                            <form action="item.php?id=<?php echo $item['id']; ?>" method="POST" class="simple-form">
                                <input type="hidden" name="action" value="submit_claim">
                                <div class="form-group">
                                    <textarea id="claim_message" name="claim_message" rows="3" placeholder="Provide details to verify you are the real owner of this item..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-success btn-full"><i class="fas fa-gavel"></i> Submit Claim Request</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Message Sending Form -->
                    <div class="msg-form-wrapper">
                        <h3><i class="far fa-comments text-blue"></i> Send Direct Message</h3>
                        <p class="text-muted">Have a quick question about this item? Text the reporter directly. Your chat log will be saved in your Messages center.</p>
                        
                        <form action="item.php?id=<?php echo $item['id']; ?>" method="POST" class="simple-form">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="receiver_id" value="<?php echo $item['user_id']; ?>">
                            <div class="form-group">
                                <textarea id="message" name="message" rows="3" placeholder="Type your message here..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-full"><i class="fas fa-paper-plane"></i> Send Message</button>
                        </form>
                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php
mysqli_close($conn);
include_once 'includes/footer.php';
?>
