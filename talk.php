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
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; background: #E8F5E9; height: 100vh; display: flex; overflow: hidden; }
        
        /* Sidebar - Fixed width, scrollable */
        .sidebar { width: 300px; background: white; border-right: 1px solid #e0e0e0; display: flex; flex-direction: column; height: 100vh; overflow-y: auto; }
        .sidebar-header { background: #8BC34A; padding: 20px; color: #1B5E20; position: sticky; top: 0; z-index: 10; }
        .sidebar-header h2 { font-size: 20px; }
        .sidebar-header p { font-size: 14px; opacity: 0.9; margin-top: 5px; }
        .chat-list { flex: 1; overflow-y: auto; }
        .chat-item { padding: 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; display: flex; gap: 12px; align-items: center; transition: background 0.2s; }
        .chat-item:hover { background: #f5f5f5; }
        .chat-avatar { width: 48px; height: 48px; background: #C5E1A5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .chat-info { flex: 1; min-width: 0; }
        .chat-name { font-weight: 600; color: #333; margin-bottom: 4px; }
        .chat-preview { font-size: 12px; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .section-title { padding: 10px 15px; background: #f8f9fa; font-weight: 600; font-size: 12px; color: #666; letter-spacing: 0.5px; }
        
        /* Main Chat Area - Flex column for proper layout */
        .chat-main { flex: 1; display: flex; flex-direction: column; background: white; height: 100vh; overflow: hidden; }
        
        /* Chat Header - Fixed at top */
        .chat-header { padding: 15px 20px; border-bottom: 1px solid #e0e0e0; background: white; flex-shrink: 0; }
        .chat-header h3 { font-size: 18px; color: #333; }
        .chat-header .status { font-size: 12px; color: #4caf50; margin-top: 4px; }
        
        /* Messages Container - Scrollable area */
        .messages-container { flex: 1; overflow-y: auto; padding: 20px; background: #F5F8F0; min-height: 0; }
        .message { margin-bottom: 16px; display: flex; }
        .message.sent { justify-content: flex-end; }
        .message.received { justify-content: flex-start; }
        .bubble { max-width: 70%; padding: 10px 16px; border-radius: 18px; word-wrap: break-word; }
        .message.sent .bubble { background: #DCF8C6; border-bottom-right-radius: 4px; }
        .message.received .bubble { background: white; border: 1px solid #e0e0e0; border-bottom-left-radius: 4px; }
        .message-info { font-size: 10px; margin-top: 4px; color: #999; text-align: right; }
        .sender-name { font-weight: 600; font-size: 12px; margin-bottom: 4px; color: #1B5E20; }
        
        /* Input Area - Fixed at bottom */
        .input-area { padding: 15px 20px; border-top: 1px solid #e0e0e0; background: white; display: flex; gap: 12px; flex-shrink: 0; }
        .input-area input { flex: 1; padding: 12px 16px; border: 1px solid #e0e0e0; border-radius: 25px; font-size: 14px; outline: none; transition: border 0.2s; }
        .input-area input:focus { border-color: #8BC34A; }
        .input-area input:disabled { background: #f5f5f5; cursor: not-allowed; }
        .input-area button { background: #8BC34A; color: white; border: none; padding: 12px 24px; border-radius: 25px; cursor: pointer; font-size: 14px; font-weight: 600; transition: background 0.2s; }
        .input-area button:hover { background: #7cb342; }
        .input-area button:disabled { background: #ccc; cursor: not-allowed; }
        
        .empty { text-align: center; padding: 60px 20px; color: #999; }
        .empty-chat { text-align: center; padding: 60px 20px; color: #999; display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .empty-chat span { font-size: 48px; }
        
        /* Mobile Responsive */
        .menu-btn { display: none; position: fixed; bottom: 20px; right: 20px; background: #8BC34A; color: white; border: none; width: 56px; height: 56px; border-radius: 50%; font-size: 24px; cursor: pointer; z-index: 99; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -300px; height: 100vh; z-index: 100; transition: 0.3s; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
            .sidebar.open { left: 0; }
            .menu-btn { display: flex; align-items: center; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>💬 FoC Connect</h2>
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
        <p id="chatSubtitle" class="status">Select a conversation</p>
    </div>
    
    <div class="messages-container" id="messagesContainer">
        <div class="empty-chat">
            <span>💬</span>
            <p>Select a conversation to start messaging</p>
        </div>
    </div>
    
    <div class="input-area">
        <input type="text" id="messageInput" placeholder="Type a message..." disabled>
        <button id="sendBtn" onclick="sendMessage()" disabled>Send</button>
    </div>
</div>

<button class="menu-btn" onclick="toggleSidebar()">☰</button>

<script>
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
            if (data.success && data.data && data.data.length > 0) {
                container.innerHTML = '';
                data.data.forEach(group => {
                    container.innerHTML += '<div class="chat-item" onclick="selectChat(' + group.id + ', \'group\', \'' + escapeHtml(group.name) + '\')">' +
                        '<div class="chat-avatar">👥</div>' +
                        '<div class="chat-info">' +
                        '<div class="chat-name">' + escapeHtml(group.name) + '</div>' +
                        '<div class="chat-preview">Group chat • ' + (group.member_count || '0') + ' members</div>' +
                        '</div>' +
                        '</div>';
                });
            } else {
                container.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No groups yet</div>';
            }
        })
        .catch(err => console.error('Groups error:', err));

    fetch('api-data.php?action=contacts')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('contactsList');
            if (data.success && data.data && data.data.length > 0) {
                container.innerHTML = '';
                data.data.forEach(contact => {
                    container.innerHTML += '<div class="chat-item" onclick="selectChat(' + contact.id + ', \'user\', \'' + escapeHtml(contact.name) + '\')">' +
                        '<div class="chat-avatar">👤</div>' +
                        '<div class="chat-info">' +
                        '<div class="chat-name">' + escapeHtml(contact.name) + '</div>' +
                        '<div class="chat-preview">' + escapeHtml(contact.matric_number || 'Student') + '</div>' +
                        '</div>' +
                        '</div>';
                });
            } else {
                container.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No contacts yet</div>';
            }
        })
        .catch(err => console.error('Contacts error:', err));
}

function selectChat(id, type, name) {
    currentChatId = id;
    currentChatType = type;
    currentChatName = name;
    document.getElementById('chatTitle').innerHTML = escapeHtml(name);
    document.getElementById('chatSubtitle').innerHTML = (type === 'group' ? 'Group Chat' : 'Private Chat');
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
    const container = document.getElementById('messagesContainer');
    container.innerHTML = '<div class="empty-chat"><span>⏳</span><p>Loading messages...</p></div>';
    
    fetch('api-data.php?action=messages&type=' + type + '&id=' + id)
        .then(res => res.json())
        .then(data => {
            container.innerHTML = '';
            if (data.success && data.data && data.data.length > 0) {
                data.data.forEach(msg => displayMessage(msg));
                scrollToBottom();
            } else {
                container.innerHTML = '<div class="empty-chat"><span>💬</span><p>No messages yet. Send the first one!</p></div>';
            }
        })
        .catch(err => {
            console.error('Load messages error:', err);
            container.innerHTML = '<div class="empty-chat"><span>⚠️</span><p>Error loading messages</p></div>';
        });
}

function displayMessage(msg) {
    const isSent = parseInt(msg.from_user_id) === parseInt(userId);
    const container = document.getElementById('messagesContainer');
    
    // Remove empty state if it exists
    const emptyDiv = container.querySelector('.empty-chat');
    if (emptyDiv) emptyDiv.remove();
    
    const div = document.createElement('div');
    div.className = 'message ' + (isSent ? 'sent' : 'received');
    
    let senderHtml = '';
    if (!isSent && currentChatType === 'group' && msg.sender_name) {
        senderHtml = '<div class="sender-name">' + escapeHtml(msg.sender_name) + '</div>';
    }
    
    const messageText = msg.message || msg.text || '';
    const timeStamp = msg.sent_at || msg.created_at || new Date().toISOString();
    
    div.innerHTML = '<div class="bubble">' + senderHtml + escapeHtml(messageText) + '<div class="message-info">' + formatTime(timeStamp) + '</div></div>';
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
    statusSpan.style.color = '#ff9800';
    
    socket = io(SOCKET_URL, { transports: ['websocket', 'polling'], reconnection: true });
    
    socket.on('connect', () => {
        isConnected = true;
        console.log('Socket connected:', socket.id);
        statusSpan.innerHTML = 'Connected ✓';
        statusSpan.style.color = '#4caf50';
        socket.emit('user-joined', userId);
    });
    
    socket.on('disconnect', () => {
        isConnected = false;
        console.log('Socket disconnected');
        statusSpan.innerHTML = 'Offline';
        statusSpan.style.color = '#f44336';
    });
    
    socket.on('connect_error', (error) => {
        isConnected = false;
        console.error('Socket error:', error);
        statusSpan.innerHTML = 'Connecting failed';
        statusSpan.style.color = '#f44336';
    });
    
    socket.on('new-message', (msg) => {
        console.log('New message received:', msg);
        if (currentChatId && ((currentChatType === 'group' && msg.group_id == currentChatId) ||
            (currentChatType === 'user' && (msg.from_user_id == currentChatId || msg.to_user_id == currentChatId)))) {
            displayMessage(msg);
        }
    });
}

function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    container.scrollTop = container.scrollHeight;
}

function formatTime(datetime) {
    if (!datetime) return 'Just now';
    try {
        const date = new Date(datetime);
        const now = new Date();
        const diff = now - date;
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + ' min ago';
        if (diff < 86400000) return Math.floor(diff / 3600000) + ' hours ago';
        return date.toLocaleDateString();
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
