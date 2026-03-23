<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/inventory.php
// Purpose: Manage product inventory — add, edit, delete, restock
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_product') {
        $name     = sanitize($conn, $_POST['name']         ?? '');
        $category = sanitize($conn, $_POST['category']     ?? 'pet_care');
        $price    = (float)($_POST['price']                ?? 0);
        $qty      = (int)($_POST['quantity']               ?? 0);
        $expiry   = sanitize($conn, $_POST['expiry_date']  ?? '');

        if (empty($name)) redirect('inventory.php?error=Product name is required.');

        $status = $qty <= 0 ? 'out_of_stock' : ($qty <= 5 ? 'low_stock' : 'in_stock');

        $image = '';
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $dir = '../assets/css/images/products/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fn  = 'prod_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fn);
                $image = $fn;
            }
        }

        $exp_sql = $expiry ? "'$expiry'" : 'NULL';
        mysqli_query($conn,
            "INSERT INTO products (name,category,price,quantity,expiry_date,status,image)
             VALUES ('$name','$category',$price,$qty,$exp_sql,'$status','$image')");
        redirect('inventory.php?success=Product added!');
    }

    if ($action === 'edit_product') {
        $pid      = (int)$_POST['product_id'];
        $name     = sanitize($conn, $_POST['name']        ?? '');
        $category = sanitize($conn, $_POST['category']    ?? 'pet_care');
        $price    = (float)($_POST['price']               ?? 0);
        $qty      = (int)($_POST['quantity']              ?? 0);
        $expiry   = sanitize($conn, $_POST['expiry_date'] ?? '');

        $status    = $qty <= 0 ? 'out_of_stock' : ($qty <= 5 ? 'low_stock' : 'in_stock');
        $img_sql   = '';
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $dir = '../assets/css/images/products/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fn  = 'prod_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fn);
                $img_sql = ", image='$fn'";
            }
        }

        $exp_sql = $expiry ? "'$expiry'" : 'NULL';
        mysqli_query($conn,
            "UPDATE products SET name='$name',category='$category',price=$price,
             quantity=$qty,expiry_date=$exp_sql,status='$status' $img_sql
             WHERE id=$pid");
        redirect('inventory.php?success=Product updated!');
    }

    if ($action === 'restock') {
        $pid = (int)$_POST['product_id'];
        $add = (int)$_POST['add_quantity'];
        $p   = getRow($conn, "SELECT quantity FROM products WHERE id=$pid");
        if ($p) {
            $nq  = max(0, $p['quantity'] + $add);
            $st  = $nq <= 0 ? 'out_of_stock' : ($nq <= 5 ? 'low_stock' : 'in_stock');
            mysqli_query($conn,
                "UPDATE products SET quantity=$nq,status='$st' WHERE id=$pid");
        }
        redirect('inventory.php?success=Stock updated!');
    }
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    mysqli_query($conn, "DELETE FROM products WHERE id=" . (int)$_GET['delete']);
    redirect('inventory.php?success=Product deleted.');
}

// ── Stats ─────────────────────────────────────────────────────
$total_prods  = countRows($conn, 'products');
$low_count    = countRows($conn, 'products', "status='low_stock'");
$out_count    = countRows($conn, 'products', "status='out_of_stock'");
$exp_count    = countRows($conn, 'products',
    "expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE()
     AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)");
$total_val    = getRow($conn,
    "SELECT COALESCE(SUM(price*quantity),0) AS v FROM products")['v'] ?? 0;

// ── Filters ───────────────────────────────────────────────────
$fc  = sanitize($conn, $_GET['fc']  ?? '');
$fs  = sanitize($conn, $_GET['fs']  ?? '');
$srch= sanitize($conn, $_GET['srch']?? '');

$where = '1=1';
if ($fc)   $where .= " AND category='$fc'";
if ($fs)   $where .= " AND status='$fs'";
if ($srch) $where .= " AND name LIKE '%$srch%'";

$products = getRows($conn,
    "SELECT * FROM products WHERE $where ORDER BY name ASC");

$prod_icons = [
    'Groom and Bloom Shampoo'       =>'🧴',
    'Activated Charcoal Pet Shampoo'=>'🧴',
    'Dermovet'                      =>'💊',
    'Pedigree'                      =>'🦴',
    'Collar'                        =>'🔗',
    'Toys'                          =>'🧸',
    'Cage for Puppy and Kittens'    =>'🏠',
    'Large Bowl'                    =>'🥣',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory — Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .inv-table-section {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }
        .inv-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        .inv-header h3 {
            font-family: var(--font-head);
            font-size: 17px;
            font-weight: 700;
        }
        .filter-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 14px;
        }
        .fpill {
            padding: 5px 14px;
            border-radius: var(--radius-lg);
            font-size: 12px;
            font-weight: 700;
            border: 1.5px solid var(--border);
            background: rgba(255,255,255,0.8);
            color: var(--text-mid);
            transition: var(--transition);
            text-decoration: none;
        }
        .fpill:hover, .fpill.on {
            background: var(--teal);
            color: #fff;
            border-color: var(--teal);
        }
        .stock-bar-wrap {
            width: 60px; height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
            margin: 0 4px;
        }
        .stock-bar-fill { height: 100%; border-radius: 3px; }
        .s-in  { background: #10b981; }
        .s-low { background: #f59e0b; }
        .s-out { background: #ef4444; }
        .exp-warn { color:#ef4444; font-weight:700; }
        .exp-soon { color:#f59e0b; font-weight:700; }
        .prod-thumb {
            width: 40px; height: 40px;
            border-radius: 6px;
            object-fit: cover;
            background: #f4fffe;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .restock-form {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .rq-input {
            width: 52px;
            padding: 3px 6px;
            border: 1.5px solid var(--border);
            border-radius: 4px;
            font-size: 12px;
            text-align: center;
            outline: none;
        }
        .rq-input:focus { border-color: var(--teal); }
        .alert-banners {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .ab {
            flex: 1;
            min-width: 200px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            font-size: 13px;
            font-weight: 700;
        }
        .ab-low { background:#fef3c7;color:#92400e;border-left:4px solid #f59e0b; }
        .ab-out { background:#fee2e2;color:#991b1b;border-left:4px solid #ef4444; }
        .ab-exp { background:#fff7ed;color:#c2410c;border-left:4px solid #f97316; }
        .ab-icon { font-size:22px; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_admin.php'; ?>
    <div class="main-content">

        <div class="topbar">
            <div class="topbar-title" style="display:flex; align-items:center; gap:10px;">
                <img src="../assets/css/images/pets/logo.png" alt="Logo"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.6);"
                     onerror="this.style.display='none';">
                PRODUCT INVENTORY
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

            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stat-cards"
                 style="grid-template-columns:repeat(4,1fr);margin-bottom:16px;">
                <div class="stat-card">
                    <div class="stat-label">Total Product</div>
                    <div class="stat-value"><?= $total_prods ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Low Stock Items</div>
                    <div class="stat-value" style="color:#f59e0b;"><?= $low_count ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">About to Expire</div>
                    <div class="stat-value" style="color:#f97316;"><?= $exp_count ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Value</div>
                    <div class="stat-value" style="font-size:22px;">
                        ₱<?= number_format($total_val, 0) ?>
                    </div>
                </div>
            </div>

            <!-- Alert Banners -->
            <?php if ($low_count || $out_count || $exp_count): ?>
            <div class="alert-banners">
                <?php if ($low_count): ?>
                <div class="ab ab-low">
                    <span class="ab-icon">⚠️</span>
                    <div>
                        <strong><?= $low_count ?> product(s) running low</strong><br>
                        <span style="font-weight:500;font-size:12px;">Please restock soon</span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($out_count): ?>
                <div class="ab ab-out">
                    <span class="ab-icon">❌</span>
                    <div>
                        <strong><?= $out_count ?> product(s) out of stock</strong><br>
                        <span style="font-weight:500;font-size:12px;">Restock immediately</span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($exp_count): ?>
                <div class="ab ab-exp">
                    <span class="ab-icon">🗓️</span>
                    <div>
                        <strong><?= $exp_count ?> product(s) expiring within 90 days</strong><br>
                        <span style="font-weight:500;font-size:12px;">Check expiry dates</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Table -->
            <div class="inv-table-section">
                <div class="inv-header">
                    <h3>Products</h3>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <button onclick="exportExcel()"
                                style="background:#1d6f42;color:#fff;border:none;cursor:pointer;
                                       display:inline-flex;align-items:center;gap:5px;
                                       padding:7px 14px;border-radius:var(--radius-sm);
                                       font-size:13px;font-weight:700;"
                                onmouseover="this.style.background='#155232'"
                                onmouseout="this.style.background='#1d6f42'">
                            📊 Export Excel
                        </button>
                        <button onclick="exportPDF()"
                                style="background:#c0392b;color:#fff;border:none;cursor:pointer;
                                       display:inline-flex;align-items:center;gap:5px;
                                       padding:7px 14px;border-radius:var(--radius-sm);
                                       font-size:13px;font-weight:700;"
                                onmouseover="this.style.background='#922b21'"
                                onmouseout="this.style.background='#c0392b'">
                            📄 Export PDF
                        </button>
                        <button class="btn btn-teal btn-sm"
                                onclick="document.getElementById('addProdModal').classList.add('open')">
                            + Add New Product
                        </button>
                    </div>
                </div>

                <div class="filter-row">
                    <a href="inventory.php"
                       class="fpill <?= !$fc && !$fs ? 'on':'' ?>">All</a>
                    <a href="?fc=pet_care"
                       class="fpill <?= $fc==='pet_care' ? 'on':'' ?>">🐾 Pet Care</a>
                    <a href="?fc=pet_supplies"
                       class="fpill <?= $fc==='pet_supplies' ? 'on':'' ?>">🏠 Pet Supplies</a>
                    <a href="?fs=low_stock"
                       class="fpill <?= $fs==='low_stock' ? 'on':'' ?>"
                       style="border-color:#f59e0b;color:#92400e;">⚠️ Low Stock</a>
                    <a href="?fs=out_of_stock"
                       class="fpill <?= $fs==='out_of_stock' ? 'on':'' ?>"
                       style="border-color:#ef4444;color:#991b1b;">❌ Out of Stock</a>
                    <form method="GET" style="margin-left:auto;display:flex;gap:6px;">
                        <div class="search-box">
                            <span>🔍</span>
                            <input type="text" name="srch"
                                   placeholder="Search..."
                                   value="<?= htmlspecialchars($srch) ?>">
                        </div>
                    </form>
                </div>

                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <h3>No products found</h3>
                    </div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table id="inventoryTable">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Expiry</th>
                                <th>Stock</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Image</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $i => $p):
                                $today  = date('Y-m-d');
                                $ecls   = '';
                                $elabel = $p['expiry_date']
                                    ? date('M. Y', strtotime($p['expiry_date']))
                                    : '—';
                                if ($p['expiry_date']) {
                                    $dl = (strtotime($p['expiry_date']) - time()) / 86400;
                                    if ($dl < 0)      $ecls = 'exp-warn';
                                    elseif ($dl <= 90) $ecls = 'exp-soon';
                                }
                                $max  = 50;
                                $pct  = min(100, round(($p['quantity']/$max)*100));
                                $bcls = $p['status']==='in_stock' ? 's-in'
                                      : ($p['status']==='low_stock' ? 's-low' : 's-out');
                                $icon = $prod_icons[$p['name']] ?? '🐾';
                            ?>
                            <tr>
                                <td style="color:var(--text-light);"><?= $i+1 ?></td>
                                <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                <td>
                                    <span style="font-size:11px;background:var(--teal-light);
                                                 color:var(--teal-dark);padding:2px 8px;
                                                 border-radius:20px;font-weight:700;">
                                        <?= $p['category']==='pet_care' ? 'Pet Care':'Pet Supplies' ?>
                                    </span>
                                </td>
                                <td class="<?= $ecls ?>"><?= $elabel ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:4px;">
                                        <div class="stock-bar-wrap">
                                            <div class="stock-bar-fill <?= $bcls ?>"
                                                 style="width:<?= $pct ?>%"></div>
                                        </div>
                                        <span style="font-weight:700;font-size:13px;">
                                            <?= $p['quantity'] ?>
                                        </span>
                                        <form method="POST" class="restock-form">
                                            <input type="hidden" name="action"     value="restock">
                                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                            <input type="number" name="add_quantity"
                                                   class="rq-input" min="1" placeholder="+qty">
                                            <button type="submit"
                                                    class="btn btn-teal btn-sm"
                                                    style="padding:3px 8px;font-size:11px;"
                                                    title="Add stock">+</button>
                                        </form>
                                    </div>
                                </td>
                                <td style="font-weight:700;color:var(--teal-dark);">
                                    ₱<?= number_format($p['price'],2) ?>
                                </td>
                                <td><?= statusBadge($p['status']) ?></td>
                                <td>
                                    <div class="prod-thumb">
                                        <?php if ($p['image']): ?>
                                            <img src="../assets/css/images/products/<?= htmlspecialchars($p['image']) ?>"
                                                 alt="<?= htmlspecialchars($p['name']) ?>"
                                                 style="width:40px;height:40px;object-fit:cover;border-radius:6px;"
                                                 onerror="this.parentElement.textContent='<?= $icon ?>'">
                                        <?php else: ?>
                                            <?= $icon ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;">
                                        <button class="btn btn-gray btn-sm"
                                                onclick='openEditProd(<?= json_encode($p) ?>)'>
                                            Edit
                                        </button>
                                        <a href="?delete=<?= $p['id'] ?>"
                                           onclick="return confirm('Delete this product?')"
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

<!-- Add Product Modal -->
<div class="modal-overlay" id="addProdModal">
    <div class="modal" style="max-width:500px;">
        <button class="modal-close" onclick="document.getElementById('addProdModal').classList.remove('open')">×</button>
        <h3 class="modal-title">📦 Add New Product</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_product">
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="name"
                       placeholder="e.g. Groom and Bloom Shampoo" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="pet_care">Pet Care</option>
                        <option value="pet_supplies">Pet Supplies</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price (₱) *</label>
                    <input type="number" name="price" step="0.01" min="0"
                           placeholder="0.00" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Quantity *</label>
                    <input type="number" name="quantity" min="0"
                           placeholder="0" required>
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date">
                </div>
            </div>
            <div class="form-group">
                <label>Product Image (optional)</label>
                <input type="file" name="image" accept="image/*"
                       onchange="previewImage(this,'addProdPrev')">
                <img id="addProdPrev"
                     style="display:none;margin-top:8px;width:80px;height:60px;
                            object-fit:cover;border-radius:6px;">
            </div>
            <div style="background:#e0f7fa;border-radius:var(--radius-sm);
                        padding:10px 14px;font-size:12px;color:var(--teal-dark);
                        margin-bottom:14px;">
                💡 Status auto-set: 0 = Out of Stock | 1–5 = Low Stock | 6+ = In Stock
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('addProdModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">Add Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal-overlay" id="editProdModal">
    <div class="modal" style="max-width:500px;">
        <button class="modal-close" onclick="document.getElementById('editProdModal').classList.remove('open')">×</button>
        <h3 class="modal-title">✏️ Edit Product</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action"     value="edit_product">
            <input type="hidden" name="product_id" id="ep_id">
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="name" id="ep_name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="ep_cat">
                        <option value="pet_care">Pet Care</option>
                        <option value="pet_supplies">Pet Supplies</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price (₱) *</label>
                    <input type="number" name="price" id="ep_price"
                           step="0.01" min="0" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" id="ep_qty" min="0">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" id="ep_expiry">
                </div>
            </div>
            <div class="form-group">
                <label>Update Image (optional)</label>
                <input type="file" name="image" accept="image/*">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('editProdModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="../assets/js/main.js"></script>
<script>
function openEditProd(p) {
    document.getElementById('ep_id').value     = p.id;
    document.getElementById('ep_name').value   = p.name;
    document.getElementById('ep_cat').value    = p.category;
    document.getElementById('ep_price').value  = p.price;
    document.getElementById('ep_qty').value    = p.quantity;
    document.getElementById('ep_expiry').value = p.expiry_date || '';
    document.getElementById('editProdModal').classList.add('open');
}

// Get clean text from a cell — for Stock col, only grab the qty number
function getCellText(td, headerText) {
    if (headerText === 'Stock') {
        // Only grab the first text node (the quantity number), ignore form inputs
        for (let node of td.childNodes) {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                return node.textContent.trim();
            }
        }
        // Fallback: grab first span or strong text
        const span = td.querySelector('span[style*="font-weight"]');
        if (span) return span.innerText.trim();
        return td.innerText.split('\n')[0].trim();
    }
    return td.innerText.trim();
}

function getTableData() {
    const table = document.getElementById('inventoryTable');
    if (!table) return null;

    const skipCols = [];
    const headers  = [];
    table.querySelectorAll('thead th').forEach((th, i) => {
        const t = th.innerText.trim();
        if (t === 'Image' || t === 'Action') {
            skipCols.push(i);
        } else {
            headers.push(t);
        }
    });

    const rows = [];
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        const cells = tr.querySelectorAll('td');
        cells.forEach((td, i) => {
            if (!skipCols.includes(i)) {
                const headerText = headers[row.length];
                row.push(getCellText(td, headerText));
            }
        });
        rows.push(row);
    });

    return { headers, rows };
}

function exportExcel() {
    document.getElementById('exportType').textContent = 'Excel';
    document.getElementById('exportIcon').textContent = '📊';
    document.getElementById('exportConfirmModal').classList.add('open');
    pendingExport = 'excel';
}

function doExportExcel() {
    const data = getTableData();
    if (!data || data.rows.length === 0) { alert('No data to export.'); return; }

    const ws = XLSX.utils.aoa_to_sheet([data.headers, ...data.rows]);
    ws['!cols'] = data.headers.map(() => ({ wch: 20 }));

    data.headers.forEach((_, i) => {
        const cell = XLSX.utils.encode_cell({ r: 0, c: i });
        if (ws[cell]) ws[cell].s = { font: { bold: true } };
    });

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Products');
    XLSX.writeFile(wb, 'Ligao_Petcare_Products.xlsx');
}

function exportPDF() {
    document.getElementById('exportType').textContent = 'PDF';
    document.getElementById('exportIcon').textContent = '📄';
    document.getElementById('exportConfirmModal').classList.add('open');
    pendingExport = 'pdf';
}

function doExportPDF() {
    const data = getTableData();
    if (!data || data.rows.length === 0) { alert('No data to export.'); return; }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

    doc.setFontSize(16);
    doc.setFont('helvetica', 'bold');
    doc.text('Ligao Petcare & Veterinary Clinic', 40, 40);
    doc.setFontSize(12);
    doc.setFont('helvetica', 'normal');
    doc.text('Product Inventory Report', 40, 58);
    doc.setFontSize(9);
    doc.setTextColor(120);
    doc.text('Generated: ' + new Date().toLocaleString(), 40, 72);
    doc.setTextColor(0);

    doc.autoTable({
        startY: 88,
        head: [data.headers],
        body: data.rows,
        styles: { fontSize: 8, cellPadding: 5 },
        headStyles: { fillColor: [0, 150, 136], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [240, 255, 253] },
        margin: { left: 40, right: 40 },
        columnStyles: {
            5: { halign: 'right' },  // Price column — right-aligned
        },
    });

    doc.save('Ligao_Petcare_Products.pdf');
}

let pendingExport = null;

function confirmExport() {
    document.getElementById('exportConfirmModal').classList.remove('open');
    if (pendingExport === 'excel') doExportExcel();
    if (pendingExport === 'pdf')   doExportPDF();
    pendingExport = null;
}

function cancelExport() {
    document.getElementById('exportConfirmModal').classList.remove('open');
    pendingExport = null;
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });
});
</script>

<!-- Export Confirmation Modal -->
<div class="modal-overlay" id="exportConfirmModal">
    <div class="modal" style="max-width:380px;text-align:center;">
        <div style="font-size:52px;margin-bottom:12px;" id="exportIcon">📊</div>
        <h3 class="modal-title">Export as <span id="exportType"></span>?</h3>
        <p style="font-size:13px;color:var(--text-mid);margin-bottom:24px;">
            This will download the current product inventory list as a
            <strong><span id="exportType"></span></strong> file.
            Make sure the data is up to date before exporting.
        </p>
        <div class="modal-actions" style="justify-content:center;">
            <button class="btn btn-gray" onclick="cancelExport()">Cancel</button>
            <button class="btn btn-teal" onclick="confirmExport()">✅ Yes, Export</button>
        </div>
    </div>
</div>

</body>
</html>