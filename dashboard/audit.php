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

page_open(__('audit.title'));
?>

<div class="section-header">
  <h2><?= __p('audit.count', $total) ?></h2>
  <span style="font-size:var(--font-sm);color:var(--text-muted)"><?= __('audit.subtitle') ?></span>
</div>

<div class="card">
  <div class="table-wrap">
    <?php if (empty($rows)): ?>
    <div class="empty-state">
      <svg data-feather="activity"></svg>
      <h3><?= __('audit.no_events_title') ?></h3>
      <p><?= __('audit.no_events_desc') ?></p>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th><?= __('audit.time') ?></th>
          <th><?= __('audit.type') ?></th>
          <th><?= __('audit.device') ?></th>
          <th><?= __('audit.remote') ?></th>
          <th><?= __('audit.user') ?></th>
          <th><?= __('audit.ip') ?></th>
          <th><?= __('audit.action') ?></th>
          <th><?= __('audit.note') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row):
            $evType   = $row['event_type'] ?? '';
            $connType = isset($row['conn_type']) ? (int)$row['conn_type'] : null;
            if ($evType === 'file') {
                $label = __('sessions.type_file'); $badgeCls = 'badge-info';
            } elseif ($connType === 1) {
                $label = __('sessions.type_file_xfer'); $badgeCls = 'badge-info';
            } else {
                $label = __('sessions.type_remote_label'); $badgeCls = 'badge-online';
            }
        ?>
        <tr>
          <td style="white-space:nowrap;font-size:0.7rem;color:var(--text-muted)"><?= htmlspecialchars(substr($row['created_at'] ?? '', 0, 16)) ?></td>
          <td><span class="badge <?= $badgeCls ?>"><?= $label ?></span></td>
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
    <span class="page-info"><?= __('audit.page', $page, $pages, $total) ?></span>
    <?php for ($p = max(1,$page-2); $p <= min($pages,$page+2); $p++): ?>
    <a class="page-btn<?= $p===$page?' active':'' ?>" href="?page=<?= $p ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php page_close(); ?>
