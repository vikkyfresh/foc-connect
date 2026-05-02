<?php
session_start();
include 'includes/db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Get user's department
$user_query = "SELECT department_id FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$current_user = mysqli_fetch_assoc($user_result);
$dept_id = $current_user['department_id'];

// Get department name
$dept_query = "SELECT name FROM departments WHERE id = $dept_id";
$dept_result = mysqli_query($conn, $dept_query);
$dept = mysqli_fetch_assoc($dept_result);

// Get all leaders in department
$leaders_query = "SELECT u.id, u.name, u.email, u.matric_number, u.student_role, u.role as user_role,
                         a.level_name
                  FROM users u
                  LEFT JOIN academic_levels a ON u.level_id = a.id
                  WHERE u.department_id = $dept_id 
                  AND (u.student_role != 'student' OR u.role IN ('lecturer', 'hod', 'level_coordinator', 'dept_exam_officer', 'dean'))
                  ORDER BY FIELD(u.student_role, 'departmental_president', 'vice_president', 'general_secretary', 'financial_secretary', 'pro', 'class_rep'), u.name";
$leaders = mysqli_query($conn, $leaders_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Leaders - FoC Connect</title>
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
        .sidebar-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.1); margin-top: auto; }
        
        .main-content { margin-left: 280px; min-height: 100vh; width: calc(100% - 280px); }
        .top-header { background: white; border-bottom: 1px solid #E2E8F0; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 20px; font-weight: 600; color: #1A2E28; }
        .profile-btn { display: flex; align-items: center; gap: 10px; background: #F5F7F6; padding: 6px 12px; border-radius: 40px; }
        .profile-avatar { width: 36px; height: 36px; background: #2E7D64; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        
        .dashboard-container { padding: 24px; max-width: 1000px; margin: 0 auto; }
        .card { background: white; border-radius: 20px; padding: 24px; margin-bottom: 24px; border: 1px solid #E8EDEC; }
        .card-title { font-size: 18px; font-weight: 600; color: #1A2E28; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #E8EDEC; }
        
        .leader-card { background: #F5F7F6; border-radius: 16px; padding: 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .leader-name { font-weight: 600; color: #1A2E28; font-size: 16px; }
        .leader-detail { font-size: 13px; color: #6B7E78; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge.student { background: #2E7D64; color: white; }
        .badge.class-rep { background: #D69E2E; color: #1A202C; }
        .badge.financial-sec { background: #3182CE; color: white; }
        .badge.pro { background: #805AD5; color: white; }
        .badge.gen-sec { background: #DD6B20; color: white; }
        .badge.vp { background: #E53E3E; color: white; }
        .badge.dept-pres { background: #8B4513; color: white; }
        .badge.level-coord { background: #6B46C1; color: white; }
        .badge.lecturer { background: #3182CE; color: white; }
        .badge.dept-exam { background: #00A3C4; color: white; }
        .badge.hod { background: #DD6B20; color: white; }
        
        .back-link { display: inline-block; margin-bottom: 20px; color: #2E7D64; text-decoration: none; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; }
            .sidebar { transform: translateX(-100%); }
        }
    </style>
</head>
<body>
<div style="display: flex; min-height: 100vh;">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">🌿 FoC Connect</div>
            <div class="sidebar-subtitle">🏛️ FACULTY OF COMPUTING</div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span><span class="nav-text">Dashboard</span></a>
            <a href="admin-roles.php" class="nav-item"><span class="nav-icon">👥</span><span class="nav-text">Role Management</span></a>
            <a href="view-leaders.php" class="nav-item active"><span class="nav-icon">🏆</span><span class="nav-text">View Leaders</span></a>
            <a href="chat.php" class="nav-item"><span class="nav-icon">💬</span><span class="nav-text">Chat</span></a>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="page-title">🏆 Department Leaders</div>
            <div class="profile-btn">
                <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                <div><?php echo htmlspecialchars($user_name); ?></div>
            </div>
        </header>

        <div class="dashboard-container">
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
            
            <div class="card">
                <div class="card-title">📌 <?php echo htmlspecialchars($dept['name']); ?> Department Leadership</div>
                
                <?php if(mysqli_num_rows($leaders) > 0): ?>
                    <?php while($leader = mysqli_fetch_assoc($leaders)): 
                        $role_display = '';
                        $badge_class = '';
                        if($leader['student_role'] != 'student') {
                            $role_map = [
                                'class_rep' => ['🟡 Class Representative', 'class-rep'],
                                'financial_secretary' => ['💰 Financial Secretary', 'financial-sec'],
                                'pro' => ['📢 Public Relations Officer (PRO)', 'pro'],
                                'general_secretary' => ['📝 General Secretary', 'gen-sec'],
                                'vice_president' => ['👑 Vice President', 'vp'],
                                'departmental_president' => ['👔 Departmental President', 'dept-pres']
                            ];
                            $role_display = $role_map[$leader['student_role']][0];
                            $badge_class = $role_map[$leader['student_role']][1];
                        } else {
                            $role_map = [
                                'lecturer' => ['👨‍🏫 Lecturer', 'lecturer'],
                                'level_coordinator' => ['📚 Level Coordinator', 'level-coord'],
                                'dept_exam_officer' => ['📋 Departmental Exam Officer', 'dept-exam'],
                                'hod' => ['👔 Head of Department (HOD)', 'hod']
                            ];
                            $role_display = $role_map[$leader['user_role']][0];
                            $badge_class = $role_map[$leader['user_role']][1];
                        }
                    ?>
                        <div class="leader-card">
                            <div>
                                <div class="leader-name"><?php echo htmlspecialchars($leader['name']); ?></div>
                                <div class="leader-detail">
                                    <?php echo htmlspecialchars($leader['matric_number'] ?? $leader['email']); ?>
                                    <?php if($leader['level_name']): ?> • <?php echo $leader['level_name']; ?><?php endif; ?>
                                </div>
                            </div>
                            <div><span class="badge <?php echo $badge_class; ?>"><?php echo $role_display; ?></span></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #8A9B97; padding: 40px;">No leaders assigned yet. Contact your HOD.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>