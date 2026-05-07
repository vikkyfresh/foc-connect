<?php
session_start();
include 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    $query = "SELECT u.*, d.name as dept_name, d.code as dept_code 
              FROM users u 
              LEFT JOIN departments d ON u.department_id = d.id 
              WHERE u.email = '$email' OR u.matric_number = '$email'";
    $result = mysqli_query($conn, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['name'] = $row['name'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['department_id'] = $row['department_id'];
            $_SESSION['dept_code'] = $row['dept_code'];
            $_SESSION['level_id'] = $row['level_id'];
            $_SESSION['matric_number'] = $row['matric_number'];
            
            // =============================================
            // SYNC USER TO CHAT DATABASE (InfinityFree)
            // =============================================
            $chat_host = 'sql200.infinityfree.com';
            $chat_user = 'if0_41808042';
            $chat_pass = 'f86pbwvj';
            $chat_db = 'if0_41808042_foc_connect';
            
            $chat_conn = mysqli_connect($chat_host, $chat_user, $chat_pass, $chat_db);
            
            if ($chat_conn) {
                // Check if user exists in chat database
                $check_query = "SELECT id FROM users WHERE id = " . intval($row['id']);
                $check_result = mysqli_query($chat_conn, $check_query);
                
                if (mysqli_num_rows($check_result) == 0) {
                    // Add user to chat database
                    $insert_query = "INSERT INTO users (id, name, department_id, matric_number, role, created_at) 
                                     VALUES (
                                         " . intval($row['id']) . ", 
                                         '" . mysqli_real_escape_string($chat_conn, $row['name']) . "', 
                                         " . intval($row['department_id'] ?? 1) . ", 
                                         '" . mysqli_real_escape_string($chat_conn, $row['matric_number'] ?? '') . "', 
                                         '" . mysqli_real_escape_string($chat_conn, $row['role'] ?? 'student') . "',
                                         NOW()
                                     )";
                    
                    if (mysqli_query($chat_conn, $insert_query)) {
                        error_log("User {$row['id']} added to chat database");
                    } else {
                        error_log("Failed to add user to chat database: " . mysqli_error($chat_conn));
                    }
                } else {
                    // Update existing user
                    $update_query = "UPDATE users SET 
                                     name = '" . mysqli_real_escape_string($chat_conn, $row['name']) . "',
                                     department_id = " . intval($row['department_id'] ?? 1) . ",
                                     matric_number = '" . mysqli_real_escape_string($chat_conn, $row['matric_number'] ?? '') . "'
                                     WHERE id = " . intval($row['id']);
                    mysqli_query($chat_conn, $update_query);
                }
                
                mysqli_close($chat_conn);
            } else {
                error_log("Chat database connection failed: " . mysqli_connect_error());
            }
            // =============================================
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "❌ Incorrect password";
        }
    } else {
        $error = "❌ User not found";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - FoC Connect</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0F4C3A">
    <link rel="manifest" href="manifest.json">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #0F4C3A;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 32px;
            padding: 40px 30px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h1 {
            font-size: 32px;
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
        input {
            width: 100%;
            padding: 16px;
            margin: 10px 0;
            border: 1px solid #E0E8E5;
            border-radius: 48px;
            font-size: 16px;
            background: #F5F7F6;
            transition: all 0.2s;
        }
        input:focus {
            outline: none;
            border-color: #2E7D64;
            background: white;
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
        }
        button:active {
            transform: scale(0.98);
        }
        .error {
            background: #FFF0F0;
            color: #E05A5A;
            padding: 12px;
            border-radius: 48px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        .register-link {
            text-align: center;
            margin-top: 24px;
            color: #8A9B97;
            font-size: 14px;
        }
        .register-link a {
            color: #2E7D64;
            text-decoration: none;
            font-weight: 600;
        }
        .demo-note {
            background: #E8F5E9;
            padding: 12px;
            border-radius: 16px;
            margin-top: 24px;
            font-size: 12px;
            color: #0F4C3A;
            text-align: center;
        }
        
        /* Loading state */
        button.loading {
            background: #8A9B97;
            cursor: wait;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌿 FoC Connect</h1>
        <div class="subtitle">Faculty of Computing</div>
        
        <?php if($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <input type="text" name="email" placeholder="Email or Matric Number" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" id="loginBtn">Login →</button>
        </form>
        
        <div class="register-link">
            New to FoC Connect? <a href="register.php">Create account</a>
        </div>
        
        <div class="demo-note">
            💚 Connect with your department • Stay updated • Learn together
        </div>
    </div>
    
    <script>
        // Add loading state to button
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.innerHTML = '⏳ Logging in...';
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>
</body>
</html>
