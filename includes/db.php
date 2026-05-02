<?php
$host = 'localhost';
$user = 'root';
$password = '';  // XAMPP default is empty
$database = 'foc_connect';

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");

// For debugging - remove in production
// echo "Connected successfully";
?>