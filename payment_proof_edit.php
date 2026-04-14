<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: payment_proof_edit.php (ROOT)
// Purpose: Edit an existing payment proof (ref number + optional new screenshot)
//          Called by both the user side and the admin side.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id  = (int)$_SESSION['user_id'];
$is_admin = isAdmin();

$txn_id = (int)($_POST['transaction_id'] ?? 0);
$ref_no = trim($_POST['reference_number'] ?? '');

if (!$txn_id || empty($ref_no)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

// ── Locate the most recent proof for this transaction ────────
$proof = $conn->query(
    "SELECT pp.*, t.user_id AS txn_owner
     FROM payment_proofs pp
     JOIN transactions t ON t.id = pp.transaction_id
     WHERE pp.transaction_id = $txn_id
     ORDER BY pp.id DESC LIMIT 1"
)->fetch_assoc();

if (!$proof) {
    echo json_encode(['success' => false, 'error' => 'No payment proof found for this transaction.']);
    exit;
}

// ── Authorization check ───────────────────────────────────────
// Users may only edit their own proofs, and only while pending or rejected.
// Admins may edit any proof at any status.
if (!$is_admin) {
    if ((int)$proof['txn_owner'] !== $user_id) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
        exit;
    }
    if (!in_array($proof['status'], ['pending', 'rejected'])) {
        echo json_encode(['success' => false, 'error' => 'This proof has already been verified and cannot be edited.']);
        exit;
    }
}

$proof_id = (int)$proof['id'];

// ── Optional file upload ──────────────────────────────────────
$new_filename = null;

if (!empty($_FILES['proof_image']['name'])) {
    $ext     = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Use JPG, PNG, or WebP.']);
        exit;
    }

    if ($_FILES['proof_image']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File too large. Max 5MB.']);
        exit;
    }

    $upload_dir = __DIR__ . '/assets/uploads/payment_proofs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $new_filename = 'proof_' . $txn_id . '_edit_' . time() . '.' . $ext;

    if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $upload_dir . $new_filename)) {
        echo json_encode(['success' => false, 'error' => 'File upload failed. Please try again.']);
        exit;
    }

    // Delete the old file to avoid orphaned uploads (best-effort)
    $old_path = $upload_dir . $proof['proof_image'];
    if (is_file($old_path)) @unlink($old_path);
}

// ── Build UPDATE query ────────────────────────────────────────
$ref_safe = $conn->real_escape_string($ref_no);

// When a user re-submits/edits after rejection, reset status back to pending
// so the admin reviews it again. Admins editing do NOT reset status.
$status_clause = '';
if (!$is_admin && $proof['status'] === 'rejected') {
    $status_clause = ", status='pending', admin_note=NULL, verified_at=NULL";
}

if ($new_filename) {
    $file_safe = $conn->real_escape_string($new_filename);
    $sql = "UPDATE payment_proofs
            SET reference_number='$ref_safe', proof_image='$file_safe'
                $status_clause
            WHERE id=$proof_id";
} else {
    $sql = "UPDATE payment_proofs
            SET reference_number='$ref_safe'
                $status_clause
            WHERE id=$proof_id";
}

if (!$conn->query($sql)) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit;
}

// ── Notify admin when user re-submits after rejection ────────
if (!$is_admin && $proof['status'] === 'rejected') {
    $admin = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
    if ($admin) {
        $ref_display = $conn->real_escape_string($ref_no);
        $ref_label   = 'TXN-' . str_pad($txn_id, 5, '0', STR_PAD_LEFT);
        $msg = $conn->real_escape_string(
            "A client has resubmitted their payment proof for $ref_label. Ref: $ref_display. Please re-verify."
        );
        $conn->query(
            "INSERT INTO notifications (user_id, title, message, type)
             VALUES ({$admin['id']}, 'Payment Proof Resubmitted', '$msg', 'billing')"
        );
    }
}

echo json_encode(['success' => true]);
exit;