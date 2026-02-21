<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth('admin');

$pageTitle = 'System Settings';
$pageSubtitle = 'Configure system-wide settings and preferences';
$activePage = 'settings';
$userId = authId();

$unreadCount = 0;
$pendingRequests = 0;

// Handle settings update
$actionMsg = '';
$actionType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update or insert each setting
        foreach ($_POST as $key => $value) {
            if ($key === 'csrf_token') continue; // Skip CSRF token
            
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        $actionMsg = "✅ Settings saved successfully!";
        $actionType = 'success';
        
    } catch (Exception $e) {
        $actionMsg = "❌ Error saving settings: " . $e->getMessage();
        $actionType = 'danger';
    }
}

// Fetch current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Helper function to get setting with default
function getSetting($settings, $key, $default = '') {
    return $settings[$key] ?? $default;
}
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

        <form method="POST">

            <!-- Academic Year Settings -->
            <div class="panel" style="margin-bottom:2rem;">
                <div class="panel-header">
                    <h3>📅 Academic Year Configuration</h3>
                    <p>Set the current academic year and important dates</p>
                </div>
                <div class="panel-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Academic Year</label>
                            <input type="text" name="academic_year" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'academic_year', '2025/2026')) ?>"
                                   placeholder="e.g., 2025/2026">
                        </div>
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="current_semester" class="form-input">
                                <option value="1" <?= getSetting($settings, 'current_semester')==='1'?'selected':'' ?>>Semester 1</option>
                                <option value="2" <?= getSetting($settings, 'current_semester')==='2'?'selected':'' ?>>Semester 2</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Academic Year Start Date</label>
                            <input type="date" name="academic_year_start" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'academic_year_start', '2025-09-01')) ?>">
                        </div>
                        <div class="form-group">
                            <label>Academic Year End Date</label>
                            <input type="date" name="academic_year_end" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'academic_year_end', '2026-06-30')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Deadlines -->
            <div class="panel" style="margin-bottom:2rem;">
                <div class="panel-header">
                    <h3>📊 Report Deadlines</h3>
                    <p>Configure submission deadlines for placement reports</p>
                </div>
                <div class="panel-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Interim Report Deadline</label>
                            <input type="date" name="interim_report_deadline" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'interim_report_deadline', '2026-01-31')) ?>">
                        </div>
                        <div class="form-group">
                            <label>Final Report Deadline</label>
                            <input type="date" name="final_report_deadline" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'final_report_deadline', '2026-06-30')) ?>">
                        </div>
                        <div class="form-group">
                            <label>Placement Start Window</label>
                            <input type="date" name="placement_start_window" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'placement_start_window', '2025-09-01')) ?>">
                        </div>
                        <div class="form-group">
                            <label>Placement End Window</label>
                            <input type="date" name="placement_end_window" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'placement_end_window', '2026-08-31')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Placement Types -->
            <div class="panel" style="margin-bottom:2rem;">
                <div class="panel-header">
                    <h3>🏢 Placement Types</h3>
                    <p>Define available placement types</p>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label>Allowed Placement Types (comma-separated)</label>
                        <input type="text" name="placement_types" class="form-input"
                               value="<?= htmlspecialchars(getSetting($settings, 'placement_types', 'Full-time,Part-time,Remote,Hybrid')) ?>"
                               placeholder="Full-time, Part-time, Remote, Hybrid">
                        <small style="color:var(--muted);display:block;margin-top:0.5rem;">
                            Separate multiple types with commas
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Minimum Placement Duration (weeks)</label>
                        <input type="number" name="min_placement_weeks" class="form-input"
                               value="<?= htmlspecialchars(getSetting($settings, 'min_placement_weeks', '48')) ?>"
                               min="1" placeholder="48">
                    </div>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="panel" style="margin-bottom:2rem;">
                <div class="panel-header">
                    <h3>🔔 Notification Settings</h3>
                    <p>Configure automatic notifications and reminders</p>
                </div>
                <div class="panel-body">
                    <div class="form-grid">
                        <div class="form-group full-col">
                            <label style="display:flex;align-items:center;gap:0.75rem;">
                                <input type="checkbox" name="enable_email_notifications"
                                       value="1" <?= getSetting($settings, 'enable_email_notifications')==='1'?'checked':'' ?>>
                                Enable Email Notifications
                            </label>
                        </div>
                        <div class="form-group full-col">
                            <label style="display:flex;align-items:center;gap:0.75rem;">
                                <input type="checkbox" name="enable_deadline_reminders"
                                       value="1" <?= getSetting($settings, 'enable_deadline_reminders')==='1'?'checked':'' ?>>
                                Send Deadline Reminders
                            </label>
                        </div>
                        <div class="form-group">
                            <label>Reminder Days Before Deadline</label>
                            <input type="number" name="reminder_days" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'reminder_days', '7')) ?>"
                                   min="1" placeholder="7">
                        </div>
                        <div class="form-group">
                            <label>Admin Notification Email</label>
                            <input type="email" name="admin_email" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'admin_email', 'admin@le.ac.uk')) ?>"
                                   placeholder="admin@le.ac.uk">
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Keys -->
            <div class="panel" style="margin-bottom:2rem;">
                <div class="panel-header">
                    <h3>🔑 API Configuration</h3>
                    <p>Configure external service API keys</p>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label>Google Maps API Key</label>
                        <input type="text" name="google_maps_api_key" class="form-input"
                               value="<?= htmlspecialchars(getSetting($settings, 'google_maps_api_key', '')) ?>"
                               placeholder="Enter Google Maps API Key">
                        <small style="color:var(--muted);display:block;margin-top:0.5rem;">
                            Required for map view features
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Google Calendar API Key</label>
                        <input type="text" name="google_calendar_api_key" class="form-input"
                               value="<?= htmlspecialchars(getSetting($settings, 'google_calendar_api_key', '')) ?>"
                               placeholder="Enter Google Calendar API Key">
                        <small style="color:var(--muted);display:block;margin-top:0.5rem;">
                            Required for calendar integration
                        </small>
                    </div>
                </div>
            </div>

            <!-- System Preferences -->
            <div class="panel" style="margin-bottom:2rem;">
                <div class="panel-header">
                    <h3>⚙️ System Preferences</h3>
                    <p>General system configuration</p>
                </div>
                <div class="panel-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Max File Upload Size (MB)</label>
                            <input type="number" name="max_upload_size" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'max_upload_size', '10')) ?>"
                                   min="1" max="50" placeholder="10">
                        </div>
                        <div class="form-group">
                            <label>Allowed File Types</label>
                            <input type="text" name="allowed_file_types" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'allowed_file_types', 'pdf,docx,xlsx,jpg,png')) ?>"
                                   placeholder="pdf, docx, xlsx, jpg, png">
                        </div>
                        <div class="form-group">
                            <label>Items Per Page</label>
                            <input type="number" name="items_per_page" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'items_per_page', '25')) ?>"
                                   min="5" max="100" placeholder="25">
                        </div>
                        <div class="form-group">
                            <label>Session Timeout (minutes)</label>
                            <input type="number" name="session_timeout" class="form-input"
                                   value="<?= htmlspecialchars(getSetting($settings, 'session_timeout', '120')) ?>"
                                   min="15" placeholder="120">
                        </div>
                        <div class="form-group full-col">
                            <label style="display:flex;align-items:center;gap:0.75rem;">
                                <input type="checkbox" name="maintenance_mode"
                                       value="1" <?= getSetting($settings, 'maintenance_mode')==='1'?'checked':'' ?>>
                                Enable Maintenance Mode (blocks non-admin users)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:1rem;">
                <a href="/inplace/admin/dashboard.php" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">💾 Save Settings</button>
            </div>

        </form>

    </div>
</div>

<?php include '../includes/footer.php'; ?>