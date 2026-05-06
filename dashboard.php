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
    'course_rep'             => ['🟡 Class Representative', 'class-rep'],
    'asst_course_rep'        => ['🟡 Asst. Class Rep', 'class-rep'],
    'financial_secretary'    => ['💰 Financial Secretary', 'financial-sec'],
    'pro'                    => ['📢 Public Relations Officer', 'pro'],
    'general_secretary'      => ['📝 General Secretary', 'gen-sec'],
    'vice_president'         => ['👑 Vice President', 'vp'],
    'departmental_president' => ['👔 Departmental President', 'dept-pres']
];

// Staff Role Mapping
$staff_role_map = [
    'lecturer'             => ['👨‍🏫 Lecturer', 'lecturer'],
    'level_coordinator'    => ['📚 Level Coordinator', 'level-coord'],
    'dept_exam_officer'    => ['📋 Dept. Exam Officer', 'dept-exam'],
    'hod'                  => ['👔 Head of Department', 'hod'],
    'faculty_exam_officer' => ['📋 Faculty Exam Officer', 'faculty-exam'],
    'dean'                 => ['🎓 Dean', 'dean']
];

// Get role display
$role_display = '👤 Student';
$role_class   = 'student';

if ($user_role == 'student' && !empty($user['student_role']) && $user['student_role'] != 'student' && isset($student_role_map[$user['student_role']])) {
    $role_display = $student_role_map[$user['student_role']][0];
    $role_class   = $student_role_map[$user['student_role']][1];
} elseif ($user_role != 'student' && isset($staff_role_map[$user_role])) {
    $role_display = $staff_role_map[$user_role][0];
    $role_class   = $staff_role_map[$user_role][1];
}

// Get unread notifications count
$notif_result  = mysqli_query($conn, "SELECT COUNT(*) as unread FROM notifications WHERE user_id = $user_id AND is_read = 0");
$unread_count  = mysqli_fetch_assoc($notif_result)['unread'];

// Department and level for filtering
$dept_id  = isset($user['department_id']) && $user['department_id'] > 0 ? (int)$user['department_id'] : 0;
$level_id = isset($user['level_id'])      && $user['level_id'] > 0      ? (int)$user['level_id']      : 0;

// Get announcements
$announcements_query = "SELECT a.*, u.name as author_name 
                        FROM announcements a
                        JOIN users u ON a.from_user_id = u.id
                        WHERE (a.department_id IS NULL OR a.department_id = $dept_id)
                        AND (a.level_id IS NULL OR a.level_id = $level_id)
                        ORDER BY a.sent_at DESC LIMIT 5";
$announcements = mysqli_query($conn, $announcements_query);

// FIX: Store courses in array so we can count AND loop without pointer issues
$courses_query  = "SELECT c.*, e.enrolled_at 
                   FROM courses c
                   JOIN enrollments e ON c.id = e.course_id
                   WHERE e.student_id = $user_id
                   LIMIT 5";
$courses_result = mysqli_query($conn, $courses_query);
$courses_data   = [];
while ($row = mysqli_fetch_assoc($courses_result)) {
    $courses_data[] = $row;
}
$has_courses = count($courses_data) > 0;

// Get pending assignments — also stored in array
$assignments_query  = "SELECT a.*, c.course_code, c.course_name 
                       FROM assignments a
                       JOIN courses c ON a.course_id = c.id
                       JOIN enrollments e ON c.id = e.course_id
                       WHERE e.student_id = $user_id AND a.due_date > NOW()
                       ORDER BY a.due_date ASC LIMIT 5";
$assignments_result = mysqli_query($conn, $assignments_query);
$assignments_data   = [];
while ($row = mysqli_fetch_assoc($assignments_result)) {
    $assignments_data[] = $row;
}
$has_assignments = count($assignments_data) > 0;

// Get course materials count
$materials_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM course_materials cm
                                         JOIN courses c ON cm.course_id = c.id
                                         JOIN enrollments e ON c.id = e.course_id
                                         WHERE e.student_id = $user_id");
$materials_count = mysqli_fetch_assoc($materials_result)['total'];

// For lecturers: get teaching courses
$teaching_courses_data = [];
if ($user_role == 'lecturer') {
    $tr = mysqli_query($conn, "SELECT * FROM courses WHERE lecturer_id = $user_id LIMIT 5");
    while ($row = mysqli_fetch_assoc($tr)) {
        $teaching_courses_data[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0F4C3A">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/icons/icon-152x152.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FoC Connect">
    <title>Dashboard - FoC Connect</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #F5F7F6;
            overflow-x: hidden;
            transition: background 0.3s ease;
        }

        :root {
            --sidebar-bg: #0F4C3A;
            --sidebar-text: #E8F5E9;
            --sidebar-hover: #2E7D64;
            --main-bg: #F5F7F6;
            --card-bg: #FFFFFF;
            --card-border: #E8EDEC;
            --text-primary: #1A2E28;
            --text-secondary: #6B7E78;
            --text-muted: #8A9B97;
            --button-primary: #2E7D64;
            --emergency: #E53E3E;
            --border-color: #E2E8F0;
            --detail-bg: #F5F7F6;
        }

        body.dark {
            --sidebar-bg: #08332A;
            --sidebar-text: #C8E6D9;
            --sidebar-hover: #1E5A48;
            --main-bg: #1A2E28;
            --card-bg: #2D4A40;
            --card-border: #3A5A50;
            --text-primary: #F5F7F6;
            --text-secondary: #A8BFB8;
            --text-muted: #8A9B97;
            --button-primary: #3A997A;
            --border-color: #3A5A50;
            --detail-bg: #3A5A50;
        }

        /* Sidebar */
        .sidebar {
            width: 280px; background: var(--sidebar-bg); color: var(--sidebar-text);
            position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; z-index: 100;
            transition: transform 0.3s ease, background 0.3s ease;
        }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo { font-size: 22px; font-weight: 700; }
        .sidebar-subtitle { font-size: 10px; font-weight: 700; opacity: 0.75; margin-top: 5px; letter-spacing: 0.5px; }
        .sidebar-nav { padding: 20px 16px; }
        .nav-item {
            display: flex; align-items: center; gap: 14px;
            padding: 12px 16px; margin: 3px 0; border-radius: 12px;
            color: var(--sidebar-text); text-decoration: none; transition: all 0.2s;
        }
        .nav-item:hover, .nav-item.active { background: var(--sidebar-hover); }
        .nav-icon { font-size: 20px; width: 26px; }
        .nav-text { font-size: 14px; flex: 1; }
        .nav-badge { background: #E53E3E; color: white; font-size: 11px; padding: 2px 7px; border-radius: 20px; }
        .sidebar-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.1); }

        /* Main */
        .main-content { margin-left: 280px; min-height: 100vh; width: calc(100% - 280px); background: var(--main-bg); transition: background 0.3s; }

        /* Header */
        .top-header {
            background: var(--card-bg); border-bottom: 1px solid var(--border-color);
            padding: 14px 24px; display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 50; flex-wrap: wrap; gap: 10px;
            transition: background 0.3s;
        }
        .page-title { font-size: 19px; font-weight: 600; color: var(--text-primary); }
        .current-datetime { font-size: 12px; color: var(--text-secondary); background: var(--detail-bg); padding: 6px 12px; border-radius: 40px; }
        .profile-btn { display: flex; align-items: center; gap: 10px; padding: 6px 12px; border-radius: 40px; background: var(--detail-bg); }
        .profile-avatar { width: 34px; height: 34px; background: var(--button-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 13px; }
        .dark-toggle { background: var(--detail-bg); border: 1px solid var(--border-color); border-radius: 40px; padding: 7px 14px; cursor: pointer; font-size: 13px; color: var(--text-primary); transition: all 0.2s; }
        .dark-toggle:hover { background: var(--button-primary); color: white; border-color: var(--button-primary); }

        /* Dashboard */
        .dashboard-container { padding: 24px; max-width: 100%; }

        /* User Card */
        .user-card {
            background: var(--card-bg); border-radius: 24px; padding: 28px;
            margin-bottom: 24px; border: 1px solid var(--card-border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04); position: relative; overflow: hidden;
            transition: all 0.3s;
        }
        .user-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #2E7D64, #D69E2E); }
        .user-info h2 { font-size: 22px; color: var(--text-primary); margin-bottom: 14px; }
        .user-details { display: flex; flex-wrap: wrap; gap: 12px; margin: 14px 0; }
        .detail-item {
            display: flex; align-items: center; gap: 8px; font-size: 13px;
            background: var(--detail-bg); padding: 9px 16px; border-radius: 40px;
            color: var(--text-primary); transition: all 0.3s;
        }
        .detail-item.highlight { background: #2E7D64; color: white; font-weight: 500; }
        body.dark .detail-item.highlight { background: #3A997A; }
        .copy-btn { background: rgba(255,255,255,0.2); border: none; cursor: pointer; font-size: 11px; padding: 3px 8px; border-radius: 20px; color: white; margin-left: 6px; }
        .copy-btn:hover { background: rgba(255,255,255,0.35); }

        /* Role badges */
        .role-badge { padding: 7px 18px; border-radius: 40px; font-size: 13px; font-weight: 600; display: inline-block; }
        .role-badge.student       { background: #2E7D64; color: white; }
        .role-badge.class-rep     { background: #D69E2E; color: #1A202C; }
        .role-badge.financial-sec { background: #3182CE; color: white; }
        .role-badge.pro           { background: #805AD5; color: white; }
        .role-badge.gen-sec       { background: #DD6B20; color: white; }
        .role-badge.vp            { background: #E53E3E; color: white; }
        .role-badge.dept-pres     { background: #8B4513; color: white; }
        .role-badge.level-coord   { background: #6B46C1; color: white; }
        .role-badge.lecturer      { background: #3182CE; color: white; }
        .role-badge.dept-exam     { background: #00A3C4; color: white; }
        .role-badge.hod           { background: #DD6B20; color: white; }
        .role-badge.faculty-exam  { background: #00A3C4; color: white; }
        .role-badge.dean          { background: #C53030; color: white; }

        /* Quick Actions */
        .quick-actions { display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap; }
        .quick-btn {
            background: var(--detail-bg); border: 1px solid var(--border-color);
            padding: 10px 20px; border-radius: 40px; cursor: pointer;
            font-size: 13px; font-weight: 500; color: var(--text-primary); transition: all 0.2s;
            text-decoration: none; display: inline-block;
        }
        .quick-btn:hover { background: var(--button-primary); color: white; border-color: var(--button-primary); }

        /* Grid */
        .dashboard-two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .dashboard-card, .full-width-card {
            background: var(--card-bg); border: 1px solid var(--card-border);
            border-radius: 20px; padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: all 0.3s;
        }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 2px solid var(--border-color); }
        .card-title { font-size: 16px; font-weight: 600; color: var(--text-primary); }
        .card-link { font-size: 13px; color: var(--button-primary); text-decoration: none; font-weight: 500; }
        .card-link:hover { text-decoration: underline; }

        /* Announcement items */
        .announcement-item { padding: 12px 0; border-bottom: 1px solid var(--border-color); }
        .announcement-item:last-child { border-bottom: none; }
        .announcement-title { font-weight: 600; font-size: 14px; color: var(--text-primary); margin-bottom: 5px; }
        .announcement-meta { font-size: 11px; color: var(--text-muted); display: flex; gap: 14px; flex-wrap: wrap; }
        .emergency { border-left: 3px solid var(--emergency); padding-left: 12px; background: rgba(229,62,62,0.07); margin-left: -4px; border-radius: 0 8px 8px 0; }

        /* Course items */
        .course-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 8px; }
        .course-item:last-child { border-bottom: none; }
        .course-code { font-weight: 700; font-size: 14px; color: var(--text-primary); }
        .course-name { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .course-status { font-size: 11px; color: var(--button-primary); background: rgba(46,125,100,0.12); padding: 4px 10px; border-radius: 20px; }

        /* Assignment items */
        .assignment-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 8px; }
        .assignment-item:last-child { border-bottom: none; }
        .assignment-title { font-weight: 600; font-size: 14px; color: var(--text-primary); }
        .assignment-course { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .assignment-due { font-size: 12px; color: var(--emergency); font-weight: 600; }

        /* Stats */
        .stats-card { background: var(--card-bg); border-radius: 20px; padding: 20px; text-align: center; border: 1px solid var(--card-border); transition: all 0.3s; }
        .stats-number { font-size: 34px; font-weight: 700; color: var(--button-primary); }
        .stats-label { font-size: 13px; color: var(--text-secondary); margin-top: 6px; }

        .empty-state { text-align: center; padding: 36px 20px; color: var(--text-muted); font-size: 14px; }

        /* Toast notification */
        .toast {
            position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%) translateY(20px);
            background: #1A2E28; color: white; padding: 10px 22px; border-radius: 40px;
            font-size: 13px; opacity: 0; transition: all 0.3s; z-index: 9999; pointer-events: none;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

        /* Responsive */
        @media (max-width: 1024px) { .dashboard-two-column { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .top-header { padding: 12px 16px; }
            .dashboard-container { padding: 14px; }
            .dark-toggle span { display: none; }
        }
        @media (min-width: 769px) { .hamburger { display: none !important; } }
        .hamburger { background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-primary); }
    </style>
</head>
<body>
<div style="display:flex; min-height:100vh;">

    <!-- ══ SIDEBAR ══ -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">🌿 FoC Connect</div>
            <div class="sidebar-subtitle">🏛️ FACULTY OF COMPUTING</div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <span class="nav-icon">📊</span><span class="nav-text">Dashboard</span>
            </a>
            <!-- FIX: changed chat.php → conversation-working.php -->
            <a href="conversation-working.php" class="nav-item">
                <span class="nav-icon">💬</span><span class="nav-text">Chat</span>
                <?php if($unread_count > 0): ?>
                    <span class="nav-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="announcements.php" class="nav-item">
                <span class="nav-icon">📢</span><span class="nav-text">Announcements</span>
            </a>
            <a href="materials.php" class="nav-item">
                <span class="nav-icon">📚</span><span class="nav-text">Course Materials</span>
                <?php if($materials_count > 0): ?>
                    <span class="nav-badge"><?php echo $materials_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="assignments.php" class="nav-item">
                <span class="nav-icon">📝</span><span class="nav-text">Assignments</span>
            </a>
            <?php if(!empty($user['is_graduated']) && $user['is_graduated'] == 1): ?>
                <a href="alumni-directory.php" class="nav-item"><span class="nav-icon">🎓</span><span class="nav-text">Alumni Directory</span></a>
                <a href="job-board.php" class="nav-item"><span class="nav-icon">💼</span><span class="nav-text">Job Board</span></a>
                <a href="mentorship.php" class="nav-item"><span class="nav-icon">🤝</span><span class="nav-text">Mentorship</span></a>
            <?php endif; ?>
            <?php if(in_array($user_role, ['dean','hod','lecturer','level_coordinator','faculty_exam_officer','dept_exam_officer'])): ?>
                <a href="moderation-panel.php" class="nav-item"><span class="nav-icon">🛡️</span><span class="nav-text">Moderation</span></a>
                <a href="progression.php" class="nav-item"><span class="nav-icon">📈</span><span class="nav-text">Progression</span></a>
                <a href="admin-roles.php" class="nav-item"><span class="nav-icon">👥</span><span class="nav-text">Role Management</span></a>
            <?php endif; ?>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </aside>

    <!-- ══ MAIN ══ -->
    <main class="main-content">
        <header class="top-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="hamburger" onclick="toggleSidebar()">☰</button>
                <div class="page-title">Dashboard</div>
            </div>
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <div class="current-datetime" id="currentDateTime"></div>
                <button class="dark-toggle" id="darkToggle" onclick="toggleDark()">🌙 <span>Dark Mode</span></button>
                <div class="profile-btn">
                    <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                    <span style="font-size:14px; color:var(--text-primary);"><?php echo htmlspecialchars($user_name); ?></span>
                </div>
            </div>
        </header>

        <div class="dashboard-container">

            <!-- User Card -->
            <div class="user-card">
                <div class="user-info">
                    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>! 👋</h2>
                    <div class="user-details">
                        <?php if(!empty($user['matric_number'])): ?>
                            <div class="detail-item highlight">
                                🎓 <strong>Matric:</strong> <?php echo htmlspecialchars($user['matric_number']); ?>
                                <button class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($user['matric_number']); ?>')">📋</button>
                            </div>
                        <?php endif; ?>
                        <div class="detail-item highlight">
                            🏛️ <?php echo htmlspecialchars($user['dept_name'] ?? 'Faculty of Computing'); ?>
                        </div>
                        <?php if(!empty($user['level_name'])): ?>
                            <div class="detail-item highlight">
                                📚 <?php echo htmlspecialchars($user['level_name']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="detail-item highlight">
                            📧 <?php echo htmlspecialchars($user['email']); ?>
                            <button class="copy-btn" onclick="copyText('<?php echo htmlspecialchars($user['email']); ?>')">📋</button>
                        </div>
                    </div>
                    <span class="role-badge <?php echo $role_class; ?>"><?php echo $role_display; ?></span>
                </div>
                <div class="quick-actions">
                    <a href="conversation-working.php" class="quick-btn">💬 Open Chat</a>
                    <a href="announcements.php" class="quick-btn">📢 Announcements</a>
                    <a href="materials.php" class="quick-btn">📚 Materials</a>
                    <a href="assignments.php" class="quick-btn">📝 Assignments</a>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="dashboard-two-column" style="margin-bottom:24px;">
                <div class="stats-card">
                    <div class="stats-number">
                        <?php echo ($user_role == 'lecturer') ? count($teaching_courses_data) : count($courses_data); ?>
                    </div>
                    <div class="stats-label"><?php echo ($user_role == 'lecturer') ? '👨‍🏫 Teaching Courses' : '📚 Enrolled Courses'; ?></div>
                </div>
                <div class="stats-card">
                    <div class="stats-number"><?php echo count($assignments_data); ?></div>
                    <div class="stats-label">📝 Pending Assignments</div>
                </div>
            </div>

            <!-- Two Column -->
            <div class="dashboard-two-column">
                <!-- Announcements -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-title">📢 Announcements</div>
                        <a href="announcements.php" class="card-link">View all →</a>
                    </div>
                    <?php if(mysqli_num_rows($announcements) > 0): ?>
                        <?php while($ann = mysqli_fetch_assoc($announcements)): ?>
                            <div class="announcement-item <?php echo $ann['is_emergency'] ? 'emergency' : ''; ?>">
                                <div class="announcement-title">
                                    <?php if($ann['is_emergency']): ?><span style="color:var(--emergency);">🔴 </span><?php endif; ?>
                                    <?php echo htmlspecialchars($ann['title']); ?>
                                </div>
                                <div class="announcement-meta">
                                    <span>👤 <?php echo htmlspecialchars($ann['author_name']); ?></span>
                                    <span>📅 <?php echo date('M d, Y g:i A', strtotime($ann['sent_at'])); ?></span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">📭 No announcements yet</div>
                    <?php endif; ?>
                </div>

                <!-- Courses -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-title"><?php echo ($user_role == 'lecturer') ? '👨‍🏫 Teaching Courses' : '📚 My Courses'; ?></div>
                        <a href="materials.php" class="card-link">View all →</a>
                    </div>
                    <?php
                    // FIX: Use stored arrays — no mysql pointer issues
                    $display_courses = ($user_role == 'lecturer') ? $teaching_courses_data : $courses_data;
                    if(count($display_courses) > 0):
                        foreach($display_courses as $course):
                    ?>
                        <div class="course-item">
                            <div>
                                <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            </div>
                            <div class="course-status"><?php echo ($user_role == 'lecturer') ? 'Teaching' : 'In Progress'; ?></div>
                        </div>
                    <?php
                        endforeach;
                    else: ?>
                        <div class="empty-state">📚 No courses <?php echo ($user_role == 'lecturer') ? 'assigned' : 'enrolled'; ?> yet</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assignments -->
            <div class="full-width-card">
                <div class="card-header">
                    <div class="card-title">📝 Pending Assignments</div>
                    <a href="assignments.php" class="card-link">View all →</a>
                </div>
                <?php if($has_assignments): ?>
                    <?php foreach($assignments_data as $assign): ?>
                        <div class="assignment-item">
                            <div>
                                <div class="assignment-title"><?php echo htmlspecialchars($assign['title']); ?></div>
                                <div class="assignment-course"><?php echo htmlspecialchars($assign['course_code']); ?> — <?php echo htmlspecialchars($assign['course_name']); ?></div>
                            </div>
                            <div class="assignment-due">Due: <?php echo date('M d, Y', strtotime($assign['due_date'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">✅ No pending assignments. You're all caught up!</div>
                <?php endif; ?>
            </div>

        </div><!-- end dashboard-container -->
    </main>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
    // ── Clock ──
    function updateDateTime() {
        const now = new Date();
        const opts = { weekday:'short', year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' };
        document.getElementById('currentDateTime').textContent = '📅 ' + now.toLocaleDateString('en-US', opts);
    }
    updateDateTime();
    setInterval(updateDateTime, 60000);

    // ── Sidebar toggle ──
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && !e.target.classList.contains('hamburger')) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ── Copy to clipboard (toast instead of alert) ──
    function copyText(text) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('📋 Copied: ' + text);
        }).catch(function() {
            showToast('❌ Copy failed');
        });
    }

    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2500);
    }

    // ── Dark Mode ──
    function toggleDark() {
        document.body.classList.toggle('dark');
        const btn = document.getElementById('darkToggle');
        const isDark = document.body.classList.contains('dark');
        btn.innerHTML = isDark ? '☀️ <span>Light Mode</span>' : '🌙 <span>Dark Mode</span>';
        localStorage.setItem('darkMode', isDark ? '1' : '0');
    }
    if (localStorage.getItem('darkMode') === '1') {
        document.body.classList.add('dark');
        document.getElementById('darkToggle').innerHTML = '☀️ <span>Light Mode</span>';
    }

    // ── PWA Service Worker ──
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('SW registered'))
                .catch(err => console.log('SW failed:', err));
        });
    }
</script>
</body>
</html>
