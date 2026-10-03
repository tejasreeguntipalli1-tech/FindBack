<?php
/**
 * Administrator Dashboard
 */
$pageTitle = "Admin Dashboard";
require_once __DIR__ . '/../includes/auth_guard.php';
require_admin_role();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$baseUrl = get_base_url();

try {
    // 1. Calculate Real Admin Statistics from MySQL
    $kpiStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_reports,
            SUM(CASE WHEN is_deleted = 0 AND type = 'lost' AND status = 'active' THEN 1 ELSE 0 END) AS active_lost,
            SUM(CASE WHEN is_deleted = 0 AND type = 'found' AND status = 'active' THEN 1 ELSE 0 END) AS active_found,
            SUM(CASE WHEN is_deleted = 0 AND status = 'recovered' THEN 1 ELSE 0 END) AS recovered_reports,
            SUM(CASE WHEN is_deleted = 0 AND verification_status = 'pending' THEN 1 ELSE 0 END) AS pending_verification,
            SUM(CASE WHEN is_deleted = 0 AND verification_status = 'verified' THEN 1 ELSE 0 END) AS verified_reports,
            SUM(CASE WHEN is_deleted = 0 AND (status = 'recovered' OR status = 'closed') THEN 1 ELSE 0 END) AS resolved_reports
        FROM reports
        WHERE is_deleted = 0
    ");
    $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);

    $resolved = (int)($kpi['resolved_reports'] ?? 0);
    $recovered = (int)($kpi['recovered_reports'] ?? 0);
    $recoveryRate = ($resolved > 0) ? round(($recovered / $resolved) * 100, 1) : 0;

    // Potential matches count
    $potentialMatchesCount = (int)$pdo->query("SELECT COUNT(*) FROM potential_matches WHERE status = 'pending'")->fetchColumn();

    // 2. Pending Verification Queue
    $pendingStmt = $pdo->query("
        SELECT r.*, u.name AS reporter_name, u.email AS reporter_email, c.name AS category_name, c.icon AS category_icon
        FROM reports r
        JOIN users u ON r.user_id = u.id
        JOIN categories c ON r.category_id = c.id
        WHERE r.verification_status = 'pending' AND r.is_deleted = 0
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $pendingReports = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Recent Admin Activity Logs
    $logsStmt = $pdo->query("
        SELECT l.*, u.name AS admin_name
        FROM admin_activity_log l
        JOIN users u ON l.admin_id = u.id
        ORDER BY l.created_at DESC
        LIMIT 6
    ");
    $recentLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    die("Database error while loading admin metrics.");
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="fa-solid fa-shield-halved text-primary me-2"></i>Campus Admin Operations Center</h3>
        <p class="text-muted small mb-0">Live moderation, real-time statistics, and activity verification across all campus sectors.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/admin/manage_reports.php" class="btn btn-primary btn-sm px-3 fw-semibold">
            <i class="fa-solid fa-list-check me-1"></i> Moderate Reports
        </a>
        <a href="<?= $baseUrl ?>/admin/analytics.php" class="btn btn-outline-info btn-sm px-3 fw-semibold">
            <i class="fa-solid fa-chart-pie me-1"></i> View Analytics
        </a>
    </div>
</div>

<!-- Smart Real KPI Cards (Sections 48, 52, & 20: Actionable KPI Navigation) -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <a href="<?= $baseUrl ?>/admin/manage_reports.php" class="text-decoration-none text-reset d-block" title="Click to view all reports">
            <div class="kpi-card shadow-sm">
                <div class="kpi-title">Total Reports</div>
                <div class="kpi-value"><?= (int)$kpi['total_reports'] ?></div>
                <div class="kpi-subtext">All submitted reports in system &rarr;</div>
                <div class="kpi-icon bg-secondary-subtle text-dark">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= $baseUrl ?>/admin/manage_reports.php?verification=pending" class="text-decoration-none text-reset d-block" title="Click to review pending reports">
            <div class="kpi-card shadow-sm">
                <div class="kpi-title">Pending Verification</div>
                <div class="kpi-value text-warning"><?= (int)$kpi['pending_verification'] ?></div>
                <div class="kpi-subtext">Requires review (Click to open) &rarr;</div>
                <div class="kpi-icon bg-warning-subtle text-warning">
                    <i class="fa-regular fa-clock"></i>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= $baseUrl ?>/admin/manage_reports.php?type=lost&status=active" class="text-decoration-none text-reset d-block" title="Click to view active lost reports">
            <div class="kpi-card shadow-sm">
                <div class="kpi-title">Active Lost Reports</div>
                <div class="kpi-value text-danger"><?= (int)$kpi['active_lost'] ?></div>
                <div class="kpi-subtext">Currently unresolved &rarr;</div>
                <div class="kpi-icon bg-danger-subtle text-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= $baseUrl ?>/admin/manage_reports.php?status=recovered" class="text-decoration-none text-reset d-block" title="Click to view recovered reports">
            <div class="kpi-card shadow-sm">
                <div class="kpi-title">Recovery Rate</div>
                <div class="kpi-value text-success"><?= $recoveryRate ?>%</div>
                <div class="kpi-subtext">Based on resolved (<?= $recovered ?> items) &rarr;</div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Pending Verification Queue -->
<div class="card card-custom p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold mb-0 text-dark">Pending Verification Queue</h5>
            <p class="text-muted small mb-0">Newly submitted items requiring admin confirmation before public publishing.</p>
        </div>
        <a href="<?= $baseUrl ?>/admin/manage_reports.php?verification=pending" class="btn btn-outline-warning btn-sm">
            View All Pending (<?= (int)$kpi['pending_verification'] ?>)
        </a>
    </div>

    <?php if (empty($pendingReports)): ?>
        <div class="empty-state py-4">
            <div class="empty-state-icon text-success"><i class="fa-solid fa-circle-check"></i></div>
            <h6 class="empty-state-title">Verification Queue Clear</h6>
            <p class="empty-state-text">All reported lost and found items have been reviewed by administrators.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th>Item Title</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Reporter</th>
                        <th>Location &amp; Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingReports as $pr): ?>
                        <tr>
                            <td>
                                <a href="<?= $baseUrl ?>/item_details.php?id=<?= $pr['id'] ?>" class="fw-semibold text-dark text-decoration-none">
                                    <?= e($pr['title']) ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($pr['type'] === 'lost'): ?>
                                    <span class="badge-lost">Lost</span>
                                <?php else: ?>
                                    <span class="badge-found">Found</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="fa-solid <?= e($pr['category_icon']) ?> me-1 text-secondary"></i>
                                <?= e($pr['category_name']) ?>
                            </td>
                            <td>
                                <div class="small fw-semibold"><?= e($pr['reporter_name']) ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= e($pr['reporter_email']) ?></div>
                            </td>
                            <td>
                                <div class="small"><?= e($pr['location']) ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= date('M d, Y', strtotime($pr['item_date'])) ?></div>
                            </td>
                            <td class="text-end">
                                <a href="<?= $baseUrl ?>/admin/manage_reports.php?action=verify&id=<?= $pr['id'] ?>" class="btn btn-success btn-sm py-1 px-2 fw-semibold" onclick="return confirm('Verify and publish this report?');">
                                    <i class="fa-solid fa-check me-1"></i> Verify
                                </a>
                                <a href="<?= $baseUrl ?>/admin/manage_reports.php?open_reject=<?= $pr['id'] ?>" class="btn btn-outline-danger btn-sm py-1 px-2">
                                    <i class="fa-solid fa-xmark me-1"></i> Reject
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Section: Real Admin Activity Log Preview -->
<div class="card card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold mb-0 text-dark">Recent Admin Activity Log</h5>
            <p class="text-muted small mb-0">Audit trail of actions executed by campus administrators.</p>
        </div>
        <a href="<?= $baseUrl ?>/admin/logs.php" class="btn btn-outline-secondary btn-sm">Full Activity Log</a>
    </div>

    <div class="table-responsive">
        <table class="table table-custom mb-0">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Report ID</th>
                    <th>Administrator</th>
                    <th>Details / Reason</th>
                    <th class="text-end">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLogs)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">No activity recorded yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td>
                                <?php if ($log['action'] === 'VERIFY_REPORT'): ?>
                                    <span class="badge-verified"><i class="fa-solid fa-check me-1"></i> Verified</span>
                                <?php elseif ($log['action'] === 'REJECT_REPORT'): ?>
                                    <span class="badge-rejected"><i class="fa-solid fa-xmark me-1"></i> Rejected</span>
                                <?php elseif ($log['action'] === 'DELETE_REPORT'): ?>
                                    <span class="badge bg-danger text-white">Deleted</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= e($log['action']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['report_id']): ?>
                                    <a href="<?= $baseUrl ?>/item_details.php?id=<?= $log['report_id'] ?>" class="text-decoration-none fw-semibold">
                                        #<?= $log['report_id'] ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="small fw-semibold"><?= e($log['admin_name']) ?></span></td>
                            <td class="small text-secondary"><?= e($log['details']) ?></td>
                            <td class="text-end small text-muted"><?= time_ago($log['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
