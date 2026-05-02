<?php
include 'includes/db.php';

// First, check what users exist
$users = mysqli_query($conn, "SELECT id, name, email, matric_number FROM users");
echo "<h3>Users in database:</h3>";
while($u = mysqli_fetch_assoc($users)) {
    echo "ID: {$u['id']} | {$u['name']} | {$u['email']} | {$u['matric_number']}<br>";
}

// Create a test user with a simple password
$test_matric = "TEST002";
$test_password = "password";
$test_hash = password_hash($test_password, PASSWORD_DEFAULT);

$check = mysqli_query($conn, "DELETE FROM users WHERE matric_number = '$test_matric'");
$insert = mysqli_query($conn, "INSERT INTO users (name, email, matric_number, password_hash, role, department_id, level_id, is_active) 
    VALUES ('Debug User', 'debug@foc.edu', '$test_matric', '$test_hash', 'student', 1, 1, 1)");

if($insert) {
    echo "<br>✅ Created user: $test_matric with password: $test_password<br>";
    
    // Now test the login immediately
    $verify = mysqli_query($conn, "SELECT * FROM users WHERE matric_number = '$test_matric'");
    $user = mysqli_fetch_assoc($verify);
    
    if(password_verify($test_password, $user['password_hash'])) {
        echo "<br>✅✅✅ PASSWORD VERIFICATION WORKS!<br>";
        echo "You can login with: $test_matric / $test_password<br>";
        echo "<a href='login.php'>Click here to login</a>";
    } else {
        echo "<br>❌ Password verification failed - hash issue";
    }
} else {
    echo "❌ Insert failed: " . mysqli_error($conn);
}
?>