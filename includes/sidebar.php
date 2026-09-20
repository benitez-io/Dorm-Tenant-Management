<?php
if (!defined('BASE_URL')) { http_response_code(403); exit('Direct access not permitted.'); }
/**
 * includes/sidebar.php
 * Renders ONLY the nav links for the current session's role. An admin
 * session never sees tenant links (and vice versa) because the other
 * branch simply never runs — there's no hiding-via-CSS involved.
 * The real access control still happens in require_role() on each
 * page; this is just what makes the UI match that.
 *
 * Admin navigation stays at one level. Module pages render their own
 * persistent horizontal tabs below the page header.
 */
$role = current_role();
$here = $_SERVER['REQUEST_URI'] ?? '';

// [icon, module label, default page, related pages]
$adminLinks = [
  ['<i class="bi bi-person-fill"></i>', 'User Management', '/admin/users.php', ['/admin/users.php', '/admin/credentials.php', '/admin/manage-tenants.php']],
  ['<i class="bi bi-people-fill"></i>', 'Tenant Management', '/admin/tenants.php', ['/admin/tenants.php', '/admin/tenant-status.php', '/admin/checkinout.php']],
  ['<i class="bi bi-building"></i>', 'Property Management', '/admin/rooms.php', ['/admin/rooms.php']],
  ['<i class="bi bi-credit-card-fill"></i>', 'Payments & Contracts', '/admin/payments.php', ['/admin/payments.php']],
  ['<i class="bi bi-tools"></i>', 'Maintenance', '/admin/maintenance.php', ['/admin/maintenance.php']],
  ['<i class="bi bi-bell-fill"></i>', 'Notifications', '/admin/notifications.php', ['/admin/notifications.php']],
  ['<i class="bi bi-graph-up-arrow"></i>', 'Reports & Analytics', '/admin/reports.php', ['/admin/reports.php']],
];

$tenantLinks = [
    ['<i class="bi bi-house-door-fill"></i>', 'Home', '/tenant/dashboard.php'],
    ['<i class="bi bi-file-earmark-text"></i>', 'Services', '/tenant/services.php'],
    ['<i class="bi bi-credit-card-fill"></i>', 'Payments', '/tenant/payments.php'],
    ['<i class="bi bi-tools"></i>', 'Maintenance', '/tenant/maintenance.php'],
    ['<i class="bi bi-person-fill"></i>', 'Profile', '/tenant/profile.php'],
];
?>
<nav class="sidebar no-scrollbar" id="sidebar">
  <div class="sidebar-brand">
    <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
    <?php if ($role === 'admin'): ?>
      <div class="sidebar-brand-text"><div class="sidebar-brand-title">Dorm Tenant</div><div class="sidebar-brand-subtitle">Management System</div></div>
    <?php else: ?>
      <div class="brand-subtitle">Tenant Portal</div>
    <?php endif; ?>
  </div>

  <?php if ($role === 'admin'): ?>
  <div class="topbar-toggle d-lg-none">
    <a class="pill-active" href="<?= BASE_URL ?>/admin/dashboard.php">Admin Dashboard</a>
  </div>
  <?php elseif ($role === 'tenant'): ?>
  <div class="topbar-toggle d-lg-none">
    <a class="pill-active" href="<?= BASE_URL ?>/tenant/dashboard.php">Tenant App</a>
  </div>
  <?php endif; ?>

  <div class="sidebar-links">
  <?php if ($role === 'admin'): ?>
    <div class="sidebar-section">Overview</div>
    <a class="sidebar-link <?= active('/admin/dashboard.php') ?>" href="<?= BASE_URL ?>/admin/dashboard.php"><span class="nav-icon"><i class="bi bi-speedometer2"></i></span> Dashboard</a>
    <?php $sectionLabels = [0 => 'Management', 3 => 'Finance', 4 => 'Operations', 6 => 'Reports']; ?>
    <?php foreach ($adminLinks as $index => [$icon, $label, $page, $relatedPages]):
        $moduleActive = false;
        foreach ($relatedPages as $relatedPage) {
            if (strpos($here, $relatedPage) !== false) { $moduleActive = true; break; }
        }
        if (isset($sectionLabels[$index])): ?><div class="sidebar-section"><?= clean($sectionLabels[$index]) ?></div><?php endif;
    ?>
      <a class="sidebar-link <?= $moduleActive ? 'active' : '' ?>" href="<?= BASE_URL . $page ?>"><span class="nav-icon"><?= $icon ?></span> <?= clean($label) ?></a>
    <?php endforeach; ?>

  <?php elseif ($role === 'tenant'): ?>
    <?php foreach ($tenantLinks as [$icon, $label, $page]): ?>
      <a class="sidebar-link <?= active($page) ?>" href="<?= BASE_URL . $page ?>"><span class="nav-icon"><?= $icon ?></span> <?= clean($label) ?></a>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>

  <div class="sidebar-user">
    <div class="user-avatar"><?= clean(strtoupper(substr($_SESSION['first_name'] ?? '?', 0, 1))) ?></div>
    <div class="user-meta">
      <div class="user-name"><?= clean(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?></div>
      <div class="user-role"><?= clean(ucfirst($role ?? '')) ?></div>
    </div>
  </div>
  <a href="<?= BASE_URL ?>/auth/logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Log Out</a>
</nav>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
