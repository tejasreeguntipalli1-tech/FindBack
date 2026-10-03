<?php
/**
 * API: Duplicate Report Detection Pre-Check
 * Implements Prompt Section 8
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/matcher.php';



$type = $_GET['type'] ?? 'lost';
$categoryId = (int)($_GET['category_id'] ?? 0);
$title = trim($_GET['title'] ?? '');
$location = trim($_GET['location'] ?? '');

if (empty($title) || $categoryId <= 0) {
    echo json_encode(['found' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, title, location, item_date, created_at
        FROM reports
        WHERE type = ? AND category_id = ? AND status = 'active' AND is_deleted = 0
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$type, $categoryId]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($candidates as $cand) {
        $nameSim = calculate_name_similarity($title, $cand['title']);
        $locSim = calculate_location_similarity($location, $cand['location']);

        // Check if strong similarity (>= 17 on title and overlapping location or high name overlap >= 21)
        if (($nameSim['score'] >= 17 && $locSim['score'] >= 9) || $nameSim['score'] >= 22) {
            echo json_encode([
                'found'       => true,
                'id'          => $cand['id'],
                'title'       => $cand['title'],
                'location'    => $cand['location'],
                'date'        => date('M d, Y', strtotime($cand['item_date'])),
                'message'     => "A similar report '{$cand['title']}' was recently submitted in {$cand['location']}."
            ]);
            exit;
        }
    }

    echo json_encode(['found' => false]);

} catch (Exception $e) {
    echo json_encode(['found' => false, 'error' => $e->getMessage()]);
}
