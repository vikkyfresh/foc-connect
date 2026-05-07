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
        .connected { color: green; }
        .empty { text-align: center; padding: 40px; color: #888; }
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
    const SOCKET_URL = 'https://foc-connect-websocket.onrender.com';
    let userId = <?php echo $user_id; ?>;
    let socket = null;
    let isConnected = false;
    let currentChatId = null;
    let currentChatType = null;
    let currentChatName = '';

    function loadChats() {
        // Load Groups
        fetch('api-data.php?action=groups')
            .then(res => res.json())
            .then(data => {
                const container = document.getElementById('groupsList');
                if(data.success && data.data && data.data.length > 0) {
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
            .catch(err => console.log('Groups error:', err));

        // Load Contacts
        fetch('api-data.php?action=contacts')
            .then(res => res.json())
            .then(data => {
                const container = document.getElementById('contactsList');
                if(data.success && data.data && data.data.length > 0) {
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
            .catch(err => console.log('Contacts error:', err));
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
        
        if(type === 'group' && socket && isConnected) {
            socket.emit('join-group', id);
        }
        
        loadMessages(type, id);
        
        if(window.innerWidth <= 768) {
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
                if(data.success && data.data && data.data.length > 0) {
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
        
        // Remove "No messages yet" empty div if it exists
        const emptyDiv = container.querySelector('.empty');
        if(emptyDiv) {
            emptyDiv.remove();
        }
        
        const div = document.createElement('div');
        div.className = 'message ' + (isSent ? 'sent' : 'received');
        
        let senderHtml = '';
        if(!isSent && currentChatType === 'group' && msg.sender_name) {
            senderHtml = '<strong>' + escapeHtml(msg.sender_name) + '</strong><br>';
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
        
        if(!message) {
            return;
        }
        
        if(!currentChatId) {
            alert('Please select a chat first');
            return;
        }
        
        if(!isConnected) {
            alert('Not connected to chat server. Please refresh the page.');
            return;
        }
        
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

    function connectSocket() {
        const statusSpan = document.getElementById('chatSubtitle');
        statusSpan.innerHTML = 'Connecting...';
        
        socket = io(SOCKET_URL, { 
            transports: ['websocket', 'polling'], 
            reconnection: true,
            reconnectionAttempts: 5
        });
        
        socket.on('connect', () => {
            isConnected = true;
            console.log('Socket connected:', socket.id);
            if(currentChatName) {
                statusSpan.innerHTML = (currentChatType === 'group' ? 'Group Chat • Connected ✓' : 'Private Chat • Connected ✓');
            } else {
                statusSpan.innerHTML = 'Connected ✓';
            }
            socket.emit('user-joined', userId);
        });
        
        socket.on('disconnect', () => {
            isConnected = false;
            console.log('Socket disconnected');
            statusSpan.innerHTML = 'Disconnected - reconnecting...';
        });
        
        socket.on('connect_error', (error) => {
            isConnected = false;
            console.error('Socket error:', error);
            statusSpan.innerHTML = 'Offline - check connection';
        });
        
        socket.on('new-message', (msg) => {
            console.log('New message received:', msg);
            if(currentChatId && ((currentChatType === 'group' && msg.group_id == currentChatId) ||
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
        if(!datetime) return 'Just now';
        try {
            const date = new Date(datetime);
            const now = new Date();
            const diff = now - date;
            if(diff < 60000) return 'Just now';
            if(diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
            if(diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
            return date.toLocaleDateString();
        } catch(e) {
            return 'Just now';
        }
    }

    function escapeHtml(text) {
        if(!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
<?php
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// FIX 1: Get userId from session directly in PHP — no async fetch needed
$user_id   = (int)$_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Chat - FoC Connect</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #E8F5E9; height: 100vh; display: flex; overflow: hidden; }

        /* Sidebar */
        .sidebar { width: 300px; background: white; border-right: 1px solid #ddd; display: flex; flex-direction: column; height: 100vh; flex-shrink: 0; }
        .sidebar-header { background: #8BC34A; padding: 20px; color: #1B5E20; }
        .sidebar-header h2 { font-size: 18px; }
        .sidebar-header p { font-size: 13px; margin-top: 4px; opacity: 0.85; }
        .chat-list { flex: 1; overflow-y: auto; }
        .chat-item { padding: 14px 15px; cursor: pointer; border-bottom: 1px solid #eee; display: flex; gap: 10px; align-items: center; transition: background 0.15s; }
        .chat-item:hover { background: #f5f5f5; }
        .chat-item.active { background: #E8F5E9; border-left: 3px solid #8BC34A; }
        .chat-avatar { width: 42px; height: 42px; background: #C5E1A5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .chat-name { font-weight: bold; font-size: 14px; color: #1B5E20; }
        .chat-preview { font-size: 12px; color: #888; margin-top: 2px; }
        .section-title { padding: 8px 15px; background: #f0f0f0; font-weight: bold; font-size: 11px; color: #666; letter-spacing: 0.5px; text-transform: uppercase; }

        /* Main chat */
        .chat-main { flex: 1; display: flex; flex-direction: column; background: white; min-width: 0; }
        .chat-header { padding: 14px 20px; border-bottom: 1px solid #ddd; background: white; display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .chat-header-info { flex: 1; }
        #chatTitle { font-size: 16px; font-weight: bold; color: #1B5E20; }
        #chatSubtitle { font-size: 11px; color: #888; margin-top: 3px; }

        /* Connection badge */
        .conn { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 2px 10px; border-radius: 20px; }
        .conn.connected    { background: #E8F5E9; color: #388E3C; }
        .conn.connecting   { background: #FFF9C4; color: #F57F17; }
        .conn.disconnected { background: #FFEBEE; color: #C62828; }
        .conn-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

        /* Messages */
        .messages { flex: 1; overflow-y: auto; padding: 16px 20px; background: #F5F8F0; display: flex; flex-direction: column; gap: 4px; }
        .empty { text-align: center; padding: 40px 20px; color: #aaa; font-size: 14px; line-height: 2; margin: auto; }
        .message { display: flex; margin-bottom: 2px; }
        .message.sent { justify-content: flex-end; }
        .message.received { justify-content: flex-start; }
        .bubble { max-width: 65%; padding: 9px 13px; border-radius: 18px; font-size: 14px; line-height: 1.5; word-wrap: break-word; }
        .message.sent .bubble { background: #DCF8C6; color: #1A2E28; border-bottom-right-radius: 4px; }
        .message.received .bubble { background: white; border: 1px solid #ddd; color: #333; border-bottom-left-radius: 4px; }
        .sender-name { font-size: 11px; font-weight: bold; color: #388E3C; margin-bottom: 3px; }
        .message-info { font-size: 10px; margin-top: 4px; color: #999; text-align: right; display: flex; justify-content: flex-end; align-items: center; gap: 3px; }
        .tick { font-size: 11px; }
        .tick.sent-t      { color: #aaa; }
        .tick.delivered-t { color: #aaa; }
        .tick.read-t      { color: #34B7F1; }

        /* Typing */
        .typing-bar { padding: 3px 20px; font-size: 12px; color: #aaa; font-style: italic; min-height: 20px; background: white; flex-shrink: 0; }

        /* Input */
        .input-area { padding: 12px 16px; border-top: 1px solid #ddd; display: flex; gap: 10px; align-items: center; background: white; flex-shrink: 0; }
        .input-area input { flex: 1; padding: 10px 16px; border: 1px solid #ddd; border-radius: 25px; font-size: 14px; outline: none; background: #F5F8F0; transition: border-color 0.2s; }
        .input-area input:focus { border-color: #8BC34A; background: white; }
        .input-area input:disabled { opacity: 0.5; cursor: not-allowed; }
        .send-btn { background: #8BC34A; color: white; border: none; padding: 10px 22px; border-radius: 25px; cursor: pointer; font-size: 14px; font-weight: 600; transition: background 0.2s; flex-shrink: 0; }
        .send-btn:hover { background: #7CB342; }
        .send-btn:disabled { background: #ccc; cursor: not-allowed; }

        /* Mobile */
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -310px; height: 100%; z-index: 100; transition: left 0.3s; width: 280px; }
            .sidebar.open { left: 0; box-shadow: 4px 0 15px rgba(0,0,0,0.15); }
            .menu-btn { display: flex !important; position: fixed; bottom: 20px; right: 20px; background: #8BC34A; color: white; border: none; width: 50px; height: 50px; border-radius: 50%; font-size: 22px; cursor: pointer; z-index: 99; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
            .bubble { max-width: 80%; }
        }
        @media (min-width: 769px) { .menu-btn { display: none !important; } }
        .menu-btn { display: none; }
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.35); z-index: 99; }
        .overlay.show { display: block; }
        .wake-btn { background: #FF9800; color: white; border: none; padding: 7px 16px; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: 600; margin-left: 6px; }
    </style>
</head>
<body>

<div class="overlay" id="overlay" onclick="closeOverlay()"></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>💬 Chats</h2>
        <p><?php echo $user_name; ?></p>
    </div>
    <div class="section-title">📁 Groups</div>
    <div id="groupsList" class="chat-list">
        <div class="empty" style="padding:16px;font-size:13px;">Loading...</div>
    </div>
    <div class="section-title">👤 Contacts</div>
    <div id="contactsList" class="chat-list">
        <div class="empty" style="padding:16px;font-size:13px;">Loading...</div>
    </div>
</div>

<!-- MAIN CHAT -->
<div class="chat-main">
    <div class="chat-header">
        <div class="chat-header-info">
            <div id="chatTitle">💬 FoC Connect Chat</div>
            <div id="chatSubtitle">
                <span id="connBadge" class="conn connecting">
                    <span class="conn-dot"></span> Connecting...
                </span>
            </div>
        </div>
    </div>

    <div class="messages" id="messages">
        <div class="empty">💬 Select a conversation to start messaging</div>
    </div>

    <div class="typing-bar" id="typingBar"></div>

    <div class="input-area">
        <input type="text" id="messageInput" placeholder="Select a chat first..." disabled
               onkeypress="if(event.key==='Enter') sendMessage()"
               oninput="handleTyping()">
        <button class="send-btn" id="sendBtn" onclick="sendMessage()" disabled>Send ➤</button>
    </div>
</div>

<button class="menu-btn" id="menuBtn" onclick="toggleSidebar()">💬</button>

<script>
    const SOCKET_URL = 'https://foc-connect-websocket.onrender.com';

    // FIX 1: userId set directly from PHP session — not async
    // This means isSent check is ALWAYS correct from the start
    const USER_ID = <?php echo $user_id; ?>;

    let socket          = null;
    let isConnected     = false;
    let isWaking        = false;
    let currentChatId   = null;
    let currentChatType = null;
    let currentChatName = null;
    let currentItem     = null;
    let typingTimer     = null;

    // Track displayed message IDs to prevent duplicates
    // FIX 2: This is the core fix — prevents messages from disappearing
    const displayedIds = new Set();

    const connBadge  = document.getElementById('connBadge');
    const messagesEl = document.getElementById('messages');
    const msgInput   = document.getElementById('messageInput');
    const sendBtn    = document.getElementById('sendBtn');
    const typingBar  = document.getElementById('typingBar');

    // ══════════════════════════════
    // LOAD CHAT LIST
    // ══════════════════════════════
    function loadChats() {
        // Groups
        fetch('api-data.php?action=groups')
            .then(r => r.json())
            .then(data => {
                const el = document.getElementById('groupsList');
                if (data.success && data.data && data.data.length > 0) {
                    el.innerHTML = '';
                    data.data.forEach(g => {
                        const div = document.createElement('div');
                        div.className = 'chat-item';
                        div.onclick = () => selectChat(g.id, 'group', g.name, div);
                        div.innerHTML = `<div class="chat-avatar">👥</div>
                            <div><div class="chat-name">${esc(g.name)}</div>
                            <div class="chat-preview">Group chat</div></div>`;
                        el.appendChild(div);
                    });
                } else {
                    el.innerHTML = '<div class="empty" style="padding:16px;font-size:13px;">No groups yet</div>';
                }
            })
            .catch(() => {
                document.getElementById('groupsList').innerHTML =
                    '<div class="empty" style="padding:16px;font-size:13px;">⚠️ Failed to load</div>';
            });

        // Contacts
        fetch('api-data.php?action=contacts')
            .then(r => r.json())
            .then(data => {
                const el = document.getElementById('contactsList');
                if (data.success && data.data && data.data.length > 0) {
                    el.innerHTML = '';
                    data.data.forEach(u => {
                        const div = document.createElement('div');
                        div.className = 'chat-item';
                        div.onclick = () => selectChat(u.id, 'user', u.name, div);
                        div.innerHTML = `<div class="chat-avatar">👤</div>
                            <div><div class="chat-name">${esc(u.name)}</div>
                            <div class="chat-preview">${esc(u.matric_number || '')}</div></div>`;
                        el.appendChild(div);
                    });
                } else {
                    el.innerHTML = '<div class="empty" style="padding:16px;font-size:13px;">No contacts yet</div>';
                }
            })
            .catch(() => {
                document.getElementById('contactsList').innerHTML =
                    '<div class="empty" style="padding:16px;font-size:13px;">⚠️ Failed to load</div>';
            });
    }

    // ══════════════════════════════
    // SELECT CHAT
    // ══════════════════════════════
    function selectChat(id, type, name, el) {
        currentChatId   = id;
        currentChatType = type;
        currentChatName = name;

        // Highlight active item
        if (currentItem) currentItem.classList.remove('active');
        if (el) el.classList.add('active');
        currentItem = el;

        document.getElementById('chatTitle').textContent   = name;
        document.getElementById('chatSubtitle').innerHTML  = `<span id="connBadge" class="conn ${isConnected ? 'connected' : 'connecting'}"><span class="conn-dot"></span> ${isConnected ? 'Connected ✓' : 'Connecting...'}</span>`;

        msgInput.disabled       = false;
        sendBtn.disabled        = false;
        msgInput.placeholder    = 'Type a message...';
        typingBar.textContent   = '';

        if (type === 'group' && socket && isConnected) {
            socket.emit('join-group', id);
        }

        // FIX 3: Clear displayedIds when switching chats
        displayedIds.clear();
        loadMessages(type, id);

        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('overlay').classList.remove('show');
        }

        msgInput.focus();
    }

    // ══════════════════════════════
    // LOAD MESSAGES
    // FIX 4: Load from api-data.php (InfinityFree) not from Render
    // This avoids the 403 from external requests on page load
    // ══════════════════════════════
    function loadMessages(type, id) {
        messagesEl.innerHTML = '<div class="empty">⏳ Loading messages...</div>';
        displayedIds.clear();

        fetch(`api-data.php?action=messages&type=${type}&id=${id}`)
            .then(r => { if (!r.ok) throw r.status; return r.json(); })
            .then(data => {
                messagesEl.innerHTML = '';
                if (data.success && data.data && data.data.length > 0) {
                    data.data.forEach(msg => displayMessage(msg));
                    scrollBottom();
                    // Mark as read
                    if (socket && isConnected) {
                        socket.emit('mark-chat-read', type === 'group'
                            ? { user_id: USER_ID, group_id: id }
                            : { user_id: USER_ID, chat_partner_id: id });
                    }
                } else {
                    messagesEl.innerHTML = '<div class="empty">💬 No messages yet. Send the first one!</div>';
                }
            })
            .catch(() => {
                messagesEl.innerHTML = `<div class="empty">⚠️ Could not load messages.<br>
                    <button onclick="loadMessages('${type}',${id})" style="margin-top:10px;padding:7px 18px;background:#8BC34A;color:white;border:none;border-radius:20px;cursor:pointer;font-size:13px;">Retry</button></div>`;
            });
    }

    // ══════════════════════════════
    // DISPLAY MESSAGE
    // FIX 5: Check displayedIds before adding — prevents duplicates
    // and prevents messages disappearing when new one arrives
    // ══════════════════════════════
    function displayMessage(msg) {
        // Skip if already displayed (prevents duplicates)
        if (msg.id && displayedIds.has(msg.id)) return;
        if (msg.id) displayedIds.add(msg.id);

        const isSent = msg.from_user_id == USER_ID;
        const div    = document.createElement('div');
        if (msg.id) div.id = 'msg-' + msg.id;
        div.className = 'message ' + (isSent ? 'sent' : 'received');

        // Sender name (group chats only, for received messages)
        let senderHtml = '';
        if (!isSent && currentChatType === 'group' && msg.sender_name) {
            senderHtml = `<div class="sender-name">${esc(msg.sender_name)}</div>`;
        }

        // Tick marks
        let tick = '';
        if (isSent) {
            const cls  = msg.status === 'read' ? 'read-t' : (msg.status === 'delivered' ? 'delivered-t' : 'sent-t');
            const mark = msg.status === 'sent' ? '✓' : '✓✓';
            tick = `<span class="tick ${cls}">${mark}</span>`;
        }

        div.innerHTML = `<div class="bubble">
            ${senderHtml}
            ${esc(msg.message || '')}
            <div class="message-info">${fmt(msg.sent_at)} ${tick}</div>
        </div>`;

        messagesEl.appendChild(div);
        scrollBottom();
    }

    // Update tick marks when status changes
    function updateTick(id, status) {
        const el = document.getElementById('msg-' + id);
        if (!el) return;
        const t = el.querySelector('.tick');
        if (!t) return;
        if (status === 'read')           { t.className = 'tick read-t';      t.textContent = '✓✓'; }
        else if (status === 'delivered') { t.className = 'tick delivered-t'; t.textContent = '✓✓'; }
    }

    // ══════════════════════════════
    // SEND MESSAGE
    // ══════════════════════════════
    function sendMessage() {
        const text = msgInput.value.trim();
        if (!text || !currentChatId) return;
        if (!isConnected || !socket) {
            alert('Not connected to chat server. Please wait or click Wake Server.');
            return;
        }

        const data = { from_user_id: USER_ID, message: text };
        if (currentChatType === 'group') data.group_id   = currentChatId;
        else                             data.to_user_id = currentChatId;

        socket.emit('send-message', data);
        msgInput.value = '';

        // Stop typing indicator
        if (socket && isConnected) {
            socket.emit('typing', {
                from_user_id: USER_ID,
                to_user_id:   currentChatType === 'user'  ? currentChatId : null,
                group_id:     currentChatType === 'group' ? currentChatId : null,
                isTyping: false
            });
        }
    }

    // ══════════════════════════════
    // TYPING INDICATOR
    // ══════════════════════════════
    function handleTyping() {
        if (!socket || !isConnected || !currentChatId) return;
        socket.emit('typing', {
            from_user_id: USER_ID,
            to_user_id:   currentChatType === 'user'  ? currentChatId : null,
            group_id:     currentChatType === 'group' ? currentChatId : null,
            isTyping: true
        });
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            socket.emit('typing', {
                from_user_id: USER_ID,
                to_user_id:   currentChatType === 'user'  ? currentChatId : null,
                group_id:     currentChatType === 'group' ? currentChatId : null,
                isTyping: false
            });
        }, 2000);
    }

    // ══════════════════════════════
    // SOCKET CONNECTION
    // ══════════════════════════════
    function setStatus(cls, html) {
        connBadge.className = 'conn ' + cls;
        connBadge.innerHTML = '<span class="conn-dot"></span> ' + html;
    }

    function wakeServer() {
        if (isWaking) return;
        isWaking = true;
        setStatus('connecting', 'Waking server...');
        fetch(SOCKET_URL + '/health', { mode: 'cors' })
            .then(r => { if (r.ok) { setStatus('connecting', 'Connecting...'); connectSocket(); } else throw 0; })
            .catch(() => { setTimeout(() => { isWaking = false; connectSocket(); }, 5000); });
    }

    function connectSocket() {
        setStatus('connecting', 'Connecting...');
        if (socket) { try { socket.disconnect(); } catch(e){} }

        socket = io(SOCKET_URL, {
            reconnection: true,
            reconnectionAttempts: 15,
            reconnectionDelay: 3000,
            reconnectionDelayMax: 10000,
            timeout: 30000,
            transports: ['websocket', 'polling']
        });

        socket.on('connect', () => {
            isConnected = true; isWaking = false;
            setStatus('connected', 'Connected ✓');
            socket.emit('user-joined', USER_ID);
            // Reload messages if a chat is already selected
            if (currentChatId) loadMessages(currentChatType, currentChatId);
        });

        socket.on('disconnect', reason => {
            isConnected = false;
            setStatus('disconnected', reason === 'io server disconnect' ? 'Server offline' : 'Reconnecting...');
        });

        socket.on('connect_error', () => {
            isConnected = false;
            connBadge.className = 'conn disconnected';
            connBadge.innerHTML = '<span class="conn-dot"></span> Offline <button class="wake-btn" onclick="wakeServer()">🔌 Wake</button>';
        });

        // FIX 6: new-message handler uses displayedIds to prevent duplicates
        socket.on('new-message', msg => {
            if (!currentChatId) return;

            const inGroup = currentChatType === 'group' && msg.group_id == currentChatId;
            const inDM    = currentChatType === 'user'  &&
                (msg.from_user_id == currentChatId || msg.to_user_id == currentChatId || msg.from_user_id == USER_ID);

            if (inGroup || inDM) {
                displayMessage(msg); // displayedIds check inside prevents duplicate
                if (msg.from_user_id != USER_ID && socket && isConnected) {
                    socket.emit('mark-message-read', {
                        message_id:  msg.id,
                        user_id:     USER_ID,
                        from_user_id: msg.from_user_id
                    });
                }
            }
        });

        socket.on('message-status-update', d => updateTick(d.message_id, d.status));
        socket.on('message-read',          d => updateTick(d.message_id, 'read'));

        socket.on('user-typing', d => {
            if (d.user_id != USER_ID) {
                typingBar.textContent = d.isTyping ? (currentChatName + ' is typing...') : '';
            }
        });
    }

    // ══════════════════════════════
    // HELPERS
    // ══════════════════════════════
    function scrollBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function fmt(dt) {
        if (!dt) return 'Just now';
        const d = new Date(dt), now = new Date(), diff = now - d;
        if (diff < 60000)    return 'Just now';
        if (diff < 3600000)  return Math.floor(diff / 60000) + 'm ago';
        if (diff < 86400000) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }

    function esc(t) {
        if (!t) return '';
        const d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    // ══════════════════════════════
    // MOBILE NAV
    // ══════════════════════════════
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('show');
    }
    function closeOverlay() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('overlay').classList.remove('show');
    }

    // ══════════════════════════════
    // KEEP-ALIVE (prevents Render sleeping)
    // ══════════════════════════════
    setInterval(() => {
        if (isConnected && socket) socket.emit('ping');
        else if (!isWaking) fetch(SOCKET_URL + '/health', { mode: 'cors' }).catch(() => {});
    }, 40000);

    // ══════════════════════════════
    // BOOT — no async fetch for userId needed anymore
    // ══════════════════════════════
    connectSocket();
    loadChats();
</script>
</body>
</html>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }

    // Initialize everything
    console.log('Page loaded, user ID:', userId);
    connectSocket();
    loadChats();

    // Enter key to send message
    document.getElementById('messageInput').addEventListener('keypress', function(e) {
        if(e.key === 'Enter') {
            sendMessage();
        }
    });
</script>
</body>
</html>
