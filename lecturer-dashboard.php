<?php
session_start();
include 'includes/db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];

// Check if user is lecturer or higher
if(!in_array($user_role, ['lecturer', 'hod', 'dean'])) {
    header("Location: dashboard.php");
    exit();
}

// Get lecturer's courses
$courses_query = "SELECT c.*, d.name as dept_name,
                  (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
                  (SELECT COUNT(*) FROM assignments WHERE course_id = c.id) as assignment_count
                  FROM courses c
                  JOIN departments d ON c.department_id = d.id
                  WHERE c.lecturer_id = $user_id OR $user_role IN ('hod', 'dean')
                  ORDER BY c.course_code";
$courses = mysqli_query($conn, $courses_query);

// Get pending submissions to grade
$pending_submissions = mysqli_query($conn, "SELECT s.*, a.title as assignment_title, a.course_id, 
                                                   c.course_code, u.name as student_name, u.matric_number
                                            FROM submissions s
                                            JOIN assignments a ON s.assignment_id = a.id
                                            JOIN courses c ON a.course_id = c.id
                                            JOIN users u ON s.student_id = u.id
                                            WHERE s.grade IS NULL AND (c.lecturer_id = $user_id OR $user_role IN ('hod', 'dean'))
                                            ORDER BY s.submitted_at DESC LIMIT 10");

// Handle grade submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['grade_submission'])) {
    $submission_id = intval($_POST['submission_id']);
    $grade = floatval($_POST['grade']);
    $feedback = mysqli_real_escape_string($conn, $_POST['feedback']);
    
    $update = "UPDATE submissions SET grade = $grade, graded_by = $user_id, graded_at = NOW() WHERE id = $submission_id";
    if(mysqli_query($conn, $update)) {
        $success = "✅ Grade submitted successfully!";
    } else {
        $error = "❌ Error updating grade: " . mysqli_error($conn);
    }
}

// Handle material upload
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_material'])) {
    $course_id = intval($_POST['course_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    if(isset($_FILES['material_file']) && $_FILES['material_file']['error'] == 0) {
        $filename = $_FILES['material_file']['name'];
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $new_filename = "material_" . time() . "_" . preg_replace('/[^a-zA-Z0-9]/', '_', $filename);
        $upload_dir = "uploads/materials/";
        $upload_path = $upload_dir . $new_filename;
        
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if(move_uploaded_file($_FILES['material_file']['tmp_name'], $upload_path)) {
            $insert = "INSERT INTO course_materials (course_id, title, description, file_name, file_path, file_type, uploaded_by) 
                       VALUES ($course_id, '$title', '$description', '$new_filename', '$upload_path', '$file_ext', $user_id)";
            if(mysqli_query($conn, $insert)) {
                $success = "✅ Course material uploaded successfully!";
            } else {
                $error = "❌ Database error: " . mysqli_error($conn);
            }
        } else {
            $error = "❌ Failed to upload file.";
        }
    } else {
        $error = "❌ Please select a file to upload.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Lecturer Dashboard - FoC Connect</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #F5F7F6; overflow-x: hidden; }

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
        .top-header { background: white; border-bottom: 1px solid #E2E8F0; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; flex-wrap: wrap; gap: 12px; }
        .page-title { font-size: 20px; font-weight: 600; color: #1A2E28; }
        .profile-btn { display: flex; align-items: center; gap: 10px; background: #F5F7F6; padding: 6px 12px; border-radius: 40px; }
        .profile-avatar { width: 36px; height: 36px; background: #2E7D64; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }

        /* Content */
        .dashboard-container { padding: 24px; max-width: 1400px; margin: 0 auto; }
        .welcome-card { background: linear-gradient(135deg, #0F4C3A 0%, #2E7D64 100%); color: white; border-radius: 20px; padding: 28px; margin-bottom: 24px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; text-align: center; border: 1px solid #E8EDEC; }
        .stat-number { font-size: 32px; font-weight: 700; color: #2E7D64; }
        .stat-label { font-size: 14px; color: #6B7E78; margin-top: 8px; }

        /* Cards */
        .section-card { background: white; border-radius: 20px; padding: 24px; margin-bottom: 24px; border: 1px solid #E8EDEC; }
        .section-title { font-size: 18px; font-weight: 600; color: #1A2E28; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #E8EDEC; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: 600; color: #1A2E28; margin-bottom: 8px; font-size: 14px; }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid #E8EDEC; border-radius: 12px; font-size: 14px; font-family: inherit; }
        .btn { background: #2E7D64; color: white; padding: 12px 24px; border: none; border-radius: 40px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .btn:hover { background: #236753; }
        .message { padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; }
        .message.success { background: #E8F5E9; color: #2E7D64; border: 1px solid #2E7D64; }
        .message.error { background: #FFF5F5; color: #E53E3E; border: 1px solid #E53E3E; }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #E8EDEC; }
        .data-table th { background: #F5F7F6; font-weight: 600; color: #1A2E28; }
        .grade-input { width: 80px; padding: 8px; border: 1px solid #E8EDEC; border-radius: 8px; }
        .grade-btn { background: #2E7D64; color: white; padding: 6px 12px; border: none; border-radius: 20px; cursor: pointer; font-size: 12px; }
        .badge { background: #E8F5E9; color: #2E7D64; padding: 4px 12px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
        .course-card { background: #F5F7F6; border-radius: 16px; padding: 16px; border: 1px solid #E8EDEC; }
        .course-code { font-weight: 700; color: #1A2E28; }
        .course-name { font-size: 13px; color: #6B7E78; margin-top: 4px; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; }
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.open { transform: translateX(0); }
            .hamburger { display: block; }
        }
        .hamburger { display: none; background: none; border: none; font-size: 24px; cursor: pointer; margin-right: 12px; }
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
            <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span><span class="nav-text">Dashboard</span></a>
            <a href="lecturer-dashboard.php" class="nav-item active"><span class="nav-icon">👨‍🏫</span><span class="nav-text">Lecturer Panel</span></a>
            <a href="chat.php" class="nav-item"><span class="nav-icon">💬</span><span class="nav-text">Chat</span></a>
            <a href="materials.php" class="nav-item"><span class="nav-icon">📚</span><span class="nav-text">Course Materials</span></a>
            <a href="assignments.php" class="nav-item"><span class="nav-icon">📝</span><span class="nav-text">Assignments</span></a>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="hamburger" onclick="toggleSidebar()">☰</button>
                <div class="page-title">Lecturer Dashboard</div>
            </div>
            <div class="profile-btn">
                <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                <div><?php echo htmlspecialchars($user_name); ?> (<?php echo ucfirst($user_role); ?>)</div>
            </div>
        </header>

        <div class="dashboard-container">
            <?php if(isset($success)): ?>
                <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if(isset($error)): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Welcome Card -->
            <div class="welcome-card">
                <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>! 👨‍🏫</h2>
                <p style="margin-top: 8px; opacity: 0.9;">Manage your courses, upload materials, and grade assignments.</p>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo mysqli_num_rows($courses); ?></div>
                    <div class="stat-label">📚 My Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo mysqli_num_rows($pending_submissions); ?></div>
                    <div class="stat-label">📝 Pending Grades</div>
                </div>
            </div>

            <!-- Pending Submissions to Grade -->
            <div class="section-card">
                <div class="section-title">📝 Pending Submissions</div>
                <?php if(mysqli_num_rows($pending_submissions) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>Student</th><th>Course</th><th>Assignment</th><th>Submitted</th><th>Grade</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php while($sub = mysqli_fetch_assoc($pending_submissions)): ?>
                                <form method="POST">
                                    <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['student_name']); ?><br><small><?php echo $sub['matric_number']; ?></small></td>
                                        <td><?php echo $sub['course_code']; ?></td>
                                        <td><?php echo htmlspecialchars($sub['assignment_title']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($sub['submitted_at'])); ?></td>
                                        <td><input type="number" name="grade" class="grade-input" step="0.01" min="0" max="100" required></td>
                                        <td><button type="submit" name="grade_submission" class="grade-btn">Grade</button></td>
                                    </tr>
                                </form>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #8A9B97; text-align: center; padding: 20px;">✅ No pending submissions to grade.</p>
                <?php endif; ?>
            </div>

            <!-- Upload Course Material -->
            <div class="section-card">
                <div class="section-title">📤 Upload Course Material</div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Select Course</label>
                        <select name="course_id" required>
                            <option value="">-- Select Course --</option>
                            <?php while($course = mysqli_fetch_assoc($courses)): ?>
                                <option value="<?php echo $course['id']; ?>"><?php echo $course['course_code']; ?> - <?php echo $course['course_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Material Title</label>
                        <input type="text" name="title" placeholder="e.g., Lecture 1: Introduction" required>
                    </div>
                    <div class="form-group">
                        <label>Description (Optional)</label>
                        <textarea name="description" rows="3" placeholder="Brief description of the material..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>File (PDF, PPT, DOC, MP4)</label>
                        <input type="file" name="material_file" accept=".pdf,.ppt,.pptx,.doc,.docx,.mp4,.zip" required>
                    </div>
                    <button type="submit" name="upload_material" class="btn">📤 Upload Material</button>
                </form>
            </div>

            <!-- My Courses -->
            <div class="section-card">
                <div class="section-title">📚 My Courses</div>
                <div class="course-grid">
                    <?php while($course = mysqli_fetch_assoc($courses)): ?>
                        <div class="course-card">
                            <div class="course-code"><?php echo $course['course_code']; ?></div>
                            <div class="course-name"><?php echo $course['course_name']; ?></div>
                            <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                                <span class="badge">👥 <?php echo $course['student_count']; ?> Students</span>
                                <span class="badge">📝 <?php echo $course['assignment_count']; ?> Assignments</span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }
</script>
</body>
</html>