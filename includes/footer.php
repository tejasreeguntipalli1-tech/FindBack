</main>

<footer class="bg-white border-top py-4 mt-auto">
    <div class="container-fluid px-lg-4 d-flex flex-column flex-md-row justify-content-between align-items-center text-muted small">
        <div class="mb-2 mb-md-0">
            <span class="fw-semibold text-dark">Campus Lost &amp; Found Platform</span> &copy; <?= date('Y') ?> &mdash; Full Stack Development System
        </div>
        <div class="d-flex align-items-center gap-3">
            <span><i class="fa-solid fa-circle text-success me-1" style="font-size: 0.6rem;"></i> Live Sync Active</span>
            <span>&bull;</span>
            <a href="<?= $baseUrl ?>/index.php" class="text-decoration-none text-muted">Browse Items</a>
            <span>&bull;</span>
            <a href="<?= $baseUrl ?>/setup_database.php" class="text-decoration-none text-muted" title="Re-seed demo data">Reset DB Data</a>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js for High-Quality Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<!-- Global App JS -->
<script src="<?= $baseUrl ?>/assets/js/main.js"></script>
<!-- Live Polling for Real-Time Alerts & Notification Sync -->
<script src="<?= $baseUrl ?>/assets/js/live_poll.js"></script>
</body>
</html>
