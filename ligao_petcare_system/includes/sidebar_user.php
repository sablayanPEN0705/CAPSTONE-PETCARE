<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: includes/sidebar_user.php
// Purpose: Reusable left sidebar for all USER pages
// Usage: include this after require auth.php on every user page
// ============================================================

$current_page = basename($_SERVER['PHP_SELF']);

// Get unread counts for badges
$unread_msgs  = getUnreadMessages($conn, $_SESSION['user_id']);
$unread_notif = getUnreadNotifications($conn, $_SESSION['user_id']);

// Fetch user profile picture + gender
$user_data     = getRow($conn, "SELECT name, profile_picture, gender FROM users WHERE id=" . (int)$_SESSION['user_id']);
$user_name     = $user_data['name']            ?? ($_SESSION['user_name'] ?? 'Pet Owner');
$user_gender   = $user_data['gender']          ?? 'female';
$user_pic      = $user_data['profile_picture'] ?? '';
$user_initials = strtoupper(substr($user_name, 0, 2));

// ── Avatar resolution ─────────────────────────────────────────
// Use dirname(__DIR__) for disk check so file_exists() works
// correctly regardless of which page includes this sidebar.
$_avatar_disk_base = dirname(__DIR__) . '/assets/images/avatars/';
$_avatar_web_base  = '../assets/images/avatars/';
$_avatar_default   = $user_gender === 'male' ? 'boy_profile.jpg' : 'girl_profile.jpg';

if (!empty($user_pic) && file_exists($_avatar_disk_base . $user_pic)) {
    $avatar_src  = $_avatar_web_base . htmlspecialchars($user_pic);
    $avatar_show = true;
} elseif (file_exists($_avatar_disk_base . $_avatar_default)) {
    $avatar_src  = $_avatar_web_base . $_avatar_default;
    $avatar_show = true;
} else {
    $avatar_src  = '';
    $avatar_show = false;
}

// Nav items: [label, file, icon emoji]
$nav_items = [
    ['Home',         'dashboard.php',    '🏠'],
    ['Pet Profile',  'pet_profile.php',  '🐾'],
    ['Services',     'services.php',     '💉'],   // veterinary/medical services
    ['Product',      'products.php',     '🛍️'],
    ['Appointment',  'appointments.php', '📅'],
    ['Transactions', 'transactions.php', '🧾'],
    ['Message',      'messages.php',     '💬'],
    ['About Us',     'about.php',        'ℹ️'],
    ['Settings',     'settings.php',     '⚙️'],
];
?>

<aside class="sidebar">

    <!-- User Profile Card -->
    <a href="profile.php" class="sidebar-logo"
       style="flex-direction:column; align-items:center; padding:20px 16px 16px; gap:10px;
              text-decoration:none; cursor:pointer; transition:var(--transition);"
       onmouseover="this.style.background='rgba(0,0,0,0.05)'"
       onmouseout="this.style.background='transparent'">

        <!-- Profile Picture -->
        <div style="position:relative; width:72px; height:72px; flex-shrink:0;">

            <?php if ($avatar_show): ?>
                <img src="<?= $avatar_src ?>"
                     alt="<?= htmlspecialchars($user_name) ?>"
                     id="sidebarUserAvatar"
                     style="width:72px; height:72px; border-radius:50%; object-fit:cover;
                            border:3px solid rgba(255,255,255,0.6);
                            box-shadow:0 2px 8px rgba(0,0,0,0.2);"
                     onerror="this.style.display='none'; document.getElementById('sidebarUserInitials').style.display='flex';">
                <div id="sidebarUserInitials"
                     style="display:none; width:72px; height:72px; border-radius:50%;
                            background:linear-gradient(135deg,rgba(255,255,255,0.3),rgba(255,255,255,0.1));
                            border:3px solid rgba(255,255,255,0.6);
                            align-items:center; justify-content:center;
                            font-size:26px; font-weight:800; color:#fff;
                            box-shadow:0 2px 8px rgba(0,0,0,0.2);">
                    <?= $user_initials ?>
                </div>
            <?php else: ?>
                <div id="sidebarUserInitials"
                     style="display:flex; width:72px; height:72px; border-radius:50%;
                            background:linear-gradient(135deg,rgba(255,255,255,0.3),rgba(255,255,255,0.1));
                            border:3px solid rgba(255,255,255,0.6);
                            align-items:center; justify-content:center;
                            font-size:26px; font-weight:800; color:#fff;
                            box-shadow:0 2px 8px rgba(0,0,0,0.2);">
                    <?= $user_initials ?>
                </div>
            <?php endif; ?>

            <!-- Online green dot -->
            <span style="position:absolute; bottom:3px; right:3px;
                         width:14px; height:14px; border-radius:50%;
                         background:#10b981; border:2px solid #fff;
                         box-shadow:0 1px 3px rgba(0,0,0,0.3);">
            </span>
        </div>

        <!-- Name + Role -->
        <div style="text-align:center;">
            <div class="username" style="font-size:14px; font-weight:800; letter-spacing:0.5px;">
                <?= htmlspecialchars($user_name) ?>
            </div>
            <div class="role" style="font-size:11px; opacity:0.75; margin-top:2px;">
                Pet Owner
            </div>
        </div>

        <!-- Clinic logo -->
        <img src="../assets/images/logo.png"
             alt="Ligao Petcare"
             style="width:36px; height:36px; border-radius:50%; object-fit:cover;
                    border:2px solid rgba(255,255,255,0.4); margin-top:2px;"
             onerror="this.style.display='none';">
    </a>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <?php foreach ($nav_items as $item): ?>
            <?php
                $is_active = ($current_page === $item[1]) ? 'active' : '';
                $badge = '';
                if ($item[1] === 'messages.php' && $unread_msgs > 0) {
                    $badge = "<span class='badge-count' style='position:static;margin-left:auto;'>{$unread_msgs}</span>";
                }
            ?>
            <a href="<?= $item[1] ?>" class="<?= $is_active ?>">
                <span class="nav-icon"><?= $item[2] ?></span>
                <span><?= $item[0] ?></span>
                <?= $badge ?>
            </a>
        <?php endforeach; ?>

        <!-- Logout -->
        <div class="nav-logout" style="margin-top:auto; padding-top:12px; border-top:1px solid var(--border);">
            <a href="#" onclick="confirmLogout(event)">
                <span class="nav-icon">🚪</span>
                <span>Log Out</span>
            </a>
        </div>
    </nav>
</aside>

<!-- Logout Confirmation Modal -->
<div class="modal-overlay" id="logoutModal">
    <div class="modal" style="max-width:380px; text-align:center;">
        <div style="font-size:48px; margin-bottom:12px;">⚠️</div>
        <h3 class="modal-title">Log out?</h3>
        <p style="font-size:14px; color:var(--text-mid); margin-bottom:24px;">
            Are you sure you want to log out?<br>You will be signed out of your account.
        </p>
        <div class="modal-actions">
            <button class="btn btn-gray" onclick="closeLogoutModal()">Cancel</button>
            <a href="../logout.php" class="btn btn-red">Log out</a>
        </div>
    </div>
</div>

<script>
function confirmLogout(e) {
    e.preventDefault();
    document.getElementById('logoutModal').classList.add('open');
}
function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('open');
}
document.getElementById('logoutModal').addEventListener('click', function(e) {
    if (e.target === this) closeLogoutModal();
});
</script>