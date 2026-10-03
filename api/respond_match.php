<?php
/**
 * API: Respond to Smart Match (e.g. Dismiss / Not a Match)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$matchId = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : 'dismiss';

if ($matchId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid match ID']);
    exit;
}

try {
    // Verify user owns either the lost or found report
    $stmt = $pdo->prepare("
        SELECT m.*, r1.user_id AS lost_user, r2.user_id AS found_user 
        FROM potential_matches m
        JOIN reports r1 ON m.lost_report_id = r1.id
        JOIN reports r2 ON m.found_report_id = r2.id
        WHERE m.id = ?
    ");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();

    if (!$match) {
        echo json_encode(['success' => false, 'message' => 'Match not found']);
        exit;
    }

    if ($match['lost_user'] != $userId && $match['found_user'] != $userId && !is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    if ($action === 'dismiss') {
        $up = $pdo->prepare("UPDATE potential_matches SET status = 'dismissed' WHERE id = ?");
        $up->execute([$matchId]);
        echo json_encode(['success' => true, 'message' => 'Match dismissed.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
