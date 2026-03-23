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

            // Insert line items
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

            // If linked to appointment, mark it completed
            if ($appt_id) {
                mysqli_query($conn, "UPDATE appointments SET status='completed' WHERE id=$appt_id");
            }

            // Deduct inventory for product items
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

            // Notify user
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

            // Notify user when marked paid
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
        redirect('transactions.php?success=Transaction status updated.');
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

// ── Monthly revenue for bar chart ────────────────────────────
$monthly_rev = getRows($conn,
    "SELECT MONTH(transaction_date) AS mo, SUM(total_amount) AS total
     FROM transactions WHERE status='paid' AND YEAR(transaction_date)=YEAR(CURDATE())
     GROUP BY MONTH(transaction_date) ORDER BY mo ASC");
$rev_data = array_fill(1, 12, 0);
foreach ($monthly_rev as $mr) $rev_data[(int)$mr['mo']] = (float)$mr['total'];

// ── Status breakdown for donut ────────────────────────────────
$paid_total    = getRow($conn, "SELECT COALESCE(SUM(total_amount),0) AS t FROM transactions WHERE status='paid'")['t'];
$pending_total = getRow($conn, "SELECT COALESCE(SUM(total_amount),0) AS t FROM transactions WHERE status='pending'")['t'];
$overdue_total = getRow($conn, "SELECT COALESCE(SUM(total_amount),0) AS t FROM transactions WHERE status='overdue'")['t'];

// ── Billing detail view ───────────────────────────────────────
$view_id   = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_txn  = null;
$txn_items = [];
if ($view_id) {
    $view_txn = getRow($conn,
        "SELECT t.*, u.name AS owner_name, p.name AS pet_name, p.species, p.breed
         FROM transactions t
         LEFT JOIN users u ON t.user_id = u.id
         LEFT JOIN pets  p ON t.pet_id  = p.id
         WHERE t.id=$view_id");
    if ($view_txn) {
        $txn_items = getRows($conn, "SELECT * FROM transaction_items WHERE transaction_id=$view_id");
    }
}

// ── Fetch transactions list ───────────────────────────────────
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

// ── Clients for dropdown ──────────────────────────────────────
$clients = getRows($conn, "SELECT id, name FROM users WHERE role='user' ORDER BY name ASC");

// ── Appointments for dropdown ─────────────────────────────────
$all_appts = getRows($conn,
    "SELECT a.id, u.name AS owner_name, p.name AS pet_name, a.appointment_date
     FROM appointments a
     LEFT JOIN users u ON a.user_id = u.id
     LEFT JOIN pets  p ON a.pet_id  = p.id
     WHERE a.status IN ('confirmed','completed')
     ORDER BY a.appointment_date DESC LIMIT 50");

// ── Product & Service suggestions for datalist ───────────────
$all_prods = getRows($conn, "SELECT name, price, status FROM products ORDER BY name ASC");
$all_svcs  = getRows($conn, "SELECT name, price_min FROM services WHERE status='available' ORDER BY name ASC");
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
            display: grid;
            grid-template-columns: 1fr 260px;
            gap: 16px;
            margin-bottom: 24px;
        }
        .chart-box {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }
        .chart-box-title {
            font-weight: 700;
            font-size: 12px;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 14px;
        }
        .txn-table-section {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }
        .txn-table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        .txn-table-header h3 { font-family: var(--font-head); font-size: 17px; font-weight: 700; }
        .txn-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .status-select-sm {
            padding: 7px 10px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 12px;
            background: #fff;
            outline: none;
        }
        /* Billing detail */
        .billing-detail {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 28px 32px;
            box-shadow: var(--shadow);
            max-width: 600px;
            margin: 0 auto;
        }
        .billing-detail h2 {
            font-family: var(--font-head);
            font-size: 20px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 20px;
        }
        .billing-info { font-size: 13px; color: var(--text-mid); margin-bottom: 16px; line-height: 2; }
        .billing-info strong { color: var(--text-dark); }
        .billing-items { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 12px; }
        .billing-items th {
            text-align: left; padding: 8px 12px;
            background: var(--teal-light); color: var(--teal-dark);
            font-size: 12px; font-weight: 700;
        }
        .billing-items td { padding: 8px 12px; border-bottom: 1px solid var(--border); }
        .billing-items td:last-child { text-align: right; font-weight: 700; }
        .billing-total-row td {
            font-weight: 800; font-size: 15px;
            border-bottom: none; padding-top: 12px; border-top: 2px solid var(--border);
        }
        /* Line items */
        .line-item-row {
            display: grid;
            grid-template-columns: 1fr 100px 100px auto;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
        }
        .remove-item-btn {
            background: #fee2e2; color: #dc2626;
            border: none; border-radius: 4px;
            padding: 5px 10px; cursor: pointer;
            font-size: 13px; font-weight: 700;
        }
        .remove-item-btn:hover { background: #ef4444; color: #fff; }
        #lineItemsContainer label { font-size: 11px; color: var(--text-light); font-weight: 700; }
        .running-total {
            text-align: right; font-weight: 800;
            font-size: 16px; color: var(--teal-dark); padding: 10px 0;
        }
        /* Responsive */
        @media(max-width:700px) {
            .txn-charts { grid-template-columns: 1fr; }
            .line-item-row { grid-template-columns: 1fr 80px 80px auto; gap: 4px; }
        }

        /* Sold-out modal */
#soldOutModal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0,0,0,0.55);
    backdrop-filter: blur(3px);
    align-items: center;
    justify-content: center;
}
#soldOutModal.show { display: flex !important; }
@keyframes slideUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:translateY(0); }
}
.sold-out-box {
    background: #fff;
    border-radius: 16px;
    padding: 32px 28px;
    max-width: 420px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    animation: slideUp 0.25s ease;
    text-align: center;
}
.sold-out-icon {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: #fff5f5;
    border: 2px solid #fee2e2;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin: 0 auto 16px;
}
.sold-out-title {
    font-family: var(--font-head);
    font-size: 18px;
    font-weight: 800;
    color: #1a1a2e;
    margin-bottom: 8px;
}
.sold-out-subtitle {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 16px;
    line-height: 1.6;
}
.sold-out-list {
    background: #fff5f5;
    border: 1px solid #fee2e2;
    border-radius: 10px;
    padding: 4px 16px;
    margin-bottom: 16px;
    text-align: left;
    max-height: 160px;
    overflow-y: auto;
}
.sold-out-list-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 0;
    border-bottom: 1px solid #fee2e2;
    font-size: 13px;
    color: #374151;
}
.sold-out-list-item:last-child { border-bottom: none; }
.sold-out-note {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 20px;
    background: #f0fffe;
    border: 1px solid #b2ebf2;
    border-radius: 8px;
    padding: 10px 14px;
    line-height: 1.6;
}
.sold-out-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}
.sold-out-cancel-btn {
    padding: 10px 24px;
    border-radius: 8px;
    border: 1.5px solid #d1d5db;
    background: #fff;
    color: #374151;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    font-family: var(--font-main);
}
.sold-out-cancel-btn:hover { background: #f9fafb; }
.sold-out-proceed-btn {
    padding: 10px 24px;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg, var(--teal), #0097a7);
    color: #fff;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,150,136,0.3);
    transition: all 0.2s;
    font-family: var(--font-main);
}
.sold-out-proceed-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,150,136,0.4); }

        /* Print */
        @media print {
            .sidebar, .topbar, .back-btn-wrap, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .page-body { padding: 0 !important; background: none !important; }
            .billing-detail {
                box-shadow: none !important;
                border: 1px solid #ccc;
                max-width: 100% !important;
                padding: 20px !important;
            }
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
                <a href="settings.php" class="topbar-btn" title="Settings">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <?php if ($view_txn): ?>
            <!-- ══ BILLING DETAIL VIEW ════════════════════════ -->
            <div class="back-btn-wrap" style="margin-bottom:14px;">
                <a href="transactions.php" class="btn btn-gray btn-sm">← Back to Transactions</a>
            </div>

            <div class="billing-detail">
                <h2>Billing Summary Details</h2>
                <div class="billing-info">
                    <p><strong>Pet:</strong> <?= htmlspecialchars($view_txn['pet_name'] ?? '—') ?></p>
                    <p><strong>Species/Breed:</strong>
                        <?= ucfirst($view_txn['species'] ?? '') ?>
                        <?= $view_txn['breed'] ? '/ '.htmlspecialchars($view_txn['breed']) : '' ?>
                    </p>
                    <p><strong>Owner:</strong> <?= htmlspecialchars($view_txn['owner_name']) ?></p>
                    <p><strong>Date:</strong> <?= formatDate($view_txn['transaction_date']) ?></p>
                    <p><strong>Status:</strong> <?= statusBadge($view_txn['status']) ?></p>
                </div>

                <?php if (!empty($txn_items)): ?>
                    <p style="font-weight:700;font-size:13px;margin-bottom:6px;">Service/Product availed:</p>
                    <table class="billing-items">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Type</th>
                                <th style="text-align:right;">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($txn_items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td style="font-size:11px;color:var(--text-light);">
                                        <span style="background:<?= $item['item_type']==='product'?'#e0f7fa':'#e8f5e9' ?>;
                                                     color:<?= $item['item_type']==='product'?'#00838f':'#2e7d32' ?>;
                                                     padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">
                                            <?= $item['item_type']==='product' ? '🛍️ Product' : '⚕️ Service' ?>
                                        </span>
                                    </td>
                                    <td><?= formatPeso($item['price']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="billing-total-row">
                                <td colspan="2">Total:</td>
                                <td><?= formatPeso($view_txn['total_amount']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color:var(--text-light);font-size:13px;">No line items recorded.</p>
                    <p style="font-weight:800;margin-top:8px;">Total: <?= formatPeso($view_txn['total_amount']) ?></p>
                <?php endif; ?>

                <?php if ($view_txn['notes'] && $view_txn['notes'] !== 'Product purchase by user'): ?>
                    <p style="margin-top:14px;font-size:13px;color:var(--text-light);">
                        <strong>Notes:</strong> <?= htmlspecialchars($view_txn['notes']) ?>
                    </p>
                <?php endif; ?>

                <div class="no-print" style="margin-top:20px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                    <!-- Print PDF -->
                    <button onclick="window.print()"
                            style="background:#c0392b;color:#fff;border:none;padding:9px 20px;
                                   border-radius:var(--radius-sm);font-weight:700;font-size:13px;
                                   cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                        📄 Print as PDF
                    </button>
                    <!-- Status update -->
                    <form method="POST" style="display:flex;gap:8px;align-items:center;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="txn_id" value="<?= $view_txn['id'] ?>">
                        <select name="status" class="status-select-sm">
                            <option value="paid"    <?= $view_txn['status']==='paid'    ?'selected':'' ?>>Paid</option>
                            <option value="pending" <?= $view_txn['status']==='pending' ?'selected':'' ?>>Pending</option>
                            <option value="overdue" <?= $view_txn['status']==='overdue' ?'selected':'' ?>>Overdue</option>
                        </select>
                        <button type="submit" class="btn btn-teal btn-sm">Update Status</button>
                    </form>
                    <a href="transactions.php" class="btn btn-gray btn-sm">Close</a>
                </div>
            </div>

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
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                            <div style="width:10px;height:10px;border-radius:50%;background:#10b981;"></div>
                            Paid ₱<?= number_format($paid_total,0) ?>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                            <div style="width:10px;height:10px;border-radius:50%;background:#f59e0b;"></div>
                            Pending ₱<?= number_format($pending_total,0) ?>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div style="width:10px;height:10px;border-radius:50%;background:#ef4444;"></div>
                            Overdue ₱<?= number_format($overdue_total,0) ?>
                        </div>
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

                <!-- Filters -->
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
                        <option value="product"     <?= $filter_source==='product'    ?'selected':'' ?>>🛍️ Product Purchase</option>
                        <option value="appointment" <?= $filter_source==='appointment'?'selected':'' ?>>📅 Appointment</option>
                        <option value="manual"      <?= $filter_source==='manual'     ?'selected':'' ?>>📄 Manual Invoice</option>
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
                                    <th>Billing Summary</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $i => $txn): ?>
                                    <tr>
                                        <td style="color:var(--text-light);"><?= $i+1 ?></td>
                                        <td><strong><?= htmlspecialchars($txn['client_name'] ?? '—') ?></strong></td>
                                        <td><?= htmlspecialchars($txn['pet_name'] ?? '—') ?></td>
                                        <td style="font-size:12px;"><?= formatDate($txn['transaction_date']) ?></td>
                                        <td style="font-weight:800;color:var(--teal-dark);"><?= formatPeso($txn['total_amount']) ?></td>
                                        <td>
                                            <?php if ($txn['notes'] === 'Product purchase by user'): ?>
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
                                               style="color:var(--teal-dark);font-weight:700;font-size:12px;text-decoration:underline;">
                                                View Details...
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
            <?php endif; // end view ?>

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

            <!-- Autocomplete datalists -->
            <datalist id="productSuggestions">
    <?php foreach ($all_prods as $ap): ?>
        <option value="<?= htmlspecialchars($ap['name']) . ($ap['status']==='out_of_stock' ? ' [SOLD OUT]' : ($ap['status']==='low_stock' ? ' [LOW STOCK]' : '')) ?>"
                data-price="<?= $ap['price'] ?>"
                data-status="<?= $ap['status'] ?>">
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
        <div class="sold-out-subtitle">
            The following product(s) are <strong style="color:#ef4444;">out of stock</strong>
            and will be <strong>removed</strong> from the invoice:
        </div>
        <div class="sold-out-list" id="soldOutList"></div>
        <div class="sold-out-note">
            💡 All other items will still be <strong>included</strong> in the invoice.
            Do you want to proceed?
        </div>
        <div class="sold-out-actions">
            <button class="sold-out-cancel-btn" onclick="closeSoldOutModal()">Cancel</button>
            <button class="sold-out-proceed-btn" onclick="confirmSoldOutProceed()">✅ Yes, Proceed</button>
        </div>
    </div>
</div>


<script src="../assets/js/main.js"></script>
<script>
// ── Charts ───────────────────────────────────────────────────
const months  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const revData = <?= json_encode(array_values($rev_data)) ?>;

new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: months,
        datasets: [{ label:'Revenue', data:revData, backgroundColor:'#1565c0', borderRadius:5 }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero:true, ticks:{ font:{size:11}, callback: v=>'₱'+v.toLocaleString() }, grid:{color:'rgba(0,0,0,0.05)'} },
            x: { ticks:{font:{size:10}}, grid:{display:false} }
        }
    }
});

new Chart(document.getElementById('statusDonut'), {
    type: 'doughnut',
    data: {
        labels: ['Paid','Pending','Overdue'],
        datasets: [{
            data: [<?= $paid_total ?>, <?= $pending_total ?>, <?= $overdue_total ?>],
            backgroundColor: ['#10b981','#f59e0b','#ef4444'],
            borderWidth: 2, borderColor: '#fff'
        }]
    },
    options: { responsive:true, plugins:{legend:{display:false}}, cutout:'60%' }
});

// ── Line items ────────────────────────────────────────────────
let itemCount = 0;

function addLineItem(name='', type='service', price='') {
    const container = document.getElementById('lineItemsContainer');
    const idx = itemCount++;
    const div = document.createElement('div');
    div.className = 'line-item-row';
    div.id = 'item-row-' + idx;
    div.innerHTML = `
        <input type="text" name="item_name[${idx}]" value="${name}"
               list="${type==='product'?'productSuggestions':'serviceSuggestions'}"
               id="item-name-${idx}" placeholder="e.g. CheckUp, Collar"
               oninput="autoFillPrice(${idx}, this.value)"
               style="padding:7px 10px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:12px;font-family:var(--font-main);">
        <select name="item_type[${idx}]" id="item-type-${idx}"
                onchange="updateItemList(${idx}, this.value)"
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

function removeItem(idx) {
    const el = document.getElementById('item-row-' + idx);
    if (el) { el.remove(); updateTotal(); }
}

function updateTotal() {
    let total = 0;
    document.querySelectorAll('[name^="item_price"]').forEach(p => total += parseFloat(p.value || 0));
    document.getElementById('runningTotal').textContent =
        'Total: ₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function autoFillPrice(idx, val) {
    const typeEl   = document.getElementById('item-type-' + idx);
    const priceEl  = document.getElementById('item-price-' + idx);
    const nameEl   = document.getElementById('item-name-' + idx);
    if (!typeEl || !priceEl) return;
    const datalist = document.getElementById(typeEl.value === 'product' ? 'productSuggestions' : 'serviceSuggestions');
    if (!datalist) return;

    // Remove any existing warning
    const oldWarn = document.getElementById('warn-' + idx);
    if (oldWarn) oldWarn.remove();
    nameEl.style.borderColor = 'var(--border)';

    for (const opt of datalist.options) {
        // Match with or without the [SOLD OUT]/[LOW STOCK] suffix
        const optBase = opt.value.replace(/ \[(SOLD OUT|LOW STOCK)\]$/, '');
        if (optBase.toLowerCase() === val.replace(/ \[(SOLD OUT|LOW STOCK)\]$/, '').toLowerCase()) {
            const p      = opt.getAttribute('data-price');
            const status = opt.getAttribute('data-status');
            if (p) { priceEl.value = parseFloat(p).toFixed(2); updateTotal(); }

            if (status === 'out_of_stock') {
                nameEl.style.borderColor = '#ef4444';
                const warn = document.createElement('div');
                warn.id = 'warn-' + idx;
                warn.style = 'color:#ef4444;font-size:11px;font-weight:700;margin-top:2px;';
                warn.textContent = '❌ This product is out of stock!';
                document.getElementById('item-row-' + idx).after(warn);
            } else if (status === 'low_stock') {
                nameEl.style.borderColor = '#f59e0b';
                const warn = document.createElement('div');
                warn.id = 'warn-' + idx;
                warn.style = 'color:#f59e0b;font-size:11px;font-weight:700;margin-top:2px;';
                warn.textContent = '⚠️ Low stock — only a few left!';
                document.getElementById('item-row-' + idx).after(warn);
            }
            break;
        }
    }
}

function updateItemList(idx, type) {
    const nameEl = document.getElementById('item-name-' + idx);
    if (nameEl) {
        nameEl.setAttribute('list', type === 'product' ? 'productSuggestions' : 'serviceSuggestions');
        nameEl.value = '';
        const priceEl = document.getElementById('item-price-' + idx);
        if (priceEl) { priceEl.value = ''; updateTotal(); }
    }
}

function loadClientPets(userId) {
    const sel = document.getElementById('petDropdown');
    sel.innerHTML = '<option value="">Loading...</option>';
    if (!userId) { sel.innerHTML = '<option value="">— None —</option>'; return; }
    fetch(`../includes/get_pets.php?user_id=${userId}`)
        .then(r => r.json())
        .then(pets => {
            sel.innerHTML = '<option value="">— None —</option>';
            pets.forEach(p => { sel.innerHTML += `<option value="${p.id}">${p.name} (${p.species})</option>`; });
        })
        .catch(() => { sel.innerHTML = '<option value="">— None —</option>'; });
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });
});

let pendingSoldOut = [];

function submitInvoice() {
    pendingSoldOut = [];

    document.querySelectorAll('[id^="warn-"]').forEach(w => {
        if (w.textContent.includes('out of stock')) {
            const idx    = w.id.replace('warn-', '');
            const nameEl = document.getElementById('item-name-' + idx);
            if (nameEl) pendingSoldOut.push({ idx, name: nameEl.value.replace(/ \[SOLD OUT\]$/, '') });
        }
    });

    document.querySelectorAll('[id^="item-name-"]').forEach(input => {
        if (input.value.includes('[SOLD OUT]')) {
            const idx     = input.id.replace('item-name-', '');
            const already = pendingSoldOut.find(i => i.idx === idx);
            if (!already) pendingSoldOut.push({ idx, name: input.value.replace(/ \[SOLD OUT\]$/, '') });
        }
    });

    if (pendingSoldOut.length > 0) {
        const listEl = document.getElementById('soldOutList');
        listEl.innerHTML = pendingSoldOut.map(i =>
            `<div class="sold-out-list-item">
                <span style="color:#ef4444;font-size:16px;flex-shrink:0;">✕</span>
                <strong>${i.name}</strong>
                <span style="margin-left:auto;font-size:11px;background:#fee2e2;color:#dc2626;
                             padding:2px 8px;border-radius:20px;font-weight:700;">Out of Stock</span>
            </div>`
        ).join('');
        document.getElementById('soldOutModal').classList.add('show');
        return;
    }
    doSubmitInvoice();
}

function closeSoldOutModal() {
    document.getElementById('soldOutModal').classList.remove('show');
    pendingSoldOut = [];
}

function confirmSoldOutProceed() {
    document.getElementById('soldOutModal').classList.remove('show');
    pendingSoldOut.forEach(item => {
        const row  = document.getElementById('item-row-' + item.idx);
        const warn = document.getElementById('warn-' + item.idx);
        if (warn) warn.remove();
        if (row)  row.remove();
    });
    pendingSoldOut = [];
    updateTotal();
    doSubmitInvoice();
}

function doSubmitInvoice() {
    const remaining = document.querySelectorAll('[id^="item-name-"]');
    if (remaining.length === 0) {
        document.getElementById('soldOutBox').innerHTML =
            `<div class="sold-out-icon">🛒</div>
             <div class="sold-out-title">Invoice is Empty</div>
             <p class="sold-out-subtitle">
                 No items are left after removing out-of-stock products.<br>
                 Please add at least one valid item.
             </p>
             <button class="sold-out-proceed-btn"
                     onclick="document.getElementById('soldOutModal').classList.remove('show')">
                 OK, Go Back
             </button>`;
        document.getElementById('soldOutModal').classList.add('show');
        return;
    }
    document.getElementById('txnForm').submit();
}

// Close modal on backdrop click
document.getElementById('soldOutModal').addEventListener('click', function(e) {
    if (e.target === this) closeSoldOutModal();
});

</script>
</body>
</html>