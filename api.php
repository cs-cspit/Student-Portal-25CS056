<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';

startSecureSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

if (!isset($_SESSION['practical13_csrf_token'])) {
    $_SESSION['practical13_csrf_token'] = bin2hex(random_bytes(32));
}

function sendApiResponse(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function readApiPayload(): array
{
    $rawPayload = file_get_contents('php://input');
    if ($rawPayload === false || trim($rawPayload) === '') {
        sendApiResponse(400, ['success' => false, 'message' => 'A JSON request body is required.']);
    }

    try {
        $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        sendApiResponse(400, ['success' => false, 'message' => 'The request body must contain valid JSON.']);
    }

    if (!is_array($payload) || array_is_list($payload)) {
        sendApiResponse(400, ['success' => false, 'message' => 'The JSON request body must be an object.']);
    }

    return $payload;
}

function apiTextLength(string $value): int
{
    $length = preg_match_all('/./us', $value);
    return $length === false ? 0 : $length;
}

function validateStudentPayload(array $payload): array
{
    $student = [];
    $errors = [];

    foreach (['full_name' => 'Full name', 'email' => 'Email', 'course' => 'Course'] as $field => $label) {
        $value = $payload[$field] ?? null;
        if (!is_string($value)) {
            $errors[$field] = "{$label} is required.";
            continue;
        }
        $student[$field] = trim($value);
        if ($student[$field] === '') {
            $errors[$field] = "{$label} is required.";
        }
    }

    if (!isset($errors['full_name'])
        && (apiTextLength($student['full_name']) > 100 || apiTextLength($student['full_name']) < 2)) {
        $errors['full_name'] = 'Full name must be between 2 and 100 characters.';
    }
    if (!isset($errors['email'])
        && (strlen($student['email']) > 254 || filter_var($student['email'], FILTER_VALIDATE_EMAIL) === false)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!isset($errors['course'])
        && (apiTextLength($student['course']) > 80 || apiTextLength($student['course']) < 2)) {
        $errors['course'] = 'Course must be between 2 and 80 characters.';
    }

    if ($errors !== []) {
        sendApiResponse(422, [
            'success' => false,
            'message' => 'Please correct the highlighted student details.',
            'errors' => $errors,
        ]);
    }

    return $student;
}

function requireValidCsrfToken(): void
{
    $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expectedToken = $_SESSION['practical13_csrf_token'] ?? '';
    if (!is_string($submittedToken) || !is_string($expectedToken)
        || !hash_equals($expectedToken, $submittedToken)) {
        sendApiResponse(403, ['success' => false, 'message' => 'Your form session expired. Reload the page and try again.']);
    }
}

function parseStudentId(): int
{
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        sendApiResponse(400, ['success' => false, 'message' => 'A valid student ID is required.']);
    }
    return $id;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$connection = null;
$statement = null;

if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');
    sendApiResponse(405, ['success' => false, 'message' => 'This HTTP method is not supported.']);
}

try {
    $connection = createMysqliConnection();

    if ($method === 'GET') {
        $statement = $connection->prepare(
            'SELECT id, full_name, email, course, created_at, updated_at
             FROM api_students
             ORDER BY full_name ASC, id ASC'
        );
        $statement->execute();
        $statement->bind_result($id, $fullName, $email, $course, $createdAt, $updatedAt);
        $students = [];
        while ($statement->fetch()) {
            $students[] = [
                'id' => (int) $id,
                'full_name' => (string) $fullName,
                'email' => (string) $email,
                'course' => (string) $course,
                'created_at' => (string) $createdAt,
                'updated_at' => (string) $updatedAt,
            ];
        }
        sendApiResponse(200, [
            'success' => true,
            'data' => $students,
            'csrf_token' => $_SESSION['practical13_csrf_token'],
        ]);
    }

    requireValidCsrfToken();

    if ($method === 'POST') {
        $student = validateStudentPayload(readApiPayload());
        $statement = $connection->prepare(
            'INSERT INTO api_students (full_name, email, course) VALUES (?, ?, ?)'
        );
        $statement->bind_param('sss', $student['full_name'], $student['email'], $student['course']);
        $statement->execute();
        $newId = $connection->insert_id;
        sendApiResponse(200, [
            'success' => true,
            'message' => 'Student added successfully.',
            'data' => ['id' => $newId] + $student,
        ]);
    }

    $id = parseStudentId();

    if ($method === 'PUT') {
        $student = validateStudentPayload(readApiPayload());
        $statement = $connection->prepare(
            'UPDATE api_students SET full_name = ?, email = ?, course = ? WHERE id = ?'
        );
        $statement->bind_param('sssi', $student['full_name'], $student['email'], $student['course'], $id);
        $statement->execute();
        if ($statement->affected_rows === 0) {
            $check = $connection->prepare('SELECT id FROM api_students WHERE id = ?');
            $check->bind_param('i', $id);
            $check->execute();
            $check->store_result();
            $exists = $check->num_rows === 1;
            $check->close();
            if (!$exists) {
                sendApiResponse(404, ['success' => false, 'message' => 'Student record not found.']);
            }
        }
        sendApiResponse(200, [
            'success' => true,
            'message' => 'Student updated successfully.',
            'data' => ['id' => $id] + $student,
        ]);
    }

    $statement = $connection->prepare('DELETE FROM api_students WHERE id = ?');
    $statement->bind_param('i', $id);
    $statement->execute();
    if ($statement->affected_rows !== 1) {
        sendApiResponse(404, ['success' => false, 'message' => 'Student record not found.']);
    }
    sendApiResponse(200, ['success' => true, 'message' => 'Student deleted successfully.']);
} catch (mysqli_sql_exception $exception) {
    error_log('Practical 13 student API database error: ' . $exception->getMessage());
    if ($exception->getCode() === 1062) {
        sendApiResponse(409, ['success' => false, 'message' => 'A student with that email address already exists.']);
    }
    sendApiResponse(500, ['success' => false, 'message' => 'The student service is temporarily unavailable.']);
} catch (RuntimeException $exception) {
    error_log('Practical 13 student API connection error: ' . $exception->getMessage());
    sendApiResponse(500, ['success' => false, 'message' => 'The student service is temporarily unavailable.']);
} finally {
    if ($statement instanceof mysqli_stmt) {
        $statement->close();
    }
    if ($connection instanceof mysqli) {
        $connection->close();
    }
}
