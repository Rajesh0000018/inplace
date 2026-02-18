<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth('tutor');

$pageTitle    = 'Tutor Dashboard';
$pageSubtitle = 'Welcome back, ' . explode(' ', authName())[0];
$activePage   = 'dashboard';
$userId       = authId();

// Sidebar badges
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM placements WHERE status IN ('submitted','awaiting_tutor')");
$pendingRequests = (int)$stmt->fetchColumn();

// Stats
$stmt = $pdo->query("SELECT COUNT(*) FROM placements WHERE status IN ('approved','active')");
$totalActive = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM placements WHERE status IN ('submitted','awaiting_tutor','awaiting_provider')");
$totalPending = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM visits WHERE visit_date >= CURDATE() AND status = 'confirmed'");
$upcomingVisits = (int)$stmt->fetchColumn();
?>
<?php include '../includes/header.php'; ?>

<div class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="page-content">

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">👥</span>
                <h3><?= $totalActive ?></h3>
                <p>Students on Placement</p>
                <div class="stat-trend trend-up">Active placements</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">📋</span>
                <h3><?= $totalPending ?></h3>
                <p>Pending Requests</p>
                <div class="stat-trend <?= $totalPending > 0 ? 'trend-neutral' : 'trend-up' ?>">
                    <?= $totalPending > 0 ? 'Requires your attention' : 'All clear!' ?>
                </div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🗓</span>
                <h3><?= $upcomingVisits ?></h3>
                <p>Upcoming Visits</p>
                <div class="stat-trend trend-neutral">Confirmed visits</div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💬</span>
                <h3><?= $unreadCount ?></h3>
                <p>Unread Messages</p>
                <div class="stat-trend trend-neutral">
                    <?= $unreadCount > 0 ? 'From students' : 'All caught up!' ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-action" onclick="window.location='/inplace/tutor/requests.php'">
                <div class="qa-icon">📋</div>
                <div class="qa-label">Auth Requests</div>
                <div class="qa-desc"><?= $pendingRequests ?> pending review</div>
            </div>
            <div class="quick-action" onclick="window.location='/inplace/tutor/all-placements.php'">
                <div class="qa-icon">👥</div>
                <div class="qa-label">All Placements</div>
                <div class="qa-desc"><?= $totalActive ?> active students</div>
            </div>
            <div class="quick-action" onclick="window.location='/inplace/tutor/visits.php'">
                <div class="qa-icon">🗓</div>
                <div class="qa-label">Visit Planner</div>
                <div class="qa-desc">Schedule visits</div>
            </div>
            <div class="quick-action" onclick="window.location='/inplace/tutor/messages.php'">
                <div class="qa-icon">💬</div>
                <div class="qa-label">Messages</div>
                <div class="qa-desc"><?= $unreadCount > 0 ? $unreadCount . ' unread' : 'No new messages' ?></div>
            </div>
        </div>

        <!-- Pending Requests Panel -->
        <?php if ($totalPending > 0): ?>
        <div class="panel">
            <div class="panel-header">
                <h3>Requests Needing Your Attention</h3>
                <a href="/inplace/tutor/requests.php" class="btn btn-primary btn-sm">View All →</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Company</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $stmt = $pdo->query("
                        SELECT p.*, u.full_name AS student_name, u.avatar_initials,
                               c.name AS company_name, c.city
                        FROM placements p
                        JOIN users u ON p.student_id = u.id
                        JOIN companies c ON p.company_id = c.id
                        WHERE p.status IN ('submitted','awaiting_tutor','awaiting_provider')
                        ORDER BY p.created_at ASC
                        LIMIT 5
                    ");
                    foreach ($stmt->fetchAll() as $req):
                        $badgeClass = match($req['status']) {
                            'submitted'         => 'open',
                            'awaiting_provider' => 'pending',
                            'awaiting_tutor'    => 'review',
                            default             => 'pending'
                        };
                    ?>
                    <tr>
                        <td>
                            <div class="avatar-cell">
                                <div class="avatar"><?= htmlspecialchars($req['avatar_initials'] ?? '??') ?></div>
                                <h4><?= htmlspecialchars($req['student_name']) ?></h4>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($req['company_name']) ?>, <?= htmlspecialchars($req['city'] ?? '') ?></td>
                        <td><span class="type-chip"><?= htmlspecialchars($req['role_title'] ?? 'N/A') ?></span></td>
                        <td><span class="badge badge-<?= $badgeClass ?>"><?= ucwords(str_replace('_',' ',$req['status'])) ?></span></td>
                        <td style="font-size:0.875rem;color:var(--muted);"><?= date('d M Y', strtotime($req['created_at'])) ?></td>
                        <td>
                            <a href="/inplace/tutor/requests.php" class="btn btn-primary btn-sm">Review →</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>