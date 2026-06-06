<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Created by Mike Hayward — github.com/Skonamonkey
require_once __DIR__ . '/config.php';

function session_init(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        session_name('SKONA_SESS');
        session_start();
    }
}

function require_login(): void {
    session_init();
    if (empty($_SESSION['access_token'])) {
        header('Location: /index.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (empty($_SESSION['is_admin'])) {
        header('Location: /home.php?notice=Admin+access+required');
        exit;
    }
}

function is_logged_in(): bool {
    session_init();
    return !empty($_SESSION['access_token']);
}

function current_user(): array {
    return [
        'id'           => $_SESSION['user_id']      ?? 0,
        'username'     => $_SESSION['username']     ?? '',
        'display_name' => $_SESSION['display_name'] ?? '',
        'is_admin'     => $_SESSION['is_admin']      ?? false,
        'token'        => $_SESSION['access_token']  ?? '',
    ];
}
