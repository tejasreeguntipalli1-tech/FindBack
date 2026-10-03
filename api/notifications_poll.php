<?php
/**
 * API: Poll Unread Notifications
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

try {
    // 1. Get current unread count
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $cntStmt->execute([$userId]);
    $unreadCount = (int)$cntStmt->fetchColumn();

    // 2. Get highest ID for user
    $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = ?");
    $maxStmt->execute([$userId]);
    $maxId = (int)$maxStmt->fetchColumn();

    $newNotifications = [];
    if ($lastId > 0 && $maxId > $lastId) {
        $stmt = $pdo->prepare("
            SELECT id, title, message, type, link, created_at 
            FROM notifications 
            WHERE user_id = ? AND id > ? AND is_read = 0 
            ORDER BY id ASC
        ");
        $stmt->execute([$userId, $lastId]);
        $newNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success'           => true,
        'unread_count'      => $unreadCount,
        'max_id'            => $maxId,
        'new_notifications' => $newNotifications
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
