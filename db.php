<?php
declare(strict_types=1);

function createMysqliConnection(): mysqli
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME') ?: 'studenthub_db';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    if (filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
        throw new RuntimeException('DB_PORT must be a valid TCP port number.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $connection = new mysqli($host, $username, $password, $database, (int) $port);
        $connection->set_charset('utf8mb4');
        return $connection;
    } catch (mysqli_sql_exception $exception) {
        error_log('StudentHub MySQLi connection failed: ' . $exception->getMessage());
        throw new RuntimeException('Unable to connect to the database.', 0, $exception);
    }
}

function createDatabaseConnection(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_NAME') ?: 'studenthub_db';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    if (filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
        throw new RuntimeException('DB_PORT must be a valid TCP port number.');
    }
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    try {
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ]);
    } catch (PDOException $exception) {
        error_log('StudentHub database connection failed: ' . $exception->getMessage());
        throw new RuntimeException('Unable to connect to the database.', 0, $exception);
    }
}
