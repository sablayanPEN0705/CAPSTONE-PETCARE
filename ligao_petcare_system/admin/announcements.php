<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/announcements.php
// Purpose: Admin — post, edit, delete, and view all announcements
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$admin_id = (int)$_SESSION['user_id'];
$success  = '';
$error    = '';

// ── Handle POST Announcement ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $title   = sanitize($conn, trim($_POST['ann_title']   ?? ''));
    $content = sanitize($conn, trim($_POST['ann_content'] ?? ''));

    if ($action === 'add') {
        if (empty($title) || empty($content)) {
            $error = 'Title and content are required.';
        } else {
            mysqli_query($conn,
                "INSERT INTO announcements (title, content, posted_by)
                 VALUES ('$title', '$content', $admin_id)"
            );
            // Notify all users
            $all_users = getRows($conn, "SELECT id FROM users WHERE role='user'");
            foreach ($all_users as $u) {
                $uid = (int)$u['id'];
                mysqli_query($conn,
                    "INSERT INTO notifications (user_id, title, message, type)
                     VALUES ($uid, 'New Announcement', '$title', 'announcement')"
                );
            }
            $success = 'Announcement posted successfully and all users notified!';
        }
    }

    if ($action === 'edit') {
        $aid = (int)($_POST['ann_id'] ?? 0);
        if (empty($title) || empty($content)) {
            $error = 'Title and content are required.';
        } elseif ($aid) {
            mysqli_query($conn,
                "UPDATE announcements SET title='$title', content='$content'
                 WHERE id=$aid"
            );
            $success = 'Announcement updated successfully!';
        }
    }
}

// ── Handle Delete ────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM announcements WHERE id=$did");
    header('Location: announcements.php?success=deleted');
    exit();
}

if (isset($_GET['success']) && $_GET['success'] === 'deleted') {
    $success = 'Announcement deleted successfully.';
}

// ── Search & Pagination ──────────────────────────────────────
$search      = sanitize($conn, $_GET['search'] ?? '');
$per_page    = 8;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$where       = '1';
if ($search) $where .= " AND (a.title LIKE '%$search%' OR a.content LIKE '%$search%')";

$total       = countRows($conn, 'announcements',
    $search ? "(title LIKE '%$search%' OR content LIKE '%$search%')" : '1');
$total_pages = max(1, ceil($total / $per_page));

$announcements = getRows($conn,
    "SELECT a.*, u.name AS posted_by_name
     FROM announcements a
     LEFT JOIN users u ON a.posted_by = u.id
     WHERE $where
     ORDER BY a.created_at DESC
     LIMIT $per_page OFFSET $offset"
);

// ── Fetch single for edit ────────────────────────────────────
$edit_ann = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_ann = getRow($conn,
        "SELECT * FROM announcements WHERE id=" . (int)$_GET['edit']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements — Admin — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .page-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 20px;
            align-items: start;
        }

        /* Compose Card */
        .compose-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            position: sticky;
            top: 80px;
        }

        .compose-card h3 {
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .compose-card textarea {
            resize: vertical;
            min-height: 130px;
        }

        .char-count {
            font-size: 11px;
            color: var(--text-light);
            text-align: right;
            margin-top: 4px;
        }

        .broadcast-note {
            background: #e0f7fa;
            border: 1px solid var(--teal-light);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            font-size: 12px;
            color: var(--teal-dark);
            font-weight: 600;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Stats row */
        .ann-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .ann-stat-box {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 16px;
            text-align: center;
            box-shadow: var(--shadow);
            border-top: 3px solid var(--teal);
        }
        .ann-stat-box:nth-child(2) { border-top-color: var(--blue-header); }
        .ann-stat-box:nth-child(3) { border-top-color: var(--yellow-dark); }

        .asb-value {
            font-family: var(--font-head);
            font-size: 32px;
            font-weight: 800;
            color: var(--text-dark);
        }
        .asb-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        /* Announcements list panel */
        .ann-list-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
        }

        .ann-list-card h3 {
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Announcement row */
        .ann-row {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 16px 18px;
            margin-bottom: 12px;
            transition: var(--transition);
            border-left: 4px solid var(--teal);
        }
        .ann-row:hover { box-shadow: var(--shadow); }
        .ann-row:last-child { margin-bottom: 0; }

        .ann-row-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 8px;
        }

        .ann-row-title {
            font-weight: 800;
            font-size: 14px;
            color: var(--text-dark);
            line-height: 1.3;
        }

        .ann-row-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }

        .ann-row-body {
            font-size: 13px;
            color: var(--text-mid);
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .ann-row-meta {
            font-size: 11px;
            color: var(--text-light);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Pagination */
        .page-nav {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 16px;
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
        .page-nav a:hover   { background: var(--teal-light); color: var(--teal-dark); }
        .page-nav .current  { background: var(--teal); color: #fff; }
        .page-nav .disabled { opacity: 0.4; pointer-events: none; }

        /* Modal preview */
        .ann-preview-body {
            font-size: 14px;
            color: var(--text-mid);
            line-height: 1.8;
            white-space: pre-wrap;
            background: #f0fffe;
            border-radius: var(--radius-sm);
            padding: 16px;
            border-left: 3px solid var(--teal);
            margin-top: 12px;
        }

        @media(max-width:900px) {
            .page-layout  { grid-template-columns: 1fr; }
            .ann-stats    { grid-template-columns: 1fr 1fr; }
            .compose-card { position: static; }
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
                     style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.6);"
                     onerror="this.style.display='none';">
                Announcements
            </div>
            <div class="topbar-actions">
                <a href="messages.php" class="topbar-btn">💬</a>
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Stats Row -->
            <?php
            $total_ann    = countRows($conn, 'announcements', '1');
            $ann_this_month = countRows($conn, 'announcements',
                "MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
            $total_users  = countRows($conn, 'users', "role='user'");
            ?>
            <div class="ann-stats">
                <div class="ann-stat-box">
                    <div class="asb-value"><?= $total_ann ?></div>
                    <div class="asb-label">Total Announcements</div>
                </div>
                <div class="ann-stat-box">
                    <div class="asb-value"><?= $ann_this_month ?></div>
                    <div class="asb-label">This Month</div>
                </div>
                <div class="ann-stat-box">
                    <div class="asb-value"><?= $total_users ?></div>
                    <div class="asb-label">Users Notified</div>
                </div>
            </div>

            <div class="page-layout">

                <!-- LEFT: Compose / Edit Form -->
                <div class="compose-card">
                    <h3>
                        <?= $edit_ann ? '✏️ Edit Announcement' : '📢 Post Announcement' ?>
                        <?php if ($edit_ann): ?>
                            <a href="announcements.php"
                               class="btn btn-gray btn-sm" style="font-size:11px;">
                                + New
                            </a>
                        <?php endif; ?>
                    </h3>

                    <div class="broadcast-note">
                        📡 <?= $edit_ann
                            ? 'Editing will update the existing announcement.'
                            : 'This will broadcast a notification to all users.' ?>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action"
                               value="<?= $edit_ann ? 'edit' : 'add' ?>">
                        <?php if ($edit_ann): ?>
                            <input type="hidden" name="ann_id" value="<?= $edit_ann['id'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label>Announcement Title *</label>
                            <input type="text"
                                   name="ann_title"
                                   id="ann_title"
                                   placeholder="e.g. Holiday Closure Notice"
                                   value="<?= htmlspecialchars($edit_ann['title'] ?? '') ?>"
                                   maxlength="255"
                                   required
                                   oninput="updateCount('ann_title','tc',255)">
                            <div class="char-count">
                                <span id="tc">
                                    <?= strlen($edit_ann['title'] ?? '') ?>
                                </span>/255
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Message *</label>
                            <textarea name="ann_content"
                                      id="ann_content"
                                      placeholder="Write your announcement here..."
                                      required
                                      oninput="updateCount('ann_content','cc',2000)"
                                      maxlength="2000"><?= htmlspecialchars($edit_ann['content'] ?? '') ?></textarea>
                            <div class="char-count">
                                <span id="cc">
                                    <?= strlen($edit_ann['content'] ?? '') ?>
                                </span>/2000
                            </div>
                        </div>

                        <button type="submit" class="btn btn-teal w-full">
                            <?= $edit_ann ? '💾 Save Changes' : '📤 Post Announcement' ?>
                        </button>

                        <?php if ($edit_ann): ?>
                            <a href="announcements.php"
                               class="btn btn-gray w-full" style="margin-top:8px;display:flex;">
                                Cancel
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- RIGHT: Announcements List -->
                <div>
                    <div class="ann-list-card">
                        <h3>
                            <span>📋 All Announcements (<?= $total ?>)</span>
                            <!-- Search -->
                            <form method="GET" style="margin:0;">
                                <div class="search-box" style="max-width:220px;min-width:180px;">
                                    <span>🔍</span>
                                    <input type="text" name="search"
                                           placeholder="Search..."
                                           value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </form>
                        </h3>

                        <?php if (empty($announcements)): ?>
                            <div class="empty-state" style="padding:32px;">
                                <div class="empty-icon">📭</div>
                                <h3><?= $search ? 'No results found' : 'No Announcements Yet' ?></h3>
                                <p><?= $search ? 'Try a different search term.' : 'Post your first announcement using the form.' ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($announcements as $ann): ?>
                                <div class="ann-row">
                                    <div class="ann-row-top">
                                        <div class="ann-row-title">
                                            <?= htmlspecialchars($ann['title']) ?>
                                        </div>
                                        <div class="ann-row-actions">
                                            <button class="btn btn-teal btn-sm"
                                                    onclick="openPreview(
                                                        '<?= htmlspecialchars($ann['title'], ENT_QUOTES) ?>',
                                                        `<?= addslashes(htmlspecialchars($ann['content'])) ?>`,
                                                        '<?= htmlspecialchars($ann['posted_by_name'] ?? 'Admin', ENT_QUOTES) ?>',
                                                        '<?= formatDate($ann['created_at']) ?>'
                                                    )">
                                                👁️
                                            </button>
                                            <a href="announcements.php?edit=<?= $ann['id'] ?>"
                                               class="btn btn-yellow btn-sm">✏️</a>
                                            <a href="announcements.php?delete=<?= $ann['id'] ?>"
                                               onclick="return confirm('Delete this announcement? This cannot be undone.')"
                                               class="btn btn-red btn-sm">🗑️</a>
                                        </div>
                                    </div>
                                    <div class="ann-row-body">
                                        <?= htmlspecialchars($ann['content']) ?>
                                    </div>
                                    <div class="ann-row-meta">
                                        <span>📅 <?= formatDate($ann['created_at']) ?></span>
                                        <span>· By <?= htmlspecialchars($ann['posted_by_name'] ?? 'Admin') ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>

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
                    </div>
                </div>

            </div><!-- /page-layout -->

        </div><!-- /page-body -->
    </div>
</div>

<!-- Preview Modal -->
<div class="modal-overlay" id="previewModal">
    <div class="modal" style="max-width:560px;">
        <button class="modal-close" onclick="closeModal('previewModal')">×</button>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
            <div style="width:44px;height:44px;border-radius:50%;
                        background:linear-gradient(135deg,var(--teal-light),#b2ebf2);
                        display:flex;align-items:center;justify-content:center;font-size:20px;">
                📢
            </div>
            <div>
                <div style="font-size:11px;font-weight:800;color:var(--teal-dark);
                            text-transform:uppercase;letter-spacing:0.5px;">
                    Announcement
                </div>
                <h3 class="modal-title" id="preview-title"
                    style="text-align:left;margin-bottom:0;font-size:17px;"></h3>
            </div>
        </div>
        <div class="ann-preview-body" id="preview-body"></div>
        <div style="display:flex;align-items:center;justify-content:space-between;
                    margin-top:14px;font-size:12px;color:var(--text-light);font-weight:600;">
            <span id="preview-meta"></span>
            <button class="btn btn-gray btn-sm" onclick="closeModal('previewModal')">Close</button>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// Character counter
function updateCount(fieldId, countId, max) {
    const len = document.getElementById(fieldId).value.length;
    const el  = document.getElementById(countId);
    el.textContent = len;
    el.style.color = len > max * 0.9 ? '#ef4444' : '';
}

// Preview modal
function openPreview(title, body, author, date) {
    document.getElementById('preview-title').textContent = title;
    document.getElementById('preview-body').textContent  = body;
    document.getElementById('preview-meta').textContent  = '📅 ' + date + ' · By ' + author;
    openModal('previewModal');
}

// Auto-open edit form if edit param present
<?php if ($edit_ann): ?>
    document.querySelector('.compose-card').scrollIntoView({ behavior: 'smooth' });
<?php endif; ?>
</script>
</body>
</html>