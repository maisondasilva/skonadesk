// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
const express = require('express');
const { getDb } = require('./db');

const loginRouter     = require('./routes/login');
const heartbeatRouter = require('./routes/heartbeat');
const abRouter        = require('./routes/ab');
const adminRouter     = require('./routes/admin');

const app  = express();
const PORT = parseInt(process.env.PORT || '21114');

app.set('trust proxy', true);

app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true }));

app.use((req, res, next) => {
    const ts = new Date().toISOString();
    console.log(`[${ts}] ${req.method} ${req.path}`);
    next();
});

app.use('/api', loginRouter);
app.use('/api', heartbeatRouter);
app.use('/api', abRouter);
app.use('/api', adminRouter);

app.get('/api/oidc/auth', (req, res) => res.status(404).json({ error: 'OIDC not configured' }));
app.post('/api/oidc/auth', (req, res) => res.status(404).json({ error: 'OIDC not configured' }));
app.get('/api/oidc/auth-query', (req, res) => res.status(404).json({ error: 'OIDC not configured' }));
app.post('/api/plugin-sign', (req, res) => res.json({ data: '' }));

app.use((req, res) => {
    console.warn(`[404] ${req.method} ${req.path}`);
    res.status(404).json({ error: 'Not found', total: 0, data: [] });
});

app.use((err, req, res, next) => {
    console.error('[error]', err.message || err);
    res.status(500).json({ error: 'Internal server error' });
});

getDb();

app.listen(PORT, '0.0.0.0', () => {
    console.log(`SkonaDesk API listening on :${PORT}`);
});
