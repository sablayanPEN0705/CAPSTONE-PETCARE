<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/profile.php
// Purpose: Pet Owner Profile — Basic Info + My Pets
// ============================================================
require_once '../includes/auth.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id = (int)$_SESSION['user_id'];
$user    = getRow($conn, "SELECT * FROM users WHERE id=$user_id");
$pets    = getRows($conn, "SELECT * FROM pets WHERE user_id=$user_id AND status='active' ORDER BY name ASC");

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// ── Handle profile update ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $name    = sanitize($conn, $_POST['name']    ?? '');
    $address = sanitize($conn, $_POST['address'] ?? '');
    $contact = sanitize($conn, $_POST['contact'] ?? '');
    $email   = sanitize($conn, $_POST['email']   ?? '');

    if (!$name || !$email) redirect('profile.php?error=Name and email are required.');

    $taken = getRow($conn, "SELECT id FROM users WHERE email='$email' AND id!=$user_id");
    if ($taken) redirect('profile.php?error=Email already used by another account.');

    $photo_sql = '';
    if (!empty($_FILES['profile_picture']['name'])) {
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            // ── FIXED: upload to assets/images/avatars/ to match the sidebar path ──
            $upload_dir_disk = dirname(__DIR__) . '/assets/images/avatars/';
            if (!is_dir($upload_dir_disk)) mkdir($upload_dir_disk, 0755, true);
            $fn = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir_disk . $fn);
            $photo_sql = ", profile_picture='$fn'";
        }
    }

    mysqli_query($conn,
        "UPDATE users SET name='$name', address='$address',
         contact_no='$contact', email='$email' $photo_sql WHERE id=$user_id");
    $_SESSION['user_name'] = $name;
    redirect('profile.php?success=Profile updated successfully!');
}

// ── Handle pet delete ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_pet') {
    $pet_id = (int)($_POST['pet_id'] ?? 0);
    if ($pet_id) {
        $pet = getRow($conn, "SELECT id FROM pets WHERE id=$pet_id AND user_id=$user_id");
        if ($pet) {
            mysqli_query($conn, "UPDATE pets SET status='deleted' WHERE id=$pet_id AND user_id=$user_id");
            redirect('profile.php?success=Pet removed successfully.');
        } else {
            redirect('profile.php?error=Pet not found or access denied.');
        }
    }
    redirect('profile.php?error=Invalid request.');
}

// ── Avatar path ───────────────────────────────────────────────
// Disk base uses absolute path so file_exists() is always reliable.
// Web base matches what sidebar_user.php uses: ../assets/images/avatars/
$_avatar_disk_base = dirname(__DIR__) . '/assets/images/avatars/';
$_avatar_web_base  = '../assets/images/avatars/';
$_avatar_default   = $user['gender'] === 'male' ? 'boy_profile.jpg' : 'girl_profile.jpg';

if (!empty($user['profile_picture']) && file_exists($_avatar_disk_base . $user['profile_picture'])) {
    $avatar_src = $_avatar_web_base . htmlspecialchars($user['profile_picture']);
    $use_img    = true;
} elseif (file_exists($_avatar_disk_base . $_avatar_default)) {
    $avatar_src = $_avatar_web_base . $_avatar_default;
    $use_img    = true;
} else {
    $avatar_src = '';
    $use_img    = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Ligao Petcare</title>
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

        /* Avatar */
        .profile-avatar-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }
        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--teal-light);
            box-shadow: var(--shadow);
        }
        .profile-avatar-placeholder {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), #0097a7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 800;
            color: #fff;
            border: 3px solid var(--teal-light);
            box-shadow: var(--shadow);
        }

        /* Info rows */
        .info-row {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            align-items: flex-start;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            width: 100px;
            font-weight: 700;
            color: var(--text-dark);
            flex-shrink: 0;
        }
        .info-value { color: var(--text-mid); font-weight: 500; }

        /* Pets grid */
        .pets-grid-profile {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .pet-profile-card {
            background: #fff;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            text-decoration: none;
            display: block;
            position: relative;
        }
        .pet-profile-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .pet-profile-img {
            height: 110px;
            background: var(--teal-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 44px;
            overflow: hidden;
        }
        .pet-profile-img img { width: 100%; height: 100%; object-fit: cover; }
        .pet-profile-body { padding: 10px 12px; }
        .pet-profile-name { font-weight: 800; font-size: 14px; color: var(--text-dark); margin-bottom: 4px; }
        .pet-profile-detail { font-size: 12px; color: var(--text-light); margin-bottom: 2px; }

        /* Delete button on pet card */
        .pet-delete-btn {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: rgba(220, 53, 69, 0.85);
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
            transition: background 0.2s, transform 0.15s;
            z-index: 2;
        }
        .pet-delete-btn:hover { background: rgba(185, 28, 40, 0.95); transform: scale(1.12); }

        /* Delete confirmation modal */
        .delete-pet-icon  { font-size: 52px; margin-bottom: 10px; }
        .delete-pet-msg   { font-size: 14px; color: var(--text-mid); margin-bottom: 20px; line-height: 1.5; }
        .delete-pet-name  { font-weight: 800; color: var(--text-dark); }

        @media(max-width:700px) {
            .profile-page-layout { grid-template-columns: 1fr; }
            .pets-grid-profile   { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_user.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title" style="display:flex;align-items:center;gap:10px;">
                <img src="../assets/images/pets/logo.png" alt="Logo"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.6);"
                     onerror="this.style.display='none';">
                Pet Owner Profile
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔</a>
                <a href="settings.php"      class="topbar-btn">⚙️</a>
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

                    <div class="profile-avatar-wrap">
                        <?php if ($use_img): ?>
                            <img
                                src="<?= $avatar_src ?>"
                                class="profile-avatar"
                                alt="<?= htmlspecialchars($user['name']) ?>"
                                onerror="this.style.display='none';document.getElementById('avatarFallback').style.display='flex';"
                            >
                            <div class="profile-avatar-placeholder" id="avatarFallback" style="display:none;">
                                <?= strtoupper(substr($user['name'], 0, 2)) ?>
                            </div>
                        <?php else: ?>
                            <div class="profile-avatar-placeholder" id="avatarFallback">
                                <?= strtoupper(substr($user['name'], 0, 2)) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="info-row">
                        <span class="info-label">Name</span>
                        <span class="info-value"><?= htmlspecialchars($user['name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?= htmlspecialchars($user['address'] ?? '—') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Contact No.</span>
                        <span class="info-value"><?= htmlspecialchars($user['contact_no'] ?? '—') ?></span>
                    </div>
                </div>

                <!-- My Pets -->
                <div class="profile-panel">
                    <h3>
                        My Pets
                        <a href="pet_profile.php" class="btn btn-gray btn-sm">View All →</a>
                    </h3>

                    <?php if (empty($pets)): ?>
                        <div style="text-align:center;padding:32px;color:var(--text-light);">
                            <div style="font-size:48px;margin-bottom:10px;">🐾</div>
                            <p style="font-weight:700;">No pets added yet.</p>
                            <a href="pet_profile.php" class="btn btn-teal btn-sm" style="margin-top:10px;">
                                + Add Your First Pet
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="pets-grid-profile">
                            <?php foreach ($pets as $pet): ?>
                                <div class="pet-profile-card" style="position:relative;">
                                    <button
                                        class="pet-delete-btn"
                                        title="Remove <?= htmlspecialchars($pet['name']) ?>"
                                        onclick="confirmDeletePet(<?= $pet['id'] ?>, '<?= htmlspecialchars(addslashes($pet['name'])) ?>', '<?= $pet['species'] === 'cat' ? '🐱' : '🐶' ?>')"
                                    >✕</button>

                                    <a href="pet_profile.php?view=<?= $pet['id'] ?>" style="text-decoration:none;display:block;">
                                        <div class="pet-profile-img">
                                            <?php if ($pet['photo']): ?>
                                                <img src="../assets/css/images/pets/<?= htmlspecialchars($pet['photo']) ?>"
                                                     alt="<?= htmlspecialchars($pet['name']) ?>"
                                                     onerror="this.parentElement.innerHTML='<?= $pet['species']==='cat'?'🐱':'🐶' ?>'">
                                            <?php else: ?>
                                                <?= $pet['species']==='cat' ? '🐱' : '🐶' ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="pet-profile-body">
                                            <div class="pet-profile-name"><?= htmlspecialchars($pet['name']) ?></div>
                                            <div class="pet-profile-detail">
                                                <?= htmlspecialchars($pet['breed'] ?: ucfirst($pet['species'])) ?>
                                            </div>
                                            <div class="pet-profile-detail">
                                                <?= htmlspecialchars($pet['age'] ?? '?') ?> yrs old
                                            </div>
                                            <div class="pet-profile-detail">
                                                <?= ucfirst($pet['gender']) ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
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
                <div style="margin-bottom:8px;">
                    <img
                        id="profilePicPreview"
                        src="<?= $avatar_src ?>"
                        style="width:60px;height:60px;object-fit:cover;border-radius:50%;
                               border:2px solid var(--teal-light);
                               <?= $use_img ? '' : 'display:none;' ?>"
                        onerror="this.style.display='none';"
                        alt="Current photo"
                    >
                </div>
                <input type="file" name="profile_picture" accept="image/*"
                       onchange="prevProfilePic(this)" style="font-size:12px;">
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
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
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

<!-- Delete Pet Confirmation Modal -->
<div class="modal-overlay" id="deletePetModal">
    <div class="modal" style="max-width:380px;text-align:center;">
        <button class="modal-close"
                onclick="document.getElementById('deletePetModal').classList.remove('open')">×</button>
        <div class="delete-pet-icon" id="deletePetEmoji">🐾</div>
        <h3 class="modal-title" style="justify-content:center;">Remove Pet?</h3>
        <p class="delete-pet-msg">
            Are you sure you want to remove <span class="delete-pet-name" id="deletePetNameDisplay"></span> from your profile?
            <br><span style="font-size:12px;color:var(--text-light);">This action cannot be undone.</span>
        </p>
        <form method="POST" id="deletePetForm">
            <input type="hidden" name="action" value="delete_pet">
            <input type="hidden" name="pet_id" id="deletePetIdInput" value="">
            <div class="modal-actions" style="justify-content:center;">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('deletePetModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn" style="background:#dc3545;color:#fff;">🗑️ Remove Pet</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function prevProfilePic(input) {
    if (input.files && input.files[0]) {
        const r = new FileReader();
        r.onload = e => {
            const img = document.getElementById('profilePicPreview');
            img.src = e.target.result;
            img.style.display = 'block';
        };
        r.readAsDataURL(input.files[0]);
    }
}

function confirmDeletePet(petId, petName, petEmoji) {
    document.getElementById('deletePetIdInput').value           = petId;
    document.getElementById('deletePetNameDisplay').textContent = petName;
    document.getElementById('deletePetEmoji').textContent       = petEmoji;
    document.getElementById('deletePetModal').classList.add('open');
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});
</script>

<?php include_once __DIR__ . '/../includes/chatbot-widget.php'; ?>



</body>
</html>