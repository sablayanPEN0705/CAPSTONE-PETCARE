<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/services.php
// Purpose: Manage clinic services — add, edit, delete
//          + Pet size/weight-based price autofill matrix
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// ── Default pricing matrix per service (size × weight brackets) ─
// Structure: [service_name => [size => [max_kg => price_min, price_max]]]
// This can be stored in DB; here we define defaults used to prefill.
$SERVICE_PRICE_MATRIX = [
    'CheckUp' => [
        'small'  => ['max_kg'=>5,  'ranges'=>[[0,5,150,250],[5,10,200,300]]],
        'medium' => ['max_kg'=>20, 'ranges'=>[[5,20,250,400],[20,30,300,500]]],
        'large'  => ['max_kg'=>99, 'ranges'=>[[20,40,400,600],[40,99,500,800]]],
    ],
    'Vaccination' => [
        'small'  => ['max_kg'=>5,  'ranges'=>[[0,5,300,500],[5,10,400,600]]],
        'medium' => ['max_kg'=>20, 'ranges'=>[[5,20,500,700],[20,30,600,900]]],
        'large'  => ['max_kg'=>99, 'ranges'=>[[20,40,700,1000],[40,99,900,1300]]],
    ],
    'Deworming' => [
        'small'  => ['max_kg'=>5,  'ranges'=>[[0,5,150,200],[5,10,200,300]]],
        'medium' => ['max_kg'=>20, 'ranges'=>[[5,20,250,350],[20,30,300,450]]],
        'large'  => ['max_kg'=>99, 'ranges'=>[[20,40,350,500],[40,99,500,700]]],
    ],
    'Grooming' => [
        'small'  => ['max_kg'=>5,  'ranges'=>[[0,5,250,400],[5,10,300,500]]],
        'medium' => ['max_kg'=>20, 'ranges'=>[[5,20,450,650],[20,30,550,800]]],
        'large'  => ['max_kg'=>99, 'ranges'=>[[20,40,700,1000],[40,99,900,1400]]],
    ],
    'Confinement' => [
        'small'  => ['max_kg'=>5,  'ranges'=>[[0,5,500,800],[5,10,700,1000]]],
        'medium' => ['max_kg'=>20, 'ranges'=>[[5,20,900,1300],[20,30,1100,1600]]],
        'large'  => ['max_kg'=>99, 'ranges'=>[[20,40,1400,2000],[40,99,1800,2600]]],
    ],
    'Treatment' => [
        'small'  => ['max_kg'=>5,  'ranges'=>[[0,5,300,600],[5,10,400,800]]],
        'medium' => ['max_kg'=>20, 'ranges'=>[[5,20,600,1000],[20,30,800,1300]]],
        'large'  => ['max_kg'=>99, 'ranges'=>[[20,40,1000,1600],[40,99,1300,2000]]],
    ],
    'Laboratory' => [
        'small'  => ['max_kg'=>5,  'ranges'=>[[0,5,400,700],[5,10,500,900]]],
        'medium' => ['max_kg'=>20, 'ranges'=>[[5,20,700,1100],[20,30,900,1400]]],
        'large'  => ['max_kg'=>99, 'ranges'=>[[20,40,1100,1600],[40,99,1400,2000]]],
    ],
    'Home Service' => [
        'small'  => ['max_kg'=>5,  'ranges'=>[[0,5,500,800],[5,10,600,1000]]],
        'medium' => ['max_kg'=>20, 'ranges'=>[[5,20,800,1200],[20,30,1000,1500]]],
        'large'  => ['max_kg'=>99, 'ranges'=>[[20,40,1200,1800],[40,99,1500,2200]]],
    ],
    'Surgery' => [
        'small'  => ['max_kg'=>5,  'ranges'=>[[0,5,2000,4000],[5,10,3000,6000]]],
        'medium' => ['max_kg'=>20, 'ranges'=>[[5,20,5000,9000],[20,30,7000,12000]]],
        'large'  => ['max_kg'=>99, 'ranges'=>[[20,40,10000,16000],[40,99,14000,22000]]],
    ],
];

// ── Handle Add Service ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_service') {
        $name      = sanitize($conn, $_POST['name']        ?? '');
        $desc      = sanitize($conn, $_POST['description'] ?? '');
        $price_min = (float)($_POST['price_min'] ?? 0);
        $price_max = (float)($_POST['price_max'] ?? 0);
        $category  = sanitize($conn, $_POST['category']    ?? '');
        $status    = sanitize($conn, $_POST['status']      ?? 'available');

        if (empty($name)) {
            redirect('services.php?error=Service name is required.');
        }

        $image = '';
        if (!empty($_FILES['image']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed)) {
                $upload_dir = '../assets/images/services/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = 'svc_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename);
                $image = $filename;
            }
        }

        mysqli_query($conn,
            "INSERT INTO services (name, description, price_min, price_max, category, status, image)
             VALUES ('$name','$desc',$price_min,$price_max,'$category','$status','$image')");
        redirect('services.php?success=Service added successfully!');
    }

    if ($action === 'edit_service') {
        $sid       = (int)$_POST['service_id'];
        $name      = sanitize($conn, $_POST['name']        ?? '');
        $desc      = sanitize($conn, $_POST['description'] ?? '');
        $price_min = (float)($_POST['price_min'] ?? 0);
        $price_max = (float)($_POST['price_max'] ?? 0);
        $category  = sanitize($conn, $_POST['category']    ?? '');
        $status    = sanitize($conn, $_POST['status']      ?? 'available');

        $image_sql = '';
        if (!empty($_FILES['image']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed)) {
                $upload_dir = '../assets/images/services/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename  = 'svc_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename);
                $image_sql = ", image='$filename'";
            }
        }

        mysqli_query($conn,
            "UPDATE services SET
                name='$name', description='$desc',
                price_min=$price_min, price_max=$price_max,
                category='$category', status='$status' $image_sql
             WHERE id=$sid");
        redirect('services.php?success=Service updated successfully!');
    }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM services WHERE id=$did");
    redirect('services.php?success=Service deleted.');
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    $svc = getRow($conn, "SELECT status FROM services WHERE id=$tid");
    if ($svc) {
        $new_status = $svc['status'] === 'available' ? 'not_available' : 'available';
        mysqli_query($conn, "UPDATE services SET status='$new_status' WHERE id=$tid");
        redirect("services.php?success=Service status toggled.");
    }
}

// ── Stats ─────────────────────────────────────────────────────
$total_services     = countRows($conn, 'services');
$available_services = countRows($conn, 'services', "status='available'");
$unavailable        = countRows($conn, 'services', "status='not_available'");
$avg_price          = getRow($conn,
    "SELECT COALESCE(AVG((price_min+price_max)/2),0) AS avg FROM services");
$avg_price          = round($avg_price['avg'] ?? 0);

// ── Fetch all services ────────────────────────────────────────
$filter_cat    = sanitize($conn, $_GET['filter_cat'] ?? '');
$filter_status = sanitize($conn, $_GET['filter_status'] ?? '');
$where         = '1=1';
if ($filter_cat)    $where .= " AND category='$filter_cat'";
if ($filter_status) $where .= " AND status='$filter_status'";
$services = getRows($conn, "SELECT * FROM services WHERE $where ORDER BY id ASC");

$icons = [
    'CheckUp'      => '🩺',
    'Confinement'  => '🏥',
    'Treatment'    => '💊',
    'Deworming'    => '🪱',
    'Vaccination'  => '💉',
    'Grooming'     => '🐩',
    'Surgery'      => '🔬',
    'Laboratory'   => '🧪',
    'Home Service' => '🏠',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services — Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Existing styles ─────────────────────── */
        .svc-status-toggle {
            cursor: pointer;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            border: none;
            transition: var(--transition);
        }
        .svc-available    { background: #d1fae5; color: #065f46; }
        .svc-not_available{ background: #fee2e2; color: #991b1b; }
        .price-range      { font-weight: 700; font-size: 13px; color: var(--teal-dark); }
        .svc-icon-cell    { font-size: 22px; text-align: center; }
        .action-btns      { display: flex; gap: 4px; align-items: center; }
        .price-row        { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .filter-bar-row   {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px; margin-bottom: 18px;
        }
        .filter-pills { display: flex; gap: 6px; flex-wrap: wrap; }
        .filter-pill {
            padding: 5px 14px; border-radius: var(--radius-lg); font-size: 12px;
            font-weight: 700; border: 1.5px solid var(--border);
            background: rgba(255,255,255,0.8); color: var(--text-mid);
            cursor: pointer; transition: var(--transition); text-decoration: none;
        }
        .filter-pill:hover,.filter-pill.active {
            background: var(--teal); color: #fff; border-color: var(--teal);
        }
        .table-section {
            background: rgba(255,255,255,0.9); border-radius: var(--radius);
            padding: 20px; box-shadow: var(--shadow);
        }
        .table-section-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 16px;
        }
        .table-section-header h3 { font-family: var(--font-head); font-size: 17px; font-weight: 700; }

        /* ── Price Autofill Panel ───────────────────── */
        .price-autofill-panel {
            background: linear-gradient(135deg, #f0fdfa 0%, #ecfdf5 100%);
            border: 1.5px solid #6ee7b7;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .price-autofill-panel h4 {
            font-size: 13px;
            font-weight: 800;
            color: #065f46;
            margin: 0 0 12px 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .size-weight-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }
        .size-weight-grid label {
            font-size: 11px;
            font-weight: 700;
            color: #047857;
            margin-bottom: 4px;
            display: block;
        }
        .size-btn-group {
            display: flex;
            gap: 6px;
        }
        .size-btn {
            flex: 1;
            padding: 7px 4px;
            border-radius: 8px;
            border: 1.5px solid #a7f3d0;
            background: #fff;
            font-size: 11px;
            font-weight: 700;
            color: #065f46;
            cursor: pointer;
            transition: all 0.15s;
            text-align: center;
        }
        .size-btn:hover { background: #d1fae5; border-color: #10b981; }
        .size-btn.active { background: #10b981; color: #fff; border-color: #059669; box-shadow: 0 2px 8px rgba(16,185,129,0.35); }

        .weight-input-wrap {
            position: relative;
        }
        .weight-input-wrap input {
            width: 100%;
            padding: 8px 36px 8px 10px;
            border: 1.5px solid #a7f3d0;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #065f46;
            background: #fff;
            box-sizing: border-box;
        }
        .weight-input-wrap input:focus { outline: none; border-color: #10b981; }
        .weight-unit {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 11px;
            font-weight: 700;
            color: #6ee7b7;
        }

        .autofill-result {
            background: #fff;
            border: 1.5px solid #a7f3d0;
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .autofill-result-label {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
        }
        .autofill-result-price {
            font-size: 17px;
            font-weight: 900;
            color: #065f46;
            font-family: var(--font-head, monospace);
        }
        .autofill-apply-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(16,185,129,0.3);
        }
        .autofill-apply-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(16,185,129,0.45);
        }
        .autofill-apply-btn:disabled {
            background: #d1d5db;
            color: #9ca3af;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }
        .no-matrix-note {
            font-size: 11px;
            color: #9ca3af;
            font-style: italic;
            text-align: center;
            padding: 6px;
        }

        .weight-hint {
            font-size: 10px;
            color: #6ee7b7;
            margin-top: 3px;
        }

        .size-labels-row {
            display: grid;
            grid-template-columns: repeat(3,1fr);
            gap: 4px;
            margin-bottom: 4px;
        }
        .size-label-chip {
            font-size: 9.5px;
            text-align: center;
            color: #6b7280;
            background: #f3f4f6;
            border-radius: 4px;
            padding: 2px 4px;
        }

        /* Divider */
        .modal-divider {
            border: none;
            border-top: 1.5px dashed #e5e7eb;
            margin: 14px 0;
        }

        /* Pricing Table Preview in modal */
        .pricing-table-preview {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 8px;
        }
        .pricing-table-preview th {
            background: #d1fae5;
            color: #065f46;
            font-weight: 800;
            padding: 5px 8px;
            text-align: left;
        }
        .pricing-table-preview td {
            padding: 5px 8px;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
        }
        .pricing-table-preview tr:last-child td { border-bottom: none; }
        .pricing-table-preview tr:hover td { background: #f0fdf4; }

        .show-table-toggle {
            font-size: 11px;
            color: #10b981;
            cursor: pointer;
            font-weight: 700;
            background: none;
            border: none;
            padding: 0;
            text-decoration: underline;
            margin-top: 6px;
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title">SERVICES</div>
            <div class="topbar-actions">
                <a href="messages.php" class="topbar-btn">💬</a>
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stat-cards" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
                <div class="stat-card">
                    <div class="stat-label">Total Services</div>
                    <div class="stat-value"><?= $total_services ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Available</div>
                    <div class="stat-value" style="color:#10b981;"><?= $available_services ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Not Available</div>
                    <div class="stat-value" style="color:#ef4444;"><?= $unavailable ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Average Price</div>
                    <div class="stat-value" style="font-size:26px;">
                        ₱<?= number_format($avg_price) ?>
                    </div>
                </div>
            </div>

            <!-- Services Table -->
            <div class="table-section">
                <div class="table-section-header">
                    <h3>Services List</h3>
                    <button class="btn btn-teal btn-sm"
                            onclick="openAddModal()">
                        + Add New Service
                    </button>
                </div>

                <!-- Filters -->
                <div class="filter-bar-row">
                    <div class="filter-pills">
                        <a href="services.php"
                           class="filter-pill <?= !$filter_status ? 'active':'' ?>">All</a>
                        <a href="?filter_status=available"
                           class="filter-pill <?= $filter_status==='available' ? 'active':'' ?>">
                            ✅ Available
                        </a>
                        <a href="?filter_status=not_available"
                           class="filter-pill <?= $filter_status==='not_available' ? 'active':'' ?>">
                            ❌ Not Available
                        </a>
                    </div>
                </div>

                <?php if (empty($services)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">⚕️</div>
                        <h3>No services found</h3>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:40px;">No.</th>
                                    <th style="width:36px;"></th>
                                    <th>Name of Services</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $i => $svc): ?>
                                    <tr>
                                        <td style="color:var(--text-light);"><?= $i+1 ?></td>
                                        <td class="svc-icon-cell">
                                            <?= $icons[$svc['name']] ?? '🐾' ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($svc['name']) ?></strong></td>
                                        <td style="font-size:12px;color:var(--text-light);max-width:220px;">
                                            <?= htmlspecialchars(substr($svc['description'],0,80))
                                                . (strlen($svc['description'])>80 ? '...' : '') ?>
                                        </td>
                                        <td class="price-range">
                                            <?php
                                            if ($svc['name'] === 'Surgery') {
                                                echo 'It Depends';
                                            } elseif ($svc['price_max'] > $svc['price_min']) {
                                                echo '₱'.number_format($svc['price_min'],0)
                                                   .' – ₱'.number_format($svc['price_max'],0);
                                            } else {
                                                echo '₱'.number_format($svc['price_min'],0).' up';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span style="font-size:11px;background:var(--teal-light);
                                                         color:var(--teal-dark);padding:2px 8px;
                                                         border-radius:20px;font-weight:700;">
                                                <?= ucfirst($svc['category'] ?? '—') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?toggle=<?= $svc['id'] ?>"
                                               class="svc-status-toggle svc-<?= $svc['status'] ?>"
                                               title="Click to toggle status">
                                                <?= $svc['status'] === 'available' ? '✅ Available' : '❌ Not Available' ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <button class="btn btn-gray btn-sm"
                                                        onclick='openEditSvc(<?= json_encode($svc) ?>)'>
                                                    Edit
                                                </button>
                                                <button type="button" class="btn btn-red btn-sm"
                                                        onclick="openDeleteSvcModal(<?= $svc['id'] ?>)">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     ADD SERVICE MODAL
════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="addServiceModal">
    <div class="modal" style="max-width:560px;">
        <button class="modal-close"
                onclick="document.getElementById('addServiceModal').classList.remove('open')">×</button>
        <h3 class="modal-title">+ Add New Service</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_service">
            <div class="form-group">
                <label>Service Name *</label>
                <select name="name" id="add_svc_name_select"
                        onchange="onAddNameChange(this.value)" required
                        style="margin-bottom:6px;">
                    <option value="">— Choose a service —</option>
                    <?php foreach (array_keys($SERVICE_PRICE_MATRIX) as $sn): ?>
                        <option value="<?= $sn ?>"><?= $sn ?></option>
                    <?php endforeach; ?>
                    <option value="__custom__">+ Custom service name…</option>
                </select>
                <input type="text" name="name" id="add_svc_name_custom"
                       placeholder="Type custom service name"
                       style="display:none;">
            </div>

            <!-- ╔══ AUTOFILL PANEL ══╗ -->
            <div class="price-autofill-panel" id="add_autofill_panel" style="display:none;">
                <h4>🐾 Auto-Price by Pet Size & Weight</h4>
                <div class="size-weight-grid">
                    <div>
                        <label>Pet Size</label>
                        <div class="size-btn-group">
                            <button type="button" class="size-btn" id="add_sz_small"
                                    onclick="selectSize('add','small')">🐱 Small</button>
                            <button type="button" class="size-btn" id="add_sz_medium"
                                    onclick="selectSize('add','medium')">🐕 Medium</button>
                            <button type="button" class="size-btn" id="add_sz_large"
                                    onclick="selectSize('add','large')">🐻 Large</button>
                        </div>
                        <div class="size-labels-row" style="margin-top:5px;">
                            <div class="size-label-chip">≤5 kg</div>
                            <div class="size-label-chip">5–20 kg</div>
                            <div class="size-label-chip">&gt;20 kg</div>
                        </div>
                    </div>
                    <div>
                        <label>Exact Weight (kg)</label>
                        <div class="weight-input-wrap">
                            <input type="number" id="add_weight" min="0.1" max="120"
                                   step="0.1" placeholder="e.g. 3.5"
                                   oninput="computePrice('add')">
                            <span class="weight-unit">kg</span>
                        </div>
                        <div class="weight-hint" id="add_weight_hint"></div>
                    </div>
                </div>
                <div class="autofill-result" id="add_autofill_result" style="display:none;">
                    <div>
                        <div class="autofill-result-label">Suggested Price Range</div>
                        <div class="autofill-result-price" id="add_suggested_price">—</div>
                    </div>
                    <button type="button" class="autofill-apply-btn"
                            onclick="applyAutofill('add')"
                            id="add_apply_btn">
                        ✅ Apply to Price Fields
                    </button>
                </div>
                <div style="margin-top:8px;">
                    <button type="button" class="show-table-toggle"
                            onclick="togglePriceTable('add')">
                        📋 View full pricing table
                    </button>
                    <div id="add_price_table_wrap" style="display:none;margin-top:8px;">
                        <table class="pricing-table-preview" id="add_price_table">
                            <thead>
                                <tr>
                                    <th>Size</th>
                                    <th>Weight Range</th>
                                    <th>Min Price</th>
                                    <th>Max Price</th>
                                </tr>
                            </thead>
                            <tbody id="add_price_table_body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- ╚══════════════════╝ -->

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"
                          placeholder="Brief description of the service..."
                          style="resize:vertical;"></textarea>
            </div>
            <div class="price-row">
                <div class="form-group">
                    <label>Minimum Price (₱)</label>
                    <input type="number" name="price_min" id="add_price_min"
                           step="0.01" placeholder="0.00" min="0">
                </div>
                <div class="form-group">
                    <label>Maximum Price (₱)</label>
                    <input type="number" name="price_max" id="add_price_max"
                           step="0.01" placeholder="0.00" min="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="veterinary">Veterinary</option>
                        <option value="grooming">Grooming</option>
                        <option value="home">Home Service</option>
                        <option value="laboratory">Laboratory</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="available">Available</option>
                        <option value="not_available">Not Available</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Service Image (optional)</label>
                <input type="file" name="image" accept="image/*"
                       onchange="previewImage(this,'addSvcPreview')">
                <img id="addSvcPreview"
                     style="display:none;margin-top:8px;width:80px;
                            height:60px;object-fit:cover;border-radius:6px;">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('addServiceModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">Add Service</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     EDIT SERVICE MODAL
════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="editServiceModal">
    <div class="modal" style="max-width:560px;">
        <button class="modal-close"
                onclick="document.getElementById('editServiceModal').classList.remove('open')">×</button>
        <h3 class="modal-title">✏️ Edit Service</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action"     value="edit_service">
            <input type="hidden" name="service_id" id="edit_svc_id">
            <div class="form-group">
                <label>Service Name *</label>
                <input type="text" name="name" id="edit_svc_name"
                       oninput="onEditNameChange(this.value)" required>
            </div>

            <!-- ╔══ AUTOFILL PANEL ══╗ -->
            <div class="price-autofill-panel" id="edit_autofill_panel">
                <h4>🐾 Auto-Price by Pet Size & Weight</h4>
                <div class="size-weight-grid">
                    <div>
                        <label>Pet Size</label>
                        <div class="size-btn-group">
                            <button type="button" class="size-btn" id="edit_sz_small"
                                    onclick="selectSize('edit','small')">🐱 Small</button>
                            <button type="button" class="size-btn" id="edit_sz_medium"
                                    onclick="selectSize('edit','medium')">🐕 Medium</button>
                            <button type="button" class="size-btn" id="edit_sz_large"
                                    onclick="selectSize('edit','large')">🐻 Large</button>
                        </div>
                        <div class="size-labels-row" style="margin-top:5px;">
                            <div class="size-label-chip">≤5 kg</div>
                            <div class="size-label-chip">5–20 kg</div>
                            <div class="size-label-chip">&gt;20 kg</div>
                        </div>
                    </div>
                    <div>
                        <label>Exact Weight (kg)</label>
                        <div class="weight-input-wrap">
                            <input type="number" id="edit_weight" min="0.1" max="120"
                                   step="0.1" placeholder="e.g. 3.5"
                                   oninput="computePrice('edit')">
                            <span class="weight-unit">kg</span>
                        </div>
                        <div class="weight-hint" id="edit_weight_hint"></div>
                    </div>
                </div>
                <div class="autofill-result" id="edit_autofill_result" style="display:none;">
                    <div>
                        <div class="autofill-result-label">Suggested Price Range</div>
                        <div class="autofill-result-price" id="edit_suggested_price">—</div>
                    </div>
                    <button type="button" class="autofill-apply-btn"
                            onclick="applyAutofill('edit')"
                            id="edit_apply_btn">
                        ✅ Apply to Price Fields
                    </button>
                </div>
                <div id="edit_no_matrix" class="no-matrix-note" style="display:none;">
                    ℹ️ No preset pricing matrix for this service. Enter prices manually.
                </div>
                <div style="margin-top:8px;">
                    <button type="button" class="show-table-toggle"
                            id="edit_table_toggle"
                            onclick="togglePriceTable('edit')"
                            style="display:none;">
                        📋 View full pricing table
                    </button>
                    <div id="edit_price_table_wrap" style="display:none;margin-top:8px;">
                        <table class="pricing-table-preview">
                            <thead>
                                <tr>
                                    <th>Size</th>
                                    <th>Weight Range</th>
                                    <th>Min Price</th>
                                    <th>Max Price</th>
                                </tr>
                            </thead>
                            <tbody id="edit_price_table_body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- ╚══════════════════╝ -->

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" id="edit_svc_desc"
                          style="resize:vertical;"></textarea>
            </div>
            <div class="price-row">
                <div class="form-group">
                    <label>Minimum Price (₱)</label>
                    <input type="number" name="price_min" id="edit_svc_pmin"
                           step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label>Maximum Price (₱)</label>
                    <input type="number" name="price_max" id="edit_svc_pmax"
                           step="0.01" min="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="edit_svc_cat">
                        <option value="veterinary">Veterinary</option>
                        <option value="grooming">Grooming</option>
                        <option value="home">Home Service</option>
                        <option value="laboratory">Laboratory</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_svc_status">
                        <option value="available">Available</option>
                        <option value="not_available">Not Available</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Update Image (optional)</label>
                <input type="file" name="image" accept="image/*">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('editServiceModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Service Confirmation Modal -->
<div id="deleteSvcModal"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);
            backdrop-filter:blur(3px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px 28px;max-width:400px;width:90%;
                box-shadow:0 20px 60px rgba(0,0,0,0.25);animation:slideUp 0.25s ease;text-align:center;">
        <div style="width:64px;height:64px;border-radius:50%;background:#fff5f5;border:2px solid #fee2e2;
                    display:flex;align-items:center;justify-content:center;font-size:28px;
                    margin:0 auto 16px;">🗑️</div>
        <div style="font-family:var(--font-head);font-size:18px;font-weight:800;color:#1a1a2e;
                    margin-bottom:8px;">Delete Service?</div>
        <div style="font-size:13px;color:#6b7280;margin-bottom:24px;line-height:1.6;">
            This action is <strong style="color:#ef4444;">permanent</strong> and cannot be undone.<br>
            This service will be removed from the system.
        </div>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="closeDeleteSvcModal()"
                    style="padding:10px 24px;border-radius:8px;border:1.5px solid #d1d5db;
                           background:#fff;color:#374151;font-weight:700;font-size:13px;
                           cursor:pointer;font-family:var(--font-main);">
                Cancel
            </button>
            <a id="deleteSvcConfirmBtn" href="#"
               style="padding:10px 24px;border-radius:8px;border:none;
                      background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;
                      font-weight:700;font-size:13px;cursor:pointer;text-decoration:none;
                      display:inline-flex;align-items:center;gap:6px;
                      box-shadow:0 4px 12px rgba(239,68,68,0.35);font-family:var(--font-main);">
                🗑️ Yes, Delete
            </a>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// ══════════════════════════════════════════════════════════════
//  PRICE MATRIX — mirrors PHP $SERVICE_PRICE_MATRIX
//  Structure: { serviceName: { size: [ [minKg, maxKg, priceMin, priceMax], … ] } }
// ══════════════════════════════════════════════════════════════
const PRICE_MATRIX = {
    'CheckUp': {
        small:  [[0,5,150,250],[5,10,200,300]],
        medium: [[5,20,250,400],[20,30,300,500]],
        large:  [[20,40,400,600],[40,120,500,800]]
    },
    'Vaccination': {
        small:  [[0,5,300,500],[5,10,400,600]],
        medium: [[5,20,500,700],[20,30,600,900]],
        large:  [[20,40,700,1000],[40,120,900,1300]]
    },
    'Deworming': {
        small:  [[0,5,150,200],[5,10,200,300]],
        medium: [[5,20,250,350],[20,30,300,450]],
        large:  [[20,40,350,500],[40,120,500,700]]
    },
    'Grooming': {
        small:  [[0,5,250,400],[5,10,300,500]],
        medium: [[5,20,450,650],[20,30,550,800]],
        large:  [[20,40,700,1000],[40,120,900,1400]]
    },
    'Confinement': {
        small:  [[0,5,500,800],[5,10,700,1000]],
        medium: [[5,20,900,1300],[20,30,1100,1600]],
        large:  [[20,40,1400,2000],[40,120,1800,2600]]
    },
    'Treatment': {
        small:  [[0,5,300,600],[5,10,400,800]],
        medium: [[5,20,600,1000],[20,30,800,1300]],
        large:  [[20,40,1000,1600],[40,120,1300,2000]]
    },
    'Laboratory': {
        small:  [[0,5,400,700],[5,10,500,900]],
        medium: [[5,20,700,1100],[20,30,900,1400]],
        large:  [[20,40,1100,1600],[40,120,1400,2000]]
    },
    'Home Service': {
        small:  [[0,5,500,800],[5,10,600,1000]],
        medium: [[5,20,800,1200],[20,30,1000,1500]],
        large:  [[20,40,1200,1800],[40,120,1500,2200]]
    },
    'Surgery': {
        small:  [[0,5,2000,4000],[5,10,3000,6000]],
        medium: [[5,20,5000,9000],[20,30,7000,12000]],
        large:  [[20,40,10000,16000],[40,120,14000,22000]]
    }
};

// Size → auto-select default weight bracket hint
const SIZE_HINTS = {
    small:  'Typical: cat or small dog (0 – 10 kg)',
    medium: 'Typical: medium dog (5 – 30 kg)',
    large:  'Typical: large/giant breed (20 kg+)'
};

// Per-modal state
const state = {
    add:  { service: '', size: '', weight: null, pmin: null, pmax: null },
    edit: { service: '', size: '', weight: null, pmin: null, pmax: null }
};

// ── Open add modal ────────────────────────────────────────────
function openAddModal() {
    resetAutofill('add');
    document.getElementById('addServiceModal').classList.add('open');
}

// ── When service name changes in ADD modal ────────────────────
function onAddNameChange(val) {
    const custom = document.getElementById('add_svc_name_custom');
    if (val === '__custom__') {
        custom.style.display = 'block';
        custom.required = true;
        // hide autofill — no matrix for custom
        document.getElementById('add_autofill_panel').style.display = 'none';
    } else {
        custom.style.display = 'none';
        custom.required = false;
        state.add.service = val;
        updateAutofillPanel('add', val);
    }
}

// ── When service name changes in EDIT modal ───────────────────
function onEditNameChange(val) {
    state.edit.service = val;
    updateAutofillPanel('edit', val);
}

// ── Show/hide autofill panel and populate pricing table ───────
function updateAutofillPanel(prefix, serviceName) {
    const panel    = document.getElementById(prefix + '_autofill_panel');
    const noMatrix = document.getElementById(prefix + '_no_matrix');
    const toggle   = document.getElementById(prefix + '_table_toggle');
    const matrix   = PRICE_MATRIX[serviceName];

    if (prefix === 'add') {
        panel.style.display = matrix ? 'block' : 'none';
    } else {
        // edit panel always visible
        if (noMatrix) noMatrix.style.display = matrix ? 'none' : 'block';
        // hide/show size buttons
        document.getElementById('edit_sz_small').style.display  = matrix ? '' : 'none';
        document.getElementById('edit_sz_medium').style.display = matrix ? '' : 'none';
        document.getElementById('edit_sz_large').style.display  = matrix ? '' : 'none';
        if (toggle) toggle.style.display = matrix ? 'inline' : 'none';
    }

    if (matrix) {
        buildPriceTable(prefix, serviceName);
    }
    // reset computed price
    resetComputedPrice(prefix);
}

// ── Build pricing reference table ────────────────────────────
function buildPriceTable(prefix, serviceName) {
    const tbody = document.getElementById(prefix + '_price_table_body');
    if (!tbody) return;
    const matrix = PRICE_MATRIX[serviceName];
    if (!matrix) { tbody.innerHTML = ''; return; }
    const sizeLabels = { small: '🐱 Small', medium: '🐕 Medium', large: '🐻 Large' };
    let rows = '';
    ['small','medium','large'].forEach(sz => {
        if (!matrix[sz]) return;
        matrix[sz].forEach(([minKg, maxKg, pMin, pMax], idx) => {
            const rowClass = idx === 0 ? '' : '';
            rows += `<tr>
                ${idx === 0 ? `<td rowspan="${matrix[sz].length}" style="font-weight:700;">${sizeLabels[sz]}</td>` : ''}
                <td>${minKg} – ${maxKg === 120 ? '120+' : maxKg} kg</td>
                <td style="color:#059669;font-weight:700;">₱${pMin.toLocaleString()}</td>
                <td style="color:#10b981;font-weight:700;">₱${pMax.toLocaleString()}</td>
            </tr>`;
        });
    });
    tbody.innerHTML = rows;
}

// ── Select pet size ───────────────────────────────────────────
function selectSize(prefix, size) {
    state[prefix].size = size;
    ['small','medium','large'].forEach(s => {
        const btn = document.getElementById(prefix + '_sz_' + s);
        if (btn) btn.classList.toggle('active', s === size);
    });
    const hint = document.getElementById(prefix + '_weight_hint');
    if (hint) hint.textContent = SIZE_HINTS[size] || '';
    computePrice(prefix);
}

// ── Compute price from size + weight ─────────────────────────
function computePrice(prefix) {
    const weightEl = document.getElementById(prefix + '_weight');
    const weight   = parseFloat(weightEl ? weightEl.value : 0);
    const size     = state[prefix].size;
    const service  = state[prefix].service;
    const resultEl = document.getElementById(prefix + '_autofill_result');
    const priceEl  = document.getElementById(prefix + '_suggested_price');

    if (!service || !size || isNaN(weight) || weight <= 0) {
        if (resultEl) resultEl.style.display = 'none';
        return;
    }

    const matrix = PRICE_MATRIX[service];
    if (!matrix || !matrix[size]) {
        if (resultEl) resultEl.style.display = 'none';
        return;
    }

    const ranges = matrix[size];
    let matched = null;
    for (const [minKg, maxKg, pMin, pMax] of ranges) {
        if (weight >= minKg && weight <= maxKg) {
            matched = [pMin, pMax];
            break;
        }
    }
    // fallback: last range if weight exceeds all
    if (!matched) matched = ranges[ranges.length - 1].slice(2);

    state[prefix].pmin = matched[0];
    state[prefix].pmax = matched[1];

    if (priceEl) {
        priceEl.textContent = '₱' + matched[0].toLocaleString()
                            + ' – ₱' + matched[1].toLocaleString();
    }
    if (resultEl) resultEl.style.display = 'flex';
}

// ── Apply autofill to price inputs ───────────────────────────
function applyAutofill(prefix) {
    const pmin = state[prefix].pmin;
    const pmax = state[prefix].pmax;
    if (pmin === null || pmax === null) return;

    const minField = document.getElementById(
        prefix === 'add' ? 'add_price_min' : 'edit_svc_pmin'
    );
    const maxField = document.getElementById(
        prefix === 'add' ? 'add_price_max' : 'edit_svc_pmax'
    );
    if (minField) minField.value = pmin;
    if (maxField) maxField.value = pmax;

    // Visual feedback on apply button
    const btn = document.getElementById(prefix + '_apply_btn');
    if (btn) {
        const orig = btn.textContent;
        btn.textContent = '✔ Applied!';
        btn.style.background = 'linear-gradient(135deg,#059669,#047857)';
        setTimeout(() => {
            btn.textContent = orig;
            btn.style.background = '';
        }, 1800);
    }
}

// ── Toggle pricing table ──────────────────────────────────────
function togglePriceTable(prefix) {
    const wrap = document.getElementById(prefix + '_price_table_wrap');
    if (!wrap) return;
    const visible = wrap.style.display !== 'none';
    wrap.style.display = visible ? 'none' : 'block';
}

// ── Reset autofill state ──────────────────────────────────────
function resetAutofill(prefix) {
    state[prefix] = { service: '', size: '', weight: null, pmin: null, pmax: null };
    resetComputedPrice(prefix);
    ['small','medium','large'].forEach(s => {
        const btn = document.getElementById(prefix + '_sz_' + s);
        if (btn) btn.classList.remove('active');
    });
    const w = document.getElementById(prefix + '_weight');
    if (w) w.value = '';
    const hint = document.getElementById(prefix + '_weight_hint');
    if (hint) hint.textContent = '';
}

function resetComputedPrice(prefix) {
    const resultEl = document.getElementById(prefix + '_autofill_result');
    if (resultEl) resultEl.style.display = 'none';
}

// ── Open edit modal ───────────────────────────────────────────
function openEditSvc(svc) {
    document.getElementById('edit_svc_id').value     = svc.id;
    document.getElementById('edit_svc_name').value   = svc.name;
    document.getElementById('edit_svc_desc').value   = svc.description || '';
    document.getElementById('edit_svc_pmin').value   = svc.price_min;
    document.getElementById('edit_svc_pmax').value   = svc.price_max;
    document.getElementById('edit_svc_cat').value    = svc.category   || 'veterinary';
    document.getElementById('edit_svc_status').value = svc.status;

    // Reset autofill state and update panel for this service
    resetAutofill('edit');
    state.edit.service = svc.name;
    updateAutofillPanel('edit', svc.name);

    document.getElementById('editServiceModal').classList.add('open');
}

// ── Delete modal ──────────────────────────────────────────────
function openDeleteSvcModal(svcId) {
    document.getElementById('deleteSvcConfirmBtn').href = '?delete=' + svcId;
    document.getElementById('deleteSvcModal').style.display = 'flex';
}
function closeDeleteSvcModal() {
    document.getElementById('deleteSvcModal').style.display = 'none';
}
document.getElementById('deleteSvcModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteSvcModal();
});

// ── Image preview ─────────────────────────────────────────────
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Close modals on outside click ────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });
});
</script>
</body>
</html>