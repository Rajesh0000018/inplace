<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth('admin');

$pageTitle = 'Registration Approvals';
$pageSubtitle = 'Review and approve student registrations';
$activePage = 'approve_registrations';
$userId = authId();

$unreadCount = 0;
$pendingRequests = 0;

// Handle approval/rejection
$actionMsg = '';
$actionType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($targetUserId > 0 && $action === 'approve') {
        $stmt = $pdo->prepare("
            UPDATE users
            SET approval_status = 'approved', approved_by = ?, approved_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId, $targetUserId]);

        $actionMsg = "Student account approved successfully!";
        $actionType = 'success';

    } elseif ($targetUserId > 0 && $action === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? '');

        $stmt = $pdo->prepare("
            UPDATE users
            SET approval_status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$userId, $reason, $targetUserId]);

        $actionMsg = "Student registration rejected.";
        $actionType = 'warning';
    }
}

// Fetch registrations
$stmt = $pdo->query("
    SELECT
        id, full_name, email, academic_year, programme_type, created_at, approval_status
    FROM users
    WHERE role = 'student' AND approval_status IN ('pending', 'approved', 'rejected')
    ORDER BY
        FIELD(approval_status, 'pending', 'approved', 'rejected'),
        created_at DESC
");
$registrations = $stmt->fetchAll();

// Count pending
$pendingCount = 0;
foreach ($registrations as $reg) {
    if ($reg['approval_status'] === 'pending') $pendingCount++;
}
?>
<?php include '../includes/header.php'; ?>

<div class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="page-content">

        <?php if ($actionMsg): ?>
        <div style="background:var(--<?= $actionType ?>-bg);
                    border:1px solid <?= $actionType==='success'?'#6ee7b7':'#fcd34d' ?>;
                    border-radius:var(--radius);padding:1.25rem 2rem;margin-bottom:1.5rem;">
            <p style="color:var(--<?= $actionType ?>);font-weight:500;"><?= htmlspecialchars($actionMsg) ?></p>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
                    gap:1.25rem;margin-bottom:2rem;">

            <div class="panel" style="margin-bottom:0;padding:1.25rem 1.75rem;">
                <p style="font-size:0.75rem;font-weight:600;text-transform:uppercase;
                          letter-spacing:0.05em;color:var(--muted);margin-bottom:0.5rem;">
                    Pending Approval
                </p>
                <h3 style="font-family:'Playfair Display',serif;font-size:1.625rem;
                           color:<?= $pendingCount>0?'var(--warning)':'var(--navy)' ?>;">
                    <?= $pendingCount ?>
                </h3>
            </div>

            <div class="panel" style="margin-bottom:0;padding:1.25rem 1.75rem;">
                <p style="font-size:0.75rem;font-weight:600;text-transform:uppercase;
                          letter-spacing:0.05em;color:var(--muted);margin-bottom:0.5rem;">
                    Total Registrations
                </p>
                <h3 style="font-family:'Playfair Display',serif;font-size:1.625rem;color:var(--navy);">
                    <?= count($registrations) ?>
                </h3>
            </div>

        </div>

        <!-- Registrations Table -->
        <div class="panel">
            <div class="panel-header">
                <h3>Student Registrations</h3>
            </div>

            <?php if (empty($registrations)): ?>
            <div style="text-align:center;padding:4rem 2rem;">
                <div style="font-size:3rem;margin-bottom:1rem;">👥</div>
                <p style="color:var(--muted);font-size:1rem;">No registrations yet.</p>
            </div>

            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Academic Year</th>
                            <th>Programme</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $reg): ?>
                        <tr>
                            <td>
                                <div style="font-weight:500;"><?= htmlspecialchars($reg['full_name']) ?></div>
                            </td>
                            <td style="font-size:0.875rem;color:var(--muted);">
                                <?= htmlspecialchars($reg['email']) ?>
                            </td>
                            <td>
                                <span class="type-chip"><?= htmlspecialchars($reg['academic_year']) ?></span>
                            </td>
                            <td style="font-size:0.875rem;">
                                <?= htmlspecialchars($reg['programme_type']) ?>
                            </td>
                            <td style="font-family:'DM Mono',monospace;font-size:0.8125rem;">
                                <?= date('d M Y', strtotime($reg['created_at'])) ?>
                            </td>
                            <td>
                                <?php
                                $badgeClass = match($reg['approval_status']) {
                                    'approved' => 'approved',
                                    'rejected' => 'rejected',
                                    'pending'  => 'pending',
                                    default    => 'pending'
                                };
                                ?>
                                <span class="badge badge-<?= $badgeClass ?>">
                                    <?= ucfirst($reg['approval_status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($reg['approval_status'] === 'pending'): ?>
                                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                                    <!-- ✅ Approve now opens custom modal -->
                                    <button type="button" class="btn btn-success btn-sm"
                                            onclick="openApproveModal(<?= (int)$reg['id'] ?>, '<?= htmlspecialchars($reg['full_name'], ENT_QUOTES) ?>')">
                                        ✓ Approve
                                    </button>

                                    <!-- Reject modal unchanged -->
                                    <button type="button" class="btn btn-danger btn-sm"
                                            onclick="openRejectModal(<?= (int)$reg['id'] ?>, '<?= htmlspecialchars($reg['full_name'], ENT_QUOTES) ?>')">
                                        ✗ Reject
                                    </button>
                                </div>
                                <?php else: ?>
                                <span style="font-size:0.875rem;color:var(--muted);">
                                    <?= $reg['approval_status'] === 'approved' ? 'Approved' : 'Rejected' ?>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ✅ Shared modal animation styles -->
<style>
  @keyframes ipFadeIn { from { opacity: 0; } to { opacity: 1; } }
  @keyframes ipPopIn { from { opacity: 0; transform: translateY(10px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }

  .ip-modal-overlay{
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,0.5);
    z-index:1000;
    align-items:center; justify-content:center;
    animation: ipFadeIn .15s ease-out;
  }
  .ip-modal-overlay.active{ display:flex; }

  .ip-modal{
    background:var(--white);
    border-radius:var(--radius);
    padding:2.5rem;
    width:100%;
    max-width:500px;
    box-shadow:0 20px 60px rgba(0,0,0,0.2);
    animation: ipPopIn .18s ease-out;
  }
</style>

<!-- ✅ Approve Modal -->
<div id="approveModal" class="ip-modal-overlay" aria-hidden="true">
  <div class="ip-modal" role="dialog" aria-modal="true" aria-labelledby="approveTitle">
    <h3 id="approveTitle" style="font-family:'Playfair Display',serif;font-size:1.375rem;
               color:var(--navy);margin-bottom:0.5rem;">
      ✅ Approve Registration
    </h3>

    <p id="approveStudentName" style="color:var(--muted);font-size:0.9rem;margin-bottom:1.5rem;"></p>

    <div style="background:var(--success-bg);border:1px solid #6ee7b7;border-radius:var(--radius-sm);
                padding:0.875rem 1rem;margin-bottom:1.25rem;">
      <p style="margin:0;color:var(--success);font-size:0.9rem;">
        This will activate the student account immediately.
      </p>
    </div>

    <form method="POST" id="approveForm">
      <input type="hidden" name="user_id" id="approveUserId">
      <input type="hidden" name="action" value="approve">

      <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
        <button type="button" class="btn btn-ghost" onclick="closeApproveModal()">Cancel</button>
        <button type="submit" class="btn btn-success" id="approveConfirmBtn">
          Confirm Approval
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Reject Modal (kept, but with animation classes) -->
<div id="rejectModal" class="ip-modal-overlay" aria-hidden="true">
  <div class="ip-modal" role="dialog" aria-modal="true" aria-labelledby="rejectTitle">
    <h3 id="rejectTitle" style="font-family:'Playfair Display',serif;font-size:1.375rem;
                   color:var(--danger);margin-bottom:0.5rem;">
      ⚠️ Reject Registration
    </h3>
    <p id="rejectStudentName" style="color:var(--muted);font-size:0.9rem;margin-bottom:1.5rem;"></p>

    <form method="POST" id="rejectForm">
      <input type="hidden" name="user_id" id="rejectUserId">
      <input type="hidden" name="action" value="reject">

      <div style="margin-bottom:1.5rem;">
        <label style="display:block;font-size:0.875rem;font-weight:500;
                      color:var(--text);margin-bottom:0.5rem;">
          Reason for rejection (optional)
        </label>
        <textarea name="rejection_reason" rows="4"
                  placeholder="e.g., Not a third-year student, invalid email domain..."
                  style="width:100%;padding:0.875rem 1rem;border:2px solid var(--border);
                         border-radius:var(--radius-sm);font-family:inherit;
                         font-size:0.9375rem;background:var(--cream);resize:vertical;"></textarea>
      </div>

      <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
        <button type="button" class="btn btn-ghost" onclick="closeRejectModal()">Cancel</button>
        <button type="submit" class="btn btn-danger" id="rejectConfirmBtn">Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<script>
  // --- Approve modal ---
  function openApproveModal(userId, studentName) {
    document.getElementById('approveUserId').value = userId;
    document.getElementById('approveStudentName').textContent =
      'You are about to approve the registration for: ' + studentName;

    // reset button state (in case user opened it before)
    const btn = document.getElementById('approveConfirmBtn');
    btn.disabled = false;
    btn.textContent = 'Confirm Approval';

    document.getElementById('approveModal').classList.add('active');
    document.getElementById('approveModal').setAttribute('aria-hidden', 'false');
  }

  function closeApproveModal() {
    document.getElementById('approveModal').classList.remove('active');
    document.getElementById('approveModal').setAttribute('aria-hidden', 'true');
  }

  // Close on background click
  document.getElementById('approveModal').addEventListener('click', function(e) {
    if (e.target === this) closeApproveModal();
  });

  // Prevent double submit + show "Approving..."
  document.getElementById('approveForm').addEventListener('submit', function() {
    const btn = document.getElementById('approveConfirmBtn');
    btn.disabled = true;
    btn.textContent = 'Approving...';
  });

  // --- Reject modal (existing but improved) ---
  function openRejectModal(userId, studentName) {
    document.getElementById('rejectUserId').value = userId;
    document.getElementById('rejectStudentName').textContent =
      'You are about to reject the registration for: ' + studentName;

    const btn = document.getElementById('rejectConfirmBtn');
    btn.disabled = false;
    btn.textContent = 'Confirm Rejection';

    document.getElementById('rejectModal').classList.add('active');
    document.getElementById('rejectModal').setAttribute('aria-hidden', 'false');
  }

  function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('active');
    document.getElementById('rejectModal').setAttribute('aria-hidden', 'true');
  }

  document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
  });

  // Prevent double submit + show "Rejecting..."
  document.getElementById('rejectForm').addEventListener('submit', function() {
    const btn = document.getElementById('rejectConfirmBtn');
    btn.disabled = true;
    btn.textContent = 'Rejecting...';
  });

  // ESC to close any open modal
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeApproveModal();
      closeRejectModal();
    }
  });
</script>

<?php include '../includes/footer.php'; ?>