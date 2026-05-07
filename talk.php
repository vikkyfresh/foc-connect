<script>
const SOCKET_URL = 'https://foc-connect-websocket.onrender.com';
let userId = <?php echo (int)$user_id; ?>;
let socket = null;
let isConnected = false;
let currentChatId = null;
let currentChatType = null;
let currentChatName = '';

// Check if user is valid
if (!userId || userId === 0) {
    alert('Error: Not logged in properly. Please logout and login again.');
    window.location.href = 'login.php';
}

function loadChats() {
    console.log('Loading chats for user:', userId);
    
    // ✅ FIXED: Added user_id parameter
    fetch('api-data.php?action=groups&user_id=' + userId)
        .then(res => res.json())
        .then(data => {
            console.log('Groups response:', data);
            const container = document.getElementById('groupsList');
            if (data.success && data.data && data.data.length > 0) {
                container.innerHTML = '';
                data.data.forEach(group => {
                    container.innerHTML += '<div class="chat-item" onclick="selectChat(' + group.id + ', \'group\', \'' + escapeHtml(group.name) + '\')">' +
                        '<div class="chat-avatar">👥</div>' +
                        '<div class="chat-info">' +
                        '<div class="chat-name">' + escapeHtml(group.name) + '</div>' +
                        '<div class="chat-preview">Group chat</div>' +
                        '</div>' +
                        '</div>';
                });
            } else {
                container.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;">No groups yet</div>';
            }
        })
        .catch(err => console.error('Groups error:', err));

    // ✅ FIXED: Added user_id parameter
    fetch('api-data.php?action=contacts&user_id=' + userId)
        .then(res => res.json())
        .then(data => {
            console.log('Contacts response:', data);
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
    
    // ✅ FIXED: Added user_id parameter
    fetch('api-data.php?action=messages&type=' + type + '&id=' + id + '&user_id=' + userId)
        .then(res => res.json())
        .then(data => {
            console.log('Messages response:', data);
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
    
    console.log('Sending message:', data);
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
        statusSpan.innerHTML = 'Connection failed';
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
