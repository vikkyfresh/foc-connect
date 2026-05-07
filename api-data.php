<?php
// ============================================
// api-data.php — Lightweight JSON API
// Used by talk.php to load groups, contacts
// and messages from InfinityFree MySQL
// ============================================

session_start();
include 'includes/db.php';

// Always return JSON — never HTML
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id  = (int)$_SESSION['user_id'];
$action   = $_GET['action'] ?? '';

// ============================================
// ACTION: groups — get user's chat groups
// ============================================
if ($action === 'groups') {
    $result = mysqli_query($conn,
        "SELECT cg.id, cg.name, cg.group_type
         FROM chat_groups cg
         JOIN group_members gm ON cg.id = gm.group_id
         WHERE gm.user_id = $user_id AND cg.is_active = 1
         ORDER BY cg.group_type ASC, cg.name ASC
         LIMIT 20"
    );

    $groups = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $groups[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $groups]);
    exit();
}

// ============================================
// ACTION: contacts — get students in same dept
// ============================================
if ($action === 'contacts') {
    // Get current user's department
    $user_row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT department_id FROM users WHERE id = $user_id LIMIT 1"
    ));
    $dept_id = (int)($user_row['department_id'] ?? 0);

    if ($dept_id > 0) {
        $result = mysqli_query($conn,
            "SELECT id, name, matric_number, role
             FROM users
             WHERE department_id = $dept_id
               AND id != $user_id
               AND is_active = 1
             ORDER BY name ASC
             LIMIT 30"
        );
    } else {
        // Admin/dean — show all students
        $result = mysqli_query($conn,
            "SELECT id, name, matric_number, role
             FROM users
             WHERE id != $user_id
               AND is_active = 1
               AND role = 'student'
             ORDER BY name ASC
             LIMIT 30"
        );
    }

    $contacts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $contacts[] = [
            'id'            => $row['id'],
            'name'          => $row['name'],
            'matric_number' => $row['matric_number'] ?? '',
            'role'          => $row['role']
        ];
    }

    echo json_encode(['success' => true, 'data' => $contacts]);
    exit();
}

// ============================================
// ACTION: messages — get messages for a chat
// ============================================
if ($action === 'messages') {
    $type = $_GET['type'] ?? '';
    $id   = (int)($_GET['id'] ?? 0);

    if (!$type || !$id) {
        echo json_encode(['success' => false, 'error' => 'Missing type or id']);
        exit();
    }

    if ($type === 'group') {
        $result = mysqli_query($conn,
            "SELECT m.*, u.name as sender_name
             FROM messages m
             JOIN users u ON m.from_user_id = u.id
             WHERE m.group_id = $id AND m.is_deleted = 0
             ORDER BY m.sent_at ASC
             LIMIT 100"
        );
    } else {
        // DM — messages between current user and the other user
        $result = mysqli_query($conn,
            "SELECT m.*, u.name as sender_name
             FROM messages m
             JOIN users u ON m.from_user_id = u.id
             WHERE m.group_id IS NULL
               AND m.is_deleted = 0
               AND (
                   (m.from_user_id = $user_id AND m.to_user_id = $id)
                   OR
                   (m.from_user_id = $id AND m.to_user_id = $user_id)
               )
             ORDER BY m.sent_at ASC
             LIMIT 100"
        );
    }

    $messages = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $messages]);
    exit();
}

// ============================================
// Unknown action
// ============================================
echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
