<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';
$user = requireAuthenticatedUser('Admin');

$students = [];
$studentLoadError = '';

try {
    $connection = createMysqliConnection();
    // Restrict the result set to accounts explicitly assigned the Student role.
    $query = $connection->prepare(
        "SELECT u.id, u.full_name, u.username, u.student_id, u.email
         FROM user_accounts AS u
         INNER JOIN auth_user_roles AS r ON r.user_id = u.id
         WHERE r.role = ?
         ORDER BY u.full_name ASC, u.id ASC"
    );
    $role = 'Student';
    $query->bind_param('s', $role);
    $query->execute();
    $query->bind_result($studentAccountId, $studentName, $studentUsername, $studentId, $studentEmail);
    while ($query->fetch()) {
        $students[] = [
            'id' => (int) $studentAccountId,
            'full_name' => (string) $studentName,
            'username' => (string) $studentUsername,
            'student_id' => (string) $studentId,
            'email' => (string) $studentEmail,
        ];
    }
} catch (mysqli_sql_exception | RuntimeException $exception) {
    error_log('StudentHub admin dashboard student query failed: ' . $exception->getMessage());
    $studentLoadError = 'Student records are temporarily unavailable. Please check the database connection and try again.';
} finally {
    if (isset($query) && $query instanceof mysqli_stmt) {
        $query->close();
    }
    if (isset($connection) && $connection instanceof mysqli) {
        $connection->close();
    }
}

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="container" style="max-width: 1100px; margin: 3rem auto;">
        <section class="card">
            <h1>Admin Dashboard</h1>
            <p>Welcome, <?= $escape($user['full_name']) ?>. This page is restricted to Admin users.</p>
            <a class="btn btn-secondary" href="dashboard.php">Main dashboard</a>
            <a class="btn" href="student_management.php">Manage students</a>
            <a class="btn" href="event_management.php">Manage events</a>
            <section aria-labelledby="practical-10-testing" style="margin-top: 2rem;">
                <h2 id="practical-10-testing">Practical 10 Testing</h2>
                <p>Use this link to verify that admins cannot open the student dashboard.</p>
                <a class="btn btn-danger" href="student_dashboard.php">Test Student Access</a>
            </section>
            <form action="logout.php" method="POST" style="margin-top: 1rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['logout_csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <button type="submit" class="btn">Log out</button>
            </form>

            <h2 style="margin-top: 2rem;">Registered Students</h2>
            <?php if ($studentLoadError !== ''): ?>
                <p class="form-message form-error-summary" role="alert"><?= $escape($studentLoadError) ?></p>
            <?php elseif ($students === []): ?>
                <p role="status">No users with the Student role are registered yet.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <caption>All registered users assigned the Student role (<?= count($students) ?>)</caption>
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Name</th>
                                <th scope="col">Username</th>
                                <th scope="col">Student ID</th>
                                <th scope="col">Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= $student['id'] ?></td>
                                    <td><?= $escape($student['full_name']) ?></td>
                                    <td><?= $escape($student['username']) ?></td>
                                    <td><?= $escape($student['student_id']) ?></td>
                                    <td><?= $escape($student['email']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
