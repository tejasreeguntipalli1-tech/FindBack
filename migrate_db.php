<?php
require_once __DIR__ . '/config/db.php';

try {
    // 1. Add related_report_id to notifications
    $cols = $pdo->query("SHOW COLUMNS FROM notifications LIKE 'related_report_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE notifications ADD COLUMN related_report_id INT DEFAULT NULL AFTER link, ADD INDEX (related_report_id)");
        echo "Added related_report_id to notifications.\n";
    } else {
        echo "notifications.related_report_id already exists.\n";
    }

    // 2. Add recovered_by to reports
    $repCols = $pdo->query("SHOW COLUMNS FROM reports LIKE 'recovered_by'")->fetchAll();
    if (empty($repCols)) {
        $pdo->exec("ALTER TABLE reports ADD COLUMN recovered_by INT DEFAULT NULL AFTER recovered_at");
        echo "Added recovered_by to reports.\n";
    } else {
        echo "reports.recovered_by already exists.\n";
    }

    // 3. Add notification_sent to potential_matches
    $matCols = $pdo->query("SHOW COLUMNS FROM potential_matches LIKE 'notification_sent'")->fetchAll();
    if (empty($matCols)) {
        $pdo->exec("ALTER TABLE potential_matches ADD COLUMN notification_sent TINYINT(1) DEFAULT 0 AFTER status");
        echo "Added notification_sent to potential_matches.\n";
    } else {
        echo "potential_matches.notification_sent already exists.\n";
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}
