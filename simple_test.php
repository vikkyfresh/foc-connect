<?php
echo "Step 1: Script started<br>";

$cc_host = 'btpaf0bjadqhld71gzms-mysql.services.clever-cloud.com';
$cc_user = 'usaypg7enbwrjvnm';
$cc_pass = '0jjwQuQBJ48iRZp6EynT';
$cc_name = 'btpaf0bjadqhld71gzms';

echo "Step 2: Connecting to database...<br>";

$conn = mysqli_connect($cc_host, $cc_user, $cc_pass, $cc_name);

if (!$conn) {
    echo "ERROR: " . mysqli_connect_error();
    exit();
}

echo "Step 3: Connected successfully!<br>";

$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
$row = mysqli_fetch_assoc($result);

echo "Step 4: Total users in database: " . $row['total'] . "<br>";

echo "Done!";
?>
