<div class="topbar">
  <div class="topbar-title">
    <h2><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h2>
    <p><?= htmlspecialchars($pageSubtitle ?? '') ?></p>
  </div>
  <div class="topbar-actions">
    <div class="topbar-notif" title="Notifications">🔔
      <?php if ($unreadCount > 0): ?>
        <div class="notif-dot"></div>
      <?php endif; ?>
    </div>
    <a href="/inplace/logout.php" class="topbar-notif" title="Sign out">🚪</a>
  </div>
</div>