<?php
session_start();
include 'includes/db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['name'];
$user_role = $_SESSION['role'];

// Get current user's department safely
$dept_result = mysqli_query($conn, "SELECT department_id FROM users WHERE id = $user_id LIMIT 1");
$dept_row = mysqli_fetch_assoc($dept_result);
$dept_id = $dept_row ? (int)$dept_row['department_id'] : 0;

// Get user's groups
$groups_query = "SELECT cg.* FROM chat_groups cg JOIN group_members gm ON cg.id = gm.group_id WHERE gm.user_id = $user_id LIMIT 10";
$groups = mysqli_query($conn, $groups_query);

// Get other users for DMs — safe query, no subquery
if($dept_id > 0) {
    $students_query = "SELECT id, name, matric_number FROM users WHERE department_id = $dept_id AND id != $user_id AND is_active = 1 LIMIT 20";
} else {
    // Admin/dean/no-dept users see all students
    $students_query = "SELECT id, name, matric_number FROM users WHERE id != $user_id AND is_active = 1 AND role = 'student' LIMIT 20";
}
$students = mysqli_query($conn, $students_query);
?>
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
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #F5F7F6; height: 100vh; display: flex; overflow: hidden; }
        
        .sidebar { width: 260px; background: #0F4C3A; color: white; display: flex; flex-direction: column; height: 100%; position: fixed; left: 0; top: 0; overflow-y: auto; z-index: 100; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo { font-size: 20px; font-weight: bold; }
        .sidebar-subtitle { font-size: 10px; opacity: 0.8; margin-top: 3px; }
        .sidebar-nav { flex: 1; padding: 15px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; margin: 3px 0; border-radius: 10px; color: white; text-decoration: none; transition: 0.2s; }
        .nav-item:hover, .nav-item.active { background: #2E7D64; }
        .nav-icon { font-size: 18px; width: 25px; }
        .sidebar-footer { padding: 15px; border-top: 1px solid rgba(255,255,255,0.1); }
        
        .chat-area { margin-left: 260px; flex: 1; display: flex; flex-direction: column; background: white; width: calc(100% - 260px); }
        .chat-header { padding: 15px 20px; border-bottom: 1px solid #E8EDEC; background: white; display: flex; align-items: center; gap: 12px; }
        .chat-header h3 { font-size: 18px; color: #1A2E28; }
        .chat-header p { font-size: 12px; color: #8A9B97; margin-top: 2px; }
        .status-badge { font-size: 10px; padding: 2px 8px; border-radius: 20px; display: inline-block; margin-left: 10px; }
        .status-badge.connected { background: #4caf50; color: white; }
        .status-badge.disconnected { background: #f44336; color: white; }
        .status-badge.connecting { background: #ff9800; color: white; }
        .status-badge.waking { background: #2196F3; color: white; }
        
        .messages { flex: 1; overflow-y: auto; padding: 20px; background: #F5F7F6; display: flex; flex-direction: column; gap: 8px; }
        .message { display: flex; margin-bottom: 8px; }
        .message.sent { justify-content: flex-end; }
        .message.received { justify-content: flex-start; }
        .bubble { max-width: 70%; padding: 10px 14px; border-radius: 20px; font-size: 14px; word-wrap: break-word; }
        .message.sent .bubble { background: #DCF8C6; color: #1A2E28; border-bottom-right-radius: 4px; }
        .message.received .bubble { background: white; color: #1A2E28; border: 1px solid #E8EDEC; border-bottom-left-radius: 4px; }
        .message-info { font-size: 10px; margin-top: 4px; display: flex; align-items: center; gap: 4px; justify-content: flex-end; }
        .tick { font-size: 12px; margin-left: 4px; }
        .tick-sent { color: #8A9B97; }
        .tick-delivered { color: #8A9B97; }
        .tick-read { color: #34B7F1; }
        
        .input-area { padding: 15px 20px; background: white; border-top: 1px solid #E8EDEC; display: flex; gap: 10px; align-items: center; }
        .input-area input { flex: 1; padding: 12px 16px; border: 1px solid #E8EDEC; border-radius: 30px; outline: none; font-size: 14px; }
        .input-area input:focus { border-color: #2E7D64; }
        .input-area button { background: #2E7D64; color: white; border: none; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; }
        .input-area button:disabled { background: #ccc; cursor: not-allowed; }
        .chat-list-side { width: 260px; background: white; border-right: 1px solid #E8EDEC; overflow-y: auto; height: 100vh; position: relative; }
        .chat-section { padding: 12px 16px; font-weight: 600; color: #0F4C3A; font-size: 11px; background: #E8F5E9; }
        .chat-item { padding: 10px 16px; cursor: pointer; border-bottom: 1px solid #F0F2F5; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .chat-item:hover { background: #F5F7F6; }
        .chat-item.active { background: #E8F5E9; border-left: 3px solid #2E7D64; }
        .chat-avatar { width: 38px; height: 38px; background: #2E7D64; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px; flex-shrink: 0; }
        .chat-name { font-weight: 500; font-size: 14px; color: #1A2E28; }
        .empty-chat { text-align: center; padding: 60px 20px; color: #8A9B97; line-height: 1.8; }
        .menu-btn { display: none; background: none; border: none; font-size: 22px; cursor: pointer; }
        .back-btn { background: none; border: none; font-size: 22px; cursor: pointer; margin-right: 10px; }
        .retry-btn { background: #2196F3; color: white; border: none; padding: 8px 16px; border-radius: 20px; cursor: pointer; font-size: 12px; margin-top: 10px; margin-right: 10px; }
        .wake-btn { background: #ff9800; color: white; border: none; padding: 8px 20px; border-radius: 20px; cursor: pointer; font-size: 12px; margin-top: 10px; font-weight: bold; }
        .typing-indicator { font-size: 12px; color: #8A9B97; padding: 4px 16px; font-style: italic; min-height: 20px; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 260px; transition: 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .chat-area { margin-left: 0; width: 100%; }
            .chat-list-side { position: fixed; left: -280px; top: 0; bottom: 0; z-index: 99; background: white; width: 260px; transition: 0.3s; }
            .chat-list-side.open { left: 0; }
            .menu-btn { display: block; }
            .hamburger { display: block; position: fixed; bottom: 20px; right: 20px; background: #2E7D64; color: white; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 24px; cursor: pointer; z-index: 98; }
        }
        @media (min-width: 769px) { .hamburger { display: none; } }
        .hamburger { background: #2E7D64; color: white; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 24px; cursor: pointer; }
        .attach-btn { background: #F5F7F6; border: 1px solid #E8EDEC; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; font-size: 20px; flex-shrink: 0; }
    </style>
</head>
<body>

<!-- ===================== LEFT NAV SIDEBAR ===================== -->
<div class="sidebar" id="mainSidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">🌿 FoC Connect</div>
        <div class="sidebar-subtitle">FACULTY OF COMPUTING</div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span><span>Dashboard</span></a>
        <a href="conversation-working.php" class="nav-item active"><span class="nav-icon">💬</span><span>Chat</span></a>
        <a href="announcements.php" class="nav-item"><span class="nav-icon">📢</span><span>Announcements</span></a>
        <a href="materials.php" class="nav-item"><span class="nav-icon">📚</span><span>Materials</span></a>
        <a href="assignments.php" class="nav-item"><span class="nav-icon">📝</span><span>Assignments</span></a>
        <a href="settings.php" class="nav-item"><span class="nav-icon">⚙️</span><span>Settings</span></a>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php" class="nav-item"><span class="nav-icon">🚪</span><span>Logout</span></a>
    </div>
</div>

<!-- ===================== MAIN CHAT AREA ===================== -->
<div class="chat-area">
    <div class="chat-header">
        <button class="menu-btn" onclick="document.getElementById('mainSidebar').classList.toggle('open')">☰</button>
        <button class="back-btn" onclick="document.getElementById('chatListSidebar').classList.toggle('open')">💬</button>
        <div style="flex:1;">
            <h3 id="chatTitle">💬 FoC Connect Chat</h3>
            <p id="chatSubtitle">Select a conversation <span id="connectionStatus" class="status-badge connecting">Connecting...</span></p>
        </div>
    </div>

    <div class="messages" id="messages">
        <div class="empty-chat">
            💬 Select a chat from the list to start messaging
        </div>
    </div>

    <div class="typing-indicator" id="typingIndicator"></div>

    <div class="input-area">
        <button class="attach-btn" onclick="document.getElementById('fileInput').click()" title="Attach file">📎</button>
        <input type="file" id="fileInput" style="display:none" onchange="uploadFile(this)">
        <input type="text" id="messageInput" placeholder="Select a chat first..." disabled onkeypress="if(event.key==='Enter') sendMessage()" oninput="handleTyping()">
        <button id="sendBtn" onclick="sendMessage()" disabled>➤</button>
    </div>
</div>

<!-- ===================== CHAT LIST SIDEBAR ===================== -->
<div class="chat-list-side" id="chatListSidebar">
    <div class="chat-section">📁 GROUPS</div>
    <?php
    $hasGroups = false;
    while($group = mysqli_fetch_assoc($groups)):
        $hasGroups = true;
    ?>
    <div class="chat-item" onclick="selectChat('group', <?php echo $group['id']; ?>, '<?php echo addslashes(htmlspecialchars($group['name'])); ?>', this)">
        <div class="chat-avatar">👥</div>
        <div>
            <div class="chat-name"><?php echo htmlspecialchars($group['name']); ?></div>
            <small style="color:#8A9B97;font-size:11px;">Group</small>
        </div>
    </div>
    <?php endwhile; ?>
    <?php if(!$hasGroups): ?>
    <div style="padding:12px 16px; font-size:12px; color:#8A9B97;">No groups yet</div>
    <?php endif; ?>

    <div class="chat-section">👤 STUDENTS</div>
    <?php
    $hasStudents = false;
    while($student = mysqli_fetch_assoc($students)):
        $hasStudents = true;
    ?>
    <div class="chat-item" onclick="selectChat('user', <?php echo $student['id']; ?>, '<?php echo addslashes(htmlspecialchars($student['name'])); ?>', this)">
        <div class="chat-avatar">👤</div>
        <div>
            <div class="chat-name"><?php echo htmlspecialchars($student['name']); ?></div>
            <small style="color:#8A9B97;font-size:11px;"><?php echo htmlspecialchars($student['matric_number']); ?></small>
        </div>
    </div>
    <?php endwhile; ?>
    <?php if(!$hasStudents): ?>
    <div style="padding:12px 16px; font-size:12px; color:#8A9B97;">No other students found</div>
    <?php endif; ?>
</div>

<button class="hamburger" onclick="document.getElementById('chatListSidebar').classList.toggle('open')">💬</button>

<!-- ===================== JAVASCRIPT ===================== -->
<script>
    // ============================================================
    // FIX 3: UPDATE THIS URL to your actual Render service URL
    // Go to dashboard.render.com → click foc-connect-chat → copy the URL shown at the top
    // It will look like: https://foc-connect-chat.onrender.com
    // ============================================================
    const SOCKET_URL = 'https://foc-connect-websocket.onrender.com';

    let socket = null;
    let isConnected = false;
    let isWaking = false;
    let typingTimeout = null;

    const userId = <?php echo json_encode((int)$user_id); ?>;
    const userName = <?php echo json_encode($user_name); ?>;

    let currentChatId = null;
    let currentChatType = null;
    let currentChatName = null;
    let currentItem = null;

    const statusSpan = document.getElementById('connectionStatus');
    const messagesContainer = document.getElementById('messages');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const typingIndicator = document.getElementById('typingIndicator');

    // ── Wake & Connect ──────────────────────────────────────────

    function wakeServer() {
        if (isWaking) return;
        isWaking = true;
        setStatus('waking', '⏳ Waking server...');

        fetch(SOCKET_URL + '/health', { mode: 'cors' })
            .then(res => {
                if (res.ok) {
                    setStatus('connecting', 'Server up, connecting...');
                    setTimeout(connectSocket, 500);
                } else {
                    throw new Error('Not OK');
                }
            })
            .catch(() => {
                // Server still waking — retry in 5s
                setTimeout(() => {
                    isWaking = false;
                    connectSocket();
                }, 5000);
            });
    }

    function connectSocket() {
        setStatus('connecting', 'Connecting...');

        if (socket) {
            try { socket.disconnect(); } catch(e) {}
            socket = null;
        }

        socket = io(SOCKET_URL, {
            reconnection: true,
            reconnectionAttempts: 15,
            reconnectionDelay: 3000,
            reconnectionDelayMax: 10000,
            timeout: 30000,
            transports: ['websocket', 'polling']
        });

        socket.on('connect', () => {
            console.log('✅ Connected to chat server');
            isConnected = true;
            isWaking = false;
            setStatus('connected', 'Connected ✓');
            socket.emit('user-joined', userId);
            // Reload current chat if one is selected
            if (currentChatId) loadMessages(currentChatType, currentChatId);
        });

        socket.on('disconnect', (reason) => {
            console.log('⚠️ Disconnected:', reason);
            isConnected = false;
            setStatus('disconnected', reason === 'io server disconnect' ? 'Server offline' : 'Disconnected — retrying...');
        });

        socket.on('connect_error', (error) => {
            console.log('❌ Connection error:', error.message);
            isConnected = false;
            setStatus('disconnected', '<button class="wake-btn" onclick="wakeServer()">🔌 Wake Server</button>');
        });

        socket.on('new-message', (msg) => {
            if (!currentChatId) return;
            const inCurrentGroup = currentChatType === 'group' && msg.group_id == currentChatId;
            const inCurrentDM = currentChatType === 'user' &&
                (msg.from_user_id == currentChatId || msg.to_user_id == currentChatId || msg.from_user_id == userId);

            if (inCurrentGroup || inCurrentDM) {
                // Avoid duplicate messages (sender already sees optimistic if we add that later)
                if (!document.getElementById('msg-' + msg.id)) {
                    displayMessage(msg);
                }
                // Mark as read if received from someone else
                if (msg.from_user_id != userId) {
                    socket.emit('mark-message-read', { message_id: msg.id, user_id: userId, from_user_id: msg.from_user_id });
                }
            }
        });

        socket.on('message-status-update', (data) => updateMessageStatus(data.message_id, data.status));
        socket.on('message-read', (data) => updateMessageStatus(data.message_id, 'read'));

        socket.on('user-typing', (data) => {
            if (data.user_id != userId) {
                typingIndicator.textContent = data.isTyping ? 'typing...' : '';
            }
        });

        socket.on('online-users', (users) => {
            console.log('Online users:', users.length);
        });
    }

    function setStatus(cls, html) {
        statusSpan.className = 'status-badge ' + cls;
        statusSpan.innerHTML = html;
    }

    // ── Load Messages ────────────────────────────────────────────

    function loadMessages(type, id) {
        messagesContainer.innerHTML = '<div class="empty-chat">⏳ Loading messages...</div>';

        fetch(`${SOCKET_URL}/api/messages/${type}/${id}`, { mode: 'cors' })
            .then(res => {
                if (!res.ok) throw new Error('Server error ' + res.status);
                return res.json();
            })
            .then(messages => {
                messagesContainer.innerHTML = '';
                if (messages.length === 0) {
                    messagesContainer.innerHTML = '<div class="empty-chat">💬 No messages yet. Send the first one!</div>';
                } else {
                    messages.forEach(msg => displayMessage(msg));
                    scrollToBottom();
                    if (type === 'user') socket.emit('mark-chat-read', { user_id: userId, chat_partner_id: id });
                    else socket.emit('mark-chat-read', { user_id: userId, group_id: id });
                }
            })
            .catch(err => {
                console.error('Load messages error:', err);
                messagesContainer.innerHTML = `
                    <div class="empty-chat">
                        ⚠️ Server is sleeping or unreachable.<br>
                        <button class="wake-btn" onclick="wakeServer(); setTimeout(() => loadMessages('${type}', ${id}), 5000);">🔌 Wake Server</button>
                        <br><small style="margin-top:8px;display:block;">Takes ~20-30 seconds on first load</small>
                    </div>`;
            });
    }

    // ── Select Chat ───────────────────────────────────────────────

    function selectChat(type, id, name, element) {
        currentChatId = id;
        currentChatType = type;
        currentChatName = name;

        if (currentItem) currentItem.classList.remove('active');
        if (element) element.classList.add('active');
        currentItem = element;

        document.getElementById('chatTitle').textContent = name;
        document.getElementById('chatSubtitle').innerHTML = (type === 'group' ? '👥 Group Chat' : '💬 Private Chat') +
            ' <span id="connectionStatus" class="status-badge ' + (isConnected ? 'connected' : 'disconnected') + '">' +
            (isConnected ? 'Connected ✓' : 'Offline') + '</span>';

        messageInput.disabled = false;
        sendBtn.disabled = false;
        messageInput.placeholder = 'Type a message...';
        typingIndicator.textContent = '';

        if (type === 'group' && socket && isConnected) {
            socket.emit('join-group', id);
        }

        loadMessages(type, id);

        if (window.innerWidth <= 768) {
            document.getElementById('chatListSidebar').classList.remove('open');
        }

        messageInput.focus();
    }

    // ── Display Message ───────────────────────────────────────────

    function displayMessage(msg) {
        // Skip if already displayed
        if (document.getElementById('msg-' + msg.id)) return;

        const isSent = msg.from_user_id == userId;
        const div = document.createElement('div');
        div.id = 'msg-' + msg.id;
        div.className = 'message ' + (isSent ? 'sent' : 'received');

        let senderName = '';
        if (!isSent && currentChatType === 'group' && msg.sender_name) {
            senderName = `<strong style="font-size:12px;color:#0F4C3A;">${escapeHtml(msg.sender_name)}</strong><br>`;
        }

        let content = escapeHtml(msg.message || '');
        if (msg.file_url) {
            if (msg.file_type && msg.file_type.startsWith('image/')) {
                content = `<a href="${SOCKET_URL}${msg.file_url}" target="_blank"><img src="${SOCKET_URL}${msg.file_url}" style="max-width:200px;max-height:150px;border-radius:10px;display:block;margin-bottom:4px;"></a>${content}`;
            } else if (msg.file_type && msg.file_type.startsWith('audio/')) {
                content = `<audio controls style="max-width:200px;"><source src="${SOCKET_URL}${msg.file_url}"></audio>${content}`;
            } else {
                content = `<a href="${SOCKET_URL}${msg.file_url}" target="_blank" style="color:#2E7D64;text-decoration:underline;">📎 ${escapeHtml(msg.file_name || 'Download file')}</a><br>${content}`;
            }
        }

        let tickHtml = '';
        if (isSent) {
            const tickClass = msg.status === 'read' ? 'tick-read' : (msg.status === 'delivered' ? 'tick-delivered' : 'tick-sent');
            const tickMark = msg.status === 'sent' ? '✓' : '✓✓';
            tickHtml = `<span class="tick ${tickClass}">${tickMark}</span>`;
        }

        div.innerHTML = `
            <div class="bubble">
                ${senderName}
                ${content}
                <div class="message-info">
                    <span>${formatTime(msg.sent_at)}</span>
                    ${tickHtml}
                </div>
            </div>`;

        messagesContainer.appendChild(div);
        scrollToBottom();
    }

    function updateMessageStatus(messageId, status) {
        const msgDiv = document.getElementById('msg-' + messageId);
        if (!msgDiv) return;
        const tick = msgDiv.querySelector('.tick');
        if (!tick) return;
        if (status === 'read') { tick.className = 'tick tick-read'; tick.textContent = '✓✓'; }
        else if (status === 'delivered') { tick.className = 'tick tick-delivered'; tick.textContent = '✓✓'; }
    }

    // ── Send Message ──────────────────────────────────────────────

    function sendMessage() {
        const message = messageInput.value.trim();
        if (!message) return;
        if (!currentChatId) { alert('Please select a chat first'); return; }
        if (!isConnected || !socket) {
            alert('Not connected. Click "Wake Server" to reconnect.');
            return;
        }

        const data = { from_user_id: userId, message };
        if (currentChatType === 'group') data.group_id = currentChatId;
        else data.to_user_id = currentChatId;

        socket.emit('send-message', data);
        messageInput.value = '';

        // Clear typing
        socket.emit('typing', { from_user_id: userId, to_user_id: currentChatType === 'user' ? currentChatId : null, group_id: currentChatType === 'group' ? currentChatId : null, isTyping: false });
    }

    // ── Typing Indicator ──────────────────────────────────────────

    function handleTyping() {
        if (!socket || !isConnected || !currentChatId) return;
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
        }, 2000);
    }

    // ── File Upload ───────────────────────────────────────────────

    function uploadFile(input) {
        const file = input.files[0];
        if (!file) return;
        if (!currentChatId) { alert('Select a chat first'); return; }
        if (!isConnected) { alert('Not connected to server'); return; }

        const formData = new FormData();
        formData.append('file', file);

        fetch(`${SOCKET_URL}/api/upload`, { method: 'POST', body: formData, mode: 'cors' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const msgData = {
                        from_user_id: userId,
                        message: '',
                        file_url: data.file_url,
                        file_name: data.file_name,
                        file_type: data.file_type,
                        file_size: data.file_size
                    };
                    if (currentChatType === 'group') msgData.group_id = currentChatId;
                    else msgData.to_user_id = currentChatId;
                    socket.emit('send-message', msgData);
                } else {
                    alert('Upload failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => alert('Upload error: ' + err.message));

        input.value = '';
    }

    // ── Helpers ───────────────────────────────────────────────────

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function formatTime(datetime) {
        if (!datetime) return 'Just now';
        const date = new Date(datetime);
        const now = new Date();
        const diff = now - date;
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
        if (diff < 86400000) return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ── Boot ──────────────────────────────────────────────────────

    // Try connecting immediately; if it fails it will show "Wake Server" button
    connectSocket();

    // Keep-alive ping every 40s to prevent Render from sleeping
    setInterval(() => {
        if (isConnected && socket) {
            socket.emit('ping');
        } else if (!isWaking) {
            fetch(SOCKET_URL + '/health', { mode: 'cors' }).catch(() => {});
        }
    }, 40000);

    // Close sidebars when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth > 768) return;
        const sidebar = document.getElementById('mainSidebar');
        const chatList = document.getElementById('chatListSidebar');
        if (sidebar && !sidebar.contains(e.target) && !e.target.classList.contains('menu-btn')) {
            sidebar.classList.remove('open');
        }
        if (chatList && !chatList.contains(e.target) && !e.target.classList.contains('hamburger') && !e.target.classList.contains('back-btn')) {
            chatList.classList.remove('open');
        }
    });
</script>
</body>
</html>
