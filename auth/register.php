<?php
/**
 * Student Registration Controller & View
 */
$pageTitle = "Register";
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$baseUrl = get_base_url();

if (is_logged_in()) {
    header("Location: $baseUrl/student/dashboard.php");
    exit;
}

$error = '';
$formData = [
    'name'       => '',
    'email'      => '',
    'student_id' => '',
    'department' => '',
    'phone'      => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['name']       = trim($_POST['name'] ?? '');
    $formData['email']      = trim($_POST['email'] ?? '');
    $formData['student_id'] = trim($_POST['student_id'] ?? '');
    $formData['department'] = trim($_POST['department'] ?? '');
    $formData['phone']      = trim($_POST['phone'] ?? '');
    $password               = $_POST['password'] ?? '';
    $confirmPassword        = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($formData['name']) || empty($formData['email']) || empty($password)) {
        $error = 'Please fill in all required fields (Name, Email, Password).';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters in length.';
    } elseif ($password !== $confirmPassword) {
        $error = 'The passwords do not match.';
    } else {
        try {
            // Check duplicate email
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$formData['email']]);
            if ($checkStmt->fetch()) {
                $error = 'An account with this email address already exists. Please sign in instead.';
            } else {
                // Hash password securely
                $hashed = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, student_id, department, phone, created_at)
                    VALUES (?, ?, ?, 'student', ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $formData['name'],
                    $formData['email'],
                    $hashed,
                    $formData['student_id'],
                    $formData['department'],
                    $formData['phone']
                ]);

                $newUserId = $pdo->lastInsertId();

                // Regenerate session and log in
                session_regenerate_id(true);
                $_SESSION['user_id']    = $newUserId;
                $_SESSION['user_name']  = $formData['name'];
                $_SESSION['user_email'] = $formData['email'];
                $_SESSION['user_role']  = 'student';
                $_SESSION['student_id'] = $formData['student_id'];
                $_SESSION['department'] = $formData['department'];
                $_SESSION['user_phone'] = $formData['phone'];

                // Create initial welcome notification
                create_notification(
                    $pdo,
                    $newUserId,
                    'Welcome to Campus Lost & Found',
                    'Your account has been registered. You can now report lost or found items and track smart matches.',
                    'system',
                    $baseUrl . '/student/dashboard.php'
                );

                set_flash('success', 'Registration successful! Welcome to Campus Lost & Found.');
                header("Location: $baseUrl/student/dashboard.php");
                exit;
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'Unable to complete registration right now. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-8 col-lg-6">
        <div class="card card-custom p-4">
            <div class="text-center mb-4">
                <div class="brand-icon mx-auto mb-2" style="width: 48px; height: 48px; font-size: 1.5rem;">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <h4 class="fw-bold text-dark">Create Student Account</h4>
                <p class="text-muted small">Join the campus Lost &amp; Found community</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-1"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" data-loading-indicator>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Jordan Lee" value="<?= e($formData['name']) ?>" required>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Campus Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. jordan@campus.edu" value="<?= e($formData['email']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Student ID / Roll No</label>
                        <input type="text" name="student_id" class="form-control" placeholder="e.g. CS2025-104" value="<?= e($formData['student_id']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Department</label>
                        <input type="text" name="department" class="form-control" placeholder="e.g. Computer Science" value="<?= e($formData['department']) ?>">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Contact Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="+91 98765 00000" value="<?= e($formData['phone']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="fa-solid fa-check me-1"></i> Complete Registration
                        </button>
                    </div>
                </div>
            </form>

            <div class="border-top pt-3 mt-4 text-center small text-muted">
                Already registered? 
                <a href="<?= $baseUrl ?>/auth/login.php" class="text-primary fw-semibold text-decoration-none">Sign in here</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
