<?php
session_start();
include 'includes/db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$assignments = mysqli_query($conn, "SELECT a.*, c.course_code, c.course_name 
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = $user_id
    ORDER BY a.due_date ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - FoC Connect</title>
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
        .sidebar-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.1); position: absolute; bottom: 0; left: 0; right: 0; }
        .main-content { margin-left: 280px; min-height: 100vh; }
        .top-header { background: white; border-bottom: 1px solid #E2E8F0; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 20px; font-weight: 600; color: #1A2E28; }
        .profile-btn { display: flex; align-items: center; gap: 10px; background: #F5F7F6; padding: 6px 12px; border-radius: 40px; }
        .profile-avatar { width: 36px; height: 36px; background: #2E7D64; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .dashboard-container { padding: 24px; max-width: 1200px; margin: 0 auto; }
        .assignment-card { background: white; border-radius: 20px; padding: 24px; margin-bottom: 20px; border: 1px solid #E8EDEC; }
        .assignment-title { font-size: 18px; font-weight: 600; color: #1A2E28; margin-bottom: 8px; }
        .assignment-meta { font-size: 13px; color: #8A9B97; margin-bottom: 12px; display: flex; gap: 16px; flex-wrap: wrap; }
        .assignment-description { font-size: 15px; color: #4A5568; margin-bottom: 16px; }
        .due-badge { background: #E53E3E; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .submission-area { margin-top: 16px; padding-top: 16px; border-top: 1px solid #E8EDEC; }
        .file-input { display: none; }
        .upload-btn { background: #2E7D64; color: white; padding: 10px 20px; border-radius: 40px; cursor: pointer; display: inline-block; font-size: 14px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #2E7D64; text-decoration: none; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } .sidebar { transform: translateX(-100%); } }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">🌿 FoC Connect</div>
            <div class="sidebar-subtitle">🏛️ FACULTY OF COMPUTING</div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span><span class="nav-text">Dashboard</span></a>
            <a href="chat.php" class="nav-item"><span class="nav-icon">💬</span><span class="nav-text">Chat</span></a>
            <a href="announcements.php" class="nav-item"><span class="nav-icon">📢</span><span class="nav-text">Announcements</span></a>
            <a href="materials.php" class="nav-item"><span class="nav-icon">📚</span><span class="nav-text">Course Materials</span></a>
            <a href="assignments.php" class="nav-item active"><span class="nav-icon">📝</span><span class="nav-text">Assignments</span></a>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="page-title">📝 Assignments</div>
            <div class="profile-btn">
                <div class="profile-avatar"><?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?></div>
                <div><?php echo htmlspecialchars($_SESSION['name']); ?></div>
            </div>
        </header>

        <div class="dashboard-container">
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
            
            <?php if(mysqli_num_rows($assignments) > 0): ?>
                <?php while($assign = mysqli_fetch_assoc($assignments)): ?>
                    <div class="assignment-card">
                        <div class="assignment-title"><?php echo htmlspecialchars($assign['title']); ?></div>
                        <div class="assignment-meta">
                            <span>📚 <?php echo htmlspecialchars($assign['course_code']); ?> - <?php echo htmlspecialchars($assign['course_name']); ?></span>
                            <span>⭐ Max Score: <?php echo $assign['max_score']; ?></span>
                        </div>
                        <div class="assignment-description">
                            <?php echo nl2br(htmlspecialchars($assign['description'])); ?>
                        </div>
                        <div>
                            <span class="due-badge">Due: <?php echo date('F d, Y g:i A', strtotime($assign['due_date'])); ?></span>
                        </div>
                        <div class="submission-area">
                            <label class="upload-btn" for="file_<?php echo $assign['id']; ?>">📤 Submit Assignment</label>
                            <input type="file" id="file_<?php echo $assign['id']; ?>" class="file-input" onchange="alert('Submission feature coming soon! Assignment ID: <?php echo $assign['id']; ?>')">
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="assignment-card" style="text-align: center; color: #8A9B97;">
                    ✅ No pending assignments. Good job!
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>