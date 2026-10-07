<?php
require_once __DIR__ . '/config/app.php';
if (is_logged_in()) {
  require_login();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $description = trim((string) ($_POST['description'] ?? ''));
    $pageUrl = trim((string) ($_POST['page_url'] ?? ''));
    if ($description === '' || mb_strlen($description) > 10000) {
      redirect('/report_bug.php?invalid=1');
    } elseif (mb_strlen($pageUrl) > 500) {
      redirect('/report_bug.php?invalid=1');
    } else {
        $userId = is_logged_in() ? current_user_id() : null;
        $role = is_logged_in() ? current_role() : 'guest';
        $stmt = get_db()->prepare('INSERT INTO system_bugs (user_id, role, description, page_url) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $role, $description, $pageUrl]);
        redirect('/report_bug.php?submitted=1');
    }
}

$refererPath = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH);
$initialPageUrl = is_string($refererPath) && $refererPath !== '' ? $refererPath : ($_SERVER['REQUEST_URI'] ?? '');
$pageTitle = 'Report a Bug';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/page_header.php';
$portalLabel = !is_logged_in() ? 'Sign In' : (is_admin_role() ? 'Admin Portal' : 'Tenant Portal');
$portalPath = !is_logged_in() ? '/auth/login.php' : (is_admin_role() ? '/admin/dashboard.php' : '/tenant/dashboard.php');
render_page_header('bi-bug-fill', 'Report a Bug', 'Tell us what happened so the team can investigate.', null, $portalLabel, $portalPath);
?>
<?php if (isset($_GET['submitted'])): ?><div class="alert alert-success" role="status">Your report was submitted. Thank you for helping us improve the system.</div><?php endif; ?>
<?php if (isset($_GET['invalid'])): ?><div class="alert alert-danger" role="alert">Please provide a description of 1 to 10,000 characters and a page URL of 500 characters or fewer.</div><?php endif; ?>
<div class="panel" style="max-width:760px">
  <form method="post">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="page-url">Page where the issue occurred</label>
      <input class="form-control" id="page-url" name="page_url" maxlength="500" value="<?= clean($initialPageUrl) ?>" placeholder="/tenant/payments.php">
    </div>
    <div class="mb-3">
      <label class="form-label" for="bug-description">What went wrong?</label>
      <textarea class="form-control" id="bug-description" name="description" rows="7" maxlength="10000" required placeholder="Describe what you expected, what happened, and any steps that reproduce it."></textarea>
      <div class="form-text">Do not include passwords or payment card details.</div>
    </div>
    <button class="btn btn-action-primary" type="submit"><i class="bi bi-send me-1"></i> Submit report</button>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>