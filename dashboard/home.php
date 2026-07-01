<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
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

page_open(__('dashboard.title'));
?>

<div class="stat-grid" id="statGrid">
  <div class="stat-card" data-accent="teal">
    <div class="stat-label"><?= __('dashboard.total_devices') ?></div>
    <div class="stat-value" id="st-devices"><?= $totalDevices ?></div>
    <div class="stat-sub"><?= __('dashboard.registered') ?></div>
    <svg class="stat-icon" data-feather="monitor"></svg>
  </div>
  <div class="stat-card" data-accent="green">
    <div class="stat-label"><?= __('dashboard.online_now') ?></div>
    <div class="stat-value" id="st-online"><?= $onlineDevices ?></div>
    <div class="stat-sub"><?= __('dashboard.seen_recently') ?></div>
    <svg class="stat-icon" data-feather="wifi"></svg>
  </div>
  <div class="stat-card" data-accent="orange" style="cursor:pointer" onclick="window.location='/sessions.php'">
    <div class="stat-label"><?= __('dashboard.active_sessions') ?></div>
    <div class="stat-value" id="st-sessions"><?= $activeSessions ?></div>
    <div class="stat-sub"><?= __('dashboard.live_right_now') ?></div>
    <svg class="stat-icon" data-feather="cast"></svg>
  </div>
  <div class="stat-card" data-accent="purple">
    <div class="stat-label"><?= __('dashboard.total_users') ?></div>
    <div class="stat-value" id="st-users"><?= $totalUsers ?></div>
    <div class="stat-sub"><?= __('dashboard.accounts') ?></div>
    <svg class="stat-icon" data-feather="users"></svg>
  </div>
  <div class="stat-card" data-accent="blue">
    <div class="stat-label"><?= __('dashboard.connections') ?></div>
    <div class="stat-value" id="st-conns"><?= $connsToday ?></div>
    <div class="stat-sub"><?= __('dashboard.last_24h') ?></div>
    <svg class="stat-icon" data-feather="zap"></svg>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;flex-wrap:wrap;" class="home-grid">

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="server"></svg>
        <?= __('dashboard.server_info') ?>
      </div>
      <a href="/server.php" class="btn btn-ghost btn-sm">
        <svg data-feather="external-link"></svg>
        <?= __('dashboard.full_details') ?>
      </a>
    </div>
    <div class="card-body">
      <?php if ($pubKey): ?>
      <div class="info-row">
        <span class="info-label"><?= __('dashboard.public_key') ?></span>
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
        <span class="info-label"><?= __('dashboard.rendezvous') ?></span>
        <div class="info-value copy-wrap">
          <code class="code-block" id="hbbsAddr"><?= htmlspecialchars($domain) ?></code>
          <button class="copy-btn" data-copy="#hbbsAddr" title="Copy address">
            <svg data-feather="copy"></svg>
          </button>
        </div>
      </div>
      <div class="info-row">
        <span class="info-label"><?= __('dashboard.relay') ?></span>
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
        <?= __('dashboard.recent_activity') ?>
      </div>
      <a href="/audit.php" class="btn btn-ghost btn-sm">
        <svg data-feather="external-link"></svg>
        <?= __('dashboard.view_all') ?>
      </a>
    </div>
    <?php if (empty($recentLog)): ?>
    <div class="empty-state" style="padding:28px">
      <svg data-feather="inbox"></svg>
      <p><?= __('dashboard.no_events') ?></p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= __('audit.time') ?></th>
            <th><?= __('audit.type') ?></th>
            <th><?= __('audit.device') ?></th>
            <th><?= __('audit.action') ?></th>
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
