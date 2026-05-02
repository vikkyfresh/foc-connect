<?php
session_start();
include 'includes/db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$reason = isset($_GET['reason']) ? mysqli_real_escape_string($conn, $_GET['reason']) : '';

if($message_id && $reason) {
    // Check if already reported
    $check = mysqli_query($conn, "SELECT id FROM moderation_logs WHERE target_message_id = $message_id AND target_user_id = $user_id AND action = 'report'");
    if(mysqli_num_rows($check) == 0) {
        // Log the report
        $insert = "INSERT INTO moderation_logs (moderator_id, action, target_message_id, target_user_id, reason) 
                   VALUES ($user_id, 'report', $message_id, $user_id, '$reason')";
        mysqli_query($conn, $insert);
        
        // Increment report count on message
        mysqli_query($conn, "UPDATE messages SET report_count = report_count + 1, is_reported = TRUE WHERE id = $message_id");
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();
?>