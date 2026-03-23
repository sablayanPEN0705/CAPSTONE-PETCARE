<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/client_records.php
// Purpose: View/manage all pet owners + their pets
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// ── Handle add new pet to client ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_pet') {
    $uid     = (int)($_POST['user_id']  ?? 0);
    $name    = sanitize($conn, $_POST['pet_name']  ?? '');
    $species = sanitize($conn, $_POST['species']   ?? '');
    $breed   = sanitize($conn, $_POST['breed']     ?? '');
    $age     = sanitize($conn, $_POST['age']       ?? '');
    $gender  = sanitize($conn, $_POST['gender']    ?? '');
    $weight  = sanitize($conn, $_POST['weight']    ?? '');
    $color   = sanitize($conn, $_POST['color']     ?? '');

    if ($uid && $name && $species && $gender) {
        mysqli_query($conn,
            "INSERT INTO pets (user_id,name,species,breed,age,gender,weight,color)
             VALUES ($uid,'$name','$species','$breed','$age','$gender',
             " . ($weight ? $weight : 'NULL') . ",'$color')");
        redirect("client_records.php?success=Pet added to client.&view=$uid");
    } else {
        redirect("client_records.php?error=Please fill required fields.&view=$uid");
    }
}

// ── Handle edit client ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_client') {
    $uid     = (int)($_POST['user_id'] ?? 0);
    $name    = sanitize($conn, $_POST['name']    ?? '');
    $address = sanitize($conn, $_POST['address'] ?? '');
    $contact = sanitize($conn, $_POST['contact'] ?? '');
    $email   = sanitize($conn, $_POST['email']   ?? '');
    if ($uid && $name) {
        mysqli_query($conn,
            "UPDATE users SET name='$name', address='$address',
             contact_no='$contact', email='$email' WHERE id=$uid");
        redirect("client_records.php?success=Client updated.&view=$uid");
    }
}

// ── Handle delete client ─────────────────────────────────────
if (isset($_GET['delete_client']) && is_numeric($_GET['delete_client'])) {
    $did = (int)$_GET['delete_client'];
    mysqli_query($conn, "DELETE FROM users WHERE id=$did AND role='user'");
    redirect('client_records.php?success=Client removed.');
}

// ── Stats ────────────────────────────────────────────────────
$total_clients  = countRows($conn, 'users', "role='user'");
$active_clients = countRows($conn, 'users', "role='user' AND status='active'");
$new_clients    = countRows($conn, 'users',
    "role='user' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");

// ── View single client profile ────────────────────────────────
$view_id     = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_client = null;
$client_pets = [];

if ($view_id) {
    $view_client = getRow($conn, "SELECT * FROM users WHERE id=$view_id AND role='user'");
    if (!$view_client) redirect('client_records.php');
    $client_pets = getRows($conn, "SELECT * FROM pets WHERE user_id=$view_id ORDER BY name ASC");
}

// ── Fetch client list ─────────────────────────────────────────
$search = sanitize($conn, $_GET['search'] ?? '');
$where  = "role='user'";
if ($search) $where .= " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR contact_no LIKE '%$search%')";

$clients = getRows($conn,
    "SELECT u.*,
            (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.user_id=u.id) AS last_visit,
            (SELECT COUNT(*) FROM pets p WHERE p.user_id=u.id) AS pet_count
     FROM users u
     WHERE $where
     ORDER BY u.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Records — Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }

        .profile-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
        }

        .profile-card h3 {
            font-family: var(--font-head);
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .profile-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal), #0097a7);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #fff;
            font-weight: 700;
            margin: 0 auto 16px;
        }

        .profile-info-row {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
            font-size: 13px;
            align-items: flex-start;
        }
        .pir-label {
            width: 100px;
            font-weight: 700;
            color: var(--text-dark);
            flex-shrink: 0;
        }
        .pir-value { color: var(--text-mid); }

        .pets-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .pet-mini-card {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius-sm);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        .pet-mini-card:hover { transform: translateY(-2px); }

        .pet-mini-img {
            height: 100px;
            background: var(--teal-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            overflow: hidden;
        }
        .pet-mini-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .pet-mini-body {
            padding: 10px;
        }
        .pet-mini-name {
            font-weight: 800;
            font-size: 14px;
            margin-bottom: 4px;
        }
        .pet-mini-detail {
            font-size: 11px;
            color: var(--text-light);
        }
        .pet-mini-tags {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .pet-tag {
            background: var(--teal-light);
            color: var(--teal-dark);
            padding: 1px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }
        .pet-tag.gender {
            background: #fce4ec;
            color: #c62828;
        }

        .add-pet-mini {
            border: 2px dashed var(--border);
            border-radius: var(--radius-sm);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            cursor: pointer;
            transition: var(--transition);
            min-height: 140px;
            background: rgba(255,255,255,0.5);
        }
        .add-pet-mini:hover {
            border-color: var(--teal);
            background: rgba(0,188,212,0.05);
        }

        /* Status dot */
        .status-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 4px;
        }
        .dot-active   { background: #10b981; }
        .dot-inactive { background: #9ca3af; }
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
                CLIENT RECORDS
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

            <?php if ($view_client): ?>
            <!-- ══ SINGLE CLIENT PROFILE ═════════════════════ -->
            <div style="margin-bottom:14px;">
                <a href="client_records.php" class="btn btn-gray btn-sm">← Back to Clients</a>
            </div>

            <div class="profile-layout">
                <!-- Basic Info -->
                <div class="profile-card">
                    <h3>
                        Basic Info
                       
                    </h3>

                    <div class="profile-avatar">
                        <?= strtoupper(substr($view_client['name'],0,2)) ?>
                    </div>

                    <div class="profile-info-row">
                        <span class="pir-label">Name</span>
                        <span class="pir-value"><?= htmlspecialchars($view_client['name']) ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="pir-label">Address</span>
                        <span class="pir-value"><?= htmlspecialchars($view_client['address'] ?? '—') ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="pir-label">Email</span>
                        <span class="pir-value"><?= htmlspecialchars($view_client['email']) ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="pir-label">Contact No.</span>
                        <span class="pir-value"><?= htmlspecialchars($view_client['contact_no'] ?? '—') ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="pir-label">Member Since</span>
                        <span class="pir-value"><?= formatDate($view_client['created_at']) ?></span>
                    </div>
                    <div class="profile-info-row">
                        <span class="pir-label">Status</span>
                        <span class="pir-value">
                            <span class="status-dot dot-<?= $view_client['status'] ?>"></span>
                            <?= ucfirst($view_client['status']) ?>
                        </span>
                    </div>

                    <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
                        <a href="appointments.php?search_appt=<?= urlencode($view_client['name']) ?>&view=list"
                           class="btn btn-teal btn-sm">📅 View Appointments</a>
                        <a href="messages.php?contact=<?= $view_client['id'] ?>"
                           class="btn btn-gray btn-sm">💬 Message</a>
                    </div>
                </div>

                <!-- Pets -->
                <div class="profile-card">
                    <h3>
                        My Pets
                        <span style="font-size:12px;color:var(--text-light);font-weight:600;">
                            <?= count($client_pets) ?> pet(s)
                        </span>
                    </h3>

                    <div class="pets-grid">
                        <?php foreach ($client_pets as $pet): ?>
                            <div class="pet-mini-card">
                                <div class="pet-mini-img">
                                    <?php if ($pet['photo']): ?>
                                        <img src="../assets/css/images/pets/<?= htmlspecialchars($pet['photo']) ?>"
                                             alt="<?= htmlspecialchars($pet['name']) ?>">
                                    <?php else: ?>
                                        <?= $pet['species']==='cat' ? '🐱' : '🐶' ?>
                                    <?php endif; ?>
                                </div>
                                <div class="pet-mini-body">
                                    <div class="pet-mini-name">
                                        <?= htmlspecialchars($pet['name']) ?>
                                    </div>
                                    <div class="pet-mini-detail">
                                        <?= htmlspecialchars($pet['age'] ?? '?') ?> yrs old
                                    </div>
                                    <div class="pet-mini-tags">
                                        <span class="pet-tag">
                                            <?= htmlspecialchars($pet['breed'] ?: ucfirst($pet['species'])) ?>
                                        </span>
                                        <span class="pet-tag gender">
                                            <?= ucfirst($pet['gender']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Add Pet -->
                        <div class="add-pet-mini"
                             onclick="document.getElementById('addPetModal').classList.add('open')">
                            <div style="font-size:28px;color:var(--teal);margin-bottom:6px;">⊕</div>
                            <div style="font-weight:700;font-size:13px;">+Add New Pet</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Client Modal -->
            <div class="modal-overlay" id="editClientModal">
                <div class="modal">
                    <button class="modal-close" onclick="document.getElementById('editClientModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">Edit Client Info</h3>
                    <form method="POST">
                        <input type="hidden" name="action"  value="edit_client">
                        <input type="hidden" name="user_id" value="<?= $view_client['id'] ?>">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name"
                                   value="<?= htmlspecialchars($view_client['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="address"
                                   value="<?= htmlspecialchars($view_client['address'] ?? '') ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact No.</label>
                                <input type="text" name="contact"
                                       value="<?= htmlspecialchars($view_client['contact_no'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email"
                                       value="<?= htmlspecialchars($view_client['email']) ?>" required>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray"
                                    onclick="document.getElementById('editClientModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Pet Modal -->
            <div class="modal-overlay" id="addPetModal">
                <div class="modal">
                    <button class="modal-close" onclick="document.getElementById('addPetModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">Add New Pet</h3>
                    <form method="POST">
                        <input type="hidden" name="action"  value="add_pet">
                        <input type="hidden" name="user_id" value="<?= $view_client['id'] ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Pet Name *</label>
                                <input type="text" name="pet_name" required>
                            </div>
                            <div class="form-group">
                                <label>Species *</label>
                                <select name="species" required>
                                    <option value="">Select</option>
                                    <option value="dog">Dog</option>
                                    <option value="cat">Cat</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Breed</label>
                                <input type="text" name="breed">
                            </div>
                            <div class="form-group">
                                <label>Age (yrs)</label>
                                <input type="text" name="age">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Gender *</label>
                                <select name="gender" required>
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Weight (kg)</label>
                                <input type="number" step="0.01" name="weight">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Color</label>
                            <input type="text" name="color">
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray"
                                    onclick="document.getElementById('addPetModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Add Pet</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- ══ CLIENT LIST ════════════════════════════════ -->

            <!-- Stats -->
            <div class="stat-cards" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
                <div class="stat-card">
                    <div class="stat-label">Total Client</div>
                    <div class="stat-value"><?= $total_clients ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active Client</div>
                    <div class="stat-value"><?= $active_clients ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">New Client</div>
                    <div class="stat-value"><?= $new_clients ?></div>
                    <div class="stat-sub">Last 30 days</div>
                </div>
            </div>

            <!-- Clients Table -->
            <div class="table-wrapper" style="background:rgba(255,255,255,0.9);padding:20px;border-radius:var(--radius);">
                <h3 style="font-family:var(--font-head);font-weight:700;
                            font-size:17px;margin-bottom:16px;">Clients</h3>

                <!-- Search + status legend -->
                <div style="display:flex;align-items:center;justify-content:space-between;
                             flex-wrap:wrap;gap:12px;margin-bottom:14px;">
                    <form method="GET" style="display:flex;gap:8px;align-items:center;">
                        <div class="search-box">
                            <span>🔍</span>
                            <input type="text" name="search"
                                   placeholder="Search Client..."
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <button type="submit" class="btn btn-teal btn-sm">Search</button>
                    </form>
                    <div style="font-size:12px;font-weight:700;display:flex;gap:12px;">
                        <span>Status:
                            <span class="status-dot dot-active"></span> Active
                            <span class="status-dot dot-inactive" style="margin-left:8px;"></span> Inactive
                        </span>
                    </div>
                </div>

                <?php if (empty($clients)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">👤</div>
                        <h3>No clients found</h3>
                    </div>
                <?php else: ?>
                    <table id="clientsTable">
                        <thead>
                            <tr>
                                <th>Client Name</th>
                                <th>Contact Info</th>
                                <th>Status</th>
                                <th>Pets</th>
                                <th>Last Visit</th>
                                <th>Date Created</th>
                                <th>Details</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $cl): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <div style="width:32px;height:32px;border-radius:50%;
                                                        background:var(--teal-light);
                                                        display:flex;align-items:center;
                                                        justify-content:center;
                                                        font-size:13px;font-weight:700;
                                                        color:var(--teal-dark);flex-shrink:0;">
                                                <?= strtoupper(substr($cl['name'],0,2)) ?>
                                            </div>
                                            <strong><?= htmlspecialchars($cl['name']) ?></strong>
                                        </div>
                                    </td>
                                    <td style="font-size:12px;">
                                        <?= htmlspecialchars($cl['contact_no'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <span class="status-dot dot-<?= $cl['status'] ?>"></span>
                                        <?= ucfirst($cl['status']) ?>
                                    </td>
                                    <td style="text-align:center;font-weight:700;">
                                        <?= $cl['pet_count'] ?>
                                    </td>
                                    <td style="font-size:12px;">
                                        <?= $cl['last_visit'] ? formatDate($cl['last_visit']) : '—' ?>
                                    </td>
                                    <td style="font-size:12px;">
                                        <?= formatDate($cl['created_at']) ?>
                                    </td>
                                    <td>
                                        <a href="client_records.php?view=<?= $cl['id'] ?>"
                                           class="btn btn-teal btn-sm">View Profile</a>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:4px;">
                                            <a href="client_records.php?view=<?= $cl['id'] ?>"
                                               class="btn btn-gray btn-sm">Edit</a>
                                            <a href="client_records.php?delete_client=<?= $cl['id'] ?>"
                                               onclick="return confirm('Delete this client? All their data will be removed.')"
                                               class="btn btn-red btn-sm">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div><!-- /page-body -->
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
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