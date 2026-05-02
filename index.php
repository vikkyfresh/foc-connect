<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>FoC Connect</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#667eea">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            text-align: center;
        }
        h1 {
            font-size: 32px;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 32px;
            font-size: 14px;
        }
        .faculty-badge {
            background: #f0f0f0;
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 24px;
            font-size: 12px;
            color: #667eea;
            font-weight: 600;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 16px;
            margin: 12px 0;
            border: none;
            border-radius: 48px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: transform 0.2s, opacity 0.2s;
        }
        .btn:active {
            transform: scale(0.98);
        }
        .btn-login {
            background: #667eea;
            color: white;
        }
        .btn-register {
            background: #48bb78;
            color: white;
        }
        .features {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 16px;
        }
        .feature {
            text-align: center;
            font-size: 12px;
            color: #888;
        }
        .feature span {
            font-size: 24px;
            display: block;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="faculty-badge">🏛️ FACULTY OF COMPUTING</div>
        <h1>🎓 FoC Connect</h1>
        <div class="subtitle">Connect • Learn • Communicate</div>
        
        <a href="login.php" class="btn btn-login">🔐 Login</a>
        <a href="register.php" class="btn btn-register">📝 Register</a>
        
        <div class="features">
            <div class="feature"><span>💬</span> Real-time Chat</div>
            <div class="feature"><span>📢</span> Announcements</div>
            <div class="feature"><span>👥</span> Department Groups</div>
            <div class="feature"><span>📱</span> Mobile App</div>
        </div>
    </div>
</body>
</html>