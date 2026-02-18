<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';

requireAuth('tutor');

header('Content-Type: application/json; charset=utf-8');

$userId = authId();

// Filters (same as reports.php)
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

try {
  // KPI: total placements
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM placements p
    JOIN users u ON u.id = p.student_id
    JOIN companies c ON c.id = p.company_id
    $whereSQL
  ");
  $stmt->execute($params);
  $kpi_totalPlacements = (int)$stmt->fetchColumn();

  // KPI: total reflections
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reflections r
    JOIN placements p ON p.id = r.placement_id
    JOIN users u ON u.id = p.student_id
    JOIN companies c ON c.id = p.company_id
    $whereSQL
  ");
  $stmt->execute($params);
  $kpi_totalReflections = (int)$stmt->fetchColumn();

  // KPI: total visits
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM visits v
    JOIN placements p ON p.id = v.placement_id
    JOIN users u ON u.id = p.student_id
    JOIN companies c ON c.id = p.company_id
    $whereSQL
  ");
  $stmt->execute($params);
  $kpi_totalVisits = (int)$stmt->fetchColumn();

  $kpi_avgReflections = $kpi_totalPlacements > 0 ? round($kpi_totalReflections / $kpi_totalPlacements, 1) : 0;

  // Chart 1: placements by status
  $stmt = $pdo->prepare("
    SELECT LOWER(p.status) AS status, COUNT(*) AS cnt
    FROM placements p
    JOIN users u ON u.id = p.student_id
    JOIN companies c ON c.id = p.company_id
    $whereSQL
    GROUP BY LOWER(p.status)
    ORDER BY cnt DESC
  ");
  $stmt->execute($params);
  $chartStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Chart 2: placements by city (top 8)
  $stmt = $pdo->prepare("
    SELECT COALESCE(NULLIF(c.city,''),'Unknown') AS city, COUNT(*) AS cnt
    FROM placements p
    JOIN users u ON u.id = p.student_id
    JOIN companies c ON c.id = p.company_id
    $whereSQL
    GROUP BY city
    ORDER BY cnt DESC
    LIMIT 8
  ");
  $stmt->execute($params);
  $chartCity = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Chart 3: reflections trend (last 12 weeks)
  $stmt = $pdo->prepare("
    SELECT DATE_FORMAT(r.created_at, '%Y-%u') AS yearweek, COUNT(*) AS cnt
    FROM reflections r
    JOIN placements p ON p.id = r.placement_id
    JOIN users u ON u.id = p.student_id
    JOIN companies c ON c.id = p.company_id
    $whereSQL
    GROUP BY yearweek
    ORDER BY yearweek DESC
    LIMIT 12
  ");
  $stmt->execute($params);
  $tmp = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $chartReflectionTrend = array_reverse($tmp);

  // Chart 4: visits by type
  $stmt = $pdo->prepare("
    SELECT LOWER(v.type) AS type, COUNT(*) AS cnt
    FROM visits v
    JOIN placements p ON p.id = v.placement_id
    JOIN users u ON u.id = p.student_id
    JOIN companies c ON c.id = p.company_id
    $whereSQL
    GROUP BY LOWER(v.type)
    ORDER BY cnt DESC
  ");
  $stmt->execute($params);
  $chartVisitType = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'ok' => true,
    'kpis' => [
      'totalPlacements' => $kpi_totalPlacements,
      'totalReflections' => $kpi_totalReflections,
      'totalVisits' => $kpi_totalVisits,
      'avgReflections' => $kpi_avgReflections,
    ],
    'charts' => [
      'status' => $chartStatus,
      'city' => $chartCity,
      'reflectionTrend' => $chartReflectionTrend,
      'visitType' => $chartVisitType,
    ],
    'ts' => time()
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Server error while building metrics.',
  ]);
}