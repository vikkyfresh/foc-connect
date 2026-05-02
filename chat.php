<?php
session_start();
include 'includes/db.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];

// Check if user is banned
$banned_check = mysqli_query($conn, "SELECT * FROM banned_users WHERE user_id = $user_id AND (expires_at IS NULL OR expires_at > NOW())");
if(mysqli_num_rows($banned_check) > 0) {
    $ban = mysqli_fetch_assoc($banned_check);
    die("🚫 You have been banned from the platform. " . ($ban['expires_at'] ? "Ban expires: " . date('M d, Y', strtotime($ban['expires_at'])) : "This is a permanent ban."));
}

// Get user details
$user_query = "SELECT u.*, d.name as dept_name, d.code as dept_code, a.level_name
               FROM users u 
               LEFT JOIN departments d ON u.department_id = d.id 
               LEFT JOIN academic_levels a ON u.level_id = a.id
               WHERE u.id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

// Get all chat groups the user belongs to
$groups_query = "SELECT cg.*, 
                 (SELECT COUNT(*) FROM messages WHERE group_id = cg.id AND sent_at > IFNULL((SELECT MAX(read_at) FROM messages WHERE from_user_id = $user_id AND group_id = cg.id), '1900-01-01')) as unread
                 FROM chat_groups cg
                 JOIN group_members gm ON cg.id = gm.group_id
                 WHERE gm.user_id = $user_id
                 ORDER BY cg.name ASC";
$groups = mysqli_query($conn, $groups_query);

// Get selected chat
$selected_group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : null;
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

$selected_name = '';
$selected_type = '';
$selected_id = null;

if($selected_group_id) {
    $group_query = "SELECT name FROM chat_groups WHERE id = $selected_group_id";
    $group_result = mysqli_query($conn, $group_query);
    if($group = mysqli_fetch_assoc($group_result)) {
        $selected_name = $group['name'];
        $selected_type = 'group';
        $selected_id = $selected_group_id;
    }
} elseif($selected_user_id) {
    $user_query2 = "SELECT name FROM users WHERE id = $selected_user_id";
    $user_result2 = mysqli_query($conn, $user_query2);
    if($chat_user = mysqli_fetch_assoc($user_result2)) {
        $selected_name = $chat_user['name'];
        $selected_type = 'user';
        $selected_id = $selected_user_id;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Chat - FoC Connect</title>
    <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #F5F7F6; height: 100vh; overflow: hidden; }

        /* Chat Container */
        .chat-app { display: flex; height: 100vh; }

        /* Sidebar (Chat List) */
        .chat-sidebar { width: 320px; background: white; border-right: 1px solid #E8EDEC; display: flex; flex-direction: column; overflow: hidden; }
        .sidebar-header { padding: 20px; background: #0F4C3A; color: white; }
        .sidebar-header h2 { font-size: 20px; }
        .sidebar-header p { font-size: 12px; opacity: 0.8; margin-top: 4px; }
        .search-box { padding: 12px 16px; background: white; border-bottom: 1px solid #E8EDEC; }
        .search-box input { width: 100%; padding: 10px 16px; border: 1px solid #E8EDEC; border-radius: 30px; font-size: 14px; background: #F5F7F6; outline: none; }
        .search-box input:focus { border-color: #2E7D64; background: white; }
        .chat-list { flex: 1; overflow-y: auto; }

        .chat-section { padding: 12px 16px; font-weight: 700; color: #0F4C3A; font-size: 12px; background: #E8F5E9; border-bottom: 1px solid #E8EDEC; margin-top: 8px; }
        .chat-subsection { padding: 8px 16px; font-weight: 600; color: #6B7E78; font-size: 11px; background: #F5F7F6; border-bottom: 1px solid #E8EDEC; margin-left: 16px; }

        .chat-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; cursor: pointer; transition: background 0.2s; border-bottom: 1px solid #F0F2F5; }
        .chat-item:hover { background: #F5F7F6; }
        .chat-item.active { background: #E8F5E9; border-left: 3px solid #2E7D64; }
        .chat-avatar { width: 48px; height: 48px; background: #2E7D64; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .chat-info { flex: 1; min-width: 0; }
        .chat-name { font-weight: 600; font-size: 15px; color: #1A2E28; }
        .chat-preview { font-size: 13px; color: #8A9B97; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .chat-meta { text-align: right; flex-shrink: 0; }
        .chat-time { font-size: 11px; color: #8A9B97; }
        .unread-badge { background: #2E7D64; color: white; font-size: 11px; padding: 2px 8px; border-radius: 20px; margin-top: 4px; }

        /* Main Chat Area */
        .chat-main { flex: 1; display: flex; flex-direction: column; background: white; }
        .chat-header { padding: 16px 20px; border-bottom: 1px solid #E8EDEC; display: flex; align-items: center; gap: 16px; background: white; }
        .back-btn { display: none; background: none; border: none; font-size: 24px; cursor: pointer; color: #1A2E28; }
        .chat-header-info { flex: 1; }
        .chat-header-name { font-weight: 600; font-size: 18px; color: #1A2E28; }
        .chat-header-status { font-size: 12px; color: #8A9B97; }
        .messages-container { flex: 1; overflow-y: auto; padding: 20px; background: #F5F7F6; display: flex; flex-direction: column; gap: 8px; }
        .message { display: flex; margin-bottom: 8px; position: relative; }
        .message.sent { justify-content: flex-end; }
        .message.received { justify-content: flex-start; }
        .bubble { max-width: 70%; padding: 10px 14px; border-radius: 20px; font-size: 14px; line-height: 1.4; word-wrap: break-word; position: relative; }
        .message.sent .bubble { background: #2E7D64; color: white; border-bottom-right-radius: 4px; }
        .message.received .bubble { background: white; color: #1A2E28; border: 1px solid #E8EDEC; border-bottom-left-radius: 4px; }
        .message-info { font-size: 10px; margin-top: 4px; color: #8A9B97; display: flex; gap: 8px; align-items: center; }
        .message.sent .message-info { justify-content: flex-end; }
        .report-btn { position: absolute; top: 0; right: -30px; background: none; border: none; cursor: pointer; font-size: 14px; opacity: 0; transition: opacity 0.2s; }
        .message:hover .report-btn { opacity: 1; }
        .typing-indicator { padding: 8px 20px; font-size: 12px; color: #8A9B97; font-style: italic; }
        .input-area { padding: 16px 20px; background: white; border-top: 1px solid #E8EDEC; display: flex; gap: 12px; align-items: center; }
        .input-area input { flex: 1; padding: 12px 16px; border: 1px solid #E8EDEC; border-radius: 30px; font-size: 15px; outline: none; background: #F5F7F6; }
        .input-area input:focus { border-color: #2E7D64; background: white; }
        .send-btn { background: #2E7D64; border: none; color: white; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 18px; }
        .empty-chat { text-align: center; padding: 60px 20px; color: #8A9B97; }
        .moderation-notice { background: #FEF2F2; color: #E53E3E; padding: 8px 16px; text-align: center; font-size: 12px; }

        @media (max-width: 768px) {
            .chat-sidebar { position: fixed; left: -320px; top: 0; bottom: 0; z-index: 200; transition: left 0.3s; }
            .chat-sidebar.open { left: 0; }
            .back-btn { display: block; }
            .overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 199; display: none; }
            .overlay.open { display: block; }
        }
    </style>
</head>
<body>
<div class="chat-app">
    <!-- Chat Sidebar -->
    <div class="chat-sidebar" id="chatSidebar">
        <div class="sidebar-header">
            <h2>💬 Chats</h2>
            <p><?php echo htmlspecialchars($user_name); ?> • <?php echo htmlspecialchars($user['dept_name']); ?></p>
        </div>
        <div class="search-box">
            <input type="text" id="searchChats" placeholder="🔍 Search chats...">
        </div>
        <div class="chat-list" id="chatList">
            
            <!-- GROUPS SECTION -->
            <div class="chat-section">💬 GROUPS</div>
            <?php while($group = mysqli_fetch_assoc($groups)): ?>
                <div class="chat-item <?php echo ($selected_group_id == $group['id']) ? 'active' : ''; ?>" 
                     onclick="openChat('group', <?php echo $group['id']; ?>, '<?php echo addslashes($group['name']); ?>')">
                    <div class="chat-avatar">👥</div>
                    <div class="chat-info">
                        <div class="chat-name"><?php echo htmlspecialchars($group['name']); ?></div>
                        <div class="chat-preview">Group chat</div>
                    </div>
                    <div class="chat-meta">
                        <?php if($group['unread'] > 0): ?>
                            <div class="unread-badge"><?php echo $group['unread']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>

            <!-- STUDENTS BY LEVEL -->
            <div class="chat-section">👨‍🎓 STUDENTS</div>
            <?php
            $level_names = ['', '100L', '200L', '300L', '400L'];
            for($level = 1; $level <= 4; $level++):
                $students_query = "SELECT id, name, matric_number FROM users 
                                   WHERE department_id = {$user['department_id']} 
                                   AND role = 'student' 
                                   AND level_id = $level 
                                   AND is_graduated = 0
                                   AND id != $user_id
                                   ORDER BY name";
                $students_list = mysqli_query($conn, $students_query);
                if(mysqli_num_rows($students_list) > 0):
            ?>
                <div class="chat-subsection">📚 <?php echo $level_names[$level]; ?></div>
                <?php while($student = mysqli_fetch_assoc($students_list)): ?>
                    <div class="chat-item <?php echo ($selected_user_id == $student['id']) ? 'active' : ''; ?>" 
                         onclick="openChat('user', <?php echo $student['id']; ?>, '<?php echo addslashes($student['name']); ?>')">
                        <div class="chat-avatar">👤</div>
                        <div class="chat-info">
                            <div class="chat-name"><?php echo htmlspecialchars($student['name']); ?></div>
                            <div class="chat-preview"><?php echo htmlspecialchars($student['matric_number']); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; endfor; ?>

            <!-- LECTURERS SECTION -->
            <div class="chat-section">👨‍🏫 LECTURERS</div>
            <?php
            $lecturers_query = "SELECT id, name, email FROM users 
                                WHERE department_id = {$user['department_id']} 
                                AND role IN ('lecturer', 'level_coordinator', 'hod', 'dept_exam_officer')
                                AND id != $user_id
                                ORDER BY name";
            $lecturers = mysqli_query($conn, $lecturers_query);
            while($lecturer = mysqli_fetch_assoc($lecturers)):
            ?>
                <div class="chat-item <?php echo ($selected_user_id == $lecturer['id']) ? 'active' : ''; ?>" 
                     onclick="openChat('user', <?php echo $lecturer['id']; ?>, '<?php echo addslashes($lecturer['name']); ?>')">
                    <div class="chat-avatar">👨‍🏫</div>
                    <div class="chat-info">
                        <div class="chat-name"><?php echo htmlspecialchars($lecturer['name']); ?></div>
                        <div class="chat-preview">Lecturer</div>
                    </div>
                </div>
            <?php endwhile; ?>

            <!-- ALUMNI SECTION (only for alumni users) -->
            <?php if($user['is_graduated'] == 1): ?>
                <div class="chat-section">🎓 ALUMNI</div>
                <?php
                $alumni_query = "SELECT id, name, graduation_year FROM users 
                                 WHERE is_graduated = 1 AND department_id = {$user['department_id']} AND id != $user_id
                                 ORDER BY graduation_year DESC, name";
                $alumni = mysqli_query($conn, $alumni_query);
                while($alumnus = mysqli_fetch_assoc($alumni)):
                ?>
                    <div class="chat-item <?php echo ($selected_user_id == $alumnus['id']) ? 'active' : ''; ?>" 
                         onclick="openChat('user', <?php echo $alumnus['id']; ?>, '<?php echo addslashes($alumnus['name']); ?>')">
                        <div class="chat-avatar">🎓</div>
                        <div class="chat-info">
                            <div class="chat-name"><?php echo htmlspecialchars($alumnus['name']); ?></div>
                            <div class="chat-preview">Class of <?php echo $alumnus['graduation_year']; ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Overlay for mobile -->
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <!-- Main Chat Area -->
    <div class="chat-main">
        <?php if($selected_name): ?>
            <div class="chat-header">
                <button class="back-btn" onclick="toggleSidebar()">☰</button>
                <div class="chat-header-info">
                    <div class="chat-header-name"><?php echo htmlspecialchars($selected_name); ?></div>
                    <div class="chat-header-status" id="chatStatus">Online</div>
                </div>
            </div>

            <div class="messages-container" id="messagesContainer">
                <div class="empty-chat">Loading messages...</div>
            </div>

            <div class="typing-indicator" id="typingIndicator" style="display: none;">
                Someone is typing...
            </div>

            <div class="input-area">
                <input type="text" id="messageInput" placeholder="Type a message..." onkeypress="handleKeyPress(event)">
                <button class="send-btn" onclick="sendMessage()">📤</button>
            </div>
        <?php else: ?>
            <div class="chat-header">
                <button class="back-btn" onclick="toggleSidebar()">☰</button>
                <div class="chat-header-info">
                    <div class="chat-header-name">💬 FoC Connect</div>
                    <div class="chat-header-status">Select a chat to start messaging</div>
                </div>
            </div>
            <div class="messages-container">
                <div class="empty-chat">
                    💚 Welcome to FoC Connect Chat<br>
                    <span style="font-size: 12px;">Click on a group or student from the sidebar to start chatting</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    const socket = io('http://localhost:3000');
    const userId = <?php echo $user_id; ?>;
    let currentChatId = null;
    let currentChatType = null;
    let currentChatName = '';

    socket.emit('user-joined', userId);

    <?php if($selected_id): ?>
        currentChatId = <?php echo $selected_id; ?>;
        currentChatType = '<?php echo $selected_type; ?>';
        currentChatName = '<?php echo addslashes($selected_name); ?>';
        
        if(currentChatType === 'group') {
            socket.emit('join-group', currentChatId);
        }
        loadMessages();
    <?php endif; ?>

    function loadMessages() {
        fetch(`http://localhost:3000/api/messages/${currentChatType}/${currentChatId}`)
            .then(res => res.json())
            .then(messages => {
                const container = document.getElementById('messagesContainer');
                container.innerHTML = '';
                
                if(messages.length === 0) {
                    container.innerHTML = '<div class="empty-chat">💬 No messages yet<br><span style="font-size: 12px;">Send a message to start the conversation!</span></div>';
                } else {
                    messages.forEach(msg => {
                        if(!msg.is_deleted_by_moderator) {
                            displayMessage(msg);
                        }
                    });
                    scrollToBottom();
                }
            });
    }

    function displayMessage(msg) {
        const container = document.getElementById('messagesContainer');
        const isSent = msg.from_user_id == userId;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
        messageDiv.id = `msg_${msg.id}`;
        
        let senderName = '';
        if(!isSent && currentChatType === 'group') {
            senderName = `<strong>${escapeHtml(msg.sender_name)}</strong><br>`;
        }
        
        let reportButton = '';
        if(!isSent && <?php echo $user['is_graduated'] == 1 || $user_role != 'student' ? 'true' : 'false'; ?>) {
            reportButton = `<button class="report-btn" onclick="reportMessage(${msg.id})" title="Report message">🚩</button>`;
        }
        
        messageDiv.innerHTML = `
            ${reportButton}
            <div class="bubble">
                ${senderName}
                ${escapeHtml(msg.message)}
                <div class="message-info">
                    <span>${formatTime(msg.sent_at)}</span>
                </div>
            </div>
        `;
        container.appendChild(messageDiv);
        scrollToBottom();
    }

    function sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        if(!message || !currentChatId) return;
        
        const data = {
            from_user_id: userId,
            message: message
        };
        
        if(currentChatType === 'group') {
            data.group_id = currentChatId;
        } else {
            data.to_user_id = currentChatId;
        }
        
        socket.emit('send-message', data);
        input.value = '';
    }

    function reportMessage(messageId) {
        const reason = prompt('Why are you reporting this message? (e.g., spam, harassment, offensive content)');
        if(reason) {
            window.location.href = `report-message.php?id=${messageId}&reason=${encodeURIComponent(reason)}`;
        }
    }

    function openChat(type, id, name) {
        window.location.href = `chat.php?${type}_id=${id}`;
    }

    function handleKeyPress(event) {
        if(event.key === 'Enter') {
            sendMessage();
        }
    }

    socket.on('new-message', (msg) => {
        if((currentChatType === 'group' && msg.group_id == currentChatId) ||
           (currentChatType === 'user' && (msg.from_user_id == currentChatId || msg.to_user_id == currentChatId))) {
            if(!msg.is_deleted_by_moderator) {
                displayMessage(msg);
            }
        }
    });

    function scrollToBottom() {
        const container = document.getElementById('messagesContainer');
        container.scrollTop = container.scrollHeight;
    }

    function formatTime(datetime) {
        const date = new Date(datetime);
        const now = new Date();
        const diff = now - date;
        
        if(diff < 86400000) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } else {
            return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function toggleSidebar() {
        document.getElementById('chatSidebar').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('open');
    }

    function closeSidebar() {
        document.getElementById('chatSidebar').classList.remove('open');
        document.getElementById('overlay').classList.remove('open');
    }

    // Search functionality
    document.getElementById('searchChats')?.addEventListener('input', function(e) {
        const search = e.target.value.toLowerCase();
        const items = document.querySelectorAll('.chat-item');
        items.forEach(item => {
            const name = item.querySelector('.chat-name')?.innerText.toLowerCase() || '';
            item.style.display = name.includes(search) ? 'flex' : 'none';
        });
    });

    // Typing indicator
    let typingTimeout;
    const messageInput = document.getElementById('messageInput');
    if(messageInput) {
        messageInput.addEventListener('input', () => {
            socket.emit('typing', {
                from_user_id: userId,
                to_user_id: currentChatType === 'user' ? currentChatId : null,
                group_id: currentChatType === 'group' ? currentChatId : null,
                isTyping: true
            });
            
            clearTimeout(typingTimeout);
            typingTimeout = setTimeout(() => {
                socket.emit('typing', {
                    from_user_id: userId,
                    to_user_id: currentChatType === 'user' ? currentChatId : null,
                    group_id: currentChatType === 'group' ? currentChatId : null,
                    isTyping: false
                });
            }, 1000);
        });
    }

    socket.on('user-typing', (data) => {
        const indicator = document.getElementById('typingIndicator');
        if(data.isTyping && data.user_id !== userId) {
            indicator.style.display = 'block';
            indicator.innerHTML = '👤 Someone is typing...';
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 2000);
        }
    });
</script>
</body>
</html>