<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';

requireAuthenticatedUser('Admin');
startSecureSession();

const MAX_POSTER_SIZE = 2 * 1024 * 1024;

function escapeEventValue(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function eventTextLength(string $value): int
{
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? 0 : $count;
}

function redirectToEventManagement(): never
{
    header('Location: event_management.php', true, 303);
    exit;
}

function eventPosterUrl(?string $filename): ?string
{
    if ($filename === null || preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/', $filename) !== 1) {
        return null;
    }

    return 'uploads/event-posters/' . rawurlencode($filename);
}

function getEventPosterUpload(array $upload): ?array
{
    $error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error)) {
        throw new RuntimeException('The poster upload did not provide a valid upload status.');
    }
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'The poster exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'The poster exceeds the 2 MB limit.',
            UPLOAD_ERR_PARTIAL => 'The poster upload was incomplete. Try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing its temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded poster.',
            UPLOAD_ERR_EXTENSION => 'A server extension stopped the poster upload.',
        ];
        throw new InvalidArgumentException($messages[$error] ?? 'The poster upload failed.');
    }

    $temporaryPath = $upload['tmp_name'] ?? '';
    $size = $upload['size'] ?? 0;
    if (!is_string($temporaryPath) || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('The uploaded poster file could not be verified.');
    }
    if (!is_int($size) || $size < 1 || $size > MAX_POSTER_SIZE) {
        throw new InvalidArgumentException('Poster must be a non-empty image no larger than 2 MB.');
    }

    if (!class_exists(finfo::class)) {
        throw new RuntimeException('PHP Fileinfo is required to validate uploaded poster types.');
    }
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($temporaryPath);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
        throw new InvalidArgumentException('Invalid poster type. Upload a JPEG, PNG, or WebP image.');
    }

    $imageInfo = getimagesize($temporaryPath);
    if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== $mimeType) {
        throw new InvalidArgumentException('The uploaded file is not a valid image.');
    }

    return [
        'temporary_path' => $temporaryPath,
        'extension' => $allowedTypes[$mimeType],
    ];
}

function storeEventPoster(array $upload): string
{
    $uploadDirectory = __DIR__ . '/uploads/event-posters';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('The poster upload folder could not be created.');
    }
    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException('The poster upload folder is not writable by PHP.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $upload['extension'];
    if (!move_uploaded_file($upload['temporary_path'], $uploadDirectory . '/' . $filename)) {
        throw new RuntimeException('The validated poster could not be saved.');
    }

    return $filename;
}

function removeEventPoster(?string $filename): void
{
    if ($filename === null || preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/', $filename) !== 1) {
        return;
    }

    $path = __DIR__ . '/uploads/event-posters/' . $filename;
    if (is_file($path) && !unlink($path)) {
        error_log('StudentHub could not remove old event poster: ' . $filename);
    }
}

$formValues = [
    'event_id' => '',
    'title' => '',
    'category' => '',
    'event_date' => '',
    'venue' => '',
    'description' => '',
];
$errors = [];
$pageError = '';
$databaseError = '';
$message = $_SESSION['event_management_message'] ?? '';
$messageType = $_SESSION['event_management_message_type'] ?? 'success';
unset($_SESSION['event_management_message'], $_SESSION['event_management_message_type']);
$events = [];
$editing = false;
$currentPoster = null;

if (!isset($_SESSION['event_management_csrf'])) {
    $_SESSION['event_management_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['event_management_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $pageError = 'Your session token is invalid or expired. Reload the page and try again.';
    } else {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        try {
            $connection = createMysqliConnection();

            if ($action === 'delete') {
                $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($eventId === false) {
                    $pageError = 'Select a valid event to delete.';
                } else {
                    $findPoster = $connection->prepare('SELECT poster_filename FROM managed_events WHERE event_id = ?');
                    $findPoster->bind_param('i', $eventId);
                    $findPoster->execute();
                    $findPoster->bind_result($posterToRemove);
                    $eventFound = $findPoster->fetch();
                    $findPoster->close();

                    if (!$eventFound) {
                        $pageError = 'The event was not found; it may already have been deleted.';
                    } else {
                        $delete = $connection->prepare('DELETE FROM managed_events WHERE event_id = ?');
                        $delete->bind_param('i', $eventId);
                        $delete->execute();
                        $deleted = $delete->affected_rows === 1;
                        $delete->close();
                        if ($deleted) {
                            removeEventPoster(is_string($posterToRemove) ? $posterToRemove : null);
                            $_SESSION['event_management_message'] = 'Event deleted successfully.';
                            $_SESSION['event_management_message_type'] = 'success';
                            $connection->close();
                            redirectToEventManagement();
                        }
                        $pageError = 'The event could not be deleted. Please try again.';
                    }
                }
            } elseif ($action === 'save') {
                foreach ($formValues as $field => $unused) {
                    $value = $_POST[$field] ?? '';
                    $formValues[$field] = is_string($value) ? trim($value) : '';
                }
                $editing = $formValues['event_id'] !== '';

                if ($editing && filter_var($formValues['event_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                    $errors['event_id'] = 'Invalid event record.';
                }
                if ($formValues['title'] === '' || eventTextLength($formValues['title']) > 150) {
                    $errors['title'] = 'Enter an event title of 1–150 characters.';
                }
                if ($formValues['category'] === '' || eventTextLength($formValues['category']) > 50) {
                    $errors['category'] = 'Enter a category of 1–50 characters.';
                }
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $formValues['event_date']);
                if (!$date || $date->format('Y-m-d') !== $formValues['event_date']) {
                    $errors['event_date'] = 'Select a valid event date.';
                }
                if ($formValues['venue'] === '' || eventTextLength($formValues['venue']) > 150) {
                    $errors['venue'] = 'Enter a venue of 1–150 characters.';
                }
                if ($formValues['description'] === '' || eventTextLength($formValues['description']) > 5000) {
                    $errors['description'] = 'Enter a description of 1–5000 characters.';
                }

                $validatedUpload = null;
                if ($errors === []) {
                    try {
                        $upload = $_FILES['poster'] ?? [];
                        if (!is_array($upload)) {
                            throw new InvalidArgumentException('The poster upload is invalid.');
                        }
                        $validatedUpload = getEventPosterUpload($upload);
                    } catch (InvalidArgumentException $exception) {
                        $errors['poster'] = $exception->getMessage();
                    } catch (RuntimeException $exception) {
                        $errors['poster'] = $exception->getMessage();
                    }
                }

                if (!$editing && $validatedUpload === null && !isset($errors['poster'])) {
                    $errors['poster'] = 'Upload a JPEG, PNG, or WebP poster to create an event.';
                }

                $oldPoster = null;
                if ($editing && $errors === []) {
                    $eventId = (int) $formValues['event_id'];
                    $current = $connection->prepare('SELECT poster_filename FROM managed_events WHERE event_id = ?');
                    $current->bind_param('i', $eventId);
                    $current->execute();
                    $current->bind_result($oldPosterValue);
                    $eventExists = $current->fetch();
                    $current->close();
                    if (!$eventExists) {
                        $errors['form'] = 'This event no longer exists. Refresh the list and try again.';
                    } else {
                        $oldPoster = is_string($oldPosterValue) ? $oldPosterValue : null;
                    }
                }

                if ($errors === []) {
                    $newPoster = null;
                    try {
                        if ($validatedUpload !== null) {
                            $newPoster = storeEventPoster($validatedUpload);
                        }

                        if ($editing) {
                            $eventId = (int) $formValues['event_id'];
                            if ($newPoster !== null) {
                                $statement = $connection->prepare(
                                    'UPDATE managed_events
                                     SET title = ?, category = ?, event_date = ?, venue = ?, description = ?, poster_filename = ?
                                     WHERE event_id = ?'
                                );
                                $statement->bind_param(
                                    'ssssssi',
                                    $formValues['title'],
                                    $formValues['category'],
                                    $formValues['event_date'],
                                    $formValues['venue'],
                                    $formValues['description'],
                                    $newPoster,
                                    $eventId
                                );
                            } else {
                                $statement = $connection->prepare(
                                    'UPDATE managed_events
                                     SET title = ?, category = ?, event_date = ?, venue = ?, description = ?
                                     WHERE event_id = ?'
                                );
                                $statement->bind_param(
                                    'sssssi',
                                    $formValues['title'],
                                    $formValues['category'],
                                    $formValues['event_date'],
                                    $formValues['venue'],
                                    $formValues['description'],
                                    $eventId
                                );
                            }
                            $statement->execute();
                            $statement->close();
                            if ($newPoster !== null) {
                                removeEventPoster($oldPoster);
                            }
                            $_SESSION['event_management_message'] = 'Event updated successfully.';
                        } else {
                            $statement = $connection->prepare(
                                'INSERT INTO managed_events (title, category, event_date, venue, description, poster_filename)
                                 VALUES (?, ?, ?, ?, ?, ?)'
                            );
                            $statement->bind_param(
                                'ssssss',
                                $formValues['title'],
                                $formValues['category'],
                                $formValues['event_date'],
                                $formValues['venue'],
                                $formValues['description'],
                                $newPoster
                            );
                            $statement->execute();
                            $statement->close();
                            $_SESSION['event_management_message'] = 'Event created successfully.';
                        }

                        $_SESSION['event_management_message_type'] = 'success';
                        $connection->close();
                        redirectToEventManagement();
                    } catch (Throwable $exception) {
                        if ($newPoster !== null) {
                            removeEventPoster($newPoster);
                        }
                        throw $exception;
                    }
                }
            } else {
                $pageError = 'Unknown event-management action.';
            }

            $connection->close();
        } catch (mysqli_sql_exception $exception) {
            error_log('StudentHub event management database operation failed: ' . $exception->getMessage());
            $pageError = 'The database operation failed. Import event_management.sql and try again.';
        } catch (RuntimeException $exception) {
            error_log('StudentHub event management operation failed: ' . $exception->getMessage());
            $pageError = $exception->getMessage();
        }
    }
}

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $editId !== false && $editId !== null) {
    try {
        $connection = createMysqliConnection();
        $statement = $connection->prepare(
            'SELECT event_id, title, category, event_date, venue, description, poster_filename
             FROM managed_events WHERE event_id = ?'
        );
        $statement->bind_param('i', $editId);
        $statement->execute();
        $result = $statement->get_result();
        $eventToEdit = $result->fetch_assoc();
        $statement->close();
        $connection->close();

        if ($eventToEdit !== null) {
            $currentPoster = is_string($eventToEdit['poster_filename']) ? $eventToEdit['poster_filename'] : null;
            foreach ($formValues as $field => $unused) {
                $formValues[$field] = (string) $eventToEdit[$field];
            }
            $editing = true;
        } else {
            $message = 'The requested event was not found.';
            $messageType = 'error';
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('StudentHub event edit form query failed: ' . $exception->getMessage());
        $databaseError = 'Unable to load this event for editing.';
    } catch (RuntimeException $exception) {
        error_log('StudentHub event edit database configuration failed: ' . $exception->getMessage());
        $databaseError = 'The database is unavailable. Check the connection settings.';
    }
}

try {
    $connection = createMysqliConnection();
    $list = $connection->prepare(
        'SELECT event_id, title, category, event_date, venue, description, poster_filename
         FROM managed_events ORDER BY event_date, event_id'
    );
    $list->execute();
    $result = $list->get_result();
    $events = $result->fetch_all(MYSQLI_ASSOC);
    $list->close();
    $connection->close();
} catch (mysqli_sql_exception $exception) {
    error_log('StudentHub event list query failed: ' . $exception->getMessage());
    $databaseError = 'Unable to load events. Confirm that event_management.sql has been imported.';
} catch (RuntimeException $exception) {
    error_log('StudentHub event list database configuration failed: ' . $exception->getMessage());
    $databaseError = 'The database is unavailable. Check the connection settings.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub - Event Management</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .event-management { max-width: 1150px; margin: 2rem auto; }
        .event-management .event-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
        .event-management .poster { display: block; width: 100%; max-height: 220px; object-fit: cover; border-radius: 5px; margin-bottom: 1rem; }
        .event-management .event-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0 1rem; }
        .event-management .full-width { grid-column: 1 / -1; }
        .event-management .actions { display: flex; flex-wrap: wrap; gap: .5rem; }
        .event-management .actions form { margin: 0; }
    </style>
</head>
<body>
    <main class="container event-management">
        <section class="card">
            <h1>Event Management</h1>
            <p>Admin-only event creation, editing, deletion, and poster upload. Accepted images: JPEG, PNG, or WebP up to 2 MB.</p>
            <p><a class="btn btn-secondary" href="admin_dashboard.php">Back to Admin Dashboard</a></p>

            <?php if ($message !== ''): ?>
                <p class="form-message <?= $messageType === 'success' ? 'form-success-summary' : 'form-error-summary' ?>" role="<?= $messageType === 'success' ? 'status' : 'alert' ?>"><?= escapeEventValue($message) ?></p>
            <?php endif; ?>
            <?php if ($pageError !== ''): ?><p class="form-message form-error-summary" role="alert"><?= escapeEventValue($pageError) ?></p><?php endif; ?>
            <?php if ($databaseError !== ''): ?><p class="form-message form-error-summary" role="alert"><?= escapeEventValue($databaseError) ?></p><?php endif; ?>
            <?php if (isset($errors['form'])): ?><p class="form-message form-error-summary" role="alert"><?= escapeEventValue($errors['form']) ?></p><?php endif; ?>

            <h2><?= $editing ? 'Update Event' : 'Create Event' ?></h2>
            <form action="event_management.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= escapeEventValue($csrfToken) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="event_id" value="<?= escapeEventValue($formValues['event_id']) ?>">
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_POSTER_SIZE ?>">
                <div class="event-form">
                    <div class="form-group">
                        <label for="event-title">Event title</label>
                        <input type="text" id="event-title" name="title" required maxlength="150" value="<?= escapeEventValue($formValues['title']) ?>">
                        <?php if (isset($errors['title'])): ?><div class="input-error"><?= escapeEventValue($errors['title']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="event-category">Category</label>
                        <input type="text" id="event-category" name="category" required maxlength="50" value="<?= escapeEventValue($formValues['category']) ?>">
                        <?php if (isset($errors['category'])): ?><div class="input-error"><?= escapeEventValue($errors['category']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="event-date">Date</label>
                        <input type="date" id="event-date" name="event_date" required value="<?= escapeEventValue($formValues['event_date']) ?>">
                        <?php if (isset($errors['event_date'])): ?><div class="input-error"><?= escapeEventValue($errors['event_date']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="event-venue">Venue</label>
                        <input type="text" id="event-venue" name="venue" required maxlength="150" value="<?= escapeEventValue($formValues['venue']) ?>">
                        <?php if (isset($errors['venue'])): ?><div class="input-error"><?= escapeEventValue($errors['venue']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group full-width">
                        <label for="event-description">Description</label>
                        <textarea id="event-description" name="description" rows="4" required maxlength="5000"><?= escapeEventValue($formValues['description']) ?></textarea>
                        <?php if (isset($errors['description'])): ?><div class="input-error"><?= escapeEventValue($errors['description']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group full-width">
                        <label for="event-poster">Poster <?= $editing ? '(optional; choose a new file to replace)' : '(required)' ?></label>
                        <input type="file" id="event-poster" name="poster" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?= $editing ? '' : 'required' ?>>
                        <small>JPEG, PNG, WebP. Maximum file size: 2 MB.</small>
                        <?php if (isset($errors['poster'])): ?><div class="input-error"><?= escapeEventValue($errors['poster']) ?></div><?php endif; ?>
                        <?php if ($editing && eventPosterUrl($currentPoster) !== null): ?>
                            <p>Current poster:</p>
                            <img class="poster" src="<?= escapeEventValue((string) eventPosterUrl($currentPoster)) ?>" alt="Current event poster">
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btn"><?= $editing ? 'Update Event' : 'Create Event' ?></button>
                <?php if ($editing): ?><a class="btn btn-secondary" href="event_management.php">Cancel edit</a><?php endif; ?>
            </form>
        </section>

        <section class="card">
            <h2>Events and Posters</h2>
            <?php if ($events === []): ?>
                <p>No events are available yet.</p>
            <?php else: ?>
                <div class="event-grid">
                    <?php foreach ($events as $event): ?>
                        <article class="card">
                            <?php if (eventPosterUrl(is_string($event['poster_filename']) ? $event['poster_filename'] : null) !== null): ?>
                                <img class="poster" src="<?= escapeEventValue((string) eventPosterUrl($event['poster_filename'])) ?>" alt="<?= escapeEventValue($event['title']) ?> poster">
                            <?php else: ?>
                                <p>No poster uploaded.</p>
                            <?php endif; ?>
                            <h3><?= escapeEventValue($event['title']) ?></h3>
                            <p><strong>Category:</strong> <?= escapeEventValue($event['category']) ?></p>
                            <p><strong>Date:</strong> <?= escapeEventValue($event['event_date']) ?></p>
                            <p><strong>Venue:</strong> <?= escapeEventValue($event['venue']) ?></p>
                            <p><?= nl2br(escapeEventValue($event['description'])) ?></p>
                            <div class="actions">
                                <a class="btn btn-secondary" href="event_management.php?edit=<?= (int) $event['event_id'] ?>">Edit</a>
                                <form action="event_management.php" method="POST" onsubmit="return confirm('Delete this event and its uploaded poster? This cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= escapeEventValue($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
                                    <button type="submit" class="btn">Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
