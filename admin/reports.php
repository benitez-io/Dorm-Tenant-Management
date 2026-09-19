<?php
require_once __DIR__ . '/../config/app.php';
require_role('admin');

$pageTitle = 'Reports & Analytics';
include __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/module_tabs.php';

$categories = [
    'occupancy'   => ['title' => 'Occupancy Reports',   'reports' => [
        ['icon' => '<i class="bi bi-building"></i>', 'title' => 'Monthly Occupancy Report', 'desc' => 'Detailed occupancy statistics, vacancy rates, and room turnover for the current month.'],
    ]],
    'payment'     => ['title' => 'Payment Reports',     'reports' => [
        ['icon' => '<i class="bi bi-credit-card-fill"></i>', 'title' => 'Monthly Payment Report', 'desc' => 'Payment collection summary, outstanding balances, and revenue breakdown.'],
    ]],
    'tenant'      => ['title' => 'Tenant Reports',      'reports' => [
        ['icon' => '<i class="bi bi-people-fill"></i>', 'title' => 'Tenant Directory', 'desc' => 'Complete list of all current tenants with contact information and room assignments.'],
    ]],
    'maintenance' => ['title' => 'Maintenance Reports', 'reports' => [
        ['icon' => '<i class="bi bi-tools"></i>', 'title' => 'Maintenance Summary Report', 'desc' => 'Overview of all maintenance requests, completion rates, and pending tasks.'],
    ]],
];
?>
<div class="page-header"><div><h1>Reports &amp; Analytics</h1><p class="text-muted">Generate and export comprehensive reports for property management.</p></div></div>
<?php render_module_tabs([
  ['key' => 'all', 'label' => 'All Reports', 'href' => '/admin/reports.php#all-reports', 'target' => 'all-reports'],
  ['key' => 'occupancy', 'label' => 'Occupancy', 'href' => '/admin/reports.php#occupancy', 'target' => 'occupancy'],
  ['key' => 'payment', 'label' => 'Payments', 'href' => '/admin/reports.php#payment', 'target' => 'payment'],
  ['key' => 'tenant', 'label' => 'Tenants', 'href' => '/admin/reports.php#tenant', 'target' => 'tenant'],
  ['key' => 'maintenance', 'label' => 'Maintenance', 'href' => '/admin/reports.php#maintenance', 'target' => 'maintenance'],
], 'all'); ?>

<div class="report-category-grid anchor-target" id="all-reports">
  <?php foreach ($categories as $key => $cat): ?>
    <div class="panel anchor-target" id="<?= $key ?>">
      <div class="panel-header"><h2><?= clean($cat['title']) ?></h2><span class="selection-state badge badge-maroon" hidden>Selected</span></div>
      <?php foreach ($cat['reports'] as $r): ?>
        <div class="report-card">
          <div class="report-card-top">
            <div class="report-card-icon"><?= $r['icon'] ?></div>
            <div><h3><?= clean($r['title']) ?></h3><p><?= clean($r['desc']) ?></p></div>
          </div>
          <div class="report-card-actions">
            <a href="<?= BASE_URL ?>/admin/report_print.php?type=<?= $key ?>&mode=view" class="btn btn-outline-maroon js-review-btn" data-review-url="<?= BASE_URL ?>/admin/report_print.php?type=<?= $key ?>&mode=view"><i class="bi bi-eye-fill"></i> Review</a>
            <a href="<?= BASE_URL ?>/admin/report_print.php?type=<?= $key ?>" target="_blank" class="btn btn-maroon"><i class="bi bi-printer-fill"></i> Print</a>
            <a href="<?= BASE_URL ?>/admin/report_export.php?type=<?= $key ?>" class="btn btn-outline-maroon"><i class="bi bi-file-earmark-pdf-fill"></i> Export PDF</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>

<p class="text-muted small mt-3">"Review" opens the report on-screen to look over. "Print" opens the same view and sends it straight to your printer dialog. "Export PDF" downloads the report as a PDF file instead.</p>

<div class="report-review-overlay" id="reportReviewOverlay" hidden>
  <div class="report-review-panel">
    <div class="report-review-header">
      <button type="button" class="report-review-close" id="reportReviewClose" aria-label="Close review">&times;</button>
    </div>
    <iframe class="report-review-frame" id="reportReviewFrame" src="about:blank" title="Report review"></iframe>
  </div>
</div>

<?php
$extraScripts = "<script>
(function () {
  var overlay = document.getElementById('reportReviewOverlay');
  var frame = document.getElementById('reportReviewFrame');
  var closeBtn = document.getElementById('reportReviewClose');
  function openReview(url) {
    frame.src = url;
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
  }
  function closeReview() {
    overlay.hidden = true;
    frame.src = 'about:blank';
    document.body.style.overflow = '';
  }
  document.querySelectorAll('.js-review-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      openReview(btn.dataset.reviewUrl);
    });
  });
  closeBtn.addEventListener('click', closeReview);
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeReview();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !overlay.hidden) closeReview();
  });
})();
</script>";
include __DIR__ . '/../includes/footer.php';
?>
