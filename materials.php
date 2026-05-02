<?php
session_start();
include 'includes/db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];
$is_alumni = isset($_SESSION['is_graduated']) && $_SESSION['is_graduated'] == 1;

// Get user's enrolled courses (or previously enrolled for alumni)
if($is_alumni) {
    // Alumni can see courses from their graduation year
    $courses_query = "SELECT DISTINCT c.* FROM courses c
                      JOIN enrollments e ON c.id = e.course_id
                      WHERE e.student_id = $user_id
                      ORDER BY c.course_code";
} else {
    $courses_query = "SELECT DISTINCT c.* FROM courses c
                      JOIN enrollments e ON c.id = e.course_id
                      WHERE e.student_id = $user_id
                      ORDER BY c.course_code";
}
$courses = mysqli_query($conn, $courses_query);

$selected_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
$materials = null;

if($selected_course) {
    $materials_query = "SELECT cm.*, u.name as uploaded_by_name 
                        FROM course_materials cm
                        JOIN users u ON cm.uploaded_by = u.id
                        WHERE cm.course_id = $selected_course
                        ORDER BY cm.created_at DESC";
    $materials = mysqli_query($conn, $materials_query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Materials - FoC Connect</title>
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
        .alumni-banner { background: linear-gradient(135deg, #D69E2E, #8B4513); color: white; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .course-selector { background: white; border-radius: 16px; padding: 20px; margin-bottom: 24px; border: 1px solid #E8EDEC; }
        .course-buttons { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; }
        .course-btn { background: #F5F7F6; border: 1px solid #E8EDEC; padding: 10px 20px; border-radius: 40px; cursor: pointer; text-decoration: none; color: #1A2E28; transition: all 0.2s; }
        .course-btn:hover, .course-btn.active { background: #2E7D64; color: white; border-color: #2E7D64; }
        .materials-list { background: white; border-radius: 16px; border: 1px solid #E8EDEC; overflow: hidden; }
        .material-item { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #E8EDEC; }
        .material-item:last-child { border-bottom: none; }
        .material-info h3 { font-size: 16px; color: #1A2E28; margin-bottom: 4px; }
        .material-info p { font-size: 13px; color: #6B7E78; margin-bottom: 4px; }
        .material-meta { font-size: 11px; color: #8A9B97; }
        .view-btn { background: #2E7D64; color: white; padding: 8px 16px; border-radius: 40px; text-decoration: none; font-size: 13px; border: none; cursor: pointer; display: inline-block; }
        .view-only-badge { background: #D69E2E; color: #1A202C; padding: 4px 12px; border-radius: 20px; font-size: 11px; margin-left: 12px; }
        .empty-state { text-align: center; padding: 40px; color: #8A9B97; }
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
            <a href="materials.php" class="nav-item active"><span class="nav-icon">📚</span><span class="nav-text">Course Materials</span></a>
            <a href="assignments.php" class="nav-item"><span class="nav-icon">📝</span><span class="nav-text">Assignments</span></a>
            <a href="alumni-directory.php" class="nav-item"><span class="nav-icon">🎓</span><span class="nav-text">Alumni Directory</span></a>
            <a href="job-board.php" class="nav-item"><span class="nav-icon">💼</span><span class="nav-text">Job Board</span></a>
            <a href="mentorship.php" class="nav-item"><span class="nav-icon">🤝</span><span class="nav-text">Mentorship</span></a>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="page-title">📚 Course Materials</div>
            <div class="profile-btn">
                <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                <div><?php echo htmlspecialchars($user_name); ?></div>
            </div>
        </header>

        <div class="dashboard-container">
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
            
            <?php if($is_alumni): ?>
            <div class="alumni-banner">
                <span style="font-size: 24px;">🎓</span>
                <div>
                    <strong>Alumni Access Mode</strong><br>
                    <small>You have read-only access to course materials. Downloads are disabled.</small>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="course-selector">
                <h3>Select a Course</h3>
                <div class="course-buttons">
                    <?php if(mysqli_num_rows($courses) > 0): ?>
                        <?php while($course = mysqli_fetch_assoc($courses)): ?>
                            <a href="materials.php?course_id=<?php echo $course['id']; ?>" 
                               class="course-btn <?php echo ($selected_course == $course['id']) ? 'active' : ''; ?>">
                                📖 <?php echo htmlspecialchars($course['course_code']); ?>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #8A9B97;">No courses available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($selected_course && $materials && mysqli_num_rows($materials) > 0): ?>
                <div class="materials-list">
                    <?php while($material = mysqli_fetch_assoc($materials)): ?>
                        <div class="material-item">
                            <div class="material-info">
                                <h3>📄 <?php echo htmlspecialchars($material['title']); ?>
                                    <?php if($is_alumni): ?>
                                        <span class="view-only-badge">👁️ View Only</span>
                                    <?php endif; ?>
                                </h3>
                                <p><?php echo htmlspecialchars($material['description']); ?></p>
                                <div class="material-meta">
                                    Uploaded by <?php echo htmlspecialchars($material['uploaded_by_name']); ?> • 
                                    <?php echo date('M d, Y', strtotime($material['created_at'])); ?>
                                    <?php if($material['file_type']): ?>
                                        • 📁 <?php echo strtoupper($material['file_type']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if($is_alumni): ?>
                                <button class="view-btn" onclick="alert('Alumni can view but not download. Contact the department for access.')">👁️ View Online</button>
                            <?php else: ?>
                                <button class="view-btn" onclick="alert('Download will be available soon. File: <?php echo $material['file_name']; ?>')">📥 Download</button>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php elseif($selected_course): ?>
                <div class="materials-list">
                    <div class="empty-state">📭 No materials uploaded for this course yet.</div>
                </div>
            <?php else: ?>
                <div class="materials-list">
                    <div class="empty-state">📚 Select a course above to view materials.</div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>