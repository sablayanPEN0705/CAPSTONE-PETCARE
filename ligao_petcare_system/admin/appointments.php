<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/appointments.php
// Purpose: View/manage all appointments — calendar + list
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// ── Handle status update ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $aid     = (int)$_POST['appt_id'];
    $status  = sanitize($conn, $_POST['status'] ?? '');
    $allowed = ['pending','confirmed','completed','cancelled'];
    if (in_array($status, $allowed)) {
        mysqli_query($conn, "UPDATE appointments SET status='$status' WHERE id=$aid");
        $appt = getRow($conn,
            "SELECT a.user_id, p.name AS pet_name, s.name AS svc_name
             FROM appointments a
             LEFT JOIN pets p     ON a.pet_id     = p.id
             LEFT JOIN services s ON a.service_id = s.id
             WHERE a.id=$aid");
       if ($appt) {
    $msg = "Your appointment for {$appt['pet_name']} ({$appt['svc_name']}) has been $status.";
    $uid = (int)$appt['user_id'];
    mysqli_query($conn,
        "INSERT INTO notifications (user_id, title, message, type)
         VALUES ($uid, 'Appointment Update', '$msg', 'appointment')");

    // Auto-create a pending transaction when appointment is completed
    if ($status === 'completed') {
        $existing = getRow($conn, "SELECT id FROM transactions WHERE appointment_id=$aid");
        if (!$existing) {
            $pet_id_row = getRow($conn, "SELECT pet_id, service_id FROM appointments WHERE id=$aid");
            $pet_id_val = $pet_id_row['pet_id'] ? (int)$pet_id_row['pet_id'] : 'NULL';
            mysqli_query($conn,
                "INSERT INTO transactions (user_id, pet_id, appointment_id, total_amount, status, transaction_date)
                 VALUES ($uid, $pet_id_val, $aid, 0, 'pending', CURDATE())");
        }
    }
}
        redirect('appointments.php?success=Appointment status updated.');
    }
}

// ── Handle archive ───────────────────────────────────────────
if (isset($_GET['archive']) && is_numeric($_GET['archive'])) {
    $aid = (int)$_GET['archive'];
    mysqli_query($conn, "UPDATE appointments SET archived=1 WHERE id=$aid");
    redirect('appointments.php?success=Appointment archived.');
}

// ── Handle restore ───────────────────────────────────────────
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $rid = (int)$_GET['restore'];
    mysqli_query($conn, "UPDATE appointments SET archived=0 WHERE id=$rid");
    redirect('appointments.php?success=Appointment restored.');
}

// ── Handle delete ────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM appointments WHERE id=$did");
    redirect('appointments.php?success=Appointment deleted.');
}
// ── Calendar month navigation ────────────────────────────────
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

// ── Service color maps (clinic + home) ──────────────────────
$svc_colors = [
    'CheckUp'     => '#1976d2',
    'Confinement' => '#7c3aed',
    'Treatment'   => '#388e3c',
    'Deworming'   => '#f57c00',
    'Vaccination' => '#ef4444',
    'Grooming'    => '#00838f',
    'Surgery'     => '#dc2626',
    'Laboratory'  => '#6366f1',
];
$home_svc_colors = [
    'CheckUp'     => '#f7a534',
    'Confinement' => '#b42d56',
    'Treatment'   => '#16a34a',
    'Deworming'   => '#968e8c',
    'Vaccination' => '#ca8a04',
    'Grooming'    => '#0891b2',
    'Surgery'     => '#1e1e1e',
    'Laboratory'  => '#ad1010',
];

// Build color maps from DB service names
$db_services = getRows($conn, "SELECT name FROM services");
$svc_name_map = $home_svc_name_map = [];
foreach ($db_services as $sv) {
    $dn = $sv['name'];
    $matched = false;
    foreach ($svc_colors as $key => $color) {
        if (strtolower($dn) === strtolower($key)) {
            $svc_name_map[$dn] = $color; $matched = true; break;
        }
    }
    if (!$matched) $svc_name_map[$dn] = '#607d8b';

    $matched = false;
    foreach ($home_svc_colors as $key => $color) {
        if (strtolower($dn) === strtolower($key)) {
            $home_svc_name_map[$dn] = $color; $matched = true; break;
        }
    }
    if (!$matched) $home_svc_name_map[$dn] = '#8b2f68';
}
$svc_colors      = $svc_name_map;
$home_svc_colors = $home_svc_name_map;

// ── Fetch calendar appointments ──────────────────────────────
$cal_appts = getRows($conn,
    "SELECT a.*, p.name AS pet_name, s.name AS svc_name, u.name AS owner_name
     FROM appointments a
     LEFT JOIN pets p     ON a.pet_id     = p.id
     LEFT JOIN services s ON a.service_id = s.id
     LEFT JOIN users u    ON a.user_id    = u.id
     WHERE MONTH(a.appointment_date)=$month
       AND YEAR(a.appointment_date)=$year
       AND a.status NOT IN ('cancelled','completed')
       AND (u.archived IS NULL OR u.archived = 0)
     ORDER BY a.appointment_date ASC, a.appointment_time ASC");

$cal_by_day = [];
foreach ($cal_appts as $ca) {
    $day = (int)date('j', strtotime($ca['appointment_date']));
    if ($ca['appointment_type'] === 'home_service') {
        $hpets = getRows($conn,
            "SELECT hsp.pet_name, hsp.species, s.name AS service_name
             FROM home_service_pets hsp
             LEFT JOIN services s ON hsp.service_id = s.id
             WHERE hsp.appointment_id = {$ca['id']}");
        if (!empty($hpets)) {
            foreach ($hpets as $hp) {
                $ev              = $ca;
                $ev['pet_name']  = $hp['pet_name'];
                $ev['svc_name']  = $hp['service_name'] ?? 'Home Service';
                $ev['is_home']   = true;
                $ev['home_pets'] = $hpets;
                $cal_by_day[$day][] = $ev;
            }
        } else {
            $ca['is_home']   = true;
            $ca['home_pets'] = [];
            $cal_by_day[$day][] = $ca;
        }
    } else {
        $ca['is_home'] = false;
        $cal_by_day[$day][] = $ca;
    }
}

// ── List view data ───────────────────────────────────────────
$filter_date   = sanitize($conn, $_GET['filter_date']   ?? '');
$search_appt   = sanitize($conn, $_GET['search_appt']   ?? '');
$filter_status = sanitize($conn, $_GET['filter_status'] ?? '');
$filter_type   = sanitize($conn, $_GET['filter_type']   ?? '');

$list_where = "1=1";
if ($filter_date)   $list_where .= " AND a.appointment_date = '$filter_date'";
if ($search_appt)   $list_where .= " AND (u.name LIKE '%$search_appt%' OR p.name LIKE '%$search_appt%')";
if ($filter_status) $list_where .= " AND a.status = '$filter_status'";
if ($filter_type)   $list_where .= " AND a.appointment_type = '$filter_type'";

$show_archived_appts = isset($_GET['show_archived_appts']) && $_GET['show_archived_appts'] == 1;

$archive_where = $show_archived_appts
    ? "AND (a.archived = 1)"
    : "AND (a.archived IS NULL OR a.archived = 0)";

$all_appts = getRows($conn,
    "SELECT a.*, p.name AS pet_name, s.name AS svc_name, u.name AS owner_name
     FROM appointments a
     LEFT JOIN pets p     ON a.pet_id     = p.id
     LEFT JOIN services s ON a.service_id = s.id
     LEFT JOIN users u    ON a.user_id    = u.id
    WHERE $list_where $archive_where
       AND (p.archived IS NULL OR p.archived = 0 OR p.id IS NULL)
     ORDER BY a.appointment_date DESC, a.appointment_time ASC
     LIMIT 200");
// Stats for header
$stat_pending   = countRows($conn, 'appointments', "status='pending'");
$stat_confirmed = countRows($conn, 'appointments', "status='confirmed'");
$stat_today     = countRows($conn, 'appointments', "status NOT IN ('cancelled','completed') AND appointment_date=CURDATE()");

$month_names   = ['','January','February','March','April','May','June',
                  'July','August','September','October','November','December'];
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_dow     = (int)date('w', mktime(0,0,0,$month,1,$year));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments — Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Stat cards ── */
        .appt-stats {
            display: grid;
            grid-template-columns: repeat(3,1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        .appt-stat-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 16px 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .appt-stat-icon {
            width: 48px; height: 48px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .appt-stat-label { font-size: 11px; color: var(--text-light); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .appt-stat-value { font-family: var(--font-head); font-size: 26px; font-weight: 800; color: var(--text-dark); }

        /* ── View toggle ── */
        .view-toggle {
            display: flex; gap: 0;
            margin-bottom: 20px;
            background: rgba(255,255,255,0.5);
            border-radius: var(--radius);
            padding: 4px;
            width: fit-content;
        }
        .view-btn {
            padding: 8px 22px;
            border-radius: var(--radius-sm);
            font-weight: 700; font-size: 13px;
            cursor: pointer; border: none;
            background: transparent; color: var(--text-mid);
            transition: var(--transition);
        }
        .view-btn.active { background: var(--blue-header); color: #fff; box-shadow: var(--shadow); }

        /* ── Calendar ── */
        .calendar-wrap {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }
        .cal-header {
            display: flex; align-items: center;
            justify-content: space-between;
            margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
        }
        .cal-header h3 { font-family: var(--font-head); font-size: 17px; font-weight: 700; }
        .cal-nav { display: flex; align-items: center; gap: 10px; }
        .cal-nav-btn {
            width: 32px; height: 32px; border-radius: 50%;
            border: 1.5px solid var(--border); background: #fff;
            cursor: pointer; font-size: 16px;
            display: flex; align-items: center; justify-content: center;
            transition: var(--transition); text-decoration: none; color: var(--text-dark);
        }
        .cal-nav-btn:hover { background: var(--teal); color: #fff; border-color: var(--teal); }
        .cal-month-label {
            font-family: var(--font-head); font-weight: 700;
            color: var(--teal-dark); font-size: 15px;
            min-width: 150px; text-align: center;
        }

        .cal-legend { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
        .cal-legend-item { display: flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: var(--text-mid); }
        .cal-legend-dot  { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }

        .cal-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 2px; }
        .cal-day-header {
            text-align: center; font-size: 11px; font-weight: 800;
            color: var(--text-light); text-transform: uppercase;
            padding: 6px 0; letter-spacing: 0.3px;
        }
        .cal-cell {
            min-height: 88px;
            background: #fafffe; border: 1px solid #e8f5f5;
            border-radius: 4px; padding: 4px; overflow: hidden;
        }
        .cal-cell.today { background: rgba(0,188,212,0.08); border-color: var(--teal); }
        .cal-cell.empty { background: transparent; border-color: transparent; }
        .cal-day-num {
            font-size: 12px; font-weight: 700; color: var(--text-mid);
            margin-bottom: 3px; text-align: right; padding-right: 2px;
        }
        .cal-cell.today .cal-day-num { color: var(--teal-dark); }
        .cal-event {
            font-size: 10px; font-weight: 700; color: #fff;
            padding: 2px 5px; border-radius: 3px; margin-bottom: 2px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
            cursor: pointer; transition: opacity 0.15s;
        }
        .cal-event:hover { opacity: 0.82; }
        .cal-more { font-size: 10px; color: var(--text-light); font-weight: 600; text-align: center; }

        /* ── List view ── */
        .list-wrap {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 20px; box-shadow: var(--shadow);
        }
        .list-toolbar {
            display: flex; align-items: center;
            gap: 10px; flex-wrap: wrap; margin-bottom: 16px;
        }
        .status-select {
            padding: 7px 12px; border: 1.5px solid var(--border);
            border-radius: var(--radius-sm); font-size: 13px;
            background: #fff; outline: none; font-family: var(--font-main);
        }

        /* ── Upcoming panel ── */
        .upcoming-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;
        }
        .upcoming-box {
            background: rgba(255,255,255,0.88);
            border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow);
        }
        .upcoming-box-title {
            font-weight: 800; font-size: 13px; color: var(--text-light);
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px;
        }
        .appt-row {
            background: rgba(255,255,255,0.7); border-radius: var(--radius-sm);
            padding: 12px 14px; margin-bottom: 10px; border-left: 3px solid var(--teal);
        }
        .appt-row.home { border-left-color: #c2185b; }
        .appt-row-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
        .appt-row-label { font-weight: 800; font-size: 14px; color: var(--text-dark); }
        .appt-row-meta { font-size: 12px; color: var(--text-light); display: flex; flex-direction: column; gap: 3px; }
        .appt-row-meta span { display: flex; align-items: center; gap: 6px; }
        .appt-row-actions { margin-top: 8px; display: flex; gap: 6px; flex-wrap: wrap; }

        .status-action-btn {
            padding: 4px 10px; border-radius: 6px; border: none;
            font-size: 11px; font-weight: 700; cursor: pointer;
            transition: var(--transition); color: #fff;
        }
        .btn-confirm  { background: #3b82f6; }
        .btn-complete { background: #10b981; }
        .btn-cancel-s { background: #ef4444; }
        .btn-del      { background: #6b7280; }
        .status-action-btn:hover { opacity: 0.85; transform: translateY(-1px); }

        /* inline table actions */
        .inline-status { display: flex; gap: 4px; flex-wrap: wrap; }

        /* ── Delete confirm modal ── */
        .del-overlay {
            display: none; position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(3px);
            align-items: center; justify-content: center;
        }
        .del-overlay.open { display: flex; }
        .del-box {
            background: #fff; border-radius: 16px; padding: 32px 28px;
            max-width: 380px; width: 90%; text-align: center;
            box-shadow: 0 24px 60px rgba(0,0,0,0.2);
            animation: popIn 0.2s ease;
        }
        @keyframes popIn {
            from { opacity:0; transform: scale(0.92) translateY(10px); }
            to   { opacity:1; transform: scale(1) translateY(0); }
        }
        .del-icon-wrap {
            width: 64px; height: 64px; border-radius: 50%;
            background: #fff5f5; border: 2px solid #fee2e2;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; margin: 0 auto 16px;
        }
        .del-box h3 { font-family: var(--font-head); font-size: 18px; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
        .del-box p  { font-size: 13px; color: var(--text-mid); margin-bottom: 24px; line-height: 1.6; }
        .del-actions { display: flex; gap: 10px; justify-content: center; }
        .btn-keep {
            padding: 10px 22px; border-radius: 8px; border: 1.5px solid var(--border);
            background: #fff; color: var(--text-dark); font-weight: 700; font-size: 13px;
            cursor: pointer; font-family: var(--font-main); transition: background 0.15s;
        }
        .btn-keep:hover { background: #f9fafb; }
        .btn-del-confirm {
            padding: 10px 22px; border-radius: 8px; border: none;
            background: linear-gradient(135deg,#ef4444,#dc2626); color: #fff;
            font-weight: 700; font-size: 13px; cursor: pointer;
            box-shadow: 0 4px 12px rgba(239,68,68,0.3); font-family: var(--font-main);
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-del-confirm:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(239,68,68,0.35); }

        @media(max-width:700px) {
            .appt-stats    { grid-template-columns: 1fr 1fr; }
            .upcoming-grid { grid-template-columns: 1fr; }
            .cal-cell      { min-height: 60px; }
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
                Appointment Management
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn" title="Notifications">
                    🔔
                    <?php $unread_notif = getUnreadNotifications($conn, $_SESSION['user_id']); if ($unread_notif > 0): ?>
                        <span class="badge-count"><?= $unread_notif ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- ── Stat Cards ── -->
            <div class="appt-stats">
                <div class="appt-stat-card">
                    <div class="appt-stat-icon" style="background:#fef3c7;">⏳</div>
                    <div>
                        <div class="appt-stat-label">Pending</div>
                        <div class="appt-stat-value"><?= $stat_pending ?></div>
                    </div>
                </div>
                <div class="appt-stat-card">
                    <div class="appt-stat-icon" style="background:#dbeafe;">✅</div>
                    <div>
                        <div class="appt-stat-label">Confirmed</div>
                        <div class="appt-stat-value"><?= $stat_confirmed ?></div>
                    </div>
                </div>
                <div class="appt-stat-card">
                    <div class="appt-stat-icon" style="background:#d1fae5;">📅</div>
                    <div>
                        <div class="appt-stat-label">Today</div>
                        <div class="appt-stat-value"><?= $stat_today ?></div>
                    </div>
                </div>
            </div>

            <!-- ── View Toggle ── -->
            <div class="view-toggle">
                <button class="view-btn active" onclick="showView('calendar',this)">📅 Calendar View</button>
                <button class="view-btn"        onclick="showView('list',this)">📋 Appointments List</button>
            </div>

            <!-- ══════════ CALENDAR VIEW ══════════════════════ -->
            <div id="view-calendar">
                <div class="calendar-wrap">
                    <div class="cal-header">
                        <h3>🐾 Pet Clinic Appointment Schedule</h3>
                        <div class="cal-nav">
                            <a href="?month=<?= $month-1 ?>&year=<?= $year ?>" class="cal-nav-btn">‹</a>
                            <div class="cal-month-label"><?= $month_names[$month] ?> <?= $year ?></div>
                            <a href="?month=<?= $month+1 ?>&year=<?= $year ?>" class="cal-nav-btn">›</a>
                        </div>
                    </div>

                    <!-- Legend -->
                    <div class="cal-legend">
                        <div style="width:100%;font-size:11px;font-weight:800;color:var(--teal-dark);
                                    text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                            🏥 Clinic
                        </div>
                        <?php foreach ($svc_colors as $svc => $color): ?>
                            <div class="cal-legend-item">
                                <div class="cal-legend-dot" style="background:<?= $color ?>"></div>
                                <?= htmlspecialchars($svc) ?>
                            </div>
                        <?php endforeach; ?>
                        <div style="width:100%;font-size:11px;font-weight:800;color:#c2185b;
                                    text-transform:uppercase;letter-spacing:0.5px;margin:8px 0 4px;">
                            🏠 Home Service
                        </div>
                        <?php foreach ($home_svc_colors as $svc => $color): ?>
                            <div class="cal-legend-item">
                                <div class="cal-legend-dot" style="background:<?= $color ?>"></div>
                                <?= htmlspecialchars($svc) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Grid -->
                    <div class="cal-grid">
                        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dh): ?>
                            <div class="cal-day-header"><?= $dh ?></div>
                        <?php endforeach; ?>
                        <?php for ($e = 0; $e < $first_dow; $e++): ?>
                            <div class="cal-cell empty"></div>
                        <?php endfor; ?>
                        <?php
                        $td = (int)date('j'); $tm = (int)date('m'); $ty = (int)date('Y');
                        for ($d = 1; $d <= $days_in_month; $d++):
                            $is_today = ($d===$td && $month===$tm && $year===$ty);
                            $events   = $cal_by_day[$d] ?? [];
                            $show_max = 4;
                        ?>
                            <div class="cal-cell <?= $is_today?'today':'' ?>">
                                <div class="cal-day-num"><?= $d ?></div>
                                <?php foreach (array_slice($events, 0, $show_max) as $ev):
                                    $is_home   = !empty($ev['is_home']);
                                    $color_map = $is_home ? $home_svc_colors : $svc_colors;
                                    $ec        = $color_map[$ev['svc_name'] ?? ''] ?? ($is_home ? '#8b2f68' : '#607d8b');
                                    $pet_lbl   = $ev['pet_name'] ?: ($is_home ? 'Home' : '?');
                                    $svc_lbl   = $ev['svc_name'] ?: ($is_home ? 'Home Service' : 'Appt');
                                    $icon      = $is_home ? '🏠' : '🏥';
                                ?>
                                    <div class="cal-event" style="background:<?= $ec ?>;"
                                         onclick="openApptModal(<?= htmlspecialchars(json_encode($ev),ENT_QUOTES) ?>)"
                                         title="<?= htmlspecialchars($icon.' '.$pet_lbl.' — '.$svc_lbl) ?>">
                                        <?= $icon ?> <?= htmlspecialchars($svc_lbl) ?> · <?= htmlspecialchars($ev['owner_name'] ?? $pet_lbl) ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($events) > $show_max): ?>
                                    <div class="cal-more">+<?= count($events)-$show_max ?> more</div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                        <?php
                        $trailing = (7 - (($first_dow + $days_in_month) % 7)) % 7;
                        for ($t = 0; $t < $trailing; $t++): ?>
                            <div class="cal-cell empty"></div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- ── Upcoming boxes (like user side) ── -->
                <div class="upcoming-grid">
                    <?php
                    $upcoming_clinic = getRows($conn,
                        "SELECT a.*, p.name AS pet_name, s.name AS svc_name, u.name AS owner_name
                         FROM appointments a
                         LEFT JOIN pets p ON a.pet_id=p.id
                         LEFT JOIN services s ON a.service_id=s.id
                         LEFT JOIN users u ON a.user_id=u.id
                        WHERE a.appointment_type='clinic' AND a.status IN('pending','confirmed')
           AND a.appointment_date>=CURDATE()
           AND (u.archived IS NULL OR u.archived = 0)
           AND (p.archived IS NULL OR p.archived = 0 OR p.id IS NULL)
         ORDER BY a.appointment_date ASC LIMIT 6");
                    $upcoming_home = getRows($conn,
                        "SELECT a.*, u.name AS owner_name
                         FROM appointments a
                         LEFT JOIN users u ON a.user_id=u.id
                         WHERE a.appointment_type='home_service' AND a.status IN('pending','confirmed')
           AND a.appointment_date>=CURDATE()
           AND (u.archived IS NULL OR u.archived = 0)
         ORDER BY a.appointment_date ASC LIMIT 6");
                    foreach ($upcoming_home as &$ha) {
                        $aid = (int)$ha['id'];
                        $ha['pet_count'] = countRows($conn,'home_service_pets',"appointment_id=$aid");
                    } unset($ha);
                    ?>
                    <!-- Clinic -->
                    <div class="upcoming-box">
                        <div class="upcoming-box-title">📅 Upcoming Clinic Appointments</div>
                        <?php if (empty($upcoming_clinic)): ?>
                            <p style="font-size:13px;color:var(--text-light);">No upcoming clinic appointments.</p>
                        <?php else: ?>
                            <?php foreach ($upcoming_clinic as $ap): ?>
                                <div class="appt-row">
                                    <div class="appt-row-top">
                                        <div>
                                            <div class="appt-row-label"><?= htmlspecialchars($ap['owner_name']??'—') ?></div>
                                            <div style="font-size:12px;color:var(--teal-dark);font-weight:600;">
                                                <?= htmlspecialchars($ap['pet_name']??'—') ?> · <?= htmlspecialchars($ap['svc_name']??'—') ?>
                                            </div>
                                        </div>
                                        <?= statusBadge($ap['status']) ?>
                                    </div>
                                    <div class="appt-row-meta">
                                        <span>📅 <?= formatDate($ap['appointment_date']) ?></span>
                                        <span>🕐 <?= formatTime($ap['appointment_time']) ?></span>
                                    </div>
                                    <div class="appt-row-actions">
                                        <?php if ($ap['status']==='pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action"  value="update_status">
                                                <input type="hidden" name="appt_id" value="<?= $ap['id'] ?>">
                                                <input type="hidden" name="status"  value="confirmed">
                                                <button type="submit" class="status-action-btn btn-confirm">Confirm</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (in_array($ap['status'],['pending','confirmed'])): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action"  value="update_status">
                                                <input type="hidden" name="appt_id" value="<?= $ap['id'] ?>">
                                                <input type="hidden" name="status"  value="completed">
                                                <button type="submit" class="status-action-btn btn-complete">Done</button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action"  value="update_status">
                                                <input type="hidden" name="appt_id" value="<?= $ap['id'] ?>">
                                                <input type="hidden" name="status"  value="cancelled">
                                                <button type="submit" class="status-action-btn btn-cancel-s">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                        <button class="status-action-btn btn-del"
                                                onclick="openDelModal(<?= $ap['id'] ?>, '<?= htmlspecialchars(addslashes($ap['owner_name']??'')) ?>', '<?= addslashes(formatDate($ap['appointment_date'])) ?>')">
                                            Del
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Home Service -->
                    <div class="upcoming-box">
                        <div class="upcoming-box-title">🏠 Upcoming Home Services</div>
                        <?php if (empty($upcoming_home)): ?>
                            <p style="font-size:13px;color:var(--text-light);">No upcoming home service appointments.</p>
                        <?php else: ?>
                            <?php foreach ($upcoming_home as $ha): ?>
                                <div class="appt-row home">
                                    <div class="appt-row-top">
                                        <div>
                                            <div class="appt-row-label"><?= htmlspecialchars($ha['owner_name']??'—') ?></div>
                                            <div style="font-size:12px;color:#c2185b;font-weight:600;">
                                                🏠 Home Service · <?= $ha['pet_count'] ?> Pet(s)
                                            </div>
                                        </div>
                                        <?= statusBadge($ha['status']) ?>
                                    </div>
                                    <div class="appt-row-meta">
                                        <span>📅 <?= formatDate($ha['appointment_date']) ?></span>
                                        <span>🕐 <?= formatTime($ha['appointment_time']) ?></span>
                                        <?php if ($ha['address']): ?>
                                            <span>📍 <?= htmlspecialchars($ha['address']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="appt-row-actions">
                                        <?php if ($ha['status']==='pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action"  value="update_status">
                                                <input type="hidden" name="appt_id" value="<?= $ha['id'] ?>">
                                                <input type="hidden" name="status"  value="confirmed">
                                                <button type="submit" class="status-action-btn btn-confirm">Confirm</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (in_array($ha['status'],['pending','confirmed'])): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action"  value="update_status">
                                                <input type="hidden" name="appt_id" value="<?= $ha['id'] ?>">
                                                <input type="hidden" name="status"  value="completed">
                                                <button type="submit" class="status-action-btn btn-complete">Done</button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action"  value="update_status">
                                                <input type="hidden" name="appt_id" value="<?= $ha['id'] ?>">
                                                <input type="hidden" name="status"  value="cancelled">
                                                <button type="submit" class="status-action-btn btn-cancel-s">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                        <button class="status-action-btn btn-del"
                                                onclick="openDelModal(<?= $ha['id'] ?>, '<?= htmlspecialchars(addslashes($ha['owner_name']??'')) ?>', '<?= addslashes(formatDate($ha['appointment_date'])) ?>')">
                                            Del
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div><!-- /view-calendar -->

            <!-- ══════════ LIST VIEW ══════════════════════════ -->
            <div id="view-list" style="display:none;">
                <div class="list-wrap">
                    <h3 style="font-family:var(--font-head);font-weight:700;font-size:17px;margin-bottom:16px;">
                        All Booked Appointments
                    </h3>

                    <form method="GET" class="list-toolbar">
                        <input type="hidden" name="view" value="list">
                        <div class="search-box">
                            <span>🔍</span>
                            <input type="text" name="search_appt"
                                   placeholder="Search owner or pet..."
                                   value="<?= htmlspecialchars($search_appt) ?>">
                        </div>
                        <input type="date" name="filter_date"
                               value="<?= htmlspecialchars($filter_date) ?>"
                               style="padding:8px 12px;border:1.5px solid var(--border);
                                      border-radius:var(--radius-sm);font-size:13px;
                                      background:#fff;outline:none;">
                        <select name="filter_status" class="status-select">
                            <option value="">All Status</option>
                            <option value="pending"   <?= $filter_status==='pending'   ?'selected':'' ?>>Pending</option>
                            <option value="confirmed" <?= $filter_status==='confirmed' ?'selected':'' ?>>Confirmed</option>
                            <option value="completed" <?= $filter_status==='completed' ?'selected':'' ?>>Completed</option>
                            <option value="cancelled" <?= $filter_status==='cancelled' ?'selected':'' ?>>Cancelled</option>
                        </select>
                        <select name="filter_type" class="status-select">
                            <option value="">All Types</option>
                            <option value="clinic"       <?= $filter_type==='clinic'       ?'selected':'' ?>>🏥 Clinic</option>
                            <option value="home_service" <?= $filter_type==='home_service' ?'selected':'' ?>>🏠 Home Service</option>
                        </select>
                        <button type="submit" class="btn btn-teal btn-sm">Filter</button>
                        <a href="appointments.php?view=list" class="btn btn-gray btn-sm">Reset</a>
                        <a href="appointments.php?view=list&show_archived_appts=<?= $show_archived_appts ? 0 : 1 ?><?= $filter_status ? '&filter_status='.urlencode($filter_status) : '' ?><?= $search_appt ? '&search_appt='.urlencode($search_appt) : '' ?>"
                           class="btn btn-sm"
                           style="background:<?= $show_archived_appts ? '#f59e0b' : '#6b7280' ?>;color:#fff;">
                            <?= $show_archived_appts ? '📋 Show Active' : '🗃️ Show Archived' ?>
                        </a>
                    </form>

                    <?php if (empty($all_appts)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <h3>No appointments found</h3>
                        </div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table id="apptTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Owner</th>
                                        <th>Pet</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Service</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Rating</th>
                                        <th>Archive</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_appts as $ap): ?>
                                        <tr>
                                            <td style="color:var(--text-light);font-size:12px;">
                                                #<?= str_pad($ap['id'],4,'0',STR_PAD_LEFT) ?>
                                            </td>
                                            <td><strong><?= htmlspecialchars($ap['owner_name']??'—') ?></strong></td>
                                            <td><?= htmlspecialchars($ap['pet_name']??'(Multiple)') ?></td>
                                            <td><?= formatDate($ap['appointment_date']) ?></td>
                                            <td><?= formatTime($ap['appointment_time']) ?></td>
                                            <td><?= htmlspecialchars($ap['svc_name'] ?? ucfirst(str_replace('_',' ',$ap['appointment_type']))) ?></td>
                                            <td>
                                                <span style="font-size:11px;background:<?= $ap['appointment_type']==='home_service'?'#fce7f3':'#dbeafe' ?>;
                                                             color:<?= $ap['appointment_type']==='home_service'?'#9d174d':'#1e40af' ?>;
                                                             padding:2px 8px;border-radius:20px;font-weight:700;">
                                                    <?= $ap['appointment_type']==='home_service'?'🏠 Home':'🏥 Clinic' ?>
                                                </span>
                                            </td>
                                            <td><?= statusBadge($ap['status']) ?></td>

                                            <td>
    <?php if ($ap['status'] === 'completed'):
        $appt_rating = getRow($conn, "SELECT stars FROM ratings WHERE appointment_id={$ap['id']} LIMIT 1");
    ?>
        <?php if ($appt_rating): ?>
            <span style="color:#f59e0b;font-size:15px;letter-spacing:1px;" title="<?= $appt_rating['stars'] ?>/5 stars">
                <?= str_repeat('★', $appt_rating['stars']) ?><?= str_repeat('☆', 5 - $appt_rating['stars']) ?>
            </span>
            <span style="font-size:11px;color:var(--text-light);display:block;"><?= $appt_rating['stars'] ?>/5</span>
        <?php else: ?>
            <span style="color:var(--text-light);font-size:12px;">Not yet rated</span>
        <?php endif; ?>
    <?php else: ?>
        <span style="color:var(--text-light);font-size:12px;">—</span>
    <?php endif; ?>
</td>

                                          <td>
                                                <?php if (!$show_archived_appts): ?>
                                                    <a href="appointments.php?view=list&archive=<?= $ap['id'] ?>"
                                                      onclick="return openArchiveModal(<?= $ap['id'] ?>, 'archive', 'appointment')"
                                                       class="status-action-btn btn-del"
                                                       style="text-decoration:none;padding:4px 10px;display:inline-block;background:#6b7280;">
                                                        🗃️ Archive
                                                    </a>
                                                <?php else: ?>
                                                    <a href="appointments.php?view=list&restore=<?= $ap['id'] ?>&show_archived_appts=1"
                                                      onclick="return openArchiveModal(<?= $ap['id'] ?>, 'restore', 'appointment')"                                                       class="status-action-btn btn-confirm"
                                                       style="text-decoration:none;padding:4px 10px;display:inline-block;">
                                                        ♻️ Restore
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="inline-status">
                                                    <?php if ($ap['status']==='pending'): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="action"  value="update_status">
                                                            <input type="hidden" name="appt_id" value="<?= $ap['id'] ?>">
                                                            <input type="hidden" name="status"  value="confirmed">
                                                            <button type="submit" class="status-action-btn btn-confirm">Confirm</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if (in_array($ap['status'],['pending','confirmed'])): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="action"  value="update_status">
                                                            <input type="hidden" name="appt_id" value="<?= $ap['id'] ?>">
                                                            <input type="hidden" name="status"  value="completed">
                                                            <button type="submit" class="status-action-btn btn-complete">Done</button>
                                                        </form>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="action"  value="update_status">
                                                            <input type="hidden" name="appt_id" value="<?= $ap['id'] ?>">
                                                            <input type="hidden" name="status"  value="cancelled">
                                                            <button type="submit" class="status-action-btn btn-cancel-s">Cancel</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button class="status-action-btn btn-del"
                                                            onclick="openDelModal(<?= $ap['id'] ?>, '<?= htmlspecialchars(addslashes($ap['owner_name']??'')) ?>', '<?= addslashes(formatDate($ap['appointment_date'])) ?>')">
                                                        Del
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div><!-- /view-list -->

        </div><!-- /page-body -->
    </div>
</div>

<!-- ══ Appointment Detail Modal (calendar click) ════════════ -->
<div class="modal-overlay" id="apptDetailModal">
    <div class="modal" style="max-width:440px;">
        <button class="modal-close" onclick="document.getElementById('apptDetailModal').classList.remove('open')">×</button>
        <h3 class="modal-title">Appointment Details</h3>
        <div id="apptDetailContent" style="font-size:13px;color:var(--text-mid);line-height:2;"></div>
        <div id="apptDetailActions" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;"></div>
        <div class="modal-actions" style="margin-top:12px;">
            <button class="btn btn-gray btn-sm"
                    onclick="document.getElementById('apptDetailModal').classList.remove('open')">Close</button>
        </div>
    </div>
</div>

<!-- ══ Delete Confirm Modal ══════════════════════════════════ -->
<div class="del-overlay" id="delOverlay" onclick="if(event.target===this)closeDelModal()">
    <div class="del-box">
        <div class="del-icon-wrap">🗓️</div>
        <h3>Delete Appointment?</h3>
        <p id="delModalInfo">This appointment will be permanently deleted.<br>This action cannot be undone.</p>
        <div class="del-actions">
            <button class="btn-keep" onclick="closeDelModal()">Keep It</button>
            <a id="delConfirmBtn" href="#" class="btn-del-confirm">✕ Yes, Delete</a>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function showView(view, btn) {
    document.getElementById('view-calendar').style.display = view === 'calendar' ? '' : 'none';
    document.getElementById('view-list').style.display     = view === 'list'     ? '' : 'none';
    document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('search_appt') || urlParams.has('filter_status') ||
    urlParams.has('filter_type')  || urlParams.get('view') === 'list') {
    showView('list', document.querySelectorAll('.view-btn')[1]);
}

// ── Calendar appointment click ────────────────────────────────
function openApptModal(ev) {
    const sc = {pending:'#f59e0b',confirmed:'#3b82f6',completed:'#10b981',cancelled:'#ef4444'}[ev.status]||'#888';
    const isHome = ev.appointment_type === 'home_service';
    const type   = isHome ? '🏠 Home Service' : '🏥 Clinic';

    let petsHtml = '';
    if (isHome && ev.home_pets && ev.home_pets.length > 0) {
        petsHtml = ev.home_pets.map(hp =>
            `<div style="font-size:12px;padding:3px 0;border-bottom:1px dotted #eee;">
                🐾 <strong>${escHtml(hp.pet_name)}</strong>
                <span style="color:#888;">${escHtml(hp.species||'')}</span>
                ${hp.service_name ? `— <span style="color:var(--teal-dark);font-weight:700;">${escHtml(hp.service_name)}</span>` : ''}
             </div>`
        ).join('');
    }

    document.getElementById('apptDetailContent').innerHTML = `
        <table style="width:100%;border-collapse:collapse;">
            <tr><td style="color:#888;width:110px;">Owner</td>
                <td><strong>${escHtml(ev.owner_name||'—')}</strong></td></tr>
            ${isHome && petsHtml
                ? `<tr><td style="color:#888;vertical-align:top;padding-top:6px;">Pets</td><td>${petsHtml}</td></tr>`
                : `<tr><td style="color:#888;">Pet</td><td>${escHtml(ev.pet_name||'—')}</td></tr>`}
            <tr><td style="color:#888;">Service</td>
                <td>${escHtml(ev.svc_name || (isHome?'Home Service':'—'))}</td></tr>
            <tr><td style="color:#888;">Type</td><td>${type}</td></tr>
            <tr><td style="color:#888;">Date</td><td>${ev.appointment_date}</td></tr>
            <tr><td style="color:#888;">Time</td><td>${ev.appointment_time}</td></tr>
            <tr><td style="color:#888;">Status</td>
                <td><span style="background:${sc};color:#fff;padding:2px 10px;
                                  border-radius:20px;font-size:12px;font-weight:700;">
                    ${ev.status.charAt(0).toUpperCase()+ev.status.slice(1)}
                </span></td></tr>
            ${ev.address ? `<tr><td style="color:#888;">Address</td><td>${escHtml(ev.address)}</td></tr>` : ''}
            ${ev.contact ? `<tr><td style="color:#888;">Contact</td><td>${escHtml(ev.contact)}</td></tr>` : ''}
        </table>`;

    // Quick action buttons inside modal
    let actions = '';
    if (ev.status === 'pending') {
        actions += `<form method="POST" style="display:inline;">
            <input type="hidden" name="action"  value="update_status">
            <input type="hidden" name="appt_id" value="${ev.id}">
            <input type="hidden" name="status"  value="confirmed">
            <button type="submit" class="status-action-btn btn-confirm">✔ Confirm</button>
        </form>`;
    }
    if (['pending','confirmed'].includes(ev.status)) {
        actions += `<form method="POST" style="display:inline;">
            <input type="hidden" name="action"  value="update_status">
            <input type="hidden" name="appt_id" value="${ev.id}">
            <input type="hidden" name="status"  value="completed">
            <button type="submit" class="status-action-btn btn-complete">✔ Done</button>
        </form>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="action"  value="update_status">
            <input type="hidden" name="appt_id" value="${ev.id}">
            <input type="hidden" name="status"  value="cancelled">
            <button type="submit" class="status-action-btn btn-cancel-s">✕ Cancel</button>
        </form>`;
    }
    actions += `<button class="status-action-btn btn-del"
        onclick="document.getElementById('apptDetailModal').classList.remove('open');
                 openDelModal(${ev.id},'${escHtml(ev.owner_name||'')}','${escHtml(ev.appointment_date)}')">
        🗑️ Delete
    </button>`;

    document.getElementById('apptDetailActions').innerHTML = actions;
    document.getElementById('apptDetailModal').classList.add('open');
}

// ── Delete modal ─────────────────────────────────────────────
function openDelModal(id, owner, date) {
    document.getElementById('delModalInfo').innerHTML =
        `Delete appointment for <strong>${owner}</strong> on <strong>${date}</strong>?<br>
         <span style="color:#ef4444;font-size:12px;">This cannot be undone.</span>`;
    document.getElementById('delConfirmBtn').href = `appointments.php?delete=${id}`;
    document.getElementById('delOverlay').classList.add('open');
}

function closeDelModal() {
    document.getElementById('delOverlay').classList.remove('open');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('apptDetailModal').classList.remove('open');
        closeDelModal();
    }
});

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
});

function escHtml(str) {
    if (!str) return '—';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<!-- ══ Archive / Restore Modal ══ -->
<div id="archiveOverlay" onclick="if(event.target===this)closeArchiveModal()"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);
            backdrop-filter:blur(3px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px 28px;max-width:380px;
                width:90%;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,0.2);
                animation:popIn 0.2s ease;">
        <div id="archiveModalIcon"
             style="width:64px;height:64px;border-radius:50%;display:flex;
                    align-items:center;justify-content:center;font-size:28px;margin:0 auto 16px;">
        </div>
        <h3 id="archiveModalTitle"
            style="font-family:var(--font-head);font-size:18px;font-weight:800;
                   color:var(--text-dark);margin-bottom:8px;"></h3>
        <p id="archiveModalDesc"
           style="font-size:13px;color:var(--text-mid);margin-bottom:24px;line-height:1.6;"></p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="closeArchiveModal()"
                    style="padding:10px 22px;border-radius:8px;border:1.5px solid var(--border);
                           background:#fff;color:var(--text-dark);font-weight:700;font-size:13px;
                           cursor:pointer;font-family:var(--font-main);">
                Cancel
            </button>
            <a id="archiveConfirmBtn" href="#"
               style="padding:10px 22px;border-radius:8px;border:none;color:#fff;font-weight:700;
                      font-size:13px;cursor:pointer;text-decoration:none;display:inline-flex;
                      align-items:center;gap:6px;transition:transform 0.15s,box-shadow 0.15s;">
            </a>
        </div>
    </div>
</div>

<script>
function openArchiveModal(id, action, type) {
    const isArchive = action === 'archive';

    // Icon + colors
    const iconWrap = document.getElementById('archiveModalIcon');
    iconWrap.textContent = isArchive ? '🗃️' : '♻️';
    iconWrap.style.background = isArchive ? '#f3f4f6' : '#ecfdf5';
    iconWrap.style.border     = isArchive ? '2px solid #d1d5db' : '2px solid #6ee7b7';

    // Title
    document.getElementById('archiveModalTitle').textContent =
        isArchive ? `Archive this ${type}?` : `Restore this ${type}?`;

    // Description
    document.getElementById('archiveModalDesc').innerHTML = isArchive
        ? `This ${type} will be <strong>moved to the archive</strong>.<br>
           <span style="color:#6b7280;font-size:12px;">You can restore it anytime from the archived view.</span>`
        : `This ${type} will be <strong>restored to active records</strong>.<br>
           <span style="color:#6b7280;font-size:12px;">It will reappear in the main list.</span>`;

    // Confirm button
    const btn = document.getElementById('archiveConfirmBtn');
    btn.textContent = isArchive ? '🗃️ Yes, Archive' : '♻️ Yes, Restore';
    btn.style.background   = isArchive
        ? 'linear-gradient(135deg,#6b7280,#4b5563)'
        : 'linear-gradient(135deg,#10b981,#059669)';
    btn.style.boxShadow = isArchive
        ? '0 4px 12px rgba(107,114,128,0.35)'
        : '0 4px 12px rgba(16,185,129,0.35)';

    // Build the correct URL
    const pageMap = { appointment: 'appointments.php', transaction: 'transactions.php' };
    const paramMap = {
        appointment: { archive: `archive=${id}`, restore: `restore=${id}&show_archived_appts=1` },
        transaction: { archive: `show_archived_txns=0`, restore: `show_archived_txns=1` }
    };

    if (type === 'transaction') {
        // Transactions use form POST — submit the parent form instead
        btn.removeAttribute('href');
        btn.onclick = () => {
            closeArchiveModal();
            const forms = document.querySelectorAll(`[name="txn_id"][value="${id}"]`);
            forms.forEach(input => {
                const form = input.closest('form');
                if (form) {
                    const actionInput = form.querySelector('[name="action"]');
                    if (actionInput && actionInput.value === (isArchive ? 'archive_transaction' : 'restore_transaction')) {
                        form.submit();
                    }
                }
            });
        };
    } else {
        btn.onclick = null;
        btn.href = `${pageMap[type]}?${paramMap[type][action]}`;
    }

    const overlay = document.getElementById('archiveOverlay');
    overlay.style.display = 'flex';
    return false;
}

function closeArchiveModal() {
    document.getElementById('archiveOverlay').style.display = 'none';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeArchiveModal();
});
</script>


</body>
</html>