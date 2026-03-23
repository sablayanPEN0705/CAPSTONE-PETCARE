<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/products.php
// Purpose: Browse products + purchase (creates transaction)
// ============================================================
require_once '../includes/auth.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id = (int)$_SESSION['user_id'];
$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// ── Handle Purchase (creates transaction + items) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'purchase') {
    $items     = $_POST['items']   ?? [];   // array of product IDs
    $qtys      = $_POST['qtys']    ?? [];   // matching quantities
    $pet_id    = (int)($_POST['pet_id'] ?? 0);

    if (empty($items)) {
        redirect('products.php?error=Please select at least one product.');
    }

    $total = 0;
    $line_items = [];

    foreach ($items as $idx => $prod_id) {
        $prod_id = (int)$prod_id;
        $qty     = max(1, (int)($qtys[$idx] ?? 1));
        $prod    = getRow($conn, "SELECT * FROM products WHERE id=$prod_id AND status != 'out_of_stock'");
        if (!$prod) continue;
        $subtotal     = $prod['price'] * $qty;
        $total       += $subtotal;
        $line_items[] = [
            'name'  => $prod['name'] . ($qty > 1 ? " x$qty" : ''),
            'price' => $subtotal,
            'id'    => $prod_id,
            'qty'   => $qty,
        ];
    }

    if (empty($line_items)) {
        redirect('products.php?error=No valid products selected.');
    }

    $today   = date('Y-m-d');
    $pet_sql = $pet_id ? $pet_id : 'NULL';

    // Insert transaction
    $sql = "INSERT INTO transactions (user_id, pet_id, total_amount, status, transaction_date, notes)
            VALUES ($user_id, $pet_sql, $total, 'pending', '$today', 'Product purchase by user')";

    if (mysqli_query($conn, $sql)) {
        $txn_id = mysqli_insert_id($conn);

        // Insert each line item
        foreach ($line_items as $li) {
            $iname  = sanitize($conn, $li['name']);
            $iprice = (float)$li['price'];
            mysqli_query($conn,
                "INSERT INTO transaction_items (transaction_id, item_type, item_name, price)
                 VALUES ($txn_id, 'product', '$iname', $iprice)");

            // Deduct stock
            $pid = (int)$li['id'];
            $qty = (int)$li['qty'];
            mysqli_query($conn,
                "UPDATE products SET quantity = GREATEST(0, quantity - $qty),
                 status = CASE
                     WHEN quantity - $qty <= 0 THEN 'out_of_stock'
                     WHEN quantity - $qty <= 5 THEN 'low_stock'
                     ELSE 'in_stock'
                 END
                 WHERE id=$pid");
        }

        // Notify admin
        $admin = getRow($conn, "SELECT id FROM users WHERE role='admin' LIMIT 1");
        if ($admin) {
            $uname = sanitize($conn, $_SESSION['user_name']);
            $msg   = "Product purchase by $uname — Total: " . formatPeso($total) . " (pending payment).";
            mysqli_query($conn,
                "INSERT INTO notifications (user_id, title, message, type)
                 VALUES ({$admin['id']}, 'New Product Purchase', '$msg', 'billing')");
        }

        redirect('products.php?success=Order placed successfully! Total: ' . urlencode(formatPeso($total)) . '. Please pay at the clinic or during your next visit.');
    } else {
        redirect('products.php?error=Purchase failed: ' . mysqli_error($conn));
    }
}

// ── Filters ──────────────────────────────────────────────────
$category = sanitize($conn, $_GET['category'] ?? 'all');
$search   = sanitize($conn, $_GET['search']   ?? '');

$where = "status != 'out_of_stock'";
if ($category === 'pet_care')     $where .= " AND category='pet_care'";
if ($category === 'pet_supplies') $where .= " AND category='pet_supplies'";
if ($search) $where .= " AND name LIKE '%$search%'";

$products = getRows($conn, "SELECT * FROM products WHERE $where ORDER BY name ASC");
$my_pets  = getRows($conn, "SELECT id, name FROM pets WHERE user_id=$user_id ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .shop-header {
            background: rgba(255,255,255,0.88);
            border-radius: var(--radius);
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .shop-header .shop-icon { font-size: 28px; }
        .shop-header-text h2 { font-family:var(--font-head);font-size:20px;font-weight:800;color:var(--text-dark);line-height:1.1; }
        .shop-header-text p  { font-size:13px;color:var(--text-light); }

        .cat-pills { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px; }
        .cat-pill {
            padding:7px 18px;border-radius:var(--radius-lg);font-size:12px;font-weight:700;
            border:2px solid var(--border);background:rgba(255,255,255,0.8);color:var(--text-mid);
            cursor:pointer;transition:var(--transition);text-decoration:none;
        }
        .cat-pill:hover,.cat-pill.active { background:var(--teal);color:#fff;border-color:var(--teal); }

        .product-grid-full {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
            gap: 16px;
        }
        .product-card-full {
            background:rgba(255,255,255,0.92);border-radius:var(--radius);overflow:hidden;
            box-shadow:var(--shadow);transition:var(--transition);display:flex;flex-direction:column;
            position:relative;
        }
        .product-card-full:hover { transform:translateY(-4px);box-shadow:var(--shadow-md);border:1.5px solid var(--teal-light); }

        .product-img-wrap { height:140px;background:#f4fffe;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative; }
        .product-img-wrap img { width:100%;height:100%;object-fit:contain;padding:12px; }
        .product-img-wrap .no-img { font-size:52px; }
        .product-stock-badge { position:absolute;top:8px;right:8px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px; }
        .badge-low { background:#fef3c7;color:#92400e; }

        .product-card-body { padding:12px 14px;flex:1;display:flex;flex-direction:column;gap:4px; }
        .product-name     { font-weight:700;font-size:13px;color:var(--text-dark);line-height:1.3; }
        .product-category { font-size:11px;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px; }
        .product-price    { font-family:var(--font-head);font-weight:800;font-size:16px;color:var(--teal-dark);margin-top:4px; }

        /* Cart selected state */
        .product-card-full.in-bill { border:2px solid var(--teal);box-shadow:0 0 0 3px rgba(0,188,212,0.15); }
        .bill-check {
            display:none;position:absolute;top:8px;left:8px;
            background:var(--teal);color:#fff;border-radius:50%;
            width:22px;height:22px;font-size:13px;
            align-items:center;justify-content:center;font-weight:700;
        }
        .product-card-full.in-bill .bill-check { display:flex; }

        .add-to-bill-btn {
            margin:0 14px 12px;padding:7px;border-radius:var(--radius-sm);
            background:var(--teal);color:#fff;border:none;font-weight:700;
            font-size:12px;cursor:pointer;transition:var(--transition);width:calc(100% - 28px);
        }
        .add-to-bill-btn:hover { background:var(--teal-dark); }
        .add-to-bill-btn.in-bill-btn { background:#ef4444; }

        /* Cart bar */
        .bill-bar {
            position:fixed;bottom:0;left:0;right:0;z-index:200;
            background:var(--blue-header);color:#fff;
            padding:14px 24px;display:none;
            align-items:center;justify-content:space-between;
            box-shadow:0 -4px 20px rgba(0,0,0,0.2);
            gap:12px;flex-wrap:wrap;
        }
        .bill-bar.visible { display:flex; }
        .bill-bar-left { font-size:14px;font-weight:700; }
        .bill-bar-right { display:flex;gap:10px;align-items:center; }

        .toolbar-row { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px; }
        .empty-products { text-align:center;padding:48px; }
        .empty-products .ep-icon { font-size:56px;margin-bottom:12px; }
        .empty-products h3 { font-size:18px;font-weight:700;color:var(--text-mid); }

        @media(max-width:500px) { .product-grid-full { grid-template-columns:1fr 1fr; } }
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
                Products
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔</a>
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body" style="padding-bottom:80px;">

            <?php if($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="shop-header">
                <div class="shop-icon">🛍️</div>
                <div class="shop-header-text">
                    <h2>Petcare Products</h2>
                    <p>Tap a product to add to bill. Payment is made in person at the clinic.</p>
                </div>
            </div>

            <div class="toolbar-row">
                <div class="cat-pills">
                    <a href="?category=all"          class="cat-pill <?= $category==='all'         ?'active':'' ?>">See All</a>
                    <a href="?category=pet_care"     class="cat-pill <?= $category==='pet_care'    ?'active':'' ?>">🐾 Pet Care</a>
                    <a href="?category=pet_supplies" class="cat-pill <?= $category==='pet_supplies'?'active':'' ?>">🏠 Pet Supplies</a>
                </div>
                <form method="GET" style="display:flex;gap:8px;align-items:center;">
                    <input type="hidden" name="category" value="<?= $category ?>">
                    <div class="search-box">
                        <span>🔍</span>
                        <input type="text" name="search" placeholder="Search products..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </form>
            </div>

            <p style="font-weight:700;font-size:13px;color:var(--text-mid);margin-bottom:14px;">All Products</p>

            <?php if(empty($products)): ?>
                <div class="empty-products">
                    <div class="ep-icon">📦</div>
                    <h3>No products found<?= $search ? " for \"$search\"" : '' ?>.</h3>
                    <a href="products.php" class="btn btn-teal btn-sm" style="margin-top:12px;">View All</a>
                </div>
            <?php else: ?>
                <div class="product-grid-full" id="productGrid">
                    <?php foreach($products as $prod):
                        $cat_lbl = $prod['category']==='pet_care' ? 'Pet Care' : 'Pet Supplies';
                        $img_src = $prod['image'] ? "../assets/css/images/products/{$prod['image']}" : null;
                    ?>
                    <div class="product-card-full" id="pcard-<?= $prod['id'] ?>">
                        <div class="bill-check">✓</div>
                        <div class="product-img-wrap">
                            <?php if($img_src): ?>
                                <img src="<?= htmlspecialchars($img_src) ?>"
                                     alt="<?= htmlspecialchars($prod['name']) ?>"
                                     onerror="this.parentElement.innerHTML='<div class=no-img>🐾</div>'">
                            <?php else: ?>
                                <div class="no-img">🐾</div>
                            <?php endif; ?>
                            <?php if($prod['status']==='low_stock'): ?>
                                <span class="product-stock-badge badge-low">Low Stock</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-card-body">
                            <div class="product-category"><?= $cat_lbl ?></div>
                            <div class="product-name"><?= htmlspecialchars($prod['name']) ?></div>
                            <div class="product-price"><?= formatPeso($prod['price']) ?></div>
                        </div>
                        <button class="add-to-bill-btn"
                                id="billbtn-<?= $prod['id'] ?>"
                                onclick="toggleBill(<?= $prod['id'] ?>, '<?= htmlspecialchars($prod['name'],ENT_QUOTES) ?>', <?= $prod['price'] ?>)">
                            🧾 Add to Bill
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Bill Bar -->
<div class="bill-bar" id="billBar">
    <div class="bill-bar-left">
        🧾 <span id="billCount">0</span> item(s) — Total: <strong id="billTotal">₱0.00</strong>
    </div>
    <div class="bill-bar-right">
        <button class="btn btn-gray btn-sm" onclick="clearBill()">Clear</button>
        <button class="btn btn-teal" onclick="buildBill(); document.getElementById('billModal').classList.add('open');">
            📋 View Bill →
        </button>
    </div>
</div>

<!-- Bill Modal -->
<div class="modal-overlay" id="billModal">
    <div class="modal" style="max-width:480px;">
        <button class="modal-close"
                onclick="document.getElementById('billModal').classList.remove('open')">×</button>
        <h3 class="modal-title">🧾 Confirm Bill Items</h3>

        <div id="billItemsList" style="margin-bottom:16px;"></div>

        <div style="background:#f0fffe;border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;font-weight:800;font-size:15px;">
                <span>Total:</span>
                <span id="billTotalModal" style="color:var(--teal-dark);"></span>
            </div>
            <p style="font-size:12px;color:var(--text-light);margin-top:6px;">
                💡 This bill will be recorded as <strong>pending</strong>. Payment is collected <strong>face to face</strong> at the clinic.
            </p>
        </div>

        <form method="POST" id="checkoutForm">
            <input type="hidden" name="action" value="purchase">
            <div id="billHiddenInputs"></div>

            <?php if(!empty($my_pets)): ?>
            <div class="form-group" style="margin-bottom:16px;">
                <label style="font-size:13px;font-weight:700;">For which pet? (optional)</label>
                <select name="pet_id">
                    <option value="">— No specific pet —</option>
                    <?php foreach($my_pets as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="modal-actions">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('billModal').classList.remove('open')">
                    Cancel
                </button>
                <button type="submit" class="btn btn-teal">✅ Confirm Bill</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// ── Cart state ────────────────────────────────────────────────
let cart = {}; // bill items // { prod_id: { name, price, qty } }

function toggleBill(id, name, price) {
    if (cart[id]) {
        delete cart[id];
        document.getElementById('pcard-' + id).classList.remove('in-bill');
        document.getElementById('billbtn-' + id).classList.remove('in-bill-btn');
        document.getElementById('billbtn-' + id).textContent = '🧾 Add to Bill';
    } else {
        cart[id] = { name, price, qty: 1 };
        document.getElementById('pcard-' + id).classList.add('in-bill');
        document.getElementById('billbtn-' + id).classList.add('in-bill-btn');
        document.getElementById('billbtn-' + id).textContent = '✕ Remove';
    }
    updateBillBar();
}

function updateBillBar() {
    const ids  = Object.keys(cart);
    const total = ids.reduce((s, id) => s + cart[id].price * cart[id].qty, 0);
    document.getElementById('billCount').textContent = ids.length;
    document.getElementById('billTotal').textContent = '₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    document.getElementById('billBar').classList.toggle('visible', ids.length > 0);
}

function clearBill() {
    Object.keys(cart).forEach(id => {
        document.getElementById('pcard-' + id)?.classList.remove('in-bill');
        document.getElementById('billbtn-' + id)?.classList.remove('in-bill-btn');
        const btn = document.getElementById('billbtn-' + id);
        if (btn) btn.textContent = '🧾 Add to Bill';
    });
    cart = {};
    updateBillBar();
}

// Build checkout modal when opened
// Close billModal on outside click
document.getElementById('billModal').addEventListener('click', function(e){
    if (e.target === this) this.classList.remove('open');
});

function buildBill() {
    const ids = Object.keys(cart);
    if (!ids.length) return;

    let listHtml  = '';
    let inputHtml = '';
    let total     = 0;

    ids.forEach((id, idx) => {
        const item    = cart[id];
        const subtotal = item.price * item.qty;
        total += subtotal;
        listHtml += `
            <div style="display:flex;justify-content:space-between;align-items:center;
                        padding:8px 0;border-bottom:1px solid var(--border);font-size:13px;">
                <div style="flex:1;">
                    <strong>${item.name}</strong>
                    <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                        <label style="font-size:11px;color:var(--text-light);">Qty:</label>
                        <input type="number" min="1" value="${item.qty}"
                               style="width:50px;padding:3px 6px;border:1px solid var(--border);
                                      border-radius:6px;font-size:12px;"
                               onchange="updateQty(${id}, this.value)">
                    </div>
                </div>
                <div style="font-weight:800;color:var(--teal-dark);margin-left:12px;">
                    ₱${subtotal.toFixed(2)}
                </div>
            </div>`;
        inputHtml += `<input type="hidden" name="items[]" value="${id}">`;
        inputHtml += `<input type="hidden" name="qtys[]"  id="qty-input-${id}" value="${item.qty}">`;
    });

    document.getElementById('billItemsList').innerHTML = listHtml;
    document.getElementById('billHiddenInputs').innerHTML = inputHtml;
    document.getElementById('billTotalModal').textContent =
        '₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function updateQty(id, val) {
    const qty = Math.max(1, parseInt(val) || 1);
    cart[id].qty = qty;
    const inp = document.getElementById('qty-input-' + id);
    if (inp) inp.value = qty;
    updateBillBar();
    // Refresh total in modal
    const total = Object.keys(cart).reduce((s, i) => s + cart[i].price * cart[i].qty, 0);
    const el = document.getElementById('billTotalModal');
    if (el) el.textContent = '₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
</script>
</body>
</html>