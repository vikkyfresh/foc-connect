<?php
include 'includes/db.php';

$name = "Working Student";
$email = "work@foc.edu";
$matric = "WORK001";
$password = "123456";
$password_hash = password_hash($password, PASSWORD_DEFAULT);

$query = "INSERT INTO users (name, email, matric_number, password_hash, role, department_id, level_id, is_active) 
          VALUES ('$name', '$email', '$matric', '$password_hash', 'student', 1, 1, 1)";

if(mysqli_query($conn, $query)) {
    echo "✅ User created successfully!<br>";
    echo "Matric Number: WORK001<br>";
    echo "Password: 123456<br>";
    echo "<a href='login.php'>Go to Login</a>";
} else {
    echo "❌ Error: " . mysqli_error($conn);
}
?>