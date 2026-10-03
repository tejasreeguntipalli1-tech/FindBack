<?php
/**
 * Public Landing & Browse Items
 * Implements Section 47: Search & Multi-Filter across database
 */
$pageTitle = "Campus Lost & Found";
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$baseUrl = get_base_url();

// Fetch Categories for Filter Dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Ticker Stats (Direct from Database)
$statStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_reports,
        SUM(CASE WHEN status = 'recovered' THEN 1 ELSE 0 END) AS recovered_count,
        SUM(CASE WHEN type = 'lost' AND status = 'active' THEN 1 ELSE 0 END) AS active_lost,
        SUM(CASE WHEN type = 'found' AND status = 'active' THEN 1 ELSE 0 END) AS active_found
    FROM reports
    WHERE is_deleted = 0
");
$stats = $statStmt->fetch(PDO::FETCH_ASSOC);

// Search & Multiple Filters Processing
$keyword   = trim($_GET['q'] ?? '');
$type      = $_GET['type'] ?? 'all';
$category  = (int)($_GET['category'] ?? 0);
$status    = $_GET['status'] ?? 'all';
$sort      = $_GET['sort'] ?? 'newest';

$sql = "
    SELECT r.*, c.name AS category_name, c.icon AS category_icon, u.name AS reporter_name
    FROM reports r
    JOIN categories c ON r.category_id = c.id
    JOIN users u ON r.user_id = u.id
    WHERE r.is_deleted = 0
";
$params = [];

// Show verified items publicly, plus unverified if logged-in user is the owner or admin
if (!is_admin()) {
    if (is_logged_in()) {
        $sql .= " AND (r.verification_status = 'verified' OR r.user_id = ?)";
        $params[] = $_SESSION['user_id'];
    } else {
        $sql .= " AND r.verification_status = 'verified'";
    }
}

if ($type === 'lost' || $type === 'found') {
    $sql .= " AND r.type = ?";
    $params[] = $type;
}

if ($category > 0) {
    $sql .= " AND r.category_id = ?";
    $params[] = $category;
}

if ($status === 'active') {
    $sql .= " AND r.status = 'active'";
} elseif ($status === 'recovered') {
    $sql .= " AND r.status = 'recovered'";
}

if (!empty($keyword)) {
    // Section 22: Normalize and tokenize search keywords to allow partial matches (e.g. 'black calc' finds 'Black Casio Calculator')
    $cleanKeyword = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $keyword);
    $tokens = preg_split('/\s+/', trim($cleanKeyword), -1, PREG_SPLIT_NO_EMPTY);
    
    if (!empty($tokens)) {
        foreach ($tokens as $tok) {
            $sql .= " AND (r.title LIKE ? OR r.description LIKE ? OR r.location LIKE ? OR c.name LIKE ?)";
            $like = '%' . $tok . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
    }
}

if ($sort === 'oldest') {
    $sql .= " ORDER BY r.item_date ASC, r.created_at ASC";
} else {
    $sql .= " ORDER BY r.item_date DESC, r.created_at DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<div class="hero-banner mb-4">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <span class="badge bg-white text-primary fw-bold text-uppercase px-3 py-2 mb-3">Official Campus Portal</span>
            <h1 class="display-6 fw-bold mb-2">Misplaced something on campus? We've got you covered.</h1>
            <p class="lead opacity-90 mb-4" style="font-size: 1.05rem;">
                Connect lost belongings with their rightful owners using our Smart Matching detection and verified security logs.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= $baseUrl ?>/student/report_item.php?type=lost" class="btn btn-danger btn-lg px-4 fs-6 fw-semibold">
                    <i class="fa-solid fa-circle-exclamation me-1"></i> Report Lost Item
                </a>
                <a href="<?= $baseUrl ?>/student/report_item.php?type=found" class="btn btn-success btn-lg px-4 fs-6 fw-semibold">
                    <i class="fa-solid fa-hand-holding-heart me-1"></i> Report Found Item
                </a>
            </div>
        </div>
        <div class="col-lg-4 d-none d-lg-block text-end opacity-75">
            <i class="fa-solid fa-compass fa-8x"></i>
        </div>
    </div>
</div>

<!-- Live Campus Statistics Ticker (Real Database Queries) -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card card-custom p-3 text-center">
            <div class="text-muted small fw-semibold text-uppercase">Total Campus Reports</div>
            <div class="fs-3 fw-bold text-dark"><?= (int)$stats['total_reports'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-custom p-3 text-center">
            <div class="text-muted small fw-semibold text-uppercase">Active Lost Items</div>
            <div class="fs-3 fw-bold text-danger"><?= (int)$stats['active_lost'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-custom p-3 text-center">
            <div class="text-muted small fw-semibold text-uppercase">Active Found Items</div>
            <div class="fs-3 fw-bold text-success"><?= (int)$stats['active_found'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card card-custom p-3 text-center">
            <div class="text-muted small fw-semibold text-uppercase">Items Recovered</div>
            <div class="fs-3 fw-bold text-primary"><?= (int)$stats['recovered_count'] ?></div>
        </div>
    </div>
</div>

<!-- Multi-Filter & Search Engine Bar (Section 47) -->
<div class="card card-custom p-3 p-md-4 mb-4 shadow-sm">
    <form method="GET" action="index.php" class="row g-2 align-items-center">
        <!-- Keyword Search -->
        <div class="col-lg-4 col-md-6">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search by item, description, location..." value="<?= e($keyword) ?>">
            </div>
        </div>

        <!-- Type Filter -->
        <div class="col-lg-2 col-md-3 col-6">
            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all">All Types</option>
                <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost Items</option>
                <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found Items</option>
            </select>
        </div>

        <!-- Category Filter -->
        <div class="col-lg-2 col-md-3 col-6">
            <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Status Filter -->
        <div class="col-lg-2 col-md-6 col-6">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all">All Statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active Only</option>
                <option value="recovered" <?= $status === 'recovered' ? 'selected' : '' ?>>Recovered</option>
            </select>
        </div>

        <!-- Sort Filter -->
        <div class="col-lg-2 col-md-6 col-6">
            <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            </select>
        </div>

        <!-- Submit & Reset Buttons -->
        <div class="col-12 d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
            <span class="text-muted small">
                Showing <strong><?= count($items) ?></strong> <?= count($items) === 1 ? 'item' : 'items' ?> from real database records
            </span>
            <div class="d-flex gap-2">
                <?php if (!empty($keyword) || $type !== 'all' || $category > 0 || $status !== 'all' || $sort !== 'newest'): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-rotate-left me-1"></i> Reset Filters
                    </a>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold">
                    <i class="fa-solid fa-filter me-1"></i> Apply Filters
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Items Grid -->
<?php if (empty($items)): ?>
    <!-- Empty State (Section 57) -->
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fa-regular fa-face-frown"></i></div>
        <h5 class="empty-state-title">We couldn't find any items matching your search</h5>
        <p class="empty-state-text">Try adjusting your filters, searching for alternate keywords, or clearing your category selection.</p>
        <a href="index.php" class="btn btn-primary btn-sm">Reset All Filters</a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($items as $item): ?>
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <div class="card card-custom h-100 overflow-hidden d-flex flex-column">
                    <!-- Image or Icon Placeholder -->
                    <div class="position-relative bg-light text-center border-bottom" style="height: 180px; overflow: hidden;">
                        <?php if (!empty($item['image_path'])): ?>
                            <img src="<?= $baseUrl ?>/<?= e($item['image_path']) ?>" alt="<?= e($item['title']) ?>" class="w-100 h-100" style="object-fit: cover;">
                        <?php else: ?>
                            <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted">
                                <i class="fa-solid <?= e($item['category_icon']) ?> fa-3x mb-2 text-secondary opacity-50"></i>
                                <span class="small text-secondary"><?= e($item['category_name']) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Top Badges -->
                        <div class="position-absolute top-0 start-0 m-2">
                            <?php if ($item['type'] === 'lost'): ?>
                                <span class="badge-lost shadow-sm">Lost</span>
                            <?php else: ?>
                                <span class="badge-found shadow-sm">Found</span>
                            <?php endif; ?>
                        </div>

                        <div class="position-absolute top-0 end-0 m-2">
                            <?php if ($item['status'] === 'recovered'): ?>
                                <span class="badge-recovered shadow-sm"><i class="fa-solid fa-check me-1"></i> Recovered</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body p-3 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-secondary-subtle text-secondary small" style="font-size: 0.7rem;">
                                <?= e($item['category_name']) ?>
                            </span>
                            <span class="text-muted small" style="font-size: 0.72rem;">
                                <?= date('M d, Y', strtotime($item['item_date'])) ?>
                            </span>
                        </div>

                        <h6 class="fw-bold text-dark mb-1">
                            <a href="<?= $baseUrl ?>/item_details.php?id=<?= $item['id'] ?>" class="text-dark text-decoration-none">
                                <?= e($item['title']) ?>
                            </a>
                        </h6>

                        <div class="small text-muted mb-2">
                            <i class="fa-solid fa-location-dot me-1 text-danger"></i> <?= e($item['location']) ?>
                        </div>

                        <p class="small text-secondary mb-3 flex-grow-1" style="font-size: 0.825rem;">
                            <?= e(mb_strimwidth($item['description'], 0, 95, '...')) ?>
                        </p>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-auto">
                            <span class="small text-muted" style="font-size: 0.75rem;">
                                By <?= e($item['reporter_name']) ?>
                            </span>
                            <a href="<?= $baseUrl ?>/item_details.php?id=<?= $item['id'] ?>" class="btn btn-outline-primary btn-sm py-1 px-2 fw-semibold" style="font-size: 0.8rem;">
                                View Details &raquo;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
