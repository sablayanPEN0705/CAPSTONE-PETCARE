<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/announcements.php
// Purpose: View all clinic announcements
// ============================================================
require_once '../includes/auth.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id = (int)$_SESSION['user_id'];

// ── Search ───────────────────────────────────────────────────
$search = sanitize($conn, $_GET['search'] ?? '');
$where  = '1';
if ($search) $where .= " AND (a.title LIKE '%$search%' OR a.content LIKE '%$search%')";

// ── Pagination ───────────────────────────────────────────────
$per_page    = 6;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$total       = countRows($conn, 'announcements', $search
    ? "(title LIKE '%$search%' OR content LIKE '%$search%')"
    : '1');
$total_pages = max(1, ceil($total / $per_page));

$announcements = getRows($conn,
    "SELECT a.*, u.name AS posted_by_name
     FROM announcements a
     LEFT JOIN users u ON a.posted_by = u.id
     WHERE $where
     ORDER BY a.created_at DESC
     LIMIT $per_page OFFSET $offset"
);

// ── Mark all notifications as read ──────────────────────────
mysqli_query($conn,
    "UPDATE notifications SET is_read=1
     WHERE user_id=$user_id AND type='announcement' AND is_read=0"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .ann-hero {
            background: linear-gradient(135deg, #1565c0, #00bcd4);
            border-radius: var(--radius);
            padding: 28px 32px;
            margin-bottom: 24px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow);
        }

        .ann-hero-icon { font-size: 48px; flex-shrink: 0; }

        .ann-hero-text h2 {
            font-family: var(--font-head);
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .ann-hero-text p {
            font-size: 13px;
            opacity: 0.85;
        }

        /* Cards grid */
        .ann-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .ann-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 22px 24px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--teal);
            transition: var(--transition);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .ann-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-left-color: var(--blue-header);
        }

        .ann-card-tag {
            font-size: 11px;
            font-weight: 800;
            color: var(--teal-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ann-card-title {
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.3;
        }

        .ann-card-body {
            font-size: 13px;
            color: var(--text-mid);
            line-height: 1.7;
            font-weight: 500;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .ann-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 6px;
            padding-top: 10px;
            border-top: 1px solid var(--border);
        }

        .ann-card-meta {
            font-size: 11px;
            color: var(--text-light);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .ann-read-more {
            font-size: 12px;
            font-weight: 700;
            color: var(--teal-dark);
            background: var(--teal-light);
            padding: 4px 12px;
            border-radius: var(--radius-lg);
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }
        .ann-read-more:hover {
            background: var(--teal);
            color: #fff;
        }

        /* Modal content */
        .ann-modal-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
        }

        .ann-modal-icon {
            width: 48px; height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal-light), #b2ebf2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .ann-modal-body {
            font-size: 14px;
            color: var(--text-mid);
            line-height: 1.8;
            white-space: pre-wrap;
            background: #f0fffe;
            border-radius: var(--radius-sm);
            padding: 16px;
            border-left: 3px solid var(--teal);
        }

        /* Empty state */
        .ann-empty {
            text-align: center;
            padding: 64px 20px;
        }
        .ann-empty .ae-icon { font-size: 64px; margin-bottom: 16px; }
        .ann-empty h3 { font-size: 20px; font-weight: 800; color: var(--text-mid); margin-bottom: 6px; }
        .ann-empty p  { font-size: 13px; color: var(--text-light); }

        /* Pagination */
        .page-nav {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 8px;
        }
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
        .page-nav a:hover    { background: var(--teal-light); color: var(--teal-dark); }
        .page-nav .current   { background: var(--teal); color: #fff; }
        .page-nav .disabled  { opacity: 0.4; pointer-events: none; }

        @media(max-width:600px) {
            .ann-grid { grid-template-columns: 1fr; }
            .ann-hero { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_user.php'; ?>

    <div class="main-content">

        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-title" style="display:flex; align-items:center; gap:10px;">
                <img src="../assets/css/images/pets/logo.png" alt="Logo"
                     style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.6);"
                     onerror="this.style.display='none';">
                Announcements
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔</a>
                <a href="settings.php"      class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <!-- Hero Banner -->
            <div class="ann-hero">
                <div class="ann-hero-icon">📢</div>
                <div class="ann-hero-text">
                    <h2>Clinic Announcements</h2>
                    <p>Stay updated with the latest news, promos, and notices from Ligao Petcare & Veterinary Clinic.</p>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="filter-bar" style="margin-bottom:20px;">
                <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;width:100%;">
                    <div class="search-box" style="flex:1;max-width:400px;">
                        <span>🔍</span>
                        <input type="text" name="search"
                               placeholder="Search announcements..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <?php if ($search): ?>
                        <a href="announcements.php" class="btn btn-gray btn-sm">✕ Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if ($search): ?>
                <p style="font-size:13px;color:var(--text-light);margin-bottom:16px;font-weight:600;">
                    Showing results for "<strong><?= htmlspecialchars($search) ?></strong>"
                    — <?= $total ?> found
                </p>
            <?php endif; ?>

            <!-- Announcements Grid -->
            <?php if (empty($announcements)): ?>
                <div class="ann-empty">
                    <div class="ae-icon">📭</div>
                    <h3><?= $search ? 'No results found' : 'No Announcements Yet' ?></h3>
                    <p><?= $search
                        ? 'Try a different search term.'
                        : 'Check back later for updates from the clinic.' ?>
                    </p>
                    <?php if ($search): ?>
                        <a href="announcements.php" class="btn btn-teal btn-sm" style="margin-top:14px;">
                            View All Announcements
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="ann-grid">
                    <?php foreach ($announcements as $ann): ?>
                        <div class="ann-card"
                             onclick="openAnnModal(
                                 <?= $ann['id'] ?>,
                                 '<?= htmlspecialchars($ann['title'], ENT_QUOTES) ?>',
                                 `<?= addslashes(nl2br(htmlspecialchars($ann['content']))) ?>`,
                                 '<?= htmlspecialchars($ann['posted_by_name'] ?? 'Admin', ENT_QUOTES) ?>',
                                 '<?= formatDate($ann['created_at']) ?>'
                             )">
                            <div class="ann-card-tag">
                                <span>📌</span> Attention Pet Owners!
                            </div>
                            <div class="ann-card-title">
                                <?= htmlspecialchars($ann['title']) ?>
                            </div>
                            <div class="ann-card-body">
                                <?= htmlspecialchars($ann['content']) ?>
                            </div>
                            <div class="ann-card-footer">
                                <div class="ann-card-meta">
                                    <span>📅 <?= formatDate($ann['created_at']) ?></span>
                                    <span>· By <?= htmlspecialchars($ann['posted_by_name'] ?? 'Admin') ?></span>
                                </div>
                                <button class="ann-read-more" onclick="event.stopPropagation();"
                                        onclick="openAnnModal(<?= $ann['id'] ?>)">
                                    Read More
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="page-nav">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">‹ Prev</a>
                        <?php else: ?>
                            <span class="disabled">‹ Prev</span>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <?php if ($p === $page): ?>
                                <span class="current"><?= $p ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">Next ›</a>
                        <?php else: ?>
                            <span class="disabled">Next ›</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div><!-- /page-body -->
    </div>
</div>

<!-- Announcement Detail Modal -->
<div class="modal-overlay" id="annModal">
    <div class="modal" style="max-width:560px;">
        <button class="modal-close" onclick="closeModal('annModal')">×</button>

        <div class="ann-modal-header">
            <div class="ann-modal-icon">📢</div>
            <div>
                <div style="font-size:11px;font-weight:800;color:var(--teal-dark);
                            text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">
                    Attention Pet Owners!
                </div>
                <h3 class="modal-title" id="ann-modal-title"
                    style="text-align:left;margin-bottom:0;font-size:18px;"></h3>
            </div>
        </div>

        <div class="ann-modal-body" id="ann-modal-body"></div>

        <div style="display:flex;align-items:center;justify-content:space-between;
                    margin-top:16px;font-size:12px;color:var(--text-light);font-weight:600;">
            <span id="ann-modal-meta"></span>
            <button class="btn btn-teal btn-sm" onclick="closeModal('annModal')">Close</button>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function openAnnModal(id, title, body, author, date) {
    document.getElementById('ann-modal-title').textContent = title;
    document.getElementById('ann-modal-body').innerHTML    = body;
    document.getElementById('ann-modal-meta').textContent  = '📅 ' + date + ' · By ' + author;
    openModal('annModal');
}
</script>
</body>
</html>