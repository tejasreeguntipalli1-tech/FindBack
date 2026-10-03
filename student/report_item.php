<?php
/**
 * Report Lost or Found Item Controller & View
 */
$pageTitle = "Submit Report";
require_once __DIR__ . '/../includes/auth_guard.php';
require_auth();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/matcher.php';

$baseUrl = get_base_url();
$userId = $_SESSION['user_id'];

// Default type from query or form
$reportType = isset($_GET['type']) && in_array($_GET['type'], ['lost', 'found']) ? $_GET['type'] : 'lost';

// Fetch categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$error = '';
$formData = [
    'type'        => $reportType,
    'category_id' => '',
    'title'       => '',
    'description' => '',
    'location'    => '',
    'item_date'   => date('Y-m-d'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['type']        = in_array($_POST['type'] ?? '', ['lost', 'found']) ? $_POST['type'] : 'lost';
    $formData['category_id'] = (int)($_POST['category_id'] ?? 0);
    $formData['title']       = trim($_POST['title'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['location']    = trim($_POST['location'] ?? '');
    $formData['item_date']   = trim($_POST['item_date'] ?? date('Y-m-d'));

    // Validation
    if (empty($formData['title']) || empty($formData['description']) || empty($formData['location']) || empty($formData['category_id'])) {
        $error = 'Please fill in all required fields (Item Name, Category, Description, Location).';
    } elseif (strlen($formData['title']) < 3) {
        $error = 'Item title must be at least 3 characters.';
    } elseif (strlen($formData['description']) < 10) {
        $error = 'Please provide a more descriptive summary (at least 10 characters) to help identify the item.';
    } else {
        // Safe Image Upload Processing
        $imagePath = null;
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['item_image'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowedMimes)) {
                $error = 'Only JPG, PNG, and WebP images are permitted.';
            } elseif ($file['size'] > $maxSize) {
                $error = 'Image file size must not exceed 5MB.';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (!$ext) $ext = 'jpg';
                $newFilename = 'item_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $destination = $uploadDir . $newFilename;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $imagePath = 'uploads/' . $newFilename;
                } else {
                    $error = 'Failed to safely upload the item image. Please try again.';
                }
            }
        }

        $duplicateWarning = null;
        $confirmedDuplicate = isset($_POST['confirm_duplicate']) && $_POST['confirm_duplicate'] == '1';

        // Section 8: Duplicate report check before final insertion
        if (empty($error) && !$confirmedDuplicate) {
            $dupStmt = $pdo->prepare("
                SELECT id, title, location, item_date 
                FROM reports 
                WHERE type = ? AND category_id = ? AND status = 'active' AND is_deleted = 0
                ORDER BY created_at DESC 
                LIMIT 25
            ");
            $dupStmt->execute([$formData['type'], $formData['category_id']]);
            $candidates = $dupStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($candidates as $cand) {
                $nameSim = calculate_name_similarity($formData['title'], $cand['title']);
                $locSim = calculate_location_similarity($formData['location'], $cand['location']);

                if (($nameSim['score'] >= 18 && $locSim['score'] >= 9) || $nameSim['score'] >= 22) {
                    $duplicateWarning = $cand;
                    break;
                }
            }
        }

        if (empty($error) && !$duplicateWarning) {
            try {
                // Insert into reports table
                $stmt = $pdo->prepare("
                    INSERT INTO reports (
                        user_id, type, category_id, title, description, location, 
                        item_date, image_path, status, verification_status, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'pending', NOW()
                    )
                ");
                $stmt->execute([
                    $userId,
                    $formData['type'],
                    $formData['category_id'],
                    $formData['title'],
                    $formData['description'],
                    $formData['location'],
                    $formData['item_date'],
                    $imagePath
                ]);

                $newReportId = $pdo->lastInsertId();

                // Trigger Smart Matching Engine immediately
                $matchCount = run_matching_engine_for_report($pdo, $newReportId);

                // Create user feedback notification
                create_notification(
                    $pdo,
                    $userId,
                    ucfirst($formData['type']) . " Report Created",
                    "Your report for '{$formData['title']}' has been created and is pending administrative verification.",
                    'system',
                    $baseUrl . "/item_details.php?id=" . $newReportId
                );

                if ($matchCount > 0) {
                    set_flash('success', "Report submitted! We found {$matchCount} potential smart match(es) for your item!");
                    header("Location: $baseUrl/student/matches.php?report_id=" . $newReportId);
                } else {
                    set_flash('success', "Report submitted successfully! It is currently pending administrative verification.");
                    header("Location: $baseUrl/student/my_reports.php");
                }
                exit;

            } catch (Exception $e) {
                error_log("Report insert error: " . $e->getMessage());
                $error = "Unable to save your report. Please check the provided information.";
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center py-3">
    <div class="col-lg-8">
        <div class="card card-custom p-4 p-md-5">
            <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                <div class="brand-icon" style="background: <?= $reportType === 'lost' ? 'linear-gradient(135deg, #e11d48, #f43f5e)' : 'linear-gradient(135deg, #0d9488, #14b8a6)' ?>;">
                    <i class="fa-solid <?= $reportType === 'lost' ? 'fa-circle-exclamation' : 'fa-hand-holding-heart' ?>"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0">Report <?= $reportType === 'lost' ? 'Lost Belonging' : 'Found Belonging' ?></h4>
                    <p class="text-muted small mb-0">Fill in the accurate details below so our smart matching engine can find candidates.</p>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-1"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($duplicateWarning)): ?>
                <!-- Section 8: Duplicate Warning Alert -->
                <div class="alert alert-warning border border-warning shadow-sm p-3 mb-4">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-warning mt-1 fs-5"></i>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1 text-dark">Similar Report Already Exists</h6>
                            <p class="small text-dark mb-2">
                                A similar <strong><?= e($duplicateWarning['title']) ?></strong> report was recently submitted in 
                                <strong><?= e($duplicateWarning['location']) ?></strong> on <?= date('M d, Y', strtotime($duplicateWarning['item_date'])) ?>.
                            </p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?= $baseUrl ?>/item_details.php?id=<?= $duplicateWarning['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm fw-semibold">
                                    <i class="fa-regular fa-eye me-1"></i> View Existing Report
                                </a>
                                <form method="POST" action="report_item.php?type=<?= e($formData['type']) ?>" class="d-inline">
                                    <input type="hidden" name="type" value="<?= e($formData['type']) ?>">
                                    <input type="hidden" name="category_id" value="<?= e($formData['category_id']) ?>">
                                    <input type="hidden" name="title" value="<?= e($formData['title']) ?>">
                                    <input type="hidden" name="description" value="<?= e($formData['description']) ?>">
                                    <input type="hidden" name="location" value="<?= e($formData['location']) ?>">
                                    <input type="hidden" name="item_date" value="<?= e($formData['item_date']) ?>">
                                    <input type="hidden" name="confirm_duplicate" value="1">
                                    <button type="submit" class="btn btn-warning btn-sm fw-semibold">
                                        <i class="fa-solid fa-arrow-right me-1"></i> Continue &amp; Submit Anyway
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="report_item.php?type=<?= e($formData['type']) ?>" enctype="multipart/form-data" data-loading-indicator id="reportItemForm">
                
                <!-- Report Type Selector -->
                <div class="mb-4">
                    <label class="form-label small fw-semibold text-secondary">Report Type</label>
                    <div class="d-flex gap-3">
                        <div class="form-check border rounded p-2 px-3 flex-fill <?= $formData['type'] === 'lost' ? 'border-danger bg-danger-subtle' : '' ?>">
                            <input class="form-check-input" type="radio" name="type" id="typeLost" value="lost" <?= $formData['type'] === 'lost' ? 'checked' : '' ?> onchange="window.location.href='report_item.php?type=lost'">
                            <label class="form-check-label fw-bold text-danger" for="typeLost">
                                <i class="fa-solid fa-circle-exclamation me-1"></i> I LOST an Item
                            </label>
                        </div>
                        <div class="form-check border rounded p-2 px-3 flex-fill <?= $formData['type'] === 'found' ? 'border-success bg-success-subtle' : '' ?>">
                            <input class="form-check-input" type="radio" name="type" id="typeFound" value="found" <?= $formData['type'] === 'found' ? 'checked' : '' ?> onchange="window.location.href='report_item.php?type=found'">
                            <label class="form-check-label fw-bold text-success" for="typeFound">
                                <i class="fa-solid fa-hand-holding-heart me-1"></i> I FOUND an Item
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Item Title / Name <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="itemTitleInput" class="form-control" placeholder="e.g. Casio fx-991EX Scientific Calculator" value="<?= e($formData['title']) ?>" required>
                        <div id="duplicateHintBox" class="d-none mt-2 alert alert-warning py-1 px-2 small mb-0"></div>
                        <div class="form-text small">Be specific (brand, model, color) to assist similarity matching.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $formData['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Campus Location <span class="text-danger">*</span></label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. CSE Block Room 204, Library 1st Floor, Cafeteria" value="<?= e($formData['location']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Date <?= $formData['type'] === 'lost' ? 'Lost' : 'Found' ?> <span class="text-danger">*</span></label>
                        <input type="date" name="item_date" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= e($formData['item_date']) ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Detailed Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Describe unique markings, scratches, stickers, contents, or circumstances..." required><?= e($formData['description']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Attach Photo (Optional)</label>
                        <input type="file" name="item_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text small">Accepted formats: JPG, PNG, WebP (Max 5MB).</div>
                    </div>

                    <div class="col-12 mt-4 pt-2 border-top d-flex justify-content-between align-items-center">
                        <a href="<?= $baseUrl ?>/student/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn <?= $formData['type'] === 'lost' ? 'btn-danger' : 'btn-success' ?> px-4 fw-semibold">
                            <i class="fa-solid fa-paper-plane me-1"></i> Submit <?= ucfirst($formData['type']) ?> Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<script>
let dupTimeout = null;
const titleInput = document.getElementById('itemTitleInput');
const catSelect = document.querySelector('select[name="category_id"]');
const locInput = document.querySelector('input[name="location"]');
const hintBox = document.getElementById('duplicateHintBox');

function checkDuplicateAsync() {
    if (!titleInput || !catSelect || !hintBox) return;
    const title = titleInput.value.trim();
    const cat = catSelect.value;
    const loc = locInput ? locInput.value.trim() : '';
    const type = '<?= e($formData['type']) ?>';

    if (title.length < 3 || !cat) {
        hintBox.classList.add('d-none');
        return;
    }

    fetch(`${window.APP_BASE_URL}/api/check_duplicate.php?type=${type}&category_id=${cat}&title=${encodeURIComponent(title)}&location=${encodeURIComponent(loc)}`)
        .then(r => r.json())
        .then(data => {
            if (data && data.found) {
                hintBox.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> Notice: A similar report <strong>${data.title}</strong> was recently submitted in ${data.location} (${data.date}). <a href="${window.APP_BASE_URL}/item_details.php?id=${data.id}" target="_blank" class="fw-bold text-primary ms-1">View Existing</a>`;
                hintBox.classList.remove('d-none');
            } else {
                hintBox.classList.add('d-none');
            }
        })
        .catch(() => {});
}

if (titleInput) {
    titleInput.addEventListener('input', () => {
        clearTimeout(dupTimeout);
        dupTimeout = setTimeout(checkDuplicateAsync, 500);
    });
}
if (catSelect) catSelect.addEventListener('change', checkDuplicateAsync);
if (locInput) locInput.addEventListener('blur', checkDuplicateAsync);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
