<?php
/**
 * Administrator Analytics Dashboard
 * Implements Sections 49, 50, 51, 52 Specification
 */
$pageTitle = "Analytics & Reports";
require_once __DIR__ . '/../includes/auth_guard.php';
require_admin_role();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$baseUrl = get_base_url();

// Pre-load initial KPI numbers for zero-flash render
$kpiStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_reports,
        SUM(CASE WHEN is_deleted = 0 AND type = 'lost' AND status = 'active' THEN 1 ELSE 0 END) AS active_lost,
        SUM(CASE WHEN is_deleted = 0 AND type = 'found' AND status = 'active' THEN 1 ELSE 0 END) AS active_found,
        SUM(CASE WHEN is_deleted = 0 AND status = 'recovered' THEN 1 ELSE 0 END) AS recovered_reports,
        SUM(CASE WHEN is_deleted = 0 AND verification_status = 'pending' THEN 1 ELSE 0 END) AS pending_verification,
        SUM(CASE WHEN is_deleted = 0 AND (status = 'recovered' OR status = 'closed') THEN 1 ELSE 0 END) AS resolved_reports
    FROM reports
    WHERE is_deleted = 0
");
$kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);

$totalReports = (int)$kpi['total_reports'];
$recovered = (int)($kpi['recovered_reports'] ?? 0);
$resolved = (int)($kpi['resolved_reports'] ?? 0);
$recoveryRate = ($resolved > 0) ? round(($recovered / $resolved) * 100, 1) : null;
$recoveryRateLabel = ($resolved > 0) ? ($recoveryRate . '%') : 'No recovery data yet';

require_once __DIR__ . '/../includes/header.php';
?>

<div id="analyticsDashboard">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-chart-pie text-info me-2"></i>Campus Analytics &amp; Intelligence</h3>
            <p class="text-muted small mb-0">Real-time metrics, reporting lifecycle trends, and recovery performance benchmarks from live MySQL data.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.location.reload()">
                <i class="fa-solid fa-arrows-rotate me-1"></i> Refresh Data
            </button>
            <a href="<?= $baseUrl ?>/admin/manage_reports.php" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-clipboard-check me-1"></i> Moderate Reports
            </a>
        </div>
    </div>

    <!-- Section 52: Smart Real KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-card shadow-sm">
                <div class="kpi-title">Total Reports</div>
                <div class="kpi-value" id="kpi-total-reports"><?= $totalReports ?></div>
                <div class="kpi-subtext">All database submissions</div>
                <div class="kpi-icon bg-light text-dark">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="kpi-card shadow-sm">
                <div class="kpi-title">Active Lost Reports</div>
                <div class="kpi-value text-danger" id="kpi-active-lost"><?= (int)$kpi['active_lost'] ?></div>
                <div class="kpi-subtext">Currently unresolved items</div>
                <div class="kpi-icon bg-danger-subtle text-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="kpi-card shadow-sm">
                <div class="kpi-title">Active Found Reports</div>
                <div class="kpi-value text-success" id="kpi-active-found"><?= (int)$kpi['active_found'] ?></div>
                <div class="kpi-subtext">In student or security custody</div>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="kpi-card shadow-sm">
                <div class="kpi-title">Recovery Rate</div>
                <div class="kpi-value text-primary" id="kpi-recovery-rate" style="<?= $resolved === 0 ? 'font-size: 1.25rem;' : '' ?>"><?= e($recoveryRateLabel) ?></div>
                <div class="kpi-subtext">Recovered / Resolved items</div>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="fa-solid fa-bullseye"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Recovery Performance Benchmark KPI Card (Section 50 - Chart 4 supporting KPI) -->
    <div class="card card-custom p-4 mb-4 bg-light border">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-award text-warning me-2"></i>Campus Recovery Effectiveness Metric</h5>
                <p class="text-muted small mb-3">
                    Calculated strictly from verified database records:
                    <code>Recovery Rate = Recovered Reports (<?= $recovered ?>) / Total Resolved Reports (<?= $resolved ?>) × 100</code>.
                </p>
                <div class="d-flex flex-wrap gap-4 text-secondary small">
                    <div><strong>Total Reports:</strong> <span class="badge bg-secondary"><?= $totalReports ?></span></div>
                    <div><strong>Resolved Reports:</strong> <span class="badge bg-dark"><?= $resolved ?></span></div>
                    <div><strong>Recovered Reports:</strong> <span class="badge bg-primary"><?= $recovered ?></span></div>
                    <div><strong>Formula Result:</strong> <span class="badge bg-success"><?= ($resolved > 0) ? ($recoveryRate . '% Effectiveness') : 'No Resolved Reports Yet' ?></span></div>
                </div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="display-5 fw-bold text-success"><?= ($resolved > 0) ? ($recoveryRate . '%') : 'N/A' ?></div>
                <small class="text-muted text-uppercase fw-semibold"><?= ($resolved > 0) ? 'Overall Recovery Success' : 'No resolved reports yet' ?></small>
            </div>
        </div>
    </div>

    <!-- Charts Grid (Sections 50 & 51) -->
    <div class="row g-4 mb-4">
        <!-- CHART 1: LOST VS FOUND -->
        <div class="col-lg-5">
            <div class="card card-custom p-4 h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Lost vs Found Distribution</h5>
                        <p class="text-muted small mb-0">Answers: <em>What is the current distribution between lost and found reports?</em></p>
                    </div>
                    <span class="badge bg-light text-secondary border">Chart 1</span>
                </div>
                <div class="position-relative mt-3" style="height: 280px;">
                    <canvas id="chartLostVsFound"></canvas>
                </div>
            </div>
        </div>

        <!-- CHART 2: REPORT ACTIVITY OVER TIME -->
        <div class="col-lg-7">
            <div class="card card-custom p-4 h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Report Activity &mdash; Historical Timeline</h5>
                        <p class="text-muted small mb-0">Answers: <em>How has reporting activity changed over time?</em></p>
                    </div>
                    <span class="badge bg-light text-secondary border">Chart 2</span>
                </div>
                <div class="position-relative mt-3" style="height: 280px;">
                    <canvas id="chartActivityOverTime"></canvas>
                </div>
            </div>
        </div>

        <!-- CHART 3: ITEMS BY CATEGORY -->
        <div class="col-lg-6">
            <div class="card card-custom p-4 h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Most Reported Item Categories</h5>
                        <p class="text-muted small mb-0">Answers: <em>Which categories of belongings are most frequently reported?</em></p>
                    </div>
                    <span class="badge bg-light text-secondary border">Chart 3</span>
                </div>
                <div class="position-relative mt-3" style="height: 320px;">
                    <canvas id="chartItemsByCategory"></canvas>
                </div>
            </div>
        </div>

        <!-- CHART 4: RECOVERY PERFORMANCE OVER TIME -->
        <div class="col-lg-6">
            <div class="card card-custom p-4 h-100 shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Recovery Performance Trends</h5>
                        <p class="text-muted small mb-0">Answers: <em>How effectively are reported items being recovered over time?</em></p>
                    </div>
                    <span class="badge bg-light text-secondary border">Chart 4</span>
                </div>
                <div class="position-relative mt-3" style="height: 320px;">
                    <canvas id="chartRecoveryPerformance"></canvas>
                </div>
            </div>
        </div>

        <!-- CHART 5: REPORT STATUS LIFECYCLE -->
        <div class="col-12">
            <div class="card card-custom p-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Report Lifecycle &amp; Verification Breakdown</h5>
                        <p class="text-muted small mb-0">Answers: <em>What is the current operational distribution of items across lifecycle states?</em></p>
                    </div>
                    <span class="badge bg-light text-secondary border">Chart 5</span>
                </div>
                <div class="position-relative mt-3" style="height: 260px;">
                    <canvas id="chartReportStatus"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load Chart.js script specifically for this page -->
<script src="<?= $baseUrl ?>/assets/js/analytics.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
