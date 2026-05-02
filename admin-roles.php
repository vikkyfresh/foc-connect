<?php
session_start();
include 'includes/db.php';

// Check if user is logged in and has admin access
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];

// Only HOD, Dean, and Admin can access this page
if(!in_array($user_role, ['hod', 'dean', 'admin'])) {
    header("Location: dashboard.php");
    exit();
}

// Get user's department
$user_query = "SELECT department_id FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$current_user = mysqli_fetch_assoc($user_result);
$dept_id = $current_user['department_id'];

// Handle role assignment
$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_role'])) {
    $target_user_id = intval($_POST['user_id']);
    $new_student_role = $_POST['student_role'];
    $new_role = $_POST['role'];
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    
    // Get current roles
    $check_query = "SELECT role, student_role FROM users WHERE id = $target_user_id";
    $check_result = mysqli_query($conn, $check_query);
    $current_roles = mysqli_fetch_assoc($check_result);
    
    $old_role = $current_roles['role'];
    $old_student_role = $current_roles['student_role'];
    
    // Update user
    if($new_student_role) {
        $update = "UPDATE users SET student_role = '$new_student_role' WHERE id = $target_user_id";
        mysqli_query($conn, $update);
    }
    
    if($new_role && $new_role != 'student') {
        $update = "UPDATE users SET role = '$new_role' WHERE id = $target_user_id";
        mysqli_query($conn, $update);
    }
    
    // Log the assignment
    $log_query = "INSERT INTO role_assignments (user_id, role_type, old_role, new_role, assigned_by, reason) 
                  VALUES ($target_user_id, 'student_role', '$old_student_role', '$new_student_role', $user_id, '$reason')";
    mysqli_query($conn, $log_query);
    
    $message = "✅ Role assigned successfully!";
}

// Handle role removal
if(isset($_GET['remove_role']) && isset($_GET['user_id'])) {
    $remove_user = intval($_GET['user_id']);
    $remove_type = $_GET['remove_type'];
    
    if($remove_type == 'student_role') {
        mysqli_query($conn, "UPDATE users SET student_role = 'student' WHERE id = $remove_user");
    } elseif($remove_type == 'role') {
        mysqli_query($conn, "UPDATE users SET role = 'student' WHERE id = $remove_user");
    }
    
    $message = "✅ Role removed successfully!";
}

// Get all students in the department
$students_query = "SELECT u.id, u.name, u.email, u.matric_number, u.level_id, 
                          a.level_name, u.student_role, u.role as user_role
                   FROM users u
                   LEFT JOIN academic_levels a ON u.level_id = a.id
                   WHERE u.department_id = $dept_id AND u.role = 'student'
                   ORDER BY u.name";
$students = mysqli_query($conn, $students_query);

// Get all staff in the department
$staff_query = "SELECT u.id, u.name, u.email, u.role, u.staff_id
                FROM users u
                WHERE u.department_id = $dept_id AND u.role IN ('lecturer', 'hod', 'level_coordinator', 'dept_exam_officer')
                ORDER BY u.role, u.name";
$staff = mysqli_query($conn, $staff_query);

// Get current leaders
$leaders_query = "SELECT u.id, u.name, u.email, u.matric_number, u.student_role, u.role as user_role,
                         a.level_name
                  FROM users u
                  LEFT JOIN academic_levels a ON u.level_id = a.id
                  WHERE u.department_id = $dept_id 
                  AND (u.student_role != 'student' OR u.role IN ('lecturer', 'hod', 'level_coordinator', 'dept_exam_officer'))
                  ORDER BY FIELD(u.student_role, 'departmental_president', 'vice_president', 'general_secretary', 'financial_secretary', 'pro', 'class_rep'), u.name";
$leaders = mysqli_query($conn, $leaders_query);

// Get role assignment history
$history_query = "SELECT ra.*, u.name as user_name, a.name as assigned_by_name
                  FROM role_assignments ra
                  JOIN users u ON ra.user_id = u.id
                  JOIN users a ON ra.assigned_by = a.id
                  WHERE u.department_id = $dept_id
                  ORDER BY ra.created_at DESC LIMIT 20";
$history = mysqli_query($conn, $history_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Role Management | FoC Connect</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #F5F7F6; }
        
        /* Sidebar */
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
        
        /* Main Content */
        .main-content { margin-left: 280px; min-height: 100vh; width: calc(100% - 280px); }
        .top-header { background: white; border-bottom: 1px solid #E2E8F0; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 20px; font-weight: 600; color: #1A2E28; }
        .profile-btn { display: flex; align-items: center; gap: 10px; background: #F5F7F6; padding: 6px 12px; border-radius: 40px; }
        .profile-avatar { width: 36px; height: 36px; background: #2E7D64; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        
        /* Content */
        .dashboard-container { padding: 24px; max-width: 1400px; margin: 0 auto; }
        .card { background: white; border-radius: 20px; padding: 24px; margin-bottom: 24px; border: 1px solid #E8EDEC; }
        .card-title { font-size: 18px; font-weight: 600; color: #1A2E28; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #E8EDEC; }
        
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: 600; color: #1A2E28; margin-bottom: 8px; font-size: 14px; }
        select, input, textarea { width: 100%; padding: 12px; border: 1px solid #E8EDEC; border-radius: 12px; font-size: 14px; font-family: inherit; }
        .btn { background: #2E7D64; color: white; padding: 12px 24px; border: none; border-radius: 40px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .btn:hover { background: #236753; }
        .btn-danger { background: #E53E3E; }
        .btn-danger:hover { background: #C53030; }
        
        .message { padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; }
        .message.success { background: #E8F5E9; color: #2E7D64; border: 1px solid #2E7D64; }
        .message.error { background: #FFF5F5; color: #E53E3E; border: 1px solid #E53E3E; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #E8EDEC; }
        .data-table th { background: #F5F7F6; font-weight: 600; color: #1A2E28; }
        
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
        .badge.dean { background: #C53030; color: white; }
        
        .role-remove { color: #E53E3E; text-decoration: none; font-size: 12px; margin-left: 8px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; }
            .sidebar { transform: translateX(-100%); }
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div style="display: flex; min-height: 100vh;">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">🌿 FoC Connect</div>
            <div class="sidebar-subtitle">🏛️ FACULTY OF COMPUTING</div>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span><span class="nav-text">Dashboard</span></a>
            <a href="admin-roles.php" class="nav-item active"><span class="nav-icon">👥</span><span class="nav-text">Role Management</span></a>
            <a href="view-leaders.php" class="nav-item"><span class="nav-icon">🏆</span><span class="nav-text">View Leaders</span></a>
            <a href="role-history.php" class="nav-item"><span class="nav-icon">📜</span><span class="nav-text">Role History</span></a>
            <a href="chat.php" class="nav-item"><span class="nav-icon">💬</span><span class="nav-text">Chat</span></a>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div class="page-title">👥 Role Management</div>
            <div class="profile-btn">
                <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                <div><?php echo htmlspecialchars($user_name); ?> (<?php echo ucfirst($user_role); ?>)</div>
            </div>
        </header>

        <div class="dashboard-container">
            <?php if($message): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="grid-2">
                <!-- Assign Student Leadership Role -->
                <div class="card">
                    <div class="card-title">🎓 Assign Student Leadership Role</div>
                    <form method="POST">
                        <div class="form-group">
                            <label>Select Student</label>
                            <select name="user_id" required>
                                <option value="">-- Select Student --</option>
                                <?php while($student = mysqli_fetch_assoc($students)): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo $student['name']; ?> (<?php echo $student['matric_number']; ?>) - <?php echo $student['level_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assign Leadership Position</label>
                            <select name="student_role" required>
                                <option value="student">👤 Student (Default)</option>
                                <option value="class_rep">🟡 Class Representative</option>
                                <option value="financial_secretary">💰 Financial Secretary</option>
                                <option value="pro">📢 Public Relations Officer (PRO)</option>
                                <option value="general_secretary">📝 General Secretary</option>
                                <option value="vice_president">👑 Vice President</option>
                                <option value="departmental_president">👔 Departmental President</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Reason for Assignment</label>
                            <textarea name="reason" rows="2" placeholder="e.g., Elected on Dec 10, 2024, Appointed by HOD, etc." required></textarea>
                        </div>
                        <button type="submit" name="assign_role" class="btn">✅ Assign Role</button>
                    </form>
                </div>

                <!-- Assign Staff Role -->
                <div class="card">
                    <div class="card-title">👨‍🏫 Assign Staff Role</div>
                    <form method="POST">
                        <div class="form-group">
                            <label>Select Staff Member</label>
                            <select name="user_id" required>
                                <option value="">-- Select Staff --</option>
                                <?php 
                                $all_staff = mysqli_query($conn, "SELECT id, name, email FROM users WHERE department_id = $dept_id AND role = 'student'");
                                while($staff_member = mysqli_fetch_assoc($all_staff)): 
                                ?>
                                    <option value="<?php echo $staff_member['id']; ?>">
                                        <?php echo $staff_member['name']; ?> (<?php echo $staff_member['email']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assign Staff Position</label>
                            <select name="role" required>
                                <option value="lecturer">👨‍🏫 Lecturer</option>
                                <option value="level_coordinator">📚 Level Coordinator</option>
                                <option value="dept_exam_officer">📋 Departmental Exam Officer</option>
                                <?php if($user_role == 'dean'): ?>
                                    <option value="hod">👔 Head of Department (HOD)</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Reason for Assignment</label>
                            <textarea name="reason" rows="2" placeholder="e.g., Appointment letter dated..., Promotion, etc." required></textarea>
                        </div>
                        <button type="submit" name="assign_role" class="btn">✅ Assign Role</button>
                    </form>
                </div>
            </div>

            <!-- Current Leaders -->
            <div class="card">
                <div class="card-title">🏆 Current Department Leaders</div>
                <?php if(mysqli_num_rows($leaders) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>Name</th><th>Matric/Staff ID</th><th>Role</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php while($leader = mysqli_fetch_assoc($leaders)): 
                                $role_display = '';
                                if($leader['student_role'] != 'student') {
                                    $role_map = [
                                        'class_rep' => '🟡 Class Rep',
                                        'financial_secretary' => '💰 Fin. Secretary',
                                        'pro' => '📢 PRO',
                                        'general_secretary' => '📝 Gen. Secretary',
                                        'vice_president' => '👑 Vice President',
                                        'departmental_president' => '👔 Dept President'
                                    ];
                                    $role_display = $role_map[$leader['student_role']] ?? $leader['student_role'];
                                    $role_type = 'student_role';
                                } else {
                                    $role_map = [
                                        'lecturer' => '👨‍🏫 Lecturer',
                                        'level_coordinator' => '📚 Level Coordinator',
                                        'dept_exam_officer' => '📋 Dept Exam Officer',
                                        'hod' => '👔 HOD'
                                    ];
                                    $role_display = $role_map[$leader['user_role']] ?? $leader['user_role'];
                                    $role_type = 'role';
                                }
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($leader['name']); ?></td>
                                    <td><?php echo htmlspecialchars($leader['matric_number'] ?? $leader['email']); ?></td>
                                    <td><span class="badge <?php echo str_replace('_', '-', $leader['student_role'] != 'student' ? $leader['student_role'] : $leader['user_role']); ?>"><?php echo $role_display; ?></span></td>
                                    <td><a href="?remove_role=1&user_id=<?php echo $leader['id']; ?>&remove_type=<?php echo $role_type; ?>" class="role-remove" onclick="return confirm('Remove this role?')">Remove</a></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #8A9B97; text-align: center;">No leaders assigned yet.</p>
                <?php endif; ?>
            </div>

            <!-- Recent Role Assignment History -->
            <div class="card">
                <div class="card-title">📜 Recent Role Assignment History</div>
                <?php if(mysqli_num_rows($history) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>User</th><th>Old Role</th><th>New Role</th><th>Assigned By</th><th>Reason</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php while($log = mysqli_fetch_assoc($history)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($log['old_role']); ?></td>
                                    <td><?php echo htmlspecialchars($log['new_role']); ?></td>
                                    <td><?php echo htmlspecialchars($log['assigned_by_name']); ?></td>
                                    <td><?php echo htmlspecialchars($log['reason']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($log['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #8A9B97; text-align: center;">No role assignments recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>