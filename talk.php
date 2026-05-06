<?php
session_start();
include 'includes/db.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Get user's groups
$groups_query = "SELECT cg.* FROM chat_groups cg JOIN group_members gm ON cg.id = gm.group_id WHERE gm.user_id = $user_id LIMIT 20";
$groups = mysqli_query($conn, $groups_query);

// Get other students for DMs
$students_query = "SELECT id, name, matric_number FROM users WHERE department_id = (SELECT department_id FROM users WHERE id = $user_id) AND id != $user_id AND role = 'student' LIMIT 20";
$students = mysqli_query($conn, $students_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <title>Chat - FoC Connect</title>
    <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #0a0a0a;
            height: 100vh;
            overflow: hidden;
            position: fixed;
            width: 100%;
        }

        /* Chat Background Pattern */
        .chat-container {
            display: flex;
            height: 100%;
            width: 100%;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgdmlld0JveD0iMCAwIDQwIDQwIj48cGF0aCBmaWxsPSIjMjA4MDYwIiBmaWxsLW9wYWNpdHk9IjAuMDMiIGQ9Ik0wIDBoNDB2NDBIMHoiLz48cGF0aCBkPSJNMjAgMjBhMjAgMjAgMCAwIDEgMjAgMjAgMjAgMjAgMCAwIDEtMjAgMjAgMjAgMjAgMCAwIDEtMjAtMjAgMjAgMjAgMCAwIDEgMjAtMjB6IiBmaWxsPSIjMjA4MDYwIiBmaWxsLW9wYWNpdHk9IjAuMDMiLz48L3N2Zz4=');
            background-repeat: repeat;
            background-size: 40px 40px;
        }

        /* WhatsApp-style Sidebar */
        .chat-sidebar {
            width: 380px;
            background: white;
            display: flex;
            flex-direction: column;
            height: 100%;
            border-right: 1px solid #e8e8e8;
            transition: transform 0.3s ease;
            z-index: 100;
        }

        /* Header */
        .sidebar-header {
            background: #0F4C3A;
            color: white;
            padding: 20px 20px 16px;
        }
        .sidebar-header h2 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .sidebar-header p {
            font-size: 13px;
            opacity: 0.8;
        }

        /* WhatsApp-style Tabs */
        .tabs {
            display: flex;
            background: white;
            border-bottom: 1px solid #e8e8e8;
            padding: 0 16px;
        }
        .tab {
            flex: 1;
            text-align: center;
            padding: 14px 0;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #6B7E78;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        .tab.active {
            color: #0F4C3A;
            border-bottom-color: #0F4C3A;
        }

        /* Search Bar */
        .search-box {
            padding: 10px 16px;
            background: white;
        }
        .search-box input {
            width: 100%;
            padding: 10px 16px;
            background: #F5F7F6;
            border: none;
            border-radius: 30px;
            font-size: 14px;
            outline: none;
        }

        /* Chat List */
        .chat-list-container {
            flex: 1;
            overflow-y: auto;
            background: white;
        }
        .chat-list {
            display: flex;
            flex-direction: column;
        }
        .chat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        .chat-item:hover {
            background: #F5F7F6;
        }
        .chat-avatar {
            width: 50px;
            height: 50px;
            background: #2E7D64;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            color: white;
        }
        .chat-info {
            flex: 1;
            min-width: 0;
        }
        .chat-name {
            font-weight: 600;
            font-size: 15px;
            color: #1A2E28;
        }
        .chat-preview {
            font-size: 13px;
            color: #8A9B97;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chat-time {
            font-size: 11px;
            color: #8A9B97;
            text-align: right;
        }
        .unread-badge {
            background: #25D366;
            color: white;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 20px;
        }

        /* Main Chat Area */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: transparent;
            height: 100%;
        }

        /* Chat Header */
        .chat-header {
            padding: 16px 20px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .chat-header h3 {
            font-size: 18px;
            color: #1A2E28;
        }
        .chat-header p {
            font-size: 12px;
            color: #8A9B97;
        }
        .status-badge {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-block;
            margin-left: 8px;
        }
        .status-badge.connected { background: #25D366; color: white; }
        .status-badge.disconnected { background: #f44336; color: white; }

        /* Messages Area with Background */
        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgdmlld0JveD0iMCAwIDQwIDQwIj48Y2lyY2xlIGN4PSIyMCIgY3k9IjIwIiByPSIxIiBmaWxsPSIjMjA4MDYwIiBmaWxsLW9wYWNpdHk9IjAuMDMiLz48L3N2Zz4=');
            background-repeat: repeat;
            background-size: 30px 30px;
        }

        /* Message Bubbles */
        .message {
            display: flex;
            margin-bottom: 8px;
        }
        .message.sent {
            justify-content: flex-end;
        }
        .message.received {
            justify-content: flex-start;
        }
        .bubble {
            max-width: 70%;
            padding: 10px 14px;
            border-radius: 20px;
            font-size: 14px;
            word-wrap: break-word;
        }
        .message.sent .bubble {
            background: #DCF8C6;
            color: #1A2E28;
            border-bottom-right-radius: 4px;
        }
        .message.received .bubble {
            background: white;
            color: #1A2E28;
            border: 1px solid #e8e8e8;
            border-bottom-left-radius: 4px;
        }
        .message-info {
            font-size: 10px;
            margin-top: 4px;
            color: #8A9B97;
            display: flex;
            gap: 4px;
            justify-content: flex-end;
        }
        .tick { font-size: 11px; margin-left: 4px; }
        .tick-sent { color: #8A9B97; }
        .tick-delivered { color: #8A9B97; }
        .tick-read { color: #34B7F1; }

        /* Input Area */
        .input-area {
            padding: 12px 16px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(0,0,0,0.05);
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .input-area input {
            flex: 1;
            padding: 12px 16px;
            background: #F5F7F6;
            border: none;
            border-radius: 30px;
            outline: none;
            font-size: 15px;
        }
        .input-area button {
            background: #2E7D64;
            color: white;
            border: none;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .input-area button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .typing-indicator {
            font-size: 12px;
            color: #8A9B97;
            padding: 6px 16px;
            font-style: italic;
            min-height: 32px;
            background: rgba(255,255,255,0.8);
        }

        .empty-chat {
            text-align: center;
            padding: 40px 20px;
            color: #8A9B97;
        }

        /* Mobile Styles */
        .menu-btn, .back-btn {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
        }
        .mobile-hamburger {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #2E7D64;
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            z-index: 98;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        @media (max-width: 768px) {
            .chat-sidebar {
                position: fixed;
                left: -380px;
                top: 0;
                bottom: 0;
                z-index: 200;
                width: 85%;
                max-width: 320px;
            }
            .chat-sidebar.open {
                left: 0;
            }
            .menu-btn, .back-btn {
                display: block;
            }
            .mobile-hamburger {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 199;
                display: none;
            }
            .overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body>

<div class="chat-container">
    <!-- Chat List Sidebar -->
    <div class="chat-sidebar" id="chatSidebar">
        <div class="sidebar-header">
            <h2>💬 Chats</h2>
            <p><?php echo htmlspecialchars($user_name); ?></p>
        </div>

        <!-- WhatsApp-style Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="chats">Chats</div>
            <div class="tab" data-tab="groups">Groups</div>
            <div class="tab" data-tab="favorites">Favorites</div>
            <div class="tab" data-tab="archive">Archive</div>
        </div>

        <div class="search-box">
            <input type="text" id="searchInput" placeholder="🔍 Search or start new chat">
        </div>

        <div class="chat-list-container">
            <div class="chat-list" id="chatList">
                <div class="chat-section" style="padding: 12px 16px; font-weight: 600; color: #6B7E78; font-size: 12px;">📁 GROUPS</div>
                <?php if(mysqli_num_rows($groups) > 0): ?>
                    <?php while($group = mysqli_fetch_assoc($groups)): ?>
                    <div class="chat-item" data-id="<?php echo $group['id']; ?>" data-type="group" data-name="<?php echo htmlspecialchars($group['name']); ?>">
                        <div class="chat-avatar">👥</div>
                        <div class="chat-info">
                            <div class="chat-name"><?php echo htmlspecialchars($group['name']); ?></div>
                            <div class="chat-preview">Tap to start chatting</div>
                        </div>
                        <div class="chat-time"></div>
                    </div>
                    <?php endwhile; ?>
                <?php endif; ?>

                <div class="chat-section" style="padding: 12px 16px; font-weight: 600; color: #6B7E78; font-size: 12px;">👤 CONTACTS</div>
                <?php if(mysqli_num_rows($students) > 0): ?>
                    <?php while($student = mysqli_fetch_assoc($students)): ?>
                    <div class="chat-item" data-id="<?php echo $student['id']; ?>" data-type="user" data-name="<?php echo htmlspecialchars($student['name']); ?>">
                        <div class="chat-avatar">👤</div>
                        <div class="chat-info">
                            <div class="chat-name"><?php echo htmlspecialchars($student['name']); ?></div>
                            <div class="chat-preview"><?php echo htmlspecialchars($student['matric_number']); ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="padding: 20px; text-align: center; color: #8A9B97;">No contacts available</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <!-- Main Chat Area -->
    <div class="chat-main">
        <div class="chat-header">
            <button class="back-btn" onclick="toggleSidebar()">☰</button>
            <div>
                <h3 id="chatTitle">💬 FoC Connect</h3>
                <p id="chatSubtitle">Select a conversation <span id="connectionStatus" class="status-badge disconnected">Offline</span></p>
            </div>
        </div>

        <div class="messages" id="messages">
            <div class="empty-chat">
                💬 Select a chat from the list to start messaging
            </div>
        </div>

        <div class="typing-indicator" id="typingIndicator"></div>

        <div class="input-area">
            <input type="text" id="messageInput" placeholder="Type a message..." disabled>
            <button id="sendBtn" onclick="sendMessage()" disabled>📤</button>
        </div>
    </div>
</div>

<button class="mobile-hamburger" onclick="toggleSidebar()">💬</button>

<script>
    // Configuration
    const SOCKET_URL = 'https://foc-connect-websocket.onrender.com';
    let userId = null;
    let socket = null;
    let isConnected = false;
    let currentChatId = null;
    let currentChatType = null;
    let currentChatName = null;
    let currentItem = null;

    // DOM elements
    const statusSpan = document.getElementById('connectionStatus');
    const messagesDiv = document.getElementById('messages');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const chatTitle = document.getElementById('chatTitle');
    const chatSubtitle = document.getElementById('chatSubtitle');
    const typingIndicator = document.getElementById('typingIndicator');

    // Get user info
    fetch('get-user.php')
        .then(res => res.json())
        .then(data => {
            userId = data.user_id;
            connectSocket();
        });

    // Tab switching
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const tabName = this.dataset.tab;
            filterChats(tabName);
        });
    });

    function filterChats(tabName) {
        const items = document.querySelectorAll('.chat-item');
        items.forEach(item => {
            if(tabName === 'chats') item.style.display = 'flex';
            else if(tabName === 'groups') {
                item.style.display = item.dataset.type === 'group' ? 'flex' : 'none';
            }
            else if(tabName === 'favorites') {
                const isFavorite = localStorage.getItem('fav_' + item.dataset.id) === 'true';
                item.style.display = isFavorite ? 'flex' : 'none';
            }
            else if(tabName === 'archive') {
                const isArchived = localStorage.getItem('archived_' + item.dataset.id) === 'true';
                item.style.display = isArchived ? 'flex' : 'none';
            }
        });
    }

    // Star/Favorite functionality (long press or menu)
    document.querySelectorAll('.chat-item').forEach(item => {
        item.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const name = this.dataset.name;
            const isFav = localStorage.getItem('fav_' + id) === 'true';
            if(isFav) {
                localStorage.removeItem('fav_' + id);
                alert('Removed from Favorites');
            } else {
                localStorage.setItem('fav_' + id, 'true');
                alert('Added to Favorites');
            }
            filterChats(document.querySelector('.tab.active').dataset.tab);
        });
    });

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        document.querySelectorAll('.chat-item').forEach(item => {
            const name = item.querySelector('.chat-name')?.innerText.toLowerCase() || '';
            item.style.display = name.includes(searchTerm) ? 'flex' : 'none';
        });
    });

    function connectSocket() {
        statusSpan.className = 'status-badge disconnected';
        statusSpan.innerHTML = 'Connecting...';

        socket = io(SOCKET_URL, {
            transports: ['websocket', 'polling'],
            reconnection: true,
            reconnectionAttempts: 10,
            reconnectionDelay: 2000
        });

        socket.on('connect', () => {
            isConnected = true;
            statusSpan.className = 'status-badge connected';
            statusSpan.innerHTML = 'Connected ✓';
            socket.emit('user-joined', userId);
        });

        socket.on('disconnect', () => {
            isConnected = false;
            statusSpan.className = 'status-badge disconnected';
            statusSpan.innerHTML = 'Offline';
        });

        socket.on('connect_error', () => {
            isConnected = false;
            statusSpan.className = 'status-badge disconnected';
            statusSpan.innerHTML = 'Offline <button onclick="wakeServer()" style="background:#ff9800; border:none; padding:2px 8px; border-radius:12px;">Wake</button>';
        });

        socket.on('new-message', (msg) => {
            if(currentChatId && ((currentChatType === 'group' && msg.group_id == currentChatId) ||
                (currentChatType === 'user' && (msg.from_user_id == currentChatId || msg.to_user_id == currentChatId)))) {
                displayMessage(msg);
                if(msg.from_user_id != userId) {
                    socket.emit('mark-message-read', { message_id: msg.id, user_id: userId, from_user_id: msg.from_user_id });
                }
            }
        });
    }

    function wakeServer() {
        statusSpan.innerHTML = 'Waking...';
        fetch(SOCKET_URL + '/health')
            .then(() => {
                if(socket) socket.disconnect();
                setTimeout(connectSocket, 1000);
            })
            .catch(() => {});
    }

    function selectChat(element) {
        const id = parseInt(element.dataset.id);
        const type = element.dataset.type;
        const name = element.dataset.name;

        if(currentItem) currentItem.classList.remove('active');
        element.classList.add('active');
        currentItem = element;
        currentChatId = id;
        currentChatType = type;
        currentChatName = name;

        chatTitle.innerHTML = name;
        chatSubtitle.innerHTML = type === 'group' ? 'Group Chat' : 'Private Chat';
        messageInput.disabled = false;
        sendBtn.disabled = false;
        messageInput.placeholder = 'Type a message...';

        if(type === 'group' && socket && isConnected) {
            socket.emit('join-group', id);
        }

        loadMessages(type, id);
        closeSidebar();
    }

    function loadMessages(type, id) {
        messagesDiv.innerHTML = '<div class="empty-chat">Loading messages...</div>';

        fetch(`chat-api.php?action=messages&type=${type}&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if(data.success && data.data) {
                    messagesDiv.innerHTML = '';
                    if(data.data.length === 0) {
                        messagesDiv.innerHTML = '<div class="empty-chat">💬 No messages yet. Send the first one!</div>';
                    } else {
                        data.data.forEach(msg => displayMessage(msg));
                        scrollToBottom();
                    }
                }
            })
            .catch(() => {
                messagesDiv.innerHTML = '<div class="empty-chat">⚠️ Error loading messages. Try again.</div>';
            });
    }

    function displayMessage(msg) {
        const isSent = msg.from_user_id == userId;
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
        messageDiv.id = `msg-${msg.id}`;

        let senderName = '';
        if(!isSent && currentChatType === 'group' && msg.sender_name) {
            senderName = `<strong style="font-size:12px;">${escapeHtml(msg.sender_name)}</strong><br>`;
        }

        let tickHtml = '';
        if(isSent) {
            if(msg.status === 'read') tickHtml = '<span class="tick tick-read">✓✓</span>';
            else if(msg.status === 'delivered') tickHtml = '<span class="tick tick-delivered">✓✓</span>';
            else tickHtml = '<span class="tick tick-sent">✓</span>';
        }

        messageDiv.innerHTML = `
            <div class="bubble">
                ${senderName}
                ${escapeHtml(msg.message)}
                <div class="message-info">
                    ${formatTime(msg.sent_at)}
                    ${tickHtml}
                </div>
            </div>
        `;
        messagesDiv.appendChild(messageDiv);
        scrollToBottom();
    }

    function sendMessage() {
        const message = messageInput.value.trim();
        if(!message || !currentChatId) return;
        if(!isConnected) {
            alert('Not connected to chat server.');
            return;
        }

        const data = { from_user_id: userId, message: message };
        if(currentChatType === 'group') data.group_id = currentChatId;
        else data.to_user_id = currentChatId;

        socket.emit('send-message', data);
        messageInput.value = '';
    }

    function scrollToBottom() {
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    function formatTime(datetime) {
        if(!datetime) return 'Just now';
        const date = new Date(datetime);
        const now = new Date();
        const diff = now - date;
        if(diff < 60000) return 'Just now';
        if(diff < 3600000) return Math.floor(diff / 60000) + 'm';
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function toggleSidebar() {
        document.getElementById('chatSidebar').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('show');
    }

    function closeSidebar() {
        document.getElementById('chatSidebar').classList.remove('open');
        document.getElementById('overlay').classList.remove('show');
    }

    // Attach click handlers
    document.querySelectorAll('.chat-item').forEach(item => {
        item.addEventListener('click', () => selectChat(item));
    });

    messageInput.addEventListener('keypress', (e) => {
        if(e.key === 'Enter') sendMessage();
    });

    // Keep-alive
    setInterval(() => {
        if(isConnected && socket) {
            socket.emit('ping');
        }
    }, 40000);
</script>
</body>
</html>
