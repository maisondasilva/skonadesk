<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$info   = api_get('/server-info');
$pubKey = $info['public_key'] ?? '';
$domain = $info['domain']     ?? '';
$apiUrl = $domain ? 'https://' . $domain : (API_PUBLIC_URL ?: '');

page_open('Server Info');
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px" class="server-grid">

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="key"></svg>
        Server Public Key
      </div>
    </div>
    <div class="card-body">
      <p style="font-size:var(--font-sm);color:var(--text-muted);margin-bottom:16px">
        Copy this key into the RustDesk client's <strong>Key</strong> field under Network settings
        to ensure you're connecting to this specific server.
      </p>
      <?php if ($pubKey): ?>
      <div class="copy-wrap">
        <code class="code-block" id="pubKey"><?= htmlspecialchars($pubKey) ?></code>
        <button class="copy-btn" data-copy="#pubKey" title="Copy key">
          <svg data-feather="copy"></svg>
        </button>
      </div>
      <?php else: ?>
      <div class="alert alert-warning">
        Public key not found. Check that <code>/data/id_ed25519.pub</code> is mounted correctly.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="server"></svg>
        Server Addresses
      </div>
    </div>
    <div class="card-body">
      <?php if ($domain): ?>
      <div class="info-row">
        <span class="info-label">Rendezvous (hbbs)</span>
        <div class="info-value copy-wrap">
          <code class="code-block" id="hbbsHost"><?= htmlspecialchars($domain) ?></code>
          <button class="copy-btn" data-copy="#hbbsHost" title="Copy"><svg data-feather="copy"></svg></button>
        </div>
      </div>
      <div class="info-row">
        <span class="info-label">Relay (hbbr)</span>
        <div class="info-value copy-wrap">
          <code class="code-block" id="hbbrHost"><?= htmlspecialchars($domain) ?></code>
          <button class="copy-btn" data-copy="#hbbrHost" title="Copy"><svg data-feather="copy"></svg></button>
        </div>
      </div>
      <div class="info-row">
        <span class="info-label">API Server</span>
        <div class="info-value copy-wrap">
          <code class="code-block" id="apiUrl">https://<?= htmlspecialchars($domain) ?></code>
          <button class="copy-btn" data-copy="#apiUrl" title="Copy"><svg data-feather="copy"></svg></button>
        </div>
      </div>
      <?php else: ?>
      <div class="alert alert-warning">Domain not configured. Set <code>DOMAIN</code> in .env</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg data-feather="book-open"></svg>
      Client Setup Instructions
    </div>
  </div>
  <div class="card-body">
    <p style="font-size:var(--font-sm);color:var(--text-secondary);margin-bottom:20px">
      Configure any RustDesk client to connect to this server by following these steps:
    </p>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px">

      <div style="background:var(--surface-input);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
          <span style="background:var(--color-primary);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:800;flex-shrink:0">1</span>
          <strong>Open Settings</strong>
        </div>
        <p style="font-size:var(--font-sm);color:var(--text-muted)">
          In the RustDesk client, click the <strong>≡ menu</strong> → <strong>Settings</strong> → <strong>Network</strong> tab.
        </p>
      </div>

      <div style="background:var(--surface-input);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
          <span style="background:var(--color-primary);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:800;flex-shrink:0">2</span>
          <strong>Set Server Addresses</strong>
        </div>
        <p style="font-size:var(--font-sm);color:var(--text-muted)">
          Set <strong>ID/Relay Server</strong> to <code style="color:var(--color-primary)"><?= htmlspecialchars($domain ?: 'your-server') ?></code><br/>
          Set <strong>API Server</strong> to <code style="color:var(--color-primary)">https://<?= htmlspecialchars($domain ?: 'your-server') ?></code>
        </p>
      </div>

      <div style="background:var(--surface-input);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
          <span style="background:var(--color-primary);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:800;flex-shrink:0">3</span>
          <strong>Set the Key</strong>
        </div>
        <p style="font-size:var(--font-sm);color:var(--text-muted)">
          Paste the <strong>Public Key</strong> shown above into the <strong>Key</strong> field.
          Leave blank if you skip key verification (less secure).
        </p>
      </div>

      <div style="background:var(--surface-input);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
          <span style="background:var(--color-primary);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:800;flex-shrink:0">4</span>
          <strong>Login</strong>
        </div>
        <p style="font-size:var(--font-sm);color:var(--text-muted)">
          Click <strong>Login</strong> in the top-right corner of the RustDesk main window
          and use your account credentials. Address book and groups will then sync.
        </p>
      </div>

    </div>

    <div class="alert alert-info" style="margin-top:20px">
      <strong>Note:</strong> The device <em>initiating</em> a connection must be logged in — this is enforced
      at the server level. The device being connected to does not need to be logged in. Login also enables
      address book sync and group views.
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg data-feather="shield"></svg>
      Required Firewall Ports
    </div>
  </div>
  <div class="card-body">
    <table style="width:100%;border-collapse:collapse;font-size:var(--font-sm)">
      <thead>
        <tr style="border-bottom:1px solid var(--border-color)">
          <th style="text-align:left;padding:6px 12px;color:var(--text-muted);font-weight:500">Port</th>
          <th style="text-align:left;padding:6px 12px;color:var(--text-muted);font-weight:500">Protocol</th>
          <th style="text-align:left;padding:6px 12px;color:var(--text-muted);font-weight:500">Purpose</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ([
          ['21115', 'TCP',     'NAT type test'],
          ['21116', 'TCP/UDP', 'Rendezvous (hbbs) — ID registration &amp; hole punching'],
          ['21117', 'TCP',     'Relay (hbbr) — fallback traffic when P2P fails'],
          ['21118', 'TCP',     'WebSocket rendezvous (browser clients)'],
          ['21119', 'TCP',     'WebSocket relay (browser clients)'],
          ['443',   'TCP',     'API &amp; Dashboard (via Nginx Proxy Manager)'],
        ] as [$port, $proto, $desc]): ?>
        <tr style="border-bottom:1px solid var(--border-color)">
          <td style="padding:6px 12px"><code><?= $port ?></code></td>
          <td style="padding:6px 12px;color:var(--text-muted)"><?= $proto ?></td>
          <td style="padding:6px 12px;color:var(--text-muted)"><?= $desc ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="font-size:var(--font-sm);color:var(--text-muted);margin:12px 0 0">
      All ports must be open on both your VPS firewall/security group <strong>and</strong> any upstream router or
      hosting panel. Port 443 is handled by Nginx Proxy Manager — no direct exposure of the API container needed.
    </p>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg data-feather="shield"></svg>
      Security Notes
    </div>
  </div>
  <div class="card-body">
    <div style="display:grid;gap:12px;font-size:var(--font-sm)">
      <div class="info-row">
        <span class="info-label">Key Check</span>
        <div class="info-value">
          <span class="badge badge-info">Patched</span>
          <span style="color:var(--text-muted);margin-left:8px">
            Standard RustDesk binaries embed a hardcoded key that never matches a custom server.
            The key check is bypassed — auth is handled by JWT login instead.
          </span>
        </div>
      </div>
      <div class="info-row">
        <span class="info-label">Transport</span>
        <div class="info-value">
          <span class="badge badge-active">HTTPS</span>
          <span style="color:var(--text-muted);margin-left:8px">API protected by TLS via Nginx Proxy Manager.</span>
        </div>
      </div>
      <div class="info-row">
        <span class="info-label">End-to-End</span>
        <div class="info-value">
          <span class="badge badge-active">Active</span>
          <span style="color:var(--text-muted);margin-left:8px">
            Peer-to-peer sessions use RustDesk's built-in E2E encryption — the server never sees session content.
          </span>
        </div>
      </div>
      <div class="info-row">
        <span class="info-label">Relay Auth</span>
        <div class="info-value">
          <span class="badge badge-active">Active</span>
          <span style="color:var(--text-muted);margin-left:8px">
            JWT token validated server-side in patched hbbs. Connections from clients without a valid
            login token are rejected at the rendezvous layer before any relay bandwidth is used.
            Only the initiating side must be logged in — the callee does not need to be.
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg data-feather="alert-triangle"></svg>
      Troubleshooting
    </div>
  </div>
  <div class="card-body" style="display:grid;gap:16px;font-size:var(--font-sm)">
    <div>
      <strong>Key mismatch</strong>
      <p style="color:var(--text-muted);margin:4px 0 0">
        Two common causes: <strong>(1) The initiating client is not logged in</strong> — log in via the account
        icon in RustDesk and try again. <strong>(2) The server key changed</strong> (e.g. after a volume wipe)
        — clear the RustDesk client settings (<em>Network → reset</em>), re-enter the server details and key
        from this page, then reconnect.
      </p>
    </div>
    <div>
      <strong>Failed to secure TCP / Device offline when logged in</strong>
      <p style="color:var(--text-muted);margin:4px 0 0">
        This is the bug SkonaDesk was built to fix. If you are seeing it, the hbbs container may be running
        the unpatched <code>rustdesk/rustdesk-server</code> image rather than <code>skonadesk-hbbs</code>.
        Check the Server Addresses card above for the running image.
      </p>
    </div>
    <div>
      <strong>Address book / groups not syncing</strong>
      <p style="color:var(--text-muted);margin:4px 0 0">
        The RustDesk client must be able to reach the API at
        <?php if ($apiUrl): ?><code><?= htmlspecialchars($apiUrl) ?></code>.<?php endif; ?>
        Check that Nginx Proxy Manager has a proxy host pointing your domain → <code>skonadesk-api:21114</code>
        with a valid SSL certificate.
      </p>
    </div>
    <div>
      <strong>Can connect on LAN but not remotely</strong>
      <p style="color:var(--text-muted);margin:4px 0 0">
        Ports 21115–21119 are not open on the VPS firewall or hosting control panel. Check all five ports
        in the table above — both TCP and UDP on 21116.
      </p>
    </div>
    <div>
      <strong>Connection works without login but fails when logged in (or vice versa)</strong>
      <p style="color:var(--text-muted);margin:4px 0 0">
        SkonaDesk enforces that the <em>initiating</em> machine is logged in — if you are logged out on the
        calling side the connection is rejected. Conversely, the machine being connected <em>to</em> does not
        need to be logged in. If connections suddenly break after logging in, check the API URL in the client
        Network settings matches exactly what is shown on this page.
      </p>
    </div>
  </div>
</div>

<?php page_close(); ?>
