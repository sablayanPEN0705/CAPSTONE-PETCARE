<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/profile.php
// Purpose: Admin Profile — Basic Info + Staff List
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$admin_id = (int)$_SESSION['user_id'];
$admin    = getRow($conn, "SELECT * FROM users WHERE id=$admin_id");
$staff    = getRows($conn, "SELECT * FROM users WHERE role='staff' ORDER BY name ASC");

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// ── Handle profile update ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $name    = sanitize($conn, $_POST['name']    ?? '');
    $address = sanitize($conn, $_POST['address'] ?? '');
    $contact = sanitize($conn, $_POST['contact'] ?? '');
    $email   = sanitize($conn, $_POST['email']   ?? '');

    if (!$name || !$email) redirect('profile.php?error=Name and email are required.');
    $taken = getRow($conn, "SELECT id FROM users WHERE email='$email' AND id!=$admin_id");
    if ($taken) redirect('profile.php?error=Email already used by another account.');

    $psql = '';
    if (!empty($_FILES['profile_picture']['name'])) {
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            // ── FIXED: upload to assets/images/avatars/ to match sidebar path ──
            $upload_dir_disk = dirname(__DIR__) . '/assets/images/avatars/';
            if (!is_dir($upload_dir_disk)) mkdir($upload_dir_disk, 0755, true);
            $fn = 'av_adm_' . $admin_id . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir_disk . $fn);
            $psql = ", profile_picture='$fn'";
        }
    }

    mysqli_query($conn,
        "UPDATE users SET name='$name', address='$address',
         contact_no='$contact', email='$email' $psql WHERE id=$admin_id");
    $_SESSION['user_name'] = $name;
    redirect('profile.php?success=Profile updated successfully!');
}

// ── Avatar resolution ─────────────────────────────────────────
// Use absolute disk path for file_exists() — matches sidebar_admin.php path
$_avatar_disk_base = dirname(__DIR__) . '/assets/images/avatars/';
$_avatar_web_base  = '../assets/images/avatars/';

if (!empty($admin['profile_picture']) && file_exists($_avatar_disk_base . $admin['profile_picture'])) {
    $avatar_src  = $_avatar_web_base . htmlspecialchars($admin['profile_picture']);
    $use_img     = true;
} else {
    $avatar_src  = '';
    $use_img     = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-page-layout {
            display: grid;
            grid-template-columns: 1fr 1.4fr;
            gap: 20px;
            align-items: start;
        }
        .profile-panel {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
        }
        .profile-panel h3 {
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .profile-avatar-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
            gap: 8px;
        }
        .profile-avatar {
            width: 90px; height: 90px;
            border-radius: 50%; object-fit: cover;
            border: 3px solid var(--teal-light);
            box-shadow: var(--shadow);
        }
        .profile-avatar-placeholder {
            width: 90px; height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), #0097a7);
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; font-weight: 800; color: #fff;
            border: 3px solid var(--teal-light);
            box-shadow: var(--shadow);
        }
        .info-row {
            display: flex; gap: 12px;
            padding: 10px 0; border-bottom: 1px solid var(--border);
            font-size: 13px; align-items: flex-start;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { width: 100px; font-weight: 700; color: var(--text-dark); flex-shrink: 0; }
        .info-value { color: var(--text-mid); font-weight: 500; }

        /* Staff cards */
        .staff-profile-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            background: #fff;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            margin-bottom: 12px;
            transition: var(--transition);
        }
        .staff-profile-card:hover { border-color: var(--teal-light); }
        .staff-profile-avatar {
            width: 48px; height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), #0097a7);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: #fff; font-weight: 700;
            flex-shrink: 0; overflow: hidden;
        }
        .staff-profile-avatar img { width:100%; height:100%; object-fit:cover; }
        .staff-profile-info .sp-name    { font-weight: 800; font-size: 14px; color: var(--text-dark); }
        .staff-profile-info .sp-pos     { font-size: 12px; color: var(--text-light); margin-top: 2px; }
        .staff-profile-info .sp-contact { font-size: 12px; color: var(--text-mid); margin-top: 2px; }

        @media(max-width:700px) {
            .profile-page-layout { grid-template-columns: 1fr; }
        }
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
                Admin Profile
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔
                    <?php $un = getUnreadNotifications($conn, $_SESSION['user_id']); if ($un > 0): ?>
                        <span class="badge-count"><?= $un ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="profile-page-layout">

                <!-- Basic Info -->
                <div class="profile-panel">
                    <h3>
                        Basic Info
                        <button class="btn btn-teal btn-sm"
                                onclick="document.getElementById('editProfileModal').classList.add('open')"
                                style="font-size:16px;padding:4px 10px;">✏️</button>
                    </h3>

                    <!-- Avatar: file_exists check on disk prevents broken <img> -->
                    <div class="profile-avatar-wrap">
                        <?php if ($use_img): ?>
                            <img
                                src="<?= $avatar_src ?>"
                                class="profile-avatar"
                                alt="<?= htmlspecialchars($admin['name']) ?>"
                                onerror="this.style.display='none';document.getElementById('adminAvatarFallback').style.display='flex';"
                            >
                            <div class="profile-avatar-placeholder" id="adminAvatarFallback" style="display:none;">
                                <?= strtoupper(substr($admin['name'], 0, 2)) ?>
                            </div>
                        <?php else: ?>
                            <div class="profile-avatar-placeholder" id="adminAvatarFallback">
                                <?= strtoupper(substr($admin['name'], 0, 2)) ?>
                            </div>
                        <?php endif; ?>
                        <span style="font-size:12px;font-weight:700;color:var(--teal-dark);
                                     background:var(--teal-light);padding:2px 12px;
                                     border-radius:20px;">Administrator</span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Name</span>
                        <span class="info-value"><?= htmlspecialchars($admin['name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?= htmlspecialchars($admin['address'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($admin['email']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Contact No.</span>
                        <span class="info-value"><?= htmlspecialchars($admin['contact_no'] ?? '—') ?></span>
                    </div>

                    <div style="margin-top:16px;">
                        <a href="settings.php?panel=account" class="btn btn-gray btn-sm">
                            🔑 Change Password
                        </a>
                    </div>
                </div>

                <!-- Staff Panel -->
                <div class="profile-panel">
                    <h3>
                        Clinic Staff
                        <a href="settings.php?panel=staff" class="btn btn-gray btn-sm">Manage →</a>
                    </h3>

                    <?php if (empty($staff)): ?>
                        <div style="text-align:center;padding:32px;color:var(--text-light);">
                            <div style="font-size:48px;margin-bottom:10px;">👥</div>
                            <p style="font-weight:700;">No staff accounts yet.</p>
                            <a href="settings.php?panel=staff" class="btn btn-teal btn-sm" style="margin-top:10px;">
                                + Add Staff
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($staff as $s): ?>
                            <div class="staff-profile-card">
                                <div class="staff-profile-avatar">
                                    <?php
                                    // Also fix staff avatars to use the correct path
                                    $staff_disk = dirname(__DIR__) . '/assets/images/avatars/' . ($s['profile_picture'] ?? '');
                                    $staff_web  = '../assets/images/avatars/' . htmlspecialchars($s['profile_picture'] ?? '');
                                    if (!empty($s['profile_picture']) && file_exists($staff_disk)):
                                    ?>
                                        <img src="<?= $staff_web ?>"
                                             alt="<?= htmlspecialchars($s['name']) ?>"
                                             onerror="this.style.display='none';this.parentElement.textContent='<?= strtoupper(substr($s['name'],0,2)) ?>'">
                                    <?php else: ?>
                                        <?= strtoupper(substr($s['name'], 0, 2)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="staff-profile-info">
                                    <div class="sp-name"><?= htmlspecialchars($s['name']) ?></div>
                                    <div class="sp-pos">
                                        <?= htmlspecialchars($s['position'] ?? 'Clinic Staff') ?>
                                        &nbsp;·&nbsp;
                                        <span style="color:<?= $s['status']==='active'?'#10b981':'#ef4444' ?>;font-weight:700;">
                                            <?= $s['status']==='active' ? '● Active' : '● Inactive' ?>
                                        </span>
                                    </div>
                                    <?php if ($s['contact_no']): ?>
                                        <div class="sp-contact">📞 <?= htmlspecialchars($s['contact_no']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal-overlay" id="editProfileModal">
    <div class="modal" style="max-width:460px;">
        <button class="modal-close"
                onclick="document.getElementById('editProfileModal').classList.remove('open')">×</button>
        <h3 class="modal-title">✏️ Edit Profile</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
                <label>Profile Photo</label>
                <!-- Show current photo as preview baseline -->
                <div style="margin-bottom:8px;">
                    <img
                        id="picPreview"
                        src="<?= $avatar_src ?>"
                        style="width:60px;height:60px;object-fit:cover;border-radius:50%;
                               border:2px solid var(--teal-light);
                               <?= $use_img ? '' : 'display:none;' ?>"
                        onerror="this.style.display='none';"
                        alt="Current photo"
                    >
                </div>
                <input type="file" name="profile_picture" accept="image/*"
                       onchange="prevPic(this)" style="font-size:12px;">
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
            <div class="modal-actions">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('editProfileModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function prevPic(input) {
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => {
            const img = document.getElementById('picPreview');
            img.src = e.target.result;
            img.style.display = 'block';
        };
        r.readAsDataURL(input.files[0]);
    }
}
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});
</script>
</body>
</html>