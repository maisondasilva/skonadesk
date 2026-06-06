# SkonaDesk

A self-hosted remote desktop management stack built on top of the [RustDesk](https://rustdesk.com) open-source server, adding the pro-tier features (API server, address books, device groups, JWT-authenticated relay, admin dashboard) that the official OSS build lacks — without requiring a RustDesk Pro licence.

Works with **standard, unmodified RustDesk clients** on Windows, macOS, Linux, iOS, and Android.

---

## Why This Exists

RustDesk's OSS server (`hbbs` + `hbbr`) handles rendezvous and relay perfectly well, but ships with no API layer. All the features that make RustDesk genuinely useful in a team — address books, device groups, shared peers, access controls — are locked behind the paid Pro tier.

Several community API implementations exist, but they all share the same fatal flaw: **as soon as a client logs in to the API server, remote desktop connections break entirely**, throwing `"Failed to secure tcp: deadline has elapsed"` on every connection attempt. Logging out restores connectivity instantly.

After nine hours of debugging the previous night's attempt, the root cause was traced to a deadlock in the RustDesk client's TCP handshake path. This stack fixes it at the source.

---

## The Bugs Fixed

### Bug 1 — "Failed to secure tcp: deadline has elapsed"

**Root cause:** When the RustDesk client has an active API session it calls `secure_tcp()` before sending a `PunchHoleRequest`. This function waits up to **18 seconds** for the server to send a `KeyExchange` message. The OSS `hbbs` never initiates one — it waits for the client to send first. Both sides wait indefinitely → 18-second timeout → connection fails.

This means: **with stock OSS hbbs, remote desktop is completely broken for any logged-in user.**

**Fix (Patch 1 in `rendezvous_server.rs`):** Immediately after accepting a new TCP connection, hbbs sends a dummy `TestNatResponse` message. The client's `secure_tcp()` receives this non-`KeyExchange` message, falls through its `_ => {}` catch-all branch, and returns `Ok(())`. The connection proceeds normally — identical to the behaviour when the user is not logged in.

**Security note:** The OSS hbbs never supported transport-layer encryption via `KeyExchange` anyway. End-to-end encryption between peers is unaffected — it happens at the peer level, not at the rendezvous level.

---

### Bug 2 — "Key mismatch" on Windows clients

**Root cause:** The `PunchHoleRequest` protobuf message includes a `licence_key` field. With `-k _`, hbbs validates this against the server's generated public key. However, standard Windows RustDesk binaries read the key from the **embedded binary licence** first (before checking the user's config file). That embedded key is always the default:

```
OeVuKk5nlHiXp+APNn0Y3pC1Iwpwn44JGqrQCsWqmBw=
```

This will never match any custom server's key → **"Key mismatch"** for every Windows client, regardless of what key is configured in settings.

**Fix (Patch 2 in `rendezvous_server.rs`):** The `licence_key` check is skipped entirely. The `key` parameter is suppressed with `let _ = key;`. Authentication is handled by the JWT relay auth layer instead (see below).

---

## Architecture

**With SSL / domain (recommended for internet-facing deployments):**

```
RustDesk Client
      │
      ├── TCP :21116 / UDP :21116 ──► hbbs  (patched rendezvous server)
      │                                  │ validates JWT in PunchHoleRequest
      ├── TCP :21117 ───────────────► hbbr  (stock relay server)
      │
      ├── HTTPS :443 ──► Nginx Proxy Manager ──► API       :21114  (Node.js)
      │                                      └──► Dashboard :8080   (PHP)
      │
      └── WebSocket :21118 ──► hbbs  (websocket clients)
```

**Without SSL / direct HTTP (homelab, trusted network):**

```
RustDesk Client
      │
      ├── TCP :21116 / UDP :21116 ──► hbbs  (patched rendezvous server)
      ├── TCP :21117 ───────────────► hbbr  (stock relay server)
      ├── HTTP :21114 ─────────────► API    (Node.js, direct)
      └── HTTP :8080  ─────────────► Dashboard (PHP, direct)
```

All four services run as Docker containers on a single host, connected via an internal `skonadesk` bridge network.

---

## Features

- **Address books** — personal per-user peer lists with aliases, notes, and tags; fully synced to the RustDesk client
- **Device groups** — organise devices into named groups visible in the client's "Accessible Devices" tab
- **User management** — create/disable/delete users, promote to admin
- **Heartbeat tracking** — devices register automatically on first heartbeat; last-seen timestamps kept
- **JWT relay authentication** — only clients holding a valid login token can initiate connections through hbbs/hbbr; unauthenticated clients are rejected at the Rust level before any relay bandwidth is consumed
- **Admin dashboard** — PHP web UI with dark/light mode, live stats, CRUD for all entities
- **Audit log** — timestamped record of connections and API events
- **Server info page** — public key display with copy button, client setup instructions

---

## Deployment Scenarios

SkonaDesk supports three common deployment patterns. Pick the one that fits your setup.

---

### Scenario A — VPS with a domain name and SSL *(recommended for internet-facing)*

The most secure option. A reverse proxy (Nginx Proxy Manager is the easiest) terminates HTTPS and forwards traffic to the API and dashboard containers. RustDesk clients connect to your domain on port 443.

**What you need:**
- A Linux VPS with a public IP (1 vCPU / 1 GB RAM is plenty for small teams)
- Docker + Docker Compose v2
- Nginx Proxy Manager (or any reverse proxy — Traefik, Caddy, etc. all work)
- A domain name with an **A record** pointing to your VPS IP

**Ports to open on the VPS firewall / hosting control panel:**

| Port | Protocol | Purpose |
|------|----------|---------|
| 21115 | TCP | NAT type test |
| 21116 | TCP + UDP | Rendezvous (hbbs) |
| 21117 | TCP | Relay (hbbr) |
| 21118 | TCP | WebSocket rendezvous |
| 21119 | TCP | WebSocket relay |
| 443 | TCP | API + Dashboard (via reverse proxy) |
| 80 | TCP | HTTP → HTTPS redirect (optional but polite) |

**No port forwarding needed** — VPS providers give you a public IP directly. Open the ports in your hosting panel's firewall (e.g. Hetzner Firewall, AWS Security Group, DigitalOcean Firewall).

---

### Scenario B — Home server / Proxmox VM, accessible from the internet *(DDNS or static IP)*

Common homelab setup: a Proxmox VM or dedicated machine on your home network that you expose to the internet via your router. You may have a static IP, or a DDNS hostname (e.g. from DuckDNS or your router's built-in DDNS service).

**What you need:**
- A machine running Linux (VM or bare metal) with Docker
- A static IP **or** a DDNS hostname pointing to your home IP
- Access to your router's port forwarding settings

**Router port forwarding — forward these from your public IP to the machine's LAN IP:**

| External port | Internal port | Protocol | Purpose |
|--------------|---------------|----------|---------|
| 21115 | 21115 | TCP | NAT type test |
| 21116 | 21116 | TCP + UDP | Rendezvous |
| 21117 | 21117 | TCP | Relay |
| 21118 | 21118 | TCP | WebSocket rendezvous |
| 21119 | 21119 | TCP | WebSocket relay |
| 21114 | 21114 | TCP | API *(if not using SSL)* |
| 8080 | 8080 | TCP | Dashboard *(if not using SSL)* |

> If you have a domain or DDNS hostname and want SSL, add Nginx Proxy Manager and configure it the same as Scenario A. If you are on a plain IP/DDNS with no SSL, use Scenario B's HTTP mode — see `.env` notes below.

**Security note for home deployments:** Ports 21114 (API) and 8080 (dashboard) expose services over plain HTTP if you are not using SSL. Do not forward these ports unless you understand what you are exposing. Consider restricting dashboard access to your LAN only and only forwarding the RustDesk protocol ports (21115–21119) externally.

---

### Scenario C — LAN only / trusted internal network *(no internet exposure)*

The simplest setup. No port forwarding, no domain, no SSL. Everything runs on your internal network. Useful for an office environment where all machines are on the same subnet, or a homelab where you only need remote access via VPN.

**What you need:**
- Any Linux machine on the LAN with Docker
- Nothing else — no domain, no router changes

All clients connect directly to the machine's LAN IP. Address books, groups, and auth all work normally. The only limitation is that clients outside your LAN (e.g. you at home) cannot reach the server unless you are connected via VPN first.

---

## Requirements

- Linux host (tested on Ubuntu 22.04 / 24.04); 1 vCPU / 1 GB RAM is sufficient for small teams
- Docker + Docker Compose v2
- **Optional:** Nginx Proxy Manager or any reverse proxy for SSL (Scenario A only)
- **Optional:** A domain name or DDNS hostname (Scenario A/B with SSL only)

---

## Installation

### 1. Build the patched hbbs image

Clone the patched server source (or use your fork) on any machine with Docker installed. A machine with 8+ cores and 16+ GB RAM will compile in ~2 minutes; a single-core VPS may take 15–20 minutes.

```bash
git clone https://github.com/YOUR_FORK/rustdesk-server.git
cd rustdesk-server
git submodule update --init --recursive
docker build --no-cache -f Dockerfile.skonadesk -t skonadesk-hbbs:latest .
```

If you compiled on a different machine, transfer the image to your server:

```bash
# On build machine
docker save skonadesk-hbbs:latest | gzip > /tmp/skonadesk-hbbs.tar.gz
scp /tmp/skonadesk-hbbs.tar.gz user@your-server:/tmp/

# On server
docker load < /tmp/skonadesk-hbbs.tar.gz
```

### 2. Configure the stack

Copy this repository to your server (e.g. `/srv/skonadesk`) and edit `.env`:

```bash
cp .env.example .env
nano .env
```

**For Scenario A (domain + SSL):**

```env
RELAY_HOST=your.domain.com
DOMAIN=your.domain.com
API_PUBLIC_URL=https://your.domain.com

JWT_SECRET=<run: openssl rand -hex 32>
APP_SECRET=<run: openssl rand -hex 32>

ADMIN_USER=youradminname        # do NOT use 'admin'
ADMIN_PASS=a-strong-password

DB_PATH=/data/skonadesk.db
PORT=21114
```

**For Scenario B/C (IP address or DDNS, no SSL):**

```env
RELAY_HOST=192.168.1.50         # or your.ddns.net
DOMAIN=192.168.1.50             # same value
API_PUBLIC_URL=http://192.168.1.50:21114

JWT_SECRET=<run: openssl rand -hex 32>
APP_SECRET=<run: openssl rand -hex 32>

ADMIN_USER=youradminname
ADMIN_PASS=a-strong-password

DB_PATH=/data/skonadesk.db
PORT=21114
```

> `API_PUBLIC_URL` is the address the **RustDesk client** uses to reach the API. For SSL setups this is your HTTPS domain; for direct HTTP it is `http://IP:21114`. The dashboard's Client Setup Guide page reads this value and shows copy-pasteable config for your users.

Generate strong secrets with:

```bash
openssl rand -hex 32
```

### 3. Start the stack

```bash
cd /srv/skonadesk
docker compose up -d
```

Verify all four containers are running:

```bash
docker compose ps
```

You should see `skonadesk-hbbs`, `skonadesk-hbbr`, `skonadesk-api`, and `skonadesk-dashboard` all in `running` state.

### 4a. SSL setup via Nginx Proxy Manager *(Scenario A only)*

Create two proxy hosts in NPM:

| Domain | Forward to | SSL |
|--------|-----------|-----|
| `your.domain.com` | `skonadesk-api:21114` | Let's Encrypt |
| `dashboard.your.domain.com` | `skonadesk-dashboard:80` | Let's Encrypt |

> **Note:** The NPM container must be on the same Docker network as the stack, or use the host's LAN IP instead of the container name.

### 4b. No SSL *(Scenario B/C)*

No extra configuration needed. Access the dashboard directly at `http://YOUR-IP:8080`. The Client Setup Guide page in the dashboard will show all values pre-filled for your IP.

### 5. Configure the RustDesk client

Open the **Client Setup Guide** page in the dashboard — it shows all values pre-filled with copy buttons. Alternatively, configure manually in the RustDesk client under **Settings → Network**:

| Field | Scenario A value | Scenario B/C value |
|-------|-----------------|-------------------|
| ID/Relay Server | `your.domain.com` | `192.168.1.50` (or DDNS host) |
| API Server | `https://your.domain.com` | `http://192.168.1.50:21114` |
| Key | *(from dashboard Server page)* | *(from dashboard Server page)* |

The public key is shown on the **Server** page of the admin dashboard with a copy button.

---

## Security & Hardening

**SkonaDesk is provided as-is. You are responsible for securing your own deployment.** The notes below describe the security model and known caveats, but they are not a substitute for understanding what you are running and who can reach it.

### Minimum hardening checklist

- [ ] Change `ADMIN_USER` and `ADMIN_PASS` in `.env` before first run. Do not use `admin` as the username — it is the first credential attackers try.
- [ ] Use randomly generated values for `JWT_SECRET` and `APP_SECRET` (`openssl rand -hex 32`). Never use the placeholder values from `.env.example`.
- [ ] For internet-facing deployments, use SSL (Scenario A). Do not expose the API or dashboard over plain HTTP on a public IP.
- [ ] If using Scenario B (home server exposed to internet), consider **not** forwarding ports 21114 and 8080 externally. The RustDesk protocol ports (21115–21119) are enough for remote desktop to work — the API and dashboard can remain LAN-only if your users are comfortable managing them via VPN or at home.
- [ ] Restrict SSH access to your server. Use key-based auth, disable password login, and consider a non-standard SSH port or fail2ban.
- [ ] Back up `./data/id_ed25519` (the server private key). If this file is lost (e.g. volume deleted), all clients will need to be reconfigured. There is no recovery path.
- [ ] Keep Docker images updated periodically, especially `rustdesk/rustdesk-server` (hbbr) which runs as stock and receives upstream security fixes.

### What SkonaDesk does NOT provide

- **Access control between specific peers** — any authenticated user can attempt to connect to any device they know the ID of. Peer-level passwords (set in the RustDesk client) are the only per-device access control.
- **Audit of session content** — connections are end-to-end encrypted between peers. The server logs that a connection was made but cannot see what was transmitted.
- **Account lockout / brute-force protection** — the API has no rate limiting on `/login`. For internet-facing deployments, put a reverse proxy with rate limiting in front (Nginx or Caddy can do this in a few lines).
- **Multi-factor authentication** — planned for a future release. For now, strong passwords and VPN access are the recommended mitigations.

---

## Security Notes & Caveats

### JWT relay authentication

When `RELAY_JWT_SECRET` is set in the hbbs environment (which this stack does automatically — it mirrors `JWT_SECRET`), the patched hbbs validates the JWT token embedded in every `PunchHoleRequest`. Clients without a valid, unexpired token receive a `LICENSE_MISMATCH` response before any relay traffic flows.

**Caveat:** This only restricts the **initiating** side of a connection. The callee (the machine being connected *to*) does not need to be logged in. This is intentional — it allows you to connect to e.g. an unattended server that has no user logged in to the API.

### The licence key check is removed

Patch 2 disables the built-in key verification. This means any RustDesk client that knows your server's hostname can register with hbbs. The key check was not strong security to begin with (the key is stored in plaintext in every client's config file and transmitted over the wire). Real access control is provided by:

- Per-device passwords (end-to-end encrypted, not visible to the server)
- JWT relay auth (above) — prevents relay abuse by unauthenticated clients
- The API's user/group system for address book and group access

### Transport encryption at the rendezvous layer

Patch 1 prevents the `KeyExchange` handshake from completing. This means the TCP connection between the client and hbbs is **not transport-encrypted**. This is identical to the behaviour of the stock OSS hbbs — it never supported this either. The sensitive data (screen content, input events, clipboard) is encrypted end-to-end between the two RustDesk peers and never passes through hbbs in plaintext.

### Default credentials

Change `ADMIN_USER` and `ADMIN_PASS` in `.env` before first run. The database is initialised from these values on startup. If you need to change them after first run, update the user directly:

```bash
docker exec -it skonadesk-api node -e "
const db = require('./db').getDb();
const bcrypt = require('bcryptjs');
db.prepare(\"UPDATE users SET password=? WHERE username=?\")
  .run(bcrypt.hashSync('new-password', 10), 'youradminname');
console.log('done');
"
```

---

## Rebuilding after code changes

If you modify `rendezvous_server.rs` or any other Rust source:

```bash
# On build machine
cd rustdesk-server
docker build --no-cache -f Dockerfile.skonadesk -t skonadesk-hbbs:latest .
docker save skonadesk-hbbs:latest | gzip > /tmp/skonadesk-hbbs.tar.gz
scp /tmp/skonadesk-hbbs.tar.gz user@your-vps:/tmp/

# On VPS
docker load < /tmp/skonadesk-hbbs.tar.gz
cd /srv/skonadesk
docker compose up -d --force-recreate hbbs
```

If you modify the Node.js API:

```bash
# On VPS
cd /srv/skonadesk
docker compose build api && docker compose up -d --force-recreate api
```

Dashboard PHP files are served from a live volume mount — changes take effect immediately with no rebuild needed.

---

## Stack File Layout

```
skonadesk-stack/
├── .env                    # secrets and config (never commit this)
├── .env.example            # safe template to commit
├── docker-compose.yml
├── data/                   # persistent data (auto-created)
│   ├── skonadesk.db        # SQLite database
│   ├── id_ed25519          # hbbs private key (auto-generated)
│   └── id_ed25519.pub      # hbbs public key
├── api/                    # Node.js API server
│   ├── Dockerfile
│   ├── server.js
│   ├── db.js
│   ├── auth.js
│   └── routes/
│       ├── login.js
│       ├── ab.js           # address book (14 endpoints)
│       ├── heartbeat.js
│       └── admin.js        # users, peers, groups, stats
└── dashboard/              # PHP 8.2 admin UI
    ├── Dockerfile
    ├── index.php           # login
    ├── home.php            # dashboard / stats
    ├── devices.php
    ├── addressbook.php
    ├── users.php
    ├── groups.php
    ├── audit.php
    ├── server.php
    ├── setup.php           # client setup guide (copy-pasteable config)
    ├── profile.php         # user profile + password change
    └── includes/
        ├── config.php
        ├── auth.php
        ├── api.php
        └── layout.php
```

---

## Credits

Built on top of the [RustDesk](https://github.com/rustdesk/rustdesk-server) open-source server. The protocol, protobuf definitions, and core rendezvous/relay logic are RustDesk's work. This project adds the API and dashboard layer, and patches the two bugs described above that prevent any third-party API from working with the stock client.
