<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/messages.php
// Purpose: Real-time-style messaging between user and clinic staff
// ============================================================
require_once '../includes/auth.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id = (int)$_SESSION['user_id'];

// ── Get admin/staff to message ───────────────────────────────
$staff = getRows($conn, "SELECT id, name FROM users WHERE role='admin' ORDER BY name ASC");

// ── Default: chat with first staff member ────────────────────
$chat_with = isset($_GET['staff']) ? (int)$_GET['staff'] : ($staff[0]['id'] ?? 0);
$chat_user = $chat_with ? getRow($conn, "SELECT * FROM users WHERE id=$chat_with") : null;

// ── Handle send message ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg  = sanitize($conn, trim($_POST['message']));
    $to   = (int)($_POST['to'] ?? 0);
    if ($msg && $to) {
        mysqli_query($conn,
            "INSERT INTO messages (sender_id, receiver_id, message)
             VALUES ($user_id, $to, '$msg')");
        // Mark admin notif
        mysqli_query($conn,
            "INSERT INTO notifications (user_id, title, message, type)
             VALUES ($to, 'New Message', 'You have a new message from {$_SESSION["user_name"]}', 'message')");
    }
    // Redirect to prevent re-submit on refresh
    header("Location: messages.php?staff=$to");
    exit();
}

// ── Mark messages from this staff as read ───────────────────
if ($chat_with) {
    mysqli_query($conn,
        "UPDATE messages SET is_read=1
         WHERE sender_id=$chat_with AND receiver_id=$user_id AND is_read=0");
}

// ── Fetch conversation ───────────────────────────────────────
$conversation = [];
if ($chat_with) {
    $conversation = getRows($conn,
        "SELECT m.*, u.name AS sender_name
         FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE (m.sender_id=$user_id AND m.receiver_id=$chat_with)
            OR (m.sender_id=$chat_with AND m.receiver_id=$user_id)
         ORDER BY m.created_at ASC");
}

// ── Unread count per staff ───────────────────────────────────
$unread_per_staff = [];
foreach ($staff as $s) {
    $sid = (int)$s['id'];
    $unread_per_staff[$sid] = countRows($conn, 'messages',
        "sender_id=$sid AND receiver_id=$user_id AND is_read=0");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .msg-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 0;
            height: calc(100vh - 130px);
            background: rgba(255,255,255,0.88);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        /* Contacts panel */
        .contacts-panel {
            background: #f0fffe;
            border-right: 1px solid var(--border);
            overflow-y: auto;
        }

        .contacts-header {
            padding: 14px 16px;
            font-weight: 800;
            font-size: 14px;
            border-bottom: 1px solid var(--border);
            background: #fff;
            color: var(--text-dark);
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 1px solid var(--border);
            text-decoration: none;
            color: inherit;
        }

        .contact-item:hover { background: rgba(0,188,212,0.08); }
        .contact-item.active { background: rgba(0,188,212,0.15); }

        .contact-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), #0097a7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #fff;
            flex-shrink: 0;
            font-weight: 700;
        }

        .contact-info { flex: 1; min-width: 0; }
        .contact-name {
            font-weight: 700;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .contact-role {
            font-size: 11px;
            color: var(--teal-dark);
            font-weight: 600;
        }

        .contact-unread {
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }

        /* Chat panel */
        .chat-panel {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .chat-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            background: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-header-avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), #0097a7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #fff;
            font-weight: 700;
            flex-shrink: 0;
        }

        .chat-header-info .chat-name {
            font-weight: 800;
            font-size: 14px;
        }
        .chat-header-info .chat-role {
            font-size: 11px;
            color: var(--teal-dark);
            font-weight: 600;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: #fafefe;
        }

        /* Message bubbles */
        .msg-group { display: flex; flex-direction: column; gap: 4px; }
        .msg-group.sent  { align-items: flex-end; }
        .msg-group.recv  { align-items: flex-start; }

        .msg-sender-name {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-light);
            margin-bottom: 2px;
            padding: 0 4px;
        }

        .msg-bubble {
            max-width: 68%;
            padding: 10px 14px;
            border-radius: 18px;
            font-size: 13px;
            line-height: 1.5;
            word-break: break-word;
        }

        .msg-bubble.recv {
            background: var(--teal-light);
            color: #1a1a2e;
            border-bottom-left-radius: 4px;
        }

        .msg-bubble.sent {
            background: var(--blue-header);
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .msg-time {
            font-size: 10px;
            color: var(--text-light);
            padding: 0 4px;
        }

        /* Empty chat */
        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            gap: 10px;
        }
        .chat-empty .ce-icon { font-size: 48px; }
        .chat-empty h3 { font-size: 16px; font-weight: 700; color: var(--text-mid); }
        .chat-empty p  { font-size: 13px; }

        /* Input area */
        .chat-input-area {
            padding: 12px 16px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
            background: #fff;
        }

        .chat-input-area input {
            flex: 1;
            padding: 10px 16px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            outline: none;
            font-size: 13px;
            transition: var(--transition);
            font-family: var(--font-main);
        }
        .chat-input-area input:focus { border-color: var(--teal); }

        .send-btn {
            padding: 10px 20px;
            background: var(--teal);
            color: #fff;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .send-btn:hover { background: var(--teal-dark); }

        @media(max-width: 700px) {
            .msg-layout { grid-template-columns: 1fr; }
            .contacts-panel { max-height: 140px; }
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
        Message
    </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔</a>
                <a href="settings.php"      class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body" style="padding-bottom:0;">
            <div class="msg-layout">

                <!-- Contacts / Staff List -->
                <div class="contacts-panel">
                    <div class="contacts-header">💬 Staff</div>

                    <?php if (empty($staff)): ?>
                        <p style="padding:16px;font-size:13px;color:var(--text-light);">
                            No staff available.
                        </p>
                    <?php else: ?>
                        <?php foreach ($staff as $s): ?>
                            <?php
                            $is_active = $s['id'] == $chat_with ? 'active' : '';
                            $initials  = strtoupper(substr($s['name'], 0, 2));
                            $unread    = $unread_per_staff[$s['id']] ?? 0;
                            ?>
                            <a href="messages.php?staff=<?= $s['id'] ?>"
                               class="contact-item <?= $is_active ?>">
                                <div class="contact-avatar"><?= $initials ?></div>
                                <div class="contact-info">
                                    <div class="contact-name"><?= htmlspecialchars($s['name']) ?></div>
                                    <div class="contact-role">Vet Staff</div>
                                </div>
                                <?php if ($unread > 0): ?>
                                    <div class="contact-unread"><?= $unread ?></div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Chat Panel -->
                <div class="chat-panel">

                    <?php if ($chat_user): ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="chat-header-avatar">
                            <?= strtoupper(substr($chat_user['name'], 0, 2)) ?>
                        </div>
                        <div class="chat-header-info">
                            <div class="chat-name"><?= htmlspecialchars($chat_user['name']) ?></div>
                            <div class="chat-role">Veterinarian / Clinic Staff</div>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($conversation)): ?>
                            <div class="chat-empty">
                                <div class="ce-icon">💬</div>
                                <h3>Start the conversation!</h3>
                                <p>Send a message to <?= htmlspecialchars($chat_user['name']) ?></p>
                            </div>
                        <?php else: ?>
                            <?php
                            $prev_sender = null;
                            foreach ($conversation as $msg):
                                $is_sent  = ($msg['sender_id'] == $user_id);
                                $type     = $is_sent ? 'sent' : 'recv';
                                $show_name = ($msg['sender_id'] !== $prev_sender);
                                $prev_sender = $msg['sender_id'];
                                $time_str = date('h:i A', strtotime($msg['created_at']));
                                $date_str = date('M d', strtotime($msg['created_at']));
                            ?>
                            <div class="msg-group <?= $type ?>">
                                <?php if ($show_name && !$is_sent): ?>
                                    <div class="msg-sender-name">
                                        <?= htmlspecialchars($msg['sender_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="msg-bubble <?= $type ?>">
                                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                </div>
                                <div class="msg-time"><?= $time_str ?>, <?= $date_str ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Input -->
                    <form method="POST" class="chat-input-area" id="msgForm"
                          onsubmit="return submitMessage()">
                        <input type="hidden" name="to" value="<?= $chat_with ?>">
                        <input type="text"
                               name="message"
                               id="msgInput"
                               placeholder="Type your message here..."
                               autocomplete="off">
                        <button type="submit" class="send-btn">
                            Send ➤
                        </button>
                    </form>

                    <?php else: ?>
                    <div class="chat-empty">
                        <div class="ce-icon">💬</div>
                        <h3>Select a staff member</h3>
                        <p>Choose from the left panel to start chatting.</p>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div><!-- /page-body -->
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// Scroll chat to bottom on load
const chatMessages = document.getElementById('chatMessages');
if (chatMessages) {
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Submit on Enter key, Shift+Enter for newline
const msgInput = document.getElementById('msgInput');
if (msgInput) {
    msgInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (this.value.trim()) {
                document.getElementById('msgForm').submit();
            }
        }
    });
}

function submitMessage() {
    const input = document.getElementById('msgInput');
    if (!input || !input.value.trim()) return false;
    return true;
}

// Auto-refresh messages every 8 seconds
setTimeout(function() {
    const url = new URL(window.location.href);
    if (url.searchParams.get('staff')) {
        location.reload();
    }
}, 8000);
</script>
</body>
</html>