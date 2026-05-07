<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get user_id from GET parameter
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if(!$user_id) {
    echo json_encode(['error' => 'No user_id provided', 'success' => false]);
    exit();
}

// InfinityFree Database
$host = 'sql200.infinityfree.com';
$user = 'if0_41808042';
$pass = 'f86pbwvj';
$db = 'if0_41808042_foc_connect';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    echo json_encode(['error' => 'Database connection failed', 'success' => false]);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'groups':
        $query = "SELECT cg.id, cg.name FROM chat_groups cg 
                  JOIN group_members gm ON cg.id = gm.group_id 
                  WHERE gm.user_id = $user_id";
        $result = mysqli_query($conn, $query);
        $data = [];
        while($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'contacts':
        $query = "SELECT id, name, matric_number FROM users 
                  WHERE id != $user_id 
                  ORDER BY name ASC";
        $result = mysqli_query($conn, $query);
        $data = [];
        while($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'messages':
        $type = isset($_GET['type']) ? $_GET['type'] : '';
        $chat_id = intval($_GET['id']);
        
        if($type == 'group') {
            $query = "SELECT m.*, u.name as sender_name, 'group' as type 
                      FROM messages m 
                      JOIN users u ON m.from_user_id = u.id 
                      WHERE m.group_id = $chat_id 
                      ORDER BY m.sent_at ASC LIMIT 200";
        } else {
            $query = "SELECT m.*, u.name as sender_name, 'user' as type 
                      FROM messages m 
                      JOIN users u ON m.from_user_id = u.id 
                      WHERE (m.from_user_id = $chat_id AND m.to_user_id = $user_id)
                         OR (m.from_user_id = $user_id AND m.to_user_id = $chat_id)
                      ORDER BY m.sent_at ASC LIMIT 200";
        }
        
        $result = mysqli_query($conn, $query);
        $messages = [];
        while($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $messages]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action', 'success' => false]);
}

mysqli_close($conn);
?>
