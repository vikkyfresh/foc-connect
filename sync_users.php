<?php
session_start();

// Check if admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Admin access only");
}

// Main database (InfinityFree - your main app)
$main_host = 'sql200.infinityfree.com';
$main_user = 'if0_41808042';
$main_pass = 'f86pbwvj';
$main_db = 'if0_41808042_your_main_db'; // CHANGE THIS to your main database name

// Chat database (same server, different database)
$chat_host = 'sql200.infinityfree.com';
$chat_user = 'if0_41808042';
$chat_pass = 'f86pbwvj';
$chat_db = 'if0_41808042_foc_connect';

$main_conn = mysqli_connect($main_host, $main_user, $main_pass, $main_db);
$chat_conn = mysqli_connect($chat_host, $chat_user, $chat_pass, $chat_db);

if (!$main_conn || !$chat_conn) {
    die("Connection failed");
}

// Get all users from main database
$users = mysqli_query($main_conn, "SELECT id, name, department_id, matric_number, role FROM users");

$synced = 0;
$errors = 0;

while($user = mysqli_fetch_assoc($users)) {
    // Check if exists in chat database
    $check = mysqli_query($chat_conn, "SELECT id FROM users WHERE id = " . $user['id']);
    
    if(mysqli_num_rows($check) == 0) {
        // Insert into chat database
        $insert = "INSERT INTO users (id, name, department_id, matric_number, role, created_at) 
                   VALUES (
                       {$user['id']},
                       '" . mysqli_real_escape_string($chat_conn, $user['name']) . "',
                       {$user['department_id']},
                       '" . mysqli_real_escape_string($chat_conn, $user['matric_number']) . "',
                       '" . mysqli_real_escape_string($chat_conn, $user['role']) . "',
                       NOW()
                   )";
        
        if(mysqli_query($chat_conn, $insert)) {
            $synced++;
            echo "✅ Synced: {$user['name']} ({$user['matric_number']})<br>";
        } else {
            $errors++;
            echo "❌ Failed: {$user['name']} - " . mysqli_error($chat_conn) . "<br>";
        }
    } else {
        echo "⏭️ Already exists: {$user['name']}<br>";
    }
}

echo "<hr>";
echo "✅ Synced: $synced users<br>";
echo "❌ Errors: $errors<br>";
echo "🎉 Done!";

mysqli_close($main_conn);
mysqli_close($chat_conn);
?>
