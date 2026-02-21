<?php
// includes/sidebar.php
// auth.php must already be included by the parent page before this is included
// All helper functions (authRole, authName, authInitials) come from includes/auth.php
?>

<aside class="sidebar">

  <div class="sidebar-logo">
    <div class="wordmark">In<span>Place</span></div>
    <div class="subtext">Placement Management System</div>
    <div class="sidebar-role-pill">
      <?= htmlspecialchars(ucfirst(authRole())) ?>
    </div>
  </div>

  <nav class="sidebar-nav">

    <!-- ══════════════════════════════════
         STUDENT NAV
    ══════════════════════════════════ -->
    <?php if (authRole() === 'student'): ?>

      <a href="/inplace/student/dashboard.php"
         class="nav-item <?= ($activePage === 'dashboard') ? 'active' : '' ?>">
        <span class="nav-icon">🏠</span> Dashboard
      </a>

      <a href="/inplace/student/my-placement.php"
         class="nav-item <?= ($activePage === 'placement') ? 'active' : '' ?>">
        <span class="nav-icon">🏢</span> My Placement
      </a>

      <a href="/inplace/student/submit-request.php"
         class="nav-item <?= ($activePage === 'request') ? 'active' : '' ?>">
        <span class="nav-icon">📋</span> Submit Request
      </a>

      <a href="/inplace/student/reports.php"
         class="nav-item <?= ($activePage === 'reports') ? 'active' : '' ?>">
        <span class="nav-icon">📄</span> My Reports
      </a>

      <a href="/inplace/student/visits.php"
         class="nav-item <?= ($activePage === 'visits') ? 'active' : '' ?>">
        <span class="nav-icon">🗓</span> Visits
      </a>

      <a href="/inplace/student/messages.php"
         class="nav-item <?= ($activePage === 'messages') ? 'active' : '' ?>">
        <span class="nav-icon">💬</span> Messages
        <?php if (!empty($unreadCount) && $unreadCount > 0): ?>
          <span class="nav-badge"><?= (int)$unreadCount ?></span>
        <?php endif; ?>
      </a>


    <!-- ══════════════════════════════════
         TUTOR NAV
    ══════════════════════════════════ -->
    <?php elseif (authRole() === 'tutor'): ?>

      <a href="/inplace/tutor/dashboard.php"
         class="nav-item <?= ($activePage === 'dashboard') ? 'active' : '' ?>">
        <span class="nav-icon">🏠</span> Dashboard
      </a>

      <a href="/inplace/tutor/all-placements.php"
         class="nav-item <?= ($activePage === 'placements') ? 'active' : '' ?>">
        <span class="nav-icon">👥</span> All Placements
      </a>

      <a href="/inplace/tutor/requests.php"
         class="nav-item <?= ($activePage === 'requests') ? 'active' : '' ?>">
        <span class="nav-icon">📋</span> Auth Requests
        <?php if (!empty($pendingRequests) && $pendingRequests > 0): ?>
          <span class="nav-badge"><?= (int)$pendingRequests ?></span>
        <?php endif; ?>
      </a>

      <a href="/inplace/tutor/map-view.php"
         class="nav-item <?= ($activePage === 'map') ? 'active' : '' ?>">
        <span class="nav-icon">🗺</span> Map View
      </a>

      <a href="/inplace/tutor/visits.php"
         class="nav-item <?= ($activePage === 'visits') ? 'active' : '' ?>">
        <span class="nav-icon">🗓</span> Visit Planner
      </a>

      <a href="/inplace/tutor/reports.php"
         class="nav-item <?= ($activePage === 'reports') ? 'active' : '' ?>">
        <span class="nav-icon">📄</span> Reports
      </a>

      <a href="/inplace/tutor/messages.php"
         class="nav-item <?= ($activePage === 'messages') ? 'active' : '' ?>">
        <span class="nav-icon">💬</span> Messages
        <?php if (!empty($unreadCount) && $unreadCount > 0): ?>
          <span class="nav-badge"><?= (int)$unreadCount ?></span>
        <?php endif; ?>
      </a>


    <!-- ══════════════════════════════════
         PROVIDER NAV
    ══════════════════════════════════ -->
    <?php elseif (authRole() === 'provider'): ?>

      <a href="/inplace/provider/dashboard.php"
         class="nav-item <?= ($activePage === 'dashboard') ? 'active' : '' ?>">
        <span class="nav-icon">🏠</span> Dashboard
      </a>

      <a href="/inplace/provider/requests.php"
         class="nav-item <?= ($activePage === 'requests') ? 'active' : '' ?>">
        <span class="nav-icon">📋</span> Auth Requests
      </a>

      <a href="/inplace/provider/visits.php"
         class="nav-item <?= ($activePage === 'visits') ? 'active' : '' ?>">
        <span class="nav-icon">🗓</span> Scheduled Visits
      </a>

      <a href="/inplace/provider/messages.php"
         class="nav-item <?= ($activePage === 'messages') ? 'active' : '' ?>">
        <span class="nav-icon">💬</span> Messages
      </a>


    <!-- ══════════════════════════════════
         ADMIN NAV
    ══════════════════════════════════ -->
    <?php elseif (authRole() === 'admin'): ?>

  <?php
    // Pending registration count
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE approval_status = 'pending'");
    $pendingApprovals = (int)$stmt->fetchColumn();
  ?>

  <a href="/inplace/admin/dashboard.php"
     class="nav-item <?= ($activePage === 'dashboard') ? 'active' : '' ?>">
    <span class="nav-icon">🏠</span> Dashboard
  </a>

  <a href="/inplace/admin/approve-registrations.php"
     class="nav-item <?= ($activePage === 'approve_registrations') ? 'active' : '' ?>">
    <span class="nav-icon">📝</span> Registration Approvals
    <?php if ($pendingApprovals > 0): ?>
      <span class="nav-badge"><?= $pendingApprovals ?></span>
    <?php endif; ?>
  </a>

  <a href="/inplace/admin/users.php"
     class="nav-item <?= ($activePage === 'users') ? 'active' : '' ?>">
    <span class="nav-icon">👥</span> Manage Users
  </a>

  <a href="/inplace/admin/placements.php"
     class="nav-item <?= ($activePage === 'placements') ? 'active' : '' ?>">
    <span class="nav-icon">🏢</span> All Placements
  </a>

  <a href="/inplace/admin/settings.php"
     class="nav-item <?= ($activePage === 'settings') ? 'active' : '' ?>">
    <span class="nav-icon">⚙️</span> Settings
  </a>

<?php endif; ?>

  <!-- ── Sidebar Footer: User Info + Logout ── -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-avatar">
        <?= htmlspecialchars(authInitials()) ?>
      </div>
      <div class="sidebar-user-info">
        <h4><?= htmlspecialchars(authName()) ?></h4>
        <p><?= htmlspecialchars(ucfirst(authRole())) ?></p>
      </div>
    </div>
    <a href="/inplace/logout.php"
       style="display:flex;align-items:center;gap:0.6rem;margin-top:1rem;
              padding:0.625rem 0.875rem;border-radius:8px;
              background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.5);
              text-decoration:none;font-size:0.875rem;transition:all 0.2s;"
       onmouseover="this.style.background='rgba(255,255,255,0.1)';this.style.color='#fff'"
       onmouseout="this.style.background='rgba(255,255,255,0.05)';this.style.color='rgba(255,255,255,0.5)'">
      🚪 Sign Out
    </a>
  </div>

</aside>