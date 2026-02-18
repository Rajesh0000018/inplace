<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth('student');   // ← correct function name

$pageTitle    = 'Submit Request';
$pageSubtitle = 'New Placement Authorisation Request';
$activePage   = 'request';
$userId       = authId();

// Sidebar badge variables
$pendingRequests = 0;

// Unread messages for sidebar badge
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();

// ── Handle form submission ────────────────────────────────────────
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Insert or find company
        $stmt = $pdo->prepare("
            INSERT INTO companies (name, address, city, sector, contact_name, contact_email, contact_phone)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($_POST['company_name']    ?? ''),
            trim($_POST['company_address'] ?? ''),
            trim($_POST['company_city']    ?? ''),
            trim($_POST['sector']          ?? ''),
            trim($_POST['supervisor_name'] ?? ''),
            trim($_POST['supervisor_email']?? ''),
            trim($_POST['supervisor_phone']?? ''),
        ]);
        $companyId = $pdo->lastInsertId();

        // 2. Insert placement
        $stmt = $pdo->prepare("
            INSERT INTO placements
                (student_id, company_id, role_title, job_description,
                 start_date, end_date, salary, working_pattern,
                 supervisor_name, supervisor_email, supervisor_phone, status)
            VALUES (?,?,?,?, ?,?,?,?, ?,?,?,'submitted')
        ");
        $stmt->execute([
            $userId,
            $companyId,
            trim($_POST['role_title']       ?? ''),
            trim($_POST['job_description']  ?? ''),
            $_POST['start_date']            ?? '',
            $_POST['end_date']              ?? '',
            trim($_POST['salary']           ?? ''),
            trim($_POST['working_pattern']  ?? ''),
            trim($_POST['supervisor_name']  ?? ''),
            trim($_POST['supervisor_email'] ?? ''),
            trim($_POST['supervisor_phone'] ?? ''),
        ]);
        $placementId = $pdo->lastInsertId();

        // 3. Handle file uploads
        if (!empty($_FILES['documents']['name'][0])) {
            foreach ($_FILES['documents']['tmp_name'] as $i => $tmp) {
                if (!$tmp) continue;
                $original = $_FILES['documents']['name'][$i];
                $safe     = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
                $dest     = '../assets/uploads/' . $safe;
                if (move_uploaded_file($tmp, $dest)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO documents
                            (placement_id, uploaded_by, doc_type, file_name, file_path, file_size)
                        VALUES (?, ?, 'offer_letter', ?, ?, ?)
                    ");
                    $size = round($_FILES['documents']['size'][$i] / 1024) . ' KB';
                    $stmt->execute([$placementId, $userId, $original, $safe, $size]);
                }
            }
        }

        // 4. Audit log
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, action, table_affected, record_id, ip_address)
            VALUES (?, 'submitted_placement_request', 'placements', ?, ?)
        ");
        $stmt->execute([$userId, $placementId, $_SERVER['REMOTE_ADDR'] ?? '']);

        $success = "Your placement request has been submitted successfully! The placement provider will be notified to confirm the details.";

    } catch (Exception $e) {
        $error = "Something went wrong. Please try again. (" . $e->getMessage() . ")";
    }
}

// ── Check if student already has an active/pending placement ────
$stmt = $pdo->prepare("
    SELECT id, status FROM placements
    WHERE student_id = ?
      AND status NOT IN ('rejected','terminated')
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$userId]);
$existingPlacement = $stmt->fetch();
?>
<?php include '../includes/header.php'; ?>

<div class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="page-content">

        <?php if ($success): ?>
        <!-- ── Success Banner ─────────────────────────────────── -->
        <div style="background:var(--success-bg);border:1px solid #6ee7b7;border-radius:var(--radius);
                    padding:1.5rem 2rem;margin-bottom:2rem;display:flex;align-items:center;gap:1rem;">
            <span style="font-size:1.75rem;">🎉</span>
            <div>
                <p style="font-weight:600;color:var(--success);margin-bottom:0.25rem;">Request Submitted!</p>
                <p style="font-size:0.9rem;color:var(--success);"><?= htmlspecialchars($success) ?></p>
            </div>
            <a href="/inplace/student/dashboard.php" class="btn btn-success btn-sm" style="margin-left:auto;">
                Back to Dashboard →
            </a>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div style="background:var(--danger-bg);border:1px solid #fca5a5;border-radius:var(--radius);
                    padding:1.25rem 2rem;margin-bottom:2rem;">
            <p style="color:var(--danger);font-weight:500;">⚠️ <?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($existingPlacement && !in_array($existingPlacement['status'], ['rejected','terminated'])): ?>
        <!-- ── Warning: already has placement ────────────────── -->
        <div style="background:var(--warning-bg);border:1px solid #fcd34d;border-radius:var(--radius);
                    padding:1.25rem 2rem;margin-bottom:2rem;display:flex;align-items:center;gap:1rem;">
            <span style="font-size:1.5rem;">⚠️</span>
            <div>
                <p style="font-weight:600;color:var(--warning);">You already have a placement request</p>
                <p style="font-size:0.875rem;color:var(--warning);">
                    Status: <strong><?= ucwords(str_replace('_', ' ', $existingPlacement['status'])) ?></strong>.
                    Submitting a new one will create an additional request.
                </p>
            </div>
        </div>
        <?php endif; ?>


        <!-- ═══════════════════════════════════════════════════════
             MAIN FORM PANEL
        ════════════════════════════════════════════════════════ -->
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h3>New Placement Authorisation Request</h3>
                    <p>All fields marked * are required. The provider will be asked to confirm the details.</p>
                </div>
                <span class="badge badge-pending">Draft</span>
            </div>

            <div class="panel-body">
                <form method="POST" enctype="multipart/form-data">

                    <!-- ── SECTION 1: Company & Role ─────────── -->
                    <div style="font-size:0.8125rem;font-weight:700;text-transform:uppercase;
                                letter-spacing:0.08em;color:var(--muted);margin-bottom:1.25rem;
                                padding-bottom:0.75rem;border-bottom:2px solid var(--border);">
                        1 · Company &amp; Role Information
                    </div>

                    <div class="form-grid" style="margin-bottom:2rem;">

                        <div class="form-group">
                            <label>Company Name <span style="color:var(--danger);">*</span></label>
                            <input type="text" name="company_name" required
                                   placeholder="e.g., Rolls-Royce plc"
                                   value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Company City / Town <span style="color:var(--danger);">*</span></label>
                            <input type="text" name="company_city" required
                                   placeholder="e.g., Derby"
                                   value="<?= htmlspecialchars($_POST['company_city'] ?? '') ?>">
                        </div>

                        <div class="form-group full-col">
                            <label>Full Company Address</label>
                            <input type="text" name="company_address"
                                   placeholder="Street, City, Postcode"
                                   value="<?= htmlspecialchars($_POST['company_address'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Industry / Sector</label>
                            <select name="sector">
                                <option value="">Select sector</option>
                                <?php
                                $sectors = [
                                    'Technology & Software',
                                    'Engineering & Manufacturing',
                                    'Finance & Banking',
                                    'Healthcare & Life Sciences',
                                    'Consultancy',
                                    'Media & Communications',
                                    'Retail & E-commerce',
                                    'Public Sector / Government',
                                    'Education & Research',
                                    'Other',
                                ];
                                foreach ($sectors as $s) {
                                    $sel = (($_POST['sector'] ?? '') === $s) ? 'selected' : '';
                                    echo "<option value=\"$s\" $sel>" . htmlspecialchars($s) . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Role / Job Title <span style="color:var(--danger);">*</span></label>
                            <input type="text" name="role_title" required
                                   placeholder="e.g., Software Engineering Intern"
                                   value="<?= htmlspecialchars($_POST['role_title'] ?? '') ?>">
                        </div>

                        <div class="form-group full-col">
                            <label>Job Description <span style="color:var(--danger);">*</span></label>
                            <textarea name="job_description" required rows="4"
                                      placeholder="Describe the role, responsibilities, technologies, and skills involved..."><?= htmlspecialchars($_POST['job_description'] ?? '') ?></textarea>
                        </div>

                    </div>


                    <!-- ── SECTION 2: Placement Dates & Salary ── -->
                    <div style="font-size:0.8125rem;font-weight:700;text-transform:uppercase;
                                letter-spacing:0.08em;color:var(--muted);margin-bottom:1.25rem;
                                padding-bottom:0.75rem;border-bottom:2px solid var(--border);">
                        2 · Placement Dates &amp; Terms
                    </div>

                    <div class="form-grid" style="margin-bottom:2rem;">

                        <div class="form-group">
                            <label>Start Date <span style="color:var(--danger);">*</span></label>
                            <input type="date" name="start_date" required
                                   value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>End Date <span style="color:var(--danger);">*</span></label>
                            <input type="date" name="end_date" required
                                   value="<?= htmlspecialchars($_POST['end_date'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Salary (Annual)</label>
                            <input type="text" name="salary"
                                   placeholder="e.g., £22,000"
                                   value="<?= htmlspecialchars($_POST['salary'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Working Pattern</label>
                            <select name="working_pattern">
                                <option value="Full-time (37.5 hrs/week)">Full-time (37.5 hrs/week)</option>
                                <option value="Full-time (40 hrs/week)">Full-time (40 hrs/week)</option>
                                <option value="Hybrid">Hybrid (office + remote)</option>
                                <option value="Remote">Fully Remote</option>
                                <option value="Part-time">Part-time</option>
                            </select>
                        </div>

                    </div>


                    <!-- ── SECTION 3: Supervisor Details ────────── -->
                    <div style="font-size:0.8125rem;font-weight:700;text-transform:uppercase;
                                letter-spacing:0.08em;color:var(--muted);margin-bottom:1.25rem;
                                padding-bottom:0.75rem;border-bottom:2px solid var(--border);">
                        3 · Supervisor Details
                    </div>

                    <div class="form-grid" style="margin-bottom:2rem;">

                        <div class="form-group">
                            <label>Supervisor Full Name <span style="color:var(--danger);">*</span></label>
                            <input type="text" name="supervisor_name" required
                                   placeholder="e.g., Mark Henderson"
                                   value="<?= htmlspecialchars($_POST['supervisor_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Supervisor Job Title</label>
                            <input type="text" name="supervisor_job_title"
                                   placeholder="e.g., Engineering Manager"
                                   value="<?= htmlspecialchars($_POST['supervisor_job_title'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Supervisor Email <span style="color:var(--danger);">*</span></label>
                            <input type="email" name="supervisor_email" required
                                   placeholder="supervisor@company.com"
                                   value="<?= htmlspecialchars($_POST['supervisor_email'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Supervisor Phone</label>
                            <input type="tel" name="supervisor_phone"
                                   placeholder="+44 7700 000000"
                                   value="<?= htmlspecialchars($_POST['supervisor_phone'] ?? '') ?>">
                        </div>

                    </div>


                    <!-- ── SECTION 4: Documents ──────────────────── -->
                    <div style="font-size:0.8125rem;font-weight:700;text-transform:uppercase;
                                letter-spacing:0.08em;color:var(--muted);margin-bottom:1.25rem;
                                padding-bottom:0.75rem;border-bottom:2px solid var(--border);">
                        4 · Supporting Documents
                    </div>

                    <div style="margin-bottom:2rem;">
                        <div class="upload-zone"
                             onclick="document.getElementById('docInput').click()"
                             id="dropZone">
                            <div style="font-size:2.5rem;margin-bottom:0.75rem;">📎</div>
                            <p><strong>Click to upload</strong> or drag and drop</p>
                            <p style="font-size:0.8125rem;margin-top:0.25rem;color:var(--muted);">
                                Offer letter, job description PDF (max 10 MB each)
                            </p>
                        </div>
                        <input id="docInput" type="file" name="documents[]"
                               multiple accept=".pdf,.doc,.docx"
                               style="display:none;"
                               onchange="showFiles(this)">
                        <div id="fileList" style="margin-top:0.875rem;display:flex;flex-direction:column;gap:0.5rem;"></div>
                    </div>


                    <!-- ── Form Actions ───────────────────────────── -->
                    <div class="divider"></div>
                    <div style="display:flex;justify-content:flex-end;gap:1rem;margin-top:1.5rem;">
                        <a href="/inplace/student/dashboard.php" class="btn btn-ghost">
                            ← Back
                        </a>
                        <button type="submit" name="action" value="draft" class="btn btn-ghost">
                            Save as Draft
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Submit Request →
                        </button>
                    </div>

                </form>
            </div><!-- /panel-body -->
        </div><!-- /panel -->

    </div><!-- /page-content -->
</div><!-- /main -->

<script>
// Show selected file names under the upload zone
function showFiles(input) {
    const list = document.getElementById('fileList');
    list.innerHTML = '';
    Array.from(input.files).forEach(f => {
        const div = document.createElement('div');
        div.style.cssText = 'display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1rem;' +
                            'background:var(--success-bg);border-radius:8px;border:1px solid #6ee7b7;';
        div.innerHTML = `<span style="font-size:1.25rem;">📄</span>
                         <span style="font-size:0.875rem;font-weight:500;color:var(--success);">${f.name}</span>
                         <span style="font-size:0.8125rem;color:var(--muted);margin-left:auto;">${(f.size/1024).toFixed(0)} KB</span>`;
        list.appendChild(div);
    });
}

// Drag and drop support
const zone = document.getElementById('dropZone');
if (zone) {
    zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.style.borderColor = 'var(--navy)';
        zone.style.background  = '#f0f2f7';
    });
    zone.addEventListener('dragleave', () => {
        zone.style.borderColor = 'var(--border)';
        zone.style.background  = 'var(--cream)';
    });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.style.borderColor = 'var(--border)';
        zone.style.background  = 'var(--cream)';
        const input = document.getElementById('docInput');
        input.files = e.dataTransfer.files;
        showFiles(input);
    });
}

// Date validation: end must be after start
document.querySelector('form').addEventListener('submit', function(e) {
    const start = document.querySelector('[name="start_date"]').value;
    const end   = document.querySelector('[name="end_date"]').value;
    if (start && end && end <= start) {
        e.preventDefault();
        alert('End date must be after start date.');
    }
});
</script>

<?php include '../includes/footer.php'; ?>