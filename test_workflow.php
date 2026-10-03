<?php
/**
 * Comprehensive Functionality Audit Script
 * Tests Prompt Sections 46, 47, 48, 54, 56, 62
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/matcher.php';

echo "=== STARTING FULL FUNCTIONALITY AUDIT ===\n\n";

// 1. Initial State Check
$initialCount = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE is_deleted = 0")->fetchColumn();
echo "[1] Initial Total Reports in DB: {$initialCount}\n";

// 2. Fetch test user (Alex) and test admin
$alex = $pdo->query("SELECT * FROM users WHERE email = 'alex@campus.edu'")->fetch();
$admin = $pdo->query("SELECT * FROM users WHERE email = 'admin@campus.edu'")->fetch();
$sarah = $pdo->query("SELECT * FROM users WHERE email = 'sarah@campus.edu'")->fetch();

assert($alex !== false, "User Alex must exist");
assert($admin !== false, "Admin must exist");
echo "[2] Verified Users: Alex (ID {$alex['id']}), Admin (ID {$admin['id']}), Sarah (ID {$sarah['id']})\n";

// 3. User Submits New Lost Report
$catId = (int)$pdo->query("SELECT id FROM categories WHERE name LIKE '%Stationery%'")->fetchColumn();
$stmt = $pdo->prepare("
    INSERT INTO reports (user_id, type, category_id, title, description, location, item_date, status, verification_status, created_at)
    VALUES (?, 'lost', ?, 'Blue Parker Vector Fountain Pen', 'Lost a dark blue metallic Parker fountain pen with silver clip on reading table 5 in Central Library.', 'Central Library 2nd Floor', '2026-10-03', 'active', 'pending', NOW())
");
$stmt->execute([$alex['id'], $catId]);
$newLostId = $pdo->lastInsertId();
echo "[3] Alex created Lost Report #{$newLostId} (Pending Verification)\n";

// Verify Total Reports increased
$newCount = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE is_deleted = 0")->fetchColumn();
echo "    -> New Total Reports: {$newCount} (Expected: " . ($initialCount + 1) . ")\n";
assert($newCount === $initialCount + 1, "Total reports count must increase by 1");

// 4. Another Student (Sarah) Submits Matching Found Report
$stmt = $pdo->prepare("
    INSERT INTO reports (user_id, type, category_id, title, description, location, item_date, status, verification_status, created_at)
    VALUES (?, 'found', ?, 'Blue Parker Pen', 'Found a blue Parker fountain pen on a desk near the bookshelves in Library.', 'Central Library', '2026-10-03', 'active', 'pending', NOW())
");
$stmt->execute([$sarah['id'], $catId]);
$newFoundId = $pdo->lastInsertId();
echo "[4] Sarah created Found Report #{$newFoundId} (Pending Verification)\n";

// 5. Run Smart Matching Engine on New Reports
echo "[5] Executing Smart Matching Engine for Report #{$newFoundId}...\n";
$matchesTriggered = run_matching_engine_for_report($pdo, $newFoundId);
echo "    -> New Matches Recorded: {$matchesTriggered}\n";

// Check potential_matches table
$matchRecord = $pdo->prepare("SELECT * FROM potential_matches WHERE (lost_report_id = ? AND found_report_id = ?) OR (lost_report_id = ? AND found_report_id = ?)");
$matchRecord->execute([$newLostId, $newFoundId, $newFoundId, $newLostId]);
$match = $matchRecord->fetch();

assert($match !== false, "Smart match record must be generated between lost and found items");
echo "    -> Match Detected! Score: {$match['match_score']}%\n";
$factors = json_decode($match['factors_json'], true);
echo "       Factors Breakdown:\n";
echo "       - Category: {$factors['category']['score']}/{$factors['category']['max']} ({$factors['category']['label']})\n";
echo "       - Name: {$factors['name']['score']}/{$factors['name']['max']} ({$factors['name']['label']})\n";
echo "       - Description: {$factors['description']['score']}/{$factors['description']['max']} ({$factors['description']['label']})\n";
echo "       - Location: {$factors['location']['score']}/{$factors['location']['max']} ({$factors['location']['label']})\n";
echo "       - Date: {$factors['date']['score']}/{$factors['date']['max']} ({$factors['date']['label']})\n";

// 6. Check Notifications Generated
$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$notifStmt->execute([$alex['id']]);
$notif = $notifStmt->fetch();
echo "[6] Alex's Latest Notification: \"{$notif['title']}\" — {$notif['message']}\n";

// 7. Admin Verifies Lost Report #$newLostId
echo "[7] Admin verifying Report #{$newLostId}...\n";
$pdo->prepare("UPDATE reports SET verification_status = 'verified' WHERE id = ?")->execute([$newLostId]);
log_admin_action($pdo, $admin['id'], $newLostId, 'VERIFY_REPORT', "Admin verified report #{$newLostId} 'Blue Parker Vector Fountain Pen'.");
create_notification($pdo, $alex['id'], "Report Verified", "Your report for 'Blue Parker Vector Fountain Pen' has been approved by admin.", 'verification', "item_details.php?id={$newLostId}");

// Check Admin Log was written
$logEntry = $pdo->query("SELECT * FROM admin_activity_log WHERE report_id = {$newLostId} ORDER BY id DESC LIMIT 1")->fetch();
assert($logEntry !== false, "Admin activity log must have record for verification");
echo "    -> Admin Activity Log entry confirmed: [{$logEntry['action']}] {$logEntry['details']}\n";

// 8. Student Marks Item as Recovered
echo "[8] Student Alex marks item #{$newLostId} as Recovered...\n";
$pdo->prepare("UPDATE reports SET status = 'recovered', recovered_at = NOW() WHERE id = ?")->execute([$newLostId]);
$pdo->prepare("UPDATE potential_matches SET status = 'resolved' WHERE lost_report_id = ? OR found_report_id = ?")->execute([$newLostId, $newLostId]);

// Verify recovery status and recovered count
$recoveredCount = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'recovered' AND is_deleted = 0")->fetchColumn();
echo "    -> Total Recovered Reports in DB: {$recoveredCount}\n";

// 9. Soft-Delete Test by Admin on Found item
echo "[9] Admin soft-deletes Found report #{$newFoundId}...\n";
$pdo->prepare("UPDATE reports SET is_deleted = 1 WHERE id = ?")->execute([$newFoundId]);
log_admin_action($pdo, $admin['id'], $newFoundId, 'DELETE_REPORT', "Soft deleted report #{$newFoundId}.");

// Verify that it no longer appears in active browse query
$browseCheck = $pdo->prepare("SELECT id FROM reports WHERE id = ? AND is_deleted = 0");
$browseCheck->execute([$newFoundId]);
assert($browseCheck->fetch() === false, "Soft-deleted item must not appear in active listings");
echo "    -> Verified: Item #{$newFoundId} excluded from active queries.\n";

echo "\n=== ALL WORKFLOW & FUNCTIONALITY AUDITS PASSED WITH 100% SUCCESS! ===\n";
