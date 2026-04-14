<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/dashboard.php
// Purpose: Admin home — stats, charts, promotions, announcements
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();


// ── Auto-create promotions table if it doesn't exist ─────────
mysqli_query($conn,
    "CREATE TABLE IF NOT EXISTS promotions (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        title       VARCHAR(200)  NOT NULL,
        description TEXT,
        icon        VARCHAR(20)   DEFAULT '🐾',
        image       VARCHAR(300)  DEFAULT NULL,
        link_label  VARCHAR(100)  DEFAULT 'Learn More',
        link_url    VARCHAR(200)  DEFAULT 'appointments.php',
        sort_order  INT           DEFAULT 0,
        is_active   TINYINT(1)    DEFAULT 1,
        created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    )"
);

// ── Add image column if it doesn't exist yet (safe migration) ─
mysqli_query($conn,
    "ALTER TABLE promotions ADD COLUMN IF NOT EXISTS image VARCHAR(300) DEFAULT NULL AFTER icon"
);

// ── Ensure upload directory exists ───────────────────────────
$upload_dir = '../uploads/promotions/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ── Helper: handle promo image upload ────────────────────────
function handlePromoImageUpload($upload_dir) {
    if (empty($_FILES['promo_image']['name'])) return null;
    $file     = $_FILES['promo_image'];
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed  = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null; // 5 MB max
    $filename = 'promo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = $upload_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return 'uploads/promotions/' . $filename;
    }
    return null;
}

// ── Handle promotions CRUD ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_promo') {
        $title  = sanitize($conn, $_POST['promo_title'] ?? '');
        $desc   = sanitize($conn, $_POST['promo_desc']  ?? '');
        $icon   = sanitize($conn, $_POST['promo_icon']  ?? '🐾');
        $llabel = sanitize($conn, $_POST['link_label']  ?? 'Learn More');
        $lurl   = sanitize($conn, $_POST['link_url']    ?? 'appointments.php');
        $image  = handlePromoImageUpload($upload_dir);
        $img_sql = $image ? "'".mysqli_real_escape_string($conn,$image)."'" : 'NULL';
        if ($title) {
            mysqli_query($conn,
                "INSERT INTO promotions (title, description, icon, image, link_label, link_url)
                 VALUES ('$title','$desc','$icon',$img_sql,'$llabel','$lurl')");
        }
        redirect('dashboard.php?promo_success=1');
    }

    if ($action === 'edit_promo') {
        $pid    = (int)$_POST['promo_id'];
        $title  = sanitize($conn, $_POST['promo_title'] ?? '');
        $desc   = sanitize($conn, $_POST['promo_desc']  ?? '');
        $icon   = sanitize($conn, $_POST['promo_icon']  ?? '🐾');
        $llabel = sanitize($conn, $_POST['link_label']  ?? 'Learn More');
        $lurl   = sanitize($conn, $_POST['link_url']    ?? 'appointments.php');
        $new_image = handlePromoImageUpload($upload_dir);

        if ($new_image) {
            // Delete old image if it exists
            $old = getRow($conn, "SELECT image FROM promotions WHERE id=$pid");
            if ($old && $old['image'] && file_exists('../' . $old['image'])) {
                @unlink('../' . $old['image']);
            }
            $img_sql = ", image='".mysqli_real_escape_string($conn,$new_image)."'";
        } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            $old = getRow($conn, "SELECT image FROM promotions WHERE id=$pid");
            if ($old && $old['image'] && file_exists('../' . $old['image'])) {
                @unlink('../' . $old['image']);
            }
            $img_sql = ", image=NULL";
        } else {
            $img_sql = '';
        }

        mysqli_query($conn,
            "UPDATE promotions
             SET title='$title', description='$desc', icon='$icon',
                 link_label='$llabel', link_url='$lurl' $img_sql
             WHERE id=$pid");
        redirect('dashboard.php?promo_success=1');
    }

    if ($action === 'toggle_promo') {
        $pid = (int)$_POST['promo_id'];
        mysqli_query($conn,
            "UPDATE promotions SET is_active = 1 - is_active WHERE id=$pid");
        redirect('dashboard.php');
    }

    if ($action === 'delete_promo') {
        $pid = (int)$_POST['promo_id'];
        $old = getRow($conn, "SELECT image FROM promotions WHERE id=$pid");
        if ($old && $old['image'] && file_exists('../' . $old['image'])) {
            @unlink('../' . $old['image']);
        }
        mysqli_query($conn, "DELETE FROM promotions WHERE id=$pid");
        redirect('dashboard.php');
    }

    // ── Post Announcement ─────────────────────────────────────
    if ($action === 'post_announcement') {
        $title   = sanitize($conn, $_POST['ann_title']   ?? '');
        $content = sanitize($conn, $_POST['ann_content'] ?? '');
        if ($title && $content) {
            $admin_id = (int)$_SESSION['user_id'];
            mysqli_query($conn,
                "INSERT INTO announcements (title, content, posted_by)
                 VALUES ('$title', '$content', $admin_id)");
            $all_users = getRows($conn, "SELECT id FROM users WHERE role='user'");
            foreach ($all_users as $u) {
                $uid = (int)$u['id'];
                mysqli_query($conn,
                    "INSERT INTO notifications (user_id, title, message, type)
                     VALUES ($uid, 'New Announcement', '$title', 'announcement')");
            }
            redirect('dashboard.php?ann_success=1');
        }
    }

    if ($action === 'edit_announcement') {
        $aid     = (int)($_POST['ann_id'] ?? 0);
        $title   = sanitize($conn, $_POST['ann_title']   ?? '');
        $content = sanitize($conn, $_POST['ann_content'] ?? '');
        if ($aid && $title && $content) {
            mysqli_query($conn,
                "UPDATE announcements SET title='$title', content='$content' WHERE id=$aid");
        }
        redirect('dashboard.php?ann_success=1');
    }
}

// Handle delete announcement

if (isset($_GET['delete_ann']) && is_numeric($_GET['delete_ann'])) {
    $did = (int)$_GET['delete_ann'];
    mysqli_query($conn, "DELETE FROM announcements WHERE id=$did");
    redirect('dashboard.php');
}

// ── Stats ─────────────────────────────────────────────────────
$total_patients   = countRows($conn, 'pets');
$total_pet_owners = countRows($conn, 'users', "role='user'");
$appts_this_week  = countRows($conn, 'appointments',
    "appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
     AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)");
$total_revenue = getRow($conn,
    "SELECT COALESCE(SUM(total_amount),0) AS rev FROM transactions WHERE status='paid'");
$total_revenue = $total_revenue['rev'] ?? 0;

// ── Charts ────────────────────────────────────────────────────
$dogs = countRows($conn, 'pets', "species='dog'");
$cats = countRows($conn, 'pets', "species='cat'");
$other= countRows($conn, 'pets', "species='other'");

$svc_chart = getRows($conn,
    "SELECT s.name, COUNT(a.id) AS cnt
     FROM services s
     LEFT JOIN appointments a ON a.service_id = s.id
     GROUP BY s.id, s.name ORDER BY cnt DESC");

$monthly = getRows($conn,
    "SELECT MONTH(appointment_date) AS mo, COUNT(*) AS cnt
     FROM appointments WHERE YEAR(appointment_date)=YEAR(CURDATE())
     GROUP BY MONTH(appointment_date) ORDER BY mo ASC");
$monthly_data = array_fill(1, 12, 0);
foreach ($monthly as $m) $monthly_data[(int)$m['mo']] = (int)$m['cnt'];

// ── Data ──────────────────────────────────────────────────────
$recent_appts  = getRows($conn,
    "SELECT a.*, u.name AS owner_name, p.name AS pet_name, s.name AS svc_name
     FROM appointments a
     LEFT JOIN users u ON a.user_id=u.id
     LEFT JOIN pets p ON a.pet_id=p.id
     LEFT JOIN services s ON a.service_id=s.id
     ORDER BY a.created_at DESC LIMIT 5");

$promotions    = getRows($conn,
    "SELECT * FROM promotions ORDER BY sort_order ASC, id ASC");

$announcements = getRows($conn,
    "SELECT a.*, u.name AS posted_by_name
     FROM announcements a LEFT JOIN users u ON a.posted_by=u.id
     ORDER BY a.created_at DESC LIMIT 5");

$promo_success = isset($_GET['promo_success']);
$ann_success   = isset($_GET['ann_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        /* Stat cards */
        .admin-stats {
            display: grid; grid-template-columns: repeat(3,1fr);
            gap: 16px; margin-bottom: 24px;
        }
        .admin-stat-card {
            background: rgba(255,255,255,0.92); border-radius: var(--radius);
            padding: 22px 20px; text-align: center; box-shadow: var(--shadow);
            border-top: 4px solid var(--teal); transition: var(--transition);
        }
        .admin-stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .admin-stat-card:nth-child(2) { border-top-color: var(--blue-header); }
        .admin-stat-card:nth-child(3) { border-top-color: var(--yellow-dark); }
        .asc-icon  { font-size: 28px; margin-bottom: 8px; }
        .asc-value { font-family: var(--font-head); font-size: 42px; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 6px; }
        .asc-label { font-size: 12px; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; }
        .asc-sub   { font-size: 11px; color: var(--teal-dark); font-weight: 600; margin-top: 4px; }

        /* Charts */
        .charts-row { display: grid; grid-template-columns: 220px 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .chart-card { background: rgba(255,255,255,0.9); border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow); }
        .chart-card-title { font-weight: 700; font-size: 12px; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px; }
        .chart-legend { margin-top: 12px; font-size: 12px; }
        .chart-legend-item { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; font-weight: 600; }
        .legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

        /* ── Promotions ── */
        .promo-section {
            background: rgba(255,255,255,0.92); border-radius: var(--radius);
            padding: 22px; box-shadow: var(--shadow); margin-bottom: 24px;
        }
        .promo-section-header {
            display: flex; align-items: center;
            justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 10px;
        }
        .promo-section-header h3 {
            font-family: var(--font-head); font-size: 16px; font-weight: 800;
            display: flex; align-items: center; gap: 8px; margin: 0;
        }
        .promo-hint {
            font-size: 12px; color: var(--text-light); font-weight: 500;
            margin-top: 3px;
        }

        /* Preview banner */
        .promo-preview-banner {
            position: relative; border-radius: var(--radius);
            overflow: hidden; margin-bottom: 16px;
            background: linear-gradient(135deg, #1565c0, #00bcd4);
            min-height: 160px; display: flex; align-items: center; justify-content: center;
        }
        .promo-preview-slide {
            display: none; width: 100%; min-height: 160px;
            color: #fff; text-align: center; animation: fadeSlide 0.4s ease;
            flex-direction: column; align-items: center; justify-content: center;
            position: relative;
        }
        .promo-preview-slide.active { display: flex; }
        @keyframes fadeSlide { from { opacity:0; } to { opacity:1; } }

        /* Slide with image background */
        .promo-preview-slide.has-image {
            background-size: cover; background-position: center; background-repeat: no-repeat;
        }
        .promo-preview-slide.has-image::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(21,101,192,0.72), rgba(0,188,212,0.65));
            border-radius: 0;
        }
        .pps-content {
            position: relative; z-index: 1;
            display: flex; flex-direction: column; align-items: center;
            padding: 20px 28px;
        }
        .pps-icon  { font-size: 30px; margin-bottom: 6px; }
        .pps-title { font-family: var(--font-head); font-size: 19px; font-weight: 800; margin-bottom: 4px; text-shadow: 0 2px 6px rgba(0,0,0,0.3); }
        .pps-desc  { font-size: 13px; opacity: 0.92; margin-bottom: 10px; text-shadow: 0 1px 4px rgba(0,0,0,0.25); }
        .pps-link  { display: inline-block; background: #fff; color: #1565c0; font-weight: 800; font-size: 11px; padding: 4px 14px; border-radius: 20px; }
        .preview-nav {
            position: absolute; bottom: 8px; left: 50%; transform: translateX(-50%);
            display: flex; gap: 6px; z-index: 2;
        }
        .preview-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: rgba(255,255,255,0.45); cursor: pointer; transition: var(--transition);
        }
        .preview-dot.active { background: #fff; transform: scale(1.3); }
        .preview-arrow {
            position: absolute; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,0.22); border: none; color: #fff;
            font-size: 16px; width: 28px; height: 28px; border-radius: 50%;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: var(--transition); z-index: 2;
        }
        .preview-arrow:hover { background: rgba(255,255,255,0.4); }
        .preview-arrow.prev { left: 8px; }
        .preview-arrow.next { right: 8px; }

        /* Promo cards */
        .promo-cards {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 12px; margin-bottom: 12px;
        }
        .promo-card-admin {
            border-radius: var(--radius); padding: 14px;
            border: 1.5px solid var(--border); background: #fafffe;
            display: flex; flex-direction: column; gap: 6px;
            transition: var(--transition); position: relative; overflow: hidden;
        }
        .promo-card-admin:hover { border-color: var(--teal-light); box-shadow: var(--shadow); }
        .promo-card-admin.inactive { opacity: 0.55; background: #f5f5f5; }

        /* Thumbnail inside card */
        .pca-thumb {
            width: 100%; height: 80px; object-fit: cover;
            border-radius: 6px; margin-bottom: 4px;
            border: 1px solid var(--border);
        }
        .pca-no-thumb {
            width: 100%; height: 80px; border-radius: 6px;
            background: linear-gradient(135deg, #e0f7fa, #e8eaf6);
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; margin-bottom: 4px; border: 1px dashed var(--border);
            color: var(--text-light); font-style: italic; font-size: 11px; gap: 6px;
        }

        .pca-top { display: flex; align-items: flex-start; gap: 10px; }
        .pca-icon { font-size: 26px; flex-shrink: 0; }
        .pca-body { flex: 1; min-width: 0; }
        .pca-title { font-weight: 800; font-size: 13px; color: var(--text-dark); margin-bottom: 3px; }
        .pca-desc  { font-size: 11px; color: var(--text-mid); line-height: 1.4; }
        .pca-link  { font-size: 11px; color: var(--teal-dark); font-weight: 700; }
        .pca-status {
            font-size: 10px; font-weight: 700;
            padding: 2px 8px; border-radius: 20px;
            display: inline-block; width: fit-content;
        }
        .pca-status.live   { background: #d1fae5; color: #065f46; }
        .pca-status.hidden { background: #f3f4f6; color: #6b7280; }
        .pca-actions { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 4px; }
        .pca-btn {
            padding: 3px 10px; border-radius: 6px; border: none;
            font-size: 11px; font-weight: 700; cursor: pointer; transition: var(--transition);
        }
        .pca-edit   { background: #dbeafe; color: #1e40af; }
        .pca-toggle { background: #f0fffe; color: var(--teal-dark); border: 1px solid var(--teal-light); }
        .pca-del    { background: #fee2e2; color: #dc2626; }
        .pca-btn:hover { opacity: 0.8; }
        .promo-empty {
            text-align: center; padding: 24px; color: var(--text-light);
            font-size: 13px; border: 2px dashed var(--border); border-radius: var(--radius);
        }

        /* ── Image upload widget ── */
        .img-upload-zone {
            border: 2px dashed var(--border); border-radius: 10px;
            padding: 16px; text-align: center; cursor: pointer;
            transition: var(--transition); background: #fafffe;
            position: relative; overflow: hidden;
        }
        .img-upload-zone:hover, .img-upload-zone.dragover {
            border-color: var(--teal); background: #f0fdfc;
        }
        .img-upload-zone input[type=file] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .img-upload-icon { font-size: 28px; margin-bottom: 6px; }
        .img-upload-label { font-size: 12px; color: var(--text-mid); font-weight: 600; }
        .img-upload-hint  { font-size: 10px; color: var(--text-light); margin-top: 2px; }

        .img-preview-wrap {
            position: relative; display: none; margin-top: 10px;
        }
        .img-preview-wrap.visible { display: block; }
        .img-preview-wrap img {
            width: 100%; max-height: 130px; object-fit: cover;
            border-radius: 8px; border: 1.5px solid var(--border);
        }
        .img-remove-btn {
            position: absolute; top: 6px; right: 6px;
            background: rgba(239,68,68,0.88); color: #fff;
            border: none; border-radius: 50%; width: 22px; height: 22px;
            font-size: 12px; cursor: pointer; display: flex;
            align-items: center; justify-content: center;
            line-height: 1; font-weight: 700;
        }
        .img-remove-btn:hover { background: #dc2626; }

        /* Announcement section */
        .ann-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .ann-compose { background: rgba(255,255,255,0.9); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); }
        .ann-compose h3 { font-family: var(--font-head); font-size: 15px; font-weight: 700; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; }
        .ann-list-panel { background: rgba(255,255,255,0.9); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); overflow-y: auto; max-height: 340px; }
        .ann-list-panel h3 { font-family: var(--font-head); font-size: 15px; font-weight: 700; margin-bottom: 14px; }
        .ann-entry { padding: 12px 0; border-bottom: 1px solid var(--border); }
        .ann-entry:last-child { border-bottom: none; }
        .ann-entry-title { font-weight: 700; font-size: 13px; color: var(--text-dark); margin-bottom: 4px; }
        .ann-entry-body  { font-size: 12px; color: var(--text-mid); line-height: 1.5; }
        .ann-entry-meta  { font-size: 11px; color: var(--text-light); margin-top: 4px; }
        .ann-delete-btn  { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 13px; padding: 0; float: right; }

        /* Recent appts */
        .recent-section { background: rgba(255,255,255,0.9); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); }
        .recent-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .recent-header h3 { font-family: var(--font-head); font-size: 15px; font-weight: 700; }

        /* Delete modal */
        .del-overlay {
            display: none; position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(3px);
            align-items: center; justify-content: center;
        }
        .del-overlay.open { display: flex; }
        .del-box {
            background: #fff; border-radius: 16px; padding: 32px 28px;
            max-width: 380px; width: 90%; text-align: center;
            box-shadow: 0 24px 60px rgba(0,0,0,0.2); animation: popIn 0.2s ease;
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
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-del-confirm:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(239,68,68,0.35); }

        @media(max-width:900px) {
            .admin-stats { grid-template-columns: 1fr 1fr; }
            .charts-row  { grid-template-columns: 1fr; }
            .ann-grid    { grid-template-columns: 1fr; }
            .promo-cards { grid-template-columns: 1fr; }
        }
        @media(max-width:600px) { .admin-stats { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title" style="display:flex; align-items:center; gap:10px;">
                <img src="../assets/css/images/pets/logo.png" alt="Ligao Petcare Logo"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.6);"
                     onerror="this.style.display='none';">
                ADMIN DASHBOARD
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

            <?php if ($promo_success): ?>
                <div class="alert alert-success">✅ Promotion saved successfully!</div>
            <?php endif; ?>
            <?php if ($ann_success): ?>
                <div class="alert alert-success">✅ Announcement posted successfully!</div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <div class="asc-icon">🐾</div>
                    <div class="asc-value"><?= $total_patients ?></div>
                    <div class="asc-label">Total Patient</div>
                    <div class="asc-sub">Overall Patient</div>
                </div>
                <div class="admin-stat-card">
                    <div class="asc-icon">📅</div>
                    <div class="asc-value"><?= $appts_this_week ?></div>
                    <div class="asc-label">Total Appointments</div>
                    <div class="asc-sub">This week</div>
                </div>
                <div class="admin-stat-card">
                    <div class="asc-icon">👤</div>
                    <div class="asc-value"><?= $total_pet_owners ?></div>
                    <div class="asc-label">Total Pet Owner</div>
                    <div class="asc-sub">Registered users</div>
                </div>
            </div>

          <!-- Charts -->
            <div class="charts-row" style="margin-bottom: 24px;">
                <div class="chart-card">
                    <div class="chart-card-title">Species Breakdown</div>
                    <canvas id="donutChart" height="160"></canvas>
                    <div class="chart-legend">
                        <div class="chart-legend-item"><div class="legend-dot" style="background:#1976d2;"></div>Dog <?= $dogs ? round($dogs/max($total_patients,1)*100) : 0 ?>%</div>
                        <div class="chart-legend-item"><div class="legend-dot" style="background:#b2ebf2;"></div>Cat <?= $cats ? round($cats/max($total_patients,1)*100) : 0 ?>%</div>
                        <?php if ($other > 0): ?><div class="chart-legend-item"><div class="legend-dot" style="background:#cddc39;"></div>Other <?= round($other/max($total_patients,1)*100) ?>%</div><?php endif; ?>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-title">Appointments by Service</div>
                    <canvas id="barChart" height="160"></canvas>
                </div>
                <div class="chart-card">
                    <div class="chart-card-title">Monthly Appointments (<?= date('Y') ?>)</div>
                    <canvas id="lineChart" height="160"></canvas>
                </div>
            </div>

           <!-- ══ PATIENT WHITEBOARD ════════════════════════ -->
            <div style="margin-top: 24px; margin-bottom: 0; clear: both; position: relative; z-index: 1;">
                <?php require_once __DIR__ . '/../includes/whiteboard-widget.php'; ?>
            </div>

            <!-- spacer -->
            <div style="height: 32px;"></div>

            <!-- ══ BANNER PROMOTIONS ══════════════════════════ -->
            <div class="promo-section">
                <div class="promo-section-header">
                    <div>
                        <h3>🎯 Banner Promotions</h3>
                        <div class="promo-hint">These appear as slides on the user dashboard. Only <strong>Live</strong> promotions are shown.</div>
                    </div>
                    <button class="btn btn-teal btn-sm"
                            onclick="document.getElementById('addPromoModal').classList.add('open')">
                        + Add Promotion
                    </button>
                </div>

                <?php
                $active_promos = array_filter($promotions, fn($p) => $p['is_active']);
                ?>

                <!-- Live preview banner -->
                <?php if (!empty($active_promos)): ?>
                <div class="promo-preview-banner" id="previewBanner">
                    <?php $pi = 0; foreach ($active_promos as $slide): ?>
                        <div class="promo-preview-slide <?= $pi === 0 ? 'active' : '' ?> <?= !empty($slide['image']) ? 'has-image' : '' ?>"
                             <?= !empty($slide['image']) ? 'style="background-image:url(\'../'.$slide['image'].'\')"' : '' ?>>
                            <div class="pps-content">
                                <?php if (empty($slide['image'])): ?>
                                    <div class="pps-icon"><?= htmlspecialchars($slide['icon']) ?></div>
                                <?php endif; ?>
                                <div class="pps-title"><?= htmlspecialchars($slide['title']) ?></div>
                                <div class="pps-desc"><?= htmlspecialchars($slide['description']) ?></div>
                                <div class="pps-link"><?= htmlspecialchars($slide['link_label']) ?></div>
                            </div>
                        </div>
                    <?php $pi++; endforeach; ?>
                    <?php if (count($active_promos) > 1): ?>
                        <button class="preview-arrow prev" onclick="prevPreview()">‹</button>
                        <button class="preview-arrow next" onclick="nextPreview()">›</button>
                        <div class="preview-nav">
                            <?php for ($d = 0; $d < count($active_promos); $d++): ?>
                                <div class="preview-dot <?= $d === 0 ? 'active' : '' ?>" onclick="goPreview(<?= $d ?>)"></div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <p style="font-size:11px;color:var(--text-light);text-align:center;margin:-8px 0 14px;">
                    ↑ Live preview — this is what users see on their dashboard
                </p>
                <?php endif; ?>

                <!-- Promotion cards -->
                <?php if (empty($promotions)): ?>
                    <div class="promo-empty">
                        <div style="font-size:36px;margin-bottom:8px;">🎯</div>
                        <p>No promotions yet.<br>Add one to show it on the user dashboard banner.</p>
                    </div>
                <?php else: ?>
                    <div class="promo-cards">
                        <?php foreach ($promotions as $promo): ?>
                            <div class="promo-card-admin <?= !$promo['is_active'] ? 'inactive' : '' ?>">
                                <!-- Thumbnail or placeholder -->
                                <?php if (!empty($promo['image'])): ?>
                                    <img src="../<?= htmlspecialchars($promo['image']) ?>"
                                         alt="<?= htmlspecialchars($promo['title']) ?>"
                                         class="pca-thumb">
                                <?php else: ?>
                                    <div class="pca-no-thumb">
                                        <span style="font-size:22px;"><?= htmlspecialchars($promo['icon']) ?></span>
                                        <span>No image</span>
                                    </div>
                                <?php endif; ?>

                                <div class="pca-top">
                                    <div class="pca-icon"><?= htmlspecialchars($promo['icon']) ?></div>
                                    <div class="pca-body">
                                        <div class="pca-title"><?= htmlspecialchars($promo['title']) ?></div>
                                        <div class="pca-desc"><?= htmlspecialchars($promo['description']) ?></div>
                                        <div class="pca-link">→ <?= htmlspecialchars($promo['link_label']) ?> (<?= htmlspecialchars($promo['link_url']) ?>)</div>
                                    </div>
                                </div>
                                <span class="pca-status <?= $promo['is_active'] ? 'live' : 'hidden' ?>">
                                    <?= $promo['is_active'] ? '● Live' : '● Hidden' ?>
                                </span>
                                <div class="pca-actions">
                                    <button class="pca-btn pca-edit"
                                            onclick='openEditPromo(<?= json_encode($promo) ?>)'>
                                        ✏️ Edit
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action"   value="toggle_promo">
                                        <input type="hidden" name="promo_id" value="<?= $promo['id'] ?>">
                                        <button type="submit" class="pca-btn pca-toggle">
                                            <?= $promo['is_active'] ? '👁️ Hide' : '👁️ Show' ?>
                                        </button>
                                    </form>
                                    <button class="pca-btn pca-del"
                                            onclick="openPromoDelModal(<?= $promo['id'] ?>, '<?= htmlspecialchars(addslashes($promo['title'])) ?>')">
                                        🗑️ Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div><!-- /promo-section -->

            <!-- Announcements -->
            <div class="ann-grid">
                <div class="ann-compose">
                    <h3>
                        📢 Write / Post Announcement
                        <span style="font-size:12px;color:var(--text-light);font-weight:600;">Broadcasts to all users</span>
                    </h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="post_announcement">
                        <div class="form-group">
                            <label>Announcement Title</label>
                            <input type="text" name="ann_title" placeholder="e.g. Holiday Closure Notice" required>
                        </div>
                        <div class="form-group">
                            <label>Message</label>
                            <textarea name="ann_content" rows="5"
                                      placeholder="Write your announcement here..."
                                      required style="resize:vertical;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-teal w-full">📤 Post Announcement</button>
                    </form>
                </div>

                <div class="ann-list-panel">
                    <h3>📋 Posted Announcements</h3>
                    <?php if (empty($announcements)): ?>
                        <p style="font-size:13px;color:var(--text-light);">No announcements yet.</p>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): ?>
                           <div class="ann-entry">
                                <div style="float:right;display:flex;gap:6px;align-items:center;">
                                    <button type="button" class="pca-btn pca-edit"
                                            onclick="openAnnEditModal(<?= $ann['id'] ?>, '<?= htmlspecialchars(addslashes($ann['title'])) ?>', '<?= htmlspecialchars(addslashes($ann['content'])) ?>')"
                                            title="Edit">✏️ Edit</button>
                                    <button type="button" class="ann-delete-btn"
                                            onclick="openAnnDelModal(<?= $ann['id'] ?>, '<?= htmlspecialchars(addslashes($ann['title'])) ?>')"
                                            title="Delete">🗑️</button>
                                </div>
                                <div class="ann-entry-title"><?= htmlspecialchars($ann['title']) ?></div>                                <div class="ann-entry-body">
                                    <?= nl2br(htmlspecialchars(substr($ann['content'],0,120)))
                                        . (strlen($ann['content'])>120 ? '...' : '') ?>
                                </div>
                                <div class="ann-entry-meta">
                                    <?= formatDate($ann['created_at']) ?> · By <?= htmlspecialchars($ann['posted_by_name'] ?? 'Admin') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Appointments -->
            <div class="recent-section" style="margin-bottom: 24px;">
                <div class="recent-header">
                    <h3>📅 Recent Appointments</h3>
                    <a href="appointments.php" class="btn btn-teal btn-sm">View All</a>
                </div>
                <?php if (empty($recent_appts)): ?>
                    <div class="empty-state" style="padding:24px;">
                        <div class="empty-icon">📭</div><h3>No appointments yet</h3>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr><th>Owner</th><th>Pet</th><th>Service</th><th>Date</th><th>Time</th><th>Type</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_appts as $ra): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($ra['owner_name']??'—') ?></strong></td>
                                        <td><?= htmlspecialchars($ra['pet_name']??'—') ?></td>
                                        <td><?= htmlspecialchars($ra['svc_name']??'—') ?></td>
                                        <td><?= formatDate($ra['appointment_date']) ?></td>
                                        <td><?= formatTime($ra['appointment_time']) ?></td>
                                        <td>
                                            <span style="font-size:11px;background:var(--teal-light);color:var(--teal-dark);padding:2px 8px;border-radius:20px;font-weight:700;">
                                                <?= ucfirst(str_replace('_',' ',$ra['appointment_type'])) ?>
                                            </span>
                                        </td>
                                        <td><?= statusBadge($ra['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
               <?php endif; ?>
            </div>

          </div><!-- /page-body -->
    </div>
</div>

<!-- ══ Add Promotion Modal -->
<div class="modal-overlay" id="addPromoModal">
    <div class="modal" style="max-width:500px;">
        <button class="modal-close" onclick="document.getElementById('addPromoModal').classList.remove('open')">×</button>
        <h3 class="modal-title">🎯 Add Banner Promotion</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_promo">

            <!-- Image upload zone -->
            <div class="form-group">
                <label>Banner Image <span style="font-size:11px;color:var(--text-light);font-weight:500;">(optional — replaces gradient background)</span></label>
                <div class="img-upload-zone" id="addUploadZone"
                     ondragover="handleDragOver(event,'addUploadZone')"
                     ondragleave="handleDragLeave('addUploadZone')"
                     ondrop="handleDrop(event,'add')">
                    <input type="file" name="promo_image" id="addPromoImage" accept="image/*"
                           onchange="previewImage(this,'addImgPreview','addUploadZone')">
                    <div id="addUploadPlaceholder">
                        <div class="img-upload-icon">🖼️</div>
                        <div class="img-upload-label">Click to upload or drag & drop</div>
                        <div class="img-upload-hint">JPG, PNG, GIF, WEBP · Max 5 MB</div>
                    </div>
                </div>
                <div class="img-preview-wrap" id="addImgPreview">
                    <img src="" alt="Preview">
                    <button type="button" class="img-remove-btn"
                            onclick="removeImage('addPromoImage','addImgPreview','addUploadZone')">✕</button>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:0 0 80px;">
                    <label>Icon</label>
                    <input type="text" name="promo_icon" value="🐾"
                           style="font-size:22px;text-align:center;">
                </div>
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="promo_title" required
                           placeholder="e.g. VACCINATION AVAILABLE">
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="promo_desc" rows="2"
                          placeholder="Short description shown on the banner..."></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Button Label</label>
                    <input type="text" name="link_label" value="Book Now"
                           placeholder="e.g. Book Now">
                </div>
                <div class="form-group">
                    <label>Button Links To</label>
                    <select name="link_url">
                        <option value="appointments.php">Appointments</option>
                        <option value="products.php">Products</option>
                        <option value="services.php">Services</option>
                        <option value="pet_profile.php">Pet Profile</option>
                        <option value="announcements.php">Announcements</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('addPromoModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">✅ Add Promotion</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ Edit Promotion Modal ═══════════════════════════════════ -->
<div class="modal-overlay" id="editPromoModal">
    <div class="modal" style="max-width:500px;">
        <button class="modal-close" onclick="document.getElementById('editPromoModal').classList.remove('open')">×</button>
        <h3 class="modal-title">✏️ Edit Promotion</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action"       value="edit_promo">
            <input type="hidden" name="promo_id"     id="edit_promo_id">
            <input type="hidden" name="remove_image" id="edit_remove_image" value="0">

            <!-- Image upload zone -->
            <div class="form-group">
                <label>Banner Image <span style="font-size:11px;color:var(--text-light);font-weight:500;">(upload new to replace existing)</span></label>

                <!-- Current image display (shown when editing a promo that has an image) -->
                <div id="editCurrentImgWrap" style="display:none;margin-bottom:8px;">
                    <p style="font-size:11px;color:var(--text-light);margin-bottom:4px;">Current image:</p>
                    <div style="position:relative;display:inline-block;">
                        <img id="editCurrentImg" src="" alt="Current"
                             style="max-height:90px;border-radius:8px;border:1.5px solid var(--border);">
                        <button type="button" class="img-remove-btn"
                                style="top:4px;right:4px;"
                                onclick="removeCurrentImage()">✕</button>
                    </div>
                </div>

                <div class="img-upload-zone" id="editUploadZone"
                     ondragover="handleDragOver(event,'editUploadZone')"
                     ondragleave="handleDragLeave('editUploadZone')"
                     ondrop="handleDrop(event,'edit')">
                    <input type="file" name="promo_image" id="editPromoImage" accept="image/*"
                           onchange="previewImage(this,'editImgPreview','editUploadZone')">
                    <div id="editUploadPlaceholder">
                        <div class="img-upload-icon">🖼️</div>
                        <div class="img-upload-label">Click to upload or drag & drop a new image</div>
                        <div class="img-upload-hint">JPG, PNG, GIF, WEBP · Max 5 MB</div>
                    </div>
                </div>
                <div class="img-preview-wrap" id="editImgPreview">
                    <img src="" alt="Preview">
                    <button type="button" class="img-remove-btn"
                            onclick="removeImage('editPromoImage','editImgPreview','editUploadZone')">✕</button>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:0 0 80px;">
                    <label>Icon</label>
                    <input type="text" name="promo_icon" id="edit_promo_icon"
                           style="font-size:22px;text-align:center;">
                </div>
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="promo_title" id="edit_promo_title" required>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="promo_desc" rows="2" id="edit_promo_desc"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Button Label</label>
                    <input type="text" name="link_label" id="edit_link_label">
                </div>
                <div class="form-group">
                    <label>Button Links To</label>
                    <select name="link_url" id="edit_link_url">
                        <option value="appointments.php">Appointments</option>
                        <option value="products.php">Products</option>
                        <option value="services.php">Services</option>
                        <option value="pet_profile.php">Pet Profile</option>
                        <option value="announcements.php">Announcements</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('editPromoModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ Delete Promotion Modal ════════════════════════════════ -->
<div class="del-overlay" id="promoDelOverlay" onclick="if(event.target===this)closePromoDelModal()">
    <div class="del-box">
        <div class="del-icon-wrap">🎯</div>
        <h3>Delete Promotion?</h3>
        <p id="promoDelMsg">This promotion will be permanently removed.</p>
        <div class="del-actions">
            <button class="btn-keep" onclick="closePromoDelModal()">Keep It</button>
            <button class="btn-del-confirm" id="promoDelConfirmBtn">✕ Yes, Delete</button>
        </div>
    </div>
</div>
<form id="promoDelForm" method="POST" style="display:none;">
    <input type="hidden" name="action"   value="delete_promo">
    <input type="hidden" name="promo_id" id="del_promo_id_input">
</form>

<!-- ══ Edit Announcement Modal ══════════════════════════════ -->
<div class="modal-overlay" id="editAnnModal">
    <div class="modal" style="max-width:500px;">
        <button class="modal-close" onclick="document.getElementById('editAnnModal').classList.remove('open')">×</button>
        <h3 class="modal-title">✏️ Edit Announcement</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_announcement">
            <input type="hidden" name="ann_id" id="edit_ann_id">
            <div class="form-group">
                <label>Announcement Title</label>
                <input type="text" name="ann_title" id="edit_ann_title" required>
            </div>
            <div class="form-group">
                <label>Message</label>
                <textarea name="ann_content" id="edit_ann_content" rows="5"
                          required style="resize:vertical;"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('editAnnModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ Delete Announcement Modal ════════════════════════════ -->
<div class="del-overlay" id="annDelOverlay" onclick="if(event.target===this)closeAnnDelModal()">
    <div class="del-box">
        <div class="del-icon-wrap">📢</div>
        <h3>Delete Announcement?</h3>
        <p id="annDelMsg">This announcement will be permanently removed.</p>
        <div class="del-actions">
            <button class="btn-keep" onclick="closeAnnDelModal()">Keep It</button>
            <button class="btn-del-confirm" id="annDelConfirmBtn">✕ Yes, Delete</button>
        </div>
    </div>
</div>
<form id="annDelForm" method="GET" action="dashboard.php" style="display:none;">
    <input type="hidden" name="delete_ann" id="del_ann_id_input">
</form>

<?php include_once '../includes/chatbot-widget.php'; ?>
<script src="../assets/js/main.js"></script>
<script>

// ── Charts ───────────────────────────────────────────────────
const blue = '#1565c0', light = '#b2ebf2', yellow = '#cddc39';

new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: { labels: ['Dog','Cat','Other'],
        datasets: [{ data: [<?= $dogs ?>,<?= $cats ?>,<?= $other ?>],
            backgroundColor:[blue,light,yellow], borderWidth:2, borderColor:'#fff' }] },
    options: { responsive:true, plugins:{ legend:{display:false} }, cutout:'65%' }
});

new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: { labels: <?= json_encode(array_column($svc_chart,'name')) ?>,
        datasets: [{ label:'Appointments', data: <?= json_encode(array_column($svc_chart,'cnt')) ?>,
            backgroundColor: blue, borderRadius:6, borderSkipped:false }] },
    options: { responsive:true, plugins:{legend:{display:false}},
        scales:{ y:{beginAtZero:true,ticks:{stepSize:1,font:{size:11}},grid:{color:'rgba(0,0,0,0.05)'}},
                 x:{ticks:{font:{size:10}},grid:{display:false}} } }
});

new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: { labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        datasets: [{ label:'Appointments', data: <?= json_encode(array_values($monthly_data)) ?>,
            borderColor:blue, backgroundColor:'rgba(21,101,192,0.08)',
            borderWidth:2.5, tension:0.4, fill:true,
            pointBackgroundColor:blue, pointRadius:4, pointHoverRadius:6 }] },
    options: { responsive:true, plugins:{legend:{display:false}},
        scales:{ y:{beginAtZero:true,ticks:{stepSize:1,font:{size:11}},grid:{color:'rgba(0,0,0,0.05)'}},
                 x:{ticks:{font:{size:10}},grid:{display:false}} } }
});

// ── Live preview carousel ─────────────────────────────────────
let previewIdx = 0;
const previewSlides = document.querySelectorAll('.promo-preview-slide');
const previewDots   = document.querySelectorAll('.preview-dot');

function showPreview(n) {
    if (!previewSlides.length) return;
    previewSlides.forEach(s => s.classList.remove('active'));
    previewDots.forEach(d => d.classList.remove('active'));
    previewIdx = (n + previewSlides.length) % previewSlides.length;
    previewSlides[previewIdx].classList.add('active');
    if (previewDots[previewIdx]) previewDots[previewIdx].classList.add('active');
}
function nextPreview() { showPreview(previewIdx + 1); }
function prevPreview() { showPreview(previewIdx - 1); }
function goPreview(n)  { showPreview(n); }

if (previewSlides.length > 1) {
    setInterval(() => showPreview(previewIdx + 1), 3500);
}

// ── Image upload helpers ──────────────────────────────────────
function previewImage(input, previewId, zoneId) {
    const file = input.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        alert('Image must be under 5 MB.');
        input.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        const wrap = document.getElementById(previewId);
        wrap.querySelector('img').src = e.target.result;
        wrap.classList.add('visible');
        document.getElementById(zoneId).querySelector('[id$="Placeholder"]').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function removeImage(inputId, previewId, zoneId) {
    document.getElementById(inputId).value = '';
    const wrap = document.getElementById(previewId);
    wrap.querySelector('img').src = '';
    wrap.classList.remove('visible');
    document.getElementById(zoneId).querySelector('[id$="Placeholder"]').style.display = '';
}

function removeCurrentImage() {
    document.getElementById('edit_remove_image').value = '1';
    document.getElementById('editCurrentImgWrap').style.display = 'none';
}

// Drag & drop
function handleDragOver(e, zoneId) {
    e.preventDefault();
    document.getElementById(zoneId).classList.add('dragover');
}
function handleDragLeave(zoneId) {
    document.getElementById(zoneId).classList.remove('dragover');
}
function handleDrop(e, prefix) {
    e.preventDefault();
    const zoneId   = prefix + 'UploadZone';
    const inputId  = prefix + 'PromoImage';
    const previewId= prefix + 'ImgPreview';
    document.getElementById(zoneId).classList.remove('dragover');
    const dt = e.dataTransfer;
    if (dt.files.length) {
        const input = document.getElementById(inputId);
        // Assign via DataTransfer
        try {
            const transfer = new DataTransfer();
            transfer.items.add(dt.files[0]);
            input.files = transfer.files;
        } catch(err) { /* fallback: browser doesn't support DataTransfer setter */ }
        previewImage(input, previewId, zoneId);
    }
}

// ── Edit promo modal ──────────────────────────────────────────
function openEditPromo(p) {
    document.getElementById('edit_promo_id').value    = p.id;
    document.getElementById('edit_promo_icon').value  = p.icon;
    document.getElementById('edit_promo_title').value = p.title;
    document.getElementById('edit_promo_desc').value  = p.description;
    document.getElementById('edit_link_label').value  = p.link_label;
    document.getElementById('edit_remove_image').value = '0';

    const sel = document.getElementById('edit_link_url');
    for (let o of sel.options) o.selected = (o.value === p.link_url);

    // Show/hide current image
    const curWrap = document.getElementById('editCurrentImgWrap');
    const curImg  = document.getElementById('editCurrentImg');
    if (p.image) {
        curImg.src = '../' + p.image;
        curWrap.style.display = '';
    } else {
        curImg.src = '';
        curWrap.style.display = 'none';
    }

    // Reset new upload preview
    removeImage('editPromoImage', 'editImgPreview', 'editUploadZone');

    document.getElementById('editPromoModal').classList.add('open');
}

// ── Delete promo modal ────────────────────────────────────────
function openPromoDelModal(id, title) {
    document.getElementById('del_promo_id_input').value = id;
    document.getElementById('promoDelMsg').innerHTML =
        '<strong>' + title + '</strong> will be permanently removed from the user banner.<br>' +
        '<span style="color:#ef4444;font-size:12px;">This action cannot be undone.</span>';
    document.getElementById('promoDelOverlay').classList.add('open');
}
function closePromoDelModal() {
    document.getElementById('promoDelOverlay').classList.remove('open');
}
document.getElementById('promoDelConfirmBtn').addEventListener('click', () => {
    document.getElementById('promoDelForm').submit();
});

// ── Edit announcement modal ───────────────────────────────────
function openAnnEditModal(id, title, content) {
    document.getElementById('edit_ann_id').value      = id;
    document.getElementById('edit_ann_title').value   = title;
    document.getElementById('edit_ann_content').value = content;
    document.getElementById('editAnnModal').classList.add('open');
}

// ── Delete announcement modal ─────────────────────────────────
function openAnnDelModal(id, title) {
    document.getElementById('del_ann_id_input').value = id;
    document.getElementById('annDelMsg').innerHTML =
        '<strong>' + title + '</strong> will be permanently removed.<br>' +
        '<span style="color:#ef4444;font-size:12px;">This action cannot be undone.</span>';
    document.getElementById('annDelOverlay').classList.add('open');
}
function closeAnnDelModal() {
    document.getElementById('annDelOverlay').classList.remove('open');
}
document.getElementById('annDelConfirmBtn').addEventListener('click', () => {
    document.getElementById('annDelForm').submit();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closePromoDelModal(); closeAnnDelModal(); }
});document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
</script>


</body>
</html>
