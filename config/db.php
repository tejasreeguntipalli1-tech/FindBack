<?php
/**
 * Database Connection Configuration
 * Uses PDO for secure prepared statements and robust error handling.
 */

$db_host = '127.0.0.1';
$db_name = 'fsd_lost_and_found';
$db_user = 'root';
$db_pass = '';
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
    // Graceful error logging rather than exposing raw server details
    error_log("Database Connection Failed: " . $e->getMessage());
    die("A temporary database error occurred. Please verify MySQL service is running and try again.");
}

function get_db_connection() {
    global $pdo;
    return $pdo;
}
