<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('login.php');
}

$submittedToken = $_POST['csrf_token'] ?? '';
$expectedToken = $_SESSION['login_csrf_token'] ?? null;
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
if (!is_string($expectedToken) || !is_string($submittedToken) || !hash_equals($expectedToken, $submittedToken)
    || !is_string($email) || !is_string($password)
    || trim($email) === '' || $password === ''
    || strlen($email) > 254 || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
    $_SESSION['login_error'] = 'Enter a valid email and password.';
    redirectTo('login.php');
}

try {
    $connection = createMysqliConnection();
    // Match the account's actual primary key (`id`) to the role's foreign key (`user_id`).
    $query = $connection->prepare(
        'SELECT u.id, u.full_name, u.email, u.password_hash, r.role
         FROM user_accounts AS u
         INNER JOIN auth_user_roles AS r ON r.user_id = u.id
         WHERE u.email = ?
         LIMIT 1'
    );
    $normalizedEmail = strtolower(trim($email));
    $query->bind_param('s', $normalizedEmail);
    $query->execute();
    $query->bind_result($userId, $fullName, $accountEmail, $passwordHash, $role);
    $userFound = $query->fetch();
    $query->close();
    $query = null;
    $connection->close();
    $connection = null;

    if ($userFound !== true || !password_verify($password, (string) $passwordHash)) {
        $_SESSION['login_error'] = 'Invalid email or password.';
        redirectTo('login.php');
    }

    if (!in_array($role, ['Admin', 'Student'], true)) {
        error_log('StudentHub login rejected an account with an invalid role.');
        $_SESSION['login_error'] = 'This account cannot sign in. Contact support.';
        redirectTo('login.php');
    }

    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => (int) $userId,
        'full_name' => (string) $fullName,
        'email' => (string) $accountEmail,
        'role' => (string) $role,
        'last_activity' => time(),
        'session_regenerated' => true,
        'logout_csrf_token' => bin2hex(random_bytes(32)),
    ];

    redirectTo('dashboard.php');
} catch (mysqli_sql_exception $exception) {
    error_log('StudentHub authentication query failed: ' . $exception->getMessage());
    $_SESSION['login_error'] = 'Login is temporarily unavailable. Confirm that the Practical 10 SQL file was imported.';
    redirectTo('login.php');
} catch (RuntimeException $exception) {
    error_log('StudentHub login database configuration failed: ' . $exception->getMessage());
    $_SESSION['login_error'] = 'Login is temporarily unavailable. Check the database connection and try again.';
    redirectTo('login.php');
} finally {
    if (isset($query) && $query instanceof mysqli_stmt) {
        $query->close();
    }
    if (isset($connection) && $connection instanceof mysqli) {
        $connection->close();
    }
}
