<?php
/**
 * User Profile Controller & View
 */
$pageTitle = "My Profile";
require_once __DIR__ . '/../includes/auth_guard.php';
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$userId = $_SESSION['user_id'];
$baseUrl = get_base_url();

$error = '';
$success = '';

// Handle Profile Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    if ($_POST['action_type'] === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $studentId = trim($_POST['student_id'] ?? '');
        $department = trim($_POST['department'] ?? '');

        if (empty($name)) {
            $error = 'Name cannot be left empty.';
        } else {
            try {
                $up = $pdo->prepare("UPDATE users SET name = ?, phone = ?, student_id = ?, department = ? WHERE id = ?");
                $up->execute([$name, $phone, $studentId, $department, $userId]);

                // Update session
                $_SESSION['user_name']  = $name;
                $_SESSION['user_phone'] = $phone;
                $_SESSION['student_id'] = $studentId;
                $_SESSION['department'] = $department;

                set_flash('success', 'Profile details updated successfully.');
                header("Location: $baseUrl/student/profile.php");
                exit;
            } catch (Exception $e) {
                error_log("Profile update error: " . $e->getMessage());
                $error = 'Unable to save profile changes right now.';
            }
        }
    } elseif ($_POST['action_type'] === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            $error = 'Please provide both your current and new passwords.';
        } elseif (strlen($newPass) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New password confirmation does not match.';
        } else {
            // Check current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $currentHash = $stmt->fetchColumn();

            if (!password_verify($currentPass, $currentHash)) {
                $error = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $up->execute([$newHash, $userId]);

                set_flash('success', 'Password updated successfully.');
                header("Location: $baseUrl/student/profile.php");
                exit;
            }
        }
    }
}

// Fetch actual logged in user data directly from DB
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center py-3">
    <div class="col-lg-8">
        <div class="card card-custom p-4 mb-4">
            <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-4">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; font-size: 1.5rem; font-weight: bold;">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div>
                    <h4 class="fw-bold mb-0 text-dark"><?= e($user['name']) ?></h4>
                    <span class="badge bg-secondary-subtle text-secondary"><?= ucfirst(e($user['role'])) ?> Account</span>
                    <span class="text-muted small ms-2">Member since <?= date('M Y', strtotime($user['created_at'])) ?></span>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-1"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <!-- Edit Details Form -->
            <form method="POST" action="profile.php" class="mb-4" data-loading-indicator>
                <input type="hidden" name="action_type" value="update_profile">
                <h5 class="fw-bold text-dark mb-3">Personal &amp; Campus Details</h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Campus Email (Read Only)</label>
                        <input type="email" class="form-control bg-light" value="<?= e($user['email']) ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Student / Employee ID</label>
                        <input type="text" name="student_id" class="form-control" value="<?= e($user['student_id'] ?? '') ?>" placeholder="e.g. CS2024-042">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Department</label>
                        <input type="text" name="department" class="form-control" value="<?= e($user['department'] ?? '') ?>" placeholder="e.g. Computer Science & Engineering">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Contact Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="+91 98765 43210">
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>

            <hr class="my-4">

            <!-- Change Password Form -->
            <form method="POST" action="profile.php" data-loading-indicator>
                <input type="hidden" name="action_type" value="change_password">
                <h5 class="fw-bold text-dark mb-3">Security &amp; Password</h5>

                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Current Password</label>
                        <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-outline-dark btn-sm px-4 fw-semibold">
                            <i class="fa-solid fa-key me-1"></i> Update Password
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
