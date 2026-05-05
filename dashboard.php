<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];

// ====================== USER DATA ======================
$stmt = $conn->prepare("SELECT u.*, d.name as dept_name, d.code as dept_code, 
                        a.level_name 
                        FROM users u 
                        LEFT JOIN departments d ON u.department_id = d.id 
                        LEFT JOIN academic_levels a ON u.level_id = a.id
                        WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Role Mapping
$student_role_map = [
    'class_rep' => ['🟡 Class Representative', 'class-rep'],
    'financial_secretary' => ['💰 Financial Secretary', 'financial-sec'],
    'pro' => ['📢 Public Relations Officer (PRO)', 'pro'],
    'general_secretary' => ['📝 General Secretary', 'gen-sec'],
    'vice_president' => ['👑 Vice President', 'vp'],
    'departmental_president' => ['👔 Departmental President', 'dept-pres']
];

$staff_role_map = [
    'lecturer' => ['👨‍🏫 Lecturer', 'lecturer'],
    'level_coordinator' => ['📚 Level Coordinator', 'level-coord'],
    'dept_exam_officer' => ['📋 Departmental Exam Officer', 'dept-exam'],
    'hod' => ['👔 Head of Department', 'hod'],
    'faculty_exam_officer' => ['📋 Faculty Exam Officer', 'faculty-exam'],
    'dean' => ['🎓 Dean', 'dean']
];

$role_display = '👤 Student';
$role_class   = 'student';

if ($user_role === 'student' && !empty($user['student_role']) && isset($student_role_map[$user['student_role']])) {
    $role_display = $student_role_map[$user['student_role']][0];
    $role_class   = $student_role_map[$user['student_role']][1];
} elseif (isset($staff_role_map[$user_role])) {
    $role_display = $staff_role_map[$user_role][0];
    $role_class   = $staff_role_map[$user_role][1];
}

// Unread Notifications
$stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread'];
$stmt->close();

// ====================== DASHBOARD DATA ======================
$dept_id  = !empty($user['department_id']) ? (int)$user['department_id'] : 0;
$level_id = !empty($user['level_id']) ? (int)$user['level_id'] : 0;

// Announcements
$stmt = $conn->prepare("SELECT a.*, u.name as author_name 
                        FROM announcements a
                        JOIN users u ON a.from_user_id = u.id
                        WHERE (a.department_id IS NULL OR a.department_id = ?)
                          AND (a.level_id IS NULL OR a.level_id = ?)
                        ORDER BY a.sent_at DESC LIMIT 5");
$stmt->bind_param("ii", $dept_id, $level_id);
$stmt->execute();
$announcements = $stmt->get_result();
$stmt->close();

// Courses for Students
$courses_result = null;
if ($user_role !== 'lecturer') {
    $stmt = $conn->prepare("SELECT c.*, e.enrolled_at 
                            FROM courses c
                            JOIN enrollments e ON c.id = e.course_id
                            WHERE e.student_id = ? LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $courses_result = $stmt->get_result();
    $stmt->close();
}

// Teaching Courses for Lecturers
$teaching_courses = 0;
if ($user_role === 'lecturer') {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM courses WHERE lecturer_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $teaching_courses = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}

// Pending Assignments
$stmt = $conn->prepare("SELECT a.*, c.course_code, c.course_name 
                        FROM assignments a
                        JOIN courses c ON a.course_id = c.id
                        JOIN enrollments e ON c.id = e.course_id
                        WHERE e.student_id = ? AND a.due_date > NOW()
                        ORDER BY a.due_date ASC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$assignments = $stmt->get_result();
$stmt->close();

// Materials Count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM course_materials cm
                        JOIN courses c ON cm.course_id = c.id
                        JOIN enrollments e ON c.id = e.course_id
                        WHERE e.student_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$materials_count = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
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

        /* All your original CSS continues here... */
        .sidebar { width: 280px; background: var(--sidebar-bg); color: var(--sidebar-text); position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; z-index: 100; }
        .main-content { margin-left: 280px; min-height: 100vh; width: calc(100% - 280px); }
        .user-card, .dashboard-card, .full-width-card, .stats-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 20px; padding: 20px; }
        .role-badge { padding: 8px 20px; border-radius: 40px; font-weight: 600; }
        /* ... Paste the rest of your original CSS styles below if any part is missing ... */

        /* I recommend you copy-paste the full <style> block from your original code here to ensure nothing is missing. */
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
            <a href="messaging.php" class="nav-item">
                <span class="nav-icon">💬</span>
                <span class="nav-text">Messages</span>
                <?php if($unread_count > 0): ?>
                    <span class="nav-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <!-- Rest of your sidebar links -->
        </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Your header, user card, stats, announcements, courses, assignments sections go here using the clean structure from before -->
    </main>
</div>

<script>
    // Your JavaScript code
</script>
</body>
</html>
