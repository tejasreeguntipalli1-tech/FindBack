<?php
/**
 * Logout Controller
 */
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

$baseUrl = get_base_url();

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

session_start();
set_flash('info', 'You have been successfully logged out.');
header("Location: $baseUrl/auth/login.php");
exit;
