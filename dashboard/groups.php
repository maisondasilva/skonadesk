<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/layout.php';

require_admin();

$flash     = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $resp = api_post('/device-group/add', ['name' => trim($_POST['name'] ?? '')]);
        $flash = api_ok($resp) ? __('groups.group_created') : api_error($resp);
        if (!api_ok($resp)) $flashType = 'danger';

    } elseif ($action === 'rename') {
        $id   = $_POST['group_id'] ?? '';
        $resp = api_put("/device-group/$id", ['name' => trim($_POST['name'] ?? '')]);
        $flash = api_ok($resp) ? __('groups.group_renamed') : api_error($resp);
        if (!api_ok($resp)) $flashType = 'danger';

    } elseif ($action === 'delete') {
        $id   = $_POST['group_id'] ?? '';
        $resp = api_delete("/device-group/$id");
        $flash = api_ok($resp) ? __('groups.group_deleted') : api_error($resp);
        if (!api_ok($resp)) $flashType = 'danger';

    } elseif ($action === 'add_member') {
        $gid    = $_POST['group_id'] ?? '';
        $uid    = (int)($_POST['user_id'] ?? 0);
        $resp   = api_post("/group/$gid/member", ['user_id' => $uid]);
        $flash  = api_ok($resp) ? __('groups.user_added') : api_error($resp);
        if (!api_ok($resp)) $flashType = 'danger';

    } elseif ($action === 'remove_member') {
        $gid    = $_POST['group_id'] ?? '';
        $uid    = (int)($_POST['user_id'] ?? 0);
        $resp   = api_delete("/group/$gid/member/$uid");
        $flash  = api_ok($resp) ? __('groups.user_removed') : api_error($resp);
        if (!api_ok($resp)) $flashType = 'danger';
    }
}

$resp   = api_get('/device-group/accessible', ['current' => 1, 'pageSize' => 200]);
$groups = $resp['data']  ?? [];
$total  = $resp['total'] ?? 0;

$devices = api_get('/peers', ['current' => 1, 'pageSize' => 1000]);
$allDevices = $devices['data'] ?? [];

$groupCounts = [];
foreach ($allDevices as $d) {
    foreach ($d['groups'] ?? [] as $g) {
        $gid = (int)($g['id'] ?? 0);
        if ($gid > 0) $groupCounts[$gid] = ($groupCounts[$gid] ?? 0) + 1;
    }
}

$membershipsResp = api_get('/group/memberships');
$allMemberships  = $membershipsResp['data'] ?? [];

$membersByGroup = [];
foreach ($allMemberships as $m) {
    $membersByGroup[(int)$m['group_id']][] = $m;
}

$usersResp = api_get('/users', ['current' => 1, 'pageSize' => 500]);
$allUsers  = $usersResp['data'] ?? [];

page_open(__('groups.title'));
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flashType ?>" style="margin:0 0 20px"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="section-header">
  <h2><?= __p('groups.count', $total) ?></h2>
  <button class="btn btn-primary" data-modal-open="addGroupModal">
    <svg data-feather="folder-plus"></svg>
    <?= __('groups.add_group') ?>
  </button>
</div>

<div class="card">
  <div class="table-wrap">
    <?php if (empty($groups)): ?>
    <div class="empty-state">
      <svg data-feather="layers"></svg>
      <h3><?= __('groups.no_groups_title') ?></h3>
      <p><?= __('groups.no_groups_desc') ?></p>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th><?= __('groups.group_name') ?></th>
          <th><?= __('groups.devices') ?></th>
          <th><?= __('groups.members') ?></th>
          <th><?= __('groups.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($groups as $g):
            $gid     = (int)($g['id']   ?? 0);
            $gname   = $g['name'] ?? '';
            $count   = $groupCounts[$gid] ?? 0;
            $members = $membersByGroup[$gid] ?? [];
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($gname) ?></strong></td>
          <td>
            <?php if ($count > 0): ?>
              <a href='/devices.php?group_id=<?= $gid ?>' style='color:var(--color-primary)'>
                <?= sprintf($count === 1 ? __('groups.devices_count') : __('groups.devices_count_plural'), $count) ?>
              </a>
            <?php else: ?>
              <span style="color:var(--text-muted)"><?= __('groups.zero_devices') ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (empty($members)): ?>
              <span style="color:var(--text-muted);font-size:0.75rem"><?= __('groups.no_members_text') ?></span>
            <?php else: ?>
              <div style="display:flex;flex-wrap:wrap;gap:4px">
                <?php foreach ($members as $m): ?>
                  <span class="badge badge-info" style="font-size:0.7rem">
                    <?= htmlspecialchars($m['username']) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn-icon" title="<?= __('groups.manage_members') ?>"
                data-modal-open="membersModal"
                data-id="<?= $gid ?>"
                data-name="<?= htmlspecialchars($gname) ?>"
                onclick="openMembersModal(this)">
                <svg data-feather="users"></svg>
              </button>
              <button class="btn-icon" title="<?= __('groups.rename_group') ?>"
                data-modal-open="renameModal"
                data-id="<?= htmlspecialchars((string)$gid) ?>"
                data-name="<?= htmlspecialchars($gname) ?>"
                onclick="fillRename(this)">
                <svg data-feather="edit-2"></svg>
              </button>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action"   value="delete" />
                <input type="hidden" name="group_id" value="<?= $gid ?>" />
                <button class="btn-icon danger" type="submit"
                  data-confirm="<?= sprintf(__('groups.confirm_delete'), htmlspecialchars($gname, ENT_QUOTES)) ?>">
                  <svg data-feather="trash-2"></svg>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<div style="margin-top:16px;padding:12px 16px;background:var(--surface-alt,rgba(0,0,0,.05));border-radius:8px;font-size:0.78rem;color:var(--text-muted)">
  <strong><?= __('groups.visibility_heading') ?></strong>
  <?= __('groups.visibility_desc') ?>
</div>

<div class="modal-backdrop" id="addGroupModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><?= __('groups.add_title') ?></span>
      <button class="modal-close" data-modal-close><svg data-feather="x"></svg></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add" />
      <div class="modal-body">
        <div class="form-group">
          <label for="newGroupName"><?= __('groups.group_name_label') ?> *</label>
          <input type="text" name="name" id="newGroupName" required placeholder="<?= __('groups.group_name_placeholder') ?>" />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close><?= __('general.cancel') ?></button>
        <button type="submit" class="btn btn-primary">
          <svg data-feather="folder-plus"></svg>
          <?= __('groups.create_group') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="renameModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><?= __('groups.rename_title') ?></span>
      <button class="modal-close" data-modal-close><svg data-feather="x"></svg></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action"   value="rename" />
      <input type="hidden" name="group_id" id="renameId"  value="" />
      <div class="modal-body">
        <div class="form-group">
          <label for="renameName"><?= __('groups.new_name_label') ?> *</label>
          <input type="text" name="name" id="renameName" required />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close><?= __('general.cancel') ?></button>
        <button type="submit" class="btn btn-primary">
          <svg data-feather="save"></svg>
          <?= __('groups.rename_btn') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="membersModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="membersModalTitle"><?= __('groups.members_title') ?></span>
      <button class="modal-close" data-modal-close><svg data-feather="x"></svg></button>
    </div>
    <div class="modal-body">
      <p style="font-size:0.8rem;color:var(--text-muted);margin:0 0 12px">
        <?= __('groups.members_desc') ?>
      </p>
      <div id="membersList" style="margin-bottom:16px;display:flex;flex-direction:column;gap:6px"></div>
      <form method="POST" id="addMemberForm">
        <input type="hidden" name="action"   value="add_member" />
        <input type="hidden" name="group_id" id="memberGroupId" value="" />
        <div style="display:flex;gap:8px;align-items:flex-end">
          <div class="form-group" style="flex:1;margin:0">
            <label for="addMemberSelect"><?= __('groups.add_user') ?></label>
            <select name="user_id" id="addMemberSelect">
              <option value=""><?= __('groups.add_user_placeholder') ?></option>
              <?php foreach ($allUsers as $u): ?>
              <option value="<?= (int)($u['id'] ?? 0) ?>">
                <?= htmlspecialchars($u['display_name'] ?: $u['name']) ?>
                (<?= htmlspecialchars($u['name']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary" style="height:38px;white-space:nowrap">
            <svg data-feather="user-plus"></svg>
            <?= __('groups.add_btn') ?>
          </button>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" data-modal-close><?= __('groups.close') ?></button>
    </div>
  </div>
</div>

<script>
const memberships = <?= json_encode($membersByGroup, JSON_UNESCAPED_UNICODE) ?>;

function openMembersModal(btn) {
    const gid   = btn.dataset.id;
    const gname = btn.dataset.name;
    document.getElementById('membersModalTitle').textContent = '<?= __('groups.members_title') ?> — ' + gname;
    document.getElementById('memberGroupId').value = gid;

    const list    = document.getElementById('membersList');
    const members = memberships[gid] || [];
    list.innerHTML = '';

    if (members.length === 0) {
        list.innerHTML = '<span style="font-size:0.8rem;color:var(--text-muted)"><?= __('groups.no_members') ?></span>';
    } else {
        members.forEach(m => {
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:6px 10px;background:var(--surface-alt,rgba(0,0,0,.04));border-radius:6px';
            row.innerHTML = `
                <span style="font-size:0.85rem"><strong>${escHtml(m.username)}</strong>${m.name && m.name !== m.username ? ' <span style="color:var(--text-muted);font-size:0.78rem">('+escHtml(m.name)+')</span>' : ''}</span>
                <form method="POST" style="margin:0">
                    <input type="hidden" name="action"   value="remove_member" />
                    <input type="hidden" name="group_id" value="${escHtml(String(gid))}" />
                    <input type="hidden" name="user_id"  value="${escHtml(String(m.user_id))}" />
                    <button class="btn-icon danger" type="submit" title="<?= __('groups.remove_from_group') ?>" style="width:26px;height:26px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                            <path d="M10 11v6"></path><path d="M14 11v6"></path>
                            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                        </svg>
                    </button>
                </form>`;
            list.appendChild(row);
        });
    }
    feather.replace({ 'stroke-width': 2 });
}

document.getElementById('addMemberForm').addEventListener('submit', function(e) {
    const sel = document.getElementById('addMemberSelect');
    if (!sel.value || sel.value === '0') { e.preventDefault(); return; }
});

function fillRename(btn) {
    document.getElementById('renameId').value   = btn.dataset.id;
    document.getElementById('renameName').value = btn.dataset.name;
    feather.replace({ 'stroke-width': 2 });
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;');
}
</script>

<?php page_close(); ?>
