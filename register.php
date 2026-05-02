<?php
session_start();
include 'includes/db.php';

$error = '';
$success = '';

// Helper function to generate matric number
function generateMatricNumber($year, $department_code, $conn) {
    $prefix = $year . $department_code;
    $query = "SELECT MAX(CAST(SUBSTRING(matric_number, 7) AS UNSIGNED)) as max_num 
              FROM users 
              WHERE matric_number LIKE '$prefix%'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $next_num = ($row['max_num'] ?? 0) + 1;
    return $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $department_id = intval($_POST['department_id']);
    $level_id = intval($_POST['level_id']);
    $year_of_entry = intval($_POST['year_of_entry']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Default roles - students cannot assign themselves leadership roles
    $role = 'student';
    $student_role = 'student';
    
    // Get department code
    $dept_query = "SELECT code FROM departments WHERE id = $department_id";
    $dept_result = mysqli_query($conn, $dept_query);
    $dept = mysqli_fetch_assoc($dept_result);
    $department_code = $dept['code'];
    
    // Generate matric number
    $year_short = substr($year_of_entry, -2);
    $matric_number = generateMatricNumber($year_short, $department_code, $conn);
    
    // Validation
    if ($password !== $confirm_password) {
        $error = "❌ Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "❌ Password must be at least 6 characters";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "❌ Invalid email format";
    } elseif ($year_of_entry < 2020 || $year_of_entry > date('Y')) {
        $error = "❌ Invalid year of entry";
    } else {
        // Check if email already exists
        $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
        if (mysqli_num_rows($check) > 0) {
            $error = "❌ Email already registered";
        } else {
            // Hash password and insert - ALWAYS student role
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (name, email, matric_number, department_id, level_id, password_hash, role, student_role, is_active) 
                      VALUES ('$name', '$email', '$matric_number', $department_id, $level_id, '$password_hash', '$role', '$student_role', 1)";
            
            if (mysqli_query($conn, $query)) {
                $success = "✅ Registration successful!<br>📋 Your Matric Number: <strong>$matric_number</strong><br>Your role is <strong>STUDENT</strong>. Leadership roles can only be assigned by the HOD or Level Coordinator.<br><a href='login.php'>Click here to login</a>";
            } else {
                $error = "❌ Registration failed: " . mysqli_error($conn);
            }
        }
    }
}

// Get departments for dropdown
$dept_query = "SELECT id, name, code FROM departments ORDER BY name";
$dept_result = mysqli_query($conn, $dept_query);

// Get levels for dropdown
$level_query = "SELECT id, level_name FROM academic_levels ORDER BY sort_order";
$level_result = mysqli_query($conn, $level_query);

$current_year = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Register - FoC Connect</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #0F4C3A 0%, #2E7D64 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 550px;
            margin: 0 auto;
            background: white;
            border-radius: 32px;
            padding: 40px 30px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3);
        }
        h1 {
            font-size: 28px;
            text-align: center;
            color: #0F4C3A;
            margin-bottom: 8px;
        }
        .subtitle {
            text-align: center;
            color: #6B7E78;
            margin-bottom: 32px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1A2E28;
            font-size: 14px;
        }
        input, select {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #E0E8E5;
            border-radius: 48px;
            font-size: 16px;
            transition: border-color 0.2s;
            font-family: inherit;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #2E7D64;
        }
        button {
            width: 100%;
            padding: 16px;
            background: #2E7D64;
            color: white;
            border: none;
            border-radius: 48px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 16px;
            transition: all 0.2s;
        }
        button:hover {
            background: #236753;
            transform: scale(0.98);
        }
        .error {
            background: #FEF2F2;
            color: #E53E3E;
            padding: 12px 16px;
            border-radius: 48px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            border: 1px solid #FEE2E2;
        }
        .success {
            background: #E8F5E9;
            color: #2E7D64;
            padding: 16px;
            border-radius: 20px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            border: 1px solid #C6F6D5;
        }
        .login-link {
            text-align: center;
            margin-top: 24px;
            color: #6B7E78;
            font-size: 14px;
        }
        .login-link a {
            color: #2E7D64;
            text-decoration: none;
            font-weight: 600;
        }
        .info-note {
            background: #E8F5E9;
            padding: 16px;
            border-radius: 20px;
            margin-top: 24px;
            font-size: 13px;
            color: #0F4C3A;
            text-align: center;
            border-left: 4px solid #2E7D64;
        }
        .row {
            display: flex;
            gap: 15px;
        }
        .row .form-group {
            flex: 1;
        }
        .format-example {
            font-size: 12px;
            color: #8A9B97;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌿 Join FoC Connect</h1>
        <div class="subtitle">Faculty of Computing</div>
        
        <?php if($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" placeholder="e.g., John Doe" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="e.g., john.doe@foc.edu" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id" required>
                            <option value="">Select Department</option>
                            <?php while($dept = mysqli_fetch_assoc($dept_result)): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo $dept['name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Level</label>
                        <select name="level_id" required>
                            <option value="">Select Level</option>
                            <?php while($level = mysqli_fetch_assoc($level_result)): ?>
                                <option value="<?php echo $level['id']; ?>" <?php echo (isset($_POST['level_id']) && $_POST['level_id'] == $level['id']) ? 'selected' : ''; ?>>
                                    <?php echo $level['level_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Year of Entry</label>
                    <input type="number" name="year_of_entry" placeholder="e.g., 2023" min="2020" max="<?php echo $current_year; ?>" required value="<?php echo isset($_POST['year_of_entry']) ? $_POST['year_of_entry'] : ''; ?>">
                    <div class="format-example">📌 Your matric number will be auto-generated (e.g., 23CS1001)</div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Min 6 characters" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm password" required>
                    </div>
                </div>
                
                <button type="submit">Register →</button>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
            
            <div class="info-note">
                💡 <strong>Important Note:</strong><br>
                Your role will be set to <strong>STUDENT</strong> by default.<br>
                Leadership roles (Class Rep, President, etc.) are assigned by the<br>
                <strong>HOD</strong> or <strong>Level Coordinator</strong> after elections or appointments.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>