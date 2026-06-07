// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
const express = require('express');
const bcrypt = require('bcryptjs');
const { v4: uuidv4 } = require('uuid');
const { getDb } = require('../db');
const { signToken, requireAuth } = require('../auth');

const router = express.Router();

const BRUTE_MAX_ATTEMPTS = 5;
const BRUTE_WINDOW_MS    = 15 * 60 * 1000;
const BRUTE_LOCKOUT_MS   = 15 * 60 * 1000;
const loginAttempts = new Map();

function bruteKey(ip, username) { return `${ip}::${username}`; }

function getBruteRecord(ip, username) {
    const now = Date.now();
    const key = bruteKey(ip, username);
    const rec = loginAttempts.get(key);
    if (!rec) return null;
    if (rec.lockedUntil && now < rec.lockedUntil) return rec;
    if (now - rec.firstAttempt > BRUTE_WINDOW_MS) { loginAttempts.delete(key); return null; }
    return rec;
}

function recordFailure(ip, username) {
    const now = Date.now();
    const key = bruteKey(ip, username);
    const rec = loginAttempts.get(key) || { count: 0, firstAttempt: now };
    if (now - rec.firstAttempt > BRUTE_WINDOW_MS) { rec.count = 0; rec.firstAttempt = now; delete rec.lockedUntil; }
    rec.count++;
    if (rec.count >= BRUTE_MAX_ATTEMPTS) {
        rec.lockedUntil = now + BRUTE_LOCKOUT_MS;
        console.log(`[brute-force] ${ip} locked out for user "${username}" after ${rec.count} failed attempts`);
    }
    loginAttempts.set(key, rec);
}

function clearFailures(ip, username) { loginAttempts.delete(bruteKey(ip, username)); }

router.get('/login-options', (req, res) => {
    res.json([
        {
            name: 'password',
            type: 'account',
        }
    ]);
});

router.post('/login', (req, res) => {
    const ip = req.ip || '';
    const { username, password, id, uuid, type } = req.body || {};

    if (!username || !password) {
        return res.json({ error: 'Username and password required' });
    }

    const rec = getBruteRecord(ip, username);
    if (rec?.lockedUntil) {
        const mins = Math.ceil((rec.lockedUntil - Date.now()) / 60000);
        return res.status(429).json({ error: `Too many failed attempts. Try again in ${mins} minute(s).` });
    }

    const db = getDb();
    const user = db.prepare('SELECT * FROM users WHERE username = ?').get(username);

    if (!user || !bcrypt.compareSync(password, user.password)) {
        recordFailure(ip, username);
        return res.json({ error: 'Invalid credentials' });
    }

    if (user.status === 0) {
        return res.json({ error: 'Account disabled' });
    }

    clearFailures(ip, username);

    const token = signToken({
        id: user.id,
        username: user.username,
        is_admin: user.is_admin === 1,
    });

    if (id) {
        db.prepare(`
            INSERT INTO devices (peer_id, user_id, uuid, last_seen)
            VALUES (?, ?, ?, datetime('now'))
            ON CONFLICT(peer_id) DO UPDATE SET user_id=excluded.user_id, uuid=excluded.uuid, last_seen=excluded.last_seen
        `).run(id, user.id, uuid || '');
    }

    res.json({
        access_token: token,
        type: 'access_token',
        tfa_type: '',
        secret: '',
        user: {
            id: user.id,
            name: user.username,
            display_name: user.name || user.username,
            email: user.email || '',
            note: '',
            status: user.status === 1 ? 1 : 0,
            is_admin: user.is_admin === 1,
            info: {
                email_verification: false,
                email_alarm_notification: false,
                login_device_whitelist: [],
                other: {},
            },
        },
    });
});

router.post('/logout', requireAuth, (req, res) => {
    res.json({ data: 'success' });
});

router.put('/user/password', requireAuth, (req, res) => {
    const { current_password, new_password } = req.body || {};

    if (!current_password || !new_password) {
        return res.status(400).json({ error: 'current_password and new_password are required' });
    }
    if (new_password.length < 8) {
        return res.status(400).json({ error: 'New password must be at least 8 characters' });
    }

    const db = getDb();
    const user = db.prepare('SELECT * FROM users WHERE id = ?').get(req.user.id);
    if (!user) {
        return res.status(401).json({ error: 'User not found' });
    }
    if (!bcrypt.compareSync(current_password, user.password)) {
        return res.status(403).json({ error: 'Current password is incorrect' });
    }

    const hash = bcrypt.hashSync(new_password, 10);
    db.prepare('UPDATE users SET password = ? WHERE id = ?').run(hash, req.user.id);
    res.json({ data: 'success' });
});

router.post('/currentUser', requireAuth, (req, res) => {
    const db = getDb();
    const user = db.prepare('SELECT * FROM users WHERE id = ?').get(req.user.id);

    if (!user) {
        return res.status(401).json({ error: 'User not found' });
    }

    res.json({
        name: user.username,
        display_name: user.name || user.username,
        email: user.email || '',
        note: '',
        status: user.status === 1 ? 1 : 0,
        is_admin: user.is_admin === 1,
        info: {
            email_verification: false,
            email_alarm_notification: false,
            login_device_whitelist: [],
            other: {},
        },
    });
});

module.exports = router;
