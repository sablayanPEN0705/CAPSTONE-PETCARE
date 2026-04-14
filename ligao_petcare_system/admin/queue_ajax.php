<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/queue_ajax.php
// Purpose: JSON endpoint for Queue Whiteboard (GET + POST)
// ============================================================
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ─── POST: Update queue entry status ────────────────────────
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $id     = intval($body['id']     ?? 0);
    $status = $body['status']        ?? '';

    $allowed = ['waiting', 'in_progress', 'done'];
    if (!$id || !in_array($status, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    // Build timestamp fields based on transition
    $extra = '';
    if ($status === 'in_progress') {
        $extra = ', called_at = NOW(), done_at = NULL';
    } elseif ($status === 'done') {
        $extra = ', done_at = NOW()';
    } elseif ($status === 'waiting') {
        $extra = ', called_at = NULL, done_at = NULL';
    }

    $sql  = "UPDATE queue_entries SET status = ? {$extra}
             WHERE id = ? AND queue_date = CURDATE()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Entry not found or not today\'s queue']);
    }
    $stmt->close();
    exit;
}

// ─── GET: Return full board data ─────────────────────────────
if ($method === 'GET') {
    $sql = "
        SELECT
            qe.id,
            qe.appointment_id,
            qe.queue_number,
            qe.status,
            qe.called_at,
            qe.done_at,
            u.name                                           AS patient_name,
            COALESCE(p.name, 'N/A')                         AS pet_name,
            COALESCE(s.name, 'N/A')                         AS service_name,
            DATE_FORMAT(a.appointment_time, '%h:%i %p')     AS appt_time
        FROM queue_entries qe
        JOIN appointments a  ON a.id  = qe.appointment_id
        JOIN users u         ON u.id  = a.user_id
        LEFT JOIN pets p     ON p.id  = a.pet_id
        LEFT JOIN services s ON s.id  = a.service_id
        WHERE qe.queue_date = CURDATE()
        ORDER BY qe.queue_number ASC
    ";

    $result  = $conn->query($sql);
    $entries = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $entries[] = $row;
        }
        $result->free();
    }

    echo json_encode(['success' => true, 'entries' => $entries]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);