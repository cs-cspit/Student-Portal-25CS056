<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';

function escapeRegistrationOutput(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function registrationTextLength(string $value): int
{
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? 0 : $count;
}

if (!isset($_SESSION['registration_csrf_token'])) {
    $_SESSION['registration_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['registration_csrf_token'];
$values = ['full_name' => '', 'student_id' => '', 'email' => ''];
$errors = [];
$formError = '';
$successMessage = '';
$transactionStarted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $field => $unused) {
        $postedValue = $_POST[$field] ?? '';
        $values[$field] = is_string($postedValue) ? trim($postedValue) : '';
    }

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $formError = 'Your form session expired. Refresh the page and try again.';
    }

    if ($values['full_name'] === '') {
        $errors['full_name'] = 'Full name is required.';
    } elseif (registrationTextLength($values['full_name']) < 2
        || registrationTextLength($values['full_name']) > 100
        || preg_match("/^[\p{L}\p{M}][\p{L}\p{M} .'-]*$/u", $values['full_name']) !== 1) {
        $errors['full_name'] = 'Enter a valid name (up to 100 characters).';
    }

    if ($values['student_id'] === '') {
        $errors['student_id'] = 'Student ID is required.';
    } elseif (strlen($values['student_id']) > 30
        || preg_match('/^[A-Za-z0-9][A-Za-z0-9_.\/-]*$/', $values['student_id']) !== 1) {
        $errors['student_id'] = 'Enter a valid Student ID using letters, numbers, dots, underscores, slashes, or hyphens (up to 30 characters).';
    }

    if ($values['email'] === '') {
        $errors['email'] = 'Email is required.';
    } elseif (strlen($values['email']) > 254 || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (!is_string($password) || $password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8 || strlen($password) > 72
        || preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9\s])\S{8,72}$/', $password) !== 1) {
        $errors['password'] = 'Use 8–72 characters with uppercase and lowercase letters, a number, and a special character.';
    }

    if (!is_string($confirmPassword) || $confirmPassword === '') {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif (is_string($password) && !hash_equals($password, $confirmPassword)) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if ($formError === '' && $errors === []) {
        try {
            // Use the MySQLi helper here (not the PDO helper) and bind all user values.
            $connection = createMysqliConnection();
            $duplicateQuery = $connection->prepare(
                'SELECT student_id, username, email
                 FROM user_accounts
                 WHERE student_id = ? OR username = ? OR email = ?'
            );
            $duplicateQuery->bind_param('sss', $values['student_id'], $values['student_id'], $values['email']);
            $duplicateQuery->execute();
            $duplicateQuery->bind_result($existingStudentId, $existingUsername, $existingEmail);
            $studentIdExists = false;
            $emailExists = false;
            while ($duplicateQuery->fetch()) {
                if ((is_string($existingStudentId) && strcasecmp($existingStudentId, $values['student_id']) === 0)
                    || strcasecmp($existingUsername, $values['student_id']) === 0) {
                    $studentIdExists = true;
                }
                if (strcasecmp($existingEmail, $values['email']) === 0) {
                    $emailExists = true;
                }
            }
            $duplicateQuery->close();

            if ($studentIdExists) {
                $errors['student_id'] = 'That Student ID is already registered.';
            }
            if ($emailExists) {
                $errors['email'] = 'That email address is already registered.';
            }

            if (!$studentIdExists && !$emailExists) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                if ($passwordHash === false) {
                    throw new RuntimeException('Password hashing failed.');
                }

                $connection->begin_transaction();
                $transactionStarted = true;
                // This prepared INSERT stores only the password hash, never the raw password.
                $insertQuery = $connection->prepare(
                    'INSERT INTO user_accounts (full_name, username, student_id, email, password_hash)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $insertQuery->bind_param(
                    'sssss',
                    $values['full_name'],
                    $values['student_id'],
                    $values['student_id'],
                    $values['email'],
                    $passwordHash
                );
                $insertQuery->execute();
                $insertQuery->close();

                $userId = $connection->insert_id;
                $role = 'Student';
                $roleQuery = $connection->prepare(
                    'INSERT INTO auth_user_roles (user_id, role) VALUES (?, ?)'
                );
                $roleQuery->bind_param('is', $userId, $role);
                $roleQuery->execute();
                $roleQuery->close();
                $connection->commit();
                $transactionStarted = false;

                $successMessage = 'Registration successful. Your account was saved securely.';
                $values = ['full_name' => '', 'student_id' => '', 'email' => ''];
                $_SESSION['registration_csrf_token'] = bin2hex(random_bytes(32));
                $csrfToken = $_SESSION['registration_csrf_token'];
            }
        } catch (mysqli_sql_exception $exception) {
            if (isset($connection) && $transactionStarted) {
                $connection->rollback();
                $transactionStarted = false;
            }
            if ($exception->getCode() === 1062) {
                $formError = 'That Student ID or email address is already registered. Please choose another.';
            } else {
                error_log('StudentHub account registration failed: ' . $exception->getMessage());
                $formError = 'We could not save your registration. Check the database setup and try again.';
            }
        } catch (RuntimeException $exception) {
            if (isset($connection) && $transactionStarted) {
                $connection->rollback();
                $transactionStarted = false;
            }
            error_log('StudentHub account registration database configuration failed: ' . $exception->getMessage());
            $formError = 'The database is not available. Check the database connection settings and try again.';
        } finally {
            if (isset($connection) && $connection instanceof mysqli) {
                $connection->close();
            }
        }
    }
}

$hasErrors = $formError !== '' || $errors !== [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create a StudentHub account with secure server-side validation.">
    <title>StudentHub - Secure Account Registration</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header>
        <div class="logo">Student<span>Hub</span> | CHARUSAT CSPIT</div>
        <div class="header-actions">
            <button class="theme-toggle-btn" aria-label="Toggle Theme">
                <span class="theme-icon">🌙</span><span class="theme-text">Dark</span>
            </button>
            <button class="hamburger-btn" aria-label="Toggle Navigation" aria-expanded="false">&#9776;</button>
        </div>
        <nav aria-label="Main Navigation">
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="about.html">About & Faculty</a></li>
                <li><a href="student_dashboard.php">Dashboard</a></li>
                <li><a href="events.html">Events</a></li>
                <li><a href="faq.html">FAQ</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="feedback.html">Feedback</a></li>
                <li><a href="login.php" class="nav-btn">Login</a></li>
            </ul>
        </nav>
    </header>

    <nav class="breadcrumbs" aria-label="Breadcrumb navigation">
        <ol>
            <li><a href="index.html">Home</a></li>
            <li><span aria-current="page">Secure Registration</span></li>
        </ol>
    </nav>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 max-w-4xl">
        <main id="main-content">
            <section class="card">
                <h1>Create a StudentHub Account</h1>
                <p>Register with a unique Student ID and email address. Your password is hashed before it is stored.</p>

                <?php if ($successMessage !== ''): ?>
                    <div class="form-message form-success-summary" role="status"><?= escapeRegistrationOutput($successMessage) ?></div>
                <?php endif; ?>

                <?php if ($hasErrors): ?>
                    <div class="form-message form-error-summary" role="alert">
                        <strong>Please correct the following:</strong>
                        <ul>
                            <?php if ($formError !== ''): ?><li><?= escapeRegistrationOutput($formError) ?></li><?php endif; ?>
                            <?php foreach ($errors as $error): ?><li><?= escapeRegistrationOutput($error) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="register_account.php" method="POST" class="mt-6">
                    <input type="hidden" name="csrf_token" value="<?= escapeRegistrationOutput($csrfToken) ?>">

                    <div class="form-group">
                        <label for="full-name">Full name</label>
                        <input type="text" id="full-name" name="full_name" required minlength="2" maxlength="100" autocomplete="name" value="<?= escapeRegistrationOutput($values['full_name']) ?>" aria-invalid="<?= isset($errors['full_name']) ? 'true' : 'false' ?>" <?= isset($errors['full_name']) ? 'aria-describedby="error-full-name"' : '' ?>>
                        <?php if (isset($errors['full_name'])): ?><div class="input-error" id="error-full-name"><?= escapeRegistrationOutput($errors['full_name']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="student-id">Student ID</label>
                        <input type="text" id="student-id" name="student_id" required maxlength="30" pattern="[A-Za-z0-9][A-Za-z0-9_./-]{0,29}" autocomplete="off" value="<?= escapeRegistrationOutput($values['student_id']) ?>" aria-invalid="<?= isset($errors['student_id']) ? 'true' : 'false' ?>" <?= isset($errors['student_id']) ? 'aria-describedby="error-student-id"' : '' ?>>
                        <?php if (isset($errors['student_id'])): ?><div class="input-error" id="error-student-id"><?= escapeRegistrationOutput($errors['student_id']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input type="email" id="email" name="email" required maxlength="254" autocomplete="email" value="<?= escapeRegistrationOutput($values['email']) ?>" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>" <?= isset($errors['email']) ? 'aria-describedby="error-email"' : '' ?>>
                        <?php if (isset($errors['email'])): ?><div class="input-error" id="error-email"><?= escapeRegistrationOutput($errors['email']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required minlength="8" maxlength="72" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s])\S{8,72}" autocomplete="new-password" aria-describedby="password-help" <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="password-help error-password"' : '' ?>>
                        <small id="password-help">Use 8–72 characters with uppercase, lowercase, a number, and a special character.</small>
                        <?php if (isset($errors['password'])): ?><div class="input-error" id="error-password"><?= escapeRegistrationOutput($errors['password']) ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="confirm-password">Confirm password</label>
                        <input type="password" id="confirm-password" name="confirm_password" required minlength="8" maxlength="72" autocomplete="new-password" aria-invalid="<?= isset($errors['confirm_password']) ? 'true' : 'false' ?>" <?= isset($errors['confirm_password']) ? 'aria-describedby="error-confirm-password"' : '' ?>>
                        <?php if (isset($errors['confirm_password'])): ?><div class="input-error" id="error-confirm-password"><?= escapeRegistrationOutput($errors['confirm_password']) ?></div><?php endif; ?>
                    </div>

                    <button type="submit" class="btn">Create Account</button>
                    <button type="reset" class="btn btn-secondary">Clear Form</button>
                </form>
            </section>
        </main>
    </div>

    <footer>
        <p>&copy; 2026 StudentHub - CHARUSAT CSPIT CSE. Practical 9 | <a href="contact.php">Contact Us</a> | <a href="faq.html">FAQ</a></p>
    </footer>

    <script src="main.js"></script>
</body>
</html>
