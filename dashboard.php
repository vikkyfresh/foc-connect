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

// ── Get full user profile ──
$user = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT u.*, d.name as dept_name, d.code as dept_code,
            a.level_name, d.connect_name
     FROM users u
     LEFT JOIN departments d ON u.department_id = d.id
     LEFT JOIN academic_levels a ON u.level_id = a.id
     WHERE u.id = $user_id LIMIT 1"
));

$dept_id  = (int)($user['department_id'] ?? 0);
$level_id = (int)($user['level_id'] ?? 0);

// ── Role display ──
$role_map = [
    'dean'                 => ['🎓 Dean',                  'dean'],
    'faculty_exam_officer' => ['📋 Faculty Exam Officer',  'faculty-exam'],
    'hod'                  => ['👔 Head of Department',    'hod'],
    'dept_exam_officer'    => ['📋 Dept. Exam Officer',    'dept-exam'],
    'level_coordinator'    => ['📚 Level Coordinator',     'level-coord'],
    'lecturer'             => ['👨‍🏫 Lecturer',             'lecturer'],
    'level_rep'            => ['🟡 Level Rep',             'class-rep'],
];
$student_role_map = [
    'course_rep'             => ['🟡 Course Rep',           'class-rep'],
    'asst_course_rep'        => ['🟡 Asst. Course Rep',     'class-rep'],
    'financial_secretary'    => ['💰 Financial Secretary',  'financial-sec'],
    'pro'                    => ['📢 PRO',                  'pro'],
    'general_secretary'      => ['📝 General Secretary',    'gen-sec'],
    'vice_president'         => ['👑 Vice President',       'vp'],
    'departmental_president' => ['👔 Dept. President',      'dept-pres'],
];

if ($user_role === 'student') {
    $sr = $user['student_role'] ?? 'student';
    [$role_display, $role_class] = $student_role_map[$sr] ?? ['👤 Student', 'student'];
} else {
    [$role_display, $role_class] = $role_map[$user_role] ?? ['👤 User', 'student'];
}

// ── Unread notifications ──
$unread = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as c FROM notifications WHERE user_id=$user_id AND is_read=0"
))['c'];

// ── Announcements (faculty-wide + dept + level) ──
$ann_where = "a.department_id IS NULL";
if ($dept_id)  $ann_where .= " OR a.department_id = $dept_id";
if ($level_id) $ann_where .= " OR a.level_id = $level_id";
$announcements = mysqli_query($conn,
    "SELECT a.*, u.name as author_name
     FROM announcements a JOIN users u ON a.from_user_id = u.id
     WHERE ($ann_where)
     ORDER BY a.is_emergency DESC, a.sent_at DESC LIMIT 6"
);

// ── Courses — stored in array ──
$courses_data = [];
if ($user_role === 'lecturer') {
    $r = mysqli_query($conn, "SELECT * FROM courses WHERE lecturer_id=$user_id ORDER BY course_code LIMIT 8");
} elseif ($dept_id && $level_id) {
    // Enrolled courses first, fall back to all courses for this dept+level
    $r = mysqli_query($conn,
        "SELECT DISTINCT c.* FROM courses c
         LEFT JOIN enrollments e ON c.id=e.course_id AND e.student_id=$user_id
         WHERE c.department_id=$dept_id AND c.level_id=$level_id
         ORDER BY e.id DESC, c.course_code ASC LIMIT 8"
    );
} elseif ($dept_id) {
    $r = mysqli_query($conn, "SELECT * FROM courses WHERE department_id=$dept_id ORDER BY level_id, course_code LIMIT 8");
} else {
    $r = mysqli_query($conn, "SELECT * FROM courses ORDER BY department_id, level_id, course_code LIMIT 8");
}
while ($row = mysqli_fetch_assoc($r)) $courses_data[] = $row;

// ── Assignments — stored in array ──
$assignments_data = [];
if ($dept_id && $level_id) {
    $r = mysqli_query($conn,
        "SELECT DISTINCT a.*, c.course_code, c.course_name
         FROM assignments a
         JOIN courses c ON a.course_id = c.id
         WHERE c.department_id=$dept_id AND c.level_id=$level_id
           AND a.due_date > NOW()
         ORDER BY a.due_date ASC LIMIT 6"
    );
    while ($row = mysqli_fetch_assoc($r)) $assignments_data[] = $row;
}

// ── Materials count ──
$materials_count = 0;
if ($dept_id && $level_id) {
    $materials_count = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as c FROM course_materials cm
         JOIN courses c ON cm.course_id=c.id
         WHERE c.department_id=$dept_id AND c.level_id=$level_id"
    ))['c'];
} elseif ($dept_id) {
    $materials_count = (int)mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as c FROM course_materials cm
         JOIN courses c ON cm.course_id=c.id
         WHERE c.department_id=$dept_id"
    ))['c'];
}

// ── Quick stats ──
$total_students = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as c FROM users WHERE role='student' AND is_active=1" . ($dept_id ? " AND department_id=$dept_id" : "")
))['c'];

$total_courses = count($courses_data);
$pending_assignments = count($assignments_data);
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
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: var(--main-bg); overflow-x: hidden; transition: background 0.3s; }

        :root {
            --sidebar-bg: #0F4C3A; --sidebar-hover: #2E7D64; --sidebar-text: #E8F5E9;
            --main-bg: #F5F7F6; --card-bg: #FFFFFF; --card-border: #E8EDEC;
            --text-primary: #1A2E28; --text-secondary: #6B7E78; --text-muted: #8A9B97;
            --btn-primary: #2E7D64; --emergency: #E53E3E;
            --border: #E2E8F0; --detail-bg: #F5F7F6;
        }
        body.dark {
            --main-bg: #1A2E28; --card-bg: #2D4A40; --card-border: #3A5A50;
            --text-primary: #F5F7F6; --text-secondary: #A8BFB8; --text-muted: #8A9B97;
            --btn-primary: #3A997A; --border: #3A5A50; --detail-bg: #3A5A50;
            --sidebar-bg: #08332A; --sidebar-hover: #1E5A48;
        }

        /* SIDEBAR */
        .sidebar {
            width: 270px; background: var(--sidebar-bg); color: var(--sidebar-text);
            position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto;
            z-index: 100; transition: transform 0.3s, background 0.3s;
            display: flex; flex-direction: column;
        }
        .sidebar-header { padding: 22px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo { font-size: 20px; font-weight: 700; }
        .sidebar-subtitle { font-size: 10px; opacity: 0.7; margin-top: 4px; letter-spacing: 0.5px; font-weight: 600; }
        .sidebar-nav { flex: 1; padding: 16px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 11px 14px; margin: 2px 0; border-radius: 12px; color: var(--sidebar-text); text-decoration: none; transition: 0.2s; font-size: 14px; }
        .nav-item:hover, .nav-item.active { background: var(--sidebar-hover); }
        .nav-icon { font-size: 19px; width: 24px; flex-shrink: 0; }
        .nav-badge { background: #E53E3E; color: white; font-size: 10px; padding: 2px 7px; border-radius: 20px; margin-left: auto; }
        .sidebar-footer { padding: 14px 16px; border-top: 1px solid rgba(255,255,255,0.1); }

        /* MAIN */
        .main { margin-left: 270px; min-height: 100vh; background: var(--main-bg); transition: background 0.3s; }
        .top-bar { background: var(--card-bg); border-bottom: 1px solid var(--border); padding: 13px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; flex-wrap: wrap; gap: 10px; transition: background 0.3s; }
        .page-title { font-size: 18px; font-weight: 600; color: var(--text-primary); }
        .top-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .datetime { font-size: 12px; color: var(--text-secondary); background: var(--detail-bg); padding: 5px 12px; border-radius: 40px; }
        .dark-btn { background: var(--detail-bg); border: 1px solid var(--border); border-radius: 40px; padding: 6px 14px; cursor: pointer; font-size: 12px; color: var(--text-primary); transition: all 0.2s; }
        .dark-btn:hover { background: var(--btn-primary); color: white; border-color: var(--btn-primary); }
        .profile-pill { display: flex; align-items: center; gap: 8px; background: var(--detail-bg); padding: 5px 12px 5px 5px; border-radius: 40px; }
        .profile-av { width: 32px; height: 32px; background: var(--btn-primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 12px; }
        .profile-name { font-size: 13px; color: var(--text-primary); font-weight: 500; }
        .hamburger { background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-primary); }

        /* CONTENT */
        .content { padding: 22px; }

        /* WELCOME CARD */
        .welcome-card { background: var(--card-bg); border-radius: 22px; padding: 26px; margin-bottom: 22px; border: 1px solid var(--card-border); box-shadow: 0 2px 8px rgba(0,0,0,0.04); position: relative; overflow: hidden; transition: all 0.3s; }
        .welcome-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #2E7D64, #C9A84C); }
        .welcome-card::after { content: ''; position: absolute; top: -60px; right: -60px; width: 200px; height: 200px; border-radius: 50%; background: radial-gradient(circle, rgba(46,125,100,0.06) 0%, transparent 70%); }
        .welcome-name { font-size: 22px; font-weight: 700; color: var(--text-primary); margin-bottom: 14px; }
        .info-pills { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .pill { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; background: var(--btn-primary); color: white; padding: 7px 14px; border-radius: 40px; font-weight: 500; }
        .pill-outline { background: var(--detail-bg); color: var(--text-primary); border: 1px solid var(--border); }
        .copy-btn { background: rgba(255,255,255,0.2); border: none; cursor: pointer; font-size: 11px; padding: 2px 7px; border-radius: 20px; color: white; margin-left: 4px; }
        .role-badge { display: inline-block; padding: 7px 18px; border-radius: 40px; font-size: 13px; font-weight: 600; margin-top: 4px; }
        .rb-student      { background: #2E7D64; color: white; }
        .rb-class-rep    { background: #D69E2E; color: #1A202C; }
        .rb-financial-sec{ background: #3182CE; color: white; }
        .rb-pro          { background: #805AD5; color: white; }
        .rb-gen-sec      { background: #DD6B20; color: white; }
        .rb-vp           { background: #E53E3E; color: white; }
        .rb-dept-pres    { background: #8B4513; color: white; }
        .rb-level-coord  { background: #6B46C1; color: white; }
        .rb-lecturer     { background: #3182CE; color: white; }
        .rb-dept-exam    { background: #00A3C4; color: white; }
        .rb-hod          { background: #DD6B20; color: white; }
        .rb-faculty-exam { background: #00A3C4; color: white; }
        .rb-dean         { background: #C53030; color: white; }
        .quick-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
        .qa-btn { background: var(--detail-bg); border: 1px solid var(--border); padding: 9px 18px; border-radius: 40px; cursor: pointer; font-size: 13px; font-weight: 500; color: var(--text-primary); transition: all 0.2s; text-decoration: none; display: inline-block; }
        .qa-btn:hover { background: var(--btn-primary); color: white; border-color: var(--btn-primary); }

        /* STATS ROW */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
        .stat-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 18px; padding: 18px 20px; text-align: center; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.06); }
        .stat-num { font-size: 32px; font-weight: 700; color: var(--btn-primary); }
        .stat-label { font-size: 12px; color: var(--text-secondary); margin-top: 4px; font-weight: 500; }

        /* GRID */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 18px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: all 0.3s; }
        .full { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 18px; padding: 18px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: all 0.3s; }
        .card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
        .card-title { font-size: 15px; font-weight: 600; color: var(--text-primary); }
        .card-link { font-size: 13px; color: var(--btn-primary); text-decoration: none; font-weight: 500; }
        .card-link:hover { text-decoration: underline; }

        /* LIST ITEMS */
        .list-item { padding: 11px 0; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; flex-wrap: wrap; }
        .list-item:last-child { border-bottom: none; padding-bottom: 0; }
        .item-title { font-weight: 600; font-size: 14px; color: var(--text-primary); }
        .item-sub { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .item-badge { font-size: 11px; padding: 3px 10px; border-radius: 20px; font-weight: 500; white-space: nowrap; }
        .badge-green  { background: rgba(46,125,100,0.12); color: var(--btn-primary); }
        .badge-red    { background: rgba(229,62,62,0.1);  color: #C53030; }
        .badge-orange { background: rgba(221,107,32,0.1); color: #C05621; }
        .emergency-item { border-left: 3px solid var(--emergency); padding-left: 10px; margin-left: -2px; background: rgba(229,62,62,0.04); border-radius: 0 8px 8px 0; }
        .empty-state { text-align: center; padding: 28px 16px; color: var(--text-muted); font-size: 13px; line-height: 1.8; }

        /* TOAST */
        .toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(16px); background: #1A2E28; color: white; padding: 9px 20px; border-radius: 40px; font-size: 13px; opacity: 0; transition: all 0.3s; z-index: 9999; pointer-events: none; }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

        /* RESPONSIVE */
        @media (max-width: 1024px) { .stats-row { grid-template-columns: repeat(2,1fr); } .two-col { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,0.2); }
            .main { margin-left: 0; }
            .stats-row { grid-template-columns: repeat(2,1fr); gap: 12px; }
            .content { padding: 14px; }
            .top-bar { padding: 11px 14px; }
            .datetime { display: none; }
        }
        @media (min-width: 769px) { .hamburger { display: none !important; } }
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 99; }
        .overlay.show { display: block; }
    </style>
</head>
<body>

<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">🌿 FoC Connect</div>
        <div class="sidebar-subtitle">🏛️ FACULTY OF COMPUTING</div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php"            class="nav-item active"><span class="nav-icon">📊</span> Dashboard</a>
        <a href="conversation-working.php" class="nav-item">
            <span class="nav-icon">💬</span> Chat
            <?php if($unread > 0): ?><span class="nav-badge"><?php echo $unread; ?></span><?php endif; ?>
        </a>
        <a href="announcements.php"  class="nav-item"><span class="nav-icon">📢</span> Announcements</a>
        <a href="materials.php"      class="nav-item"><span class="nav-icon">📚</span> Materials<?php if($materials_count > 0): ?><span class="nav-badge"><?php echo $materials_count; ?></span><?php endif; ?></a>
        <a href="assignments.php"    class="nav-item"><span class="nav-icon">📝</span> Assignments</a>
        <?php if(!empty($user['is_graduated']) && $user['is_graduated']): ?>
            <a href="alumni-directory.php" class="nav-item"><span class="nav-icon">🎓</span> Alumni</a>
            <a href="job-board.php"        class="nav-item"><span class="nav-icon">💼</span> Job Board</a>
            <a href="mentorship.php"       class="nav-item"><span class="nav-icon">🤝</span> Mentorship</a>
        <?php endif; ?>
        <?php if(in_array($user_role, ['dean','hod','lecturer','level_coordinator','faculty_exam_officer','dept_exam_officer'])): ?>
            <a href="moderation-panel.php" class="nav-item"><span class="nav-icon">🛡️</span> Moderation</a>
            <a href="progression.php"      class="nav-item"><span class="nav-icon">📈</span> Progression</a>
            <a href="admin-roles.php"      class="nav-item"><span class="nav-icon">👥</span> Role Management</a>
        <?php endif; ?>
        <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span> Settings</a>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span> Logout</a>
    </div>
</aside>

<!-- MAIN -->
<main class="main">
    <header class="top-bar">
        <div style="display:flex;align-items:center;gap:10px;">
            <button class="hamburger" onclick="toggleSidebar()">☰</button>
            <div class="page-title">Dashboard</div>
        </div>
        <div class="top-right">
            <div class="datetime" id="dt"></div>
            <button class="dark-btn" id="darkBtn" onclick="toggleDark()">🌙 Dark</button>
            <div class="profile-pill">
                <div class="profile-av"><?php echo strtoupper(substr($user_name,0,2)); ?></div>
                <span class="profile-name"><?php echo htmlspecialchars(explode(' ',$user_name)[0]); ?></span>
            </div>
        </div>
    </header>

    <div class="content">

        <!-- WELCOME CARD -->
        <div class="welcome-card">
            <div class="welcome-name">Welcome back, <?php echo htmlspecialchars(explode(' ',$user_name)[0]); ?>! 👋</div>
            <div class="info-pills">
                <?php if(!empty($user['matric_number'])): ?>
                    <div class="pill">🎓 <?php echo htmlspecialchars($user['matric_number']); ?><button class="copy-btn" onclick="copy('<?php echo htmlspecialchars($user['matric_number']); ?>')">📋</button></div>
                <?php endif; ?>
                <?php if(!empty($user['staff_id'])): ?>
                    <div class="pill">🪪 <?php echo htmlspecialchars($user['staff_id']); ?><button class="copy-btn" onclick="copy('<?php echo htmlspecialchars($user['staff_id']); ?>')">📋</button></div>
                <?php endif; ?>
                <div class="pill">🏛️ <?php echo htmlspecialchars($user['dept_name'] ?? 'Faculty of Computing'); ?></div>
                <?php if(!empty($user['level_name'])): ?>
                    <div class="pill">📚 <?php echo htmlspecialchars($user['level_name']); ?></div>
                <?php endif; ?>
                <div class="pill pill-outline">📧 <?php echo htmlspecialchars($user['email']); ?><button class="copy-btn" style="background:var(--detail-bg);color:var(--text-primary);" onclick="copy('<?php echo htmlspecialchars($user['email']); ?>')">📋</button></div>
            </div>
            <span class="role-badge rb-<?php echo $role_class; ?>"><?php echo $role_display; ?></span>
            <div class="quick-actions">
                <a href="conversation-working.php" class="qa-btn">💬 Open Chat</a>
                <a href="announcements.php"        class="qa-btn">📢 Announcements</a>
                <a href="materials.php"            class="qa-btn">📚 Materials</a>
                <a href="assignments.php"          class="qa-btn">📝 Assignments</a>
                <?php if($user_role === 'student'): ?>
                    <a href="job-board.php"    class="qa-btn">💼 Jobs</a>
                    <a href="mentorship.php"   class="qa-btn">🤝 Mentorship</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- STATS ROW -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-num"><?php echo $total_courses; ?></div>
                <div class="stat-label">📚 <?php echo $user_role === 'lecturer' ? 'Teaching Courses' : 'My Courses'; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?php echo $pending_assignments; ?></div>
                <div class="stat-label">📝 Pending Assignments</div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?php echo $materials_count; ?></div>
                <div class="stat-label">📁 Course Materials</div>
            </div>
            <div class="stat-card">
                <div class="stat-num"><?php echo $total_students; ?></div>
                <div class="stat-label">👥 <?php echo $dept_id ? 'Dept Students' : 'Total Students'; ?></div>
            </div>
        </div>

        <!-- TWO COLUMN -->
        <div class="two-col">

            <!-- ANNOUNCEMENTS -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title">📢 Announcements</div>
                    <a href="announcements.php" class="card-link">View all →</a>
                </div>
                <?php if(mysqli_num_rows($announcements) > 0): ?>
                    <?php while($ann = mysqli_fetch_assoc($announcements)): ?>
                        <div class="list-item <?php echo $ann['is_emergency'] ? 'emergency-item' : ''; ?>">
                            <div style="flex:1;">
                                <div class="item-title">
                                    <?php if($ann['is_emergency']): ?><span style="color:var(--emergency);">🔴 </span><?php endif; ?>
                                    <?php echo htmlspecialchars($ann['title']); ?>
                                </div>
                                <div class="item-sub">👤 <?php echo htmlspecialchars($ann['author_name']); ?> · <?php echo date('M d, g:i A', strtotime($ann['sent_at'])); ?></div>
                            </div>
                            <?php if($ann['is_emergency']): ?>
                                <span class="item-badge badge-red">Urgent</span>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">📭 No announcements yet</div>
                <?php endif; ?>
            </div>

            <!-- COURSES -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><?php echo $user_role === 'lecturer' ? '👨‍🏫 Teaching Courses' : '📚 My Courses'; ?></div>
                    <a href="materials.php" class="card-link">Materials →</a>
                </div>
                <?php if(count($courses_data) > 0): ?>
                    <?php foreach($courses_data as $c): ?>
                        <div class="list-item">
                            <div>
                                <div class="item-title"><?php echo htmlspecialchars($c['course_code']); ?></div>
                                <div class="item-sub"><?php echo htmlspecialchars($c['course_name']); ?></div>
                            </div>
                            <span class="item-badge badge-green"><?php echo $user_role === 'lecturer' ? 'Teaching' : 'Active'; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">📚 No courses found.<br>Contact your HOD if this is incorrect.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ASSIGNMENTS -->
        <div class="full">
            <div class="card-head">
                <div class="card-title">📝 Pending Assignments</div>
                <a href="assignments.php" class="card-link">View all →</a>
            </div>
            <?php if(count($assignments_data) > 0): ?>
                <?php foreach($assignments_data as $a): ?>
                    <?php
                    $due = strtotime($a['due_date']);
                    $diff = $due - time();
                    $urgency = $diff < 86400*2 ? 'badge-red' : ($diff < 86400*5 ? 'badge-orange' : 'badge-green');
                    $due_label = $diff < 86400 ? 'Due Today!' : ($diff < 86400*2 ? 'Due Tomorrow' : 'Due '.date('M d', $due));
                    ?>
                    <div class="list-item">
                        <div style="flex:1;">
                            <div class="item-title"><?php echo htmlspecialchars($a['title']); ?></div>
                            <div class="item-sub"><?php echo htmlspecialchars($a['course_code']); ?> — <?php echo htmlspecialchars($a['course_name']); ?></div>
                        </div>
                        <span class="item-badge <?php echo $urgency; ?>"><?php echo $due_label; ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">✅ No pending assignments. You're all caught up!</div>
            <?php endif; ?>
        </div>

        <!-- MATERIALS PREVIEW -->
        <?php
        $mats_preview = [];
        if ($dept_id && $level_id) {
            $mr = mysqli_query($conn,
                "SELECT cm.*, c.course_code FROM course_materials cm
                 JOIN courses c ON cm.course_id=c.id
                 WHERE c.department_id=$dept_id AND c.level_id=$level_id
                 ORDER BY cm.created_at DESC LIMIT 5"
            );
            while($row = mysqli_fetch_assoc($mr)) $mats_preview[] = $row;
        }
        ?>
        <?php if(count($mats_preview) > 0): ?>
        <div class="full">
            <div class="card-head">
                <div class="card-title">📁 Recent Course Materials</div>
                <a href="materials.php" class="card-link">View all →</a>
            </div>
            <?php foreach($mats_preview as $m): ?>
                <div class="list-item">
                    <div style="flex:1;">
                        <div class="item-title">📄 <?php echo htmlspecialchars($m['title']); ?></div>
                        <div class="item-sub"><?php echo htmlspecialchars($m['course_code']); ?> · <?php echo date('M d, Y', strtotime($m['created_at'])); ?></div>
                    </div>
                    <span class="item-badge badge-green">Available</span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div><!-- end content -->
</main>

<div class="toast" id="toast"></div>

<script>
    // Clock
    function tick() {
        const now = new Date();
        document.getElementById('dt').textContent = '📅 ' + now.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric'}) + ' ' + now.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
    }
    tick(); setInterval(tick, 30000);

    // Sidebar
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('show'); }
    function closeSidebar()  { document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('show'); }

    // Copy
    function copy(t) {
        navigator.clipboard.writeText(t).then(() => toast('📋 Copied: ' + t)).catch(() => toast('❌ Copy failed'));
    }
    function toast(msg) {
        const el = document.getElementById('toast');
        el.textContent = msg; el.classList.add('show');
        setTimeout(() => el.classList.remove('show'), 2500);
    }

    // Dark mode
    function toggleDark() {
        document.body.classList.toggle('dark');
        const on = document.body.classList.contains('dark');
        document.getElementById('darkBtn').textContent = on ? '☀️ Light' : '🌙 Dark';
        localStorage.setItem('dm', on ? '1' : '0');
    }
    if (localStorage.getItem('dm') === '1') { document.body.classList.add('dark'); document.getElementById('darkBtn').textContent = '☀️ Light'; }

    // PWA
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').catch(()=>{});
    }
</script>
</body>
</html>
