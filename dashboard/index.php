<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

session_init();

if (is_logged_in()) {
    header('Location: /home.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $resp = api_post('/login', ['username' => $username, 'password' => $password]);

        if (api_ok($resp) && !empty($resp['access_token'])) {
            $_SESSION['access_token']  = $resp['access_token'];
            $_SESSION['username']      = $resp['user']['name']         ?? $username;
            $_SESSION['display_name']  = $resp['user']['display_name'] ?? $username;
            $_SESSION['is_admin']      = !empty($resp['user']['is_admin']);
            $_SESSION['user_id']       = $resp['user']['id']           ?? 0;
            header('Location: /home.php');
            exit;
        } else {
            $error = api_error($resp);
            if (str_contains($error, '401') || str_contains(strtolower($error), 'unauthori')) {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="icon" type="image/x-icon" href="/assets/favicon.ico" />
  <link rel="icon" type="image/svg+xml" href="/assets/icon.svg" />
  <link rel="stylesheet" href="/assets/theme.css" />
  <link rel="stylesheet" href="/assets/style.css" />
</head>
<body>

<div class="login-page">
  <div class="login-card">
    <div class="login-brand">
      <img src="/assets/icon.png" alt="<?= APP_NAME ?>" />
      <h1><?= APP_NAME ?></h1>
      <p>Remote Desktop Management</p>
    </div>

    <?php if ($error): ?>
    <div class="login-error">
      <svg data-feather="alert-circle" style="width:16px;height:16px;flex-shrink:0"></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="login-form" autocomplete="on">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               placeholder="Enter username" required autofocus />
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password"
               placeholder="Enter password" required />
      </div>
      <button type="submit" class="btn btn-primary">
        <svg data-feather="log-in"></svg>
        Sign In
      </button>
    </form>
    <p style="font-size:0.65rem;color:var(--text-muted);text-align:center;margin-top:16px">
      Built on <a href="https://rustdesk.com" target="_blank" rel="noopener"
        style="color:var(--text-muted);text-decoration:underline;text-underline-offset:2px">RustDesk</a>
      open-source server &mdash; AGPL-3.0
    </p>
  </div>
</div>

<script src="https://unpkg.com/feather-icons@4.29.1/dist/feather.min.js"></script>
<script>
  const saved = localStorage.getItem('skona-theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);
  document.addEventListener('DOMContentLoaded', () => feather.replace({ 'stroke-width': 2 }));
</script>
</body>
</html>
