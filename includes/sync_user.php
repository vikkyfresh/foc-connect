<?php
// ============================================
// FoC Connect - User Sync Helper
// Include this in your register.php after INSERT
// Syncs new users to Clever Cloud via Render API
// ============================================

function syncUserToChat($user_id, $name, $email, $role, $department_id, $level_id, $matric_number, $is_active = 1) {
    $render_url = 'https://foc-connect-websocket.onrender.com/api/sync-user';

    $data = json_encode([
        'id'            => $user_id,
        'name'          => $name,
        'email'         => $email,
        'role'          => $role,
        'department_id' => $department_id,
        'level_id'      => $level_id,
        'matric_number' => $matric_number,
        'is_active'     => $is_active
    ]);

    $ch = curl_init($render_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // don't block the page for long
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Chat sync failed for user $user_id: $error");
        return false;
    }

    $result = json_decode($response, true);
    return isset($result['success']) && $result['success'];
}
?>
