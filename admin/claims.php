<?php
// =================================================================
// Admin Claims Management (admin/claims.php)
// Allows administrators to review ownership claims, verify details,
// approve/reject claims, and automatically update item status.
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

// 4. Handle POST actions (Approve claim, Reject claim, or Delete claim)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'update_claim') {
        $claim_id = (int)$_POST['claim_id'];
        $item_id = (int)$_POST['item_id'];
        $new_response = sanitize_input($_POST['owner_response']);
        
        // Update claim record
        $claim_update = "UPDATE claims SET owner_response = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $claim_update);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $new_response, $claim_id);
            if (mysqli_stmt_execute($stmt)) {
                
                // If claim is approved by admin, set item status to 'claimed' or 'recovered'
                if ($new_response === 'approved') {
                    $item_update = "UPDATE items SET status = 'claimed' WHERE id = ?";
                    $i_stmt = mysqli_prepare($conn, $item_update);
                    if ($i_stmt) {
                        mysqli_stmt_bind_param($i_stmt, "i", $item_id);
                        mysqli_stmt_execute($i_stmt);
                        mysqli_stmt_close($i_stmt);
                    }
                }
                
                $success_msg = "Claim #" . $claim_id . " status has been set to '" . ucfirst($new_response) . "'.";
            } else {
                $error_msg = "Failed to update claim status.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'delete_claim') {
        $claim_id = (int)$_POST['claim_id'];
        
        $del_query = "DELETE FROM claims WHERE id = ?";
        $stmt = mysqli_prepare($conn, $del_query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $claim_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Claim record #" . $claim_id . " deleted successfully.";
            } else {
                $error_msg = "Failed to delete claim record.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// 5. Filter status (pending, approved, rejected, or all)
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$highlight_id = isset($_GET['review_id']) ? (int)$_GET['review_id'] : 0;

// 6. Fetch Claims
$sql = "SELECT c.*, i.title as item_title, i.image as item_pic, i.item_type, i.id as item_id,
               u.full_name as claimant_name, u.email as claimant_email, u.phone as claimant_phone, u.student_staff_id as claimant_card,
               o.full_name as owner_name, o.email as owner_email
        FROM claims c
        JOIN items i ON c.item_id = i.id
        JOIN users u ON c.claimant_id = u.id
        JOIN users o ON i.user_id = o.id
        WHERE 1=1";

if (!empty($filter_status)) {
    $sql .= " AND c.owner_response = '$filter_status'";
}

$sql .= " ORDER BY c.created_at DESC";

$claims_res = mysqli_query($conn, $sql);
$claims_list = [];
if ($claims_res) {
    while ($row = mysqli_fetch_assoc($claims_res)) {
        $claims_list[] = $row;
    }
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
            <a href="claims.php" class="sidebar-link active"><i class="fas fa-file-signature"></i> Claims Management <?php echo $total_pending_claims > 0 ? "<span class='text-gold'>($total_pending_claims)</span>" : ""; ?></a>
            <a href="users.php" class="sidebar-link"><i class="fas fa-users-cog"></i> User Accounts</a>
            <a href="../user/profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile Settings</a>
            <a href="../user/add-item.php" class="sidebar-link"><i class="fas fa-plus-circle"></i> Post Admin Notice</a>
        </nav>
    </aside>

    <!-- Main Workspace -->
    <main class="dash-main">
        
        <div class="dash-header" style="margin-bottom: 1rem;" style=>
            <div>
                <h2><i class="fas fa-file-signature text-blue"></i> Administrative Claims Verification</h2>
                <p>Review ownership claims submitted by students and staff across the school portal.</p>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="claims.php?status=pending" class="btn <?php echo $filter_status === 'pending' ? 'btn-primary' : 'btn-outline'; ?>"> Pending (<?php echo $total_pending_claims; ?>)</a>
                <a href="claims.php" class="btn <?php echo empty($filter_status) ? 'btn-primary' : 'btn-outline'; ?>"><i class="fas fa-list"></i> All Claims</a>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success mt-1"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger mt-1"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Claims List Container -->
        <?php if (count($claims_list) > 0): ?>
            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <?php foreach ($claims_list as $claim): ?>
                    <?php $is_highlighted = ($highlight_id === (int)$claim['id']); ?>
                    <div class="spec-table-card" style="margin: 0; padding: 1.5rem; border-left: 4px solid <?php 
                        if ($claim['owner_response'] === 'pending') echo 'var(--accent-gold)';
                        else if ($claim['owner_response'] === 'approved') echo 'var(--accent-green)';
                        else echo 'var(--accent-red)';
                    ?>; <?php if ($is_highlighted) echo 'background: #eff6ff; border-color: var(--primary-blue);'; ?>">
                        
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1rem;">
                            
                            <!-- Item & Claimant Brief Info -->
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($claim['item_pic']); ?>" alt="Pic" style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover;" onerror="this.src='./assets/uploads/default_item.jpg';">
                                <div>
                                    <h3 style="margin: 0; font-size: 1.1rem; color: var(--dark-charcoal);"><?php echo htmlspecialchars($claim['item_title']); ?></h3>
                                    <p style="margin: 0.2rem 0 0 0; font-size: 0.85rem; color: var(--medium-gray);">
                                        Reported Poster: <strong><?php echo htmlspecialchars($claim['owner_name']); ?></strong> (<?php echo htmlspecialchars($claim['owner_email']); ?>)
                                    </p>
                                </div>
                            </div>

                            <!-- Claim Status Badge -->
                            <div>
                                <?php if ($claim['owner_response'] === 'pending'): ?>
                                    <span class="badge badge-gold" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;"><i class="fas fa-hourglass-half"></i> Pending Review</span>
                                <?php elseif ($claim['owner_response'] === 'approved'): ?>
                                    <span class="badge badge-green" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;"><i class="fas fa-check-circle"></i> Approved</span>
                                <?php else: ?>
                                    <span class="badge badge-red" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;"><i class="fas fa-times-circle"></i> Rejected</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Proof & Contact Details -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                            
                            <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.95rem; color: var(--primary-blue);"><i class="fas fa-user-tag"></i> Claimant Information</h4>
                                <p style="margin: 0 0 0.25rem 0; font-size: 0.88rem;"><strong>Name:</strong> <?php echo htmlspecialchars($claim['claimant_name']); ?></p>
                                <p style="margin: 0 0 0.25rem 0; font-size: 0.88rem;"><strong>ID/Staff Card:</strong> <?php echo htmlspecialchars($claim['claimant_card']); ?></p>
                                <p style="margin: 0 0 0.25rem 0; font-size: 0.88rem;"><strong>Email:</strong> <?php echo htmlspecialchars($claim['claimant_email']); ?></p>
                                <p style="margin: 0; font-size: 0.88rem;"><strong>Phone:</strong> <?php echo htmlspecialchars($claim['claimant_phone']); ?></p>
                            </div>

                            <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.95rem; color: var(--primary-blue);"><i class="fas fa-comment-dots"></i> Submitted Ownership Proof</h4>
                                <p style="margin: 0; font-size: 0.9rem; color: #334155; line-height: 1.5; white-space: pre-wrap;"><?php echo htmlspecialchars($claim['proof_details']); ?></p>
                                <small class="text-muted" style="display: block; margin-top: 0.5rem;"><i class="far fa-clock"></i> Submitted on <?php echo date('M d, Y H:i', strtotime($claim['created_at'])); ?></small>
                            </div>

                        </div>

                        <!-- Admin Decision Bar -->
                        <div style="display: flex; justify-content: space-between; align-items: center; background: #fff; padding-top: 0.75rem; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 0.75rem;">
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <a href="../item.php?id=<?php echo $claim['item_id']; ?>" class="btn btn-outline" style="font-size: 0.82rem; padding: 0.4rem 0.8rem;"><i class="far fa-eye"></i> View Item Page</a>
                                <a href="../user/messages.php?chat_user_id=<?php echo $claim['claimant_id']; ?>&item_id=<?php echo $claim['item_id']; ?>" class="btn btn-outline" style="font-size: 0.82rem; padding: 0.4rem 0.8rem;">
                                    <i class="fas fa-envelope">
                                    </i> Message Claimant</a>
                            </div>

                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <form action="claims.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="action_type" value="update_claim">
                                    <input type="hidden" name="claim_id" value="<?php echo $claim['id']; ?>">
                                    <input type="hidden" name="item_id" value="<?php echo $claim['item_id']; ?>">
                                    <input type="hidden" name="owner_response" value="approved">
                                    <button type="submit" class="btn btn-primary" style="background:#10b981; color: white; border-color: var(--accent-green); font-size: 0.82rem; padding: 0.4rem 0.8rem;" 
                                        <?php echo $claim['owner_response'] === 'approved' ? 'disabled' : ''; ?>>
                                        <i class="fas fa-check"></i> Approve Claim
                                    </button>
                                </form>

                                <form action="claims.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="action_type" value="update_claim">
                                    <input type="hidden" name="claim_id" value="<?php echo $claim['id']; ?>">
                                    <input type="hidden" name="item_id" value="<?php echo $claim['item_id']; ?>">
                                    <input type="hidden" name="owner_response" value="rejected">
                                    <button type="submit" class="btn btn-danger" style="font-size: 0.82rem; padding: 0.4rem 0.8rem;" 
                                        <?php echo $claim['owner_response'] === 'rejected' ? 'disabled' : ''; ?>>
                                        <i class="fas fa-times"></i> Reject Claim
                                    </button>
                                </form>

                                <form action="claims.php" method="POST" onsubmit="return confirm('Permanently delete this claim record?');" style="display: inline;">
                                    <input type="hidden" name="action_type" value="delete_claim">
                                    <input type="hidden" name="claim_id" value="<?php echo $claim['id']; ?>">
                                    <button type="submit" class="btn btn-outline" style="color: var(--accent-red); font-size: 0.82rem; padding: 0.4rem 0.6rem;" title="Delete Record"><i class="far fa-trash-alt"></i> Delete</button>
                                </form>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="spec-table-card text-center" style="padding: 3rem 1rem; color: var(--medium-gray);">
                <i class="fas fa-file-invoice" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.4;"></i>
                <h4>No claims found</h4>
                <p>No ownership claims are currently matching your selection.</p>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php
mysqli_close($conn);
include_once '../includes/footer.php';
?>
