<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/dashboard.php
// Purpose: Admin home — stats, charts, announcements
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

// ── Stats ────────────────────────────────────────────────────
$total_patients   = countRows($conn, 'pets');
$total_pet_owners = countRows($conn, 'users', "role='user'");
$appts_this_week  = countRows($conn, 'appointments',
    "appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
     AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)");
$total_revenue    = getRow($conn,
    "SELECT COALESCE(SUM(total_amount),0) AS rev FROM transactions WHERE status='paid'");
$total_revenue    = $total_revenue['rev'] ?? 0;

// ── Species breakdown for donut chart ───────────────────────
$dogs = countRows($conn, 'pets', "species='dog'");
$cats = countRows($conn, 'pets', "species='cat'");
$other= countRows($conn, 'pets', "species='other'");

// ── Appointments per service for bar chart ───────────────────
$svc_chart = getRows($conn,
    "SELECT s.name, COUNT(a.id) AS cnt
     FROM services s
     LEFT JOIN appointments a ON a.service_id = s.id
     GROUP BY s.id, s.name
     ORDER BY cnt DESC");

// ── Monthly appointments for line chart ──────────────────────
$monthly = getRows($conn,
    "SELECT MONTH(appointment_date) AS mo, COUNT(*) AS cnt
     FROM appointments
     WHERE YEAR(appointment_date) = YEAR(CURDATE())
     GROUP BY MONTH(appointment_date)
     ORDER BY mo ASC");
$monthly_data = array_fill(1, 12, 0);
foreach ($monthly as $m) $monthly_data[(int)$m['mo']] = (int)$m['cnt'];

// ── Recent appointments ───────────────────────────────────────
$recent_appts = getRows($conn,
    "SELECT a.*, u.name AS owner_name, p.name AS pet_name, s.name AS svc_name
     FROM appointments a
     LEFT JOIN users u    ON a.user_id     = u.id
     LEFT JOIN pets  p    ON a.pet_id      = p.id
     LEFT JOIN services s ON a.service_id  = s.id
     ORDER BY a.created_at DESC LIMIT 5");

// ── Handle Post Announcement ─────────────────────────────────
$ann_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post_announcement') {
    $title   = sanitize($conn, $_POST['ann_title']   ?? '');
    $content = sanitize($conn, $_POST['ann_content'] ?? '');
    if ($title && $content) {
        $admin_id = (int)$_SESSION['user_id'];
        mysqli_query($conn,
            "INSERT INTO announcements (title, content, posted_by)
             VALUES ('$title', '$content', $admin_id)");
        // Notify all users
        $all_users = getRows($conn, "SELECT id FROM users WHERE role='user'");
        foreach ($all_users as $u) {
            $uid = (int)$u['id'];
            mysqli_query($conn,
                "INSERT INTO notifications (user_id, title, message, type)
                 VALUES ($uid, 'New Announcement', '$title', 'announcement')");
        }
        $ann_success = 'Announcement posted successfully!';
    }
}

// ── Fetch announcements ──────────────────────────────────────
$announcements = getRows($conn,
    "SELECT a.*, u.name AS posted_by_name
     FROM announcements a
     LEFT JOIN users u ON a.posted_by = u.id
     ORDER BY a.created_at DESC LIMIT 5");
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
        /* ── Stat cards ── */
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .admin-stat-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 22px 20px;
            text-align: center;
            box-shadow: var(--shadow);
            border-top: 4px solid var(--teal);
            transition: var(--transition);
        }
        .admin-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .admin-stat-card:nth-child(2) { border-top-color: var(--blue-header); }
        .admin-stat-card:nth-child(3) { border-top-color: var(--yellow-dark); }

        .asc-icon {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .asc-value {
            font-family: var(--font-head);
            font-size: 42px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1;
            margin-bottom: 6px;
        }
        .asc-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .asc-sub {
            font-size: 11px;
            color: var(--teal-dark);
            font-weight: 600;
            margin-top: 4px;
        }

        /* ── Charts row ── */
        .charts-row {
            display: grid;
            grid-template-columns: 220px 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .chart-card {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 18px;
            box-shadow: var(--shadow);
        }

        .chart-card-title {
            font-weight: 700;
            font-size: 12px;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 14px;
        }

        .chart-legend {
            margin-top: 12px;
            font-size: 12px;
        }
        .chart-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
            font-weight: 600;
        }
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* ── Announcement section ── */
        .ann-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .ann-compose {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .ann-compose h3 {
            font-family: var(--font-head);
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ann-list-panel {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            overflow-y: auto;
            max-height: 340px;
        }

        .ann-list-panel h3 {
            font-family: var(--font-head);
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .ann-entry {
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .ann-entry:last-child { border-bottom: none; }
        .ann-entry-title {
            font-weight: 700;
            font-size: 13px;
            color: var(--text-dark);
            margin-bottom: 4px;
        }
        .ann-entry-body {
            font-size: 12px;
            color: var(--text-mid);
            line-height: 1.5;
        }
        .ann-entry-meta {
            font-size: 11px;
            color: var(--text-light);
            margin-top: 4px;
        }
        .ann-delete-btn {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 13px;
            padding: 0;
            float: right;
        }

        /* ── Recent appointments ── */
        .recent-section {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .recent-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .recent-header h3 {
            font-family: var(--font-head);
            font-size: 15px;
            font-weight: 700;
        }

        @media(max-width:900px) {
            .admin-stats  { grid-template-columns: 1fr 1fr; }
            .charts-row   { grid-template-columns: 1fr; }
            .ann-grid     { grid-template-columns: 1fr; }
        }
        @media(max-width:600px) {
            .admin-stats  { grid-template-columns: 1fr; }
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
        <img
            src="../assets/css/images/pets/logo.png"
            alt="Ligao Petcare Logo"
            style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.6);"
            onerror="this.style.display='none';"
        >
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

            <?php if ($ann_success): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($ann_success) ?></div>
            <?php endif; ?>

            <!-- ── Stat Cards ── -->
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

            <!-- ── Charts ── -->
            <div class="charts-row">

                <!-- Donut — Species -->
                <div class="chart-card">
                    <div class="chart-card-title">Species Breakdown</div>
                    <canvas id="donutChart" height="160"></canvas>
                    <div class="chart-legend">
                        <div class="chart-legend-item">
                            <div class="legend-dot" style="background:#1976d2;"></div>
                            Dog <?= $dogs ? round($dogs/max($total_patients,1)*100) : 0 ?>%
                        </div>
                        <div class="chart-legend-item">
                            <div class="legend-dot" style="background:#b2ebf2;"></div>
                            Cat <?= $cats ? round($cats/max($total_patients,1)*100) : 0 ?>%
                        </div>
                        <?php if ($other > 0): ?>
                        <div class="chart-legend-item">
                            <div class="legend-dot" style="background:#cddc39;"></div>
                            Other <?= round($other/max($total_patients,1)*100) ?>%
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bar — Appointments per Service -->
                <div class="chart-card">
                    <div class="chart-card-title">Appointments by Service</div>
                    <canvas id="barChart" height="160"></canvas>
                </div>

                <!-- Line — Monthly Appointments -->
                <div class="chart-card">
                    <div class="chart-card-title">Monthly Appointments (<?= date('Y') ?>)</div>
                    <canvas id="lineChart" height="160"></canvas>
                </div>

            </div><!-- /charts-row -->

            <!-- ── Announcement ── -->
            <div class="ann-grid">

                <!-- Compose -->
                <div class="ann-compose">
                    <h3>
                        📢 Write / Post Announcement
                        <span style="font-size:12px;color:var(--text-light);font-weight:600;">
                            Broadcasts to all users
                        </span>
                    </h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="post_announcement">
                        <div class="form-group">
                            <label>Announcement Title</label>
                            <input type="text" name="ann_title"
                                   placeholder="e.g. Holiday Closure Notice" required>
                        </div>
                        <div class="form-group">
                            <label>Message</label>
                            <textarea name="ann_content" rows="5"
                                      placeholder="Write your announcement here..."
                                      required
                                      style="resize:vertical;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-teal w-full">
                            📤 Post Announcement
                        </button>
                    </form>
                </div>

                <!-- Announcements List -->
                <div class="ann-list-panel">
                    <h3>📋 Posted Announcements</h3>
                    <?php if (empty($announcements)): ?>
                        <p style="font-size:13px;color:var(--text-light);">
                            No announcements yet.
                        </p>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): ?>
                            <div class="ann-entry">
                                <div>
                                    <a href="?delete_ann=<?= $ann['id'] ?>"
                                       onclick="return confirm('Delete this announcement?')"
                                       class="ann-delete-btn" title="Delete">🗑️</a>
                                    <div class="ann-entry-title">
                                        <?= htmlspecialchars($ann['title']) ?>
                                    </div>
                                    <div class="ann-entry-body">
                                        <?= nl2br(htmlspecialchars(substr($ann['content'], 0, 120)))
                                            . (strlen($ann['content']) > 120 ? '...' : '') ?>
                                    </div>
                                    <div class="ann-entry-meta">
                                        <?= formatDate($ann['created_at']) ?>
                                        · By <?= htmlspecialchars($ann['posted_by_name'] ?? 'Admin') ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div><!-- /ann-grid -->

            <!-- ── Recent Appointments ── -->
            <div class="recent-section">
                <div class="recent-header">
                    <h3>📅 Recent Appointments</h3>
                    <a href="appointments.php" class="btn btn-teal btn-sm">View All</a>
                </div>
                <?php if (empty($recent_appts)): ?>
                    <div class="empty-state" style="padding:24px;">
                        <div class="empty-icon">📭</div>
                        <h3>No appointments yet</h3>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Owner</th>
                                    <th>Pet</th>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_appts as $ra): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($ra['owner_name'] ?? '—') ?></strong></td>
                                        <td><?= htmlspecialchars($ra['pet_name']   ?? '—') ?></td>
                                        <td><?= htmlspecialchars($ra['svc_name']   ?? '—') ?></td>
                                        <td><?= formatDate($ra['appointment_date']) ?></td>
                                        <td><?= formatTime($ra['appointment_time']) ?></td>
                                        <td>
                                            <span style="font-size:11px;background:var(--teal-light);
                                                         color:var(--teal-dark);padding:2px 8px;
                                                         border-radius:20px;font-weight:700;">
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

<?php
// Handle delete announcement
if (isset($_GET['delete_ann']) && is_numeric($_GET['delete_ann'])) {
    $did = (int)$_GET['delete_ann'];
    mysqli_query($conn, "DELETE FROM announcements WHERE id=$did");
    redirect('dashboard.php');
}
?>

<script src="../assets/css/js/main.js"></script>
<script>
// ── Chart.js color palette ───────────────────────────────────
const teal   = '#00bcd4';
const blue   = '#1565c0';
const yellow = '#cddc39';
const light  = '#b2ebf2';

// ── Donut Chart — Species ────────────────────────────────────
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: ['Dog', 'Cat', 'Other'],
        datasets: [{
            data: [<?= $dogs ?>, <?= $cats ?>, <?= $other ?>],
            backgroundColor: [blue, light, yellow],
            borderWidth: 2,
            borderColor: '#fff',
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ` ${ctx.label}: ${ctx.raw} pets`
                }
            }
        },
        cutout: '65%',
    }
});

// ── Bar Chart — Appointments per Service ─────────────────────
const svcLabels = <?= json_encode(array_column($svc_chart, 'name')) ?>;
const svcData   = <?= json_encode(array_column($svc_chart, 'cnt')) ?>;

new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels: svcLabels,
        datasets: [{
            label: 'Appointments',
            data: svcData,
            backgroundColor: blue,
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, font: { size: 11 } },
                grid: { color: 'rgba(0,0,0,0.05)' }
            },
            x: {
                ticks: { font: { size: 10 } },
                grid: { display: false }
            }
        }
    }
});

// ── Line Chart — Monthly ─────────────────────────────────────
const months     = ['Jan','Feb','Mar','Apr','May','Jun',
                    'Jul','Aug','Sep','Oct','Nov','Dec'];
const monthlyRaw = <?= json_encode(array_values($monthly_data)) ?>;

new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: months,
        datasets: [{
            label: 'Appointments',
            data: monthlyRaw,
            borderColor: blue,
            backgroundColor: 'rgba(21,101,192,0.08)',
            borderWidth: 2.5,
            tension: 0.4,
            fill: true,
            pointBackgroundColor: blue,
            pointRadius: 4,
            pointHoverRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, font: { size: 11 } },
                grid: { color: 'rgba(0,0,0,0.05)' }
            },
            x: {
                ticks: { font: { size: 10 } },
                grid: { display: false }
            }
        }
    }
});
</script>
</body>
</html>