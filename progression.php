<?php
session_start();
include 'includes/db.php';

// Check if user is logged in and has admin access
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['dean', 'hod', 'admin'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];

// Get user's department (if HOD)
$user_dept_id = null;
if($user_role == 'hod') {
    $dept_query = "SELECT department_id FROM users WHERE id = $user_id";
    $dept_result = mysqli_query($conn, $dept_query);
    $user_dept = mysqli_fetch_assoc($dept_result);
    $user_dept_id = $user_dept['department_id'];
}

$message = '';
$error = '';

// Function to create alumni year group
function createAlumniYearGroup($year, $conn) {
    $group_name = "🎓 Class of $year Alumni";
    $check = mysqli_query($conn, "SELECT id FROM chat_groups WHERE name = '$group_name'");
    if(mysqli_num_rows($check) == 0) {
        $insert = "INSERT INTO chat_groups (name, group_type, created_by) VALUES ('$group_name', 'alumni', 1)";
        mysqli_query($conn, $insert);
        return mysqli_insert_id($conn);
    } else {
        $row = mysqli_fetch_assoc($check);
        return $row['id'];
    }
}

// Handle single student promotion/graduation
if(isset($_GET['promote']) && isset($_GET['student_id'])) {
    $student_id = intval($_GET['student_id']);
    
    // Get current student info
    $student_query = "SELECT level_id, department_id FROM users WHERE id = $student_id";
    $student_result = mysqli_query($conn, $student_query);
    $student = mysqli_fetch_assoc($student_result);
    $current_level = $student['level_id'];
    
    // Check if student is in 400L (level_id = 4)
    if($current_level == 4) {
        // GRADUATE the student
        $year = date('Y');
        
        // Update user to graduated status
        $update = "UPDATE users SET is_graduated = 1, graduation_year = $year, level_id = NULL WHERE id = $student_id";
        
        if(mysqli_query($conn, $update)) {
            // Create or get alumni year group
            $alumni_group_id = createAlumniYearGroup($year, $conn);
            
            // Add student to alumni year group
            mysqli_query($conn, "INSERT IGNORE INTO group_members (user_id, group_id) VALUES ($student_id, $alumni_group_id)");
            
            // Also add to main alumni association
            $main_alumni = mysqli_query($conn, "SELECT id FROM chat_groups WHERE name = '🎓 FoC Alumni Association'");
            if(mysqli_num_rows($main_alumni) > 0) {
                $main_id = mysqli_fetch_assoc($main_alumni)['id'];
                mysqli_query($conn, "INSERT IGNORE INTO group_members (user_id, group_id) VALUES ($student_id, $main_id)");
            }
            
            // Log graduation
            $log = "INSERT INTO graduation_records (student_id, graduation_year) VALUES ($student_id, $year)";
            mysqli_query($conn, $log);
            
            // Log progression (use NULL for graduation since level 5 doesn't exist)
            $prog_log = "INSERT INTO progression_history (student_id, old_level_id, new_level_id, academic_session, processed_by) 
                        VALUES ($student_id, 4, NULL, '2024/2025', $user_id)";
            mysqli_query($conn, $prog_log);
            
            $message = "🎓 Student graduated successfully! Added to Class of $year Alumni group.";
        } else {
            $error = "❌ Graduation failed: " . mysqli_error($conn);
        }
    } else {
        // Regular promotion to next level
        $new_level = $current_level + 1;
        $update = "UPDATE users SET level_id = $new_level, progression_date = NOW() WHERE id = $student_id";
        
        if(mysqli_query($conn, $update)) {
            // Log progression
            $log = "INSERT INTO progression_history (student_id, old_level_id, new_level_id, academic_session, processed_by) 
                    VALUES ($student_id, $current_level, $new_level, '2024/2025', $user_id)";
            mysqli_query($conn, $log);
            
            $level_names = ['', '100L', '200L', '300L', '400L'];
            $message = "✅ Student promoted to " . $level_names[$new_level] . " successfully!";
        } else {
            $error = "❌ Promotion failed: " . mysqli_error($conn);
        }
    }
}

// Handle bulk progression
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_progress'])) {
    $from_level = intval($_POST['from_level']);
    $to_level = $from_level + 1;
    $dept_id = intval($_POST['department_id']);
    
    // Build department condition
    $dept_condition = ($dept_id > 0) ? "AND department_id = $dept_id" : "";
    
    if($to_level <= 4) {
        // Regular promotion
        $update = "UPDATE users SET level_id = $to_level, progression_date = NOW() 
                   WHERE role = 'student' AND is_graduated = 0 AND level_id = $from_level $dept_condition";
        mysqli_query($conn, $update);
        $affected = mysqli_affected_rows($conn);
        
        // Log bulk progression
        $students = mysqli_query($conn, "SELECT id FROM users WHERE role = 'student' AND level_id = $to_level AND progression_date = CURDATE() $dept_condition");
        while($student = mysqli_fetch_assoc($students)) {
            $log = "INSERT INTO progression_history (student_id, old_level_id, new_level_id, academic_session, processed_by) 
                    VALUES ({$student['id']}, $from_level, $to_level, '2024/2025', $user_id)";
            mysqli_query($conn, $log);
        }
        
        $level_names = ['', '100L', '200L', '300L', '400L'];
        $message = "✅ $affected students promoted from " . $level_names[$from_level] . " to " . $level_names[$to_level] . "!";
        
    } elseif($to_level == 5) {
        // GRADUATE all 400L students
        $year = date('Y');
        
        // Get students to graduate
        $students_query = "SELECT id FROM users WHERE role = 'student' AND is_graduated = 0 AND level_id = 4 $dept_condition";
        $students = mysqli_query($conn, $students_query);
        $graduate_count = 0;
        
        // Create or get alumni year group
        $alumni_group_id = createAlumniYearGroup($year, $conn);
        
        // Get main alumni group
        $main_alumni = mysqli_query($conn, "SELECT id FROM chat_groups WHERE name = '🎓 FoC Alumni Association'");
        $main_id = mysqli_num_rows($main_alumni) > 0 ? mysqli_fetch_assoc($main_alumni)['id'] : null;
        
        while($student = mysqli_fetch_assoc($students)) {
            // Graduate student
            $update = "UPDATE users SET is_graduated = 1, graduation_year = $year, level_id = NULL WHERE id = {$student['id']}";
            mysqli_query($conn, $update);
            
            // Add to alumni year group
            mysqli_query($conn, "INSERT IGNORE INTO group_members (user_id, group_id) VALUES ({$student['id']}, $alumni_group_id)");
            
            // Add to main alumni group
            if($main_id) {
                mysqli_query($conn, "INSERT IGNORE INTO group_members (user_id, group_id) VALUES ({$student['id']}, $main_id)");
            }
            
            // Log graduation
            mysqli_query($conn, "INSERT INTO graduation_records (student_id, graduation_year) VALUES ({$student['id']}, $year)");
            
            // Log progression (use NULL for graduation)
            mysqli_query($conn, "INSERT INTO progression_history (student_id, old_level_id, new_level_id, academic_session, processed_by) 
                                VALUES ({$student['id']}, 4, NULL, '2024/2025', $user_id)");
            
            $graduate_count++;
        }
        
        $message = "🎓 $graduate_count students graduated successfully! Added to Class of $year Alumni group.";
    }
}

// Get departments for dropdown
$departments = mysqli_query($conn, "SELECT * FROM departments ORDER BY name");

// Get students by level (filter by HOD's department if applicable)
$students_by_level = [];
$level_names = ['', '100L', '200L', '300L', '400L'];

for($i = 1; $i <= 4; $i++) {
    $dept_filter = ($user_role == 'hod' && $user_dept_id) ? "AND u.department_id = $user_dept_id" : "";
    $query = "SELECT u.id, u.name, u.matric_number, u.email, a.level_name, d.name as dept_name
              FROM users u 
              JOIN academic_levels a ON u.level_id = a.id 
              LEFT JOIN departments d ON u.department_id = d.id
              WHERE u.level_id = $i AND u.role = 'student' AND u.is_graduated = 0 $dept_filter
              ORDER BY u.name";
    $students_by_level[$i] = mysqli_query($conn, $query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Progression - FoC Connect</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #F5F7F6; }
        
        .sidebar { width: 280px; background: #0F4C3A; color: #E8F5E9; position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; z-index: 100; }
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
        
        .dashboard-container { padding: 24px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 20px; padding: 24px; margin-bottom: 24px; border: 1px solid #E8EDEC; }
        .card-title { font-size: 18px; font-weight: 600; color: #1A2E28; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #E8EDEC; }
        
        .btn { background: #2E7D64; color: white; padding: 10px 20px; border: none; border-radius: 40px; cursor: pointer; font-size: 14px; transition: background 0.2s; }
        .btn:hover { background: #236753; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-graduate { background: #D69E2E; color: #1A202C; }
        .btn-graduate:hover { background: #B7791F; }
        
        .message { padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; }
        .message.success { background: #E8F5E9; color: #2E7D64; border: 1px solid #2E7D64; }
        .message.error { background: #FEF2F2; color: #E53E3E; border: 1px solid #FEE2E2; }
        
        .level-section { margin-bottom: 30px; }
        .level-header { background: #0F4C3A; color: white; padding: 12px 16px; border-radius: 12px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .level-header h3 { font-size: 16px; }
        .student-list { list-style: none; }
        .student-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #E8EDEC; flex-wrap: wrap; gap: 10px; }
        .student-item:last-child { border-bottom: none; }
        .student-name { font-weight: 500; color: #1A2E28; }
        .student-matric { font-size: 12px; color: #8A9B97; margin-left: 8px; }
        .student-dept { font-size: 11px; color: #2E7D64; margin-left: 8px; background: #E8F5E9; padding: 2px 8px; border-radius: 20px; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        select, input { width: 100%; padding: 10px; border-radius: 40px; border: 1px solid #E8EDEC; font-family: inherit; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #1A2E28; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; }
            .sidebar { transform: translateX(-100%); }
            .grid-2 { grid-template-columns: 1fr; }
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
            <a href="progression.php" class="nav-item active"><span class="nav-icon">📈</span><span class="nav-text">Student Progression</span></a>
            <a href="graduation.php" class="nav-item"><span class="nav-icon">🎓</span><span class="nav-text">Graduation</span></a>
            <a href="admin-roles.php" class="nav-item"><span class="nav-icon">👥</span><span class="nav-text">Role Management</span></a>
            <a href="view-leaders.php" class="nav-item"><span class="nav-icon">🏆</span><span class="nav-text">View Leaders</span></a>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="page-title">📈 Student Progression Management</div>
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

            <!-- Bulk Progression -->
            <div class="card">
                <div class="card-title">📅 End of Session Bulk Progression</div>
                <form method="POST" class="grid-2">
                    <div>
                        <label>Select Department</label>
                        <select name="department_id">
                            <option value="0">All Departments</option>
                            <?php while($dept = mysqli_fetch_assoc($departments)): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo $dept['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label>Progress From Level</label>
                        <select name="from_level" required>
                            <option value="1">100L → 200L</option>
                            <option value="2">200L → 300L</option>
                            <option value="3">300L → 400L</option>
                            <option value="4">400L → Graduate (Alumni)</option>
                        </select>
                    </div>
                    <div style="grid-column: span 2;">
                        <button type="submit" name="bulk_progress" class="btn" onclick="return confirm('WARNING: This will promote ALL students in the selected level. Are you sure?')">✅ Process Bulk Progression</button>
                    </div>
                </form>
            </div>

            <!-- Students by Level -->
            <?php for($level = 1; $level <= 4; $level++): 
                $next_level = $level + 1;
                $next_name = $next_level <= 4 ? $level_names[$next_level] : 'Graduate';
                $student_count = mysqli_num_rows($students_by_level[$level]);
            ?>
                <div class="level-section">
                    <div class="level-header">
                        <h3>📚 <?php echo $level_names[$level]; ?> Students (<?php echo $student_count; ?> students)</h3>
                    </div>
                    <?php if($student_count > 0): ?>
                        <div class="student-list">
                            <?php while($student = mysqli_fetch_assoc($students_by_level[$level])): ?>
                                <div class="student-item">
                                    <div>
                                        <span class="student-name"><?php echo htmlspecialchars($student['name']); ?></span>
                                        <span class="student-matric"><?php echo $student['matric_number']; ?></span>
                                        <?php if($user_role == 'dean' && isset($student['dept_name'])): ?>
                                            <span class="student-dept"><?php echo $student['dept_name']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if($level == 4): ?>
                                        <a href="?promote=1&student_id=<?php echo $student['id']; ?>" class="btn-sm btn-graduate" onclick="return confirm('Graduate <?php echo $student['name']; ?>? They will become an alumni.')">🎓 Graduate</a>
                                    <?php else: ?>
                                        <a href="?promote=1&student_id=<?php echo $student['id']; ?>" class="btn-sm btn" onclick="return confirm('Promote <?php echo $student['name']; ?> to <?php echo $next_name; ?>?')">⬆ Promote to <?php echo $next_name; ?></a>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #8A9B97; padding: 20px; text-align: center; background: white; border-radius: 12px;">No students in <?php echo $level_names[$level]; ?></p>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </main>
</div>
</body>
</html>