<?php
session_start();

// ============================================
// CONNECT TO CLEVER CLOUD DATABASE
// (Same as Node.js chat server)
// ============================================
$cc_host = 'btpaf0bjadqhld71gzms-mysql.services.clever-cloud.com';
$cc_user = 'usaypg7enbwrjvnm';
$cc_pass = '0jjwQuQBJ48iRZp6EynT';
$cc_name = 'btpaf0bjadqhld71gzms';
$cc_port = 3306;

$conn = mysqli_connect($cc_host, $cc_user, $cc_pass, $cc_name, $cc_port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Get user's groups from Clever Cloud
$groups_query = "SELECT cg.* FROM chat_groups cg 
                 JOIN group_members gm ON cg.id = gm.group_id 
                 WHERE gm.user_id = $user_id 
                 ORDER BY cg.name ASC";
$groups_result = mysqli_query($conn, $groups_query);
$groups = [];
while($row = mysqli_fetch_assoc($groups_result)) {
    $groups[] = $row;
}

// Get user info from Clever Cloud
$user_query = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);

// Get other students for DMs (from Clever Cloud)
$students_query = "SELECT id, name, matric_number FROM users 
                   WHERE department_id = (SELECT department_id FROM users WHERE id = $user_id) 
                   AND id != $user_id AND role = 'student' 
                   ORDER BY name ASC LIMIT 20";
$students = mysqli_query($conn, $students_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <title>Chat - FoC Connect</title>
    <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/emoji-picker-element@latest/index.css">
    <script type="module">
        import { Picker } from 'https://cdn.jsdelivr.net/npm/emoji-picker-element@latest/index.js';
        window.EmojiPicker = { Picker };
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #E8F5E9; height: 100vh; overflow: hidden; position: fixed; width: 100%; }
        .chat-container { display: flex; height: 100%; width: 100%; background-color: #F1F8E9; background-image: repeating-linear-gradient(45deg, rgba(139,195,74,0.08) 0px, rgba(139,195,74,0.08) 2px, transparent 2px, transparent 60px); }
        .chat-sidebar { width: 380px; background: #FFFFFF; display: flex; flex-direction: column; height: 100%; border-right: 1px solid #E0E8E0; transition: transform 0.3s ease; z-index: 100; box-shadow: 2px 0 8px rgba(0,0,0,0.02); }
        .sidebar-header { background: #8BC34A; color: #1B5E20; padding: 20px 20px 16px; }
        .sidebar-header h2 { font-size: 22px; font-weight: 600; margin-bottom: 4px; }
        .sidebar-header p { font-size: 13px; opacity: 0.85; color: #2E5C1E; }
        .tabs { display: flex; background: #FFFFFF; border-bottom: 1px solid #E8EDE8; padding: 0 16px; }
        .tab { flex: 1; text-align: center; padding: 14px 0; cursor: pointer; font-size: 14px; font-weight: 600; color: #8A9B8A; border-bottom: 2px solid transparent; transition: all 0.2s; }
        .tab.active { color: #8BC34A; border-bottom-color: #8BC34A; }
        .search-box { padding: 10px 16px; background: #FFFFFF; }
        .search-box input { width: 100%; padding: 10px 16px; background: #F5F8F0; border: none; border-radius: 30px; font-size: 14px; outline: none; }
        .chat-list-container { flex: 1; overflow-y: auto; background: #FFFFFF; }
        .chat-section { padding: 12px 16px; font-weight: 600; color: #8BC34A; font-size: 12px; background: #F9FBF7; border-bottom: 1px solid #F0F4EC; }
        .chat-list { display: flex; flex-direction: column; }
        .chat-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; cursor: pointer; border-bottom: 1px solid #F0F4EC; transition: background 0.2s; }
        .chat-item:hover { background: #F8FBF4; }
        .chat-avatar { width: 50px; height: 50px; background: #C5E1A5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; color: #558B2F; }
        .chat-info { flex: 1; min-width: 0; }
        .chat-name { font-weight: 600; font-size: 15px; color: #2E3B2E; }
        .chat-preview { font-size: 13px; color: #9AAB9A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .empty-groups { padding: 30px 20px; text-align: center; color: #9AAB9A; font-size: 13px; }
        .chat-main { flex: 1; display: flex; flex-direction: column; background: transparent; height: 100%; }
        .chat-header { padding: 16px 20px; background: rgba(255,255,255,0.96); backdrop-filter: blur(8px); border-bottom: 1px solid #E8EDE8; display: flex; align-items: center; gap: 12px; }
        .chat-header h3 { font-size: 18px; color: #2E3B2E; }
        .chat-header p { font-size: 12px; color: #8BC34A; }
        .status-badge { font-size: 10px; padding: 3px 8px; border-radius: 20px; display: inline-block; margin-left: 8px; }
        .status-badge.connected { background: #8BC34A; color: white; }
        .status-badge.disconnected { background: #EF9A9A; color: white; }
        .messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 8px; background-image: repeating-linear-gradient(45deg, rgba(139,195,74,0.05) 0px, rgba(139,195,74,0.05) 2px, transparent 2px, transparent 60px); }
        .message { display: flex; margin-bottom: 8px; }
        .message.sent { justify-content: flex-end; }
        .message.received { justify-content: flex-start; }
        .bubble { max-width: 70%; padding: 10px 14px; border-radius: 20px; font-size: 14px; word-wrap: break-word; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .message.sent .bubble { background: #DCF8C6; color: #2E3B2E; border-bottom-right-radius: 4px; }
        .message.received .bubble { background: white; color: #2E3B2E; border: 1px solid #E0E8E0; border-bottom-left-radius: 4px; }
        .message-info { font-size: 10px; margin-top: 4px; color: #9AAB9A; display: flex; gap: 4px; justify-content: flex-end; }
        .tick { font-size: 11px; margin-left: 4px; }
        .tick-sent { color: #9AAB9A; }
        .tick-delivered { color: #9AAB9A; }
        .tick-read { color: #8BC34A; }
        .input-area { padding: 12px 16px; background: rgba(255,255,255,0.96); backdrop-filter: blur(8px); border-top: 1px solid #E8EDE8; display: flex; gap: 8px; align-items: center; position: relative; }
        .input-area input { flex: 1; padding: 12px 16px; background: #F5F8F0; border: none; border-radius: 30px; outline: none; font-size: 15px; }
        .emoji-btn { background: #F5F8F0; border: none; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; font-size: 22px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .send-btn { background: #8BC34A; color: white; border: none; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .send-btn:disabled { background: #C8DCC8; cursor: not-allowed; }
        .emoji-picker-container { position: absolute; bottom: 70px; left: 20px; z-index: 1000; display: none; box-shadow: 0 4px 20px rgba(0,0,0,0.15); border-radius: 16px; overflow: hidden; }
        .emoji-picker-container.show { display: block; }
        .typing-indicator { font-size: 12px; color: #8BC34A; padding: 6px 16px; font-style: italic; min-height: 32px; background: rgba(255,255,255,0.85); }
        .empty-chat { text-align: center; padding: 60px 20px; color: #8BC34A; font-size: 15px; }
        .back-btn { display: none; background: none; border: none; font-size: 24px; cursor: pointer; }
        .mobile-hamburger { display: none; position: fixed; bottom: 20px; right: 20px; background: #8BC34A; color: white; border: none; width: 52px; height: 52px; border-radius: 50%; font-size: 24px; cursor: pointer; z-index: 98; box-shadow: 0 3px 12px rgba(139,195,74,0.3); }
        @media (max-width: 768px) {
            .chat-sidebar { position: fixed; left: -380px; top: 0; bottom: 0; z-index: 200; width: 85%; max-width: 320px; }
            .chat-sidebar.open { left: 0; }
            .back-btn { display: block; }
            .mobile-hamburger { display: flex; align-items: center; justify-content: center; }
            .overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(46,59,46,0.5); z-index: 199; display: none; }
            .overlay.show { display: block; }
        }
    </style>
</head>
<body>

<div class="chat-container">
    <div class="chat-sidebar" id="chatSidebar">
        <div class="sidebar-header">
            <h2>💬 Chats</h2>
            <p><?php echo htmlspecialchars($user_name); ?></p>
        </div>
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
                <div class="chat-section">📁 GROUPS</div>
                <?php if(count($groups) > 0): ?>
                    <?php foreach($groups as $group): ?>
                    <div class="chat-item" data-id="<?php echo $group['id']; ?>" data-type="group" data-name="<?php echo htmlspecialchars($group['name']); ?>">
                        <div class="chat-avatar">👥</div>
                        <div class="chat-info">
                            <div class="chat-name"><?php echo htmlspecialchars($group['name']); ?></div>
                            <div class="chat-preview">Group conversation</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-groups">📭 No groups yet. Contact your HOD to be added.</div>
                <?php endif; ?>
                <div class="chat-section">👤 CONTACTS</div>
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
                    <div class="empty-groups">👤 No other students in your department</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <div class="chat-main">
        <div class="chat-header">
            <button class="back-btn" onclick="toggleSidebar()">☰</button>
            <div>
                <h3 id="chatTitle">💬 FoC Connect</h3>
                <p id="chatSubtitle">Select a conversation <span id="connectionStatus" class="status-badge disconnected">Offline</span></p>
            </div>
        </div>

        <div class="messages" id="messages">
            <div class="empty-chat">💬 Select a chat from the list to start messaging</div>
        </div>

        <div class="typing-indicator" id="typingIndicator"></div>

        <div class="input-area">
            <button class="emoji-btn" id="emojiBtn" onclick="toggleEmojiPicker()">😊</button>
            <input type="text" id="messageInput" placeholder="Type a message..." disabled>
            <button class="send-btn" id="sendBtn" onclick="sendMessage()" disabled>📤</button>
        </div>
        <div class="emoji-picker-container" id="emojiPickerContainer"></div>
    </div>
</div>

<button class="mobile-hamburger" onclick="toggleSidebar()">💬</button>

<script>
    const SOCKET_URL = 'https://foc-connect-websocket.onrender.com';
    let userId = <?php echo $user_id; ?>;
    let socket = null;
    let isConnected = false;
    let currentChatId = null;
    let currentChatType = null;
    let currentChatName = null;
    let currentItem = null;
    let emojiPicker = null;

    const statusSpan = document.getElementById('connectionStatus');
    const messagesDiv = document.getElementById('messages');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const chatTitle = document.getElementById('chatTitle');
    const chatSubtitle = document.getElementById('chatSubtitle');
    const typingIndicator = document.getElementById('typingIndicator');

    // Initialize Emoji Picker
    function initEmojiPicker() {
        if (window.EmojiPicker && !emojiPicker) {
            emojiPicker = new window.EmojiPicker.Picker({
                dataSource: 'https://cdn.jsdelivr.net/npm/emoji-picker-element-data@^1.0.0/emojibase/data.json',
                locale: 'en'
            });
            emojiPicker.addEventListener('emoji-click', event => {
                messageInput.value += event.detail.unicode;
                messageInput.focus();
                document.getElementById('emojiPickerContainer').classList.remove('show');
            });
            document.getElementById('emojiPickerContainer').appendChild(emojiPicker);
        }
    }

    function toggleEmojiPicker() {
        if (!emojiPicker) initEmojiPicker();
        document.getElementById('emojiPickerContainer').classList.toggle('show');
    }

    document.addEventListener('click', function(e) {
        const container = document.getElementById('emojiPickerContainer');
        const emojiBtn = document.getElementById('emojiBtn');
        if (container && !container.contains(e.target) && !emojiBtn.contains(e.target)) {
            container.classList.remove('show');
        }
    });

    // Tab switching
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            filterChats(this.dataset.tab);
        });
    });

    function filterChats(tabName) {
        document.querySelectorAll('.chat-item').forEach(item => {
            if(tabName === 'chats') item.style.display = 'flex';
            else if(tabName === 'groups') item.style.display = item.dataset.type === 'group' ? 'flex' : 'none';
            else if(tabName === 'favorites') item.style.display = localStorage.getItem('fav_' + item.dataset.id) === 'true' ? 'flex' : 'none';
            else if(tabName === 'archive') item.style.display = localStorage.getItem('archived_' + item.dataset.id) === 'true' ? 'flex' : 'none';
        });
    }

    // Right-click / long press for favorites
    document.querySelectorAll('.chat-item').forEach(item => {
        item.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const isFav = localStorage.getItem('fav_' + id) === 'true';
            if(isFav) {
                localStorage.removeItem('fav_' + id);
                alert('❌ Removed from Favorites');
            } else {
                localStorage.setItem('fav_' + id, 'true');
                alert('⭐ Added to Favorites');
            }
            filterChats(document.querySelector('.tab.active').dataset.tab);
        });
    });

    // Search
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.chat-item').forEach(item => {
            const name = item.querySelector('.chat-name')?.innerText.toLowerCase() || '';
            item.style.display = name.includes(term) ? 'flex' : 'none';
        });
    });

    function connectSocket() {
        statusSpan.className = 'status-badge disconnected';
        statusSpan.innerHTML = 'Connecting...';

        socket = io(SOCKET_URL, { transports: ['websocket', 'polling'], reconnection: true, reconnectionAttempts: 10, reconnectionDelay: 2000 });

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
            statusSpan.innerHTML = 'Offline <button onclick="wakeServer()" style="background:#8BC34A; border:none; padding:2px 8px; border-radius:12px;">Wake</button>';
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
        fetch(SOCKET_URL + '/health').then(() => { if(socket) socket.disconnect(); setTimeout(connectSocket, 1000); }).catch(() => {});
    }

    // ============================================
    // LOAD MESSAGES - Using api-data.php
    // ============================================
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

        if(type === 'group' && socket && isConnected) socket.emit('join-group', id);

        loadMessages(type, id);
        closeSidebar();
    }

    function loadMessages(type, id) {
        messagesDiv.innerHTML = '<div class="empty-chat">🍃 Loading messages...</div>';

        fetch(`api-data.php?action=messages&type=${type}&id=${id}`)
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
                } else {
                    messagesDiv.innerHTML = '<div class="empty-chat">⚠️ ' + (data.error || 'No messages found') + '</div>';
                }
            })
            .catch(err => {
                console.error('Load error:', err);
                messagesDiv.innerHTML = '<div class="empty-chat">⚠️ Error loading messages. Make sure the server is running.</div>';
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
        if(!isConnected) { alert('Not connected to chat server.'); return; }

        const data = { from_user_id: userId, message: message };
        if(currentChatType === 'group') data.group_id = currentChatId;
        else data.to_user_id = currentChatId;

        socket.emit('send-message', data);
        messageInput.value = '';
    }

    function scrollToBottom() { messagesDiv.scrollTop = messagesDiv.scrollHeight; }
    function formatTime(datetime) {
        if(!datetime) return 'Just now';
        const date = new Date(datetime);
        const now = new Date();
        const diff = now - date;
        if(diff < 60000) return 'Just now';
        if(diff < 3600000) return Math.floor(diff / 60000) + 'm';
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
    function toggleSidebar() { document.getElementById('chatSidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('show'); }
    function closeSidebar() { document.getElementById('chatSidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('show'); }

    document.querySelectorAll('.chat-item').forEach(item => { item.addEventListener('click', () => selectChat(item)); });
    messageInput.addEventListener('keypress', (e) => { if(e.key === 'Enter') sendMessage(); });
    setInterval(() => { if(isConnected && socket) socket.emit('ping'); }, 40000);

    connectSocket();
</script>
</body>
</html>
