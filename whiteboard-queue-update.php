<?php
// whiteboard-queue-update.php
require_once 'includes/auth.php';
requireLogin();
requireAdmin();

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

$appt_id   = (int)($input['appt_id']      ?? 0);
$queue_status = sanitize($conn, $input['queue_status'] ?? '');

$allowed_qs = ['none','arrived','waiting','ongoing','done'];
if (!$appt_id || !in_array($queue_status, $allowed_qs)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Update queue_status
$ok = mysqli_query($conn,
    "UPDATE appointments SET queue_status='$queue_status' WHERE id=$appt_id");

if (!$ok) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

// ── When marked Done → also complete the appointment ─────────
if ($queue_status === 'done') {
    mysqli_query($conn,
        "UPDATE appointments SET status='completed' WHERE id=$appt_id AND status IN ('pending','confirmed')");

    // Notify the user
    $appt = getRow($conn,
        "SELECT a.user_id, p.name AS pet_name, s.name AS svc_name
         FROM appointments a
         LEFT JOIN pets p     ON a.pet_id     = p.id
         LEFT JOIN services s ON a.service_id = s.id
         WHERE a.id = $appt_id");

    if ($appt) {
        $uid     = (int)$appt['user_id'];
        $pet     = mysqli_real_escape_string($conn, $appt['pet_name'] ?? 'your pet');
        $svc     = mysqli_real_escape_string($conn, $appt['svc_name'] ?? 'service');
        $msg     = "Your appointment for $pet ($svc) has been completed.";
        mysqli_query($conn,
            "INSERT INTO notifications (user_id, title, message, type)
             VALUES ($uid, 'Appointment Completed', '$msg', 'appointment')");

        // Auto-create pending transaction if not yet existing
        $existing = getRow($conn, "SELECT id FROM transactions WHERE appointment_id=$appt_id");
        if (!$existing) {
            $pet_row = getRow($conn, "SELECT pet_id FROM appointments WHERE id=$appt_id");
            $pet_id_val = $pet_row && $pet_row['pet_id'] ? (int)$pet_row['pet_id'] : 'NULL';
            mysqli_query($conn,
                "INSERT INTO transactions (user_id, pet_id, appointment_id, total_amount, status, transaction_date)
                 VALUES ($uid, $pet_id_val, $appt_id, 0, 'pending', CURDATE())");
        }
    }
}

// ── When un-marked from Done (toggled back) → revert to confirmed ─
if ($queue_status === 'none') {
    // Only revert if it was completed by whiteboard (not manually set elsewhere)
    // Safe to attempt — won't affect manually cancelled ones
    mysqli_query($conn,
        "UPDATE appointments SET status='confirmed' 
         WHERE id=$appt_id AND status='completed'");
}

echo json_encode(['success' => true]);