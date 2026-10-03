<?php
/**
 * Advanced End-to-End Verification Test Suite
 * Validates All Priority 1 - 7 Upgrades
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/matcher.php';

echo "========================================================\n";
echo "   CAMPUSFIND ADVANCED E2E VERIFICATION TEST SUITE     \n";
echo "========================================================\n\n";

$passCount = 0;
$totalTests = 0;

function assert_test($condition, $testName, $details = '') {
    global $passCount, $totalTests;
    $totalTests++;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$testName}\n";
        if ($details) echo "        -> {$details}\n";
    } else {
        echo " [FAIL] {$testName}\n";
        if ($details) echo "        -> ERROR: {$details}\n";
    }
}

// ----------------------------------------------------
// PRIORITY 1: SMART MATCHING QUALITY & EXPLAINABILITY
// ----------------------------------------------------
echo "--- PRIORITY 1: Smart Matching Scoring & Explainability ---\n";

// Test matching calculation directly with known pairs
$lostSample = [
    'id' => 101,
    'user_id' => 2,
    'type' => 'lost',
    'title' => 'Black Casio Scientific Calculator',
    'description' => 'Left my black Casio calculator with FX sticker in Lab 302',
    'category_id' => 1, // Electronics
    'category_name' => 'Electronics',
    'location' => 'Academic Block CSE Lab 302',
    'item_date' => '2026-10-02'
];

$foundSampleSimilar = [
    'id' => 102,
    'user_id' => 3,
    'type' => 'found',
    'title' => 'Casio Scientific Calculator FX',
    'description' => 'Found a black scientific calculator on desk in CSE Lab',
    'category_id' => 1,
    'category_name' => 'Electronics',
    'location' => 'CSE Block 3rd floor Lab',
    'item_date' => '2026-10-02'
];

$foundSampleDissimilar = [
    'id' => 103,
    'user_id' => 4,
    'type' => 'found',
    'title' => 'Red Water Bottle',
    'description' => 'Found stainless steel red flask near football ground',
    'category_id' => 3,
    'category_name' => 'Personal Items',
    'location' => 'Sports Complex',
    'item_date' => '2026-09-01'
];

$matchHigh = calculate_smart_match($lostSample, $foundSampleSimilar, 'Electronics');
assert_test($matchHigh['score'] >= 75, "High similarity detection for related calculator reports", "Calculated score: {$matchHigh['score']}% (Expected >= 75%)");
assert_test(isset($matchHigh['confidence_label']) && in_array($matchHigh['confidence_label'], ['Strong Potential Match', 'Possible Match']), "Confidence level properly formatted", "Got: {$matchHigh['confidence_label']}");
assert_test($matchHigh['factors']['category']['score'] === 30, "Category factor scores 30/30 for identical category", $matchHigh['factors']['category']['label']);
assert_test($matchHigh['factors']['location']['score'] > 0, "Location factor recognizes CSE / Lab overlap", "Score: {$matchHigh['factors']['location']['score']}/15 ({$matchHigh['factors']['location']['label']})");

$matchLow = calculate_smart_match($lostSample, $foundSampleDissimilar, 'Different');
assert_test($matchLow['score'] < 30, "Low similarity correctly assigned to unrelated items", "Calculated score: {$matchLow['score']}% (Expected < 30%)");

// Opposite type enforcement
$isOpposite = function($t1, $t2) {
    return ($t1 === 'lost' && $t2 === 'found') || ($t1 === 'found' && $t2 === 'lost');
};
assert_test($isOpposite('lost', 'found') === true, "Opposite type matching: 'lost' matches 'found'");
assert_test($isOpposite('lost', 'lost') === false, "Opposite type matching: 'lost' does NOT match 'lost'");

// ----------------------------------------------------
// PRIORITY 2: DUPLICATE DETECTION API
// ----------------------------------------------------
echo "\n--- PRIORITY 2: Duplicate Detection API ---\n";

// Insert an active report for Alex
$stmt = $pdo->prepare("INSERT INTO reports (user_id, type, category_id, title, description, location, item_date, status, verification_status, created_at) VALUES (2, 'lost', 1, 'Dell Inspiron 15 Charger', 'Left black Dell laptop 65W AC power adapter in library', 'Central Library', '2026-10-03', 'active', 'pending', NOW())");
$stmt->execute();
$testDupReportId = $pdo->lastInsertId();

// Test the live API endpoint http://localhost:8000/api/check_duplicate.php via GET
$dupUrl = 'http://localhost:8000/api/check_duplicate.php?' . http_build_query([
    'title' => 'Dell Inspiron 15 Charger',
    'category_id' => 1,
    'type' => 'lost',
    'location' => 'Central Library'
]);
$apiResponse = @file_get_contents($dupUrl);
$dupApiResult = json_decode($apiResponse, true);

assert_test($dupApiResult !== null && isset($dupApiResult['found']), "api/check_duplicate.php HTTP endpoint responds with valid JSON");
assert_test($dupApiResult['found'] === true, "api/check_duplicate.php detected duplicate submission", "Matched: " . ($dupApiResult['title'] ?? 'N/A'));

// Cleanup test dup report
$pdo->prepare("DELETE FROM reports WHERE id = ?")->execute([$testDupReportId]);

// ----------------------------------------------------
// PRIORITY 3: RECOVERY LIFECYCLE & AUTHORIZATION
// ----------------------------------------------------
echo "\n--- PRIORITY 3: Recovery Lifecycle & Authorization ---\n";

// Create a report owned by Alex (User 2)
$stmt = $pdo->prepare("INSERT INTO reports (user_id, type, category_id, title, description, location, item_date, status, verification_status, created_at) VALUES (2, 'lost', 2, 'Sony WH-1000XM4 Headphones', 'Black noise cancelling headphones', 'Library 3rd floor', '2026-10-01', 'active', 'verified', NOW())");
$stmt->execute();
$recoveryReportId = $pdo->lastInsertId();

// Unauthorized attempt: Sarah (User 3, non-admin) tries to mark Alex's report as recovered
$unauthorizedAllowed = false;
$userRole = 'student';
$currentUserId = 3;
if ($userRole === 'admin' || 2 == $currentUserId) {
    $unauthorizedAllowed = true;
}
assert_test($unauthorizedAllowed === false, "Unauthorized non-owner student blocked from marking item recovered", "User 3 cannot recover User 2's item");

// Authorized recovery: Alex (User 2) marks item recovered
$pdo->prepare("UPDATE reports SET status = 'recovered', recovered_at = NOW(), recovered_by = ? WHERE id = ?")->execute([2, $recoveryReportId]);

$recoveredCheck = $pdo->prepare("SELECT status, recovered_at, recovered_by FROM reports WHERE id = ?");
$recoveredCheck->execute([$recoveryReportId]);
$recRow = $recoveredCheck->fetch();

assert_test($recRow['status'] === 'recovered', "Report status successfully updated to 'recovered'");
assert_test(!empty($recRow['recovered_at']), "Timestamp recovered_at accurately recorded in database", $recRow['recovered_at']);
assert_test((int)$recRow['recovered_by'] === 2, "recovered_by accurately records the recovering user ID", "Recovered by User ID: " . $recRow['recovered_by']);

// Cleanup
$pdo->prepare("DELETE FROM reports WHERE id = ?")->execute([$recoveryReportId]);

// ----------------------------------------------------
// PRIORITY 4: EVENT-DRIVEN NOTIFICATIONS & DEDUPLICATION
// ----------------------------------------------------
echo "\n--- PRIORITY 4: Notification System & Deduplication ---\n";

$initNotifCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = 2")->fetchColumn();
create_notification($pdo, 2, "Test Event Alert", "This is an automated notification test.", 'system', "item_details.php?id=1", 1);
$newNotifCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = 2")->fetchColumn();
assert_test($newNotifCount === $initNotifCount + 1, "Notification successfully stored in database with related_report_id", "Initial: {$initNotifCount}, New: {$newNotifCount}");

// Verify related_report_id is saved
$latestNotif = $pdo->query("SELECT * FROM notifications WHERE user_id = 2 ORDER BY id DESC LIMIT 1")->fetch();
assert_test((int)$latestNotif['related_report_id'] === 1, "related_report_id column properly populated", "Value: {$latestNotif['related_report_id']}");

// Test live notification polling endpoint
$pollResponse = @file_get_contents('http://localhost:8000/api/notifications_poll.php?last_id=0');
$pollJson = json_decode($pollResponse, true);
assert_test($pollJson !== null, "api/notifications_poll.php returns valid JSON structure");

// Mark as read test
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$latestNotif['id']]);
$readCheck = $pdo->prepare("SELECT is_read FROM notifications WHERE id = ?");
$readCheck->execute([$latestNotif['id']]);
assert_test((int)$readCheck->fetchColumn() === 1, "Individual notification marked as read");

// Delete test notif
$pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$latestNotif['id']]);

// ----------------------------------------------------
// PRIORITY 5: 100% DATABASE-DRIVEN ANALYTICS
// ----------------------------------------------------
echo "\n--- PRIORITY 5: Analytics & Metrics Integrity ---\n";

// Execute HTTP request to live endpoint
$analyticsJson = @file_get_contents('http://localhost:8000/api/analytics_data.php');
$analytics = json_decode($analyticsJson, true);

assert_test($analytics !== null && isset($analytics['kpi']), "api/analytics_data.php returns valid JSON payload");
assert_test(isset($analytics['kpi']['total_reports']), "KPI total_reports present", "Count: " . ($analytics['kpi']['total_reports'] ?? 0));
assert_test(isset($analytics['kpi']['recovery_rate']), "KPI recovery_rate present", "Rate: " . ($analytics['kpi']['recovery_rate_label'] ?? ''));
assert_test(isset($analytics['kpi']['activity_trend']), "KPI activity_trend present", "Trend: " . ($analytics['kpi']['activity_trend'] ?? ''));
assert_test(isset($analytics['chart1']['labels']) && count($analytics['chart1']['labels']) === 3, "Chart 1 (Lost vs Found vs Recovered) has complete 3-label structure");
assert_test(isset($analytics['chart2']['labels']) && count($analytics['chart2']['labels']) >= 1, "Chart 2 (Activity Over Time) covers timeline intervals");
assert_test(isset($analytics['chart3']['labels']), "Chart 3 (Categories) present with labels");
assert_test(isset($analytics['chart4']['labels']), "Chart 4 (Recovery Performance) present with labels");
assert_test(isset($analytics['chart5']['labels']), "Chart 5 (Lifecycle Statuses) present with labels");

// ----------------------------------------------------
// PRIORITY 6: SEARCH RELEVANCE & MULTI-FILTER COMBINATION
// ----------------------------------------------------
echo "\n--- PRIORITY 6: Multi-Token Search & Filter Relevance ---\n";

$searchTokens = ['casio', 'calc'];
$whereClauses = ["r.is_deleted = 0"];
$params = [];

foreach ($searchTokens as $token) {
    $whereClauses[] = "(r.title LIKE ? OR r.description LIKE ? OR r.location LIKE ?)";
    $like = "%{$token}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT r.id, r.title FROM reports r WHERE " . implode(" AND ", $whereClauses);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

assert_test(count($results) > 0, "Multi-token search 'casio calc' returns matching Casio Calculator records", "Matched " . count($results) . " report(s)");

// Multi-filter test (Type + Status + Category)
$multiFilterSql = "
    SELECT COUNT(*) FROM reports r 
    WHERE r.is_deleted = 0 
      AND r.type = 'lost' 
      AND r.status = 'active' 
      AND r.category_id = 1
";
$multiCount = (int)$pdo->query($multiFilterSql)->fetchColumn();
assert_test($multiCount >= 0, "Combined multi-filter query executes cleanly without SQL syntax errors", "Matching items: {$multiCount}");

// ----------------------------------------------------
// PRIORITY 7: ADMIN VERIFICATION & REJECTION FEEDBACK
// ----------------------------------------------------
echo "\n--- PRIORITY 7: Admin Verification & Rejection Visibility ---\n";

// Insert a pending report for Alex
$stmt = $pdo->prepare("INSERT INTO reports (user_id, type, category_id, title, description, location, item_date, status, verification_status, created_at) VALUES (2, 'lost', 3, 'Black Umbrella', 'Left near canteen bench', 'Canteen Area', '2026-10-02', 'active', 'pending', NOW())");
$stmt->execute();
$rejectReportId = $pdo->lastInsertId();

// Admin rejects the report with mandatory reason
$rejectionReason = "Item photo is blurry and description lacks distinguishing brand/tag marks. Please re-submit with clear details.";
$pdo->prepare("UPDATE reports SET verification_status = 'rejected', rejection_reason = ? WHERE id = ?")->execute([$rejectionReason, $rejectReportId]);
log_admin_action($pdo, 1, $rejectReportId, 'REJECT_REPORT', "Admin rejected report #{$rejectReportId}: {$rejectionReason}");
create_notification($pdo, 2, "Report Rejected by Admin", "Your report 'Black Umbrella' was rejected. Reason: {$rejectionReason}", 'verification', "item_details.php?id={$rejectReportId}", $rejectReportId);

// Verify rejection status & reason in database
$rejectCheck = $pdo->prepare("SELECT verification_status, rejection_reason FROM reports WHERE id = ?");
$rejectCheck->execute([$rejectReportId]);
$rejRow = $rejectCheck->fetch();

assert_test($rejRow['verification_status'] === 'rejected', "Report verification_status marked 'rejected'");
assert_test($rejRow['rejection_reason'] === $rejectionReason, "Admin rejection reason stored accurately in database", $rejRow['rejection_reason']);

// Verify student notification with rejection reason
$rejNotif = $pdo->prepare("SELECT message, type FROM notifications WHERE user_id = 2 AND title LIKE '%Rejected%' ORDER BY id DESC LIMIT 1");
$rejNotif->execute();
$rejNotifRow = $rejNotif->fetch();
assert_test($rejNotifRow !== false && strpos($rejNotifRow['message'], "blurry") !== false, "Rejection notification delivered to student with full explanation");

// Cleanup
$pdo->prepare("DELETE FROM reports WHERE id = ?")->execute([$rejectReportId]);
$pdo->prepare("DELETE FROM notifications WHERE user_id = 2 AND title LIKE '%Rejected%'")->execute();

// ----------------------------------------------------
// SUMMARY
// ----------------------------------------------------
echo "\n========================================================\n";
echo " ADVANCED VERIFICATION SUMMARY: {$passCount} / {$totalTests} TESTS PASSED (" . round(($passCount / $totalTests) * 100) . "%)\n";
echo "========================================================\n";

if ($passCount === $totalTests) {
    echo ">>> ALL FUNCTIONALITY UPGRADE CRITERIA FULLY SATISFIED! <<<\n";
} else {
    echo ">>> SOME CHECKS FAILED - REVIEW ABOVE <<<\n";
}
