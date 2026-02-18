<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth('tutor');

$pageTitle    = 'Authorisation Requests';
$pageSubtitle = 'Review and action student placement requests';
$activePage   = 'requests';
$userId       = authId();

// Sidebar badges
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM placements WHERE status IN ('submitted','awaiting_tutor')");
$pendingRequests = (int)$stmt->fetchColumn();

// ── Handle approve / reject action ──────────────────────────────
$actionMsg = '';
$actionType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $placementId = (int)$_POST['placement_id'];
    $action      = $_POST['action'];       // 'approved' or 'rejected'
    $comments    = trim($_POST['comments'] ?? '');

    $allowed = ['approved', 'rejected', 'awaiting_provider'];
    if (in_array($action, $allowed)) {

        // Update placement status
        $stmt = $pdo->prepare("
            UPDATE placements
            SET status = ?, tutor_comments = ?, tutor_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$action, $comments, $userId, $placementId]);

        // Notify the student
        $stmt = $pdo->prepare("SELECT student_id FROM placements WHERE id = ?");
        $stmt->execute([$placementId]);
        $row = $stmt->fetch();

        if ($row) {
            if ($action === 'approved') {
                $msg = "🎉 Your placement request has been approved! Log in to view your placement details.";
            } elseif ($action === 'rejected') {
                $msg = "Your placement request was not approved." . ($comments ? " Tutor feedback: $comments" : " Please contact your tutor for more information.");
            } else {
                $msg = "Your placement request requires further information. Please check your messages.";
            }

            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'placement_decision', ?)");
            $stmt->execute([$row['student_id'], $msg]);

            // Also send as message
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $row['student_id'], $msg]);
        }

        // Audit log
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, table_affected, record_id, details) VALUES (?, ?, 'placements', ?, ?)");
        $stmt->execute([$userId, 'placement_' . $action, $placementId, $comments]);

        $actionMsg  = $action === 'approved'
            ? "✅ Placement approved successfully! Student has been notified."
            : ($action === 'rejected' ? "❌ Placement rejected. Student has been notified." : "ℹ️ Request sent back for more information.");
        $actionType = $action === 'approved' ? 'success' : ($action === 'rejected' ? 'danger' : 'warning');
    }
}

// ── Fetch all requests with filters ─────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$filterSearch = trim($_GET['search'] ?? '');

$where  = [];
$params = [];

if ($filterStatus) {
    $where[]  = "p.status = ?";
    $params[] = $filterStatus;
}

if ($filterSearch) {
    $where[]  = "(u.full_name LIKE ? OR c.name LIKE ? OR c.city LIKE ?)";
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT
        p.*,
        u.full_name   AS student_name,
        u.email       AS student_email,
        u.avatar_initials AS student_initials,
        c.name        AS company_name,
        c.city        AS company_city,
        c.sector      AS company_sector,
        (SELECT COUNT(*) FROM documents d WHERE d.placement_id = p.id) AS doc_count
    FROM placements p
    JOIN users u ON p.student_id = u.id
    JOIN companies c ON p.company_id = c.id
    $whereSQL
    ORDER BY
        FIELD(p.status,'submitted','awaiting_tutor','awaiting_provider','approved','rejected') ASC,
        p.created_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Status counts for tabs
$stmt = $pdo->query("
    SELECT status, COUNT(*) as cnt
    FROM placements
    GROUP BY status
");
$counts = [];
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['status']] = $row['cnt'];
}
?>
<?php include '../includes/header.php'; ?>

<div class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="page-content">

        <?php if ($actionMsg): ?>
        <div style="background:var(--<?= $actionType ?>-bg);border:1px solid <?= $actionType==='success'?'#6ee7b7':($actionType==='danger'?'#fca5a5':'#fcd34d') ?>;
                    border-radius:var(--radius);padding:1.25rem 2rem;margin-bottom:1.5rem;">
            <p style="color:var(--<?= $actionType ?>);font-weight:500;"><?= htmlspecialchars($actionMsg) ?></p>
        </div>
        <?php endif; ?>

        <!-- ── Status Tab Counts ─────────────────────────────── -->
        <div style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
            <?php
            $tabs = [
                ''                 => ['All Requests', count($requests) ?: array_sum($counts)],
                'submitted'        => ['Submitted', $counts['submitted'] ?? 0],
                'awaiting_provider'=> ['Awaiting Provider', $counts['awaiting_provider'] ?? 0],
                'awaiting_tutor'   => ['Awaiting Tutor', $counts['awaiting_tutor'] ?? 0],
                'approved'         => ['Approved', $counts['approved'] ?? 0],
                'rejected'         => ['Rejected', $counts['rejected'] ?? 0],
            ];
            foreach ($tabs as $val => [$label, $cnt]):
                $active = ($filterStatus === $val);
            ?>
            <a href="?status=<?= urlencode($val) ?>&search=<?= urlencode($filterSearch) ?>"
               style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.625rem 1.25rem;
                      border-radius:50px;font-size:0.875rem;font-weight:600;text-decoration:none;
                      transition:all 0.2s;
                      background:<?= $active ? 'var(--navy)' : 'var(--white)' ?>;
                      color:<?= $active ? 'var(--white)' : 'var(--muted)' ?>;
                      border:2px solid <?= $active ? 'var(--navy)' : 'var(--border)' ?>;">
                <?= $label ?>
                <span style="background:<?= $active ? 'rgba(255,255,255,0.2)' : 'var(--cream)' ?>;
                             color:<?= $active ? 'var(--white)' : 'var(--text)' ?>;
                             padding:0.1rem 0.5rem;border-radius:50px;font-size:0.75rem;">
                    <?= $cnt ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ── Search / Filter Bar ───────────────────────────── -->
        <form method="GET" style="display:flex;gap:0.875rem;margin-bottom:1.5rem;flex-wrap:wrap;">
            <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
            <input type="text" name="search"
                   value="<?= htmlspecialchars($filterSearch) ?>"
                   placeholder="🔍  Search student name, company, city..."
                   style="padding:0.6875rem 1rem;border:1.5px solid var(--border);border-radius:var(--radius-sm);
                          font-family:inherit;font-size:0.875rem;background:var(--white);min-width:300px;">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($filterSearch || $filterStatus): ?>
                <a href="requests.php" class="btn btn-ghost btn-sm">✕ Clear</a>
            <?php endif; ?>
        </form>

        <!-- ── Requests Table ────────────────────────────────── -->
        <div class="panel">
            <div class="panel-header">
                <h3><?= count($requests) ?> Request<?= count($requests) !== 1 ? 's' : '' ?></h3>
                <p><?= $filterStatus ? ucwords(str_replace('_',' ',$filterStatus)) : 'All statuses' ?></p>
            </div>

            <?php if (empty($requests)): ?>
            <div style="text-align:center;padding:4rem 2rem;">
                <div style="font-size:3rem;margin-bottom:1rem;">📋</div>
                <p style="color:var(--muted);font-size:1rem;">No requests found.</p>
            </div>

            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Company</th>
                            <th>Role</th>
                            <th>Dates</th>
                            <th>Docs</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <!-- Student -->
                            <td>
                                <div class="avatar-cell">
                                    <div class="avatar"><?= htmlspecialchars($req['student_initials'] ?? '??') ?></div>
                                    <div>
                                        <h4><?= htmlspecialchars($req['student_name']) ?></h4>
                                        <p><?= htmlspecialchars($req['student_email']) ?></p>
                                    </div>
                                </div>
                            </td>

                            <!-- Company -->
                            <td>
                                <div style="font-weight:500;"><?= htmlspecialchars($req['company_name']) ?></div>
                                <div style="font-size:0.8125rem;color:var(--muted);">
                                    <?= htmlspecialchars($req['company_city'] ?? '') ?>
                                    <?= $req['company_sector'] ? ' · ' . htmlspecialchars($req['company_sector']) : '' ?>
                                </div>
                            </td>

                            <!-- Role -->
                            <td>
                                <span class="type-chip"><?= htmlspecialchars($req['role_title'] ?? 'N/A') ?></span>
                            </td>

                            <!-- Dates -->
                            <td style="font-family:'DM Mono',monospace;font-size:0.8125rem;color:var(--muted);">
                                <?= date('d M Y', strtotime($req['start_date'])) ?><br>
                                → <?= date('d M Y', strtotime($req['end_date'])) ?>
                            </td>

                            <!-- Docs -->
                            <td style="text-align:center;">
                                <span style="font-size:0.875rem;font-weight:600;color:<?= $req['doc_count'] > 0 ? 'var(--success)' : 'var(--muted)' ?>">
                                    <?= $req['doc_count'] > 0 ? '📎 ' . $req['doc_count'] : '—' ?>
                                </span>
                            </td>

                            <!-- Status Badge -->
                            <td>
                                <?php
                                $badgeClass = match($req['status']) {
                                    'approved'          => 'approved',
                                    'rejected'          => 'rejected',
                                    'submitted',
                                    'awaiting_provider' => 'open',
                                    'awaiting_tutor'    => 'review',
                                    default             => 'pending'
                                };
                                ?>
                                <span class="badge badge-<?= $badgeClass ?>">
                                    <?= ucwords(str_replace('_', ' ', $req['status'])) ?>
                                </span>
                            </td>

                            <!-- Submitted date -->
                            <td style="font-size:0.875rem;color:var(--muted);">
                                <?= date('d M Y', strtotime($req['created_at'])) ?>
                            </td>

                            <!-- Actions -->
                            <td>
                                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                                    <!-- Always: View Details button -->
                                    <button class="btn btn-ghost btn-sm"
                                            onclick="openDetail(<?= $req['id'] ?>)">
                                        View
                                    </button>

                                    <?php if (in_array($req['status'], ['submitted','awaiting_tutor','awaiting_provider'])): ?>
                                        <!-- Approve -->
                                        <button class="btn btn-success btn-sm"
                                                onclick="openApprove(<?= $req['id'] ?>, '<?= htmlspecialchars(addslashes($req['student_name'])) ?>', '<?= htmlspecialchars(addslashes($req['company_name'])) ?>')">
                                            ✓ Approve
                                        </button>
                                        <!-- Reject -->
                                        <button class="btn btn-danger btn-sm"
                                                onclick="openReject(<?= $req['id'] ?>, '<?= htmlspecialchars(addslashes($req['student_name'])) ?>')">
                                            ✗ Reject
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <!-- ── Expandable Detail Row ─────────── -->
                        <tr id="detail-<?= $req['id'] ?>" style="display:none;">
                            <td colspan="8" style="background:var(--cream);padding:1.5rem 2rem;">
                                <div class="info-grid" style="margin-bottom:1rem;">
                                    <div class="info-item"><label>Supervisor</label><p><?= htmlspecialchars($req['supervisor_name'] ?? 'N/A') ?></p></div>
                                    <div class="info-item"><label>Supervisor Email</label><p><?= htmlspecialchars($req['supervisor_email'] ?? 'N/A') ?></p></div>
                                    <div class="info-item"><label>Salary</label><p><?= htmlspecialchars($req['salary'] ?? 'Not stated') ?></p></div>
                                    <div class="info-item"><label>Working Pattern</label><p><?= htmlspecialchars($req['working_pattern'] ?? 'N/A') ?></p></div>
                                    <?php if ($req['tutor_comments']): ?>
                                    <div class="info-item"><label>Tutor Comments</label><p><?= htmlspecialchars($req['tutor_comments']) ?></p></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($req['job_description']): ?>
                                <div>
                                    <p style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted);margin-bottom:0.5rem;">Job Description</p>
                                    <p style="font-size:0.9rem;line-height:1.6;color:var(--text);"><?= nl2br(htmlspecialchars($req['job_description'])) ?></p>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /page-content -->
</div><!-- /main -->


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Approve
══════════════════════════════════════════════════════════════ -->
<div id="approveModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
     z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--white);border-radius:var(--radius);padding:2.5rem;
                width:100%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <h3 style="font-family:'Playfair Display',serif;font-size:1.375rem;color:var(--navy);margin-bottom:0.5rem;">
            ✅ Approve Placement
        </h3>
        <p id="approveSubtitle" style="color:var(--muted);font-size:0.9rem;margin-bottom:1.5rem;"></p>
        <form method="POST">
            <input type="hidden" name="placement_id" id="approvePlacementId">
            <input type="hidden" name="action" value="approved">
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label>Comments for student (optional)</label>
                <textarea name="comments" rows="3"
                          placeholder="e.g., Approved — all details verified. Good luck with your placement!"
                          style="padding:0.875rem;border:2px solid var(--border);border-radius:var(--radius-sm);
                                 width:100%;font-family:inherit;font-size:0.9375rem;background:var(--cream);"></textarea>
            </div>
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                <button type="button" class="btn btn-ghost" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn btn-success">✓ Confirm Approval</button>
            </div>
        </form>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════
     MODAL: Reject
══════════════════════════════════════════════════════════════ -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
     z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--white);border-radius:var(--radius);padding:2.5rem;
                width:100%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <h3 style="font-family:'Playfair Display',serif;font-size:1.375rem;color:var(--danger);margin-bottom:0.5rem;">
            ✗ Reject Request
        </h3>
        <p id="rejectSubtitle" style="color:var(--muted);font-size:0.9rem;margin-bottom:1.5rem;"></p>
        <form method="POST">
            <input type="hidden" name="placement_id" id="rejectPlacementId">
            <input type="hidden" name="action" value="rejected">
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label>Reason for rejection <span style="color:var(--danger);">*</span></label>
                <textarea name="comments" rows="4" required
                          placeholder="Explain clearly why this request is being rejected and what the student should do next..."
                          style="padding:0.875rem;border:2px solid #fca5a5;border-radius:var(--radius-sm);
                                 width:100%;font-family:inherit;font-size:0.9375rem;background:#fff8f8;"></textarea>
            </div>
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                <button type="button" class="btn btn-ghost" onclick="closeModals()">Cancel</button>
                <button type="submit" class="btn btn-danger">✗ Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>


<script>
function openDetail(id) {
    const row = document.getElementById('detail-' + id);
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}

function openApprove(id, student, company) {
    document.getElementById('approvePlacementId').value = id;
    document.getElementById('approveSubtitle').textContent =
        'You are about to approve ' + student + '\'s placement at ' + company + '.';
    document.getElementById('approveModal').style.display = 'flex';
}

function openReject(id, student) {
    document.getElementById('rejectPlacementId').value = id;
    document.getElementById('rejectSubtitle').textContent =
        'You are about to reject ' + student + '\'s placement request. This action will notify the student.';
    document.getElementById('rejectModal').style.display = 'flex';
}

function closeModals() {
    document.getElementById('approveModal').style.display = 'none';
    document.getElementById('rejectModal').style.display = 'none';
}

// Close on outside click
['approveModal','rejectModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) closeModals();
    });
});
</script>

<?php include '../includes/footer.php'; ?>