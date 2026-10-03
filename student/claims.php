<?php
/**
 * Contact Requests & Claims Controller & View
 */
$pageTitle = "Contact Requests & Claims";
require_once __DIR__ . '/../includes/auth_guard.php';
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$userId = $_SESSION['user_id'];
$baseUrl = get_base_url();

// Handle accept / decline action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $reqId = (int)$_GET['id'];
    $act = $_GET['action'];

    $checkStmt = $pdo->prepare("SELECT c.*, r.title as report_title, u.name as sender_name FROM contact_requests c JOIN reports r ON c.report_id = r.id JOIN users u ON c.sender_id = u.id WHERE c.id = ? AND c.receiver_id = ?");
    $checkStmt->execute([$reqId, $userId]);
    $request = $checkStmt->fetch();

    if ($request) {
        if ($act === 'accept') {
            $pdo->prepare("UPDATE contact_requests SET status = 'accepted' WHERE id = ?")->execute([$reqId]);
            create_notification(
                $pdo,
                $request['sender_id'],
                "Contact Request Accepted!",
                "Your request regarding '{$request['report_title']}' was accepted. You can now coordinate handover.",
                'contact',
                $baseUrl . "/student/claims.php"
            );
            set_flash('success', "Contact request accepted. You can now coordinate handover with {$request['sender_name']}.");
        } elseif ($act === 'reject') {
            $pdo->prepare("UPDATE contact_requests SET status = 'rejected' WHERE id = ?")->execute([$reqId]);
            create_notification(
                $pdo,
                $request['sender_id'],
                "Contact Request Declined",
                "Your request regarding '{$request['report_title']}' was declined by the reporter.",
                'contact',
                $baseUrl . "/student/claims.php"
            );
            set_flash('info', "Contact request declined.");
        }
    }
    header("Location: $baseUrl/student/claims.php");
    exit;
}

// Fetch Incoming Requests
$incStmt = $pdo->prepare("
    SELECT c.*, r.title AS report_title, r.type AS report_type, u.name AS sender_name, u.student_id AS sender_sid, u.department AS sender_dept
    FROM contact_requests c
    JOIN reports r ON c.report_id = r.id
    JOIN users u ON c.sender_id = u.id
    WHERE c.receiver_id = ?
    ORDER BY c.created_at DESC
");
$incStmt->execute([$userId]);
$incoming = $incStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Outgoing Requests
$outStmt = $pdo->prepare("
    SELECT c.*, r.title AS report_title, r.type AS report_type, u.name AS receiver_name, u.email AS receiver_email, u.phone AS receiver_phone
    FROM contact_requests c
    JOIN reports r ON c.report_id = r.id
    JOIN users u ON c.receiver_id = u.id
    WHERE c.sender_id = ?
    ORDER BY c.created_at DESC
");
$outStmt->execute([$userId]);
$outgoing = $outStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h3 class="fw-bold mb-1"><i class="fa-regular fa-envelope text-primary me-2"></i>Contact Inquiries &amp; Handover Requests</h3>
    <p class="text-muted small mb-0">Coordinate secure communication between finders and owners while protecting contact details until verified.</p>
</div>

<!-- Tabs for Incoming vs Outgoing -->
<ul class="nav nav-tabs mb-4" id="claimsTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="incoming-tab" data-bs-toggle="tab" data-bs-target="#incoming-pane" type="button" role="tab">
            <i class="fa-solid fa-inbox me-1"></i> Received Inquiries
            <?php if (count($incoming) > 0): ?>
                <span class="badge bg-primary ms-1"><?= count($incoming) ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="outgoing-tab" data-bs-toggle="tab" data-bs-target="#outgoing-pane" type="button" role="tab">
            <i class="fa-solid fa-paper-plane me-1"></i> Sent Inquiries
            <?php if (count($outgoing) > 0): ?>
                <span class="badge bg-secondary ms-1"><?= count($outgoing) ?></span>
            <?php endif; ?>
        </button>
    </li>
</ul>

<div class="tab-content" id="claimsTabContent">
    <!-- Incoming Tab -->
    <div class="tab-pane fade show active" id="incoming-pane" role="tabpanel">
        <?php if (empty($incoming)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-regular fa-folder-open"></i></div>
                <h6 class="empty-state-title">No received inquiries</h6>
                <p class="empty-state-text">You haven't received any messages regarding your items yet. When another student contacts you, their inquiry will be listed here.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($incoming as $req): ?>
                    <div class="col-md-6">
                        <div class="card card-custom p-4 h-100 shadow-sm border <?= $req['status'] === 'pending' ? 'border-warning' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-light text-dark border mb-1">Item: <?= e($req['report_title']) ?></span>
                                    <h6 class="fw-bold mb-0 text-dark"><?= e($req['sender_name']) ?></h6>
                                    <small class="text-muted"><?= e($req['sender_dept']) ?> (<?= e($req['sender_sid']) ?>)</small>
                                </div>
                                <div>
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <span class="badge-pending">Pending Response</span>
                                    <?php elseif ($req['status'] === 'accepted'): ?>
                                        <span class="badge-verified">Accepted</span>
                                    <?php else: ?>
                                        <span class="badge-rejected">Declined</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="bg-light p-3 rounded my-3 border small">
                                <strong>Message:</strong><br>
                                <?= nl2br(e($req['message'])) ?>
                            </div>

                            <?php if ($req['status'] === 'accepted'): ?>
                                <div class="bg-success-subtle text-success p-3 rounded mb-3 small border border-success-subtle">
                                    <div class="fw-bold mb-1"><i class="fa-solid fa-check-circle me-1"></i> Contact Information Shared:</div>
                                    <div>Email: <strong><?= e($req['contact_email']) ?></strong></div>
                                    <?php if (!empty($req['contact_phone'])): ?>
                                        <div>Phone: <strong><?= e($req['contact_phone']) ?></strong></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="mt-auto pt-2 d-flex justify-content-between align-items-center">
                                <small class="text-muted"><?= time_ago($req['created_at']) ?></small>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <div class="d-flex gap-2">
                                        <a href="claims.php?action=reject&id=<?= $req['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Decline this inquiry?');">Decline</a>
                                        <a href="claims.php?action=accept&id=<?= $req['id'] ?>" class="btn btn-success btn-sm fw-semibold">Accept &amp; Connect</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Outgoing Tab -->
    <div class="tab-pane fade" id="outgoing-pane" role="tabpanel">
        <?php if (empty($outgoing)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-regular fa-paper-plane"></i></div>
                <h6 class="empty-state-title">No sent inquiries</h6>
                <p class="empty-state-text">You haven't contacted any item reporters yet. Find an item in Browse and use "Contact Reporter" to send a claim message.</p>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($outgoing as $req): ?>
                    <div class="col-md-6">
                        <div class="card card-custom p-4 h-100 shadow-sm">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-light text-dark border mb-1">To: <?= e($req['receiver_name']) ?></span>
                                    <h6 class="fw-bold mb-0 text-dark">Regarding: <?= e($req['report_title']) ?></h6>
                                </div>
                                <div>
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <span class="badge-pending">Awaiting Response</span>
                                    <?php elseif ($req['status'] === 'accepted'): ?>
                                        <span class="badge-verified">Accepted</span>
                                    <?php else: ?>
                                        <span class="badge-rejected">Declined</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="bg-light p-3 rounded my-3 border small">
                                <strong>Your Inquiry:</strong><br>
                                <?= nl2br(e($req['message'])) ?>
                            </div>

                            <?php if ($req['status'] === 'accepted'): ?>
                                <div class="bg-success-subtle text-success p-3 rounded mb-3 small border border-success-subtle">
                                    <div class="fw-bold mb-1"><i class="fa-solid fa-circle-check me-1"></i> Reporter Accepted! Contact details:</div>
                                    <div>Email: <strong><?= e($req['receiver_email']) ?></strong></div>
                                    <?php if (!empty($req['receiver_phone'])): ?>
                                        <div>Phone: <strong><?= e($req['receiver_phone']) ?></strong></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="mt-auto pt-2 text-muted small">
                                Sent <?= time_ago($req['created_at']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
