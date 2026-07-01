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

$resp = api_get('/settings');
$settings = $resp['data'] ?? [];
$currentDefault = $settings['default_language'] ?? 'en';
$availableLangs = LanguageService::getAvailable();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $defaultLang = trim($_POST['default_language'] ?? 'en');
        $resp = api_put('/settings', ['default_language' => $defaultLang]);
        $flash = api_ok($resp) ? __('settings.saved') : api_error($resp);
        if (!api_ok($resp)) $flashType = 'danger';
    }
}

page_open(__('settings.title'));
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flashType ?>" style="margin:0 0 20px"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div style="max-width:560px;display:grid;gap:24px">

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg data-feather="settings"></svg>
        <?= __('settings.general') ?>
      </div>
    </div>
    <div class="card-body">
      <form method="POST" class="form-grid">
        <input type="hidden" name="action" value="save_settings" />

        <div class="form-group">
          <label for="defaultLanguage"><?= __('settings.default_language') ?></label>
          <select id="defaultLanguage" name="default_language" style="padding:8px 10px;border-radius:var(--radius);border:1px solid var(--border);background:var(--bg-input);color:var(--text);width:100%;font-size:var(--font-sm)">
            <?php foreach ($availableLangs as $l): ?>
            <option value="<?= htmlspecialchars($l['code']) ?>"<?= $l['code'] === $currentDefault ? ' selected' : '' ?>>
              <?= htmlspecialchars($l['name_native']) ?> (<?= htmlspecialchars($l['name']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
          <small style="display:block;margin-top:4px;color:var(--text-muted);font-size:0.7rem">
            <?= __('settings.default_language_hint') ?>
          </small>
        </div>

        <div>
          <button type="submit" class="btn btn-primary">
            <svg data-feather="save"></svg>
            <?= __('settings.save') ?>
          </button>
        </div>
      </form>
    </div>
  </div>

</div>

<?php page_close(); ?>
