<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/layout.php';

require_login();
$user = current_user();

$flash     = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $flash     = 'All password fields are required.';
            $flashType = 'danger';
        } elseif ($new !== $confirm) {
            $flash     = 'New passwords do not match.';
            $flashType = 'danger';
        } elseif (strlen($new) < 8) {
            $flash     = 'New password must be at least 8 characters.';
            $flashType = 'danger';
        } else {
            $resp = api_put('/user/password', [
                'current_password' => $current,
                'new_password'     => $new,
            ]);
            if (api_ok($resp)) {
                $flash = 'Password changed successfully.';
            } else {
                $flash     = api_error($resp);
                $flashType = 'danger';
            }
        }
    }
}

page_open('My Profile');
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flashType ?>" style="margin:0 0 20px"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div style="max-width:560px;display:grid;gap:24px">

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="user"></svg>
        Account
      </div>
    </div>
    <div class="card-body">
      <div style="display:grid;gap:12px;font-size:var(--font-sm)">
        <div class="info-row">
          <span class="info-label">Username</span>
          <span class="info-value"><code><?= htmlspecialchars($user['username']) ?></code></span>
        </div>
        <div class="info-row">
          <span class="info-label">Display name</span>
          <span class="info-value"><?= htmlspecialchars($user['display_name'] ?: $user['username']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Role</span>
          <span class="info-value">
            <?php if ($user['is_admin']): ?>
              <span class="badge badge-admin">Admin</span>
            <?php else: ?>
              <span class="badge badge-info">User</span>
            <?php endif; ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="lock"></svg>
        Change Password
      </div>
    </div>
    <div class="card-body">
      <form method="POST" class="form-grid" autocomplete="off">
        <input type="hidden" name="action" value="change_password" />

        <div class="form-group">
          <label for="current_password">Current Password</label>
          <input type="password" id="current_password" name="current_password"
                 placeholder="Enter current password" required autocomplete="current-password" />
        </div>

        <div class="form-group">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password"
                 placeholder="Min 8 characters" required autocomplete="new-password"
                 oninput="checkMatch()" />
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password"
                 placeholder="Repeat new password" required autocomplete="new-password"
                 oninput="checkMatch()" />
          <small id="matchHint" style="display:none;margin-top:4px;font-size:0.7rem"></small>
        </div>

        <div>
          <button type="submit" class="btn btn-primary">
            <svg data-feather="save"></svg>
            Update Password
          </button>
        </div>
      </form>
    </div>
  </div>

</div>

<script>
function checkMatch() {
    const np   = document.getElementById('new_password').value;
    const cp   = document.getElementById('confirm_password').value;
    const hint = document.getElementById('matchHint');
    if (!cp) { hint.style.display = 'none'; return; }
    hint.style.display = 'block';
    if (np === cp) {
        hint.textContent = '✓ Passwords match';
        hint.style.color = 'var(--teal)';
    } else {
        hint.textContent = '✗ Passwords do not match';
        hint.style.color = 'var(--danger)';
    }
}
</script>

<?php page_close(); ?>
