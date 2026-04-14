<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/appointments.php
// ============================================================
require_once '../includes/auth.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id = (int)$_SESSION['user_id'];

// ── Handle Rating Submission ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_rating') {
    $appt_id = (int)($_POST['appointment_id'] ?? 0);
    $stars   = max(1, min(5, (int)($_POST['stars'] ?? 5)));
    if ($appt_id) {
        $appt_check = getRow($conn,
            "SELECT id FROM appointments WHERE id=$appt_id AND user_id=$user_id AND status='completed'");
        if ($appt_check) {
            $already = getRow($conn,
                "SELECT id FROM ratings WHERE user_id=$user_id AND appointment_id=$appt_id");
            if (!$already) {
                mysqli_query($conn,
                    "INSERT INTO ratings (user_id, appointment_id, stars)
                     VALUES ($user_id, $appt_id, $stars)");
            }
        }
    }
    redirect('appointments.php?view=list&rated=1');
}
$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$my_pets  = getRows($conn, "SELECT * FROM pets WHERE user_id=$user_id ORDER BY name ASC");
$services = getRows($conn, "SELECT * FROM services WHERE status='available' ORDER BY name ASC");

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

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

$db_services = getRows($conn, "SELECT name FROM services");
$svc_name_map      = [];
$home_svc_name_map = [];

foreach ($db_services as $sv) {
    $db_name = $sv['name'];

    $matched_clinic = false;
    foreach ($svc_colors as $key => $color) {
        if (strtolower($db_name) === strtolower($key)) {
            $svc_name_map[$db_name] = $color;
            $matched_clinic = true;
            break;
        }
    }
    if (!$matched_clinic) {
        $svc_name_map[$db_name] = '#607d8b';
    }

    $matched_home = false;
    foreach ($home_svc_colors as $key => $color) {
        if (strtolower($db_name) === strtolower($key)) {
            $home_svc_name_map[$db_name] = $color;
            $matched_home = true;
            break;
        }
    }
    if (!$matched_home) {
        $home_svc_name_map[$db_name] = '#8b2f68';
    }
}

$svc_colors      = $svc_name_map;
$home_svc_colors = $home_svc_name_map;
$month_names   = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
$days_in_month = cal_days_in_month(CAL_GREGORIAN,$month,$year);
$first_dow     = (int)date('w',mktime(0,0,0,$month,1,$year));

$cal_appts = getRows($conn,
    "SELECT a.*,p.name AS pet_name,s.name AS svc_name FROM appointments a
     LEFT JOIN pets p ON a.pet_id=p.id LEFT JOIN services s ON a.service_id=s.id
     WHERE a.user_id=$user_id AND MONTH(a.appointment_date)=$month AND YEAR(a.appointment_date)=$year
     AND a.status NOT IN ('cancelled','completed')
     ORDER BY a.appointment_date ASC,a.appointment_time ASC");

$cal_by_day = [];
foreach ($cal_appts as $ca) {
    $day = (int)date('j', strtotime($ca['appointment_date']));

    if ($ca['appointment_type'] === 'home_service') {
        $hpets = getRows($conn,
            "SELECT hsp.pet_name, hsp.species, s.name AS service_name, s.id AS service_id
             FROM home_service_pets hsp
             LEFT JOIN services s ON hsp.service_id = s.id
             WHERE hsp.appointment_id = {$ca['id']}");

        if (!empty($hpets)) {
            foreach ($hpets as $hp) {
                $event              = $ca;
                $event['pet_name']  = $hp['pet_name'];
                $event['svc_name']  = $hp['service_name'] ?? 'Home Service';
                $event['is_home']   = true;
                $event['home_pets'] = $hpets;
                $event['home_svc_label'] = $hp['service_name'] ?? 'Home Service';
                $cal_by_day[$day][] = $event;
            }
        } else {
            $ca['is_home']        = true;
            $ca['home_pets']      = [];
            $ca['home_svc_label'] = 'Home Service';
            $cal_by_day[$day][]   = $ca;
        }
    } else {
        $ca['is_home'] = false;
        $cal_by_day[$day][] = $ca;
    }
}

if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='book_clinic'){
    $pet_id=(int)($_POST['pet_id']??0);$svc_id=(int)($_POST['service_id']??0);
    $date=sanitize($conn,$_POST['appt_date']??'');$time=sanitize($conn,$_POST['appt_time']??'');
    $agreed=$_POST['agreed']??'';
    if(!$pet_id||!$svc_id||!$date||!$time) redirect('appointments.php?error=Please fill in all required fields.');
    if(!$agreed) redirect('appointments.php?error=You must agree to the clinic policies.');
    if(mysqli_query($conn,"INSERT INTO appointments(user_id,pet_id,service_id,appointment_type,appointment_date,appointment_time,status)VALUES($user_id,$pet_id,$svc_id,'clinic','$date','$time','pending')")){
        $appt_id=mysqli_insert_id($conn);
        $pet_name=getRow($conn,"SELECT name FROM pets WHERE id=$pet_id")['name']??'';
        $svc_name=getRow($conn,"SELECT name FROM services WHERE id=$svc_id")['name']??'';
        $admin=getRow($conn,"SELECT id FROM users WHERE role='admin' LIMIT 1");
        if($admin){$msg="New clinic appointment booked by {$_SESSION['user_name']} for $pet_name — $svc_name on ".formatDate($date);mysqli_query($conn,"INSERT INTO notifications(user_id,title,message,type)VALUES({$admin['id']},'New Appointment','$msg','appointment')");}
        redirect('appointments.php?success=Your checkup appointment for '.urlencode($pet_name).' has been successfully booked!');
    }else{redirect('appointments.php?error=Booking failed: '.mysqli_error($conn));}
}

if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='book_home'){
    $owner=sanitize($conn,$_POST['owner_name']??'');$address=sanitize($conn,$_POST['address']??'');
    $contact=sanitize($conn,$_POST['contact']??'');$date=sanitize($conn,$_POST['appt_date']??'');
    $time=sanitize($conn,$_POST['appt_time']??'');$agreed=$_POST['agreed_home']??'';$pets_data=$_POST['home_pets']??[];
    if(!$owner||!$address||!$date||!$time) redirect('appointments.php?error=Please fill in all required fields for home service.');
    if(!$agreed) redirect('appointments.php?error=You must agree to the home service policies.');
    if(mysqli_query($conn,"INSERT INTO appointments(user_id,pet_id,service_id,appointment_type,appointment_date,appointment_time,address,contact,status)VALUES($user_id,NULL,NULL,'home_service','$date','$time','$address','$contact','pending')")){
        $appt_id=mysqli_insert_id($conn);
        foreach($pets_data as $hp){$hname=sanitize($conn,$hp['name']??'');$hspecies=sanitize($conn,$hp['species']??'');$hsvc=(int)($hp['service_id']??0);if($hname)mysqli_query($conn,"INSERT INTO home_service_pets(appointment_id,pet_name,species,service_id)VALUES($appt_id,'$hname','$hspecies',".($hsvc?:'NULL').")");}
        $count=count($pets_data);
        redirect("appointments.php?success=Your home service appointment for $count pet(s) has been successfully booked!");
    }else{redirect('appointments.php?error=Home Service booking failed: '.mysqli_error($conn));}
}

if(isset($_GET['cancel'])&&is_numeric($_GET['cancel'])){
    $cid=(int)$_GET['cancel'];
    mysqli_query($conn,"UPDATE appointments SET status='cancelled' WHERE id=$cid AND user_id=$user_id AND status IN('pending','confirmed')");
    redirect('appointments.php?success=Appointment cancelled.');
}

$upcoming_clinic=getRows($conn,"SELECT a.*,p.name AS pet_name,s.name AS service_name FROM appointments a LEFT JOIN pets p ON a.pet_id=p.id LEFT JOIN services s ON a.service_id=s.id WHERE a.user_id=$user_id AND a.appointment_type='clinic' AND a.status IN('pending','confirmed') AND a.appointment_date>=CURDATE() ORDER BY a.appointment_date ASC LIMIT 5");
$upcoming_home=getRows($conn,"SELECT a.*,s.name AS service_name FROM appointments a LEFT JOIN services s ON a.service_id=s.id WHERE a.user_id=$user_id AND a.appointment_type='home_service' AND a.status IN('pending','confirmed') AND a.appointment_date>=CURDATE() ORDER BY a.appointment_date ASC LIMIT 5");
foreach($upcoming_home as &$ha){$aid=(int)$ha['id'];$ha['pet_count']=countRows($conn,'home_service_pets',"appointment_id=$aid");}unset($ha);

$from=sanitize($conn,$_GET['from']??date('Y-01-01'));$to=sanitize($conn,$_GET['to']??date('Y-12-31'));$hsearch=sanitize($conn,$_GET['hsearch']??'');
$hist_where="a.user_id=$user_id AND a.status IN('completed','cancelled') AND a.appointment_date BETWEEN '$from' AND '$to'";

$unrated_appt = getRow($conn,
    "SELECT a.id, a.appointment_date, s.name AS service_name
     FROM appointments a
     LEFT JOIN services s ON a.service_id = s.id
     WHERE a.user_id=$user_id AND a.status='completed'
     AND a.id NOT IN (SELECT appointment_id FROM ratings WHERE user_id=$user_id)
     ORDER BY a.appointment_date DESC LIMIT 1");
if($hsearch)$hist_where.=" AND (p.name LIKE '%$hsearch%' OR s.name LIKE '%$hsearch%')";
$history=getRows($conn,"SELECT a.*,p.name AS pet_name,s.name AS service_name FROM appointments a LEFT JOIN pets p ON a.pet_id=p.id LEFT JOIN services s ON a.service_id=s.id WHERE $hist_where ORDER BY a.appointment_date DESC");

$billing=null;
if(isset($_GET['billing'])&&is_numeric($_GET['billing'])){
    $bid=(int)$_GET['billing'];
    $billing=getRow($conn,"SELECT t.*,u.name AS owner_name,p.name AS pet_name,p.breed,p.species FROM transactions t LEFT JOIN users u ON t.user_id=u.id LEFT JOIN pets p ON t.pet_id=p.id WHERE t.appointment_id=$bid AND t.user_id=$user_id");
    if($billing){$tid=(int)$billing['id'];$billing['items']=getRows($conn,"SELECT * FROM transaction_items WHERE transaction_id=$tid");}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .view-toggle { display:flex;gap:0;margin-bottom:20px;background:rgba(255,255,255,0.5);border-radius:var(--radius);padding:4px;width:fit-content; }
        .view-btn { padding:8px 22px;border-radius:var(--radius-sm);font-weight:700;font-size:13px;cursor:pointer;border:none;background:transparent;color:var(--text-mid);transition:var(--transition); }
        .view-btn.active { background:var(--blue-header);color:#fff;box-shadow:var(--shadow); }
        .calendar-wrap { background:rgba(255,255,255,0.9);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:24px; }
        .cal-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px; }
        .cal-header h3 { font-family:var(--font-head);font-size:17px;font-weight:700; }
        .cal-nav { display:flex;align-items:center;gap:10px; }
        .cal-nav-btn { width:32px;height:32px;border-radius:50%;border:1.5px solid var(--border);background:#fff;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:var(--transition);text-decoration:none;color:var(--text-dark); }
        .cal-nav-btn:hover { background:var(--teal);color:#fff;border-color:var(--teal); }
        .cal-month-label { font-family:var(--font-head);font-weight:700;color:var(--teal-dark);font-size:15px;min-width:140px;text-align:center; }
        .cal-legend { display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px; }
        .cal-legend-item { display:flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:var(--text-mid); }
        .cal-legend-dot { width:10px;height:10px;border-radius:2px;flex-shrink:0; }
        .cal-grid { display:grid;grid-template-columns:repeat(7,1fr);gap:2px; }
        .cal-day-header { text-align:center;font-size:11px;font-weight:800;color:var(--text-light);text-transform:uppercase;padding:6px 0;letter-spacing:0.3px; }
        .cal-cell { min-height:80px;background:#fafffe;border:1px solid #e8f5f5;border-radius:4px;padding:4px;overflow:hidden; }
        .cal-cell.today { background:rgba(0,188,212,0.08);border-color:var(--teal); }
        .cal-cell.empty { background:transparent;border-color:transparent; }
        .cal-day-num { font-size:12px;font-weight:700;color:var(--text-mid);margin-bottom:3px;text-align:right;padding-right:2px; }
        .cal-cell.today .cal-day-num { color:var(--teal-dark); }
        .cal-event { font-size:10px;font-weight:700;color:#fff;padding:2px 5px;border-radius:3px;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;cursor:pointer;transition:opacity 0.15s; }
        .cal-event:hover { opacity:0.8; }
        .cal-more { font-size:10px;color:var(--text-light);font-weight:600;text-align:center; }
        .appt-section-grid { display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px; }
        .appt-box { background:rgba(255,255,255,0.88);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow); }
        .appt-box-title { font-weight:800;font-size:13px;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:14px; }
        .appt-row { background:rgba(255,255,255,0.7);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:10px;border-left:3px solid var(--teal); }
        .appt-row-top { display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px; }
        .appt-row-label { font-weight:800;font-size:14px;color:var(--text-dark); }
        .appt-row-meta { font-size:12px;color:var(--text-light);display:flex;flex-direction:column;gap:3px; }
        .appt-row-meta span { display:flex;align-items:center;gap:6px; }
        .add-appt-card { border:2px dashed var(--border);border-radius:var(--radius);padding:24px;text-align:center;cursor:pointer;transition:var(--transition);display:flex;flex-direction:column;align-items:center;gap:8px;background:rgba(255,255,255,0.5); }
        .add-appt-card:hover { border-color:var(--teal);background:rgba(0,188,212,0.05); }
        .add-appt-card .add-icon { font-size:32px;color:var(--teal); }
        .add-appt-card .add-label { font-weight:800;font-size:15px; }
        .add-appt-card .add-sub { font-size:12px;color:var(--text-light); }
        .history-section { background:rgba(255,255,255,0.88);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow); }
        .history-filters { display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:16px; }
        .date-range { display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600; }
        .date-range input[type="date"] { padding:6px 10px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:12px;background:#fff;outline:none; }
        .policy-list { font-size:13px;color:var(--text-mid);line-height:1.9; }
        .policy-list h4 { font-weight:800;color:var(--text-dark);margin:12px 0 4px; }
        .policy-list ul { list-style:disc;padding-left:18px; }
        .policy-list ul li { margin-bottom:6px; }
        .pet-entry { background:#f0fffe;border:1px solid var(--teal-light);border-radius:var(--radius-sm);padding:12px;margin-bottom:10px; }
        .pet-entry-header { display:flex;justify-content:space-between;font-weight:700;font-size:13px;margin-bottom:8px;color:var(--teal-dark); }
        .billing-table { width:100%;font-size:13px;margin-top:8px; }
        .billing-table td { padding:6px 0; }
        .billing-table td:last-child { text-align:right;font-weight:700; }
        .billing-total { border-top:2px solid var(--border);font-weight:800;font-size:15px;padding-top:8px;margin-top:4px; }
        .policy-agree-box { background:#f0fffe;border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:14px;border:1px solid var(--teal-light); }
        .policy-agree-box label { display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:13px;color:var(--text-mid); }
        .policy-agree-box input[type="checkbox"] { margin-top:2px;flex-shrink:0;accent-color:var(--teal);width:16px;height:16px;cursor:pointer; }
        @media(max-width:700px){ .appt-section-grid{grid-template-columns:1fr;} .cal-cell{min-height:56px;} }
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
                Appointment
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔</a>
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>
        <div class="page-body">
            <?php if($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

            <?php if(isset($_GET['rated'])): ?>
                <div class="alert alert-success">⭐ Thank you for your rating!</div>
            <?php endif; ?>

            <?php if($unrated_appt): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(function() {
                            document.getElementById('ratingModal').classList.add('open');
                            document.getElementById('rating_appt_id').value = '<?= $unrated_appt['id'] ?>';
                            document.getElementById('ratingApptInfo').textContent =
                                '<?= addslashes(formatDate($unrated_appt['appointment_date']) . ' — ' . ($unrated_appt['service_name'] ?? 'Clinic Visit')) ?>';
                        }, 800);
                    });
                </script>
            <?php endif; ?>
            <?php if($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <?php
// Pre-load billing data for JS receipt (no page reload needed)
$preloaded_bills = [];
foreach($history as $h) {
    $hbill = getRow($conn, "SELECT t.*,u.name AS owner_name,u.address,u.contact_no,p.name AS pet_name,p.breed,p.species,s.name AS svc_name FROM transactions t LEFT JOIN users u ON t.user_id=u.id LEFT JOIN pets p ON t.pet_id=p.id LEFT JOIN appointments a ON t.appointment_id=a.id LEFT JOIN services s ON a.service_id=s.id WHERE t.appointment_id={$h['id']} AND t.user_id=$user_id LIMIT 1");
    if($hbill) {
        $hbill_items = getRows($conn,"SELECT * FROM transaction_items WHERE transaction_id={$hbill['id']} ORDER BY id ASC");
        $preloaded_bills[$h['id']] = [
            'ref'      => 'TXN-'.str_pad($hbill['id'],5,'0',STR_PAD_LEFT),
            'date'     => formatDate($hbill['transaction_date']),
            'type'     => ucfirst(str_replace('_',' ',$h['appointment_type'])),
            'status'   => $hbill['status'] ?? 'paid',
            'owner'    => $hbill['owner_name'] ?? '—',
            'address'  => $hbill['address'] ?? '',
            'contact'  => $hbill['contact_no'] ?? '',
            'pet'      => $hbill['pet_name'] ?? '—',
            'breed'    => ($hbill['species']??'').'/'.($hbill['breed']??'—'),
            'service'  => $hbill['svc_name'] ?? ($h['service_name'] ?? '—'),
            'appt_date'=> formatDate($h['appointment_date']),
            'appt_time'=> formatTime($h['appointment_time']),
            'items'    => array_map(fn($i) => ['name'=>$i['item_name'],'price'=>$i['price']], $hbill_items),
            'total'    => $hbill['total_amount'],
        ];
    }
}
?>

            <div class="view-toggle">
                <button class="view-btn active" onclick="showView('calendar',this)">📅 Calendar View</button>
                <button class="view-btn" onclick="showView('list',this)">📋 Appointments List</button>
            </div>

            <!-- CALENDAR VIEW -->
            <div id="view-calendar">
                <div class="calendar-wrap">
                    <div class="cal-header">
                        <h3>🐾 My Appointment Schedule</h3>
                        <div class="cal-nav">
                            <a href="?month=<?= $month-1 ?>&year=<?= $year ?>" class="cal-nav-btn">‹</a>
                            <div class="cal-month-label"><?= $month_names[$month] ?> <?= $year ?></div>
                            <a href="?month=<?= $month+1 ?>&year=<?= $year ?>" class="cal-nav-btn">›</a>
                        </div>
                    </div>
                    <div class="cal-legend">
                        <div style="width:100%;font-size:11px;font-weight:800;color:var(--teal-dark);
                                    text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                            🏥 Clinic
                        </div>
                        <?php foreach($svc_colors as $svc=>$color): if($svc==='Home Service') continue; ?>
                            <div class="cal-legend-item">
                                <div class="cal-legend-dot" style="background:<?= $color ?>"></div>
                                <?= htmlspecialchars($svc) ?>
                            </div>
                        <?php endforeach; ?>
                        <div style="width:100%;font-size:11px;font-weight:800;color:#c2185b;
                                    text-transform:uppercase;letter-spacing:0.5px;margin:8px 0 4px;">
                            🏠 Home Service
                        </div>
                        <?php foreach($home_svc_colors as $svc=>$color): if($svc==='Home Service') continue; ?>
                            <div class="cal-legend-item">
                                <div class="cal-legend-dot" style="background:<?= $color ?>"></div>
                                <?= htmlspecialchars($svc) ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="cal-legend-item">
                            <div class="cal-legend-dot"></div>
                        </div>
                    </div>
                    <div class="cal-grid">
                        <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dh): ?>
                            <div class="cal-day-header"><?= $dh ?></div>
                        <?php endforeach; ?>
                        <?php for($e=0;$e<$first_dow;$e++): ?><div class="cal-cell empty"></div><?php endfor; ?>
                        <?php
                        $td=(int)date('j');$tm=(int)date('m');$ty=(int)date('Y');
                        for($d=1;$d<=$days_in_month;$d++):
                            $is_today=($d===$td&&$month===$tm&&$year===$ty);
                            $events=$cal_by_day[$d]??[];$show_max=5;
                        ?>
                            <div class="cal-cell <?= $is_today?'today':'' ?>">
                                <div class="cal-day-num"><?= $d ?></div>
                                <?php foreach(array_slice($events,0,$show_max) as $ev):
                                    $is_home   = !empty($ev['is_home']);
                                    $color_map = $is_home ? $home_svc_colors : $svc_colors;
                                    $svc_key   = $ev['svc_name'] ?? '';
                                    $ec        = $color_map[$svc_key] ?? ($is_home ? '#8b2f68' : '#607d8b');
                                    $pet_label = $ev['pet_name'] ?: ($is_home ? 'Home' : '?');
                                    $svc_label = $ev['svc_name'] ?: ($is_home ? 'Home Service' : 'Appointment');
                                    $icon      = $is_home ? '🏠' : '🏥';
                                ?>
                                    <div class="cal-event" style="background:<?= $ec ?>;"
                                         onclick="openApptModal(<?= htmlspecialchars(json_encode($ev),ENT_QUOTES) ?>)"
                                         title="<?= htmlspecialchars($icon.' '.$pet_label.' — '.$svc_label) ?>">
                                        <?= $icon ?> <?= htmlspecialchars($svc_label) ?> · <?= htmlspecialchars($pet_label) ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if(count($events)>$show_max): ?><div class="cal-more">+<?= count($events)-$show_max ?> more</div><?php endif; ?>
                            </div>
                        <?php endfor; ?>
                        <?php $trailing=(7-(($first_dow+$days_in_month)%7))%7;for($t=0;$t<$trailing;$t++): ?>
                            <div class="cal-cell empty"></div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div style="display:flex;gap:16px;margin-bottom:24px;">
                    <div class="add-appt-card" style="flex:1;"
                         onclick="document.getElementById('clinicBookModal').classList.add('open')">
                        <div class="add-icon">⊕</div>
                        <div class="add-label">Add Clinic Appointment</div>
                        <div class="add-sub">Tap to schedule a new clinic visit!</div>
                    </div>
                    <div class="add-appt-card" style="flex:1;"
                         onclick="document.getElementById('homeBookModal').classList.add('open')">
                        <div class="add-icon">⊕</div>
                        <div class="add-label">Add Home Service</div>
                        <div class="add-sub">Tap to request a home visit!</div>
                    </div>
                </div>
            </div><!-- /view-calendar -->

            <!-- LIST VIEW -->
            <div id="view-list" style="display:none;">
                <?php $total_upcoming=count($upcoming_clinic)+count($upcoming_home); ?>
                <?php if($total_upcoming===0&&empty($history)): ?>
                <div class="card text-center" style="padding:32px;margin-bottom:20px;">
                    <div style="font-size:48px;margin-bottom:12px;">📅</div>
                    <h3 style="font-family:var(--font-head);font-weight:800;font-size:18px;margin-bottom:6px;">No Appointments Yet!</h3>
                    <p style="font-size:13px;color:var(--text-light);">You don't have any appointment scheduled.</p>
                </div>
                <?php endif; ?>
                <div class="appt-section-grid">
                    <div class="appt-box">
                        <div class="appt-box-title">📅 Clinic Appointment</div>
                        <?php if(!empty($upcoming_clinic)): ?>
                            <?php foreach($upcoming_clinic as $ap): ?>
                                <div class="appt-row">
                                    <div class="appt-row-top">
                                        <div>
                                            <div class="appt-row-label"><?= htmlspecialchars($ap['pet_name']??'—') ?></div>
                                            <div style="font-size:12px;color:var(--teal-dark);font-weight:600;"><?= htmlspecialchars($ap['service_name']??'—') ?></div>
                                        </div>
                                        <?= statusBadge($ap['status']) ?>
                                    </div>
                                    <div class="appt-row-meta">
                                        <span>📅 <?= formatDate($ap['appointment_date']) ?></span>
                                        <span>🕐 <?= formatTime($ap['appointment_time']) ?></span>
                                    </div>
                                    <div style="margin-top:8px;">
                                        <button type="button" class="btn btn-red btn-sm"
                                                onclick="openCancelModal(<?= $ap['id'] ?>, '<?= addslashes(htmlspecialchars($ap['pet_name']??'')) ?>', '<?= addslashes(formatDate($ap['appointment_date'])) ?>')">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="font-size:13px;color:var(--text-light);margin-bottom:14px;">Whooh!! No appointment Schedule!</p>
                        <?php endif; ?>
                        <div class="add-appt-card" onclick="document.getElementById('clinicBookModal').classList.add('open')">
                            <div class="add-icon">⊕</div><div class="add-label">Add Appointment</div>
                            <div class="add-sub">Tap to schedule new appointment!</div>
                        </div>
                    </div>
                    <div class="appt-box">
                        <div class="appt-box-title">🏠 Home Service</div>
                        <?php if(!empty($upcoming_home)): ?>
                            <?php foreach($upcoming_home as $ha): ?>
                                <div class="appt-row" style="border-left-color:var(--yellow-dark);">
                                    <div class="appt-row-top">
                                        <div>
                                            <div class="appt-row-label"><?= $ha['pet_count'] ?> Pet(s)</div>
                                            <div style="font-size:12px;color:var(--teal-dark);font-weight:600;">Home Service</div>
                                        </div>
                                        <?= statusBadge($ha['status']) ?>
                                    </div>
                                    <div class="appt-row-meta">
                                        <span>📅 <?= formatDate($ha['appointment_date']) ?></span>
                                        <span>🕐 <?= formatTime($ha['appointment_time']) ?></span>
                                    </div>
                                    <div style="margin-top:8px;">
                                        <button type="button" class="btn btn-red btn-sm"
                                                onclick="openCancelModal(<?= $ha['id'] ?>, '<?= $ha['pet_count'] ?> Pet(s)', '<?= addslashes(formatDate($ha['appointment_date'])) ?>')">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="font-size:13px;color:var(--text-light);margin-bottom:14px;">Whooh!! No appointment Schedule!</p>
                        <?php endif; ?>
                        <div class="add-appt-card" onclick="document.getElementById('homeBookModal').classList.add('open')">
                            <div class="add-icon">⊕</div><div class="add-label">Add Appointment</div>
                            <div class="add-sub">Tap to schedule new appointment!</div>
                        </div>
                    </div>
                </div>

                <div class="history-section">
                    <h3 style="font-family:var(--font-head);font-weight:800;font-size:18px;text-align:center;margin-bottom:20px;">History</h3>
                    <?php if(empty($history)): ?>
                        <div class="empty-state"><div class="empty-icon">📭</div><h3>No History Records</h3><p>You have no past appointments yet.</p></div>
                    <?php else: ?>
                        <form method="GET" class="history-filters">
                            <input type="hidden" name="view" value="list">
                            <div class="search-box" style="min-width:180px;">
                                <span>🔍</span>
                                <input type="text" name="hsearch" placeholder="Search appointments..."
                                       value="<?= htmlspecialchars($hsearch) ?>">
                            </div>
                            <div class="date-range">
                                <span>From:</span><input type="date" name="from" value="<?= $from ?>">
                                <span>To:</span><input type="date" name="to" value="<?= $to ?>">
                            </div>
                            <button type="submit" class="btn btn-teal btn-sm">Filter</button>
                        </form>
                        <p style="font-weight:700;font-size:13px;margin-bottom:10px;">History Appointment Records</p>
                        <div class="table-wrapper">
                            <table id="historyTable">
                                <thead><tr><th>Name</th><th>Date</th><th>Time</th><th>Service</th><th>Type</th><th>Billing</th><th>Status</th><th>Rating</th></tr></thead>
                                <tbody>
                                    <?php foreach($history as $h): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($h['pet_name']??'—') ?></strong></td>
                                            <td><?= formatDate($h['appointment_date']) ?></td>
                                            <td><?= formatTime($h['appointment_time']) ?></td>
                                            <td><?= htmlspecialchars($h['service_name']??'—') ?></td>
                                            <td><span style="font-size:11px;background:var(--teal-light);color:var(--teal-dark);padding:2px 8px;border-radius:20px;font-weight:700;"><?= ucfirst(str_replace('_',' ',$h['appointment_type'])) ?></span></td>
                                            <td>
                                               <?php if(isset($preloaded_bills[$h['id']])): ?>
                                                    <button class="btn btn-teal btn-sm" onclick='openApptReceipt(<?= htmlspecialchars(json_encode($preloaded_bills[$h['id']]),ENT_QUOTES) ?>)'>🧾 View Receipt</button>
                                                <?php else: ?><span style="color:var(--text-light);font-size:12px;">—</span><?php endif; ?>
                                            </td>
                                            <td><?= statusBadge($h['status']) ?></td>
                                            <td>
                                                <?php if($h['status']==='completed'):
                                                    $user_rating = getRow($conn, "SELECT stars FROM ratings WHERE user_id=$user_id AND appointment_id={$h['id']}");
                                                ?>
                                                    <?php if($user_rating): ?>
                                                        <span style="color:#f59e0b;font-size:14px;letter-spacing:1px;">
                                                            <?= str_repeat('★',$user_rating['stars']) ?><?= str_repeat('☆',5-$user_rating['stars']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <button class="btn btn-teal btn-sm"
                                                                onclick="openRating(<?= $h['id'] ?>, '<?= addslashes(formatDate($h['appointment_date']).' — '.($h['service_name']??'Clinic Visit')) ?>')">
                                                            ⭐ Rate
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color:var(--text-light);font-size:12px;">—</span>
                                                <?php endif; ?>
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

<!-- APPOINTMENT DETAIL MODAL -->
<div class="modal-overlay" id="apptDetailModal">
    <div class="modal" style="max-width:400px;">
        <button class="modal-close" onclick="document.getElementById('apptDetailModal').classList.remove('open')">×</button>
        <h3 class="modal-title">Appointment Details</h3>
        <div id="apptDetailContent" style="font-size:13px;color:var(--text-mid);line-height:2;"></div>
        <div class="modal-actions">
            <button class="btn btn-gray btn-sm" onclick="document.getElementById('apptDetailModal').classList.remove('open')">Close</button>
        </div>
    </div>
</div>

<!-- CLINIC BOOKING MODAL -->
<div class="modal-overlay" id="clinicBookModal">
    <div class="modal">
        <button class="modal-close" onclick="document.getElementById('clinicBookModal').classList.remove('open')">×</button>
        <h3 class="modal-title">📋 Fill Out Appointment Details</h3>
        <form method="POST">
            <input type="hidden" name="action" value="book_clinic">
            <div class="form-group">
                <label>Name of Pet *</label>
                <select name="pet_id" required>
                    <option value="">— Select Pet —</option>
                    <?php foreach($my_pets as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= ucfirst($p['species']) ?>/<?= htmlspecialchars($p['breed']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?php if(empty($my_pets)): ?><small style="color:#ef4444;">No pets found. <a href="pet_profile.php" style="color:var(--teal-dark);">Add a pet first →</a></small><?php endif; ?>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="appt_date" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Time *</label>
                    <input type="time" name="appt_time" required>
                </div>
            </div>
            <div class="form-group">
                <label>Service *</label>
                <select name="service_id" required>
                    <option value="">— Select Service —</option>
                    <?php foreach($services as $sv): if($sv['name']==='Home Service') continue; ?>
                        <option value="<?= $sv['id'] ?>"><?= htmlspecialchars($sv['name']) ?> (₱<?= number_format($sv['price_min'],0) ?><?= $sv['price_max']>$sv['price_min']?' – ₱'.number_format($sv['price_max'],0):'' ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Pet Owner Name</label>
                <input type="text" name="owner_name" value="<?= htmlspecialchars($_SESSION['user_name']) ?>" readonly style="background:#f0f0f0;">
            </div>
            <div class="policy-agree-box">
                <label>
                    <input type="checkbox" name="agreed" value="1" required>
                    <span>
                        I have read and agree to the
                        <a href="#"
                           onclick="event.preventDefault();document.getElementById('clinicBookModal').classList.remove('open');document.getElementById('clinicPoliciesModal').classList.add('open');"
                           style="color:var(--teal-dark);font-weight:700;text-decoration:underline;">
                            Clinic Policies
                        </a>
                        — I will arrive <strong>10 minutes early</strong> and cancel <strong>24 hours in advance</strong> if needed.
                    </span>
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray" onclick="document.getElementById('clinicBookModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">BOOK APPOINTMENT</button>
            </div>
        </form>
    </div>
</div>

<!-- CLINIC POLICIES MODAL -->
<div class="modal-overlay" id="clinicPoliciesModal">
    <div class="modal">
        <button class="modal-close"
                onclick="document.getElementById('clinicPoliciesModal').classList.remove('open');document.getElementById('clinicBookModal').classList.add('open');">×</button>
        <h3 class="modal-title">Ligao Petcare &amp; Veterinary Clinic Policies</h3>
        <div class="policy-list">
            <ul>
                <li><strong>Please arrive at least 10 minutes</strong> before your scheduled time.</li>
                <li><strong>Cancellations should be made at least 24 hours</strong> in advance.</li>
            </ul>
        </div>
        <div class="modal-actions">
            <button class="btn btn-teal"
                    onclick="document.getElementById('clinicPoliciesModal').classList.remove('open');document.getElementById('clinicBookModal').classList.add('open');">
                Back to Booking
            </button>
        </div>
    </div>
</div>

<!-- HOME SERVICE BOOKING MODAL -->
<div class="modal-overlay" id="homeBookModal">
    <div class="modal" style="max-width:560px;">
        <button class="modal-close" onclick="document.getElementById('homeBookModal').classList.remove('open')">×</button>
        <h3 class="modal-title">🏠 Fill Out Home Service Details</h3>
        <form method="POST" id="homeForm">
            <input type="hidden" name="action" value="book_home">
            <div class="form-group">
                <label>Pet Owner Name *</label>
                <input type="text" name="owner_name" value="<?= htmlspecialchars($_SESSION['user_name']) ?>" readonly style="background:#f0f0f0;">
            </div>
            <div class="form-group">
                <label>Address *</label>
                <input type="text" name="address" placeholder="Enter your address" required>
            </div>
            <div class="form-group">
                <label>Contact *</label>
                <input type="text" name="contact" placeholder="Enter contact number" required>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Date *</label><input type="date" name="appt_date" min="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>Time *</label><input type="time" name="appt_time" required></div>
            </div>
            <p style="font-weight:800;font-size:14px;margin:14px 0 10px;color:var(--text-dark);">Pet Details</p>
            <div id="homePetsContainer">
                <div class="pet-entry" id="pet-entry-0">
                    <div class="pet-entry-header"><span>🐾 Pet 1</span></div>
                    <div class="form-group" style="margin-bottom:8px;">
                        <label style="font-size:12px;">Pet Name</label>
                        <input type="text" name="home_pets[0][name]" placeholder="Enter pet's name">
                    </div>
                    <div class="form-row" style="gap:8px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;">Species/Breed</label>
                            <input type="text" name="home_pets[0][species]" placeholder="e.g. Dog/Aspin">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:12px;">Type of Service</label>
                            <select name="home_pets[0][service_id]">
                                <option value="">Select service</option>
                                <?php foreach($services as $sv): if(strtolower($sv['name'])==='home service') continue; ?>
                                    <option value="<?= $sv['id'] ?>"><?= htmlspecialchars($sv['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-gray btn-sm" style="margin-bottom:14px;" onclick="addHomePet()">+ Add Pet</button>
            <div class="policy-agree-box">
                <label>
                    <input type="checkbox" name="agreed_home" value="1" required>
                    <span>
                        I have read and agree to the
                        <a href="#"
                           onclick="event.preventDefault();document.getElementById('homeBookModal').classList.remove('open');document.getElementById('homePoliciesModal').classList.add('open');"
                           style="color:var(--teal-dark);font-weight:700;text-decoration:underline;">
                            Home Service Policies
                        </a>
                        — Available within Ligao City, Oas, and Polangui Albay only.
                    </span>
                </label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray" onclick="document.getElementById('homeBookModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal" id="homeSubmitBtn">PROCEED TO BOOKING</button>
            </div>
        </form>
    </div>
</div>

<!-- HOME POLICIES MODAL -->
<div class="modal-overlay" id="homePoliciesModal">
    <div class="modal">
        <button class="modal-close"
                onclick="document.getElementById('homePoliciesModal').classList.remove('open');document.getElementById('homeBookModal').classList.add('open');">×</button>
        <h3 class="modal-title">Ligao Petcare &amp; Veterinary Home Service Policies</h3>
        <div class="policy-list">
            <h4>Service Availability:</h4><ul><li>Available within Ligao City, Oas, and Polangui Albay only.</li></ul>
            <h4>Cancellation Policy:</h4><ul><li>Changes or cancellations must be made <strong>24 hours in advance</strong>.</li></ul>
            <h4>Travel Surcharge:</h4><ul><li>A nominal travel fee applies based on distance.</li></ul>
            <h4>Pet Readiness:</h4><ul><li>Please ensure your pet is secure and readily available upon arrival.</li></ul>
            <h4>Safety Protocols:</h4><ul><li>Our vet will wear PPE. Please maintain cleanliness in the surroundings.</li></ul>
        </div>
        <div class="modal-actions">
            <button class="btn btn-teal"
                    onclick="document.getElementById('homePoliciesModal').classList.remove('open');document.getElementById('homeBookModal').classList.add('open');">
                Back to Booking
            </button>
        </div>
    </div>
</div>

<!-- Cancel Appointment Modal -->
<div id="cancelModal"
     style="display:none;position:fixed;inset:0;z-index:9999;
            background:rgba(0,0,0,0.55);backdrop-filter:blur(3px);
            align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px 28px;
                max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.25);
                animation:cancelSlideUp 0.25s ease;text-align:center;">
        <div style="width:64px;height:64px;border-radius:50%;
                    background:#fff5f5;border:2px solid #fee2e2;
                    display:flex;align-items:center;justify-content:center;
                    font-size:28px;margin:0 auto 16px;">🗓️</div>
        <h3 style="font-family:var(--font-head);font-size:18px;font-weight:800;
                   color:#1a1a2e;margin-bottom:8px;">Cancel Appointment?</h3>
        <p id="cancelModalInfo"
           style="font-size:13px;color:#6b7280;margin-bottom:8px;line-height:1.6;"></p>
        <p style="font-size:12px;color:#ef4444;font-weight:700;margin-bottom:20px;
                  background:#fff5f5;border:1px solid #fee2e2;
                  border-radius:8px;padding:10px 14px;">
            ⚠️ This action cannot be undone. The appointment will be permanently cancelled.
        </p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="closeCancelModal()"
                    style="padding:10px 24px;border-radius:8px;border:1.5px solid #d1d5db;
                           background:#fff;color:#374151;font-weight:700;font-size:13px;
                           cursor:pointer;font-family:var(--font-main);"
                    onmouseover="this.style.background='#f9fafb'"
                    onmouseout="this.style.background='#fff'">
                Keep Appointment
            </button>
            <a id="cancelConfirmBtn" href="#"
               style="padding:10px 24px;border-radius:8px;border:none;
                      background:linear-gradient(135deg,#ef4444,#dc2626);
                      color:#fff;font-weight:700;font-size:13px;cursor:pointer;
                      box-shadow:0 4px 12px rgba(239,68,68,0.3);
                      text-decoration:none;display:inline-flex;align-items:center;gap:6px;
                      font-family:var(--font-main);"
               onmouseover="this.style.transform='translateY(-1px)'"
               onmouseout="this.style.transform='translateY(0)'">
                ✕ Yes, Cancel
            </a>
        </div>
    </div>
</div>
<style>
@keyframes cancelSlideUp {
    from { opacity:0; transform:translateY(20px); }
    to   { opacity:1; transform:translateY(0); }
}
#cancelModal.show { display:flex !important; }
</style>

<!-- Rating Modal -->
<div class="modal-overlay" id="ratingModal">
    <div class="modal" style="max-width:380px;text-align:center;">
        <button class="modal-close"
                onclick="document.getElementById('ratingModal').classList.remove('open')">×</button>
        <div style="font-size:48px;margin-bottom:8px;">⭐</div>
        <h3 class="modal-title" style="margin-bottom:6px;">Rate Your Visit</h3>
        <p id="ratingApptInfo" style="font-size:12px;color:var(--text-light);margin-bottom:20px;"></p>

        <form method="POST" id="ratingForm">
            <input type="hidden" name="action" value="submit_rating">
            <input type="hidden" name="appointment_id" id="rating_appt_id" value="">
            <input type="hidden" name="stars" id="rating_stars_input" value="5">

            <div style="display:flex;justify-content:center;gap:8px;margin-bottom:24px;" id="starContainer">
                <?php for($s=1;$s<=5;$s++): ?>
                    <span class="rating-star" data-val="<?= $s ?>"
                          style="font-size:36px;cursor:pointer;color:#d1d5db;transition:color .15s;"
                          onclick="selectStar(<?= $s ?>)"
                          onmouseover="hoverStar(<?= $s ?>)"
                          onmouseout="resetStars()">★</span>
                <?php endfor; ?>
            </div>

            <p id="ratingLabel" style="font-size:13px;font-weight:700;color:var(--teal-dark);margin-bottom:20px;min-height:20px;"></p>

            <div class="modal-actions" style="justify-content:center;">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('ratingModal').classList.remove('open')">
                    Maybe Later
                </button>
                <button type="submit" class="btn btn-teal" id="submitRatingBtn" disabled
                        style="opacity:.5;">
                    ✅ Submit Rating
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══ RECEIPT MODAL (matches transactions.php style) ══════════ -->
<div class="receipt-overlay" id="apptReceiptOverlay" onclick="if(event.target===this)closeApptReceipt()" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;width:380px;max-width:96vw;max-height:90vh;overflow-y:auto;border-radius:12px;box-shadow:0 24px 64px rgba(0,0,0,0.3);font-family:'Courier New',monospace;">

        <!-- Action bar -->
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #eee;">
            <span style="font-weight:700;font-size:13px;font-family:sans-serif;">Digital Receipt</span>
            <button onclick="closeApptReceipt()" style="padding:6px 12px;background:#f3f4f6;border:none;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;">✕ Close</button>
        </div>

        <!-- Receipt body -->
        <div style="padding:24px;">

            <!-- Clinic header -->
            <div style="text-align:center;border-bottom:2px dashed #ccc;padding-bottom:16px;margin-bottom:16px;">
                <div style="font-size:22px;margin-bottom:4px;">🐾</div>
                <div style="font-size:15px;font-weight:900;letter-spacing:1px;">LIGAO PETCARE</div>
                <div style="font-size:11px;color:#555;">&amp; Veterinary Clinic</div>
                <div style="font-size:10px;color:#888;margin-top:4px;">Ligao City, Albay</div>
            </div>

            <!-- Meta -->
            <div style="font-size:11px;margin-bottom:14px;line-height:1.8;">
                <div style="display:flex;justify-content:space-between;"><span style="color:#888;">Receipt No.:</span><strong id="ar_ref"></strong></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#888;">Date:</span><span id="ar_date"></span></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#888;">Type:</span><span id="ar_type"></span></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#888;">Status:</span><strong id="ar_status"></strong></div>
            </div>

            <!-- Client -->
            <div style="border-top:1px dashed #ccc;border-bottom:1px dashed #ccc;padding:10px 0;margin-bottom:14px;font-size:11px;line-height:1.8;">
                <div style="font-weight:800;margin-bottom:4px;font-size:12px;">CLIENT</div>
                <div><span style="color:#888;">Name:</span> <span id="ar_owner"></span></div>
                <div id="ar_address_row"><span style="color:#888;">Address:</span> <span id="ar_address"></span></div>
                <div id="ar_contact_row"><span style="color:#888;">Contact:</span> <span id="ar_contact"></span></div>
                <div id="ar_pet_row"><span style="color:#888;">Pet:</span> <span id="ar_pet"></span> <span id="ar_breed" style="color:#888;"></span></div>
                <div id="ar_service_row"><span style="color:#888;">Service:</span> <span id="ar_service"></span></div>
                <div id="ar_appt_row"><span style="color:#888;">Appt. Date:</span> <span id="ar_appt_date"></span> <span id="ar_appt_time" style="font-weight:700;color:#007b83;margin-left:4px;"></span></div>
            </div>

            <!-- Items -->
            <div style="font-size:11px;margin-bottom:14px;">
                <div style="font-weight:800;margin-bottom:8px;font-size:12px;">ITEMS</div>
                <div id="ar_items_list"></div>
                <div style="border-top:1px dashed #ccc;margin-top:10px;padding-top:8px;display:flex;justify-content:space-between;font-weight:800;font-size:13px;">
                    <span>TOTAL</span><span id="ar_total" style="color:#007b83;"></span>
                </div>
            </div>

            <!-- Footer -->
            <div style="text-align:center;font-size:10px;color:#aaa;border-top:2px dashed #ccc;padding-top:14px;line-height:1.7;">
                <div>Payment is collected <strong>in person</strong> at the clinic.</div>
                <div>Thank you for trusting Ligao Petcare! 🐾</div>
                <div style="margin-top:6px;font-size:9px;">— This is a digital copy of your receipt —</div>
            </div>

        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// ── Rating logic ─────────────────────────────────────────────
const starLabels = ['','😞 Poor','😕 Fair','😊 Good','😃 Great','🤩 Excellent!'];
let selectedStars = 0;

function openRating(apptId, info) {
    selectedStars = 0;
    document.getElementById('rating_appt_id').value = apptId;
    document.getElementById('ratingApptInfo').textContent = info;
    document.getElementById('rating_stars_input').value = '';
    document.getElementById('submitRatingBtn').disabled = true;
    document.getElementById('submitRatingBtn').style.opacity = '.5';
    document.getElementById('ratingLabel').textContent = '';
    resetStars();
    document.getElementById('ratingModal').classList.add('open');
}

function hoverStar(val) {
    document.querySelectorAll('.rating-star').forEach((s, i) => {
        s.style.color = i < val ? '#f59e0b' : '#d1d5db';
    });
    document.getElementById('ratingLabel').textContent = starLabels[val] || '';
}

function resetStars() {
    document.querySelectorAll('.rating-star').forEach((s, i) => {
        s.style.color = i < selectedStars ? '#f59e0b' : '#d1d5db';
    });
    document.getElementById('ratingLabel').textContent = starLabels[selectedStars] || '';
}

function selectStar(val) {
    selectedStars = val;
    document.getElementById('rating_stars_input').value = val;
    document.getElementById('submitRatingBtn').disabled = false;
    document.getElementById('submitRatingBtn').style.opacity = '1';
    resetStars();
}

function openCancelModal(id, petName, date) {
    document.getElementById('cancelModalInfo').innerHTML =
        'You are about to cancel the appointment for <strong>' + petName + '</strong> on <strong>' + date + '</strong>.';
    document.getElementById('cancelConfirmBtn').href = 'appointments.php?cancel=' + id;
    document.getElementById('cancelModal').classList.add('show');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('show');
}

document.addEventListener('DOMContentLoaded', function() {
    var cm = document.getElementById('cancelModal');
    if (cm) cm.addEventListener('click', function(e) { if (e.target === cm) closeCancelModal(); });
});

function showView(view,btn){
    document.getElementById('view-calendar').style.display=view==='calendar'?'':'none';
    document.getElementById('view-list').style.display=view==='list'?'':'none';
    document.querySelectorAll('.view-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
}
const urlParams=new URLSearchParams(window.location.search);
if(urlParams.get('view')==='list'||urlParams.has('hsearch')||urlParams.has('from')||urlParams.has('to')){
    showView('list',document.querySelectorAll('.view-btn')[1]);
}
document.querySelectorAll('.modal-overlay').forEach(overlay=>{
    overlay.addEventListener('click',function(e){if(e.target===overlay)overlay.classList.remove('open');});
});
function openApptModal(ev){
    const sc={pending:'#f59e0b',confirmed:'#3b82f6',completed:'#10b981',cancelled:'#ef4444'}[ev.status]||'#888';
    const isHome = ev.appointment_type === 'home_service';
    const type   = isHome ? '🏠 Home Service' : '🏥 Clinic';

    let serviceRow = '';
    let petRow     = '';

    if (isHome && ev.home_pets && ev.home_pets.length > 0) {
        let petsHtml = ev.home_pets.map(hp =>
            `<div style="padding:4px 0;border-bottom:1px solid #f0f0f0;font-size:12px;">
                🐾 <strong>${escHtml(hp.pet_name)}</strong>
                <span style="color:#888;margin-left:4px;">${escHtml(hp.species||'')}</span>
                ${hp.service_name
                    ? `— <span style="color:var(--teal-dark);font-weight:700;">${escHtml(hp.service_name)}</span>`
                    : ''}
             </div>`
        ).join('');
        petRow     = `<tr><td style="color:#888;width:100px;vertical-align:top;padding-top:6px;">Pets</td><td>${petsHtml}</td></tr>`;
        serviceRow = `<tr><td style="color:#888;">Services</td><td style="color:var(--teal-dark);font-weight:700;">${escHtml(ev.home_svc_label||'Home Service')}</td></tr>`;
    } else {
        petRow     = `<tr><td style="color:#888;width:100px;">Pet</td><td><strong>${escHtml(ev.pet_name||'—')}</strong></td></tr>`;
        serviceRow = `<tr><td style="color:#888;">Service</td><td>${escHtml(ev.svc_name||type)}</td></tr>`;
    }

    document.getElementById('apptDetailContent').innerHTML=`
        <table style="width:100%;border-collapse:collapse;">
            ${petRow}
            ${serviceRow}
            <tr><td style="color:#888;">Type</td><td>${type}</td></tr>
            <tr><td style="color:#888;">Date</td><td>${ev.appointment_date}</td></tr>
            <tr><td style="color:#888;">Time</td><td>${ev.appointment_time}</td></tr>
            <tr><td style="color:#888;">Status</td><td><span style="background:${sc};color:#fff;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;">${ev.status.charAt(0).toUpperCase()+ev.status.slice(1)}</span></td></tr>
            ${ev.address?`<tr><td style="color:#888;">Address</td><td>${escHtml(ev.address)}</td></tr>`:''}
        </table>`;
    document.getElementById('apptDetailModal').classList.add('open');
}
function escHtml(str){if(!str)return'—';return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

function formatApptPeso(v){return'₱'+parseFloat(v||0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');}

function openApptReceipt(data) {
    document.getElementById('ar_ref').textContent   = data.ref    || '—';
    document.getElementById('ar_date').textContent  = data.date   || '—';
    document.getElementById('ar_type').textContent  = data.type   || '—';
    document.getElementById('ar_owner').textContent = data.owner  || '—';
    document.getElementById('ar_total').textContent = formatApptPeso(data.total);

    const st = document.getElementById('ar_status');
    st.textContent = data.status === 'paid' ? '✔ PAID' : (data.status||'—').toUpperCase();
    st.style.color = data.status === 'paid' ? '#065f46' : '#92400e';

    const show = (rowId, valId, val) => {
        document.getElementById(rowId).style.display = val ? '' : 'none';
        if(val) document.getElementById(valId).textContent = val;
    };
    show('ar_address_row','ar_address', data.address);
    show('ar_contact_row','ar_contact', data.contact);
    show('ar_pet_row',    'ar_pet',     data.pet);
    show('ar_service_row','ar_service', data.service);
    show('ar_appt_row',   'ar_appt_date', data.appt_date);

    document.getElementById('ar_breed').textContent    = data.breed    || '';
    document.getElementById('ar_appt_time').textContent = data.appt_time || '';
    document.getElementById('ar_appt_time').style.display = data.appt_time ? '' : 'none';

    let html = '';
    if(data.items && data.items.length > 0) {
        data.items.forEach(i => {
            html += `<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dotted #eee;">
                <span>${escHtml(i.name)}</span><span style="font-weight:700;">${formatApptPeso(i.price)}</span></div>`;
        });
    } else {
        html = '<div style="color:#aaa;">No line items recorded.</div>';
    }
    document.getElementById('ar_items_list').innerHTML = html;

    const overlay = document.getElementById('apptReceiptOverlay');
    overlay.style.display = 'flex';
}

function closeApptReceipt() {
    document.getElementById('apptReceiptOverlay').style.display = 'none';
}

document.addEventListener('keydown', e => { if(e.key==='Escape') closeApptReceipt(); });
let petCount=1;
// Service options for dynamically added pets — excludes "Home Service"
const serviceOptions=`<?php foreach($services as $sv): if(strtolower($sv['name'])==='home service') continue; echo "<option value='{$sv['id']}'>".htmlspecialchars($sv['name'],ENT_QUOTES)."</option>\n"; endforeach; ?>`;
function addHomePet(){
    const idx=petCount++;
    const container=document.getElementById('homePetsContainer');
    const div=document.createElement('div');
    div.className='pet-entry';div.id='pet-entry-'+idx;
    div.innerHTML=`<div class="pet-entry-header"><span>🐾 Pet ${idx+1}</span><button type="button" onclick="removePet(${idx})" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:16px;">✕</button></div>
        <div class="form-group" style="margin-bottom:8px;"><label style="font-size:12px;">Pet Name</label><input type="text" name="home_pets[${idx}][name]" placeholder="Enter pet's name"></div>
        <div class="form-row" style="gap:8px;"><div class="form-group" style="margin-bottom:0;"><label style="font-size:12px;">Species/Breed</label><input type="text" name="home_pets[${idx}][species]" placeholder="e.g. Dog/Aspin"></div>
        <div class="form-group" style="margin-bottom:0;"><label style="font-size:12px;">Type of Service</label><select name="home_pets[${idx}][service_id]"><option value="">Select service</option>${serviceOptions}</select></div></div>`;
    container.appendChild(div);
    document.getElementById('homeSubmitBtn').textContent='PROCEED TO BOOKING for '+petCount+' Pet'+(petCount>1?'s':'');
}
function removePet(idx){const el=document.getElementById('pet-entry-'+idx);if(el)el.remove();petCount=Math.max(1,petCount-1);document.getElementById('homeSubmitBtn').textContent='PROCEED TO BOOKING for '+petCount+' Pet'+(petCount>1?'s':'');}
</script>

<?php include_once __DIR__ . '/../includes/chatbot-widget.php'; ?>

</body>
</html>