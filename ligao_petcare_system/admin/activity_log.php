<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/activity_log.php
// Purpose: Admin-wide activity log viewer — all users + admin
// ============================================================
require_once '../includes/auth.php';
require_once '../includes/activity_log.php';
requireLogin();
requireAdmin();

// ── Build WHERE ───────────────────────────────────────────────
$filter_role   = $_GET['role']   ?? 'all';
$filter_module = $_GET['module'] ?? 'all';
$filter_user   = (int)($_GET['user_id'] ?? 0);
$search        = trim($_GET['search'] ?? '');
$date_from     = $_GET['date_from'] ?? '';
$date_to       = $_GET['date_to']   ?? '';

$conditions = [];

if ($filter_role !== 'all') {
    $safe = mysqli_real_escape_string($conn, $filter_role);
    $conditions[] = "role = '$safe'";
}
if ($filter_module !== 'all') {
    $safe = mysqli_real_escape_string($conn, $filter_module);
    $conditions[] = "module = '$safe'";
}
if ($filter_user > 0) {
    $conditions[] = "user_id = $filter_user";
}
if ($search !== '') {
    $safe = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(description LIKE '%$safe%' OR action LIKE '%$safe%')";
}
if ($date_from !== '') {
    $safe = mysqli_real_escape_string($conn, $date_from);
    $conditions[] = "DATE(created_at) >= '$safe'";
}
if ($date_to !== '') {
    $safe = mysqli_real_escape_string($conn, $date_to);
    $conditions[] = "DATE(created_at) <= '$safe'";
}

$where = $conditions ? implode(' AND ', $conditions) : '1';

// ── Pagination ────────────────────────────────────────────────
$per_page    = 20;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$total       = countRows($conn, 'activity_logs', $where);
$total_pages = max(1, (int)ceil($total / $per_page));

$logs = getRows($conn,
    "SELECT al.*, u.name AS user_name, u.email AS user_email
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     WHERE $where
     ORDER BY al.created_at DESC
     LIMIT $per_page OFFSET $offset"
);

// ── Summary counts for stat cards ────────────────────────────
$cnt_today   = countRows($conn, 'activity_logs', "DATE(created_at) = CURDATE()");
$cnt_admin   = countRows($conn, 'activity_logs', "role = 'admin'");
$cnt_users   = countRows($conn, 'activity_logs', "role = 'user'");
$cnt_total   = countRows($conn, 'activity_logs', '1');

// ── Module counts for sidebar tabs ───────────────────────────
$mod_res = mysqli_query($conn,
    "SELECT module, COUNT(*) AS cnt FROM activity_logs GROUP BY module ORDER BY cnt DESC");
$mod_counts = [];
while ($r = mysqli_fetch_assoc($mod_res)) {
    $mod_counts[$r['module']] = (int)$r['cnt'];
}

// ── User list for filter dropdown ────────────────────────────
$users_res = mysqli_query($conn,
    "SELECT DISTINCT al.user_id, u.name, u.role
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     ORDER BY u.name ASC");
$user_options = [];
while ($r = mysqli_fetch_assoc($users_res)) {
    $user_options[] = $r;
}

// ── Helpers ───────────────────────────────────────────────────
$module_meta = [
    'auth'         => ['🔐', 'Auth'],
    'settings'     => ['⚙️',  'Settings'],
    'appointments' => ['📅', 'Appointments'],
    'pets'         => ['🐾', 'Pets'],
    'messages'     => ['💬', 'Messages'],
    'transactions' => ['🧾', 'Transactions'],
    'staff'        => ['👥', 'Staff'],
    'inventory'    => ['📦', 'Inventory'],
    'announcements'=> ['📢', 'Announcements'],
    'notifications'=> ['🔔', 'Notifications'],
    'general'      => ['🔔', 'General'],
];

function modIcon(string $m): string  { global $module_meta; return $module_meta[$m][0] ?? '🔔'; }
function modLabel(string $m): string { global $module_meta; return $module_meta[$m][1] ?? ucfirst($m); }

function actionColor(string $a): string {
    if (str_contains($a, 'login'))   return '#10b981';
    if (str_contains($a, 'logout'))  return '#6b7280';
    if (str_contains($a, 'delete') || str_contains($a, 'cancel')) return '#ef4444';
    if (str_contains($a, 'create') || str_contains($a, 'add'))    return '#3b82f6';
    if (str_contains($a, 'update') || str_contains($a, 'edit') || str_contains($a, 'change')) return '#f59e0b';
    return '#8b5cf6';
}

function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60).'m ago';
    if ($diff < 86400)  return floor($diff/3600).'h ago';
    if ($diff < 604800) return floor($diff/86400).'d ago';
    return date('M j, Y', strtotime($dt));
}

function roleBadge(string $role): string {
    $map = [
        'admin' => ['#1565c0','#dbeafe','Admin'],
        'staff' => ['#065f46','#d1fae5','Staff'],
        'user'  => ['#5b21b6','#ede9fe','User'],
    ];
    [$color,$bg,$lbl] = $map[$role] ?? ['#374151','#f3f4f6', ucfirst($role)];
    return "<span style=\"background:$bg;color:$color;font-size:10px;font-weight:800;
             padding:2px 8px;border-radius:20px;text-transform:uppercase;\">$lbl</span>";
}

// Build pagination query string (preserves all filters)
function pageQS(int $p): string {
    $qs = $_GET;
    $qs['page'] = $p;
    return '?' . http_build_query($qs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log — Admin — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Stat cards ── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 18px 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .stat-val  { font-family: var(--font-head); font-size: 28px; font-weight: 800; line-height: 1; }
        .stat-lbl  { font-size: 12px; color: var(--text-light); font-weight: 700; margin-top: 3px; }

        /* ── Filters panel ── */
        .filters-panel {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 18px 22px;
            margin-bottom: 18px;
            box-shadow: var(--shadow);
        }
        .filters-panel h4 {
            font-size: 13px; font-weight: 800;
            color: var(--text-dark); margin-bottom: 12px;
            display: flex; align-items: center; gap:6px;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: end;
        }
        .filters-grid label { font-size: 11px; font-weight: 700; color: var(--text-light); display: block; margin-bottom: 4px; }
        .filters-grid input,
        .filters-grid select {
            width: 100%;
            padding: 8px 10px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            background: #fff;
            color: var(--text-dark);
        }

        /* ── Header strip ── */
        .log-header {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 14px;
            box-shadow: var(--shadow);
            display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap; gap: 12px;
        }
        .log-header h2 {
            font-family: var(--font-head); font-size: 18px; font-weight: 800;
            display: flex; align-items: center; gap: 10px;
        }
        .total-badge {
            background: var(--teal); color: #fff;
            font-size: 11px; font-weight: 800;
            padding: 2px 10px; border-radius: 20px;
        }

        /* ── Log list ── */
        .al-list {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden;
        }
        .al-item {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 14px 20px; border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }
        .al-item:last-child { border-bottom: none; }
        .al-item:hover { background: rgba(0,188,212,0.05); }

        /* Timeline dot */
        .al-dot-col {
            display: flex; flex-direction: column; align-items: center;
            width: 28px; flex-shrink: 0; padding-top: 4px;
        }
        .al-dot { width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0; }
        .al-line { width: 2px; background: var(--border); flex: 1; margin-top: 4px; min-height: 16px; }
        .al-item:last-child .al-line { display: none; }

        /* Content */
        .al-content { flex: 1; min-width: 0; }
        .al-row1 { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 3px; }
        .al-action { font-size: 13px; font-weight: 800; color: var(--text-dark); }
        .al-mod-badge {
            font-size: 10px; font-weight: 700; padding: 2px 7px;
            border-radius: 20px; background: rgba(0,188,212,0.12);
            color: var(--teal-dark); text-transform: uppercase; letter-spacing: 0.3px;
        }
        .al-desc { font-size: 13px; color: var(--text-mid); line-height: 1.5; margin-bottom: 5px; }
        .al-meta { display: flex; gap: 12px; flex-wrap: wrap; font-size: 11px; color: var(--text-light); font-weight: 600; }

        /* User avatar chip */
        .user-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--teal-light); color: var(--teal-dark);
            font-size: 11px; font-weight: 700;
            padding: 3px 10px; border-radius: 20px;
        }

        /* Empty */
        .al-empty { text-align: center; padding: 56px 20px; }
        .al-empty .ei { font-size: 52px; margin-bottom: 12px; }
        .al-empty h3 { font-size: 17px; font-weight: 800; color: var(--text-mid); }

        /* Pagination */
        .page-nav { display: flex; justify-content: center; gap: 6px; margin-top: 16px; flex-wrap: wrap; }
        .page-nav a, .page-nav span {
            padding: 7px 14px; border-radius: var(--radius-sm);
            font-size: 13px; font-weight: 700;
            background: rgba(255,255,255,0.85); color: var(--text-dark);
            text-decoration: none; transition: var(--transition);
        }
        .page-nav a:hover  { background: var(--teal-light); }
        .page-nav .current { background: var(--teal); color: #fff; }
        .page-nav .disabled { opacity: 0.4; pointer-events: none; }

        @media(max-width:900px) { .stat-row { grid-template-columns: 1fr 1fr; } }
        @media(max-width:700px) { .filters-grid { grid-template-columns: 1fr 1fr; } }
        @media(max-width:500px) {
            .stat-row { grid-template-columns: 1fr 1fr; }
            .al-item  { padding: 12px 14px; }
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
                Activity Log
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn" title="Notifications">
                    🔔
                    <?php $un = getUnreadNotifications($conn, $_SESSION['user_id']); if ($un > 0): ?>
                        <span class="badge-count"><?= $un ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="topbar-btn" title="Settings">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <!-- Stat Cards -->
            <div class="stat-row">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#dbeafe;">📋</div>
                    <div>
                        <div class="stat-val"><?= number_format($cnt_total) ?></div>
                        <div class="stat-lbl">Total Events</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#d1fae5;">📅</div>
                    <div>
                        <div class="stat-val"><?= number_format($cnt_today) ?></div>
                        <div class="stat-lbl">Today</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fef3c7;">🛡️</div>
                    <div>
                        <div class="stat-val"><?= number_format($cnt_admin) ?></div>
                        <div class="stat-lbl">Admin/Staff Actions</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#ede9fe;">👤</div>
                    <div>
                        <div class="stat-val"><?= number_format($cnt_users) ?></div>
                        <div class="stat-lbl">User Actions</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-panel">
                <h4>🔍 Filter Logs</h4>
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div>
                            <label>Search</label>
                            <input type="text" name="search"
                                   value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Description or action…">
                        </div>
                        <div>
                            <label>Role</label>
                            <select name="role">
                                <option value="all" <?= $filter_role==='all'?'selected':'' ?>>All Roles</option>
                                <option value="admin" <?= $filter_role==='admin'?'selected':'' ?>>Admin</option>
                                <option value="staff" <?= $filter_role==='staff'?'selected':'' ?>>Staff</option>
                                <option value="user"  <?= $filter_role==='user' ?'selected':'' ?>>User</option>
                            </select>
                        </div>
                        <div>
                            <label>Module</label>
                            <select name="module">
                                <option value="all">All Modules</option>
                                <?php foreach ($mod_counts as $mod => $cnt): ?>
                                    <option value="<?= htmlspecialchars($mod) ?>"
                                        <?= $filter_module===$mod?'selected':'' ?>>
                                        <?= modLabel($mod) ?> (<?= $cnt ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Date From</label>
                            <input type="date" name="date_from"
                                   value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        <div>
                            <label>Date To</label>
                            <input type="date" name="date_to"
                                   value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-teal"
                                    style="width:100%;padding:9px 0;">Filter</button>
                        </div>
                    </div>
                    <?php if ($search || $filter_role!=='all' || $filter_module!=='all' || $date_from || $date_to || $filter_user): ?>
                        <div style="margin-top:10px;">
                            <a href="activity_log.php" style="font-size:12px;color:var(--teal-dark);font-weight:700;">
                                × Clear filters
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Log Header -->
            <div class="log-header">
                <h2>
                    📋 Activity Log
                    <span class="total-badge"><?= number_format($total) ?> record<?= $total!==1?'s':'' ?></span>
                </h2>
                <span style="font-size:13px;color:var(--text-light);">
                    Page <?= $page ?> of <?= $total_pages ?>
                </span>
            </div>

            <!-- List -->
            <?php if (empty($logs)): ?>
                <div class="al-list">
                    <div class="al-empty">
                        <div class="ei">📋</div>
                        <h3>No activity found</h3>
                        <p style="font-size:13px;color:var(--text-light);margin-top:6px;">
                            Try adjusting your filters.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="al-list">
                    <?php foreach ($logs as $log):
                        $color = actionColor($log['action']);
                    ?>
                    <div class="al-item">
                        <div class="al-dot-col">
                            <div class="al-dot" style="background:<?= $color ?>;"></div>
                            <div class="al-line"></div>
                        </div>
                        <div class="al-content">
                            <div class="al-row1">
                                <span class="al-action">
                                    <?= modIcon($log['module']) ?>
                                    <?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?>
                                </span>
                                <span class="al-mod-badge"><?= modLabel($log['module']) ?></span>
                                <?= roleBadge($log['role']) ?>
                            </div>
                            <div class="al-desc"><?= htmlspecialchars($log['description']) ?></div>
                            <div class="al-meta">
                                <!-- Actor -->
                                <span class="user-chip">
                                    👤 <?= htmlspecialchars($log['user_name'] ?? 'Unknown') ?>
                                    <?php if ($log['user_email']): ?>
                                        · <?= htmlspecialchars($log['user_email']) ?>
                                    <?php endif; ?>
                                </span>
                                <span>🕐 <?= timeAgo($log['created_at']) ?></span>
                                <span><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></span>
                                <?php if ($log['ip_address']): ?>
                                    <span>🌐 <?= htmlspecialchars($log['ip_address']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="page-nav">
                        <?php if ($page > 1): ?>
                            <a href="<?= pageQS($page-1) ?>">‹ Prev</a>
                        <?php else: ?>
                            <span class="disabled">‹ Prev</span>
                        <?php endif; ?>
                        <?php
                        $start = max(1, $page-2);
                        $end   = min($total_pages, $page+2);
                        if ($start > 1) echo '<span>…</span>';
                        for ($p = $start; $p <= $end; $p++):
                        ?>
                            <?php if ($p === $page): ?>
                                <span class="current"><?= $p ?></span>
                            <?php else: ?>
                                <a href="<?= pageQS($p) ?>"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($end < $total_pages) echo '<span>…</span>'; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="<?= pageQS($page+1) ?>">Next ›</a>
                        <?php else: ?>
                            <span class="disabled">Next ›</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>