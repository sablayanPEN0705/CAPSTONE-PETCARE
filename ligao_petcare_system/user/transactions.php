<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/transactions.php
// Purpose: Transaction history + printable digital receipts
// ============================================================
require_once '../includes/auth.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id = (int)$_SESSION['user_id'];

// ── Filters ──────────────────────────────────────────────────
$search   = sanitize($conn, $_GET['search'] ?? '');
$from     = sanitize($conn, $_GET['from']   ?? date('Y-01-01'));
$to       = sanitize($conn, $_GET['to']     ?? date('Y-12-31'));
$type_f   = sanitize($conn, $_GET['type']   ?? 'all');  // all | product | appointment

$where = "t.user_id=$user_id AND t.transaction_date BETWEEN '$from' AND '$to'";
if ($type_f === 'product')     $where .= " AND t.notes = 'Product purchase by user'";
if ($type_f === 'appointment') $where .= " AND t.appointment_id IS NOT NULL AND (t.notes IS NULL OR t.notes != 'Product purchase by user')";
if ($search)                   $where .= " AND (p.name LIKE '%$search%' OR t.notes LIKE '%$search%')";

$transactions = getRows($conn,
    "SELECT t.*, p.name AS pet_name, p.species, p.breed,
            u.name AS owner_name, u.address, u.contact_no,
            a.appointment_date, a.appointment_time, a.appointment_type,
            s.name AS service_name
     FROM transactions t
     LEFT JOIN users u   ON t.user_id = u.id
     LEFT JOIN pets p    ON t.pet_id  = p.id
     LEFT JOIN appointments a ON t.appointment_id = a.id
     LEFT JOIN services s     ON a.service_id = s.id
     WHERE $where
     ORDER BY t.transaction_date DESC, t.id DESC");

// Attach line items to each transaction
foreach ($transactions as &$txn) {
    $tid = (int)$txn['id'];
    $txn['items'] = getRows($conn,
        "SELECT * FROM transaction_items WHERE transaction_id=$tid ORDER BY id ASC");
}
unset($txn);

$total_spent = array_sum(array_column($transactions, 'total_amount'));
$txn_count   = count($transactions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Summary cards ── */
        .txn-summary-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        .txn-stat-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 16px 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .txn-stat-icon {
            font-size: 30px;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .txn-stat-label { font-size: 11px; color: var(--text-light); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .txn-stat-value { font-family: var(--font-head); font-size: 20px; font-weight: 800; color: var(--text-dark); }

        /* ── Filter bar ── */
        .txn-filters {
            background: rgba(255,255,255,0.88);
            border-radius: var(--radius);
            padding: 14px 18px;
            box-shadow: var(--shadow);
            margin-bottom: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .type-pills { display:flex;gap:6px; }
        .type-pill {
            padding: 5px 14px;
            border-radius: var(--radius-lg);
            font-size: 12px;
            font-weight: 700;
            border: 2px solid var(--border);
            background: rgba(255,255,255,0.8);
            color: var(--text-mid);
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
        }
        .type-pill:hover, .type-pill.active {
            background: var(--teal);
            color: #fff;
            border-color: var(--teal);
        }
        .date-range { display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600; }
        .date-range input[type="date"] {
            padding: 6px 10px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 12px;
            background: #fff;
            outline: none;
        }

        /* ── Transaction cards ── */
        .txn-list { display: flex; flex-direction: column; gap: 12px; }
        .txn-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }
        .txn-card:hover { box-shadow: var(--shadow-md); }
        .txn-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            gap: 12px;
            flex-wrap: wrap;
        }
        .txn-card-left { display:flex;align-items:center;gap:12px; }
        .txn-type-icon {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .txn-ref    { font-weight: 800; font-size: 14px; color: var(--text-dark); }
        .txn-date   { font-size: 12px; color: var(--text-light); margin-top: 2px; }
        .txn-amount { font-family: var(--font-head); font-size: 18px; font-weight: 800; color: var(--teal-dark); }

        .txn-card-body { padding: 12px 18px; }
        .txn-meta-row  { display:flex;flex-wrap:wrap;gap:6px 20px;font-size:12px;color:var(--text-mid);margin-bottom:10px; }
        .txn-meta-row span { display:flex;align-items:center;gap:4px; }

        .txn-items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .txn-items-table th {
            text-align: left;
            font-size: 11px;
            font-weight: 800;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 6px 0;
            border-bottom: 1px solid var(--border);
        }
        .txn-items-table td {
            padding: 6px 0;
            border-bottom: 1px solid #f0f9ff;
            color: var(--text-dark);
        }
        .txn-items-table td:last-child { text-align: right; font-weight: 700; }
        .txn-items-table tr:last-child td { border-bottom: none; }
        .txn-total-row td {
            font-weight: 800;
            font-size: 13px;
            color: var(--teal-dark);
            border-top: 2px solid var(--border);
            padding-top: 8px;
        }

        .txn-card-footer {
            padding: 10px 18px;
            background: #f8fffe;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        /* ── Status badge overrides ── */
        .badge-paid    { background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }
        .badge-pending { background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700; }

        /* ── Empty state ── */
        .txn-empty { text-align:center;padding:56px 20px;color:var(--text-light); }
        .txn-empty .ei { font-size:56px;margin-bottom:14px; }
        .txn-empty h3  { font-family:var(--font-head);font-weight:800;font-size:18px;margin-bottom:6px;color:var(--text-mid); }

        @media(max-width:700px) {
            .txn-summary-row { grid-template-columns: 1fr 1fr; }
        }
        @media(max-width:480px) {
            .txn-summary-row { grid-template-columns: 1fr; }
        }

        /* ── RECEIPT PRINT MODAL ── */
        .receipt-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }
        .receipt-overlay.open { display: flex; }

        .receipt-box {
            background: #fff;
            width: 380px;
            max-width: 96vw;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 12px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.3);
            font-family: 'Courier New', monospace;
        }

        /* ── PRINT STYLES ── */
        @media print {
            body * { visibility: hidden; }
            #receiptPrintArea, #receiptPrintArea * { visibility: visible; }
            #receiptPrintArea {
                position: fixed;
                inset: 0;
                margin: 0;
                padding: 24px;
                background: #fff;
                font-family: 'Courier New', monospace;
            }
            .receipt-no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_user.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title" style="display:flex;align-items:center;gap:10px;">
                <img src="../assets/images/pets/logo.png" alt="Logo"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.6);"
                     onerror="this.style.display='none';">
                My Transactions
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔</a>
                <a href="settings.php"      class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <!-- Summary -->
            <div class="txn-summary-row">
                <div class="txn-stat-card">
                    <div class="txn-stat-icon" style="background:#e0f7fa;">🧾</div>
                    <div>
                        <div class="txn-stat-label">Total Transactions</div>
                        <div class="txn-stat-value"><?= $txn_count ?></div>
                    </div>
                </div>
                <div class="txn-stat-card">
                    <div class="txn-stat-icon" style="background:#f0fdf4;">💰</div>
                    <div>
                        <div class="txn-stat-label">Total Spent</div>
                        <div class="txn-stat-value"><?= formatPeso($total_spent) ?></div>
                    </div>
                </div>
                <div class="txn-stat-card">
                    <div class="txn-stat-icon" style="background:#fef9c3;">📅</div>
                    <div>
                        <div class="txn-stat-label">Period</div>
                        <div class="txn-stat-value" style="font-size:13px;">
                            <?= date('M j', strtotime($from)) ?> – <?= date('M j, Y', strtotime($to)) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="txn-filters" id="txnFilterForm">
                <div class="type-pills">
                    <button type="button"
                            class="type-pill <?= $type_f==='all'         ? 'active':'' ?>"
                            onclick="setTypeFilter('all')">All</button>
                    <button type="button"
                            class="type-pill <?= $type_f==='appointment' ? 'active':'' ?>"
                            onclick="setTypeFilter('appointment')">🏥 Services</button>
                    <button type="button"
                            class="type-pill <?= $type_f==='product'     ? 'active':'' ?>"
                            onclick="setTypeFilter('product')">🛍️ Products</button>
                </div>
                <div class="date-range">
                    <span>From:</span>
                    <input type="date" name="from" value="<?= $from ?>">
                    <span>To:</span>
                    <input type="date" name="to" value="<?= $to ?>">
                </div>
                <div class="search-box" style="min-width:180px;">
                    <span>🔍</span>
                    <input type="text" name="search" placeholder="Search..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <input type="hidden" name="type" id="typeInput" value="<?= $type_f ?>">
                <button type="submit" class="btn btn-teal btn-sm">Filter</button>
            </form>

            <!-- Transaction List -->
            <?php if (empty($transactions)): ?>
                <div class="txn-empty">
                    <div class="ei">🧾</div>
                    <h3>No transactions found</h3>
                    <p>Transactions from appointments and product purchases will appear here.</p>
                </div>
            <?php else: ?>
                <div class="txn-list">
                    <?php foreach ($transactions as $txn):
                        $is_product = ($txn['notes'] === 'Product purchase by user');
                        $is_appt    = !empty($txn['appointment_id']) && !$is_product;
                        $type_icon  = $is_product ? '🛍️' : '🏥';
                        $type_bg    = $is_product ? '#fdf4ff' : '#e0f7fa';
                        $type_lbl   = $is_product
                            ? 'Product Purchase'
                            : ($txn['appointment_type'] === 'home_service' ? '🏠 Home Service' : '🏥 Clinic Service');
                        $ref_no    = 'TXN-' . str_pad($txn['id'], 5, '0', STR_PAD_LEFT);
                        $status_badge = $txn['status'] === 'paid'
                            ? '<span class="badge-paid">✔ Paid</span>'
                            : '<span class="badge-pending">⏳ Pending</span>';

                        // ── Real transaction time from created_at ────────────────
                        // transaction_date is a DATE column (no clock time).
                        // created_at is a DATETIME/TIMESTAMP — use it for the real time.
                        $txn_date_display = '';
                        $txn_time_display = '';
                        $txn_dt = DateTime::createFromFormat('Y-m-d H:i:s', $txn['created_at'] ?? '')
                               ?: DateTime::createFromFormat('Y-m-d H:i',   $txn['created_at'] ?? '');
                        if ($txn_dt) {
                            $txn_date_display = $txn_dt->format('F j, Y');
                            $txn_time_display = $txn_dt->format('g:i A');
                        } else {
                            $txn_date_display = formatDate($txn['transaction_date']);
                        }

                        // ── Appointment scheduled date + time ────────────────────
                        $appt_date_display = '';
                        $appt_time_display = '';
                        if (!empty($txn['appointment_date'])) {
                            $appt_d = DateTime::createFromFormat('Y-m-d H:i:s', $txn['appointment_date'])
                                   ?: DateTime::createFromFormat('Y-m-d',       $txn['appointment_date']);
                            if ($appt_d) $appt_date_display = $appt_d->format('F j, Y');
                        }
                        // appointment_time is a separate TIME column e.g. "09:00:00"
                        if (!empty($txn['appointment_time'])) {
                            $appt_t = DateTime::createFromFormat('H:i:s', $txn['appointment_time'])
                                   ?: DateTime::createFromFormat('H:i',   $txn['appointment_time']);
                            if ($appt_t) $appt_time_display = $appt_t->format('g:i A');
                        }
                    ?>
                    <div class="txn-card">
                        <div class="txn-card-header">
                            <div class="txn-card-left">
                                <div class="txn-type-icon" style="background:<?= $type_bg ?>;">
                                    <?= $type_icon ?>
                                </div>
                                <div>
                                    <div class="txn-ref"><?= $ref_no ?></div>
                                    <div class="txn-date">
                                        <?= $txn_date_display ?>
                                        <?php if ($txn_time_display): ?>
                                            <span style="color:var(--teal-dark);font-weight:700;">· <?= $txn_time_display ?></span>
                                        <?php endif; ?>
                                        &nbsp;·&nbsp; <?= $type_lbl ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <?= $status_badge ?>
                                <div class="txn-amount"><?= formatPeso($txn['total_amount']) ?></div>
                            </div>
                        </div>

                        <div class="txn-card-body">
                           <div class="txn-meta-row">
                                <?php if ($txn['pet_name']): ?>
                                    <span>🐾 <strong><?= htmlspecialchars($txn['pet_name']) ?></strong>
                                        <?php if ($txn['breed']): ?>
                                            (<?= htmlspecialchars($txn['breed']) ?>)
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!$is_product && $txn['service_name']): ?>
                                    <span>💉 <?= htmlspecialchars($txn['service_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!$is_product && $appt_date_display): ?>
                                    <span>📅 Appt:
                                        <?= $appt_date_display ?>
                                        <?php if ($appt_time_display): ?>
                                            <strong style="color:var(--teal-dark);">&nbsp;<?= $appt_time_display ?></strong>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($is_product): ?>
                                    <span style="font-size:11px;background:#fdf4ff;color:#7c3aed;padding:2px 8px;border-radius:20px;font-weight:700;">
                                        🛍️ Product Order
                                    </span>
                                <?php endif; ?>
                            </div>


<?php
// Show submitted payment proof if any
$user_proof = getRow($conn,
    "SELECT method, reference_number, proof_image, status, admin_note
     FROM payment_proofs
     WHERE transaction_id={$txn['id']}
     ORDER BY id DESC LIMIT 1");
if ($user_proof):
    $up_method_icon  = $user_proof['method']==='gcash' ? '💙' : '💚';
    $up_method_label = strtoupper($user_proof['method']);
    $up_status_map   = [
        'pending'  => ['⏳ Under Review', '#fef3c7', '#92400e'],
        'verified' => ['✅ Verified',     '#d1fae5', '#065f46'],
        'rejected' => ['❌ Rejected',     '#fee2e2', '#991b1b'],
    ];
    [$up_status_label, $up_status_bg, $up_status_color] =
        $up_status_map[$user_proof['status']] ?? ['—','#f3f4f6','#374151'];
?>
<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;
            padding:12px 14px;margin-bottom:10px;">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
        <span style="font-size:12px;font-weight:800;">💳 Payment Proof</span>
        <span style="font-size:11px;font-weight:700;padding:2px 10px;border-radius:20px;
                     background:<?= $up_status_bg ?>;color:<?= $up_status_color ?>;">
            <?= $up_status_label ?>
        </span>
        <span style="font-size:11px;font-weight:700;padding:2px 10px;border-radius:20px;
                     background:<?= $user_proof['method']==='gcash'?'#eff6ff':'#f0fffe' ?>;
                     color:<?= $user_proof['method']==='gcash'?'#0070f3':'#00b09b' ?>;">
            <?= $up_method_icon ?> <?= $up_method_label ?>
        </span>
        <?php if (in_array($user_proof['status'], ['pending', 'rejected'])): ?>
        <button onclick="openEditProofModal(<?= $txn['id'] ?>, '<?= htmlspecialchars($user_proof['reference_number'], ENT_QUOTES) ?>')"
                style="margin-left:auto;background:#f0fffe;border:1.5px solid #b2dfdb;
                       color:#0f766e;font-size:11px;font-weight:700;padding:3px 10px;
                       border-radius:20px;cursor:pointer;">
            ✏️ Edit
        </button>
        <?php endif; ?>
    </div>
    <div style="font-size:12px;color:#374151;margin-bottom:8px;">
        📋 <strong>Ref No.:</strong> <?= htmlspecialchars($user_proof['reference_number']) ?>
    </div>
    <img src="../assets/uploads/payment_proofs/<?= htmlspecialchars($user_proof['proof_image']) ?>"
         alt="Your payment screenshot"
         onclick="openImgLightbox('../assets/uploads/payment_proofs/<?= htmlspecialchars($user_proof['proof_image']) ?>')"
         style="max-width:100%;max-height:160px;object-fit:contain;
                border-radius:8px;border:1px solid #e5e7eb;cursor:pointer;">
    <div style="font-size:10px;color:#9ca3af;margin-top:4px;">Click to view full size</div>
    <?php if ($user_proof['status']==='rejected' && $user_proof['admin_note']): ?>
        <div style="margin-top:8px;font-size:11px;color:#991b1b;
                    background:#fff5f5;border:1px solid #fca5a5;
                    padding:8px;border-radius:6px;">
            ⚠️ <strong>Rejection reason:</strong> <?= htmlspecialchars($user_proof['admin_note']) ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>



                            <?php if (!empty($txn['items'])): ?>
                                <table class="txn-items-table">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th style="text-align:right;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($txn['items'] as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                <td><?= formatPeso($item['price']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="txn-total-row">
                                            <td>Total</td>
                                            <td><?= formatPeso($txn['total_amount']) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            <?php elseif ($txn['notes']): ?>
                                <p style="font-size:12px;color:var(--text-light);">
                                    <?= htmlspecialchars($txn['notes']) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="txn-card-footer">
                            <?php if ($txn['status'] === 'pending'):
                                // Check if proof already submitted
                                $proof_row = getRow($conn,
                                    "SELECT status FROM payment_proofs
                                     WHERE transaction_id={$txn['id']}
                                     ORDER BY id DESC LIMIT 1");
                            ?>
                                <?php if ($proof_row && $proof_row['status'] === 'pending'): ?>
                                    <span style="font-size:12px;font-weight:700;color:#92400e;
                                                 background:#fef3c7;padding:5px 12px;
                                                 border-radius:20px;">
                                        ⏳ Payment proof under review
                                    </span>
                                <?php elseif ($proof_row && $proof_row['status'] === 'rejected'): ?>
                                    <button class="btn btn-sm"
                                            style="background:#ef4444;color:#fff;"
                                            onclick="openPayModal(<?= $txn['id'] ?>, '<?= formatPeso($txn['total_amount']) ?>')">
                                        ⚠️ Resubmit Payment Proof
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm"
                                            style="background:linear-gradient(135deg,#0070f3,#00b09b);color:#fff;"
                                            onclick="openPayModal(<?= $txn['id'] ?>, '<?= formatPeso($txn['total_amount']) ?>')">
                                        💳 Pay Online (GCash / PayMaya)
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <button
                                class="btn btn-teal btn-sm"
                                onclick='openReceipt(<?= htmlspecialchars(json_encode([
                                    "ref"        => $ref_no,
                                    "date"       => $txn_date_display,
                                    "time"       => $txn_time_display,
                                    "type"       => $type_lbl,
                                    "status"     => $txn["status"],
                                    "owner"      => $txn["owner_name"],
                                    "address"    => $txn["address"],
                                    "contact"    => $txn["contact_no"],
                                    "pet"        => $txn["pet_name"],
                                    "breed"      => $txn["breed"],
                                    "species"    => $txn["species"],
                                    "service"    => $txn["service_name"],
                                    "appt_date"  => $appt_date_display,
                                    "appt_time"  => $appt_time_display,
                                    "appt_type"  => $txn["appointment_type"],
                                    "items"      => array_map(fn($i) => ["name"=>$i["item_name"],"price"=>$i["price"]], $txn["items"]),
                                    "total"      => $txn["total_amount"],
                                  "notes"      => $txn["notes"],
                                    "pay_method" => (function() use ($conn, $txn) {
                                        $p = getRow($conn, "SELECT method FROM payment_proofs WHERE transaction_id={$txn['id']} AND status='verified' ORDER BY id DESC LIMIT 1");
                                        return $p ? $p['method'] : 'onclinic';
                                    })(),
                                    "pay_ref"    => (function() use ($conn, $txn) {
                                        $p = getRow($conn, "SELECT reference_number FROM payment_proofs WHERE transaction_id={$txn['id']} AND status='verified' ORDER BY id DESC LIMIT 1");
                                        return $p ? $p['reference_number'] : '';
                                    })(),
                                ]), ENT_QUOTES) ?>)'>
                                🧾 View Receipt
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div><!-- /page-body -->
    </div>
</div>

<!-- ══ RECEIPT MODAL ══════════════════════════════════════════ -->
<div class="receipt-overlay" id="receiptOverlay" onclick="if(event.target===this)closeReceipt()">
    <div class="receipt-box">

        <!-- Action bar (hidden on print) -->
        <div class="receipt-no-print"
             style="display:flex;justify-content:space-between;align-items:center;
                    padding:12px 16px;border-bottom:1px solid #eee;">
            <span style="font-weight:700;font-size:13px;font-family:var(--font-main);">Digital Receipt</span>
            <div style="display:flex;gap:8px;">
                <button onclick="closeReceipt()"
                        style="padding:6px 12px;background:#f3f4f6;border:none;
                               border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;">
                    ✕ Close
                </button>
            </div>
        </div>

        <!-- Receipt content (this is what prints) -->
        <div id="receiptPrintArea" style="padding:24px;">

            <!-- Clinic header -->
            <div style="text-align:center;border-bottom:2px dashed #ccc;padding-bottom:16px;margin-bottom:16px;">
                <div style="font-size:22px;margin-bottom:4px;">🐾</div>
                <div style="font-size:15px;font-weight:900;letter-spacing:1px;">LIGAO PETCARE</div>
                <div style="font-size:11px;color:#555;">&amp; Veterinary Clinic</div>
                <div style="font-size:10px;color:#888;margin-top:4px;">Ligao City, Albay</div>
            </div>

            <!-- Receipt meta -->
            <div style="font-size:11px;margin-bottom:14px;line-height:1.8;">
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#888;">Receipt No.:</span>
                    <strong id="r_ref"></strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#888;">Date:</span>
                    <span id="r_date"></span>
                </div>
                <!-- TIME row — only shown when a real time was recorded -->
                <div id="r_time_row" style="display:none;justify-content:space-between;">
                    <span style="color:#888;">Time:</span>
                    <span id="r_time" style="font-weight:700;color:#007b83;"></span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#888;">Type:</span>
                    <span id="r_type"></span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#888;">Status:</span>
                    <strong id="r_status"></strong>
                </div>
            </div>

            <!-- Client info -->
            <div style="border-top:1px dashed #ccc;border-bottom:1px dashed #ccc;
                        padding:10px 0;margin-bottom:14px;font-size:11px;line-height:1.8;">
                <div style="font-weight:800;margin-bottom:4px;font-size:12px;">CLIENT</div>
                <div><span style="color:#888;">Name:</span> <span id="r_owner"></span></div>
                <div id="r_address_row" style="display:none;">
                    <span style="color:#888;">Address:</span> <span id="r_address"></span>
                </div>
                <div id="r_contact_row" style="display:none;">
                    <span style="color:#888;">Contact:</span> <span id="r_contact"></span>
                </div>
                <div id="r_pet_row" style="display:none;">
                    <span style="color:#888;">Pet:</span>
                    <span id="r_pet"></span>
                    <span id="r_breed" style="color:#888;"></span>
                </div>
                <div id="r_service_row" style="display:none;">
                    <span style="color:#888;">Service:</span> <span id="r_service"></span>
                </div>
                <!-- Appointment row now shows date AND time together -->
                <div id="r_appt_row" style="display:none;">
                    <span style="color:#888;">Appt. Date:</span>
                    <span id="r_appt_date"></span>
                    <span id="r_appt_time" style="font-weight:700;color:#007b83;margin-left:4px;display:none;"></span>
                </div>
            </div>

            <!-- Line items -->
            <div style="font-size:11px;margin-bottom:14px;">
                <div style="font-weight:800;margin-bottom:8px;font-size:12px;">ITEMS</div>
                <div id="r_items_list"></div>
                <div style="border-top:1px dashed #ccc;margin-top:10px;padding-top:8px;
                            display:flex;justify-content:space-between;font-weight:800;font-size:13px;">
                    <span>TOTAL</span>
                    <span id="r_total" style="color:#007b83;"></span>
                </div>
            </div>

            <!-- Footer note -->
            <div style="text-align:center;font-size:10px;color:#aaa;
                        border-top:2px dashed #ccc;padding-top:14px;margin-top:4px;line-height:1.7;">
                <div>Payment is collected <strong>in person</strong> at the clinic.</div>
                <div>Thank you for trusting Ligao Petcare! 🐾</div>
                <div style="margin-top:6px;font-size:9px;">
                    — This is a digital copy of your receipt —
                </div>
            </div>

        </div><!-- /receiptPrintArea -->
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function openReceipt(data) {
    // ── meta ──────────────────────────────────────────────────
    document.getElementById('r_ref').textContent  = data.ref  || '—';
    document.getElementById('r_date').textContent = data.date || '—';
    document.getElementById('r_type').textContent = data.type || '—';

    // Time row — only show when a real time string was passed from PHP
    const timeRow = document.getElementById('r_time_row');
    const timeEl  = document.getElementById('r_time');
    if (data.time) {
        timeEl.textContent    = data.time;
        timeRow.style.display = 'flex';
    } else {
        timeRow.style.display = 'none';
    }

    // Status
    const statusEl = document.getElementById('r_status');
    statusEl.textContent = data.status === 'paid' ? '✔ PAID' : (data.status || '—').toUpperCase();
    statusEl.style.color = data.status === 'paid' ? '#065f46' : '#92400e';

    // ── client ────────────────────────────────────────────────
    document.getElementById('r_owner').textContent = data.owner || '—';
    setReceiptRow('r_address_row', 'r_address', data.address);
    setReceiptRow('r_contact_row', 'r_contact', data.contact);
    setReceiptRow('r_service_row', 'r_service', data.service);

    if (data.pet) {
        document.getElementById('r_pet_row').style.display = '';
        document.getElementById('r_pet').textContent   = data.pet;
        document.getElementById('r_breed').textContent = data.breed ? `(${data.breed})` : '';
    } else {
        document.getElementById('r_pet_row').style.display = 'none';
    }

    // Appointment date + time
    if (data.appt_date) {
        document.getElementById('r_appt_row').style.display = '';
        document.getElementById('r_appt_date').textContent  = data.appt_date;
        const apptTimeEl = document.getElementById('r_appt_time');
        if (data.appt_time) {
            apptTimeEl.textContent    = data.appt_time;
            apptTimeEl.style.display  = '';
        } else {
            apptTimeEl.style.display  = 'none';
        }
    } else {
        document.getElementById('r_appt_row').style.display = 'none';
    }

    // ── items ─────────────────────────────────────────────────
    let itemsHtml = '';
    if (data.items && data.items.length > 0) {
        data.items.forEach(item => {
            itemsHtml += `
                <div style="display:flex;justify-content:space-between;
                            padding:4px 0;border-bottom:1px dotted #eee;">
                    <span>${escHtml(item.name)}</span>
                    <span style="font-weight:700;">${formatPeso(item.price)}</span>
                </div>`;
        });
    } else if (data.notes && data.notes !== 'Product purchase by user') {
        itemsHtml = `<div style="color:#888;">${escHtml(data.notes)}</div>`;
    } else {
        itemsHtml = '<div style="color:#aaa;">No line items recorded.</div>';
    }
    document.getElementById('r_items_list').innerHTML = itemsHtml;

    // ── total ─────────────────────────────────────────────────
    document.getElementById('r_total').textContent = formatPeso(data.total);

    // ── payment method ────────────────────────────────────────
    var pmEl = document.getElementById('r_pay_method_row');
    if (pmEl) pmEl.remove(); // remove old if re-opened
    var payMethodHtml = '';
    if (data.pay_method && data.pay_method !== 'onclinic') {
        var pmIcon  = data.pay_method === 'gcash' ? '💙' : '💚';
        var pmLabel = data.pay_method === 'gcash' ? 'GCash' : 'PayMaya';
        payMethodHtml = '<div id="r_pay_method_row" style="margin-top:10px;padding-top:10px;' +
            'border-top:1px dashed #eee;font-size:11px;">' +
            '<div style="display:flex;justify-content:space-between;margin-bottom:3px;">' +
            '<span style="color:#888;">Payment Method:</span>' +
            '<strong>' + pmIcon + ' ' + pmLabel + '</strong></div>';
        if (data.pay_ref) {
            payMethodHtml += '<div style="display:flex;justify-content:space-between;">' +
                '<span style="color:#888;">Reference No.:</span>' +
                '<strong style="color:#0f766e;">' + escHtml(data.pay_ref) + '</strong></div>';
        }
        payMethodHtml += '</div>';
    } else {
        payMethodHtml = '<div id="r_pay_method_row" style="margin-top:10px;padding-top:10px;' +
            'border-top:1px dashed #eee;font-size:11px;">' +
            '<div style="display:flex;justify-content:space-between;">' +
            '<span style="color:#888;">Payment Method:</span>' +
            '<strong>🏥 On Clinic</strong></div></div>';
    }
    document.getElementById('r_items_list').insertAdjacentHTML('afterend', payMethodHtml);

    document.getElementById('receiptOverlay').classList.add('open');
}

function setReceiptRow(rowId, valId, value) {
    const row = document.getElementById(rowId);
    const val = document.getElementById(valId);
    if (value) {
        row.style.display = '';
        val.textContent   = value;
    } else {
        row.style.display = 'none';
    }
}

function closeReceipt() {
    document.getElementById('receiptOverlay').classList.remove('open');
}

function formatPeso(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function escHtml(str) {
    if (!str) return '—';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeReceipt();
});

function setTypeFilter(type) {
    document.getElementById('typeInput').value = type;
    document.querySelectorAll('.type-pill').forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById('txnFilterForm').submit();
}</script>

<?php include_once __DIR__ . '/../includes/chatbot-widget.php'; ?>


<!-- ══ ONLINE PAYMENT MODAL ══════════════════════════════════ -->
<div class="receipt-overlay" id="payModal" onclick="if(event.target===this)closePayModal()" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;width:420px;max-width:96vw;max-height:92vh;overflow-y:auto;border-radius:16px;box-shadow:0 24px 64px rgba(0,0,0,0.3);">

        <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #eee;">
            <span style="font-weight:800;font-size:15px;">💳 Pay Online</span>
            <button onclick="closePayModal()" style="background:#f3f4f6;border:none;border-radius:8px;padding:6px 12px;font-weight:700;cursor:pointer;">✕</button>
        </div>

        <div style="padding:20px;">

            <!-- Amount -->
            <div style="text-align:center;margin-bottom:20px;padding:14px;background:#f0fffe;border-radius:12px;border:1.5px solid #b2dfdb;">
                <div style="font-size:12px;color:#6b7280;font-weight:600;margin-bottom:4px;">Amount to Pay</div>
                <div id="payAmount" style="font-size:26px;font-weight:900;color:#0f766e;"></div>
            </div>

            <!-- Method selector -->
            <div style="margin-bottom:18px;">
                <div style="font-size:12px;font-weight:800;color:#374151;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px;">Select Payment Method</div>
                <div style="display:flex;gap:10px;">
                    <div id="methodGcash" onclick="selectMethod('gcash')"
                         style="flex:1;border:2px solid #e5e7eb;border-radius:12px;padding:14px 10px;text-align:center;cursor:pointer;transition:all .15s;">
                        <div style="font-size:28px;margin-bottom:6px;">💙</div>
                        <div style="font-weight:800;font-size:14px;color:#0070f3;">GCash</div>
                        <div style="font-size:10px;color:#6b7280;">Send via GCash</div>
                    </div>
                    <div id="methodPaymaya" onclick="selectMethod('paymaya')"
                         style="flex:1;border:2px solid #e5e7eb;border-radius:12px;padding:14px 10px;text-align:center;cursor:pointer;transition:all .15s;">
                        <div style="font-size:28px;margin-bottom:6px;">💚</div>
                        <div style="font-weight:800;font-size:14px;color:#00b09b;">PayMaya</div>
                        <div style="font-size:10px;color:#6b7280;">Send via Maya</div>
                    </div>
                </div>
            </div>

            <!-- Payment details (shown after method select) -->
            <div id="payDetails" style="display:none;">
                <div id="payAccountBox" style="background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:10px;padding:14px;margin-bottom:16px;text-align:center;">
                    <div style="font-size:11px;color:#6b7280;margin-bottom:4px;">Send payment to:</div>
                    <div id="payAccountName" style="font-weight:800;font-size:15px;color:#1a1a2e;margin-bottom:2px;"></div>
                    <div id="payAccountNumber" style="font-size:22px;font-weight:900;color:#0f766e;letter-spacing:2px;margin-bottom:6px;"></div>
                    <button onclick="copyAccountNumber()" style="background:#e0f2f1;border:none;color:#0f766e;font-weight:700;font-size:11px;padding:4px 12px;border-radius:20px;cursor:pointer;">
                        📋 Copy Number
                    </button>
                </div>

                <form id="payForm">
                    <input type="hidden" id="pay_txn_id" name="transaction_id">
                    <input type="hidden" id="pay_method" name="method">

                    <div style="margin-bottom:12px;">
                        <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:5px;">Reference / Confirmation Number *</label>
                        <input type="text" id="pay_ref" name="reference_number"
                               placeholder="e.g. 1234567890"
                               style="width:100%;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:8px;font-size:13px;outline:none;box-sizing:border-box;">
                        <div style="font-size:11px;color:#9ca3af;margin-top:4px;">Found in your GCash/Maya transaction history</div>
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="font-size:12px;font-weight:700;color:#374151;display:block;margin-bottom:5px;">Screenshot of Payment *</label>
                        <div id="proofDropZone" onclick="document.getElementById('pay_proof').click()"
                             style="border:2px dashed #cbd5e1;border-radius:10px;padding:24px;text-align:center;cursor:pointer;transition:border-color .15s;background:#fafafa;">
                            <div style="font-size:32px;margin-bottom:6px;">📸</div>
                            <div style="font-size:12px;color:#6b7280;font-weight:600;">Click to upload screenshot</div>
                            <div style="font-size:11px;color:#9ca3af;margin-top:2px;">JPG, PNG, WebP · Max 5MB</div>
                        </div>
                        <input type="file" id="pay_proof" name="proof_image" accept="image/*" style="display:none;" onchange="previewProof(this)">
                        <img id="proofPreview" style="display:none;width:100%;border-radius:8px;margin-top:8px;max-height:200px;object-fit:contain;border:1px solid #e5e7eb;">
                    </div>

                    <div id="payError" style="display:none;color:#ef4444;font-size:12px;font-weight:600;margin-bottom:10px;background:#fff5f5;padding:8px 12px;border-radius:8px;border:1px solid #fca5a5;"></div>

                    <button type="button" onclick="submitPayment()"
                            id="paySubmitBtn"
                            style="width:100%;padding:12px;background:linear-gradient(135deg,#0070f3,#00b09b);color:#fff;border:none;border-radius:10px;font-weight:800;font-size:14px;cursor:pointer;">
                        ✅ Submit Payment Proof
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
// ── Payment modal state ───────────────────────────────────────
var _payTxnId   = null;
var _payMethod  = null;

// Configure your GCash and PayMaya account details here:
var PAY_ACCOUNTS = {
    gcash: {
        name:   'Ligao Petcare & Vet Clinic',
        number: '0926-396-7678'   // ← replace with real GCash number
    },
    paymaya: {
        name:   'Ligao Petcare & Vet Clinic',
        number: '0926-396-7678'   // ← replace with real PayMaya number
    }
};

function openPayModal(txnId, amount) {
    _payTxnId  = txnId;
    _payMethod = null;
    document.getElementById('pay_txn_id').value = txnId;
    document.getElementById('payAmount').textContent = amount;
    document.getElementById('payDetails').style.display = 'none';
    document.getElementById('pay_ref').value = '';
    document.getElementById('pay_proof').value = '';
    document.getElementById('proofPreview').style.display = 'none';
    document.getElementById('proofDropZone').style.display = '';
    document.getElementById('payError').style.display = 'none';
    document.getElementById('paySubmitBtn').disabled = false;
    document.getElementById('paySubmitBtn').textContent = '✅ Submit Payment Proof';
    // Reset method highlight
    ['methodGcash','methodPaymaya'].forEach(function(id){
        var el = document.getElementById(id);
        el.style.borderColor = '#e5e7eb';
        el.style.background  = '#fff';
    });
    var modal = document.getElementById('payModal');
    modal.style.display = 'flex';
}

function closePayModal() {
    document.getElementById('payModal').style.display = 'none';
}

function selectMethod(method) {
    _payMethod = method;
    document.getElementById('pay_method').value = method;

    // Highlight selected
    document.getElementById('methodGcash').style.borderColor   = method === 'gcash'    ? '#0070f3' : '#e5e7eb';
    document.getElementById('methodGcash').style.background    = method === 'gcash'    ? '#eff6ff' : '#fff';
    document.getElementById('methodPaymaya').style.borderColor = method === 'paymaya'  ? '#00b09b' : '#e5e7eb';
    document.getElementById('methodPaymaya').style.background  = method === 'paymaya'  ? '#f0fffe' : '#fff';

    // Show account info
    var acct = PAY_ACCOUNTS[method];
    document.getElementById('payAccountName').textContent   = acct.name;
    document.getElementById('payAccountNumber').textContent = acct.number;
    document.getElementById('payDetails').style.display = '';
}

function copyAccountNumber() {
    var num = document.getElementById('payAccountNumber').textContent;
    navigator.clipboard.writeText(num.replace(/-/g,'')).then(function(){
        var btn = event.target;
        btn.textContent = '✅ Copied!';
        setTimeout(function(){ btn.textContent = '📋 Copy Number'; }, 2000);
    });
}

function previewProof(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('proofPreview');
            img.src = e.target.result;
            img.style.display = '';
            document.getElementById('proofDropZone').style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function submitPayment() {
    var ref   = document.getElementById('pay_ref').value.trim();
    var proof = document.getElementById('pay_proof').files[0];
    var errEl = document.getElementById('payError');

    if (!_payMethod) {
        errEl.textContent = '⚠️ Please select a payment method.';
        errEl.style.display = '';
        return;
    }
    if (!ref) {
        errEl.textContent = '⚠️ Please enter your reference number.';
        errEl.style.display = '';
        return;
    }
    if (!proof) {
        errEl.textContent = '⚠️ Please attach a screenshot of your payment.';
        errEl.style.display = '';
        return;
    }

    errEl.style.display = 'none';
    var btn = document.getElementById('paySubmitBtn');
    btn.disabled    = true;
    btn.textContent = '⏳ Submitting…';

    var formData = new FormData();
    formData.append('transaction_id',    _payTxnId);
    formData.append('method',            _payMethod);
    formData.append('reference_number',  ref);
    formData.append('proof_image',       proof);

    fetch('../payment_proof_upload.php', {
        method: 'POST',
        body:   formData
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            btn.textContent = '✅ Submitted!';
            btn.style.background = '#10b981';
            setTimeout(function(){ window.location.reload(); }, 1500);
        } else {
            errEl.textContent   = '⚠️ ' + (d.error || 'Submission failed.');
            errEl.style.display = '';
            btn.disabled        = false;
            btn.textContent     = '✅ Submit Payment Proof';
        }
    })
    .catch(function(){
        errEl.textContent   = '⚠️ Network error. Please try again.';
        errEl.style.display = '';
        btn.disabled        = false;
        btn.textContent     = '✅ Submit Payment Proof';
    });
}
</script>



<!-- ══ IMAGE LIGHTBOX ══ -->
<div id="imgLightboxOverlay"
     style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.85);
            align-items:center;justify-content:center;backdrop-filter:blur(4px);"
     onclick="if(event.target===this)closeImgLightbox()">
    <div style="position:relative;max-width:92vw;max-height:90vh;">
        <button onclick="closeImgLightbox()"
                style="position:absolute;top:-14px;right:-14px;width:32px;height:32px;
                       border-radius:50%;background:#fff;border:none;font-weight:800;
                       font-size:16px;cursor:pointer;display:flex;align-items:center;
                       justify-content:center;z-index:1;box-shadow:0 2px 8px rgba(0,0,0,0.3);">
            ✕
        </button>
        <img id="imgLightboxImg"
             src=""
             alt="Full size"
             style="max-width:92vw;max-height:88vh;border-radius:10px;object-fit:contain;display:block;">
    </div>
</div>

<!-- ══ EDIT PAYMENT PROOF MODAL ══ -->
<div id="editProofOverlay"
     style="display:none;position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,0.6);
            backdrop-filter:blur(4px);align-items:center;justify-content:center;"
     onclick="if(event.target===this)closeEditProofModal()">
    <div style="background:#fff;width:420px;max-width:96vw;max-height:92vh;overflow-y:auto;
                border-radius:16px;box-shadow:0 24px 64px rgba(0,0,0,0.3);">
        <div style="display:flex;justify-content:space-between;align-items:center;
                    padding:16px 20px;border-bottom:1px solid #eee;">
            <span style="font-weight:800;font-size:15px;">✏️ Edit Payment Proof</span>
            <button onclick="closeEditProofModal()"
                    style="background:#f3f4f6;border:none;border-radius:8px;
                           padding:6px 12px;font-weight:700;cursor:pointer;">✕ Close</button>
        </div>
        <div style="padding:20px;">
            <div id="editProofError"
                 style="display:none;color:#991b1b;font-size:12px;font-weight:600;
                        margin-bottom:12px;background:#fff5f5;padding:8px 12px;
                        border-radius:8px;border:1px solid #fca5a5;"></div>
            <form id="editProofForm">
                <input type="hidden" id="edit_txn_id" name="transaction_id">
                <div style="margin-bottom:14px;">
                    <label style="font-size:12px;font-weight:700;color:#374151;
                                  display:block;margin-bottom:5px;">
                        Reference / Confirmation Number *
                    </label>
                    <input type="text" id="edit_ref" name="reference_number"
                           placeholder="e.g. 1234567890"
                           style="width:100%;padding:10px 12px;border:1.5px solid #d1d5db;
                                  border-radius:8px;font-size:13px;outline:none;
                                  box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="font-size:12px;font-weight:700;color:#374151;
                                  display:block;margin-bottom:5px;">
                        New Screenshot (optional — leave blank to keep existing)
                    </label>
                    <div id="editProofDropZone"
                         onclick="document.getElementById('edit_proof').click()"
                         style="border:2px dashed #cbd5e1;border-radius:10px;padding:20px;
                                text-align:center;cursor:pointer;background:#fafafa;">
                        <div style="font-size:28px;margin-bottom:6px;">📸</div>
                        <div style="font-size:12px;color:#6b7280;font-weight:600;">Click to upload new screenshot</div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:2px;">JPG, PNG, WebP · Max 5MB · Optional</div>
                    </div>
                    <input type="file" id="edit_proof" name="proof_image" accept="image/*"
                           style="display:none;" onchange="previewEditProof(this)">
                    <img id="editProofPreview"
                         style="display:none;width:100%;border-radius:8px;margin-top:8px;
                                max-height:180px;object-fit:contain;border:1px solid #e5e7eb;">
                </div>
                <button type="button" onclick="submitEditProof()"
                        id="editProofSubmitBtn"
                        style="width:100%;padding:12px;
                               background:linear-gradient(135deg,#0070f3,#00b09b);
                               color:#fff;border:none;border-radius:10px;
                               font-weight:800;font-size:14px;cursor:pointer;">
                    💾 Save Changes
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openImgLightbox(src) {
    document.getElementById('imgLightboxImg').src = src;
    var el = document.getElementById('imgLightboxOverlay');
    el.style.display = 'flex';
}
function closeImgLightbox() {
    document.getElementById('imgLightboxOverlay').style.display = 'none';
    document.getElementById('imgLightboxImg').src = '';
}

function openEditProofModal(txnId, currentRef) {
    document.getElementById('edit_txn_id').value = txnId;
    document.getElementById('edit_ref').value    = currentRef;
    document.getElementById('edit_proof').value  = '';
    document.getElementById('editProofPreview').style.display   = 'none';
    document.getElementById('editProofDropZone').style.display  = '';
    document.getElementById('editProofError').style.display     = 'none';
    document.getElementById('editProofSubmitBtn').disabled      = false;
    document.getElementById('editProofSubmitBtn').textContent   = '💾 Save Changes';
    document.getElementById('editProofOverlay').style.display   = 'flex';
}
function closeEditProofModal() {
    document.getElementById('editProofOverlay').style.display = 'none';
}

function previewEditProof(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('editProofPreview');
            img.src = e.target.result;
            img.style.display = '';
            document.getElementById('editProofDropZone').style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function submitEditProof() {
    var ref  = document.getElementById('edit_ref').value.trim();
    var txnId = document.getElementById('edit_txn_id').value;
    var errEl = document.getElementById('editProofError');

    if (!ref) {
        errEl.textContent   = '⚠️ Please enter your reference number.';
        errEl.style.display = '';
        return;
    }
    errEl.style.display = 'none';
    var btn = document.getElementById('editProofSubmitBtn');
    btn.disabled    = true;
    btn.textContent = '⏳ Saving…';

    var formData = new FormData();
    formData.append('transaction_id',   txnId);
    formData.append('reference_number', ref);
    var fileInput = document.getElementById('edit_proof');
    if (fileInput.files[0]) formData.append('proof_image', fileInput.files[0]);

    fetch('../payment_proof_edit.php', {
        method: 'POST',
        body:   formData
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            btn.textContent = '✅ Saved!';
            btn.style.background = '#10b981';
            setTimeout(function(){ window.location.reload(); }, 1200);
        } else {
            errEl.textContent   = '⚠️ ' + (d.error || 'Update failed.');
            errEl.style.display = '';
            btn.disabled        = false;
            btn.textContent     = '💾 Save Changes';
        }
    })
    .catch(function(){
        errEl.textContent   = '⚠️ Network error. Please try again.';
        errEl.style.display = '';
        btn.disabled        = false;
        btn.textContent     = '💾 Save Changes';
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImgLightbox();
        closeEditProofModal();
    }
});
</script>




</body>
</html>