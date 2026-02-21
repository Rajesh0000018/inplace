<?php
session_start();
require_once 'config/db.php';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = $_SESSION['registration_success'] ?? '';
unset($_SESSION['registration_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if ($email && $password) {
        // Fetch user
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, password, role, avatar_initials, approval_status, rejection_reason
            FROM users
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Check approval status
            if ($user['approval_status'] === 'pending') {
                $error = "Your account is pending admin approval. You will receive an email once your account is approved.";
            } elseif ($user['approval_status'] === 'rejected') {
                $reason = $user['rejection_reason'] ? " Reason: " . $user['rejection_reason'] : "";
                $error = "Your account registration was not approved.$reason Please contact the placement office for more information.";
            } else {
                // Approved - create session
                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'avatar_initials' => $user['avatar_initials']
                ];
                
                header("Location: dashboard.php");
                exit;
            }
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Please enter both email and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - InPlace</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

       body {
    font-family: 'DM Sans', sans-serif;
    background-image: url("assets/images/library-bg.jpg");
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}
body::before {
    content: "";
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.35); /* dark overlay */
    z-index: -1;
}

        .auth-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            max-width: 1100px;
            width: 100%;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }

        .auth-left {
            background: linear-gradient(135deg, #0c1b33 0%, #1a2d4d 100%);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            color: white;
        }

        .auth-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(232, 160, 32, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }

        .back-link {
            position: absolute;
            top: 2rem;
            left: 2rem;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .back-link:hover {
            transform: translateX(-5px);
        }

        .illustration {
            width: 100%;
            max-width: 280px;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }

        .illustration svg {
            width: 100%;
            height: auto;
        }

        .auth-left h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 1rem;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .auth-left p {
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }

        .auth-right {
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 2rem;
        }

        .form-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: #0c1b33;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: #6b7a8d;
        }

        .form-header p a {
            color: #e8a020;
            text-decoration: none;
            font-weight: 600;
        }

        .form-header p a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9375rem;
        }

        .alert-danger {
            background: #fff5f5;
            border: 1px solid #feb2b2;
            color: #c53030;
        }

        .alert-warning {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            color: #92400e;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #15803d;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 0.9375rem;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e8dcc8;
            border-radius: 10px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
            background: #f8f5f0;
        }

        .form-input:focus {
            outline: none;
            border-color: #e8a020;
            background: white;
            box-shadow: 0 0 0 3px rgba(232, 160, 32, 0.1);
        }

        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #0c1b33 0%, #1a2d4d 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(12, 27, 51, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(12, 27, 51, 0.3);
        }

        @media (max-width: 968px) {
            .auth-container {
                grid-template-columns: 1fr;
            }

            .auth-left {
                padding: 2rem;
                min-height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-left">
            <a href="index.html" class="back-link">
                ← Back to Home
            </a>

            <div class="illustration">
                <svg viewBox="0 0 400 400" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Login Shield -->
                    <path d="M200 100 L280 130 L280 220 Q280 280 200 320 Q120 280 120 220 L120 130 Z" fill="#e8a020" opacity="0.2"/>
                    <path d="M200 120 L260 140 L260 210 Q260 260 200 290 Q140 260 140 210 L140 140 Z" fill="#e8a020"/>
                    <!-- Checkmark -->
                    <path d="M170 200 L190 220 L230 170" stroke="white" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
                    <!-- Decorative elements -->
                    <circle cx="100" cy="120" r="20" fill="white" opacity="0.1"/>
                    <circle cx="300" cy="280" r="30" fill="white" opacity="0.1"/>
                    <circle cx="320" cy="150" r="15" fill="white" opacity="0.15"/>
                </svg>
            </div>

            <h2>Welcome Back!</h2>
            <p>Sign in to access your placement management dashboard and continue your professional journey.</p>
        </div>

        <div class="auth-right">
            <div class="form-header">
                <h1>Sign In</h1>
                <p>Don't have an account? <a href="register.php">Register here</a></p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert <?= strpos($error, 'pending') !== false ? 'alert-warning' : 'alert-danger' ?>">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="your.name@student.le.ac.uk"
                        required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <button type="submit" class="btn-primary">
                    Sign In
                </button>
            </form>
        </div>
    </div>
</body>
</html>