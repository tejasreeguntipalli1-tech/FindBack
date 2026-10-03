<?php
/**
 * Global Utility & Helper Functions
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function set_flash($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type'    => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function get_flash_messages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function render_flash_messages() {
    $messages = get_flash_messages();
    if (empty($messages)) {
        return;
    }
    foreach ($messages as $msg) {
        $alertClass = $msg['type'] === 'error' ? 'danger' : $msg['type'];
        $icon = 'fa-info-circle';
        if ($alertClass === 'success') $icon = 'fa-check-circle';
        if ($alertClass === 'danger') $icon = 'fa-exclamation-circle';
        if ($alertClass === 'warning') $icon = 'fa-triangle-exclamation';

        echo '<div class="alert alert-' . e($alertClass) . ' alert-dismissible fade show shadow-sm" role="alert">';
        echo '<i class="fa-solid ' . $icon . ' me-2"></i> ' . e($msg['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

function time_ago($datetime) {
    if (!$datetime) return 'Never';
    $time = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 1) return 'Just now';

    $intervals = [
        31536000 => 'year',
        2592000  => 'month',
        604800   => 'week',
        86400    => 'day',
        3600     => 'hour',
        60       => 'minute',
        1        => 'second'
    ];

    foreach ($intervals as $secs => $label) {
        $div = $diff / $secs;
        if ($div >= 1) {
            $round = round($div);
            return $round . ' ' . $label . ($round > 1 ? 's' : '') . ' ago';
        }
    }
    return 'Just now';
}

function create_notification($pdo, $userId, $title, $message, $type = 'system', $link = null, $relatedReportId = null) {
    try {
        // Prevent duplicate alerts: check if identical notification exists for this user
        if ($relatedReportId !== null) {
            $dupCheck = $pdo->prepare("
                SELECT id FROM notifications 
                WHERE user_id = ? AND type = ? AND related_report_id = ? AND created_at >= (NOW() - INTERVAL 48 HOUR)
            ");
            $dupCheck->execute([$userId, $type, $relatedReportId]);
            if ($dupCheck->fetch()) {
                return true; // Already notified, avoid spam
            }
        } elseif ($link !== null) {
            $dupCheck = $pdo->prepare("
                SELECT id FROM notifications 
                WHERE user_id = ? AND type = ? AND link = ? AND created_at >= (NOW() - INTERVAL 24 HOUR)
            ");
            $dupCheck->execute([$userId, $type, $link]);
            if ($dupCheck->fetch()) {
                return true;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, link, related_report_id, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        return $stmt->execute([$userId, $title, $message, $type, $link, $relatedReportId]);
    } catch (PDOException $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

function log_admin_action($pdo, $adminId, $reportId, $action, $details) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log (admin_id, report_id, action, details, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$adminId, $reportId, $action, $details]);
    } catch (PDOException $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
        return false;
    }
}
