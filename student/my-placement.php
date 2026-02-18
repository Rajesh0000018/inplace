<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth('student');   // ← correct function name (NOT require_role)

$pageTitle    = 'My Placement';
$pageSubtitle = 'Welcome back, ' . explode(' ', authName())[0];
$activePage   = 'placement';
$userId       = authId();

// Badge variables sidebar needs
$pendingRequests = 0;

// Unread messages (for sidebar badge)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();

// ── Active placement with company + tutor info ───────────────────
$stmt = $pdo->prepare("
    SELECT
        p.*,
        c.name        AS company_name,
        c.city        AS company_city,
        c.address     AS company_address,
        c.sector      AS company_sector,
        u.full_name   AS tutor_name
    FROM placements p
    JOIN companies c ON p.company_id = c.id
    LEFT JOIN users u ON p.tutor_id = u.id
    WHERE p.student_id = ?
      AND p.status IN ('approved','active')
    ORDER BY p.id DESC
    LIMIT 1
");
$stmt->execute([$userId]);
$placement = $stmt->fetch();

// ── Progress % ───────────────────────────────────────────────────
$progressPct   = 0;
$monthsElapsed = 0;
$monthsTotal   = 0;
if ($placement) {
    $start         = new DateTime($placement['start_date']);
    $end           = new DateTime($placement['end_date']);
    $today         = new DateTime();
    $totalDays     = max(1, $start->diff($end)->days);
    $elapsedDays   = $start->diff($today)->days;
    $progressPct   = min(100, round(($elapsedDays / $totalDays) * 100));
    $monthsElapsed = round($elapsedDays / 30, 1);
    $monthsTotal   = round($totalDays / 30, 1);
}

// ── Documents uploaded by this student ──────────────────────────
$documents = [];
if ($placement) {
    $stmt = $pdo->prepare("
        SELECT * FROM documents
        WHERE placement_id = ? AND uploaded_by = ?
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute([$placement['id'], $userId]);
    $documents = $stmt->fetchAll();
}

// ── Weekly reflections ───────────────────────────────────────────
$reflections = [];
if ($placement) {
    $stmt = $pdo->prepare("
        SELECT * FROM reflections
        WHERE student_id = ? AND placement_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId, $placement['id']]);
    $reflections = $stmt->fetchAll();
}
?>
<?php include '../includes/header.php'; ?>

<div class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="page-content">

        <?php if ($placement): ?>

        <!-- ═══════════════════════════════════════════════════════
             CURRENT PLACEMENT CARD
        ════════════════════════════════════════════════════════ -->
        <div class="panel" style="margin-bottom:1.5rem;">
            <div class="panel-header">
                <div>
                    <h3>Current Placement</h3>
                    <p>Effective from <?= date('j F Y', strtotime($placement['start_date'])) ?></p>
                </div>
                <div style="display:flex;gap:0.75rem;align-items:center;">
                    <span class="badge badge-approved">Approved</span>
                    <button class="btn btn-ghost btn-sm"
                            onclick="document.getElementById('changeModal').style.display='flex'">
                        Request Change
                    </button>
                </div>
            </div>

            <div class="panel-body">
                <!-- Info Grid -->
                <div class="info-grid" style="margin-bottom:2rem;">

                    <div class="info-item">
                        <label>Company</label>
                        <p><?= htmlspecialchars($placement['company_name']) ?></p>
                    </div>

                    <div class="info-item">
                        <label>Role</label>
                        <p><?= htmlspecialchars($placement['role_title'] ?? 'N/A') ?></p>
                    </div>

                    <div class="info-item">
                        <label>Location</label>
                        <p><?= htmlspecialchars($placement['company_city'] ?? 'N/A') ?></p>
                    </div>

                    <div class="info-item">
                        <label>Start Date</label>
                        <p><?= date('j F Y', strtotime($placement['start_date'])) ?></p>
                    </div>

                    <div class="info-item">
                        <label>End Date</label>
                        <p><?= date('j F Y', strtotime($placement['end_date'])) ?></p>
                    </div>

                    <div class="info-item">
                        <label>Salary</label>
                        <p><?= htmlspecialchars($placement['salary'] ?? 'Not specified') ?></p>
                    </div>

                    <div class="info-item">
                        <label>Supervisor</label>
                        <p><?= htmlspecialchars($placement['supervisor_name'] ?? 'N/A') ?></p>
                    </div>

                    <div class="info-item">
                        <label>Supervisor Email</label>
                        <p>
                            <a href="mailto:<?= htmlspecialchars($placement['supervisor_email'] ?? '') ?>"
                               style="color:var(--navy);text-decoration:none;">
                                <?= htmlspecialchars($placement['supervisor_email'] ?? 'N/A') ?>
                            </a>
                        </p>
                    </div>

                    <div class="info-item">
                        <label>Placement Tutor</label>
                        <p><?= htmlspecialchars($placement['tutor_name'] ?? 'Not assigned') ?></p>
                    </div>

                </div><!-- /info-grid -->

                <div class="divider"></div>

                <!-- Placement Progress -->
                <h4 style="font-size:1rem;font-weight:600;margin-bottom:1.25rem;">Placement Progress</h4>
                <div style="display:flex;align-items:center;gap:1.25rem;margin-bottom:0.5rem;">
                    <div style="flex:1;">
                        <div class="progress-bar" style="height:10px;">
                            <div class="progress-fill" style="width:<?= $progressPct ?>%;height:100%;"></div>
                        </div>
                    </div>
                    <div style="font-size:0.9375rem;font-weight:700;color:var(--navy);white-space:nowrap;">
                        <?= $progressPct ?>% Complete
                    </div>
                </div>
                <p style="font-size:0.875rem;color:var(--muted);">
                    <?= $monthsElapsed ?> months completed of <?= $monthsTotal ?>-month placement
                </p>

            </div><!-- /panel-body -->
        </div><!-- /placement panel -->


        <!-- ═══════════════════════════════════════════════════════
             TWO COLUMN: Documents + Reflections
        ════════════════════════════════════════════════════════ -->
        <div class="two-col">

            <!-- ── Documents ───────────────────────────────────── -->
            <div class="panel">
                <div class="panel-header">
                    <h3>Documents</h3>
                    <button class="btn btn-primary btn-sm"
                            onclick="document.getElementById('uploadModal').style.display='flex'">
                        Upload New
                    </button>
                </div>

                <div class="panel-body" style="padding:0;">
                    <?php if (empty($documents)): ?>
                        <div style="text-align:center;padding:3rem 2rem;">
                            <div style="font-size:2.5rem;margin-bottom:0.75rem;">📁</div>
                            <p style="color:var(--muted);">No documents uploaded yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                        <div style="display:flex;align-items:center;gap:1rem;
                                    padding:1.125rem 2rem;
                                    border-bottom:1px solid var(--border);">
                            <div style="width:40px;height:40px;background:var(--info-bg);
                                        border-radius:8px;display:flex;align-items:center;
                                        justify-content:center;font-size:1.25rem;flex-shrink:0;">
                                📄
                            </div>
                            <div style="flex:1;min-width:0;">
                                <p style="font-weight:500;font-size:0.9375rem;
                                          overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars($doc['file_name']) ?>
                                </p>
                                <p style="font-size:0.8125rem;color:var(--muted);">
                                    <?= ucwords(str_replace('_', ' ', $doc['doc_type'])) ?>
                                    · Uploaded <?= date('d M Y', strtotime($doc['uploaded_at'])) ?>
                                </p>
                            </div>
                            <a href="/inplace/assets/uploads/<?= htmlspecialchars($doc['file_path']) ?>"
                               download
                               class="btn btn-ghost btn-sm">
                                ⬇ Download
                            </a>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div><!-- /documents panel -->


            <!-- ── Weekly Reflection Log ───────────────────────── -->
            <div class="panel">
                <div class="panel-header">
                    <h3>Weekly Reflection Log</h3>
                    <button class="btn btn-primary btn-sm"
                            onclick="document.getElementById('reflectionModal').style.display='flex'">
                        + Add Entry
                    </button>
                </div>

                <div class="panel-body" style="padding:0;">
                    <?php if (empty($reflections)): ?>
                        <div style="text-align:center;padding:3rem 2rem;">
                            <div style="font-size:2.5rem;margin-bottom:0.75rem;">📝</div>
                            <p style="color:var(--muted);">No reflections logged yet.<br>Start recording your weekly progress!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reflections as $r): ?>
                        <div style="padding:1.25rem 2rem;border-bottom:1px solid var(--border);">
                            <p style="font-size:0.75rem;font-weight:600;color:var(--muted);
                                      text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">
                                <?= htmlspecialchars($r['week_label']) ?>
                                · <?= date('d M Y', strtotime($r['created_at'])) ?>
                            </p>
                            <p style="font-size:0.9rem;color:var(--text);line-height:1.6;">
                                <?= nl2br(htmlspecialchars($r['content'])) ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div><!-- /reflections panel -->

        </div><!-- /two-col -->


        <?php else: ?>
        <!-- ═══════════════════════════════════════════════════════
             NO PLACEMENT YET
        ════════════════════════════════════════════════════════ -->
        <div class="panel">
            <div class="panel-body" style="text-align:center;padding:4rem 2rem;">
                <div style="font-size:4rem;margin-bottom:1rem;">🏢</div>
                <h3 style="font-family:'Playfair Display',serif;font-size:1.5rem;
                           color:var(--navy);margin-bottom:0.75rem;">
                    No Active Placement
                </h3>
                <p style="color:var(--muted);margin-bottom:2rem;max-width:400px;margin-left:auto;margin-right:auto;">
                    You don't have an approved placement yet. Submit an authorisation
                    request to get started.
                </p>
                <a href="/inplace/student/submit-request.php" class="btn btn-primary">
                    Submit a Request →
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /page-content -->
</div><!-- /main -->


<!-- ═══════════════════════════════════════════════════════════════
     MODAL: Add Reflection
════════════════════════════════════════════════════════════════ -->
<div id="reflectionModal" style="display:none;position:fixed;inset:0;
     background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--white);border-radius:var(--radius);padding:2.5rem;
                width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <h3 style="font-family:'Playfair Display',serif;font-size:1.375rem;
                   color:var(--navy);margin-bottom:1.5rem;">Add Weekly Reflection</h3>
      <form method="POST" action="/inplace/actions/save-reflection.php">
            <div class="form-group" style="margin-bottom:1.25rem;">
                <label>Week Label (e.g. "Week 12 · Mar 18")</label>
                <input type="text" name="week_label"
                       placeholder="Week 12 · Mar 18"
                       style="padding:0.875rem 1rem;border:2px solid var(--border);
                              border-radius:var(--radius-sm);width:100%;font-family:inherit;
                              font-size:0.9375rem;background:var(--cream);">
            </div>
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label>What did you work on this week?</label>
                <textarea name="content" rows="5"
                          placeholder="Describe your tasks, learnings, and challenges..."
                          style="padding:0.875rem 1rem;border:2px solid var(--border);
                                 border-radius:var(--radius-sm);width:100%;font-family:inherit;
                                 font-size:0.9375rem;background:var(--cream);resize:vertical;"></textarea>
            </div>
            <input type="hidden" name="placement_id" value="<?= $placement['id'] ?? '' ?>">
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                <button type="button" class="btn btn-ghost"
                        onclick="document.getElementById('reflectionModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">Save Entry</button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════
     MODAL: Upload Document
════════════════════════════════════════════════════════════════ -->
<div id="uploadModal" style="display:none;position:fixed;inset:0;
     background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--white);border-radius:var(--radius);padding:2.5rem;
                width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <h3 style="font-family:'Playfair Display',serif;font-size:1.375rem;
                   color:var(--navy);margin-bottom:1.5rem;">Upload Document</h3>
        <form method="POST" action="/inplace/student/actions/upload-doc.php"
              enctype="multipart/form-data">
            <div class="form-group" style="margin-bottom:1.25rem;">
                <label>Document Type</label>
                <select name="doc_type"
                        style="padding:0.875rem 1rem;border:2px solid var(--border);
                               border-radius:var(--radius-sm);width:100%;font-family:inherit;
                               font-size:0.9375rem;background:var(--cream);">
                    <option value="offer_letter">Offer Letter</option>
                    <option value="job_description">Job Description</option>
                    <option value="interim_report">Interim Report</option>
                    <option value="final_report">Final Report</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label>File (PDF, max 10 MB)</label>
                <div class="upload-zone"
                     onclick="document.getElementById('fileInput').click()"
                     style="cursor:pointer;">
                    <div style="font-size:2.5rem;margin-bottom:0.5rem;">📎</div>
                    <p><strong>Click to choose file</strong> or drag and drop</p>
                    <p style="font-size:0.8125rem;color:var(--muted);margin-top:0.25rem;">PDF only · max 10 MB</p>
                </div>
                <input id="fileInput" type="file" name="document" accept=".pdf"
                       style="display:none;"
                       onchange="document.getElementById('fileName').textContent = this.files[0]?.name || ''">
                <p id="fileName" style="font-size:0.875rem;color:var(--success);margin-top:0.5rem;"></p>
            </div>
            <input type="hidden" name="placement_id" value="<?= $placement['id'] ?? '' ?>">
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                <button type="button" class="btn btn-ghost"
                        onclick="document.getElementById('uploadModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">Upload →</button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════
     MODAL: Request Change
════════════════════════════════════════════════════════════════ -->
<div id="changeModal" style="display:none;position:fixed;inset:0;
     background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--white);border-radius:var(--radius);padding:2.5rem;
                width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <h3 style="font-family:'Playfair Display',serif;font-size:1.375rem;
                   color:var(--navy);margin-bottom:1.5rem;">Request Placement Change</h3>
        <form method="POST" action="/inplace/student/actions/request-change.php">
            <div class="form-group" style="margin-bottom:1.25rem;">
                <label>Type of Change</label>
                <select name="change_type"
                        style="padding:0.875rem 1rem;border:2px solid var(--border);
                               border-radius:var(--radius-sm);width:100%;font-family:inherit;
                               font-size:0.9375rem;background:var(--cream);">
                    <option value="end_date">Extend End Date</option>
                    <option value="role">Change Role (same company)</option>
                    <option value="supervisor">Change Supervisor</option>
                    <option value="transfer">Transfer to Different Company</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label>Justification / Details</label>
                <textarea name="justification" rows="4"
                          placeholder="Explain why you need this change..."
                          style="padding:0.875rem 1rem;border:2px solid var(--border);
                                 border-radius:var(--radius-sm);width:100%;font-family:inherit;
                                 font-size:0.9375rem;background:var(--cream);resize:vertical;"></textarea>
            </div>
            <input type="hidden" name="placement_id" value="<?= $placement['id'] ?? '' ?>">
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                <button type="button" class="btn btn-ghost"
                        onclick="document.getElementById('changeModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Close modals when clicking outside -->
<script>
['reflectionModal','uploadModal','changeModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});
</script>

<?php include '../includes/footer.php'; ?>