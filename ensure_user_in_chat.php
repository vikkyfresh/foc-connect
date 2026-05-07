<?php
// Run this file once to add ALL users to chat database
$chat_host = 'sql200.infinityfree.com';
$chat_user = 'if0_41808042';
$chat_pass = 'f86pbwvj';
$chat_db = 'if0_41808042_foc_connect';

$chat_conn = mysqli_connect($chat_host, $chat_user, $chat_pass, $chat_db);

if (!$chat_conn) {
    die("Chat DB connection failed: " . mysqli_connect_error());
}

// First, check what users are currently in chat DB
$existing = mysqli_query($chat_conn, "SELECT id, name, matric_number FROM users");
echo "<h3>Current users in chat database:</h3>";
while($row = mysqli_fetch_assoc($existing)) {
    echo "ID: {$row['id']} - {$row['name']} ({$row['matric_number']})<br>";
}

// If user 23CS1001 is missing, add them
$check = mysqli_query($chat_conn, "SELECT id FROM users WHERE matric_number = '23CS1001' OR id = 11");
if(mysqli_num_rows($check) == 0) {
    echo "<h3>Adding user 23CS1001...</h3>";
    $insert = "INSERT INTO users (id, name, department_id, matric_number, role) VALUES 
               (11, 'John Doe', 1, '23CS1001', 'student')";
    if(mysqli_query($chat_conn, $insert)) {
        echo "✅ User 23CS1001 added successfully!<br>";
    } else {
        echo "❌ Failed to add: " . mysqli_error($chat_conn) . "<br>";
    }
} else {
    echo "<h3>✅ User 23CS1001 already exists in chat database</h3>";
}

// Show all users now
echo "<h3>All users in chat database:</h3>";
$all = mysqli_query($chat_conn, "SELECT id, name, matric_number FROM users");
while($row = mysqli_fetch_assoc($all)) {
    echo "ID: {$row['id']} - {$row['name']} ({$row['matric_number']})<br>";
}
?>
