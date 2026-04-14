<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: index.php (root)
// Purpose: Login page
// ============================================================
require_once 'includes/auth.php';

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('user/dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($conn, $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $sql  = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
        $user = getRow($conn, $sql);

        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'admin') {
                redirect('admin/dashboard.php');
            } else {
                redirect('user/dashboard.php');
            }
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Ligao Petcare & Veterinary Clinic</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-page { flex-direction: column; gap: 0; }

        .auth-box {
            animation: slideUp 0.4s ease both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 0;
            color: var(--text-light);
            font-size: 12px;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .signup-link {
            text-align: center;
            font-size: 13px;
            color: var(--text-mid);
            margin-top: 16px;
        }
        .signup-link a {
            color: var(--teal-dark);
            font-weight: 800;
            text-decoration: underline;
        }

        .password-wrapper {
            position: relative;
        }
        .password-wrapper input {
            padding-right: 40px;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: var(--text-light);
            padding: 0;
        }
        .toggle-password:hover { color: var(--teal-dark); }

        .demo-hint {
            background: #e0f7fa;
            border: 1px solid var(--teal-light);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            font-size: 12px;
            color: var(--teal-dark);
            margin-bottom: 18px;
            line-height: 1.6;
        }
        .demo-hint strong { display: block; margin-bottom: 2px; }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-box">

        <!-- Logo -->
        <div class="auth-logo" style="justify-content:center;">
            <img src="assets/css/images/pets/logo.png" alt="Ligao Petcare Logo"
                 onerror="this.style.display='none'">
            <h1>Ligao Petcare &<br>Veterinary Clinic</h1>
        </div>

        <h2 class="auth-title">LOGIN TO YOUR ACCOUNT</h2>

        <!-- Error Alert -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <span>⚠️</span> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        
        <!-- Login Form -->
        <form method="POST" action="index.php" autocomplete="off">

            <div class="form-group">
                <label for="email">Email Address :</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Password :</label>
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >
                    <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:8px;">
                LOGIN
            </button>
        </form>

        <div class="divider">or</div>

        <div class="signup-link">
            Don't have an account?
            <a href="signup.php">Sign Up now</a>
        </div>

    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const btn   = document.querySelector('.toggle-password');
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁️';
    }
}
</script>
</body>
</html>