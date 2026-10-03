<?php
/**
 * Session & Authentication Guards
 * Production-ready session initialization, CSRF token management, and dynamic base URL detection.
 */

require_once __DIR__ . '/env.php';

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_logged_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'         => $_SESSION['user_id'],
        'name'       => $_SESSION['user_name'] ?? 'User',
        'email'      => $_SESSION['user_email'] ?? '',
        'role'       => $_SESSION['user_role'] ?? 'student',
        'student_id' => $_SESSION['student_id'] ?? '',
        'department' => $_SESSION['department'] ?? '',
        'phone'      => $_SESSION['user_phone'] ?? '',
    ];
}

function is_admin() {
    return is_logged_in() && (($_SESSION['user_role'] ?? '') === 'admin');
}

function is_student() {
    return is_logged_in() && (($_SESSION['user_role'] ?? '') === 'student');
}

/**
 * Automatically determine the base URL so links work across Apache, Nginx, and PHP built-in server
 */
function get_base_url() {
    // 1. Explicit environment configuration override (e.g. from .env)
    $appUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '');
    if (!empty($appUrl) && $appUrl !== 'http://localhost:8000') {
        // If an absolute URL or path is configured
        $parsed = parse_url($appUrl, PHP_URL_PATH);
        if ($parsed !== null) {
            return rtrim($parsed, '/');
        }
    }

    // 2. Dynamic directory discovery from SCRIPT_NAME
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    
    // Check known application sub-paths
    $knownSubdirs = ['/admin', '/student', '/api', '/auth', '/includes', '/config'];
    $dir = dirname($scriptName);
    foreach ($knownSubdirs as $sub) {
        if (substr($dir, -strlen($sub)) === $sub) {
            $dir = substr($dir, 0, -strlen($sub));
            break;
        }
    }
    $dir = str_replace('\\', '/', $dir);

    // If installed in a root folder (like http://localhost:8000/ or http://mycampusfind.com/)
    if ($dir === '/' || $dir === '.' || empty($dir)) {
        return '';
    }

    return rtrim($dir, '/');
}
