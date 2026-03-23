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
if ($type_f === 'product')     $where .= " AND t.appointment_id IS NULL";
if ($type_f === 'appointment') $where .= " AND t.appointment_id IS NOT NULL";
if ($search)                   $where .= " AND (p.name LIKE '%$search%' OR t.notes LIKE '%$search%')";

$transactions = getRows($conn,
    "SELECT t.*, p.name AS pet_name, p.species, p.breed,
            u.name AS owner_name, u.address, u.contact_no,
            a.appointment_date, a.appointment_type,
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
            <form method="GET" class="txn-filters">
                <div class="type-pills">
                    <a href="?type=all&from=<?= $from ?>&to=<?= $to ?>"
                       class="type-pill <?= $type_f==='all'         ? 'active':'' ?>">All</a>
                    <a href="?type=appointment&from=<?= $from ?>&to=<?= $to ?>"
                       class="type-pill <?= $type_f==='appointment' ? 'active':'' ?>">🏥 Services</a>
                    <a href="?type=product&from=<?= $from ?>&to=<?= $to ?>"
                       class="type-pill <?= $type_f==='product'     ? 'active':'' ?>">🛍️ Products</a>
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
                <input type="hidden" name="type" value="<?= $type_f ?>">
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
                        $is_appt   = !empty($txn['appointment_id']);
                        $type_icon = $is_appt ? '🏥' : '🛍️';
                        $type_bg   = $is_appt ? '#e0f7fa' : '#fdf4ff';
                        $type_lbl  = $is_appt
                            ? ucfirst(str_replace('_', ' ', $txn['appointment_type'] ?? 'Clinic'))
                            : 'Product Purchase';
                        $ref_no    = 'TXN-' . str_pad($txn['id'], 5, '0', STR_PAD_LEFT);
                        $status_badge = $txn['status'] === 'paid'
                            ? '<span class="badge-paid">✔ Paid</span>'
                            : '<span class="badge-pending">⏳ Pending</span>';
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
                                        <?= formatDate($txn['transaction_date']) ?>
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
                                <?php if ($txn['service_name']): ?>
                                    <span>💉 <?= htmlspecialchars($txn['service_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($txn['appointment_date']): ?>
                                    <span>📅 Appt: <?= formatDate($txn['appointment_date']) ?></span>
                                <?php endif; ?>
                            </div>

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
                            <button
                                class="btn btn-teal btn-sm"
                                onclick='openReceipt(<?= htmlspecialchars(json_encode([
                                    "ref"        => $ref_no,
                                    "date"       => $txn["transaction_date"],
                                    "type"       => $type_lbl,
                                    "status"     => $txn["status"],
                                    "owner"      => $txn["owner_name"],
                                    "address"    => $txn["address"],
                                    "contact"    => $txn["contact_no"],
                                    "pet"        => $txn["pet_name"],
                                    "breed"      => $txn["breed"],
                                    "species"    => $txn["species"],
                                    "service"    => $txn["service_name"],
                                    "appt_date"  => $txn["appointment_date"],
                                    "appt_type"  => $txn["appointment_type"],
                                    "items"      => array_map(fn($i) => ["name"=>$i["item_name"],"price"=>$i["price"]], $txn["items"]),
                                    "total"      => $txn["total_amount"],
                                    "notes"      => $txn["notes"],
                                ]), ENT_QUOTES) ?>)'>
                                🖨️ View Receipt
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
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#888;">Type:</span>
                    <span id="r_type"></span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#888;">Status:</span>
                    <strong id="r_status" style="text-transform:capitalize;"></strong>
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
                <div id="r_appt_row" style="display:none;">
                    <span style="color:#888;">Appt. Date:</span> <span id="r_appt_date"></span>
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
    // meta
    document.getElementById('r_ref').textContent    = data.ref    || '—';
    document.getElementById('r_date').textContent   = data.date   || '—';
    document.getElementById('r_type').textContent   = data.type   || '—';
    const statusEl = document.getElementById('r_status');
    statusEl.textContent = data.status || '—';
    statusEl.style.color = data.status === 'paid' ? '#065f46' : '#92400e';

    // client
    document.getElementById('r_owner').textContent  = data.owner  || '—';

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

    if (data.appt_date) {
        document.getElementById('r_appt_row').style.display = '';
        document.getElementById('r_appt_date').textContent  = data.appt_date;
    } else {
        document.getElementById('r_appt_row').style.display = 'none';
    }

    // items
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
    } else if (data.notes) {
        itemsHtml = `<div style="color:#888;">${escHtml(data.notes)}</div>`;
    } else {
        itemsHtml = '<div style="color:#aaa;">No line items recorded.</div>';
    }
    document.getElementById('r_items_list').innerHTML = itemsHtml;

    // total
    document.getElementById('r_total').textContent = formatPeso(data.total);

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

// Close on overlay click already handled on the element itself
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeReceipt();
});
</script>
</body>
</html>