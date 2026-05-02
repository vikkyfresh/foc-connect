<?php
session_start();
include 'includes/db.php';

// Check if user is logged in and has moderation权限
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['dean', 'hod', 'admin', 'lecturer', 'level_coordinator'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];
$user_dept_id = null;

// Get user's department if HOD or Lecturer
if(in_array($user_role, ['hod', 'lecturer', 'level_coordinator'])) {
    $dept_query = "SELECT department_id FROM users WHERE id = $user_id";
    $dept_result = mysqli_query($conn, $dept_query);
    $user_dept = mysqli_fetch_assoc($dept_result);
    $user_dept_id = $user_dept['department_id'];
}

$message = '';
$error = '';

// Handle mute user
if(isset($_GET['mute']) && isset($_GET['user_id'])) {
    $target_id = intval($_GET['user_id']);
    $duration = isset($_GET['duration']) ? intval($_GET['duration']) : 24;
    $reason = isset($_GET['reason']) ? mysqli_real_escape_string($conn, $_GET['reason']) : 'Violation of group rules';
    
    $expires = date('Y-m-d H:i:s', strtotime("+$duration hours"));
    $insert = "INSERT INTO muted_users (user_id, muted_by, reason, expires_at) VALUES ($target_id, $user_id, '$reason', '$expires')";
    mysqli_query($conn, $insert);
    
    mysqli_query($conn, "INSERT INTO moderation_logs (moderator_id, action, target_user_id, reason) VALUES ($user_id, 'mute', $target_id, '$reason')");
    $message = "✅ User muted for $duration hours";
}

// Handle unmute user
if(isset($_GET['unmute']) && isset($_GET['user_id'])) {
    $target_id = intval($_GET['user_id']);
    mysqli_query($conn, "DELETE FROM muted_users WHERE user_id = $target_id");
    mysqli_query($conn, "INSERT INTO moderation_logs (moderator_id, action, target_user_id, reason) VALUES ($user_id, 'unmute', $target_id, 'Manually unmuted')");
    $message = "✅ User unmuted";
}

// Handle warn user
if(isset($_GET['warn']) && isset($_GET['user_id'])) {
    $target_id = intval($_GET['user_id']);
    $reason = isset($_GET['reason']) ? mysqli_real_escape_string($conn, $_GET['reason']) : 'Warning issued';
    
    // Get current warning level
    $warn_query = "SELECT warning_level FROM user_warnings WHERE user_id = $target_id ORDER BY id DESC LIMIT 1";
    $warn_result = mysqli_query($conn, $warn_query);
    $current_level = mysqli_num_rows($warn_result) > 0 ? mysqli_fetch_assoc($warn_result)['warning_level'] : 0;
    $new_level = $current_level + 1;
    
    $expires = $new_level >= 3 ? date('Y-m-d H:i:s', strtotime('+30 days')) : null;
    $insert = "INSERT INTO user_warnings (user_id, warned_by, warning_level, reason, expires_at) VALUES ($target_id, $user_id, $new_level, '$reason', " . ($expires ? "'$expires'" : "NULL") . ")";
    mysqli_query($conn, $insert);
    
    mysqli_query($conn, "INSERT INTO moderation_logs (moderator_id, action, target_user_id, reason) VALUES ($user_id, 'warn', $target_id, 'Warning Level $new_level: $reason')");
    $message = "⚠️ User warned (Level $new_level)";
}

// Handle ban user
if(isset($_GET['ban']) && isset($_GET['user_id'])) {
    $target_id = intval($_GET['user_id']);
    $duration = isset($_GET['duration']) ? intval($_GET['duration']) : 0;
    $reason = isset($_GET['reason']) ? mysqli_real_escape_string($conn, $_GET['reason']) : 'Banned for violation';
    
    $expires = $duration > 0 ? date('Y-m-d H:i:s', strtotime("+$duration days")) : null;
    $insert = "INSERT INTO banned_users (user_id, banned_by, reason, expires_at) VALUES ($target_id, $user_id, '$reason', " . ($expires ? "'$expires'" : "NULL") . ")";
    mysqli_query($conn, $insert);
    
    mysqli_query($conn, "UPDATE users SET is_active = 0 WHERE id = $target_id");
    mysqli_query($conn, "INSERT INTO moderation_logs (moderator_id, action, target_user_id, reason) VALUES ($user_id, 'ban', $target_id, '$reason')");
    $message = "🚫 User banned";
}

// Handle unban user
if(isset($_GET['unban']) && isset($_GET['user_id'])) {
    $target_id = intval($_GET['user_id']);
    mysqli_query($conn, "DELETE FROM banned_users WHERE user_id = $target_id");
    mysqli_query($conn, "UPDATE users SET is_active = 1 WHERE id = $target_id");
    mysqli_query($conn, "INSERT INTO moderation_logs (moderator_id, action, target_user_id, reason) VALUES ($user_id, 'unban', $target_id, 'Manually unbanned')");
    $message = "✅ User unbanned";
}

// Handle delete message
if(isset($_GET['delete_msg']) && isset($_GET['msg_id'])) {
    $msg_id = intval($_GET['msg_id']);
    mysqli_query($conn, "UPDATE messages SET is_deleted_by_moderator = 1, deleted_by = $user_id, deleted_at = NOW() WHERE id = $msg_id");
    mysqli_query($conn, "INSERT INTO moderation_logs (moderator_id, action, target_message_id, reason) VALUES ($user_id, 'delete_message', $msg_id, 'Message deleted by moderator')");
    $message = "✅ Message deleted";
}

// Get reported messages
$dept_condition = ($user_dept_id && in_array($user_role, ['hod', 'lecturer', 'level_coordinator'])) ? "AND u.department_id = $user_dept_id" : "";
$reported_messages = mysqli_query($conn, "SELECT m.*, u.name as sender_name, u.matric_number, u.department_id,
                                          (SELECT COUNT(*) FROM moderation_logs WHERE target_message_id = m.id AND action = 'report') as report_count
                                          FROM messages m
                                          JOIN users u ON m.from_user_id = u.id
                                          WHERE m.report_count > 0 AND m.is_deleted_by_moderator = 0 $dept_condition
                                          ORDER BY m.report_count DESC, m.sent_at DESC LIMIT 50");

// Get warnings
$warnings = mysqli_query($conn, "SELECT w.*, u.name as user_name, u.matric_number, a.name as warned_by_name
                                 FROM user_warnings w
                                 JOIN users u ON w.user_id = u.id
                                 JOIN users a ON w.warned_by = a.id
                                 WHERE w.expires_at IS NULL OR w.expires_at > NOW()
                                 ORDER BY w.created_at DESC LIMIT 30");

// Get muted users
$muted_users = mysqli_query($conn, "SELECT mu.*, u.name, u.matric_number, a.name as muted_by_name
                                    FROM muted_users mu
                                    JOIN users u ON mu.user_id = u.id
                                    JOIN users a ON mu.muted_by = a.id
                                    WHERE mu.expires_at > NOW()
                                    ORDER BY mu.expires_at ASC");

// Get banned users
$banned_users = mysqli_query($conn, "SELECT bu.*, u.name, u.matric_number, a.name as banned_by_name
                                     FROM banned_users bu
                                     JOIN users u ON bu.user_id = u.id
                                     JOIN users a ON bu.banned_by = a.id
                                     WHERE bu.expires_at IS NULL OR bu.expires_at > NOW()
                                     ORDER BY bu.banned_at DESC");

// Get moderation logs
$logs = mysqli_query($conn, "SELECT l.*, a.name as moderator_name, 
                             CASE 
                                WHEN l.target_user_id THEN u.name 
                                ELSE NULL 
                             END as target_name
                             FROM moderation_logs l
                             LEFT JOIN users a ON l.moderator_id = a.id
                             LEFT JOIN users u ON l.target_user_id = u.id
                             ORDER BY l.created_at DESC LIMIT 50");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderation Panel - FoC Connect</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #F5F7F6; }
        
        .sidebar { width: 280px; background: #0F4C3A; color: #E8F5E9; position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo { font-size: 24px; font-weight: 700; }
        .sidebar-subtitle { font-size: 11px; font-weight: bold; opacity: 0.8; margin-top: 6px; }
        .sidebar-nav { padding: 20px 16px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; margin: 4px 0; border-radius: 12px; color: #E8F5E9; text-decoration: none; transition: background 0.2s; }
        .nav-item:hover, .nav-item.active { background: #2E7D64; }
        .nav-icon { font-size: 22px; width: 28px; }
        .nav-text { font-size: 15px; flex: 1; }
        .sidebar-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.1); margin-top: auto; }
        
        .main-content { margin-left: 280px; min-height: 100vh; }
        .top-header { background: white; border-bottom: 1px solid #E2E8F0; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 20px; font-weight: 600; color: #1A2E28; }
        .profile-btn { display: flex; align-items: center; gap: 10px; background: #F5F7F6; padding: 6px 12px; border-radius: 40px; }
        .profile-avatar { width: 36px; height: 36px; background: #2E7D64; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        
        .dashboard-container { padding: 24px; max-width: 1400px; margin: 0 auto; }
        .card { background: white; border-radius: 20px; padding: 24px; margin-bottom: 24px; border: 1px solid #E8EDEC; }
        .card-title { font-size: 18px; font-weight: 600; color: #1A2E28; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #E8EDEC; display: flex; justify-content: space-between; align-items: center; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; text-align: center; border: 1px solid #E8EDEC; }
        .stat-number { font-size: 32px; font-weight: 700; color: #2E7D64; }
        .stat-label { font-size: 14px; color: #6B7E78; margin-top: 8px; }
        
        .message-item { padding: 16px; border-bottom: 1px solid #E8EDEC; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .message-text { flex: 1; }
        .message-sender { font-weight: 600; color: #1A2E28; }
        .message-content { font-size: 14px; color: #4A5568; margin-top: 4px; }
        .message-meta { font-size: 11px; color: #8A9B97; margin-top: 4px; }
        .report-badge { background: #E53E3E; color: white; padding: 2px 8px; border-radius: 20px; font-size: 11px; }
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn { padding: 6px 12px; border-radius: 40px; border: none; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
        .btn-warn { background: #D69E2E; color: white; }
        .btn-mute { background: #3182CE; color: white; }
        .btn-ban { background: #E53E3E; color: white; }
        .btn-delete { background: #8A9B97; color: white; }
        .btn-sm { padding: 4px 8px; font-size: 11px; }
        
        .user-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #E8EDEC; flex-wrap: wrap; gap: 12px; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .grid-2 { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .main-content { margin-left: 0; } .sidebar { transform: translateX(-100%); } }
        
        .back-link { display: inline-block; margin-bottom: 20px; color: #2E7D64; text-decoration: none; }
        .badge { background: #E8F5E9; color: #2E7D64; padding: 2px 8px; border-radius: 20px; font-size: 11px; }
    </style>
</head>
<body>
<div style="display: flex; min-height: 100vh;">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">🌿 FoC Connect</div>
            <div class="sidebar-subtitle">🏛️ FACULTY OF COMPUTING</div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span><span class="nav-text">Dashboard</span></a>
            <a href="chat.php" class="nav-item"><span class="nav-icon">💬</span><span class="nav-text">Chat</span></a>
            <a href="moderation-panel.php" class="nav-item active"><span class="nav-icon">🛡️</span><span class="nav-text">Moderation</span></a>
            <a href="admin-roles.php" class="nav-item"><span class="nav-icon">👥</span><span class="nav-text">Role Management</span></a>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="page-title">🛡️ Moderation Panel</div>
            <div class="profile-btn">
                <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                <div><?php echo htmlspecialchars($user_name); ?> (<?php echo ucfirst($user_role); ?>)</div>
            </div>
        </header>

        <div class="dashboard-container">
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
            
            <?php if($message): ?>
                <div class="card" style="background: #E8F5E9; color: #2E7D64;"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-number"><?php echo mysqli_num_rows($reported_messages); ?></div><div class="stat-label">Reported Messages</div></div>
                <div class="stat-card"><div class="stat-number"><?php echo mysqli_num_rows($warnings); ?></div><div class="stat-label">Active Warnings</div></div>
                <div class="stat-card"><div class="stat-number"><?php echo mysqli_num_rows($muted_users); ?></div><div class="stat-label">Muted Users</div></div>
                <div class="stat-card"><div class="stat-number"><?php echo mysqli_num_rows($banned_users); ?></div><div class="stat-label">Banned Users</div></div>
            </div>
            
            <!-- Reported Messages -->
            <div class="card">
                <div class="card-title">🚨 Reported Messages</div>
                <?php if(mysqli_num_rows($reported_messages) > 0): ?>
                    <?php while($msg = mysqli_fetch_assoc($reported_messages)): ?>
                        <div class="message-item">
                            <div class="message-text">
                                <div class="message-sender"><?php echo htmlspecialchars($msg['sender_name']); ?> (<?php echo $msg['matric_number']; ?>)</div>
                                <div class="message-content"><?php echo htmlspecialchars(substr($msg['message'], 0, 150)); ?></div>
                                <div class="message-meta">📅 <?php echo date('M d, Y g:i A', strtotime($msg['sent_at'])); ?> • 🚨 <?php echo $msg['report_count']; ?> reports</div>
                            </div>
                            <div class="action-buttons">
                                <a href="?warn=1&user_id=<?php echo $msg['from_user_id']; ?>&reason=Inappropriate message" class="btn btn-warn btn-sm" onclick="return confirm('Warn this user?')">⚠️ Warn</a>
                                <a href="?mute=1&user_id=<?php echo $msg['from_user_id']; ?>&duration=24" class="btn btn-mute btn-sm" onclick="return confirm('Mute this user for 24 hours?')">🔇 Mute</a>
                                <a href="?delete_msg=1&msg_id=<?php echo $msg['id']; ?>" class="btn btn-delete btn-sm" onclick="return confirm('Delete this message?')">🗑️ Delete</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #8A9B97;">✅ No reported messages</div>
                <?php endif; ?>
            </div>
            
            <div class="grid-2">
                <!-- Active Warnings -->
                <div class="card">
                    <div class="card-title">⚠️ Active Warnings</div>
                    <?php if(mysqli_num_rows($warnings) > 0): ?>
                        <?php while($warn = mysqli_fetch_assoc($warnings)): ?>
                            <div class="user-item">
                                <div><strong><?php echo htmlspecialchars($warn['user_name']); ?></strong><br><small><?php echo $warn['matric_number']; ?></small></div>
                                <div><span class="badge">Level <?php echo $warn['warning_level']; ?></span><br><small>By: <?php echo $warn['warned_by_name']; ?></small></div>
                                <div><a href="?unmute=1&user_id=<?php echo $warn['user_id']; ?>" class="btn btn-sm" style="background:#2E7D64; color:white;" onclick="return confirm('Clear warning?')">Clear</a></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #8A9B97;">No active warnings</div>
                    <?php endif; ?>
                </div>
                
                <!-- Muted Users -->
                <div class="card">
                    <div class="card-title">🔇 Muted Users</div>
                    <?php if(mysqli_num_rows($muted_users) > 0): ?>
                        <?php while($muted = mysqli_fetch_assoc($muted_users)): ?>
                            <div class="user-item">
                                <div><strong><?php echo htmlspecialchars($muted['name']); ?></strong><br><small><?php echo $muted['matric_number']; ?></small></div>
                                <div><small>Expires: <?php echo date('M d, H:i', strtotime($muted['expires_at'])); ?></small></div>
                                <div><a href="?unmute=1&user_id=<?php echo $muted['user_id']; ?>" class="btn btn-sm" style="background:#2E7D64; color:white;" onclick="return confirm('Unmute this user?')">Unmute</a></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #8A9B97;">No muted users</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="grid-2">
                <!-- Banned Users -->
                <div class="card">
                    <div class="card-title">🚫 Banned Users</div>
                    <?php if(mysqli_num_rows($banned_users) > 0): ?>
                        <?php while($banned = mysqli_fetch_assoc($banned_users)): ?>
                            <div class="user-item">
                                <div><strong><?php echo htmlspecialchars($banned['name']); ?></strong><br><small><?php echo $banned['matric_number']; ?></small></div>
                                <div><small><?php echo $banned['expires_at'] ? 'Until: ' . date('M d, Y', strtotime($banned['expires_at'])) : 'Permanent'; ?></small></div>
                                <div><a href="?unban=1&user_id=<?php echo $banned['user_id']; ?>" class="btn btn-sm" style="background:#2E7D64; color:white;" onclick="return confirm('Unban this user?')">Unban</a></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #8A9B97;">No banned users</div>
                    <?php endif; ?>
                </div>
                
                <!-- Moderation Logs -->
                <div class="card">
                    <div class="card-title">📜 Moderation Logs</div>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php if(mysqli_num_rows($logs) > 0): ?>
                            <?php while($log = mysqli_fetch_assoc($logs)): ?>
                                <div class="user-item" style="padding: 8px 0;">
                                    <div><span class="badge"><?php echo ucfirst($log['action']); ?></span><br><small><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></small></div>
                                    <div><small>By: <?php echo htmlspecialchars($log['moderator_name']); ?><br>Target: <?php echo htmlspecialchars($log['target_name'] ?? 'Message'); ?></small></div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 20px; color: #8A9B97;">No moderation logs</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>