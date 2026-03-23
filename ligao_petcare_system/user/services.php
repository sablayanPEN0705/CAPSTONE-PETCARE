<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/services.php
// Purpose: Display all available clinic services
// ============================================================
require_once '../includes/auth.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id = (int)$_SESSION['user_id'];

// ── Fetch all services ───────────────────────────────────────
$search   = sanitize($conn, $_GET['search'] ?? '');
$where    = "status='available'";
if ($search) $where .= " AND name LIKE '%$search%'";
$services = getRows($conn, "SELECT * FROM services WHERE $where ORDER BY name ASC");

// ── Service icons map ────────────────────────────────────────
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
    <title>Services — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .services-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .services-header h2 {
            font-family: var(--font-head);
            font-size: 22px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .service-card-full {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 22px 18px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .service-card-full:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--teal);
        }

        .svc-icon-wrap {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal-light), #e0f7fa);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin-bottom: 4px;
            box-shadow: 0 2px 8px rgba(0,188,212,0.2);
        }

        .svc-name {
            font-family: var(--font-head);
            font-weight: 800;
            font-size: 15px;
            color: var(--text-dark);
        }

        .svc-desc {
            font-size: 12px;
            color: var(--text-light);
            line-height: 1.6;
            text-align: center;
        }

        .svc-price {
            font-weight: 800;
            color: var(--teal-dark);
            font-size: 14px;
            background: var(--teal-light);
            padding: 4px 14px;
            border-radius: var(--radius-lg);
            margin-top: 4px;
        }

        .svc-book-btn {
            margin-top: 6px;
            width: 100%;
        }

        /* Service detail modal */
        .modal-svc-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal-light), #b2ebf2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            margin: 0 auto 16px;
        }

        .modal-svc-detail {
            font-size: 13px;
            color: var(--text-mid);
            line-height: 1.8;
            margin-bottom: 12px;
        }

        .modal-svc-detail strong {
            color: var(--text-dark);
        }

        /* Search with mic icon */
        .search-mic {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 8px 16px;
            min-width: 260px;
        }
        .search-mic input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 13px;
            flex: 1;
        }
        .search-mic .mic-icon {
            color: var(--text-light);
            cursor: pointer;
            font-size: 16px;
        }

        .no-results {
            text-align: center;
            padding: 48px;
            color: var(--text-light);
        }
        .no-results .nr-icon { font-size: 48px; margin-bottom: 12px; }
        .no-results h3 { font-size: 18px; font-weight: 700; color: var(--text-mid); }

        @media(max-width:600px) {
            .service-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_user.php'; ?>

    <div class="main-content">

        <div class="topbar">
    <div class="topbar-title" style="display:flex; align-items:center; gap:10px;">
        <img src="../assets/css/images/pets/logo.png" alt="Logo"
             style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.6);"
             onerror="this.style.display='none';">
        Pet Services Dashboard
    </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔</a>
                <a href="settings.php"      class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <!-- Header Row -->
            <div class="services-header">
                <h2>Services</h2>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <!-- Search -->
                    <form method="GET" style="margin:0;">
                        <div class="search-mic">
                            <span>🔍</span>
                            <input type="text" name="search"
                                   placeholder="Search Services"
                                   value="<?= htmlspecialchars($search) ?>">
                            <span class="mic-icon">🎙️</span>
                        </div>
                    </form>
                    <a href="?search=" class="btn btn-gray btn-sm">See All</a>
                </div>
            </div>

            <!-- Services Grid -->
            <?php if (empty($services)): ?>
                <div class="no-results">
                    <div class="nr-icon">🔍</div>
                    <h3>No services found for "<?= htmlspecialchars($search) ?>"</h3>
                    <a href="services.php" class="btn btn-teal btn-sm" style="margin-top:12px;">
                        View All Services
                    </a>
                </div>
            <?php else: ?>
                <div class="service-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));">
                    <?php foreach ($services as $svc): ?>
                        <?php
                        $icon     = $icons[$svc['name']] ?? '🐾';
                        $price_lbl = $svc['price_max'] > $svc['price_min']
                            ? '₱'.number_format($svc['price_min'],0).' – ₱'.number_format($svc['price_max'],0)
                            : '₱'.number_format($svc['price_min'],0);
                        if ($svc['name'] === 'Surgery') $price_lbl = 'It Depends';
                        ?>
                        <div class="service-card-full"
                             onclick="openServiceModal(
                                 '<?= htmlspecialchars($svc['name'], ENT_QUOTES) ?>',
                                 '<?= htmlspecialchars($svc['description'], ENT_QUOTES) ?>',
                                 '<?= $icon ?>',
                                 '<?= $price_lbl ?>',
                                 <?= $svc['id'] ?>
                             )">
                            <div class="svc-icon-wrap"><?= $icon ?></div>
                            <div class="svc-name"><?= strtoupper(htmlspecialchars($svc['name'])) ?></div>
                            <div class="svc-desc"><?= htmlspecialchars($svc['description']) ?></div>
                            <div class="svc-price"><?= $price_lbl ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div><!-- /page-body -->
    </div>
</div>

<!-- Service Detail Modal -->
<div class="modal-overlay" id="serviceDetailModal">
    <div class="modal" style="max-width:440px;text-align:center;">
        <button class="modal-close" onclick="closeModal('serviceDetailModal')">×</button>

        <div class="modal-svc-icon" id="modal-svc-icon"></div>
        <h3 class="modal-title" id="modal-svc-name" style="margin-bottom:8px;"></h3>
        <div class="modal-svc-detail" id="modal-svc-desc"></div>

        <div style="margin-bottom:16px;">
            <span class="svc-price" id="modal-svc-price"></span>
        </div>

        <div class="modal-actions" style="justify-content:center;">
            <button class="btn btn-gray btn-sm"
                    onclick="closeModal('serviceDetailModal')">Close</button>
            <a href="appointments.php" class="btn btn-teal btn-sm"
               id="modal-book-btn">📅 Book Appointment</a>
        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function openServiceModal(name, desc, icon, price, svcId) {
    document.getElementById('modal-svc-icon').textContent = icon;
    document.getElementById('modal-svc-name').textContent = name;
    document.getElementById('modal-svc-desc').textContent = desc;
    document.getElementById('modal-svc-price').textContent = price;
    openModal('serviceDetailModal');
}
</script>
</body>
</html>