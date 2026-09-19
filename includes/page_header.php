<?php
if (!defined('BASE_URL')) { http_response_code(403); exit('Direct access not permitted.'); }

/**
 * Shared "workspace" header: breadcrumb, icon avatar, title + WORKSPACE
 * badge, subtitle, and an optional right-aligned action button.
 */
function render_page_header(string $icon, string $title, string $subtitle, ?string $actionHtml = null): void
{
    ?>
    <div class="breadcrumb-trail">Admin Portal <i class="bi bi-chevron-right"></i> <?= clean($title) ?></div>
    <div class="page-header">
      <div class="page-header-main">
        <span class="page-icon-avatar"><i class="bi <?= clean($icon) ?>"></i></span>
        <div>
          <div class="page-title-row"><h1><?= clean($title) ?></h1><span class="workspace-badge">Workspace</span></div>
          <p class="text-muted"><?= clean($subtitle) ?></p>
        </div>
      </div>
      <?php if ($actionHtml): ?><div class="page-header-action"><?= $actionHtml ?></div><?php endif; ?>
    </div>
    <?php
}
