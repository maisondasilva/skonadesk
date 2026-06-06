<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$stats  = api_get('/stats');
$info   = api_get('/server-info');
$recent = api_get('/audit/log', ['current' => 1, 'pageSize' => 8]);

$totalDevices   = $stats['total_devices']     ?? 0;
$onlineDevices  = $stats['online_devices']    ?? 0;
$totalUsers     = $stats['total_users']       ?? 0;
$connsToday     = $stats['connections_today'] ?? 0;
$activeSessions = $stats['active_sessions']   ?? 0;

$pubKey    = $info['public_key'] ?? '';
$domain    = $info['domain']     ?? '';
$recentLog = $recent['data']     ?? [];

page_open('Dashboard');
?>

<div class="stat-grid" id="statGrid">
  <div class="stat-card" data-accent="teal">
    <div class="stat-label">Total Devices</div>
    <div class="stat-value" id="st-devices"><?= $totalDevices ?></div>
    <div class="stat-sub">registered</div>
    <svg class="stat-icon" data-feather="monitor"></svg>
  </div>
  <div class="stat-card" data-accent="green">
    <div class="stat-label">Online Now</div>
    <div class="stat-value" id="st-online"><?= $onlineDevices ?></div>
    <div class="stat-sub">seen &lt; 2 min ago</div>
    <svg class="stat-icon" data-feather="wifi"></svg>
  </div>
  <div class="stat-card" data-accent="orange" style="cursor:pointer" onclick="window.location='/sessions.php'">
    <div class="stat-label">Active Sessions</div>
    <div class="stat-value" id="st-sessions"><?= $activeSessions ?></div>
    <div class="stat-sub">live right now</div>
    <svg class="stat-icon" data-feather="cast"></svg>
  </div>
  <div class="stat-card" data-accent="purple">
    <div class="stat-label">Total Users</div>
    <div class="stat-value" id="st-users"><?= $totalUsers ?></div>
    <div class="stat-sub">accounts</div>
    <svg class="stat-icon" data-feather="users"></svg>
  </div>
  <div class="stat-card" data-accent="blue">
    <div class="stat-label">Connections</div>
    <div class="stat-value" id="st-conns"><?= $connsToday ?></div>
    <div class="stat-sub">last 24 hours</div>
    <svg class="stat-icon" data-feather="zap"></svg>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;flex-wrap:wrap;" class="home-grid">

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="server"></svg>
        Server Connection Info
      </div>
      <a href="/server.php" class="btn btn-ghost btn-sm">
        <svg data-feather="external-link"></svg>
        Full details
      </a>
    </div>
    <div class="card-body">
      <?php if ($pubKey): ?>
      <div class="info-row">
        <span class="info-label">Public Key</span>
        <div class="info-value copy-wrap">
          <code class="code-block" id="pubKeyShort"><?= htmlspecialchars($pubKey) ?></code>
          <button class="copy-btn" data-copy="#pubKeyShort" title="Copy key">
            <svg data-feather="copy"></svg>
          </button>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($domain): ?>
      <div class="info-row">
        <span class="info-label">Rendezvous</span>
        <div class="info-value copy-wrap">
          <code class="code-block" id="hbbsAddr"><?= htmlspecialchars($domain) ?></code>
          <button class="copy-btn" data-copy="#hbbsAddr" title="Copy address">
            <svg data-feather="copy"></svg>
          </button>
        </div>
      </div>
      <div class="info-row">
        <span class="info-label">Relay</span>
        <div class="info-value">
          <code class="code-block"><?= htmlspecialchars($domain) ?></code>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="activity"></svg>
        Recent Activity
      </div>
      <a href="/audit.php" class="btn btn-ghost btn-sm">
        <svg data-feather="external-link"></svg>
        View all
      </a>
    </div>
    <?php if (empty($recentLog)): ?>
    <div class="empty-state" style="padding:28px">
      <svg data-feather="inbox"></svg>
      <p>No audit events yet</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>Event</th>
            <th>Device</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentLog as $row): ?>
          <tr>
            <td style="color:var(--text-muted);white-space:nowrap;"><?= htmlspecialchars(substr($row['created_at'] ?? '', 0, 16)) ?></td>
            <td><span class="badge badge-info"><?= htmlspecialchars($row['event_type'] ?? '') ?></span></td>
            <td style="font-family:monospace"><?= htmlspecialchars($row['peer_id'] ?? '') ?></td>
            <td><?= htmlspecialchars($row['action'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
(function autoRefreshStats() {
    const token = <?= json_encode($_SESSION['access_token'] ?? '') ?>;
    if (!token) return;

    async function refresh() {
        try {
            const r = await fetch('/ajax/stats.php', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!r.ok) return;
            const d = await r.json();
            if (d.total_devices     !== undefined) document.getElementById('st-devices').textContent  = d.total_devices;
            if (d.online_devices    !== undefined) document.getElementById('st-online').textContent   = d.online_devices;
            if (d.active_sessions   !== undefined) document.getElementById('st-sessions').textContent = d.active_sessions;
            if (d.total_users       !== undefined) document.getElementById('st-users').textContent    = d.total_users;
            if (d.connections_today !== undefined) document.getElementById('st-conns').textContent    = d.connections_today;
        } catch(e) {}
    }

    setInterval(refresh, 30000);
})();
</script>

<?php page_close(); ?>
