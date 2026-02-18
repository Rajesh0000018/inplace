<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth(['admin']);

$pageTitle = "Admin Dashboard";
$activeNav = "dashboard";
include __DIR__ . '/../includes/header.php';
?>
<div class="app active">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main">
    <?php $pageSubtitle = "System overview"; include __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content">
      <div class="panel">
        <div class="panel-header"><h3>Admin</h3><p>Configure and monitor</p></div>
        <div class="panel-body">
          <p style="color:var(--muted);">Add admin tools here (user management, imports, logs, etc.).</p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>