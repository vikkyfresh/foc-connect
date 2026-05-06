<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
session_start();

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Connect to Clever Cloud
$cc_host = 'btpaf0bjadqhld71gzms-mysql.services.clever-cloud.com';
$cc_user = 'usaypg7enbwrjvnm';
$cc_pass = '0jjwQuQBJ48iRZp6EynT';
$cc_name = 'btpaf0bjadqhld71gzms';
$cc_port = 3306;

$conn = mysqli_connect($cc_host, $cc_user, $cc_pass, $cc_name, $cc_port);

if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

mysqli_set_charset($conn, 'utf8mb4');

$action = isset($_GET['action']) ? $_GET['action'] : '';

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

mysqli_close($conn);
?>
