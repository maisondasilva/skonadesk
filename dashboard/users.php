<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/layout.php';

require_admin();
$me = current_user();

$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 20;
$flash    = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $resp = api_post('/users/add', [
            'username' => trim($_POST['username'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'name'     => trim($_POST['name']     ?? ''),
            'email'    => trim($_POST['email']    ?? ''),
            'is_admin' => !empty($_POST['is_admin']),
        ]);
        $flash = api_ok($resp) ? __('users.user_created') : api_error($resp);
        if (!api_ok($resp)) $flashType = 'danger';

    } elseif ($action === 'edit') {
        $uname = $_POST['username'] ?? '';
        $data  = [];
        if (!empty($_POST['new_password'])) $data['password'] = $_POST['new_password'];
        if (isset($_POST['display_name'])) $data['name']     = trim($_POST['display_name']);
        if (isset($_POST['email']))        $data['email']    = trim($_POST['email']);
        $data['is_admin'] = !empty($_POST['is_admin']);
        $data['status']   = !empty($_POST['active']);
        $resp = api_put("/users/$uname", $data);
        $flash = api_ok($resp) ? __('users.user_updated') : api_error($resp);
        if (!api_ok($resp)) $flashType = 'danger';

    } elseif ($action === 'delete') {
        $uname = $_POST['username'] ?? '';
        $resp  = api_delete("/users/$uname");
        $flash = api_ok($resp) ? __('users.user_deleted') : api_error($resp);
        if (!api_ok($resp)) $flashType = 'danger';
    }
}

$resp  = api_get('/users', ['current' => $page, 'pageSize' => $pageSize]);
$users = $resp['data']  ?? [];
$total = $resp['total'] ?? 0;
$pages = (int)ceil($total / $pageSize);

page_open(__('users.title'));
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flashType ?>" style="margin:0 0 20px"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="section-header">
  <h2><?= __p('users.count', $total) ?></h2>
  <button class="btn btn-primary" data-modal-open="addUserModal">
    <svg data-feather="user-plus"></svg>
    <?= __('users.add_user') ?>
  </button>
</div>

<div class="card">
  <div class="table-wrap">
    <?php if (empty($users)): ?>
    <div class="empty-state">
      <svg data-feather="users"></svg>
      <h3><?= __('users.no_users') ?></h3>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th><?= __('users.username') ?></th>
          <th><?= __('users.display_name') ?></th>
          <th><?= __('users.email') ?></th>
          <th><?= __('users.role') ?></th>
          <th><?= __('users.status') ?></th>
          <th><?= __('users.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u):
            $uname  = $u['name']         ?? '';
            $dname  = $u['display_name'] ?? '';
            $email  = $u['email']        ?? '';
            $admin  = !empty($u['is_admin']);
            $active = ($u['status'] ?? 1) == 1;
            $isSelf = ($uname === $me['username']);
        ?>
        <tr>
          <td><strong><?= htmlspecialchars($uname) ?></strong><?= $isSelf ? ' <span style="font-size:0.65rem;color:var(--text-muted)">' . __('users.you_label') . '</span>' : '' ?></td>
          <td><?= htmlspecialchars($dname) ?></td>
          <td style="color:var(--text-muted)"><?= htmlspecialchars($email) ?></td>
          <td><?= $admin ? '<span class="badge badge-admin">' . __('users.admin') . '</span>' : '<span style="color:var(--text-muted)">' . __('users.user') . '</span>' ?></td>
          <td><?= $active ? '<span class="badge badge-active">' . __('users.active') . '</span>' : '<span class="badge badge-inactive">' . __('users.inactive') . '</span>' ?></td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn-icon" title="<?= __('users.edit_user') ?>"
                data-modal-open="editUserModal"
                data-username="<?= htmlspecialchars($uname) ?>"
                data-display="<?= htmlspecialchars($dname) ?>"
                data-email="<?= htmlspecialchars($email) ?>"
                data-admin="<?= $admin ? '1' : '0' ?>"
                data-active="<?= $active ? '1' : '0' ?>"
                onclick="fillEditUser(this)">
                <svg data-feather="edit-2"></svg>
              </button>
              <?php if (!$isSelf): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action"   value="delete" />
                <input type="hidden" name="username" value="<?= htmlspecialchars($uname) ?>" />
                <button class="btn-icon danger" type="submit"
                  data-confirm="<?= htmlspecialchars(__('users.confirm_delete', $uname)) ?>">
                  <svg data-feather="trash-2"></svg>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <span class="page-info"><?= __('users.page', $page, $pages) ?></span>
    <?php for ($p = max(1,$page-2); $p <= min($pages,$page+2); $p++): ?>
    <a class="page-btn<?= $p===$page?' active':'' ?>" href="?page=<?= $p ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<div class="modal-backdrop" id="addUserModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><?= __('users.add_title') ?></span>
      <button class="modal-close" data-modal-close><svg data-feather="x"></svg></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add" />
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label><?= __('users.username') ?> *</label>
            <input type="text" name="username" required placeholder="<?= __('users.username_placeholder') ?>" />
          </div>
          <div class="form-group">
            <label><?= __('users.display_name') ?></label>
            <input type="text" name="name" placeholder="<?= __('users.display_name_placeholder') ?>" />
          </div>
        </div>
        <div class="form-row" style="margin-top:14px">
          <div class="form-group">
            <label><?= __('users.password') ?> *</label>
            <input type="password" name="password" required placeholder="<?= __('users.password_placeholder') ?>" />
          </div>
          <div class="form-group">
            <label><?= __('users.email') ?></label>
            <input type="email" name="email" placeholder="<?= __('users.email_placeholder') ?>" />
          </div>
        </div>
        <div class="form-check" style="margin-top:14px">
          <input type="checkbox" name="is_admin" id="newAdmin" value="1" />
          <label for="newAdmin"><?= __('users.admin_checkbox') ?></label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close><?= __('general.cancel') ?></button>
        <button type="submit" class="btn btn-primary">
          <svg data-feather="user-plus"></svg>
          <?= __('users.create_user') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="editUserModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><?= __('users.edit_title') ?></span>
      <button class="modal-close" data-modal-close><svg data-feather="x"></svg></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action"   value="edit" />
      <input type="hidden" name="username" id="editUsername" value="" />
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label><?= __('users.username') ?></label>
            <input type="text" id="editUsernameDisplay" readonly style="opacity:0.6" />
          </div>
          <div class="form-group">
            <label><?= __('users.display_name') ?></label>
            <input type="text" name="display_name" id="editDisplayName" />
          </div>
        </div>
        <div class="form-row" style="margin-top:14px">
          <div class="form-group">
            <label><?= __('users.new_password') ?> <span style="font-weight:400;color:var(--text-muted)">(<?= __('users.new_password_hint') ?>)</span></label>
            <input type="password" name="new_password" placeholder="<?= __('users.new_password_placeholder') ?>" />
          </div>
          <div class="form-group">
            <label><?= __('users.email') ?></label>
            <input type="email" name="email" id="editEmail" />
          </div>
        </div>
        <div style="display:flex;gap:20px;margin-top:14px">
          <div class="form-check">
            <input type="checkbox" name="is_admin" id="editAdmin" value="1" />
            <label for="editAdmin"><?= __('users.admin_checkbox') ?></label>
          </div>
          <div class="form-check">
            <input type="checkbox" name="active" id="editActive" value="1" checked />
            <label for="editActive"><?= __('users.active_checkbox') ?></label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close><?= __('general.cancel') ?></button>
        <button type="submit" class="btn btn-primary">
          <svg data-feather="save"></svg>
          <?= __('users.save_changes') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function fillEditUser(btn) {
    document.getElementById('editUsername').value        = btn.dataset.username;
    document.getElementById('editUsernameDisplay').value = btn.dataset.username;
    document.getElementById('editDisplayName').value     = btn.dataset.display;
    document.getElementById('editEmail').value           = btn.dataset.email;
    document.getElementById('editAdmin').checked         = btn.dataset.admin === '1';
    document.getElementById('editActive').checked        = btn.dataset.active === '1';
    feather.replace({ 'stroke-width': 2 });
}
</script>

<?php page_close(); ?>
