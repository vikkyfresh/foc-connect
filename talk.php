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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes">
    <title>Messages - FoC Connect</title>
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
            background: #F5F7F6;
            height: 100vh;
            overflow: hidden;
            position: fixed;
            width: 100%;
        }

        /* Main Container */
        .chat-container {
            display: flex;
            height: 100%;
            width: 100%;
        }

        /* Chat List Sidebar */
        .chat-sidebar {
            width: 300px;
            background: white;
            border-right: 1px solid #E8EDEC;
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: transform 0.3s ease;
            z-index: 100;
        }

        .sidebar-header {
            padding: 20px;
            background: #0F4C3A;
            color: white;
        }

        .sidebar-header h2 {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .sidebar-header p {
            font-size: 12px;
            opacity: 0.8;
        }

        .search-box {
            padding: 12px 16px;
            border-bottom: 1px solid #E8EDEC;
        }

        .search-box input {
            width: 100%;
            padding: 10px 16px;
            border: 1px solid #E8EDEC;
            border-radius: 30px;
            font-size: 14px;
            background: #F5F7F6;
            outline: none;
        }

        .chat-list {
            flex: 1;
            overflow-y: auto;
        }

        .chat-section {
            padding: 12px 16px;
            font-weight: 700;
            color: #0F4C3A;
            font-size: 12px;
            background: #E8F5E9;
            border-bottom: 1px solid #E8EDEC;
        }

        .chat-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            cursor: pointer;
            border-bottom: 1px solid #F0F2F5;
            transition: background 0.2s;
        }

        .chat-item:hover {
            background: #F5F7F6;
        }

        .chat-item.active {
            background: #E8F5E9;
            border-left: 3px solid #2E7D64;
        }

        .chat-avatar {
            width: 48px;
            height: 48px;
            background: #2E7D64;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
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

        /* Main Chat Area */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
            height: 100%;
        }

        .chat-header {
            padding: 16px 20px;
            border-bottom: 1px solid #E8EDEC;
            background: white;
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
        .status-badge.connected { background: #4caf50; color: white; }
        .status-badge.disconnected { background: #f44336; color: white; }

        /* Messages Area */
        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            background: #F5F7F6;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

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
            max-width: 75%;
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
            border: 1px solid #E8EDEC;
            border-bottom-left-radius: 4px;
        }

        .message-info {
            font-size: 10px;
            margin-top: 4px;
            color: #8A9B97;
            text-align: right;
        }

        /* Input Area */
        .input-area {
            padding: 12px 16px;
            background: white;
            border-top: 1px solid #E8EDEC;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .input-area input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #E8EDEC;
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
        }

        .empty-chat {
            text-align: center;
            padding: 40px 20px;
            color: #8A9B97;
        }

        /* Wake Button */
        .wake-btn {
            background: #ff9800;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 11px;
            margin-left: 8px;
        }

        /* Mobile Styles */
        .menu-btn, .back-btn {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #1A2E28;
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
                left: -300px;
                top: 0;
                bottom: 0;
                z-index: 200;
                width: 280px;
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
            <h2>💬 Messages</h2>
            <p><?php echo htmlspecialchars($user_name); ?></p>
        </div>
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="🔍 Search conversations...">
        </div>
        <div class="chat-list" id="chatList">
            <div class="chat-section">📁 GROUPS</div>
            <?php if(mysqli_num_rows($groups) > 0): ?>
                <?php while($group = mysqli_fetch_assoc($groups)): ?>
                <div class="chat-item" data-id="<?php echo $group['id']; ?>" data-type="group" data-name="<?php echo htmlspecialchars($group['name']); ?>">
                    <div class="chat-avatar">👥</div>
                    <div class="chat-info">
                        <div class="chat-name"><?php echo htmlspecialchars($group['name']); ?></div>
                        <div class="chat-preview">Group conversation</div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 20px; text-align: center; color: #8A9B97;">No groups available</div>
            <?php endif; ?>
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
            <input type="text" id="messageInput" placeholder="Select a chat first..." disabled>
            <button id="sendBtn" onclick="sendMessage()" disabled>➤</button>
        </div>
    </div>
</div>

<button class="mobile-hamburger" onclick="toggleSidebar()">💬</button>

<script>
    // Configuration
    const SOCKET_URL = 'https://foc-connect-websocket.onrender.com';
    const userId = <?php echo $user_id; ?>;
    const userName = <?php echo json_encode($user_name); ?>;

    // State variables
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

    // ========== Socket.IO Connection ==========
    function connectSocket() {
        statusSpan.className = 'status-badge disconnected';
        statusSpan.innerHTML = 'Connecting...';

        socket = io(SOCKET_URL, {
            transports: ['websocket', 'polling'],
            reconnection: true,
            reconnectionAttempts: 10,
            reconnectionDelay: 2000,
            timeout: 30000
        });

        socket.on('connect', () => {
            console.log('✅ Connected to chat server');
            isConnected = true;
            statusSpan.className = 'status-badge connected';
            statusSpan.innerHTML = 'Connected ✓';
            socket.emit('user-joined', userId);
            
            // Re-join current group if any
            if (currentChatId && currentChatType === 'group') {
                socket.emit('join-group', currentChatId);
            }
        });

        socket.on('disconnect', () => {
            console.log('⚠️ Disconnected');
            isConnected = false;
            statusSpan.className = 'status-badge disconnected';
            statusSpan.innerHTML = 'Offline';
        });

        socket.on('connect_error', (error) => {
            console.log('❌ Connection error:', error.message);
            isConnected = false;
            statusSpan.className = 'status-badge disconnected';
            statusSpan.innerHTML = 'Offline <button class="wake-btn" onclick="wakeServer()">Wake</button>';
        });

        socket.on('new-message', (msg) => {
            console.log('📨 New message:', msg);
            if (currentChatId && ((currentChatType === 'group' && msg.group_id == currentChatId) ||
                (currentChatType === 'user' && (msg.from_user_id == currentChatId || msg.to_user_id == currentChatId)))) {
                displayMessage(msg);
                if (msg.from_user_id != userId) {
                    socket.emit('mark-message-read', { message_id: msg.id, user_id: userId, from_user_id: msg.from_user_id });
                }
            }
        });

        socket.on('user-typing', (data) => {
            if (data.user_id != userId && data.isTyping) {
                typingIndicator.textContent = 'Someone is typing...';
                setTimeout(() => {
                    if (typingIndicator.textContent === 'Someone is typing...') {
                        typingIndicator.textContent = '';
                    }
                }, 3000);
            } else if (!data.isTyping) {
                typingIndicator.textContent = '';
            }
        });
    }

    function wakeServer() {
        statusSpan.innerHTML = 'Waking...';
        fetch(SOCKET_URL + '/health')
            .then(() => {
                statusSpan.innerHTML = 'Reconnecting...';
                if (socket) socket.disconnect();
                setTimeout(connectSocket, 1000);
            })
            .catch(() => {
                statusSpan.innerHTML = 'Offline <button class="wake-btn" onclick="wakeServer()">Wake</button>';
            });
    }

    // ========== Chat Selection ==========
    function selectChat(element) {
        const chatId = parseInt(element.dataset.id);
        const chatType = element.dataset.type;
        const chatName = element.dataset.name;

        if (currentItem) {
            currentItem.classList.remove('active');
        }
        element.classList.add('active');
        currentItem = element;
        currentChatId = chatId;
        currentChatType = chatType;
        currentChatName = chatName;

        chatTitle.innerHTML = chatName;
        chatSubtitle.innerHTML = chatType === 'group' ? 'Group Chat' : 'Private Chat';
        messageInput.disabled = false;
        sendBtn.disabled = false;
        messageInput.placeholder = 'Type a message...';

        if (chatType === 'group' && socket && isConnected) {
            socket.emit('join-group', chatId);
        }

        loadMessages(chatType, chatId);
        closeSidebar();
    }

    function loadMessages(type, id) {
        messagesDiv.innerHTML = '<div class="empty-chat">Loading messages...</div>';

        fetch(`${SOCKET_URL}/api/messages/${type}/${id}`)
            .then(res => res.json())
            .then(messages => {
                messagesDiv.innerHTML = '';
                if (messages.length === 0) {
                    messagesDiv.innerHTML = '<div class="empty-chat">💬 No messages yet. Send the first one!</div>';
                } else {
                    messages.forEach(msg => displayMessage(msg));
                    scrollToBottom();
                }
            })
            .catch(err => {
                console.error('Load error:', err);
                messagesDiv.innerHTML = '<div class="empty-chat">⚠️ Server waking up. Click "Wake" button.<br><br><button class="wake-btn" onclick="wakeServer()">🔌 Wake Server</button></div>';
            });
    }

    function displayMessage(msg) {
        const isSent = msg.from_user_id == userId;
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
        messageDiv.id = `msg-${msg.id}`;

        let senderName = '';
        if (!isSent && currentChatType === 'group' && msg.sender_name) {
            senderName = `<strong style="font-size:12px;">${escapeHtml(msg.sender_name)}</strong><br>`;
        }

        messageDiv.innerHTML = `
            <div class="bubble">
                ${senderName}
                ${escapeHtml(msg.message)}
                <div class="message-info">
                    ${formatTime(msg.sent_at)}
                </div>
            </div>
        `;
        messagesDiv.appendChild(messageDiv);
        scrollToBottom();
    }

    function sendMessage() {
        const message = messageInput.value.trim();
        if (!message || !currentChatId) return;
        if (!isConnected) {
            alert('Not connected to chat server. Click "Wake" button.');
            return;
        }

        const data = { from_user_id: userId, message: message };
        if (currentChatType === 'group') {
            data.group_id = currentChatId;
        } else {
            data.to_user_id = currentChatId;
        }

        socket.emit('send-message', data);
        messageInput.value = '';
        
        // Send typing stopped
        socket.emit('typing', {
            from_user_id: userId,
            to_user_id: currentChatType === 'user' ? currentChatId : null,
            group_id: currentChatType === 'group' ? currentChatId : null,
            isTyping: false
        });
    }

    function handleTyping() {
        if (!socket || !isConnected || !currentChatId) return;
        socket.emit('typing', {
            from_user_id: userId,
            to_user_id: currentChatType === 'user' ? currentChatId : null,
            group_id: currentChatType === 'group' ? currentChatId : null,
            isTyping: true
        });
        clearTimeout(window.typingTimeout);
        window.typingTimeout = setTimeout(() => {
            socket.emit('typing', {
                from_user_id: userId,
                to_user_id: currentChatType === 'user' ? currentChatId : null,
                group_id: currentChatType === 'group' ? currentChatId : null,
                isTyping: false
            });
        }, 2000);
    }

    // ========== Helper Functions ==========
    function scrollToBottom() {
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    function formatTime(datetime) {
        if (!datetime) return 'Just now';
        const date = new Date(datetime);
        const now = new Date();
        const diff = now - date;
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm';
        if (diff < 86400000) return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        return date.toLocaleDateString();
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

    // ========== Event Listeners ==========
    // Attach click handlers to chat items
    function attachChatHandlers() {
        document.querySelectorAll('.chat-item').forEach(item => {
            item.removeEventListener('click', selectChatHandler);
            item.addEventListener('click', selectChatHandler);
        });
    }

    function selectChatHandler(e) {
        selectChat(this);
    }

    // Search functionality
    document.getElementById('searchInput')?.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        document.querySelectorAll('.chat-item').forEach(item => {
            const name = item.querySelector('.chat-name')?.innerText.toLowerCase() || '';
            item.style.display = name.includes(searchTerm) ? 'flex' : 'none';
        });
    });

    // Typing handler
    messageInput.addEventListener('input', handleTyping);
    messageInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // Initialize
    connectSocket();
    attachChatHandlers();

    // Keep-alive ping every 40 seconds
    setInterval(() => {
        if (isConnected && socket) {
            socket.emit('ping');
        } else if (!isConnected) {
            fetch(SOCKET_URL + '/health').catch(() => {});
        }
    }, 40000);
</script>
</body>
</html>
