<?php
/**
 * Smart Potential Matches Comparison Page
 * Implements Section 54 & 55 Specification
 */
$pageTitle = "Smart Matches";
require_once __DIR__ . '/../includes/auth_guard.php';
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/matcher.php';

$userId = $_SESSION['user_id'];
$baseUrl = get_base_url();

$reportFilterId = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;

// Re-scan trigger if requested
if (isset($_GET['rescan']) && $reportFilterId > 0) {
    $foundNew = run_matching_engine_for_report($pdo, $reportFilterId);
    set_flash('info', "Smart matching scan complete. Found {$foundNew} new candidate(s).");
    header("Location: $baseUrl/student/matches.php?report_id=" . $reportFilterId);
    exit;
}

// Fetch matches
$query = "
    SELECT m.*,
           r_lost.id AS lost_id, r_lost.title AS lost_title, r_lost.description AS lost_desc, 
           r_lost.location AS lost_loc, r_lost.item_date AS lost_date, r_lost.user_id AS lost_uid,
           r_lost.image_path AS lost_img, u_lost.name AS lost_user_name,
           r_found.id AS found_id, r_found.title AS found_title, r_found.description AS found_desc, 
           r_found.location AS found_loc, r_found.item_date AS found_date, r_found.user_id AS found_uid,
           r_found.image_path AS found_img, u_found.name AS found_user_name,
           c.name AS category_name
    FROM potential_matches m
    JOIN reports r_lost ON m.lost_report_id = r_lost.id
    JOIN reports r_found ON m.found_report_id = r_found.id
    JOIN categories c ON r_lost.category_id = c.id
    JOIN users u_lost ON r_lost.user_id = u_lost.id
    JOIN users u_found ON r_found.user_id = u_found.id
    WHERE (r_lost.user_id = :uid OR r_found.user_id = :uid)
      AND m.status = 'pending'
      AND r_lost.is_deleted = 0
      AND r_found.is_deleted = 0
";

if ($reportFilterId > 0) {
    $query .= " AND (r_lost.id = :rid OR r_found.id = :rid)";
    $stmt = $pdo->prepare($query . " ORDER BY m.match_score DESC");
    $stmt->execute(['uid' => $userId, 'rid' => $reportFilterId]);
} else {
    $stmt = $pdo->prepare($query . " ORDER BY m.match_score DESC");
    $stmt->execute(['uid' => $userId]);
}

$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="fa-solid fa-wand-magic-sparkles text-warning me-2"></i>Smart Match Detection</h3>
        <p class="text-muted small mb-0">Algorithmic similarity analysis across Category (30%), Name (25%), Description (20%), Location (15%), and Date (10%).</p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($reportFilterId > 0): ?>
            <a href="matches.php?report_id=<?= $reportFilterId ?>&rescan=1" class="btn btn-outline-primary btn-sm fw-semibold">
                <i class="fa-solid fa-arrows-rotate me-1"></i> Re-Scan Item
            </a>
            <a href="matches.php" class="btn btn-outline-secondary btn-sm">View All Matches</a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($matches)): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <h5 class="empty-state-title">No potential matches found yet</h5>
        <p class="empty-state-text">Our Smart Match engine continuously analyzes newly submitted lost and found reports. As soon as another student submits a similar item, it will appear here.</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="<?= $baseUrl ?>/student/my_reports.php" class="btn btn-outline-primary btn-sm">View My Reports</a>
            <a href="<?= $baseUrl ?>/index.php" class="btn btn-primary btn-sm">Browse All Items</a>
        </div>
    </div>
<?php else: ?>

    <div class="row g-4">
        <?php foreach ($matches as $match): ?>
            <?php
                $isLostOwner = ($match['lost_uid'] == $userId);
                $myReport = $isLostOwner ? [
                    'id'    => $match['lost_id'],
                    'title' => $match['lost_title'],
                    'desc'  => $match['lost_desc'],
                    'loc'   => $match['lost_loc'],
                    'date'  => $match['lost_date'],
                    'img'   => $match['lost_img'],
                    'user'  => $match['lost_user_name'],
                    'role'  => 'YOUR LOST ITEM'
                ] : [
                    'id'    => $match['found_id'],
                    'title' => $match['found_title'],
                    'desc'  => $match['found_desc'],
                    'loc'   => $match['found_loc'],
                    'date'  => $match['found_date'],
                    'img'   => $match['found_img'],
                    'user'  => $match['found_user_name'],
                    'role'  => 'YOUR FOUND ITEM'
                ];

                $otherReport = $isLostOwner ? [
                    'id'    => $match['found_id'],
                    'title' => $match['found_title'],
                    'desc'  => $match['found_desc'],
                    'loc'   => $match['found_loc'],
                    'date'  => $match['found_date'],
                    'img'   => $match['found_img'],
                    'user'  => $match['found_user_name'],
                    'role'  => 'POTENTIAL FOUND ITEM'
                ] : [
                    'id'    => $match['lost_id'],
                    'title' => $match['lost_title'],
                    'desc'  => $match['lost_desc'],
                    'loc'   => $match['lost_loc'],
                    'date'  => $match['lost_date'],
                    'img'   => $match['lost_img'],
                    'user'  => $match['lost_user_name'],
                    'role'  => 'POTENTIAL LOST ITEM'
                ];

                $factors = json_decode($match['factors_json'], true) ?: [];
            ?>

            <div class="col-12" id="match-card-<?= $match['id'] ?>">
                <div class="match-comparison-box">
                    <div class="match-header">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary-subtle text-secondary small fw-semibold">Category: <?= e($match['category_name']) ?></span>
                            <span class="text-muted small">&bull; Detected <?= time_ago($match['created_at']) ?></span>
                        </div>
                        <?php
                            $scoreVal = (int)$match['match_score'];
                            $confBadge = ($scoreVal >= 80) ? 'bg-success text-white' : (($scoreVal >= 60) ? 'bg-warning text-dark' : 'bg-secondary text-white');
                            $confLabel = ($scoreVal >= 80) ? 'Strong Potential Match' : (($scoreVal >= 60) ? 'Possible Match' : 'Potential Match');
                        ?>
                        <div class="match-score-badge d-flex align-items-center gap-2">
                            <span class="badge <?= $confBadge ?> fw-bold px-2 py-1" style="font-size: 0.75rem;"><?= $confLabel ?></span>
                            <span><i class="fa-solid fa-gauge-high me-1 text-success"></i> <?= $scoreVal ?>%</span>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="row align-items-center g-4">
                            <!-- Left Item: Your Item -->
                            <div class="col-lg-4">
                                <div class="p-3 border rounded bg-light h-100">
                                    <div class="badge bg-dark-subtle text-dark small fw-bold mb-2"><?= $myReport['role'] ?></div>
                                    <h5 class="fw-bold text-dark mb-1"><?= e($myReport['title']) ?></h5>
                                    
                                    <div class="small text-muted mb-2">
                                        <i class="fa-solid fa-location-dot me-1 text-danger"></i> <?= e($myReport['loc']) ?>
                                    </div>
                                    <div class="small text-muted mb-2">
                                        <i class="fa-regular fa-calendar me-1"></i> <?= date('d M Y', strtotime($myReport['date'])) ?>
                                    </div>
                                    <p class="small text-secondary mb-3" style="min-height: 48px;">
                                        <?= e(mb_strimwidth($myReport['desc'], 0, 110, '...')) ?>
                                    </p>
                                    <a href="<?= $baseUrl ?>/item_details.php?id=<?= $myReport['id'] ?>" class="btn btn-outline-secondary btn-sm w-100">
                                        View Your Item Details
                                    </a>
                                </div>
                            </div>

                            <!-- Center: VS & Factors Breakdown -->
                            <div class="col-lg-4">
                                <div class="text-center mb-3">
                                    <span class="badge bg-secondary px-3 py-2 fs-6 fw-bold">VS</span>
                                </div>

                                <div class="bg-white border rounded p-3">
                                    <div class="small fw-bold text-uppercase text-secondary mb-2 text-center">Matching Factors Analysis</div>
                                    
                                    <div class="match-factor-row">
                                        <span class="small fw-semibold">Category (30%)</span>
                                        <span class="badge bg-success-subtle text-success fw-bold">
                                            <?= $factors['category']['symbol'] ?? '✓' ?> <?= $factors['category']['score'] ?? 30 ?>/30
                                        </span>
                                    </div>

                                    <div class="match-factor-row">
                                        <span class="small fw-semibold">Item Name (25%)</span>
                                        <span class="badge <?= ($factors['name']['score'] ?? 0) >= 15 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?> fw-bold">
                                            <?= $factors['name']['symbol'] ?? '✓' ?> <?= $factors['name']['score'] ?? 20 ?>/25
                                        </span>
                                    </div>

                                    <div class="match-factor-row">
                                        <span class="small fw-semibold">Location (15%)</span>
                                        <span class="badge <?= ($factors['location']['score'] ?? 0) >= 10 ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?> fw-bold">
                                            <?= $factors['location']['symbol'] ?? '✓' ?> <?= $factors['location']['score'] ?? 12 ?>/15
                                        </span>
                                    </div>

                                    <div class="match-factor-row">
                                        <span class="small fw-semibold">Date Proximity (10%)</span>
                                        <span class="badge bg-success-subtle text-success fw-bold">
                                            <?= $factors['date']['symbol'] ?? '✓' ?> <?= $factors['date']['score'] ?? 10 ?>/10
                                        </span>
                                    </div>

                                    <div class="match-factor-row">
                                        <span class="small fw-semibold">Description (20%)</span>
                                        <span class="badge <?= ($factors['description']['score'] ?? 0) >= 10 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> fw-bold">
                                            <?= $factors['description']['symbol'] ?? '~' ?> <?= $factors['description']['score'] ?? 12 ?>/20
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Item: Matched Item -->
                            <div class="col-lg-4">
                                <div class="p-3 border rounded bg-light h-100">
                                    <div class="badge bg-primary-subtle text-primary small fw-bold mb-2"><?= $otherReport['role'] ?></div>
                                    <h5 class="fw-bold text-dark mb-1"><?= e($otherReport['title']) ?></h5>
                                    
                                    <div class="small text-muted mb-2">
                                        <i class="fa-solid fa-location-dot me-1 text-danger"></i> <?= e($otherReport['loc']) ?>
                                    </div>
                                    <div class="small text-muted mb-2">
                                        <i class="fa-regular fa-calendar me-1"></i> <?= date('d M Y', strtotime($otherReport['date'])) ?>
                                    </div>
                                    <p class="small text-secondary mb-3" style="min-height: 48px;">
                                        <?= e(mb_strimwidth($otherReport['desc'], 0, 110, '...')) ?>
                                    </p>
                                    <a href="<?= $baseUrl ?>/item_details.php?id=<?= $otherReport['id'] ?>" class="btn btn-outline-primary btn-sm w-100">
                                        View Found Item
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons Bar as per Section 55 -->
                        <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-4 pt-3 border-top">
                            <a href="<?= $baseUrl ?>/item_details.php?id=<?= $otherReport['id'] ?>" class="btn btn-outline-primary btn-sm px-3 fw-semibold">
                                <i class="fa-regular fa-eye me-1"></i> View Item
                            </a>

                            <button type="button" class="btn btn-success btn-sm px-3 fw-semibold" onclick="openContactModal(<?= $otherReport['id'] ?>, '<?= e(addslashes($otherReport['title'])) ?>')">
                                <i class="fa-regular fa-envelope me-1"></i> Contact <?= $isLostOwner ? 'Finder' : 'Owner' ?>
                            </button>

                            <button type="button" class="btn btn-outline-danger btn-sm px-3 fw-semibold" onclick="dismissMatch(<?= $match['id'] ?>)">
                                <i class="fa-solid fa-xmark me-1"></i> Not a Match
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<!-- Contact / Claim Modal -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="contactModalLabel">Send Contact Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="contactForm">
                <div class="modal-body">
                    <input type="hidden" name="report_id" id="modalReportId">
                    <p class="small text-muted mb-3" id="modalItemTitle"></p>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Your Message / Verification Details <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control" rows="3" placeholder="Hello! I believe this is my item / I found your item. Can we coordinate safe handover?" required></textarea>
                        <div class="form-text small">Explain identifying features (e.g. stickers, serial code) to establish ownership.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Your Contact Email</label>
                        <input type="email" name="contact_email" class="form-control" value="<?= e($_SESSION['user_email']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Your Contact Phone (Optional)</label>
                        <input type="text" name="contact_phone" class="form-control" value="<?= e($_SESSION['user_phone'] ?? '') ?>" placeholder="+91 98765 43210">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold" id="sendClaimBtn">
                        <i class="fa-solid fa-paper-plane me-1"></i> Send Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openContactModal(reportId, reportTitle) {
    document.getElementById('modalReportId').value = reportId;
    document.getElementById('modalItemTitle').innerHTML = 'Sending inquiry regarding: <strong>' + reportTitle + '</strong>';
    const modal = new bootstrap.Modal(document.getElementById('contactModal'));
    modal.show();
}

// Contact Form AJAX Submit
document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('sendClaimBtn');
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
        btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i> Send Request';
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('contactModal')).hide();
            showToast('Request Sent', data.message, 'success');
        } else {
            alert(data.message || 'Error sending request');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = 'Send Request';
        alert('Network error occurred.');
    });
});

// Dismiss match ("Not a Match")
function dismissMatch(matchId) {
    if (!confirm('Mark this comparison as "Not a Match"? It will be removed from your active matches list.')) {
        return;
    }
    const formData = new FormData();
    formData.append('match_id', matchId);
    formData.append('action', 'dismiss');

    fetch('<?= $baseUrl ?>/api/respond_match.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('match-card-' + matchId);
            if (card) {
                card.style.transition = 'opacity 0.3s ease';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
            }
            showToast('Match Dismissed', 'This comparison has been dismissed.', 'info');
        } else {
            alert(data.message || 'Error dismissing match');
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
