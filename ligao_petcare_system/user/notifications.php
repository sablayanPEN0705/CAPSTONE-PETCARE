<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/notifications.php
// Purpose: User notifications — appointment 24hr alerts + general
// ============================================================
require_once '../includes/auth.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id = (int)$_SESSION['user_id'];

// ── Auto-generate 24hr appointment reminder notifications ────
$upcoming_24hr = getRows($conn,
    "SELECT a.*, p.name AS pet_name, s.name AS service_name
     FROM appointments a
     LEFT JOIN pets p     ON a.pet_id     = p.id
     LEFT JOIN services s ON a.service_id = s.id
     WHERE a.user_id = $user_id
       AND a.status IN ('pending','confirmed')
       AND CONCAT(a.appointment_date, ' ', a.appointment_time)
           BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)"
);

foreach ($upcoming_24hr as $appt) {
    $aid      = (int)$appt['id'];
    $pet_lbl  = $appt['appointment_type'] === 'home_service'
        ? 'Home Service'
        : htmlspecialchars($appt['pet_name'] ?? 'your pet');
    $svc_lbl  = $appt['appointment_type'] === 'home_service'
        ? 'Home Service'
        : htmlspecialchars($appt['service_name'] ?? 'appointment');
    $date_lbl = date('F j, Y', strtotime($appt['appointment_date']));
    $time_lbl = date('g:i A', strtotime($appt['appointment_time']));

    $already = getRow($conn,
        "SELECT id FROM notifications
         WHERE user_id=$user_id
           AND type='appointment_reminder'
           AND message LIKE '%appointment_id:$aid%'"
    );

    if (!$already) {
        $msg = "Reminder: Your $svc_lbl appointment for $pet_lbl is tomorrow on $date_lbl at $time_lbl. [appointment_id:$aid]";
        $msg = sanitize($conn, $msg);
        mysqli_query($conn,
            "INSERT INTO notifications (user_id, title, message, type)
             VALUES ($user_id, '⏰ Appointment Reminder', '$msg', 'appointment_reminder')"
        );
    }
}

// ── Handle mark as read ──────────────────────────────────────
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $nid = (int)$_GET['mark_read'];
    mysqli_query($conn,
        "UPDATE notifications SET is_read=1 WHERE id=$nid AND user_id=$user_id");
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['mark_all_read'])) {
    mysqli_query($conn,
        "UPDATE notifications SET is_read=1 WHERE user_id=$user_id AND is_read=0");
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $nid = (int)$_GET['delete'];
    mysqli_query($conn,
        "DELETE FROM notifications WHERE id=$nid AND user_id=$user_id");
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['clear_all'])) {
    mysqli_query($conn,
        "DELETE FROM notifications WHERE user_id=$user_id");
    header("Location: notifications.php");
    exit();
}

// ── Filter ───────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$where  = "user_id=$user_id";
if ($filter === 'unread')       $where .= " AND is_read=0";
if ($filter === 'appointment')  $where .= " AND type IN ('appointment','appointment_reminder')";
if ($filter === 'announcement') $where .= " AND type='announcement'";
if ($filter === 'message')      $where .= " AND type='message'";

// ── Pagination ───────────────────────────────────────────────
$per_page    = 10;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$total       = countRows($conn, 'notifications', $where);
$total_pages = max(1, ceil($total / $per_page));

$notifications = getRows($conn,
    "SELECT * FROM notifications
     WHERE $where
     ORDER BY created_at DESC
     LIMIT $per_page OFFSET $offset"
);

// ── Counts for filter tabs ───────────────────────────────────
$count_all    = countRows($conn, 'notifications', "user_id=$user_id");
$count_unread = countRows($conn, 'notifications', "user_id=$user_id AND is_read=0");
$count_appt   = countRows($conn, 'notifications', "user_id=$user_id AND type IN ('appointment','appointment_reminder')");
$count_ann    = countRows($conn, 'notifications', "user_id=$user_id AND type='announcement'");
$count_msg    = countRows($conn, 'notifications', "user_id=$user_id AND type='message'");

// ── Destination map ──────────────────────────────────────────
$type_destinations = [
    'appointment'          => 'appointments.php',
    'appointment_reminder' => 'appointments.php',
    'message'              => 'messages.php',
    'announcement'         => null,
    'billing'              => 'transactions.php',
    'general'              => null,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Header strip ── */
        .notif-header-strip {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 18px 22px;
            margin-bottom: 18px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .notif-header-strip h2 {
            font-family: var(--font-head);
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .unread-badge {
            background: #ef4444;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            padding: 2px 10px;
            border-radius: 20px;
        }
        .notif-actions { display:flex; gap:8px; flex-wrap:wrap; }

        /* ── Alert banner for 24hr reminders ── */
        .reminder-banner {
            background: linear-gradient(135deg, #1565c0, #00bcd4);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 18px;
            color: #fff;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: var(--shadow);
        }
        .reminder-banner-icon { font-size: 32px; flex-shrink: 0; }
        .reminder-banner-text h3 {
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .reminder-banner-text p { font-size: 13px; opacity: 0.9; margin-bottom: 8px; }
        .reminder-item {
            background: rgba(255,255,255,0.15);
            border-radius: var(--radius-sm);
            padding: 8px 14px;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .reminder-item:last-child { margin-bottom: 0; }

        /* ── Filter tabs ── */
        .filter-tabs-row { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
        .filter-tab {
            padding: 7px 16px;
            border-radius: var(--radius-lg);
            font-size: 12px;
            font-weight: 700;
            border: 2px solid var(--border);
            background: rgba(255,255,255,0.8);
            color: var(--text-mid);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filter-tab.active, .filter-tab:hover {
            background: var(--teal);
            color: #fff;
            border-color: var(--teal);
        }
        .tab-count { background:rgba(0,0,0,0.15); border-radius:10px; padding:1px 6px; font-size:11px; }
        .filter-tab.active .tab-count { background: rgba(255,255,255,0.3); }

        /* ── Notification list ── */
        .notif-list {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s, transform 0.1s;
            position: relative;
            cursor: pointer;
            color: inherit;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover {
            background: rgba(0,188,212,0.07);
            transform: translateX(2px);
        }
        .notif-item.unread { background: rgba(0,188,212,0.06); }
        .notif-item.unread::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: var(--teal);
            border-radius: 0 2px 2px 0;
        }
        .notif-item .notif-arrow {
            font-size: 14px;
            color: var(--text-light);
            align-self: center;
            flex-shrink: 0;
            transition: transform 0.15s, color 0.15s;
        }
        .notif-item:hover .notif-arrow { transform: translateX(3px); color: var(--teal); }

        /* Type icon circle */
        .notif-icon-wrap {
            width: 44px; height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .ni-appointment          { background: #dbeafe; }
        .ni-appointment_reminder { background: #fef3c7; }
        .ni-announcement         { background: #d1fae5; }
        .ni-message              { background: #ede9fe; }
        .ni-billing              { background: #fdf4ff; }
        .ni-general              { background: #f3f4f6; }

        .notif-content { flex: 1; min-width: 0; }
        .notif-title {
            font-weight: 800;
            font-size: 14px;
            color: var(--text-dark);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .notif-title .new-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #ef4444;
            flex-shrink: 0;
        }
        .notif-msg {
            font-size: 13px;
            color: var(--text-mid);
            line-height: 1.6;
            margin-bottom: 6px;
        }
        .notif-time {
            font-size: 11px;
            color: var(--text-light);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .notif-dest-hint {
            font-size: 11px;
            color: var(--teal-dark);
            font-weight: 700;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .notif-item-actions {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex-shrink: 0;
        }
        .notif-action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-light);
            padding: 4px 6px;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notif-action-btn:hover { background: #f0f0f0; color: var(--text-dark); }
        .notif-action-btn.del:hover { background: #fee2e2; color: #ef4444; }

        /* Type label badge */
        .type-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .tb-appointment          { background: #dbeafe; color: #1e40af; }
        .tb-appointment_reminder { background: #fef3c7; color: #92400e; }
        .tb-announcement         { background: #d1fae5; color: #065f46; }
        .tb-message              { background: #ede9fe; color: #5b21b6; }
        .tb-billing              { background: #fdf4ff; color: #7e22ce; }
        .tb-general              { background: #f3f4f6; color: #374151; }

        /* Pagination */
        .page-nav { display:flex; justify-content:center; gap:6px; margin-top:16px; }
        .page-nav a, .page-nav span {
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 700;
            background: rgba(255,255,255,0.85);
            color: var(--text-dark);
            transition: var(--transition);
            text-decoration: none;
        }
        .page-nav a:hover   { background: var(--teal-light); }
        .page-nav .current  { background: var(--teal); color: #fff; }
        .page-nav .disabled { opacity: 0.4; pointer-events: none; }

        /* Empty state */
        .notif-empty { text-align:center; padding:64px 20px; }
        .notif-empty .ne-icon { font-size:56px; margin-bottom:14px; }
        .notif-empty h3 { font-size:18px; font-weight:800; color:var(--text-mid); margin-bottom:6px; }
        .notif-empty p  { font-size:13px; color:var(--text-light); }

        /* ── Delete confirmation modal ── */
        .delete-notif-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
            align-items: center;
            justify-content: center;
        }
        .delete-notif-overlay.open { display: flex; }

        .delete-notif-box {
            background: #fff;
            border-radius: 16px;
            padding: 32px 28px;
            max-width: 380px;
            width: 90%;
            box-shadow: 0 24px 60px rgba(0,0,0,0.2);
            text-align: center;
            animation: popIn 0.2s ease;
        }
        @keyframes popIn {
            from { opacity:0; transform: scale(0.92) translateY(10px); }
            to   { opacity:1; transform: scale(1)    translateY(0); }
        }
        .delete-notif-icon-wrap {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: #fff5f5;
            border: 2px solid #fee2e2;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 16px;
        }
        .delete-notif-box h3 {
            font-family: var(--font-head);
            font-size: 18px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .delete-notif-box p {
            font-size: 13px;
            color: var(--text-mid);
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .delete-notif-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .delete-notif-actions .btn-keep {
            padding: 10px 22px;
            border-radius: 8px;
            border: 1.5px solid var(--border);
            background: #fff;
            color: var(--text-dark);
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.15s;
            font-family: var(--font-main);
        }
        .delete-notif-actions .btn-keep:hover { background: #f9fafb; }
        .delete-notif-actions .btn-del-confirm {
            padding: 10px 22px;
            border-radius: 8px;
            border: none;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(239,68,68,0.3);
            transition: transform 0.15s, box-shadow 0.15s;
            font-family: var(--font-main);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .delete-notif-actions .btn-del-confirm:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(239,68,68,0.35);
        }

        @media(max-width:600px) {
            .notif-item { padding: 12px 14px; gap: 10px; }
            .notif-icon-wrap { width:36px; height:36px; font-size:16px; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_user.php'; ?>

    <div class="main-content">

        <div class="topbar">
            <div class="topbar-title" style="display:flex; align-items:center; gap:10px;">
                <img src="../assets/css/images/pets/logo.png" alt="Logo"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.6);"
                     onerror="this.style.display='none';">
                Notifications
            </div>
            <div class="topbar-actions">
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <!-- 24hr Reminder Banner -->
            <?php if (!empty($upcoming_24hr)): ?>
            <div class="reminder-banner">
                <div class="reminder-banner-icon">⏰</div>
                <div class="reminder-banner-text">
                    <h3>Upcoming Appointment Reminder!</h3>
                    <p>You have <?= count($upcoming_24hr) ?> appointment(s) within the next 24 hours:</p>
                    <?php foreach ($upcoming_24hr as $appt): ?>
                        <div class="reminder-item">
                            <?= $appt['appointment_type'] === 'home_service' ? '🏠' : '🏥' ?>
                            <strong>
                                <?= $appt['appointment_type'] === 'home_service'
                                    ? 'Home Service'
                                    : htmlspecialchars($appt['pet_name'] ?? '—') . ' — ' . htmlspecialchars($appt['service_name'] ?? '—') ?>
                            </strong>
                            &nbsp;·&nbsp;
                            <?= date('M j, Y', strtotime($appt['appointment_date'])) ?>
                            at <?= date('g:i A', strtotime($appt['appointment_time'])) ?>
                            &nbsp;·&nbsp;
                            <span style="background:rgba(255,255,255,0.25);padding:1px 8px;border-radius:10px;font-size:11px;">
                                <?= ucfirst($appt['status']) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <div style="margin-top:10px;">
                        <a href="appointments.php" class="btn btn-sm"
                           style="background:#fff;color:var(--blue-header);font-size:12px;">
                            📅 View Appointments
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Header Strip -->
            <div class="notif-header-strip">
                <h2>
                    🔔 Notifications
                    <?php if ($count_unread > 0): ?>
                        <span class="unread-badge"><?= $count_unread ?> new</span>
                    <?php endif; ?>
                </h2>
                <div class="notif-actions">
                    <?php if ($count_unread > 0): ?>
                        <a href="?mark_all_read=1" class="btn btn-teal btn-sm">✓ Mark All Read</a>
                    <?php endif; ?>
                    <?php if ($count_all > 0): ?>
                        <a href="?clear_all=1"
                           onclick="return confirm('Clear all notifications? This cannot be undone.')"
                           class="btn btn-gray btn-sm">🗑️ Clear All</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs-row">
                <?php
                $tabs = [
                    ['all',          'All',           $count_all,   '🔔'],
                    ['unread',       'Unread',        $count_unread,'🔴'],
                    ['appointment',  'Appointments',  $count_appt,  '📅'],
                    ['announcement', 'Announcements', $count_ann,   '📢'],
                    ['message',      'Messages',      $count_msg,   '💬'],
                ];
                foreach ($tabs as $tab):
                    $is_active = $filter === $tab[0] ? 'active' : '';
                ?>
                    <a href="?filter=<?= $tab[0] ?>"
                       class="filter-tab <?= $is_active ?>">
                        <?= $tab[3] ?> <?= $tab[1] ?>
                        <span class="tab-count"><?= $tab[2] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Notifications List -->
            <?php if (empty($notifications)): ?>
                <div class="notif-list">
                    <div class="notif-empty">
                        <div class="ne-icon">🔔</div>
                        <h3>No notifications</h3>
                        <p>
                            <?php if ($filter !== 'all'): ?>
                                No <?= $filter ?> notifications found.
                                <a href="notifications.php" style="color:var(--teal-dark);font-weight:700;">View all</a>
                            <?php else: ?>
                                You're all caught up! We'll notify you about appointments and updates.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="notif-list">
                    <?php foreach ($notifications as $notif):
                        $is_unread = !$notif['is_read'];
                        $type      = $notif['type'] ?? 'general';

                        $icons = [
                            'appointment'          => ['📅', 'ni-appointment'],
                            'appointment_reminder' => ['⏰', 'ni-appointment_reminder'],
                            'announcement'         => ['📢', 'ni-announcement'],
                            'message'              => ['💬', 'ni-message'],
                            'billing'              => ['🧾', 'ni-billing'],
                            'general'              => ['🔔', 'ni-general'],
                        ];
                        [$icon, $icon_class] = $icons[$type] ?? ['🔔', 'ni-general'];

                        $display_msg = trim(preg_replace('/\[appointment_id:\d+\]/', '', $notif['message']));

                        $created  = strtotime($notif['created_at']);
                        $diff     = time() - $created;
                        if      ($diff < 60)     $time_str = 'Just now';
                        elseif  ($diff < 3600)   $time_str = floor($diff/60)   . 'm ago';
                        elseif  ($diff < 86400)  $time_str = floor($diff/3600) . 'h ago';
                        elseif  ($diff < 604800) $time_str = floor($diff/86400). 'd ago';
                        else                     $time_str = date('M j, Y', $created);

                        $dest_hints = [
                            'appointment'          => ['📅', 'Go to Appointments'],
                            'appointment_reminder' => ['📅', 'Go to Appointments'],
                            'message'              => ['💬', 'Go to Messages'],
                            'billing'              => ['🧾', 'Go to Transactions'],
                            'announcement'         => ['📖', 'Tap to read full announcement'],
                            'general'              => null,
                        ];
                        $hint = $dest_hints[$type] ?? null;
                    ?>
                    <div class="notif-item <?= $is_unread ? 'unread' : '' ?>"
                         id="notif-<?= $notif['id'] ?>"
                         onclick="handleNotifClick(
                             '<?= $type ?>',
                             <?= $notif['id'] ?>,
                             '<?= htmlspecialchars($notif['title'] ?? '', ENT_QUOTES) ?>',
                             '<?= htmlspecialchars($display_msg, ENT_QUOTES) ?>',
                             '<?= date('M j, Y g:i A', $created) ?>'
                         )">

                        <div class="notif-icon-wrap <?= $icon_class ?>">
                            <?= $icon ?>
                        </div>

                        <div class="notif-content">
                            <div class="notif-title">
                                <?php if ($is_unread): ?>
                                    <span class="new-dot"></span>
                                <?php endif; ?>
                                <?= htmlspecialchars($notif['title'] ?? 'Notification') ?>
                                <span class="type-badge tb-<?= $type ?>">
                                    <?= ucfirst(str_replace('_', ' ', $type)) ?>
                                </span>
                            </div>
                            <div class="notif-msg"><?= htmlspecialchars($display_msg) ?></div>
                            <div class="notif-time">
                                🕐 <?= $time_str ?> &nbsp;·&nbsp; <?= date('M j, Y g:i A', $created) ?>
                            </div>
                            <?php if ($hint): ?>
                                <div class="notif-dest-hint">
                                    <?= $hint[0] ?> <span><?= $hint[1] ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="notif-arrow">›</div>

                        <!-- Action buttons — stopPropagation prevents row click -->
                        <div class="notif-item-actions" onclick="event.stopPropagation()">
                            <?php if ($is_unread): ?>
                                <a href="?mark_read=<?= $notif['id'] ?>&filter=<?= $filter ?>"
                                   class="notif-action-btn" title="Mark as read">✓</a>
                            <?php endif; ?>
                            <button
                                class="notif-action-btn del"
                                title="Delete notification"
                                onclick="openDeleteModal(<?= $notif['id'] ?>, '<?= $filter ?>')">
                                🗑️
                            </button>
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

<!-- ══ Announcement Detail Modal ════════════════════════════ -->
<div class="modal-overlay" id="notifAnnModal">
    <div class="modal" style="max-width:520px;">
        <button class="modal-close"
                onclick="document.getElementById('notifAnnModal').classList.remove('open')">×</button>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
            <div style="width:48px;height:48px;border-radius:50%;
                        background:linear-gradient(135deg,#d1fae5,#a7f3d0);
                        display:flex;align-items:center;justify-content:center;font-size:22px;">
                📢
            </div>
            <div>
                <div style="font-size:11px;font-weight:800;color:var(--teal-dark);
                            text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;">
                    Announcement
                </div>
                <h3 id="notif-modal-title"
                    style="font-family:var(--font-head);font-size:17px;font-weight:800;color:var(--text-dark);"></h3>
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
            <button class="btn btn-gray btn-sm"
                    onclick="document.getElementById('notifAnnModal').classList.remove('open')">
                Close
            </button>
        </div>
    </div>
</div>

<!-- ══ Delete Confirmation Modal ════════════════════════════ -->
<div class="delete-notif-overlay" id="deleteNotifOverlay"
     onclick="if(event.target===this)closeDeleteModal()">
    <div class="delete-notif-box">
        <div class="delete-notif-icon-wrap">🗑️</div>
        <h3>Delete Notification?</h3>
        <p>This notification will be permanently removed.<br>This action cannot be undone.</p>
        <div class="delete-notif-actions">
            <button class="btn-keep" onclick="closeDeleteModal()">Keep It</button>
            <a id="deleteNotifConfirmBtn" href="#" class="btn-del-confirm">
                ✕ Yes, Delete
            </a>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// ── Destination map ───────────────────────────────────────────
const TYPE_DEST = {
    appointment:          'appointments.php',
    appointment_reminder: 'appointments.php',
    message:              'messages.php',
    billing:              'transactions.php',
    announcement:         null,
    general:              null,
};

function handleNotifClick(type, id, title, message, time) {
    fetch('notifications.php?mark_read=' + id);

    const el = document.getElementById('notif-' + id);
    if (el) {
        el.classList.remove('unread');
        const dot = el.querySelector('.new-dot');
        if (dot) dot.remove();
    }

    const dest = TYPE_DEST[type];
    if (dest) {
        window.location.href = dest;
    } else if (type === 'announcement') {
        document.getElementById('notif-modal-title').textContent = title;
        document.getElementById('notif-modal-body').textContent  = message;
        document.getElementById('notif-modal-time').textContent  = '🕐 ' + time;
        document.getElementById('notifAnnModal').classList.add('open');
    }
    // general — silently marked read
}

// ── Delete modal ─────────────────────────────────────────────
function openDeleteModal(id, filter) {
    const url = 'notifications.php?delete=' + id + '&filter=' + encodeURIComponent(filter);
    document.getElementById('deleteNotifConfirmBtn').href = url;
    document.getElementById('deleteNotifOverlay').classList.add('open');
}

function closeDeleteModal() {
    document.getElementById('deleteNotifOverlay').classList.remove('open');
}

// Escape key closes both modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('notifAnnModal').classList.remove('open');
        closeDeleteModal();
    }
});

document.getElementById('notifAnnModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});
</script>

<?php include_once __DIR__ . '/../includes/chatbot-widget.php'; ?>

</body>
</html>