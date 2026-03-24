<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/services.php
// Purpose: Manage clinic services — add, edit, delete
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

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

        // Handle image upload
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

// ── Handle Delete ─────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM services WHERE id=$did");
    redirect('services.php?success=Service deleted.');
}

// ── Handle Toggle Status ──────────────────────────────────────
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

// ── Service icons ─────────────────────────────────────────────
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
        .svc-status-toggle {
            cursor: pointer;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            border: none;
            transition: var(--transition);
        }
        .svc-available {
            background: #d1fae5;
            color: #065f46;
        }
        .svc-not_available {
            background: #fee2e2;
            color: #991b1b;
        }

        .price-range {
            font-weight: 700;
            font-size: 13px;
            color: var(--teal-dark);
        }

        .svc-icon-cell {
            font-size: 22px;
            text-align: center;
        }

        .action-btns {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        /* Add/Edit form rows */
        .price-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .filter-bar-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .filter-pills {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .filter-pill {
            padding: 5px 14px;
            border-radius: var(--radius-lg);
            font-size: 12px;
            font-weight: 700;
            border: 1.5px solid var(--border);
            background: rgba(255,255,255,0.8);
            color: var(--text-mid);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        .filter-pill:hover,
        .filter-pill.active {
            background: var(--teal);
            color: #fff;
            border-color: var(--teal);
        }

        .table-section {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .table-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .table-section-header h3 {
            font-family: var(--font-head);
            font-size: 17px;
            font-weight: 700;
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
                            onclick="document.getElementById('addServiceModal').classList.add('open')">
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
                                        <td style="font-size:12px;color:var(--text-light);
                                                    max-width:220px;">
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
                                                <a href="?delete=<?= $svc['id'] ?>"
                                                   onclick="return confirm('Delete this service?')"
                                                   class="btn btn-red btn-sm">Delete</a>
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

<!-- Add Service Modal -->
<div class="modal-overlay" id="addServiceModal">
    <div class="modal" style="max-width:520px;">
        <button class="modal-close" onclick="document.getElementById('addServiceModal').classList.remove('open')">×</button>
        <h3 class="modal-title">+ Add New Service</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_service">
            <div class="form-group">
                <label>Service Name *</label>
                <input type="text" name="name" placeholder="e.g. CheckUp" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"
                          placeholder="Brief description of the service..."
                          style="resize:vertical;"></textarea>
            </div>
            <div class="price-row">
                <div class="form-group">
                    <label>Minimum Price (₱)</label>
                    <input type="number" name="price_min" step="0.01"
                           placeholder="0.00" min="0">
                </div>
                <div class="form-group">
                    <label>Maximum Price (₱)</label>
                    <input type="number" name="price_max" step="0.01"
                           placeholder="0.00" min="0">
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

<!-- Edit Service Modal -->
<div class="modal-overlay" id="editServiceModal">
    <div class="modal" style="max-width:520px;">
        <button class="modal-close" onclick="document.getElementById('editServiceModal').classList.remove('open')">×</button>
        <h3 class="modal-title">✏️ Edit Service</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action"     value="edit_service">
            <input type="hidden" name="service_id" id="edit_svc_id">
            <div class="form-group">
                <label>Service Name *</label>
                <input type="text" name="name" id="edit_svc_name" required>
            </div>
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

<script src="../assets/js/main.js"></script>
<script>
// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });
});

// Image preview helper (in case main.js doesn't have it)
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function openEditSvc(svc) {
    document.getElementById('edit_svc_id').value     = svc.id;
    document.getElementById('edit_svc_name').value   = svc.name;
    document.getElementById('edit_svc_desc').value   = svc.description || '';
    document.getElementById('edit_svc_pmin').value   = svc.price_min;
    document.getElementById('edit_svc_pmax').value   = svc.price_max;
    document.getElementById('edit_svc_cat').value    = svc.category   || 'veterinary';
    document.getElementById('edit_svc_status').value = svc.status;
    document.getElementById('editServiceModal').classList.add('open');
}
</script>
</body>
</html>