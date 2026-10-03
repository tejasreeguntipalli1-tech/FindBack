<?php
/**
 * Authentication & Authorization Guards
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

function require_auth() {
    if (!is_logged_in()) {
        set_flash('danger', 'You must log in to access this page.');
        $base = get_base_url();
        header("Location: $base/auth/login.php");
        exit;
    }
}

function require_admin_role() {
    require_auth();
    if (!is_admin()) {
        set_flash('danger', 'Access denied. Administrator privileges required.');
        $base = get_base_url();
        header("Location: $base/student/dashboard.php");
        exit;
    }
}

function require_student_role() {
    require_auth();
    // Admins can also preview student pages if needed, but role check can be soft or strict.
}
