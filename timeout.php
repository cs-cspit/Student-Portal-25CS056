<?php
declare(strict_types=1);

$wasExpired = ($_GET['reason'] ?? '') === 'expired';
$message = $wasExpired
    ? 'Your session expired after inactivity. You have been logged out; please sign in again.'
    : 'You were signed out.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Session Ended</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="container" style="max-width: 640px; margin: 3rem auto;">
        <section class="card">
            <h1>Session Ended</h1>
            <p class="form-message form-error-summary" role="status"><?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <a class="btn" href="login.php?timeout=1">Return to login</a>
        </section>
    </main>
</body>
</html>
