<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

session_init();
header('Content-Type: text/html; charset=utf-8');

if (!is_logged_in()) { echo ''; exit; }

$resp     = api_get('/sessions');
$sessions = $resp['data'] ?? [];
$now      = new DateTime('now', new DateTimeZone('UTC'));

function ajax_os_icon(string $os): string {
    if (!$os) return '';
    $lower = strtolower($os);
    $title = htmlspecialchars($os, ENT_QUOTES);
    if (str_contains($lower, 'windows')) {
        return '<span class="os-icon os-icon-windows" title="'.$title.'">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 21 21" fill="currentColor"><path d="M0 0h10v10H0zm11 0h10v10H11zM0 11h10v10H0zm11 0h10v10H11z"/></svg>
        </span>';
    }
    if (str_contains($lower, 'mac') || str_contains($lower, 'darwin')) {
        return '<span class="os-icon os-icon-mac" title="'.$title.'">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
        </span>';
    }
    if (str_contains($lower, 'android')) {
        return '<span class="os-icon os-icon-android" title="'.$title.'">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 18c0 .55.45 1 1 1h1v3.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5V19h2v3.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5V19h1c.55 0 1-.45 1-1V8H6v10zm-2.5-1C2.67 17 2 16.33 2 15.5v-7C2 7.67 2.67 7 3.5 7S5 7.67 5 8.5v7c0 .83-.67 1.5-1.5 1.5zm17 0c-.83 0-1.5-.67-1.5-1.5v-7c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v7c0 .83-.67 1.5-1.5 1.5zM15.53 2.16l1.3-1.3c.2-.2.2-.51 0-.71-.2-.2-.51-.2-.71 0l-1.48 1.48C13.85 1.23 12.95 1 12 1c-.96 0-1.86.23-2.65.63L7.85.15c-.2-.2-.51-.2-.71 0-.2.2-.2.51 0 .71l1.31 1.31C7.15 3.23 6 5.01 6 7h12c0-1.99-1.15-3.77-2.47-4.84zM10 5H9V4h1v1zm5 0h-1V4h1v1z"/></svg>
        </span>';
    }
    return '<span class="os-icon os-icon-linux" title="'.$title.'">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
    </span>';
}

function ajax_type_badge(?int $type): string {
    switch ($type) {
        case 1:
            return '<span class="badge" style="font-size:0.7rem;background:rgba(99,102,241,0.15);color:#818cf8;border:1px solid rgba(99,102,241,0.3)">
                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>File</span>';
        case 2:
            return '<span class="badge" style="font-size:0.7rem;background:rgba(245,158,11,0.15);color:#f59e0b;border:1px solid rgba(245,158,11,0.3)">
                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>Port Fwd</span>';
        default:
            return '<span class="badge" style="font-size:0.7rem;background:rgba(16,185,129,0.15);color:#10b981;border:1px solid rgba(16,185,129,0.3)">
                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>Remote</span>';
    }
}

function ajax_duration(string $ts, DateTime $now): string {
    if (!$ts) return '—';
    try {
        $d    = new DateTime($ts, new DateTimeZone('UTC'));
        $diff = max(0, $now->getTimestamp() - $d->getTimestamp());
        if ($diff < 60)   return $diff . 's';
        if ($diff < 3600) return (int)($diff/60) . 'm ' . ($diff%60) . 's';
        $h = (int)($diff/3600);
        $m = (int)(($diff%3600)/60);
        return $h . 'h ' . $m . 'm';
    } catch (Exception $e) { return '—'; }
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <div style="display:flex;align-items:center;gap:12px">
    <span class="badge badge-online" style="font-size:0.8rem;padding:4px 10px">
      <span class="dot dot-green"></span>
      <?= count($sessions) ?> active
    </span>
    <span style="font-size:0.8rem;color:var(--text-muted)">Auto-refreshes every 5 seconds</span>
  </div>
  <button class="btn btn-ghost btn-sm" onclick="refreshSessions()">
    <svg data-feather="refresh-cw" style="width:14px;height:14px"></svg>
    Refresh now
  </button>
</div>

<?php if (empty($sessions)): ?>
<div class="card">
  <div class="empty-state" style="padding:60px">
    <svg data-feather="cast" style="width:48px;height:48px;opacity:.3"></svg>
    <p style="margin-top:16px;color:var(--text-muted)">No active sessions right now</p>
    <p style="font-size:0.8rem;color:var(--text-muted)">Sessions appear here while remote connections are in progress</p>
  </div>
</div>
<?php else: ?>
<div class="card" id="sessionsCard">
  <div class="table-wrap">
    <table id="sessionsTable">
      <thead>
        <tr>
          <th style="width:80px">Status</th>
          <th style="width:90px">Type</th>
          <th>Controlling (Caller)</th>
          <th style="text-align:center;width:40px"></th>
          <th>Target Device</th>
          <th>Target User</th>
          <th>Duration</th>
          <th>Caller IP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $s):
            $callerIp   = htmlspecialchars($s['caller_wan_ip'] ?? '');
            $callerOs   = $s['caller_os']   ?? '';
            $callerId   = htmlspecialchars($s['caller_id']    ?? '');
            $rawName    = $s['caller_name'] ?? '';
            $callerName = $rawName ? htmlspecialchars($rawName) : ($callerIp ?: '—');
            $targetName = htmlspecialchars($s['target_name']  ?? $s['target_id'] ?? '—');
            $targetId   = htmlspecialchars($s['target_id']    ?? '');
            $targetOs   = $s['target_os']   ?? '';
            $targetUser = htmlspecialchars($s['target_user']  ?? '');
            $since      = $s['connected_since'] ?? '';
            $duration   = ajax_duration($since, $now);
            $sinceStr   = $since ? htmlspecialchars(substr($since, 0, 16)) : '—';
            $connType   = isset($s['conn_type']) && $s['conn_type'] !== null ? (int)$s['conn_type'] : null;
        ?>
        <tr>
          <td>
            <span class="badge badge-online" style="font-size:0.72rem">
              <span class="dot dot-green"></span>Live
            </span>
          </td>
          <td><?= ajax_type_badge($connType) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <?= ajax_os_icon($callerOs) ?>
              <div>
                <div style="font-weight:600;line-height:1.3"><?= $callerName ?></div>
                <div style="font-size:0.7rem;color:var(--text-muted);font-family:monospace"><?= $callerId ?></div>
              </div>
            </div>
          </td>
          <td style="text-align:center;color:var(--text-muted)">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="5" y1="12" x2="19" y2="12"/>
              <polyline points="12 5 19 12 12 19"/>
            </svg>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:6px">
              <?= ajax_os_icon($targetOs) ?>
              <div>
                <div style="font-weight:600;line-height:1.3"><?= $targetName ?></div>
                <div style="font-size:0.7rem;color:var(--text-muted);font-family:monospace"><?= $targetId ?></div>
              </div>
            </div>
          </td>
          <td style="font-size:0.82rem"><?= $targetUser ?: '<span style="color:var(--text-muted)">—</span>' ?></td>
          <td>
            <div style="font-weight:600;font-size:0.88rem;font-family:monospace;color:var(--color-warning)"><?= htmlspecialchars($duration) ?></div>
            <?php if ($sinceStr !== '—'): ?>
            <div style="font-size:0.7rem;color:var(--text-muted)"><?= $sinceStr ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:0.78rem;color:var(--text-muted)"><?= $callerIp ?: '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
