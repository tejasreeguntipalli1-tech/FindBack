<?php
/**
 * Student Dashboard
 */
$pageTitle = "Student Dashboard";
require_once __DIR__ . '/../includes/auth_guard.php';
require_student_role();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$userId = $_SESSION['user_id'];
$baseUrl = get_base_url();

try {
    // 1. Calculate Real Student KPIs from MySQL
    $kpiStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN type = 'lost' AND status = 'active' AND is_deleted = 0 THEN 1 ELSE 0 END) AS active_lost,
            SUM(CASE WHEN type = 'found' AND status = 'active' AND is_deleted = 0 THEN 1 ELSE 0 END) AS active_found,
            SUM(CASE WHEN status = 'recovered' AND is_deleted = 0 THEN 1 ELSE 0 END) AS recovered_items,
            SUM(CASE WHEN verification_status = 'pending' AND is_deleted = 0 THEN 1 ELSE 0 END) AS pending_verifications
        FROM reports
        WHERE user_id = ?
    ");
    $kpiStmt->execute([$userId]);
    $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);

    // Potential matches count for this student
    $matchStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT m.id)
        FROM potential_matches m
        JOIN reports r1 ON m.lost_report_id = r1.id
        JOIN reports r2 ON m.found_report_id = r2.id
        WHERE (r1.user_id = ? OR r2.user_id = ?) AND m.status = 'pending'
    ");
    $matchStmt->execute([$userId, $userId]);
    $potentialMatchesCount = (int)$matchStmt->fetchColumn();

    // 2. Fetch Recent Reports by this student
    $recentStmt = $pdo->prepare("
        SELECT r.*, c.name AS category_name, c.icon AS category_icon
        FROM reports r
        JOIN categories c ON r.category_id = c.id
        WHERE r.user_id = ? AND r.is_deleted = 0
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $recentStmt->execute([$userId]);
    $myReports = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Top Potential Matches for quick view
    $matchesStmt = $pdo->prepare("
        SELECT m.*, 
               r_lost.title AS lost_title, r_lost.location AS lost_location, r_lost.item_date AS lost_date, r_lost.user_id AS lost_user_id,
               r_found.title AS found_title, r_found.location AS found_location, r_found.item_date AS found_date, r_found.user_id AS found_user_id
        FROM potential_matches m
        JOIN reports r_lost ON m.lost_report_id = r_lost.id
        JOIN reports r_found ON m.found_report_id = r_found.id
        WHERE (r_lost.user_id = ? OR r_found.user_id = ?) AND m.status = 'pending'
        ORDER BY m.match_score DESC
        LIMIT 3
    ");
    $matchesStmt->execute([$userId, $userId]);
    $topMatches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    die("Database error while loading dashboard.");
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">Student Dashboard</h3>
        <p class="text-muted small mb-0">Welcome back, <?= e($_SESSION['user_name']) ?>! Track your reports and smart match notifications.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/student/report_item.php?type=lost" class="btn btn-danger btn-sm px-3 fw-semibold shadow-sm">
            <i class="fa-solid fa-plus me-1"></i> Report Lost Item
        </a>
        <a href="<?= $baseUrl ?>/student/report_item.php?type=found" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm">
            <i class="fa-solid fa-plus me-1"></i> Report Found Item
        </a>
    </div>
</div>

<!-- Real KPI Cards (Calculated directly from Database & Actionable Navigation) -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <a href="<?= $baseUrl ?>/student/my_reports.php?type=lost&status=active" class="text-decoration-none text-reset d-block" title="View your active lost reports">
            <div class="kpi-card shadow-sm">
                <div class="kpi-title">Active Lost Items</div>
                <div class="kpi-value text-danger"><?= (int)($kpi['active_lost'] ?? 0) ?></div>
                <div class="kpi-subtext">Currently open lost reports &rarr;</div>
                <div class="kpi-icon bg-danger-subtle text-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= $baseUrl ?>/student/my_reports.php?type=found&status=active" class="text-decoration-none text-reset d-block" title="View your active found reports">
            <div class="kpi-card shadow-sm">
                <div class="kpi-title">Active Found Items</div>
                <div class="kpi-value text-success"><?= (int)($kpi['active_found'] ?? 0) ?></div>
                <div class="kpi-subtext">Items reported in your custody &rarr;</div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= $baseUrl ?>/student/matches.php" class="text-decoration-none text-reset d-block" title="View your smart match alerts">
            <div class="kpi-card shadow-sm">
                <div class="kpi-title">Potential Matches</div>
                <div class="kpi-value text-warning"><?= $potentialMatchesCount ?></div>
                <div class="kpi-subtext">Smart similarity alerts &rarr;</div>
                <div class="kpi-icon bg-warning-subtle text-warning">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-xl-3">
        <a href="<?= $baseUrl ?>/student/my_reports.php?status=recovered" class="text-decoration-none text-reset d-block" title="View your resolution history">
            <div class="kpi-card shadow-sm">
                <div class="kpi-title">Items Recovered</div>
                <div class="kpi-value text-primary"><?= (int)($kpi['recovered_items'] ?? 0) ?></div>
                <div class="kpi-subtext">Successfully resolved items &rarr;</div>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Section: Smart Matches Alert if any -->
<?php if (!empty($topMatches)): ?>
    <div class="card card-custom p-4 mb-4 border-warning shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-wand-magic-sparkles text-warning me-2"></i> Smart Match Alerts Detected!
                </h5>
                <p class="text-muted small mb-0">Our similarity algorithm detected potential matching items based on Category, Title, Location, and Date proximity.</p>
            </div>
            <a href="<?= $baseUrl ?>/student/matches.php" class="btn btn-outline-warning btn-sm fw-semibold">View All Matches (<?= count($topMatches) ?>)</a>
        </div>

        <div class="row g-3">
            <?php foreach ($topMatches as $match): ?>
                <?php 
                    $isLostOwner = ($match['lost_user_id'] == $userId);
                    $myTitle = $isLostOwner ? $match['lost_title'] : $match['found_title'];
                    $otherTitle = $isLostOwner ? $match['found_title'] : $match['lost_title'];
                    $otherId = $isLostOwner ? $match['found_report_id'] : $match['lost_report_id'];
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="border rounded p-3 bg-white h-100 d-flex flex-direction-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-warning text-dark fw-bold"><?= $match['match_score'] ?>% Similarity</span>
                                <small class="text-muted"><?= time_ago($match['created_at']) ?></small>
                            </div>
                            <div class="small fw-bold text-muted mb-1"><?= $isLostOwner ? 'YOUR LOST ITEM' : 'YOUR FOUND ITEM' ?>:</div>
                            <div class="fw-semibold text-dark mb-2"><?= e($myTitle) ?></div>

                            <div class="small fw-bold text-muted mb-1"><?= $isLostOwner ? 'MATCHED FOUND ITEM' : 'MATCHED LOST ITEM' ?>:</div>
                            <div class="fw-semibold text-primary mb-3"><?= e($otherTitle) ?></div>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="<?= $baseUrl ?>/student/matches.php?match_id=<?= $match['id'] ?>" class="btn btn-sm btn-primary w-100">Compare &amp; Resolve</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Section: My Recent Reports Table -->
<div class="card card-custom p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0 text-dark">My Recent Reports</h5>
        <a href="<?= $baseUrl ?>/student/my_reports.php" class="btn btn-outline-secondary btn-sm">View All My Reports</a>
    </div>

    <?php if (empty($myReports)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-regular fa-folder-open"></i></div>
            <h6 class="empty-state-title">No reports submitted yet</h6>
            <p class="empty-state-text">You haven't reported any lost or found items on campus. If you've misplaced an item or found someone's belonging, submit a report to get started.</p>
            <div class="d-flex justify-content-center gap-2">
                <a href="<?= $baseUrl ?>/student/report_item.php?type=lost" class="btn btn-danger btn-sm">Report Lost Item</a>
                <a href="<?= $baseUrl ?>/student/report_item.php?type=found" class="btn btn-success btn-sm">Report Found Item</a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th>Item Details</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Report Date</th>
                        <th>Status</th>
                        <th>Verification</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myReports as $report): ?>
                        <tr>
                            <td>
                                <a href="<?= $baseUrl ?>/item_details.php?id=<?= $report['id'] ?>" class="fw-semibold text-dark text-decoration-none">
                                    <?= e($report['title']) ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($report['type'] === 'lost'): ?>
                                    <span class="badge-lost">Lost</span>
                                <?php else: ?>
                                    <span class="badge-found">Found</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="fa-solid <?= e($report['category_icon']) ?> me-1 text-secondary"></i>
                                <?= e($report['category_name']) ?>
                            </td>
                            <td><?= e($report['location']) ?></td>
                            <td><?= date('M d, Y', strtotime($report['item_date'])) ?></td>
                            <td>
                                <?php if ($report['status'] === 'recovered'): ?>
                                    <span class="badge-recovered">Recovered</span>
                                <?php elseif ($report['status'] === 'active'): ?>
                                    <span class="badge bg-light text-dark border">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Closed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($report['verification_status'] === 'verified'): ?>
                                    <span class="badge-verified"><i class="fa-solid fa-check me-1"></i> Verified</span>
                                <?php elseif ($report['verification_status'] === 'pending'): ?>
                                    <span class="badge-pending"><i class="fa-regular fa-clock me-1"></i> Pending</span>
                                <?php else: ?>
                                    <span class="badge-rejected"><i class="fa-solid fa-xmark me-1"></i> Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= $baseUrl ?>/item_details.php?id=<?= $report['id'] ?>" class="btn btn-outline-secondary btn-sm py-1 px-2" title="View Details">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                <?php if ($report['status'] === 'active'): ?>
                                    <a href="<?= $baseUrl ?>/student/my_reports.php?action=recover&id=<?= $report['id'] ?>" class="btn btn-outline-success btn-sm py-1 px-2" title="Mark Recovered" onclick="return confirm('Confirm that this item has been recovered?');">
                                        <i class="fa-solid fa-check"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
