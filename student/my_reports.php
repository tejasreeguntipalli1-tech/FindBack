<?php
/**
 * Student's My Reports Controller & View
 */
$pageTitle = "My Reports";
require_once __DIR__ . '/../includes/auth_guard.php';
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$userId = $_SESSION['user_id'];
$baseUrl = get_base_url();

// Handle Mark Recovered Action
if (isset($_GET['action']) && $_GET['action'] === 'recover' && isset($_GET['id'])) {
    $reportId = (int)$_GET['id'];
    
    // Verify user owns the report
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ? AND user_id = ? AND is_deleted = 0");
    $stmt->execute([$reportId, $userId]);
    $report = $stmt->fetch();

    if ($report) {
        if ($report['status'] === 'recovered') {
            set_flash('info', 'This report has already been marked as recovered.');
        } else {
            // Update report status with recovery timestamp and user
            $up = $pdo->prepare("UPDATE reports SET status = 'recovered', recovered_at = NOW(), recovered_by = ? WHERE id = ?");
            $up->execute([$userId, $reportId]);

            // Update associated matches to resolved
            $matchUp = $pdo->prepare("UPDATE potential_matches SET status = 'resolved' WHERE (lost_report_id = ? OR found_report_id = ?)");
            $matchUp->execute([$reportId, $reportId]);

            // Create context-aware notification (Sections 12 & 13)
            create_notification(
                $pdo,
                $userId,
                "Report Marked as Recovered",
                "Your report '{$report['title']}' has been marked as recovered and saved in your resolution history.",
                'recovery',
                $baseUrl . "/item_details.php?id=" . $reportId,
                $reportId
            );

            set_flash('success', "Success! '{$report['title']}' marked as recovered.");
        }
    } else {
        set_flash('danger', 'Unauthorized or report not found.');
    }
    header("Location: $baseUrl/student/my_reports.php");
    exit;
}

// Filters
$typeFilter = $_GET['type'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';

$sql = "
    SELECT r.*, c.name AS category_name, c.icon AS category_icon,
           (SELECT COUNT(*) FROM potential_matches m WHERE (m.lost_report_id = r.id OR m.found_report_id = r.id) AND m.status = 'pending') AS match_count
    FROM reports r
    JOIN categories c ON r.category_id = c.id
    WHERE r.user_id = ? AND r.is_deleted = 0
";

$params = [$userId];

if ($typeFilter === 'lost' || $typeFilter === 'found') {
    $sql .= " AND r.type = ?";
    $params[] = $typeFilter;
}

if ($statusFilter === 'active') {
    $sql .= " AND r.status = 'active'";
} elseif ($statusFilter === 'recovered') {
    $sql .= " AND r.status = 'recovered'";
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1">My Item Reports</h3>
        <p class="text-muted small mb-0">Manage items you have reported as lost or found across the campus.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/student/report_item.php?type=lost" class="btn btn-danger btn-sm px-3 fw-semibold shadow-sm">
            <i class="fa-solid fa-plus me-1"></i> Report Lost
        </a>
        <a href="<?= $baseUrl ?>/student/report_item.php?type=found" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm">
            <i class="fa-solid fa-plus me-1"></i> Report Found
        </a>
    </div>
</div>

<!-- Filters Bar -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" action="my_reports.php" class="row g-2 align-items-center">
        <div class="col-auto">
            <label class="small fw-semibold text-secondary me-2">Type:</label>
            <div class="btn-group btn-group-sm">
                <a href="my_reports.php?type=all&status=<?= e($statusFilter) ?>" class="btn <?= $typeFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
                <a href="my_reports.php?type=lost&status=<?= e($statusFilter) ?>" class="btn <?= $typeFilter === 'lost' ? 'btn-danger' : 'btn-outline-secondary' ?>">Lost</a>
                <a href="my_reports.php?type=found&status=<?= e($statusFilter) ?>" class="btn <?= $typeFilter === 'found' ? 'btn-success' : 'btn-outline-secondary' ?>">Found</a>
            </div>
        </div>

        <div class="col-auto ms-md-4">
            <label class="small fw-semibold text-secondary me-2">Status:</label>
            <div class="btn-group btn-group-sm">
                <a href="my_reports.php?type=<?= e($typeFilter) ?>&status=all" class="btn <?= $statusFilter === 'all' ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
                <a href="my_reports.php?type=<?= e($typeFilter) ?>&status=active" class="btn <?= $statusFilter === 'active' ? 'btn-dark' : 'btn-outline-secondary' ?>">Active</a>
                <a href="my_reports.php?type=<?= e($typeFilter) ?>&status=recovered" class="btn <?= $statusFilter === 'recovered' ? 'btn-dark' : 'btn-outline-secondary' ?>">Recovered</a>
            </div>
        </div>

        <?php if ($typeFilter !== 'all' || $statusFilter !== 'all'): ?>
            <div class="col-auto ms-auto">
                <a href="my_reports.php" class="btn btn-link btn-sm text-secondary text-decoration-none">
                    <i class="fa-solid fa-rotate-left me-1"></i> Reset Filters
                </a>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Reports List -->
<?php if (empty($reports)): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa-regular fa-clipboard"></i></div>
        <h5 class="empty-state-title">No reports found</h5>
        <p class="empty-state-text">You don't have any reports matching the selected filters. Submit a report or reset filters to see all records.</p>
        <a href="my_reports.php" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
    </div>
<?php else: ?>
    <div class="card card-custom p-0 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th>Item Title</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Date Reported</th>
                        <th>Status</th>
                        <th>Admin Review</th>
                        <th>Smart Matches</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($r['image_path'])): ?>
                                        <img src="<?= $baseUrl ?>/<?= e($r['image_path']) ?>" alt="" class="rounded border" style="width: 40px; height: 40px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded bg-light border d-flex align-items-center justify-content-center text-muted" style="width: 40px; height: 40px;">
                                            <i class="fa-solid <?= e($r['category_icon']) ?>"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <a href="<?= $baseUrl ?>/item_details.php?id=<?= $r['id'] ?>" class="fw-semibold text-dark text-decoration-none">
                                            <?= e($r['title']) ?>
                                        </a>
                                        <div class="text-muted" style="font-size: 0.75rem;">ID #<?= $r['id'] ?></div>
                                    </div>
                                </div>
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
                            <td class="small"><?= e($r['location']) ?></td>
                            <td class="small"><?= date('M d, Y', strtotime($r['item_date'])) ?></td>
                            <td>
                                <?php if ($r['status'] === 'recovered'): ?>
                                    <span class="badge-recovered"><i class="fa-solid fa-check me-1"></i> Recovered</span>
                                <?php elseif ($r['status'] === 'active'): ?>
                                    <span class="badge bg-light text-dark border">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Closed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['verification_status'] === 'verified'): ?>
                                    <span class="badge-verified"><i class="fa-solid fa-check me-1"></i> Verified</span>
                                <?php elseif ($r['verification_status'] === 'pending'): ?>
                                    <span class="badge-pending"><i class="fa-regular fa-clock me-1"></i> Pending verification</span>
                                <?php else: ?>
                                    <div>
                                        <span class="badge-rejected"><i class="fa-solid fa-xmark me-1"></i> Rejected</span>
                                        <?php if (!empty($r['rejection_reason'])): ?>
                                            <div class="text-danger small mt-1" style="font-size: 0.75rem; max-width: 180px;">
                                                <i class="fa-solid fa-circle-exclamation me-1"></i> <?= e($r['rejection_reason']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['match_count'] > 0): ?>
                                    <a href="<?= $baseUrl ?>/student/matches.php?report_id=<?= $r['id'] ?>" class="btn btn-warning btn-sm py-0 px-2 fw-semibold" style="font-size: 0.75rem;">
                                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i> <?= $r['match_count'] ?> Potential Match<?= $r['match_count'] > 1 ? 'es' : '' ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">None yet</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="<?= $baseUrl ?>/item_details.php?id=<?= $r['id'] ?>" class="btn btn-outline-secondary btn-sm py-1 px-2" title="View details">
                                        <i class="fa-regular fa-eye"></i>
                                    </a>
                                    <?php if ($r['status'] === 'active'): ?>
                                        <a href="my_reports.php?action=recover&id=<?= $r['id'] ?>" class="btn btn-outline-success btn-sm py-1 px-2" title="Mark as Recovered" onclick="return confirm('Confirm that this item has been successfully recovered?');">
                                            <i class="fa-solid fa-check"></i> Recovered
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
