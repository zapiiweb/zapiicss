const Redis = require('ioredis');
const mysql = require('mysql2/promise');
const { BufferJSON, initAuthCreds, proto } = require('@whiskeysockets/baileys');

/**
 * Custom Auth State implementation with Redis + MySQL
 * 
 * This implementation follows Baileys best practices for production:
 * - Credentials (creds) are saved immediately to MySQL (changes rarely)
 * - Keys are cached in Redis with batched writes to MySQL
 * - Uses Redis pub/sub for coordinated flushes
 * - QR codes and connection status are saved to MySQL on events
 */

class CustomAuthState {
    constructor(sessionId, dbConfig, redisConfig = {}) {
        this.sessionId = sessionId;
        this.dbConfig = dbConfig;
        
        // Initialize Redis client with graceful error handling
        this.redis = new Redis({
            host: redisConfig.host || '127.0.0.1',
            port: redisConfig.port || 6379,
            password: redisConfig.password || null,
            db: redisConfig.db || 0,
            keyPrefix: `baileys:${sessionId}:`,
            retryStrategy: (times) => {
                // Retry with exponential backoff up to 30 seconds
                const delay = Math.min(times * 50, 30000);
                return delay;
            },
            maxRetriesPerRequest: 3, // Reduce from default 20 to fail faster
            enableOfflineQueue: false, // Don't queue commands when offline
        });
        
        // Add error listeners to prevent unhandled error crashes
        this.redis.on('error', (error) => {
            // Log once per connection cycle, not every retry
            if (error.code === 'ECONNREFUSED' && !this.redisOfflineLogged) {
                console.warn(`[${this.sessionId}] Redis offline - falling back to MySQL-only mode`);
                this.redisOfflineLogged = true;
            } else if (error.code !== 'ECONNREFUSED') {
                console.error(`[${this.sessionId}] Redis error:`, error.message);
            }
        });
        
        this.redis.on('connect', () => {
            console.log(`[${this.sessionId}] Redis connected`);
            this.redisOfflineLogged = false; // Reset flag when reconnected
        });
        
        // State management
        this.state = {
            creds: null,
            keys: {
                get: async (type, ids) => await this.getKeys(type, ids),
                set: async (data) => await this.setKeys(data)
            }
        };
        
        // Batching configuration
        this.batchConfig = {
            interval: 15000, // 15 seconds
            maxChanges: 50,   // Max changes before forcing flush
        };
        
        // Pending changes cache
        this.pendingKeys = {};
        this.pendingCount = 0;
        this.flushTimer = null;
        
        // MySQL connection pool
        this.mysqlPool = null;
        
        // Redis offline flags
        this.redisOfflineLogged = false;
        this.redisWriteFailureLogged = false;
    }
    
    /**
     * Initialize MySQL connection pool
     */
    async initMysqlPool() {
        if (!this.mysqlPool) {
            this.mysqlPool = mysql.createPool({
                host: this.dbConfig.host,
                port: this.dbConfig.port || 3306,
                user: this.dbConfig.user,
                password: this.dbConfig.password,
                database: this.dbConfig.database,
                waitForConnections: true,
                connectionLimit: 10,
                queueLimit: 0
            });
        }
        return this.mysqlPool;
    }
    
    /**
     * Load credentials from MySQL database
     */
    async loadCredsFromDB() {
        try {
            const pool = await this.initMysqlPool();
            const [rows] = await pool.execute(
                'SELECT creds FROM baileys_auth_creds WHERE session_id = ? LIMIT 1',
                [this.sessionId]
            );
            
            if (rows.length > 0 && rows[0].creds) {
                return JSON.parse(rows[0].creds, BufferJSON.reviver);
            }
            
            return null;
        } catch (error) {
            console.error(`Error loading creds from DB for session ${this.sessionId}:`, error);
            return null;
        }
    }
    
    /**
     * Save credentials to MySQL database (immediate save)
     */
    async saveCredsToDB(creds) {
        try {
            const pool = await this.initMysqlPool();
            const credsJson = JSON.stringify(creds, BufferJSON.replacer, 2);
            
            await pool.execute(
                `INSERT INTO baileys_auth_creds (session_id, creds, updated_at, created_at) 
                 VALUES (?, ?, NOW(), NOW()) 
                 ON DUPLICATE KEY UPDATE creds = ?, updated_at = NOW()`,
                [this.sessionId, credsJson, credsJson]
            );
            
            console.log(`[${this.sessionId}] Creds saved to MySQL`);
        } catch (error) {
            console.error(`Error saving creds to DB for session ${this.sessionId}:`, error);
            throw error;
        }
    }
    
    /**
     * Update QR code in database
     */
    async updateQRCode(qr) {
        try {
            const pool = await this.initMysqlPool();
            await pool.execute(
                `INSERT INTO baileys_auth_creds (session_id, qr_code, updated_at, created_at) 
                 VALUES (?, ?, NOW(), NOW()) 
                 ON DUPLICATE KEY UPDATE qr_code = ?, updated_at = NOW()`,
                [this.sessionId, qr, qr]
            );
            console.log(`[${this.sessionId}] QR code saved to MySQL`);
        } catch (error) {
            console.error(`Error saving QR code for session ${this.sessionId}:`, error);
        }
    }
    
    /**
     * Update connection status in database
     */
    async updateConnectionStatus(status, connectedAt = null) {
        try {
            const pool = await this.initMysqlPool();
            
            if (connectedAt) {
                await pool.execute(
                    `INSERT INTO baileys_auth_creds (session_id, connection_status, connected_at, updated_at, created_at) 
                     VALUES (?, ?, ?, NOW(), NOW()) 
                     ON DUPLICATE KEY UPDATE connection_status = ?, connected_at = ?, updated_at = NOW()`,
                    [this.sessionId, status, connectedAt, status, connectedAt]
                );
            } else {
                await pool.execute(
                    `INSERT INTO baileys_auth_creds (session_id, connection_status, updated_at, created_at) 
                     VALUES (?, ?, NOW(), NOW()) 
                     ON DUPLICATE KEY UPDATE connection_status = ?, updated_at = NOW()`,
                    [this.sessionId, status, status]
                );
            }
            
            console.log(`[${this.sessionId}] Connection status updated: ${status}`);
        } catch (error) {
            console.error(`Error updating connection status for session ${this.sessionId}:`, error);
        }
    }
    
    /**
     * Get keys from Redis cache (with MySQL fallback)
     * CRITICAL: Must work in MySQL-only mode when Redis is offline
     */
    async getKeys(type, ids) {
        try {
            const data = {};
            let foundInRedis = 0;
            let redisAccessFailed = false;
            
            // Try Redis first (fastest) - but don't fail if Redis is offline
            try {
                for (const id of ids) {
                    const key = `${type}:${id}`;
                    const cached = await this.redis.get(key);
                    
                    if (cached) {
                        data[id] = JSON.parse(cached, BufferJSON.reviver);
                        foundInRedis++;
                    }
                }
                
                // If all keys found in Redis, return immediately
                if (foundInRedis === ids.length) {
                    return data;
                }
            } catch (redisError) {
                // Redis is offline - fall through to MySQL
                redisAccessFailed = true;
                if (!this.redisOfflineLogged) {
                    console.warn(`[${this.sessionId}] Redis offline during getKeys - using MySQL:`, redisError.message);
                }
            }
            
            // Some keys missing in Redis (or Redis is offline) - fallback to MySQL
            if (!redisAccessFailed && foundInRedis < ids.length) {
                console.log(`[${this.sessionId}] ${ids.length - foundInRedis} keys missing in Redis, falling back to MySQL`);
            }
            
            const pool = await this.initMysqlPool();
            const [rows] = await pool.execute(
                'SELECT `keys` FROM baileys_auth_creds WHERE session_id = ? LIMIT 1',
                [this.sessionId]
            );
            
            if (rows.length > 0 && rows[0].keys) {
                try {
                    const mysqlKeys = JSON.parse(rows[0].keys);
                    const keysFromMySQL = []; // Track which keys we got from MySQL
                    
                    // Add missing keys to return data
                    for (const id of ids) {
                        if (!data[id]) {
                            const key = `${type}:${id}`;
                            if (mysqlKeys[key]) {
                                // Add to return data
                                data[id] = JSON.parse(mysqlKeys[key], BufferJSON.reviver);
                                // Track that this key came from MySQL (for Redis restoration)
                                keysFromMySQL.push({ key, value: mysqlKeys[key] });
                            }
                        }
                    }
                    
                    // Try to restore MySQL keys to Redis (best effort - don't fail if Redis is offline)
                    if (!redisAccessFailed && keysFromMySQL.length > 0) {
                        try {
                            const pipeline = this.redis.pipeline();
                            
                            for (const { key, value } of keysFromMySQL) {
                                // Restore to Redis with 7 days TTL
                                pipeline.set(key, value, 'EX', 604800);
                            }
                            
                            await pipeline.exec();
                            console.log(`[${this.sessionId}] Restored ${keysFromMySQL.length} keys from MySQL to Redis`);
                        } catch (redisRestoreError) {
                            // Redis restore failed - not critical, data is already in 'data' variable
                            console.warn(`[${this.sessionId}] Could not restore keys to Redis:`, redisRestoreError.message);
                        }
                    }
                    
                } catch (e) {
                    console.error(`Error parsing keys from MySQL for session ${this.sessionId}:`, e);
                }
            }
            
            return data;
            
        } catch (error) {
            console.error(`Error getting keys for session ${this.sessionId}:`, error);
            return {};
        }
    }
    
    /**
     * Set keys to Redis cache (batched write to MySQL)
     * CRITICAL: Must work in MySQL-only mode when Redis is offline
     */
    async setKeys(data) {
        try {
            // Track pending changes for batch flush (MUST happen regardless of Redis status)
            for (const category in data) {
                for (const id in data[category]) {
                    const key = `${category}:${id}`;
                    const value = JSON.stringify(data[category][id], BufferJSON.replacer);
                    
                    // Track pending changes for batch flush to MySQL
                    this.pendingKeys[key] = value;
                    this.pendingCount++;
                }
            }
            
            // Try to store in Redis (best effort - don't fail if Redis is offline)
            try {
                const pipeline = this.redis.pipeline();
                
                for (const category in data) {
                    for (const id in data[category]) {
                        const key = `${category}:${id}`;
                        const value = this.pendingKeys[key]; // Use already serialized value
                        
                        // Cache in Redis with 7 days TTL
                        pipeline.set(key, value, 'EX', 604800);
                    }
                }
                
                await pipeline.exec();
            } catch (redisError) {
                // Redis is offline - log once and continue with MySQL-only mode
                if (!this.redisWriteFailureLogged) {
                    console.warn(`[${this.sessionId}] Redis write failed - operating in MySQL-only mode:`, redisError.message);
                    this.redisWriteFailureLogged = true;
                }
                // Don't throw - continue to MySQL flush logic below
            }
            
            // Check if we should flush to MySQL
            if (this.pendingCount >= this.batchConfig.maxChanges) {
                console.log(`[${this.sessionId}] Flushing keys to MySQL (max changes reached: ${this.pendingCount})`);
                await this.flushKeysToDB();
            } else if (!this.flushTimer) {
                // Start batch timer if not already running
                this.startFlushTimer();
            }
            
        } catch (error) {
            // Log error but don't throw - session must continue even with storage issues
            console.error(`Error in setKeys for session ${this.sessionId}:`, error);
            // Keys are tracked in pendingKeys, so they'll be flushed to MySQL on timer/max changes
        }
    }
    
    /**
     * Start batch flush timer
     */
    startFlushTimer() {
        if (this.flushTimer) {
            clearTimeout(this.flushTimer);
        }
        
        this.flushTimer = setTimeout(async () => {
            if (this.pendingCount > 0) {
                console.log(`[${this.sessionId}] Flushing keys to MySQL (timer: ${this.pendingCount} changes)`);
                await this.flushKeysToDB();
            }
            this.flushTimer = null;
        }, this.batchConfig.interval);
    }
    
    /**
     * Flush pending keys to MySQL database
     */
    async flushKeysToDB() {
        if (this.pendingCount === 0) return;
        
        const keysToFlush = { ...this.pendingKeys };
        const countToFlush = this.pendingCount;
        
        try {
            // Clear pending before flush to avoid race conditions
            this.pendingKeys = {};
            this.pendingCount = 0;
            
            // Load existing keys from MySQL
            const pool = await this.initMysqlPool();
            const [rows] = await pool.execute(
                'SELECT `keys` FROM baileys_auth_creds WHERE session_id = ? LIMIT 1',
                [this.sessionId]
            );
            
            // Merge with existing keys
            let allKeys = {};
            if (rows.length > 0 && rows[0].keys) {
                try {
                    allKeys = JSON.parse(rows[0].keys);
                } catch (e) {
                    console.error(`Error parsing existing keys for session ${this.sessionId}:`, e);
                    allKeys = {};
                }
            }
            
            // Merge new keys with existing keys
            Object.assign(allKeys, keysToFlush);
            
            // Serialize keys to JSON
            const keysJson = JSON.stringify(allKeys);
            
            // Store keys in MySQL
            await pool.execute(
                `INSERT INTO baileys_auth_creds (session_id, \`keys\`, updated_at, created_at) 
                 VALUES (?, ?, NOW(), NOW()) 
                 ON DUPLICATE KEY UPDATE \`keys\` = ?, updated_at = NOW()`,
                [this.sessionId, keysJson, keysJson]
            );
            
            console.log(`[${this.sessionId}] Flushed ${countToFlush} key changes to MySQL (total keys: ${Object.keys(allKeys).length})`);
            
        } catch (error) {
            console.error(`Error flushing keys to MySQL for session ${this.sessionId}:`, error);
            // Restore pending keys on error (keysToFlush first, then newer pendingKeys to avoid overwriting fresh values)
            this.pendingKeys = { ...keysToFlush, ...this.pendingKeys };
            this.pendingCount += countToFlush;
            throw error;
        }
    }
    
    /**
     * Initialize auth state
     */
    async init() {
        // Load creds from MySQL or create new
        let creds = await this.loadCredsFromDB();
        
        if (!creds) {
            console.log(`[${this.sessionId}] Creating new auth credentials`);
            creds = initAuthCreds();
            await this.saveCredsToDB(creds);
        } else {
            console.log(`[${this.sessionId}] Loaded existing credentials from MySQL`);
        }
        
        this.state.creds = creds;
        
        return this.state;
    }
    
    /**
     * Save credentials (called by Baileys on creds.update event)
     */
    async saveCreds() {
        if (this.state.creds) {
            await this.saveCredsToDB(this.state.creds);
        }
    }
    
    /**
     * Clear session data (delete from Redis and MySQL)
     * CRITICAL: MySQL cleanup must execute even if Redis fails
     */
    async clearSession() {
        // Clear Redis keys (best effort - don't let Redis failures block MySQL cleanup)
        try {
            const keys = await this.redis.keys('*');
            if (keys.length > 0) {
                await this.redis.del(...keys);
                console.log(`[${this.sessionId}] Cleared ${keys.length} keys from Redis`);
            }
        } catch (error) {
            console.error(`[${this.sessionId}] Redis cleanup failed (non-critical):`, error.message);
            // Continue to MySQL cleanup - Redis failure is not critical
        }
        
        // Clear MySQL data (MUST execute regardless of Redis status)
        try {
            const pool = await this.initMysqlPool();
            const [result] = await pool.execute(
                'DELETE FROM baileys_auth_creds WHERE session_id = ?',
                [this.sessionId]
            );
            
            if (result.affectedRows > 0) {
                console.log(`[${this.sessionId}] Deleted ${result.affectedRows} credential row(s) from MySQL`);
            } else {
                console.log(`[${this.sessionId}] No credentials found in MySQL to delete`);
            }
        } catch (error) {
            console.error(`[${this.sessionId}] CRITICAL: MySQL cleanup failed:`, error);
            throw error; // Re-throw MySQL errors as they are critical
        }
    }
    
    /**
     * Cleanup resources
     */
    async cleanup() {
        // Flush any pending keys
        if (this.pendingCount > 0) {
            await this.flushKeysToDB();
        }
        
        // Clear flush timer
        if (this.flushTimer) {
            clearTimeout(this.flushTimer);
            this.flushTimer = null;
        }
        
        // Close Redis connection (best effort - don't fail if Redis is offline)
        if (this.redis) {
            try {
                await this.redis.quit();
            } catch (error) {
                // Redis already offline or connection closed - not critical
                console.log(`[${this.sessionId}] Redis cleanup skipped (already offline)`);
            }
        }
        
        // Close MySQL connection pool
        if (this.mysqlPool) {
            await this.mysqlPool.end();
        }
    }
}

/**
 * Factory function to create custom auth state
 * Compatible with Baileys' auth state interface
 */
async function useCustomAuthState(sessionId, dbConfig, redisConfig) {
    const authState = new CustomAuthState(sessionId, dbConfig, redisConfig);
    const state = await authState.init();
    
    return {
        state,
        saveCreds: () => authState.saveCreds(),
        updateQRCode: (qr) => authState.updateQRCode(qr),
        updateConnectionStatus: (status, connectedAt) => authState.updateConnectionStatus(status, connectedAt),
        clearSession: () => authState.clearSession(),
        cleanup: () => authState.cleanup()
    };
}

module.exports = {
    CustomAuthState,
    useCustomAuthState
};
