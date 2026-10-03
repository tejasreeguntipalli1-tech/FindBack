<?php
/**
 * Session & Authentication Guards
 */

if (session_status() === PHP_SESSION_NONE) {
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
 * Automatically determine the base URL so links work across Apache and PHP -S
 */
function get_base_url() {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    // If inside /fsd_lost_and_found/ subdirectory:
    $pos = strpos($scriptName, '/fsd_lost_and_found');
    if ($pos !== false) {
        return '/fsd_lost_and_found';
    }
    // If served from root
    return '';
}
