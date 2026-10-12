<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';

requireAuthenticatedUser('Admin');
startSecureSession();

function escapeStudentValue(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function studentTextLength(string $value): int
{
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? 0 : $count;
}

function redirectToStudentManagement(array $parameters = []): never
{
    $query = $parameters === [] ? '' : '?' . http_build_query($parameters);
    header('Location: student_management.php' . $query, true, 303);
    exit;
}

$courses = ['B.Tech CSE', 'B.Tech IT', 'B.Tech EE', 'B.Tech ME', 'B.Tech CE', 'M.Tech CSE', 'Other'];
$statuses = ['Active', 'Inactive'];
$formValues = ['student_id' => '', 'roll_number' => '', 'full_name' => '', 'email' => '', 'course' => ''];
$errors = [];
$message = $_SESSION['student_management_message'] ?? '';
$messageType = $_SESSION['student_management_message_type'] ?? 'success';
unset($_SESSION['student_management_message'], $_SESSION['student_management_message_type']);
$databaseError = '';
$students = [];
$editing = false;
$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$courseFilter = is_string($_GET['course'] ?? null) ? $_GET['course'] : '';
$statusFilter = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'Active';

if (!in_array($courseFilter, $courses, true)) {
    $courseFilter = '';
}
if (!in_array($statusFilter, $statuses, true)) {
    $statusFilter = 'Active';
}

if (!isset($_SESSION['student_management_csrf'])) {
    $_SESSION['student_management_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['student_management_csrf'];
$connection = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $errors['form'] = 'Your session token is invalid or expired. Reload the page and try again.';
    } else {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        try {
            $connection = createMysqliConnection();

            if ($action === 'delete') {
                $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($studentId === false) {
                    $errors['form'] = 'Select a valid student record to deactivate.';
                } else {
                    $statement = $connection->prepare("UPDATE managed_students SET status = 'Inactive' WHERE student_id = ? AND status = 'Active'");
                    $statement->bind_param('i', $studentId);
                    $statement->execute();
                    if ($statement->affected_rows === 1) {
                        $_SESSION['student_management_message'] = 'Student marked inactive successfully. The record has not been permanently deleted.';
                        $_SESSION['student_management_message_type'] = 'success';
                    } else {
                        $_SESSION['student_management_message'] = 'No active student was changed. The record may already be inactive or no longer exist.';
                        $_SESSION['student_management_message_type'] = 'error';
                    }
                    $statement->close();
                    $connection->close();
                    redirectToStudentManagement(['status' => 'Inactive']);
                }
            } elseif ($action === 'save') {
                foreach ($formValues as $field => $unused) {
                    $value = $_POST[$field] ?? '';
                    $formValues[$field] = is_string($value) ? trim($value) : '';
                }
                $editing = $formValues['student_id'] !== '';

                if ($editing && filter_var($formValues['student_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                    $errors['student_id'] = 'Invalid student record.';
                }
                if ($formValues['roll_number'] === '' || preg_match('/^[A-Za-z0-9-]{3,20}$/', $formValues['roll_number']) !== 1) {
                    $errors['roll_number'] = 'Enter a roll number of 3–20 letters, numbers, or hyphens.';
                }
                if (studentTextLength($formValues['full_name']) < 2 || studentTextLength($formValues['full_name']) > 100
                    || preg_match("/^[\p{L}\p{M}][\p{L}\p{M} .'-]*$/u", $formValues['full_name']) !== 1) {
                    $errors['full_name'] = 'Enter a valid student name (2–100 characters).';
                }
                if (strlen($formValues['email']) > 254 || filter_var($formValues['email'], FILTER_VALIDATE_EMAIL) === false) {
                    $errors['email'] = 'Enter a valid email address.';
                }
                if (!in_array($formValues['course'], $courses, true)) {
                    $errors['course'] = 'Select a course from the list.';
                }

                if ($errors === []) {
                    try {
                        if ($editing) {
                            $studentId = (int) $formValues['student_id'];
                            $exists = $connection->prepare('SELECT student_id FROM managed_students WHERE student_id = ?');
                            $exists->bind_param('i', $studentId);
                            $exists->execute();
                            $exists->store_result();
                            $recordExists = $exists->num_rows === 1;
                            $exists->close();

                            if (!$recordExists) {
                                $errors['form'] = 'This student record no longer exists. Refresh the list and try again.';
                            } else {
                                $statement = $connection->prepare(
                                    'UPDATE managed_students
                                     SET roll_number = ?, full_name = ?, email = ?, course = ?
                                     WHERE student_id = ?'
                                );
                                $statement->bind_param(
                                    'ssssi',
                                    $formValues['roll_number'],
                                    $formValues['full_name'],
                                    $formValues['email'],
                                    $formValues['course'],
                                    $studentId
                                );
                                $statement->execute();
                                $statement->close();
                                $_SESSION['student_management_message'] = 'Student details updated successfully.';
                            }
                        } else {
                            $statement = $connection->prepare(
                                'INSERT INTO managed_students (roll_number, full_name, email, course)
                                 VALUES (?, ?, ?, ?)'
                            );
                            $statement->bind_param(
                                'ssss',
                                $formValues['roll_number'],
                                $formValues['full_name'],
                                $formValues['email'],
                                $formValues['course']
                            );
                            $statement->execute();
                            $statement->close();
                            $_SESSION['student_management_message'] = 'Student added successfully.';
                        }

                        if ($errors === []) {
                            $_SESSION['student_management_message_type'] = 'success';
                            $connection->close();
                            redirectToStudentManagement();
                        }
                    } catch (mysqli_sql_exception $exception) {
                        if ((int) $exception->getCode() === 1062) {
                            $errors['form'] = 'That roll number or email address is already assigned to a student.';
                        } else {
                            throw $exception;
                        }
                    }
                }
            } else {
                $errors['form'] = 'Unknown student-management action.';
            }

            $connection->close();
            $connection = null;
        } catch (mysqli_sql_exception $exception) {
            error_log('StudentHub student management operation failed: ' . $exception->getMessage());
            $errors['form'] = 'The database operation failed. Check the student_management.sql setup and try again.';
        } catch (RuntimeException $exception) {
            error_log('StudentHub student management database configuration failed: ' . $exception->getMessage());
            $errors['form'] = 'The database is unavailable. Check the connection settings and try again.';
        }
    }
}

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $editId !== false && $editId !== null) {
    try {
        $connection = createMysqliConnection();
        $statement = $connection->prepare(
            'SELECT student_id, roll_number, full_name, email, course
             FROM managed_students
             WHERE student_id = ?'
        );
        $statement->bind_param('i', $editId);
        $statement->execute();
        $result = $statement->get_result();
        $studentToEdit = $result->fetch_assoc();
        $statement->close();
        $connection->close();
        $connection = null;

        if ($studentToEdit !== null) {
            $formValues = array_map(static fn($value): string => (string) $value, $studentToEdit);
            $editing = true;
        } else {
            $message = 'The requested student record was not found.';
            $messageType = 'error';
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('StudentHub edit form load failed: ' . $exception->getMessage());
        $databaseError = 'Unable to load the student for editing.';
    } catch (RuntimeException $exception) {
        error_log('StudentHub edit database configuration failed: ' . $exception->getMessage());
        $databaseError = 'The database is unavailable. Check the connection settings.';
    }
}

if (!($connection instanceof mysqli)) {
    try {
        $connection = createMysqliConnection();
    } catch (RuntimeException $exception) {
        error_log('StudentHub student list database configuration failed: ' . $exception->getMessage());
        $databaseError = 'Unable to connect to the database. Import student_management.sql and check the connection settings.';
    } catch (mysqli_sql_exception $exception) {
        error_log('StudentHub student list query connection failed: ' . $exception->getMessage());
        $databaseError = 'Unable to connect to the student database.';
    }
}

if ($databaseError === '' && isset($connection) && $connection instanceof mysqli) {
    try {
        if (($_GET['export'] ?? '') === 'csv') {
            $sql = "SELECT roll_number, full_name, email, course, status
                    FROM managed_students
                    WHERE (? = '' OR CONCAT_WS(' ', roll_number, full_name, email, course) LIKE ?)
                      AND (? = '' OR course = ?)
                      AND (? = '' OR status = ?)
                    ORDER BY full_name, roll_number";
            $export = $connection->prepare($sql);
            $like = '%' . $search . '%';
            $export->bind_param('ssssss', $search, $like, $courseFilter, $courseFilter, $statusFilter, $statusFilter);
            $export->execute();
            $export->bind_result($roll, $name, $email, $course, $status);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="student-records.csv"');
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('Unable to open CSV output stream.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Roll Number', 'Full Name', 'Email', 'Course', 'Status'], ',', '"', '');
            while ($export->fetch()) {
                $row = [$roll, $name, $email, $course, $status];
                foreach ($row as &$cell) {
                    if (preg_match('/^[\s]*[=+\-@]/u', $cell) === 1) {
                        $cell = "'" . $cell;
                    }
                }
                unset($cell);
                fputcsv($output, $row, ',', '"', '');
            }
            fclose($output);
            $export->close();
            $connection->close();
            exit;
        }

        $sql = "SELECT student_id, roll_number, full_name, email, course, status
                FROM managed_students
                WHERE (? = '' OR CONCAT_WS(' ', roll_number, full_name, email, course) LIKE ?)
                  AND (? = '' OR course = ?)
                  AND (? = '' OR status = ?)
                ORDER BY status = 'Inactive', full_name, roll_number";
        $listing = $connection->prepare($sql);
        $like = '%' . $search . '%';
        $listing->bind_param('ssssss', $search, $like, $courseFilter, $courseFilter, $statusFilter, $statusFilter);
        $listing->execute();
        $result = $listing->get_result();
        $students = $result->fetch_all(MYSQLI_ASSOC);
        $listing->close();
        $connection->close();
    } catch (mysqli_sql_exception $exception) {
        error_log('StudentHub student list query failed: ' . $exception->getMessage());
        $databaseError = 'Unable to load student records. Confirm that student_management.sql has been imported.';
    } catch (RuntimeException $exception) {
        error_log('StudentHub CSV export failed: ' . $exception->getMessage());
        $databaseError = 'Unable to export student records.';
    }
}

$queryParameters = array_filter(
    ['q' => $search, 'course' => $courseFilter, 'status' => $statusFilter],
    static fn(string $value): bool => $value !== ''
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Student Management</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .student-management { max-width: 1150px; margin: 2rem auto; }
        .student-management .table-scroll { overflow-x: auto; }
        .student-management .record-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .student-management .record-actions form { margin: 0; }
        .student-management .status-inactive { color: #991b1b; font-weight: 700; }
        .student-management .status-active { color: #166534; font-weight: 700; }
        .student-management .filter-row { display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; }
        .student-management .filter-row .form-group { flex: 1 1 200px; }
        .student-management .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0 1rem; }
        .student-management .button-row { display: flex; flex-wrap: wrap; gap: .6rem; }
    </style>
</head>
<body>
    <main class="container student-management">
        <section class="card">
            <h1>Student Management</h1>
            <p>Search, filter, add, update, deactivate, and export student records. Deactivation is a soft delete.</p>
            <p><a class="btn btn-secondary" href="admin_dashboard.php">Back to Admin Dashboard</a></p>

            <?php if ($message !== ''): ?>
                <p class="form-message <?= $messageType === 'success' ? 'form-success-summary' : 'form-error-summary' ?>" role="<?= $messageType === 'success' ? 'status' : 'alert' ?>"><?= escapeStudentValue($message) ?></p>
            <?php endif; ?>

            <?php if ($databaseError !== ''): ?>
                <p class="form-message form-error-summary" role="alert"><?= escapeStudentValue($databaseError) ?></p>
            <?php endif; ?>

            <?php if (isset($errors['form'])): ?>
                <p class="form-message form-error-summary" role="alert"><?= escapeStudentValue($errors['form']) ?></p>
            <?php endif; ?>

            <h2><?= $editing ? 'Update Student' : 'Add Student' ?></h2>
            <form action="student_management.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= escapeStudentValue($csrfToken) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="student_id" value="<?= escapeStudentValue($formValues['student_id']) ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="roll-number">Roll number</label>
                        <input type="text" id="roll-number" name="roll_number" required minlength="3" maxlength="20" value="<?= escapeStudentValue($formValues['roll_number']) ?>" aria-invalid="<?= isset($errors['roll_number']) ? 'true' : 'false' ?>">
                        <?php if (isset($errors['roll_number'])): ?><div class="input-error"><?= escapeStudentValue($errors['roll_number']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="full-name">Full name</label>
                        <input type="text" id="full-name" name="full_name" required minlength="2" maxlength="100" value="<?= escapeStudentValue($formValues['full_name']) ?>" aria-invalid="<?= isset($errors['full_name']) ? 'true' : 'false' ?>">
                        <?php if (isset($errors['full_name'])): ?><div class="input-error"><?= escapeStudentValue($errors['full_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="student-email">Email</label>
                        <input type="email" id="student-email" name="email" required maxlength="254" value="<?= escapeStudentValue($formValues['email']) ?>" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>">
                        <?php if (isset($errors['email'])): ?><div class="input-error"><?= escapeStudentValue($errors['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="student-course">Course</label>
                        <select id="student-course" name="course" required aria-invalid="<?= isset($errors['course']) ? 'true' : 'false' ?>">
                            <option value="">Select course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= escapeStudentValue($course) ?>" <?= $formValues['course'] === $course ? 'selected' : '' ?>><?= escapeStudentValue($course) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['course'])): ?><div class="input-error"><?= escapeStudentValue($errors['course']) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="button-row">
                    <button type="submit" class="btn"><?= $editing ? 'Update Student' : 'Add Student' ?></button>
                    <?php if ($editing): ?><a class="btn btn-secondary" href="student_management.php">Cancel edit</a><?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Student Records</h2>
            <form action="student_management.php" method="GET" class="filter-row">
                <div class="form-group">
                    <label for="student-search">Search name, email, course, or roll number</label>
                    <input type="search" id="student-search" name="q" value="<?= escapeStudentValue($search) ?>" placeholder="Type to search...">
                </div>
                <div class="form-group">
                    <label for="course-filter">Filter by course</label>
                    <select id="course-filter" name="course">
                        <option value="">All courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= escapeStudentValue($course) ?>" <?= $courseFilter === $course ? 'selected' : '' ?>><?= escapeStudentValue($course) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status-filter">Status</label>
                    <select id="status-filter" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= escapeStudentValue($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= escapeStudentValue($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="button-row">
                    <button type="submit" class="btn">Search / Filter</button>
                    <a class="btn btn-secondary" href="student_management.php">Clear</a>
                    <a class="btn btn-secondary" href="student_management.php?<?= escapeStudentValue(http_build_query(array_merge($queryParameters, ['export' => 'csv']))) ?>">Export CSV</a>
                </div>
            </form>

            <div class="table-scroll">
                <table>
                    <caption>Student records matching the selected search and filters</caption>
                    <thead>
                        <tr><th scope="col">Roll number</th><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Course</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($students === []): ?>
                            <tr><td colspan="6">No student records match these filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= escapeStudentValue($student['roll_number']) ?></td>
                                    <td><?= escapeStudentValue($student['full_name']) ?></td>
                                    <td><?= escapeStudentValue($student['email']) ?></td>
                                    <td><?= escapeStudentValue($student['course']) ?></td>
                                    <td class="<?= $student['status'] === 'Active' ? 'status-active' : 'status-inactive' ?>"><?= escapeStudentValue($student['status']) ?></td>
                                    <td>
                                        <div class="record-actions">
                                            <?php if ($student['status'] === 'Active'): ?>
                                                <a class="btn btn-secondary" href="student_management.php?edit=<?= (int) $student['student_id'] ?>">Edit</a>
                                                <form action="student_management.php" method="POST" onsubmit="return confirm('Mark this student as inactive? The record will be retained and not permanently deleted.');">
                                                    <input type="hidden" name="csrf_token" value="<?= escapeStudentValue($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="student_id" value="<?= (int) $student['student_id'] ?>">
                                                    <button type="submit" class="btn">Deactivate</button>
                                                </form>
                                            <?php else: ?>
                                                <span>Record retained (soft deleted)</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
