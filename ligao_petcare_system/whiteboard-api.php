<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: whiteboard-api.php  (place in ROOT folder)
// Purpose: Real-time patient flow data API for the whiteboard
//
// PRIVACY MODEL:
//   Admin  → sees full details for every appointment today
//   User   → sees ALL appointments today (like a waiting-room
//             board) but only anonymous data: ID, time, type,
//             service, queue status. Their OWN appointment is
//             flagged with is_mine=true so the widget can
//             highlight it differently.
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// ── Auto-add queue_status column if it doesn't exist yet ─────
$conn->query("
    ALTER TABLE appointments
    ADD COLUMN IF NOT EXISTS queue_status
        ENUM('none','arrived','waiting','ongoing','done')
        NOT NULL DEFAULT 'none'
");

$is_admin = isAdmin();
$user_id  = (int)$_SESSION['user_id'];
$today    = date('Y-m-d');

// ── Query ─────────────────────────────────────────────────────
// Admin  → all today, respecting archived flags
// User   → also all today (waiting-room view), but the payload
//          below will strip private fields from other people's rows
if ($is_admin) {
    $where = "a.appointment_date = '$today'
              AND a.status != 'cancelled'
              AND (a.archived IS NULL OR a.archived = 0)
              AND (u.archived  IS NULL OR u.archived  = 0)";
} else {
    $where = "a.appointment_date = '$today'
              AND a.status NOT IN ('cancelled','completed')
              AND (a.archived IS NULL OR a.archived = 0)";
}
$sql = "
    SELECT
        a.id,
        a.user_id,
        a.appointment_type,
        a.appointment_time,
        a.appointment_date,
        a.status,
        a.queue_status,
        a.address,
        a.contact,
        a.notes,
        u.name       AS owner_name,
        u.contact_no AS owner_contact,
        p.name       AS pet_name,
        p.species,
        p.breed,
        s.name       AS service_name,
        s.category   AS service_category
    FROM appointments a
    LEFT JOIN users    u ON u.id = a.user_id
    LEFT JOIN pets     p ON p.id = a.pet_id
    LEFT JOIN services s ON s.id = a.service_id
    WHERE $where
    ORDER BY
        FIELD(a.status, 'confirmed', 'pending', 'completed', 'cancelled'),
        a.appointment_time ASC
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

// ── Status display map ────────────────────────────────────────
$STATUS_MAP = [
    'pending'   => 'waiting',
    'confirmed' => 'ongoing',
    'completed' => 'done',
    'cancelled' => 'cancelled',
];

// ── Counts ────────────────────────────────────────────────────
$counts = [
    'pending'   => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0,
];

$appointments = [];

while ($row = $result->fetch_assoc()) {

    $appt_id      = (int)$row['id'];
    $appt_user_id = (int)$row['user_id'];
    $status       = $row['status'];
    $queue_status = $row['queue_status'] ?? 'none';
    $is_mine      = (!$is_admin && $appt_user_id === $user_id);

    if (isset($counts[$status])) $counts[$status]++;

    // ── Home-service: fetch per-pet sub-rows ─────────────────
    $home_pets = [];
    if ($row['appointment_type'] === 'home_service') {
        $hsp = $conn->query("
            SELECT hsp.pet_name, hsp.species, hsp.breed, s.name AS service_name
            FROM home_service_pets hsp
            LEFT JOIN services s ON s.id = hsp.service_id
            WHERE hsp.appointment_id = $appt_id
        ");
        if ($hsp) {
            while ($hp = $hsp->fetch_assoc()) {
                if ($is_admin) {
                    // Admin: full detail
                    $home_pets[] = [
                        'pet_name'     => $hp['pet_name']     ?? '',
                        'species'      => $hp['species']      ?? '',
                        'breed'        => $hp['breed']        ?? '',
                        'service_name' => $hp['service_name'] ?? '',
                    ];
                } else {
                    // User (own OR other): species + service only — no pet name
                    $home_pets[] = [
                        'species'      => $hp['species']      ?? '',
                        'service_name' => $hp['service_name'] ?? '',
                    ];
                }
            }
        }
    }

    // ── Build payload ─────────────────────────────────────────
    if ($is_admin) {
        // ── ADMIN: full details ───────────────────────────────
        $appointments[] = [
            'id'               => $appt_id,
            'appointment_date' => $row['appointment_date'],
            'appointment_time' => $row['appointment_time'],
            'appointment_type' => $row['appointment_type'],
            'status'           => $status,
            'queue_status'     => $queue_status,
            'display_status'   => $STATUS_MAP[$status] ?? $status,
            'owner_name'       => $row['owner_name']       ?? '',
            'owner_contact'    => $row['owner_contact']    ?? '',
            'pet_name'         => $row['pet_name']         ?? '',
            'species'          => $row['species']          ?? '',
            'breed'            => $row['breed']            ?? '',
            'service_name'     => $row['service_name']     ?? '',
            'service_category' => $row['service_category'] ?? '',
            'address'          => $row['address']          ?? '',
            'contact'          => $row['contact']          ?? '',
            'notes'            => $row['notes']            ?? '',
            'home_pets'        => $home_pets,
            'is_mine'          => true, // always true for admin (irrelevant)
        ];

    } else {
        // ── USER: anonymous waiting-room payload ──────────────
        // Every row shows only: ID, time, date, type, service,
        // queue_status, species (for icon only).
        // is_mine=true on their own row so the widget can
        // highlight it with "Your appointment" label.
        //
        // Deliberately excluded for ALL rows (own and others):
        //   owner_name, pet_name, address, contact, notes,
        //   owner_contact, breed
        $appointments[] = [
            'id'               => $appt_id,
            'appointment_date' => $row['appointment_date'],
            'appointment_time' => $row['appointment_time'],
            'appointment_type' => $row['appointment_type'],
            'status'           => $status,
            'queue_status'     => $queue_status,
            'display_status'   => $STATUS_MAP[$status] ?? $status,
            'species'          => $row['species']          ?? '', // used for species icon only
            'service_name'     => $row['service_name']     ?? '',
            'service_category' => $row['service_category'] ?? '',
            'home_pets'        => $home_pets,
            'is_mine'          => $is_mine,   // true only for their own appointment
        ];
    }
}

// ── Upcoming next-7-days count ────────────────────────────────
// Admin: all upcoming | User: only their own upcoming
if ($is_admin) {
    $up_sql = "
        SELECT COUNT(*) AS cnt FROM appointments
        WHERE appointment_date > '$today'
          AND appointment_date <= DATE_ADD('$today', INTERVAL 7 DAY)
          AND status NOT IN ('cancelled')
          AND (archived IS NULL OR archived = 0)
    ";
} else {
    $up_sql = "
        SELECT COUNT(*) AS cnt FROM appointments
        WHERE appointment_date > '$today'
          AND appointment_date <= DATE_ADD('$today', INTERVAL 7 DAY)
          AND user_id = $user_id
          AND status NOT IN ('cancelled')
          AND (archived IS NULL OR archived = 0)
    ";
}

$up_res      = $conn->query($up_sql);
$upcoming_7d = $up_res ? (int)($up_res->fetch_assoc()['cnt'] ?? 0) : 0;

echo json_encode([
    'success'      => true,
    'is_admin'     => $is_admin,
    'today'        => $today,
    'server_time'  => date('H:i:s'),
    'appointments' => $appointments,
    'counts'       => $counts,
    'upcoming_7d'  => $upcoming_7d,
]);
exit;
?>