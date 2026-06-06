// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
const jwt = require('jsonwebtoken');

const SECRET = process.env.JWT_SECRET || 'changeme';
const EXPIRY = '7d';

function signToken(payload) {
    return jwt.sign(payload, SECRET, { expiresIn: EXPIRY });
}

function verifyToken(token) {
    try {
        return jwt.verify(token, SECRET);
    } catch {
        return null;
    }
}

function requireAuth(req, res, next) {
    const auth = req.headers['authorization'] || '';
    const token = auth.startsWith('Bearer ') ? auth.slice(7) : '';
    if (!token) return res.status(401).json({ error: 'Unauthorized' });
    const payload = verifyToken(token);
    if (!payload) return res.status(401).json({ error: 'Invalid or expired token' });
    req.user = payload;
    next();
}

function optionalAuth(req, res, next) {
    const auth = req.headers['authorization'] || '';
    const token = auth.startsWith('Bearer ') ? auth.slice(7) : '';
    if (token) {
        const payload = verifyToken(token);
        if (payload) req.user = payload;
    }
    next();
}

module.exports = { signToken, verifyToken, requireAuth, optionalAuth };
