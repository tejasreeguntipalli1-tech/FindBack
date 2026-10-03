<?php
/**
 * API: Submit Contact Request / Claim on an Item
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to contact the reporter.']);
    exit;
}

$senderId = $_SESSION['user_id'];
$reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
$message = trim($_POST['message'] ?? '');
$contactEmail = trim($_POST['contact_email'] ?? $_SESSION['user_email']);
$contactPhone = trim($_POST['contact_phone'] ?? '');

if ($reportId <= 0 || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in a message describing your claim.']);
    exit;
}

try {
    // 1. Fetch report details
    $stmt = $pdo->prepare("SELECT r.*, u.id as owner_id, u.name as owner_name FROM reports r JOIN users u ON r.user_id = u.id WHERE r.id = ? AND r.is_deleted = 0");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();

    if (!$report) {
        echo json_encode(['success' => false, 'message' => 'Report not found or has been removed.']);
        exit;
    }

    if ($report['owner_id'] == $senderId) {
        echo json_encode(['success' => false, 'message' => 'You cannot submit a claim on your own report.']);
        exit;
    }

    // 2. Insert into contact_requests
    $ins = $pdo->prepare("
        INSERT INTO contact_requests (sender_id, receiver_id, report_id, message, contact_email, contact_phone, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $ins->execute([$senderId, $report['owner_id'], $reportId, $message, $contactEmail, $contactPhone]);

    // 3. Notify report owner
    $senderName = $_SESSION['user_name'] ?? 'A student';
    $baseUrl = get_base_url();
    create_notification(
        $pdo,
        $report['owner_id'],
        "New Claim / Message on '{$report['title']}'",
        "{$senderName} has sent you a message regarding your report.",
        'contact',
        $baseUrl . "/student/claims.php"
    );

    echo json_encode(['success' => true, 'message' => 'Your message has been sent to the reporter successfully!']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
