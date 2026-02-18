<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth('tutor');

$pageTitle    = 'Schedule Visit';
$pageSubtitle = 'Schedule a new placement visit';
$activePage   = 'visits';
$userId       = authId();

// Sidebar badges
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM placements WHERE status IN ('submitted','awaiting_tutor')");
$pendingRequests = (int)$stmt->fetchColumn();

// ── Handle form submission ───────────────────────────────────────
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $placementId = (int)$_POST['placement_id'];
        $visitDate   = $_POST['visit_date'];
        $visitTime   = $_POST['visit_time'];
        $type        = $_POST['type'];
        $location    = trim($_POST['location'] ?? '');
        $meetingLink = trim($_POST['meeting_link'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');

        // Insert visit
        $stmt = $pdo->prepare("
            INSERT INTO visits
                (placement_id, tutor_id, visit_date, visit_time, type, location, meeting_link, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'proposed')
        ");
        $stmt->execute([$placementId, $userId, $visitDate, $visitTime, $type, $location, $meetingLink, $notes]);

        // Notify student
        $stmt = $pdo->prepare("SELECT student_id FROM placements WHERE id = ?");
        $stmt->execute([$placementId]);
        $row = $stmt->fetch();
        
        if ($row) {
            $msg = "📅 Your placement tutor has proposed a visit on " . date('d M Y', strtotime($visitDate)) . " at " . date('g:i A', strtotime($visitTime)) . ". Please confirm or request a reschedule.";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'visit_proposed', ?)");
            $stmt->execute([$row['student_id'], $msg]);
        }

        $success = "Visit scheduled successfully! Student has been notified.";

    } catch (Exception $e) {
        $error = "Failed to schedule visit: " . $e->getMessage();
    }
}

// ── Fetch all active placements for dropdown ─────────────────────
$stmt = $pdo->query("
    SELECT
        p.id,
        u.full_name AS student_name,
        c.name      AS company_name,
        c.city
    FROM placements p
    JOIN users u     ON p.student_id = u.id
    JOIN companies c ON p.company_id = c.id
    WHERE p.status IN ('approved','active')
    ORDER BY u.full_name ASC
");
$placements = $stmt->fetchAll();
?>
<?php include '../includes/header.php'; ?>

<div class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="page-content">

        <?php if ($success): ?>
        <div style="background:var(--success-bg);border:1px solid #6ee7b7;border-radius:var(--radius);
                    padding:1.25rem 2rem;margin-bottom:1.5rem;">
            <p style="color:var(--success);font-weight:500;">✅ <?= htmlspecialchars($success) ?></p>
            <a href="/inplace/tutor/visits.php" class="btn btn-success btn-sm" style="margin-top:0.75rem;">
                View All Visits →
            </a>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div style="background:var(--danger-bg);border:1px solid #fca5a5;border-radius:var(--radius);
                    padding:1.25rem 2rem;margin-bottom:1.5rem;">
            <p style="color:var(--danger);font-weight:500;">⚠️ <?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════════════
             SCHEDULE FORM
        ════════════════════════════════════════════════════════ -->
        <div class="panel">
            <div class="panel-header">
                <h3>Schedule New Visit</h3>
                <p>Schedule a placement visit with one of your students</p>
            </div>

            <div class="panel-body">
                <form method="POST">

                    <div class="form-grid" style="margin-bottom:2rem;">

                        <div class="form-group full-col">
                            <label>Select Student Placement <span style="color:var(--danger);">*</span></label>
                            <select name="placement_id" required
                                    style="padding:0.875rem 1rem;border:2px solid var(--border);
                                           border-radius:var(--radius-sm);width:100%;font-family:inherit;
                                           font-size:0.9375rem;background:var(--cream);">
                                <option value="">-- Choose a student --</option>
                                <?php foreach ($placements as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['student_name']) ?>
                                    — <?= htmlspecialchars($p['company_name']) ?>,
                                    <?= htmlspecialchars($p['city']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Visit Date <span style="color:var(--danger);">*</span></label>
                            <input type="date" name="visit_date" required
                                   min="<?= date('Y-m-d') ?>"
                                   value="<?= htmlspecialchars($_POST['visit_date'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Visit Time <span style="color:var(--danger);">*</span></label>
                            <input type="time" name="visit_time" required
                                   value="<?= htmlspecialchars($_POST['visit_time'] ?? '14:00') ?>">
                        </div>

                        <div class="form-group full-col">
                            <label>Visit Type <span style="color:var(--danger);">*</span></label>
                            <select name="type" required id="visitType" onchange="toggleVisitFields()">
                                <option value="physical">📍 Physical (In-person at company)</option>
                                <option value="virtual">🖥 Virtual (Online meeting)</option>
                            </select>
                        </div>

                        <div class="form-group full-col" id="locationField">
                            <label>Location / Company Address</label>
                            <input type="text" name="location"
                                   placeholder="e.g., Rolls-Royce plc, Moor Lane, Derby"
                                   value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                        </div>

                        <div class="form-group full-col" id="meetingLinkField" style="display:none;">
                            <label>Meeting Link (Teams / Zoom)</label>
                            <input type="url" name="meeting_link"
                                   placeholder="https://teams.microsoft.com/..."
                                   value="<?= htmlspecialchars($_POST['meeting_link'] ?? '') ?>">
                        </div>

                        <div class="form-group full-col">
                            <label>Notes / Agenda</label>
                            <textarea name="notes" rows="4"
                                      placeholder="What will be discussed during this visit?"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>

                    </div>

                    <div class="divider"></div>

                    <div style="display:flex;justify-content:flex-end;gap:1rem;margin-top:1.5rem;">
                        <a href="/inplace/tutor/visits.php" class="btn btn-ghost">← Back</a>
                        <button type="submit" class="btn btn-primary">Schedule Visit →</button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>

<script>
function toggleVisitFields() {
    const type = document.getElementById('visitType').value;
    const locationField = document.getElementById('locationField');
    const meetingLinkField = document.getElementById('meetingLinkField');
    
    if (type === 'virtual') {
        locationField.style.display = 'none';
        meetingLinkField.style.display = 'block';
    } else {
        locationField.style.display = 'block';
        meetingLinkField.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>