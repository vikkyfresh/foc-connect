<?php
session_start();
include 'includes/db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

$announcements_query = "SELECT a.*, u.name as author_name 
                        FROM announcements a
                        JOIN users u ON a.from_user_id = u.id
                        ORDER BY a.sent_at DESC";
$announcements = mysqli_query($conn, $announcements_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Announcements - FoC Connect</title>
    <link rel="manifest" href="manifest.json">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #F5F7F6; }
        .sidebar { width: 280px; background: #0F4C3A; color: #E8F5E9; position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo { font-size: 24px; font-weight: 700; }
        .sidebar-subtitle { font-size: 11px; font-weight: bold; opacity: 0.8; margin-top: 6px; }
        .sidebar-nav { padding: 20px 16px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; margin: 4px 0; border-radius: 12px; color: #E8F5E9; text-decoration: none; transition: 0.2s; }
        .nav-item:hover, .nav-item.active { background: #2E7D64; }
        .nav-icon { font-size: 22px; width: 28px; }
        .nav-text { font-size: 15px; flex: 1; }
        .sidebar-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.1); margin-top: auto; }
        .main-content { margin-left: 280px; min-height: 100vh; width: calc(100% - 280px); }
        .top-header { background: white; border-bottom: 1px solid #E2E8F0; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 20px; font-weight: 600; color: #1A2E28; }
        .profile-btn { display: flex; align-items: center; gap: 10px; background: #F5F7F6; padding: 6px 12px; border-radius: 40px; }
        .profile-avatar { width: 36px; height: 36px; background: #2E7D64; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .dashboard-container { padding: 24px; max-width: 900px; margin: 0 auto; }
        .card { background: white; border-radius: 20px; padding: 24px; margin-bottom: 24px; border: 1px solid #E8EDEC; }
        .card-title { font-size: 18px; font-weight: 600; color: #1A2E28; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #E8EDEC; }
        .announcement-card { background: white; border-radius: 20px; padding: 24px; margin-bottom: 20px; border: 1px solid #E8EDEC; }
        .announcement-title { font-size: 18px; font-weight: 600; color: #1A2E28; margin-bottom: 8px; }
        .announcement-meta { font-size: 13px; color: #8A9B97; margin-bottom: 12px; display: flex; gap: 16px; flex-wrap: wrap; }
        .announcement-message { font-size: 15px; color: #4A5568; line-height: 1.5; }
        .emergency { border-left: 4px solid #E53E3E; background: #FFF5F5; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #2E7D64; text-decoration: none; }
        @media (max-width: 768px) { .main-content { margin-left: 0; width: 100%; } .sidebar { transform: translateX(-100%); } }
        .hamburger { display: none; background: none; border: none; font-size: 24px; cursor: pointer; margin-right: 12px; }
    </style>
</head>
<body>
<div style="display: flex; min-height: 100vh;">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">🌿 FoC Connect</div>
            <div class="sidebar-subtitle">🏛️ FACULTY OF COMPUTING</div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span><span class="nav-text">Dashboard</span></a>
            <a href="talk.php" target="_blank" class="nav-item"><span class="nav-icon">💬</span><span class="nav-text">Chat</span></a>
            <a href="announcements.php" class="nav-item active"><span class="nav-icon">📢</span><span class="nav-text">Announcements</span></a>
            <a href="materials.php" class="nav-item"><span class="nav-icon">📚</span><span class="nav-text">Course Materials</span></a>
            <a href="assignments.php" class="nav-item"><span class="nav-icon">📝</span><span class="nav-text">Assignments</span></a>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </div>

    <main class="main-content">
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="hamburger" onclick="toggleSidebar()">☰</button>
                <div class="page-title">📢 Announcements</div>
            </div>
            <div class="profile-btn">
                <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                <div><?php echo htmlspecialchars($user_name); ?></div>
            </div>
        </header>

        <div class="dashboard-container">
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
            
            <div class="card">
                <div class="card-title">📢 All Announcements</div>
                <?php if(mysqli_num_rows($announcements) > 0): ?>
                    <?php while($ann = mysqli_fetch_assoc($announcements)): ?>
                        <div class="announcement-card <?php echo $ann['is_emergency'] ? 'emergency' : ''; ?>">
                            <div class="announcement-title">
                                <?php echo htmlspecialchars($ann['title']); ?>
                                <?php if($ann['is_emergency']): ?> <span style="color:#E53E3E;">🔴 EMERGENCY</span><?php endif; ?>
                            </div>
                            <div class="announcement-meta">
                                <span>👤 By: <?php echo htmlspecialchars($ann['author_name']); ?></span>
                                <span>📅 Date: <?php echo date('F d, Y', strtotime($ann['sent_at'])); ?></span>
                                <span>🕐 Time: <?php echo date('g:i A', strtotime($ann['sent_at'])); ?></span>
                            </div>
                            <div class="announcement-message">
                                <?php echo nl2br(htmlspecialchars($ann['message'])); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">📭 No announcements yet</div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }
    if(window.innerWidth <= 768) {
        document.querySelector('.hamburger').style.display = 'block';
    }
</script>
</body>
</html>
