<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user'])) {
  header("Location: /inplace/dashboard.php");
  exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email        = trim($_POST['email'] ?? '');
  $password     = $_POST['password'] ?? '';
  $selectedRole = strtolower(trim($_POST['selected_role'] ?? 'student'));

  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  if ($u && password_verify($password, $u['password'])) {
    $dbRole = strtolower(trim($u['role'] ?? ''));

    // Enforce role match
    if ($selectedRole && $dbRole !== $selectedRole) {
      $error = "You selected " . e(ucfirst($selectedRole)) . " but this account is a " . e(ucfirst($dbRole)) . " account.";
    } else {
      $_SESSION['user'] = [
        'id' => (int)$u['id'],
        'full_name' => $u['full_name'],
        'email' => $u['email'],
        'role' => $u['role'],
        'avatar_initials' => $u['avatar_initials'] ?: initials($u['full_name']),
      ];
      header("Location: /inplace/dashboard.php");
      exit;
    }
  } else {
    $error = "Invalid email or password.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>InPlace — Login</title>

  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/inplace/assets/css/style.css">
</head>
<body>

<div class="login-screen" id="loginScreen">
  <div class="login-decoration"></div>

  <div class="login-left">
    <div class="login-badge">School of Informatics</div>

    <h1>Industrial<br><span>Placement</span><br>Portal</h1>

    <p>
      A centralised platform to manage, track and coordinate Year in Industry placements
      for students, tutors and providers.
    </p>

    <div class="login-features">
      <div class="login-feature"><div class="login-feature-icon">📋</div> Submit & track authorisation requests</div>
      <div class="login-feature"><div class="login-feature-icon">🗓</div> Schedule and manage placement visits</div>
      <div class="login-feature"><div class="login-feature-icon">🗺</div> Visualise placements on an interactive map</div>
      <div class="login-feature"><div class="login-feature-icon">💬</div> In-platform messaging & notifications</div>
    </div>
  </div>

  <div class="login-right">
    <div class="login-card">
      <h2>Welcome Back</h2>
      <p>Sign in with your university credentials</p>

      <?php if ($error): ?>
        <div class="alert-danger"><?= e($error) ?></div>
      <?php endif; ?>

      <div class="role-selector" id="roleSelector">
        <button type="button" class="role-btn active" data-role="student">
          <h4>🎓 Student</h4>
          <p>Year in Industry</p>
        </button>

        <button type="button" class="role-btn" data-role="tutor">
          <h4>👨‍🏫 Tutor</h4>
          <p>Placement coordinator</p>
        </button>

        <button type="button" class="role-btn" data-role="provider">
          <h4>🏢 Provider</h4>
          <p>Employer / company</p>
        </button>

        <button type="button" class="role-btn" data-role="admin">
          <h4>⚙️ Admin</h4>
          <p>System administrator</p>
        </button>
      </div>

      <form method="POST" autocomplete="off">
        <input type="hidden" name="selected_role" id="selectedRole" value="student">

        <div class="form-field">
          <label>University Email</label>
          <input
            type="email"
            name="email"
            placeholder="e.g., abc123@sheffield.ac.uk"
            value="<?= e($email) ?>"
            required
          >
        </div>

        <div class="form-field">
          <label>Password</label>
          <input type="password" name="password" placeholder="••••••••" required>
        </div>

        <button class="btn-login" type="submit">Sign In with University SSO →</button>
      </form>

      <!-- TIP REMOVED (you asked to hide it) -->
    </div>
  </div>
</div>

<script>
  (function () {
    const buttons = document.querySelectorAll('.role-btn');
    const selectedRole = document.getElementById('selectedRole');

    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        buttons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedRole.value = btn.dataset.role || 'student';
      });
    });
  })();
</script>

</body>
</html>