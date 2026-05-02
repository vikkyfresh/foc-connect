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

$message = '';
$error = '';
$year = date('Y');

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

// Process graduation
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_graduation'])) {
    $graduation_year = intval($_POST['graduation_year']);
    $department_id = intval($_POST['department_id']);
    
    // Build department condition
    $dept_condition = ($department_id > 0) ? "AND department_id = $department_id" : "";
    
    // Get students to graduate (400L students who are not already graduated)
    $students_query = "SELECT id, name, matric_number FROM users 
                       WHERE role = 'student' 
                       AND is_graduated = 0 
                       AND level_id = 4 
                       $dept_condition";
    $students = mysqli_query($conn, $students_query);
    $graduate_count = 0;
    $graduated_students = [];
    
    if(mysqli_num_rows($students) == 0) {
        $error = "No 400L students found to graduate in the selected department.";
    } else {
        // Create or get alumni year group
        $alumni_group_id = createAlumniYearGroup($graduation_year, $conn);
        
        // Get main alumni group
        $main_alumni = mysqli_query($conn, "SELECT id FROM chat_groups WHERE name = '🎓 FoC Alumni Association'");
        $main_id = mysqli_num_rows($main_alumni) > 0 ? mysqli_fetch_assoc($main_alumni)['id'] : null;
        
        while($student = mysqli_fetch_assoc($students)) {
            // Graduate student
            $update = "UPDATE users SET is_graduated = 1, graduation_year = $graduation_year, level_id = NULL WHERE id = {$student['id']}";
            if(mysqli_query($conn, $update)) {
                $graduate_count++;
                $graduated_students[] = $student['name'] . " (" . $student['matric_number'] . ")";
                
                // Add to alumni year group
                mysqli_query($conn, "INSERT IGNORE INTO group_members (user_id, group_id) VALUES ({$student['id']}, $alumni_group_id)");
                
                // Add to main alumni group
                if($main_id) {
                    mysqli_query($conn, "INSERT IGNORE INTO group_members (user_id, group_id) VALUES ({$student['id']}, $main_id)");
                }
                
                // Log graduation
                mysqli_query($conn, "INSERT INTO graduation_records (student_id, graduation_year) VALUES ({$student['id']}, $graduation_year)");
            }
        }
        
        if($graduate_count > 0) {
            $message = "🎓 $graduate_count student(s) graduated successfully! Added to Class of $graduation_year Alumni group.";
        } else {
            $error = "❌ No students were graduated. Please check again.";
        }
    }
}

// Get departments for dropdown
$departments = mysqli_query($conn, "SELECT * FROM departments ORDER BY name");

// Get 400L students count for each department
$dept_counts = [];
$dept_query = "SELECT d.id, d.name, COUNT(u.id) as student_count 
                FROM departments d
                LEFT JOIN users u ON u.department_id = d.id 
                AND u.role = 'student' 
                AND u.is_graduated = 0 
                AND u.level_id = 4
                GROUP BY d.id
                ORDER BY d.name";
$dept_counts_result = mysqli_query($conn, $dept_query);

// Get total 400L students
$total_400l = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'student' AND is_graduated = 0 AND level_id = 4");
$total_400l_count = mysqli_fetch_assoc($total_400l)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graduation Processing - FoC Connect</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #F5F7F6; }
        
        /* Sidebar */
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
        
        /* Main Content */
        .main-content { margin-left: 280px; min-height: 100vh; width: calc(100% - 280px); }
        .top-header { background: white; border-bottom: 1px solid #E2E8F0; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 20px; font-weight: 600; color: #1A2E28; }
        .profile-btn { display: flex; align-items: center; gap: 10px; background: #F5F7F6; padding: 6px 12px; border-radius: 40px; }
        .profile-avatar { width: 36px; height: 36px; background: #2E7D64; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        
        /* Content */
        .dashboard-container { padding: 24px; max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 20px; padding: 24px; margin-bottom: 24px; border: 1px solid #E8EDEC; }
        .card-title { font-size: 18px; font-weight: 600; color: #1A2E28; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #E8EDEC; }
        
        .btn { background: #2E7D64; color: white; padding: 12px 24px; border: none; border-radius: 40px; cursor: pointer; font-size: 16px; font-weight: 600; transition: background 0.2s; width: 100%; }
        .btn:hover { background: #236753; }
        .btn-danger { background: #E53E3E; }
        .btn-danger:hover { background: #C53030; }
        
        .message { padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; }
        .message.success { background: #E8F5E9; color: #2E7D64; border: 1px solid #2E7D64; }
        .message.error { background: #FEF2F2; color: #E53E3E; border: 1px solid #FEE2E2; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; color: #1A2E28; margin-bottom: 8px; font-size: 14px; }
        select, input { width: 100%; padding: 12px; border: 1px solid #E8EDEC; border-radius: 12px; font-size: 14px; font-family: inherit; }
        
        .stats-card { background: #E8F5E9; border-radius: 16px; padding: 16px; text-align: center; margin-bottom: 20px; }
        .stats-number { font-size: 36px; font-weight: 700; color: #2E7D64; }
        .stats-label { font-size: 14px; color: #1A2E28; margin-top: 8px; }
        
        .dept-list { margin-top: 20px; }
        .dept-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #E8EDEC; }
        .dept-name { font-weight: 500; }
        .dept-count { color: #2E7D64; font-weight: 600; }
        
        .back-link { display: inline-block; margin-bottom: 20px; color: #2E7D64; text-decoration: none; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; }
            .sidebar { transform: translateX(-100%); }
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
            <a href="progression.php" class="nav-item"><span class="nav-icon">📈</span><span class="nav-text">Student Progression</span></a>
            <a href="graduation.php" class="nav-item active"><span class="nav-icon">🎓</span><span class="nav-text">Graduation</span></a>
            <a href="admin-roles.php" class="nav-item"><span class="nav-icon">👥</span><span class="nav-text">Role Management</span></a>
            <a href="view-leaders.php" class="nav-item"><span class="nav-icon">🏆</span><span class="nav-text">View Leaders</span></a>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div class="page-title">🎓 Graduation Processing</div>
            <div class="profile-btn">
                <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                <div><?php echo htmlspecialchars($user_name); ?> (<?php echo ucfirst($user_role); ?>)</div>
            </div>
        </header>

        <div class="dashboard-container">
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
            
            <?php if($message): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-card">
                <div class="stats-number"><?php echo $total_400l_count; ?></div>
                <div class="stats-label">Students Ready for Graduation (400L)</div>
            </div>

            <!-- Graduation Form -->
            <div class="card">
                <div class="card-title">🎓 Process Graduation</div>
                <form method="POST">
                    <div class="form-group">
                        <label>Select Department</label>
                        <select name="department_id">
                            <option value="0">All Departments</option>
                            <?php while($dept = mysqli_fetch_assoc($departments)): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Graduation Year</label>
                        <input type="number" name="graduation_year" value="<?php echo $year; ?>" required min="2000" max="2030">
                    </div>
                    <button type="submit" name="process_graduation" class="btn" onclick="return confirm('WARNING: This will graduate ALL 400L students in the selected department. This action cannot be undone. Are you sure?')">🎓 Process Graduation</button>
                </form>
            </div>

            <!-- Students by Department -->
            <?php if(mysqli_num_rows($dept_counts_result) > 0): ?>
            <div class="card">
                <div class="card-title">📊 400L Students by Department</div>
                <div class="dept-list">
                    <?php while($dept = mysqli_fetch_assoc($dept_counts_result)): ?>
                        <div class="dept-item">
                            <span class="dept-name"><?php echo htmlspecialchars($dept['name']); ?></span>
                            <span class="dept-count"><?php echo $dept['student_count']; ?> students</span>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Instructions -->
            <div class="card">
                <div class="card-title">ℹ️ Information</div>
                <p style="color: #4A5568; line-height: 1.6; margin-bottom: 12px;">
                    When you process graduation:
                </p>
                <ul style="color: #4A5568; line-height: 1.8; margin-left: 20px;">
                    <li>✅ Students will be marked as graduated</li>
                    <li>✅ They will be added to "🎓 Class of YYYY Alumni" group</li>
                    <li>✅ They will be added to "🎓 FoC Alumni Association" group</li>
                    <li>✅ Their level will be removed (no longer current student)</li>
                    <li>✅ They can still access the platform as alumni</li>
                </ul>
            </div>
        </div>
    </main>
</div>
</body>
</html>