<?php
// ============================================================
// File: payment_proof_verify.php (ROOT)
// Purpose: Admin verifies or rejects payment proofs
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$proof_id = (int)($input['proof_id'] ?? 0);
$action   = $input['action']     ?? ''; // 'verify' or 'reject'
$note     = $input['note']       ?? '';

if (!$proof_id || !in_array($action, ['verify', 'reject'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$proof = $conn->query(
    "SELECT pp.*, t.user_id, t.total_amount
     FROM payment_proofs pp
     JOIN transactions t ON t.id = pp.transaction_id
     WHERE pp.id = $proof_id"
)->fetch_assoc();

if (!$proof) {
    echo json_encode(['success' => false, 'error' => 'Proof not found']);
    exit;
}

$note_safe   = $conn->real_escape_string($note);
$now         = date('Y-m-d H:i:s');
$new_status  = $action === 'verify' ? 'verified' : 'rejected';

$conn->query(
    "UPDATE payment_proofs
     SET status='$new_status', admin_note='$note_safe', verified_at='$now'
     WHERE id=$proof_id"
);

if ($action === 'verify') {
    // Mark transaction as paid
    $conn->query(
        "UPDATE transactions SET status='paid'
         WHERE id={$proof['transaction_id']}"
    );

    // Notify user
    $amt = number_format($proof['total_amount'], 2);
    $method_label = strtoupper($proof['method']);
    $msg = $conn->real_escape_string(
        "Your $method_label payment of ₱$amt has been verified! Your receipt is now available."
    );
    $conn->query(
        "INSERT INTO notifications (user_id, title, message, type)
         VALUES ({$proof['user_id']}, 'Payment Verified ✅', '$msg', 'billing')"
    );
} else {
    // Notify user of rejection
    $reason = $note ? " Reason: $note" : '';
    $method_label = strtoupper($proof['method']);
    $msg = $conn->real_escape_string(
        "Your $method_label payment proof was not verified.$reason Please resubmit or pay in person."
    );
    $conn->query(
        "INSERT INTO notifications (user_id, title, message, type)
         VALUES ({$proof['user_id']}, 'Payment Proof Rejected ❌', '$msg', 'billing')"
    );
}

echo json_encode(['success' => true]);
exit;