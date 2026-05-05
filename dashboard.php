<?php
session_start();
include 'includes/db.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];

// Get user data with profile info
$user_query = "SELECT u.*, d.name as dept_name, d.code as dept_code, 
               a.level_name, d.connect_name
               FROM users u 
               LEFT JOIN departments d ON u.department_id = d.id 
               LEFT JOIN academic_levels a ON u.level_id = a.id
               WHERE u.id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

// Student Leadership Role Mapping
$student_role_map = [
    'class_rep' => ['🟡 Class Representative', 'class-rep'],
    'financial_secretary' => ['💰 Financial Secretary', 'financial-sec'],
    'pro' => ['📢 Public Relations Officer (PRO)', 'pro'],
    'general_secretary' => ['📝 General Secretary', 'gen-sec'],
    'vice_president' => ['👑 Vice President', 'vp'],
    'departmental_president' => ['👔 Departmental President', 'dept-pres']
];

// Staff Role Mapping
$staff_role_map = [
    'lecturer' => ['👨‍🏫 Lecturer', 'lecturer'],
    'level_coordinator' => ['📚 Level Coordinator', 'level-coord'],
    'dept_exam_officer' => ['📋 Departmental Exam Officer', 'dept-exam'],
    'hod' => ['👔 Head of Department', 'hod'],
    'faculty_exam_officer' => ['📋 Faculty Exam Officer', 'faculty-exam'],
    'dean' => ['🎓 Dean', 'dean']
];

// Get role display
$role_display = 'Student';
$role_class = 'student';

if($user_role == 'student' && isset($user['student_role']) && $user['student_role'] != 'student' && isset($student_role_map[$user['student_role']])) {
    $role_display = $student_role_map[$user['student_role']][0];
    $role_class = $student_role_map[$user['student_role']][1];
} elseif($user_role != 'student' && isset($staff_role_map[$user_role])) {
    $role_display = $staff_role_map[$user_role][0];
    $role_class = $staff_role_map[$user_role][1];
} elseif($user_role == 'student') {
    $role_display = '👤 Student';
    $role_class = 'student';
}

// Get unread notifications count
$notif_query = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = $user_id AND is_read = 0";
$notif_result = mysqli_query($conn, $notif_query);
$unread_count = mysqli_fetch_assoc($notif_result)['unread'];

// Get announcements
$dept_id = isset($user['department_id']) && $user['department_id'] > 0 ? $user['department_id'] : 0;
$level_id = isset($user['level_id']) && $user['level_id'] > 0 ? $user['level_id'] : 0;

$announcements_query = "SELECT a.*, u.name as author_name 
                        FROM announcements a
                        JOIN users u ON a.from_user_id = u.id
                        WHERE (a.department_id IS NULL OR a.department_id = $dept_id)
                        AND (a.level_id IS NULL OR a.level_id = $level_id)
                        ORDER BY a.sent_at DESC LIMIT 5";
$announcements = mysqli_query($conn, $announcements_query);

// Get enrolled courses
$courses_query = "SELECT c.*, e.enrolled_at 
                  FROM courses c
                  JOIN enrollments e ON c.id = e.course_id
                  WHERE e.student_id = $user_id
                  LIMIT 5";
$courses = mysqli_query($conn, $courses_query);
$has_courses = mysqli_num_rows($courses) > 0;

// Get pending assignments
$assignments_query = "SELECT a.*, c.course_code, c.course_name 
                      FROM assignments a
                      JOIN courses c ON a.course_id = c.id
                      JOIN enrollments e ON c.id = e.course_id
                      WHERE e.student_id = $user_id AND a.due_date > NOW()
                      ORDER BY a.due_date ASC LIMIT 5";
$assignments = mysqli_query($conn, $assignments_query);

// Get course materials count
$materials_query = "SELECT COUNT(*) as total FROM course_materials cm
                    JOIN courses c ON cm.course_id = c.id
                    JOIN enrollments e ON c.id = e.course_id
                    WHERE e.student_id = $user_id";
$materials_result = mysqli_query($conn, $materials_query);
$materials_count = mysqli_fetch_assoc($materials_result)['total'];

// For lecturers: get teaching courses count
$teaching_courses = 0;
if($user_role == 'lecturer') {
    $teaching_query = "SELECT COUNT(*) as total FROM courses WHERE lecturer_id = $user_id";
    $teaching_result = mysqli_query($conn, $teaching_query);
    $teaching_courses = mysqli_fetch_assoc($teaching_result)['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0F4C3A">
    <link rel="manifest" href="manifest.json">
    <title>Dashboard - FoC Connect</title>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #F5F7F6; overflow-x: hidden; transition: background 0.3s ease; }
        :root { --sidebar-bg: #0F4C3A; --sidebar-text: #E8F5E9; --sidebar-hover: #2E7D64; --sidebar-active: #3A997A; --main-bg: #F5F7F6; --card-bg: #FFFFFF; --card-border: #E8EDEC; --text-primary: #1A2E28; --text-secondary: #6B7E78; --text-muted: #8A9B97; --button-primary: #2E7D64; --emergency: #E53E3E; --border-color: #E2E8F0; --detail-bg: #F5F7F6; }
        body.dark { --sidebar-bg: #08332A; --sidebar-text: #C8E6D9; --sidebar-hover: #1E5A48; --sidebar-active: #2A7D64; --main-bg: #1A2E28; --card-bg: #2D4A40; --card-border: #3A5A50; --text-primary: #F5F7F6; --text-secondary: #A8BFB8; --text-muted: #8A9B97; --button-primary: #3A997A; --border-color: #3A5A50; --detail-bg: #3A5A50; }

        .sidebar { width: 280px; background: var(--sidebar-bg); color: var(--sidebar-text); position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; z-index: 100; transition: transform 0.3s ease; }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo { font-size: 24px; font-weight: 700; }
        .sidebar-subtitle { font-size: 11px; font-weight: bold; opacity: 0.8; margin-top: 6px; letter-spacing: 0.5px; }
        .sidebar-nav { padding: 20px 16px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; margin: 4px 0; border-radius: 12px; color: var(--sidebar-text); text-decoration: none; transition: all 0.2s; }
        .nav-item:hover, .nav-item.active { background: var(--sidebar-hover); }
        .nav-icon { font-size: 22px; width: 28px; }
        .nav-text { font-size: 15px; flex: 1; }
        .nav-badge { background: #E53E3E; color: white; font-size: 11px; padding: 2px 8px; border-radius: 20px; }
        .main-content { margin-left: 280px; min-height: 100vh; width: calc(100% - 280px); transition: margin 0.3s; }
        
        .top-header { background: var(--card-bg); border-bottom: 1px solid var(--border-color); padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; transition: background 0.3s ease; }
        .dashboard-container { padding: 24px; max-width: 1200px; margin: 0 auto; }
        .user-card { background: var(--card-bg); border-radius: 24px; padding: 28px; margin-bottom: 24px; border: 1px solid var(--card-border); position: relative; overflow: hidden; }
        .user-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--button-primary), #D69E2E); }
        .detail-item { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; background: var(--detail-bg); padding: 10px 18px; border-radius: 40px; margin: 4px; }
        .detail-item.highlight { background: var(--button-primary); color: white; }
        .copy-btn { background: rgba(255,255,255,0.2); border: none; cursor: pointer; font-size: 12px; padding: 4px 8px; border-radius: 20px; margin-left: 8px; color: white; }
        
        .role-badge { padding: 8px 20px; border-radius: 40px; font-size: 14px; font-weight: 600; display: inline-block; margin-top: 10px; }
        .role-badge.student { background: #2E7D64; color: white; }
        .quick-btn { background: var(--detail-bg); border: 1px solid var(--border-color); padding: 12px 24px; border-radius: 40px; cursor: pointer; font-size: 14px; font-weight: 500; color: var(--text-primary); transition: all 0.2s; margin-right: 10px; margin-top: 10px;}
        .quick-btn:hover { background: var(--button-primary); color: white; }

        .dashboard-two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .dashboard-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;}
        .stats-card { background: var(--card-bg); border-radius: 20px; padding: 20px; text-align: center; border: 1px solid var(--card-border); }
        .stats-number { font-size: 36px; font-weight: 700; color: var(--button-primary); }
        
        .announcement-item { padding: 14px 0; border-bottom: 1px solid var(--border-color); }
        .announcement-item.emergency { border-left: 4px solid var(--emergency); padding-left: 15px; background: rgba(229, 62, 62, 0.05); }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .dashboard-two-column { grid-template-columns: 1fr; }
        }
        .hamburger { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-primary); display: none; }
        @media (max-width: 768px) { .hamburger { display: block; } }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? '') == 'dark' ? 'dark' : ''; ?>">
<div style="display: flex; min-height: 100vh;">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">🌿 FoC Connect</div>
            <div class="sidebar-subtitle">🏛️ FACULTY OF COMPUTING</div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="messaging.php" class="nav-item">
                <span class="nav-icon">💬</span>
                <span class="nav-text">Chat</span>
                <?php if($unread_count > 0): ?>
                    <span class="nav-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="announcements.php" class="nav-item">
                <span class="nav-icon">📢</span>
                <span class="nav-text">Announcements</span>
            </a>
            <a href="materials.php" class="nav-item">
                <span class="nav-icon">📚</span>
                <span class="nav-text">Course Materials</span>
                <?php if($materials_count > 0): ?>
                    <span class="nav-badge"><?php echo $materials_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="assignments.php" class="nav-item">
                <span class="nav-icon">📝</span>
                <span class="nav-text">Assignments</span>
            </a>
            <?php if(isset($user['is_graduated']) && $user['is_graduated'] == 1): ?>
                <a href="alumni-directory.php" class="nav-item">
                    <span class="nav-icon">🎓</span>
                    <span class="nav-text">Alumni Directory</span>
                </a>
            <?php endif; ?>
            <?php if(in_array($user_role, ['dean', 'hod', 'admin', 'lecturer', 'level_coordinator'])): ?>
                <a href="moderation-panel.php" class="nav-item">
                    <span class="nav-icon">🛡️</span>
                    <span class="nav-text">Moderation</span>
                </a>
            <?php endif; ?>
            <a href="settings.php" class="nav-item">
                <span class="nav-icon">⚙️</span>
                <span class="nav-text">Settings</span>
            </a>
        </nav>
        <div class="sidebar-footer" style="padding: 20px;">
            <a href="logout.php" class="nav-item">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="hamburger" onclick="toggleSidebar()">☰</button>
                <div class="page-title" style="font-weight: bold; font-size: 1.2rem;">Dashboard</div>
            </div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="quick-btn" style="padding: 8px 16px;" onclick="toggleDarkMode()">🌙 Mode</button>
                <div class="profile-avatar" style="width:35px; height:35px; background:var(--sidebar-bg); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="dashboard-container">
            <div class="user-card">
                <div class="user-info">
                    <h2>Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋</h2>
                    <div class="user-details" style="margin-top: 15px;">
                        <?php if($user['matric_number']): ?>
                            <div class="detail-item highlight">🎓 <strong>Matric:</strong> <?php echo htmlspecialchars($user['matric_number']); ?></div>
                        <?php endif; ?>
                        <div class="detail-item">🏛️ <?php echo htmlspecialchars($user['dept_name'] ?? 'Faculty of Computing'); ?></div>
                        <?php if($user['level_name']): ?>
                            <div class="detail-item">📚 <?php echo htmlspecialchars($user['level_name']); ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="role-badge <?php echo $role_class; ?>"><?php echo $role_display; ?></span>
                </div>
                <div class="quick-actions">
                    <button class="quick-btn" onclick="window.location.href='messaging.php'">💬 Message</button>
                    <button class="quick-btn" onclick="window.location.href='materials.php'">📚 Materials</button>
                </div>
            </div>

            <div class="dashboard-two-column">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $has_courses ? mysqli_num_rows($courses) : ($user_role == 'lecturer' ? $teaching_courses : '0'); ?></div>
                    <div class="stats-label">Courses</div>
                </div>
                <div class="stats-card">
                    <div class="stats-number"><?php echo mysqli_num_rows($assignments); ?></div>
                    <div class="stats-label">Due Assignments</div>
                </div>
            </div>

            <div class="dashboard-two-column">
                <!-- Announcements -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <span style="font-weight:bold;">📢 Announcements</span>
                        <a href="announcements.php" style="font-size: 12px; color: var(--button-primary); text-decoration:none;">View All</a>
                    </div>
                    <?php if(mysqli_num_rows($announcements) > 0): ?>
                        <?php while($ann = mysqli_fetch_assoc($announcements)): ?>
                            <div class="announcement-item <?php echo $ann['is_emergency'] ? 'emergency' : ''; ?>">
                                <div style="font-weight:600;"><?php echo htmlspecialchars($ann['title']); ?></div>
                                <div style="font-size:11px; color:var(--text-muted);"><?php echo date('M d', strtotime($ann['sent_at'])); ?> • <?php echo htmlspecialchars($ann['author_name']); ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align:center; padding:20px; color:var(--text-muted);">No new announcements</p>
                    <?php endif; ?>
                </div>

                <!-- Course List -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <span style="font-weight:bold;">📚 My Courses</span>
                    </div>
                    <?php if($has_courses): ?>
                        <?php while($course = mysqli_fetch_assoc($courses)): ?>
                            <div style="padding: 10px 0; border-bottom: 1px solid var(--border-color);">
                                <div style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                <div style="font-size:12px; color:var(--text-secondary);"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align:center; padding:20px; color:var(--text-muted);">No courses found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }
    function toggleDarkMode() {
        document.body.classList.toggle('dark');
        const isDark = document.body.classList.contains('dark');
        document.cookie = "theme=" + (isDark ? "dark" : "light") + ";path=/";
    }
</script>
</body>
</html>
