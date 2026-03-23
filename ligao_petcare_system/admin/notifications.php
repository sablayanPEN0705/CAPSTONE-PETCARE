<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/notifications.php
// Purpose: Admin notifications — appointments, expiring products,
//          low stock, messages
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$admin_id = (int)$_SESSION['user_id'];

// ════════════════════════════════════════════════════════════
//  AUTO-GENERATE SYSTEM ALERTS
// ════════════════════════════════════════════════════════════

// ── 1. Appointments scheduled within 24 hours ────────────────
$appts_24hr = getRows($conn,
    "SELECT a.*, u.name AS owner_name, p.name AS pet_name, s.name AS service_name
     FROM appointments a
     LEFT JOIN users u    ON a.user_id    = u.id
     LEFT JOIN pets  p    ON a.pet_id     = p.id
     LEFT JOIN services s ON a.service_id = s.id
     WHERE a.status IN ('pending','confirmed')
       AND CONCAT(a.appointment_date,' ',a.appointment_time)
           BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)"
);

foreach ($appts_24hr as $appt) {
    $aid      = (int)$appt['id'];
    $owner    = sanitize($conn, $appt['owner_name'] ?? 'Unknown');
    $pet      = $appt['appointment_type'] === 'home_service'
        ? 'Home Service'
        : sanitize($conn, $appt['pet_name'] ?? 'pet');
    $svc      = $appt['appointment_type'] === 'home_service'
        ? 'Home Service'
        : sanitize($conn, $appt['service_name'] ?? 'appointment');
    $date_lbl = date('M j, Y', strtotime($appt['appointment_date']));
    $time_lbl = date('g:i A',  strtotime($appt['appointment_time']));

    $already = getRow($conn,
        "SELECT id FROM notifications
         WHERE user_id=$admin_id AND type='appt_reminder_admin'
           AND message LIKE '%[appt:$aid]%'"
    );
    if (!$already) {
        $msg = sanitize($conn,
            "Upcoming: $owner's $svc appointment for $pet on $date_lbl at $time_lbl. [appt:$aid]");
        mysqli_query($conn,
            "INSERT INTO notifications (user_id, title, message, type)
             VALUES ($admin_id, '📅 Appointment Today/Tomorrow', '$msg', 'appt_reminder_admin')"
        );
    }
}

// ── 2. Products expiring within 2 weeks ─────────────────────
$expiring = getRows($conn,
    "SELECT * FROM products
     WHERE expiry_date IS NOT NULL
       AND expiry_date BETWEEN CURDATE()
           AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
       AND status != 'out_of_stock'
     ORDER BY expiry_date ASC"
);

foreach ($expiring as $prod) {
    $pid       = (int)$prod['id'];
    $pname     = sanitize($conn, $prod['name']);
    $exp_date  = date('M j, Y', strtotime($prod['expiry_date']));
    $days_left = (int)((strtotime($prod['expiry_date']) - strtotime(date('Y-m-d'))) / 86400);
    $days_lbl  = $days_left === 0 ? 'expires TODAY' : "expires in $days_left day(s)";

    $already = getRow($conn,
        "SELECT id FROM notifications
         WHERE user_id=$admin_id AND type='product_expiry'
           AND message LIKE '%[prod:$pid]%'
           AND DATE(created_at) = CURDATE()"
    );
    if (!$already) {
        $msg = sanitize($conn,
            "Product \"$pname\" $days_lbl ($exp_date). Qty: {$prod['quantity']}. [prod:$pid]");
        mysqli_query($conn,
            "INSERT INTO notifications (user_id, title, message, type)
             VALUES ($admin_id, '⚠️ Product Expiring Soon', '$msg', 'product_expiry')"
        );
    }
}

// ── 3. Low stock products ────────────────────────────────────
$low_stock = getRows($conn,
    "SELECT * FROM products
     WHERE status = 'low_stock' OR (quantity <= 5 AND status != 'out_of_stock')
     ORDER BY quantity ASC"
);

foreach ($low_stock as $prod) {
    $pid   = (int)$prod['id'];
    $pname = sanitize($conn, $prod['name']);
    $qty   = (int)$prod['quantity'];

    $already = getRow($conn,
        "SELECT id FROM notifications
         WHERE user_id=$admin_id AND type='low_stock'
           AND message LIKE '%[prod:$pid]%'
           AND DATE(created_at) = CURDATE()"
    );
    if (!$already) {
        $msg = sanitize($conn,
            "Low stock alert: \"$pname\" has only $qty item(s) remaining. Please restock soon. [prod:$pid]");
        mysqli_query($conn,
            "INSERT INTO notifications (user_id, title, message, type)
             VALUES ($admin_id, '📦 Low Stock Alert', '$msg', 'low_stock')"
        );
    }
}

// ── 4. Out of stock ──────────────────────────────────────────
$out_of_stock = getRows($conn,
    "SELECT * FROM products WHERE status='out_of_stock' ORDER BY name ASC"
);

foreach ($out_of_stock as $prod) {
    $pid   = (int)$prod['id'];
    $pname = sanitize($conn, $prod['name']);

    $already = getRow($conn,
        "SELECT id FROM notifications
         WHERE user_id=$admin_id AND type='out_of_stock'
           AND message LIKE '%[prod:$pid]%'
           AND DATE(created_at) = CURDATE()"
    );
    if (!$already) {
        $msg = sanitize($conn,
            "\"$pname\" is now OUT OF STOCK. Please reorder immediately. [prod:$pid]");
        mysqli_query($conn,
            "INSERT INTO notifications (user_id, title, message, type)
             VALUES ($admin_id, '🚨 Out of Stock', '$msg', 'out_of_stock')"
        );
    }
}

// ── Handle actions ───────────────────────────────────────────
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $nid = (int)$_GET['mark_read'];
    mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE id=$nid AND user_id=$admin_id");
    header("Location: notifications.php"); exit();
}
if (isset($_GET['mark_all_read'])) {
    mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE user_id=$admin_id AND is_read=0");
    header("Location: notifications.php"); exit();
}
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $nid = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM notifications WHERE id=$nid AND user_id=$admin_id");
    header("Location: notifications.php"); exit();
}
if (isset($_GET['clear_all'])) {
    mysqli_query($conn, "DELETE FROM notifications WHERE user_id=$admin_id");
    header("Location: notifications.php"); exit();
}

// ── Filter & paginate ────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$where  = "user_id=$admin_id";
if ($filter === 'unread')       $where .= " AND is_read=0";
if ($filter === 'appointment')  $where .= " AND type IN ('appointment','appt_reminder_admin')";
if ($filter === 'low_stock')    $where .= " AND type='low_stock'";
if ($filter === 'out_of_stock') $where .= " AND type='out_of_stock'";
if ($filter === 'expiry')       $where .= " AND type='product_expiry'";
if ($filter === 'message')      $where .= " AND type='message'";

$per_page    = 12;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$total       = countRows($conn, 'notifications', $where);
$total_pages = max(1, ceil($total / $per_page));

$notifications = getRows($conn,
    "SELECT * FROM notifications
     WHERE $where
     ORDER BY is_read ASC, created_at DESC
     LIMIT $per_page OFFSET $offset"
);

// ── Tab counts ───────────────────────────────────────────────
$cnt_all    = countRows($conn, 'notifications', "user_id=$admin_id");
$cnt_unread = countRows($conn, 'notifications', "user_id=$admin_id AND is_read=0");
$cnt_appt   = countRows($conn, 'notifications', "user_id=$admin_id AND type IN ('appointment','appt_reminder_admin')");
$cnt_low    = countRows($conn, 'notifications', "user_id=$admin_id AND type='low_stock'");
$cnt_out    = countRows($conn, 'notifications', "user_id=$admin_id AND type='out_of_stock'");
$cnt_expiry = countRows($conn, 'notifications', "user_id=$admin_id AND type='product_expiry'");
$cnt_msg    = countRows($conn, 'notifications', "user_id=$admin_id AND type='message'");

// ── Live summary cards (view-only counters) ──────────────────
$live_expiring = countRows($conn, 'products',
    "expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND status!='out_of_stock'");
$live_low      = countRows($conn, 'products',
    "status='low_stock' OR (quantity<=5 AND status!='out_of_stock')");
$live_outstock = countRows($conn, 'products', "status='out_of_stock'");
$live_appts_24 = count($appts_24hr);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Admin — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Alert summary cards — VIEW ONLY, not clickable ── */
        .alert-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        .alert-card {
            border-radius: var(--radius);
            padding: 18px 16px;
            text-align: center;
            box-shadow: var(--shadow);
            color: #fff;
            display: block;
            cursor: default;
            pointer-events: none;
            user-select: none;
        }
        .ac-appointment { background: linear-gradient(135deg, #1565c0, #1976d2); }
        .ac-expiry      { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .ac-lowstock    { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .ac-outstock    { background: linear-gradient(135deg, #6b7280, #4b5563); }
        .ac-icon  { font-size: 28px; margin-bottom: 8px; }
        .ac-value {
            font-family: var(--font-head);
            font-size: 36px; font-weight: 800;
            line-height: 1; margin-bottom: 4px;
        }
        .ac-label {
            font-size: 11px; font-weight: 700; opacity: 0.9;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        .ac-sub { font-size: 11px; opacity: 0.75; margin-top: 2px; }

        /* ── Live alerts strip ── */
        .live-alerts {
            margin-bottom: 20px;
            display: flex; flex-direction: column; gap: 10px;
        }
        .live-alert-row {
            border-radius: var(--radius-sm);
            padding: 14px 18px;
            display: flex; align-items: center; gap: 14px;
            box-shadow: var(--shadow);
        }
        .lar-appt   { background: #dbeafe; border-left: 4px solid #1565c0; }
        .lar-expiry { background: #fef3c7; border-left: 4px solid #f59e0b; }
        .lar-icon { font-size: 22px; flex-shrink: 0; }
        .lar-text { flex: 1; }
        .lar-text .lar-title  { font-weight: 800; font-size: 13px; color: var(--text-dark); margin-bottom: 2px; }
        .lar-text .lar-detail { font-size: 12px; color: var(--text-mid); font-weight: 600; }

        /* ── Header strip ── */
        .notif-header-strip {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius); padding: 16px 20px;
            margin-bottom: 16px; box-shadow: var(--shadow);
            display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap; gap: 12px;
        }
        .notif-header-strip h2 {
            font-family: var(--font-head); font-size: 18px; font-weight: 800;
            display: flex; align-items: center; gap: 10px;
        }
        .unread-badge {
            background: #ef4444; color: #fff;
            font-size: 11px; font-weight: 800;
            padding: 2px 10px; border-radius: 20px;
        }

        /* ── Filter tabs ── */
        .filter-tabs-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .filter-tab {
            padding: 7px 14px; border-radius: var(--radius-lg);
            font-size: 12px; font-weight: 700;
            border: 2px solid var(--border);
            background: rgba(255,255,255,0.8); color: var(--text-mid);
            cursor: pointer; transition: var(--transition);
            text-decoration: none; display: flex; align-items: center; gap: 5px;
        }
        .filter-tab.active,
        .filter-tab:hover { background: var(--teal); color: #fff; border-color: var(--teal); }
        .tab-count {
            background: rgba(0,0,0,0.15); border-radius: 10px;
            padding: 1px 6px; font-size: 10px;
        }
        .filter-tab.active .tab-count { background: rgba(255,255,255,0.3); }

        /* ── Notification list ── */
        .notif-list {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden;
        }
        .notif-item {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 16px 20px; border-bottom: 1px solid var(--border);
            transition: var(--transition); position: relative; cursor: pointer;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: rgba(0,188,212,0.06); }
        .notif-item.unread { background: rgba(0,188,212,0.05); }
        .notif-item.unread::before {
            content: ''; position: absolute;
            left: 0; top: 0; bottom: 0; width: 4px;
            border-radius: 0 2px 2px 0;
        }
        .notif-item.unread.t-appt_reminder_admin::before,
        .notif-item.unread.t-appointment::before    { background: #1565c0; }
        .notif-item.unread.t-product_expiry::before { background: #f59e0b; }
        .notif-item.unread.t-low_stock::before      { background: #ef4444; }
        .notif-item.unread.t-out_of_stock::before   { background: #6b7280; }
        .notif-item.unread.t-message::before        { background: #8b5cf6; }

        .notif-icon-wrap {
            width: 44px; height: 44px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .ni-appt    { background: #dbeafe; }
        .ni-expiry  { background: #fef3c7; }
        .ni-low     { background: #fee2e2; }
        .ni-out     { background: #f3f4f6; }
        .ni-message { background: #ede9fe; }
        .ni-general { background: #f0fffe; }

        .notif-content { flex: 1; min-width: 0; }
        .notif-title {
            font-weight: 800; font-size: 14px; color: var(--text-dark);
            margin-bottom: 4px; display: flex; align-items: center;
            gap: 8px; flex-wrap: wrap;
        }
        .new-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #ef4444; flex-shrink: 0;
        }
        .notif-msg  { font-size: 13px; color: var(--text-mid); line-height: 1.6; margin-bottom: 6px; }
        .notif-time { font-size: 11px; color: var(--text-light); font-weight: 600; }

        .type-badge {
            font-size: 10px; font-weight: 700; padding: 2px 8px;
            border-radius: 20px; text-transform: uppercase; letter-spacing: 0.3px;
        }
        .tb-appointment,
        .tb-appt_reminder_admin { background: #dbeafe; color: #1e40af; }
        .tb-product_expiry      { background: #fef3c7; color: #92400e; }
        .tb-low_stock           { background: #fee2e2; color: #991b1b; }
        .tb-out_of_stock        { background: #f3f4f6; color: #374151; }
        .tb-message             { background: #ede9fe; color: #5b21b6; }
        .tb-general             { background: #f0fffe; color: var(--teal-dark); }

        .notif-item-actions { display: flex; flex-direction: column; gap: 6px; flex-shrink: 0; }
        .notif-action-btn {
            background: none; border: none; cursor: pointer;
            font-size: 14px; color: var(--text-light); padding: 4px 6px;
            border-radius: var(--radius-sm); transition: var(--transition);
            text-decoration: none; display: flex; align-items: center; justify-content: center;
        }
        .notif-action-btn:hover     { background: #f0f0f0; color: var(--text-dark); }
        .notif-action-btn.del:hover { background: #fee2e2; color: #ef4444; }

        /* Pagination */
        .page-nav { display: flex; justify-content: center; gap: 6px; margin-top: 16px; }
        .page-nav a, .page-nav span {
            padding: 7px 14px; border-radius: var(--radius-sm);
            font-size: 13px; font-weight: 700;
            background: rgba(255,255,255,0.85);
            color: var(--text-dark); transition: var(--transition); text-decoration: none;
        }
        .page-nav a:hover   { background: var(--teal-light); }
        .page-nav .current  { background: var(--teal); color: #fff; }
        .page-nav .disabled { opacity: 0.4; pointer-events: none; }

        .notif-empty { text-align: center; padding: 48px 20px; }
        .notif-empty .ne-icon { font-size: 56px; margin-bottom: 14px; }
        .notif-empty h3 { font-size: 18px; font-weight: 800; color: var(--text-mid); }

        @media(max-width:900px) { .alert-summary { grid-template-columns: 1fr 1fr; } }
        @media(max-width:500px) {
            .alert-summary { grid-template-columns: 1fr 1fr; }
            .notif-item { padding: 12px 14px; gap: 10px; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_admin.php'; ?>

    <div class="main-content">

        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-title" style="display:flex; align-items:center; gap:10px;">
                <img src="../assets/css/images/pets/logo.png" alt="Logo"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.6);"
                     onerror="this.style.display='none';">
                Notifications
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

            <!-- ── Summary Cards — VIEW ONLY, not clickable ── -->
            <div class="alert-summary">
                <div class="alert-card ac-appointment">
                    <div class="ac-icon">📅</div>
                    <div class="ac-value"><?= $live_appts_24 ?></div>
                    <div class="ac-label">Appointments</div>
                    <div class="ac-sub">Within 24 hours</div>
                </div>
                <div class="alert-card ac-expiry">
                    <div class="ac-icon">⚠️</div>
                    <div class="ac-value"><?= $live_expiring ?></div>
                    <div class="ac-label">Expiring Soon</div>
                    <div class="ac-sub">Within 2 weeks</div>
                </div>
                <div class="alert-card ac-lowstock">
                    <div class="ac-icon">📦</div>
                    <div class="ac-value"><?= $live_low ?></div>
                    <div class="ac-label">Low Stock</div>
                    <div class="ac-sub">≤ 5 items left</div>
                </div>
                <div class="alert-card ac-outstock">
                    <div class="ac-icon">🚨</div>
                    <div class="ac-value"><?= $live_outstock ?></div>
                    <div class="ac-label">Out of Stock</div>
                    <div class="ac-sub">Needs reorder</div>
                </div>
            </div>

            <!-- ── Live Alert Rows — Appointments & Expiry only ── -->
            <div class="live-alerts">
                <?php if (!empty($appts_24hr)): ?>
                <div class="live-alert-row lar-appt">
                    <div class="lar-icon">📅</div>
                    <div class="lar-text">
                        <div class="lar-title">
                            <?= count($appts_24hr) ?> Appointment(s) Coming Up in 24 Hours
                        </div>
                        <div class="lar-detail">
                            <?php foreach ($appts_24hr as $i => $appt):
                                if ($i > 2) { echo '+ more...'; break; }
                                $label = $appt['appointment_type'] === 'home_service'
                                    ? 'Home Service'
                                    : htmlspecialchars($appt['pet_name'] ?? '—') . ' / ' . htmlspecialchars($appt['service_name'] ?? '—');
                            ?>
                                <span style="margin-right:12px;">
                                    · <?= htmlspecialchars($appt['owner_name'] ?? '?') ?> —
                                    <?= $label ?> @
                                    <?= date('g:i A', strtotime($appt['appointment_time'])) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($expiring)): ?>
                <div class="live-alert-row lar-expiry">
                    <div class="lar-icon">⚠️</div>
                    <div class="lar-text">
                        <div class="lar-title">
                            <?= count($expiring) ?> Product(s) Expiring Within 2 Weeks
                        </div>
                        <div class="lar-detail">
                            <?php foreach ($expiring as $i => $p):
                                if ($i > 2) { echo '+ more...'; break; }
                                $days = (int)((strtotime($p['expiry_date']) - strtotime(date('Y-m-d'))) / 86400);
                            ?>
                                <span style="margin-right:12px;">
                                    · <?= htmlspecialchars($p['name']) ?>
                                    (<?= $days === 0 ? 'TODAY' : "in $days day(s)" ?>, Qty: <?= $p['quantity'] ?>)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div><!-- /live-alerts -->

            <!-- ── Notification Log Header ── -->
            <div class="notif-header-strip">
                <h2>
                    🔔 Notification Log
                    <?php if ($cnt_unread > 0): ?>
                        <span class="unread-badge"><?= $cnt_unread ?> unread</span>
                    <?php endif; ?>
                </h2>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <?php if ($cnt_unread > 0): ?>
                        <a href="?mark_all_read=1" class="btn btn-teal btn-sm">✓ Mark All Read</a>
                    <?php endif; ?>
                    <?php if ($cnt_all > 0): ?>
                        <a href="?clear_all=1"
                           onclick="return confirm('Clear ALL notifications? This cannot be undone.')"
                           class="btn btn-gray btn-sm">🗑️ Clear All</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Filter Tabs — Low Stock & Out of Stock are separate clickable tabs ── -->
            <div class="filter-tabs-row">
                <?php
                $tabs = [
                    ['all',          '🔔', 'All',          $cnt_all],
                    ['unread',       '🔴', 'Unread',        $cnt_unread],
                    ['appointment',  '📅', 'Appointments',  $cnt_appt],
                    ['expiry',       '⚠️', 'Expiring',      $cnt_expiry],
                    ['low_stock',    '📦', 'Low Stock',     $cnt_low],
                    ['out_of_stock', '🚨', 'Out of Stock',  $cnt_out],
                    ['message',      '💬', 'Messages',      $cnt_msg],
                ];
                foreach ($tabs as $tab):
                    $active = $filter === $tab[0] ? 'active' : '';
                ?>
                    <a href="?filter=<?= $tab[0] ?>" class="filter-tab <?= $active ?>">
                        <?= $tab[1] ?> <?= $tab[2] ?>
                        <span class="tab-count"><?= $tab[3] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- ── Notification List ── -->
            <?php if (empty($notifications)): ?>
                <div class="notif-list">
                    <div class="notif-empty">
                        <div class="ne-icon">✅</div>
                        <h3>
                            <?= $filter !== 'all'
                                ? "No $filter notifications"
                                : 'All clear — no notifications' ?>
                        </h3>
                        <p style="font-size:13px;color:var(--text-light);margin-top:6px;">
                            <?= $filter !== 'all'
                                ? '<a href="notifications.php" style="color:var(--teal-dark);font-weight:700;">View all notifications</a>'
                                : 'System alerts will appear here as they are generated.' ?>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="notif-list">
                    <?php foreach ($notifications as $notif):
                        $is_unread = !$notif['is_read'];
                        $type      = $notif['type'] ?? 'general';

                        $map = [
                            'appointment'         => ['📅', 'ni-appt'],
                            'appt_reminder_admin' => ['📅', 'ni-appt'],
                            'product_expiry'      => ['⚠️', 'ni-expiry'],
                            'low_stock'           => ['📦', 'ni-low'],
                            'out_of_stock'        => ['🚨', 'ni-out'],
                            'message'             => ['💬', 'ni-message'],
                            'general'             => ['🔔', 'ni-general'],
                        ];
                        [$icon, $icon_cls] = $map[$type] ?? ['🔔', 'ni-general'];

                        $display_msg = trim(preg_replace('/\[(appt|prod):\d+\]/', '', $notif['message']));

                        $created  = strtotime($notif['created_at']);
                        $diff     = time() - $created;
                        if ($diff < 60)         $time_str = 'Just now';
                        elseif ($diff < 3600)   $time_str = floor($diff/60)   . 'm ago';
                        elseif ($diff < 86400)  $time_str = floor($diff/3600) . 'h ago';
                        elseif ($diff < 604800) $time_str = floor($diff/86400). 'd ago';
                        else                    $time_str = date('M j, Y', $created);

                        $type_labels = [
                            'appointment'         => 'Appointment',
                            'appt_reminder_admin' => 'Appt. Reminder',
                            'product_expiry'      => 'Expiring Product',
                            'low_stock'           => 'Low Stock',
                            'out_of_stock'        => 'Out of Stock',
                            'message'             => 'Message',
                            'general'             => 'General',
                        ];
                        $type_lbl = $type_labels[$type] ?? ucfirst($type);

                        // Direct page each type navigates to
                        $page_map = [
                            'appointment'         => 'appointments.php',
                            'appt_reminder_admin' => 'appointments.php',
                            'product_expiry'      => 'inventory.php',
                            'low_stock'           => 'inventory.php',
                            'out_of_stock'        => 'inventory.php',
                            'message'             => 'messages.php',
                        ];
                        $goto = $page_map[$type] ?? '';
                    ?>
                    <div class="notif-item <?= $is_unread ? 'unread' : '' ?> t-<?= $type ?>"
                         id="notif-<?= $notif['id'] ?>"
                         onclick="handleNotifClick(
                             '<?= $type ?>',
                             <?= $notif['id'] ?>,
                             '<?= htmlspecialchars($notif['title'] ?? '', ENT_QUOTES) ?>',
                             '<?= htmlspecialchars($display_msg, ENT_QUOTES) ?>',
                             '<?= date('M j, Y g:i A', $created) ?>',
                             '<?= $goto ?>'
                         )">
                        <div class="notif-icon-wrap <?= $icon_cls ?>">
                            <?= $icon ?>
                        </div>
                        <div class="notif-content">
                            <div class="notif-title">
                                <?php if ($is_unread): ?>
                                    <span class="new-dot"></span>
                                <?php endif; ?>
                                <?= htmlspecialchars($notif['title'] ?? 'Notification') ?>
                                <span class="type-badge tb-<?= $type ?>">
                                    <?= $type_lbl ?>
                                </span>
                            </div>
                            <div class="notif-msg"><?= htmlspecialchars($display_msg) ?></div>
                            <div class="notif-time">
                                🕐 <?= $time_str ?> &nbsp;·&nbsp;
                                <?= date('M j, Y g:i A', $created) ?>
                            </div>
                        </div>
                        <div class="notif-item-actions">
                            <?php if ($is_unread): ?>
                                <a href="?mark_read=<?= $notif['id'] ?>&filter=<?= $filter ?>"
                                   onclick="event.stopPropagation();"
                                   class="notif-action-btn" title="Mark as read">✓</a>
                            <?php endif; ?>
                            <a href="?delete=<?= $notif['id'] ?>&filter=<?= $filter ?>"
                               onclick="event.stopPropagation(); return confirm('Delete this notification?');"
                               class="notif-action-btn del" title="Delete">🗑️</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="page-nav">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&filter=<?= $filter ?>">‹ Prev</a>
                        <?php else: ?>
                            <span class="disabled">‹ Prev</span>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <?php if ($p === $page): ?>
                                <span class="current"><?= $p ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $p ?>&filter=<?= $filter ?>"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page+1 ?>&filter=<?= $filter ?>">Next ›</a>
                        <?php else: ?>
                            <span class="disabled">Next ›</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div><!-- /page-body -->
    </div>
</div>

<!-- Modal only shown for announcement/general types -->
<div class="modal-overlay" id="notifAnnModal">
    <div class="modal" style="max-width:520px;">
        <button class="modal-close" onclick="closeModal('notifAnnModal')">×</button>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
            <div style="width:48px;height:48px;border-radius:50%;
                        background:linear-gradient(135deg,#d1fae5,#a7f3d0);
                        display:flex;align-items:center;justify-content:center;font-size:22px;">
                📢
            </div>
            <div>
                <div style="font-size:11px;font-weight:800;color:var(--teal-dark);
                            text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">
                    Notification
                </div>
                <h3 id="notif-modal-title"
                    style="font-family:var(--font-head);font-size:17px;
                           font-weight:800;color:var(--text-dark);"></h3>
            </div>
        </div>
        <div id="notif-modal-body"
             style="font-size:14px;color:var(--text-mid);line-height:1.8;
                    background:#f0fffe;border-radius:var(--radius-sm);
                    padding:16px;border-left:3px solid var(--teal);
                    white-space:pre-wrap;margin-bottom:16px;"></div>
        <div style="display:flex;justify-content:space-between;align-items:center;
                    font-size:12px;color:var(--text-light);font-weight:600;">
            <span id="notif-modal-time"></span>
            <button class="btn btn-gray btn-sm" onclick="closeModal('notifAnnModal')">Close</button>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function handleNotifClick(type, id, title, message, time, gotoPage) {
    // Mark as read silently
    fetch('notifications.php?mark_read=' + id);

    // Remove unread dot immediately
    const el = document.getElementById('notif-' + id);
    if (el) {
        el.classList.remove('unread');
        const dot = el.querySelector('.new-dot');
        if (dot) dot.remove();
    }

    // All types with a page redirect directly — announcement/general show modal
    if (gotoPage) {
        window.location.href = gotoPage;
    } else {
        // Show detail modal (announcement, general)
        document.getElementById('notif-modal-title').textContent = title;
        document.getElementById('notif-modal-body').textContent  = message;
        document.getElementById('notif-modal-time').textContent  = '🕐 ' + time;
        openModal('notifAnnModal');
    }
}
</script>
</body>
</html>