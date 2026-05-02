<?php
session_start();
include 'includes/db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Get filter parameters
$search_year = isset($_GET['year']) ? intval($_GET['year']) : '';
$search_dept = isset($_GET['department']) ? intval($_GET['department']) : '';
$search_company = isset($_GET['company']) ? mysqli_real_escape_string($conn, $_GET['company']) : '';

// Build query
$query = "SELECT u.id, u.name, u.email, u.matric_number, u.graduation_year, 
                 u.current_employer, u.current_position, u.linkedin_url, u.alumni_bio,
                 d.name as dept_name, d.code as dept_code
          FROM users u
          LEFT JOIN departments d ON u.department_id = d.id
          WHERE u.is_graduated = 1";

if($search_year) {
    $query .= " AND u.graduation_year = $search_year";
}
if($search_dept) {
    $query .= " AND u.department_id = $search_dept";
}
if($search_company) {
    $query .= " AND u.current_employer LIKE '%$search_company%'";
}

$query .= " ORDER BY u.graduation_year DESC, u.name ASC";
$alumni = mysqli_query($conn, $query);

// Get unique graduation years for filter
$years_query = "SELECT DISTINCT graduation_year FROM users WHERE is_graduated = 1 AND graduation_year IS NOT NULL ORDER BY graduation_year DESC";
$years = mysqli_query($conn, $years_query);

// Get departments for filter
$departments = mysqli_query($conn, "SELECT id, name FROM departments ORDER BY name");

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_alumni,
    COUNT(DISTINCT graduation_year) as total_years,
    COUNT(DISTINCT current_employer) as total_companies
    FROM users WHERE is_graduated = 1";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Directory - FoC Connect</title>
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
        
        .dashboard-container { padding: 24px; max-width: 1200px; margin: 0 auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 20px; padding: 20px; text-align: center; border: 1px solid #E8EDEC; }
        .stat-number { font-size: 32px; font-weight: 700; color: #2E7D64; }
        .stat-label { font-size: 14px; color: #6B7E78; margin-top: 8px; }
        
        .filter-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 24px; border: 1px solid #E8EDEC; }
        .filter-form { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-size: 12px; font-weight: 600; color: #1A2E28; margin-bottom: 5px; }
        .filter-group select, .filter-group input { width: 100%; padding: 10px; border: 1px solid #E8EDEC; border-radius: 40px; }
        .btn { background: #2E7D64; color: white; padding: 10px 20px; border: none; border-radius: 40px; cursor: pointer; }
        .btn-reset { background: #8A9B97; }
        
        .alumni-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 16px; border: 1px solid #E8EDEC; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; }
        .alumni-info h3 { font-size: 18px; color: #1A2E28; margin-bottom: 4px; }
        .alumni-meta { font-size: 13px; color: #6B7E78; margin-bottom: 8px; }
        .alumni-meta span { margin-right: 16px; }
        .alumni-bio { font-size: 14px; color: #4A5568; margin-top: 8px; }
        .alumni-contact { font-size: 13px; color: #2E7D64; margin-top: 8px; }
        .message-btn { background: #2E7D64; color: white; padding: 8px 16px; border-radius: 40px; text-decoration: none; font-size: 13px; display: inline-block; }
        
        .empty-state { text-align: center; padding: 60px; color: #8A9B97; background: white; border-radius: 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #2E7D64; text-decoration: none; }
        
        @media (max-width: 768px) { .main-content { margin-left: 0; } .sidebar { transform: translateX(-100%); } .stats-grid { grid-template-columns: 1fr; } }
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
            <a href="alumni-directory.php" class="nav-item active"><span class="nav-icon">🎓</span><span class="nav-text">Alumni Directory</span></a>
            <a href="job-board.php" class="nav-item"><span class="nav-icon">💼</span><span class="nav-text">Job Board</span></a>
            <a href="mentorship.php" class="nav-item"><span class="nav-icon">🤝</span><span class="nav-text">Mentorship</span></a>
            <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span class="nav-text">Settings</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span class="nav-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header">
            <div class="page-title">🎓 Alumni Directory</div>
            <div class="profile-btn">
                <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
                <div><?php echo htmlspecialchars($user_name); ?></div>
            </div>
        </header>

        <div class="dashboard-container">
            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-number"><?php echo $stats['total_alumni']; ?></div><div class="stat-label">Total Alumni</div></div>
                <div class="stat-card"><div class="stat-number"><?php echo $stats['total_years']; ?></div><div class="stat-label">Graduating Classes</div></div>
                <div class="stat-card"><div class="stat-number"><?php echo $stats['total_companies']; ?></div><div class="stat-label">Companies Represented</div></div>
            </div>
            
            <!-- Filters -->
            <div class="filter-card">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label>Graduation Year</label>
                        <select name="year">
                            <option value="">All Years</option>
                            <?php while($year = mysqli_fetch_assoc($years)): ?>
                                <option value="<?php echo $year['graduation_year']; ?>" <?php echo ($search_year == $year['graduation_year']) ? 'selected' : ''; ?>><?php echo $year['graduation_year']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Department</label>
                        <select name="department">
                            <option value="">All Departments</option>
                            <?php while($dept = mysqli_fetch_assoc($departments)): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($search_dept == $dept['id']) ? 'selected' : ''; ?>><?php echo $dept['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Company</label>
                        <input type="text" name="company" placeholder="Search by company..." value="<?php echo htmlspecialchars($search_company); ?>">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn">🔍 Search</button>
                        <a href="alumni-directory.php" class="btn btn-reset" style="display: inline-block; text-decoration: none; margin-left: 10px;">Reset</a>
                    </div>
                </form>
            </div>
            
            <!-- Alumni List -->
            <?php if(mysqli_num_rows($alumni) > 0): ?>
                <?php while($alumnus = mysqli_fetch_assoc($alumni)): ?>
                    <div class="alumni-card">
                        <div class="alumni-info">
                            <h3><?php echo htmlspecialchars($alumnus['name']); ?></h3>
                            <div class="alumni-meta">
                                <span>🎓 Class of <?php echo $alumnus['graduation_year']; ?></span>
                                <span>🏛️ <?php echo htmlspecialchars($alumnus['dept_name']); ?></span>
                                <span>📧 <?php echo htmlspecialchars($alumnus['email']); ?></span>
                            </div>
                            <?php if($alumnus['current_employer'] || $alumnus['current_position']): ?>
                                <div class="alumni-meta">
                                    <span>💼 <?php echo htmlspecialchars($alumnus['current_position']); ?> at <?php echo htmlspecialchars($alumnus['current_employer']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if($alumnus['alumni_bio']): ?>
                                <div class="alumni-bio"><?php echo htmlspecialchars(substr($alumnus['alumni_bio'], 0, 200)); ?></div>
                            <?php endif; ?>
                            <div class="alumni-contact">
                                <a href="chat.php?user_id=<?php echo $alumnus['id']; ?>" class="message-btn">💬 Send Message</a>
                                <?php if($alumnus['linkedin_url']): ?>
                                    <a href="<?php echo $alumnus['linkedin_url']; ?>" target="_blank" class="message-btn" style="background: #0077B5;">🔗 LinkedIn</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">🎓 No alumni found matching your criteria.</div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>