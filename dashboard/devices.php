<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/layout.php';

require_login();
$user = current_user();

$page          = max(1, (int)($_GET['page']     ?? 1));
$filterGroupId = max(0, (int)($_GET['group_id'] ?? 0));
$pageSize      = 20;
$flash         = '';
$flashType     = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit' && $user['is_admin']) {
        $id       = $_POST['peer_id'] ?? '';
        $groupIds = array_filter(array_map('intval', (array)($_POST['group_ids'] ?? [])));
        $note     = $_POST['note'] ?? '';
        $resp = api_put("/peers/$id", ['group_ids' => array_values($groupIds), 'note' => $note]);
        $flash = api_ok($resp) ? "Device updated." : api_error($resp);
        if (!api_ok($resp)) $flashType = 'danger';

    } elseif ($action === 'delete' && $user['is_admin']) {
        $id = $_POST['peer_id'] ?? '';
        $resp = api_delete("/peers/$id");
        $flash = api_ok($resp) ? "Device removed." : api_error($resp);
        if (!api_ok($resp)) $flashType = 'danger';
    }
}

$groups    = api_get('/device-group/accessible', ['current' => 1, 'pageSize' => 200]);
$groupList = $groups['data'] ?? [];

$filterGroupName = '';
if ($filterGroupId) {
    foreach ($groupList as $g) {
        if ((int)$g['id'] === $filterGroupId) { $filterGroupName = $g['name']; break; }
    }
}

$apiParams = ['current' => $page, 'pageSize' => $pageSize];
if ($filterGroupId) $apiParams['group_id'] = $filterGroupId;
$resp    = api_get('/peers', $apiParams);
$devices = $resp['data']  ?? [];
$total   = $resp['total'] ?? 0;
$pages   = (int)ceil($total / $pageSize);

$now = new DateTime('now', new DateTimeZone('UTC'));

function time_ago(string $ts, DateTime $now): string {
    if (!$ts) return '—';
    try {
        $d = new DateTime($ts, new DateTimeZone('UTC'));
        $diff = $now->getTimestamp() - $d->getTimestamp();
        if ($diff < 120)   return '<span class="badge badge-online"><span class="dot dot-green"></span>Online</span>';
        if ($diff < 3600)  return (int)($diff/60) . ' min ago';
        if ($diff < 86400) return (int)($diff/3600) . ' hr ago';
        return $d->format('d M');
    } catch (Exception $e) {
        return htmlspecialchars($ts);
    }
}

function os_icon(string $os): string {
    if (!$os) {
        return '<span class="os-icon os-icon-unknown" title="Unknown OS">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </span>';
    }
    $lower = strtolower($os);
    $title = htmlspecialchars($os, ENT_QUOTES);

    if (str_contains($lower, 'windows')) {
        return '<span class="os-icon os-icon-windows" title="'.$title.'">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 21 21" fill="currentColor">
                <path d="M0 0h10v10H0zm11 0h10v10H11zM0 11h10v10H0zm11 0h10v10H11z"/>
            </svg>
        </span>';
    }
    if (str_contains($lower, 'mac') || str_contains($lower, 'darwin')) {
        return '<span class="os-icon os-icon-mac" title="'.$title.'">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
            </svg>
        </span>';
    }
    if (str_contains($lower, 'android')) {
        return '<span class="os-icon os-icon-android" title="'.$title.'">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M6 18c0 .55.45 1 1 1h1v3.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5V19h2v3.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5V19h1c.55 0 1-.45 1-1V8H6v10zm-2.5-1C2.67 17 2 16.33 2 15.5v-7C2 7.67 2.67 7 3.5 7S5 7.67 5 8.5v7c0 .83-.67 1.5-1.5 1.5zm17 0c-.83 0-1.5-.67-1.5-1.5v-7c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v7c0 .83-.67 1.5-1.5 1.5zM15.53 2.16l1.3-1.3c.2-.2.2-.51 0-.71-.2-.2-.51-.2-.71 0l-1.48 1.48C13.85 1.23 12.95 1 12 1c-.96 0-1.86.23-2.65.63L7.85.15c-.2-.2-.51-.2-.71 0-.2.2-.2.51 0 .71l1.31 1.31C7.15 3.23 6 5.01 6 7h12c0-1.99-1.15-3.77-2.47-4.84zM10 5H9V4h1v1zm5 0h-1V4h1v1z"/>
            </svg>
        </span>';
    }
    return '<span class="os-icon os-icon-linux" title="'.$title.'">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>
        </svg>
    </span>';
}

function format_version(string $ver): string {
    if (!$ver) return '—';
    if (preg_match('/^\d+\.\d+/', $ver)) return htmlspecialchars($ver);
    if (ctype_digit($ver) && strlen($ver) >= 4) {
        $v = (int)$ver;
        $patch = $v % 100;
        $v     = intdiv($v, 100);
        $minor = $v % 100;
        $major = intdiv($v, 100);
        return "$major.$minor.$patch";
    }
    return htmlspecialchars($ver);
}

page_open('Devices');
?>

<style>
.os-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    flex-shrink: 0;
}
.os-icon-windows { color: #00adef; background: rgba(0,173,239,.14); }
.os-icon-mac     { color: #aaa;    background: rgba(170,170,170,.14); }
.os-icon-android { color: #78c257; background: rgba(120,194,87,.14); }
.os-icon-linux   { color: #f0c040; background: rgba(240,192,64,.14); }
.os-icon-unknown { color: var(--text-muted); background: rgba(100,100,100,.1); }

.device-sub-row td {
    padding: 0 12px 10px !important;
    border-top: none !important;
}
.device-sub-inner {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 20px;
    font-size: 0.7rem;
    color: var(--text-muted);
    padding: 4px 10px 3px;
    border-left: 2px solid var(--color-primary, #38B2AC);
    border-radius: 0 4px 4px 0;
    background: rgba(0,0,0,.03);
}
.device-sub-inner .si { display: flex; align-items: center; gap: 5px; }
.device-sub-inner .si-label { opacity: .6; font-size: 0.65rem; text-transform: uppercase; letter-spacing: .04em; }
.device-id-mono {
    font-family: monospace;
    font-size: 0.72rem;
    cursor: pointer;
    padding: 1px 5px;
    border-radius: 4px;
    background: rgba(0,0,0,.06);
    transition: background .15s;
}
.device-id-mono:hover { background: rgba(0,0,0,.12); color: var(--color-primary); }
</style>

<?php if ($flash): ?>
<div class="alert alert-<?= $flashType ?>" style="margin:0 0 20px"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="section-header">
  <h2>
    <?= $total ?> Device<?= $total !== 1 ? 's' : '' ?>
    <?php if ($filterGroupName): ?>
      <span style="font-size:0.75rem;font-weight:400;margin-left:8px;padding:2px 10px;background:var(--color-primary);color:#fff;border-radius:12px">
        <?= htmlspecialchars($filterGroupName) ?>
      </span>
    <?php endif; ?>
  </h2>
  <div style="display:flex;align-items:center;gap:12px">
    <?php if ($filterGroupId): ?>
    <a href="/devices.php" class="btn btn-ghost" style="font-size:0.8rem;padding:4px 12px">
      <svg data-feather="x" style="width:13px;height:13px"></svg>
      Clear filter
    </a>
    <?php endif; ?>
    <span style="font-size:var(--font-sm);color:var(--text-muted)">Auto-registered via heartbeat</span>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <?php if (empty($devices)): ?>
    <div class="empty-state">
      <svg data-feather="monitor"></svg>
      <h3>No devices yet</h3>
      <p>Devices appear here once they connect and send a heartbeat.</p>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Status</th>
          <th>Device</th>
          <th style="text-align:center">OS</th>
          <th>User</th>
          <th>Groups</th>
          <th>Last Seen</th>
          <th>Version</th>
          <?php if ($user['is_admin']): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($devices as $d):
            $id          = $d['id']                    ?? '';
            $name        = $d['info']['device_name']   ?? ($d['info']['hostname'] ?? '');
            $host        = $d['info']['hostname']       ?? '';
            $os          = $d['info']['os']             ?? '';
            $cpu         = $d['info']['cpu']            ?? '';
            $memory      = $d['info']['memory']         ?? '';
            $wanIp       = $d['info']['wan_ip']         ?? '';
            $uname       = $d['user_name']              ?? '';
            $groups      = $d['groups']                 ?? [];
            $note        = $d['note']                   ?? '';
            $lastSeen    = $d['last_seen']              ?? '';
            $ver         = $d['version']                ?? '';
            $displayName = $name ?: $id;
            $subLine     = ($host && $host !== $name) ? $host : '';
            $groupsJson  = htmlspecialchars(json_encode($groups), ENT_QUOTES);

            $hasSubInfo  = $cpu || $memory || $os || $id;
        ?>
        <tr>
          <td style="white-space:nowrap"><?= time_ago($lastSeen, $now) ?></td>
          <td>
            <div style="font-weight:600;line-height:1.3"><?= htmlspecialchars($displayName) ?></div>
            <?php if ($subLine): ?><div style="font-size:0.7rem;color:var(--text-muted)"><?= htmlspecialchars($subLine) ?></div><?php endif; ?>
            <?php if ($note): ?><div style="font-size:0.7rem;color:var(--text-muted);font-style:italic"><?= htmlspecialchars($note) ?></div><?php endif; ?>
          </td>
          <td style="text-align:center"><?= os_icon($os) ?></td>
          <td style="font-size:0.82rem"><?= htmlspecialchars($uname) ?></td>
          <td>
            <?php if (empty($groups)): ?>
              <span style="color:var(--text-muted)">—</span>
            <?php else: ?>
              <?php foreach ($groups as $g): ?>
              <span class="badge badge-info" style="margin-right:2px"><?= htmlspecialchars($g['name']) ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap;font-size:0.72rem;color:var(--text-muted)"><?= time_ago($lastSeen, $now) !== htmlspecialchars($lastSeen) ? htmlspecialchars(substr($lastSeen,0,16)) : '—' ?></td>
          <td style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap"><?= format_version($ver) ?></td>
          <?php if ($user['is_admin']): ?>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn-icon" title="Edit device"
                data-modal-open="editModal"
                data-peer="<?= htmlspecialchars($id) ?>"
                data-groups="<?= $groupsJson ?>"
                data-note="<?= htmlspecialchars($note) ?>"
                onclick="fillEditModal(this)">
                <svg data-feather="edit-2"></svg>
              </button>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action"  value="delete" />
                <input type="hidden" name="peer_id" value="<?= htmlspecialchars($id) ?>" />
                <button class="btn-icon danger" type="submit"
                  data-confirm="Remove device <?= htmlspecialchars($id) ?>?">
                  <svg data-feather="trash-2"></svg>
                </button>
              </form>
            </div>
          </td>
          <?php endif; ?>
        </tr>
        <?php if ($hasSubInfo): ?>
        <tr class="device-sub-row">
          <td colspan="<?= $user['is_admin'] ? 8 : 7 ?>">
            <div class="device-sub-inner">
              <div class="si">
                <span class="si-label">ID</span>
                <span class="device-id-mono" title="Click to copy" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($id) ?>');this.textContent='Copied!';setTimeout(()=>this.textContent='<?= htmlspecialchars($id) ?>',1500)"><?= htmlspecialchars($id) ?></span>
              </div>
              <?php if ($cpu): ?>
              <div class="si">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.5"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
                <span><?= htmlspecialchars($cpu) ?></span>
              </div>
              <?php endif; ?>
              <?php if ($memory): ?>
              <div class="si">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.5"><path d="M6 19v-3M10 19v-3M14 19v-3M18 19v-3M8 11V9M16 11V9M12 11V9M2 15h20M2 7l10-4 10 4"/></svg>
                <span><?= htmlspecialchars($memory) ?> RAM</span>
              </div>
              <?php endif; ?>
              <?php if ($wanIp): ?>
              <div class="si">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                <span><?= htmlspecialchars($wanIp) ?></span>
              </div>
              <?php endif; ?>
              <?php if ($os): ?>
              <div class="si">
                <span class="si-label">OS</span>
                <span><?= htmlspecialchars($os) ?></span>
              </div>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <span class="page-info">Page <?= $page ?> of <?= $pages ?> (<?= $total ?> total)</span>
    <?php
    $pgBase = $filterGroupId ? "?group_id=$filterGroupId&page=" : "?page=";
    for ($p = max(1,$page-2); $p <= min($pages,$page+2); $p++):
    ?>
    <a class="page-btn<?= $p===$page?' active':'' ?>" href="<?= $pgBase . $p ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($user['is_admin']): ?>
<div class="modal-backdrop" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Device</span>
      <button class="modal-close" data-modal-close><svg data-feather="x"></svg></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action"  value="edit" />
      <input type="hidden" name="peer_id" id="editPeerId" value="" />
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:16px">
          <label>Device ID</label>
          <input type="text" id="editPeerIdDisplay" readonly style="opacity:0.6" />
        </div>
        <div class="form-group" style="margin-bottom:16px">
          <label>Device Groups</label>
          <div id="editGroupChecks" style="display:flex;flex-direction:column;gap:6px;max-height:140px;overflow-y:auto;padding:8px 10px;background:var(--surface-alt,rgba(0,0,0,.05));border-radius:6px">
            <?php if (empty($groupList)): ?>
            <span style="font-size:0.8rem;color:var(--text-muted)">No groups defined yet.</span>
            <?php else: ?>
            <?php foreach ($groupList as $g): ?>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.85rem;font-weight:normal">
              <input type="checkbox" name="group_ids[]" value="<?= (int)$g['id'] ?>" />
              <?= htmlspecialchars($g['name']) ?>
            </label>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="form-group">
          <label for="editNote">Note</label>
          <input type="text" name="note" id="editNote" placeholder="Optional note" />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">
          <svg data-feather="save"></svg>
          Save
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function fillEditModal(btn) {
    document.getElementById('editPeerId').value        = btn.dataset.peer;
    document.getElementById('editPeerIdDisplay').value = btn.dataset.peer;
    document.getElementById('editNote').value          = btn.dataset.note || '';
    const groups  = JSON.parse(btn.dataset.groups || '[]');
    const ids     = new Set(groups.map(g => String(g.id)));
    document.querySelectorAll('#editGroupChecks input[type=checkbox]').forEach(cb => {
        cb.checked = ids.has(cb.value);
    });
    feather.replace({ 'stroke-width': 2 });
}
</script>
<?php endif; ?>

<?php page_close(); ?>
