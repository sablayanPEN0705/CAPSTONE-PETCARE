<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/messages.php
// Purpose: Admin side messaging with all pet owners
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$admin_id = (int)$_SESSION['user_id'];

// ── Handle send message ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = sanitize($conn, trim($_POST['message']));
    $to  = (int)($_POST['to'] ?? 0);

    $attachment = '';
    if (!empty($_FILES['attachment']['name'])) {
        $upload_dir = '../uploads/messages/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $orig_name  = basename($_FILES['attachment']['name']);
        $ext        = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $allowed    = ['jpg','jpeg','png','gif','pdf','doc','docx','txt'];
        $max_size   = 5 * 1024 * 1024;
        if (in_array($ext, $allowed) && $_FILES['attachment']['size'] <= $max_size) {
            $safe_name = uniqid('msg_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $safe_name)) {
                $attachment = $safe_name;
            }
        }
    }

    if (($msg || $attachment) && $to) {
        $attach_col = sanitize($conn, $attachment);
        mysqli_query($conn,
            "INSERT INTO messages (sender_id, receiver_id, message, attachment)
             VALUES ($admin_id, $to, '$msg', '$attach_col')");
        mysqli_query($conn,
            "INSERT INTO notifications (user_id, title, message, type)
             VALUES ($to, 'New Message from Clinic',
             'You have a new message from the clinic.', 'message')");
    }
    header("Location: messages.php?contact=$to");
    exit();
}
// ── Handle delete/unsend message ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_msg_id'])) {
    $del_id = (int)$_POST['delete_msg_id'];
    $action = $_POST['delete_action'] ?? '';
    $redirect_to = (int)($_POST['redirect_to'] ?? 0);
    if ($action === 'unsend') {
        mysqli_query($conn, "DELETE FROM messages WHERE id=$del_id AND sender_id=$admin_id");
    } elseif ($action === 'delete') {
        mysqli_query($conn, "UPDATE messages SET deleted_by_admin=1 WHERE id=$del_id AND receiver_id=$admin_id");
    }
    header("Location: messages.php?contact=$redirect_to");
    exit();
}
// ── Active chat contact ──────────────────────────────────────
$chat_with = isset($_GET['contact']) ? (int)$_GET['contact'] : 0;$chat_user = null;

// ── Mark as read ─────────────────────────────────────────────
if ($chat_with) {
    mysqli_query($conn,
        "UPDATE messages SET is_read=1
         WHERE sender_id=$chat_with AND receiver_id=$admin_id AND is_read=0");
    $chat_user = getRow($conn, "SELECT * FROM users WHERE id=$chat_with AND role='user'");
}

// ── Fetch conversation ───────────────────────────────────────
$conversation = [];
if ($chat_with) {
    $conversation = getRows($conn,
        "SELECT m.*, u.name AS sender_name
         FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE ((m.sender_id=$admin_id AND m.receiver_id=$chat_with)
            OR (m.sender_id=$chat_with AND m.receiver_id=$admin_id))
           AND m.deleted_by_admin = 0
         ORDER BY m.created_at ASC");
}

// ── Fetch ALL registered users as contacts ───────────────────
$contacts = getRows($conn,
    "SELECT u.id, u.name, u.profile_picture,
            (SELECT m2.message FROM messages m2
             WHERE (m2.sender_id=u.id AND m2.receiver_id=$admin_id)
                OR (m2.sender_id=$admin_id AND m2.receiver_id=u.id)
             ORDER BY m2.created_at DESC LIMIT 1) AS last_msg,
            (SELECT COUNT(*) FROM messages m3
             WHERE m3.sender_id=u.id
               AND m3.receiver_id=$admin_id
               AND m3.is_read=0) AS unread_count,
            (SELECT m4.created_at FROM messages m4
             WHERE (m4.sender_id=u.id AND m4.receiver_id=$admin_id)
                OR (m4.sender_id=$admin_id AND m4.receiver_id=u.id)
             ORDER BY m4.created_at DESC LIMIT 1) AS last_time
     FROM users u
     WHERE u.role='user' AND u.status='active'
     ORDER BY last_time DESC, u.name ASC");

// ── Avatar disk base (absolute) ──────────────────────────────
// Same path used by sidebar_user.php and profile.php
$_avatar_disk_base = dirname(__DIR__) . '/assets/images/avatars/';
$_avatar_web_base  = '../assets/images/avatars/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .msg-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 0;
            height: calc(100vh - 130px);
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        /* Contacts Panel */
        .contacts-panel {
            background: #f0fffe;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }
        .contacts-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .contacts-header span { font-weight: 800; font-size: 14px; }
        .contacts-count {
            font-size: 11px;
            background: var(--teal-light);
            color: var(--teal-dark);
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 700;
        }
        .contacts-search {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            background: #fff;
        }
        .contacts-search input {
            width: 100%;
            padding: 8px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            font-size: 12px;
            outline: none;
            background: #f9f9f9;
            font-family: var(--font-main);
            box-sizing: border-box;
        }
        .contacts-search input:focus { border-color: var(--teal); }
        .contacts-list { flex: 1; overflow-y: auto; }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            cursor: pointer;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
        }
        .contact-item:hover  { background: rgba(0,188,212,0.06); }
        .contact-item.active { background: rgba(0,188,212,0.14); border-left: 3px solid var(--teal); }

        .contact-avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), #0097a7);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700; color: #fff;
            flex-shrink: 0; overflow: hidden;
            position: relative;
        }
        .contact-avatar img {
            width: 100%; height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .contact-avatar .ca-initials {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
        }

        .contact-body { flex: 1; min-width: 0; }
        .contact-name {
            font-weight: 700; font-size: 13px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .contact-preview {
            font-size: 11px; color: var(--text-light);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .contact-preview.new-chat { color: var(--teal-dark); font-style: italic; }

        .contact-meta {
            display: flex; flex-direction: column;
            align-items: flex-end; gap: 4px; flex-shrink: 0;
        }
        .contact-time { font-size: 10px; color: var(--text-light); }
        .unread-badge {
            background: #ef4444; color: #fff;
            font-size: 10px; font-weight: 700;
            min-width: 18px; height: 18px;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            padding: 0 4px;
        }

        /* Chat Panel */
        .chat-panel { display: flex; flex-direction: column; height: 100%; }
        .chat-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            background: #fff;
            display: flex; align-items: center; gap: 12px;
        }
        .chat-header-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), #0097a7);
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: #fff; font-weight: 700;
            overflow: hidden; flex-shrink: 0;
        }
        .chat-header-avatar img {
            width: 100%; height: 100%; object-fit: cover; border-radius: 50%;
        }
        .chat-header-info .chat-name { font-weight: 800; font-size: 14px; }
        .chat-header-info .chat-role { font-size: 11px; color: var(--teal-dark); font-weight: 600; }
        .chat-header-actions { margin-left: auto; display: flex; gap: 8px; }
        .chat-action-btn {
            padding: 5px 12px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: #fff; font-size: 12px; font-weight: 700;
            cursor: pointer; transition: var(--transition);
            text-decoration: none; color: var(--text-mid);
        }
        .chat-action-btn:hover { border-color: var(--teal); color: var(--teal-dark); }

        .chat-messages {
            flex: 1; overflow-y: auto;
            padding: 16px 20px;
            display: flex; flex-direction: column; gap: 12px;
            background: #fafefe;
        }
        .msg-group { display: flex; flex-direction: column; gap: 3px; }
        .msg-group.sent { align-items: flex-end; }
        .msg-group.recv { align-items: flex-start; }
        .msg-sender { font-size: 11px; color: var(--text-light); font-weight: 700; padding: 0 4px; }
        .msg-bubble {
            max-width: 70%; padding: 10px 14px; border-radius: 18px;
            font-size: 13px; line-height: 1.5; word-break: break-word;
        }
        .msg-bubble.recv {
            background: var(--teal-light); color: #1a1a2e;
            border-bottom-left-radius: 4px;
        }
        .msg-bubble.sent {
            background: var(--blue-header); color: #fff;
            border-bottom-right-radius: 4px;
        }
        .msg-time { font-size: 10px; color: var(--text-light); padding: 0 4px; }

        .chat-empty {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 10px; color: var(--text-light);
        }
        .chat-empty .ce-icon { font-size: 48px; }
        .chat-empty h3 { font-size: 16px; font-weight: 700; color: var(--text-mid); }

        .chat-input-area {
            padding: 12px 16px;
            border-top: 1px solid var(--border);
            display: flex; gap: 10px; background: #fff;
        }
        .chat-input-area input {
            flex: 1; padding: 10px 16px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg); outline: none;
            font-size: 13px; transition: var(--transition);
            font-family: var(--font-main);
        }
        .chat-input-area input:focus { border-color: var(--teal); }
        .send-btn {
            padding: 10px 20px; background: var(--teal);
            color: #fff; border: none; border-radius: var(--radius-lg);
            font-weight: 700; font-size: 13px; cursor: pointer; transition: var(--transition);
        }
        .send-btn:hover { background: var(--teal-dark); }

        .no-contacts {
            padding: 24px 16px; text-align: center;
            color: var(--text-light); font-size: 13px;
        }

       @media(max-width:700px) {
            .msg-layout { grid-template-columns: 1fr; }
            .contacts-panel { max-height: 200px; }
        }

        .file-attach-btn {
            width: 38px; height: 38px; border-radius: 50%;
            background: #e0f7fa; color: var(--teal-dark);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; cursor: pointer; flex-shrink: 0;
            transition: var(--transition); align-self: flex-end;
        }
        .file-attach-btn:hover { background: var(--teal-light); }

        .msg-file-link {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.2); padding: 6px 10px;
            border-radius: 10px; font-size: 12px; font-weight: 700;
            text-decoration: none; margin-top: 4px;
        }
        .msg-bubble.sent .msg-file-link { color: #fff; border: 1px solid rgba(255,255,255,0.4); }
        .msg-bubble.recv .msg-file-link { color: var(--teal-dark); border: 1px solid var(--teal); }

.msg-actions {
    display: none;
    gap: 4px;
    margin-top: 3px;
}
.msg-group:hover .msg-actions { display: flex; }
.msg-group.sent .msg-actions { justify-content: flex-end; }
.msg-group.recv .msg-actions { justify-content: flex-start; }
.msg-action-btn {
    font-size: 10px; font-weight: 700;
    padding: 2px 8px; border-radius: 10px;
    border: none; cursor: pointer;
    transition: var(--transition);
}
.msg-action-btn.unsend { background: #fee2e2; color: #dc2626; }
.msg-action-btn.unsend:hover { background: #fca5a5; }
.msg-action-btn.delete { background: #f3f4f6; color: #6b7280; }
.msg-action-btn.delete:hover { background: #e5e7eb; }

/* ── Delete/Unsend Modal ── */
.confirm-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.45);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999;
    opacity: 0; pointer-events: none;
    transition: opacity 0.2s ease;
}
.confirm-overlay.active {
    opacity: 1; pointer-events: all;
}
.confirm-box {
    background: #fff;
    border-radius: 16px;
    padding: 28px 32px;
    max-width: 340px; width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    text-align: center;
    transform: scale(0.9);
    transition: transform 0.2s ease;
}
.confirm-overlay.active .confirm-box {
    transform: scale(1);
}
.confirm-icon {
    font-size: 40px; margin-bottom: 10px;
}
.confirm-title {
    font-size: 16px; font-weight: 800;
    color: #1a1a2e; margin-bottom: 6px;
}
.confirm-subtitle {
    font-size: 13px; color: #6b7280; margin-bottom: 22px; line-height: 1.5;
}
.confirm-btns {
    display: flex; gap: 10px; justify-content: center;
}
.confirm-btn {
    flex: 1; padding: 10px 16px;
    border-radius: 10px; border: none;
    font-size: 13px; font-weight: 700;
    cursor: pointer; transition: all 0.15s ease;
}
.confirm-btn.cancel {
    background: #f3f4f6; color: #374151;
}
.confirm-btn.cancel:hover { background: #e5e7eb; }
.confirm-btn.confirm-unsend {
    background: #fee2e2; color: #dc2626;
}
.confirm-btn.confirm-unsend:hover { background: #fca5a5; }
.confirm-btn.confirm-delete {
    background: #6b7280; color: #fff;
}
.confirm-btn.confirm-delete:hover { background: #4b5563; }
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
                MESSAGES
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

        <div class="page-body" style="padding-bottom:0;">
            <div class="msg-layout">

                <!-- Contacts Panel -->
                <div class="contacts-panel">
                    <div class="contacts-header">
                        <span>💬 All Clients</span>
                        <span class="contacts-count"><?= count($contacts) ?></span>
                    </div>

                    <div class="contacts-search">
                        <input type="text" id="contactSearch"
                               placeholder="🔍 Search client..."
                               oninput="filterContacts(this.value)">
                    </div>

                    <div class="contacts-list" id="contactsList">
                        <?php if (empty($contacts)): ?>
                            <div class="no-contacts">
                                <div style="font-size:32px;margin-bottom:8px;">👤</div>
                                <p>No registered clients yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($contacts as $ct):
                                $is_active = ($ct['id'] == $chat_with) ? 'active' : '';
                                $initials  = strtoupper(substr($ct['name'], 0, 2));
                                $unread    = (int)($ct['unread_count'] ?? 0);
                                $has_msg   = !empty($ct['last_msg']);
                                $preview   = $has_msg
                                    ? (strlen($ct['last_msg']) > 30 ? substr($ct['last_msg'],0,30).'...' : $ct['last_msg'])
                                    : 'Tap to start a conversation';
                                $time_str  = $ct['last_time'] ? date('M d', strtotime($ct['last_time'])) : '';

                                // ── Avatar: use absolute disk path for file_exists() ──
                                $ct_pic       = $ct['profile_picture'] ?? '';
                                $ct_has_avatar = !empty($ct_pic) && file_exists($_avatar_disk_base . $ct_pic);
                                $ct_avatar_src = $ct_has_avatar ? $_avatar_web_base . htmlspecialchars($ct_pic) : '';
                            ?>
                                <a href="messages.php?contact=<?= $ct['id'] ?>"
                                   class="contact-item <?= $is_active ?>"
                                   data-name="<?= strtolower(htmlspecialchars($ct['name'])) ?>">
                                    <div class="contact-avatar">
                                        <?php if ($ct_has_avatar): ?>
                                            <img src="<?= $ct_avatar_src ?>"
                                                 alt="<?= htmlspecialchars($ct['name']) ?>"
                                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                            <div class="ca-initials" style="display:none;position:absolute;inset:0;">
                                                <?= $initials ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="ca-initials"><?= $initials ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="contact-body">
                                        <div class="contact-name"><?= htmlspecialchars($ct['name']) ?></div>
                                        <div class="contact-preview <?= !$has_msg ? 'new-chat' : '' ?>">
                                            <?= htmlspecialchars($preview) ?>
                                        </div>
                                    </div>
                                    <div class="contact-meta">
                                        <?php if ($time_str): ?>
                                            <span class="contact-time"><?= $time_str ?></span>
                                        <?php endif; ?>
                                        <?php if ($unread > 0): ?>
                                            <span class="unread-badge"><?= $unread ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Panel -->
                <div class="chat-panel">

                    <?php if ($chat_user):
                        // Chat header avatar — same absolute path check
                        $cu_pic       = $chat_user['profile_picture'] ?? '';
                        $cu_has_avatar = !empty($cu_pic) && file_exists($_avatar_disk_base . $cu_pic);
                        $cu_avatar_src = $cu_has_avatar ? $_avatar_web_base . htmlspecialchars($cu_pic) : '';
                        $cu_initials   = strtoupper(substr($chat_user['name'], 0, 2));
                    ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="chat-header-avatar">
                            <?php if ($cu_has_avatar): ?>
                                <img src="<?= $cu_avatar_src ?>"
                                     alt="<?= htmlspecialchars($chat_user['name']) ?>"
                                     onerror="this.style.display='none';this.parentElement.textContent='<?= $cu_initials ?>'">
                            <?php else: ?>
                                <?= $cu_initials ?>
                            <?php endif; ?>
                        </div>
                        <div class="chat-header-info">
                            <div class="chat-name"><?= htmlspecialchars($chat_user['name']) ?></div>
                            <div class="chat-role">Pet Owner</div>
                        </div>
                        <div class="chat-header-actions">
                            <a href="client_records.php?view=<?= $chat_user['id'] ?>"
                               class="chat-action-btn">👤 Profile</a>
                            <a href="appointments.php?search_appt=<?= urlencode($chat_user['name']) ?>&view=list"
                               class="chat-action-btn">📅 Appointments</a>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($conversation)): ?>
                            <div class="chat-empty">
                                <div class="ce-icon">💬</div>
                                <h3>No messages yet</h3>
                                <p>Start the conversation with <?= htmlspecialchars($chat_user['name']) ?></p>
                            </div>
                        <?php else: ?>
                            <?php
                            $prev_sender = null;
                            foreach ($conversation as $msg):
                                $is_sent     = ($msg['sender_id'] == $admin_id);
                                $type        = $is_sent ? 'sent' : 'recv';
                                $show_name   = ($msg['sender_id'] !== $prev_sender);
                                $prev_sender = $msg['sender_id'];
                                $time_str    = date('h:i A', strtotime($msg['created_at']));
                                $date_str    = date('M d',   strtotime($msg['created_at']));
                            ?>
                            <div class="msg-group <?= $type ?>">
                                <?php if ($show_name && !$is_sent): ?>
                                    <div class="msg-sender">
                                        <?= htmlspecialchars($msg['sender_name']) ?>
                                    </div>
                                <?php endif; ?>
                               <div class="msg-bubble <?= $type ?>">
                                    <?php if ($msg['message']): ?>
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($msg['attachment'])): ?>
                                        <?php
                                        $att      = $msg['attachment'];
                                        $att_ext  = strtolower(pathinfo($att, PATHINFO_EXTENSION));
                                        $att_url  = '../uploads/messages/' . htmlspecialchars($att);
                                        $img_exts = ['jpg','jpeg','png','gif'];
                                        ?>
                                        <?php if (in_array($att_ext, $img_exts)): ?>
                                            <img src="<?= $att_url ?>"
                                                 style="max-width:200px;max-height:200px;border-radius:8px;
                                                        display:block;margin-top:6px;cursor:pointer;"
                                                 onclick="window.open(this.src)">
                                        <?php else: ?>
                                            <a href="<?= $att_url ?>" target="_blank" class="msg-file-link">
                                                📄 <?= htmlspecialchars(pathinfo($att, PATHINFO_EXTENSION)) ?> file
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                               <div class="msg-actions">
                                    <?php if ($is_sent): ?>
                                        <form method="POST" style="display:inline;" onsubmit="event.preventDefault(); openConfirm(this, 'unsend')">
                                            <input type="hidden" name="delete_msg_id" value="<?= $msg['id'] ?>">
                                            <input type="hidden" name="delete_action" value="unsend">
                                            <input type="hidden" name="redirect_to" value="<?= $chat_with ?>">
                                            <button type="submit" class="msg-action-btn unsend">✕ Unsend</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline;" onsubmit="event.preventDefault(); openConfirm(this, 'delete')">
                                            <input type="hidden" name="delete_msg_id" value="<?= $msg['id'] ?>">
                                            <input type="hidden" name="delete_action" value="delete">
                                            <input type="hidden" name="redirect_to" value="<?= $chat_with ?>">
                                            <button type="submit" class="msg-action-btn delete">🗑 Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div class="msg-time"><?= $time_str ?>, <?= $date_str ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                   <!-- Input -->
                    <form method="POST" class="chat-input-area" id="msgForm"
                          onsubmit="return validateMsg()"
                          enctype="multipart/form-data">
                        <input type="hidden" name="to" value="<?= $chat_with ?>">
                        <label class="file-attach-btn" title="Attach file">
                            📎
                            <input type="file" name="attachment" id="fileInput"
                                   accept="image/*,.pdf,.doc,.docx,.txt"
                                   style="display:none;"
                                   onchange="showFilePreview(this)">
                        </label>
                        <div style="flex:1;display:flex;flex-direction:column;gap:4px;">
                            <div id="filePreview" style="display:none;font-size:11px;color:var(--teal-dark);
                                 background:#e0f7fa;padding:4px 10px;border-radius:8px;
                                 align-items:center;gap:6px;">
                                <span id="filePreviewName"></span>
                                <span onclick="clearFile()" style="cursor:pointer;color:#ef4444;font-weight:700;">✕</span>
                            </div>
                            <input type="text" name="message" id="msgInput"
                                   placeholder="Type your message here..."
                                   autocomplete="off">
                        </div>
                        <button type="submit" class="send-btn">Send ➤</button>
                    </form>

                    <?php else: ?>
                    <div class="chat-empty">
                        <div class="ce-icon">💬</div>
                        <h3>Select a client to message</h3>
                        <p>Search or choose a client from the left panel.</p>
                    </div>
                    <?php endif; ?>

                </div><!-- /chat-panel -->
            </div><!-- /msg-layout -->
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// Scroll chat to bottom
const chatEl = document.getElementById('chatMessages');
if (chatEl) chatEl.scrollTop = chatEl.scrollHeight;

// Enter to send
const msgInput = document.getElementById('msgInput');
if (msgInput) {
    msgInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        const file = document.getElementById('fileInput');
        if (this.value.trim() || (file && file.files.length > 0)) {
            document.getElementById('msgForm').dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}));
        }
    }
});
}

function validateMsg() {
    const inp  = document.getElementById('msgInput');
    const file = document.getElementById('fileInput');
    return (inp && inp.value.trim().length > 0) || (file && file.files.length > 0);
}

function showFilePreview(input) {
    const preview  = document.getElementById('filePreview');
    const nameSpan = document.getElementById('filePreviewName');
    if (input.files && input.files[0]) {
        nameSpan.textContent = '📎 ' + input.files[0].name;
        preview.style.display = 'flex';
    }
}

function clearFile() {
    document.getElementById('fileInput').value = '';
    document.getElementById('filePreview').style.display = 'none';
    document.getElementById('filePreviewName').textContent = '';
}
// Filter contacts by name
function filterContacts(query) {
    const q = query.toLowerCase().trim();
    let visibleCount = 0;
    document.querySelectorAll('#contactsList .contact-item').forEach(item => {
        const name = item.getAttribute('data-name') || '';
        const show = !q || name.includes(q);
        item.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });
    let noResult = document.getElementById('noSearchResult');
    if (!noResult) {
        noResult = document.createElement('div');
        noResult.id = 'noSearchResult';
        noResult.className = 'no-contacts';
        noResult.innerHTML = '<div style="font-size:24px;">🔍</div><p>No client found.</p>';
        document.getElementById('contactsList').appendChild(noResult);
    }
    noResult.style.display = (visibleCount === 0 && q) ? '' : 'none';
}

// Auto-refresh every 8 seconds when in active chat
setTimeout(() => {
    const url = new URL(window.location.href);
    if (url.searchParams.get('contact')) location.reload();
}, 8000);
</script>

<!-- Centered Confirm Modal -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-icon" id="confirmIcon">⚠️</div>
        <div class="confirm-title" id="confirmTitle">Are you sure?</div>
        <div class="confirm-subtitle" id="confirmSubtitle">This action cannot be undone.</div>
        <div class="confirm-btns">
            <button class="confirm-btn cancel" onclick="closeConfirm()">Cancel</button>
            <button class="confirm-btn" id="confirmActionBtn" onclick="submitConfirm()">Confirm</button>
        </div>
    </div>
</div>

<script>
let _pendingForm = null;

function openConfirm(formEl, type) {
    _pendingForm = formEl;
    const overlay = document.getElementById('confirmOverlay');
    const icon    = document.getElementById('confirmIcon');
    const title   = document.getElementById('confirmTitle');
    const sub     = document.getElementById('confirmSubtitle');
    const btn     = document.getElementById('confirmActionBtn');

    if (type === 'unsend') {
        icon.textContent  = '↩️';
        title.textContent = 'Unsend Message?';
        sub.textContent   = 'This will remove the message for everyone. This cannot be undone.';
        btn.textContent   = 'Yes, Unsend';
        btn.className     = 'confirm-btn confirm-unsend';
    } else {
        icon.textContent  = '🗑️';
        title.textContent = 'Delete Message?';
        sub.textContent   = 'This will remove the message from your view only.';
        btn.textContent   = 'Yes, Delete';
        btn.className     = 'confirm-btn confirm-delete';
    }
    overlay.classList.add('active');
}

function closeConfirm() {
    document.getElementById('confirmOverlay').classList.remove('active');
    _pendingForm = null;
}

function submitConfirm() {
    if (_pendingForm) _pendingForm.submit();
    closeConfirm();
}

// Close on backdrop click
document.getElementById('confirmOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeConfirm();
});
</script>
</body>
</html>