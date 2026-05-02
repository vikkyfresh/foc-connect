const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const mysql = require('mysql2');

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    },
    transports: ['websocket', 'polling']
});

// MySQL connection for InfinityFree database
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

// Keep connection alive
setInterval(() => {
    db.query('SELECT 1', (err) => {
        if (err) console.log('MySQL keepalive error:', err);
        else console.log('MySQL keepalive OK');
    });
}, 30000);

// Store online users
const onlineUsers = {};

io.on('connection', (socket) => {
    console.log('🟢 New user connected:', socket.id);
    
    socket.on('user-joined', (userId) => {
        onlineUsers[userId] = socket.id;
        console.log(`👤 User ${userId} is online (${Object.keys(onlineUsers).length} online)`);
        io.emit('online-users', Object.keys(onlineUsers));
    });
    
    socket.on('send-message', async (data) => {
        const { from_user_id, to_user_id, group_id, message } = data;
        
        const query = `INSERT INTO messages (from_user_id, to_user_id, group_id, message, sent_at, status) 
                       VALUES (?, ?, ?, ?, NOW(), 'sent')`;
        
        db.query(query, [from_user_id, to_user_id || null, group_id || null, message], (err, result) => {
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
    
    socket.on('join-group', (groupId) => {
        socket.join(`group_${groupId}`);
        console.log(`📢 User joined group: ${groupId}`);
    });
    
    socket.on('leave-group', (groupId) => {
        socket.leave(`group_${groupId}`);
    });
    
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

// API endpoint to get messages
app.get('/api/messages/:type/:id', (req, res) => {
    const { type, id } = req.params;
    
    let query = '';
    if (type === 'group') {
        query = `SELECT m.*, u.name as sender_name 
                 FROM messages m 
                 JOIN users u ON m.from_user_id = u.id 
                 WHERE m.group_id = ? AND (m.is_deleted_by_moderator = 0 OR m.is_deleted_by_moderator IS NULL)
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

// API endpoint to get user's groups
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

// Root endpoint
app.get('/', (req, res) => {
    res.json({ status: 'Chat server is running', online: Object.keys(onlineUsers).length });
});

// Health check endpoint for Render
app.get('/health', (req, res) => {
    res.status(200).json({ status: 'ok', timestamp: new Date().toISOString() });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`🚀 Chat server running on port ${PORT}`);
    console.log(`📡 WebSocket: ws://localhost:${PORT}`);
});
