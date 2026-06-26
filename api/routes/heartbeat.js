// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
const express = require('express');
const fs = require('fs');
const path = require('path');
const { getDb } = require('../db');
const { optionalAuth } = require('../auth');

const router = express.Router();

function buildConfigOptions() {
    const opts = {};

    const domain = process.env.DOMAIN || '';
    if (domain) {
        opts['custom-rendezvous-server'] = domain;
    }

    const pubKey = (() => {
        if (process.env.SERVER_PUB_KEY) return process.env.SERVER_PUB_KEY.trim();
        try {
            const keyPath = path.join(path.dirname(process.env.DB_PATH || '/data/skonadesk.db'), 'id_ed25519.pub');
            return fs.readFileSync(keyPath, 'utf8').trim();
        } catch {
            return '';
        }
    })();
    if (pubKey) {
        opts['key'] = pubKey;
    }

    return opts;
}

router.post('/heartbeat', optionalAuth, (req, res) => {
    const { id, uuid, ver, conns, modified_at } = req.body || {};

    if (id) {
        const db = getDb();
        const userId = req.user ? req.user.id : null;

        const verStr = (() => {
            if (!ver) return '';
            const n = parseInt(ver);
            if (n >= 10000) {
                const patch = n % 100;
                const minor = Math.floor(n / 100) % 100;
                const major = Math.floor(n / 10000);
                return `${major}.${minor}.${patch}`;
            }
            return String(ver);
        })();

        const wanIp = req.ip || '';

        db.prepare(`
            INSERT INTO devices (peer_id, user_id, uuid, version, last_seen, active_conns, wan_ip)
            VALUES (?, ?, ?, ?, datetime('now'), ?, ?)
            ON CONFLICT(peer_id) DO UPDATE SET
                last_seen    = datetime('now'),
                active_conns = excluded.active_conns,
                version      = COALESCE(NULLIF(devices.version, ''), excluded.version),
                user_id      = COALESCE(excluded.user_id, devices.user_id),
                uuid         = excluded.uuid,
                wan_ip       = excluded.wan_ip
        `).run(
            id,
            userId,
            uuid || '',
            verStr,
            JSON.stringify(conns || []),
            wanIp
        );
    }

    res.json({});
});

router.post('/sysinfo', optionalAuth, (req, res) => {
    const { id, uuid, version } = req.body || {};

    if (id) {
        const db = getDb();
        const userId = req.user ? req.user.id : null;

        const os = req.body['os'] || '';
        const hostname = req.body['hostname'] || '';
        const username = req.body['username'] || '';
        const deviceName = req.body['device_name'] || hostname;
        const cpu = req.body['cpu'] || '';
        const memory = req.body['memory'] || '';

        db.prepare(`
            INSERT INTO devices (peer_id, user_id, uuid, version, os, hostname, username, device_name, cpu, memory, last_seen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
            ON CONFLICT(peer_id) DO UPDATE SET
                user_id     = COALESCE(excluded.user_id, devices.user_id),
                uuid        = excluded.uuid,
                version     = COALESCE(NULLIF(excluded.version, ''),     devices.version),
                os          = COALESCE(NULLIF(excluded.os, ''),          devices.os),
                hostname    = COALESCE(NULLIF(excluded.hostname, ''),    devices.hostname),
                username    = COALESCE(NULLIF(excluded.username, ''),    devices.username),
                device_name = COALESCE(NULLIF(excluded.device_name, ''), devices.device_name),
                cpu         = COALESCE(NULLIF(excluded.cpu, ''),         devices.cpu),
                memory      = COALESCE(NULLIF(excluded.memory, ''),      devices.memory),
                last_seen   = datetime('now')
        `).run(id, userId, uuid || '', version || '', os, hostname, username, deviceName, cpu, memory);
    }

    res.send('SYSINFO_UPDATED');
});

router.get('/sysinfo_ver', optionalAuth, (req, res) => {
    res.send('');
});

router.post('/audit/conn', optionalAuth, (req, res) => {
    const { id, conn_id, uuid, peer_id, type, action, ip } = req.body || {};
    const peer = req.body?.peer;
    const db = getDb();
    if (Array.isArray(peer) && peer[0] && !action) {
        const connType = (req.body?.type !== undefined && req.body?.type !== null) ? parseInt(req.body.type, 10) : null;
        db.prepare(`
            UPDATE audit_log SET remote_id = ?, conn_type = ?
            WHERE event_type = 'conn' AND peer_id = ? AND action = 'new'
              AND (conn_id = ? OR CAST(conn_id AS INTEGER) = ?)
        `).run(String(peer[0]), connType, id || '', conn_id, parseInt(conn_id, 10));
    } else {
        const deviceUser = db.prepare('SELECT user_id FROM devices WHERE peer_id = ?').get(id || '');
        const userId = req.user?.id || deviceUser?.user_id || null;
        db.prepare(`
            INSERT INTO audit_log (event_type, peer_id, conn_id, user_id, remote_id, ip, action)
            VALUES ('conn', ?, ?, ?, ?, ?, ?)
        `).run(id || '', conn_id || '', userId, peer_id || '', ip || '', action || '');
    }
    res.json({ data: 'success' });
});

router.post('/audit/file', optionalAuth, (req, res) => {
    const { id, conn_id, uuid, peer_id, type, action, ip, file } = req.body || {};
    const db = getDb();
    const deviceUser = db.prepare('SELECT user_id FROM devices WHERE peer_id = ?').get(id || '');
    const userId = req.user?.id || deviceUser?.user_id || null;
    db.prepare(`
        INSERT INTO audit_log (event_type, peer_id, conn_id, user_id, remote_id, ip, action, note)
        VALUES ('file', ?, ?, ?, ?, ?, ?, ?)
    `).run(id || '', conn_id || '', userId, peer_id || '', ip || '', action || '', file || '');
    res.json({ data: 'success' });
});

router.get('/audit/log', optionalAuth, (req, res) => {
    const db = getDb();
    const page   = parseInt(req.query.current)  || 1;
    const limit  = parseInt(req.query.pageSize) || 50;
    const offset = (page - 1) * limit;
    const count  = db.prepare('SELECT COUNT(*) as n FROM audit_log').get().n;
    const rows   = db.prepare(
        'SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT ? OFFSET ?'
    ).all(limit, offset);
    res.json({ data: rows, total: count });
});

router.get('/server-info', optionalAuth, (req, res) => {
    const pubKey = (() => {
        if (process.env.SERVER_PUB_KEY) return process.env.SERVER_PUB_KEY.trim();
        try {
            const keyPath = path.join(path.dirname(process.env.DB_PATH || '/data/skonadesk.db'), 'id_ed25519.pub');
            return fs.readFileSync(keyPath, 'utf8').trim();
        } catch {
            return '';
        }
    })();
    const domain = process.env.DOMAIN || '';
    res.json({
        public_key: pubKey,
        domain:     domain,
        relay:      domain ? `${domain}:21117` : '',
        rendezvous: domain ? `${domain}:21116` : '',
        api_url:    domain ? `https://${domain}` : '',
    });
});

module.exports = router;
