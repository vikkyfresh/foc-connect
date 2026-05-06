<?php
$cc_host = 'btpaf0bjadqhld71gzms-mysql.services.clever-cloud.com';
$cc_user = 'usaypg7enbwrjvnm';
$cc_pass = '0jjwQuQBJ48iRZp6EynT';
$cc_name = 'btpaf0bjadqhld71gzms';

$conn = mysqli_connect($cc_host, $cc_user, $cc_pass, $cc_name);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "<h2>Checking data for user_id = 10</h2>";

// Check if user exists in chat database
$user_check = mysqli_query($conn, "SELECT * FROM users WHERE id = 10");
if(mysqli_num_rows($user_check) > 0) {
    $user = mysqli_fetch_assoc($user_check);
    echo "✅ User found: " . $user['name'] . "<br>";
} else {
    echo "❌ User ID 10 does NOT exist in Clever Cloud users table<br>";
}

// Check groups
$groups = mysqli_query($conn, "SELECT cg.id, cg.name FROM chat_groups cg 
                               JOIN group_members gm ON cg.id = gm.group_id 
                               WHERE gm.user_id = 10");

if(mysqli_num_rows($groups) > 0) {
    echo "<h3>Groups for user 10:</h3>";
    while($row = mysqli_fetch_assoc($groups)) {
        echo "- " . $row['name'] . " (ID: " . $row['id'] . ")<br>";
    }
} else {
    echo "❌ User 10 is not in any groups<br>";
}

// Check contacts (other users)
$contacts = mysqli_query($conn, "SELECT id, name FROM users WHERE id != 10 LIMIT 5");

if(mysqli_num_rows($contacts) > 0) {
    echo "<h3>Other users in database:</h3>";
    while($row = mysqli_fetch_assoc($contacts)) {
        echo "- " . $row['name'] . " (ID: " . $row['id'] . ")<br>";
    }
} else {
    echo "❌ No other users found in database<br>";
}
?>
