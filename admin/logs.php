<?php
/**
 * Admin Activity Audit Log
 * Implements Section 60 Specification
 */
$pageTitle = "Admin Activity Log";
require_once __DIR__ . '/../includes/auth_guard.php';
require_admin_role();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$baseUrl = get_base_url();

// Filter by action
$actionFilter = $_GET['action'] ?? 'all';

$sql = "
    SELECT l.*, u.name AS admin_name, u.email AS admin_email, r.title AS report_title, r.type AS report_type
    FROM admin_activity_log l
    JOIN users u ON l.admin_id = u.id
    LEFT JOIN reports r ON l.report_id = r.id
";

$params = [];
if ($actionFilter !== 'all') {
    $sql .= " WHERE l.action = ?";
    $params[] = $actionFilter;
}

$sql .= " ORDER BY l.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Administrative Audit Log</h3>
        <p class="text-muted small mb-0">Transparent records of all verification, rejection, and modification operations executed by administrators.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Admin Dashboard
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card card-custom p-3 mb-4 shadow-sm">
    <form method="GET" action="logs.php" class="row g-2 align-items-center">
        <div class="col-auto">
            <label class="small fw-semibold text-secondary me-2">Filter by Action:</label>
            <div class="btn-group btn-group-sm">
                <a href="logs.php?action=all" class="btn <?= $actionFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">All Actions</a>
                <a href="logs.php?action=VERIFY_REPORT" class="btn <?= $actionFilter === 'VERIFY_REPORT' ? 'btn-success' : 'btn-outline-secondary' ?>">Verifications</a>
                <a href="logs.php?action=REJECT_REPORT" class="btn <?= $actionFilter === 'REJECT_REPORT' ? 'btn-danger' : 'btn-outline-secondary' ?>">Rejections</a>
                <a href="logs.php?action=DELETE_REPORT" class="btn <?= $actionFilter === 'DELETE_REPORT' ? 'btn-dark' : 'btn-outline-secondary' ?>">Deletions</a>
                <a href="logs.php?action=MARK_RECOVERED" class="btn <?= $actionFilter === 'MARK_RECOVERED' ? 'btn-info' : 'btn-outline-secondary' ?>">Recoveries</a>
            </div>
        </div>
    </form>
</div>

<!-- Logs Table -->
<?php if (empty($logs)): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa-solid fa-clipboard-list"></i></div>
        <h5 class="empty-state-title">No audit log entries found</h5>
        <p class="empty-state-text">No administrative actions have been recorded matching your criteria.</p>
    </div>
<?php else: ?>
    <div class="card card-custom p-0 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Action Performed</th>
                        <th>Associated Report</th>
                        <th>Administrator</th>
                        <th>Details / Justification</th>
                        <th class="text-end">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="small fw-bold text-muted">#<?= $log['id'] ?></td>
                            <td>
                                <?php if ($log['action'] === 'VERIFY_REPORT'): ?>
                                    <span class="badge-verified"><i class="fa-solid fa-check me-1"></i> VERIFY</span>
                                <?php elseif ($log['action'] === 'REJECT_REPORT'): ?>
                                    <span class="badge-rejected"><i class="fa-solid fa-xmark me-1"></i> REJECT</span>
                                <?php elseif ($log['action'] === 'DELETE_REPORT'): ?>
                                    <span class="badge bg-danger text-white"><i class="fa-regular fa-trash-can me-1"></i> DELETE</span>
                                <?php elseif ($log['action'] === 'MARK_RECOVERED'): ?>
                                    <span class="badge-recovered"><i class="fa-solid fa-handshake me-1"></i> RECOVERED</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= e($log['action']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['report_id']): ?>
                                    <a href="<?= $baseUrl ?>/item_details.php?id=<?= $log['report_id'] ?>" class="fw-semibold text-dark text-decoration-none">
                                        #<?= $log['report_id'] ?> <?= e($log['report_title'] ? '— ' . $log['report_title'] : '') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">General Action</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small fw-semibold"><?= e($log['admin_name']) ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;"><?= e($log['admin_email']) ?></div>
                            </td>
                            <td>
                                <div class="small text-secondary"><?= e($log['details']) ?></div>
                            </td>
                            <td class="text-end text-muted small">
                                <div><?= date('d M Y, h:i A', strtotime($log['created_at'])) ?></div>
                                <div class="text-secondary" style="font-size: 0.72rem;"><?= time_ago($log['created_at']) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
