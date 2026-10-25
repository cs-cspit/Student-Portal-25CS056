<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
$user = requireAuthenticatedUser('Student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="container" style="max-width: 900px; margin: 3rem auto;">
        <section class="card">
            <h1>Student Dashboard</h1>
            <p>Welcome, <?= htmlspecialchars($user['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>. This page is restricted to Student users.</p>
            <a class="btn btn-secondary" href="dashboard.php">Main dashboard</a>
            <section aria-labelledby="practical-10-testing" style="margin-top: 2rem;">
                <h2 id="practical-10-testing">Practical 10 Testing</h2>
                <p>Use this link to verify that students cannot open the admin dashboard.</p>
                <a class="btn btn-danger" href="admin_dashboard.php">Test Admin Access</a>
            </section>
            <form action="logout.php" method="POST" style="margin-top: 1rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['logout_csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <button type="submit" class="btn">Log out</button>
            </form>
        </section>
    </main>
</body>
</html>
