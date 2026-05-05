<?php
// FoC Connect - Database Connection (InfinityFree MySQL)
$conn = mysqli_connect(
    'sql200.infinityfree.com',
    'if0_41808042',
    'f86pbwvj',
    'if0_41808042_foc_connect'
);

if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');
?>
