<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: user/pet_profile.php
// Purpose: View/add pets, view individual pet with medical records
// ============================================================
require_once '../includes/auth.php';
requireLogin();
if (isAdmin()) redirect('../admin/dashboard.php');

$user_id = (int)$_SESSION['user_id'];

// ── Handle alerts passed via URL ─────────────────────────────
$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// ── Handle Add Pet form ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add_pet') {
        $name    = sanitize($conn, $_POST['pet_name']   ?? '');
        $species = sanitize($conn, $_POST['species']    ?? '');
        $breed   = sanitize($conn, $_POST['breed']      ?? '');
        $dob     = sanitize($conn, $_POST['dob']        ?? '');
        $age     = sanitize($conn, $_POST['age']        ?? '');
        $gender  = sanitize($conn, $_POST['gender']     ?? '');
        $weight  = sanitize($conn, $_POST['weight']     ?? '');
        $color   = sanitize($conn, $_POST['color']      ?? '');

        if (empty($name) || empty($species) || empty($gender)) {
            redirect('pet_profile.php?error=Please fill in all required fields.');
        }

        // Handle photo upload
        $photo = '';
        if (!empty($_FILES['photo']['name'])) {
            $ext      = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed)) {
                $upload_dir = '../assets/css/images/pets/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename = 'pet_' . time() . '_' . rand(100,999) . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename);
                $photo = $filename;
            }
        }

        $sql = "INSERT INTO pets (user_id, name, species, breed, date_of_birth, age, gender, weight, color, photo)
                VALUES ($user_id, '$name', '$species', '$breed', " .
               ($dob ? "'$dob'" : "NULL") . ", '$age', '$gender', " .
               ($weight ? "$weight" : "NULL") . ", '$color', '$photo')";

        if (mysqli_query($conn, $sql)) {
            redirect('pet_profile.php?success=Pet added successfully!');
        } else {
            redirect('pet_profile.php?error=Failed to add pet: ' . mysqli_error($conn));
        }
    }

    if ($_POST['action'] === 'edit_pet') {
        $pet_id  = (int)($_POST['pet_id'] ?? 0);
        $name    = sanitize($conn, $_POST['pet_name']  ?? '');
        $species = sanitize($conn, $_POST['species']   ?? '');
        $breed   = sanitize($conn, $_POST['breed']     ?? '');
        $dob     = sanitize($conn, $_POST['dob']       ?? '');
        $age     = sanitize($conn, $_POST['age']       ?? '');
        $gender  = sanitize($conn, $_POST['gender']    ?? '');
        $weight  = sanitize($conn, $_POST['weight']    ?? '');
        $color   = sanitize($conn, $_POST['color']     ?? '');

        // Verify ownership
        $own = getRow($conn, "SELECT id FROM pets WHERE id=$pet_id AND user_id=$user_id");
        if (!$own) redirect('pet_profile.php?error=Unauthorized');

        $photo_sql = '';
        if (!empty($_FILES['photo']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed)) {
                $upload_dir = '../assets/css/images/pets/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $filename   = 'pet_' . time() . '_' . rand(100,999) . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename);
                $photo_sql  = ", photo='$filename'";
            }
        }

        $sql = "UPDATE pets SET
                    name='$name', species='$species', breed='$breed',
                    date_of_birth=" . ($dob ? "'$dob'" : "NULL") . ",
                    age='$age', gender='$gender',
                    weight=" . ($weight ? "$weight" : "NULL") . ",
                    color='$color' $photo_sql
                WHERE id=$pet_id AND user_id=$user_id";

        mysqli_query($conn, $sql);
        redirect('pet_profile.php?success=Pet updated!&view=' . $pet_id);
    }

    // ── Handle delete via POST ────────────────────────────────
    if ($_POST['action'] === 'delete_pet') {
        $pet_id = (int)($_POST['pet_id'] ?? 0);
        if ($pet_id) {
            $own = getRow($conn, "SELECT id FROM pets WHERE id=$pet_id AND user_id=$user_id");
            if ($own) {
                mysqli_query($conn, "UPDATE pets SET status='deleted' WHERE id=$pet_id AND user_id=$user_id");
                redirect('pet_profile.php?success=Pet removed successfully.');
            } else {
                redirect('pet_profile.php?error=Pet not found or access denied.');
            }
        }
        redirect('pet_profile.php?error=Invalid request.');
    }
}

// ── Handle delete via GET (legacy, kept for safety) ──────────
if (isset($_GET['delete_pet'])) {
    $pid = (int)$_GET['delete_pet'];
    mysqli_query($conn, "UPDATE pets SET status='deleted' WHERE id=$pid AND user_id=$user_id");
    redirect('pet_profile.php?success=Pet removed.');
}

// ── Determine view: list or single pet ──────────────────────
$view_pet_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_pet    = null;

if ($view_pet_id) {
    $view_pet = getRow($conn,
        "SELECT * FROM pets WHERE id=$view_pet_id AND user_id=$user_id"
    );
    if (!$view_pet) redirect('pet_profile.php');
}

// ── Fetch all pets ───────────────────────────────────────────
$filter  = sanitize($conn, $_GET['filter'] ?? 'all');
$search  = sanitize($conn, $_GET['search'] ?? '');
$where   = "user_id=$user_id AND status='active'";
if ($filter === 'dog')  $where .= " AND species='dog'";
if ($filter === 'cat')  $where .= " AND species='cat'";
if ($search) $where .= " AND name LIKE '%$search%'";
$pets = getRows($conn, "SELECT * FROM pets WHERE $where ORDER BY name ASC");

// ── Fetch medical records if viewing single pet ──────────────
$medications = [];
$vaccines    = [];
$allergies   = [];
if ($view_pet) {
    $pid         = $view_pet['id'];
    
$medications = getRows($conn, "SELECT * FROM pet_medications WHERE pet_id=$pid ORDER BY id DESC");
$vaccines    = getRows($conn, "SELECT * FROM pet_vaccines    WHERE pet_id=$pid ORDER BY date_given DESC");
$allergies     = getRows($conn, "SELECT * FROM pet_allergies   WHERE pet_id=$pid ORDER BY id DESC");
$consultations = getRows($conn, "SELECT * FROM pet_consultations WHERE pet_id=$pid ORDER BY date_of_visit DESC");
$med_history   = getRow($conn,  "SELECT * FROM pet_medical_history WHERE pet_id=$pid");
$documents     = getRows($conn,
    "SELECT d.*, u.name AS uploader_name
     FROM pet_documents d
     LEFT JOIN users u ON d.uploaded_by = u.id
     WHERE d.pet_id=$pid
     ORDER BY d.created_at DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Profile — Ligao Petcare</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .pet-photo-large {
            width: 160px; height: 160px;
            border-radius: var(--radius);
            object-fit: cover;
            box-shadow: var(--shadow);
        }
        .pet-detail-layout {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 20px;
            align-items: start;
            margin-bottom: 20px;
        }
        .pet-info-card {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 20px 24px;
        }
        .pet-info-card h2 {
            font-family: var(--font-head);
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .pet-info-row {
            display: flex;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 13px;
        }
        .pet-info-row .pir-label {
            width: 90px;
            color: var(--text-light);
            font-weight: 600;
            flex-shrink: 0;
        }
        .pet-info-row .pir-value {
            font-weight: 700;
            color: var(--text-dark);
        }
        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        .filter-tab {
            padding: 6px 16px;
            border-radius: var(--radius-lg);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: 2px solid var(--border);
            background: rgba(255,255,255,0.7);
            color: var(--text-mid);
            transition: var(--transition);
            text-decoration: none;
        }
        .filter-tab.active,
        .filter-tab:hover {
            background: var(--teal);
            color: #fff;
            border-color: var(--teal);
        }
        .photo-placeholder {
            width: 160px; height: 160px;
            border-radius: var(--radius);
            background: var(--teal-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            box-shadow: var(--shadow);
        }
        .edit-btn {
            float: right;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: var(--teal-dark);
        }
        .med-edit-btn {
            float: right;
            background: none;
            border: none;
            font-size: 14px;
            cursor: pointer;
            color: var(--text-light);
        }
        .med-edit-btn:hover { color: var(--teal-dark); }
        .drop-select {
            float: right;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: #fff;
        }

        /* ── Delete button on pet card (matches profile.php) ── */
        .pet-card-wrap {
            position: relative;
            text-decoration: none;
        }
        .pet-delete-btn {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: rgba(220, 53, 69, 0.85);
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
            transition: background 0.2s, transform 0.15s;
            z-index: 2;
        }
        .pet-delete-btn:hover {
            background: rgba(185, 28, 40, 0.95);
            transform: scale(1.12);
        }

        /* ── Delete confirmation modal ── */
        .delete-pet-icon  { font-size: 52px; margin-bottom: 10px; }
        .delete-pet-msg   { font-size: 14px; color: var(--text-mid); margin-bottom: 20px; line-height: 1.5; }
        .delete-pet-name  { font-weight: 800; color: var(--text-dark); }

        @media(max-width:700px) {
            .pet-detail-layout { grid-template-columns: 1fr; }
            .medical-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include '../includes/sidebar_user.php'; ?>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-title" style="display:flex; align-items:center; gap:10px;">
                <img
                    src="../assets/css/images/pets/logo.png"
                    alt="Ligao Petcare Logo"
                    style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.6);"
                    onerror="this.style.display='none';"
                >
                PET PROFILE
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn">🔔</a>
                <a href="settings.php" class="topbar-btn">⚙️</a>
            </div>
        </div>

        <div class="page-body">

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($view_pet): ?>
            <!-- ══════════════════════════════════════════════
                 SINGLE PET VIEW
            ══════════════════════════════════════════════ -->
            <div style="margin-bottom:12px;">
                <a href="pet_profile.php" class="btn btn-gray btn-sm">← Back to Pets</a>
            </div>

            <!-- Pet Header -->
            <div class="pet-detail-layout">
                <div>
                    <?php if ($view_pet['photo']): ?>
                        <img src="../assets/css/images/pets/<?= htmlspecialchars($view_pet['photo']) ?>"
                             alt="<?= htmlspecialchars($view_pet['name']) ?>"
                             class="pet-photo-large">
                    <?php else: ?>
                        <div class="photo-placeholder">
                            <?= $view_pet['species'] === 'cat' ? '🐱' : '🐶' ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pet-info-card">
                   <button class="edit-btn" onclick="document.getElementById('editPetModal').classList.add('open')" title="Edit Pet">✏️</button>
                    <h2><?= strtoupper(htmlspecialchars($view_pet['name'])) ?></h2>

                    <div class="pet-info-row">
                        <span class="pir-label">Species:</span>
                        <span class="pir-value"><?= ucfirst($view_pet['species']) ?></span>
                        <span class="pir-label" style="margin-left:20px;">Age:</span>
                        <span class="pir-value"><?= htmlspecialchars($view_pet['age'] ?? '—') ?> years old</span>
                    </div>
                    <div class="pet-info-row">
                        <span class="pir-label">Gender:</span>
                        <span class="pir-value"><?= ucfirst($view_pet['gender']) ?></span>
                        <span class="pir-label" style="margin-left:20px;">Weight:</span>
                        <span class="pir-value"><?= $view_pet['weight'] ? $view_pet['weight'] . ' kg' : '—' ?></span>
                    </div>
                    <div class="pet-info-row">
                        <span class="pir-label">Breed:</span>
                        <span class="pir-value"><?= htmlspecialchars($view_pet['breed'] ?? '—') ?></span>
                    </div>
                    <div class="pet-info-row">
                        <span class="pir-label">Color:</span>
                        <span class="pir-value"><?= htmlspecialchars($view_pet['color'] ?? '—') ?></span>
                    </div>
                    <div class="pet-info-row" style="margin-top:10px;">
                        <a href="appointments.php?pet_id=<?= $view_pet['id'] ?>"
                           class="btn btn-teal btn-sm">📅 Book Appointment</a>
                    </div>
                </div>
            </div>

            <!-- Medical Records -->
            <div style="margin-bottom:16px;" class="flex-between">
                <h2 style="font-family:var(--font-head);font-weight:800;font-size:18px;">
                    MEDICAL RECORDS
                </h2>
                <select class="drop-select" onchange="filterMedical(this.value)">
                    <option value="all">All Records</option>
                    <option value="medications">Medications</option>
                    <option value="vaccines">Vaccines</option>
                    <option value="allergies">Allergies</option>
                </select>
            </div>

            <div class="medical-grid">
                <!-- Medications -->
                <div class="medical-card" id="sec-medications">
                    <div class="med-title">
                        💊 MEDICATIONS
                    </div>
                    <?php if (empty($medications)): ?>
                        <p style="font-size:12px;color:var(--text-light);">No medications on record.</p>
                    <?php else: ?>
                        <?php foreach ($medications as $med): ?>
                            <div class="med-item">
                                <div class="med-name"><?= htmlspecialchars($med['medication_name']) ?></div>
                                <div class="med-detail"><?= htmlspecialchars($med['dosage']) ?>, <?= htmlspecialchars($med['frequency']) ?></div>
                                <?php if ($med['notes']): ?>
                                    <div class="med-detail" style="color:var(--text-mid);"><?= htmlspecialchars($med['notes']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div style="margin-top:10px;">
                        <small style="color:var(--text-light);font-size:11px;">* Records updated by clinic staff only</small>
                    </div>
                </div>

                <!-- Vaccines -->
                <div class="medical-card" id="sec-vaccines">
                    <div class="med-title">
                        💉 VACCINES
                    </div>
                    <?php if (empty($vaccines)): ?>
                        <p style="font-size:12px;color:var(--text-light);">No vaccine records yet.</p>
                    <?php else: ?>
                        <?php foreach ($vaccines as $vac): ?>
                            <div class="med-item">
                                <div class="med-name"><?= htmlspecialchars($vac['vaccine_name']) ?></div>
                                <div class="med-detail">
                                    Date Given: <strong><?= formatDate($vac['date_given']) ?></strong>
                                </div>
                                <div class="med-detail">
                                    Next Due: <strong><?= formatDate($vac['next_due']) ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div style="margin-top:10px;">
                        <small style="color:var(--text-light);font-size:11px;">* Records updated by clinic staff only</small>
                    </div>
                </div>

                <!-- Allergies -->
                <div class="medical-card" id="sec-allergies">
                    <div class="med-title">
                        🌿 ALLERGIES
                    </div>
                    <?php if (empty($allergies)): ?>
                        <p style="font-size:12px;color:var(--text-light);">No allergy records on file.</p>
                    <?php else: ?>
                        <?php foreach ($allergies as $alg): ?>
                            <div class="med-item">
                                <div class="med-name"><?= htmlspecialchars($alg['allergen']) ?></div>
                                <div class="med-detail">
                                    Reaction: <strong><?= htmlspecialchars($alg['reaction']) ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div style="margin-top:10px;">
                        <small style="color:var(--text-light);font-size:11px;">* Records updated by clinic staff only</small>
                    </div>
                </div>
            </div><!-- /medical-grid -->

            <!-- ══ PREVIOUS CONSULTATIONS + MEDICAL HISTORY ══ -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;">

                <!-- Previous Consultations -->
                <div style="background:rgba(255,255,255,0.92);border-radius:var(--radius);
                            padding:16px 20px;box-shadow:var(--shadow);">
                    <div style="font-family:var(--font-head);font-weight:800;font-size:14px;
                                margin-bottom:12px;color:var(--text-dark);">
                        📋 Previous Consultations
                    </div>
                    <?php if (empty($consultations)): ?>
                        <p style="font-size:12px;color:var(--text-light);">No consultation records on file.</p>
                    <?php else: ?>
                        <?php foreach ($consultations as $con): ?>
                            <div style="font-size:12px;margin-bottom:10px;padding-bottom:10px;
                                        border-bottom:1px solid var(--border);">
                                <div style="font-weight:700;color:var(--teal-dark);margin-bottom:2px;">
                                    <?= formatDate($con['date_of_visit']) ?>
                                </div>
                                <div><strong>Reason for Visit:</strong> <?= htmlspecialchars($con['reason_for_visit'] ?? '—') ?></div>
                                <div><strong>Diagnosis:</strong> <?= htmlspecialchars($con['diagnosis'] ?? '—') ?></div>
                                <div><strong>Treatment Given:</strong> <?= htmlspecialchars($con['treatment_given'] ?? '—') ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div style="margin-top:8px;">
                        <small style="color:var(--text-light);font-size:11px;">* Updated by clinic staff only</small>
                    </div>
                </div>

                <!-- Medical History -->
                <div style="background:rgba(255,255,255,0.92);border-radius:var(--radius);
                            padding:16px 20px;box-shadow:var(--shadow);">
                    <div style="font-family:var(--font-head);font-weight:800;font-size:14px;
                                margin-bottom:12px;color:var(--text-dark);">
                        🏥 Medical History
                    </div>
                    <?php $mh = $med_history; ?>
                    <div style="font-size:12px;display:flex;flex-direction:column;gap:8px;">
                        <div>
                            <span style="color:var(--text-light);font-weight:700;">Clinic:</span>
                            <span style="font-weight:600;margin-left:4px;">
                                <?= htmlspecialchars($mh['clinic_name'] ?? '—') ?>
                            </span>
                        </div>
                        <div>
                            <span style="color:var(--text-light);font-weight:700;">Past Illnesses:</span>
                            <span style="font-weight:600;margin-left:4px;">
                                <?= htmlspecialchars($mh['past_illnesses'] ?? '—') ?>
                            </span>
                        </div>
                        <div>
                            <span style="color:var(--text-light);font-weight:700;">Chronic Conditions:</span>
                            <span style="font-weight:600;margin-left:4px;">
                                <?= htmlspecialchars($mh['chronic_conditions'] ?? 'None') ?>
                            </span>
                        </div>
                        <div>
                            <span style="color:var(--text-light);font-weight:700;">Injuries/Surgeries:</span>
                            <span style="font-weight:600;margin-left:4px;">
                                <?= htmlspecialchars($mh['injuries_surgeries'] ?? 'None') ?>
                            </span>
                        </div>
                    </div>
                    <div style="margin-top:8px;">
                        <small style="color:var(--text-light);font-size:11px;">* Updated by clinic staff only</small>
                    </div>
                </div>

            </div>

            <!-- ══ VACCINATION CERTIFICATE (Booklet) ═════════ -->
            <div style="background:rgba(255,255,255,0.92);border-radius:var(--radius);
                        padding:20px;box-shadow:var(--shadow);margin-top:20px;">
                <h3 style="font-family:var(--font-head);font-weight:800;font-size:16px;
                            text-align:center;margin-bottom:16px;color:var(--text-dark);">
                    💉 VACCINATION CERTIFICATE (Booklet)
                </h3>
                <?php if (empty($vaccines)): ?>
                    <p style="font-size:12px;color:var(--text-light);text-align:center;">No vaccination records on file.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;font-size:12px;border-collapse:collapse;">
                            <thead>
                                <tr style="background:var(--teal-light);">
                                    <th style="padding:8px 10px;text-align:center;color:var(--teal-dark);font-size:11px;">No.</th>
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Date</th>
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Wt. kg</th>
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Against</th>
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Manufacturer</th>
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Due Date</th>
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Veterinarian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vaccines as $vi => $vb): ?>
                                    <tr style="border-bottom:1px solid var(--border);
                                               <?= $vi%2===0 ? 'background:#fafffe;' : '' ?>">
                                        <td style="padding:7px 10px;text-align:center;color:var(--text-light);"><?= $vi+1 ?></td>
                                        <td style="padding:7px 10px;"><?= $vb['date_given'] ? date('m/d/y',strtotime($vb['date_given'])) : '—' ?></td>
                                        <td style="padding:7px 10px;"><?= $view_pet['weight'] ? $view_pet['weight'].' kg' : '—' ?></td>
                                        <td style="padding:7px 10px;font-weight:700;"><?= htmlspecialchars($vb['vaccine_name']) ?></td>
                                        <td style="padding:7px 10px;color:var(--text-mid);"><?= htmlspecialchars($vb['notes'] ?? '—') ?></td>
                                        <td style="padding:7px 10px;"><?= $vb['next_due'] ? date('m/d/y',strtotime($vb['next_due'])) : '—' ?></td>
                                        <td style="padding:7px 10px;">Dr. Ann</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <div style="margin-top:10px;text-align:center;">
                    <small style="color:var(--text-light);font-size:11px;">* Vaccination records updated by clinic staff only</small>
                </div>
            </div>

            </div>

            <!-- ══ DOCUMENTS & ATTACHMENTS ══════════════════ -->
            <?php if (!empty($documents)): ?>
            <div style="background:rgba(255,255,255,0.92);border-radius:var(--radius);
                        padding:20px;box-shadow:var(--shadow);margin-top:20px;">
                <h3 style="font-family:var(--font-head);font-weight:800;font-size:16px;
                            margin-bottom:16px;color:var(--text-dark);display:flex;align-items:center;gap:8px;">
                    📂 Documents &amp; Attachments
                    <span style="background:var(--teal);color:#fff;font-size:11px;font-weight:800;
                                 padding:2px 10px;border-radius:20px;"><?= count($documents) ?></span>
                </h3>

                <?php
                // Reuse the same helper functions from admin side inline
                $doc_type_labels = [
                    'lab_result'   => '🧪 Lab Result',
                    'xray'         => '🔬 X-Ray',
                    'prescription' => '💊 Prescription',
                    'photo'        => '📷 Photo',
                    'certificate'  => '📜 Certificate',
                    'invoice'      => '🧾 Invoice/Receipt',
                    'other'        => '📎 Other',
                    'general'      => '📄 General',
                ];
                $doc_badge_colors = [
                    'lab_result'   => ['#dbeafe','#1e40af'],
                    'xray'         => ['#e0e7ff','#3730a3'],
                    'prescription' => ['#d1fae5','#065f46'],
                    'photo'        => ['#fce7f3','#9d174d'],
                    'certificate'  => ['#fef3c7','#92400e'],
                    'invoice'      => ['#fdf4ff','#7e22ce'],
                    'other'        => ['#f3f4f6','#374151'],
                    'general'      => ['#e0f7fa','#006064'],
                ];
                ?>

                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;"
                     id="userDocGrid">
                    <?php foreach ($documents as $doc):
                        $is_img   = str_starts_with($doc['mime_type'], 'image/');
                        $file_url = '../assets/pet_documents/' . $doc['file_name'];
                        $label    = $doc_type_labels[$doc['document_type']] ?? '📄 ' . ucfirst($doc['document_type']);
                        [$bg, $fg]= $doc_badge_colors[$doc['document_type']] ?? ['#f3f4f6','#374151'];
                        $size_str = $doc['file_size'] < 1048576
                                    ? round($doc['file_size'] / 1024, 1) . ' KB'
                                    : round($doc['file_size'] / 1048576, 1) . ' MB';
                    ?>
                        <div style="border:1.5px solid var(--border);border-radius:var(--radius);
                                    overflow:hidden;background:#fff;display:flex;flex-direction:column;
                                    transition:var(--transition);"
                             onmouseover="this.style.borderColor='var(--teal)';this.style.transform='translateY(-2px)'"
                             onmouseout="this.style.borderColor='var(--border)';this.style.transform=''">

                            <!-- Preview -->
                            <div style="height:120px;display:flex;align-items:center;justify-content:center;
                                        background:#f8fffe;overflow:hidden;cursor:pointer;"
                                 onclick="<?= $is_img
                                    ? "openUserLightbox('".htmlspecialchars($file_url)."','".htmlspecialchars(addslashes($doc['title']))."')"
                                    : "window.open('".htmlspecialchars($file_url)."','_blank')" ?>">
                                <?php if ($is_img): ?>
                                    <img src="<?= htmlspecialchars($file_url) ?>"
                                         alt="<?= htmlspecialchars($doc['title']) ?>"
                                         style="width:100%;height:100%;object-fit:cover;" loading="lazy">
                                <?php else: ?>
                                    <div style="font-size:44px;opacity:0.7;">
                                        <?php
                                        if ($doc['mime_type'] === 'application/pdf') echo '📕';
                                        elseif (str_contains($doc['mime_type'], 'word'))  echo '📘';
                                        elseif (str_contains($doc['mime_type'], 'excel') || str_contains($doc['mime_type'], 'spreadsheet')) echo '📗';
                                        else echo '📄';
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Body -->
                            <div style="padding:10px 12px;flex:1;">
                                <span style="display:inline-block;font-size:10px;font-weight:700;
                                             padding:2px 7px;border-radius:20px;margin-bottom:4px;
                                             background:<?= $bg ?>;color:<?= $fg ?>;">
                                    <?= $label ?>
                                </span>
                                <div style="font-weight:700;font-size:13px;color:var(--text-dark);
                                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                                     title="<?= htmlspecialchars($doc['title']) ?>">
                                    <?= htmlspecialchars($doc['title']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--text-light);margin-top:3px;line-height:1.6;">
                                    <?= $size_str ?> &nbsp;·&nbsp; <?= date('M j, Y', strtotime($doc['created_at'])) ?>
                                    <?php if ($doc['notes']): ?>
                                        <br>📝 <?= htmlspecialchars(mb_strimwidth($doc['notes'], 0, 40, '…')) ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div style="display:flex;gap:6px;padding:8px 12px;
                                        border-top:1px solid var(--border);background:#fafffe;">
                                <?php if ($is_img): ?>
                                    <button onclick="openUserLightbox('<?= htmlspecialchars($file_url) ?>','<?= htmlspecialchars(addslashes($doc['title'])) ?>')"
                                            style="flex:1;padding:5px 0;border-radius:var(--radius-sm);
                                                   font-size:11px;font-weight:700;cursor:pointer;border:none;
                                                   background:#e0f7fa;color:var(--teal-dark);transition:var(--transition);"
                                            onmouseover="this.style.background='var(--teal)';this.style.color='#fff'"
                                            onmouseout="this.style.background='#e0f7fa';this.style.color='var(--teal-dark)'">
                                        🔍 View
                                    </button>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($file_url) ?>" target="_blank"
                                       style="flex:1;text-align:center;padding:5px 0;border-radius:var(--radius-sm);
                                              font-size:11px;font-weight:700;text-decoration:none;
                                              background:#e0f7fa;color:var(--teal-dark);transition:var(--transition);"
                                       onmouseover="this.style.background='var(--teal)';this.style.color='#fff'"
                                       onmouseout="this.style.background='#e0f7fa';this.style.color='var(--teal-dark)'">
                                        🔍 Open
                                    </a>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars($file_url) ?>"
                                   download="<?= htmlspecialchars($doc['original_name']) ?>"
                                   style="flex:1;text-align:center;padding:5px 0;border-radius:var(--radius-sm);
                                          font-size:11px;font-weight:700;text-decoration:none;
                                          background:#dbeafe;color:#1e40af;transition:var(--transition);"
                                   onmouseover="this.style.background='#1e40af';this.style.color='#fff'"
                                   onmouseout="this.style.background='#dbeafe';this.style.color='#1e40af'">
                                    ⬇ Save
                                </a>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:12px;text-align:center;">
                    <small style="color:var(--text-light);font-size:11px;">
                        * Documents uploaded and managed by clinic staff only
                    </small>
                </div>
            </div>
            <?php endif; // end documents ?>

            
            <!-- Edit Pet Modal -->
            <div class="modal-overlay" id="editPetModal">
                <div class="modal">
                    <button class="modal-close" onclick="document.getElementById('editPetModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">Edit Pet Details</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action"  value="edit_pet">
                        <input type="hidden" name="pet_id"  value="<?= $view_pet['id'] ?>">
                        <div class="form-group">
                            <label>Pet Name *</label>
                            <input type="text" name="pet_name" value="<?= htmlspecialchars($view_pet['name']) ?>" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" name="dob" id="edit_dob"
                                       value="<?= $view_pet['date_of_birth'] ?>"
                                       oninput="calcAge('edit_dob','edit_age')">
                            </div>
                            <div class="form-group">
                                <label>Age (years)</label>
                                <input type="text" name="age" id="edit_age" value="<?= htmlspecialchars($view_pet['age']) ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Gender *</label>
                                <select name="gender" required>
                                    <option value="male"   <?= $view_pet['gender']==='male'   ? 'selected':'' ?>>Male</option>
                                    <option value="female" <?= $view_pet['gender']==='female' ? 'selected':'' ?>>Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Weight (kg)</label>
                                <input type="number" step="0.01" name="weight" value="<?= $view_pet['weight'] ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Species *</label>
                                <select name="species" required>
                                    <option value="dog"   <?= $view_pet['species']==='dog'   ? 'selected':'' ?>>Dog</option>
                                    <option value="cat"   <?= $view_pet['species']==='cat'   ? 'selected':'' ?>>Cat</option>
                                    <option value="other" <?= $view_pet['species']==='other' ? 'selected':'' ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Breed</label>
                                <input type="text" name="breed" value="<?= htmlspecialchars($view_pet['breed']) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Color</label>
                            <input type="text" name="color" value="<?= htmlspecialchars($view_pet['color']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Update Photo</label>
                            <input type="file" name="photo" accept="image/*">
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('editPetModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- ══════════════════════════════════════════════
                 PET LIST VIEW
            ══════════════════════════════════════════════ -->

            <!-- Search -->
            <div class="filter-bar" style="margin-bottom:16px;">
                <div class="search-box" style="flex:1;max-width:360px;">
                    <span>🔍</span>
                    <input type="text" placeholder="Search pet..."
                           oninput="filterPetCards(this.value)">
                </div>
            </div>

            <!-- Category filter tabs -->
            <div class="filter-tabs">
                <a href="pet_profile.php?filter=all"
                   class="filter-tab <?= $filter==='all' ? 'active':'' ?>">All</a>
                <a href="pet_profile.php?filter=dog"
                   class="filter-tab <?= $filter==='dog' ? 'active':'' ?>">🐶 Dogs</a>
                <a href="pet_profile.php?filter=cat"
                   class="filter-tab <?= $filter==='cat' ? 'active':'' ?>">🐱 Cats</a>
            </div>

            <div class="pet-grid" id="petGrid">
                <!-- Existing pets -->
                <?php foreach ($pets as $pet): ?>
                    <div class="pet-card-wrap pet-card" style="position:relative;">
                        <!-- Delete button -->
                        <button
                            class="pet-delete-btn"
                            title="Remove <?= htmlspecialchars($pet['name']) ?>"
                            onclick="confirmDeletePet(<?= $pet['id'] ?>, '<?= htmlspecialchars(addslashes($pet['name'])) ?>', '<?= $pet['species'] === 'cat' ? '🐱' : '🐶' ?>')"
                        >✕</button>

                        <a href="pet_profile.php?view=<?= $pet['id'] ?>" style="text-decoration:none;display:block;">
                            <?php if ($pet['photo']): ?>
                                <img src="../assets/css/images/pets/<?= htmlspecialchars($pet['photo']) ?>"
                                     alt="<?= htmlspecialchars($pet['name']) ?>">
                            <?php else: ?>
                                <div style="height:140px;background:var(--teal-light);display:flex;align-items:center;justify-content:center;font-size:52px;">
                                    <?= $pet['species'] === 'cat' ? '🐱' : '🐶' ?>
                                </div>
                            <?php endif; ?>
                            <div class="pet-card-body">
                                <div class="pet-card-name">
                                    <span style="color:#ef4444;margin-right:4px;">♡</span>
                                    <?= strtoupper(htmlspecialchars($pet['name'])) ?>
                                </div>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;justify-content:center;">
                                    <span style="background:var(--teal-light);color:var(--teal-dark);padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                                        <?= htmlspecialchars($pet['breed'] ?: ucfirst($pet['species'])) ?>
                                    </span>
                                    <span style="background:#e0f7fa;color:#00838f;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                                        <?= ucfirst($pet['gender']) ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>

                <!-- Add New Pet Card -->
                <div class="pet-card-add" onclick="document.getElementById('addPetModal').classList.add('open')">
                    <div class="add-icon">⊕</div>
                    <div class="add-label">Add Pet</div>
                    <div class="add-sub">Tap to add your pet!</div>
                </div>
            </div>

            <?php if (empty($pets)): ?>
                <div class="empty-state" style="margin-top:20px;">
                    <div class="empty-icon">🐾</div>
                    <h3>No Pets Yet</h3>
                    <p>Add your first pet to get started!</p>
                </div>
            <?php endif; ?>
            <?php endif; // end view_pet / list view ?>

        </div><!-- /page-body -->
    </div><!-- /main-content -->
</div><!-- /app-wrapper -->

<!-- Add Pet Modal -->
<div class="modal-overlay" id="addPetModal">
    <div class="modal">
        <button class="modal-close" onclick="document.getElementById('addPetModal').classList.remove('open')">×</button>
        <h3 class="modal-title">Fill Out Pet Details</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_pet">
            <div class="form-group">
                <label>Pet Name *</label>
                <input type="text" name="pet_name" placeholder="Enter pet's name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" id="add_dob"
                           oninput="calcAge('add_dob','add_age')">
                </div>
                <div class="form-group">
                    <label>Age (years)</label>
                    <input type="text" name="age" id="add_age" placeholder="Select Age">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Gender *</label>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Weight (kg)</label>
                    <input type="number" step="0.01" name="weight" placeholder="Enter weight">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Species *</label>
                    <select name="species" required>
                        <option value="">Select Species</option>
                        <option value="dog">Dog</option>
                        <option value="cat">Cat</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Breed</label>
                    <input type="text" name="breed" placeholder="Select/type breed">
                </div>
            </div>
            <div class="form-group">
                <label>Color</label>
                <input type="text" name="color" placeholder="Enter color">
            </div>
            <div class="form-group">
                <label>Pet Photo</label>
                <input type="file" name="photo" accept="image/*"
                       onchange="previewImage(this,'addPetPreview')">
                <img id="addPetPreview"
                     style="display:none;margin-top:10px;width:100px;height:100px;object-fit:cover;border-radius:8px;">
            </div>
            <div class="modal-actions">
               <button type="button" class="btn btn-gray" onclick="document.getElementById('addPetModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">Add pet</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Pet Confirmation Modal -->
<div class="modal-overlay" id="deletePetModal">
    <div class="modal" style="max-width:380px;text-align:center;">
        <button class="modal-close"
                onclick="document.getElementById('deletePetModal').classList.remove('open')">×</button>
        <div class="delete-pet-icon" id="deletePetEmoji">🐾</div>
        <h3 class="modal-title" style="justify-content:center;">Remove Pet?</h3>
        <p class="delete-pet-msg">
            Are you sure you want to remove <span class="delete-pet-name" id="deletePetNameDisplay"></span> from your profile?
            <br><span style="font-size:12px;color:var(--text-light);">This action cannot be undone.</span>
        </p>
        <form method="POST" id="deletePetForm">
            <input type="hidden" name="action" value="delete_pet">
            <input type="hidden" name="pet_id" id="deletePetIdInput" value="">
            <div class="modal-actions" style="justify-content:center;">
                <button type="button" class="btn btn-gray"
                        onclick="document.getElementById('deletePetModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn" style="background:#dc3545;color:#fff;">🗑️ Remove Pet</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// Filter pet cards by search input
function filterPetCards(query) {
    const cards = document.querySelectorAll('#petGrid .pet-card');
    const q = query.toLowerCase();
    cards.forEach(card => {
        const name = card.querySelector('.pet-card-name')?.textContent.toLowerCase() || '';
        card.style.display = name.includes(q) ? '' : 'none';
    });
}

// Filter medical record sections
function filterMedical(val) {
    const sections = ['medications','vaccines','allergies'];
    sections.forEach(s => {
        const el = document.getElementById('sec-' + s);
        if (el) el.style.display = (val === 'all' || val === s) ? '' : 'none';
    });
}

// Open delete confirmation modal
function confirmDeletePet(petId, petName, petEmoji) {
    document.getElementById('deletePetIdInput').value          = petId;
    document.getElementById('deletePetNameDisplay').textContent = petName;
    document.getElementById('deletePetEmoji').textContent       = petEmoji;
    document.getElementById('deletePetModal').classList.add('open');
}

// Close modal on outside click
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});

// Responsive: stack consultation+history on mobile
document.addEventListener('DOMContentLoaded', function() {
    function checkWidth() {
        const grid = document.querySelector('.consult-history-grid');
        if (grid) grid.style.gridTemplateColumns = window.innerWidth < 700 ? '1fr' : '1fr 1fr';
    }
    window.addEventListener('resize', checkWidth);
    checkWidth();
});

// User-side image lightbox
function openUserLightbox(src, caption) {
    // Reuse existing delete modal overlay or create a simple one
    const ov = document.createElement('div');
    ov.id = 'userLightbox';
    ov.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.88);display:flex;align-items:center;justify-content:center;flex-direction:column;';
    ov.innerHTML = `
        <button onclick="document.getElementById('userLightbox').remove()"
                style="position:absolute;top:18px;right:22px;font-size:32px;color:#fff;background:none;border:none;cursor:pointer;">×</button>
        <img src="${src}" style="max-width:90vw;max-height:85vh;border-radius:10px;object-fit:contain;">
        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:12px;font-weight:600;">${caption}</div>`;
    ov.addEventListener('click', e => { if (e.target === ov) ov.remove(); });
    document.body.appendChild(ov);
}

</script>

<?php include_once __DIR__ . '/../includes/chatbot-widget.php'; ?>


</body>
</html>