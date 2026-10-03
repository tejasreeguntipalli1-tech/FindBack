<?php
/**
 * Admin Reports Moderation & Management
 * Implements Section 47: Admin Verify, Admin Reject, and Soft Delete
 */
$pageTitle = "Moderate Reports";
require_once __DIR__ . '/../includes/auth_guard.php';
require_admin_role();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$adminId = $_SESSION['user_id'];
$baseUrl = get_base_url();

// 1. Handle Verify Action
if (isset($_GET['action']) && $_GET['action'] === 'verify' && isset($_GET['id'])) {
    $reportId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT r.*, u.name as reporter_name FROM reports r JOIN users u ON r.user_id = u.id WHERE r.id = ? AND r.is_deleted = 0");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();

    if ($report) {
        $pdo->prepare("UPDATE reports SET verification_status = 'verified' WHERE id = ?")->execute([$reportId]);
        
        // Log action in database
        log_admin_action($pdo, $adminId, $reportId, 'VERIFY_REPORT', "Verified report #{$reportId} '{$report['title']}' submitted by {$report['reporter_name']}.");

        // Notify report owner
        create_notification(
            $pdo,
            $report['user_id'],
            "Report Verified",
            "Your report '{$report['title']}' has been reviewed and approved by the campus administrator.",
            'verification',
            $baseUrl . "/item_details.php?id=" . $reportId
        );

        set_flash('success', "Report #{$reportId} has been successfully verified and published.");
    }
    header("Location: $baseUrl/admin/manage_reports.php");
    exit;
}

// 2. Handle Reject Action (Requires Rejection Reason)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $reason = trim($_POST['rejection_reason'] ?? '');

    if ($reportId <= 0 || empty($reason)) {
        set_flash('danger', 'A valid rejection reason is required before rejecting a report.');
    } else {
        $stmt = $pdo->prepare("SELECT r.*, u.name as reporter_name FROM reports r JOIN users u ON r.user_id = u.id WHERE r.id = ? AND r.is_deleted = 0");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();

        if ($report) {
            $pdo->prepare("UPDATE reports SET verification_status = 'rejected', rejection_reason = ?, status = 'closed' WHERE id = ?")
                ->execute([$reason, $reportId]);

            // Log action in database
            log_admin_action($pdo, $adminId, $reportId, 'REJECT_REPORT', "Rejected report #{$reportId}: {$reason}");

            // Notify report owner
            create_notification(
                $pdo,
                $report['user_id'],
                "Report Rejected by Admin",
                "Your report '{$report['title']}' was rejected. Reason: {$reason}",
                'verification',
                $baseUrl . "/student/my_reports.php"
            );

            set_flash('warning', "Report #{$reportId} was rejected with the specified reason logged.");
        }
    }
    header("Location: $baseUrl/admin/manage_reports.php");
    exit;
}

// 3. Handle Soft Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $reportId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT title FROM reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();

    if ($report) {
        $pdo->prepare("UPDATE reports SET is_deleted = 1 WHERE id = ?")->execute([$reportId]);
        log_admin_action($pdo, $adminId, $reportId, 'DELETE_REPORT', "Soft-deleted report #{$reportId} '{$report['title']}'.");
        set_flash('info', "Report #{$reportId} removed from public active listings.");
    }
    header("Location: $baseUrl/admin/manage_reports.php");
    exit;
}

// 4. Handle Mark Recovered by Admin
if (isset($_GET['action']) && $_GET['action'] === 'recover' && isset($_GET['id'])) {
    $reportId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();

    if ($report) {
        $pdo->prepare("UPDATE reports SET status = 'recovered', recovered_at = NOW(), recovered_by = ? WHERE id = ?")->execute([$adminId, $reportId]);
        log_admin_action($pdo, $adminId, $reportId, 'MARK_RECOVERED', "Admin verified physical recovery of report #{$reportId}.");
        
        create_notification(
            $pdo,
            $report['user_id'],
            "Item Marked Recovered by Admin",
            "Security administration updated your report '{$report['title']}' to RECOVERED.",
            'recovery',
            $baseUrl . "/item_details.php?id=" . $reportId,
            $reportId
        );
        set_flash('success', "Report #{$reportId} marked as recovered.");
    }
    header("Location: $baseUrl/admin/manage_reports.php");
    exit;
}

// Filters & Search
$typeFilter = $_GET['type'] ?? 'all';
$verificationFilter = $_GET['verification'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

$sql = "
    SELECT r.*, u.name AS reporter_name, u.email AS reporter_email, c.name AS category_name, c.icon AS category_icon
    FROM reports r
    JOIN users u ON r.user_id = u.id
    JOIN categories c ON r.category_id = c.id
    WHERE r.is_deleted = 0
";
$params = [];

if ($typeFilter === 'lost' || $typeFilter === 'found') {
    $sql .= " AND r.type = ?";
    $params[] = $typeFilter;
}

if ($verificationFilter === 'pending' || $verificationFilter === 'verified' || $verificationFilter === 'rejected') {
    $sql .= " AND r.verification_status = ?";
    $params[] = $verificationFilter;
}

if ($statusFilter === 'active' || $statusFilter === 'recovered' || $statusFilter === 'closed') {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $cleanSearch = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $searchQuery);
    $tokens = preg_split('/\s+/', trim($cleanSearch), -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($tokens)) {
        foreach ($tokens as $tok) {
            $sql .= " AND (r.title LIKE ? OR r.description LIKE ? OR r.location LIKE ? OR u.name LIKE ?)";
            $like = '%' . $tok . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
    }
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Moderate Reports</h3>
        <p class="text-muted small mb-0">Approve, reject with recorded reasons, or manage item listings across the university.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Admin Dashboard
        </a>
    </div>
</div>

<!-- Filters Bar -->
<div class="card card-custom p-3 mb-4 shadow-sm">
    <form method="GET" action="manage_reports.php" class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search title, reporter, location..." value="<?= e($searchQuery) ?>">
        </div>

        <div class="col-md-2">
            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all">All Types</option>
                <option value="lost" <?= $typeFilter === 'lost' ? 'selected' : '' ?>>Lost Only</option>
                <option value="found" <?= $typeFilter === 'found' ? 'selected' : '' ?>>Found Only</option>
            </select>
        </div>

        <div class="col-md-3">
            <select name="verification" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all">All Verification Statuses</option>
                <option value="pending" <?= $verificationFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                <option value="verified" <?= $verificationFilter === 'verified' ? 'selected' : '' ?>>Verified</option>
                <option value="rejected" <?= $verificationFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>

        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all">All Lifecycles</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="recovered" <?= $statusFilter === 'recovered' ? 'selected' : '' ?>>Recovered</option>
                <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>

        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-fill">Filter</button>
            <a href="manage_reports.php" class="btn btn-outline-secondary btn-sm" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
        </div>
    </form>
</div>

<!-- Reports Table -->
<?php if (empty($reports)): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
        <h5 class="empty-state-title">No reports match your filters</h5>
        <p class="empty-state-text">Try adjusting the verification filter or clearing the search keyword.</p>
        <a href="manage_reports.php" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
    </div>
<?php else: ?>
    <div class="card card-custom p-0 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item Details</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Reporter</th>
                        <th>Location &amp; Date</th>
                        <th>Verification</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td class="small fw-bold text-muted">#<?= $r['id'] ?></td>
                            <td>
                                <a href="<?= $baseUrl ?>/item_details.php?id=<?= $r['id'] ?>" class="fw-semibold text-dark text-decoration-none">
                                    <?= e($r['title']) ?>
                                </a>
                                <?php if (!empty($r['rejection_reason'])): ?>
                                    <div class="text-danger small mt-1">
                                        <i class="fa-solid fa-circle-exclamation me-1"></i> Reason: <?= e($r['rejection_reason']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['type'] === 'lost'): ?>
                                    <span class="badge-lost">Lost</span>
                                <?php else: ?>
                                    <span class="badge-found">Found</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="small text-secondary"><?= e($r['category_name']) ?></span>
                            </td>
                            <td>
                                <div class="small fw-semibold"><?= e($r['reporter_name']) ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;"><?= e($r['reporter_email']) ?></div>
                            </td>
                            <td>
                                <div class="small"><?= e($r['location']) ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;"><?= date('M d, Y', strtotime($r['item_date'])) ?></div>
                            </td>
                            <td>
                                <?php if ($r['verification_status'] === 'verified'): ?>
                                    <span class="badge-verified"><i class="fa-solid fa-check me-1"></i> Verified</span>
                                <?php elseif ($r['verification_status'] === 'pending'): ?>
                                    <span class="badge-pending"><i class="fa-regular fa-clock me-1"></i> Pending</span>
                                <?php else: ?>
                                    <span class="badge-rejected"><i class="fa-solid fa-xmark me-1"></i> Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['status'] === 'recovered'): ?>
                                    <span class="badge-recovered">Recovered</span>
                                <?php elseif ($r['status'] === 'active'): ?>
                                    <span class="badge bg-light text-dark border">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Closed</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= $baseUrl ?>/item_details.php?id=<?= $r['id'] ?>" class="btn btn-outline-secondary btn-sm py-1 px-2" title="View details">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>

                                    <?php if ($r['verification_status'] !== 'verified'): ?>
                                        <a href="manage_reports.php?action=verify&id=<?= $r['id'] ?>" class="btn btn-outline-success btn-sm py-1 px-2" title="Verify report" onclick="return confirm('Verify and approve this report?');">
                                            <i class="fa-solid fa-check"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($r['verification_status'] !== 'rejected'): ?>
                                        <button type="button" class="btn btn-outline-warning btn-sm py-1 px-2" title="Reject report" onclick="openRejectModal(<?= $r['id'] ?>, '<?= e(addslashes($r['title'])) ?>')">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($r['status'] === 'active'): ?>
                                        <a href="manage_reports.php?action=recover&id=<?= $r['id'] ?>" class="btn btn-outline-info btn-sm py-1 px-2" title="Mark as Recovered" onclick="return confirm('Mark this item as recovered?');">
                                            <i class="fa-solid fa-handshake"></i>
                                        </a>
                                    <?php endif; ?>

                                    <a href="manage_reports.php?action=delete&id=<?= $r['id'] ?>" class="btn btn-outline-danger btn-sm py-1 px-2" title="Remove from public view" onclick="return confirm('Are you sure you want to remove this report from public listings?');">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Reject Modal (Requires Reason) -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title fw-bold text-dark" id="rejectModalLabel">
                    <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i> Reject Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_reports.php">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="report_id" id="rejectReportId">
                <div class="modal-body">
                    <p class="small text-muted mb-3" id="rejectReportTitleText"></p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Administrative Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="e.g. Insufficient description details, duplicate report, or violates university posting policy." required></textarea>
                        <div class="form-text small">This explanation will be recorded in the audit log and delivered to the student.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-semibold">
                        <i class="fa-solid fa-ban me-1"></i> Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRejectModal(id, title) {
    document.getElementById('rejectReportId').value = id;
    document.getElementById('rejectReportTitleText').innerHTML = 'Rejecting submission #<strong>' + id + '</strong>: ' + title;
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}

<?php if (isset($_GET['open_reject'])): ?>
document.addEventListener('DOMContentLoaded', () => {
    openRejectModal(<?= (int)$_GET['open_reject'] ?>, 'Report #<?= (int)$_GET['open_reject'] ?>');
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
