const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const mysql = require('mysql2');
const multer = require('multer');
const path = require('path');
const fs = require('fs');

const app = express();
const server = http.createServer(app);

// ============================================
// FIX 1: onlineUsers declared FIRST before everything
// ============================================
const onlineUsers = {};

const io = socketIo(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"],
        credentials: false
    },
    transports: ['websocket', 'polling'],
    pingTimeout: 60000,
    pingInterval: 25000
});

// ============================================
// FIX 2: CORS middleware for all HTTP routes
// ============================================
app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
    if (req.method === 'OPTIONS') return res.sendStatus(200);
    next();
});

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// ============================================
// FILE UPLOAD CONFIGURATION
// ============================================
const uploadDir = './uploads';
const voiceDir = './uploads/voice';
const imageDir = './uploads/images';

if (!fs.existsSync(uploadDir)) fs.mkdirSync(uploadDir, { recursive: true });
if (!fs.existsSync(voiceDir)) fs.mkdirSync(voiceDir, { recursive: true });
if (!fs.existsSync(imageDir)) fs.mkdirSync(imageDir, { recursive: true });

const storage = multer.diskStorage({
    destination: (req, file, cb) => {
        if (file.mimetype.startsWith('audio/')) cb(null, voiceDir);
        else if (file.mimetype.startsWith('image/')) cb(null, imageDir);
        else cb(null, uploadDir);
    },
    filename: (req, file, cb) => {
        const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
        cb(null, uniqueSuffix + path.extname(file.originalname));
    }
});

const fileFilter = (req, file, cb) => {
    const allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain', 'text/csv',
        'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm',
        'video/mp4', 'video/webm'
    ];
    if (allowedTypes.includes(file.mimetype)) cb(null, true);
    else cb(new Error('File type not allowed'), false);
};

const upload = multer({ storage, fileFilter, limits: { fileSize: 50 * 1024 * 1024 } });

// ============================================
// DATABASE CONNECTION
// ============================================
const db = mysql.createConnection({
    host: 'sql200.infinityfree.com',
    user: 'if0_41808042',
    password: 'f86pbwvj',
    database: 'if0_41808042_foc_connect',
    connectTimeout: 60000
});

db.connect((err) => {
    if (err) console.error('❌ MySQL connection failed:', err.message);
    else console.log('✅ MySQL connected successfully');
});

setInterval(() => {
    db.query('SELECT 1', (err) => {
        if (err) console.log('⚠️ MySQL keepalive error:', err.message);
    });
}, 30000);

// ============================================
// HTTP ROUTES
// ============================================

app.get('/', (req, res) => {
    res.json({
        status: 'FoC Connect Chat Server is running ✅',
        onlineUsers: Object.keys(onlineUsers).length,
        timestamp: new Date().toISOString()
    });
});

app.get('/health', (req, res) => {
    res.status(200).json({ status: 'ok', timestamp: new Date().toISOString() });
});

app.post('/api/upload', upload.single('file'), (req, res) => {
    if (!req.file) return res.status(400).json({ error: 'No file uploaded' });
    let fileUrl = '';
    if (req.file.mimetype.startsWith('audio/')) fileUrl = `/uploads/voice/${req.file.filename}`;
    else if (req.file.mimetype.startsWith('image/')) fileUrl = `/uploads/images/${req.file.filename}`;
    else fileUrl = `/uploads/${req.file.filename}`;
    res.json({ success: true, file_url: fileUrl, file_name: req.file.originalname, file_type: req.file.mimetype, file_size: req.file.size });
});

app.use('/uploads', express.static(path.join(__dirname, 'uploads')));

app.get('/api/messages/:type/:id', (req, res) => {
    const { type, id } = req.params;
    if (type === 'group') {
        db.query(
            `SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.from_user_id = u.id WHERE m.group_id = ? ORDER BY m.sent_at ASC LIMIT 100`,
            [id], (err, rows) => {
                if (err) return res.status(500).json({ error: err.message });
                res.json(rows || []);
            }
        );
    } else {
        db.query(
            `SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.from_user_id = u.id WHERE ((m.from_user_id = ? OR m.to_user_id = ?) AND m.group_id IS NULL) ORDER BY m.sent_at ASC LIMIT 100`,
            [id, id], (err, rows) => {
                if (err) return res.status(500).json({ error: err.message });
                res.json(rows || []);
            }
        );
    }
});

app.get('/api/user-groups/:userId', (req, res) => {
    db.query(
        `SELECT cg.* FROM chat_groups cg JOIN group_members gm ON cg.id = gm.group_id WHERE gm.user_id = ? ORDER BY cg.name ASC`,
        [req.params.userId], (err, rows) => {
            if (err) return res.status(500).json({ error: err.message });
            res.json(rows || []);
        }
    );
});

// ============================================
// SOCKET.IO — REAL-TIME CHAT EVENTS
// ============================================
io.on('connection', (socket) => {
    console.log('🟢 Socket connected:', socket.id);

    socket.on('user-joined', (userId) => {
        socket.userId = userId;
        onlineUsers[userId] = socket.id;
        console.log(`👤 User ${userId} online — total online: ${Object.keys(onlineUsers).length}`);
        io.emit('online-users', Object.keys(onlineUsers));
    });

    socket.on('send-message', (data) => {
        const { from_user_id, to_user_id, group_id, message, file_url, file_name, file_type, file_size } = data;
        db.query(
            `INSERT INTO messages (from_user_id, to_user_id, group_id, message, file_url, file_name, file_type, file_size, sent_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'sent')`,
            [from_user_id, to_user_id || null, group_id || null, message || '', file_url || null, file_name || null, file_type || null, file_size || null],
            (err, result) => {
                if (err) return console.error('❌ Error saving message:', err);
                const messageId = result.insertId;
                db.query(
                    `SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.from_user_id = u.id WHERE m.id = ?`,
                    [messageId],
                    (err, rows) => {
                        if (err || !rows[0]) return;
                        const newMessage = rows[0];
                        const senderSocketId = onlineUsers[from_user_id];

                        if (group_id) {
                            io.to(`group_${group_id}`).emit('new-message', newMessage);
                        } else if (to_user_id) {
                            const recipientSocketId = onlineUsers[to_user_id];
                            if (recipientSocketId) {
                                db.query(`UPDATE messages SET status = 'delivered', delivered_at = NOW() WHERE id = ?`, [messageId]);
                                newMessage.status = 'delivered';
                                io.to(recipientSocketId).emit('new-message', newMessage);
                                if (senderSocketId) io.to(senderSocketId).emit('message-status-update', { message_id: messageId, status: 'delivered' });
                            }
                            // Always echo back to sender
                            if (senderSocketId) io.to(senderSocketId).emit('new-message', newMessage);
                        }
                    }
                );
            }
        );
    });

    socket.on('mark-message-read', (data) => {
        const { message_id, user_id, from_user_id } = data;
        db.query(`UPDATE messages SET read_at = NOW(), status = 'read' WHERE id = ?`, [message_id], (err) => {
            if (!err && onlineUsers[from_user_id]) {
                io.to(onlineUsers[from_user_id]).emit('message-read', { message_id, user_id });
            }
        });
    });

    socket.on('mark-chat-read', (data) => {
        const { user_id, chat_partner_id, group_id } = data;
        if (group_id) {
            db.query(`UPDATE messages SET read_at = NOW(), status = 'read' WHERE group_id = ? AND from_user_id != ? AND read_at IS NULL`, [group_id, user_id]);
        } else if (chat_partner_id) {
            db.query(`UPDATE messages SET read_at = NOW(), status = 'read' WHERE ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)) AND read_at IS NULL`, [chat_partner_id, user_id, user_id, chat_partner_id]);
        }
    });

    socket.on('join-group', (groupId) => {
        socket.join(`group_${groupId}`);
        console.log(`📢 Socket ${socket.id} joined group_${groupId}`);
    });

    socket.on('leave-group', (groupId) => socket.leave(`group_${groupId}`));

    socket.on('typing', (data) => {
        const { from_user_id, to_user_id, group_id, isTyping } = data;
        if (group_id) socket.to(`group_${group_id}`).emit('user-typing', { user_id: from_user_id, isTyping });
        else if (to_user_id && onlineUsers[to_user_id]) io.to(onlineUsers[to_user_id]).emit('user-typing', { user_id: from_user_id, isTyping });
    });

    socket.on('send-voice-note', (data) => {
        const { from_user_id, to_user_id, group_id, voice_url, duration } = data;
        db.query(
            `INSERT INTO messages (from_user_id, to_user_id, group_id, message, file_url, file_type, sent_at, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'sent')`,
            [from_user_id, to_user_id || null, group_id || null, `🎤 Voice note (${duration}s)`, voice_url, 'audio/mp3'],
            (err, result) => {
                if (err) return console.error('Error saving voice note:', err);
                db.query(`SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.from_user_id = u.id WHERE m.id = ?`, [result.insertId], (err, rows) => {
                    if (err || !rows[0]) return;
                    const msg = rows[0];
                    if (group_id) io.to(`group_${group_id}`).emit('new-message', msg);
                    else if (to_user_id) {
                        const recip = onlineUsers[to_user_id];
                        const sender = onlineUsers[from_user_id];
                        if (recip) io.to(recip).emit('new-message', msg);
                        if (sender) io.to(sender).emit('new-message', msg);
                    }
                });
            }
        );
    });

    // WebRTC signaling
    socket.on('call-user', ({ callerId, recipientId, callType, offer }) => {
        const recip = onlineUsers[recipientId];
        if (recip) io.to(recip).emit('incoming-call', { from: callerId, callType, offer });
        else socket.emit('call-error', { message: 'User is offline' });
    });

    socket.on('accept-call', ({ callerId, recipientId, answer }) => {
        const caller = onlineUsers[callerId];
        if (caller) io.to(caller).emit('call-accepted', { from: recipientId, answer });
    });

    socket.on('ice-candidate', ({ targetId, candidate }) => {
        const target = onlineUsers[targetId];
        if (target) io.to(target).emit('ice-candidate', { from: socket.userId, candidate });
    });

    socket.on('reject-call', ({ callerId, recipientId }) => {
        const caller = onlineUsers[callerId];
        if (caller) io.to(caller).emit('call-rejected', { from: recipientId });
    });

    socket.on('end-call', ({ targetId }) => {
        const target = onlineUsers[targetId];
        if (target) io.to(target).emit('call-ended', { from: socket.userId });
    });

    socket.on('disconnect', () => {
        const userId = Object.keys(onlineUsers).find(key => onlineUsers[key] === socket.id);
        if (userId) {
            delete onlineUsers[userId];
            io.emit('online-users', Object.keys(onlineUsers));
            console.log(`🔴 User ${userId} went offline`);
        }
    });
});

// ============================================
// START SERVER
// ============================================
const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`🚀 FoC Connect Chat Server running on port ${PORT}`);
});
