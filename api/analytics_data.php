<?php
/**
 * API: Analytics & Metrics Engine
 * Supplies real database calculations for all 5 required charts & KPI cards.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

try {
    // 1. KPI Aggregates
    $kpiStmt = $pdo->query("
        SELECT 
            COUNT(*) AS total_reports,
            SUM(CASE WHEN is_deleted = 0 AND type = 'lost' AND status = 'active' THEN 1 ELSE 0 END) AS active_lost,
            SUM(CASE WHEN is_deleted = 0 AND type = 'found' AND status = 'active' THEN 1 ELSE 0 END) AS active_found,
            SUM(CASE WHEN is_deleted = 0 AND status = 'recovered' THEN 1 ELSE 0 END) AS recovered_reports,
            SUM(CASE WHEN is_deleted = 0 AND verification_status = 'pending' THEN 1 ELSE 0 END) AS pending_verification,
            SUM(CASE WHEN is_deleted = 0 AND verification_status = 'verified' THEN 1 ELSE 0 END) AS verified_reports,
            SUM(CASE WHEN is_deleted = 0 AND verification_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_reports,
            SUM(CASE WHEN is_deleted = 0 AND (status = 'recovered' OR status = 'closed') THEN 1 ELSE 0 END) AS resolved_reports
        FROM reports
        WHERE is_deleted = 0
    ");
    $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC);

    // Potential matches count
    $matchCnt = (int)$pdo->query("SELECT COUNT(*) FROM potential_matches WHERE status = 'pending'")->fetchColumn();
    $kpi['potential_matches'] = $matchCnt;

    $recovered = (int)($kpi['recovered_reports'] ?? 0);
    $resolved  = (int)($kpi['resolved_reports'] ?? 0);
    $recoveryRate = ($resolved > 0) ? round(($recovered / $resolved) * 100, 1) : null;
    $kpi['recovery_rate'] = $recoveryRate;
    $kpi['recovery_rate_label'] = ($resolved > 0) ? ($recoveryRate . '%') : 'No recovery data yet';

    // 2. Chart 1 — Lost vs Found vs Recovered
    // Distribution
    $chart1 = [
        'labels' => ['Active Lost Items', 'Active Found Items', 'Successfully Recovered'],
        'data'   => [
            (int)$kpi['active_lost'],
            (int)$kpi['active_found'],
            (int)$kpi['recovered_reports']
        ]
    ];

    // 3. Chart 2 — Report Activity Over Time (Monthly trend of Lost vs Found)
    $actStmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%b %Y') AS month_label,
            DATE_FORMAT(created_at, '%Y-%m') AS month_sort,
            SUM(CASE WHEN type = 'lost' THEN 1 ELSE 0 END) AS lost_count,
            SUM(CASE WHEN type = 'found' THEN 1 ELSE 0 END) AS found_count,
            COUNT(*) AS total_count
        FROM reports
        WHERE is_deleted = 0
        GROUP BY month_sort, month_label
        ORDER BY month_sort ASC
    ");
    $activityRows = $actStmt->fetchAll(PDO::FETCH_ASSOC);

    $timeLabels = [];
    $timeLost = [];
    $timeFound = [];
    $timeTotal = [];

    foreach ($activityRows as $row) {
        $timeLabels[] = $row['month_label'];
        $timeLost[] = (int)$row['lost_count'];
        $timeFound[] = (int)$row['found_count'];
        $timeTotal[] = (int)$row['total_count'];
    }

    $chart2 = [
        'labels' => $timeLabels,
        'lost'   => $timeLost,
        'found'  => $timeFound,
        'total'  => $timeTotal
    ];

    // Real trend calculation if sufficient historical data exists (Section 21)
    $activityTrend = null;
    if (count($timeTotal) >= 2) {
        $prevTotal = $timeTotal[count($timeTotal) - 2];
        $currTotal = $timeTotal[count($timeTotal) - 1];
        if ($prevTotal > 0) {
            $activityTrend = round((($currTotal - $prevTotal) / $prevTotal) * 100, 1);
        }
    }
    $kpi['activity_trend'] = $activityTrend;

    // 4. Chart 3 — Items by Category (Horizontal Bar Chart)
    $catStmt = $pdo->query("
        SELECT 
            c.name AS category,
            COUNT(r.id) AS count
        FROM categories c
        LEFT JOIN reports r ON c.id = r.category_id AND r.is_deleted = 0
        GROUP BY c.id, c.name
        ORDER BY count DESC
    ");
    $catRows = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    $catLabels = [];
    $catCounts = [];
    foreach ($catRows as $crow) {
        $catLabels[] = $crow['category'];
        $catCounts[] = (int)$crow['count'];
    }

    $chart3 = [
        'labels' => $catLabels,
        'data'   => $catCounts
    ];

    // 5. Chart 4 — Recovery Performance Trend (Monthly Recoveries vs Reports)
    $recStmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%b %Y') AS month_label,
            DATE_FORMAT(created_at, '%Y-%m') AS month_sort,
            COUNT(*) AS total_reports,
            SUM(CASE WHEN status = 'recovered' THEN 1 ELSE 0 END) AS recovered_count
        FROM reports
        WHERE is_deleted = 0
        GROUP BY month_sort, month_label
        ORDER BY month_sort ASC
    ");
    $recRows = $recStmt->fetchAll(PDO::FETCH_ASSOC);

    $recLabels = [];
    $recReports = [];
    $recRecovered = [];
    foreach ($recRows as $rrow) {
        $recLabels[] = $rrow['month_label'];
        $recReports[] = (int)$rrow['total_reports'];
        $recRecovered[] = (int)$rrow['recovered_count'];
    }

    $chart4 = [
        'labels'    => $recLabels,
        'reports'   => $recReports,
        'recovered' => $recRecovered,
        'rate'      => $recoveryRate
    ];

    // 6. Chart 5 — Report Status Lifecycle
    $chart5 = [
        'labels' => ['Pending Verification', 'Verified Active', 'Successfully Recovered', 'Rejected Submission'],
        'data'   => [
            (int)$kpi['pending_verification'],
            (int)$kpi['verified_reports'] - (int)$kpi['recovered_reports'],
            (int)$kpi['recovered_reports'],
            (int)$kpi['rejected_reports']
        ]
    ];

    echo json_encode([
        'success' => true,
        'kpi'     => $kpi,
        'chart1'  => $chart1,
        'chart2'  => $chart2,
        'chart3'  => $chart3,
        'chart4'  => $chart4,
        'chart5'  => $chart5
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
