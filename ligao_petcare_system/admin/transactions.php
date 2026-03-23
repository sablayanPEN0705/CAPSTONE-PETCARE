<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/transactions.php
// Purpose: Manage all billing records, create invoices
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// ── Handle Create Transaction ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_transaction') {
        $user_id     = (int)($_POST['user_id']    ?? 0);
        $pet_id      = (int)($_POST['pet_id']     ?? 0);
        $appt_id     = (int)($_POST['appt_id']    ?? 0);
        $status      = sanitize($conn, $_POST['status']   ?? 'pending');
        $txn_date    = sanitize($conn, $_POST['txn_date'] ?? date('Y-m-d'));
        $notes       = sanitize($conn, $_POST['notes']    ?? '');
        $item_names  = $_POST['item_name']  ?? [];
        $item_types  = $_POST['item_type']  ?? [];
        $item_prices = $_POST['item_price'] ?? [];

        if (!$user_id) redirect('transactions.php?error=Please select a client.');

        $total = 0;
        foreach ($item_prices as $p) $total += (float)$p;

        $sql = "INSERT INTO transactions
                    (user_id, pet_id, appointment_id, total_amount, status, transaction_date, notes)
                VALUES ($user_id," .
               ($pet_id  ? $pet_id  : 'NULL') . "," .
               ($appt_id ? $appt_id : 'NULL') .
               ",$total,'$status','$txn_date','$notes')";

        if (mysqli_query($conn, $sql)) {
            $txn_id = mysqli_insert_id($conn);

            foreach ($item_names as $i => $iname) {
                $iname  = sanitize($conn, preg_replace('/ \[(SOLD OUT|LOW STOCK)\]$/', '', $iname));
                $itype  = sanitize($conn, $item_types[$i]  ?? 'service');
                $iprice = (float)($item_prices[$i] ?? 0);
                if ($iname) {
                    mysqli_query($conn,
                        "INSERT INTO transaction_items (transaction_id, item_type, item_name, price)
                         VALUES ($txn_id,'$itype','$iname',$iprice)");
                }
            }

            if ($appt_id) {
                mysqli_query($conn, "UPDATE appointments SET status='completed' WHERE id=$appt_id");
            }

            foreach ($item_names as $i => $iname) {
                $itype_check = sanitize($conn, $item_types[$i] ?? '');
                if ($itype_check === 'product') {
                    $pname_check = sanitize($conn, preg_replace('/ \[(SOLD OUT|LOW STOCK)\]$/', '', $iname));
                    $prod_found  = getRow($conn, "SELECT id, quantity FROM products WHERE name='$pname_check' LIMIT 1");
                    if ($prod_found) {
                        $new_qty = max(0, (int)$prod_found['quantity'] - 1);
                        $new_st  = $new_qty <= 0 ? 'out_of_stock' : ($new_qty <= 5 ? 'low_stock' : 'in_stock');
                        mysqli_query($conn, "UPDATE products SET quantity=$new_qty, status='$new_st' WHERE id={$prod_found['id']}");
                    }
                }
            }

            $client = getRow($conn, "SELECT name FROM users WHERE id=$user_id");
            if ($client) {
                mysqli_query($conn,
                    "INSERT INTO notifications (user_id, title, message, type)
                     VALUES ($user_id, 'New Invoice',
                     'A billing summary of ₱" . number_format($total,2) . " has been created for your visit.','billing')");
            }
            redirect('transactions.php?success=Transaction created successfully!');
        } else {
            redirect('transactions.php?error=Failed: ' . mysqli_error($conn));
        }
    }

    if ($action === 'update_status') {
        $tid    = (int)$_POST['txn_id'];
        $status = sanitize($conn, $_POST['status'] ?? '');
        if (in_array($status, ['paid','pending','overdue'])) {
            mysqli_query($conn, "UPDATE transactions SET status='$status' WHERE id=$tid");
            if ($status === 'paid') {
                $txn = getRow($conn, "SELECT user_id, total_amount FROM transactions WHERE id=$tid");
                if ($txn) {
                    $uid = (int)$txn['user_id'];
                    $amt = number_format($txn['total_amount'], 2);
                    mysqli_query($conn,
                        "INSERT INTO notifications (user_id, title, message, type)
                         VALUES ($uid, 'Payment Confirmed',
                         'Your payment of ₱$amt has been confirmed. Your receipt is now available.','billing')");
                }
            }
        }
        redirect('transactions.php?success=Transaction status updated.&view=' . (int)$_POST['txn_id']);
    }

    if ($action === 'delete_transaction') {
        $tid = (int)$_POST['txn_id'];
        mysqli_query($conn, "DELETE FROM transactions WHERE id=$tid");
        redirect('transactions.php?success=Transaction deleted.');
    }
}

// ── Stats ─────────────────────────────────────────────────────
$paid_count    = countRows($conn, 'transactions', "status='paid'");
$pending_count = countRows($conn, 'transactions', "status='pending'");
$overdue_count = countRows($conn, 'transactions', "status='overdue'");
$total_revenue = getRow($conn, "SELECT COALESCE(SUM(total_amount),0) AS rev FROM transactions WHERE status='paid'")['rev'] ?? 0;

$monthly_rev = getRows($conn,
    "SELECT MONTH(transaction_date) AS mo, SUM(total_amount) AS total
     FROM transactions WHERE status='paid' AND YEAR(transaction_date)=YEAR(CURDATE())
     GROUP BY MONTH(transaction_date) ORDER BY mo ASC");
$rev_data = array_fill(1, 12, 0);
foreach ($monthly_rev as $mr) $rev_data[(int)$mr['mo']] = (float)$mr['total'];

$paid_total    = getRow($conn, "SELECT COALESCE(SUM(total_amount),0) AS t FROM transactions WHERE status='paid'")['t'];
$pending_total = getRow($conn, "SELECT COALESCE(SUM(total_amount),0) AS t FROM transactions WHERE status='pending'")['t'];
$overdue_total = getRow($conn, "SELECT COALESCE(SUM(total_amount),0) AS t FROM transactions WHERE status='overdue'")['t'];

// ── Billing detail view ───────────────────────────────────────
$view_id   = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_txn  = null;
$txn_items = [];
if ($view_id) {
    $view_txn = getRow($conn,
        "SELECT t.*, u.name AS owner_name, u.address, u.contact_no,
                p.name AS pet_name, p.species, p.breed,
                a.appointment_date, a.appointment_type,
                s.name AS service_name
         FROM transactions t
         LEFT JOIN users u ON t.user_id = u.id
         LEFT JOIN pets  p ON t.pet_id  = p.id
         LEFT JOIN appointments a ON t.appointment_id = a.id
         LEFT JOIN services s ON a.service_id = s.id
         WHERE t.id=$view_id");
    if ($view_txn) {
        $txn_items = getRows($conn, "SELECT * FROM transaction_items WHERE transaction_id=$view_id");
    }
}

// ── Transactions list ─────────────────────────────────────────
$search_txn    = sanitize($conn, $_GET['search_txn']    ?? '');
$filter_status = sanitize($conn, $_GET['filter_status'] ?? '');
$filter_source = sanitize($conn, $_GET['filter_source'] ?? '');
$sort_by       = sanitize($conn, $_GET['sort_by']       ?? 'desc');

$where = '1=1';
if ($search_txn)    $where .= " AND u.name LIKE '%$search_txn%'";
if ($filter_status) $where .= " AND t.status='$filter_status'";
if ($filter_source === 'product')     $where .= " AND t.notes = 'Product purchase by user'";
if ($filter_source === 'appointment') $where .= " AND t.appointment_id IS NOT NULL AND (t.notes IS NULL OR t.notes != 'Product purchase by user')";
if ($filter_source === 'manual')      $where .= " AND t.appointment_id IS NULL AND (t.notes IS NULL OR t.notes != 'Product purchase by user')";

$transactions = getRows($conn,
    "SELECT t.*, u.name AS client_name, p.name AS pet_name
     FROM transactions t
     LEFT JOIN users u ON t.user_id = u.id
     LEFT JOIN pets  p ON t.pet_id  = p.id
     WHERE $where
     ORDER BY t.created_at " . ($sort_by === 'asc' ? 'ASC' : 'DESC') . " LIMIT 100");

$clients    = getRows($conn, "SELECT id, name FROM users WHERE role='user' ORDER BY name ASC");
$all_appts  = getRows($conn,
    "SELECT a.id, u.name AS owner_name, p.name AS pet_name, a.appointment_date
     FROM appointments a
     LEFT JOIN users u ON a.user_id = u.id
     LEFT JOIN pets  p ON a.pet_id  = p.id
     WHERE a.status IN ('confirmed','completed')
     ORDER BY a.appointment_date DESC LIMIT 50");
$all_prods  = getRows($conn, "SELECT name, price, status FROM products ORDER BY name ASC");
$all_svcs   = getRows($conn, "SELECT name, price_min FROM services WHERE status='available' ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions — Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        .txn-charts {
            display: grid; grid-template-columns: 1fr 260px;
            gap: 16px; margin-bottom: 24px;
        }
        .chart-box {
            background: rgba(255,255,255,0.9); border-radius: var(--radius);
            padding: 20px; box-shadow: var(--shadow);
        }
        .chart-box-title { font-weight:700; font-size:12px; color:var(--text-light); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:14px; }
        .txn-table-section { background:rgba(255,255,255,0.9); border-radius:var(--radius); padding:20px; box-shadow:var(--shadow); }
        .txn-table-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:16px; }
        .txn-table-header h3 { font-family:var(--font-head); font-size:17px; font-weight:700; }
        .txn-filters { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .status-select-sm { padding:7px 10px; border:1.5px solid var(--border); border-radius:var(--radius-sm); font-size:12px; background:#fff; outline:none; }
        .line-item-row { display:grid; grid-template-columns:1fr 100px 100px auto; gap:8px; align-items:center; margin-bottom:8px; }
        .remove-item-btn { background:#fee2e2; color:#dc2626; border:none; border-radius:4px; padding:5px 10px; cursor:pointer; font-size:13px; font-weight:700; }
        .remove-item-btn:hover { background:#ef4444; color:#fff; }
        #lineItemsContainer label { font-size:11px; color:var(--text-light); font-weight:700; }
        .running-total { text-align:right; font-weight:800; font-size:16px; color:var(--teal-dark); padding:10px 0; }

        /* ── Receipt / Billing Detail ── */
        .receipt-page-wrap {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* The printable receipt card — narrower */
        .receipt-card {
            width: 360px;
            flex-shrink: 0;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.12);
            font-family: 'Courier New', monospace;
            overflow: hidden;
        }
        .receipt-card-inner { padding: 26px 22px; }

        /* Clinic header — compact */
        .receipt-clinic-header {
            text-align: center;
            padding-bottom: 14px;
            margin-bottom: 14px;
            border-bottom: 2px dashed #ccc;
        }
        .receipt-clinic-icon  { font-size: 30px; margin-bottom: 4px; }
        .receipt-clinic-name  { font-size: 16px; font-weight: 900; letter-spacing: 1.5px; }
        .receipt-clinic-sub   { font-size: 11px; color: #666; }
        .receipt-clinic-addr  { font-size: 10px; color: #999; margin-top: 3px; }

        /* Meta rows */
        .receipt-meta { font-size: 12px; line-height: 2; margin-bottom: 14px; }
        .receipt-meta-row { display: flex; justify-content: space-between; }
        .receipt-meta-row .lbl  { color: #888; }
        .receipt-meta-row .val  { font-weight: 700; }
        .receipt-meta-row .val.paid    { color: #065f46; }
        .receipt-meta-row .val.pending { color: #92400e; }
        .receipt-meta-row .val.overdue { color: #991b1b; }

        /* Dashed dividers */
        .receipt-divider {
            border: none;
            border-top: 2px dashed #ccc;
            margin: 12px 0;
        }

        /* Client section */
        .receipt-section-title {
            font-size: 11px; font-weight: 900;
            letter-spacing: 1px; text-transform: uppercase;
            margin-bottom: 6px; color: #333;
        }
        .receipt-client-info { font-size: 12px; line-height: 1.9; color: #444; margin-bottom: 14px; }
        .receipt-client-info .lbl { color: #888; display: inline-block; width: 64px; }

        /* Items table */
        .receipt-items { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 4px; }
        .receipt-items th {
            text-align: left; padding: 4px 0;
            border-bottom: 1px solid #ccc; font-size: 10px;
            font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; color: #555;
        }
        .receipt-items th:last-child { text-align: right; }
        .receipt-items td { padding: 6px 0; border-bottom: 1px dotted #eee; color: #333; }
        .receipt-items td:last-child { text-align: right; font-weight: 700; }
        .receipt-items tr:last-child td { border-bottom: none; }
        .receipt-total-row {
            display: flex; justify-content: space-between;
            font-weight: 900; font-size: 14px;
            padding: 10px 0 0; border-top: 2px dashed #ccc; margin-top: 6px;
        }
        .receipt-total-row .total-val { color: #007b83; }

        /* Footer note */
        .receipt-footer {
            text-align: center; font-size: 10px; color: #aaa;
            padding-top: 12px; margin-top: 4px;
            border-top: 2px dashed #ccc; line-height: 1.8;
        }

        /* Type badge inline */
        .item-type-pill {
            font-size: 9px; font-weight: 700; padding: 1px 6px;
            border-radius: 10px; margin-left: 4px;
        }
        .item-type-pill.service { background: #e8f5e9; color: #2e7d32; }
        .item-type-pill.product { background: #e0f7fa; color: #00838f; }

        /* Action sidebar — compact, shorter */
        .receipt-actions-sidebar {
            width: 300px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .receipt-action-box {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 18px 20px;
            box-shadow: var(--shadow);
        }
        .receipt-action-box h4 {
            font-family: var(--font-head);
            font-size: 13px; font-weight: 800;
            color: var(--text-dark); margin-bottom: 12px;
        }

        /* ── Print styles ── */
        @media print {
            @page {
                size: A4;
                margin: 15mm;
            }

            /* Hide everything */
            body * { visibility: hidden !important; }

            /* Show only the receipt */
            #printableReceipt,
            #printableReceipt * { visibility: visible !important; }

            #printableReceipt {
                position: absolute !important;
                top: 0 !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                width: 360px !important;
                box-shadow: none !important;
                border-radius: 4px !important;
                font-family: 'Courier New', monospace !important;
                background: #fff !important;
                border: 1px solid #ddd !important;
            }

            .receipt-card-inner {
                padding: 20px 18px !important;
            }

            /* Dashed dividers must explicitly print */
            .receipt-divider {
                border: none !important;
                border-top: 2px dashed #999 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Preserve status colors in print */
            .receipt-meta-row .val.paid    { color: #065f46 !important; }
            .receipt-meta-row .val.pending { color: #92400e !important; }
            .receipt-meta-row .val.overdue { color: #991b1b !important; }
            .receipt-total-row .total-val  { color: #007b83 !important; }

            /* Prevent page break inside receipt */
            #printableReceipt { page-break-inside: avoid !important; }
        }

        /* Sold-out modal */
        #soldOutModal { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.55); backdrop-filter:blur(3px); align-items:center; justify-content:center; }
        #soldOutModal.show { display:flex !important; }
        @keyframes slideUp { from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);} }
        .sold-out-box { background:#fff;border-radius:16px;padding:32px 28px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.25);animation:slideUp 0.25s ease;text-align:center; }
        .sold-out-icon { width:64px;height:64px;border-radius:50%;background:#fff5f5;border:2px solid #fee2e2;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 16px; }
        .sold-out-title { font-family:var(--font-head);font-size:18px;font-weight:800;color:#1a1a2e;margin-bottom:8px; }
        .sold-out-subtitle { font-size:13px;color:#6b7280;margin-bottom:16px;line-height:1.6; }
        .sold-out-list { background:#fff5f5;border:1px solid #fee2e2;border-radius:10px;padding:4px 16px;margin-bottom:16px;text-align:left;max-height:160px;overflow-y:auto; }
        .sold-out-list-item { display:flex;align-items:center;gap:8px;padding:9px 0;border-bottom:1px solid #fee2e2;font-size:13px;color:#374151; }
        .sold-out-list-item:last-child { border-bottom:none; }
        .sold-out-note { font-size:12px;color:#6b7280;margin-bottom:20px;background:#f0fffe;border:1px solid #b2ebf2;border-radius:8px;padding:10px 14px;line-height:1.6; }
        .sold-out-actions { display:flex;gap:10px;justify-content:center; }
        .sold-out-cancel-btn { padding:10px 24px;border-radius:8px;border:1.5px solid #d1d5db;background:#fff;color:#374151;font-weight:700;font-size:13px;cursor:pointer;font-family:var(--font-main); }
        .sold-out-proceed-btn { padding:10px 24px;border-radius:8px;border:none;background:linear-gradient(135deg,var(--teal),#0097a7);color:#fff;font-weight:700;font-size:13px;cursor:pointer;box-shadow:0 4px 12px rgba(0,150,136,0.3);font-family:var(--font-main); }

        @media(max-width:700px) {
            .txn-charts { grid-template-columns:1fr; }
            .line-item-row { grid-template-columns:1fr 80px 80px auto; gap:4px; }
            .receipt-page-wrap { flex-direction:column; justify-content:flex-start; }
            .receipt-card { width:100%; }
            .receipt-actions-sidebar { width:100%; }
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
                TRANSACTION
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn" title="Notifications">
                    🔔
                    <?php $unread_notif = getUnreadNotifications($conn, $_SESSION['user_id']); if ($unread_notif > 0): ?>
                        <span class="badge-count"><?= $unread_notif ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <?php if ($view_txn):
                $ref_no   = 'TXN-' . str_pad($view_txn['id'], 5, '0', STR_PAD_LEFT);
                $is_appt  = !empty($view_txn['appointment_id']);
                $type_lbl = $is_appt
                    ? ucfirst(str_replace('_', ' ', $view_txn['appointment_type'] ?? 'Clinic'))
                    : 'Product Purchase';
            ?>
            <!-- ══ BILLING / RECEIPT VIEW ═════════════════════ -->
            <div style="margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;" class="no-print">
                <a href="transactions.php" class="btn btn-gray btn-sm">← Back to Transactions</a>
                <button onclick="window.print()"
                        class="btn btn-teal btn-sm"
                        style="display:flex;align-items:center;gap:6px;">
                    🖨️ Print Receipt
                </button>
            </div>

            <div class="receipt-page-wrap">

                <!-- ── Printable Receipt Card ── -->
                <div class="receipt-card" id="printableReceipt">
                    <div class="receipt-card-inner">

                        <!-- Clinic Header -->
                        <div class="receipt-clinic-header">
                            <div class="receipt-clinic-icon">🐾</div>
                            <div class="receipt-clinic-name">LIGAO PETCARE</div>
                            <div class="receipt-clinic-sub">&amp; Veterinary Clinic</div>
                            <div class="receipt-clinic-addr">Ligao City, Albay</div>
                        </div>

                        <!-- Receipt Meta -->
                        <div class="receipt-meta">
                            <div class="receipt-meta-row">
                                <span class="lbl">Receipt No.:</span>
                                <span class="val"><?= $ref_no ?></span>
                            </div>
                            <div class="receipt-meta-row">
                                <span class="lbl">Date:</span>
                                <span class="val"><?= formatDate($view_txn['transaction_date']) ?></span>
                            </div>
                            <div class="receipt-meta-row">
                                <span class="lbl">Type:</span>
                                <span class="val"><?= htmlspecialchars($type_lbl) ?></span>
                            </div>
                            <div class="receipt-meta-row">
                                <span class="lbl">Status:</span>
                                <span class="val <?= $view_txn['status'] ?>">
                                    <?= $view_txn['status'] === 'paid'
                                        ? '✔ PAID'
                                        : strtoupper($view_txn['status']) ?>
                                </span>
                            </div>
                        </div>

                        <hr class="receipt-divider">

                        <!-- Client Info -->
                        <div class="receipt-section-title">Client</div>
                        <div class="receipt-client-info">
                            <div><span class="lbl">Name:</span> <strong><?= htmlspecialchars($view_txn['owner_name'] ?? '—') ?></strong></div>
                            <?php if ($view_txn['address']): ?>
                                <div><span class="lbl">Address:</span> <?= htmlspecialchars($view_txn['address']) ?></div>
                            <?php endif; ?>
                            <?php if ($view_txn['contact_no']): ?>
                                <div><span class="lbl">Contact:</span> <?= htmlspecialchars($view_txn['contact_no']) ?></div>
                            <?php endif; ?>
                            <?php if ($view_txn['pet_name']): ?>
                                <div>
                                    <span class="lbl">Pet:</span>
                                    <strong><?= htmlspecialchars($view_txn['pet_name']) ?></strong>
                                    <?php if ($view_txn['species']): ?>
                                        (<?= ucfirst($view_txn['species']) ?><?= $view_txn['breed'] ? ', ' . htmlspecialchars($view_txn['breed']) : '' ?>)
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($view_txn['service_name']): ?>
                                <div><span class="lbl">Service:</span> <?= htmlspecialchars($view_txn['service_name']) ?></div>
                            <?php endif; ?>
                            <?php if ($view_txn['appointment_date']): ?>
                                <div><span class="lbl">Appt. Date:</span> <?= formatDate($view_txn['appointment_date']) ?></div>
                            <?php endif; ?>
                        </div>

                        <hr class="receipt-divider">

                        <!-- Line Items -->
                        <div class="receipt-section-title">Items</div>
                        <?php if (!empty($txn_items)): ?>
                            <table class="receipt-items">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th style="text-align:right;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($txn_items as $item): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($item['item_name']) ?>
                                                <span class="item-type-pill <?= $item['item_type'] ?>">
                                                    <?= $item['item_type'] === 'product' ? '🛍️' : '⚕️' ?>
                                                </span>
                                            </td>
                                            <td><?= formatPeso($item['price']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif ($view_txn['notes'] && $view_txn['notes'] !== 'Product purchase by user'): ?>
                            <div style="font-size:11px;color:#888;padding:6px 0;">
                                <?= htmlspecialchars($view_txn['notes']) ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size:11px;color:#aaa;padding:6px 0;">No line items recorded.</div>
                        <?php endif; ?>

                        <!-- Total -->
                        <div class="receipt-total-row">
                            <span>TOTAL</span>
                            <span class="total-val"><?= formatPeso($view_txn['total_amount']) ?></span>
                        </div>

                        <!-- Footer -->
                        <div class="receipt-footer">
                            <div>Payment is collected <strong>in person</strong> at the clinic.</div>
                            <div>Thank you for trusting Ligao Petcare! 🐾</div>
                            <div style="margin-top:6px;font-size:9px;">
                                — Official Receipt — <?= $ref_no ?> —
                            </div>
                        </div>

                    </div><!-- /receipt-card-inner -->
                </div><!-- /receipt-card -->

                <!-- ── Actions Sidebar ── -->
                <div class="receipt-actions-sidebar no-print">

                    <!-- Update Status -->
                    <div class="receipt-action-box">
                        <h4>⚙️ Update Payment Status</h4>
                        <form method="POST" style="display:flex;flex-direction:column;gap:10px;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="txn_id" value="<?= $view_txn['id'] ?>">
                            <select name="status" class="status-select-sm" style="width:100%;padding:9px 12px;font-size:13px;">
                                <option value="paid"    <?= $view_txn['status']==='paid'    ?'selected':'' ?>>✔ Paid</option>
                                <option value="pending" <?= $view_txn['status']==='pending' ?'selected':'' ?>>⏳ Pending</option>
                                <option value="overdue" <?= $view_txn['status']==='overdue' ?'selected':'' ?>>⚠️ Overdue</option>
                            </select>
                            <button type="submit" class="btn btn-teal" style="width:100%;font-size:13px;">
                                Update Status
                            </button>
                        </form>
                    </div>

                    <!-- Print -->
                    <div class="receipt-action-box">
                        <h4>🖨️ Print Receipt</h4>
                        <button onclick="window.print()"
                                style="width:100%;padding:11px;background:linear-gradient(135deg,#1565c0,#1976d2);
                                       color:#fff;border:none;border-radius:var(--radius-sm);
                                       font-weight:800;font-size:13px;cursor:pointer;
                                       display:flex;align-items:center;justify-content:center;gap:6px;">
                            📄 Print / Save as PDF
                        </button>
                    </div>

                    <!-- Delete -->
                    <div class="receipt-action-box">
                        <h4 style="color:#ef4444;">🗑️ Delete Transaction</h4>
                        <form method="POST"
                              onsubmit="return confirm('Permanently delete this transaction? This cannot be undone.')">
                            <input type="hidden" name="action" value="delete_transaction">
                            <input type="hidden" name="txn_id" value="<?= $view_txn['id'] ?>">
                            <button type="submit"
                                    style="width:100%;padding:10px;background:#fee2e2;color:#dc2626;
                                           border:1.5px solid #fca5a5;border-radius:var(--radius-sm);
                                           font-weight:700;font-size:13px;cursor:pointer;">
                                🗑️ Delete Transaction
                            </button>
                        </form>
                    </div>

                </div><!-- /receipt-actions-sidebar -->
            </div><!-- /receipt-page-wrap -->

            <?php else: ?>
            <!-- ══ TRANSACTIONS LIST ══════════════════════════ -->

            <!-- Stats -->
            <div class="stat-cards" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
                <div class="stat-card">
                    <div class="stat-label" style="color:#10b981;">Paid Invoices</div>
                    <div class="stat-value" style="color:#10b981;"><?= $paid_count ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label" style="color:#f59e0b;">Pending Payments</div>
                    <div class="stat-value" style="color:#f59e0b;"><?= $pending_count ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label" style="color:#ef4444;">Overdue Bills</div>
                    <div class="stat-value" style="color:#ef4444;"><?= $overdue_count ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label" style="color:var(--blue-header);">Total Revenue</div>
                    <div class="stat-value" style="font-size:22px;color:var(--blue-header);">
                        ₱<?= number_format($total_revenue,0) ?>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="txn-charts">
                <div class="chart-box">
                    <div class="chart-box-title">Monthly Revenue (<?= date('Y') ?>)</div>
                    <canvas id="revenueChart" height="120"></canvas>
                </div>
                <div class="chart-box">
                    <div class="chart-box-title">Payment Status</div>
                    <canvas id="statusDonut" height="160"></canvas>
                    <div style="margin-top:10px;font-size:12px;">
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;"><div style="width:10px;height:10px;border-radius:50%;background:#10b981;"></div>Paid ₱<?= number_format($paid_total,0) ?></div>
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;"><div style="width:10px;height:10px;border-radius:50%;background:#f59e0b;"></div>Pending ₱<?= number_format($pending_total,0) ?></div>
                        <div style="display:flex;align-items:center;gap:6px;"><div style="width:10px;height:10px;border-radius:50%;background:#ef4444;"></div>Overdue ₱<?= number_format($overdue_total,0) ?></div>
                    </div>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="txn-table-section">
                <div class="txn-table-header">
                    <h3>Transaction Records</h3>
                    <button class="btn btn-teal btn-sm"
                            onclick="document.getElementById('createTxnModal').classList.add('open');if(itemCount===0)addLineItem();">
                        + Create Invoice
                    </button>
                </div>

                <form method="GET" class="txn-filters" style="margin-bottom:16px;">
                    <div class="search-box">
                        <span>🔍</span>
                        <input type="text" name="search_txn" placeholder="Search Transaction..."
                               value="<?= htmlspecialchars($search_txn) ?>">
                    </div>
                    <select name="filter_status" class="status-select-sm">
                        <option value="">All Status</option>
                        <option value="paid"    <?= $filter_status==='paid'    ?'selected':'' ?>>Paid</option>
                        <option value="pending" <?= $filter_status==='pending' ?'selected':'' ?>>Pending</option>
                        <option value="overdue" <?= $filter_status==='overdue' ?'selected':'' ?>>Overdue</option>
                    </select>
                    <select name="filter_source" class="status-select-sm">
                        <option value="">All Sources</option>
                        <option value="product"     <?= $filter_source==='product'    ?'selected':'' ?>>🛍️ Product</option>
                        <option value="appointment" <?= $filter_source==='appointment'?'selected':'' ?>>📅 Appointment</option>
                        <option value="manual"      <?= $filter_source==='manual'     ?'selected':'' ?>>📄 Manual</option>
                    </select>
                    <select name="sort_by" class="status-select-sm">
                        <option value="desc" <?= $sort_by==='desc'?'selected':'' ?>>Newest First</option>
                        <option value="asc"  <?= $sort_by==='asc' ?'selected':'' ?>>Oldest First</option>
                    </select>
                    <button type="submit" class="btn btn-teal btn-sm">Filter</button>
                    <a href="transactions.php" class="btn btn-gray btn-sm">Reset</a>
                </form>

                <?php if (empty($transactions)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📄</div>
                        <h3>No transactions found</h3>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Client Name</th>
                                    <th>Pet</th>
                                    <th>Date</th>
                                    <th>Total Amount</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Receipt</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $i => $txn): ?>
                                    <tr>
                                        <td style="color:var(--text-light);"><?= $i+1 ?></td>
                                        <td><strong><?= htmlspecialchars($txn['client_name']??'—') ?></strong></td>
                                        <td><?= htmlspecialchars($txn['pet_name']??'—') ?></td>
                                        <td style="font-size:12px;"><?= formatDate($txn['transaction_date']) ?></td>
                                        <td style="font-weight:800;color:var(--teal-dark);"><?= formatPeso($txn['total_amount']) ?></td>
                                        <td>
                                            <?php if ($txn['notes']==='Product purchase by user'): ?>
                                                <span style="background:#e0f7fa;color:#00838f;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">🛍️ Product</span>
                                            <?php elseif ($txn['appointment_id']): ?>
                                                <span style="background:#e8f5e9;color:#2e7d32;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">📅 Appointment</span>
                                            <?php else: ?>
                                                <span style="background:#f3f4f6;color:#374151;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">📄 Manual</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= statusBadge($txn['status']) ?></td>
                                        <td>
                                            <a href="transactions.php?view=<?= $txn['id'] ?>"
                                               style="color:var(--teal-dark);font-weight:700;font-size:12px;
                                                      display:inline-flex;align-items:center;gap:4px;text-decoration:underline;">
                                                🖨️ View Receipt
                                            </a>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:4px;">
                                                <a href="transactions.php?view=<?= $txn['id'] ?>" class="btn btn-gray btn-sm">Edit</a>
                                                <form method="POST" style="display:inline;"
                                                      onsubmit="return confirm('Delete this transaction?')">
                                                    <input type="hidden" name="action" value="delete_transaction">
                                                    <input type="hidden" name="txn_id" value="<?= $txn['id'] ?>">
                                                    <button type="submit" class="btn btn-red btn-sm">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Create Transaction Modal -->
<div class="modal-overlay" id="createTxnModal">
    <div class="modal" style="max-width:580px;">
        <button class="modal-close"
                onclick="document.getElementById('createTxnModal').classList.remove('open')">×</button>
        <h3 class="modal-title">📄 Create Invoice / Transaction</h3>
        <form method="POST" id="txnForm">
            <input type="hidden" name="action" value="create_transaction">
            <div class="form-row">
                <div class="form-group">
                    <label>Client *</label>
                    <select name="user_id" required onchange="loadClientPets(this.value)">
                        <option value="">— Select Client —</option>
                        <?php foreach ($clients as $cl): ?>
                            <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pet</label>
                    <select name="pet_id" id="petDropdown">
                        <option value="">— Select Client First —</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Linked Appointment (optional)</label>
                    <select name="appt_id">
                        <option value="">— None —</option>
                        <?php foreach ($all_appts as $ap): ?>
                            <option value="<?= $ap['id'] ?>">
                                #<?= $ap['id'] ?> — <?= htmlspecialchars($ap['owner_name']) ?>
                                / <?= htmlspecialchars($ap['pet_name'] ?? 'Multi-pet') ?>
                                (<?= formatDate($ap['appointment_date']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Transaction Date</label>
                    <input type="date" name="txn_date" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <p style="font-weight:700;font-size:13px;margin:10px 0 8px;">Services / Products Availed</p>
            <div style="display:grid;grid-template-columns:1fr 100px 100px 36px;gap:6px;margin-bottom:6px;">
                <label style="font-size:11px;color:var(--text-light);">Item Name</label>
                <label style="font-size:11px;color:var(--text-light);">Type</label>
                <label style="font-size:11px;color:var(--text-light);">Price (₱)</label>
                <span></span>
            </div>

            <datalist id="productSuggestions">
                <?php foreach ($all_prods as $ap): ?>
                    <option value="<?= htmlspecialchars($ap['name']) . ($ap['status']==='out_of_stock' ? ' [SOLD OUT]' : ($ap['status']==='low_stock' ? ' [LOW STOCK]' : '')) ?>"
                            data-price="<?= $ap['price'] ?>" data-status="<?= $ap['status'] ?>">
                <?php endforeach; ?>
            </datalist>
            <datalist id="serviceSuggestions">
                <?php foreach ($all_svcs as $as): ?>
                    <option value="<?= htmlspecialchars($as['name']) ?>" data-price="<?= $as['price_min'] ?>">
                <?php endforeach; ?>
            </datalist>

            <div id="lineItemsContainer"></div>

            <button type="button" class="btn btn-gray btn-sm" style="margin-bottom:10px;"
                    onclick="addLineItem()">+ Add Item</button>
            <div class="running-total" id="runningTotal">Total: ₱0.00</div>

            <div class="form-row">
                <div class="form-group">
                    <label>Payment Status</label>
                    <select name="status">
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes (optional)</label>
                    <input type="text" name="notes" placeholder="Any additional notes...">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('createTxnModal').classList.remove('open')">Cancel</button>
                <button type="button" class="btn btn-teal" onclick="submitInvoice()">Create Invoice</button>
            </div>
        </form>
    </div>
</div>

<!-- Sold-Out Warning Modal -->
<div id="soldOutModal">
    <div class="sold-out-box" id="soldOutBox">
        <div class="sold-out-icon">⚠️</div>
        <div class="sold-out-title">Product Out of Stock</div>
        <div class="sold-out-subtitle">The following product(s) are <strong style="color:#ef4444;">out of stock</strong> and will be <strong>removed</strong> from the invoice:</div>
        <div class="sold-out-list" id="soldOutList"></div>
        <div class="sold-out-note">💡 All other items will still be <strong>included</strong>. Do you want to proceed?</div>
        <div class="sold-out-actions">
            <button class="sold-out-cancel-btn" onclick="closeSoldOutModal()">Cancel</button>
            <button class="sold-out-proceed-btn" onclick="confirmSoldOutProceed()">✅ Yes, Proceed</button>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// Charts
const months  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const revData = <?= json_encode(array_values($rev_data)) ?>;

new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: { labels:months, datasets:[{label:'Revenue',data:revData,backgroundColor:'#1565c0',borderRadius:5}] },
    options: { responsive:true, plugins:{legend:{display:false}},
        scales:{ y:{beginAtZero:true,ticks:{font:{size:11},callback:v=>'₱'+v.toLocaleString()},grid:{color:'rgba(0,0,0,0.05)'}},
                 x:{ticks:{font:{size:10}},grid:{display:false}} } }
});

new Chart(document.getElementById('statusDonut'), {
    type: 'doughnut',
    data: { labels:['Paid','Pending','Overdue'],
        datasets:[{data:[<?= $paid_total ?>,<?= $pending_total ?>,<?= $overdue_total ?>],
            backgroundColor:['#10b981','#f59e0b','#ef4444'],borderWidth:2,borderColor:'#fff'}] },
    options: { responsive:true, plugins:{legend:{display:false}}, cutout:'60%' }
});

// Line items
let itemCount = 0;

function addLineItem(name='', type='service', price='') {
    const container = document.getElementById('lineItemsContainer');
    const idx = itemCount++;
    const div = document.createElement('div');
    div.className = 'line-item-row'; div.id = 'item-row-' + idx;
    div.innerHTML = `
        <input type="text" name="item_name[${idx}]" value="${name}"
               list="${type==='product'?'productSuggestions':'serviceSuggestions'}"
               id="item-name-${idx}" placeholder="e.g. CheckUp, Collar"
               oninput="autoFillPrice(${idx}, this.value)"
               style="padding:7px 10px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:12px;font-family:var(--font-main);">
        <select name="item_type[${idx}]" id="item-type-${idx}" onchange="updateItemList(${idx}, this.value)"
                style="padding:7px 8px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:12px;font-family:var(--font-main);">
            <option value="service" ${type==='service'?'selected':''}>Service</option>
            <option value="product" ${type==='product'?'selected':''}>Product</option>
        </select>
        <input type="number" name="item_price[${idx}]" id="item-price-${idx}" value="${price}"
               placeholder="0.00" min="0" step="0.01" oninput="updateTotal()"
               style="padding:7px 10px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:12px;font-family:var(--font-main);">
        <button type="button" class="remove-item-btn" onclick="removeItem(${idx})">✕</button>`;
    container.appendChild(div);
    updateTotal();
}

function removeItem(idx) { const el=document.getElementById('item-row-'+idx); if(el){el.remove();updateTotal();} }
function updateTotal() {
    let total=0;
    document.querySelectorAll('[name^="item_price"]').forEach(p=>total+=parseFloat(p.value||0));
    document.getElementById('runningTotal').textContent='Total: ₱'+total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
}

function autoFillPrice(idx, val) {
    const typeEl=document.getElementById('item-type-'+idx);
    const priceEl=document.getElementById('item-price-'+idx);
    const nameEl=document.getElementById('item-name-'+idx);
    if(!typeEl||!priceEl)return;
    const datalist=document.getElementById(typeEl.value==='product'?'productSuggestions':'serviceSuggestions');
    if(!datalist)return;
    const oldWarn=document.getElementById('warn-'+idx);
    if(oldWarn)oldWarn.remove();
    nameEl.style.borderColor='var(--border)';
    for(const opt of datalist.options){
        const optBase=opt.value.replace(/ \[(SOLD OUT|LOW STOCK)\]$/,'');
        if(optBase.toLowerCase()===val.replace(/ \[(SOLD OUT|LOW STOCK)\]$/,'').toLowerCase()){
            const p=opt.getAttribute('data-price');
            const status=opt.getAttribute('data-status');
            if(p){priceEl.value=parseFloat(p).toFixed(2);updateTotal();}
            if(status==='out_of_stock'){
                nameEl.style.borderColor='#ef4444';
                const warn=document.createElement('div');
                warn.id='warn-'+idx;warn.style='color:#ef4444;font-size:11px;font-weight:700;margin-top:2px;';
                warn.textContent='❌ This product is out of stock!';
                document.getElementById('item-row-'+idx).after(warn);
            }else if(status==='low_stock'){
                nameEl.style.borderColor='#f59e0b';
                const warn=document.createElement('div');
                warn.id='warn-'+idx;warn.style='color:#f59e0b;font-size:11px;font-weight:700;margin-top:2px;';
                warn.textContent='⚠️ Low stock — only a few left!';
                document.getElementById('item-row-'+idx).after(warn);
            }
            break;
        }
    }
}

function updateItemList(idx,type){
    const nameEl=document.getElementById('item-name-'+idx);
    if(nameEl){nameEl.setAttribute('list',type==='product'?'productSuggestions':'serviceSuggestions');nameEl.value='';}
    const priceEl=document.getElementById('item-price-'+idx);
    if(priceEl){priceEl.value='';updateTotal();}
}

function loadClientPets(userId){
    const sel=document.getElementById('petDropdown');
    sel.innerHTML='<option value="">Loading...</option>';
    if(!userId){sel.innerHTML='<option value="">— None —</option>';return;}
    fetch(`../includes/get_pets.php?user_id=${userId}`)
        .then(r=>r.json())
        .then(pets=>{
            sel.innerHTML='<option value="">— None —</option>';
            pets.forEach(p=>{sel.innerHTML+=`<option value="${p.id}">${p.name} (${p.species})</option>`;});
        }).catch(()=>{sel.innerHTML='<option value="">— None —</option>';});
}

document.querySelectorAll('.modal-overlay').forEach(o=>{
    o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open');});
});

let pendingSoldOut=[];

function submitInvoice(){
    pendingSoldOut=[];
    document.querySelectorAll('[id^="warn-"]').forEach(w=>{
        if(w.textContent.includes('out of stock')){
            const idx=w.id.replace('warn-','');
            const nameEl=document.getElementById('item-name-'+idx);
            if(nameEl)pendingSoldOut.push({idx,name:nameEl.value.replace(/ \[SOLD OUT\]$/,'')});
        }
    });
    document.querySelectorAll('[id^="item-name-"]').forEach(input=>{
        if(input.value.includes('[SOLD OUT]')){
            const idx=input.id.replace('item-name-','');
            if(!pendingSoldOut.find(i=>i.idx===idx))pendingSoldOut.push({idx,name:input.value.replace(/ \[SOLD OUT\]$/,'')});
        }
    });
    if(pendingSoldOut.length>0){
        document.getElementById('soldOutList').innerHTML=pendingSoldOut.map(i=>
            `<div class="sold-out-list-item"><span style="color:#ef4444;font-size:16px;flex-shrink:0;">✕</span>
             <strong>${i.name}</strong>
             <span style="margin-left:auto;font-size:11px;background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:20px;font-weight:700;">Out of Stock</span></div>`
        ).join('');
        document.getElementById('soldOutModal').classList.add('show');
        return;
    }
    doSubmitInvoice();
}

function closeSoldOutModal(){document.getElementById('soldOutModal').classList.remove('show');pendingSoldOut=[];}
function confirmSoldOutProceed(){
    document.getElementById('soldOutModal').classList.remove('show');
    pendingSoldOut.forEach(item=>{
        const row=document.getElementById('item-row-'+item.idx);
        const warn=document.getElementById('warn-'+item.idx);
        if(warn)warn.remove();if(row)row.remove();
    });
    pendingSoldOut=[];updateTotal();doSubmitInvoice();
}
function doSubmitInvoice(){
    const remaining=document.querySelectorAll('[id^="item-name-"]');
    if(remaining.length===0){
        document.getElementById('soldOutBox').innerHTML=
            `<div class="sold-out-icon">🛒</div>
             <div class="sold-out-title">Invoice is Empty</div>
             <p class="sold-out-subtitle">No items left after removing out-of-stock products. Please add at least one valid item.</p>
             <button class="sold-out-proceed-btn" onclick="document.getElementById('soldOutModal').classList.remove('show')">OK, Go Back</button>`;
        document.getElementById('soldOutModal').classList.add('show');
        return;
    }
    document.getElementById('txnForm').submit();
}

document.getElementById('soldOutModal').addEventListener('click',function(e){if(e.target===this)closeSoldOutModal();});
</script>
</body>
</html>