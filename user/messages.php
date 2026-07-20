<?php
// =================================================================
// Message Box / Chat Center (user/messages.php)
// Allows users and admins to communicate regarding specific reports.
// Features a beautiful split dual-pane layout, real-time responsive
// styled bubbles, unread notification badges, and quick item access.
// =================================================================

// 1. Include dependencies
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// 2. Protect page - redirect to login if not logged in
check_login();

// 3. Retrieve currently logged in user info
$user = get_logged_in_user();
$user_id = (int)$user['id'];

$error_msg = "";
$success_msg = "";

// 4. Handle sending a reply in the chat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $active_item_id = (int)$_POST['item_id'];
    $active_other_id = (int)$_POST['receiver_id'];
    $reply_text = sanitize_input($_POST['reply_message']);
    
    if (empty($reply_text)) {
        $error_msg = "Your message cannot be empty.";
    } else {
        // Insert message record
        $reply_query = "INSERT INTO messages (sender_id, receiver_id, item_id, message) VALUES (?, ?, ?, ?)";
        $reply_stmt = mysqli_prepare($conn, $reply_query);
        if ($reply_stmt) {
            mysqli_stmt_bind_param($reply_stmt, "iiis", $user_id, $active_other_id, $active_item_id, $reply_text);
            if (mysqli_stmt_execute($reply_stmt)) {
                // Post-Redirect-Get to avoid form resubmission on refresh
                header("Location: messages.php?chat_user_id=" . $active_other_id . "&item_id=" . $active_item_id);
                exit();
            } else {
                $error_msg = "Failed to send message: " . mysqli_error($conn);
            }
            mysqli_stmt_close($reply_stmt);
        }
    }
}

// 5. Gather all messages involving current user, sorted DESC by creation
// This allows us to map and group the latest conversation items on the fly
$convs_query = "SELECT m.*, 
                       i.title AS item_title, i.image AS item_pic, i.item_type,
                       u.full_name AS other_name, u.profile_picture AS other_pic, u.role AS other_role, u.id AS other_id
                FROM messages m
                JOIN items i ON m.item_id = i.id
                JOIN users u ON u.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
                WHERE m.sender_id = ? OR m.receiver_id = ?
                ORDER BY m.created_at DESC";

$all_messages = [];
$conversations = [];
$unread_msg_count = 0;

$stmt = mysqli_prepare($conn, $convs_query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "iii", $user_id, $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $all_messages[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Group messages into distinct conversations sorted by latest message
foreach ($all_messages as $msg) {
    $item_id = (int)$msg['item_id'];
    $other_id = (int)$msg['other_id'];
    $key = $item_id . '_' . $other_id;
    
    if (!isset($conversations[$key])) {
        $conversations[$key] = [
            'item_id' => $item_id,
            'item_title' => $msg['item_title'],
            'item_pic' => $msg['item_pic'],
            'item_type' => $msg['item_type'],
            'other_id' => $other_id,
            'other_name' => $msg['other_name'],
            'other_pic' => $msg['other_pic'],
            'other_role' => $msg['other_role'],
            'last_message' => $msg['message'],
            'last_message_time' => $msg['created_at'],
            'unread_count' => 0
        ];
    }
    
    // Count as unread if current user is the receiver and message is unread
    if ((int)$msg['receiver_id'] === $user_id && (int)$msg['is_read'] === 0) {
        $conversations[$key]['unread_count']++;
        $unread_msg_count++;
    }
}

// 6. Handle active conversation selection
$active_item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$active_other_id = isset($_GET['chat_user_id']) ? (int)$_GET['chat_user_id'] : 0;

// If no active conversation set, open the most recent one automatically if available
if (($active_item_id === 0 || $active_other_id === 0) && count($conversations) > 0) {
    $first_conv = reset($conversations);
    $active_item_id = $first_conv['item_id'];
    $active_other_id = $first_conv['other_id'];
}

$active_key = $active_item_id . '_' . $active_other_id;
$active_conv = isset($conversations[$active_key]) ? $conversations[$active_key] : null;

// 7. If we have an active conversation, mark unread messages from other user as read
if ($active_conv && $active_conv['unread_count'] > 0) {
    $mark_query = "UPDATE messages SET is_read = 1 
                   WHERE item_id = ? AND sender_id = ? AND receiver_id = ? AND is_read = 0";
    $mark_stmt = mysqli_prepare($conn, $mark_query);
    if ($mark_stmt) {
        mysqli_stmt_bind_param($mark_stmt, "iii", $active_item_id, $active_other_id, $user_id);
        mysqli_stmt_execute($mark_stmt);
        mysqli_stmt_close($mark_stmt);
        
        // Refresh local count
        $unread_msg_count -= $active_conv['unread_count'];
        $conversations[$active_key]['unread_count'] = 0;
    }
}

// 8. Fetch active chat message logs
$active_messages = [];
if ($active_conv) {
    $logs_query = "SELECT m.*, u.full_name as sender_name, u.profile_picture as sender_pic 
                   FROM messages m
                   JOIN users u ON m.sender_id = u.id
                   WHERE m.item_id = ? 
                   AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                   ORDER BY m.created_at ASC";
    $logs_stmt = mysqli_prepare($conn, $logs_query);
    if ($logs_stmt) {
        mysqli_stmt_bind_param($logs_stmt, "iiiii", $active_item_id, $user_id, $active_other_id, $active_other_id, $user_id);
        mysqli_stmt_execute($logs_stmt);
        $logs_res = mysqli_stmt_get_result($logs_stmt);
        while ($row = mysqli_fetch_assoc($logs_res)) {
            $active_messages[] = $row;
        }
        mysqli_stmt_close($logs_stmt);
    }
}

// Sidebar badge for claims
$claims_count = get_count($conn, "SELECT COUNT(*) FROM claims c JOIN items i ON c.item_id = i.id WHERE i.user_id = ? AND c.owner_response = 'pending'", [$user_id], "i");

$base_path = "../";
include_once '../includes/header.php';
?>

<!-- Additional Inline Styles to elevate CSS for standard messages split layout -->
<style>
    .chat-container {
        display: grid;
        grid-template-columns: 350px 1fr;
        background: #ffffff;
        border: 1px solid var(--border-gray);
        border-radius: 12px;
        height: 700px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        overflow: hidden;
    }
    .chat-sidebar {
        border-right: 1px solid var(--border-gray);
        display: flex;
        flex-direction: column;
        background: #fdfdfd;
        height: 100%;
    }
    .chat-sidebar-header {
        padding: 1.25rem;
        border-bottom: 1px solid var(--border-gray);
        background: #fff;
    }
    .chat-sidebar-header h4 {
        margin: 0;
        color: var(--dark-charcoal);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1.15rem;
    }
    .chat-search-wrapper {
        margin-top: 0.75rem;
        position: relative;
    }
    .chat-search-wrapper input {
        width: 100%;
        padding: 0.5rem 1rem 0.5rem 2.25rem;
        border-radius: 8px;
        border: 1px solid var(--border-gray);
        font-size: 0.85rem;
        outline: none;
        background: #f8fafc;
    }
    .chat-search-wrapper i {
        position: absolute;
        left: 0.8rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--medium-gray);
        font-size: 0.85rem;
    }
    .chat-list {
        flex: 1;
        overflow-y: auto;
    }
    .chat-list-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f3f4f6;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        background: #fff;
    }
    .chat-list-item:hover {
        background: #f8fafc;
    }
    .chat-list-item.active {
        background: #eff6ff;
        border-left: 4px solid var(--primary-blue);
    }
    .chat-avatar-wrapper {
        position: relative;
        flex-shrink: 0;
    }
    .chat-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e5e7eb;
    }
    .chat-avatar-badge {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        object-fit: cover;
        border: 1.5px solid #fff;
    }
    .chat-info {
        flex: 1;
        min-width: 0;
    }
    .chat-info-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.25rem;
    }
    .chat-info-top h5 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--dark-charcoal);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .chat-time {
        font-size: 0.75rem;
        color: var(--medium-gray);
    }
    .chat-item-context {
        font-size: 0.78rem;
        font-weight: 500;
        color: var(--primary-blue);
        margin-bottom: 0.15rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .chat-message-preview {
        font-size: 0.82rem;
        color: #6b7280;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin: 0;
    }
    .chat-unread-dot {
        background: var(--primary-blue);
        color: #fff;
        font-size: 0.7rem;
        font-weight: 700;
        border-radius: 10px;
        padding: 0.15rem 0.45rem;
        flex-shrink: 0;
    }
    .chat-empty-list {
        padding: 3rem 1.5rem;
        text-align: center;
        color: var(--medium-gray);
    }
    .chat-empty-list i {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
        opacity: 0.4;
    }

    /* Main Chat Panel */
    .chat-window {
        display: flex;
        flex-direction: column;
        background: #f8fafc;
        height: 100%;
        position: relative;
    }
    .chat-window-empty {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        height: 100%;
        padding: 3rem;
        background: #f8fafc;
        color: var(--medium-gray);
    }
    .chat-window-empty i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        color: #cbd5e1;
    }
    .chat-window-empty h4 {
        color: var(--dark-charcoal);
        margin-bottom: 0.5rem;
    }

    /* Active Chat Header */
    .chat-header {
        background: #fff;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-gray);
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.01);
    }
    .chat-header-user {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .chat-header-user h4 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--dark-charcoal);
    }
    .chat-header-user p {
        margin: 0;
        font-size: 0.8rem;
        color: var(--medium-gray);
    }
    .chat-header-item-card {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #f1f5f9;
        padding: 0.4rem 0.8rem;
        border-radius: 8px;
        max-width: 320px;
        border: 1px solid #e2e8f0;
    }
    .chat-header-item-pic {
        width: 36px;
        height: 36px;
        border-radius: 4px;
        object-fit: cover;
    }
    .chat-header-item-info {
        min-width: 0;
    }
    .chat-header-item-title {
        margin: 0;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--dark-charcoal);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .chat-header-item-type {
        margin: 0;
        font-size: 0.75rem;
    }

    /* Chat Messages Stream Area */
    .chat-history {
        flex: 1;
        padding: 1.5rem;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .message-row {
        display: flex;
        gap: 0.75rem;
        max-width: 75%;
    }
    .message-row.incoming {
        align-self: flex-start;
    }
    .message-row.outgoing {
        align-self: flex-end;
        flex-direction: row-reverse;
    }
    .bubble-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        align-self: flex-end;
        border: 1px solid #cbd5e1;
    }
    .message-bubble {
        padding: 0.75rem 1rem;
        border-radius: 16px;
        font-size: 0.92rem;
        position: relative;
        line-height: 1.5;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .incoming .message-bubble {
        background: #ffffff;
        color: var(--dark-charcoal);
        border-bottom-left-radius: 4px;
        border: 1px solid #e2e8f0;
    }
    .outgoing .message-bubble {
        background: var(--primary-blue);
        color: #ffffff;
        border-bottom-right-radius: 4px;
    }
    .message-meta {
        font-size: 0.72rem;
        margin-top: 0.25rem;
        text-align: right;
        display: block;
    }
    .incoming .message-meta {
        color: var(--medium-gray);
    }
    .outgoing .message-meta {
        color: rgba(255, 255, 255, 0.75);
    }

    /* Chat Input Form Panel */
    .chat-input-panel {
        background: #ffffff;
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border-gray);
    }
    .chat-form {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    .chat-textarea {
        flex: 1;
        border: 1px solid var(--border-gray);
        border-radius: 24px;
        padding: 0.75rem 1.25rem;
        font-size: 0.9rem;
        resize: none;
        outline: none;
        transition: border 0.2s ease;
        background: #f8fafc;
        height: 44px;
        line-height: 20px;
    }
    .chat-textarea:focus {
        border-color: var(--primary-blue);
        background: #fff;
    }
    .chat-send-btn {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: var(--primary-blue);
        color: #fff;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s ease;
        flex-shrink: 0;
    }
    .chat-send-btn:hover {
        background: var(--primary-blue-hover);
    }
    
    @media (max-width: 768px) {
        .chat-container {
            grid-template-columns: 1fr;
            height: 600px;
        }
        .chat-sidebar {
            display: <?php echo ($active_item_id > 0) ? 'none' : 'flex'; ?>;
        }
        .chat-window {
            display: <?php echo ($active_item_id > 0) ? 'flex' : 'none'; ?>;
        }
        .back-to-list-btn {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            padding: 0.5rem;
            margin-right: 0.5rem;
            color: var(--primary-blue);
            font-size: 1.1rem;
        }
    }
    .back-to-list-btn {
        display: none;
    }
</style>

<div class="dashboard-layout">
    
    <!-- Sidebar Panel -->
    <aside class="dash-sidebar">
        <div class="sidebar-profile">
            <!-- Profile Avatar with fallback check -->
            <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($user['avatar'] ?? 'default_avatar.png'); ?>" alt="Avatar" class="sidebar-avatar" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
            <p><?php echo htmlspecialchars($user['id_card']); ?></p>
            <span class="badge-role mt-1"><?php echo ucfirst($user['role']); ?></span>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="sidebar-link"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="profile.php" class="sidebar-link"><i class="fas fa-user-circle"></i> Profile Settings</a>
            <a href="my-items.php" class="sidebar-link"><i class="fas fa-list-ul"></i> My Reports</a>
            <a href="add-item.php" class="sidebar-link"><i class="fas fa-plus-circle"></i> Post New Item</a>
            <a href="claims.php" class="sidebar-link"><i class="fas fa-gavel"></i> Manage Claims <?php echo $claims_count > 0 ? "<span class='text-green'>($claims_count)</span>" : ""; ?></a>
            <a href="messages.php" class="sidebar-link active"><i class="fas fa-envelope"></i> Message Box <?php echo $unread_msg_count > 0 ? "<span class='text-blue'>($unread_msg_count)</span>" : ""; ?></a>
        </nav>
    </aside>

    <!-- Main Chat Workspace -->
    <main class="dash-main" style="max-width: 100%; padding: 0;">
        
        <div class="chat-container">
            
            <!-- Left Panel: Conversation List -->
            <div class="chat-sidebar" id="sidebarPanel">
                <div class="chat-sidebar-header">
                    <h4><i class="far fa-comments text-blue"></i> Messages</h4>
                    <div class="chat-search-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="chatSearchInput" onkeyup="filterChats()" placeholder="Search conversations...">
                    </div>
                </div>
                
                <div class="chat-list" id="chatsListContainer">
                    <?php if (count($conversations) > 0): ?>
                        <?php foreach ($conversations as $conv): ?>
                            <?php 
                            $conv_active = ($conv['item_id'] === $active_item_id && $conv['other_id'] === $active_other_id);
                            ?>
                            <div class="chat-list-item <?php echo $conv_active ? 'active' : ''; ?>" 
                                 onclick="location.href='messages.php?chat_user_id=<?php echo $conv['other_id']; ?>&item_id=<?php echo $conv['item_id']; ?>'">
                                
                                <div class="chat-avatar-wrapper">
                                    <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($conv['other_pic']); ?>" alt="User Avatar" class="chat-avatar" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
                                    <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($conv['item_pic']); ?>" alt="Item Pic" class="chat-avatar-badge" onerror="this.src='https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&q=80&w=50'">
                                </div>
                                
                                <div class="chat-info">
                                    <div class="chat-info-top">
                                        <h5><?php echo htmlspecialchars($conv['other_name']); ?></h5>
                                        <span class="chat-time"><?php echo date('H:i', strtotime($conv['last_message_time'])); ?></span>
                                    </div>
                                    <div class="chat-item-context">
                                        <?php if ($conv['item_type'] === 'lost'): ?>
                                            <span class="text-red">[Lost]</span>
                                        <?php else: ?>
                                            <span class="text-green">[Found]</span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($conv['item_title']); ?>
                                    </div>
                                    <p class="chat-message-preview"><?php echo htmlspecialchars($conv['last_message']); ?></p>
                                </div>
                                
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="chat-unread-dot"><?php echo $conv['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="chat-empty-list">
                            <i class="far fa-envelope-open"></i>
                            <p>No messages yet.</p>
                            <p style="font-size: 0.8rem; margin-top: 0.5rem; color: var(--medium-gray);">When you message a poster on an item details page, the chat thread will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Panel: Conversation Stream Window -->
            <div class="chat-window" id="windowPanel">
                <?php if ($active_conv): ?>
                    
                    <!-- Chat Window Header -->
                    <div class="chat-header">
                        <div class="chat-header-user">
                            <a href="messages.php" class="back-to-list-btn" title="Back to list"><i class="fas fa-arrow-left"></i></a>
                            <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($active_conv['other_pic']); ?>" alt="Pic" class="table-thumbnail" style="border-radius: 50%;" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
                            <div>
                                <h4><?php echo htmlspecialchars($active_conv['other_name']); ?></h4>
                                <p><?php echo ucfirst($active_conv['other_role']); ?></p>
                            </div>
                        </div>
                        
                        <!-- Mini Item Context Card -->
                        <a href="<?php echo $base_path; ?>item.php?id=<?php echo $active_conv['item_id']; ?>" class="chat-header-item-card" title="Click to view full Item details">
                            <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($active_conv['item_pic']); ?>" alt="Item" class="chat-header-item-pic" onerror="this.src='https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&q=80&w=100'">
                            <div class="chat-header-item-info">
                                <p class="chat-header-item-title"><?php echo htmlspecialchars($active_conv['item_title']); ?></p>
                                <p class="chat-header-item-type">
                                    <?php if ($active_conv['item_type'] === 'lost'): ?>
                                        <span class="text-red"><i class="fas fa-search-plus"></i> Lost Item</span>
                                    <?php else: ?>
                                        <span class="text-green"><i class="fas fa-hand-holding-heart"></i> Found Item</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </a>
                    </div>
                    
                    <!-- Chat History Feed -->
                    <div class="chat-history" id="chatHistoryContainer">
                        <?php foreach ($active_messages as $m): ?>
                            <?php 
                            $is_mine = ((int)$m['sender_id'] === $user_id);
                            ?>
                            <div class="message-row <?php echo $is_mine ? 'outgoing' : 'incoming'; ?>">
                                <img src="<?php echo $base_path; ?>assets/uploads/<?php echo htmlspecialchars($m['sender_pic']); ?>" alt="S" class="bubble-avatar" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
                                <div class="message-bubble">
                                    <div style="word-break: break-word;"><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
                                    <span class="message-meta"><?php echo date('M d, H:i', strtotime($m['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Chat Message Input Box -->
                    <div class="chat-input-panel">
                        <form action="messages.php?chat_user_id=<?php echo $active_other_id; ?>&item_id=<?php echo $active_item_id; ?>" method="POST" class="chat-form">
                            <input type="hidden" name="send_reply" value="1">
                            <input type="hidden" name="item_id" value="<?php echo $active_item_id; ?>">
                            <input type="hidden" name="receiver_id" value="<?php echo $active_other_id; ?>">
                            
                            <input type="text" name="reply_message" id="reply_message" class="chat-textarea" placeholder="Write a message..." required autocomplete="off">
                            <button type="submit" class="chat-send-btn" title="Send message"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                    
                <?php else: ?>
                    
                    <!-- Empty Chat Window State -->
                    <div class="chat-window-empty">
                        <i class="far fa-comments"></i>
                        <h4>Chat Center</h4>
                        <p style="max-width: 400px; line-height: 1.5;">Select a chat from the conversation list on the left to read messages and reply. To start a new conversation, browse items and click 'Send Direct Message'.</p>
                    </div>
                    
                <?php endif; ?>
            </div>
            
        </div>

    </main>
</div>

<!-- Dynamic scrolling and search filtering scripts -->
<script>
    // 1. Auto scrolls chat window history box to the very bottom
    window.addEventListener('DOMContentLoaded', (event) => {
        const chatFeed = document.getElementById('chatHistoryContainer');
        if (chatFeed) {
            chatFeed.scrollTop = chatFeed.scrollHeight;
        }
    });

    // 2. Local javascript search filtering for active conversation entries
    function filterChats() {
        const input = document.getElementById('chatSearchInput');
        const filter = input.value.toLowerCase();
        const chatList = document.getElementById('chatsListContainer');
        const items = chatList.getElementsByClassName('chat-list-item');

        for (let i = 0; i < items.length; i++) {
            const h5 = items[i].getElementsByTagName('h5')[0];
            const context = items[i].getElementsByClassName('chat-item-context')[0];
            const preview = items[i].getElementsByClassName('chat-message-preview')[0];
            
            const nameText = h5 ? h5.textContent || h5.innerText : "";
            const contextText = context ? context.textContent || context.innerText : "";
            const previewText = preview ? preview.textContent || preview.innerText : "";
            
            if (
                nameText.toLowerCase().indexOf(filter) > -1 || 
                contextText.toLowerCase().indexOf(filter) > -1 ||
                previewText.toLowerCase().indexOf(filter) > -1
            ) {
                items[i].style.display = "";
            } else {
                items[i].style.display = "none";
            }
        }
    }
</script>

<?php
mysqli_close($conn);
include_once '../includes/footer.php';
?>
