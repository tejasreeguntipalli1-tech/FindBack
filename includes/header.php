<?php
/**
 * Global Header Component
 */
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/db.php';

$baseUrl = get_base_url();
$currentUser = get_logged_user();

// Fetch initial unread notification count
$unreadNotifCount = 0;
$recentNotifications = [];
if ($currentUser) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$currentUser['id']]);
        $unreadNotifCount = (int)$stmt->fetchColumn();

        $notifListStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6");
        $notifListStmt->execute([$currentUser['id']]);
        $recentNotifications = $notifListStmt->fetchAll();
    } catch (Exception $e) {
        error_log("Header notif error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?>Campus Lost & Found Platform</title>
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/style.css">
    <!-- Base URL for JavaScript -->
    <script>
        window.APP_BASE_URL = "<?= $baseUrl ?>";
        window.IS_LOGGED_IN = <?= $currentUser ? 'true' : 'false' ?>;
        window.CURRENT_USER_ID = <?= $currentUser ? $currentUser['id'] : 'null' ?>;
    </script>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container-fluid px-lg-4">
        <a class="brand-logo" href="<?= $baseUrl ?>/index.php">
            <div class="brand-icon">
                <i class="fa-solid fa-compass"></i>
            </div>
            <span>Campus <span class="text-primary">Lost&Found</span></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-dismiss="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active fw-bold text-primary' : '' ?>" href="<?= $baseUrl ?>/index.php">
                        <i class="fa-solid fa-list-ul me-1"></i> Browse Items
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>/student/report_item.php?type=lost">
                        <i class="fa-solid fa-circle-exclamation text-danger me-1"></i> Report Lost
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>/student/report_item.php?type=found">
                        <i class="fa-solid fa-hand-holding-heart text-success me-1"></i> Report Found
                    </a>
                </li>

                <?php if ($currentUser && $currentUser['role'] === 'student'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/student/dashboard.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="<?= $baseUrl ?>/student/dashboard.php">
                            <i class="fa-solid fa-gauge me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/student/my_reports.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="<?= $baseUrl ?>/student/my_reports.php">
                            <i class="fa-solid fa-file-lines me-1"></i> My Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/student/matches.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="<?= $baseUrl ?>/student/matches.php">
                            <i class="fa-solid fa-wand-magic-sparkles text-warning me-1"></i> Matches
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/admin/dashboard.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="<?= $baseUrl ?>/admin/dashboard.php">
                            <i class="fa-solid fa-shield-halved text-primary me-1"></i> Admin Panel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/admin/manage_reports.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="<?= $baseUrl ?>/admin/manage_reports.php">
                            <i class="fa-solid fa-clipboard-check me-1"></i> Verify Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/admin/analytics.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="<?= $baseUrl ?>/admin/analytics.php">
                            <i class="fa-solid fa-chart-pie text-info me-1"></i> Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/admin/logs.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="<?= $baseUrl ?>/admin/logs.php">
                            <i class="fa-solid fa-clock-rotate-left me-1"></i> Activity Log
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <?php if ($currentUser): ?>
                    <!-- Real-Time Notifications Dropdown -->
                    <div class="dropdown" id="notificationDropdownContainer">
                        <button class="btn btn-light position-relative rounded-circle p-2" type="button" id="notifDropdownButton" data-bs-toggle="dropdown" aria-expanded="false" style="width: 42px; height: 42px;">
                            <i class="fa-regular fa-bell text-secondary"></i>
                            <span id="notif-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?= $unreadNotifCount == 0 ? 'd-none' : '' ?>">
                                <?= $unreadNotifCount ?>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-0" aria-labelledby="notifDropdownButton" style="width: 340px; max-height: 440px; overflow-y: auto;">
                            <li class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light rounded-top">
                                <span class="fw-bold small text-uppercase text-secondary">Notifications</span>
                                <button type="button" id="mark-all-read-btn" class="btn btn-link btn-sm p-0 text-decoration-none small text-primary">Mark all as read</button>
                            </li>
                            <div id="notif-items-list">
                                <?php if (empty($recentNotifications)): ?>
                                    <li class="p-4 text-center text-muted small">
                                        <i class="fa-regular fa-bell-slash fa-2x mb-2 d-block text-secondary opacity-50"></i>
                                        You're all caught up. No notifications.
                                    </li>
                                <?php else: ?>
                                    <?php foreach ($recentNotifications as $notif): ?>
                                        <li class="p-3 border-bottom <?= $notif['is_read'] ? 'bg-white' : 'bg-light' ?> notif-item" data-id="<?= $notif['id'] ?>">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="mt-1">
                                                    <?php if ($notif['type'] === 'match'): ?>
                                                        <i class="fa-solid fa-wand-magic-sparkles text-warning"></i>
                                                    <?php elseif ($notif['type'] === 'verification'): ?>
                                                        <i class="fa-solid fa-shield-check text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fa-solid fa-circle-info text-primary"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <strong class="small text-dark"><?= e($notif['title']) ?></strong>
                                                        <span class="text-muted" style="font-size: 0.7rem;"><?= time_ago($notif['created_at']) ?></span>
                                                    </div>
                                                    <p class="small text-muted mb-1"><?= e($notif['message']) ?></p>
                                                    <?php if ($notif['link']): ?>
                                                        <a href="<?= e($notif['link']) ?>" class="btn btn-outline-primary btn-sm py-0 px-2 text-decoration-none" style="font-size: 0.75rem;">View Details</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </ul>
                    </div>

                    <!-- User Account Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: bold; font-size: 0.85rem;">
                                <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                            </div>
                            <span class="d-none d-md-inline small fw-semibold"><?= e($currentUser['name']) ?></span>
                            <span class="badge bg-secondary-subtle text-secondary small"><?= ucfirst(e($currentUser['role'])) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold small"><?= e($currentUser['name']) ?></div>
                                <div class="text-muted small"><?= e($currentUser['email']) ?></div>
                            </li>
                            <li><a class="dropdown-item py-2" href="<?= $baseUrl ?>/student/profile.php"><i class="fa-regular fa-user me-2 text-secondary"></i> My Profile</a></li>
                            <?php if ($currentUser['role'] === 'student'): ?>
                                <li><a class="dropdown-item py-2" href="<?= $baseUrl ?>/student/my_reports.php"><i class="fa-solid fa-list-check me-2 text-secondary"></i> My Reports</a></li>
                                <li><a class="dropdown-item py-2" href="<?= $baseUrl ?>/student/claims.php"><i class="fa-regular fa-envelope me-2 text-secondary"></i> Contact Requests</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item py-2" href="<?= $baseUrl ?>/admin/manage_reports.php"><i class="fa-solid fa-sliders me-2 text-secondary"></i> Report Moderation</a></li>
                                <li><a class="dropdown-item py-2" href="<?= $baseUrl ?>/admin/users.php"><i class="fa-solid fa-users me-2 text-secondary"></i> Users Directory</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger py-2" href="<?= $baseUrl ?>/auth/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Log Out</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>/auth/login.php" class="btn btn-outline-primary btn-sm px-3 fw-semibold">Log In</a>
                    <a href="<?= $baseUrl ?>/auth/register.php" class="btn btn-primary btn-sm px-3 fw-semibold">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div id="toast-container" aria-live="polite" aria-atomic="true"></div>

<main class="container-fluid px-lg-4 py-4">
    <?php render_flash_messages(); ?>
