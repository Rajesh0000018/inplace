<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user'])) {
  header("Location: /inplace/dashboard.php");
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  if ($u && password_verify($password, $u['password'])) {
    $_SESSION['user'] = [
      'id' => (int)$u['id'],
      'full_name' => $u['full_name'],
      'email' => $u['email'],
      'role' => $u['role'],
      'avatar_initials' => $u['avatar_initials'] ?: initials($u['full_name']),
    ];
    header("Location: /inplace/dashboard.php");
    exit;
  } else {
    $error = "Invalid email or password.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <p>A centralised platform to manage, track and coordinate Year in Industry placements for students, tutors and providers.</p>
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
      <p>Sign in with your credentials</p>

      <?php if ($error): ?>
        <div style="margin-bottom:1rem;padding:0.875rem;border-radius:10px;background:var(--danger-bg);border:1px solid #fca5a5;color:var(--danger);">
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <div class="form-field">
          <label>University Email</label>
          <input type="email" name="email" placeholder="e.g., abc123@sheffield.ac.uk" required>
        </div>
        <div class="form-field">
          <label>Password</label>
          <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button class="btn-login" type="submit">Sign In →</button>
      </form>

      <p style="margin-top:1rem;font-size:0.8rem;">
        Tip: Use the seeded accounts once you replace seed hashes with real bcrypt hashes.
      </p>
    </div>
  </div>
</div>

</body>
</html>