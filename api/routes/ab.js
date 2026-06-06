const express = require('express');
const { v4: uuidv4 } = require('uuid');
const { getDb } = require('../db');
const { requireAuth } = require('../auth');

const router = express.Router();

function getUserPersonalAbGuid(userId) {
    const db = getDb();
    const ab = db.prepare('SELECT guid FROM address_books WHERE owner_id = ? LIMIT 1').get(userId);
    if (!ab) {
        const guid = uuidv4();
        db.prepare(`
            INSERT INTO address_books (guid, owner_id, name) VALUES (?, ?, 'My address book')
        `).run(guid, userId);
        return guid;
    }
    return ab.guid;
}

function fetchAbPeers(abGuid) {
    const db = getDb();
    return db.prepare('SELECT * FROM ab_peers WHERE ab_guid = ?').all(abGuid).map(peerRow);
}

function peerRow(p) {
    return {
        id:       p.peer_id,
        alias:    p.alias    || '',
        note:     p.note     || '',
        password: p.password || '',
        hash:     p.hash     || '',
        tags:     JSON.parse(p.tags || '[]'),
        username: p.username || '',
        hostname: p.hostname || '',
        platform: p.platform || '',
    };
}

function fetchAbTags(abGuid) {
    const db = getDb();
    return db.prepare('SELECT name, color FROM ab_tags WHERE ab_guid = ?').all(abGuid);
}

// ─── Legacy Address Book ──────────────────────────────────────────────────────

// GET /api/ab  — client fetches entire address book (legacy mode)
// Response: {"data": "<json-encoded-string>", "licensed_devices": 0}
// The inner data string is JSON of {peers:[...], tags:[...]}
router.get('/ab', requireAuth, (req, res) => {
    const guid = getUserPersonalAbGuid(req.user.id);
    const peers = fetchAbPeers(guid);
    const tags = fetchAbTags(guid).map(t => t.name);

    if (peers.length === 0 && tags.length === 0) {
        return res.json(null);
    }

    res.json({
        data: JSON.stringify({ peers, tags }),
        licensed_devices: 100,
    });
});

// POST /api/ab  — client pushes entire address book (legacy mode)
// Body: {"data": "<json-encoded-string>"}
router.post('/ab', requireAuth, (req, res) => {
    const db = getDb();
    const guid = getUserPersonalAbGuid(req.user.id);

    let abData;
    try {
        abData = JSON.parse(req.body.data || '{}');
    } catch {
        return res.status(400).json({ error: 'invalid data' });
    }

    const peers = Array.isArray(abData.peers) ? abData.peers : [];
    const tags  = Array.isArray(abData.tags)  ? abData.tags  : [];

    db.prepare('DELETE FROM ab_peers WHERE ab_guid = ?').run(guid);
    db.prepare('DELETE FROM ab_tags  WHERE ab_guid = ?').run(guid);

    const insertPeer = db.prepare(`
        INSERT OR IGNORE INTO ab_peers (ab_guid, peer_id, alias, note, password, tags)
        VALUES (?, ?, ?, ?, ?, ?)
    `);
    for (const p of peers) {
        insertPeer.run(guid, p.id || '', p.alias || '', p.note || '', p.password || '', JSON.stringify(p.tags || []));
    }

    const insertTag = db.prepare('INSERT OR IGNORE INTO ab_tags (ab_guid, name, color) VALUES (?, ?, 0)');
    for (const t of tags) {
        if (typeof t === 'string') insertTag.run(guid, t);
    }

    res.send(null);
});

// ─── New-Mode Address Book ────────────────────────────────────────────────────

// POST /api/ab/settings — not fully supported, return 404 so client falls back to legacy
router.post('/ab/settings', requireAuth, (req, res) => {
    res.status(404).json({ error: 'shared address books not supported' });
});

// POST /api/ab/personal — returns the personal address book GUID for this user
router.post('/ab/personal', requireAuth, (req, res) => {
    const guid = getUserPersonalAbGuid(req.user.id);
    res.json({ guid });
});

// POST /api/ab/shared/profiles — returns list of shared address books (none for now)
router.post('/ab/shared/profiles', requireAuth, (req, res) => {
    res.json({ data: [] });
});

// GET or POST /api/ab/peers?current=N&pageSize=N&ab=GUID
// Response: {"data": [...], "total": N}
function handleAbPeers(req, res) {
    const db = getDb();
    const abGuid = req.query.ab || getUserPersonalAbGuid(req.user.id);
    const pageSize = parseInt(req.query.pageSize) || 100;
    const current  = parseInt(req.query.current)  || 1;
    const offset   = (current - 1) * pageSize;

    const total = db.prepare('SELECT COUNT(*) as n FROM ab_peers WHERE ab_guid = ?').get(abGuid).n;
    const rows  = db.prepare('SELECT * FROM ab_peers WHERE ab_guid = ? LIMIT ? OFFSET ?').all(abGuid, pageSize, offset);

    res.json({
        data: rows.map(peerRow),
        total,
    });
}

router.get('/ab/peers', requireAuth, handleAbPeers);
router.post('/ab/peers', requireAuth, handleAbPeers);

// GET or POST /api/ab/tags/{guid}  — returns tag list as a raw JSON array
// Response: [{name, color}, ...]
function handleAbTags(req, res) {
    const tags = fetchAbTags(req.params.guid);
    res.json(tags);
}

router.get('/ab/tags/:guid', requireAuth, handleAbTags);
router.post('/ab/tags/:guid', requireAuth, handleAbTags);

// POST /api/ab/peer/add/{guid}  — add a single peer
router.post('/ab/peer/add/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const { id, alias, note, password, hash, tags, username, hostname, platform } = req.body || {};
    if (!id) return res.status(400).json({ error: 'peer id required' });

    db.prepare(`
        INSERT OR REPLACE INTO ab_peers (ab_guid, peer_id, alias, note, password, hash, tags, username, hostname, platform)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(req.params.guid, id, alias || '', note || '', password || '', hash || '', JSON.stringify(tags || []), username || '', hostname || '', platform || '');

    res.send('');
});

// PUT /api/ab/peer/update/{guid}  — update a single peer field
router.put('/ab/peer/update/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const body = req.body || {};
    const { id } = body;
    if (!id) return res.status(400).json({ error: 'peer id required' });

    const existing = db.prepare('SELECT * FROM ab_peers WHERE ab_guid=? AND peer_id=?').get(req.params.guid, id);
    if (!existing) return res.status(404).json({ error: 'peer not found' });

    const alias    = 'alias'    in body ? body.alias    : existing.alias;
    const note     = 'note'     in body ? body.note     : existing.note;
    const password = 'password' in body ? body.password : existing.password;
    const hash     = 'hash'     in body ? body.hash     : existing.hash;
    const tags     = 'tags'     in body ? JSON.stringify(body.tags) : existing.tags;
    const username = 'username' in body ? body.username : existing.username;
    const hostname = 'hostname' in body ? body.hostname : existing.hostname;
    const platform = 'platform' in body ? body.platform : existing.platform;

    db.prepare(`
        UPDATE ab_peers SET alias=?, note=?, password=?, hash=?, tags=?, username=?, hostname=?, platform=?
        WHERE ab_guid=? AND peer_id=?
    `).run(alias || '', note || '', password || '', hash || '', tags || '[]', username || '', hostname || '', platform || '', req.params.guid, id);

    res.send('');
});

// DELETE /api/ab/peer/{guid}  — body is JSON array of peer IDs ["id1","id2"]
router.delete('/ab/peer/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const ids = Array.isArray(req.body) ? req.body : [];

    const del = db.prepare('DELETE FROM ab_peers WHERE ab_guid=? AND peer_id=?');
    for (const id of ids) {
        del.run(req.params.guid, id);
    }

    res.send('');
});

// POST /api/ab/tag/add/{guid}  — add a tag
router.post('/ab/tag/add/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const { name, color } = req.body || {};
    if (!name) return res.status(400).json({ error: 'tag name required' });

    db.prepare('INSERT OR IGNORE INTO ab_tags (ab_guid, name, color) VALUES (?, ?, ?)').run(
        req.params.guid, name, color || 0
    );
    res.send('');
});

// PUT /api/ab/tag/rename/{guid}  — body {"old": "oldname", "new": "newname"}
router.put('/ab/tag/rename/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const oldName = req.body?.old;
    const newName = req.body?.new;
    if (!oldName || !newName) return res.status(400).json({ error: 'old and new required' });

    db.prepare('UPDATE ab_tags SET name=? WHERE ab_guid=? AND name=?').run(newName, req.params.guid, oldName);
    res.send('');
});

// PUT /api/ab/tag/update/{guid}  — update tag color; body {"name": "tag", "color": 123456}
router.put('/ab/tag/update/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const { name, color } = req.body || {};
    if (!name) return res.status(400).json({ error: 'tag name required' });

    db.prepare('UPDATE ab_tags SET color=? WHERE ab_guid=? AND name=?').run(color || 0, req.params.guid, name);
    res.send('');
});

// DELETE /api/ab/tag/{guid}  — body is JSON array of tag names ["tag1"]
router.delete('/ab/tag/:guid', requireAuth, (req, res) => {
    const db = getDb();
    const names = Array.isArray(req.body) ? req.body : [];

    const del = db.prepare('DELETE FROM ab_tags WHERE ab_guid=? AND name=?');
    for (const name of names) {
        del.run(req.params.guid, name);
    }

    res.send('');
});

module.exports = router;
