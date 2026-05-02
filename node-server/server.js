const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const mysql = require('mysql2');
const multer = require('multer');
const path = require('path');
const fs = require('fs');

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    },
    transports: ['websocket', 'polling']
});

// ============================================
// FILE UPLOAD CONFIGURATION
// ============================================

// Ensure upload directories exist
const uploadDir = './uploads';
const voiceDir = './uploads/voice';
const imageDir = './uploads/images';

if (!fs.existsSync(uploadDir)) fs.mkdirSync(uploadDir);
if (!fs.existsSync(voiceDir)) fs.mkdirSync(voiceDir);
if (!fs.existsSync(imageDir)) fs.mkdirSync(imageDir);

// Configure storage for different file types
const storage = multer.diskStorage({
    destination: (req, file, cb) => {
        if (file.mimetype.startsWith('audio/')) {
            cb(null, voiceDir);
        } else if (file.mimetype.startsWith('image/')) {
            cb(null, imageDir);
        } else {
            cb(null, uploadDir);
        }
    },
    filename: (req, file, cb) => {
        const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
        const ext = path.extname(file.originalname);
        cb(null, uniqueSuffix + ext);
    }
});

// File filter for allowed types
const fileFilter = (req, file, cb) => {
    const allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain', 'text/csv',
        'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm',
        'video/mp4', 'video/webm'
    ];
    if (allowedTypes.includes(file.mimetype)) {
        cb(null, true);
    } else {
        cb(new Error('File type not allowed'), false);
    }
};

const upload = multer({
    storage: storage,
    fileFilter: fileFilter,
    limits: { fileSize: 50 * 1024 * 1024 } // 50MB max
});

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
    if (err) {
        console.error('❌ MySQL connection failed:', err);
    } else {
        console.log('✅ MySQL connected');
    }
});

// Keep database connection alive
setInterval(() => {
    db.query('SELECT 1', (err) => {
        if (err) console.log('MySQL keepalive error:', err);
        else console.log('MySQL keepalive OK');
    });
}, 30000);

// ============================================
// FILE UPLOAD ENDPOINT
// ============================================

app.post('/api/upload', upload.single('file'), (req, res) => {
    if (!req.file) {
        return res.status(400).json({ error: 'No file uploaded' });
    }

    let fileUrl = '';
    if (req.file.mimetype.startsWith('audio/')) {
        fileUrl = `/uploads/voice/${req.file.filename}`;
    } else if (req.file.mimetype.startsWith('image/')) {
        fileUrl = `/uploads/images/${req.file.filename}`;
    } else {
        fileUrl = `/uploads/${req.file.filename}`;
    }

    res.json({
        success: true,
        file_url: fileUrl,
        file_name: req.file.originalname,
        file_type: req.file.mimetype,
        file_size: req.file.size
    });
});

// Serve uploaded files
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));

// ============================================
// API ENDPOINTS
// ============================================

app.get('/api/messages/:type/:id', (req, res) => {
    const { type, id } = req.params;

    let query = '';
    if (type === 'group') {
        query = `SELECT m.*, u.name as sender_name 
                 FROM messages m 
                 JOIN users u ON m.from_user_id = u.id 
                 WHERE m.group_id = ? 
                 ORDER BY m.sent_at ASC LIMIT 100`;
        db.query(query, [id], (err, rows) => {
            if (err) {
                console.error('API error:', err);
                res.status(500).json({ error: err.message });
            } else {
                res.json(rows || []);
            }
        });
    } else {
        query = `SELECT m.*, u.name as sender_name 
                 FROM messages m 
                 JOIN users u ON m.from_user_id = u.id 
                 WHERE ((m.from_user_id = ? OR m.to_user_id = ?) AND m.group_id IS NULL)
                 ORDER BY m.sent_at ASC LIMIT 100`;
        db.query(query, [id, id], (err, rows) => {
            if (err) {
                console.error('API error:', err);
                res.status(500).json({ error: err.message });
            } else {
                res.json(rows || []);
            }
        });
    }
});

app.get('/api/user-groups/:userId', (req, res) => {
    const { userId } = req.params;

    const query = `SELECT cg.* 
                   FROM chat_groups cg
                   JOIN group_members gm ON cg.id = gm.group_id
                   WHERE gm.user_id = ?
                   ORDER BY cg.name ASC`;

    db.query(query, [userId], (err, rows) => {
        if (err) {
            res.status(500).json({ error: err.message });
        } else {
            res.json(rows || []);
        }
    });
});

app.get('/', (req, res) => {
    res.json({ status: 'Chat server is running' });
});

app.get('/health', (req, res) => {
    res.status(200).json({ status: 'ok', timestamp: new Date().toISOString() });
});

// ============================================
// WEBRTC SIGNALING FOR AUDIO/VIDEO CALLS
// ============================================

// Store call rooms and participants
const callRooms = {};

io.on('connection', (socket) => {
    console.log('🟢 New user connected:', socket.id);

    // User joins with their user ID
    socket.on('user-joined', (userId) => {
        socket.userId = userId;
        onlineUsers[userId] = socket.id;
        console.log(`👤 User ${userId} is online (${Object.keys(onlineUsers).length} online)`);
        io.emit('online-users', Object.keys(onlineUsers));
    });

    // ============================================
    // TEXT MESSAGES WITH FILE SUPPORT
    // ============================================

    socket.on('send-message', async (data) => {
        const { from_user_id, to_user_id, group_id, message, file_url, file_name, file_type, file_size } = data;

        const query = `INSERT INTO messages (from_user_id, to_user_id, group_id, message, file_url, file_name, file_type, file_size, sent_at, status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'sent')`;

        db.query(query, [from_user_id, to_user_id || null, group_id || null, message || '', file_url || null, file_name || null, file_type || null, file_size || null], (err, result) => {
            if (err) {
                console.error('❌ Error saving message:', err);
                return;
            }

            const messageId = result.insertId;

            db.query(`SELECT m.*, u.name as sender_name 
                      FROM messages m 
                      JOIN users u ON m.from_user_id = u.id 
                      WHERE m.id = ?`, [messageId], (err, rows) => {
                if (err) return;

                const newMessage = rows[0];

                if (group_id) {
                    io.to(`group_${group_id}`).emit('new-message', newMessage);
                } else if (to_user_id) {
                    const recipientSocketId = onlineUsers[to_user_id];
                    if (recipientSocketId) {
                        db.query(`UPDATE messages SET status = 'delivered', delivered_at = NOW() WHERE id = ?`, [messageId]);
                        newMessage.status = 'delivered';
                        io.to(recipientSocketId).emit('new-message', newMessage);
                        io.to(onlineUsers[from_user_id]).emit('message-status-update', {
                            message_id: messageId,
                            status: 'delivered'
                        });
                    } else {
                        io.to(onlineUsers[from_user_id]).emit('new-message', newMessage);
                    }
                }
            });
        });
    });

    // Mark message as read (blue tick)
    socket.on('mark-message-read', (data) => {
        const { message_id, user_id, from_user_id } = data;

        db.query(`UPDATE messages SET read_at = NOW(), status = 'read' WHERE id = ?`, [message_id], (err) => {
            if (!err && onlineUsers[from_user_id]) {
                io.to(onlineUsers[from_user_id]).emit('message-read', {
                    message_id: message_id,
                    user_id: user_id
                });
            }
        });
    });

    // Mark all messages in a chat as read
    socket.on('mark-chat-read', (data) => {
        const { user_id, chat_partner_id, group_id } = data;

        if (group_id) {
            const query = `UPDATE messages SET read_at = NOW(), status = 'read' 
                           WHERE group_id = ? AND from_user_id != ? AND read_at IS NULL`;
            db.query(query, [group_id, user_id]);
        } else if (chat_partner_id) {
            const query = `UPDATE messages SET read_at = NOW(), status = 'read' 
                           WHERE ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)) 
                           AND read_at IS NULL`;
            db.query(query, [chat_partner_id, user_id, user_id, chat_partner_id]);
        }
    });

    // Join group
    socket.on('join-group', (groupId) => {
        socket.join(`group_${groupId}`);
        console.log(`📢 User joined group: ${groupId}`);
    });

    // Leave group
    socket.on('leave-group', (groupId) => {
        socket.leave(`group_${groupId}`);
    });

    // Typing indicator
    socket.on('typing', (data) => {
        const { from_user_id, to_user_id, group_id, isTyping } = data;

        if (group_id) {
            socket.to(`group_${group_id}`).emit('user-typing', {
                user_id: from_user_id,
                isTyping: isTyping
            });
        } else if (to_user_id && onlineUsers[to_user_id]) {
            io.to(onlineUsers[to_user_id]).emit('user-typing', {
                user_id: from_user_id,
                isTyping: isTyping
            });
        }
    });

    // ============================================
    // VOICE NOTES (RECORDING)
    // ============================================

    socket.on('send-voice-note', (data) => {
        const { from_user_id, to_user_id, group_id, voice_url, duration } = data;

        const query = `INSERT INTO messages (from_user_id, to_user_id, group_id, message, file_url, file_type, file_size, sent_at, status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'sent')`;

        db.query(query, [from_user_id, to_user_id || null, group_id || null, `🎤 Voice note (${duration}s)`, voice_url, 'audio/mp3', null], (err, result) => {
            if (err) return console.error('Error saving voice note:', err);

            db.query(`SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.from_user_id = u.id WHERE m.id = ?`, [result.insertId], (err, rows) => {
                if (err) return;
                const newMessage = rows[0];

                if (group_id) {
                    io.to(`group_${group_id}`).emit('new-message', newMessage);
                } else if (to_user_id) {
                    const recipientSocketId = onlineUsers[to_user_id];
                    if (recipientSocketId) {
                        io.to(recipientSocketId).emit('new-message', newMessage);
                    }
                    io.to(onlineUsers[from_user_id]).emit('new-message', newMessage);
                }
            });
        });
    });

    // ============================================
    // WEBRTC AUDIO/VIDEO CALL SIGNALING
    // ============================================

    // Initiate a call
    socket.on('call-user', (data) => {
        const { callerId, recipientId, callType, offer } = data;
        const recipientSocketId = onlineUsers[recipientId];

        if (recipientSocketId) {
            console.log(`📞 ${callerId} is calling ${recipientId} (${callType})`);
            io.to(recipientSocketId).emit('incoming-call', {
                from: callerId,
                callType: callType,
                offer: offer
            });
        } else {
            socket.emit('call-error', { message: 'User is offline' });
        }
    });

    // Accept call
    socket.on('accept-call', (data) => {
        const { callerId, recipientId, answer } = data;
        const callerSocketId = onlineUsers[callerId];

        if (callerSocketId) {
            console.log(`📞 ${recipientId} accepted call from ${callerId}`);
            io.to(callerSocketId).emit('call-accepted', {
                from: recipientId,
                answer: answer
            });
        }
    });

    // ICE candidate exchange
    socket.on('ice-candidate', (data) => {
        const { targetId, candidate } = data;
        const targetSocketId = onlineUsers[targetId];

        if (targetSocketId) {
            io.to(targetSocketId).emit('ice-candidate', {
                from: socket.userId,
                candidate: candidate
            });
        }
    });

    // Reject call
    socket.on('reject-call', (data) => {
        const { callerId, recipientId } = data;
        const callerSocketId = onlineUsers[callerId];

        if (callerSocketId) {
            console.log(`📞 ${recipientId} rejected call from ${callerId}`);
            io.to(callerSocketId).emit('call-rejected', { from: recipientId });
        }
    });

    // End call
    socket.on('end-call', (data) => {
        const { targetId } = data;
        const targetSocketId = onlineUsers[targetId];

        if (targetSocketId) {
            io.to(targetSocketId).emit('call-ended', { from: socket.userId });
        }
    });

    // ============================================
    // DISCONNECT HANDLER
    // ============================================

    socket.on('disconnect', () => {
        const userId = Object.keys(onlineUsers).find(key => onlineUsers[key] === socket.id);
        if (userId) {
            delete onlineUsers[userId];
            io.emit('online-users', Object.keys(onlineUsers));
            console.log(`🔴 User ${userId} went offline`);
        }
        console.log('🔴 User disconnected:', socket.id);
    });
});

// Store online users
const onlineUsers = {};

// ============================================
// START SERVER
// ============================================

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`🚀 Chat server running on port ${PORT}`);
    console.log(`📡 WebSocket: ws://localhost:${PORT}`);
    console.log(`📁 Upload directory: ${path.join(__dirname, 'uploads')}`);
});
