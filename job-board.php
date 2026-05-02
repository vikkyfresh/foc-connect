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
$is_alumni = isset($_SESSION['is_graduated']) && $_SESSION['is_graduated'] == 1;

$message = '';
$error = '';

// Handle job posting (alumni only)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_job']) && $is_alumni) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $company = mysqli_real_escape_string($conn, $_POST['company']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $job_type = mysqli_real_escape_string($conn, $_POST['job_type']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $requirements = mysqli_real_escape_string($conn, $_POST['requirements']);
    $how_to_apply = mysqli_real_escape_string($conn, $_POST['how_to_apply']);
    $contact_email = mysqli_real_escape_string($conn, $_POST['contact_email']);
    $deadline = mysqli_real_escape_string($conn, $_POST['deadline']);
    
    $insert = "INSERT INTO job_postings (posted_by, title, company, location, job_type, description, requirements, how_to_apply, contact_email, deadline) 
               VALUES ($user_id, '$title', '$company', '$location', '$job_type', '$description', '$requirements', '$how_to_apply', '$contact_email', '$deadline')";
    
    if(mysqli_query($conn, $insert)) {
        $message = "✅ Job posted successfully!";
    } else {
        $error = "❌ Error: " . mysqli_error($conn);
    }
}

// Handle job application (students only)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_job']) && !$is_alumni) {
    $job_id = intval($_POST['job_id']);
    $cover_letter = mysqli_real_escape_string($conn, $_POST['cover_letter']);
    
    $check = mysqli_query($conn, "SELECT id FROM job_applications WHERE job_id = $job_id AND student_id = $user_id");
    if(mysqli_num_rows($check) > 0) {
        $error = "You have already applied for this job.";
    } else {
        $insert = "INSERT INTO job_applications (job_id, student_id, cover_letter) VALUES ($job_id, $user_id, '$cover_letter')";
        if(mysqli_query($conn, $insert)) {
            $message = "✅ Application submitted successfully!";
        } else {
            $error = "❌ Error: " . mysqli_error($conn);
        }
    }
}

// Get all jobs
$jobs_query = "SELECT j.*, u.name as poster_name, u.is_graduated as poster_is_alumni,
               (SELECT COUNT(*) FROM job_applications WHERE job_id = j.id) as application_count
               FROM job_postings j
               JOIN users u ON j.posted_by = u.id
               WHERE j.deadline >= CURDATE() OR j.deadline IS NULL
               ORDER BY j.created_at DESC";
$jobs = mysqli_query($conn, $jobs_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Board - FoC Connect</title>
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
        
        .main-content { margin-left: 280px; min-height: 100vh; }
        .top-header { background: white; border-bottom: 1px solid #E2E8F0; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 20px; font-weight: 600; color: #1A2E28; }
        .profile-btn { display: flex; align-items: center; gap: 10px; background: #F5F7F6; padding: 6px 12px; border-radius: 40px; }
        .profile-avatar { width: 36px; height: 36px; background: #2E7D64; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        
        .dashboard-container { padding: 24px; max-width: 1000px; margin: 0 auto; }
        .card { background: white; border-radius: 20px; padding: 24px; margin-bottom: 24px; border: 1px solid #E8EDEC; }
        .card-title { font-size: 18px; font-weight: 600; color: #1A2E28; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #E8EDEC; }
        
        .job-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 16px; border: 1px solid #E8EDEC; }
        .job-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .job-title { font-size: 18px; font-weight: 600; color: #1A2E28; }
        .job-company { font-size: 14px; color: #2E7D64; margin-bottom: 8px; }
        .job-meta { display: flex; flex-wrap: wrap; gap: 16px; font-size: 13px; color: #8A9B97; margin-bottom: 16px; }
        .job-description, .job-requirements { font-size: 14px; color: #4A5568; line-height: 1.5; margin-bottom: 12px; }
        .apply-form { margin-top: 16px; padding-top: 16px; border-top: 1px solid #E8EDEC; }
        textarea { width: 100%; padding: 12px; border: 1px solid #E8EDEC; border-radius: 12px; font-family: inherit; margin-bottom: 12px; }
        .btn { background: #2E7D64; color: white; padding: 10px 20px; border: none; border-radius: 40px; cursor: pointer; }
        .alumni-banner { background: linear-gradient(135deg, #D69E2E, #8B4513); color: white; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; }
        .empty-state { text-align: center; padding: 40px; color: #8A9B97; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #2E7D64; text-decoration: none; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #E8EDEC; border-radius: 40px; }
        
        @media (max-width: 768px) { .main-content { margin-left: 0; } .sidebar { transform: translateX(-100%); } }
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
            <a href="chat.php" class="nav-item"><span class="nav-icon">💬</span><span class="nav-text">Chat</span></a>
            <a href="announcements.php" class="nav-item"><span class="nav-icon">📢</span><span class="nav-text">Announcements</span></a>
            <a href="materials.php" class="nav-item"><span class="nav-icon">📚</span><span class="nav-text">Course Materials</span></a>
            <a href="alumni-directory.php" class="nav-item"><span class="nav-icon">🎓</span><span class="nav-text">Alumni Directory</span></a>
            <a href="job-board.php" class="nav-item active"><span class="nav-icon">💼</span><span class="nav-text">Job Board</span></a>
            <a href="mentorship.php" class="nav-item"><span class="nav-icon">🤝</span><span class="nav-text">Mentorship</span></a>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="page-title">💼 Job Board</div>
            <div class="profile-btn">
                <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                <div><?php echo htmlspecialchars($user_name); ?></div>
            </div>
        </header>

        <div class="dashboard-container">
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
            
            <?php if($message): ?>
                <div class="card" style="background: #E8F5E9; color: #2E7D64;"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="card" style="background: #FEF2F2; color: #E53E3E;"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($is_alumni): ?>
            <div class="alumni-banner">
                <strong>🎓 Alumni Job Posting</strong><br>
                <small>Share job opportunities with current students</small>
            </div>
            
            <div class="card">
                <div class="card-title">📝 Post a Job Opening</div>
                <form method="POST">
                    <div class="form-group"><input type="text" name="title" placeholder="Job Title" required></div>
                    <div class="form-group"><input type="text" name="company" placeholder="Company Name" required></div>
                    <div class="form-group"><input type="text" name="location" placeholder="Location (e.g., Lagos, Remote)"></div>
                    <div class="form-group">
                        <select name="job_type" required>
                            <option value="full_time">Full Time</option>
                            <option value="part_time">Part Time</option>
                            <option value="internship">Internship</option>
                            <option value="contract">Contract</option>
                            <option value="remote">Remote</option>
                        </select>
                    </div>
                    <div class="form-group"><textarea name="description" rows="4" placeholder="Job Description" required></textarea></div>
                    <div class="form-group"><textarea name="requirements" rows="3" placeholder="Requirements"></textarea></div>
                    <div class="form-group"><textarea name="how_to_apply" rows="3" placeholder="How to Apply (email, link, etc.)" required></textarea></div>
                    <div class="form-group"><input type="email" name="contact_email" placeholder="Contact Email" required></div>
                    <div class="form-group"><input type="date" name="deadline" placeholder="Application Deadline"></div>
                    <button type="submit" name="post_job" class="btn">📤 Post Job</button>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-title">📋 Available Positions</div>
                <?php if(mysqli_num_rows($jobs) > 0): ?>
                    <?php while($job = mysqli_fetch_assoc($jobs)): ?>
                        <div class="job-card">
                            <div class="job-header">
                                <div>
                                    <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                    <div class="job-company">🏢 <?php echo htmlspecialchars($job['company']); ?> • <?php echo ucfirst(str_replace('_', ' ', $job['job_type'])); ?></div>
                                </div>
                                <div style="font-size: 12px; color: #8A9B97;">📅 Posted by <?php echo htmlspecialchars($job['poster_name']); ?></div>
                            </div>
                            <div class="job-meta">
                                <span>📍 <?php echo htmlspecialchars($job['location'] ?: 'Not specified'); ?></span>
                                <span>📊 <?php echo $job['application_count']; ?> applicant(s)</span>
                                <?php if($job['deadline']): ?><span>⏰ Deadline: <?php echo date('M d, Y', strtotime($job['deadline'])); ?></span><?php endif; ?>
                            </div>
                            <div class="job-description"><strong>Description:</strong><br><?php echo nl2br(htmlspecialchars($job['description'])); ?></div>
                            <?php if($job['requirements']): ?>
                                <div class="job-requirements"><strong>Requirements:</strong><br><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></div>
                            <?php endif; ?>
                            
                            <?php if(!$is_alumni): ?>
                            <div class="apply-form">
                                <form method="POST">
                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                    <textarea name="cover_letter" rows="3" placeholder="Why are you interested in this position?" required></textarea>
                                    <button type="submit" name="apply_job" class="btn">📝 Apply Now</button>
                                </form>
                            </div>
                            <?php else: ?>
                            <div style="margin-top: 12px; font-size: 13px; color: #8A9B97;">📧 To apply: <?php echo htmlspecialchars($job['how_to_apply']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">💼 No job postings at the moment.</div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>