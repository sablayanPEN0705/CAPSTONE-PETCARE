<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: includes/sidebar_admin.php
// Purpose: Reusable left sidebar for all ADMIN pages
// Usage: include this after require auth.php on every admin page
// ============================================================

$current_page = basename($_SERVER['PHP_SELF']);

// Get unread message count for admin
$unread_msgs = getUnreadMessages($conn, $_SESSION['user_id']);

// Fetch admin profile picture
$admin_data     = getRow($conn, "SELECT name, profile_picture FROM users WHERE id=" . (int)$_SESSION['user_id']);
$admin_name     = $admin_data['name'] ?? ($_SESSION['user_name'] ?? 'Administrator');
$admin_pic      = $admin_data['profile_picture'] ?? '';
$admin_initials = strtoupper(substr($admin_name, 0, 2));

// ── Avatar resolution ─────────────────────────────────────────
// Use absolute disk path for file_exists() so it works correctly
// regardless of which admin page is including this sidebar.
// Web path:  ../assets/images/avatars/  (relative from admin/ pages)
// Disk path: <project_root>/assets/images/avatars/
$_avatar_disk_base = dirname(__DIR__) . '/assets/images/avatars/';
$_avatar_web_base  = '../assets/images/avatars/';

if (!empty($admin_pic) && file_exists($_avatar_disk_base . $admin_pic)) {
    $avatar_src  = $_avatar_web_base . htmlspecialchars($admin_pic);
    $avatar_show = true;
} else {
    $avatar_src  = '';
    $avatar_show = false;
}

// Admin nav items: [label, file, icon]
$nav_items = [
    ['Dashboard',         'dashboard.php',       '🏠'],
    ['Appointments',      'appointments.php',    '📅'],
    ['Client Record',     'client_records.php',  '📋'],
    ['Pet Records',       'pet_records.php',     '🐾'],
    ['Message',           'messages.php',        '💬'],
    ['Services',          'services.php',        '⚕️'],
    ['Transaction',       'transactions.php',    '📄'],
    ['Product Inventory', 'inventory.php',       '📦'],
    ['Settings',          'settings.php',        '⚙️'],
];
?>

<aside class="sidebar">

    <!-- Admin Profile Card -->
    <a href="profile.php" class="sidebar-logo"
       style="flex-direction:column; align-items:center; padding:20px 16px 16px; gap:10px;
              text-decoration:none; cursor:pointer; transition:var(--transition);"
       onmouseover="this.style.background='rgba(0,0,0,0.05)'"
       onmouseout="this.style.background='transparent'">

        <!-- Profile Picture -->
        <div style="position:relative; width:72px; height:72px; flex-shrink:0;">

            <?php if ($avatar_show): ?>
                <img src="<?= $avatar_src ?>"
                     alt="<?= htmlspecialchars($admin_name) ?>"
                     id="sidebarAdminAvatar"
                     style="width:72px; height:72px; border-radius:50%; object-fit:cover;
                            border:3px solid rgba(255,255,255,0.6);
                            box-shadow:0 2px 8px rgba(0,0,0,0.2);"
                     onerror="this.style.display='none'; document.getElementById('sidebarAdminInitials').style.display='flex';">
                <div id="sidebarAdminInitials"
                     style="display:none; width:72px; height:72px; border-radius:50%;
                            background:linear-gradient(135deg,rgba(255,255,255,0.3),rgba(255,255,255,0.1));
                            border:3px solid rgba(255,255,255,0.6);
                            align-items:center; justify-content:center;
                            font-size:26px; font-weight:800; color:#fff;
                            box-shadow:0 2px 8px rgba(0,0,0,0.2);">
                    <?= $admin_initials ?>
                </div>
            <?php else: ?>
                <div id="sidebarAdminInitials"
                     style="display:flex; width:72px; height:72px; border-radius:50%;
                            background:linear-gradient(135deg,rgba(255,255,255,0.3),rgba(255,255,255,0.1));
                            border:3px solid rgba(255,255,255,0.6);
                            align-items:center; justify-content:center;
                            font-size:26px; font-weight:800; color:#fff;
                            box-shadow:0 2px 8px rgba(0,0,0,0.2);">
                    <?= $admin_initials ?>
                </div>
            <?php endif; ?>

            <!-- Online indicator dot -->
            <span style="position:absolute; bottom:3px; right:3px;
                         width:14px; height:14px; border-radius:50%;
                         background:#10b981; border:2px solid #fff;
                         box-shadow:0 1px 3px rgba(0,0,0,0.3);">
            </span>
        </div>

        <!-- Name + Role -->
        <div style="text-align:center;">
            <div class="username" style="font-size:14px; font-weight:800; letter-spacing:0.5px;">
                <?= htmlspecialchars($admin_name) ?>
            </div>
            <div class="role" style="font-size:11px; opacity:0.75; margin-top:2px;">
                Administrator
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