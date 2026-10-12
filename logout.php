<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_SESSION['logout_csrf_token'])
    || !is_string($_POST['csrf_token'] ?? null)
    || !hash_equals($_SESSION['logout_csrf_token'], $_POST['csrf_token'])) {
    http_response_code(400);
    exit('Invalid logout request. Return to your dashboard and try again.');
}

destroyCurrentSession();
redirectTo('login.php?logged_out=1');
