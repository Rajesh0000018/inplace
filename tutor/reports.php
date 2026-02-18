<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth('tutor');

$pageTitle    = 'Reports';
$pageSubtitle = 'View student placement progress (reflections + visits)';
$activePage   = 'reports';
$userId       = authId();

// Sidebar badges
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM placements WHERE LOWER(status) IN ('submitted','awaiting_tutor')");
$pendingRequests = (int)$stmt->fetchColumn();

// Filters
$filterStudent = trim($_GET['student'] ?? '');
$filterCompany = trim($_GET['company'] ?? '');
$filterStatus  = trim($_GET['status'] ?? '');

$where = ["LOWER(p.status) IN ('approved','active','completed')"];
$params = [];

if ($filterStudent !== '') {
  $where[] = "u.full_name LIKE ?";
  $params[] = "%$filterStudent%";
}
if ($filterCompany !== '') {
  $where[] = "c.name LIKE ?";
  $params[] = "%$filterCompany%";
}
if ($filterStatus !== '') {
  $where[] = "LOWER(p.status) = ?";
  $params[] = strtolower($filterStatus);
}

$whereSQL = "WHERE " . implode(" AND ", $where);

// Table rows (server-side)
$stmt = $pdo->prepare("
  SELECT
    p.id AS placement_id,
    p.student_id,
    p.company_id,
    p.role_title,
    p.start_date,
    p.end_date,
    p.status,

    u.full_name AS student_name,
    u.email     AS student_email,
    u.avatar_initials AS student_initials,

    c.name   AS company_name,
    c.city   AS company_city,

    (SELECT COUNT(*) FROM reflections r WHERE r.placement_id = p.id) AS reflections_count,
    (SELECT MAX(r.created_at) FROM reflections r WHERE r.placement_id = p.id) AS last_reflection_at,

    (SELECT COUNT(*) FROM visits v WHERE v.placement_id = p.id) AS visits_count,
    (SELECT MAX(CONCAT(v.visit_date,' ',v.visit_time)) FROM visits v WHERE v.placement_id = p.id) AS last_visit_at

  FROM placements p
  JOIN users u     ON u.id = p.student_id
  JOIN companies c ON c.id = p.company_id
  $whereSQL
  ORDER BY p.start_date DESC, u.full_name ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function badgeClass($status) {
  $s = strtolower($status ?? '');
  return match($s) {
    'approved','active','completed' => 'approved',
    'terminated','rejected'         => 'rejected',
    default                         => 'pending'
  };
}
function fmtDateTime($dt) {
  if (!$dt) return '—';
  $ts = strtotime($dt);
  if (!$ts) return '—';
  return date('d M Y, g:i A', $ts);
}
?>
<?php include '../includes/header.php'; ?>

<div class="main">
  <?php include '../includes/topbar.php'; ?>

  <div class="page-content">

    <!-- Filter bar -->
    <form method="GET" class="filter-bar" style="margin-bottom:1.25rem;">
      <input type="text" name="student" value="<?= htmlspecialchars($filterStudent) ?>"
             placeholder="🔎 Student name...">
      <input type="text" name="company" value="<?= htmlspecialchars($filterCompany) ?>"
             placeholder="🏢 Company name...">

      <select name="status" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="approved"  <?= strtolower($filterStatus)==='approved'?'selected':'' ?>>Approved</option>
        <option value="active"    <?= strtolower($filterStatus)==='active'?'selected':'' ?>>Active</option>
        <option value="completed" <?= strtolower($filterStatus)==='completed'?'selected':'' ?>>Completed</option>
      </select>

      <div style="margin-left:auto;display:flex;gap:0.75rem;align-items:center;">
        <small id="liveStatus" style="color:var(--muted);">Live: loading…</small>
        <?php if ($filterStudent || $filterCompany || $filterStatus): ?>
          <a href="reports.php" class="btn btn-ghost btn-sm">✕ Clear</a>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm" type="submit">Search</button>
      </div>
    </form>

    <!-- KPI ROW (AJAX will fill) -->
    <div style="display:grid;grid-template-columns:repeat(4,minmax(220px,1fr));gap:1rem;margin-bottom:1.25rem;">
      <div class="panel" style="margin-bottom:0;padding:1.1rem 1.25rem;">
        <p style="font-size:0.75rem;color:var(--muted);text-transform:uppercase;font-weight:700;letter-spacing:.05em;">Total Placements</p>
        <div id="kpiPlacements" style="font-size:1.6rem;font-weight:800;color:var(--navy);">—</div>
      </div>
      <div class="panel" style="margin-bottom:0;padding:1.1rem 1.25rem;">
        <p style="font-size:0.75rem;color:var(--muted);text-transform:uppercase;font-weight:700;letter-spacing:.05em;">Total Reflections</p>
        <div id="kpiReflections" style="font-size:1.6rem;font-weight:800;color:var(--navy);">—</div>
      </div>
      <div class="panel" style="margin-bottom:0;padding:1.1rem 1.25rem;">
        <p style="font-size:0.75rem;color:var(--muted);text-transform:uppercase;font-weight:700;letter-spacing:.05em;">Total Visits</p>
        <div id="kpiVisits" style="font-size:1.6rem;font-weight:800;color:var(--navy);">—</div>
      </div>
      <div class="panel" style="margin-bottom:0;padding:1.1rem 1.25rem;">
        <p style="font-size:0.75rem;color:var(--muted);text-transform:uppercase;font-weight:700;letter-spacing:.05em;">Avg Reflections / Placement</p>
        <div id="kpiAvg" style="font-size:1.6rem;font-weight:800;color:var(--navy);">—</div>
      </div>
    </div>

    <!-- CHARTS (AJAX will fill) -->
    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:1rem;margin-bottom:1.25rem;">
      <div class="panel" style="margin-bottom:0;">
        <div class="panel-header"><h3 style="font-size:1rem;">Placements by Status</h3></div>
        <div class="panel-body"><canvas id="chartStatus" height="140"></canvas></div>
      </div>

      <div class="panel" style="margin-bottom:0;">
        <div class="panel-header"><h3 style="font-size:1rem;">Placements by City (Top 8)</h3></div>
        <div class="panel-body"><canvas id="chartCity" height="140"></canvas></div>
      </div>

      <div class="panel" style="margin-bottom:0;">
        <div class="panel-header"><h3 style="font-size:1rem;">Reflections Trend (Last 12 Weeks)</h3></div>
        <div class="panel-body"><canvas id="chartRefTrend" height="140"></canvas></div>
      </div>

      <div class="panel" style="margin-bottom:0;">
        <div class="panel-header"><h3 style="font-size:1rem;">Visits by Type</h3></div>
        <div class="panel-body"><canvas id="chartVisitType" height="140"></canvas></div>
      </div>
    </div>

    <!-- TABLE (server side) -->
    <div class="panel">
      <div class="panel-header">
        <div>
          <h3><?= count($rows) ?> Report<?= count($rows)!==1?'s':'' ?></h3>
          <p>Student progress based on reflections + visits</p>
        </div>
      </div>

      <?php if (empty($rows)): ?>
        <div class="panel-body" style="text-align:center;padding:3rem 2rem;">
          <div style="font-size:2.5rem;margin-bottom:0.75rem;">📄</div>
          <p style="color:var(--muted);">No records found.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Company</th>
                <th>Status</th>
                <th>Reflections</th>
                <th>Visits</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
              <tr>
                <td>
                  <div class="avatar-cell">
                    <div class="avatar"><?= htmlspecialchars($r['student_initials'] ?? '??') ?></div>
                    <div>
                      <h4><?= htmlspecialchars($r['student_name']) ?></h4>
                      <p><?= htmlspecialchars($r['student_email']) ?></p>
                    </div>
                  </div>
                </td>

                <td>
                  <div style="font-weight:600;"><?= htmlspecialchars($r['company_name']) ?></div>
                  <div style="font-size:0.82rem;color:var(--muted);">
                    <?= htmlspecialchars($r['company_city'] ?? 'N/A') ?>
                    <?= $r['role_title'] ? ' · ' . htmlspecialchars($r['role_title']) : '' ?>
                  </div>
                </td>

                <td>
                  <span class="badge badge-<?= badgeClass($r['status']) ?>">
                    <?= htmlspecialchars(ucfirst(strtolower($r['status'] ?? ''))) ?>
                  </span>
                </td>

                <td>
                  <div style="font-weight:700;"><?= (int)$r['reflections_count'] ?></div>
                  <div style="font-size:0.8rem;color:var(--muted);">
                    Last: <?= htmlspecialchars(fmtDateTime($r['last_reflection_at'])) ?>
                  </div>
                </td>

                <td>
                  <div style="font-weight:700;"><?= (int)$r['visits_count'] ?></div>
                  <div style="font-size:0.8rem;color:var(--muted);">
                    Last: <?= htmlspecialchars(fmtDateTime($r['last_visit_at'])) ?>
                  </div>
                </td>

                <td>
                  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <a class="btn btn-ghost btn-sm"
                       href="/inplace/tutor/all-placements.php#detail-<?= (int)$r['placement_id'] ?>">
                      View
                    </a>
                    <a class="btn btn-primary btn-sm"
                       href="/inplace/tutor/schedule-visit.php?placement_id=<?= (int)$r['placement_id'] ?>">
                      🗓 Visit
                    </a>
                    <a class="btn btn-ghost btn-sm"
                       href="/inplace/tutor/messages.php?student_id=<?= (int)$r['student_id'] ?>">
                      💬 Message
                    </a>
                  </div>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const charts = {}; // store Chart.js instances

function toLabelsAndValues(arr, labelKey, valueKey) {
  return {
    labels: (arr || []).map(x => (x[labelKey] ?? 'Unknown')),
    values: (arr || []).map(x => Number(x[valueKey] ?? 0))
  };
}

function upsertChart(id, type, labels, values, options = {}) {
  const el = document.getElementById(id);
  if (!el) return;

  if (!charts[id]) {
    charts[id] = new Chart(el, {
      type,
      data: { labels, datasets: [{ data: values }] },
      options: Object.assign({ responsive: true }, options)
    });
    return;
  }

  charts[id].data.labels = labels;
  charts[id].data.datasets[0].data = values;
  charts[id].update();
}

function buildUrlWithCurrentFilters() {
  const params = new URLSearchParams(window.location.search);
  // Keep student/company/status from URL (server uses same)
  return '/inplace/tutor/api/reports-metrics.php?' + params.toString();
}

async function refreshMetrics() {
  const statusEl = document.getElementById('liveStatus');
  try {
    const url = buildUrlWithCurrentFilters();
    const res = await fetch(url, { cache: 'no-store' });
    const data = await res.json();

    if (!data.ok) throw new Error('API returned not ok');

    // KPIs
    document.getElementById('kpiPlacements').textContent  = data.kpis.totalPlacements ?? '—';
    document.getElementById('kpiReflections').textContent = data.kpis.totalReflections ?? '—';
    document.getElementById('kpiVisits').textContent      = data.kpis.totalVisits ?? '—';
    document.getElementById('kpiAvg').textContent         = data.kpis.avgReflections ?? '—';

    // Charts
    // 1) Status donut
    {
      const d = toLabelsAndValues(data.charts.status, 'status', 'cnt');
      upsertChart('chartStatus', 'doughnut', d.labels, d.values, {
        plugins: { legend: { position: 'bottom' } }
      });
    }

    // 2) City bar
    {
      const d = toLabelsAndValues(data.charts.city, 'city', 'cnt');
      upsertChart('chartCity', 'bar', d.labels, d.values, {
        plugins: { legend: { display: false } }
      });
    }

    // 3) Reflection trend line
    {
      const labels = (data.charts.reflectionTrend || []).map(x => x.yearweek);
      const values = (data.charts.reflectionTrend || []).map(x => Number(x.cnt || 0));
      upsertChart('chartRefTrend', 'line', labels, values, {
        plugins: { legend: { display: false } },
        elements: { line: { tension: 0.3 } }
      });
    }

    // 4) Visit type bar
    {
      const d = toLabelsAndValues(data.charts.visitType, 'type', 'cnt');
      upsertChart('chartVisitType', 'bar', d.labels, d.values, {
        plugins: { legend: { display: false } }
      });
    }

    const t = new Date((data.ts || Date.now()/1000) * 1000);
    statusEl.textContent = 'Live: updated ' + t.toLocaleTimeString();
  } catch (e) {
    statusEl.textContent = 'Live: error (check API path / DB tables)';
    console.error(e);
  }
}

// initial + polling
refreshMetrics();
setInterval(refreshMetrics, 10000);
</script>

<?php include '../includes/footer.php'; ?>