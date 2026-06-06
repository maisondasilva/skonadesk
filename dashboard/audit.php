<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 50;

$resp  = api_get('/audit/log', ['current' => $page, 'pageSize' => $pageSize]);
$rows  = $resp['data']  ?? [];
$total = $resp['total'] ?? 0;
$pages = (int)ceil($total / $pageSize);

page_open('Audit Log');
?>

<div class="section-header">
  <h2><?= $total ?> Event<?= $total !== 1 ? 's' : '' ?></h2>
  <span style="font-size:var(--font-sm);color:var(--text-muted)">Connection and file transfer events</span>
</div>

<div class="card">
  <div class="table-wrap">
    <?php if (empty($rows)): ?>
    <div class="empty-state">
      <svg data-feather="activity"></svg>
      <h3>No audit events yet</h3>
      <p>Connection and file-transfer events will appear here.</p>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Time</th>
          <th>Type</th>
          <th>Device</th>
          <th>Remote</th>
          <th>User</th>
          <th>IP</th>
          <th>Action</th>
          <th>Note</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
            $type   = $row['event_type'] ?? '';
            $badgeCls = $type === 'file' ? 'badge-info' : 'badge-online';
        ?>
        <tr>
          <td style="white-space:nowrap;font-size:0.7rem;color:var(--text-muted)"><?= htmlspecialchars(substr($row['created_at'] ?? '', 0, 16)) ?></td>
          <td><span class="badge <?= $badgeCls ?>"><?= htmlspecialchars($type) ?></span></td>
          <td><code style="font-size:0.7rem"><?= htmlspecialchars($row['peer_id'] ?? '') ?></code></td>
          <td><code style="font-size:0.7rem"><?= htmlspecialchars($row['remote_id'] ?? '') ?></code></td>
          <td><?= htmlspecialchars($row['username'] ?? '') ?></td>
          <td style="font-size:0.7rem;color:var(--text-muted)"><?= htmlspecialchars($row['ip'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['action'] ?? '') ?></td>
          <td style="color:var(--text-muted)"><?= htmlspecialchars($row['note'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <span class="page-info">Page <?= $page ?> of <?= $pages ?> (<?= $total ?> total)</span>
    <?php for ($p = max(1,$page-2); $p <= min($pages,$page+2); $p++): ?>
    <a class="page-btn<?= $p===$page?' active':'' ?>" href="?page=<?= $p ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php page_close(); ?>
