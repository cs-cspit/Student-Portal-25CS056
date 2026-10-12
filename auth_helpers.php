<?php
declare(strict_types=1);

const DEFAULT_SESSION_TIMEOUT_SECONDS = 900;

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function sessionTimeoutSeconds(): int
{
    $configuredTimeout = getenv('AUTH_SESSION_TIMEOUT');
    if ($configuredTimeout === false || filter_var($configuredTimeout, FILTER_VALIDATE_INT) === false) {
        return DEFAULT_SESSION_TIMEOUT_SECONDS;
    }

    return max(60, min((int) $configuredTimeout, 86400));
}

function redirectTo(string $location): never
{
    header('Location: ' . $location, true, 302);
    exit;
}

function destroyCurrentSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

function requireAuthenticatedUser(?string $requiredRole = null): array
{
    startSecureSession();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $lastActivity = $_SESSION['last_activity'] ?? null;
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        redirectTo('login.php?auth=required');
    }

    if (!is_int($lastActivity) || time() - $lastActivity > sessionTimeoutSeconds()) {
        destroyCurrentSession();
        redirectTo('timeout.php?reason=expired');
    }

    $_SESSION['last_activity'] = time();

    if ($requiredRole !== null && $_SESSION['role'] !== $requiredRole) {
        http_response_code(403);
        $roleLabel = $requiredRole === 'Admin' ? 'administrator' : 'student';
        $safeRole = htmlspecialchars($roleLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ownDashboard = $_SESSION['role'] === 'Admin' ? 'admin_dashboard.php' : 'student_dashboard.php';
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Access Denied</title><link rel="stylesheet" href="style.css"></head><body><main class="container"><section class="card"><h1>403 - Unauthorized Access</h1><p>You do not have permission to access the ' . $safeRole . ' dashboard.</p><p><a class="btn" href="' . $ownDashboard . '">Go to your dashboard</a> <a class="btn btn-secondary" href="dashboard.php">Main dashboard</a></p></section></main></body></html>';
        exit;
    }

    return [
        'user_id' => (int) $_SESSION['user_id'],
        'full_name' => (string) $_SESSION['full_name'],
        'email' => (string) $_SESSION['email'],
        'role' => (string) $_SESSION['role'],
    ];
}
