<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: cron/send_appointment_reminders.php
// Purpose: Send SMS reminders 24 hours and 1 hour before
//          appointments. Run via cron job every 30 minutes.
//
// CRON SETUP (run every 30 minutes):
//   */30 * * * * php /path/to/your/project/cron/send_appointment_reminders.php
// ============================================================

require_once __DIR__ . '/../includes/db.php';

// ── Semaphore SMS API Config ─────────────────────────────────
define('SMS_API_KEY',    'YOUR_SEMAPHORE_API_KEY');  // ← Replace with your key
define('SMS_SENDER',     'LigaoPet');                // ← Max 11 chars (your sender name)
define('CLINIC_NAME',    'Ligao Petcare & Vet Clinic');

// ── Send SMS via Semaphore ────────────────────────────────────
function sendSMS($contact_no, $message) {
    // Normalize PH number to 09XXXXXXXXX format
    $number = preg_replace('/\D/', '', $contact_no); // strip non-digits
    if (strlen($number) === 11 && substr($number, 0, 2) === '09') {
        // already 09XXXXXXXXX — good
    } elseif (strlen($number) === 10 && substr($number, 0, 1) === '9') {
        $number = '0' . $number; // add leading 0
    } elseif (strlen($number) === 12 && substr($number, 0, 2) === '63') {
        $number = '0' . substr($number, 2); // +639XX → 09XX
    } else {
        error_log("SMS: Invalid number format: $contact_no");
        return false;
    }

    $payload = http_build_query([
        'apikey'      => SMS_API_KEY,
        'number'      => $number,
        'message'     => $message,
        'sendername'  => SMS_SENDER,
    ]);

    $ch = curl_init('https://api.semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("SMS curl error: $err");
        return false;
    }

    $result = json_decode($response, true);
    // Semaphore returns array of message objects on success
    if (is_array($result) && isset($result[0]['message_id'])) {
        return true;
    }

    error_log("SMS failed for $number: $response");
    return false;
}

// ── Log SMS sent (avoid duplicates) ──────────────────────────
// We store sent reminders in a simple table: sms_reminders
// Create it if not exists:
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS sms_reminders (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT NOT NULL,
        reminder_type  ENUM('24h','1h') NOT NULL,
        sent_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_appt_type (appointment_id, reminder_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Current time ──────────────────────────────────────────────
$now        = new DateTime();
$now_ts     = $now->getTimestamp();

// ── Fetch upcoming confirmed/pending appointments ─────────────
$appointments = getRows($conn,
    "SELECT a.id,
            a.appointment_date,
            a.appointment_time,
            a.appointment_type,
            a.address,
            a.contact        AS appt_contact,
            u.name           AS owner_name,
            u.contact_no     AS user_contact,
            p.name           AS pet_name,
            s.name           AS svc_name
     FROM appointments a
     LEFT JOIN users    u ON a.user_id    = u.id
     LEFT JOIN pets     p ON a.pet_id     = p.id
     LEFT JOIN services s ON a.service_id = s.id
     WHERE a.status IN ('pending','confirmed')
       AND a.appointment_date >= CURDATE()
       AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
     ORDER BY a.appointment_date ASC, a.appointment_time ASC");

$sent_count = 0;

foreach ($appointments as $appt) {
    $appt_id      = (int)$appt['id'];
    $appt_datetime= $appt['appointment_date'] . ' ' . $appt['appointment_time'];
    $appt_ts      = strtotime($appt_datetime);

    if (!$appt_ts) continue;

    $diff_seconds = $appt_ts - $now_ts;
    $diff_hours   = $diff_seconds / 3600;

    // Determine which contact number to use
    // For home service, prefer the contact given during booking
    $contact = !empty($appt['appt_contact'])
        ? $appt['appt_contact']
        : $appt['user_contact'];

    if (empty($contact)) continue;

    $owner   = $appt['owner_name'] ?: 'Pet Owner';
    $pet     = $appt['pet_name']   ?: 'your pet';
    $service = $appt['svc_name']
        ?: ($appt['appointment_type'] === 'home_service' ? 'Home Service' : 'Clinic Visit');
    $date_fmt = date('F d, Y', strtotime($appt['appointment_date']));
    $time_fmt = date('g:i A', strtotime($appt['appointment_time']));
    $type_lbl = $appt['appointment_type'] === 'home_service' ? 'Home Service' : 'Clinic';

    // ── 24-HOUR REMINDER ────────────────────────────────────
    // Send if appointment is 23–25 hours away (window avoids duplicates)
    if ($diff_hours >= 23 && $diff_hours <= 25) {
        // Check if already sent
        $already = getRow($conn,
            "SELECT id FROM sms_reminders
             WHERE appointment_id=$appt_id AND reminder_type='24h'");

        if (!$already) {
            $msg = "Hi $owner! Reminder from " . CLINIC_NAME . ": "
                 . "Your $type_lbl appointment for $pet ($service) "
                 . "is TOMORROW, $date_fmt at $time_fmt. "
                 . "Please arrive 10 mins early. "
                 . "To cancel, call 0926-396-7678 at least 24hrs ahead.";

            if (sendSMS($contact, $msg)) {
                mysqli_query($conn,
                    "INSERT IGNORE INTO sms_reminders (appointment_id, reminder_type)
                     VALUES ($appt_id, '24h')");
                $sent_count++;
                echo "[24h] Sent to $owner ($contact) for Appt #$appt_id\n";
            }
        }
    }

    // ── 1-HOUR REMINDER ─────────────────────────────────────
    // Send if appointment is 55–65 minutes away
    if ($diff_seconds >= (55*60) && $diff_seconds <= (65*60)) {
        $already = getRow($conn,
            "SELECT id FROM sms_reminders
             WHERE appointment_id=$appt_id AND reminder_type='1h'");

        if (!$already) {
            $msg = "Hi $owner! " . CLINIC_NAME . " reminds you: "
                 . "Your $type_lbl appointment for $pet ($service) "
                 . "is in about 1 HOUR at $time_fmt today. "
                 . "We look forward to seeing you! "
                 . "Questions? Call 0926-396-7678.";

            if (sendSMS($contact, $msg)) {
                mysqli_query($conn,
                    "INSERT IGNORE INTO sms_reminders (appointment_id, reminder_type)
                     VALUES ($appt_id, '1h')");
                $sent_count++;
                echo "[1h]  Sent to $owner ($contact) for Appt #$appt_id\n";
            }
        }
    }
}

echo "Done. $sent_count SMS sent at " . date('Y-m-d H:i:s') . "\n";