<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function escapeDatabaseOutput(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$errorMessage = '';
$students = [];
$events = [];
$registrations = [];
$connectionSucceeded = false;

try {
    $pdo = createDatabaseConnection();
    $connectionSucceeded = true;

    $studentQuery = $pdo->prepare(
        'SELECT student_id, roll_number, full_name, email, department
         FROM students
         WHERE department LIKE :department
         ORDER BY roll_number
         LIMIT :row_limit'
    );
    $studentQuery->bindValue(':department', '%', PDO::PARAM_STR);
    $studentQuery->bindValue(':row_limit', 10, PDO::PARAM_INT);
    $studentQuery->execute();
    $students = $studentQuery->fetchAll();

    $eventQuery = $pdo->prepare(
        'SELECT event_id, title, category, event_date, venue
         FROM events
         WHERE event_date >= :start_date
         ORDER BY event_date
         LIMIT :row_limit'
    );
    $eventQuery->bindValue(':start_date', '2026-01-01', PDO::PARAM_STR);
    $eventQuery->bindValue(':row_limit', 10, PDO::PARAM_INT);
    $eventQuery->execute();
    $events = $eventQuery->fetchAll();

    $registrationQuery = $pdo->prepare(
        'SELECT r.registration_id, s.roll_number, s.full_name, e.title, r.registration_status
         FROM registrations AS r
         INNER JOIN students AS s ON s.student_id = r.student_id
         INNER JOIN events AS e ON e.event_id = r.event_id
         WHERE r.registration_status <> :excluded_status
         ORDER BY r.registration_id
         LIMIT :row_limit'
    );
    $registrationQuery->bindValue(':excluded_status', 'Cancelled', PDO::PARAM_STR);
    $registrationQuery->bindValue(':row_limit', 10, PDO::PARAM_INT);
    $registrationQuery->execute();
    $registrations = $registrationQuery->fetchAll();
} catch (RuntimeException $exception) {
    $errorMessage = 'Database connection failed. Check that MySQL is running, schema.sql has been imported, and your DB_* settings are correct.';
} catch (PDOException $exception) {
    error_log('StudentHub test query failed: ' . $exception->getMessage());
    $errorMessage = 'The database connected, but a test query failed. Confirm that schema.sql was imported correctly.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - PDO Database Test</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .database-test { max-width: 1100px; margin: 2rem auto; }
        .database-test table { margin: 1rem 0 2rem; }
        .database-test .connection-success { color: #166534; background: #dcfce7; border: 1px solid #86efac; }
        .database-test .connection-error { color: #991b1b; background: #fee2e2; border: 1px solid #fca5a5; }
        .database-test .connection-success,
        .database-test .connection-error { padding: 1rem; border-radius: 6px; }
        .database-test .table-scroll { overflow-x: auto; }
    </style>
</head>
<body>
    <main class="container database-test">
        <section class="card">
            <h1>PDO Database Connection Test</h1>

            <?php if ($connectionSucceeded): ?>
                <p class="connection-success" role="status">Database connection successful. Prepared statements returned the following records.</p>
            <?php else: ?>
                <p class="connection-error" role="alert"><?= escapeDatabaseOutput($errorMessage) ?></p>
            <?php endif; ?>

            <?php if ($connectionSucceeded): ?>
                <h2>Students (<?= count($students) ?>)</h2>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>ID</th><th>Roll Number</th><th>Name</th><th>Email</th><th>Department</th></tr></thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= (int) $student['student_id'] ?></td>
                                    <td><?= escapeDatabaseOutput($student['roll_number']) ?></td>
                                    <td><?= escapeDatabaseOutput($student['full_name']) ?></td>
                                    <td><?= escapeDatabaseOutput($student['email']) ?></td>
                                    <td><?= escapeDatabaseOutput($student['department']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h2>Events (<?= count($events) ?>)</h2>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Date</th><th>Venue</th></tr></thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?= (int) $event['event_id'] ?></td>
                                    <td><?= escapeDatabaseOutput($event['title']) ?></td>
                                    <td><?= escapeDatabaseOutput($event['category']) ?></td>
                                    <td><?= escapeDatabaseOutput($event['event_date']) ?></td>
                                    <td><?= escapeDatabaseOutput($event['venue']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h2>Registrations (<?= count($registrations) ?>)</h2>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>ID</th><th>Student Roll</th><th>Student Name</th><th>Event</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($registrations as $registration): ?>
                                <tr>
                                    <td><?= (int) $registration['registration_id'] ?></td>
                                    <td><?= escapeDatabaseOutput($registration['roll_number']) ?></td>
                                    <td><?= escapeDatabaseOutput($registration['full_name']) ?></td>
                                    <td><?= escapeDatabaseOutput($registration['title']) ?></td>
                                    <td><?= escapeDatabaseOutput($registration['registration_status']) ?></td>
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
