<?php
/**
 * Database Connection Configuration Example
 * Copy this file to config/db.php or configure your environment variables.
 */

$db_host    = getenv('DB_HOST') ?: '127.0.0.1';
$db_name    = getenv('DB_NAME') ?: 'fsd_lost_and_found';
$db_user    = getenv('DB_USER') ?: 'root';
$db_pass    = getenv('DB_PASS') ?: '';
$db_charset = 'utf8mb4';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    error_log("Database Connection Failed: " . $e->getMessage());
    die("A database connection error occurred. Please verify MySQL service is running and credentials in config/db.php are correct.");
}

function get_db_connection() {
    global $pdo;
    return $pdo;
}
