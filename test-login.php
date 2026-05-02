<?php
include 'includes/db.php';

echo "<h2>🔍 Login Debugger</h2>";

// Get all users
$result = mysqli_query($conn, "SELECT id, name, email, matric_number, role FROM users");
echo "<h3>Users in Database:</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Matric Number</th><th>Role</th></tr>";
while($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['name']}</td>";
    echo "<td>{$row['email']}</td>";
    echo "<td>{$row['matric_number']}</td>";
    echo "<td>{$row['role']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test login with different credentials
echo "<h3>Test Login Attempts:</h3>";

$tests = [
    ['matric' => 'TEST002', 'email' => 'debug@foc.edu', 'password' => 'password'],
    ['matric' => '23CS1001', 'email' => 'john.doe@foc.edu', 'password' => 'password'],
    ['matric' => 'WORK001', 'email' => 'work@foc.edu', 'password' => '123456'],
    ['matric' => 'TEST001', 'email' => 'test@foc.edu', 'password' => 'password'],
];

foreach($tests as $test) {
    echo "<p><strong>Testing:</strong> {$test['matric']} / {$test['password']}</p>";
    
    $query = "SELECT u.*, d.name as dept_name 
              FROM users u 
              LEFT JOIN departments d ON u.department_id = d.id 
              WHERE u.matric_number = '{$test['matric']}' OR u.email = '{$test['email']}'";
    
    $result = mysqli_query($conn, $query);
    
    if($row = mysqli_fetch_assoc($result)) {
        echo "✅ User found: {$row['name']}<br>";
        echo "Stored hash: {$row['password_hash']}<br>";
        
        if(password_verify($test['password'], $row['password_hash'])) {
            echo "✅✅ PASSWORD MATCHES! Login would work.<br>";
        } else {
            echo "❌ Password does NOT match.<br>";
            // Generate a new hash for testing
            $new_hash = password_hash($test['password'], PASSWORD_DEFAULT);
            echo "To fix, run: UPDATE users SET password_hash = '$new_hash' WHERE id = {$row['id']};<br>";
        }
    } else {
        echo "❌ User not found with matric/email: {$test['matric']}<br>";
    }
    echo "<hr>";
}
?>

<a href="login.php">Go to Login Page</a>