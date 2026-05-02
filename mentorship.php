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

// Alumni sign up as mentor
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['become_mentor']) && $is_alumni) {
    $industry = mysqli_real_escape_string($conn, $_POST['industry']);
    $expertise = mysqli_real_escape_string($conn, $_POST['expertise']);
    $experience = intval($_POST['experience']);
    $availability = intval($_POST['availability']);
    
    $check = mysqli_query($conn, "SELECT id FROM mentors WHERE user_id = $user_id");
    if(mysqli_num_rows($check) > 0) {
        $update = "UPDATE mentors SET industry='$industry', expertise='$expertise', years_of_experience=$experience, availability_hours=$availability WHERE user_id=$user_id";
        mysqli_query($conn, $update);
        $message = "✅ Mentor profile updated!";
    } else {
        $insert = "INSERT INTO mentors (user_id, industry, expertise, years_of_experience, availability_hours) 
                   VALUES ($user_id, '$industry', '$expertise', $experience, $availability)";
        if(mysqli_query($conn, $insert)) {
            $message = "✅ You are now a mentor! Students can request mentorship.";
        } else {
            $error = "❌ Error: " . mysqli_error($conn);
        }
    }
}

// Student requests mentorship
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_mentor']) && !$is_alumni) {
    $mentor_id = intval($_POST['mentor_id']);
    $message_text = mysqli_real_escape_string($conn, $_POST['message']);
    
    $check = mysqli_query($conn, "SELECT id FROM mentorship_requests WHERE mentor_id=$mentor_id AND mentee_id=$user_id AND status='pending'");
    if(mysqli_num_rows($check) > 0) {
        $error = "You already have a pending request with this mentor.";
    } else {
        $insert = "INSERT INTO mentorship_requests (mentor_id, mentee_id, message) VALUES ($mentor_id, $user_id, '$message_text')";
        if(mysqli_query($conn, $insert)) {
            $message = "✅ Mentorship request sent! The mentor will review it.";
        } else {
            $error = "❌ Error: " . mysqli_error($conn);
        }
    }
}

// Mentor accepts/declines request
if(isset($_GET['action']) && isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);
    $action = $_GET['action'];
    
    if($action == 'accept') {
        $update = "UPDATE mentorship_requests SET status='accepted', responded_at=NOW() WHERE id=$request_id";
        mysqli_query($conn, $update);
        
        // Get request details
        $req = mysqli_fetch_assoc(mysqli_query($conn, "SELECT mentor_id, mentee_id FROM mentorship_requests WHERE id=$request_id"));
        mysqli_query($conn, "INSERT INTO mentorships (mentor_id, mentee_id) VALUES ({$req['mentor_id']}, {$req['mentee_id']})");
        $message = "✅ Mentorship request accepted!";
    } elseif($action == 'decline') {
        $update = "UPDATE mentorship_requests SET status='declined', responded_at=NOW() WHERE id=$request_id";
        mysqli_query($conn, $update);
        $message = "❌ Mentorship request declined.";
    }
}

// Get mentors for students
$mentors = null;
$pending_requests = null;
$my_mentors = null;
$my_mentees = null;

if(!$is_alumni) {
    $mentors = mysqli_query($conn, "SELECT m.*, u.name, u.email, u.current_position, u.current_employer, d.name as dept_name
                                    FROM mentors m
                                    JOIN users u ON m.user_id = u.id
                                    LEFT JOIN departments d ON u.department_id = d.id
                                    WHERE m.is_active = 1 AND u.id != $user_id
                                    ORDER BY m.years_of_experience DESC");
    
    $my_mentors = mysqli_query($conn, "SELECT ms.*, u.name, u.email, u.current_position
                                       FROM mentorships ms
                                       JOIN users u ON ms.mentor_id = u.id
                                       WHERE ms.mentee_id = $user_id AND ms.is_active = 1");
} else {
    $pending_requests = mysqli_query($conn, "SELECT r.*, u.name, u.email, u.matric_number, a.level_name
                                             FROM mentorship_requests r
                                             JOIN users u ON r.mentee_id = u.id
                                             LEFT JOIN academic_levels a ON u.level_id = a.id
                                             WHERE r.mentor_id = $user_id AND r.status = 'pending'");
    
    $my_mentees = mysqli_query($conn, "SELECT ms.*, u.name, u.email, u.matric_number, a.level_name
                                       FROM mentorships ms
                                       JOIN users u ON ms.mentee_id = u.id
                                       LEFT JOIN academic_levels a ON u.level_id = a.id
                                       WHERE ms.mentor_id = $user_id AND ms.is_active = 1");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentorship - FoC Connect</title>
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
        
        .mentor-card { background: #F5F7F6; border-radius: 16px; padding: 16px; margin-bottom: 12px; }
        .mentor-name { font-size: 16px; font-weight: 600; color: #1A2E28; }
        .mentor-detail { font-size: 13px; color: #6B7E78; margin: 4px 0; }
        .mentor-expertise { font-size: 13px; color: #2E7D64; margin: 8px 0; }
        .btn { background: #2E7D64; color: white; padding: 8px 16px; border: none; border-radius: 40px; cursor: pointer; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #E8EDEC; border-radius: 40px; }
        .alumni-banner { background: linear-gradient(135deg, #D69E2E, #8B4513); color: white; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #2E7D64; text-decoration: none; }
        .empty-state { text-align: center; padding: 40px; color: #8A9B97; }
        
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
            <a href="job-board.php" class="nav-item"><span class="nav-icon">💼</span><span class="nav-text">Job Board</span></a>
            <a href="mentorship.php" class="nav-item active"><span class="nav-icon">🤝</span><span class="nav-text">Mentorship</span></a>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="page-title">🤝 Mentorship Program</div>
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
                <strong>🎓 Alumni Mentor Portal</strong><br>
                <small>Share your experience and guide current students</small>
            </div>
            
            <div class="card">
                <div class="card-title">👨‍🏫 Become a Mentor</div>
                <form method="POST">
                    <div class="form-group"><input type="text" name="industry" placeholder="Your Industry (e.g., Software, Finance, Data Science)" required></div>
                    <div class="form-group"><textarea name="expertise" rows="3" placeholder="Your Expertise (e.g., Python, System Design, Career Advising)" required></textarea></div>
                    <div class="form-group"><input type="number" name="experience" placeholder="Years of Experience" required></div>
                    <div class="form-group"><input type="number" name="availability" placeholder="Hours available per week" required></div>
                    <button type="submit" name="become_mentor" class="btn">✅ Become a Mentor</button>
                </form>
            </div>
            
            <!-- Pending Requests -->
            <?php if($pending_requests && mysqli_num_rows($pending_requests) > 0): ?>
            <div class="card">
                <div class="card-title">📋 Pending Mentorship Requests</div>
                <?php while($req = mysqli_fetch_assoc($pending_requests)): ?>
                    <div class="mentor-card">
                        <div class="mentor-name"><?php echo htmlspecialchars($req['name']); ?></div>
                        <div class="mentor-detail">📚 <?php echo $req['matric_number']; ?> • <?php echo $req['level_name']; ?></div>
                        <div class="mentor-detail">💬 <?php echo htmlspecialchars($req['message']); ?></div>
                        <div style="margin-top: 12px;">
                            <a href="?action=accept&request_id=<?php echo $req['id']; ?>" class="btn-sm btn" onclick="return confirm('Accept this mentorship request?')">✅ Accept</a>
                            <a href="?action=decline&request_id=<?php echo $req['id']; ?>" class="btn-sm" style="background: #E53E3E; color: white; padding: 6px 12px; border-radius: 40px; text-decoration: none;" onclick="return confirm('Decline this request?')">❌ Decline</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
            
            <!-- My Mentees -->
            <?php if($my_mentees && mysqli_num_rows($my_mentees) > 0): ?>
            <div class="card">
                <div class="card-title">👥 My Mentees</div>
                <?php while($mentee = mysqli_fetch_assoc($my_mentees)): ?>
                    <div class="mentor-card">
                        <div class="mentor-name"><?php echo htmlspecialchars($mentee['name']); ?></div>
                        <div class="mentor-detail">📚 <?php echo $mentee['matric_number']; ?> • <?php echo $mentee['level_name']; ?></div>
                        <div style="margin-top: 12px;"><a href="chat.php?user_id=<?php echo $mentee['id']; ?>" class="btn-sm btn">💬 Send Message</a></div>
                    </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <!-- Student View -->
            
            <!-- My Mentors -->
            <?php if($my_mentors && mysqli_num_rows($my_mentors) > 0): ?>
            <div class="card">
                <div class="card-title">👨‍🏫 Your Mentors</div>
                <?php while($mentor = mysqli_fetch_assoc($my_mentors)): ?>
                    <div class="mentor-card">
                        <div class="mentor-name"><?php echo htmlspecialchars($mentor['name']); ?></div>
                        <div class="mentor-detail">💼 <?php echo htmlspecialchars($mentor['current_position']); ?> at <?php echo htmlspecialchars($mentor['current_employer']); ?></div>
                        <div style="margin-top: 12px;"><a href="chat.php?user_id=<?php echo $mentor['id']; ?>" class="btn-sm btn">💬 Message Mentor</a></div>
                    </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
            
            <!-- Available Mentors -->
            <div class="card">
                <div class="card-title">🌟 Available Mentors</div>
                <?php if($mentors && mysqli_num_rows($mentors) > 0): ?>
                    <?php while($mentor = mysqli_fetch_assoc($mentors)): ?>
                        <div class="mentor-card">
                            <div class="mentor-name"><?php echo htmlspecialchars($mentor['name']); ?></div>
                            <div class="mentor-detail">🏛️ <?php echo htmlspecialchars($mentor['dept_name']); ?> • 💼 <?php echo htmlspecialchars($mentor['current_position']); ?> at <?php echo htmlspecialchars($mentor['current_employer']); ?></div>
                            <div class="mentor-expertise">🔧 Expertise: <?php echo htmlspecialchars(substr($mentor['expertise'], 0, 100)); ?>...</div>
                            <div class="mentor-detail">📅 <?php echo $mentor['years_of_experience']; ?> years experience • ⏰ <?php echo $mentor['availability_hours']; ?> hrs/week</div>
                            <form method="POST" style="margin-top: 12px;">
                                <input type="hidden" name="mentor_id" value="<?php echo $mentor['user_id']; ?>">
                                <textarea name="message" rows="2" placeholder="Why do you want this mentor?" required style="width: 100%; margin-bottom: 8px;"></textarea>
                                <button type="submit" name="request_mentor" class="btn-sm btn">🤝 Request Mentorship</button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">🌟 No mentors available at the moment. Check back later!</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>