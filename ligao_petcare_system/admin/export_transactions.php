<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/export_transactions.php
// Purpose: Export transaction summary as PDF (print) or Excel
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$format        = $_GET['format']        ?? 'pdf';   // 'pdf' | 'excel'
$filter_status = sanitize($conn, $_GET['filter_status'] ?? '');
$filter_source = sanitize($conn, $_GET['filter_source'] ?? '');
$date_from     = sanitize($conn, $_GET['date_from']     ?? '');
$date_to       = sanitize($conn, $_GET['date_to']       ?? '');
$search_txn    = sanitize($conn, $_GET['search_txn']    ?? '');

// ── Build WHERE ───────────────────────────────────────────────
$where = '1=1';
if ($search_txn)    $where .= " AND u.name LIKE '%$search_txn%'";
if ($filter_status) $where .= " AND t.status='$filter_status'";
if ($date_from)     $where .= " AND t.transaction_date >= '$date_from'";
if ($date_to)       $where .= " AND t.transaction_date <= '$date_to'";
if ($filter_source === 'product')     $where .= " AND t.notes = 'Product purchase by user'";
if ($filter_source === 'appointment') $where .= " AND t.appointment_id IS NOT NULL AND (t.notes IS NULL OR t.notes != 'Product purchase by user')";
if ($filter_source === 'manual')      $where .= " AND t.appointment_id IS NULL AND (t.notes IS NULL OR t.notes != 'Product purchase by user')";

$transactions = getRows($conn,
    "SELECT t.*, u.name AS client_name, p.name AS pet_name
     FROM transactions t
     LEFT JOIN users u ON t.user_id = u.id
     LEFT JOIN pets  p ON t.pet_id  = p.id
     WHERE $where
     ORDER BY t.transaction_date DESC");

// ── Summary stats ────────────────────────────────────────────
$total_all     = array_sum(array_column($transactions, 'total_amount'));
$paid_sum      = array_sum(array_map(fn($r) => $r['status'] === 'paid'    ? $r['total_amount'] : 0, $transactions));
$pending_sum   = array_sum(array_map(fn($r) => $r['status'] === 'pending' ? $r['total_amount'] : 0, $transactions));
$overdue_sum   = array_sum(array_map(fn($r) => $r['status'] === 'overdue' ? $r['total_amount'] : 0, $transactions));
$paid_count    = count(array_filter($transactions, fn($r) => $r['status'] === 'paid'));
$pending_count = count(array_filter($transactions, fn($r) => $r['status'] === 'pending'));
$overdue_count = count(array_filter($transactions, fn($r) => $r['status'] === 'overdue'));
$total_count   = count($transactions);

// ── Active filters label ─────────────────────────────────────
$filter_labels = [];
if ($filter_status) $filter_labels[] = 'Status: ' . ucfirst($filter_status);
if ($filter_source) $filter_labels[] = 'Source: ' . ucfirst($filter_source);
if ($date_from)     $filter_labels[] = 'From: ' . date('M d, Y', strtotime($date_from));
if ($date_to)       $filter_labels[] = 'To: ' . date('M d, Y', strtotime($date_to));
if ($search_txn)    $filter_labels[] = 'Search: ' . htmlspecialchars($search_txn);
$filter_str = $filter_labels ? implode(' | ', $filter_labels) : 'All Transactions';

// ════════════════════════════════════════════════════════════
// EXCEL EXPORT
// ════════════════════════════════════════════════════════════
if ($format === 'excel') {
    $filename = 'transactions_report_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // BOM for Excel UTF-8 compatibility
    fputs($out, "\xEF\xBB\xBF");

    // Clinic header
    fputcsv($out, ['LIGAO PETCARE & VETERINARY CLINIC']);
    fputcsv($out, ['Transaction Summary Report']);
    fputcsv($out, ['Generated: ' . date('F d, Y h:i A')]);
    fputcsv($out, ['Filters: ' . $filter_str]);
    fputcsv($out, []);

    // Summary block
    fputcsv($out, ['SUMMARY']);
    fputcsv($out, ['Total Transactions', $total_count]);
    fputcsv($out, ['Total Amount',       '₱' . number_format($total_all, 2)]);
    fputcsv($out, []);
    fputcsv($out, ['STATUS BREAKDOWN', 'Count', 'Amount']);
    fputcsv($out, ['Paid',    $paid_count,    '₱' . number_format($paid_sum, 2)]);
    fputcsv($out, ['Pending', $pending_count, '₱' . number_format($pending_sum, 2)]);
    fputcsv($out, ['Overdue', $overdue_count, '₱' . number_format($overdue_sum, 2)]);
    fputcsv($out, []);

    // Column headers
    fputcsv($out, ['#', 'Reference No.', 'Client Name', 'Pet', 'Date', 'Source', 'Total Amount', 'Status', 'Notes']);

    // Rows
    foreach ($transactions as $i => $txn) {
        $ref = 'TXN-' . str_pad($txn['id'], 5, '0', STR_PAD_LEFT);

        if ($txn['notes'] === 'Product purchase by user') {
            $source = 'Product';
        } elseif ($txn['appointment_id']) {
            $source = 'Appointment';
        } else {
            $source = 'Manual';
        }

        fputcsv($out, [
            $i + 1,
            $ref,
            $txn['client_name'] ?? '—',
            $txn['pet_name']    ?? '—',
            date('M d, Y', strtotime($txn['transaction_date'])),
            $source,
            number_format($txn['total_amount'], 2),
            ucfirst($txn['status']),
            $txn['notes'] ?? '',
        ]);
    }

    fclose($out);
    exit;
}

// ════════════════════════════════════════════════════════════
// PDF / PRINT EXPORT  (rendered HTML → browser print → PDF)
// ════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction Report — Ligao Petcare</title>
    <style>
        /* ── Base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #1a1a2e;
            background: #f5f7fa;
            padding: 24px;
        }

        /* ── No-print toolbar ── */
        .toolbar {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .toolbar-btn {
            padding: 9px 20px;
            border-radius: 8px;
            border: none;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print  { background: #1565c0; color: #fff; }
        .btn-back   { background: #e5e7eb; color: #374151; text-decoration: none; }
        .btn-excel  {
            background: #16a34a; color: #fff; text-decoration: none;
            padding: 9px 20px; border-radius: 8px; font-size: 13px;
            font-weight: 700; display: inline-flex; align-items: center; gap: 6px;
        }

        /* ── Report wrapper ── */
        .report {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        /* ── Header band ── */
        .report-header {
            background: linear-gradient(135deg, #0f4c81 0%, #1565c0 60%, #007b83 100%);
            color: #fff;
            padding: 28px 32px 24px;
        }
        .report-header-top {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 10px;
        }
        .report-logo {
            width: 52px; height: 52px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }
        .report-clinic-name  { font-size: 20px; font-weight: 900; letter-spacing: 1px; }
        .report-clinic-sub   { font-size: 12px; opacity: 0.8; margin-top: 2px; }
        .report-title        { font-size: 15px; font-weight: 700; opacity: 0.95; margin-top: 12px; }
        .report-meta         { font-size: 11px; opacity: 0.75; margin-top: 4px; }
        .report-filters      { font-size: 11px; opacity: 0.75; margin-top: 2px; font-style: italic; }

        /* ── Summary cards ── */
        .summary-section {
            padding: 22px 32px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }
        .summary-section h3 {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #6b7280;
            margin-bottom: 14px;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        .summary-card {
            background: #fff;
            border-radius: 10px;
            padding: 14px 16px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        .summary-card .sc-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .summary-card .sc-count {
            font-size: 22px;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 4px;
        }
        .summary-card .sc-amount {
            font-size: 11px;
            font-weight: 700;
        }
        .card-total   .sc-label, .card-total   .sc-count, .card-total   .sc-amount { color: #1565c0; }
        .card-paid    .sc-label, .card-paid    .sc-count, .card-paid    .sc-amount { color: #059669; }
        .card-pending .sc-label, .card-pending .sc-count, .card-pending .sc-amount { color: #d97706; }
        .card-overdue .sc-label, .card-overdue .sc-count, .card-overdue .sc-amount { color: #dc2626; }
        .card-total   { border-top: 3px solid #1565c0; }
        .card-paid    { border-top: 3px solid #059669; }
        .card-pending { border-top: 3px solid #d97706; }
        .card-overdue { border-top: 3px solid #dc2626; }

        /* ── Table section ── */
        .table-section {
            padding: 22px 32px 28px;
        }
        .table-section h3 {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #6b7280;
            margin-bottom: 14px;
        }

        table.txn-report {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
        }
        table.txn-report thead tr {
            background: #1565c0;
            color: #fff;
        }
        table.txn-report thead th {
            padding: 10px 10px;
            text-align: left;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        table.txn-report thead th:last-child { text-align: right; }
        table.txn-report tbody tr:nth-child(even) { background: #f8fafc; }
        table.txn-report tbody tr:hover { background: #eff6ff; }
        table.txn-report tbody td {
            padding: 9px 10px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        table.txn-report tbody td:last-child { text-align: right; font-weight: 800; }

        .ref-no     { font-size: 10px; color: #6b7280; font-weight: 700; }
        .client-name { font-weight: 700; color: #1a1a2e; }
        .pet-name   { color: #6b7280; font-size: 10px; }

        /* Status badges */
        .badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-paid    { background: #d1fae5; color: #065f46; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-overdue { background: #fee2e2; color: #991b1b; }

        /* Source tag */
        .source-tag {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }
        .src-product     { background: #e0f7fa; color: #00838f; }
        .src-appointment { background: #e8f5e9; color: #2e7d32; }
        .src-manual      { background: #f3f4f6; color: #374151; }

        /* Amount */
        .amount { color: #007b83; font-weight: 800; }

        /* Grand total row */
        .grand-total-row td {
            background: #eff6ff !important;
            font-weight: 900 !important;
            font-size: 12px !important;
            border-top: 2px solid #1565c0 !important;
        }
        .grand-total-row td:last-child { color: #1565c0; font-size: 14px !important; }

        /* Empty state */
        .empty-report {
            text-align: center;
            padding: 40px 20px;
            color: #9ca3af;
        }
        .empty-report .empty-icon { font-size: 40px; margin-bottom: 10px; }

        /* ── Footer ── */
        .report-footer {
            background: #f8fafc;
            border-top: 1px solid #e5e7eb;
            padding: 14px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            color: #9ca3af;
        }

        /* ── Print styles ── */
        @media print {
            @page { size: A4 landscape; margin: 12mm; }
            body { background: #fff !important; padding: 0 !important; font-size: 10px !important; }
            .toolbar { display: none !important; }
            .report {
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
            }
            .report-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .summary-cards { gap: 8px !important; }
            .summary-card  { page-break-inside: avoid; }
            table.txn-report { font-size: 9.5px !important; }
            table.txn-report thead tr {
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            table.txn-report tbody tr:nth-child(even) {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .badge, .source-tag {
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .grand-total-row td {
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<!-- Toolbar (hidden on print) -->
<div class="toolbar no-print">
    <a href="transactions.php" class="toolbar-btn btn-back">← Back</a>
    <button onclick="window.print()" class="toolbar-btn btn-print">🖨️ Print / Save as PDF</button>
    <a href="export_transactions.php?format=excel
        <?= $filter_status ? '&filter_status=' . urlencode($filter_status) : '' ?>
        <?= $filter_source ? '&filter_source=' . urlencode($filter_source) : '' ?>
        <?= $date_from     ? '&date_from='     . urlencode($date_from)     : '' ?>
        <?= $date_to       ? '&date_to='       . urlencode($date_to)       : '' ?>
        <?= $search_txn    ? '&search_txn='    . urlencode($search_txn)    : '' ?>"
       class="btn-excel">📊 Download Excel (.csv)</a>
    <span style="color:#6b7280;font-size:12px;">
        <?= $total_count ?> record<?= $total_count !== 1 ? 's' : '' ?> found
    </span>
</div>

<!-- Report -->
<div class="report">

    <!-- Header -->
    <div class="report-header">
        <div class="report-header-top">
            <div class="report-logo">🐾</div>
            <div>
                <div class="report-clinic-name">LIGAO PETCARE &amp; VETERINARY CLINIC</div>
                <div class="report-clinic-sub">Ligao City, Albay</div>
            </div>
        </div>
        <div class="report-title">Transaction Summary Report</div>
        <div class="report-meta">Generated: <?= date('F d, Y — h:i A') ?></div>
        <div class="report-filters">Filters: <?= $filter_str ?></div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-section">
        <h3>Summary Overview</h3>
        <div class="summary-cards">
            <div class="summary-card card-total">
                <div class="sc-label">Total Invoices</div>
                <div class="sc-count"><?= $total_count ?></div>
                <div class="sc-amount">₱<?= number_format($total_all, 2) ?></div>
            </div>
            <div class="summary-card card-paid">
                <div class="sc-label">✔ Paid</div>
                <div class="sc-count"><?= $paid_count ?></div>
                <div class="sc-amount">₱<?= number_format($paid_sum, 2) ?></div>
            </div>
            <div class="summary-card card-pending">
                <div class="sc-label">⏳ Pending</div>
                <div class="sc-count"><?= $pending_count ?></div>
                <div class="sc-amount">₱<?= number_format($pending_sum, 2) ?></div>
            </div>
            <div class="summary-card card-overdue">
                <div class="sc-label">⚠ Overdue</div>
                <div class="sc-count"><?= $overdue_count ?></div>
                <div class="sc-amount">₱<?= number_format($overdue_sum, 2) ?></div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="table-section">
        <h3>Transaction Records (<?= $total_count ?>)</h3>

        <?php if (empty($transactions)): ?>
            <div class="empty-report">
                <div class="empty-icon">📄</div>
                <p>No transactions match the selected filters.</p>
            </div>
        <?php else: ?>
            <table class="txn-report">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Reference</th>
                        <th>Client Name</th>
                        <th>Pet</th>
                        <th>Date</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $i => $txn): ?>
                        <?php
                            $ref = 'TXN-' . str_pad($txn['id'], 5, '0', STR_PAD_LEFT);
                            if ($txn['notes'] === 'Product purchase by user') {
                                $src_class = 'src-product';
                                $src_label = '🛍️ Product';
                            } elseif ($txn['appointment_id']) {
                                $src_class = 'src-appointment';
                                $src_label = '📅 Appointment';
                            } else {
                                $src_class = 'src-manual';
                                $src_label = '📄 Manual';
                            }
                            $badge_class = 'badge-' . $txn['status'];
                            $badge_label = $txn['status'] === 'paid' ? '✔ Paid' : ucfirst($txn['status']);
                        ?>
                        <tr>
                            <td style="color:#9ca3af;"><?= $i + 1 ?></td>
                            <td><span class="ref-no"><?= $ref ?></span></td>
                            <td>
                                <span class="client-name"><?= htmlspecialchars($txn['client_name'] ?? '—') ?></span>
                            </td>
                            <td><span class="pet-name"><?= htmlspecialchars($txn['pet_name'] ?? '—') ?></span></td>
                            <td style="white-space:nowrap;color:#374151;"><?= date('M d, Y', strtotime($txn['transaction_date'])) ?></td>
                            <td><span class="source-tag <?= $src_class ?>"><?= $src_label ?></span></td>
                            <td><span class="badge <?= $badge_class ?>"><?= $badge_label ?></span></td>
                            <td><span class="amount">₱<?= number_format($txn['total_amount'], 2) ?></span></td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Grand Total Row -->
                    <tr class="grand-total-row">
                        <td colspan="7" style="text-align:right;letter-spacing:0.5px;">
                            GRAND TOTAL (<?= $total_count ?> transaction<?= $total_count !== 1 ? 's' : '' ?>)
                        </td>
                        <td>₱<?= number_format($total_all, 2) ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="report-footer">
        <span>🐾 Ligao Petcare &amp; Veterinary Clinic &mdash; Ligao City, Albay</span>
        <span>Report generated on <?= date('F d, Y') ?> at <?= date('h:i A') ?></span>
    </div>

</div><!-- /report -->

<script>
    // Auto-trigger print dialog if ?autoprint=1 is in URL
    const params = new URLSearchParams(window.location.search);
    if (params.get('autoprint') === '1') {
        window.addEventListener('load', () => setTimeout(() => window.print(), 500));
    }
</script>
</body>
</html>