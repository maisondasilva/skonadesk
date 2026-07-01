<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
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
            $flash     = __('profile.password_required');
            $flashType = 'danger';
        } elseif ($new !== $confirm) {
            $flash     = __('profile.password_mismatch');
            $flashType = 'danger';
        } elseif (strlen($new) < 8) {
            $flash     = __('profile.password_too_short');
            $flashType = 'danger';
        } else {
            $resp = api_put('/user/password', [
                'current_password' => $current,
                'new_password'     => $new,
            ]);
            if (api_ok($resp)) {
                $flash = __('profile.password_changed');
            } else {
                $flash     = api_error($resp);
                $flashType = 'danger';
            }
        }

    } elseif ($action === 'change_language') {
        $language = trim($_POST['language'] ?? '');
        if ($language) {
            $resp = api_put('/user/language', ['language' => $language]);
            if (api_ok($resp)) {
                set_session_language($language);
                // Re-init LanguageService so the page renders in the new language
                LanguageService::init($language);
                $flash = __('settings.saved');
            } else {
                $flash     = api_error($resp);
                $flashType = 'danger';
            }
        }
    }
}

$availableLangs = LanguageService::getAvailable();
$currentLang = $user['language'] ?: get_effective_language();

page_open(__('profile.title'));
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flashType ?>" style="margin:0 0 20px"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div style="max-width:560px;display:grid;gap:24px">

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="user"></svg>
        <?= __('profile.account') ?>
      </div>
    </div>
    <div class="card-body">
      <div style="display:grid;gap:12px;font-size:var(--font-sm)">
        <div class="info-row">
          <span class="info-label"><?= __('profile.username') ?></span>
          <span class="info-value"><code><?= htmlspecialchars($user['username']) ?></code></span>
        </div>
        <div class="info-row">
          <span class="info-label"><?= __('profile.display_name') ?></span>
          <span class="info-value"><?= htmlspecialchars($user['display_name'] ?: $user['username']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label"><?= __('profile.role') ?></span>
          <span class="info-value">
            <?php if ($user['is_admin']): ?>
              <span class="badge badge-admin"><?= __('users.admin') ?></span>
            <?php else: ?>
              <span class="badge badge-info"><?= __('users.user') ?></span>
            <?php endif; ?>
          </span>
        </div>
        <div class="info-row">
          <span class="info-label"><?= __('profile.language') ?></span>
          <form method="POST" class="info-value" style="display:flex;align-items:center;gap:8px;margin:0">
            <input type="hidden" name="action" value="change_language" />
            <select name="language" onchange="this.form.submit()" style="padding:4px 8px;border-radius:var(--radius);border:1px solid var(--border);background:var(--bg-input);color:var(--text);font-size:0.8rem">
              <?php foreach ($availableLangs as $l): ?>
              <option value="<?= htmlspecialchars($l['code']) ?>"<?= $l['code'] === $currentLang ? ' selected' : '' ?>>
                <?= htmlspecialchars($l['name_native']) ?> (<?= htmlspecialchars($l['name']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="lock"></svg>
        <?= __('profile.change_password') ?>
      </div>
    </div>
    <div class="card-body">
      <form method="POST" class="form-grid" autocomplete="off">
        <input type="hidden" name="action" value="change_password" />

        <div class="form-group">
          <label for="current_password"><?= __('profile.current_password') ?></label>
          <input type="password" id="current_password" name="current_password"
                 placeholder="<?= __('profile.current_placeholder') ?>" required autocomplete="current-password" />
        </div>

        <div class="form-group">
          <label for="new_password"><?= __('profile.new_password') ?></label>
          <input type="password" id="new_password" name="new_password"
                 placeholder="<?= __('profile.new_placeholder') ?>" required autocomplete="new-password"
                 oninput="checkMatch()" />
        </div>

        <div class="form-group">
          <label for="confirm_password"><?= __('profile.confirm_password') ?></label>
          <input type="password" id="confirm_password" name="confirm_password"
                 placeholder="<?= __('profile.confirm_placeholder') ?>" required autocomplete="new-password"
                 oninput="checkMatch()" />
          <small id="matchHint" style="display:none;margin-top:4px;font-size:0.7rem"></small>
        </div>

        <div>
          <button type="submit" class="btn btn-primary">
            <svg data-feather="save"></svg>
            <?= __('profile.update_password') ?>
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
        hint.textContent = '<?= __('profile.match_hint_match') ?>';
        hint.style.color = 'var(--teal)';
    } else {
        hint.textContent = '<?= __('profile.match_hint_no_match') ?>';
        hint.style.color = 'var(--danger)';
    }
}
</script>

<?php page_close(); ?>
