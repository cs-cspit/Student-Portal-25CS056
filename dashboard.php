<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
$user = requireAuthenticatedUser();
$sessionRegenerated = !empty($_SESSION['session_regenerated']);
unset($_SESSION['session_regenerated']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="container" style="max-width: 900px; margin: 3rem auto;">
        <section class="card">
            <h1>Welcome, <?= htmlspecialchars($user['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
            <p>Signed in as <?= htmlspecialchars($user['role'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= htmlspecialchars($user['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>).</p>
            <?php if ($sessionRegenerated): ?><p class="form-message form-success-summary" role="status">Login successful. Your session ID was regenerated.</p><?php endif; ?>
            <p>Your session expires after <?= (int) (sessionTimeoutSeconds() / 60) ?> minutes of inactivity.</p>
            <nav aria-label="Role dashboards">
                <?php if ($user['role'] === 'Admin'): ?>
                    <a class="btn" href="admin_dashboard.php">Admin dashboard</a>
                <?php else: ?>
                    <a class="btn" href="student_dashboard.php">Student dashboard</a>
                <?php endif; ?>
            </nav>
            <form action="logout.php" method="POST" style="margin-top: 1rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['logout_csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <button type="submit" class="btn btn-secondary">Log out</button>
            </form>
        </section>
    </main>
</body>
</html>
