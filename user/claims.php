<?php
// =================================================================
// Claims Management Page (user/claims.php)
// Allows claim tracking. Finders can approve or reject claims, which
// updates the response state and can transition the item status.
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

// 3. Handle Claim Response actions (Approve / Reject)
if (isset($_GET['action']) && isset($_GET['claim_id'])) {
    $action = sanitize_input($_GET['action']);
    $claim_id = (int)$_GET['claim_id'];
    
    // Safety verification: Check if this claim exists AND is on an item owned by currently logged-in user!
    $verify_query = "SELECT c.*, i.user_id as item_owner_id, i.id as item_id
                     FROM claims c
                     JOIN items i ON c.item_id = i.id
                     WHERE c.id = ?";
    $stmt = mysqli_prepare($conn, $verify_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $claim_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $claim = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
    
    if ($claim && (int)$claim['item_owner_id'] === $user_id) {
        $item_id = $claim['item_id'];
        
        if ($action === 'approve') {
            // Update claim status to 'approved'
            $update_claim = "UPDATE claims SET owner_response = 'approved' WHERE id = ?";
            $up_stmt = mysqli_prepare($conn, $update_claim);
            mysqli_stmt_bind_param($up_stmt, "i", $claim_id);
            mysqli_stmt_execute($up_stmt);
            mysqli_stmt_close($up_stmt);
            
            // Auto-update item status to 'claimed' to resolve it
            $update_item = "UPDATE items SET status = 'claimed' WHERE id = ?";
            $item_stmt = mysqli_prepare($conn, $update_item);
            mysqli_stmt_bind_param($item_stmt, "i", $item_id);
            mysqli_stmt_execute($item_stmt);
            mysqli_stmt_close($item_stmt);
            
            $success_msg = "Claim approved successfully! The item status has been marked as 'Claimed'.";
        } 
        elseif ($action === 'reject') {
            // Update claim status to 'rejected'
            $update_claim = "UPDATE claims SET owner_response = 'rejected' WHERE id = ?";
            $up_stmt = mysqli_prepare($conn, $update_claim);
            mysqli_stmt_bind_param($up_stmt, "i", $claim_id);
            mysqli_stmt_execute($up_stmt);
            mysqli_stmt_close($up_stmt);
            
            $success_msg = "Claim request rejected.";
        }
    } else {
        $error_msg = "Unauthorized action or invalid claim ID.";
    }
}

// 4. Fetch Claims Received (Claims on found items posted by ME)
$received_query = "SELECT c.*, i.title as item_title, i.image as item_pic, u.full_name as claimant_name, u.email as claimant_email, u.phone as claimant_phone
                   FROM claims c
                   JOIN items i ON c.item_id = i.id
                   JOIN users u ON c.claimant_id = u.id
                   WHERE i.user_id = ?
                   ORDER BY c.created_at DESC";
$rec_stmt = mysqli_prepare($conn, $received_query);
$claims_received = [];
if ($rec_stmt) {
    mysqli_stmt_bind_param($rec_stmt, "i", $user_id);
    mysqli_stmt_execute($rec_stmt);
    $res = mysqli_stmt_get_result($rec_stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $claims_received[] = $row;
    }
    mysqli_stmt_close($rec_stmt);
}

// 5. Fetch Claims Submitted (Claims made by ME on found items posted by others)
$submitted_query = "SELECT c.*, i.id as item_id, i.title as item_title, i.image as item_pic, u.full_name as finder_name
                     FROM claims c
                     JOIN items i ON c.item_id = i.id
                     JOIN users u ON i.user_id = u.id
                     WHERE c.claimant_id = ?
                     ORDER BY c.created_at DESC";
$sub_stmt = mysqli_prepare($conn, $submitted_query);
$claims_submitted = [];
if ($sub_stmt) {
    mysqli_stmt_bind_param($sub_stmt, "i", $user_id);
    mysqli_stmt_execute($sub_stmt);
    $res = mysqli_stmt_get_result($sub_stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $claims_submitted[] = $row;
    }
    mysqli_stmt_close($sub_stmt);
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
            <a href="my-items.php" class="sidebar-link"><i class="fas fa-list-ul"></i> My Reports</a>
            <a href="add-item.php" class="sidebar-link"><i class="fas fa-plus-circle"></i> Post New Item</a>
            <a href="claims.php" class="sidebar-link active"><i class="fas fa-gavel"></i> Manage Claims <?php echo $claims_count > 0 ? "<span class='text-green'>($claims_count)</span>" : ""; ?></a>
            <a href="messages.php" class="sidebar-link"><i class="fas fa-envelope"></i> Message Box <?php echo $unread_msg_count > 0 ? "<span class='text-blue'>($unread_msg_count)</span>" : ""; ?></a>
        </nav>
    </aside>

    <!-- Claims Panel Workspace -->
    <main class="dash-main">
        <div class="dash-header">
            <h2>Claims Center</h2>
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

        <!-- SECTION A: Claims Received (Claims other users submitted on my found items) -->
        <div class="spec-table-card" style="margin-bottom: 2rem;">
            <h3><i class="fas fa-file-signature text-green"></i> Claims Received (Verification Requests)</h3>
            <p class="text-muted" style="font-size: 0.88rem; margin-bottom: 1rem;">These users claim that items you reported finding belong to them. Review their description and details before approving or rejecting.</p>

            <?php if (count($claims_received) > 0): ?>
                <div class="table-responsive">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Item Photo</th>
                                <th>Found Item Name</th>
                                <th>Claimant Name & Contact</th>
                                <th>Claimant Description / Proof</th>
                                <th>Submitted Date</th>
                                <th>Status / Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($claims_received as $claim): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $base_path; ?>assets/uploads/<?php echo $claim['item_pic']; ?>" alt="Item" class="table-thumbnail" onerror="this.src='https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&q=80&w=100'">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($claim['item_title']); ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($claim['claimant_name']); ?></strong><br>
                                        <small class="text-muted"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($claim['claimant_email']); ?></small><br>
                                        <small class="text-muted"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($claim['claimant_phone']); ?></small>
                                    </td>
                                    <td>
                                        <div style="max-width: 300px; font-size: 0.85rem; line-height: 1.4;">
                                            <?php echo nl2br(htmlspecialchars($claim['claim_message'])); ?>
                                        </div>
                                    </td>
                                    <td><?php echo format_date($claim['created_at']); ?></td>
                                    <td>
                                        <?php if ($claim['owner_response'] === 'pending'): ?>
                                            <div class="action-buttons-group">
                                                <a href="claims.php?action=approve&claim_id=<?php echo $claim['id']; ?>" class="btn btn-success" style="padding: 0.35rem 0.65rem; font-size: 0.82rem;" onclick="return confirm('Are you sure you want to approve this claim? It will mark the item status as claimed.')"><i class="fas fa-check"></i> Approve</a>
                                                <a href="claims.php?action=reject&claim_id=<?php echo $claim['id']; ?>" class="btn btn-danger" style="padding: 0.35rem 0.65rem; font-size: 0.82rem;" onclick="return confirm('Are you sure you want to reject this claim request?')"><i class="fas fa-times"></i> Reject</a>
                                            </div>
                                        <?php else: ?>
                                            <?php 
                                            if ($claim['owner_response'] === 'approved') {
                                                echo "<span class='text-green' style='font-weight:600;'><i class='fas fa-check-circle'></i> Approved</span>";
                                            } else {
                                                echo "<span class='text-red' style='font-weight:600;'><i class='fas fa-times-circle'></i> Rejected</span>";
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted" style="padding: 2rem;">No verification claims have been submitted for your found items yet.</p>
            <?php endif; ?>
        </div>

        <!-- SECTION B: Claims Submitted (My claims on items found by other users) -->
        <div class="spec-table-card">
            <h3><i class="fas fa-gavel text-blue"></i> Claims Submitted (My Claims)</h3>
            <p class="text-muted" style="font-size: 0.88rem; margin-bottom: 1rem;">List of items reported found by others that you have claimed as your own property.</p>

            <?php if (count($claims_submitted) > 0): ?>
                <div class="table-responsive">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>Item Photo</th>
                                <th>Item Name</th>
                                <th>Finder Name</th>
                                <th>My Claim Message</th>
                                <th>Submitted Date</th>
                                <th>Response Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($claims_submitted as $claim): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $base_path; ?>assets/uploads/<?php echo $claim['item_pic']; ?>" alt="Item" class="table-thumbnail" onerror="this.src='https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&q=80&w=100'">
                                    </td>
                                    <td>
                                        <a href="<?php echo $base_path; ?>item.php?id=<?php echo $claim['item_id']; ?>" class="text-underline text-blue" style="font-weight: 600;">
                                            <?php echo htmlspecialchars($claim['item_title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($claim['finder_name']); ?></td>
                                    <td>
                                        <div style="max-width: 300px; font-size: 0.85rem; line-height: 1.4;">
                                            <?php echo nl2br(htmlspecialchars($claim['claim_message'])); ?>
                                        </div>
                                    </td>
                                    <td><?php echo format_date($claim['created_at']); ?></td>
                                    <td>
                                        <?php 
                                        if ($claim['owner_response'] === 'pending') {
                                            echo "<span class='text-gold' style='font-weight:600;'><i class='fas fa-hourglass-half'></i> Pending Review</span>";
                                        } elseif ($claim['owner_response'] === 'approved') {
                                            echo "<span class='text-green' style='font-weight:600;'><i class='fas fa-check-circle'></i> Claim Approved!</span>";
                                        } else {
                                            echo "<span class='text-red' style='font-weight:600;'><i class='fas fa-times-circle'></i> Claim Rejected</span>";
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted" style="padding: 2rem;">You have not made any claims on found items yet.</p>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php
mysqli_close($conn);
include_once '../includes/footer.php';
?>
