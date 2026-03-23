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
    $aid    = (int)$_POST['appt_id'];
    $status = sanitize($conn, $_POST['status'] ?? '');
    $allowed = ['pending','confirmed','completed','cancelled'];
    if (in_array($status, $allowed)) {
        mysqli_query($conn, "UPDATE appointments SET status='$status' WHERE id=$aid");
        // Notify the user
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
        }
        redirect('appointments.php?success=Appointment status updated.');
    }
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

// ── Fetch appointments for calendar month ────────────────────
$cal_appts = getRows($conn,
    "SELECT a.*, p.name AS pet_name, s.name AS svc_name, u.name AS owner_name
     FROM appointments a
     LEFT JOIN pets p     ON a.pet_id     = p.id
     LEFT JOIN services s ON a.service_id = s.id
     LEFT JOIN users u    ON a.user_id    = u.id
     WHERE MONTH(a.appointment_date)=$month
       AND YEAR(a.appointment_date)=$year
     ORDER BY a.appointment_date ASC, a.appointment_time ASC");

// Group by day
$cal_by_day = [];
foreach ($cal_appts as $ca) {
    $day = (int)date('j', strtotime($ca['appointment_date']));
    $cal_by_day[$day][] = $ca;
}

// ── Fetch all appointments for the list ──────────────────────
$filter_date   = sanitize($conn, $_GET['filter_date'] ?? '');
$search_appt   = sanitize($conn, $_GET['search_appt'] ?? '');
$filter_status = sanitize($conn, $_GET['filter_status'] ?? '');

$list_where = "1=1";
if ($filter_date)   $list_where .= " AND a.appointment_date = '$filter_date'";
if ($search_appt)   $list_where .= " AND (u.name LIKE '%$search_appt%' OR p.name LIKE '%$search_appt%')";
if ($filter_status) $list_where .= " AND a.status = '$filter_status'";

$all_appts = getRows($conn,
    "SELECT a.*, p.name AS pet_name, s.name AS svc_name, u.name AS owner_name
     FROM appointments a
     LEFT JOIN pets p     ON a.pet_id     = p.id
     LEFT JOIN services s ON a.service_id = s.id
     LEFT JOIN users u    ON a.user_id    = u.id
     WHERE $list_where
     ORDER BY a.appointment_date DESC, a.appointment_time ASC
     LIMIT 100");

// ── Service colors for calendar ──────────────────────────────
$svc_colors = [
    'CheckUp'      => '#1976d2',
    'Confinement'  => '#7c3aed',
    'Treatment'    => '#388e3c',
    'Deworming'    => '#f57c00',
    'Vaccination'  => '#ef4444',
    'Grooming'     => '#00838f',
    'Surgery'      => '#dc2626',
    'Laboratory'   => '#6366f1',
];

$home_svc_colors = [
    'CheckUp'      => '#f7a534',
    'Confinement'  => '#b42d56',
    'Treatment'    => '#51ff0c',
    'Deworming'    => '#968e8c',
    'Vaccination'  => '#ffef0e',
    'Grooming'     => '#85cff1',
    'Surgery'      => '#000000',
    'Laboratory'   => '#ad1010',
];

// Build dynamic color maps from DB
$db_services = getRows($conn, "SELECT name FROM services");
$svc_name_map = []; $home_svc_name_map = [];
foreach ($db_services as $sv) {
    $db_name = $sv['name'];
    $matched = false;
    foreach ($svc_colors as $key => $color) {
        if (strtolower($db_name) === strtolower($key)) { $svc_name_map[$db_name] = $color; $matched = true; break; }
    }
    if (!$matched) $svc_name_map[$db_name] = '#607d8b';
    $matched = false;
    foreach ($home_svc_colors as $key => $color) {
        if (strtolower($db_name) === strtolower($key)) { $home_svc_name_map[$db_name] = $color; $matched = true; break; }
    }
    if (!$matched) $home_svc_name_map[$db_name] = '#8b2f68';
}
$svc_colors = $svc_name_map;
$home_svc_colors = $home_svc_name_map;

$month_names = ['','January','February','March','April','May','June',
                'July','August','September','October','November','December'];
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_dow     = (int)date('w', mktime(0,0,0,$month,1,$year)); // 0=Sun
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments — Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>

        .cal-month-label { font-family:var(--font-head);font-weight:700;color:var(--teal-dark);font-size:15px;min-width:140px;text-align:center; }

        /* ── View toggle ── */
        .view-toggle {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }
        .view-btn {
            padding: 8px 20px;
            border-radius: var(--radius-sm);
            border: 2px solid var(--border);
            background: rgba(255,255,255,0.8);
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-mid);
        }
        .view-btn.active,
        .view-btn:hover {
            background: var(--blue-header);
            color: #fff;
            border-color: var(--blue-header);
        }

        /* ── Calendar ── */
        .calendar-wrap {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .cal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .cal-header h3 {
            font-family: var(--font-head);
            font-size: 18px;
            font-weight: 700;
        }
        .cal-nav {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cal-nav-btn {
            width: 32px; height: 32px;
            border-radius: 50%;
            border: 1.5px solid var(--border);
            background: #fff;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            text-decoration: none;
            color: var(--text-dark);
        }
        .cal-nav-btn:hover {
            background: var(--teal);
            color: #fff;
            border-color: var(--teal);
        }

        /* Legend */
        .cal-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }
        .cal-legend-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .cal-legend-dot {
            width: 10px; height: 10px;
            border-radius: 2px;
            flex-shrink: 0;
        }

        /* Grid */
        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }

        .cal-day-header {
            text-align: center;
            font-size: 11px;
            font-weight: 800;
            color: var(--text-light);
            text-transform: uppercase;
            padding: 6px 0;
            letter-spacing: 0.3px;
        }

        .cal-cell {
            min-height: 88px;
            background: #fafffe;
            border: 1px solid #e8f5f5;
            border-radius: 4px;
            padding: 4px;
            vertical-align: top;
            overflow: hidden;
        }
        .cal-cell.today {
            background: rgba(0,188,212,0.08);
            border-color: var(--teal);
        }
        .cal-cell.empty { background: transparent; border-color: transparent; }

        .cal-day-num {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-mid);
            margin-bottom: 3px;
            text-align: right;
            padding-right: 2px;
        }
        .cal-cell.today .cal-day-num {
            color: var(--teal-dark);
        }

        .cal-event {
            font-size: 10px;
            font-weight: 700;
            color: #fff;
            padding: 2px 5px;
            border-radius: 3px;
            margin-bottom: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            transition: opacity 0.15s;
        }
        .cal-event:hover { opacity: 0.85; }

        .cal-more {
            font-size: 10px;
            color: var(--text-light);
            font-weight: 600;
            text-align: center;
        }

        /* ── Appointments List ── */
        .list-wrap {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .list-toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .status-select {
            padding: 7px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            background: #fff;
            outline: none;
            font-family: var(--font-main);
        }

        .inline-status {
            display: flex;
            gap: 4px;
        }
        .status-action-btn {
            padding: 3px 8px;
            border-radius: 4px;
            border: none;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            color: #fff;
        }
        .btn-confirm   { background: #3b82f6; }
        .btn-complete  { background: #10b981; }
        .btn-cancel-s  { background: #ef4444; }
        .status-action-btn:hover { opacity: 0.85; transform: scale(1.05); }

        @media(max-width:700px) {
            .cal-grid { grid-template-columns: repeat(7,1fr); }
            .cal-cell { min-height: 60px; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <div class="topbar">
    <div class="topbar-title" style="display:flex; align-items:center; gap:10px;">
        <img src="../assets/css/images/pets/logo.png" alt="Logo"
             style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.6);"
             onerror="this.style.display='none';">
        Appointment
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

            <!-- View Toggle -->
            <div class="view-toggle">
                <button class="view-btn active" onclick="showView('calendar',this)">
                    📅 Calendar View
                </button>
                <button class="view-btn" onclick="showView('list',this)">
                    📋 Booked Appointments List
                </button>
            </div>

            <!-- ══ CALENDAR VIEW ══════════════════════════════ -->
            <div id="view-calendar">
                <div class="calendar-wrap">

                    <!-- Calendar Header -->
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
    <div style="width:100%;font-size:11px;font-weight:800;color:var(--teal-dark);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">🏥 Clinic</div>
    <?php foreach ($svc_colors as $svc => $color): if(strtolower($svc)==='home service') continue; ?>
        <div class="cal-legend-item">
            <div class="cal-legend-dot" style="background:<?= $color ?>"></div>
            <?= htmlspecialchars($svc) ?>
        </div>
    <?php endforeach; ?>
    <div style="width:100%;font-size:11px;font-weight:800;color:#c2185b;text-transform:uppercase;letter-spacing:0.5px;margin:8px 0 4px;">🏠 Home Service</div>
    <?php foreach ($home_svc_colors as $svc => $color): if(strtolower($svc)==='home service') continue; ?>
        <div class="cal-legend-item">
            <div class="cal-legend-dot" style="background:<?= $color ?>"></div>
            <?= htmlspecialchars($svc) ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="cal-legend-item"><div class="cal-legend-dot"></div></div>
                    <!-- Day headers -->
                    <div class="cal-grid">
                        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dh): ?>
                            <div class="cal-day-header"><?= $dh ?></div>
                        <?php endforeach; ?>

                        <!-- Empty cells before day 1 -->
                        <?php for ($e = 0; $e < $first_dow; $e++): ?>
                            <div class="cal-cell empty"></div>
                        <?php endfor; ?>

                        <!-- Day cells -->
                        <?php
                        $today_day = (int)date('j');
                        $today_m   = (int)date('m');
                        $today_y   = (int)date('Y');
                        for ($d = 1; $d <= $days_in_month; $d++):
                            $is_today = ($d === $today_day && $month === $today_m && $year === $today_y);
                            $events   = $cal_by_day[$d] ?? [];
                            $show_max = 5;
                        ?>
                            <div class="cal-cell <?= $is_today ? 'today' : '' ?>">
                                <div class="cal-day-num"><?= $d ?></div>
                                <?php foreach (array_slice($events, 0, $show_max) as $ev):
                                   $is_home = ($ev['appointment_type'] === 'home_service');
$ec = $is_home
    ? ($home_svc_colors[$ev['svc_name'] ?? ''] ?? '#8b2f68')
    : ($svc_colors[$ev['svc_name'] ?? ''] ?? '#607d8b');
                                    $label = $ev['svc_name'] ?: ucfirst(str_replace('_',' ',$ev['appointment_type']));
                                    $pet   = $ev['pet_name'] ?: ($ev['appointment_type'] === 'home_service' ? 'Home' : '?');
                                ?>
                                    <div class="cal-event"
                                         style="background:<?= $ec ?>;"
                                         onclick="openApptModal(<?= htmlspecialchars(json_encode($ev), ENT_QUOTES) ?>)"
                                         title="<?= htmlspecialchars($pet . ' — ' . $label) ?>">
                                       <?= $is_home ? '🏠' : '🏥' ?> <?= htmlspecialchars($label) ?> · <?= htmlspecialchars($pet) ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($events) > $show_max): ?>
                                    <div class="cal-more">
                                        +<?= count($events) - $show_max ?> more
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>

                        <!-- Trailing empty cells -->
                        <?php
                        $total_cells = $first_dow + $days_in_month;
                        $trailing    = (7 - ($total_cells % 7)) % 7;
                        for ($t = 0; $t < $trailing; $t++): ?>
                            <div class="cal-cell empty"></div>
                        <?php endfor; ?>
                    </div><!-- /cal-grid -->

                </div><!-- /calendar-wrap -->
            </div><!-- /view-calendar -->

            <!-- ══ LIST VIEW ══════════════════════════════════ -->
            <div id="view-list" style="display:none;">
                <div class="list-wrap">
                    <h3 style="font-family:var(--font-head);font-weight:700;
                                font-size:17px;margin-bottom:16px;">
                        BOOKED APPOINTMENT
                    </h3>

                    <!-- Toolbar -->
                    <form method="GET" class="list-toolbar">
                        <input type="hidden" name="view" value="list">
                        <div class="search-box">
                            <span>🔍</span>
                            <input type="text" name="search_appt"
                                   placeholder="Search Appointments..."
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
                        <button type="submit" class="btn btn-teal btn-sm">Filter</button>
                        <a href="appointments.php?view=list" class="btn btn-gray btn-sm">Reset</a>
                    </form>

                    <!-- Appointments Table -->
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
                                        <th>Appt. ID</th>
                                        <th>Owner Name</th>
                                        <th>Pet Name</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Service</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_appts as $ap): ?>
                                        <tr>
                                            <td style="color:var(--text-light);font-size:12px;">
                                                #<?= str_pad($ap['id'],4,'0',STR_PAD_LEFT) ?>
                                            </td>
                                            <td><strong><?= htmlspecialchars($ap['owner_name'] ?? '—') ?></strong></td>
                                            <td><?= htmlspecialchars($ap['pet_name'] ?? '(Home Service)') ?></td>
                                            <td><?= formatDate($ap['appointment_date']) ?></td>
                                            <td><?= formatTime($ap['appointment_time']) ?></td>
                                            <td><?= htmlspecialchars($ap['svc_name'] ?? ucfirst(str_replace('_',' ',$ap['appointment_type']))) ?></td>
                                            <td><?= statusBadge($ap['status']) ?></td>
                                            <td>
                                                <div class="inline-status">
                                                    <?php if ($ap['status'] === 'pending'): ?>
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
                                                    <a href="?delete=<?= $ap['id'] ?>"
                                                       onclick="return confirm('Delete this appointment?')"
                                                       class="status-action-btn btn-cancel-s"
                                                       style="text-decoration:none;">Del</a>
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

<!-- Appointment Detail Modal (Calendar click) -->
<div class="modal-overlay" id="apptDetailModal">
    <div class="modal" style="max-width:420px;">
        <button class="modal-close" onclick="closeModal('apptDetailModal')">×</button>
        <h3 class="modal-title">Appointment Details</h3>
        <div id="apptDetailContent" style="font-size:13px;color:var(--text-mid);line-height:2;"></div>
        <div class="modal-actions">
            <button class="btn btn-gray btn-sm" onclick="closeModal('apptDetailModal')">Close</button>
        </div>
    </div>
</div>

<script src="../assets/css/js/main.js"></script>
<script>
// ── Toggle calendar vs list view ─────────────────────────────
function showView(view, btn) {
    document.getElementById('view-calendar').style.display = view === 'calendar' ? '' : 'none';
    document.getElementById('view-list').style.display     = view === 'list'     ? '' : 'none';
    document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// Auto-show list view if URL has view=list or search params
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('search_appt') || urlParams.has('filter_status') ||
    urlParams.has('filter_date')  || urlParams.get('view') === 'list') {
    showView('list', document.querySelectorAll('.view-btn')[1]);
}

// ── Calendar appointment click ───────────────────────────────
function openApptModal(ev) {
    const statusColors = {
        pending:   '#f59e0b', confirmed: '#3b82f6',
        completed: '#10b981', cancelled: '#ef4444'
    };
    const sc = statusColors[ev.status] || '#888';
    const type = ev.appointment_type === 'home_service' ? '🏠 Home Service' : '🏥 Clinic';

    document.getElementById('apptDetailContent').innerHTML = `
        <table style="width:100%;border-collapse:collapse;">
            <tr><td style="color:#888;width:110px;">Owner</td>
                <td><strong>${escHtml(ev.owner_name || '—')}</strong></td></tr>
            <tr><td style="color:#888;">Pet</td>
                <td>${escHtml(ev.pet_name || '(Multiple pets)')}</td></tr>
            <tr><td style="color:#888;">Service</td>
                <td>${escHtml(ev.svc_name || type)}</td></tr>
            <tr><td style="color:#888;">Type</td>
                <td>${type}</td></tr>
            <tr><td style="color:#888;">Date</td>
                <td>${ev.appointment_date}</td></tr>
            <tr><td style="color:#888;">Time</td>
                <td>${ev.appointment_time}</td></tr>
            <tr><td style="color:#888;">Status</td>
                <td><span style="background:${sc};color:#fff;padding:2px 10px;
                                  border-radius:20px;font-size:12px;font-weight:700;">
                    ${ev.status.charAt(0).toUpperCase()+ev.status.slice(1)}
                </span></td></tr>
            ${ev.address ? `<tr><td style="color:#888;">Address</td>
                <td>${escHtml(ev.address)}</td></tr>` : ''}
        </table>`;
    openModal('apptDetailModal');
}

function escHtml(str) {
    if (!str) return '—';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>