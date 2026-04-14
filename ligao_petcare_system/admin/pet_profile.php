<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/pet_profile.php
// Purpose: View individual pet profile + appointment history
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$pet_id  = isset($_GET['pet_id'])  ? (int)$_GET['pet_id']  : 0;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$pet_id) redirect('client_records.php');

$pet = getRow($conn,
    "SELECT p.*, u.name AS owner_name, u.email AS owner_email,
            u.contact_no AS owner_contact, u.address AS owner_address
     FROM pets p
     LEFT JOIN users u ON p.user_id = u.id
     WHERE p.id = $pet_id");

if (!$pet) redirect('client_records.php');

$back_url = $user_id
    ? "client_records.php?view=$user_id"
    : "client_records.php";

// Pet's appointment history
$pet_appts = getRows($conn,
    "SELECT a.*, s.name AS svc_name
     FROM appointments a
     LEFT JOIN services s ON a.service_id = s.id
     WHERE a.pet_id = $pet_id
     ORDER BY a.appointment_date DESC
     LIMIT 50");

$species_icon = match(strtolower($pet['species'] ?? '')) {
    'cat'   => '🐱',
    'dog'   => '🐶',
    'bird'  => '🦜',
    'rabbit'=> '🐰',
    default => '🐾',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Profile — <?= htmlspecialchars($pet['name']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .pet-profile-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            align-items: start;
        }

        .pet-profile-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .pet-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--teal-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: 0 auto 16px;
            overflow: hidden;
            border: 3px solid var(--teal);
        }
        .pet-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pet-name-big {
            font-family: var(--font-head);
            font-size: 22px;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .pet-species-badge {
            display: inline-block;
            background: var(--teal-light);
            color: var(--teal-dark);
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .pet-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            text-align: left;
        }
        .pet-info-row:last-child { border-bottom: none; }
        .pet-info-row .lbl {
            color: var(--text-light);
            font-weight: 700;
            font-size: 12px;
        }
        .pet-info-row .val {
            color: var(--text-dark);
            font-weight: 600;
        }

        .owner-box {
            background: var(--teal-light);
            border-radius: var(--radius-sm);
            padding: 14px;
            margin-top: 16px;
            text-align: left;
        }
        .owner-box-title {
            font-size: 11px;
            font-weight: 800;
            color: var(--teal-dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .owner-box-row {
            font-size: 12px;
            color: var(--text-dark);
            margin-bottom: 4px;
            display: flex;
            gap: 6px;
        }
        .owner-box-row .lbl { color: var(--text-light); font-weight: 700; flex-shrink: 0; }

        .appt-card {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
        }
        .appt-card h3 {
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        @media (max-width: 768px) {
            .pet-profile-layout {
                grid-template-columns: 1fr;
            }
        }

        @keyframes slideUp {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .pet-profile-card, .appt-card {
            animation: slideUp 0.3s ease;
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
                PET PROFILE
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn" title="Notifications">🔔</a>
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <!-- Back button -->
            <div style="margin-bottom:14px;">
                <a href="<?= $back_url ?>" class="btn btn-gray btn-sm">← Back to Client</a>
            </div>

            <div class="pet-profile-layout">

                <!-- LEFT: Pet Info Card -->
                <div class="pet-profile-card">
                    <div class="pet-avatar">
                        <?php if (!empty($pet['photo'])): ?>
                            <img src="../assets/css/images/pets/<?= htmlspecialchars($pet['photo']) ?>"
                                 alt="<?= htmlspecialchars($pet['name']) ?>">
                        <?php else: ?>
                            <?= $species_icon ?>
                        <?php endif; ?>
                    </div>

                    <div class="pet-name-big"><?= htmlspecialchars($pet['name']) ?></div>
                    <div class="pet-species-badge">
                        <?= $species_icon ?> <?= ucfirst($pet['species'] ?? 'Unknown') ?>
                    </div>

                    <div style="margin-bottom:16px;">
                        <div class="pet-info-row">
                            <span class="lbl">Breed</span>
                            <span class="val"><?= htmlspecialchars($pet['breed'] ?: '—') ?></span>
                        </div>
                        <div class="pet-info-row">
                            <span class="lbl">Age</span>
                            <span class="val"><?= htmlspecialchars($pet['age'] ?? '—') ?> yrs</span>
                        </div>
                        <div class="pet-info-row">
                            <span class="lbl">Gender</span>
                            <span class="val"><?= ucfirst($pet['gender'] ?? '—') ?></span>
                        </div>
                        <div class="pet-info-row">
                            <span class="lbl">Weight</span>
                            <span class="val"><?= $pet['weight'] ? $pet['weight'] . ' kg' : '—' ?></span>
                        </div>
                        <div class="pet-info-row">
                            <span class="lbl">Color</span>
                            <span class="val"><?= htmlspecialchars($pet['color'] ?: '—') ?></span>
                        </div>
                    </div>

                    <!-- Owner Info -->
                    <div class="owner-box">
                        <div class="owner-box-title">👤 Owner</div>
                        <div class="owner-box-row">
                            <span class="lbl">Name:</span>
                            <strong><?= htmlspecialchars($pet['owner_name'] ?? '—') ?></strong>
                        </div>
                        <?php if ($pet['owner_contact']): ?>
                        <div class="owner-box-row">
                            <span class="lbl">Contact:</span>
                            <?= htmlspecialchars($pet['owner_contact']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($pet['owner_email']): ?>
                        <div class="owner-box-row">
                            <span class="lbl">Email:</span>
                            <?= htmlspecialchars($pet['owner_email']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($pet['owner_address']): ?>
                        <div class="owner-box-row">
                            <span class="lbl">Address:</span>
                            <?= htmlspecialchars($pet['owner_address']) ?>
                        </div>
                        <?php endif; ?>
                        <div style="margin-top:10px;">
                            <a href="client_records.php?view=<?= $pet['user_id'] ?>"
                               class="btn btn-teal btn-sm" style="width:100%;text-align:center;display:block;">
                               View Full Client Profile
                            </a>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Appointment History -->
                <div class="appt-card">
                    <h3>📅 Appointment History
                        <span style="font-size:12px;color:var(--text-light);font-weight:600;margin-left:8px;">
                            <?= count($pet_appts) ?> record(s)
                        </span>
                    </h3>

                    <?php if (empty($pet_appts)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <h3>No appointments yet</h3>
                            <p>This pet has no appointment history.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Service</th>
                                        <th>Type</th>
                                        <th>Billing</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pet_appts as $pa):
                                        $pa_txn = getRow($conn,
                                            "SELECT t.id, t.total_amount, t.status AS txn_status
                                             FROM transactions t
                                             WHERE t.appointment_id = {$pa['id']}
                                             LIMIT 1");
                                    ?>
                                        <tr>
                                            <td><?= formatDate($pa['appointment_date']) ?></td>
                                            <td><?= formatTime($pa['appointment_time']) ?></td>
                                            <td><?= htmlspecialchars($pa['svc_name'] ?? ucfirst(str_replace('_',' ',$pa['appointment_type']))) ?></td>
                                            <td>
                                                <span style="font-size:11px;
                                                    background:<?= $pa['appointment_type']==='home_service'?'#fce7f3':'#dbeafe' ?>;
                                                    color:<?= $pa['appointment_type']==='home_service'?'#9d174d':'#1e40af' ?>;
                                                    padding:2px 8px;border-radius:20px;font-weight:700;">
                                                    <?= $pa['appointment_type']==='home_service'?'🏠 Home':'🏥 Clinic' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($pa_txn): ?>
                                                    <a href="transactions.php?view=<?= $pa_txn['id'] ?>"
                                                       style="color:var(--teal-dark);font-weight:700;font-size:12px;text-decoration:underline;">
                                                        <?= formatPeso($pa_txn['total_amount']) ?>
                                                    </a>
                                                    <span style="display:block;font-size:10px;font-weight:700;margin-top:2px;
                                                        color:<?= $pa_txn['txn_status']==='paid'?'#065f46':($pa_txn['txn_status']==='overdue'?'#991b1b':'#92400e') ?>;">
                                                        <?= $pa_txn['txn_status']==='paid'?'✔ Paid':($pa_txn['txn_status']==='overdue'?'⚠ Overdue':'⏳ Pending') ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:var(--text-light);font-size:12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= statusBadge($pa['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div><!-- /pet-profile-layout -->
        </div><!-- /page-body -->
    </div>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>