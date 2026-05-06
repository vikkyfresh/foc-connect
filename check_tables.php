<?php
session_start();
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br><br>";

// YOUR INFINITYFREE DATABASE - NOT Clever Cloud!
$host = 'sql200.infinityfree.com';
$user = 'if0_41808042';
$pass = 'f86pbwvj';
$db = 'if0_41808042_foc_connect';

$conn = mysqli_connect($host, $user, $pass, $db);

if(!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "✅ Connected to InfinityFree database!<br><br>";

// Check all tables
$tables = mysqli_query($conn, "SHOW TABLES");
echo "<strong>Tables in database:</strong><br>";
$hasTables = false;
while($row = mysqli_fetch_array($tables)) {
    $hasTables = true;
    echo "- " . $row[0] . "<br>";
}

if(!$hasTables) {
    echo "❌ NO TABLES FOUND! Database is empty.<br>";
}

echo "<br>";

// Check if user 10 exists
$user_check = mysqli_query($conn, "SELECT * FROM users WHERE id = 10");
if(mysqli_num_rows($user_check) > 0) {
    $user = mysqli_fetch_assoc($user_check);
    echo "✅ User 10 exists: " . $user['name'] . "<br>";
} else {
    echo "❌ User 10 does NOT exist in users table<br>";
}

// Check groups for user 10
$groups = mysqli_query($conn, "SELECT cg.* FROM chat_groups cg 
                               JOIN group_members gm ON cg.id = gm.group_id 
                               WHERE gm.user_id = 10");
if(mysqli_num_rows($groups) > 0) {
    echo "✅ User 10 is in " . mysqli_num_rows($groups) . " group(s)<br>";
    while($group = mysqli_fetch_assoc($groups)) {
        echo "  - " . $group['name'] . "<br>";
    }
} else {
    echo "❌ User 10 is NOT in any groups<br>";
}

// Check contacts (other users)
$contacts = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE id != 10");
$count = mysqli_fetch_assoc($contacts);
if($count['total'] > 0) {
    echo "✅ There are " . $count['total'] . " other user(s) in database<br>";
} else {
    echo "❌ No other users found<br>";
}
?>
