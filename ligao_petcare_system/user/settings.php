<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/settings.php  (with activity logging)
// ============================================================
require_once '../includes/auth.php';
require_once '../includes/activity_log.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id = (int)$_SESSION['user_id'];
$user    = getRow($conn, "SELECT * FROM users WHERE id=$user_id");

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';
$panel   = $_GET['panel']   ?? '';

// ── Handle Account Update ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_account') {
    $name    = sanitize($conn, $_POST['name']    ?? '');
    $address = sanitize($conn, $_POST['address'] ?? '');
    $contact = sanitize($conn, $_POST['contact'] ?? '');
    $email   = sanitize($conn, $_POST['email']   ?? '');

    if (empty($name) || empty($email)) {
        redirect('settings.php?error=Name and email are required.&panel=account');
    }

    $taken = getRow($conn, "SELECT id FROM users WHERE email='$email' AND id != $user_id");
    if ($taken) {
        redirect('settings.php?error=That email is already used by another account.&panel=account');
    }

    $photo_sql = '';
    if (!empty($_FILES['profile_picture']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed)) {
            $upload_dir = '../assets/images/avatars/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename   = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $filename);
            $photo_sql  = ", profile_picture='$filename'";
        }
    }

    mysqli_query($conn,
        "UPDATE users SET name='$name', address='$address',
                          contact_no='$contact', email='$email' $photo_sql
         WHERE id=$user_id");

    $_SESSION['user_name'] = $name;

    // ── LOG ──
    logActivity($conn, $user_id, 'user', 'update_profile', 'settings',
        "Updated account profile (name: $name, email: $email).",
        ['email' => $email, 'has_photo' => !empty($photo_sql)]
    );

    redirect('settings.php?success=Account updated successfully!&panel=account');
}

// ── Handle Password Change ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new_pw  = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'])) {
        // ── LOG failed attempt ──
        logActivity($conn, $user_id, 'user', 'change_password_failed', 'settings',
            'Password change failed: incorrect current password.');
        redirect('settings.php?error=Current password is incorrect.&panel=account');
    }
    if (strlen($new_pw) < 6) {
        redirect('settings.php?error=New password must be at least 6 characters.&panel=account');
    }
    if ($new_pw !== $confirm) {
        redirect('settings.php?error=New passwords do not match.&panel=account');
    }

    $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
    mysqli_query($conn, "UPDATE users SET password='$hashed' WHERE id=$user_id");

    // ── LOG ──
    logActivity($conn, $user_id, 'user', 'change_password', 'settings',
        'Password changed successfully.');

    redirect('settings.php?success=Password changed successfully!&panel=account');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .settings-layout { display: grid; grid-template-columns: 1fr; gap: 20px; align-items: start; }
        .settings-menu { background: rgba(255,255,255,0.9); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); }
        .settings-menu-title { padding: 18px 20px; font-family: var(--font-head); font-size: 17px; font-weight: 800; color: var(--text-dark); border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
        .settings-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; cursor: pointer; transition: var(--transition); border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600; color: var(--text-dark); text-decoration: none; }
        .settings-menu-item:last-child { border-bottom: none; }
        .settings-menu-item:hover { background: rgba(0,188,212,0.08); color: var(--teal-dark); }
        .settings-menu-item.active { background: rgba(0,188,212,0.12); color: var(--teal-dark); font-weight: 700; }
        .settings-menu-item .smi-left { display: flex; align-items: center; gap: 10px; }
        .settings-menu-item .smi-icon { font-size: 18px; }
        .settings-menu-item .smi-arrow { color: var(--text-light); font-size: 14px; }
        .settings-content { background: rgba(255,255,255,0.9); border-radius: var(--radius); padding: 28px 32px; box-shadow: var(--shadow); }
        .settings-content h3 { font-family: var(--font-head); font-size: 18px; font-weight: 800; margin-bottom: 24px; color: var(--text-dark); display: flex; align-items: center; gap: 8px; }
        .avatar-upload { display: flex; align-items: center; gap: 20px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border); }
        .avatar-preview { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; background: var(--teal-light); display: flex; align-items: center; justify-content: center; font-size: 36px; color: var(--teal-dark); flex-shrink: 0; font-weight: 700; overflow: hidden; }
        .avatar-preview img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-upload-info h4 { font-weight: 700; font-size: 14px; margin-bottom: 4px; }
        .avatar-upload-info p  { font-size: 12px; color: var(--text-light); margin-bottom: 8px; }
        .pw-section { margin-top: 28px; padding-top: 24px; border-top: 1px solid var(--border); }
        .pw-section h4 { font-weight: 700; font-size: 15px; margin-bottom: 16px; color: var(--text-dark); }
        .policy-content { font-size: 13px; color: var(--text-mid); line-height: 1.9; }
        .policy-content h4 { font-weight: 800; color: var(--text-dark); margin: 16px 0 6px; font-size: 14px; }
        .policy-content ul { list-style: disc; padding-left: 20px; }
        .policy-content ul li { margin-bottom: 6px; }
        .policy-content p { margin-bottom: 10px; }
        .notif-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--border); }
        .notif-item:last-child { border-bottom: none; }
        .notif-label h4 { font-weight: 700; font-size: 14px; margin-bottom: 2px; }
        .notif-label p  { font-size: 12px; color: var(--text-light); }
        .toggle { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #ccc; border-radius: 24px; transition: var(--transition); }
        .toggle-slider::before { content: ''; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: var(--transition); }
        .toggle input:checked + .toggle-slider { background: var(--teal); }
        .toggle input:checked + .toggle-slider::before { transform: translateX(20px); }
        @media(max-width:700px) { .settings-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_user.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title" style="display:flex;align-items:center;gap:10px;">
                <img src="../assets/css/images/pets/logo.png" alt="Logo"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.6);"
                     onerror="this.style.display='none';">
                Settings
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔</a>
                <a href="settings.php"      class="topbar-btn">⚙️</a>
            </div>
        </div>
        <div class="page-body">
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="settings-layout">
                <div class="settings-content">

                    <?php if ($panel === 'account'): ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                        <a href="settings.php" class="btn btn-gray btn-sm">← Back</a>
                        <h3 style="margin-bottom:0;">👤 Account Settings</h3>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_account">
                        <div class="avatar-upload">
                            <div class="avatar-preview" id="avatarPreviewWrap">
                                <?php if ($user['profile_picture']): ?>
                                    <img src="../assets/images/avatars/<?= htmlspecialchars($user['profile_picture']) ?>"
                                         id="avatarPreview" alt="Avatar">
                                <?php else: ?>
                                    <span id="avatarInitials"><?= strtoupper(substr($user['name'], 0, 2)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="avatar-upload-info">
                                <h4>Profile Photo</h4>
                                <p>JPG, PNG or GIF. Max 2MB.</p>
                                <input type="file" name="profile_picture" accept="image/*"
                                       onchange="previewAvatar(this)" style="font-size:12px;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact No.</label>
                                <input type="text" name="contact" value="<?= htmlspecialchars($user['contact_no'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Email Address *</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-teal">Save Changes</button>
                    </form>
                    <div class="pw-section">
                        <h4>🔑 Change Password</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" placeholder="Enter current password" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" placeholder="Min. 6 characters" required>
                                </div>
                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <input type="password" name="confirm_password" placeholder="Re-enter new password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:auto;padding:10px 24px;">
                                Update Password
                            </button>
                        </form>
                    </div>

                    <?php elseif ($panel === 'notifications'): ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                        <a href="settings.php" class="btn btn-gray btn-sm">← Back</a>
                        <h3 style="margin-bottom:0;">🔔 Notifications</h3>
                    </div>
                    <p style="font-size:13px;color:var(--text-light);margin-bottom:20px;">
                        Manage how you receive notifications from Ligao Petcare.
                    </p>
                    <?php foreach([
                        ['Appointment Reminders','Get notified about upcoming appointments',true],
                        ['Messages','Notify when clinic staff sends a message',true],
                        ['Clinic Announcements','Stay updated with clinic news and promos',true],
                        ['Vaccine Due Alerts','Remind me when my pet\'s vaccine is due',false],
                    ] as $ni): ?>
                        <div class="notif-item">
                            <div class="notif-label">
                                <h4><?= htmlspecialchars($ni[0]) ?></h4>
                                <p><?= htmlspecialchars($ni[1]) ?></p>
                            </div>
                            <label class="toggle">
                                <input type="checkbox" <?= $ni[2]?'checked':'' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <div style="margin-top:20px;">
                        <button class="btn btn-teal" onclick="alert('Notification preferences saved!')">
                            Save Preferences
                        </button>
                    </div>

                    <?php elseif ($panel === 'help'): ?>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                        <a href="settings.php" class="btn btn-gray btn-sm">← Back</a>
                        <h3 style="margin-bottom:0;">❓ Help</h3>
                    </div>
                    <p style="font-size:13px;color:var(--text-light);margin-bottom:20px;">
                        Frequently asked questions and support resources.
                    </p>
                    <?php
                    $faqs = [
                        ['How do I book an appointment?',
                         'Go to the Appointments page from the sidebar. Click "Add Appointment" under Clinic or Home Service, fill in the details, agree to the policies, and submit.'],
                        ['Can I add multiple pets?',
                         'Yes! Go to Pet Profile and click the "Add Pet" card. You can add as many pets as you need.'],
                        ['How do I view my pet\'s medical records?',
                         'Go to Pet Profile, click on any pet card to open its profile. Medical records including medications, vaccines, and allergies are shown below.'],
                        ['How do I cancel an appointment?',
                         'Go to Appointments page, find the scheduled appointment and click Cancel. Note: cancellations should be made at least 24 hours in advance.'],
                        ['How do I contact the clinic?',
                         'You can use the Messages page to chat directly with clinic staff, or call 0926-396-7678 / 0950-138-1530.'],
                    ];
                    ?>
                    <?php foreach ($faqs as $i => $faq): ?>
                        <div style="margin-bottom:12px;border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;">
                            <div style="padding:12px 16px;background:#f0fffe;font-weight:700;font-size:13px;
                                        cursor:pointer;display:flex;justify-content:space-between;align-items:center;"
                                 onclick="toggleFaq(<?= $i ?>)">
                                <span><?= htmlspecialchars($faq[0]) ?></span>
                                <span id="faq-arrow-<?= $i ?>">›</span>
                            </div>
                            <div id="faq-<?= $i ?>" style="display:none;padding:12px 16px;
                                                             font-size:13px;color:var(--text-mid);
                                                             background:#fff;line-height:1.7;">
                                <?= htmlspecialchars($faq[1]) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div style="margin-top:20px;padding:16px;background:#e0f7fa;border-radius:var(--radius-sm);font-size:13px;">
                        <strong>Still need help?</strong><br>
                        <a href="messages.php" style="color:var(--teal-dark);font-weight:700;">💬 Message our clinic staff</a>
                        or call <strong>0926-396-7678</strong>
                    </div>

                    <?php elseif ($panel === 'privacy'): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                        <h3 style="margin-bottom:0;">🔒 Privacy Policy</h3>
                        <a href="settings.php" class="btn btn-gray btn-sm">← Back</a>
                    </div>
                    <div class="policy-content">
                        <p>Ligao Veterinary Clinic values the privacy of its users. This Privacy Policy explains how the PetCare System collects, uses, and protects the information of pet owners and their pets.</p>
                        <h4>Information Collected</h4>
                        <p>The system may collect personal information such as the pet owner's name, contact details, and pet information including name, breed, age, and medical records. Appointment schedules and service records may also be stored.</p>
                        <h4>Purpose of Data Collection</h4>
                        <ul>
                            <li>To manage pet records and schedule veterinary appointments</li>
                            <li>To maintain service history and improve clinic operations</li>
                        </ul>
                        <h4>Data Protection</h4>
                        <p>All information stored in the PetCare System will be protected and only accessible to authorized clinic staff and administrators.</p>
                        <h4>Confidentiality</h4>
                        <ul>
                            <li>Personal information and pet records will be treated as confidential</li>
                            <li>The clinic will not share user information with third parties without permission unless required by law</li>
                        </ul>
                        <h4>Data Accuracy</h4>
                        <p>Users are encouraged to provide accurate and updated information to ensure proper record management and veterinary care.</p>
                        <h4>Policy Updates</h4>
                        <p>Ligao Veterinary Clinic may update this Privacy Policy when necessary to improve system security and services.</p>
                    </div>

                    <?php elseif ($panel === 'terms'): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                        <h3 style="margin-bottom:0;">📄 Terms &amp; Conditions</h3>
                        <button onclick="window.history.back()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-light);">×</button>
                    </div>
                    <div class="policy-content">
                        <p>Welcome to the PetCare System. By using this system, you agree to follow the rules and policies stated below.</p>
                        <h4>User Agreement</h4>
                        <ul>
                            <li>By creating an account, the user agrees to follow the rules and policies of Ligao Veterinary Clinic</li>
                            <li>Users must provide accurate information during registration and while using the system</li>
                            <li>Users are responsible for keeping their login credentials confidential</li>
                            <li>The system must only be used for legitimate veterinary service purposes</li>
                        </ul>
                        <h4>Treatment Consent</h4>
                        <ul>
                            <li>By scheduling an appointment, the pet owner allows the veterinarian and clinic staff to provide necessary medical care for their pet</li>
                            <li>The veterinarian will examine the pet and recommend appropriate treatment</li>
                            <li>The clinic will provide proper and safe veterinary care</li>
                        </ul>
                        <h4>General System Policy</h4>
                        <ul>
                            <li>The PetCare System is designed to help manage veterinary services, appointments, and pet records efficiently</li>
                            <li>Users must use the system responsibly and only for its intended purpose</li>
                        </ul>
                    </div>

                    <?php else: ?>
                    <h3>⚙️ Settings</h3>
                    <p style="font-size:14px;color:var(--text-light);margin-bottom:24px;">Select a settings category below.</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <?php
                        $items = [
                            ['account',       '👤', 'Account Settings',  'Update your name, email, address, and password'],
                            ['notifications', '🔔', 'Notifications',     'Manage your notification preferences'],
                            ['help',          '❓', 'Help',              'FAQs and support resources'],
                            ['privacy',       '🔒', 'Privacy Policy',    'How we handle your data'],
                            ['terms',         '📄', 'Terms & Conditions','System usage rules and policies'],
                        ];
                        foreach ($items as $item): ?>
                            <a href="settings.php?panel=<?= $item[0] ?>"
                               style="text-decoration:none;background:rgba(255,255,255,0.8);
                                      border:1.5px solid var(--border);border-radius:var(--radius);
                                      padding:16px;transition:var(--transition);display:block;"
                               onmouseover="this.style.borderColor='var(--teal)'"
                               onmouseout="this.style.borderColor='var(--border)'">
                                <div style="font-size:28px;margin-bottom:8px;"><?= $item[1] ?></div>
                                <div style="font-weight:700;font-size:14px;margin-bottom:4px;"><?= $item[2] ?></div>
                                <div style="font-size:12px;color:var(--text-light);"><?= $item[3] ?></div>
                            </a>
                        <?php endforeach; ?>
                        <!-- Activity Log shortcut -->
                        <a href="activity_log.php"
                           style="text-decoration:none;background:rgba(255,255,255,0.8);
                                  border:1.5px solid var(--border);border-radius:var(--radius);
                                  padding:16px;transition:var(--transition);display:block;"
                           onmouseover="this.style.borderColor='var(--teal)'"
                           onmouseout="this.style.borderColor='var(--border)'">
                            <div style="font-size:28px;margin-bottom:8px;">📋</div>
                            <div style="font-weight:700;font-size:14px;margin-bottom:4px;">Activity Log</div>
                            <div style="font-size:12px;color:var(--text-light);">View your account activity history</div>
                        </a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const wrap = document.getElementById('avatarPreviewWrap');
            wrap.innerHTML = `<img src="${e.target.result}"
                                   style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function toggleFaq(idx) {
    const el    = document.getElementById('faq-' + idx);
    const arrow = document.getElementById('faq-arrow-' + idx);
    if (!el) return;
    const open = el.style.display !== 'none';
    el.style.display  = open ? 'none' : 'block';
    arrow.textContent = open ? '›' : '˅';
}
</script>

<?php include_once __DIR__ . '/../includes/chatbot-widget.php'; ?>
</body>
</html>