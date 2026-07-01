<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/LanguageService.php';

$_NAV = [
    ['path' => '/home.php',         'icon' => 'grid',       'label' => 'nav.dashboard'],
    ['path' => '/devices.php',      'icon' => 'monitor',    'label' => 'nav.devices'],
    ['path' => '/sessions.php',     'icon' => 'cast',       'label' => 'nav.sessions'],
    ['path' => '/addressbook.php',  'icon' => 'book',       'label' => 'nav.address_book'],
    ['path' => '/users.php',        'icon' => 'users',      'label' => 'nav.users',    'admin' => true],
    ['path' => '/groups.php',       'icon' => 'layers',     'label' => 'nav.groups',   'admin' => true],
    ['path' => '/audit.php',        'icon' => 'activity',   'label' => 'nav.audit_log'],
    ['path' => '/server.php',       'icon' => 'server',     'label' => 'nav.server'],
];

function page_open(string $title, string $activeFile = ''): void {
    global $_NAV;
    session_init();
    $user    = current_user();
    $isAdmin = $user['is_admin'];
    $active  = $activeFile ?: basename($_SERVER['PHP_SELF'] ?? '');
    $appName = APP_NAME;

    // Initialise language
    $langCode = get_effective_language();
    LanguageService::init($langCode);
?><!DOCTYPE html>
<html lang="<?= LanguageService::getCode() ?>" data-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($title) ?> | <?= $appName ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="icon" type="image/x-icon" href="/assets/favicon.ico" />
  <link rel="icon" type="image/svg+xml" href="/assets/icon.svg" />
  <link rel="stylesheet" href="/assets/theme.css" />
  <link rel="stylesheet" href="/assets/style.css" />
</head>
<body>

<div class="app-layout" id="appLayout">

  <nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <img src="/assets/icon.png" class="brand-icon" alt="<?= $appName ?>" />
      <span class="brand-name"><?= $appName ?></span>
    </div>

    <ul class="nav-list">
<?php foreach ($_NAV as $item):
    if (!empty($item['admin']) && !$isAdmin) continue;
    $isCurrent = (basename($active) === basename($item['path']));
?>      <li class="nav-item<?= $isCurrent ? ' active' : '' ?>">
        <a href="<?= $item['path'] ?>" class="nav-link">
          <svg class="nav-icon" data-feather="<?= $item['icon'] ?>"></svg>
          <span class="nav-label"><?= __($item['label']) ?></span>
        </a>
      </li>
<?php endforeach; ?>
<?php if ($isAdmin): ?>
      <li class="nav-item<?= basename($active) === 'settings.php' ? ' active' : '' ?>">
        <a href="/settings.php" class="nav-link">
          <svg class="nav-icon" data-feather="settings"></svg>
          <span class="nav-label"><?= __('nav.settings') ?></span>
        </a>
      </li>
<?php endif; ?>
    </ul>

    <div class="sidebar-footer">
      <button class="nav-link collapse-toggle" id="collapseBtn" title="<?= __('nav.collapse') ?>">
        <svg data-feather="chevrons-left" class="nav-icon"></svg>
        <span class="nav-label"><?= __('nav.collapse') ?></span>
      </button>
    </div>
  </nav>

  <div class="main-wrap">
    <header class="topbar">
      <button class="mobile-toggle" id="mobileToggle" aria-label="Menu">
        <svg data-feather="menu"></svg>
      </button>
      <h1 class="topbar-title"><?= htmlspecialchars($title) ?></h1>
      <div class="topbar-user">
        <?php if ($isAdmin): ?>
          <span class="badge badge-admin"><?= __('users.admin') ?></span>
        <?php endif; ?>
        <a href="/profile.php" class="topbar-logout" title="<?= __('nav.my_profile') ?>" style="<?= basename($active) === 'profile.php' ? 'color:var(--teal)' : '' ?>">
          <span class="user-name"><?= htmlspecialchars($user['display_name'] ?: $user['username']) ?></span>
        </a>
        <a href="/logout.php" class="topbar-logout" title="Logout">
          <svg data-feather="log-out"></svg>
        </a>
        <button class="theme-toggle" id="themeToggle" title="Toggle theme">
          <svg data-feather="sun" class="icon-sun"></svg>
          <svg data-feather="moon" class="icon-moon"></svg>
        </button>
      </div>
    </header>

    <?php if (!empty($_GET['notice'])): ?>
    <div class="alert alert-info"><?= htmlspecialchars($_GET['notice']) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <main class="content">
<?php
}

function page_close(): void {
?>
    </main>
  </div>
</div>

<script src="https://unpkg.com/feather-icons@4.29.1/dist/feather.min.js"></script>
<script src="/assets/app.js"></script>
</body>
</html>
<?php
}
