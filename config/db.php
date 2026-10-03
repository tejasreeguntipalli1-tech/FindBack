<?php
/**
 * Database Connection Configuration
 * Uses PDO for secure prepared statements and robust error handling.
 * Supports both local development (XAMPP fallback) and production deployment (via .env or system environment variables).
 */

require_once __DIR__ . '/env.php';

// Read from environment variables with fallback to local development defaults
$db_host    = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
$db_port    = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
$db_name    = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'fsd_lost_and_found');
$db_user    = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
$db_pass    = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($_ENV['DB_PASS'] ?? '');
$db_charset = 'utf8mb4';

$dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=$db_charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Graceful error logging rather than exposing raw server details
    error_log("Database Connection Failed: " . $e->getMessage());
    $isDebug = (getenv('APP_DEBUG') === 'true' || ($_ENV['APP_DEBUG'] ?? '') === 'true');
    if ($isDebug) {
        die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
    } else {
        die("A temporary database error occurred. Please verify MySQL service is running and credentials in config/db.php or .env are correct.");
    }
}

function get_db_connection() {
    global $pdo;
    return $pdo;
}
