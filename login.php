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
        button:active {
            background: #236753;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🌿 FoC Connect</h1>
        <div class="subtitle">Faculty of Computing</div>
        
        <?php if($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="text" name="email" placeholder="Email or Matric Number" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login →</button>
        </form>
        
        <div class="register-link">
            New to FoC Connect? <a href="register.php">Create account</a>
        </div>
        
        <div class="demo-note">
            💚 Connect with your department • Stay updated • Learn together
        </div>
    </div>
</body>
</html>