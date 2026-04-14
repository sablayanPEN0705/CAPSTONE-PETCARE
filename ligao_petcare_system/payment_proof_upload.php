<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: payment_proof_upload.php (ROOT)
// Purpose: Handle GCash / PayMaya proof upload from user
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$txn_id  = (int)($_POST['transaction_id'] ?? 0);
$method  = $_POST['method'] ?? '';
$ref_no  = trim($_POST['reference_number'] ?? '');

$allowed_methods = ['gcash', 'paymaya'];
if (!$txn_id || !in_array($method, $allowed_methods) || empty($ref_no)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

// Verify transaction belongs to this user and is pending
$txn = $conn->query(
    "SELECT id, status FROM transactions
     WHERE id=$txn_id AND user_id=$user_id AND status='pending'"
)->fetch_assoc();

if (!$txn) {
    echo json_encode(['success' => false, 'error' => 'Transaction not found or already paid.']);
    exit;
}

// Check no existing pending proof
$existing = $conn->query(
    "SELECT id FROM payment_proofs
     WHERE transaction_id=$txn_id AND status='pending'"
)->fetch_assoc();

if ($existing) {
    echo json_encode(['success' => false, 'error' => 'A proof is already submitted and under review.']);
    exit;
}

// Handle file upload
if (empty($_FILES['proof_image']['name'])) {
    echo json_encode(['success' => false, 'error' => 'Please attach a screenshot.']);
    exit;
}

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

$filename = 'proof_' . $txn_id . '_' . time() . '.' . $ext;
if (!move_uploaded_file($_FILES['proof_image']['tmp_name'], $upload_dir . $filename)) {
    echo json_encode(['success' => false, 'error' => 'Upload failed. Try again.']);
    exit;
}

$method_safe = $conn->real_escape_string($method);
$ref_safe    = $conn->real_escape_string($ref_no);
$file_safe   = $conn->real_escape_string($filename);

$conn->query(
    "INSERT INTO payment_proofs (transaction_id, user_id, method, reference_number, proof_image)
     VALUES ($txn_id, $user_id, '$method_safe', '$ref_safe', '$file_safe')"
);

// Notify admin
$admin = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
if ($admin) {
    $method_label = strtoupper($method);
    $msg = $conn->real_escape_string(
        "New $method_label payment proof submitted for TXN-" . str_pad($txn_id, 5, '0', STR_PAD_LEFT) .
        ". Ref: $ref_no. Please verify."
    );
    $conn->query(
        "INSERT INTO notifications (user_id, title, message, type)
         VALUES ({$admin['id']}, 'Payment Proof Submitted', '$msg', 'billing')"
    );
}

echo json_encode(['success' => true, 'message' => 'Payment proof submitted! We will verify within 24 hours.']);
exit;