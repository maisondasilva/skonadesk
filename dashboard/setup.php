<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$apiUrl    = API_PUBLIC_URL ?: 'http://your-server:21114';
$domain    = parse_url($apiUrl, PHP_URL_HOST) ?: 'your-server';
$dashUrl   = (API_USE_SSL ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? $domain);
$useSSL    = API_USE_SSL;
$keyFile   = rtrim(DATA_PATH, '/') . '/id_ed25519.pub';
$pubKey    = '';
if (is_readable($keyFile)) {
    $pubKey = trim(file_get_contents($keyFile));
}

page_open('Client Setup Guide');
?>

<style>
.setup-step {
    counter-increment: step;
    display: grid;
    gap: 8px;
}
.setup-steps { counter-reset: step; display: grid; gap: 24px; }
.step-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: var(--font-sm);
}
.step-num {
    width: 26px; height: 26px;
    border-radius: 50%;
    background: var(--teal);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem; font-weight: 700; flex-shrink: 0;
}
.copy-field {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 8px 12px;
    font-family: monospace;
    font-size: 0.8rem;
    word-break: break-all;
}
.copy-field span { flex: 1; }
.copy-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    padding: 4px;
    display: flex;
    align-items: center;
    border-radius: 4px;
    transition: color .15s, background .15s;
    flex-shrink: 0;
}
.copy-btn:hover { color: var(--teal); background: var(--bg-hover); }
.copy-btn svg { width: 15px; height: 15px; }
.note-box {
    background: var(--bg-card);
    border-left: 3px solid var(--teal);
    padding: 10px 14px;
    border-radius: 0 var(--radius) var(--radius) 0;
    font-size: var(--font-sm);
    color: var(--text-muted);
}
.port-table td, .port-table th {
    padding: 6px 12px;
    font-size: var(--font-sm);
}
.port-table th { color: var(--text-muted); font-weight: 500; }
</style>

<div style="max-width:680px;display:grid;gap:24px">

  <?php if (!$pubKey): ?>
  <div class="alert alert-danger">
    Public key file not found at <code><?= htmlspecialchars($keyFile) ?></code>.
    Make sure the <code>./data</code> volume is mounted correctly and hbbs has started at least once.
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="info"></svg>
        Your Server Details
      </div>
    </div>
    <div class="card-body" style="display:grid;gap:12px">
      <?php if (!$useSSL): ?>
      <div class="note-box" style="border-color:var(--warning)">
        <strong>HTTP mode</strong> — you are running without SSL. All traffic between the RustDesk client
        and this API is unencrypted. This is fine for a trusted home network or VLAN; for anything
        internet-facing, consider adding a domain and Nginx Proxy Manager with a free Let's Encrypt certificate.
      </div>
      <?php endif; ?>
      <div>
        <div style="font-size:var(--font-sm);color:var(--text-muted);margin-bottom:6px">Rendezvous / Relay server (ID server)</div>
        <div class="copy-field">
          <span id="val-domain"><?= htmlspecialchars($domain) ?></span>
          <button class="copy-btn" onclick="copyVal('val-domain', this)" title="Copy">
            <svg data-feather="copy"></svg>
          </button>
        </div>
      </div>
      <div>
        <div style="font-size:var(--font-sm);color:var(--text-muted);margin-bottom:6px">API Server URL <span style="font-style:italic">(enter this in the RustDesk client Network settings)</span></div>
        <div class="copy-field">
          <span id="val-apiurl"><?= htmlspecialchars($apiUrl) ?></span>
          <button class="copy-btn" onclick="copyVal('val-apiurl', this)" title="Copy">
            <svg data-feather="copy"></svg>
          </button>
        </div>
      </div>
      <div>
        <div style="font-size:var(--font-sm);color:var(--text-muted);margin-bottom:6px">Dashboard URL</div>
        <div class="copy-field">
          <span id="val-dashurl"><?= htmlspecialchars($dashUrl) ?></span>
          <button class="copy-btn" onclick="copyVal('val-dashurl', this)" title="Copy">
            <svg data-feather="copy"></svg>
          </button>
        </div>
      </div>
      <div>
        <div style="font-size:var(--font-sm);color:var(--text-muted);margin-bottom:6px">
          Public Key
          <?php if (!$pubKey): ?><span style="color:var(--danger)"> — not found</span><?php endif; ?>
        </div>
        <div class="copy-field">
          <span id="val-key"><?= $pubKey ? htmlspecialchars($pubKey) : '(key not available)' ?></span>
          <?php if ($pubKey): ?>
          <button class="copy-btn" onclick="copyVal('val-key', this)" title="Copy">
            <svg data-feather="copy"></svg>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="monitor"></svg>
        Step-by-Step Client Configuration
      </div>
    </div>
    <div class="card-body">
      <ol class="setup-steps">

        <li class="setup-step">
          <div class="step-label">
            <span class="step-num">1</span>
            Download the RustDesk client
          </div>
          <p style="font-size:var(--font-sm);color:var(--text-muted);margin:0">
            Download the latest <strong>standard</strong> RustDesk client from
            <a href="https://rustdesk.com/download" target="_blank" rel="noopener"
               style="color:var(--teal)">rustdesk.com/download</a>.
            No Pro licence or custom build is required — the standard client works with SkonaDesk out of the box.
          </p>
        </li>

        <li class="setup-step">
          <div class="step-label">
            <span class="step-num">2</span>
            Open Network Settings
          </div>
          <p style="font-size:var(--font-sm);color:var(--text-muted);margin:0">
            In the RustDesk client: click the <strong>three-dot menu (⋮)</strong> → <strong>Network</strong> → unlock if required.
          </p>
        </li>

        <li class="setup-step">
          <div class="step-label">
            <span class="step-num">3</span>
            Set the ID / Relay server
          </div>
          <div style="display:grid;gap:8px">
            <div>
              <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:4px">ID Server (rendezvous)</div>
              <div class="copy-field">
                <span id="val-id-server"><?= htmlspecialchars($domain) ?></span>
                <button class="copy-btn" onclick="copyVal('val-id-server', this)" title="Copy">
                  <svg data-feather="copy"></svg>
                </button>
              </div>
            </div>
            <div>
              <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:4px">Relay Server</div>
              <div class="copy-field">
                <span id="val-relay"><?= htmlspecialchars($domain) ?></span>
                <button class="copy-btn" onclick="copyVal('val-relay', this)" title="Copy">
                  <svg data-feather="copy"></svg>
                </button>
              </div>
            </div>
            <div>
              <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:4px">API Server</div>
              <div class="copy-field">
                <span id="val-api2"><?= htmlspecialchars($apiUrl) ?></span>
                <button class="copy-btn" onclick="copyVal('val-api2', this)" title="Copy">
                  <svg data-feather="copy"></svg>
                </button>
              </div>
            </div>
            <?php if ($pubKey): ?>
            <div>
              <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:4px">Key</div>
              <div class="copy-field">
                <span id="val-key2"><?= htmlspecialchars($pubKey) ?></span>
                <button class="copy-btn" onclick="copyVal('val-key2', this)" title="Copy">
                  <svg data-feather="copy"></svg>
                </button>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <div class="note-box">
            <?php if ($useSSL): ?>
              The <strong>API Server</strong> field is the full HTTPS URL above. RustDesk contacts it on port 443 via Nginx Proxy Manager.
            <?php else: ?>
              <strong>HTTP mode:</strong> The <strong>API Server</strong> field must include the port, e.g. <code><?= htmlspecialchars($apiUrl) ?></code>.
              The RustDesk client contacts it directly — no proxy required.
            <?php endif; ?>
          </div>
        </li>

        <li class="setup-step">
          <div class="step-label">
            <span class="step-num">4</span>
            Log in
          </div>
          <p style="font-size:var(--font-sm);color:var(--text-muted);margin:0">
            In the RustDesk client click the <strong>account icon</strong> (top right) and sign in with your
            SkonaDesk username and password. Your address book and groups will sync automatically.
          </p>
          <div class="note-box" style="border-color:var(--warning)">
            <strong>Important:</strong> The machine <em>initiating</em> the connection must be logged in.
            The machine being connected to does not need to be.
          </div>
        </li>

        <li class="setup-step">
          <div class="step-label">
            <span class="step-num">5</span>
            Test the connection
          </div>
          <p style="font-size:var(--font-sm);color:var(--text-muted);margin:0">
            Enter another machine's Device ID and click Connect. If the connection fails, check the
            <a href="/server.php" style="color:var(--teal)">Server page</a> and verify the firewall ports below are open.
          </p>
        </li>

      </ol>
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
      <table class="port-table" style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="border-bottom:1px solid var(--border)">
            <th style="text-align:left">Port</th>
            <th style="text-align:left">Protocol</th>
            <th style="text-align:left">Purpose</th>
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
          <tr style="border-bottom:1px solid var(--border)">
            <td><code><?= $port ?></code></td>
            <td style="color:var(--text-muted)"><?= $proto ?></td>
            <td style="color:var(--text-muted)"><?= $desc ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p style="font-size:var(--font-sm);color:var(--text-muted);margin:12px 0 0">
        All ports must be open on both your VPS firewall/security group <strong>and</strong> any upstream router or hosting panel.
        Port 443 is handled by Nginx Proxy Manager — no direct exposure of the API container needed.
      </p>
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
          The key shown above changed (e.g. after a volume wipe). Clear the RustDesk client settings
          (<em>Network → reset</em>), re-enter the server details and key, then reconnect.
        </p>
      </div>
      <div>
        <strong>Failed to secure tcp / Device offline when logged in</strong>
        <p style="color:var(--text-muted);margin:4px 0 0">
          This is the bug SkonaDesk was built to fix. If you are seeing it, the hbbs container may be running
          the unpatched <code>rustdesk/rustdesk-server</code> image rather than <code>skonadesk-hbbs</code>.
          Check the <a href="/server.php" style="color:var(--teal)">Server page</a> for the running image version.
        </p>
      </div>
      <div>
        <strong>Address book / groups not syncing</strong>
        <p style="color:var(--text-muted);margin:4px 0 0">
          The RustDesk client must be able to reach the API at <code><?= htmlspecialchars($apiUrl) ?></code>.
          <?php if ($useSSL): ?>
            Check that Nginx Proxy Manager has a proxy host pointing <code><?= htmlspecialchars($domain) ?></code>
            → <code>skonadesk-api:21114</code> with a valid SSL certificate.
          <?php else: ?>
            Check that port <strong>21114</strong> is open on the server firewall and that
            <code>API_PUBLIC_URL</code> in your <code>.env</code> matches the IP/port your clients can reach.
          <?php endif; ?>
        </p>
      </div>
      <div>
        <strong>Can connect on LAN but not remotely</strong>
        <p style="color:var(--text-muted);margin:4px 0 0">
          Ports 21115–21119 are not open on the VPS firewall or hosting control panel. Check all five ports
          in the table above — both TCP and UDP on 21116.
        </p>
      </div>
    </div>
  </div>

</div>

<script>
function copyVal(id, btn) {
    const text = document.getElementById(id)?.textContent?.trim();
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        const icon = btn.querySelector('svg');
        icon.setAttribute('data-feather', 'check');
        feather.replace({ 'stroke-width': 2 });
        btn.style.color = 'var(--teal)';
        setTimeout(() => {
            icon.setAttribute('data-feather', 'copy');
            feather.replace({ 'stroke-width': 2 });
            btn.style.color = '';
        }, 1800);
    });
}
</script>

<?php page_close(); ?>
