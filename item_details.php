<?php
/**
 * Detailed Item View & Interaction Page
 * Implements Section 47: Item Details, Mark Recovered, and Contact Request
 */
$pageTitle = "Item Details";
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/matcher.php';

$baseUrl = get_base_url();
$itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentUser = get_logged_user();

// Fetch report
$stmt = $pdo->prepare("
    SELECT r.*, c.name AS category_name, c.icon AS category_icon, 
           u.id AS reporter_uid, u.name AS reporter_name, u.email AS reporter_email, 
           u.phone AS reporter_phone, u.department AS reporter_dept, u.student_id AS reporter_sid
    FROM reports r
    JOIN categories c ON r.category_id = c.id
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ? AND r.is_deleted = 0
");
$stmt->execute([$itemId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle Non-Existent or Deleted Record
if (!$item) {
    http_response_code(404);
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="row justify-content-center py-5">
        <div class="col-md-6 text-center">
            <div class="card card-custom p-5">
                <i class="fa-solid fa-triangle-exclamation fa-3x text-warning mb-3"></i>
                <h4 class="fw-bold">Item Report Not Found</h4>
                <p class="text-muted">The requested report does not exist or has been removed from campus listings.</p>
                <div class="mt-3">
                    <a href="<?= $baseUrl ?>/index.php" class="btn btn-primary btn-sm px-4">Browse Active Items</a>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$isOwner = ($currentUser && $currentUser['id'] == $item['user_id']);
$canManage = $isOwner || is_admin();

// Fetch any potential matches associated with this item if owner or admin
$itemMatches = [];
if ($canManage) {
    $matchStmt = $pdo->prepare("
        SELECT m.*, 
               r_other.id AS other_id, r_other.title AS other_title, r_other.type AS other_type,
               r_other.location AS other_loc, r_other.item_date AS other_date
        FROM potential_matches m
        JOIN reports r_other ON (CASE WHEN m.lost_report_id = ? THEN m.found_report_id ELSE m.lost_report_id END) = r_other.id
        WHERE (m.lost_report_id = ? OR m.found_report_id = ?) AND m.status = 'pending'
        ORDER BY m.match_score DESC
    ");
    $matchStmt->execute([$item['id'], $item['id'], $item['id']]);
    $itemMatches = $matchStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row g-4 py-2">
    <!-- Main Item Details Column -->
    <div class="col-lg-8">
        <div class="card card-custom p-4 p-md-5">
            <!-- Header row -->
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <?php if ($item['type'] === 'lost'): ?>
                            <span class="badge-lost fs-6">Lost Item</span>
                        <?php else: ?>
                            <span class="badge-found fs-6">Found Item</span>
                        <?php endif; ?>

                        <span class="badge bg-secondary-subtle text-secondary">
                            <i class="fa-solid <?= e($item['category_icon']) ?> me-1"></i> <?= e($item['category_name']) ?>
                        </span>

                        <?php if ($item['status'] === 'recovered'): ?>
                            <span class="badge-recovered"><i class="fa-solid fa-check me-1"></i> Recovered</span>
                        <?php endif; ?>

                        <?php if ($item['verification_status'] === 'verified'): ?>
                            <span class="badge-verified"><i class="fa-solid fa-shield-check me-1"></i> Verified</span>
                        <?php elseif ($item['verification_status'] === 'pending'): ?>
                            <span class="badge-pending"><i class="fa-regular fa-clock me-1"></i> Pending Review</span>
                        <?php else: ?>
                            <span class="badge-rejected"><i class="fa-solid fa-ban me-1"></i> Rejected</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?= e($item['title']) ?></h3>
                    <div class="text-muted small">
                        Reported on <?= date('F d, Y', strtotime($item['created_at'])) ?> &bull; Report Reference #<?= $item['id'] ?>
                    </div>
                    <?php if ($canManage && $item['verification_status'] === 'rejected' && !empty($item['rejection_reason'])): ?>
                        <div class="alert alert-danger py-2 px-3 small mt-2 mb-0">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> <strong>Administrative Rejection Notice:</strong> <?= e($item['rejection_reason']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Button for Owner/Admin -->
                <?php if ($canManage && $item['status'] === 'active'): ?>
                    <a href="<?= $baseUrl ?>/student/my_reports.php?action=recover&id=<?= $item['id'] ?>" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm" onclick="return confirm('Confirm that this item has been successfully recovered?');">
                        <i class="fa-solid fa-check me-1"></i> Mark as Recovered
                    </a>
                <?php endif; ?>
            </div>

            <!-- Image View if available -->
            <?php if (!empty($item['image_path'])): ?>
                <div class="rounded overflow-hidden my-3 border bg-light text-center" style="max-height: 420px;">
                    <img src="<?= $baseUrl ?>/<?= e($item['image_path']) ?>" alt="<?= e($item['title']) ?>" class="img-fluid" style="max-height: 420px; object-fit: contain;">
                </div>
            <?php endif; ?>

            <!-- Item Specific Attributes -->
            <div class="row g-3 my-3 p-3 bg-light rounded border">
                <div class="col-sm-6">
                    <div class="small text-muted fw-semibold text-uppercase">Campus Location</div>
                    <div class="fw-bold text-dark">
                        <i class="fa-solid fa-location-dot text-danger me-1"></i> <?= e($item['location']) ?>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="small text-muted fw-semibold text-uppercase">Date <?= $item['type'] === 'lost' ? 'Lost' : 'Found' ?></div>
                    <div class="fw-bold text-dark">
                        <i class="fa-regular fa-calendar text-primary me-1"></i> <?= date('l, F d, Y', strtotime($item['item_date'])) ?>
                    </div>
                </div>
                <?php if ($item['status'] === 'recovered' && !empty($item['recovered_at'])): ?>
                    <div class="col-12 border-top pt-2 mt-2">
                        <div class="small text-muted fw-semibold text-uppercase">Recovery Confirmed At</div>
                        <div class="fw-bold text-success">
                            <i class="fa-solid fa-circle-check me-1"></i> <?= date('F d, Y, h:i A', strtotime($item['recovered_at'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Full Description -->
            <div class="my-3">
                <h6 class="fw-bold text-dark">Detailed Description</h6>
                <div class="text-secondary leading-relaxed p-3 bg-white border rounded">
                    <?= nl2br(e($item['description'])) ?>
                </div>
            </div>

            <!-- Potential Matches Preview if available for owner -->
            <?php if (!empty($itemMatches)): ?>
                <div class="mt-4 p-4 border border-warning rounded bg-warning-subtle">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-wand-magic-sparkles text-warning me-2"></i> Smart Match Detected For This Item!
                        </h6>
                        <a href="<?= $baseUrl ?>/student/matches.php?report_id=<?= $item['id'] ?>" class="btn btn-warning btn-sm fw-semibold">
                            Compare Matches
                        </a>
                    </div>
                    <?php foreach ($itemMatches as $im): ?>
                        <div class="p-3 bg-white rounded border mb-2 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-warning text-dark fw-bold me-2"><?= $im['match_score'] ?>% Match</span>
                                <span class="fw-semibold text-dark"><?= e($im['other_title']) ?></span>
                                <span class="text-muted small ms-2">(<?= e($im['other_loc']) ?>)</span>
                            </div>
                            <a href="<?= $baseUrl ?>/student/matches.php?match_id=<?= $im['id'] ?>" class="btn btn-sm btn-outline-primary">View Comparison</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar: Reporter & Safe Contact Card -->
    <div class="col-lg-4">
        <div class="card card-custom p-4 mb-4">
            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                <i class="fa-regular fa-address-card text-primary me-2"></i> Reporter Information
            </h6>

            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-weight: bold; font-size: 1.2rem;">
                    <?= strtoupper(substr($item['reporter_name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="fw-bold text-dark"><?= e($item['reporter_name']) ?></div>
                    <div class="text-muted small"><?= e($item['reporter_dept']) ?></div>
                    <?php if (!empty($item['reporter_sid'])): ?>
                        <div class="text-muted small">ID: <?= e($item['reporter_sid']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($canManage): ?>
                <!-- Direct Private Contact details visible to Owner or Admin -->
                <div class="p-3 bg-light rounded border small">
                    <div class="fw-bold text-dark mb-1">Administrative Contact Access:</div>
                    <div><i class="fa-regular fa-envelope me-1 text-muted"></i> <?= e($item['reporter_email']) ?></div>
                    <?php if (!empty($item['reporter_phone'])): ?>
                        <div><i class="fa-solid fa-phone me-1 text-muted"></i> <?= e($item['reporter_phone']) ?></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Privacy Protected Handover Flow -->
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fa-solid fa-shield-halved me-1"></i> Contact details are protected. Submit a verified inquiry below to request handover.
                </div>

                <?php if (is_logged_in()): ?>
                    <button type="button" class="btn btn-primary w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#contactModal">
                        <i class="fa-regular fa-envelope me-1"></i> Contact <?= $item['type'] === 'lost' ? 'Owner' : 'Finder' ?>
                    </button>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>/auth/login.php" class="btn btn-outline-primary w-100 fw-semibold">
                        Log In to Contact Reporter
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="card card-custom p-4 bg-light border">
            <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-circle-question text-secondary me-2"></i>Campus Safe Recovery Tips</h6>
            <ul class="text-muted small ps-3 mb-0">
                <li class="mb-2">Always verify physical identifying marks or purchase receipts before handing over valuable electronics.</li>
                <li class="mb-2">Conduct handovers at designated Campus Security Desks or Library Reception.</li>
                <li>Report any suspicious or fraudulent claim attempts to Security.</li>
            </ul>
        </div>
    </div>
</div>

<!-- Contact Modal -->
<?php if (is_logged_in() && !$canManage): ?>
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="contactModalLabel">
                    Contact Reporter &mdash; <?= e($item['title']) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="detailsContactForm">
                <div class="modal-body">
                    <input type="hidden" name="report_id" value="<?= $item['id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Your Message / Ownership Proof <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control" rows="4" placeholder="Hello <?= e($item['reporter_name']) ?>, I am contacting you regarding this item..." required></textarea>
                        <div class="form-text small">Describe specific features (wallpaper, engravings, contents) to authenticate your claim.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Your Email</label>
                        <input type="email" name="contact_email" class="form-control" value="<?= e($currentUser['email']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Your Contact Phone (Optional)</label>
                        <input type="text" name="contact_phone" class="form-control" value="<?= e($currentUser['phone']) ?>" placeholder="+91 98765 43210">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold" id="detailsSendBtn">
                        <i class="fa-solid fa-paper-plane me-1"></i> Send Contact Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('detailsContactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('detailsSendBtn');
    btn.disabled = true;
    btn.innerHTML = 'Sending...';

    const formData = new FormData(this);
    fetch('<?= $baseUrl ?>/api/submit_claim.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Send Contact Request';
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('contactModal')).hide();
            showToast('Request Sent', data.message, 'success');
        } else {
            alert(data.message || 'Error sending request');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = 'Send Contact Request';
        alert('Network error occurred.');
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
