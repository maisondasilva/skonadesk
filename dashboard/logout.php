<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/api.php';

session_init();

if (!empty($_SESSION['access_token'])) {
    api_post('/logout', []);
}

session_destroy();

header('Location: /index.php');
exit;
