<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth('provider');

$pageTitle = 'Scheduled Visits';
$pageSubtitle = 'Upcoming tutor visits to your workplace';
$activePage = 'visits';
$userId = authId();

// Get provider's company
$stmt = $pdo->prepare("SELECT company_id, full_name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$provider = $stmt->fetch();

// Fetch all visits for this company's placements
$stmt = $pdo->prepare("
    SELECT 
        v.*,
        p.role_title,
        s.full_name AS student_name,
        s.avatar_initials AS student_initials,
        t.full_name AS tutor_name,
        t.avatar_initials AS tutor_initials,
        t.email AS tutor_email,
        c.name AS company_name,
        c.address AS company_address,
        c.city AS company_city
    FROM visits v
    JOIN placements p ON v.placement_id = p.id
    JOIN users s ON p.student_id = s.id
    JOIN users t ON p.tutor_id = t.id
    JOIN companies c ON p.company_id = c.id
    WHERE p.company_id = ?
    ORDER BY v.visit_date ASC, v.visit_time ASC
");
$stmt->execute([$provider['company_id']]);
$allVisits = $stmt->fetchAll();

// Separate into upcoming and past
$upcomingVisits = [];
$pastVisits = [];
$today = date('Y-m-d');

foreach ($allVisits as $visit) {
    if ($visit['visit_date'] >= $today) {
        $upcomingVisits[] = $visit;
    } else {
        $pastVisits[] = $visit;
    }
}

$unreadCount = 0;
$pendingRequests = 0;
?>
<?php include '../includes/header.php'; ?>

<div class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="page-content">

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.25rem;margin-bottom:2rem;">
            
            <div class="panel" style="margin-bottom:0;padding:1.5rem;">
                <p style="font-size:0.75rem;font-weight:600;text-transform:uppercase;
                          letter-spacing:0.05em;color:var(--muted);margin-bottom:0.5rem;">
                    Upcoming Visits
                </p>
                <h3 style="font-family:'Playfair Display',serif;font-size:2rem;color:var(--info);">
                    <?= count($upcomingVisits) ?>
                </h3>
            </div>

            <div class="panel" style="margin-bottom:0;padding:1.5rem;">
                <p style="font-size:0.75rem;font-weight:600;text-transform:uppercase;
                          letter-spacing:0.05em;color:var(--muted);margin-bottom:0.5rem;">
                    This Month
                </p>
                <h3 style="font-family:'Playfair Display',serif;font-size:2rem;color:var(--navy);">
                    <?php
                    $thisMonth = 0;
                    $currentMonth = date('Y-m');
                    foreach ($upcomingVisits as $v) {
                        if (substr($v['visit_date'], 0, 7) === $currentMonth) {
                            $thisMonth++;
                        }
                    }
                    echo $thisMonth;
                    ?>
                </h3>
            </div>

            <div class="panel" style="margin-bottom:0;padding:1.5rem;">
                <p style="font-size:0.75rem;font-weight:600;text-transform:uppercase;
                          letter-spacing:0.05em;color:var(--muted);margin-bottom:0.5rem;">
                    Completed
                </p>
                <h3 style="font-family:'Playfair Display',serif;font-size:2rem;color:var(--success);">
                    <?php
                    $completed = 0;
                    foreach ($pastVisits as $v) {
                        if ($v['status'] === 'completed') $completed++;
                    }
                    echo $completed;
                    ?>
                </h3>
            </div>

        </div>

        <!-- Upcoming Visits -->
        <div class="panel" style="margin-bottom:2rem;">
            <div class="panel-header">
                <h3>📅 Upcoming Visits</h3>
                <p>Scheduled tutor visits to your workplace</p>
            </div>

            <?php if (empty($upcomingVisits)): ?>
            <div style="text-align:center;padding:4rem 2rem;">
                <div style="font-size:3rem;margin-bottom:1rem;">📅</div>
                <p style="color:var(--muted);font-size:1rem;">No upcoming visits scheduled.</p>
            </div>

            <?php else: ?>
            <div class="visit-grid" style="padding:2rem;">
                <?php foreach ($upcomingVisits as $visit): ?>
                <div class="visit-card">
                    
                    <!-- Date Block -->
                    <div class="visit-date-block">
                        <div class="date-box">
                            <div class="day"><?= date('d', strtotime($visit['visit_date'])) ?></div>
                            <div class="month"><?= date('M', strtotime($visit['visit_date'])) ?></div>
                        </div>
                        <div class="visit-date-info">
                            <h4><?= date('l', strtotime($visit['visit_date'])) ?></h4>
                            <p>
                                <?= date('g:i A', strtotime($visit['visit_time'])) ?>
                                <?php if ($visit['duration_hours']): ?>
                                    · <?= $visit['duration_hours'] ?>h
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <!-- Student Info -->
                    <div class="visit-meta">
                        <div class="visit-meta-row">
                            <span>👨‍🎓</span>
                            <strong>Student:</strong>
                            <?= htmlspecialchars($visit['student_name']) ?>
                        </div>
                        <div class="visit-meta-row">
                            <span>👨‍🏫</span>
                            <strong>Tutor:</strong>
                            <?= htmlspecialchars($visit['tutor_name']) ?>
                        </div>
                        <div class="visit-meta-row">
                            <span>💼</span>
                            <strong>Role:</strong>
                            <?= htmlspecialchars($visit['role_title']) ?>
                        </div>
                        <?php if ($visit['location']): ?>
                        <div class="visit-meta-row">
                            <span>📍</span>
                            <strong>Location:</strong>
                            <?= htmlspecialchars($visit['location']) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Purpose -->
                    <?php if ($visit['purpose']): ?>
                    <div style="padding:0.875rem;background:var(--cream);
                                border-radius:var(--radius-sm);margin-bottom:1rem;">
                        <p style="font-size:0.8125rem;color:var(--text);line-height:1.5;">
                            <strong>Purpose:</strong><br>
                            <?= nl2br(htmlspecialchars($visit['purpose'])) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- Status Badge -->
                    <?php
                    $statusBadge = match($visit['status']) {
                        'scheduled' => ['pending', 'Scheduled'],
                        'confirmed' => ['approved', 'Confirmed'],
                        'completed' => ['approved', 'Completed'],
                        'cancelled' => ['rejected', 'Cancelled'],
                        default => ['open', ucfirst($visit['status'])]
                    };
                    ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="badge badge-<?= $statusBadge[0] ?>">
                            <?= $statusBadge[1] ?>
                        </span>
                        
                        <a href="mailto:<?= htmlspecialchars($visit['tutor_email']) ?>" 
                           class="btn btn-ghost btn-sm">
                            📧 Contact Tutor
                        </a>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Past Visits -->
        <?php if (!empty($pastVisits)): ?>
        <div class="panel">
            <div class="panel-header">
                <h3>📋 Past Visits</h3>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Tutor</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($pastVisits, 0, 10) as $visit): ?>
                        <tr>
                            <td style="font-family:'DM Mono',monospace;font-size:0.875rem;">
                                <?= date('M j, Y', strtotime($visit['visit_date'])) ?><br>
                                <span style="color:var(--muted);font-size:0.75rem;">
                                    <?= date('g:i A', strtotime($visit['visit_time'])) ?>
                                </span>
                            </td>
                            
                            <td>
                                <div class="avatar-cell">
                                    <div class="avatar" style="width:32px;height:32px;">
                                        <?= htmlspecialchars($visit['student_initials']) ?>
                                    </div>
                                    <div>
                                        <h4 style="font-size:0.875rem;">
                                            <?= htmlspecialchars($visit['student_name']) ?>
                                        </h4>
                                    </div>
                                </div>
                            </td>

                            <td style="font-size:0.875rem;">
                                <?= htmlspecialchars($visit['tutor_name']) ?>
                            </td>

                            <td style="max-width:200px;">
                                <p style="font-size:0.8125rem;color:var(--muted);
                                          overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars($visit['purpose'] ?? 'General check-in') ?>
                                </p>
                            </td>

                            <td>
                                <?php
                                $statusBadge = match($visit['status']) {
                                    'completed' => ['approved', 'Completed'],
                                    'cancelled' => ['rejected', 'Cancelled'],
                                    default => ['open', ucfirst($visit['status'])]
                                };
                                ?>
                                <span class="badge badge-<?= $statusBadge[0] ?>">
                                    <?= $statusBadge[1] ?>
                                </span>
                            </td>

                            <td>
                                <a href="view-visit.php?id=<?= $visit['id'] ?>" 
                                   class="btn btn-ghost btn-sm">
                                    View Details
                                </a>
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