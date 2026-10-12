<?php
declare(strict_types=1);

session_start();

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cleanInput(mixed $value): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = str_replace("\0", '', $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return trim(strip_tags($value));
}

function textLength(string $value): int
{
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? 0 : $count;
}

function csvSafeValue(string $value): string
{
    if (preg_match('/^[\s]*[=+\-@]/u', $value) === 1) {
        return "'" . $value;
    }

    return $value;
}

$departments = [
    'cse' => 'Computer Science & Engineering (CSE)',
    'academic' => 'Academic Cell / Timetable Desk',
    'exam' => 'Examination Branch',
    'it' => 'IT Support Desk',
];

if (!isset($_SESSION['contact_csrf_token'])) {
    $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['contact_csrf_token'];
$values = ['name' => '', 'email' => '', 'dept' => 'cse', 'subject' => '', 'message' => ''];
$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $unused) {
        $values[$key] = cleanInput($_POST[$key] ?? '');
    }

    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $errors['form'] = 'Your form session expired. Refresh the page and submit again.';
    }

    if (textLength($values['name']) < 2 || textLength($values['name']) > 80
        || preg_match("/^[\p{L}\p{M}][\p{L}\p{M} .'-]*$/u", $values['name']) !== 1) {
        $errors['name'] = 'Enter a valid name using letters, spaces, apostrophes, periods, or hyphens (2–80 characters).';
    }

    if (strlen($values['email']) > 254 || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (!array_key_exists($values['dept'], $departments)) {
        $errors['dept'] = 'Select a department from the list.';
    }

    if (textLength($values['subject']) < 3 || textLength($values['subject']) > 120) {
        $errors['subject'] = 'Subject must be between 3 and 120 characters.';
    }

    if (textLength($values['message']) < 10 || textLength($values['message']) > 3000) {
        $errors['message'] = 'Message must be between 10 and 3000 characters.';
    }

    if ($errors === []) {
        $storageDirectory = __DIR__ . '/storage';
        $csvPath = $storageDirectory . '/contact_submissions.csv';
        $file = @fopen($csvPath, 'c+');
        $saved = false;

        if ($file !== false) {
            if (flock($file, LOCK_EX)) {
                $fileInfo = fstat($file);
                $readyToWrite = $fileInfo !== false;
                if ($readyToWrite && $fileInfo['size'] === 0) {
                    $readyToWrite = fputcsv($file, ['submitted_at', 'name', 'email', 'department', 'subject', 'message'], ',', '"', '') !== false;
                }

                if ($readyToWrite && fseek($file, 0, SEEK_END) === 0) {
                    $saved = fputcsv($file, [
                        date(DATE_ATOM),
                        csvSafeValue($values['name']),
                        csvSafeValue($values['email']),
                        csvSafeValue($departments[$values['dept']]),
                        csvSafeValue($values['subject']),
                        csvSafeValue($values['message']),
                    ], ',', '"', '') !== false && fflush($file);
                }

                flock($file, LOCK_UN);
            }

            fclose($file);
        }

        if ($saved) {
            $successMessage = 'Thank you. Your inquiry was submitted successfully.';
            $values = ['name' => '', 'email' => '', 'dept' => 'cse', 'subject' => '', 'message' => ''];
            $_SESSION['contact_csrf_token'] = bin2hex(random_bytes(32));
            $csrfToken = $_SESSION['contact_csrf_token'];
        } else {
            error_log('Contact form submission could not be written to storage/contact_submissions.csv.');
            $errors['form'] = 'We could not save your inquiry right now. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contact StudentHub Support - Send inquiries to CHARUSAT CSPIT support desk.">
    <title>StudentHub - Contact Us</title>
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
                <li><a href="dashboard.html">Dashboard</a></li>
                <li><a href="events.html">Events</a></li>
                <li><a href="faq.html">FAQ</a></li>
                <li><a href="contact.php" class="active" aria-current="page">Contact</a></li>
                <li><a href="feedback.html">Feedback</a></li>
                <li><a href="login.php" class="nav-btn">Login</a></li>
            </ul>
        </nav>
    </header>

    <nav class="breadcrumbs" aria-label="Breadcrumb navigation">
        <ol>
            <li><a href="index.html">Home</a></li>
            <li><span aria-current="page">Contact Us</span></li>
        </ol>
    </nav>

    <div class="container">
        <div class="layout-with-sidebar">
            <main id="main-content">
                <section class="card">
                    <h1>Contact CHARUSAT CSPIT Support Desk</h1>
                    <p>If you have any academic inquiries, timetable queries, or portal access issues, please fill out the form below.</p>

                    <?php if ($successMessage !== ''): ?>
                        <div class="form-message form-success-summary" role="status"><?= escapeHtml($successMessage) ?></div>
                    <?php endif; ?>

                    <?php if ($errors !== []): ?>
                        <div class="form-message form-error-summary" role="alert">
                            <strong>Please correct the following:</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= escapeHtml($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="contact.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">

                        <div class="form-group">
                            <label for="contact-name">Your Name <span style="color:red">*</span></label>
                            <input type="text" id="contact-name" name="name" required minlength="2" maxlength="80" autocomplete="name" value="<?= escapeHtml($values['name']) ?>" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>" <?= isset($errors['name']) ? 'aria-describedby="error-name"' : '' ?>>
                            <?php if (isset($errors['name'])): ?><div class="input-error" id="error-name"><?= escapeHtml($errors['name']) ?></div><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="contact-email">Email Address <span style="color:red">*</span></label>
                            <input type="email" id="contact-email" name="email" required maxlength="254" autocomplete="email" value="<?= escapeHtml($values['email']) ?>" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>" <?= isset($errors['email']) ? 'aria-describedby="error-email"' : '' ?>>
                            <?php if (isset($errors['email'])): ?><div class="input-error" id="error-email"><?= escapeHtml($errors['email']) ?></div><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="contact-dept">Relevant Department <span style="color:red">*</span></label>
                            <select id="contact-dept" name="dept" required aria-invalid="<?= isset($errors['dept']) ? 'true' : 'false' ?>" <?= isset($errors['dept']) ? 'aria-describedby="error-dept"' : '' ?>>
                                <?php foreach ($departments as $key => $label): ?>
                                    <option value="<?= escapeHtml($key) ?>" <?= $values['dept'] === $key ? 'selected' : '' ?>><?= escapeHtml($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['dept'])): ?><div class="input-error" id="error-dept"><?= escapeHtml($errors['dept']) ?></div><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="contact-subject">Inquiry Subject <span style="color:red">*</span></label>
                            <input type="text" id="contact-subject" name="subject" required minlength="3" maxlength="120" value="<?= escapeHtml($values['subject']) ?>" aria-invalid="<?= isset($errors['subject']) ? 'true' : 'false' ?>" <?= isset($errors['subject']) ? 'aria-describedby="error-subject"' : '' ?>>
                            <?php if (isset($errors['subject'])): ?><div class="input-error" id="error-subject"><?= escapeHtml($errors['subject']) ?></div><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="contact-message">Detailed Message <span style="color:red">*</span></label>
                            <textarea id="contact-message" name="message" rows="5" required minlength="10" maxlength="3000" aria-invalid="<?= isset($errors['message']) ? 'true' : 'false' ?>" <?= isset($errors['message']) ? 'aria-describedby="error-message"' : '' ?>><?= escapeHtml($values['message']) ?></textarea>
                            <?php if (isset($errors['message'])): ?><div class="input-error" id="error-message"><?= escapeHtml($errors['message']) ?></div><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn">Submit Inquiry</button>
                            <button type="reset" class="btn btn-secondary">Clear Form</button>
                        </div>
                    </form>
                </section>
            </main>

            <aside aria-label="Campus Info Sidebar">
                <div class="card">
                    <h2>Campus Address</h2>
                    <address style="font-style: normal; line-height: 1.8;">
                        <strong>Department of Computer Science & Engineering</strong><br>
                        Chandubhai S. Patel Institute of Technology (CSPIT)<br>
                        Charotar University of Science and Technology (CHARUSAT)<br>
                        132, Highway, Off Nadiad Petlad Road, Changa, Gujarat 388421
                    </address>
                </div>

                <div class="card">
                    <h2>Working Hours</h2>
                    <ul style="list-style: none; line-height: 1.8;">
                        <li><strong>Monday - Friday:</strong> 9:00 AM - 4:30 PM</li>
                        <li><strong>Saturday:</strong> 9:00 AM - 1:00 PM</li>
                        <li><strong>Sunday & Holidays:</strong> Closed</li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 StudentHub - CHARUSAT CSPIT CSE. Academic Practical Submission | <a href="contact.php">Contact Us</a> | <a href="faq.html">FAQ</a></p>
    </footer>

    <script src="main.js"></script>
</body>
</html>
