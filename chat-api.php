<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
session_start();

include 'includes/db.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different actions
switch($action) {
    case 'groups':
        $groups = mysqli_query($conn, "SELECT cg.id, cg.name FROM chat_groups cg 
                                       JOIN group_members gm ON cg.id = gm.group_id 
                                       WHERE gm.user_id = $user_id");
        $data = [];
        while($row = mysqli_fetch_assoc($groups)) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'messages':
        $type = isset($_GET['type']) ? $_GET['type'] : '';
        $id = intval($_GET['id']);
        
        if($type == 'group') {
            $query = "SELECT m.*, u.name as sender_name 
                      FROM messages m 
                      JOIN users u ON m.from_user_id = u.id 
                      WHERE m.group_id = $id 
                      ORDER BY m.sent_at ASC LIMIT 100";
        } else {
            $query = "SELECT m.*, u.name as sender_name 
                      FROM messages m 
                      JOIN users u ON m.from_user_id = u.id 
                      WHERE (m.from_user_id = $id AND m.to_user_id = $user_id)
                         OR (m.from_user_id = $user_id AND m.to_user_id = $id)
                      ORDER BY m.sent_at ASC LIMIT 100";
        }
        
        $result = mysqli_query($conn, $query);
        $messages = [];
        while($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $messages]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
