<?php
/**
 * Administrator Users Directory
 */
$pageTitle = "Users Directory";
require_once __DIR__ . '/../includes/auth_guard.php';
require_admin_role();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$baseUrl = get_base_url();

$usersStmt = $pdo->query("
    SELECT u.*,
           COUNT(r.id) AS total_reports,
           SUM(CASE WHEN r.status = 'recovered' THEN 1 ELSE 0 END) AS recovered_count
    FROM users u
    LEFT JOIN reports r ON u.id = r.user_id AND r.is_deleted = 0
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="fa-solid fa-users text-primary me-2"></i>Campus Users Directory</h3>
        <p class="text-muted small mb-0">Registered students and administrative accounts across academic departments.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Admin Dashboard
        </a>
    </div>
</div>

<div class="card card-custom p-0 overflow-hidden shadow-sm">
    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Student / Employee ID</th>
                    <th>Department</th>
                    <th>Phone</th>
                    <th>Total Reports</th>
                    <th>Recovered Items</th>
                    <th class="text-end">Registered</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-primary' ?> text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: bold; font-size: 0.8rem;">
                                    <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark"><?= e($u['name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?= e($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge bg-danger-subtle text-danger fw-bold">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-primary-subtle text-primary fw-bold">Student</span>
                            <?php endif; ?>
                        </td>
                        <td class="small fw-semibold"><?= e($u['student_id'] ?: '—') ?></td>
                        <td class="small"><?= e($u['department'] ?: 'Campus General') ?></td>
                        <td class="small text-muted"><?= e($u['phone'] ?: '—') ?></td>
                        <td class="small fw-semibold"><?= (int)$u['total_reports'] ?></td>
                        <td class="small text-success fw-semibold"><?= (int)$u['recovered_count'] ?></td>
                        <td class="text-end text-muted small"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
