<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/sms_reminders.php
// Purpose: View SMS reminder logs, manually trigger, configure
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// Ensure sms_reminders table exists
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS sms_reminders (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT NOT NULL,
        reminder_type  ENUM('24h','1h') NOT NULL,
        sent_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_appt_type (appointment_id, reminder_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Handle manual test SMS ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'test_sms') {
    $test_number  = sanitize($conn, $_POST['test_number'] ?? '');
    $test_message = sanitize($conn, $_POST['test_message'] ?? 'Test SMS from Ligao Petcare & Vet Clinic. System is working!');

    if (empty($test_number)) {
        redirect('sms_reminders.php?error=Please enter a phone number.');
    }

    // Call the sendSMS function from cron script inline
    $api_key = getSmsApiKey($conn);
    $result  = sendSmsNow($test_number, $test_message, $api_key);

    if ($result) {
        redirect('sms_reminders.php?success=Test SMS sent successfully to ' . urlencode($test_number));
    } else {
        redirect('sms_reminders.php?error=SMS failed. Check your API key and phone number.');
    }
}

// ── Handle save API key ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_config') {
    $api_key    = sanitize($conn, $_POST['api_key']    ?? '');
    $sender_name= sanitize($conn, $_POST['sender_name']?? 'LigaoPet');

    // Store in a simple settings table
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS system_settings (
            setting_key   VARCHAR(100) PRIMARY KEY,
            setting_value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    mysqli_query($conn,
        "INSERT INTO system_settings (setting_key, setting_value)
         VALUES ('sms_api_key', '$api_key')
         ON DUPLICATE KEY UPDATE setting_value='$api_key'");
    mysqli_query($conn,
        "INSERT INTO system_settings (setting_key, setting_value)
         VALUES ('sms_sender', '$sender_name')
         ON DUPLICATE KEY UPDATE setting_value='$sender_name'");

    redirect('sms_reminders.php?success=SMS configuration saved!');
}

// ── Handle manual trigger for specific appointment ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_now') {
    $appt_id   = (int)$_POST['appt_id'];
    $rem_type  = $_POST['reminder_type'] ?? '24h';
    $api_key   = getSmsApiKey($conn);

    $appt = getRow($conn,
        "SELECT a.*, u.name AS owner_name, u.contact_no AS user_contact,
                p.name AS pet_name, s.name AS svc_name
         FROM appointments a
         LEFT JOIN users    u ON a.user_id    = u.id
         LEFT JOIN pets     p ON a.pet_id     = p.id
         LEFT JOIN services s ON a.service_id = s.id
         WHERE a.id=$appt_id");

    if ($appt) {
        $contact  = !empty($appt['contact']) ? $appt['contact'] : $appt['user_contact'];
        $date_fmt = date('F d, Y', strtotime($appt['appointment_date']));
        $time_fmt = date('g:i A',  strtotime($appt['appointment_time']));
        $type_lbl = $appt['appointment_type'] === 'home_service' ? 'Home Service' : 'Clinic';
        $pet      = $appt['pet_name']  ?: 'your pet';
        $service  = $appt['svc_name']  ?: $type_lbl;
        $owner    = $appt['owner_name']?: 'Pet Owner';

        if ($rem_type === '24h') {
            $msg = "Hi $owner! Reminder from Ligao Petcare & Vet Clinic: "
                 . "Your $type_lbl appointment for $pet ($service) "
                 . "is TOMORROW, $date_fmt at $time_fmt. "
                 . "Please arrive 10 mins early. Call 0926-396-7678 to cancel 24hrs ahead.";
        } else {
            $msg = "Hi $owner! Ligao Petcare & Vet Clinic reminds you: "
                 . "Your $type_lbl appointment for $pet ($service) "
                 . "is in about 1 HOUR at $time_fmt today. "
                 . "We look forward to seeing you! Call 0926-396-7678 for questions.";
        }

        $result = sendSmsNow($contact, $msg, $api_key);
        if ($result) {
            // Log it
            mysqli_query($conn,
                "INSERT IGNORE INTO sms_reminders (appointment_id, reminder_type)
                 VALUES ($appt_id, '$rem_type')");
            redirect('sms_reminders.php?success=SMS sent to ' . urlencode($contact));
        } else {
            redirect('sms_reminders.php?error=Failed to send SMS. Check API key.');
        }
    }
}

// ── Helper functions ──────────────────────────────────────────
function getSmsApiKey($conn) {
    $row = getRow($conn, "SELECT setting_value FROM system_settings WHERE setting_key='sms_api_key'");
    return $row['setting_value'] ?? '';
}

function getSender($conn) {
    $row = getRow($conn, "SELECT setting_value FROM system_settings WHERE setting_key='sms_sender'");
    return $row['setting_value'] ?? 'LigaoPet';
}

function sendSmsNow($contact_no, $message, $api_key) {
    if (empty($api_key)) return false;

    $number = preg_replace('/\D/', '', $contact_no);
    if (strlen($number) === 10 && substr($number,0,1)==='9') $number = '0'.$number;
    if (strlen($number) === 12 && substr($number,0,2)==='63') $number = '0'.substr($number,2);
    if (strlen($number) !== 11) return false;

    $payload = http_build_query([
        'apikey'     => $api_key,
        'number'     => $number,
        'message'    => $message,
        'sendername' => 'LigaoPet',
    ]);

    $ch = curl_init('https://api.semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return is_array($result) && isset($result[0]['message_id']);
}

// ── Data ──────────────────────────────────────────────────────
$api_key     = getSmsApiKey($conn);
$sender_name = getSender($conn);

// Recent SMS logs
$sms_logs = getRows($conn,
    "SELECT sr.*, a.appointment_date, a.appointment_time, a.appointment_type,
            u.name AS owner_name, u.contact_no, p.name AS pet_name
     FROM sms_reminders sr
     LEFT JOIN appointments a ON sr.appointment_id = a.id
     LEFT JOIN users u        ON a.user_id         = u.id
     LEFT JOIN pets  p        ON a.pet_id          = p.id
     ORDER BY sr.sent_at DESC
     LIMIT 50");

// Upcoming appointments (next 48 hrs) with SMS status
$upcoming = getRows($conn,
    "SELECT a.id, a.appointment_date, a.appointment_time, a.appointment_type,
            a.status, a.contact AS appt_contact,
            u.name AS owner_name, u.contact_no,
            p.name AS pet_name, s.name AS svc_name,
            MAX(CASE WHEN sr.reminder_type='24h' THEN 1 ELSE 0 END) AS sent_24h,
            MAX(CASE WHEN sr.reminder_type='1h'  THEN 1 ELSE 0 END) AS sent_1h
     FROM appointments a
     LEFT JOIN users u        ON a.user_id    = u.id
     LEFT JOIN pets  p        ON a.pet_id     = p.id
     LEFT JOIN services s     ON a.service_id = s.id
     LEFT JOIN sms_reminders sr ON a.id = sr.appointment_id
     WHERE a.status IN ('pending','confirmed')
       AND a.appointment_date >= CURDATE()
       AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
     GROUP BY a.id
     ORDER BY a.appointment_date ASC, a.appointment_time ASC");

// Stats
$total_sent   = countRows($conn, 'sms_reminders');
$sent_24h     = countRows($conn, 'sms_reminders', "reminder_type='24h'");
$sent_1h      = countRows($conn, 'sms_reminders', "reminder_type='1h'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Reminders — Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .sms-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 20px;
            align-items: start;
        }

        .sms-section {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 20px 24px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }
        .sms-section h3 {
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sms-status-sent {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #d1fae5;
            color: #065f46;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }
        .sms-status-pending {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fef3c7;
            color: #92400e;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .config-notice {
            background: #fef3c7;
            border: 1.5px solid #f59e0b;
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            font-size: 13px;
            color: #92400e;
            margin-bottom: 16px;
        }
        .config-notice.ok {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }

        .cron-box {
            background: #1e1e2e;
            color: #a6e3a1;
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            font-family: monospace;
            font-size: 12px;
            line-height: 1.7;
            overflow-x: auto;
        }

        .send-now-form {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        @media(max-width:900px) {
            .sms-layout { grid-template-columns: 1fr; }
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
                SMS Reminders
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔
                    <?php $n=getUnreadNotifications($conn,$_SESSION['user_id']);if($n>0): ?>
                        <span class="badge-count"><?= $n ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <?php if($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <!-- Stats -->
            <div class="stat-cards" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
                <div class="stat-card">
                    <div class="stat-label">Total SMS Sent</div>
                    <div class="stat-value"><?= $total_sent ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">24-Hour Reminders</div>
                    <div class="stat-value" style="color:var(--teal-dark);"><?= $sent_24h ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">1-Hour Reminders</div>
                    <div class="stat-value" style="color:#f59e0b;"><?= $sent_1h ?></div>
                </div>
            </div>

            <div class="sms-layout">

                <!-- LEFT: Upcoming + Logs -->
                <div>
                    <!-- Upcoming Appointments -->
                    <div class="sms-section">
                        <h3>📅 Upcoming Appointments (Next 3 Days)</h3>

                        <?php if (empty($upcoming)): ?>
                            <div class="empty-state" style="padding:20px;">
                                <div class="empty-icon">📭</div>
                                <h3>No upcoming appointments</h3>
                            </div>
                        <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Owner / Pet</th>
                                        <th>Date & Time</th>
                                        <th>Contact</th>
                                        <th>24h SMS</th>
                                        <th>1h SMS</th>
                                        <th>Send Now</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming as $ap):
                                        $contact = !empty($ap['appt_contact']) ? $ap['appt_contact'] : $ap['contact_no'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($ap['owner_name'] ?? '—') ?></strong><br>
                                            <span style="font-size:11px;color:var(--text-light);">
                                                🐾 <?= htmlspecialchars($ap['pet_name'] ?? '(Home Service)') ?>
                                            </span>
                                        </td>
                                        <td style="font-size:12px;">
                                            <?= formatDate($ap['appointment_date']) ?><br>
                                            <?= formatTime($ap['appointment_time']) ?>
                                        </td>
                                        <td style="font-size:12px;"><?= htmlspecialchars($contact ?? '—') ?></td>
                                        <td>
                                            <?php if ($ap['sent_24h']): ?>
                                                <span class="sms-status-sent">✅ Sent</span>
                                            <?php else: ?>
                                                <span class="sms-status-pending">⏳ Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($ap['sent_1h']): ?>
                                                <span class="sms-status-sent">✅ Sent</span>
                                            <?php else: ?>
                                                <span class="sms-status-pending">⏳ Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="send-now-form">
                                                <input type="hidden" name="action"  value="send_now">
                                                <input type="hidden" name="appt_id" value="<?= $ap['id'] ?>">
                                                <select name="reminder_type"
                                                        style="padding:4px 6px;border:1px solid var(--border);
                                                               border-radius:6px;font-size:11px;">
                                                    <option value="24h">24h</option>
                                                    <option value="1h">1h</option>
                                                </select>
                                                <button type="submit"
                                                        class="btn btn-teal btn-sm"
                                                        style="padding:4px 10px;font-size:11px;"
                                                        onclick="return confirm('Send SMS reminder now?')">
                                                    Send
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- SMS Log -->
                    <div class="sms-section">
                        <h3>📋 SMS Log (Last 50)</h3>
                        <?php if (empty($sms_logs)): ?>
                            <p style="font-size:13px;color:var(--text-light);">No SMS sent yet.</p>
                        <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Owner</th>
                                        <th>Contact</th>
                                        <th>Type</th>
                                        <th>Appt. Date</th>
                                        <th>Sent At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sms_logs as $log): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($log['owner_name'] ?? '—') ?></strong></td>
                                        <td style="font-size:12px;"><?= htmlspecialchars($log['contact_no'] ?? '—') ?></td>
                                        <td>
                                            <span style="background:<?= $log['reminder_type']==='24h'?'#dbeafe':'#fef9c3' ?>;
                                                         color:<?= $log['reminder_type']==='24h'?'#1d4ed8':'#92400e' ?>;
                                                         padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">
                                                <?= $log['reminder_type']==='24h' ? '🌙 24-Hour' : '⏰ 1-Hour' ?>
                                            </span>
                                        </td>
                                        <td style="font-size:12px;"><?= formatDate($log['appointment_date']) ?></td>
                                        <td style="font-size:11px;color:var(--text-light);">
                                            <?= date('M d, Y h:i A', strtotime($log['sent_at'])) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- RIGHT: Config + Test -->
                <div>
                    <!-- API Config -->
                    <div class="sms-section">
                        <h3>⚙️ SMS Configuration</h3>

                        <?php if (empty($api_key)): ?>
                            <div class="config-notice">
                                ⚠️ No API key configured. Enter your Semaphore API key below.
                            </div>
                        <?php else: ?>
                            <div class="config-notice ok">
                                ✅ API key is configured. SMS reminders are active.
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="action" value="save_config">
                            <div class="form-group">
                                <label>Semaphore API Key</label>
                                <input type="text" name="api_key"
                                       value="<?= htmlspecialchars($api_key) ?>"
                                       placeholder="Paste your API key here">
                                <small style="font-size:11px;color:var(--text-light);">
                                    Get your key at
                                    <a href="https://semaphore.co" target="_blank"
                                       style="color:var(--teal-dark);">semaphore.co</a>
                                </small>
                            </div>
                            <div class="form-group">
                                <label>Sender Name (max 11 chars)</label>
                                <input type="text" name="sender_name"
                                       value="<?= htmlspecialchars($sender_name) ?>"
                                       maxlength="11" placeholder="LigaoPet">
                            </div>
                            <button type="submit" class="btn btn-teal btn-sm">Save Config</button>
                        </form>
                    </div>

                    <!-- Test SMS -->
                    <div class="sms-section">
                        <h3>📱 Send Test SMS</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="test_sms">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="test_number"
                                       placeholder="e.g. 09261234567" required>
                            </div>
                            <div class="form-group">
                                <label>Message</label>
                                <textarea name="test_message" rows="3"
                                          style="resize:vertical;"
                                          placeholder="Test message...">Test SMS from Ligao Petcare &amp; Vet Clinic. System working!</textarea>
                            </div>
                            <button type="submit" class="btn btn-teal btn-sm"
                                    onclick="return confirm('Send test SMS now?')">
                                📤 Send Test
                            </button>
                        </form>
                    </div>

                    <!-- Cron Setup -->
                    <div class="sms-section">
                        <h3>🕐 Cron Job Setup</h3>
                        <p style="font-size:13px;color:var(--text-mid);margin-bottom:12px;">
                            Add this to your server's crontab to auto-send reminders
                            every 30 minutes:
                        </p>
                        <div class="cron-box">
                            # Edit crontab:<br>
                            crontab -e<br><br>
                            # Add this line:<br>
                            */30 * * * * php <?= dirname(dirname($_SERVER['SCRIPT_FILENAME'])) ?>/cron/send_appointment_reminders.php >> <?= dirname(dirname($_SERVER['SCRIPT_FILENAME'])) ?>/cron/sms_log.txt 2>&1
                        </div>
                        <p style="font-size:11px;color:var(--text-light);margin-top:10px;">
                            💡 On cPanel hosting: go to <strong>Cron Jobs</strong> and paste
                            the command above with the correct file path.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>