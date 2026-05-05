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
$has_assignments = mysqli_num_rows($assignments) > 0;

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
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/icons/icon-152x152.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FoC Connect">
    
    <title>Dashboard - FoC Connect</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #F5F7F6;
            overflow-x: hidden;
            transition: background 0.3s ease;
        }

        /* Dark Mode Variables */
        :root {
            --sidebar-bg: #0F4C3A;
            --sidebar-text: #E8F5E9;
            --sidebar-hover: #2E7D64;
            --sidebar-active: #3A997A;
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
            --sidebar-active: #2A7D64;
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
            width: 280px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 100;
            transition: background 0.3s ease;
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-logo {
            font-size: 24px;
            font-weight: 700;
        }

        .sidebar-subtitle {
            font-size: 11px;
            font-weight: bold;
            opacity: 0.8;
            margin-top: 6px;
            letter-spacing: 0.5px;
        }

        .sidebar-nav {
            padding: 20px 16px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            margin: 4px 0;
            border-radius: 12px;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-item:hover, .nav-item.active {
            background: var(--sidebar-hover);
        }

        .nav-icon {
            font-size: 22px;
            width: 28px;
        }

        .nav-text {
            font-size: 15px;
            flex: 1;
        }

        .nav-badge {
            background: #E53E3E;
            color: white;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .sidebar-footer {
            padding: 20px 16px;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            width: calc(100% - 280px);
        }

        /* Top Header */
        .top-header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
            flex-wrap: wrap;
            gap: 12px;
            transition: background 0.3s ease;
        }

        .page-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .current-datetime {
            font-size: 13px;
            color: var(--text-secondary);
            background: var(--detail-bg);
            padding: 6px 12px;
            border-radius: 40px;
        }

        .profile-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 40px;
            background: var(--detail-bg);
        }

        .profile-avatar {
            width: 36px;
            height: 36px;
            background: var(--button-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .dark-toggle {
            background: var(--detail-bg);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            padding: 8px 16px;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-primary);
            transition: all 0.2s;
        }
        .dark-toggle:hover {
            background: var(--button-primary);
            color: white;
        }

        /* Dashboard Container */
        .dashboard-container {
            padding: 24px;
            max-width: 100%;
        }

        /* User Card */
        .user-card {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 24px;
            border: 1px solid var(--card-border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--button-primary), #D69E2E);
        }

        .user-info h2 {
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 16px;
        }

        .user-details {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin: 16px 0;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            background: var(--detail-bg);
            padding: 10px 18px;
            border-radius: 40px;
            transition: all 0.3s ease;
        }

        .detail-item.highlight {
            background: #2E7D64;
            color: white;
            font-weight: 500;
        }
        body.dark .detail-item.highlight {
            background: #3A997A;
        }

        .copy-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            cursor: pointer;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 20px;
            margin-left: 8px;
            color: white;
        }

        /* Role Badges */
        .role-badge {
            padding: 8px 20px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
        }
        .role-badge.student { background: #2E7D64; color: white; }
        .role-badge.class-rep { background: #D69E2E; color: #1A202C; }
        .role-badge.financial-sec { background: #3182CE; color: white; }
        .role-badge.pro { background: #805AD5; color: white; }
        .role-badge.gen-sec { background: #DD6B20; color: white; }
        .role-badge.vp { background: #E53E3E; color: white; }
        .role-badge.dept-pres { background: #8B4513; color: white; }
        .role-badge.level-coord { background: #6B46C1; color: white; }
        .role-badge.lecturer { background: #3182CE; color: white; }
        .role-badge.dept-exam { background: #00A3C4; color: white; }
        .role-badge.hod { background: #DD6B20; color: white; }
        .role-badge.faculty-exam { background: #00A3C4; color: white; }
        .role-badge.dean { background: #C53030; color: white; }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 16px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .quick-btn {
            background: var(--detail-bg);
            border: 1px solid var(--border-color);
            padding: 12px 24px;
            border-radius: 40px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            transition: all 0.2s;
        }

        .quick-btn:hover {
            background: var(--button-primary);
            color: white;
            border-color: var(--button-primary);
        }

        /* Grid Layout */
        .dashboard-two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .dashboard-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            height: 100%;
            transition: all 0.3s ease;
        }

        .full-width-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-link {
            font-size: 13px;
            color: var(--button-primary);
            text-decoration: none;
            font-weight: 500;
        }

        /* Announcement Items */
        .announcement-item {
            padding: 14px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .announcement-item:last-child {
            border-bottom: none;
        }

        .announcement-title {
            font-weight: 600;
            font-size: 15px;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .announcement-meta {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .emergency {
            border-left: 3px solid var(--emergency);
            padding-left: 12px;
            background: rgba(229, 62, 62, 0.1);
            margin-left: -12px;
        }

        /* Course Items */
        .course-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 8px;
        }

        .course-item:last-child {
            border-bottom: none;
        }

        .course-code {
            font-weight: 700;
            font-size: 15px;
            color: var(--text-primary);
        }

        .course-name {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .course-status {
            font-size: 12px;
            color: var(--button-primary);
            background: rgba(46, 125, 100, 0.15);
            padding: 4px 12px;
            border-radius: 20px;
        }

        /* Assignment Items */
        .assignment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 8px;
        }

        .assignment-item:last-child {
            border-bottom: none;
        }

        .assignment-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }

        .assignment-course {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .assignment-due {
            font-size: 12px;
            color: var(--emergency);
            font-weight: 500;
        }

        /* Stats Card */
        .stats-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--card-border);
        }
        .stats-number {
            font-size: 36px;
            font-weight: 700;
            color: var(--button-primary);
        }
        .stats-label {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        /* Eruda Button */
        .eruda-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background: #2E7D64;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            font-size: 24px;
            transition: transform 0.2s;
            border: none;
        }
        .eruda-toggle:hover {
            transform: scale(1.05);
        }
        @media (min-width: 769px) {
            .eruda-toggle {
                display: none;
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-two-column {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .top-header {
                padding: 12px 16px;
            }
            .dashboard-container {
                padding: 16px;
            }
            .user-details {
                gap: 12px;
            }
            .hamburger {
                display: block;
            }
            .dark-toggle span {
                display: none;
            }
            .dark-toggle {
                padding: 8px 12px;
            }
        }

        @media (min-width: 769px) {
            .hamburger {
                display: none;
            }
        }

        .hamburger {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-primary);
        }
    </style>
</head>
<body>
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
            <a href="talk.php" target="_blank" class="nav-item">
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
            <?php if($user['is_graduated'] == 1): ?>
                <a href="alumni-directory.php" class="nav-item">
                    <span class="nav-icon">🎓</span>
                    <span class="nav-text">Alumni Directory</span>
                </a>
                <a href="job-board.php" class="nav-item">
                    <span class="nav-icon">💼</span>
                    <span class="nav-text">Job Board</span>
                </a>
                <a href="mentorship.php" class="nav-item">
                    <span class="nav-icon">🤝</span>
                    <span class="nav-text">Mentorship</span>
                </a>
            <?php endif; ?>
            <?php if(in_array($user_role, ['dean', 'hod', 'admin', 'lecturer', 'level_coordinator'])): ?>
                <a href="moderation-panel.php" class="nav-item">
                    <span class="nav-icon">🛡️</span>
                    <span class="nav-text">Moderation</span>
                </a>
                <a href="progression.php" class="nav-item">
                    <span class="nav-icon">📈</span>
                    <span class="nav-text">Progression</span>
                </a>
                <a href="admin-roles.php" class="nav-item">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Role Management</span>
                </a>
            <?php endif; ?>
            <a href="settings.php" class="nav-item">
                <span class="nav-icon">⚙️</span>
                <span class="nav-text">Settings</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="hamburger" onclick="toggleSidebar()">☰</button>
                <div class="page-title">Dashboard</div>
            </div>
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div class="current-datetime" id="currentDateTime"></div>
                <button class="dark-toggle" id="darkModeToggle" onclick="toggleDarkMode()">🌙 Dark Mode</button>
                <div class="profile-btn">
                    <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                    <div><?php echo htmlspecialchars($user_name); ?></div>
                </div>
            </div>
        </header>

        <div class="dashboard-container">
            <!-- User Card -->
            <div class="user-card">
                <div class="user-info">
                    <h2>Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋</h2>
                    <div class="user-details">
                        <?php if($user['matric_number']): ?>
                            <div class="detail-item highlight">
                                🎓 <strong>Matric No:</strong> <?php echo htmlspecialchars($user['matric_number']); ?>
                                <button class="copy-btn" onclick="copyToClipboard('<?php echo $user['matric_number']; ?>')">📋 Copy</button>
                            </div>
                        <?php endif; ?>
                        <div class="detail-item highlight">
                            🏛️ <strong>Department:</strong> <?php echo htmlspecialchars($user['dept_name'] ?? 'Faculty of Computing'); ?>
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($user['dept_name'] ?? 'Faculty of Computing'); ?>')">📋 Copy</button>
                        </div>
                        <?php if($user['level_name']): ?>
                            <div class="detail-item highlight">
                                📚 <strong>Level:</strong> <?php echo htmlspecialchars($user['level_name']); ?>
                                <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($user['level_name']); ?>')">📋 Copy</button>
                            </div>
                        <?php endif; ?>
                        <div class="detail-item highlight">
                            📧 <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo $user['email']; ?>')">📋 Copy</button>
                        </div>
                    </div>
                </div>
                <div>
                    <span class="role-badge <?php echo $role_class; ?>"><?php echo $role_display; ?></span>
                </div>
                <div class="quick-actions">
                    <button class="quick-btn" onclick="window.open('talk.php', '_blank')">💬 New Chat</button>
                    <button class="quick-btn" onclick="window.location.href='announcements.php'">📢 Announcements</button>
                    <button class="quick-btn" onclick="window.location.href='materials.php'">📚 Course Materials</button>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="dashboard-two-column" style="margin-bottom: 24px;">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $has_courses ? mysqli_num_rows($courses) : ($user_role == 'lecturer' ? $teaching_courses : '0'); ?></div>
                    <div class="stats-label"><?php echo ($user_role == 'lecturer') ? '👨‍🏫 Teaching Courses' : '📚 Enrolled Courses'; ?></div>
                </div>
                <div class="stats-card">
                    <div class="stats-number"><?php echo mysqli_num_rows($assignments); ?></div>
                    <div class="stats-label">📝 Pending Assignments</div>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="dashboard-two-column">
                <!-- Recent Announcements -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-title">📢 Recent Announcements</div>
                        <a href="announcements.php" class="card-link">View all →</a>
                    </div>
                    <?php if(mysqli_num_rows($announcements) > 0): ?>
                        <?php while($ann = mysqli_fetch_assoc($announcements)): ?>
                            <div class="announcement-item <?php echo $ann['is_emergency'] ? 'emergency' : ''; ?>">
                                <div class="announcement-title">
                                    <?php echo htmlspecialchars($ann['title']); ?>
                                    <?php if($ann['is_emergency']): ?> <span style="color:var(--emergency);">🔴</span><?php endif; ?>
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

                <!-- My Courses -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-title"><?php echo ($user_role == 'lecturer') ? '👨‍🏫 My Teaching Courses' : '📚 My Courses'; ?></div>
                        <a href="materials.php" class="card-link">View all →</a>
                    </div>
                    <?php if($has_courses): ?>
                        <?php while($course = mysqli_fetch_assoc($courses)): ?>
                            <div class="course-item">
                                <div>
                                    <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                    <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                </div>
                                <div class="course-status">In Progress</div>
                            </div>
                        <?php endwhile; ?>
                    <?php elseif($user_role == 'lecturer' && $teaching_courses > 0): ?>
                        <?php
                        $teaching_query = "SELECT c.* FROM courses c WHERE c.lecturer_id = $user_id LIMIT 5";
                        $teaching_result = mysqli_query($conn, $teaching_query);
                        while($course = mysqli_fetch_assoc($teaching_result)):
                        ?>
                            <div class="course-item">
                                <div>
                                    <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                    <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                </div>
                                <div class="course-status">Teaching</div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">📚 No courses <?php echo ($user_role == 'lecturer') ? 'to teach' : 'enrolled'; ?> yet</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Assignments -->
            <div class="full-width-card">
                <div class="card-header">
                    <div class="card-title">📝 Pending Assignments</div>
                    <a href="assignments.php" class="card-link">View all →</a>
                </div>
                <?php if($has_assignments): ?>
                    <?php while($assign = mysqli_fetch_assoc($assignments)): ?>
                        <div class="assignment-item">
                            <div>
                                <div class="assignment-title"><?php echo htmlspecialchars($assign['title']); ?></div>
                                <div class="assignment-course"><?php echo htmlspecialchars($assign['course_code']); ?> - <?php echo htmlspecialchars($assign['course_name']); ?></div>
                            </div>
                            <div class="assignment-due">Due: <?php echo date('M d, Y g:i A', strtotime($assign['due_date'])); ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">✅ No pending assignments. Good job!</div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Eruda Button -->
<button class="eruda-toggle" onclick="toggleEruda()">🐞</button>

<script>
    // Simple function to toggle Eruda (will be replaced once loaded)
    function toggleEruda() {
        if (typeof eruda !== 'undefined') {
            eruda.toggle();
        } else {
            alert('Eruda loading... Please wait a moment');
        }
    }
    
    // Load Eruda properly
    (function() {
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/eruda';
        script.onload = function() {
            eruda.init();
            console.log('Eruda initialized successfully');
            if (/Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                eruda.show();
            }
        };
        script.onerror = function() {
            console.log('Eruda failed to load - check internet connection');
        };
        document.head.appendChild(script);
    })();
    
    // Push Notification Test Functions (without Eruda dependency)
    window.testNotification = function() {
        if (Notification.permission === 'granted') {
            new Notification('🎓 FoC Connect Test', {
                body: 'Push notifications are working on your device!',
                icon: '/foc-connect/assets/icons/icon-192x192.png',
                vibrate: [200, 100, 200]
            });
            alert('✅ Test notification sent! Check your notifications.');
        } else {
            alert('❌ Notification permission not granted. Run requestPermission() first.');
        }
    };
    
    window.requestPermission = async function() {
        const result = await Notification.requestPermission();
        if (result === 'granted') {
            alert('✅ Permission granted! You can now receive notifications.');
            testNotification();
        } else {
            alert('❌ Permission denied. Please enable notifications in browser settings.');
        }
    };
    
    window.checkPushStatus = function() {
        let status = 'Service Worker: ' + ('serviceWorker' in navigator ? '✅' : '❌') + '\n';
        status += 'Push Manager: ' + ('PushManager' in window ? '✅' : '❌') + '\n';
        status += 'Notification Permission: ' + Notification.permission + '\n';
        alert(status);
    };
    
    // Update date/time
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        document.getElementById('currentDateTime').innerHTML = `📅 ${now.toLocaleDateString('en-US', options)}`;
    }
    updateDateTime();
    setInterval(updateDateTime, 60000);
    
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }
    
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('📋 Copied: ' + text);
        });
    }
    
    function toggleDarkMode() {
        document.body.classList.toggle('dark');
        const toggle = document.getElementById('darkModeToggle');
        if (document.body.classList.contains('dark')) {
            toggle.innerHTML = '☀️ Light Mode';
            localStorage.setItem('darkMode', 'enabled');
        } else {
            toggle.innerHTML = '🌙 Dark Mode';
            localStorage.setItem('darkMode', 'disabled');
        }
    }
    
    // Register Service Worker for PWA
    if('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('Service Worker registered:', reg))
                .catch(err => console.log('Service Worker failed:', err));
        });
    }
    
    if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark');
        document.getElementById('darkModeToggle').innerHTML = '☀️ Light Mode';
    }
    
    console.log('FoC Connect Dashboard Loaded');
    console.log('Commands: requestPermission(), testNotification(), checkPushStatus()');
</script>
</body>
</html>
