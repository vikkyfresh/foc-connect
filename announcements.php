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

// First, let's check if announcements table has data
// Run this query to debug - uncomment to see what's happening
// $check = mysqli_query($conn, "SELECT COUNT(*) as total FROM announcements");
// $count = mysqli_fetch_assoc($check);
// echo "<!-- Total announcements in DB: " . $count['total'] . " -->";

// Get user's department and level
$user_query = "SELECT department_id, level_id FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

$dept_id = $user['department_id'];
$level_id = $user['level_id'];

// Get announcements - simpler query first
$announcements_query = "SELECT a.*, u.name as author_name, d.name as dept_name
                        FROM announcements a
                        LEFT JOIN users u ON a.from_user_id = u.id
                        LEFT JOIN departments d ON a.department_id = d.id
                        WHERE (a.department_id IS NULL OR a.department_id = $dept_id)
                        AND (a.level_id IS NULL OR a.level_id = $level_id)
                        ORDER BY a.sent_at DESC";
$announcements = mysqli_query($conn, $announcements_query);

// If no announcements, try getting all announcements
if(mysqli_num_rows($announcements) == 0) {
    $announcements_query = "SELECT a.*, u.name as author_name, d.name as dept_name
                            FROM announcements a
                            LEFT JOIN users u ON a.from_user_id = u.id
                            LEFT JOIN departments d ON a.department_id = d.id
                            ORDER BY a.sent_at DESC LIMIT 10";
    $announcements = mysqli_query($conn, $announcements_query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Announcements - FoC Connect</title>
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
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #0F4C3A;
            color: #E8F5E9;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 100;
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
            color: #E8F5E9;
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-item:hover, .nav-item.active {
            background: #2E7D64;
        }

        .nav-icon {
            font-size: 22px;
            width: 28px;
        }

        .nav-text {
            font-size: 15px;
            flex: 1;
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
            background: white;
            border-bottom: 1px solid #E2E8F0;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-title {
            font-size: 20px;
            font-weight: 600;
            color: #1A2E28;
        }

        .current-datetime {
            font-size: 13px;
            color: #6B7E78;
            background: #F5F7F6;
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
            background: #F5F7F6;
        }

        .profile-avatar {
            width: 36px;
            height: 36px;
            background: #2E7D64;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Dashboard Container */
        .dashboard-container {
            padding: 24px;
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Announcement Cards */
        .announcement-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid #E8EDEC;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .announcement-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .emergency {
            border-left: 4px solid #E53E3E;
            background: #FFF5F5;
        }

        .announcement-title {
            font-size: 18px;
            font-weight: 600;
            color: #1A2E28;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .emergency-badge {
            background: #E53E3E;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .announcement-meta {
            font-size: 13px;
            color: #8A9B97;
            margin-bottom: 14px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        .announcement-message {
            font-size: 15px;
            color: #4A5568;
            line-height: 1.5;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2E7D64;
            text-decoration: none;
            font-size: 14px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            border: 1px solid #E8EDEC;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .empty-state-text {
            color: #8A9B97;
            font-size: 16px;
        }

        .empty-state-small {
            color: #BCC7C4;
            font-size: 13px;
            margin-top: 8px;
        }

        /* Responsive */
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
            .hamburger {
                display: block;
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
            color: #1A2E28;
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
            <a href="dashboard.php" class="nav-item">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="chat.php" class="nav-item">
                <span class="nav-icon">💬</span>
                <span class="nav-text">Chat</span>
            </a>
            <a href="announcements.php" class="nav-item active">
                <span class="nav-icon">📢</span>
                <span class="nav-text">Announcements</span>
            </a>
            <a href="materials.php" class="nav-item">
                <span class="nav-icon">📚</span>
                <span class="nav-text">Course Materials</span>
            </a>
            <a href="assignments.php" class="nav-item">
                <span class="nav-icon">📝</span>
                <span class="nav-text">Assignments</span>
            </a>
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
                <div class="page-title">Announcements</div>
            </div>
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div class="current-datetime" id="currentDateTime"></div>
                <div class="profile-btn">
                    <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                    <div><?php echo htmlspecialchars($user_name); ?></div>
                </div>
            </div>
        </header>

        <div class="dashboard-container">
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

            <?php if(mysqli_num_rows($announcements) > 0): ?>
                <?php while($ann = mysqli_fetch_assoc($announcements)): ?>
                    <div class="announcement-card <?php echo $ann['is_emergency'] ? 'emergency' : ''; ?>">
                        <div class="announcement-title">
                            <?php echo htmlspecialchars($ann['title']); ?>
                            <?php if($ann['is_emergency']): ?>
                                <span class="emergency-badge">🚨 EMERGENCY</span>
                            <?php endif; ?>
                        </div>
                        <div class="announcement-meta">
                            <span>👤 By: <?php echo htmlspecialchars($ann['author_name']); ?></span>
                            <span>📅 Date: <?php echo date('F d, Y', strtotime($ann['sent_at'])); ?></span>
                            <span>🕐 Time: <?php echo date('g:i A', strtotime($ann['sent_at'])); ?></span>
                            <?php if($ann['dept_name']): ?>
                                <span>🏛️ Department: <?php echo htmlspecialchars($ann['dept_name']); ?></span>
                            <?php else: ?>
                                <span>🏛️ Faculty-wide</span>
                            <?php endif; ?>
                        </div>
                        <div class="announcement-message">
                            <?php echo nl2br(htmlspecialchars($ann['message'])); ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <div class="empty-state-text">No announcements yet</div>
                    <div class="empty-state-small">Check back later for updates from your department</div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
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
</script>
</body>
</html>