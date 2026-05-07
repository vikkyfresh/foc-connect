<?php
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat - FoC Connect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #E8F5E9; height: 100vh; display: flex; }
        .sidebar { width: 300px; background: white; border-right: 1px solid #ddd; display: flex; flex-direction: column; }
        .sidebar-header { background: #8BC34A; padding: 20px; color: #1B5E20; }
        .chat-list { flex: 1; overflow-y: auto; }
        .chat-item { padding: 15px; cursor: pointer; border-bottom: 1px solid #eee; display: flex; gap: 10px; align-items: center; }
        .chat-item:hover { background: #f5f5f5; }
        .chat-avatar { width: 45px; height: 45px; background: #C5E1A5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .chat-name { font-weight: bold; }
        .chat-preview { font-size: 12px; color: #888; }
        .chat-main { flex: 1; display: flex; flex-direction: column; background: white; }
        .chat-header { padding: 15px; border-bottom: 1px solid #ddd; background: white; }
        .messages { flex: 1; overflow-y: auto; padding: 20px; background: #F5F8F0; }
        .message { margin-bottom: 15px; display: flex; }
        .message.sent { justify-content: flex-end; }
        .message.received { justify-content: flex-start; }
        .bubble { max-width: 60%; padding: 10px 15px; border-radius: 20px; }
        .message.sent .bubble { background: #DCF8C6; }
        .message.received .bubble { background: white; border: 1px solid #ddd; }
        .message-info { font-size: 10px; margin-top: 4px; color: #888; text-align: right; }
        .input-area { padding: 15px; border-top: 1px solid #ddd; display: flex; gap: 10px; }
        .input-area input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 25px; }
        .input-area button { background: #8BC34A; color: white; border: none; padding: 10px 20px; border-radius: 25px; cursor: pointer; }
        .status { font-size: 11px; margin-top: 5px; color: #888; }
        .section-title { padding: 10px 15px; background: #f0f0f0; font-weight: bold; font-size: 12px; color: #666; }
        @media (max-width: 768px) { .sidebar { position: fixed; left: -300px; height: 100%; z-index: 100; transition: 0.3s; } .sidebar.open { left: 0; } .menu-btn { position: fixed; bottom: 20px; right: 20px; background: #8BC34A; color: white; border: none; width: 50px; height: 50px; border-radius: 50%; font-size: 24px; cursor: pointer; z-index: 99; } }
        .menu-btn { display: none; }
        @media (max-width: 768px) { .menu-btn { display: block; } }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>💬 Chats</h2>
        <p><?php echo htmlspecialchars($user_name); ?></p>
    </div>
    <div>
        <div class="section-title">📁 GROUPS</div>
        <div id="groupsList" class="chat-list"></div>
        <div class="section-title">👤 CONTACTS</div>
        <div id="contactsList" class="chat-list"></div>
    </div>
</div>

<div class="chat-main">
    <div class="chat-header">
        <h3 id="chatTitle">💬 FoC Connect</h3>
        <p id="chatSubtitle" class="status">Select a chat</p>
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
// Make sure there is NO PHP code or HTML inside this script tag
const SOCKET_URL = 'https://foc-connect-websocket.onrender.com';
let userId = <?php echo (int)$user_id; ?>;
let socket = null;
let isConnected = false;
let currentChatId = null;
let currentChatType = null;
let currentChatName = '';

function loadChats() {
    fetch('api-data.php?action=groups')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('groupsList');
            if (data.success && data.data.length > 0) {
                container.innerHTML = '';
                data.data.forEach(group => {
                    container.innerHTML += '<div class="chat-item" onclick="selectChat(' + group.id + ', \'group\', \'' + escapeHtml(group.name) + '\')">' +
                        '<div class="chat-avatar">👥</div>' +
                        '<div><div class="chat-name">' + escapeHtml(group.name) + '</div><div class="chat-preview">Group chat</div></div>' +
                        '</div>';
                });
            } else {
                container.innerHTML = '<div class="empty" style="padding:20px;">No groups yet</div>';
            }
        })
        .catch(err => console.error('Groups error:', err));

    fetch('api-data.php?action=contacts')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('contactsList');
            if (data.success && data.data.length > 0) {
                container.innerHTML = '';
                data.data.forEach(contact => {
                    container.innerHTML += '<div class="chat-item" onclick="selectChat(' + contact.id + ', \'user\', \'' + escapeHtml(contact.name) + '\')">' +
                        '<div class="chat-avatar">👤</div>' +
                        '<div><div class="chat-name">' + escapeHtml(contact.name) + '</div><div class="chat-preview">' + escapeHtml(contact.matric_number || 'Student') + '</div></div>' +
                        '</div>';
                });
            } else {
                container.innerHTML = '<div class="empty" style="padding:20px;">No contacts yet</div>';
            }
        })
        .catch(err => console.error('Contacts error:', err));
}

function selectChat(id, type, name) {
    currentChatId = id;
    currentChatType = type;
    currentChatName = name;
    document.getElementById('chatTitle').innerHTML = escapeHtml(name);
    document.getElementById('chatSubtitle').innerHTML = (type === 'group' ? 'Group Chat • Connected ✓' : 'Private Chat • Connected ✓');
    document.getElementById('messageInput').disabled = false;
    document.getElementById('sendBtn').disabled = false;
    document.getElementById('messageInput').placeholder = 'Type a message...';
    
    if (type === 'group' && socket && isConnected) {
        socket.emit('join-group', id);
    }
    
    loadMessages(type, id);
    
    if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('open');
    }
}

function loadMessages(type, id) {
    const container = document.getElementById('messages');
    container.innerHTML = '<div class="empty">Loading messages...</div>';
    
    fetch('api-data.php?action=messages&type=' + type + '&id=' + id)
        .then(res => res.json())
        .then(data => {
            container.innerHTML = '';
            if (data.success && data.data.length > 0) {
                data.data.forEach(msg => displayMessage(msg));
                scrollToBottom();
            } else {
                container.innerHTML = '<div class="empty">💬 No messages yet. Send the first one!</div>';
            }
        })
        .catch(err => {
            console.error('Load messages error:', err);
            container.innerHTML = '<div class="empty">⚠️ Error loading messages</div>';
        });
}

function displayMessage(msg) {
    const isSent = parseInt(msg.from_user_id) === parseInt(userId);
    const container = document.getElementById('messages');
    
    const emptyDiv = container.querySelector('.empty');
    if (emptyDiv) emptyDiv.remove();
    
    const div = document.createElement('div');
    div.className = 'message ' + (isSent ? 'sent' : 'received');
    
    let senderHtml = '';
    if (!isSent && currentChatType === 'group' && msg.sender_name) {
        senderHtml = '<strong>' + escapeHtml(msg.sender_name) + '</strong><br>';
    }
    
    div.innerHTML = '<div class="bubble">' + senderHtml + escapeHtml(msg.message || msg.text || '') + '<div class="message-info">' + formatTime(msg.sent_at) + '</div></div>';
    container.appendChild(div);
    scrollToBottom();
}

function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message || !currentChatId) return;
    if (!isConnected) {
        alert('Not connected to chat server');
        return;
    }
    
    const data = { from_user_id: userId, message: message };
    if (currentChatType === 'group') data.group_id = currentChatId;
    else data.to_user_id = currentChatId;
    
    socket.emit('send-message', data);
    input.value = '';
}

function connectSocket() {
    const statusSpan = document.getElementById('chatSubtitle');
    statusSpan.innerHTML = 'Connecting...';
    
    socket = io(SOCKET_URL, { transports: ['websocket', 'polling'], reconnection: true });
    
    socket.on('connect', () => {
        isConnected = true;
        console.log('Socket connected:', socket.id);
        statusSpan.innerHTML = currentChatName ? (currentChatType === 'group' ? 'Group Chat • Connected ✓' : 'Private Chat • Connected ✓') : 'Connected ✓';
        socket.emit('user-joined', userId);
    });
    
    socket.on('disconnect', () => {
        isConnected = false;
        console.log('Socket disconnected');
        statusSpan.innerHTML = 'Disconnected';
    });
    
    socket.on('connect_error', (error) => {
        isConnected = false;
        console.error('Socket error:', error);
        statusSpan.innerHTML = 'Offline';
    });
    
    socket.on('new-message', (msg) => {
        console.log('New message:', msg);
        if (currentChatId && ((currentChatType === 'group' && msg.group_id == currentChatId) ||
            (currentChatType === 'user' && (msg.from_user_id == currentChatId || msg.to_user_id == currentChatId)))) {
            displayMessage(msg);
        }
    });
}

function scrollToBottom() {
    const container = document.getElementById('messages');
    container.scrollTop = container.scrollHeight;
}

function formatTime(datetime) {
    if (!datetime) return 'Just now';
    try {
        const date = new Date(datetime);
        const diff = new Date() - date;
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm';
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return 'Just now';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// Initialize
console.log('Page loaded, user ID:', userId);
connectSocket();
loadChats();

document.getElementById('messageInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') sendMessage();
});
</script>
</body>
</html>
