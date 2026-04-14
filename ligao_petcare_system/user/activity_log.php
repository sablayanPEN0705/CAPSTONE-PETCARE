<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/activity_log.php
// Purpose: Show the current user their own activity history
// ============================================================
require_once '../includes/auth.php';
require_once '../includes/activity_log.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id = (int)$_SESSION['user_id'];

// ── Filter ───────────────────────────────────────────────────
$module = $_GET['module'] ?? 'all';
$where  = "user_id = $user_id";
if ($module !== 'all') {
    $safe_module = mysqli_real_escape_string($conn, $module);
    $where .= " AND module = '$safe_module'";
}

// ── Pagination ───────────────────────────────────────────────
$per_page    = 15;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$total       = countRows($conn, 'activity_logs', $where);
$total_pages = max(1, (int)ceil($total / $per_page));

$logs = getRows($conn,
    "SELECT * FROM activity_logs
     WHERE $where
     ORDER BY created_at DESC
     LIMIT $per_page OFFSET $offset"
);

// ── Count by module for tabs ──────────────────────────────────
$mod_counts = [];
$res = mysqli_query($conn,
    "SELECT module, COUNT(*) AS cnt
     FROM activity_logs WHERE user_id=$user_id GROUP BY module");
while ($row = mysqli_fetch_assoc($res)) {
    $mod_counts[$row['module']] = (int)$row['cnt'];
}
$total_all = array_sum($mod_counts);

// ── Helpers ───────────────────────────────────────────────────
$module_meta = [
    'auth'         => ['🔐', 'Login & Auth'],
    'settings'     => ['⚙️',  'Settings'],
    'appointments' => ['📅', 'Appointments'],
    'pets'         => ['🐾', 'Pets'],
    'messages'     => ['💬', 'Messages'],
    'transactions' => ['🧾', 'Transactions'],
    'general'      => ['🔔', 'General'],
];

function moduleIcon(string $m): string {
    global $module_meta;
    return $module_meta[$m][0] ?? '🔔';
}
function moduleLabel(string $m): string {
    global $module_meta;
    return $module_meta[$m][1] ?? ucfirst($m);
}
function actionColor(string $action): string {
    if (str_contains($action, 'login'))   return '#10b981'; // green
    if (str_contains($action, 'logout'))  return '#6b7280'; // gray
    if (str_contains($action, 'delete') || str_contains($action, 'cancel'))
                                          return '#ef4444'; // red
    if (str_contains($action, 'create') || str_contains($action, 'add') || str_contains($action, 'book'))
                                          return '#3b82f6'; // blue
    if (str_contains($action, 'update') || str_contains($action, 'edit') || str_contains($action, 'change'))
                                          return '#f59e0b'; // amber
    return '#8b5cf6'; // purple default
}
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60).'m ago';
    if ($diff < 86400)  return floor($diff/3600).'h ago';
    if ($diff < 604800) return floor($diff/86400).'d ago';
    return date('M j, Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Page header ── */
        .al-header {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 20px 24px;
            margin-bottom: 18px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .al-header h2 {
            font-family: var(--font-head);
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .al-total-badge {
            background: var(--teal);
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            padding: 2px 12px;
            border-radius: 20px;
        }

        /* ── Filter tabs ── */
        .al-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .al-tab {
            padding: 7px 14px;
            border-radius: var(--radius-lg);
            font-size: 12px;
            font-weight: 700;
            border: 2px solid var(--border);
            background: rgba(255,255,255,0.85);
            color: var(--text-mid);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }
        .al-tab:hover, .al-tab.active {
            background: var(--teal);
            color: #fff;
            border-color: var(--teal);
        }
        .al-tab .cnt {
            background: rgba(0,0,0,0.12);
            border-radius: 10px;
            padding: 1px 6px;
            font-size: 11px;
        }
        .al-tab.active .cnt { background: rgba(255,255,255,0.28); }

        /* ── Log list ── */
        .al-list {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .al-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }
        .al-item:last-child { border-bottom: none; }
        .al-item:hover { background: rgba(0,188,212,0.05); }

        /* Timeline dot */
        .al-dot-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 32px;
            flex-shrink: 0;
            padding-top: 4px;
        }
        .al-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px currentColor;
            flex-shrink: 0;
        }
        .al-line {
            width: 2px;
            background: var(--border);
            flex: 1;
            margin-top: 4px;
            min-height: 18px;
        }
        .al-item:last-child .al-line { display: none; }

        /* Content */
        .al-content { flex: 1; min-width: 0; }
        .al-row1 {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }
        .al-action {
            font-size: 13px;
            font-weight: 800;
            color: var(--text-dark);
        }
        .al-module-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            background: rgba(0,188,212,0.12);
            color: var(--teal-dark);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .al-desc {
            font-size: 13px;
            color: var(--text-mid);
            line-height: 1.55;
            margin-bottom: 6px;
        }
        .al-meta {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            font-size: 11px;
            color: var(--text-light);
            font-weight: 600;
        }

        /* Empty state */
        .al-empty { text-align: center; padding: 60px 20px; }
        .al-empty .ei { font-size: 52px; margin-bottom: 12px; }
        .al-empty h3 { font-size: 17px; font-weight: 800; color: var(--text-mid); }

        /* Pagination */
        .page-nav { display: flex; justify-content: center; gap: 6px; margin-top: 16px; }
        .page-nav a, .page-nav span {
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px; font-weight: 700;
            background: rgba(255,255,255,0.85);
            color: var(--text-dark); text-decoration: none;
            transition: var(--transition);
        }
        .page-nav a:hover  { background: var(--teal-light); }
        .page-nav .current { background: var(--teal); color: #fff; }
        .page-nav .disabled { opacity: 0.4; pointer-events: none; }

        @media(max-width:600px) {
            .al-item { padding: 12px 14px; }
        }
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
                Activity Log
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔</a>
                <a href="settings.php"      class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <!-- Header -->
            <div class="al-header">
                <h2>
                    📋 My Activity Log
                    <span class="al-total-badge"><?= $total_all ?> total</span>
                </h2>
                <p style="font-size:13px;color:var(--text-light);margin:0;">
                    A record of all actions on your account.
                </p>
            </div>

            <!-- Filter Tabs -->
            <div class="al-tabs">
                <a href="?module=all"
                   class="al-tab <?= $module === 'all' ? 'active' : '' ?>">
                    🔔 All <span class="cnt"><?= $total_all ?></span>
                </a>
                <?php foreach ($module_meta as $slug => [$icon, $label]): ?>
                    <?php $cnt = $mod_counts[$slug] ?? 0; if ($cnt === 0) continue; ?>
                    <a href="?module=<?= $slug ?>"
                       class="al-tab <?= $module === $slug ? 'active' : '' ?>">
                        <?= $icon ?> <?= $label ?> <span class="cnt"><?= $cnt ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Log List -->
            <?php if (empty($logs)): ?>
                <div class="al-list">
                    <div class="al-empty">
                        <div class="ei">📋</div>
                        <h3>No activity recorded yet</h3>
                        <p style="font-size:13px;color:var(--text-light);margin-top:6px;">
                            Actions like logins, bookings, and profile updates will appear here.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <div class="al-list">
                    <?php foreach ($logs as $log):
                        $color = actionColor($log['action']);
                    ?>
                    <div class="al-item">
                        <!-- Timeline dot -->
                        <div class="al-dot-col">
                            <div class="al-dot" style="color:<?= $color ?>; background:<?= $color ?>;"></div>
                            <div class="al-line"></div>
                        </div>

                        <!-- Content -->
                        <div class="al-content">
                            <div class="al-row1">
                                <span class="al-action">
                                    <?= moduleIcon($log['module']) ?>
                                    <?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?>
                                </span>
                                <span class="al-module-badge">
                                    <?= moduleLabel($log['module']) ?>
                                </span>
                            </div>
                            <div class="al-desc"><?= htmlspecialchars($log['description']) ?></div>
                            <div class="al-meta">
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
                            <a href="?module=<?= $module ?>&page=<?= $page-1 ?>">‹ Prev</a>
                        <?php else: ?>
                            <span class="disabled">‹ Prev</span>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <?php if ($p === $page): ?>
                                <span class="current"><?= $p ?></span>
                            <?php else: ?>
                                <a href="?module=<?= $module ?>&page=<?= $p ?>"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?module=<?= $module ?>&page=<?= $page+1 ?>">Next ›</a>
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
<?php include_once __DIR__ . '/../includes/chatbot-widget.php'; ?>
</body>
</html>