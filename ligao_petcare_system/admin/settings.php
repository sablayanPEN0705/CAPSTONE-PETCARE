<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/settings.php
// Purpose: Admin account settings + Staff Management
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$admin_id = (int)$_SESSION['user_id'];
$admin    = getRow($conn, "SELECT * FROM users WHERE id=$admin_id");
$success  = $_GET['success'] ?? '';
$error    = $_GET['error']   ?? '';
$panel    = $_GET['panel']   ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_account') {
        $name    = sanitize($conn, $_POST['name']    ?? '');
        $address = sanitize($conn, $_POST['address'] ?? '');
        $contact = sanitize($conn, $_POST['contact'] ?? '');
        $email   = sanitize($conn, $_POST['email']   ?? '');
        if (!$name || !$email)
            redirect('settings.php?error=Name and email required.&panel=account');
        $taken = getRow($conn, "SELECT id FROM users WHERE email='$email' AND id!=$admin_id");
        if ($taken)
            redirect('settings.php?error=Email already used.&panel=account');
        $psql = '';
        if (!empty($_FILES['profile_picture']['name'])) {
            $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $d = '../assets/images/avatars/';
                if (!is_dir($d)) mkdir($d, 0755, true);
                $fn = 'av_adm_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['profile_picture']['tmp_name'], $d . $fn);
                $psql = ", profile_picture='$fn'";
            }
        }
        mysqli_query($conn,
            "UPDATE users SET name='$name', address='$address',
             contact_no='$contact', email='$email' $psql WHERE id=$admin_id");
        $_SESSION['user_name'] = $name;
        redirect('settings.php?success=Account updated!&panel=account');
    }

    if ($action === 'change_password') {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password']     ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (!password_verify($cur, $admin['password']))
            redirect('settings.php?error=Current password incorrect.&panel=account');
        if (strlen($new) < 6)
            redirect('settings.php?error=New password must be 6+ characters.&panel=account');
        if ($new !== $conf)
            redirect('settings.php?error=Passwords do not match.&panel=account');
        mysqli_query($conn,
            "UPDATE users SET password='" . password_hash($new, PASSWORD_DEFAULT) . "'
             WHERE id=$admin_id");
        redirect('settings.php?success=Password changed!&panel=account');
    }

    // ── Staff Management ─────────────────────────────────────
    if ($action === 'add_staff') {
        $sname    = sanitize($conn, $_POST['staff_name']     ?? '');
        $semail   = sanitize($conn, $_POST['staff_email']    ?? '');
        $scontact = sanitize($conn, $_POST['staff_contact']  ?? '');
        $spos     = sanitize($conn, $_POST['staff_position'] ?? '');
        $spass    = $_POST['staff_password'] ?? '';
        if (!$sname || !$semail || !$spass)
            redirect('settings.php?error=Name, email and password are required.&panel=staff');
        $exists = getRow($conn, "SELECT id FROM users WHERE email='$semail'");
        if ($exists)
            redirect('settings.php?error=Email already in use.&panel=staff');
        $hashed = password_hash($spass, PASSWORD_DEFAULT);
        mysqli_query($conn,
            "INSERT INTO users (name, contact_no, email, password, role, position, status)
             VALUES ('$sname', '$scontact', '$semail', '$hashed', 'staff', '$spos', 'active')");
        redirect('settings.php?success=Staff account created!&panel=staff');
    }

    if ($action === 'edit_staff') {
        $sid      = (int)$_POST['staff_id'];
        $sname    = sanitize($conn, $_POST['staff_name']     ?? '');
        $semail   = sanitize($conn, $_POST['staff_email']    ?? '');
        $scontact = sanitize($conn, $_POST['staff_contact']  ?? '');
        $spos     = sanitize($conn, $_POST['staff_position'] ?? '');
        $sstatus  = sanitize($conn, $_POST['staff_status']   ?? 'active');
        if (!$sname || !$semail)
            redirect('settings.php?error=Name and email required.&panel=staff');
        $taken = getRow($conn, "SELECT id FROM users WHERE email='$semail' AND id!=$sid");
        if ($taken)
            redirect('settings.php?error=Email already in use.&panel=staff');
        // Optional password reset
        $pass_sql = '';
        $snewpass = $_POST['staff_new_password'] ?? '';
        if (!empty($snewpass)) {
            if (strlen($snewpass) < 6)
                redirect('settings.php?error=New password must be 6+ characters.&panel=staff');
            $pass_sql = ", password='" . password_hash($snewpass, PASSWORD_DEFAULT) . "'";
        }
        mysqli_query($conn,
            "UPDATE users SET name='$sname', contact_no='$scontact', email='$semail',
             position='$spos', status='$sstatus' $pass_sql WHERE id=$sid AND role='staff'");
        redirect('settings.php?success=Staff account updated!&panel=staff');
    }

    if ($action === 'delete_staff') {
        $sid = (int)$_POST['staff_id'];
        mysqli_query($conn, "DELETE FROM users WHERE id=$sid AND role='staff'");
        redirect('settings.php?success=Staff account removed.&panel=staff');
    }

    if ($action === 'toggle_staff_status') {
        $sid     = (int)$_POST['staff_id'];
        $cur_st  = sanitize($conn, $_POST['current_status'] ?? 'active');
        $new_st  = $cur_st === 'active' ? 'inactive' : 'active';
        mysqli_query($conn, "UPDATE users SET status='$new_st' WHERE id=$sid AND role='staff'");
        redirect('settings.php?success=Staff status updated.&panel=staff');
    }
}

// Fetch staff list
$staff_list = getRows($conn,
    "SELECT * FROM users WHERE role='staff' ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .sl { display:grid; grid-template-columns:1fr; gap:20px; align-items:start; }
        .sc { background:rgba(255,255,255,.9); border-radius:var(--radius);
               padding:28px 32px; box-shadow:var(--shadow); }
        .sc h3 { font-family:var(--font-head); font-size:18px; font-weight:800;
                  margin-bottom:24px; display:flex; align-items:center; gap:8px; }
        .av-wrap { display:flex; align-items:center; gap:20px; margin-bottom:24px;
                    padding-bottom:20px; border-bottom:1px solid var(--border); }
        .av-circle { width:80px; height:80px; border-radius:50%; overflow:hidden; flex-shrink:0;
                      background:linear-gradient(135deg,var(--teal),#0097a7);
                      display:flex; align-items:center; justify-content:center;
                      font-size:30px; color:#fff; font-weight:700; }
        .av-circle img { width:100%; height:100%; object-fit:cover; }
        .pw-sec { margin-top:28px; padding-top:24px; border-top:1px solid var(--border); }
        .pw-sec h4 { font-weight:700; font-size:15px; margin-bottom:16px; }
        .tog { position:relative; width:44px; height:24px; flex-shrink:0; }
        .tog input { opacity:0; width:0; height:0; }
        .tsl { position:absolute; cursor:pointer; inset:0; background:#ccc;
                border-radius:24px; transition:var(--transition); }
        .tsl::before { content:''; position:absolute; height:18px; width:18px; left:3px;
                         bottom:3px; background:#fff; border-radius:50%;
                         transition:var(--transition); }
        .tog input:checked + .tsl { background:var(--teal); }
        .tog input:checked + .tsl::before { transform:translateX(20px); }
        .nr { display:flex; align-items:center; justify-content:space-between;
               padding:13px 0; border-bottom:1px solid var(--border); }
        .nr:last-child { border-bottom:none; }
        .pc { font-size:13px; color:var(--text-mid); line-height:1.9; }
        .pc h4 { font-weight:800; color:var(--text-dark); margin:16px 0 6px; font-size:14px; }
        .pc ul { list-style:disc; padding-left:20px; }
        .pc ul li { margin-bottom:6px; }

        /* Staff cards */
        .staff-card {
            background: #fff;
            border-radius: var(--radius);
            padding: 18px 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 14px;
            border: 1.5px solid var(--border);
            transition: var(--transition);
        }
        .staff-card:hover { border-color: var(--teal-light); }
        .staff-avatar {
            width: 52px; height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), #0097a7);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: #fff; font-weight: 700;
            flex-shrink: 0; overflow: hidden;
        }
        .staff-avatar img { width:100%; height:100%; object-fit:cover; }
        .staff-info { flex: 1; }
        .staff-info .s-name { font-weight: 800; font-size: 15px; color: var(--text-dark); }
        .staff-info .s-pos  { font-size: 12px; color: var(--text-light); font-weight: 600; margin-top:2px; }
        .staff-info .s-email { font-size: 12px; color: var(--text-mid); margin-top:2px; }
        .staff-actions { display:flex; gap:8px; align-items:center; }
        @media(max-width:700px) { .sl{ grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_admin.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title" style="display:flex;align-items:center;gap:10px;">
                <img src="../assets/css/images/pets/logo.png" alt="Logo"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.6);"
                     onerror="this.style.display='none';">
                Settings
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn" title="Notifications">
                    🔔
                    <?php $unread_notif = getUnreadNotifications($conn, $_SESSION['user_id']); if ($unread_notif > 0): ?>
                        <span class="badge-count"><?= $unread_notif ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="topbar-btn" title="Settings">⚙️</a>
            </div>
        </div>

        <div class="page-body">
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="sl">
                <div class="sc">

                    <?php if ($panel === 'account'): ?>
                    <!-- ── Account Settings ── -->
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                        <a href="settings.php" class="btn btn-gray btn-sm">← Back</a>
                        <h3 style="margin-bottom:0;">👤 Account Settings</h3>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_account">
                        <div class="av-wrap">
                            <div class="av-circle" id="avWrap">
                                <?php if ($admin['profile_picture']): ?>
                                    <img src="../assets/images/avatars/<?= htmlspecialchars($admin['profile_picture']) ?>">
                                <?php else: ?>
                                    <?= strtoupper(substr($admin['name'], 0, 2)) ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong style="font-size:14px;"><?= htmlspecialchars($admin['name']) ?></strong><br>
                                <span style="font-size:12px;color:var(--text-light);">Administrator</span><br>
                                <input type="file" name="profile_picture" accept="image/*"
                                       onchange="prevAv(this)" style="font-size:12px;margin-top:6px;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($admin['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($admin['address'] ?? '') ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact No.</label>
                                <input type="text" name="contact" value="<?= htmlspecialchars($admin['contact_no'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-teal">Save Changes</button>
                    </form>
                    <div class="pw-sec">
                        <h4>🔑 Change Password</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" placeholder="Min. 6 characters" required>
                                </div>
                                <div class="form-group">
                                    <label>Confirm</label>
                                    <input type="password" name="confirm_password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:auto;padding:10px 24px;">
                                Update Password
                            </button>
                        </form>
                    </div>

                    <?php elseif ($panel === 'staff'): ?>
                    <!-- ── Staff Management ── -->
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <a href="settings.php" class="btn btn-gray btn-sm">← Back</a>
                            <h3 style="margin-bottom:0;">👥 Staff Management</h3>
                        </div>
                        <button class="btn btn-teal btn-sm"
                                onclick="document.getElementById('addStaffModal').classList.add('open')">
                            + Add Staff
                        </button>
                    </div>

                    <?php if (empty($staff_list)): ?>
                        <div style="text-align:center;padding:40px;color:var(--text-light);">
                            <div style="font-size:48px;margin-bottom:12px;">👥</div>
                            <p style="font-weight:700;">No staff accounts yet.</p>
                            <p style="font-size:13px;">Click "+ Add Staff" to create one.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($staff_list as $staff): ?>
                            <div class="staff-card">
                                <div class="staff-avatar">
                                    <?php if ($staff['profile_picture']): ?>
                                        <img src="../assets/images/avatars/<?= htmlspecialchars($staff['profile_picture']) ?>"
                                             onerror="this.parentElement.textContent='<?= strtoupper(substr($staff['name'],0,2)) ?>'">
                                    <?php else: ?>
                                        <?= strtoupper(substr($staff['name'], 0, 2)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="staff-info">
                                    <div class="s-name"><?= htmlspecialchars($staff['name']) ?></div>
                                    <div class="s-pos">
                                        <?= htmlspecialchars($staff['position'] ?? 'Clinic Staff') ?>
                                        &nbsp;·&nbsp;
                                        <?php if ($staff['status'] === 'active'): ?>
                                            <span style="color:#10b981;font-weight:700;">● Active</span>
                                        <?php else: ?>
                                            <span style="color:#ef4444;font-weight:700;">● Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="s-email">✉️ <?= htmlspecialchars($staff['email']) ?>
                                        <?= $staff['contact_no'] ? '&nbsp;·&nbsp;📞 '.htmlspecialchars($staff['contact_no']) : '' ?>
                                    </div>
                                </div>
                                <div class="staff-actions">
                                    <!-- Toggle status -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_staff_status">
                                        <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= $staff['status'] ?>">
                                        <button type="submit" class="btn btn-sm"
                                                style="background:<?= $staff['status']==='active'?'#fef3c7':'#dcfce7' ?>;
                                                       color:<?= $staff['status']==='active'?'#92400e':'#166534' ?>;
                                                       border:none;font-weight:700;cursor:pointer;padding:5px 10px;
                                                       border-radius:var(--radius-sm);font-size:11px;">
                                            <?= $staff['status']==='active' ? '⏸ Deactivate' : '▶ Activate' ?>
                                        </button>
                                    </form>
                                    <button class="btn btn-gray btn-sm"
                                            onclick='openEditStaff(<?= json_encode($staff) ?>)'>
                                        ✏️ Edit
                                    </button>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Delete <?= htmlspecialchars($staff['name'],ENT_QUOTES) ?>\'s account?')">
                                        <input type="hidden" name="action"   value="delete_staff">
                                        <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                        <button type="submit" class="btn btn-red btn-sm">🗑 Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php elseif ($panel === 'notifications'): ?>
                    <!-- ── Notifications ── -->
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                        <a href="settings.php" class="btn btn-gray btn-sm">← Back</a>
                        <h3 style="margin-bottom:0;">🔔 Notifications</h3>
                    </div>
                    <?php foreach([
                        ['New Appointment Bookings','Alert when a client books',true],
                        ['New Client Registrations','Alert on new sign-ups',true],
                        ['Messages from Clients','Notify on incoming messages',true],
                        ['Low Stock Alerts','Warn when inventory runs low',true],
                        ['Upcoming Vaccine Due Dates','Remind about due vaccines',false],
                        ['Overdue Billing Reminders','Alert on unpaid transactions',true],
                    ] as $ni): ?>
                        <div class="nr">
                            <div>
                                <div style="font-weight:700;font-size:14px;margin-bottom:2px;"><?= htmlspecialchars($ni[0]) ?></div>
                                <div style="font-size:12px;color:var(--text-light);"><?= htmlspecialchars($ni[1]) ?></div>
                            </div>
                            <label class="tog">
                                <input type="checkbox" <?= $ni[2]?'checked':'' ?>>
                                <span class="tsl"></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <div style="margin-top:20px;">
                        <button class="btn btn-teal" onclick="alert('Notification preferences saved!')">Save Preferences</button>
                    </div>

                    <?php elseif ($panel === 'about'): ?>
                    <!-- ── About ── -->
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                        <a href="settings.php" class="btn btn-gray btn-sm">← Back</a>
                        <h3 style="margin-bottom:0;">❓ About Us</h3>
                    </div>
                    <div style="text-align:center;margin-bottom:24px;">
                        <div style="font-size:56px;margin-bottom:10px;">🐾</div>
                        <h2 style="font-family:var(--font-head);font-size:19px;font-weight:800;">Ligao Petcare &amp; Veterinary Clinic</h2>
                        <p style="font-size:12px;color:var(--text-light);">PetCare Management System v1.0</p>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <?php foreach([
                            ['📍','Address','National Highway, Zone 4, Tuburan, Ligao City, Albay'],
                            ['👩‍⚕️','Clinic Head','Dr. Ann Lawrence S. Polidario, DVM'],
                            ['📞','Contact','0926-396-7678 · 0950-138-1530'],
                            ['📘','Facebook','Ligao Petcare & Veterinary Clinic'],
                        ] as $ib): ?>
                            <div style="background:rgba(0,188,212,.07);border-radius:var(--radius-sm);
                                        padding:14px;border:1px solid var(--teal-light);">
                                <div style="font-size:22px;margin-bottom:6px;"><?= $ib[0] ?></div>
                                <div style="font-size:11px;color:var(--text-light);font-weight:700;
                                            text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;"><?= $ib[1] ?></div>
                                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($ib[2]) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php elseif ($panel === 'terms'): ?>
                    <!-- ── Terms ── -->
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                        <a href="settings.php" class="btn btn-gray btn-sm">← Back</a>
                        <h3 style="margin-bottom:0;">📄 Terms &amp; Conditions</h3>
                    </div>
                    <div class="pc">
                        <p>Welcome to the PetCare System admin panel. By accessing this panel, you agree to the following:</p>
                        <h4>Administrator Responsibilities</h4>
                        <ul>
                            <li>Maintain accurate records of clients, pets, appointments, and transactions</li>
                            <li>Admin accounts must not be shared with unauthorized personnel</li>
                            <li>Any misuse of the admin panel is strictly prohibited</li>
                        </ul>
                        <h4>Data Management</h4>
                        <ul>
                            <li>All client and pet data must be handled with confidentiality</li>
                            <li>Medical records may only be updated by authorized veterinary staff</li>
                            <li>Billing information must accurately reflect services availed</li>
                        </ul>
                        <h4>System Usage</h4>
                        <ul>
                            <li>This system is exclusively for Ligao Petcare & Veterinary Clinic operations</li>
                            <li>Regular database backups are strongly recommended</li>
                        </ul>
                        <h4>Privacy Compliance</h4>
                        <ul>
                            <li>Client personal data must not be shared without consent</li>
                            <li>The clinic complies with applicable data privacy laws</li>
                        </ul>
                    </div>

                    <?php else: ?>
                    <!-- ── Default Overview ── -->
                    <h3>⚙️ Settings</h3>
                    <p style="font-size:13px;color:var(--text-light);margin-bottom:20px;">
                        Select a settings category below.
                    </p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <?php foreach([
                            ['account','👤','Account Settings','Edit profile and password'],
                            ['staff',  '👥','Staff Management','Manage Doc Ann & Mam Kristen'],
                            ['notifications','🔔','Notifications','Configure admin alerts'],
                            ['about','❓','About Us','Clinic info and system details'],
                            ['terms','📄','Terms & Conditions','Admin usage policies'],
                        ] as $ov): ?>
                            <a href="?panel=<?= $ov[0] ?>"
                               style="text-decoration:none;background:rgba(255,255,255,.8);
                                      border:1.5px solid var(--border);border-radius:var(--radius);
                                      padding:16px;transition:var(--transition);display:block;"
                               onmouseover="this.style.borderColor='var(--teal)'"
                               onmouseout="this.style.borderColor='var(--border)'">
                                <div style="font-size:28px;margin-bottom:8px;"><?= $ov[1] ?></div>
                                <div style="font-weight:700;font-size:14px;margin-bottom:4px;"><?= htmlspecialchars($ov[2]) ?></div>
                                <div style="font-size:12px;color:var(--text-light);"><?= htmlspecialchars($ov[3]) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal-overlay" id="addStaffModal">
    <div class="modal" style="max-width:480px;">
        <button class="modal-close"
                onclick="document.getElementById('addStaffModal').classList.remove('open')">×</button>
        <h3 class="modal-title">👥 Add Staff Account</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_staff">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="staff_name" placeholder="e.g. Dr. Ann Lawrence S. Polidario" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Position / Role</label>
                    <select name="staff_position">
                        <option value="Veterinarian">Veterinarian</option>
                        <option value="Clinic Staff">Clinic Staff</option>
                        <option value="Groomer">Groomer</option>
                        <option value="Vet Assistant">Vet Assistant</option>
                        <option value="Multitasker">Multitasker</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Contact No.</label>
                    <input type="text" name="staff_contact" placeholder="e.g. 09261234567">
                </div>
            </div>
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="staff_email" placeholder="e.g. drann@ligaopetcare.com" required>
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="staff_password" placeholder="Min. 6 characters" required>
            </div>
            <div style="background:#e0f7fa;border-radius:var(--radius-sm);padding:10px 14px;
                        font-size:12px;color:var(--teal-dark);margin-bottom:14px;">
                💡 Share the login credentials with the staff member after creation.
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('addStaffModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal-overlay" id="editStaffModal">
    <div class="modal" style="max-width:480px;">
        <button class="modal-close"
                onclick="document.getElementById('editStaffModal').classList.remove('open')">×</button>
        <h3 class="modal-title">✏️ Edit Staff Account</h3>
        <form method="POST">
            <input type="hidden" name="action"   value="edit_staff">
            <input type="hidden" name="staff_id" id="es_id">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="staff_name" id="es_name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Position / Role</label>
                    <select name="staff_position" id="es_pos">
                        <option value="Veterinarian">Veterinarian</option>
                        <option value="Clinic Staff">Clinic Staff</option>
                        <option value="Groomer">Groomer</option>
                        <option value="Vet Assistant">Vet Assistant</option>
                        <option value="Multitasker">Multitasker</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Contact No.</label>
                    <input type="text" name="staff_contact" id="es_contact">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="staff_email" id="es_email" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="staff_status" id="es_status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>New Password <span style="color:var(--text-light);font-weight:400;">(leave blank to keep current)</span></label>
                <input type="password" name="staff_new_password" placeholder="Min. 6 characters">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('editStaffModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function prevAv(input) {
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => {
            document.getElementById('avWrap').innerHTML =
                `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
        };
        r.readAsDataURL(input.files[0]);
    }
}

function openEditStaff(s) {
    document.getElementById('es_id').value      = s.id;
    document.getElementById('es_name').value    = s.name;
    document.getElementById('es_email').value   = s.email;
    document.getElementById('es_contact').value = s.contact_no || '';
    document.getElementById('es_pos').value     = s.position   || 'Clinic Staff';
    document.getElementById('es_status').value  = s.status     || 'active';
    document.getElementById('editStaffModal').classList.add('open');
}

// Close modals on outside click
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });
});
</script>
</body>
</html>