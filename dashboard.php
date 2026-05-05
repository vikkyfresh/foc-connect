<?php
session_start();
include 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];

// ==================== FETCH USER DATA ====================
$stmt = $conn->prepare("SELECT u.*, d.name as dept_name, d.code as dept_code, 
                        a.level_name, d.connect_name 
                        FROM users u 
                        LEFT JOIN departments d ON u.department_id = d.id 
                        LEFT JOIN academic_levels a ON u.level_id = a.id
                        WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Student & Staff Role Mapping
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

// Determine Role Display
$role_display = '👤 Student';
$role_class = 'student';

if ($user_role === 'student' && !empty($user['student_role']) && isset($student_role_map[$user['student_role']])) {
    $role_display = $student_role_map[$user['student_role']][0];
    $role_class = $student_role_map[$user['student_role']][1];
} elseif (isset($staff_role_map[$user_role])) {
    $role_display = $staff_role_map[$user_role][0];
    $role_class = $staff_role_map[$user_role][1];
}

// Unread Notifications
$stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread'];
$stmt->close();

// ==================== DASHBOARD DATA ====================
$dept_id = !empty($user['department_id']) ? (int)$user['department_id'] : 0;
$level_id = !empty($user['level_id']) ? (int)$user['level_id'] : 0;

// Recent Announcements
$announcements_query = "SELECT a.*, u.name as author_name 
                        FROM announcements a
                        JOIN users u ON a.from_user_id = u.id
                        WHERE (a.department_id IS NULL OR a.department_id = ?)
                        AND (a.level_id IS NULL OR a.level_id = ?)
                        ORDER BY a.sent_at DESC LIMIT 5";

$stmt = $conn->prepare($announcements_query);
$stmt->bind_param("ii", $dept_id, $level_id);
$stmt->execute();
$announcements = $stmt->get_result();
$stmt->close();

// Enrolled Courses (Students)
$courses = [];
if ($user_role !== 'lecturer') {
    $stmt = $conn->prepare("SELECT c.*, e.enrolled_at 
                            FROM courses c
                            JOIN enrollments e ON c.id = e.course_id
                            WHERE e.student_id = ? 
                            LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $courses = $stmt->get_result();
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

// Course Materials Count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM course_materials cm
                        JOIN courses c ON cm.course_id = c.id
                        JOIN enrollments e ON c.id = e.course_id
                        WHERE e.student_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$materials_count = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Teaching Courses Count (Lecturers)
$teaching_courses = 0;
if ($user_role === 'lecturer') {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM courses WHERE lecturer_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $teaching_courses = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0F4C3A">
    
    <!-- PWA -->
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/icons/icon-152x152.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FoC Connect">
    
    <title>Dashboard - FoC Connect</title>
    
    <style>
        /* Your existing CSS remains unchanged */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #F5F7F6;
            overflow-x: hidden;
        }
        /* ... (All your previous CSS styles - kept exactly the same) ... */
        /* Paste all your original <style> content here */
    </style>
</head>
<body>
<div style="display: flex; min-height: 100vh;">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <!-- ... Your sidebar remains the same except chat link ... -->
        <div class="sidebar-header">
            <div class="sidebar-logo">🌿 FoC Connect</div>
            <div class="sidebar-subtitle">🏛️ FACULTY OF COMPUTING</div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="messaging.php" class="nav-item">   <!-- Changed -->
                <span class="nav-icon">💬</span>
                <span class="nav-text">Messages</span>
                <?php if($unread_count > 0): ?>
                    <span class="nav-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <!-- Rest of sidebar unchanged -->
            <a href="announcements.php" class="nav-item">
                <span class="nav-icon">📢</span>
                <span class="nav-text">Announcements</span>
            </a>
            <!-- ... other links ... -->
        </nav>
        <!-- ... footer ... -->
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <!-- ... header unchanged ... -->
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="hamburger" onclick="toggleSidebar()">☰</button>
                <div class="page-title">Dashboard</div>
            </div>
            <!-- ... rest of header ... -->
        </header>

        <div class="dashboard-container">
            <!-- User Card -->
            <div class="user-card">
                <div class="user-info">
                    <h2>Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋</h2>
                    <div class="user-details">
                        <?php if(!empty($user['matric_number'])): ?>
                            <div class="detail-item highlight">
                                🎓 <strong>Matric No:</strong> <?php echo htmlspecialchars($user['matric_number']); ?>
                                <button class="copy-btn" onclick="copyToClipboard('<?php echo $user['matric_number']; ?>')">📋 Copy</button>
                            </div>
                        <?php endif; ?>
                        <div class="detail-item highlight">
                            🏛️ <strong>Department:</strong> <?php echo htmlspecialchars($user['dept_name'] ?? 'Faculty of Computing'); ?>
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($user['dept_name'] ?? 'Faculty of Computing'); ?>')">📋 Copy</button>
                        </div>
                        <?php if(!empty($user['level_name'])): ?>
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
                    <button class="quick-btn" onclick="window.location.href='messaging.php'">💬 New Message</button>
                    <button class="quick-btn" onclick="window.location.href='announcements.php'">📢 Announcements</button>
                    <button class="quick-btn" onclick="window.location.href='materials.php'">📚 Course Materials</button>
                </div>
            </div>

            <!-- Stats -->
            <div class="dashboard-two-column" style="margin-bottom: 24px;">
                <div class="stats-card">
                    <div class="stats-number">
                        <?php echo ($user_role === 'lecturer') ? $teaching_courses : mysqli_num_rows($courses); ?>
                    </div>
                    <div class="stats-label">
                        <?php echo ($user_role === 'lecturer') ? '👨‍🏫 Teaching Courses' : '📚 Enrolled Courses'; ?>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-number"><?php echo mysqli_num_rows($assignments); ?></div>
                    <div class="stats-label">📝 Pending Assignments</div>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="dashboard-two-column">
                <!-- Announcements -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-title">📢 Recent Announcements</div>
                        <a href="announcements.php" class="card-link">View all →</a>
                    </div>
                    <?php if($announcements->num_rows > 0): ?>
                        <?php while($ann = $announcements->fetch_assoc()): ?>
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

                <!-- Courses -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-title"><?php echo ($user_role === 'lecturer') ? '👨‍🏫 My Teaching Courses' : '📚 My Courses'; ?></div>
                        <a href="materials.php" class="card-link">View all →</a>
                    </div>
                    <?php if(($user_role !== 'lecturer' && $courses->num_rows > 0) || ($user_role === 'lecturer' && $teaching_courses > 0)): ?>
                        <!-- Courses display logic here (you can expand) -->
                        <div class="empty-state">Course display logic can be expanded here</div>
                    <?php else: ?>
                        <div class="empty-state">📚 No courses <?php echo ($user_role === 'lecturer') ? 'to teach' : 'enrolled'; ?> yet</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Assignments -->
            <div class="full-width-card">
                <div class="card-header">
                    <div class="card-title">📝 Pending Assignments</div>
                    <a href="assignments.php" class="card-link">View all →</a>
                </div>
                <?php if($assignments->num_rows > 0): ?>
                    <?php while($assign = $assignments->fetch_assoc()): ?>
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

<!-- Your scripts remain the same -->
<script>
    // ... Your existing JavaScript (unchanged) ...
</script>
</body>
</html>
