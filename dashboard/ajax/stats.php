<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

session_init();
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

$resp = api_get('/stats');
unset($resp['_status']);
echo json_encode($resp);
