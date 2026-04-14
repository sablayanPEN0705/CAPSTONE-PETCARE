<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/invoice_receipt.php
// Purpose: Show user's confirmed/paid invoices as receipts
// ============================================================
require_once '../includes/auth.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id = (int)$_SESSION['user_id'];

// ── Single receipt view ──────────────────────────────────────
$view_id  = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_txn = null;
$txn_items = [];

if ($view_id) {
    $view_txn = getRow($conn,
        "SELECT t.*, u.name AS owner_name, p.name AS pet_name, p.species, p.breed
         FROM transactions t
         LEFT JOIN users u ON t.user_id = u.id
         LEFT JOIN pets  p ON t.pet_id  = p.id
         WHERE t.id=$view_id AND t.user_id=$user_id AND t.status='paid'");
    if ($view_txn) {
        $txn_items = getRows($conn,
            "SELECT * FROM transaction_items WHERE transaction_id=$view_id");
    } else {
        redirect('invoice_receipt.php');
    }
}

// ── Fetch all PAID transactions for this user ────────────────
$receipts = getRows($conn,
    "SELECT t.*, p.name AS pet_name, s.name AS svc_name
     FROM transactions t
     LEFT JOIN pets     p ON t.pet_id         = p.id
     LEFT JOIN appointments a ON t.appointment_id = a.id
     LEFT JOIN services  s ON a.service_id    = s.id
     WHERE t.user_id=$user_id AND t.status='paid'
     ORDER BY t.transaction_date DESC, t.created_at DESC");

// Count total paid
$total_paid_count  = count($receipts);
$total_paid_amount = array_sum(array_column($receipts, 'total_amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Receipt — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Receipt List ── */
        .receipt-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .receipt-header h2 {
            font-family: var(--font-head);
            font-size: 20px;
            font-weight: 800;
        }

        .receipt-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 20px;
        }
        .receipt-stat {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 16px 20px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        .receipt-stat .rs-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }
        .receipt-stat .rs-value {
            font-family: var(--font-head);
            font-size: 24px;
            font-weight: 800;
            color: #10b981;
        }

        .receipt-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .receipt-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 16px 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border-left: 4px solid #10b981;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        .receipt-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-left-color: var(--teal);
        }

        .rc-left { flex: 1; }
        .rc-ref {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-light);
            margin-bottom: 3px;
        }
        .rc-title {
            font-weight: 800;
            font-size: 14px;
            color: var(--text-dark);
            margin-bottom: 3px;
        }
        .rc-meta {
            font-size: 12px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .rc-right {
            text-align: right;
            flex-shrink: 0;
        }
        .rc-amount {
            font-family: var(--font-head);
            font-size: 18px;
            font-weight: 800;
            color: #10b981;
        }
        .rc-status {
            font-size: 11px;
            font-weight: 700;
            background: #d1fae5;
            color: #065f46;
            padding: 2px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 4px;
        }
        .rc-type {
            font-size: 11px;
            color: var(--text-light);
            margin-top: 3px;
        }

        /* ── Single Receipt View ── */
        .receipt-doc {
            background: rgba(255,255,255,0.96);
            border-radius: var(--radius);
            padding: 36px 40px;
            box-shadow: var(--shadow-md);
            max-width: 640px;
            margin: 0 auto;
            position: relative;
        }

        .receipt-doc::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: linear-gradient(90deg, #10b981, var(--teal));
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .receipt-clinic-header {
            text-align: center;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px dashed var(--border);
        }
        .receipt-clinic-header img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 8px;
            border: 3px solid var(--teal-light);
        }
        .receipt-clinic-header h2 {
            font-family: var(--font-head);
            font-size: 18px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 2px;
        }
        .receipt-clinic-header p {
            font-size: 12px;
            color: var(--text-light);
        }

        .receipt-badge-paid {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #d1fae5;
            color: #065f46;
            padding: 6px 18px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 13px;
            margin: 10px 0;
        }

        .receipt-ref {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 4px;
        }

        .receipt-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 20px 0;
            padding: 16px;
            background: #f8fffe;
            border-radius: var(--radius-sm);
            border: 1px solid var(--teal-light);
        }
        .rig-item .rig-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }
        .rig-item .rig-value {
            font-weight: 700;
            font-size: 13px;
            color: var(--text-dark);
        }

        .receipt-items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 13px;
        }
        .receipt-items-table thead th {
            background: var(--teal-light);
            color: var(--teal-dark);
            padding: 8px 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .receipt-items-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
        }
        .receipt-items-table tbody tr:last-child td { border-bottom: none; }
        .receipt-items-table td:last-child { text-align: right; font-weight: 700; }

        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 12px;
            background: #f0fffe;
            border-radius: var(--radius-sm);
            border: 2px solid var(--teal-light);
            margin-top: 8px;
        }
        .receipt-total-row .rt-label {
            font-family: var(--font-head);
            font-weight: 800;
            font-size: 15px;
        }
        .receipt-total-row .rt-amount {
            font-family: var(--font-head);
            font-weight: 800;
            font-size: 20px;
            color: #10b981;
        }

        .receipt-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 2px dashed var(--border);
        }
        .receipt-footer p {
            font-size: 12px;
            color: var(--text-light);
            margin-bottom: 4px;
        }
        .receipt-footer strong { color: var(--teal-dark); }

        .empty-receipts {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255,255,255,0.88);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        .empty-receipts .er-icon { font-size: 56px; margin-bottom: 14px; }
        .empty-receipts h3 { font-weight: 800; font-size: 18px; margin-bottom: 8px; }
        .empty-receipts p  { font-size: 13px; color: var(--text-light); }

        .print-btn {
            background: #1d6f42;
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }
        .print-btn:hover { background: #155232; }

        @media print {
            .sidebar, .topbar, .back-btn-wrap, .print-actions { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .page-body { padding: 0 !important; background: none !important; }
            .receipt-doc { box-shadow: none; border: 1px solid #ccc; }
        }

        @media(max-width:600px) {
            .receipt-summary { grid-template-columns: 1fr; }
            .receipt-info-grid { grid-template-columns: 1fr; }
            .receipt-doc { padding: 24px 18px; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_user.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title" style="display:flex;align-items:center;gap:10px;">
                <img src="../assets/css/images/pets/logo.png" alt="Logo"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.6);"
                     onerror="this.style.display='none';">
                Invoice Receipt
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔</a>
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <?php if ($view_txn): ?>
            <!-- ══ SINGLE RECEIPT VIEW ═══════════════════════ -->

            <div class="back-btn-wrap" style="margin-bottom:14px;">
    <a href="invoice_receipt.php" class="btn btn-gray btn-sm">← Back to Receipts</a>
</div>
            <div class="receipt-doc" id="printArea">

                <!-- Clinic Header -->
                <div class="receipt-clinic-header">
                    <img src="../assets/css/images/pets/logo.png" alt="Ligao Petcare"
                         onerror="this.style.display='none'">
                    <h2>Ligao Petcare & Veterinary Clinic</h2>
                    <p>National Highway, Zone 4, Tuburan, Ligao City, Albay</p>
                    <p>0926-396-7678 · 0950-138-1530</p>
                    <div class="receipt-badge-paid">✅ PAID — Official Receipt</div>
                    <div class="receipt-ref">
                        Receipt No.: <strong>#<?= str_pad($view_txn['id'], 6, '0', STR_PAD_LEFT) ?></strong>
                    </div>
                </div>

                <!-- Info Grid -->
                <div class="receipt-info-grid">
                    <div class="rig-item">
                        <div class="rig-label">Client</div>
                        <div class="rig-value"><?= htmlspecialchars($view_txn['owner_name']) ?></div>
                    </div>
                    <div class="rig-item">
                        <div class="rig-label">Date</div>
                        <div class="rig-value"><?= formatDate($view_txn['transaction_date']) ?></div>
                    </div>
                    <div class="rig-item">
                        <div class="rig-label">Pet</div>
                        <div class="rig-value">
                            <?php if ($view_txn['pet_name']): ?>
                                <?= htmlspecialchars($view_txn['pet_name']) ?>
                                <?php if ($view_txn['species']): ?>
                                    <span style="font-size:11px;color:var(--text-light);">
                                        (<?= ucfirst($view_txn['species']) ?>
                                        <?= $view_txn['breed'] ? '/ '.htmlspecialchars($view_txn['breed']) : '' ?>)
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="rig-item">
                        <div class="rig-label">Payment Status</div>
                        <div class="rig-value" style="color:#10b981;">✅ Paid</div>
                    </div>
                </div>

                <!-- Items Table -->
                <p style="font-weight:800;font-size:13px;margin-bottom:8px;color:var(--text-dark);">
                    Services / Products Availed:
                </p>

                <?php if (!empty($txn_items)): ?>
                    <table class="receipt-items-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Type</th>
                                <th style="text-align:right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($txn_items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td>
                                        <span style="font-size:11px;background:<?= $item['item_type']==='product'?'#e0f7fa':'#e8f5e9' ?>;
                                                     color:<?= $item['item_type']==='product'?'#00838f':'#2e7d32' ?>;
                                                     padding:2px 8px;border-radius:20px;font-weight:700;">
                                            <?= $item['item_type']==='product' ? '🛍️ Product' : '⚕️ Service' ?>
                                        </span>
                                    </td>
                                    <td><?= formatPeso($item['price']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="font-size:13px;color:var(--text-light);padding:12px 0;">
                        No itemized details available.
                    </p>
                <?php endif; ?>

                <!-- Total -->
                <div class="receipt-total-row">
                    <span class="rt-label">TOTAL AMOUNT PAID:</span>
                    <span class="rt-amount"><?= formatPeso($view_txn['total_amount']) ?></span>
                </div>

                <?php if ($view_txn['notes'] && $view_txn['notes'] !== 'Product purchase by user'): ?>
                    <p style="font-size:12px;color:var(--text-light);margin-top:12px;">
                        <strong>Notes:</strong> <?= htmlspecialchars($view_txn['notes']) ?>
                    </p>
                <?php endif; ?>

                <!-- Footer -->
                <div class="receipt-footer">
                    <p>Thank you for trusting <strong>Ligao Petcare & Veterinary Clinic</strong>!</p>
                    <p>This is your official payment receipt. Please keep for your records.</p>
                    <p style="margin-top:8px;font-size:11px;">
                        Generated on <?= date('F d, Y \a\t h:i A') ?>
                    </p>
                </div>

            </div><!-- /receipt-doc -->

            <?php else: ?>
            <!-- ══ RECEIPTS LIST VIEW ═════════════════════════ -->

            <div class="receipt-header">
                <h2>🧾 Invoice Receipts</h2>
            </div>

            <!-- Summary Stats -->
            <div class="receipt-summary">
                <div class="receipt-stat">
                    <div class="rs-label">Total Receipts</div>
                    <div class="rs-value"><?= $total_paid_count ?></div>
                </div>
                <div class="receipt-stat">
                    <div class="rs-label">Total Amount Paid</div>
                    <div class="rs-value" style="font-size:18px;"><?= formatPeso($total_paid_amount) ?></div>
                </div>
            </div>

            <?php if (empty($receipts)): ?>
                <div class="empty-receipts">
                    <div class="er-icon">🧾</div>
                    <h3>No Receipts Yet</h3>
                    <p>Your paid invoices will appear here once the clinic confirms your payment.</p>
                    <div style="margin-top:20px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                        <a href="appointments.php" class="btn btn-teal btn-sm">📅 Book Appointment</a>
                        <a href="products.php"     class="btn btn-gray btn-sm">🛍️ View Products</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="receipt-list">
                    <?php foreach ($receipts as $r):
                        // Determine label — service name or product purchase
                        if ($r['notes'] === 'Product purchase by user') {
                            $title   = 'Product Purchase';
                            $type_lbl = '🛍️ Product';
                            $type_color = '#00838f';
                            $type_bg    = '#e0f7fa';
                        } elseif ($r['svc_name']) {
                            $title   = $r['svc_name'];
                            $type_lbl = '⚕️ Service';
                            $type_color = '#2e7d32';
                            $type_bg    = '#e8f5e9';
                        } else {
                            $title   = 'Clinic Invoice';
                            $type_lbl = '📄 Invoice';
                            $type_color = '#374151';
                            $type_bg    = '#f3f4f6';
                        }
                    ?>
                    <a href="invoice_receipt.php?view=<?= $r['id'] ?>" class="receipt-card">
                        <div class="rc-left">
                            <div class="rc-ref">
                                Receipt #<?= str_pad($r['id'], 6, '0', STR_PAD_LEFT) ?>
                            </div>
                            <div class="rc-title"><?= htmlspecialchars($title) ?></div>
                            <div class="rc-meta">
                                <span>📅 <?= formatDate($r['transaction_date']) ?></span>
                                <?php if ($r['pet_name']): ?>
                                    <span>🐾 <?= htmlspecialchars($r['pet_name']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="rc-right">
                            <div class="rc-amount"><?= formatPeso($r['total_amount']) ?></div>
                            <div class="rc-status">✅ Paid</div>
                            <div class="rc-type">
                                <span style="background:<?= $type_bg ?>;color:<?= $type_color ?>;
                                             padding:1px 8px;border-radius:20px;font-size:10px;font-weight:700;">
                                    <?= $type_lbl ?>
                                </span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php endif; // end view_txn ?>

        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>

<?php include_once __DIR__ . '/../includes/chatbot-widget.php'; ?>

</body>
</html>