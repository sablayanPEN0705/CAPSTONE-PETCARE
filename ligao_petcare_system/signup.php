<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: signup.php
// Purpose: Pet Owner Registration page
// ============================================================
require_once 'includes/auth.php';

// Already logged in? Redirect
if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/dashboard.php' : 'user/dashboard.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve & sanitize inputs
    $name       = sanitize($conn, $_POST['name']       ?? '');
    $address    = sanitize($conn, $_POST['address']    ?? '');
    $contact    = sanitize($conn, $_POST['contact']    ?? '');
    $email      = sanitize($conn, $_POST['email']      ?? '');
    $password   = $_POST['password']                   ?? '';
    $confirm_pw = $_POST['confirm_password']           ?? '';
    $agreed     = $_POST['agreed']                     ?? '';

    // Validation
    if (empty($name) || empty($address) || empty($contact) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_pw) {
        $error = 'Passwords do not match.';
    } elseif (empty($agreed)) {
        $error = 'You must agree to the Terms and Conditions to register.';
    } else {
        // Check if email already exists
        $check = getRow($conn, "SELECT id FROM users WHERE email = '$email'");
        if ($check) {
            $error = 'This email address is already registered. Please login instead.';
        } else {
            // Hash password
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (name, address, contact_no, email, password, role)
                    VALUES ('$name', '$address', '$contact', '$email', '$hashed', 'user')";

            if (mysqli_query($conn, $sql)) {
    $new_id = mysqli_insert_id($conn);
    $new_user = getRow($conn, "SELECT * FROM users WHERE id=$new_id");
    $_SESSION['user_id']   = $new_user['id'];
    $_SESSION['user_name'] = $new_user['name'];
    $_SESSION['user_role'] = $new_user['role'];
    redirect('user/dashboard.php');
} else {
                $error = 'Registration failed. Please try again. Error: ' . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — Ligao Petcare & Veterinary Clinic</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-box {
            max-width: 520px;
            animation: slideUp 0.4s ease both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .terms-check {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: var(--text-mid);
            margin-top: 6px;
        }
        .terms-check input[type="checkbox"] {
            margin-top: 2px;
            width: 16px;
            height: 16px;
            accent-color: var(--teal);
            flex-shrink: 0;
        }
        .terms-check a {
            color: var(--teal-dark);
            font-weight: 700;
            text-decoration: underline;
            cursor: pointer;
        }

        .login-link {
            text-align: center;
            font-size: 13px;
            color: var(--text-mid);
            margin-top: 16px;
        }
        .login-link a {
            color: var(--teal-dark);
            font-weight: 800;
            text-decoration: underline;
        }

        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 40px; }
        .toggle-pw {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; font-size: 15px;
            color: var(--text-light); padding: 0;
        }
        .toggle-pw:hover { color: var(--teal-dark); }

        /* Terms Modal */
        .modal-body {
            font-size: 13px;
            color: var(--text-mid);
            line-height: 1.8;
        }
        .modal-body h4 {
            font-weight: 800;
            color: var(--text-dark);
            margin: 14px 0 6px;
            font-size: 14px;
        }
        .modal-body ul {
            list-style: disc;
            padding-left: 18px;
        }
        .modal-body ul li { margin-bottom: 6px; }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-box">

        <!-- Logo -->
        <div class="auth-logo" style="justify-content:center;">
            <img src="assets/css/images/pets/logo.png" alt="Logo"
                 onerror="this.style.display='none'">
            <h1>Ligao Petcare &<br>Veterinary Clinic</h1>
        </div>

        <h2 class="auth-title">SIGN UP</h2>
        <p class="auth-subtitle">Pet Owner Information</p>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($success) ?>
                <a href="index.php" style="margin-left:8px;font-weight:700;color:#065f46;text-decoration:underline;">Login now →</a>
            </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <?php if (!$success): ?>
        <form method="POST" action="signup.php" autocomplete="off">

            <div class="form-group">
                <label for="name">Name <span style="color:#ef4444">*</span></label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Enter your full name"
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                    required autofocus
                >
            </div>

            <div class="form-group">
                <label for="address">Address <span style="color:#ef4444">*</span></label>
                <input
                    type="text"
                    id="address"
                    name="address"
                    placeholder="Enter your address"
                    value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="contact">Contact No. <span style="color:#ef4444">*</span></label>
                <input
                    type="text"
                    id="contact"
                    name="contact"
                    placeholder="e.g. 09XXXXXXXXX"
                    value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address <span style="color:#ef4444">*</span></label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <span style="color:#ef4444">*</span></label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Min. 6 characters"
                            required
                        >
                        <button type="button" class="toggle-pw" onclick="togglePw('password', this)">👁️</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span style="color:#ef4444">*</span></label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Re-enter password"
                            required
                        >
                        <button type="button" class="toggle-pw" onclick="togglePw('confirm_password', this)">👁️</button>
                    </div>
                </div>
            </div>

            <!-- Terms & Conditions Checkbox -->
            <div class="form-group">
                <label class="terms-check">
                    <input type="checkbox" name="agreed" value="1"
                           <?= !empty($_POST['agreed']) ? 'checked' : '' ?>>
                    <span>
                        I agree to the
                        <a onclick="openTermsModal()">Terms and Conditions</a>
                        and
                        <a onclick="openTermsModal()">Privacy Policy</a>
                        of the PetCare System.
                    </span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:4px;">
                SIGN IN
            </button>
        </form>
        <?php endif; ?>

    </div>
</div>

<!-- Terms & Conditions Modal -->
<div class="modal-overlay" id="termsModal">
    <div class="modal">
        <button class="modal-close" onclick="closeTermsModal()">×</button>
        <h3 class="modal-title">Terms and Conditions & Privacy Policy</h3>

        <div class="modal-body">
            <p>
                Welcome to the <strong>PetCare System</strong>. Before continuing, please review
                and agree to our Terms and Conditions and Privacy Policy.
            </p>

            <h4>User Agreement</h4>
            <ul>
                <li>By using this system, you agree to <strong>provide accurate information</strong> when managing your account, pet profiles, and appointment requests.</li>
                <li>The system is intended only for legitimate veterinary service purposes. Any misuse or unauthorized access is strictly prohibited.</li>
                <li>Users are responsible for keeping their login credentials confidential.</li>
            </ul>

            <h4>Treatment Consent</h4>
            <ul>
                <li>When booking appointments, the pet owner allows the veterinarian and clinic staff to examine and provide the necessary medical care for their pet.</li>
                <li>The clinic will always inform the owner about the recommended treatments before they are performed.</li>
            </ul>

            <h4>Privacy Policy</h4>
            <ul>
                <li>The PetCare System collects basic information such as pet owner details, pet information, appointment schedules, and service records.</li>
                <li>This information is used only to manage veterinary services and improve clinic operations.</li>
                <li>All personal data and pet records will be kept confidential and will only be accessed by authorized clinic personnel.</li>
                <li>The clinic will not share user information with third parties without permission unless required by law.</li>
            </ul>

            <p style="margin-top:14px;">
                By clicking <strong>"I Agree"</strong>, you confirm that you have read and accepted
                the Terms and Conditions and Privacy Policy of the PetCare System.
            </p>
        </div>

        <div class="modal-actions">
            <button class="btn btn-gray" onclick="closeTermsModal()">Cancel</button>
            <button class="btn btn-teal" onclick="agreeTerms()">I Agree</button>
        </div>
    </div>
</div>

<script>
// Password toggle
function togglePw(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁️';
    }
}

// Terms modal
function openTermsModal() {
    document.getElementById('termsModal').classList.add('open');
}

function closeTermsModal() {
    document.getElementById('termsModal').classList.remove('open');
}

function agreeTerms() {
    document.querySelector('input[name="agreed"]').checked = true;
    closeTermsModal();
}

// Close modal on overlay click
document.getElementById('termsModal').addEventListener('click', function(e) {
    if (e.target === this) closeTermsModal();
});

// Real-time password match check
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const pw  = document.getElementById('password').value;
    const cpw = this.value;
    if (cpw && pw !== cpw) {
        this.style.borderColor = '#ef4444';
    } else {
        this.style.borderColor = '';
    }
});
</script>
</body>
</html>