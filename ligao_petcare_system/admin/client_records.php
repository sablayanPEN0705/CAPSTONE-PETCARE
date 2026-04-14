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

// Handle archive/unarchive client (GET)
if (isset($_GET['archive_client']) && is_numeric($_GET['archive_client'])) {
    $acid = (int)$_GET['archive_client'];
    mysqli_query($conn, "UPDATE users SET archived=1, status='archived' WHERE id=$acid AND role='user'");
    redirect('client_records.php?success=Client archived.');
}
if (isset($_GET['unarchive_client']) && is_numeric($_GET['unarchive_client'])) {
    $uacid = (int)$_GET['unarchive_client'];
    mysqli_query($conn, "UPDATE users SET archived=0, status='active' WHERE id=$uacid AND role='user'");
    redirect('client_records.php?success=Client restored.');
}
// ── Stats ────────────────────────────────────────────────────
$total_clients  = countRows($conn, 'users', "role='user'");$active_clients = countRows($conn, 'users', "role='user' AND status='active'");
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
$show_archived = isset($_GET['archived']) && $_GET['archived'] === '1';
$where  = $show_archived ? "role='user' AND archived=1" : "role='user' AND (archived=0 OR archived IS NULL)";
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
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
            .pets-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            .pet-mini-img {
                height: 80px;
                font-size: 28px;
            }
            .pet-mini-name {
                font-size: 12px;
            }
            .add-pet-mini {
                min-height: 110px;
                padding: 14px;
            }
            .profile-card {
                padding: 16px;
            }
        }

        @media (max-width: 480px) {
            .pets-grid {
                grid-template-columns: 1fr 1fr;
            }
            .pet-mini-img {
                height: 70px;
                font-size: 24px;
            }
            .pet-tag {
                font-size: 9px;
                padding: 1px 6px;
            }
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
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
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

        @keyframes slideUp { from{opacity:0;transform:translateY(24px);}to{opacity:1;transform:translateY(0);} }

        /* Status dot */
        .status-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 4px;
        }
        .dot-active   { background: #10b981; }
.dot-inactive { background: #9ca3af; }
.dot-archived { background: #f59e0b; }
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
                            <a href="pet_profile.php?pet_id=<?= $pet['id'] ?>&user_id=<?= $view_client['id'] ?>"
                               class="pet-mini-card" style="text-decoration:none;color:inherit;display:block;">
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
                                    <div style="margin-top:6px;font-size:10px;color:var(--teal-dark);font-weight:700;">
                                        View Profile →
                                    </div>
                                </div>
                            </a>
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

            <!-- Appointment History with Ratings -->
            <div class="profile-card" style="margin-top:20px;">
                <h3>📅 Appointment History</h3>
                <?php
                $client_appts = getRows($conn,
                    "SELECT a.*, p.name AS pet_name, s.name AS svc_name
                     FROM appointments a
                     LEFT JOIN pets p ON a.pet_id = p.id
                     LEFT JOIN services s ON a.service_id = s.id
                     WHERE a.user_id = {$view_client['id']}
                     ORDER BY a.appointment_date DESC
                     LIMIT 50");
                ?>
                <?php if (empty($client_appts)): ?>
                    <p style="font-size:13px;color:var(--text-light);">No appointment history found.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                               <tr>
                        <th>Pet</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Service</th>
                        <th>Type</th>
                        <th>Billing</th>
                        <th>Status</th>
                        <th>Rating</th>
                    </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($client_appts as $ca):
                        $ca_rating = null;
                        if ($ca['status'] === 'completed') {
                            $ca_rating = getRow($conn,
                                "SELECT stars FROM ratings
                                 WHERE user_id = {$view_client['id']}
                                 AND appointment_id = {$ca['id']}
                                 LIMIT 1");
                        }
                        $ca_txn = getRow($conn,
                            "SELECT t.id, t.total_amount, t.status AS txn_status
                             FROM transactions t
                             WHERE t.appointment_id = {$ca['id']}
                             LIMIT 1");
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ca['pet_name'] ?? '—') ?></strong></td>
                        <td><?= formatDate($ca['appointment_date']) ?></td>
                        <td><?= formatTime($ca['appointment_time']) ?></td>
                        <td><?= htmlspecialchars($ca['svc_name'] ?? ucfirst(str_replace('_',' ',$ca['appointment_type']))) ?></td>
                        <td>
                            <span style="font-size:11px;background:<?= $ca['appointment_type']==='home_service'?'#fce7f3':'#dbeafe' ?>;
                                         color:<?= $ca['appointment_type']==='home_service'?'#9d174d':'#1e40af' ?>;
                                         padding:2px 8px;border-radius:20px;font-weight:700;">
                                <?= $ca['appointment_type']==='home_service'?'🏠 Home':'🏥 Clinic' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($ca_txn): ?>
                                <a href="transactions.php?view=<?= $ca_txn['id'] ?>"
                                   style="color:var(--teal-dark);font-weight:700;font-size:12px;text-decoration:underline;">
                                    <?= formatPeso($ca_txn['total_amount']) ?>
                                </a>
                                <span style="display:block;font-size:10px;font-weight:700;margin-top:2px;
                                             color:<?= $ca_txn['txn_status']==='paid'?'#065f46':($ca_txn['txn_status']==='overdue'?'#991b1b':'#92400e') ?>;">
                                    <?= $ca_txn['txn_status']==='paid'?'✔ Paid':($ca_txn['txn_status']==='overdue'?'⚠ Overdue':'⏳ Pending') ?>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--text-light);font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= statusBadge($ca['status']) ?></td>                                    <td>
                                        <?php if ($ca['status'] === 'completed'): ?>
                                            <?php if ($ca_rating): ?>
                                                <span style="color:#f59e0b;font-size:15px;letter-spacing:1px;"
                                                      title="<?= $ca_rating['stars'] ?>/5 stars">
                                                    <?= str_repeat('★', $ca_rating['stars']) ?><?= str_repeat('☆', 5 - $ca_rating['stars']) ?>
                                                </span>
                                                <span style="font-size:11px;color:var(--text-light);display:block;">
                                                    <?= $ca_rating['stars'] ?>/5
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#9ca3af;font-size:12px;font-style:italic;">Not rated</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-light);font-size:12px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
            <?php $total_archived_clients = countRows($conn, 'users', "role='user' AND archived=1"); ?>
<div class="stat-cards" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
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
    <div class="stat-card" style="cursor:pointer;" onclick="window.location='client_records.php?<?= $show_archived ? '' : 'archived=1' ?>'">
        <div class="stat-label">🗄️ Archived</div>
        <div class="stat-value"><?= $total_archived_clients ?></div>
        <div class="stat-sub"><?= $show_archived ? 'Click to show active' : 'Click to view' ?></div>
    </div>
</div>
            <!-- Clients Table -->
            <div class="table-wrapper" style="background:rgba(255,255,255,0.9);padding:20px;border-radius:var(--radius);">
                <h3 style="font-family:var(--font-head);font-weight:700;font-size:17px;margin-bottom:16px;">
    <?= $show_archived ? '🗄️ Archived Clients' : 'Clients' ?>
    <?php if ($show_archived): ?>
        <a href="client_records.php" class="btn btn-gray btn-sm" style="margin-left:10px;">← Back to Active Clients</a>
    <?php endif; ?>
</h3>
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
                                            <?php if ($show_archived): ?>
    <a href="client_records.php?unarchive_client=<?= $cl['id'] ?>" class="btn btn-teal btn-sm">Restore</a>
<?php else: ?>
    <button type="button" class="btn btn-red btn-sm"
            onclick="openArchiveClientModal(<?= $cl['id'] ?>)">Archive</button>
<?php endif; ?>                                        </div>
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

<!-- Delete Client Confirmation Modal -->
<div id="deleteClientModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);backdrop-filter:blur(3px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:32px 28px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.25);animation:slideUp 0.25s ease;text-align:center;">
        <div style="width:64px;height:64px;border-radius:50%;background:#fff5f5;border:2px solid #fee2e2;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 16px;">🗑️</div>
        <div style="font-family:var(--font-head);font-size:18px;font-weight:800;color:#1a1a2e;margin-bottom:8px;">Archive Client?</div>
<div style="font-size:13px;color:#6b7280;margin-bottom:24px;line-height:1.6;">
    This client will be <strong style="color:#f59e0b;">archived</strong> and hidden from active records.<br>You can restore them later from the Archived tab.
</div>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="closeDeleteClientModal()"
                    style="padding:10px 24px;border-radius:8px;border:1.5px solid #d1d5db;background:#fff;color:#374151;font-weight:700;font-size:13px;cursor:pointer;font-family:var(--font-main);">
                Cancel
            </button>
            <a id="deleteClientConfirmBtn" href="#"
               style="padding:10px 24px;border-radius:8px;border:none;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-weight:700;font-size:13px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(239,68,68,0.35);font-family:var(--font-main);">
               🗄️ Yes, Archive
            </a>
        </div>
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

// Delete client modal
function openArchiveClientModal(clientId) {
    document.getElementById('deleteClientConfirmBtn').href = 'client_records.php?archive_client=' + clientId;    const modal = document.getElementById('deleteClientModal');
    modal.style.display = 'flex';
}
function closeDeleteClientModal() {
    document.getElementById('deleteClientModal').style.display = 'none';
}
function closeArchiveClientModal() { closeDeleteClientModal(); }
document.getElementById('deleteClientModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteClientModal();
});
</script>
</body>
</html>