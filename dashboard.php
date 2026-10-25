<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';

$user = requireAuthenticatedUser();
$isAdmin = $user['role'] === 'Admin';
$sessionRegenerated = !empty($_SESSION['session_regenerated']);
unset($_SESSION['session_regenerated']);

$stats = ['users' => 0, 'students' => 0, 'events' => 0];
$latestRegistrations = [];
$auditLogs = [];
$dashboardError = '';
$connection = null;
$statsQuery = null;
$registrationsQuery = null;
$auditQuery = null;
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

if ($isAdmin) {
    try {
        $connection = createMysqliConnection();
        recordAuditAction($connection, 'dashboard.view', 'Admin opened the Practical 14 dashboard.');

        $statsQuery = $connection->prepare(
            'SELECT
                (SELECT COUNT(*) FROM user_accounts) AS user_count,
                (SELECT COUNT(*) FROM students) AS student_count,
                (SELECT COUNT(*) FROM events) AS event_count'
        );
        $statsQuery->execute();
        $statsQuery->bind_result($userCount, $studentCount, $eventCount);
        if ($statsQuery->fetch()) {
            $stats = [
                'users' => (int) $userCount,
                'students' => (int) $studentCount,
                'events' => (int) $eventCount,
            ];
        }
        $statsQuery->close();
        $statsQuery = null;

        $registrationsQuery = $connection->prepare(
            'SELECT r.registration_id, s.full_name, s.roll_number, e.title,
                    r.registered_at, r.registration_status
             FROM registrations AS r
             INNER JOIN students AS s ON s.student_id = r.student_id
             INNER JOIN events AS e ON e.event_id = r.event_id
             ORDER BY r.registered_at DESC, r.registration_id DESC
             LIMIT 10'
        );
        $registrationsQuery->execute();
        $registrationsQuery->bind_result($registrationId, $studentName, $rollNumber, $eventTitle, $registeredAt, $registrationStatus);
        while ($registrationsQuery->fetch()) {
            $latestRegistrations[] = [
                'id' => (int) $registrationId,
                'student' => (string) $studentName,
                'roll_number' => (string) $rollNumber,
                'event' => (string) $eventTitle,
                'registered_at' => (string) $registeredAt,
                'status' => (string) $registrationStatus,
            ];
        }
        $registrationsQuery->close();
        $registrationsQuery = null;

        $auditQuery = $connection->prepare(
            'SELECT admin_user_id, action, details, ip_address, created_at
             FROM audit_logs
             ORDER BY created_at DESC, audit_id DESC
             LIMIT 10'
        );
        $auditQuery->execute();
        $auditQuery->bind_result($auditAdminId, $auditAction, $auditDetails, $auditIp, $auditCreatedAt);
        while ($auditQuery->fetch()) {
            $auditLogs[] = [
                'admin_user_id' => (int) $auditAdminId,
                'action' => (string) $auditAction,
                'details' => (string) $auditDetails,
                'ip_address' => (string) $auditIp,
                'created_at' => (string) $auditCreatedAt,
            ];
        }
    } catch (mysqli_sql_exception | RuntimeException $exception) {
        error_log('StudentHub Practical 14 dashboard query failed: ' . $exception->getMessage());
        $dashboardError = 'Dashboard data or audit history is unavailable. Confirm that the Practical 14 SQL section is installed, then reload.';
    } finally {
        if ($statsQuery instanceof mysqli_stmt) {
            $statsQuery->close();
        }
        if ($registrationsQuery instanceof mysqli_stmt) {
            $registrationsQuery->close();
        }
        if ($auditQuery instanceof mysqli_stmt) {
            $auditQuery->close();
        }
        if ($connection instanceof mysqli) {
            $connection->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Role-aware StudentHub dashboard with live academic statistics and audit history.">
    <title>StudentHub - <?= $isAdmin ? 'Admin' : 'Student' ?> Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="bg-primary text-white py-3">
        <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between gap-3">
            <a class="text-white text-decoration-none fs-4 fw-bold" href="index.html">StudentHub</a>
            <nav class="d-flex flex-wrap gap-2" aria-label="Dashboard navigation">
                <a class="btn btn-outline-light" href="dashboard.php">Dashboard</a>
                <?php if ($isAdmin): ?>
                    <a class="btn btn-outline-light" href="admin_dashboard.php">Admin portal</a>
                    <a class="btn btn-outline-light" href="student_management.php">Manage students</a>
                    <a class="btn btn-outline-light" href="event_management.php">Manage events</a>
                <?php else: ?>
                    <a class="btn btn-outline-light" href="student_dashboard.php">Student portal</a>
                    <a class="btn btn-outline-light" href="events.html">Events</a>
                <?php endif; ?>
                <form action="logout.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $escape((string) $_SESSION['logout_csrf_token']) ?>">
                    <button type="submit" class="btn btn-light text-primary">Log out</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="container py-4">
        <section class="card shadow-sm mb-4">
            <div class="card-body">
                <h1 class="h2"><?= $isAdmin ? 'Admin Dashboard' : 'Student Dashboard' ?></h1>
                <p class="mb-1">Welcome, <?= $escape($user['full_name']) ?>.</p>
                <p class="text-muted mb-0">Signed in as <span class="badge text-bg-<?= $isAdmin ? 'danger' : 'primary' ?>"><?= $escape($user['role']) ?></span> · <?= $escape($user['email']) ?></p>
                <?php if ($sessionRegenerated): ?>
                    <p class="alert alert-success mt-3 mb-0" role="status">Login successful. Your session ID was regenerated.</p>
                <?php endif; ?>
                <p class="small text-muted mt-3 mb-0">Session expires after <?= (int) (sessionTimeoutSeconds() / 60) ?> minutes of inactivity.</p>
            </div>
        </section>

        <?php if ($isAdmin): ?>
            <?php if ($dashboardError !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= $escape($dashboardError) ?></div>
            <?php else: ?>
                <section aria-labelledby="dashboard-stats-title" class="mb-4">
                    <h2 id="dashboard-stats-title" class="h4 mb-3">Live portal statistics</h2>
                    <div class="row g-3">
                        <?php foreach (['users' => 'Registered users', 'students' => 'Students', 'events' => 'Events'] as $key => $label): ?>
                            <div class="col-12 col-md-4">
                                <article class="card shadow-sm h-100">
                                    <div class="card-body">
                                        <p class="text-muted mb-1"><?= $escape($label) ?></p>
                                        <p class="display-6 fw-bold mb-0"><?= $stats[$key] ?></p>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="card shadow-sm mb-4" aria-labelledby="latest-registrations-title">
                    <div class="card-body">
                        <h2 id="latest-registrations-title" class="h4">Latest event registrations</h2>
                        <?php if ($latestRegistrations === []): ?>
                            <p class="text-muted mb-0">No event registrations have been recorded.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <caption class="caption-top">Most recent registrations, newest first</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Student</th>
                                            <th scope="col">Roll number</th>
                                            <th scope="col">Event</th>
                                            <th scope="col">Registered at</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($latestRegistrations as $registration): ?>
                                            <tr>
                                                <td><?= $escape($registration['student']) ?></td>
                                                <td><?= $escape($registration['roll_number']) ?></td>
                                                <td><?= $escape($registration['event']) ?></td>
                                                <td><?= $escape($registration['registered_at']) ?></td>
                                                <td><?= $escape($registration['status']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="card shadow-sm" aria-labelledby="audit-logs-title">
                    <div class="card-body">
                        <h2 id="audit-logs-title" class="h4">Recent admin audit logs</h2>
                        <?php if ($auditLogs === []): ?>
                            <p class="text-muted mb-0">No admin actions have been recorded yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <caption class="caption-top">Latest recorded administrator actions</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Admin user ID</th>
                                            <th scope="col">Action</th>
                                            <th scope="col">Details</th>
                                            <th scope="col">IP address</th>
                                            <th scope="col">Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($auditLogs as $log): ?>
                                            <tr>
                                                <td><?= $log['admin_user_id'] ?></td>
                                                <td><?= $escape($log['action']) ?></td>
                                                <td><?= $escape($log['details']) ?></td>
                                                <td><?= $escape($log['ip_address']) ?></td>
                                                <td><?= $escape($log['created_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <section class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h4">Student portal</h2>
                    <p class="mb-3">Use the student portal for your account and event information. Administrative statistics and management tools are restricted to Admin users.</p>
                    <a class="btn btn-primary" href="student_dashboard.php">Open student dashboard</a>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
