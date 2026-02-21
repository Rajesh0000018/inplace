<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth('provider');

$pageTitle = 'Authorization Requests';
$pageSubtitle = 'Review and respond to placement requests';
$activePage = 'auth-requests';
$userId = authId();

// Get provider's company
$stmt = $pdo->prepare("SELECT company_id, full_name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$provider = $stmt->fetch();

// Handle approval/feedback
$actionMsg = '';
$actionType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $placementId = $_POST['placement_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        // Approve the placement
        $stmt = $pdo->prepare("
            UPDATE placements 
            SET status = 'awaiting_tutor',
                provider_approved_at = NOW(),
                provider_approved_by = ?
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$userId, $placementId, $provider['company_id']]);
        
        $actionMsg = "✅ Placement approved successfully!";
        $actionType = 'success';
        
    } elseif ($action === 'provide_feedback') {
        $feedback = trim($_POST['feedback']);
        
        $stmt = $pdo->prepare("
            UPDATE placements 
            SET provider_feedback = ?,
                provider_feedback_at = NOW()
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$feedback, $placementId, $provider['company_id']]);
        
        $actionMsg = "✅ Feedback submitted successfully!";
        $actionType = 'success';
    }
}

// Fetch all placement requests for this company
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        s.full_name AS student_name,
        s.email AS student_email,
        s.avatar_initials,
        s.academic_year,
        s.programme_type,
        t.full_name AS tutor_name,
        c.name AS company_name
    FROM placements p
    JOIN users s ON p.student_id = s.id
    LEFT JOIN users t ON p.tutor_id = t.id
    JOIN companies c ON p.company_id = c.id
    WHERE p.company_id = ?
    ORDER BY 
        CASE p.status
            WHEN 'awaiting_provider' THEN 1
            WHEN 'awaiting_tutor' THEN 2
            WHEN 'approved' THEN 3
            WHEN 'active' THEN 4
            ELSE 5
        END,
        p.created_at DESC
");
$stmt->execute([$provider['company_id']]);
$requests = $stmt->fetchAll();

// Count pending
$pendingCount = 0;
foreach ($requests as $req) {
    if ($req['status'] === 'awaiting_provider') {
        $pendingCount++;
    }
}

$unreadCount = 0;
$pendingRequests = $pendingCount;
?>
<?php include '../includes/header.php'; ?>

<div class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="page-content">

        <?php if ($actionMsg): ?>
        <div style="background:var(--<?= $actionType ?>-bg);
                    border:1px solid <?= $actionType==='success'?'#6ee7b7':'#fca5a5' ?>;
                    border-radius:var(--radius);padding:1.25rem 2rem;margin-bottom:1.5rem;">
            <p style="color:var(--<?= $actionType ?>);font-weight:500;"><?= htmlspecialchars($actionMsg) ?></p>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;margin-bottom:2rem;">
            
            <div class="panel" style="margin-bottom:0;padding:1.5rem;">
                <p style="font-size:0.75rem;font-weight:600;text-transform:uppercase;
                          letter-spacing:0.05em;color:var(--muted);margin-bottom:0.5rem;">
                    Pending Approval
                </p>
                <h3 style="font-family:'Playfair Display',serif;font-size:2rem;color:var(--warning);">
                    <?= $pendingCount ?>
                </h3>
            </div>

            <div class="panel" style="margin-bottom:0;padding:1.5rem;">
                <p style="font-size:0.75rem;font-weight:600;text-transform:uppercase;
                          letter-spacing:0.05em;color:var(--muted);margin-bottom:0.5rem;">
                    Total Requests
                </p>
                <h3 style="font-family:'Playfair Display',serif;font-size:2rem;color:var(--navy);">
                    <?= count($requests) ?>
                </h3>
            </div>

            <div class="panel" style="margin-bottom:0;padding:1.5rem;">
                <p style="font-size:0.75rem;font-weight:600;text-transform:uppercase;
                          letter-spacing:0.05em;color:var(--muted);margin-bottom:0.5rem;">
                    Approved
                </p>
                <h3 style="font-family:'Playfair Display',serif;font-size:2rem;color:var(--success);">
                    <?php
                    $approved = 0;
                    foreach ($requests as $r) {
                        if (in_array($r['status'], ['approved', 'active'])) $approved++;
                    }
                    echo $approved;
                    ?>
                </h3>
            </div>

            <div class="panel" style="margin-bottom:0;padding:1.5rem;">
                <p style="font-size:0.75rem;font-weight:600;text-transform:uppercase;
                          letter-spacing:0.05em;color:var(--muted);margin-bottom:0.5rem;">
                    Active Placements
                </p>
                <h3 style="font-family:'Playfair Display',serif;font-size:2rem;color:var(--info);">
                    <?php
                    $active = 0;
                    foreach ($requests as $r) {
                        if ($r['status'] === 'active') $active++;
                    }
                    echo $active;
                    ?>
                </h3>
            </div>

        </div>

        <!-- Requests Table -->
        <div class="panel">
            <div class="panel-header">
                <h3>📋 All Placement Requests</h3>
            </div>

            <?php if (empty($requests)): ?>
            <div style="text-align:center;padding:4rem 2rem;">
                <div style="font-size:3rem;margin-bottom:1rem;">📋</div>
                <p style="color:var(--muted);font-size:1rem;">No placement requests yet.</p>
            </div>

            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Role</th>
                            <th>Dates</th>
                            <th>Year/Programme</th>
                            <th>Tutor</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <!-- Student -->
                            <td>
                                <div class="avatar-cell">
                                    <div class="avatar"><?= htmlspecialchars($req['avatar_initials']) ?></div>
                                    <div>
                                        <h4><?= htmlspecialchars($req['student_name']) ?></h4>
                                        <p><?= htmlspecialchars($req['student_email']) ?></p>
                                    </div>
                                </div>
                            </td>

                            <!-- Role -->
                            <td>
                                <span class="type-chip">
                                    <?= htmlspecialchars($req['role_title'] ?? 'Not specified') ?>
                                </span>
                            </td>

                            <!-- Dates -->
                            <td style="font-family:'DM Mono',monospace;font-size:0.8125rem;">
                                <?= date('d M Y', strtotime($req['start_date'])) ?><br>
                                <span style="color:var(--muted);">to</span><br>
                                <?= date('d M Y', strtotime($req['end_date'])) ?>
                            </td>

                            <!-- Year/Programme -->
                            <td>
                                <?= htmlspecialchars($req['academic_year'] ?? 'N/A') ?><br>
                                <span style="font-size:0.75rem;color:var(--muted);">
                                    <?= htmlspecialchars($req['programme_type'] ?? '') ?>
                                </span>
                            </td>

                            <!-- Tutor -->
                            <td style="font-size:0.875rem;">
                                <?= htmlspecialchars($req['tutor_name'] ?? 'Unassigned') ?>
                            </td>

                            <!-- Status -->
                            <td>
                                <?php
                                $badgeClass = match($req['status']) {
                                    'awaiting_provider' => 'pending',
                                    'awaiting_tutor' => 'review',
                                    'approved', 'active' => 'approved',
                                    'rejected' => 'rejected',
                                    default => 'open'
                                };
                                ?>
                                <span class="badge badge-<?= $badgeClass ?>">
                                    <?= ucwords(str_replace('_', ' ', $req['status'])) ?>
                                </span>
                            </td>

                            <!-- Actions -->
                            <td>
                                <?php if ($req['status'] === 'awaiting_provider'): ?>
                                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="placement_id" value="<?= $req['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                ✓ Approve
                                            </button>
                                        </form>
                                        <button onclick="showFeedbackModal(<?= $req['id'] ?>)" 
                                                class="btn btn-ghost btn-sm">
                                            💬 Feedback
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <a href="view-placement.php?id=<?= $req['id'] ?>" 
                                       class="btn btn-ghost btn-sm">View</a>
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

<!-- Feedback Modal -->
<div id="feedbackModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);
                                z-index:1000;align-items:center;justify-content:center;">
    <div class="panel" style="width:90%;max-width:500px;margin:0;">
        <div class="panel-header">
            <h3>💬 Provide Feedback</h3>
        </div>
        <div class="panel-body">
            <form method="POST" id="feedbackForm">
                <input type="hidden" name="placement_id" id="feedbackPlacementId">
                <input type="hidden" name="action" value="provide_feedback">
                
                <div class="form-group">
                    <label>Feedback / Comments</label>
                    <textarea name="feedback" class="form-input" rows="5" 
                              placeholder="Share your thoughts about this placement request..." required></textarea>
                    <small style="color:var(--muted);display:block;margin-top:0.5rem;">
                        This will be shared with the student and their tutor.
                    </small>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:1rem;margin-top:1.5rem;">
                    <button type="button" onclick="hideFeedbackModal()" class="btn btn-ghost">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Submit Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showFeedbackModal(placementId) {
    document.getElementById('feedbackPlacementId').value = placementId;
    document.getElementById('feedbackModal').style.display = 'flex';
}

function hideFeedbackModal() {
    document.getElementById('feedbackModal').style.display = 'none';
}

// Close modal on background click
document.getElementById('feedbackModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideFeedbackModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>