<?php
/**
 * Login Controller & View
 */
$pageTitle = "Login";
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$baseUrl = get_base_url();

if (is_logged_in()) {
    if (is_admin()) {
        header("Location: $baseUrl/admin/dashboard.php");
    } else {
        header("Location: $baseUrl/student/dashboard.php");
    }
    exit;
}

$error = '';
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $emailInput = $email;

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID for security
                session_regenerate_id(true);

                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['student_id'] = $user['student_id'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['user_phone'] = $user['phone'];

                set_flash('success', "Welcome back, " . $user['name'] . "!");

                if ($user['role'] === 'admin') {
                    header("Location: $baseUrl/admin/dashboard.php");
                } else {
                    header("Location: $baseUrl/student/dashboard.php");
                }
                exit;
            } else {
                $error = 'Invalid email address or password. Please check your credentials.';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'A database error occurred. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-6 col-lg-5 col-xl-4">
        <div class="card card-custom p-4">
            <div class="text-center mb-4">
                <div class="brand-icon mx-auto mb-2" style="width: 48px; height: 48px; font-size: 1.5rem;">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h4 class="fw-bold text-dark">Sign In</h4>
                <p class="text-muted small">Access your campus Lost &amp; Found account</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-1"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" data-loading-indicator>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Campus Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-regular fa-envelope"></i></span>
                        <input type="email" name="email" id="loginEmail" class="form-control" placeholder="e.g. alex@campus.edu" value="<?= e($emailInput) ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label small fw-semibold">Password</label>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-key"></i></span>
                        <input type="password" name="password" id="loginPassword" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
                    <i class="fa-solid fa-right-to-bracket me-1"></i> Sign In
                </button>
            </form>

            <div class="border-top pt-3 mt-2 text-center small text-muted">
                Don't have an account yet? 
                <a href="<?= $baseUrl ?>/auth/register.php" class="text-primary fw-semibold text-decoration-none">Register here</a>
            </div>

            <!-- Demo Quick Fill for Live Demonstration -->
            <div class="bg-light border rounded p-3 mt-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold small text-secondary"><i class="fa-solid fa-bolt text-warning me-1"></i> Demo Accounts</span>
                    <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;">Quick Fill</span>
                </div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-sm btn-outline-danger text-start py-1 px-2" onclick="fillDemo('admin@campus.edu', 'Admin@123')">
                        <span class="fw-bold">Admin:</span> admin@campus.edu (Admin@123)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary text-start py-1 px-2" onclick="fillDemo('alex@campus.edu', 'Student@123')">
                        <span class="fw-bold">Student Alex:</span> alex@campus.edu (Student@123)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success text-start py-1 px-2" onclick="fillDemo('sarah@campus.edu', 'Student@123')">
                        <span class="fw-bold">Student Sarah:</span> sarah@campus.edu (Student@123)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillDemo(email, pass) {
    document.getElementById('loginEmail').value = email;
    document.getElementById('loginPassword').value = pass;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
