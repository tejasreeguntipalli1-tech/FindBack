<?php
/**
 * API: Mark Notifications as Read
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$markAll = isset($_GET['all']) && $_GET['all'] == '1';
$notifId = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

try {
    if ($markAll) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
    } elseif ($notifId > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
