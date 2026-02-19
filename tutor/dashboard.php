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

$stmt = $pdo->query("SELECT COUNT(*) FROM placements WHERE LOWER(status) IN ('submitted','awaiting_tutor','awaiting_provider')");
$pendingRequests = (int)$stmt->fetchColumn();

// KPIs
$stmt = $pdo->query("SELECT COUNT(*) FROM placements WHERE LOWER(status) IN ('approved','active')");
$totalActive = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM placements WHERE LOWER(status) IN ('submitted','awaiting_tutor','awaiting_provider')");
$totalPending = (int)$stmt->fetchColumn();

$stmt = $pdo->query("
  SELECT COUNT(*)
  FROM visits
  WHERE visit_date >= CURDATE()
    AND LOWER(status) IN ('proposed','confirmed')
");
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
        <div class="stat-trend trend-neutral">Planned visits</div>
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

    <!-- ✅ CHARTS (only once) -->
    <div style="display:grid;grid-template-columns:repeat(2,minmax(320px,1fr));gap:1rem;margin-top:1.25rem;">
      <div class="panel" style="margin-bottom:0;">
        <div class="panel-header"><h3 style="font-size:1rem;">Placements by Status</h3></div>
        <div class="panel-body" style="height:280px;">
          <canvas id="dashChartStatus" style="width:100%;height:100%;"></canvas>
        </div>
      </div>

      <div class="panel" style="margin-bottom:0;">
        <div class="panel-header"><h3 style="font-size:1rem;">Placements by City (Top 8)</h3></div>
        <div class="panel-body" style="height:280px;">
          <canvas id="dashChartCity" style="width:100%;height:100%;"></canvas>
        </div>
      </div>

      <div class="panel" style="margin-bottom:0;">
        <div class="panel-header"><h3 style="font-size:1rem;">Reflections Trend (Last 12 Weeks)</h3></div>
        <div class="panel-body" style="height:280px;">
          <canvas id="dashChartRefTrend" style="width:100%;height:100%;"></canvas>
        </div>
      </div>

      <div class="panel" style="margin-bottom:0;">
        <div class="panel-header"><h3 style="font-size:1rem;">Visits by Status</h3></div>
        <div class="panel-body" style="height:280px;">
          <canvas id="dashChartVisitStatus" style="width:100%;height:100%;"></canvas>
        </div>
      </div>
    </div>

    <small id="dashLive" style="display:block;margin-top:0.75rem;color:var(--muted);">
      Live: loading…
    </small>

    <!-- ✅ KEEP ONLY THIS Quick Actions (the bottom one with navigation) -->
    <div class="quick-actions" style="margin-top:1.25rem;">
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

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const dashCharts = {};

function cssVar(name, fallback) {
  const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return v || fallback;
}

function upsertChart(id, type, labels, values, options = {}, datasetExtras = {}) {
  const el = document.getElementById(id);
  if (!el) return;

  const baseDataset = Object.assign({ data: values }, datasetExtras);

  if (!dashCharts[id]) {
    dashCharts[id] = new Chart(el, {
      type,
      data: { labels, datasets: [baseDataset] },
      options: Object.assign({
        responsive: true,
        maintainAspectRatio: false
      }, options)
    });
    return;
  }

  dashCharts[id].data.labels = labels;
  dashCharts[id].data.datasets[0] = baseDataset;
  dashCharts[id].update();
}

async function refreshDashboardCharts() {
  const live = document.getElementById('dashLive');

  try {
    const res = await fetch('/inplace/tutor/api/dashboard-metrics.php', { cache: 'no-store' });
    const data = await res.json();

    if (!data.ok) throw new Error(data.error || 'API error');

    const NAVY = cssVar('--navy', '#0c1b33');
    const GOLD = cssVar('--gold', '#e8a020');

    // ✅ 1) Placements by Status (charts.status)
    {
      const labels = (data.charts.status || []).map(x => x.status);
      const values = (data.charts.status || []).map(x => Number(x.cnt || 0));

      upsertChart('dashChartStatus', 'doughnut', labels, values, {
        plugins: { legend: { position: 'bottom' } }
      }, {
        backgroundColor: labels.map((_, i) => i % 2 === 0 ? NAVY : GOLD)
      });
    }

    // ✅ 2) Placements by City (charts.city)
    {
      const labels = (data.charts.city || []).map(x => x.city);
      const values = (data.charts.city || []).map(x => Number(x.cnt || 0));

      upsertChart('dashChartCity', 'bar', labels, values, {
        plugins: { legend: { display: false } }
      }, {
        backgroundColor: NAVY
      });
    }

    // ✅ 3) Reflections Trend (charts.reflectionTrend uses key "week")
    {
      const labels = (data.charts.reflectionTrend || []).map(x => x.week);
      const values = (data.charts.reflectionTrend || []).map(x => Number(x.cnt || 0));

      upsertChart('dashChartRefTrend', 'line', labels, values, {
        plugins: { legend: { display: false } },
        elements: { line: { tension: 0.3 } }
      }, {
        borderColor: NAVY,
        backgroundColor: GOLD,
        fill: false
      });
    }

    // ✅ 4) Visits by Status (charts.visits)
    {
      const labels = (data.charts.visits || []).map(x => x.status);
      const values = (data.charts.visits || []).map(x => Number(x.cnt || 0));

      upsertChart('dashChartVisitStatus', 'bar', labels, values, {
        plugins: { legend: { display: false } }
      }, {
        backgroundColor: GOLD
      });
    }

    const t = new Date((data.ts || Date.now()/1000) * 1000);
    live.textContent = 'Live: updated ' + t.toLocaleTimeString();

  } catch (e) {
    live.textContent = 'Live: error (check /inplace/tutor/api/dashboard-metrics.php)';
    console.error(e);
  }
}

refreshDashboardCharts();
setInterval(refreshDashboardCharts, 10000);
</script>

<?php include '../includes/footer.php'; ?>