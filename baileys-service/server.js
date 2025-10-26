require('dotenv').config();

const express = require('express');
const cors = require('cors');
const makeWASocket = require('@whiskeysockets/baileys').default;
const { DisconnectReason, fetchLatestBaileysVersion, downloadMediaMessage } = require('@whiskeysockets/baileys');
const { useCustomAuthState } = require('./customAuthState');
const P = require('pino');
const fs = require('fs');
const path = require('path');
const axios = require('axios');

const app = express();
app.use(cors());
app.use(express.json());

const PORT = process.env.PORT || 3001;
const AUTH_DIR = path.join(__dirname, 'auth_sessions');
const MEDIA_DIR = path.join(__dirname, '..', 'assets', 'media', 'conversation');
const WEBHOOK_URL = process.env.WEBHOOK_URL || null;

// MySQL Database configuration (from .env)
const DB_CONFIG = {
    host: process.env.DB_HOST,
    port: process.env.DB_PORT,
    user: process.env.DB_USERNAME,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_DATABASE
};

// Redis configuration
const REDIS_CONFIG = {
    host: process.env.REDIS_HOST || '127.0.0.1',
    port: parseInt(process.env.REDIS_PORT) || 6379,
    password: process.env.REDIS_PASSWORD || null,
    db: parseInt(process.env.REDIS_DB) || 0
};

// Validate required DB configuration
if (!DB_CONFIG.host || !DB_CONFIG.user || !DB_CONFIG.database) {
    console.error('ERROR: Database configuration missing in .env file');
    console.error('Required: DB_HOST, DB_USERNAME, DB_DATABASE');
    process.exit(1);
}

// Store active sessions
const sessions = new Map();
const qrCodes = new Map();
const sessionWebhooks = new Map();
const messageJobs = new Map(); // Track async message sending jobs
const sessionUserIds = new Map(); // Map sessionId to userId for file organization
const authStateHandlers = new Map(); // Store auth state handlers for cleanup

// Create directories if they don't exist
if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
}
if (!fs.existsSync(MEDIA_DIR)) {
    fs.mkdirSync(MEDIA_DIR, { recursive: true });
}

// Logger configuration
const logger = P({ level: 'silent' });

/**
 * Save media file with user_id/year/month/day directory structure
 */
function saveMediaFile(buffer, fileName, sessionId) {
    const userId = sessionUserIds.get(sessionId) || 'unknown';
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    
    // Create subdirectory structure: userId/year/month/day
    const subDir = path.join(String(userId), String(year), month, day);
    const fullPath = path.join(MEDIA_DIR, subDir);
    
    // Create directory if it doesn't exist
    if (!fs.existsSync(fullPath)) {
        fs.mkdirSync(fullPath, { recursive: true });
    }
    
    // Save file
    const filePath = path.join(fullPath, fileName);
    fs.writeFileSync(filePath, buffer);
    
    // Return relative path from conversation directory
    return path.join(subDir, fileName).replace(/\\/g, '/'); // Normalize path separators
}

/**
 * Check if session exists in database with given status
 */
async function getSessionStatusFromDB(sessionId) {
    const mysql = require('mysql2/promise');
    const pool = await mysql.createPool(DB_CONFIG);
    
    try {
        const [rows] = await pool.execute(
            'SELECT connection_status FROM baileys_auth_creds WHERE session_id = ? LIMIT 1',
            [sessionId]
        );
        await pool.end();
        
        if (rows.length > 0) {
            return rows[0].connection_status;
        }
        return null;
    } catch (error) {
        console.error(`Error checking session status in DB for ${sessionId}:`, error);
        await pool.end();
        return null;
    }
}

/**
 * Detect and clear stale sessions (DB says "connected" but no in-memory socket)
 * CRITICAL: Must execute direct SQL DELETE to remove stale credentials from MySQL
 * before creating new authHandler, even when Redis is offline or authHandler doesn't exist.
 */
async function clearStaleSession(sessionId) {
    console.log(`[${sessionId}] Detected stale session - clearing credentials before reconnect`);
    
    const mysql = require('mysql2/promise');
    const pool = await mysql.createPool(DB_CONFIG);
    
    try {
        // CRITICAL: Delete credentials and keys directly from MySQL
        // This ensures loadCredsFromDB() won't find old creds and will generate new QR
        const [result] = await pool.execute(
            'DELETE FROM baileys_auth_creds WHERE session_id = ?',
            [sessionId]
        );
        
        if (result.affectedRows > 0) {
            console.log(`[${sessionId}] Deleted ${result.affectedRows} stale credential row(s) from MySQL`);
        } else {
            console.log(`[${sessionId}] No stale credentials found in MySQL`);
        }
        
        // Update status to disconnected (in case there's a separate status table)
        // This is now redundant since we deleted the row above, but kept for safety
        
    } catch (error) {
        console.error(`Error deleting stale credentials from MySQL for ${sessionId}:`, error);
        // Don't throw - continue cleanup even if MySQL fails
    }
    
    // Try to clear Redis keys if authHandler exists
    const authHandler = authStateHandlers.get(sessionId);
    if (authHandler) {
        try {
            if (authHandler.clearSession) {
                await authHandler.clearSession();
            }
            if (authHandler.cleanup) {
                await authHandler.cleanup();
            }
        } catch (error) {
            console.error(`Error clearing authHandler for ${sessionId}:`, error);
            // Don't throw - MySQL cleanup above is what matters
        }
    }
    
    // Remove from memory
    authStateHandlers.delete(sessionId);
    
    // Close pool
    try {
        await pool.end();
    } catch (error) {
        console.error(`Error closing MySQL pool:`, error);
    }
    
    console.log(`[${sessionId}] Stale session cleanup complete - ready for fresh QR generation`);
}

/**
 * Create or get WhatsApp session
 * @param {string} sessionId - The session identifier
 * @param {number|null} userId - The user ID
 * @param {boolean} skipStaleCheck - Skip stale session cleanup (used for restart after QR scan)
 */
async function createSession(sessionId, userId = null, skipStaleCheck = false) {
    // Check if session already exists in memory
    if (sessions.has(sessionId)) {
        return { success: true, message: 'Session already exists' };
    }
    
    // Detect stale sessions: Any DB credentials without an active in-memory socket
    // This handles all states: connected, logged_out, connecting, disconnected
    // Skip this check when reconnecting after error 515 (restart required) to preserve credentials
    if (!skipStaleCheck) {
        const dbStatus = await getSessionStatusFromDB(sessionId);
        if (dbStatus && dbStatus !== 'new') {
            console.log(`[${sessionId}] Stale session detected - DB has credentials (status: ${dbStatus}) but no active socket`);
            await clearStaleSession(sessionId);
        }
    } else {
        console.log(`[${sessionId}] Skipping stale session check - reconnecting with existing credentials`);
    }

    try {
        const sessionPath = path.join(AUTH_DIR, sessionId);
        if (!fs.existsSync(sessionPath)) {
            fs.mkdirSync(sessionPath, { recursive: true });
        }
        
        // Persist userId in session directory for reconnections
        if (userId) {
            const userIdFile = path.join(sessionPath, 'user_id.txt');
            fs.writeFileSync(userIdFile, String(userId));
            sessionUserIds.set(sessionId, userId);
            console.log(`Persisted userId ${userId} for session ${sessionId}`);
        } else {
            // Try to restore userId from persisted file
            const userIdFile = path.join(sessionPath, 'user_id.txt');
            if (fs.existsSync(userIdFile)) {
                const restoredUserId = fs.readFileSync(userIdFile, 'utf8').trim();
                sessionUserIds.set(sessionId, restoredUserId);
                console.log(`Restored userId ${restoredUserId} for session ${sessionId}`);
            }
        }

        // Use custom auth state with Redis + MySQL
        const authHandler = await useCustomAuthState(sessionId, DB_CONFIG, REDIS_CONFIG);
        const { state, saveCreds, updateQRCode, updateConnectionStatus, clearSession, cleanup } = authHandler;
        
        // Store auth handler for later use
        authStateHandlers.set(sessionId, authHandler);
        
        const { version } = await fetchLatestBaileysVersion();

        const sock = makeWASocket({
            version,
            auth: state,
            logger,
            printQRInTerminal: false,
            browser: ['Zapii', 'Chrome', '1.0.0'],
            markOnlineOnConnect: true,
            // getMessage is CRITICAL for status updates - must return message content
            getMessage: async (key) => {
                try {
                    const mysql = require('mysql2/promise');
                    const connection = await mysql.createConnection(DB_CONFIG);
                    
                    // Try to find message by whatsapp_message_id
                    const [rows] = await connection.execute(
                        'SELECT body FROM messages WHERE whatsapp_message_id = ? LIMIT 1',
                        [key.id]
                    );
                    
                    await connection.end();
                    
                    if (rows && rows.length > 0 && rows[0].body) {
                        // Return the actual message content
                        return {
                            conversation: rows[0].body
                        };
                    }
                } catch (error) {
                    console.error(`[${sessionId}] Error fetching message for getMessage:`, error.message);
                }
                
                // Fallback: return empty message to at least enable status processing
                return {
                    conversation: ''
                };
            }
        });

        // Handle credentials update
        sock.ev.on('creds.update', saveCreds);

        // Handle connection updates
        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                qrCodes.set(sessionId, qr);
                console.log(`QR Code generated for session: ${sessionId}`);
                
                // Save QR code to database
                await updateQRCode(qr);
                await updateConnectionStatus('connecting');
            }

            if (connection === 'close') {
                // Extract status code and error info for better debugging
                const statusCode = lastDisconnect?.error?.output?.statusCode;
                const errorMessage = lastDisconnect?.error?.message;
                
                console.log(`[${sessionId}] Connection closed - StatusCode: ${statusCode}, Error: ${errorMessage || 'none'}`);
                
                // Handle logged out
                if (statusCode === DisconnectReason.loggedOut) {
                    console.log(`[${sessionId}] Session logged out`);
                    await updateConnectionStatus('logged_out');
                    
                    // Cleanup auth handler
                    const authHandler = authStateHandlers.get(sessionId);
                    if (authHandler && authHandler.cleanup) {
                        await authHandler.cleanup();
                    }
                    authStateHandlers.delete(sessionId);
                    sessions.delete(sessionId);
                    qrCodes.delete(sessionId);
                }
                // Handle 515 (restart required) - This is EXPECTED after QR scan
                // WhatsApp forces a restart to present credentials
                // We should reconnect WITHOUT clearing credentials
                else if (statusCode === DisconnectReason.restartRequired) {
                    console.log(`[${sessionId}] Restart required after pairing (EXPECTED) - reconnecting with saved credentials`);
                    await updateConnectionStatus('connecting');
                    
                    // Remove old socket from memory but keep credentials in DB
                    sessions.delete(sessionId);
                    
                    // Reconnect with skipStaleCheck=true to preserve credentials
                    setTimeout(() => createSession(sessionId, null, true), 2000);
                }
                // Handle other errors
                else if (statusCode) {
                    console.log(`[${sessionId}] Reconnecting due to error (code: ${statusCode})`);
                    await updateConnectionStatus('disconnected');
                    
                    // Cleanup auth handler before reconnecting
                    const authHandler = authStateHandlers.get(sessionId);
                    if (authHandler && authHandler.cleanup) {
                        await authHandler.cleanup();
                    }
                    authStateHandlers.delete(sessionId);
                    sessions.delete(sessionId);
                    
                    setTimeout(() => createSession(sessionId), 3000);
                } else {
                    // No error - this is normal during QR waiting, keep socket alive
                    console.log(`[${sessionId}] Connection closed without error - keeping session alive for QR scan`);
                    await updateConnectionStatus('connecting');
                }
            } else if (connection === 'open') {
                console.log(`Session connected: ${sessionId}`);
                qrCodes.delete(sessionId);
                
                // Update connection status with timestamp
                const connectedAt = new Date().toISOString().slice(0, 19).replace('T', ' ');
                await updateConnectionStatus('connected', connectedAt);
            }
        });

        // Handle incoming messages (including messages sent from phone)
        sock.ev.on('messages.upsert', async ({ messages, type }) => {
            // Process both notify and append types for full history sync
            if (type !== 'notify' && type !== 'append') return;

            for (const msg of messages) {
                if (!msg.message) continue;

                const webhookUrl = sessionWebhooks.get(sessionId);
                if (!webhookUrl) continue;

                try {
                    // Get profile picture URL
                    let profilePicUrl = null;
                    try {
                        const jid = msg.key.remoteJid;
                        profilePicUrl = await sock.profilePictureUrl(jid, 'image');
                    } catch (err) {
                        // Profile picture not available
                        console.log(`No profile picture for ${msg.key.remoteJid}`);
                    }

                    const messageData = {
                        sessionId,
                        messageId: msg.key.id,
                        from: msg.key.remoteJid.replace('@s.whatsapp.net', ''),
                        timestamp: msg.messageTimestamp,
                        message: msg.message.conversation || 
                                msg.message.extendedTextMessage?.text || 
                                '',
                        messageType: 'text',
                        pushName: msg.pushName || '',
                        profilePicUrl: profilePicUrl,
                        fromMe: msg.key.fromMe || false  // Identificar mensagens do celular
                    };

                    // MIME type to extension mapping
                    const mimeToExt = {
                        'video/quicktime': 'mov',
                        'video/x-msvideo': 'avi',
                        'video/mp4': 'mp4',
                        'audio/aac': 'aac',
                        'audio/mpeg': 'mp3',
                        'audio/mp4': 'm4a',
                        'audio/x-m4a': 'm4a',
                        'audio/wav': 'wav',
                        'audio/x-wav': 'wav',
                        'audio/ogg': 'ogg',
                        'image/jpeg': 'jpg',
                        'image/jpg': 'jpg',
                        'image/png': 'png',
                        'image/gif': 'gif',
                        'application/pdf': 'pdf',
                        'application/msword': 'doc',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
                        'application/vnd.ms-excel': 'xls',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
                        'application/vnd.ms-powerpoint': 'ppt',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'pptx',
                        'application/zip': 'zip',
                        'application/x-rar-compressed': 'rar'
                    };

                    // Handle media messages
                    if (msg.message.imageMessage) {
                        messageData.messageType = 'image';
                        messageData.caption = msg.message.imageMessage.caption || '';
                        messageData.mimetype = msg.message.imageMessage.mimetype;
                        
                        try {
                            const buffer = await downloadMediaMessage(msg, 'buffer', {});
                            const extension = mimeToExt[msg.message.imageMessage.mimetype] || msg.message.imageMessage.mimetype.split('/')[1] || 'jpg';
                            const fileName = `${Date.now()}_${msg.key.id}.${extension}`;
                            const relativePath = saveMediaFile(buffer, fileName, sessionId);
                            messageData.fileName = relativePath;
                            console.log(`Image saved: ${relativePath}`);
                        } catch (err) {
                            console.error('Error downloading image:', err);
                        }
                    } else if (msg.message.documentMessage) {
                        messageData.messageType = 'document';
                        messageData.caption = msg.message.documentMessage.caption || '';
                        messageData.fileName = msg.message.documentMessage.fileName;
                        messageData.mimetype = msg.message.documentMessage.mimetype;
                        
                        try {
                            const buffer = await downloadMediaMessage(msg, 'buffer', {});
                            const fileName = msg.message.documentMessage.fileName || `${Date.now()}_document`;
                            const relativePath = saveMediaFile(buffer, fileName, sessionId);
                            messageData.fileName = relativePath;
                            console.log(`Document saved: ${relativePath}`);
                        } catch (err) {
                            console.error('Error downloading document:', err);
                        }
                    } else if (msg.message.videoMessage) {
                        messageData.messageType = 'video';
                        messageData.caption = msg.message.videoMessage.caption || '';
                        messageData.mimetype = msg.message.videoMessage.mimetype;
                        
                        try {
                            const buffer = await downloadMediaMessage(msg, 'buffer', {});
                            const extension = mimeToExt[msg.message.videoMessage.mimetype] || msg.message.videoMessage.mimetype.split('/')[1] || 'mp4';
                            const fileName = `${Date.now()}_${msg.key.id}.${extension}`;
                            const relativePath = saveMediaFile(buffer, fileName, sessionId);
                            messageData.fileName = relativePath;
                            console.log(`Video saved: ${relativePath}`);
                        } catch (err) {
                            console.error('Error downloading video:', err);
                        }
                    } else if (msg.message.stickerMessage) {
                        messageData.messageType = 'sticker';
                        messageData.mimetype = msg.message.stickerMessage.mimetype || 'image/webp';
                        
                        try {
                            const buffer = await downloadMediaMessage(msg, 'buffer', {});
                            const extension = 'webp'; // Stickers são sempre webp
                            const fileName = `${Date.now()}_${msg.key.id}.${extension}`;
                            const relativePath = saveMediaFile(buffer, fileName, sessionId);
                            messageData.fileName = relativePath;
                            console.log(`Sticker saved: ${relativePath}`);
                        } catch (err) {
                            console.error('Error downloading sticker:', err);
                        }
                    } else if (msg.message.audioMessage) {
                        messageData.messageType = 'audio';
                        messageData.mimetype = msg.message.audioMessage.mimetype;
                        
                        try {
                            const buffer = await downloadMediaMessage(msg, 'buffer', {});
                            const extension = mimeToExt[msg.message.audioMessage.mimetype] || msg.message.audioMessage.mimetype.split('/')[1] || 'mp3';
                            const fileName = `${Date.now()}_${msg.key.id}.${extension}`;
                            const relativePath = saveMediaFile(buffer, fileName, sessionId);
                            messageData.fileName = relativePath;
                            console.log(`Audio saved: ${relativePath}`);
                        } catch (err) {
                            console.error('Error downloading audio:', err);
                        }
                    }

                    await axios.post(webhookUrl, messageData).catch(err => {
                        console.error(`Webhook error for session ${sessionId}:`, err.message);
                    });

                } catch (error) {
                    console.error(`Error processing message for session ${sessionId}:`, error);
                }
            }
        });

        // Handle message status updates (delivered, read, failed)
        sock.ev.on('messages.update', async (updates) => {
            console.log(`[${sessionId}] Received ${updates.length} message status update(s)`);
            
            const webhookUrl = sessionWebhooks.get(sessionId);
            if (!webhookUrl) {
                console.log(`[${sessionId}] No webhook URL configured - skipping status updates`);
                return;
            }

            for (const update of updates) {
                try {
                    console.log(`[${sessionId}] Status update for message ${update.key.id}:`, JSON.stringify(update.update));
                    
                    const statusData = {
                        type: 'status_update',
                        sessionId,
                        messageId: update.key.id,
                        status: null
                    };

                    // Map Baileys status to system status
                    // Baileys: 1=sent, 2=delivered, 3=read, 4=played
                    // System: 1=sent, 2=delivered, 3=read, 9=failed
                    if (update.update.status === 1) {
                        statusData.status = 1; // sent
                        console.log(`[${sessionId}] Message ${update.key.id} status: SENT`);
                    } else if (update.update.status === 2) {
                        statusData.status = 2; // delivered
                        console.log(`[${sessionId}] Message ${update.key.id} status: DELIVERED`);
                    } else if (update.update.status === 3 || update.update.status === 4) {
                        statusData.status = 3; // read (includes played for media)
                        console.log(`[${sessionId}] Message ${update.key.id} status: READ`);
                    } else {
                        console.log(`[${sessionId}] Message ${update.key.id} unknown status: ${update.update.status}`);
                    }

                    // Only send webhook if status is mapped
                    if (statusData.status) {
                        console.log(`[${sessionId}] Sending status webhook for message ${update.key.id} with status ${statusData.status}`);
                        await axios.post(webhookUrl, statusData).catch(err => {
                            console.error(`[${sessionId}] Status webhook error:`, err.message);
                        });
                        console.log(`[${sessionId}] Status webhook sent successfully`);
                    }

                } catch (error) {
                    console.error(`[${sessionId}] Error processing status update:`, error);
                }
            }
        });

        sessions.set(sessionId, sock);
        return { success: true, message: 'Session created successfully' };

    } catch (error) {
        console.error(`Error creating session ${sessionId}:`, error);
        return { success: false, message: error.message };
    }
}

/**
 * Get session status
 */
function getSessionStatus(sessionId) {
    const sock = sessions.get(sessionId);
    const qr = qrCodes.get(sessionId);
    
    if (!sock) {
        return { connected: false, hasQR: false };
    }

    return {
        connected: sock.user ? true : false,
        hasQR: qr ? true : false,
        user: sock.user || null
    };
}

/**
 * Delete session
 */
async function deleteSession(sessionId) {
    const sock = sessions.get(sessionId);
    
    if (sock) {
        await sock.logout();
        sessions.delete(sessionId);
    }
    
    qrCodes.delete(sessionId);
    
    // Clear session data from Redis and MySQL using custom auth state
    const authHandler = authStateHandlers.get(sessionId);
    if (authHandler && authHandler.clearSession) {
        await authHandler.clearSession();
    }
    
    // Cleanup auth handler
    if (authHandler && authHandler.cleanup) {
        await authHandler.cleanup();
    }
    authStateHandlers.delete(sessionId);
    
    // Remove auth files (legacy - keeping for backward compatibility)
    const sessionPath = path.join(AUTH_DIR, sessionId);
    if (fs.existsSync(sessionPath)) {
        fs.rmSync(sessionPath, { recursive: true, force: true });
    }
    
    return { success: true, message: 'Session deleted' };
}

/**
 * Send message
 */
async function sendMessage(sessionId, to, message, options = {}) {
    try {
        const sock = sessions.get(sessionId);
        
        if (!sock || !sock.user) {
            throw new Error('Session not connected');
        }

        // Ensure proper JID format
        let jid = to;
        if (!to.includes('@')) {
            jid = `${to}@s.whatsapp.net`;
        }

        let content = { text: message };
        
        // Handle media
        if (options.mediaType && options.mediaUrl) {
            console.log(`Sending ${options.mediaType} from URL: ${options.mediaUrl}`);
            
            switch(options.mediaType) {
                case 'image':
                    content = {
                        image: { url: options.mediaUrl },
                        caption: message || options.caption || ''
                    };
                    break;
                case 'document':
                    content = {
                        document: { url: options.mediaUrl },
                        mimetype: options.mimeType || 'application/pdf',
                        fileName: options.fileName || 'document.pdf',
                        caption: message || options.caption || ''
                    };
                    break;
                case 'video':
                    content = {
                        video: { url: options.mediaUrl },
                        caption: message || options.caption || ''
                    };
                    break;
                case 'audio':
                    content = {
                        audio: { url: options.mediaUrl },
                        mimetype: options.mimeType || 'audio/mpeg'
                    };
                    break;
            }
        }

        console.log(`Sending message to ${jid}...`);
        const result = await sock.sendMessage(jid, content);
        console.log(`Message sent successfully: ${result.key.id}`);
        
        return { 
            success: true, 
            message: 'Message sent',
            messageId: result.key.id
        };
    } catch (error) {
        console.error(`Error sending message: ${error.message}`);
        throw error;
    }
}

// API Routes

/**
 * POST /session/start - Start a new session
 */
app.post('/session/start', async (req, res) => {
    const { sessionId, userId } = req.body;
    
    if (!sessionId) {
        return res.status(400).json({ error: 'sessionId is required' });
    }

    const result = await createSession(sessionId, userId);
    res.json(result);
});

/**
 * GET /session/qr/:sessionId - Get QR code for session
 */
app.get('/session/qr/:sessionId', (req, res) => {
    const { sessionId } = req.params;
    const qr = qrCodes.get(sessionId);
    
    if (!qr) {
        return res.status(404).json({ error: 'QR code not available' });
    }
    
    res.json({ qr });
});

/**
 * GET /session/status/:sessionId - Get session status
 */
app.get('/session/status/:sessionId', (req, res) => {
    const { sessionId } = req.params;
    const status = getSessionStatus(sessionId);
    res.json(status);
});

/**
 * DELETE /session/:sessionId - Delete session
 */
app.delete('/session/:sessionId', async (req, res) => {
    const { sessionId } = req.params;
    const result = await deleteSession(sessionId);
    res.json(result);
});

/**
 * POST /message/send - Send message (ASYNC with webhook callback)
 */
app.post('/message/send', async (req, res) => {
    const { sessionId, to, message, mediaType, mediaUrl, mimeType, fileName, caption, callbackUrl } = req.body;
    
    if (!sessionId || !to) {
        return res.status(400).json({ error: 'sessionId and to are required' });
    }

    if (!message && !mediaType) {
        return res.status(400).json({ error: 'Either message or mediaType is required' });
    }

    // Generate unique job ID
    const jobId = `job_${Date.now()}_${Math.random().toString(36).substring(7)}`;
    
    // Store job status
    messageJobs.set(jobId, {
        status: 'processing',
        sessionId,
        to,
        createdAt: new Date().toISOString()
    });

    // Respond immediately with 202 Accepted
    res.status(202).json({ 
        success: true,
        jobId,
        status: 'processing',
        message: 'Message is being sent in background'
    });

    // Process message sending in background
    setImmediate(async () => {
        try {
            const options = {
                mediaType,
                mediaUrl,
                mimeType,
                fileName,
                caption
            };
            
            console.log(`[Job ${jobId}] Starting background message send...`);
            const result = await sendMessage(sessionId, to, message, options);
            
            // Update job status
            messageJobs.set(jobId, {
                status: 'completed',
                messageId: result.messageId,
                completedAt: new Date().toISOString()
            });
            
            console.log(`[Job ${jobId}] Message sent successfully: ${result.messageId}`);
            
            // Call webhook if provided
            if (callbackUrl) {
                try {
                    await axios.post(callbackUrl, {
                        jobId,
                        status: 'sent',
                        messageId: result.messageId,
                        sessionId,
                        to
                    });
                    console.log(`[Job ${jobId}] Webhook callback sent to ${callbackUrl}`);
                } catch (webhookError) {
                    console.error(`[Job ${jobId}] Webhook callback failed:`, webhookError.message);
                }
            }
            
        } catch (error) {
            console.error(`[Job ${jobId}] Message send failed:`, error.message);
            
            // Update job status
            messageJobs.set(jobId, {
                status: 'failed',
                error: error.message,
                failedAt: new Date().toISOString()
            });
            
            // Call webhook with failure
            if (callbackUrl) {
                try {
                    await axios.post(callbackUrl, {
                        jobId,
                        status: 'failed',
                        error: error.message,
                        sessionId,
                        to
                    });
                } catch (webhookError) {
                    console.error(`[Job ${jobId}] Webhook failure callback failed:`, webhookError.message);
                }
            }
        }
    });
});

/**
 * POST /webhook/set - Set webhook URL for session
 */
app.post('/webhook/set', (req, res) => {
    const { sessionId, webhookUrl } = req.body;
    
    if (!sessionId || !webhookUrl) {
        return res.status(400).json({ error: 'sessionId and webhookUrl are required' });
    }

    sessionWebhooks.set(sessionId, webhookUrl);
    res.json({ success: true, message: 'Webhook URL set successfully' });
});

/**
 * GET /health - Health check
 */
app.get('/health', (req, res) => {
    res.json({ 
        status: 'ok', 
        activeSessions: sessions.size,
        pendingQRs: qrCodes.size
    });
});

/**
 * Reconcile sessions on startup - auto-reconnect sessions with saved credentials
 */
async function reconcileSessions() {
    console.log('Reconciling sessions on startup...');
    const mysql = require('mysql2/promise');
    const pool = await mysql.createPool(DB_CONFIG);
    
    try {
        // Find all sessions with credentials saved (connected or disconnected)
        const [rows] = await pool.execute(
            'SELECT session_id, user_id, connection_status FROM baileys_auth_creds WHERE creds IS NOT NULL'
        );
        
        if (rows.length === 0) {
            console.log('No sessions found to reconcile');
            return;
        }
        
        console.log(`Found ${rows.length} session(s) with saved credentials`);
        
        // Auto-reconnect sessions that have credentials but no active socket
        let reconnected = 0;
        for (const row of rows) {
            const sessionId = row.session_id;
            const userId = row.user_id;
            const hasActiveSocket = sessions.has(sessionId);
            
            if (!hasActiveSocket) {
                console.log(`[${sessionId}] Auto-reconnecting session for user ${userId}...`);
                
                try {
                    // Restore webhook URL (assuming pattern: https://your-domain/webhook/baileys)
                    if (process.env.WEBHOOK_URL) {
                        sessionWebhooks.set(sessionId, process.env.WEBHOOK_URL);
                    }
                    
                    // Create session (will load credentials from MySQL automatically)
                    const result = await createSession(sessionId, userId, true); // skipStaleCheck=true
                    
                    if (result.success) {
                        console.log(`[${sessionId}] Auto-reconnected successfully`);
                        reconnected++;
                    } else {
                        console.log(`[${sessionId}] Auto-reconnect failed: ${result.message}`);
                        // Mark as disconnected if reconnection failed
                        await pool.execute(
                            'UPDATE baileys_auth_creds SET connection_status = ?, updated_at = NOW() WHERE session_id = ?',
                            ['disconnected', sessionId]
                        );
                    }
                } catch (error) {
                    console.error(`[${sessionId}] Error during auto-reconnect:`, error.message);
                    // Mark as disconnected on error
                    await pool.execute(
                        'UPDATE baileys_auth_creds SET connection_status = ?, updated_at = NOW() WHERE session_id = ?',
                        ['disconnected', sessionId]
                    );
                }
                
                // Add delay between reconnections to avoid overwhelming WhatsApp servers
                await new Promise(resolve => setTimeout(resolve, 2000));
            }
        }
        
        console.log(`Reconciliation complete: ${reconnected} session(s) auto-reconnected`);
    } catch (error) {
        console.error('Error during session reconciliation:', error);
    } finally {
        await pool.end();
    }
}

// Start server with increased timeout for large file uploads
const server = app.listen(PORT, '127.0.0.1', async () => {
    console.log(`Baileys WhatsApp Service running on http://127.0.0.1:${PORT}`);
    console.log(`Active sessions will be stored in: ${AUTH_DIR}`);
    
    // Reconcile stale sessions on startup
    await reconcileSessions();
});

// Increase server timeout to 5 minutes for large media uploads
server.timeout = 300000; // 5 minutes
server.keepAliveTimeout = 305000; // 5 minutes + 5 seconds

// Graceful shutdown
process.on('SIGINT', async () => {
    console.log('Shutting down gracefully...');
    
    // Close all socket connections
    for (const [sessionId, sock] of sessions) {
        try {
            await sock.end();
        } catch (error) {
            console.error(`Error closing session ${sessionId}:`, error);
        }
    }
    
    // Cleanup all auth handlers (flush pending keys, close Redis/MySQL connections)
    for (const [sessionId, authHandler] of authStateHandlers) {
        try {
            if (authHandler && authHandler.cleanup) {
                console.log(`Cleaning up auth handler for session ${sessionId}...`);
                await authHandler.cleanup();
            }
        } catch (error) {
            console.error(`Error cleaning up auth handler for session ${sessionId}:`, error);
        }
    }
    
    console.log('Shutdown complete');
    process.exit(0);
});
