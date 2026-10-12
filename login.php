<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
startSecureSession();

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    redirectTo('dashboard.php');
}

if (!isset($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['login_csrf_token'];
$loginError = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
$statusMessage = isset($_GET['logged_out'])
    ? 'You have been logged out successfully.'
    : (isset($_GET['timeout'])
        ? 'Your session expired due to inactivity. Please log in again.'
        : (isset($_GET['auth']) ? 'Please log in to access that page.' : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Secure Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="container" style="max-width: 520px; margin: 3rem auto;">
        <section class="card">
            <h1>StudentHub Login</h1>
            <p>Sign in with your registered email and password.</p>
            <?php if ($statusMessage !== ''): ?><p class="form-message form-success-summary" role="status"><?= htmlspecialchars($statusMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>
            <?php if ($loginError !== ''): ?><p class="form-message form-error-summary" role="alert"><?= htmlspecialchars($loginError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><?php endif; ?>

            <form action="authenticate.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" required maxlength="254" autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn">Log in</button>
                <p>Demo student: student.demo@example.test / Student@123</p>
                <p>Demo admin: admin.demo@example.test / Admin@123</p>
                <p><a href="register_account.php">Create a student account</a></p>
            </form>
        </section>
    </main>
</body>
</html>
