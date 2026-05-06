<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_name = $_SESSION['name'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Chat - FoC Connect</title>
    <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #F5F7F6;
            height: 100vh;
            display: flex;
            overflow: hidden;
            position: fixed;
            width: 100%;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #0F4C3A;
            color: white;
            height: 100%;
            padding: 20px;
            overflow-y: auto;
            transition: transform 0.3s;
            z-index: 100;
        }
        .sidebar h2 { margin-bottom: 20px; font-size: 22px; }
        .sidebar a {
            display: block;
            padding: 12px;
            margin: 5px 0;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
        .sidebar a:hover { background: #2E7D64; }
        
        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
            width: calc(100% - 280px);
        }
        .chat-header {
            padding: 16px 20px;
            border-bottom: 1px solid #ddd;
            background: white;
        }
        .chat-header h2 { color: #0F4C3A; font-size: 20px; }
        .chat-header p { font-size: 12px; color: #888; margin-top: 4px; }
        
        /* Messages */
        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #F5F7F6;
        }
        .message {
            margin-bottom: 15px;
            display: flex;
        }
        .message.sent { justify-content: flex-end; }
        .message.received { justify-content: flex-start; }
        .bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        .message.sent .bubble {
            background: #2E7D64;
            color: white;
            border-bottom-right-radius: 4px;
        }
        .message.received .bubble {
            background: white;
            border: 1px solid #ddd;
            border-bottom-left-radius: 4px;
        }
        .message-info {
            font-size: 10px;
            margin-top: 4px;
            color: #888;
        }
        .message.sent .message-info { text-align: right; }
        
        /* Input */
        .input-area {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
            background: white;
        }
        .input-area input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 25px;
            outline: none;
            font-size: 14px;
        }
        .input-area button {
            background: #2E7D64;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
        }
        .input-area button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        /* Chat List */
        .chat-list {
            margin-top: 20px;
        }
        .chat-section {
            padding: 10px 0;
            font-weight: bold;
            color: #D69E2E;
            font-size: 12px;
            text-transform: uppercase;
        }
        .chat-item {
            padding: 12px;
            cursor: pointer;
            border-radius: 8px;
            margin-bottom: 4px;
            background: rgba(255,255,255,0.1);
        }
        .chat-item:hover { background: rgba(255,255,255,0.2); }
        .chat-item.active { background: #2E7D64; }
        .status {
            font-size: 11px;
            margin-top: 8px;
            padding: 8px;
            text-align: center;
            background: #1a3a2e;
            border-radius: 8px;
        }
        .connected { color: #4caf50; }
        .disconnected { color: #f44336; }
        
        .empty { text-align: center; padding: 40px; color: #888; }
        .wake-btn {
            background: #ff9800;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 20px;
            cursor: pointer;
            margin-left: 8px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                bottom: 0;
                z-index: 200;
            }
            .sidebar.open { left: 0; }
            .chat-area { width: 100%; }
            .menu-btn {
                display: block;
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
                z-index: 99;
            }
            .back-btn {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                margin-right: 10px;
            }
        }
        .menu-btn, .back-btn { display: none; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <h2>🌿 FoC Connect</h2>
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="talk.php" style="background:#2E7D64;">💬 Chat</a>
    <a href="announcements.php">📢 Announcements</a>
    <a href="materials.php">📚 Materials</a>
    <a href="assignments.php">📝 Assignments</a>
    <a href="logout.php">🚪 Logout</a>
    
    <div style="margin-top: 30px;">
        <div class="chat-section">📁 GROUPS</div>
        <div id="groupsList" class="chat-list"></div>
    </div>
    
    <div class="status" id="status">🟡 Connecting...</div>
</div>

<!-- Chat Area -->
<div class="chat-area">
    <div class="chat-header">
        <div style="display: flex; align-items: center;">
            <button class="back-btn" onclick="toggleSidebar()">☰</button>
            <div>
                <h2 id="chatTitle">💬 FoC Connect</h2>
                <p id="chatSubtitle">Select a chat to start messaging</p>
            </div>
        </div>
    </div>
    
    <div class="messages" id="messages">
        <div class="empty">💬 Select a conversation to start messaging</div>
    </div>
    
    <div class="input-area">
        <input type="text" id="messageInput" placeholder="Select a chat first..." disabled>
        <button id="sendBtn" onclick="sendMessage()" disabled>Send</button>
    </div>
</div>

<button class="menu-btn" onclick="toggleSidebar()">💬</button>

<script>
    // Configuration
    const SOCKET_URL = 'https://foc-connect-websocket.onrender.com';
    let userId = null;
    let userName = null;
    let socket = null;
    let isConnected = false;
    let currentChatId = null;
    let currentChatType = null;
    let currentChatName = null;
    let currentItem = null;
    
    // DOM Elements
    const groupsList = document.getElementById('groupsList');
    const messagesDiv = document.getElementById('messages');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const chatTitle = document.getElementById('chatTitle');
    const chatSubtitle = document.getElementById('chatSubtitle');
    const statusDiv = document.getElementById('status');
    
    // Get user info from session via AJAX
    fetch('chat-api.php?action=groups')
        .then(res => res.json())
        .then(data => {
            if(data.success && data.data) {
                renderGroups(data.data);
                // Also need user ID - could add another endpoint or set in PHP
            }
        });
    
    // Also get user ID from a simple endpoint
    fetch('get-user.php')
        .then(res => res.json())
        .then(data => {
            userId = data.user_id;
            userName = data.name;
            connectSocket();
        });
    
    function renderGroups(groups) {
        if(!groups || groups.length === 0) {
            groupsList.innerHTML = '<div style="padding:12px; color:#aaa;">No groups available</div>';
            return;
        }
        
        groupsList.innerHTML = '';
        groups.forEach(group => {
            const div = document.createElement('div');
            div.className = 'chat-item';
            div.setAttribute('data-id', group.id);
            div.setAttribute('data-type', 'group');
            div.setAttribute('data-name', group.name);
            div.innerHTML = `👥 ${group.name}`;
            div.onclick = () => selectChat(div);
            groupsList.appendChild(div);
        });
    }
    
    function selectChat(element) {
        const id = parseInt(element.dataset.id);
        const type = element.dataset.type;
        const name = element.dataset.name;
        
        if(currentItem) {
            currentItem.classList.remove('active');
        }
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
        
        if(window.innerWidth <= 768) {
            toggleSidebar();
        }
    }
    
    function loadMessages(type, id) {
        messagesDiv.innerHTML = '<div class="empty">Loading messages...</div>';
        
        fetch(`chat-api.php?action=messages&type=${type}&id=${id}`)
            .then(res => res.json())
            .then(data => {
                if(data.success && data.data) {
                    messagesDiv.innerHTML = '';
                    if(data.data.length === 0) {
                        messagesDiv.innerHTML = '<div class="empty">💬 No messages yet. Send the first one!</div>';
                    } else {
                        data.data.forEach(msg => displayMessage(msg));
                        scrollToBottom();
                    }
                }
            })
            .catch(err => {
                messagesDiv.innerHTML = '<div class="empty">⚠️ Error loading messages. Try again.</div>';
            });
    }
    
    function displayMessage(msg) {
        const isSent = msg.from_user_id == userId;
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
        
        let senderName = '';
        if(!isSent && currentChatType === 'group' && msg.sender_name) {
            senderName = `<strong>${escapeHtml(msg.sender_name)}</strong><br>`;
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
        if(!message || !currentChatId) return;
        if(!isConnected) {
            alert('Not connected. Please wait for connection.');
            return;
        }
        
        const data = { from_user_id: userId, message: message };
        if(currentChatType === 'group') {
            data.group_id = currentChatId;
        } else {
            data.to_user_id = currentChatId;
        }
        
        socket.emit('send-message', data);
        messageInput.value = '';
    }
    
    function connectSocket() {
        statusDiv.innerHTML = '🟡 Connecting to server...';
        
        socket = io(SOCKET_URL, {
            transports: ['websocket', 'polling'],
            reconnection: true,
            reconnectionAttempts: 10,
            reconnectionDelay: 2000
        });
        
        socket.on('connect', () => {
            console.log('Connected!');
            isConnected = true;
            statusDiv.innerHTML = '✅ Connected';
            socket.emit('user-joined', userId);
        });
        
        socket.on('disconnect', () => {
            isConnected = false;
            statusDiv.innerHTML = '⚠️ Disconnected <button class="wake-btn" onclick="wakeServer()">Wake</button>';
        });
        
        socket.on('connect_error', () => {
            isConnected = false;
            statusDiv.innerHTML = '❌ Offline <button class="wake-btn" onclick="wakeServer()">Wake</button>';
        });
        
        socket.on('new-message', (msg) => {
            if(currentChatId && ((currentChatType === 'group' && msg.group_id == currentChatId) ||
               (currentChatType === 'user' && (msg.from_user_id == currentChatId || msg.to_user_id == currentChatId)))) {
                displayMessage(msg);
            }
        });
    }
    
    function wakeServer() {
        statusDiv.innerHTML = '🟡 Waking server...';
        fetch(SOCKET_URL + '/health')
            .then(() => {
                statusDiv.innerHTML = '🟡 Reconnecting...';
                if(socket) socket.disconnect();
                setTimeout(connectSocket, 1000);
            })
            .catch(() => {
                statusDiv.innerHTML = '❌ Failed <button class="wake-btn" onclick="wakeServer()">Try Again</button>';
            });
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
        document.getElementById('sidebar').classList.toggle('open');
    }
    
    messageInput.addEventListener('keypress', (e) => {
        if(e.key === 'Enter') sendMessage();
    });
</script>
</body>
</html>
