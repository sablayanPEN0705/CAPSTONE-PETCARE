<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/pet_records.php
// Purpose: View all pets + edit medical records (admin only)
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// ── Handle Add/Edit Medication ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_medication') {
    $pid    = (int)$_POST['pet_id'];
    $name   = sanitize($conn, $_POST['med_name']          ?? '');
    $dose   = sanitize($conn, $_POST['dosage']            ?? '');
    $freq   = sanitize($conn, $_POST['frequency']         ?? '');
    $note   = sanitize($conn, $_POST['notes']             ?? '');
    $prescby = sanitize($conn, $_POST['prescribed_by']    ?? '');
    $prescdt = sanitize($conn, $_POST['prescription_date'] ?? '');
    if ($pid && $name) {
        mysqli_query($conn,
            "INSERT INTO pet_medications (pet_id,medication_name,dosage,frequency,notes,prescribed_by,prescription_date)
             VALUES ($pid,'$name','$dose','$freq','$note','$prescby'," .
             ($prescdt ? "'$prescdt'" : 'NULL') . ")");
    }
    redirect("pet_records.php?success=Medication added.&view=$pid");
}

    if ($action === 'edit_medication') {
    $mid     = (int)$_POST['med_id'];
    $pid     = (int)$_POST['pet_id'];
    $name    = sanitize($conn, $_POST['med_name']           ?? '');
    $dose    = sanitize($conn, $_POST['dosage']             ?? '');
    $freq    = sanitize($conn, $_POST['frequency']          ?? '');
    $note    = sanitize($conn, $_POST['notes']              ?? '');
    $prescby = sanitize($conn, $_POST['prescribed_by']      ?? '');
    $prescdt = sanitize($conn, $_POST['prescription_date']  ?? '');
    mysqli_query($conn,
        "UPDATE pet_medications
         SET medication_name='$name', dosage='$dose', frequency='$freq', notes='$note',
             prescribed_by='$prescby',
             prescription_date=" . ($prescdt ? "'$prescdt'" : 'NULL') . "
         WHERE id=$mid AND pet_id=$pid");
    redirect("pet_records.php?success=Medication updated.&view=$pid");
}
    if ($action === 'delete_medication') {
        $mid = (int)$_POST['med_id'];
        $pid = (int)$_POST['pet_id'];
        mysqli_query($conn, "DELETE FROM pet_medications WHERE id=$mid AND pet_id=$pid");
        redirect("pet_records.php?success=Medication removed.&view=$pid");
    }

    // ── Vaccines ─────────────────────────────────────────────
    if ($action === 'add_vaccine') {
        $pid   = (int)$_POST['pet_id'];
        $name  = sanitize($conn, $_POST['vac_name']  ?? '');
        $given = sanitize($conn, $_POST['date_given'] ?? '');
        $due   = sanitize($conn, $_POST['next_due']   ?? '');
        $note  = sanitize($conn, $_POST['notes']      ?? '');
        if ($pid && $name) {
            mysqli_query($conn,
                "INSERT INTO pet_vaccines (pet_id,vaccine_name,date_given,next_due,notes)
                 VALUES ($pid,'$name'," .
                ($given ? "'$given'" : 'NULL') . "," .
                ($due   ? "'$due'"   : 'NULL') . ",'$note')");
            // Notify owner about upcoming vaccine
            $pet = getRow($conn,
                "SELECT p.name, p.user_id FROM pets p WHERE p.id=$pid");
            if ($pet && $due) {
                $uid = (int)$pet['user_id'];
                $msg = "{$pet['name']}'s $name vaccine is due on " . formatDate($due);
                mysqli_query($conn,
                    "INSERT INTO notifications (user_id,title,message,type)
                     VALUES ($uid,'Vaccine Reminder','$msg','vaccine')");
            }
        }
        redirect("pet_records.php?success=Vaccine record added.&view=$pid");
    }

    if ($action === 'edit_vaccine') {
        $vid   = (int)$_POST['vac_id'];
        $pid   = (int)$_POST['pet_id'];
        $name  = sanitize($conn, $_POST['vac_name']   ?? '');
        $given = sanitize($conn, $_POST['date_given'] ?? '');
        $due   = sanitize($conn, $_POST['next_due']   ?? '');
        $note  = sanitize($conn, $_POST['notes']      ?? '');
        mysqli_query($conn,
            "UPDATE pet_vaccines
             SET vaccine_name='$name',
                 date_given=" . ($given ? "'$given'" : 'NULL') . ",
                 next_due="   . ($due   ? "'$due'"   : 'NULL') . ",
                 notes='$note'
             WHERE id=$vid AND pet_id=$pid");
        redirect("pet_records.php?success=Vaccine updated.&view=$pid");
    }

    if ($action === 'delete_vaccine') {
        $vid = (int)$_POST['vac_id'];
        $pid = (int)$_POST['pet_id'];
        mysqli_query($conn, "DELETE FROM pet_vaccines WHERE id=$vid AND pet_id=$pid");
        redirect("pet_records.php?success=Vaccine removed.&view=$pid");
    }

    // ── Allergies ─────────────────────────────────────────────
    if ($action === 'add_allergy') {
        $pid      = (int)$_POST['pet_id'];
        $allergen = sanitize($conn, $_POST['allergen'] ?? '');
        $reaction = sanitize($conn, $_POST['reaction'] ?? '');
        if ($pid && $allergen) {
            mysqli_query($conn,
                "INSERT INTO pet_allergies (pet_id,allergen,reaction)
                 VALUES ($pid,'$allergen','$reaction')");
        }
        redirect("pet_records.php?success=Allergy added.&view=$pid");
    }

    if ($action === 'edit_allergy') {
        $alid     = (int)$_POST['allergy_id'];
        $pid      = (int)$_POST['pet_id'];
        $allergen = sanitize($conn, $_POST['allergen'] ?? '');
        $reaction = sanitize($conn, $_POST['reaction'] ?? '');
        mysqli_query($conn,
            "UPDATE pet_allergies
             SET allergen='$allergen', reaction='$reaction'
             WHERE id=$alid AND pet_id=$pid");
        redirect("pet_records.php?success=Allergy updated.&view=$pid");
    }

    if ($action === 'delete_allergy') {
        $alid = (int)$_POST['allergy_id'];
        $pid  = (int)$_POST['pet_id'];
        mysqli_query($conn, "DELETE FROM pet_allergies WHERE id=$alid AND pet_id=$pid");
        redirect("pet_records.php?success=Allergy removed.&view=$pid");
    }

    // ── Previous Consultations ───────────────────────────────
    if ($action === 'add_consultation') {
        $pid   = (int)$_POST['pet_id'];
        $date  = sanitize($conn, $_POST['date_of_visit']    ?? '');
        $rsn   = sanitize($conn, $_POST['reason_for_visit'] ?? '');
        $diag  = sanitize($conn, $_POST['diagnosis']        ?? '');
        $trt   = sanitize($conn, $_POST['treatment_given']  ?? '');
        $vet   = sanitize($conn, $_POST['vet_name']         ?? '');
        $note  = sanitize($conn, $_POST['notes']            ?? '');
        if ($pid && $date) {
            mysqli_query($conn,
                "INSERT INTO pet_consultations (pet_id,date_of_visit,reason_for_visit,diagnosis,treatment_given,vet_name,notes)
                 VALUES ($pid,'$date','$rsn','$diag','$trt','$vet','$note')");
        }
        redirect("pet_records.php?success=Consultation added.&view=$pid");
    }

    if ($action === 'delete_consultation') {
        $cid = (int)$_POST['consult_id'];
        $pid = (int)$_POST['pet_id'];
        mysqli_query($conn, "DELETE FROM pet_consultations WHERE id=$cid AND pet_id=$pid");
        redirect("pet_records.php?success=Consultation removed.&view=$pid");
    }

    // ── Medical History ──────────────────────────────────────
    if ($action === 'save_medical_history') {
        $pid    = (int)$_POST['pet_id'];
        $clinic = sanitize($conn, $_POST['clinic_name']        ?? '');
        $ill    = sanitize($conn, $_POST['past_illnesses']     ?? '');
        $chron  = sanitize($conn, $_POST['chronic_conditions'] ?? '');
        $inj    = sanitize($conn, $_POST['injuries_surgeries'] ?? '');
        $note   = sanitize($conn, $_POST['notes']              ?? '');
        $exists = getRow($conn, "SELECT id FROM pet_medical_history WHERE pet_id=$pid");
        if ($exists) {
            mysqli_query($conn,
                "UPDATE pet_medical_history SET clinic_name='$clinic',past_illnesses='$ill',
                 chronic_conditions='$chron',injuries_surgeries='$inj',notes='$note'
                 WHERE pet_id=$pid");
        } else {
            mysqli_query($conn,
                "INSERT INTO pet_medical_history (pet_id,clinic_name,past_illnesses,chronic_conditions,injuries_surgeries,notes)
                 VALUES ($pid,'$clinic','$ill','$chron','$inj','$note')");
        }
        redirect("pet_records.php?success=Medical history saved.&view=$pid");
    }

    // ── Edit Pet Info ─────────────────────────────────────────
    if ($action === 'edit_pet') {
        $pid     = (int)$_POST['pet_id'];
        $name    = sanitize($conn, $_POST['pet_name'] ?? '');
        $species = sanitize($conn, $_POST['species']  ?? '');
        $breed   = sanitize($conn, $_POST['breed']    ?? '');
        $age     = sanitize($conn, $_POST['age']      ?? '');
        $gender  = sanitize($conn, $_POST['gender']   ?? '');
        $weight  = sanitize($conn, $_POST['weight']   ?? '');
        $color   = sanitize($conn, $_POST['color']    ?? '');
        mysqli_query($conn,
            "UPDATE pets SET name='$name',species='$species',breed='$breed',
             age='$age',gender='$gender',
             weight=" . ($weight ? $weight : 'NULL') . ",
             color='$color'
             WHERE id=$pid");
        redirect("pet_records.php?success=Pet info updated.&view=$pid");
    }
}

// ── Stats ─────────────────────────────────────────────────────
$total_pets = countRows($conn, 'pets');
$total_dogs = countRows($conn, 'pets', "species='dog'");
$total_cats = countRows($conn, 'pets', "species='cat'");

// ── View single pet ──────────────────────────────────────────
$view_id  = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_pet = null;
$meds     = [];
$vaccines = [];
$allergies= [];
$pet_owner= null;

if ($view_id) {
    $view_pet = getRow($conn, "SELECT * FROM pets WHERE id=$view_id");
    if (!$view_pet) redirect('pet_records.php');
    $pid       = $view_pet['id'];
    $meds      = getRows($conn, "SELECT * FROM pet_medications WHERE pet_id=$pid ORDER BY id DESC");
    $vaccines  = getRows($conn, "SELECT * FROM pet_vaccines    WHERE pet_id=$pid ORDER BY date_given DESC");
    $allergies     = getRows($conn, "SELECT * FROM pet_allergies   WHERE pet_id=$pid ORDER BY id DESC");
    $consultations = getRows($conn, "SELECT * FROM pet_consultations WHERE pet_id=$pid ORDER BY date_of_visit DESC");
    $med_history   = getRow($conn,  "SELECT * FROM pet_medical_history WHERE pet_id=$pid");
    $pet_owner     = getRow($conn,  "SELECT * FROM users WHERE id={$view_pet['user_id']}");
}

// ── Pet list ─────────────────────────────────────────────────
$search  = sanitize($conn, $_GET['search']  ?? '');
$species = sanitize($conn, $_GET['species'] ?? '');
$where   = '1=1';
if ($search)  $where .= " AND (p.name LIKE '%$search%' OR u.name LIKE '%$search%' OR p.breed LIKE '%$search%')";
if ($species) $where .= " AND p.species='$species'";

$pets = getRows($conn,
    "SELECT p.*, u.name AS owner_name, u.contact_no AS owner_contact
     FROM pets p
     LEFT JOIN users u ON p.user_id = u.id
     WHERE $where
     ORDER BY p.name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Records — Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Pet detail layout */
        .pet-detail-top {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .pet-large-photo {
            width: 160px; height: 160px;
            border-radius: var(--radius);
            object-fit: cover;
            box-shadow: var(--shadow);
        }

        .pet-photo-placeholder {
            width: 160px; height: 160px;
            border-radius: var(--radius);
            background: var(--teal-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 56px;
            box-shadow: var(--shadow);
        }

        .pet-info-box {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 20px 24px;
            box-shadow: var(--shadow);
            position: relative;
        }

        .pet-info-box h2 {
            font-family: var(--font-head);
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 14px;
        }

        .pet-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 20px;
        }

        .pi-row {
            display: flex;
            gap: 8px;
            font-size: 13px;
        }
        .pi-label {
            color: var(--text-light);
            font-weight: 600;
            width: 70px;
            flex-shrink: 0;
        }
        .pi-value { font-weight: 700; color: var(--text-dark); }

        /* Medical record cards */
        .med-card {
            background: rgba(255,255,255,0.9);
            border-radius: var(--radius);
            padding: 18px;
            box-shadow: var(--shadow);
        }

        .med-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .med-card-title {
            font-weight: 800;
            font-size: 14px;
            color: var(--blue-header);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .med-entry {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .med-entry:last-of-type { border-bottom: none; }

        .med-entry-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .med-entry-name { font-weight: 700; font-size: 13px; }
        .med-entry-detail {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 2px;
        }

        .med-entry-actions {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
            margin-left: 8px;
        }

        .med-action-btn {
            padding: 2px 8px;
            font-size: 11px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 700;
            transition: var(--transition);
        }
        .med-edit-btn   { background: #e0f7fa; color: var(--teal-dark); }
        .med-delete-btn { background: #fee2e2; color: #dc2626; }
        .med-edit-btn:hover   { background: var(--teal); color: #fff; }
        .med-delete-btn:hover { background: #ef4444; color: #fff; }

        /* Vaccine badge colors */
        .vaccine-dates {
            display: flex;
            gap: 12px;
            font-size: 12px;
            margin-top: 4px;
        }
        .vd-item { display: flex; flex-direction: column; gap: 1px; }
        .vd-label { color: var(--text-light); font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .vd-value { font-weight: 700; color: var(--text-dark); }

        /* Overdue vaccine highlight */
        .vaccine-overdue { color: #ef4444 !important; }
        .vaccine-soon    { color: #f59e0b !important; }
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
                PET RECORDS
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

            <?php if ($view_pet): ?>
            <!-- ══ SINGLE PET VIEW ════════════════════════════ -->
            <div style="margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;">
                <a href="pet_records.php" class="btn btn-gray btn-sm">← Back to Pets</a>
                <?php if ($pet_owner): ?>
                    <span style="font-size:13px;color:var(--text-light);">
                        Owner:
                        <a href="client_records.php?view=<?= $pet_owner['id'] ?>"
                           style="color:var(--teal-dark);font-weight:700;">
                            <?= htmlspecialchars($pet_owner['name']) ?>
                        </a>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Pet Header -->
            <div class="pet-detail-top">
                <div>
                    <?php if ($view_pet['photo']): ?>
                        <?php if ($view_pet['photo']): ?>
                        <img src="../assets/css/images/pets/<?= htmlspecialchars($view_pet['photo']) ?>"
                             class="pet-large-photo"
                             alt="<?= htmlspecialchars($view_pet['name']) ?>">
                    <?php else: ?>
                        <div class="pet-photo-placeholder">
                            <?= $view_pet['species']==='cat' ? '🐱' : '🐶' ?>
                        </div>
                    <?php endif; ?>






                    <?php else: ?>
                        <div class="pet-photo-placeholder">
                            <?= $view_pet['species']==='cat' ? '🐱' : '🐶' ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pet-info-box">
                    <button style="position:absolute;top:14px;right:14px;background:none;border:none;
                                   font-size:18px;cursor:pointer;color:var(--teal-dark);"
                            onclick="document.getElementById('editPetModal').classList.add('open')" title="Edit">✏️</button>
                    <h2><?= strtoupper(htmlspecialchars($view_pet['name'])) ?></h2>
                    <div class="pet-info-grid">
                        <div class="pi-row">
                            <span class="pi-label">Species:</span>
                            <span class="pi-value"><?= ucfirst($view_pet['species']) ?></span>
                        </div>
                        <div class="pi-row">
                            <span class="pi-label">Age:</span>
                            <span class="pi-value"><?= htmlspecialchars($view_pet['age'] ?? '—') ?> years old</span>
                        </div>
                        <div class="pi-row">
                            <span class="pi-label">Gender:</span>
                            <span class="pi-value"><?= ucfirst($view_pet['gender']) ?></span>
                        </div>
                        <div class="pi-row">
                            <span class="pi-label">Weight:</span>
                            <span class="pi-value"><?= $view_pet['weight'] ? $view_pet['weight'].' kg' : '—' ?></span>
                        </div>
                        <div class="pi-row">
                            <span class="pi-label">Breed:</span>
                            <span class="pi-value"><?= htmlspecialchars($view_pet['breed'] ?? '—') ?></span>
                        </div>
                        <div class="pi-row">
                            <span class="pi-label">Color:</span>
                            <span class="pi-value"><?= htmlspecialchars($view_pet['color'] ?? '—') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Medical Records heading -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h2 style="font-family:var(--font-head);font-weight:800;font-size:18px;">
                    MEDICAL RECORDS
                </h2>
            </div>

            <!-- 3-column medical grid -->
            <div class="medical-grid">

                <!-- ── MEDICATIONS ── -->
                <div class="med-card">
                    <div class="med-card-header">
                        <div class="med-card-title">💊 MEDICATIONS</div>
                        <button class="btn btn-teal btn-sm"
                                onclick="document.getElementById('addMedModal').classList.add('open')">+ Add</button>
                    </div>

                    <?php if (empty($meds)): ?>
                        <p style="font-size:12px;color:var(--text-light);">No medications recorded.</p>
                    <?php else: ?>
                        <?php foreach ($meds as $med): ?>
                            <div class="med-entry">
                                <div class="med-entry-top">
                                    <div>
                                        <div class="med-entry-name">
                                            <?= htmlspecialchars($med['medication_name']) ?>
                                        </div>
                                       <div class="med-entry-detail">
    <?= htmlspecialchars($med['dosage']) ?>
    <?= $med['frequency'] ? ', '.$med['frequency'] : '' ?>
</div>
<?php if ($med['notes']): ?>
    <div class="med-entry-detail" style="color:var(--text-mid);">
        📝 <?= htmlspecialchars($med['notes']) ?>
    </div>
<?php endif; ?>
<?php if ($med['prescribed_by'] || $med['prescription_date']): ?>
    <div style="margin-top:6px;padding:6px 10px;background:#f0fffe;
                border-left:3px solid var(--teal);border-radius:0 6px 6px 0;font-size:11px;">
        <?php if ($med['prescribed_by']): ?>
            <div style="color:var(--teal-dark);font-weight:700;">
                👨‍⚕️ Prescribed by: <?= htmlspecialchars($med['prescribed_by']) ?>
            </div>
        <?php endif; ?>
        <?php if ($med['prescription_date']): ?>
            <div style="color:var(--text-light);margin-top:2px;">
                📅 Date: <?= date('M d, Y', strtotime($med['prescription_date'])) ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
                                    </div>
                                    <div class="med-entry-actions">
                                        <button class="med-action-btn med-edit-btn"
                                                onclick='openEditMed(<?= json_encode($med) ?>)'>Edit</button>
                                        <form method="POST" style="display:inline;"
                                              onsubmit="return confirm('Delete this medication?')">
                                            <input type="hidden" name="action"  value="delete_medication">
                                            <input type="hidden" name="med_id"  value="<?= $med['id'] ?>">
                                            <input type="hidden" name="pet_id"  value="<?= $view_id ?>">
                                            <button type="submit" class="med-action-btn med-delete-btn">Del</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- ── VACCINES ── -->
                <div class="med-card">
                    <div class="med-card-header">
                        <div class="med-card-title">💉 VACCINES</div>
                        <button class="btn btn-teal btn-sm"
                                onclick="document.getElementById('addVacModal').classList.add('open')">+ Add</button>
                    </div>

                    <?php if (empty($vaccines)): ?>
                        <p style="font-size:12px;color:var(--text-light);">No vaccine records.</p>
                    <?php else: ?>
                        <?php foreach ($vaccines as $vac):
                            $today    = date('Y-m-d');
                            $due_cls  = '';
                            if ($vac['next_due']) {
                                if ($vac['next_due'] < $today) $due_cls = 'vaccine-overdue';
                                elseif ($vac['next_due'] <= date('Y-m-d', strtotime('+30 days'))) $due_cls = 'vaccine-soon';
                            }
                        ?>
                            <div class="med-entry">
                                <div class="med-entry-top">
                                    <div>
                                        <div class="med-entry-name">
                                            <?= htmlspecialchars($vac['vaccine_name']) ?>
                                        </div>
                                        <div class="vaccine-dates">
                                            <div class="vd-item">
                                                <span class="vd-label">Given</span>
                                                <span class="vd-value">
                                                    <?= $vac['date_given'] ? date('m/d/y',strtotime($vac['date_given'])) : '—' ?>
                                                </span>
                                            </div>
                                            <div class="vd-item">
                                                <span class="vd-label">Next Due</span>
                                                <span class="vd-value <?= $due_cls ?>">
                                                    <?= $vac['next_due'] ? date('m/d/y',strtotime($vac['next_due'])) : '—' ?>
                                                    <?= $due_cls==='vaccine-overdue' ? ' ⚠️' : ($due_cls==='vaccine-soon' ? ' ⏰' : '') ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="med-entry-actions">
                                        <button class="med-action-btn med-edit-btn"
                                                onclick='openEditVac(<?= json_encode($vac) ?>)'>Edit</button>
                                        <form method="POST" style="display:inline;"
                                              onsubmit="return confirm('Delete this vaccine record?')">
                                            <input type="hidden" name="action"  value="delete_vaccine">
                                            <input type="hidden" name="vac_id"  value="<?= $vac['id'] ?>">
                                            <input type="hidden" name="pet_id"  value="<?= $view_id ?>">
                                            <button type="submit" class="med-action-btn med-delete-btn">Del</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- ── ALLERGIES ── -->
                <div class="med-card">
                    <div class="med-card-header">
                        <div class="med-card-title">🌿 ALLERGIES</div>
                        <button class="btn btn-teal btn-sm"
                                onclick="document.getElementById('addAllergyModal').classList.add('open')">+ Add</button>
                    </div>

                    <?php if (empty($allergies)): ?>
                        <p style="font-size:12px;color:var(--text-light);">No allergy records.</p>
                    <?php else: ?>
                        <?php foreach ($allergies as $alg): ?>
                            <div class="med-entry">
                                <div class="med-entry-top">
                                    <div>
                                        <div class="med-entry-name">
                                            <?= htmlspecialchars($alg['allergen']) ?>
                                        </div>
                                        <div class="med-entry-detail">
                                            Reaction: <strong><?= htmlspecialchars($alg['reaction']) ?></strong>
                                        </div>
                                    </div>
                                    <div class="med-entry-actions">
                                        <button class="med-action-btn med-edit-btn"
                                                onclick='openEditAllergy(<?= json_encode($alg) ?>)'>Edit</button>
                                        <form method="POST" style="display:inline;"
                                              onsubmit="return confirm('Delete allergy?')">
                                            <input type="hidden" name="action"     value="delete_allergy">
                                            <input type="hidden" name="allergy_id" value="<?= $alg['id'] ?>">
                                            <input type="hidden" name="pet_id"     value="<?= $view_id ?>">
                                            <button type="submit" class="med-action-btn med-delete-btn">Del</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div><!-- /medical-grid -->

            <!-- ══ PREVIOUS CONSULTATIONS ═══════════════════ -->
            <div style="background:rgba(255,255,255,0.9);border-radius:var(--radius);
                        padding:20px;box-shadow:var(--shadow);margin-top:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h3 style="font-family:var(--font-head);font-weight:800;font-size:17px;">
                        📋 Previous Consultations
                    </h3>
                    <button class="btn btn-teal btn-sm" onclick="document.getElementById('addConsultModal').classList.add('open')">+ Add</button>
                </div>

                <?php if (empty($consultations)): ?>
                    <p style="font-size:13px;color:var(--text-light);">No consultation records yet.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table style="width:100%;font-size:13px;border-collapse:collapse;">
                            <thead>
                                <tr style="background:var(--teal-light);">
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Date of Visit</th>
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Reason for Visit</th>
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Diagnosis</th>
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Treatment Given</th>
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Veterinarian</th>
                                    <th style="padding:8px 12px;text-align:center;color:var(--teal-dark);font-size:12px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultations as $con): ?>
                                    <tr style="border-bottom:1px solid var(--border);">
                                        <td style="padding:8px 12px;"><?= formatDate($con['date_of_visit']) ?></td>
                                        <td style="padding:8px 12px;"><?= htmlspecialchars($con['reason_for_visit'] ?? '—') ?></td>
                                        <td style="padding:8px 12px;"><?= htmlspecialchars($con['diagnosis'] ?? '—') ?></td>
                                        <td style="padding:8px 12px;"><?= htmlspecialchars($con['treatment_given'] ?? '—') ?></td>
                                        <td style="padding:8px 12px;"><?= htmlspecialchars($con['vet_name'] ?? '—') ?></td>
                                        <td style="padding:8px 12px;text-align:center;">
                                            <form method="POST" style="display:inline;"
                                                  onsubmit="return confirm('Delete this consultation?')">
                                                <input type="hidden" name="action"     value="delete_consultation">
                                                <input type="hidden" name="consult_id" value="<?= $con['id'] ?>">
                                                <input type="hidden" name="pet_id"     value="<?= $view_id ?>">
                                                <button type="submit" class="btn btn-red btn-sm">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ══ MEDICAL HISTORY ═══════════════════════════ -->
            <div style="background:rgba(255,255,255,0.9);border-radius:var(--radius);
                        padding:20px;box-shadow:var(--shadow);margin-top:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h3 style="font-family:var(--font-head);font-weight:800;font-size:17px;">
                        🏥 Medical History
                    </h3>
                    <button class="btn btn-teal btn-sm" onclick="document.getElementById('editMedHistModal').classList.add('open')">✏️ Edit</button>
                </div>
                <?php $mh = $med_history; ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="background:#f8fffe;border-radius:var(--radius-sm);padding:14px;border:1px solid var(--teal-light);">
                        <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Clinic</div>
                        <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($mh['clinic_name'] ?? '—') ?></div>
                    </div>
                    <div style="background:#f8fffe;border-radius:var(--radius-sm);padding:14px;border:1px solid var(--teal-light);">
                        <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Past Illnesses</div>
                        <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($mh['past_illnesses'] ?? '—') ?></div>
                    </div>
                    <div style="background:#f8fffe;border-radius:var(--radius-sm);padding:14px;border:1px solid var(--teal-light);">
                        <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Chronic Conditions</div>
                        <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($mh['chronic_conditions'] ?? 'None') ?></div>
                    </div>
                    <div style="background:#f8fffe;border-radius:var(--radius-sm);padding:14px;border:1px solid var(--teal-light);">
                        <div style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Injuries/Surgeries</div>
                        <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($mh['injuries_surgeries'] ?? 'None') ?></div>
                    </div>
                </div>
            </div>

            <!-- ══ VACCINATION CERTIFICATE (Booklet) ═════════ -->
            <div style="background:rgba(255,255,255,0.9);border-radius:var(--radius);
                        padding:20px;box-shadow:var(--shadow);margin-top:20px;">
                <h3 style="font-family:var(--font-head);font-weight:800;font-size:17px;
                            text-align:center;margin-bottom:16px;">
                    💉 VACCINATION CERTIFICATE (Booklet)
                </h3>
                <?php
                $vac_booklet = getRows($conn,
                    "SELECT v.*, 
                            COALESCE(v.notes,'') AS manufacturer
                     FROM pet_vaccines v
                     WHERE v.pet_id=$view_id
                     ORDER BY v.date_given ASC");
                ?>
                <?php if (empty($vac_booklet)): ?>
                    <p style="font-size:13px;color:var(--text-light);text-align:center;">No vaccination records.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table style="width:100%;font-size:13px;border-collapse:collapse;">
                            <thead>
                                <tr style="background:var(--teal-light);">
                                    <th style="padding:8px 10px;text-align:center;color:var(--teal-dark);font-size:11px;">No.</th>
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Date</th>
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Wt. kg</th>
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Against</th>
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Manufacturer</th>
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Due Date</th>
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Veterinarian Name</th>
                                    <th style="padding:8px 10px;text-align:center;color:var(--teal-dark);font-size:11px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vac_booklet as $vi => $vb): ?>
                                    <tr style="border-bottom:1px solid var(--border);<?= $vi%2===0?'background:#fafffe;':'' ?>">
                                        <td style="padding:8px 10px;text-align:center;color:var(--text-light);"><?= $vi+1 ?></td>
                                        <td style="padding:8px 10px;"><?= $vb['date_given'] ? date('m/d/y',strtotime($vb['date_given'])) : '—' ?></td>
                                        <td style="padding:8px 10px;"><?= $view_pet['weight'] ? $view_pet['weight'].' kg' : '—' ?></td>
                                        <td style="padding:8px 10px;font-weight:700;"><?= htmlspecialchars($vb['vaccine_name']) ?></td>
                                        <td style="padding:8px 10px;color:var(--text-mid);"><?= htmlspecialchars($vb['notes'] ?? '—') ?></td>
                                        <td style="padding:8px 10px;"><?= $vb['next_due'] ? date('m/d/y',strtotime($vb['next_due'])) : '—' ?></td>
                                        <td style="padding:8px 10px;">Dr. Ann</td>
                                        <td style="padding:8px 10px;text-align:center;">
                                            <button class="med-action-btn med-edit-btn"
                                                    onclick='openEditVac(<?= json_encode($vb) ?>)'>Edit</button>
                                            <form method="POST" style="display:inline;"
                                                  onsubmit="return confirm('Delete vaccine?')">
                                                <input type="hidden" name="action" value="delete_vaccine">
                                                <input type="hidden" name="vac_id" value="<?= $vb['id'] ?>">
                                                <input type="hidden" name="pet_id" value="<?= $view_id ?>">
                                                <button type="submit" class="med-action-btn med-delete-btn">Del</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── MODALS ─────────────────────────────────── -->

            <!-- Edit Pet -->
            <div class="modal-overlay" id="editPetModal">
                <div class="modal">
                    <button class="modal-close" onclick="document.getElementById('editPetModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">Edit Pet Info</h3>
                    <form method="POST">
                        <input type="hidden" name="action"  value="edit_pet">
                        <input type="hidden" name="pet_id"  value="<?= $view_pet['id'] ?>">
                        <div class="form-group">
                            <label>Pet Name *</label>
                            <input type="text" name="pet_name"
                                   value="<?= htmlspecialchars($view_pet['name']) ?>" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Species</label>
                                <select name="species">
                                    <option value="dog"   <?= $view_pet['species']==='dog'   ? 'selected':'' ?>>Dog</option>
                                    <option value="cat"   <?= $view_pet['species']==='cat'   ? 'selected':'' ?>>Cat</option>
                                    <option value="other" <?= $view_pet['species']==='other' ? 'selected':'' ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Breed</label>
                                <input type="text" name="breed"
                                       value="<?= htmlspecialchars($view_pet['breed'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Age (yrs)</label>
                                <input type="text" name="age"
                                       value="<?= htmlspecialchars($view_pet['age'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="gender">
                                    <option value="male"   <?= $view_pet['gender']==='male'   ? 'selected':'' ?>>Male</option>
                                    <option value="female" <?= $view_pet['gender']==='female' ? 'selected':'' ?>>Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Weight (kg)</label>
                                <input type="number" step="0.01" name="weight"
                                       value="<?= $view_pet['weight'] ?>">
                            </div>
                            <div class="form-group">
                                <label>Color</label>
                                <input type="text" name="color"
                                       value="<?= htmlspecialchars($view_pet['color'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray"
                                    onclick="document.getElementById('editPetModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Medication -->
            <div class="modal-overlay" id="addMedModal">
                <div class="modal">
                    <button class="modal-close" onclick="document.getElementById('addMedModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">💊 Add Medication</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_medication">
                        <input type="hidden" name="pet_id" value="<?= $view_id ?>">
                        <div class="form-group">
                            <label>Medication Name *</label>
                            <input type="text" name="med_name" required placeholder="e.g. Amoxicillin">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Dosage</label>
                                <input type="text" name="dosage" placeholder="e.g. 5 mg">
                            </div>
                            <div class="form-group">
                                <label>Frequency</label>
                                <input type="text" name="frequency" placeholder="e.g. Twice a Day">
                            </div>
                        </div>
                      <div class="form-group">
    <label>Notes</label>
    <textarea name="notes" rows="2" placeholder="Optional notes..."></textarea>
</div>
<div class="form-row">
    <div class="form-group">
        <label>Prescribed By</label>
        <input type="text" name="prescribed_by" placeholder="e.g. Dr. Ann" value="Dr. Ann">
    </div>
    <div class="form-group">
        <label>Prescription Date</label>
        <input type="date" name="prescription_date" value="<?= date('Y-m-d') ?>">
    </div>
</div>
<div class="modal-actions">
    <button type="button" class="btn btn-gray"
            onclick="document.getElementById('addMedModal').classList.remove('open')">Cancel</button>
    <button type="submit" class="btn btn-teal">Add Medication</button>
</div>
                    </form>
                </div>
            </div>

            <!-- Edit Medication -->
            <div class="modal-overlay" id="editMedModal">
                <div class="modal">
                    <button class="modal-close" onclick="document.getElementById('editMedModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">💊 Edit Medication</h3>
                    <form method="POST">
                        <input type="hidden" name="action"  value="edit_medication">
                        <input type="hidden" name="pet_id"  value="<?= $view_id ?>">
                        <input type="hidden" name="med_id"  id="edit_med_id">
                        <div class="form-group">
                            <label>Medication Name *</label>
                            <input type="text" name="med_name" id="edit_med_name" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Dosage</label>
                                <input type="text" name="dosage" id="edit_med_dose">
                            </div>
                            <div class="form-group">
                                <label>Frequency</label>
                                <input type="text" name="frequency" id="edit_med_freq">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" rows="2" id="edit_med_notes"></textarea>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray"
                                    onclick="document.getElementById('editMedModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Vaccine -->
            <div class="modal-overlay" id="addVacModal">
                <div class="modal">
                    <button class="modal-close" onclick="document.getElementById('addVacModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">💉 Add Vaccine Record</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_vaccine">
                        <input type="hidden" name="pet_id" value="<?= $view_id ?>">
                        <div class="form-group">
                            <label>Vaccine Name *</label>
                            <input type="text" name="vac_name" required placeholder="e.g. Rabies, DHPP">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date Given</label>
                                <input type="date" name="date_given">
                            </div>
                            <div class="form-group">
                                <label>Next Due Date</label>
                                <input type="date" name="next_due">
                            </div>
                        </div>
                        <div class="form-group">
    <label>Notes</label>
    <textarea name="notes" rows="2" id="edit_med_notes"></textarea>
</div>
<div class="form-row">
    <div class="form-group">
        <label>Prescribed By</label>
        <input type="text" name="prescribed_by" id="edit_med_prescby" placeholder="e.g. Dr. Ann">
    </div>
    <div class="form-group">
        <label>Prescription Date</label>
        <input type="date" name="prescription_date" id="edit_med_prescdt">
    </div>
</div>
<div class="modal-actions">
    <button type="button" class="btn btn-gray"
            onclick="document.getElementById('editMedModal').classList.remove('open')">Cancel</button>
    <button type="submit" class="btn btn-teal">Save Changes</button>
</div>
                    </form>
                </div>
            </div>

            <!-- Edit Vaccine -->
            <div class="modal-overlay" id="editVacModal">
                <div class="modal">
                    <button class="modal-close" onclick="document.getElementById('editVacModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">💉 Edit Vaccine Record</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_vaccine">
                        <input type="hidden" name="pet_id" value="<?= $view_id ?>">
                        <input type="hidden" name="vac_id" id="edit_vac_id">
                        <div class="form-group">
                            <label>Vaccine Name *</label>
                            <input type="text" name="vac_name" id="edit_vac_name" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date Given</label>
                                <input type="date" name="date_given" id="edit_vac_given">
                            </div>
                            <div class="form-group">
                                <label>Next Due Date</label>
                                <input type="date" name="next_due" id="edit_vac_due">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" rows="2" id="edit_vac_notes"></textarea>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray"
                                    onclick="document.getElementById('editVacModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Allergy -->
            <div class="modal-overlay" id="addAllergyModal">
                <div class="modal">
                    <button class="modal-close" onclick="document.getElementById('addAllergyModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">🌿 Add Allergy</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_allergy">
                        <input type="hidden" name="pet_id" value="<?= $view_id ?>">
                        <div class="form-group">
                            <label>Allergen *</label>
                            <input type="text" name="allergen" required placeholder="e.g. Pollen, Bees, Chicken">
                        </div>
                        <div class="form-group">
                            <label>Reaction</label>
                            <input type="text" name="reaction" placeholder="e.g. Itchy nose and watery eyes">
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray"
                                    onclick="document.getElementById('addAllergyModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Add Allergy</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Allergy -->
            <div class="modal-overlay" id="editAllergyModal">
                <div class="modal">
                    <button class="modal-close" onclick="document.getElementById('editAllergyModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">🌿 Edit Allergy</h3>
                    <form method="POST">
                        <input type="hidden" name="action"     value="edit_allergy">
                        <input type="hidden" name="pet_id"     value="<?= $view_id ?>">
                        <input type="hidden" name="allergy_id" id="edit_allergy_id">
                        <div class="form-group">
                            <label>Allergen *</label>
                            <input type="text" name="allergen" id="edit_allergen" required>
                        </div>
                        <div class="form-group">
                            <label>Reaction</label>
                            <input type="text" name="reaction" id="edit_reaction">
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray"
                                    onclick="document.getElementById('editAllergyModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- ══ PET LIST ════════════════════════════════════ -->

            <!-- Stats -->
            <div class="stat-cards" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
                <div class="stat-card">
                    <div class="stat-label">Total Pet</div>
                    <div class="stat-value"><?= $total_pets ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Dog</div>
                    <div class="stat-value"><?= $total_dogs ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Cat</div>
                    <div class="stat-value"><?= $total_cats ?></div>
                </div>
            </div>

            <!-- Table -->
            <div style="background:rgba(255,255,255,0.9);border-radius:var(--radius);
                        padding:20px;box-shadow:var(--shadow);">
                <h3 style="font-family:var(--font-head);font-weight:700;
                            font-size:17px;margin-bottom:16px;">Pets</h3>

                <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
                    <div class="search-box">
                        <span>🔍</span>
                        <input type="text" name="search"
                               placeholder="Search Pet..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <select name="species"
                            style="padding:8px 12px;border:1.5px solid var(--border);
                                   border-radius:var(--radius-sm);font-size:13px;
                                   background:#fff;outline:none;">
                        <option value="">All Species</option>
                        <option value="dog" <?= $species==='dog' ? 'selected':'' ?>>🐶 Dogs</option>
                        <option value="cat" <?= $species==='cat' ? 'selected':'' ?>>🐱 Cats</option>
                    </select>
                    <button type="submit" class="btn btn-teal btn-sm">Search</button>
                    <a href="pet_records.php" class="btn btn-gray btn-sm">Reset</a>
                </form>

                <?php if (empty($pets)): ?>
                    <div class="empty-state"><div class="empty-icon">🐾</div><h3>No pets found</h3></div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Pet Name</th>
                                    <th>Sex/Age</th>
                                    <th>Breed</th>
                                    <th>Owner</th>
                                    <th>Contact No.</th>
                                    <th>Status</th>
                                    <th>Last Visit</th>
                                    <th>Details</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pets as $i => $pet): ?>
                                    <?php
                                    $last_visit = getRow($conn,
                                        "SELECT MAX(appointment_date) AS lv FROM appointments
                                         WHERE pet_id={$pet['id']} AND status='completed'");
                                    ?>
                                    <tr>
                                        <td style="color:var(--text-light);"><?= $i+1 ?></td>
                                        <td>
    <div style="display:flex;align-items:center;gap:8px;">
        <?php if ($pet['photo']): ?>
            <img src="../assets/css/images/pets/<?= htmlspecialchars($pet['photo']) ?>"
                 style="width:32px;height:32px;border-radius:50%;object-fit:cover;"
                 onerror="this.style.display='none'">
        <?php else: ?>
            <div style="width:32px;height:32px;border-radius:50%;background:var(--teal-light);
                        display:flex;align-items:center;justify-content:center;font-size:16px;">
                <?= $pet['species']==='cat' ? '🐱' : '🐶' ?>
            </div>
        <?php endif; ?>
        <strong><?= htmlspecialchars($pet['name']) ?></strong>
    </div>
</td>
                                        <td><?= strtoupper(substr($pet['gender'],0,1)) ?>/<?= $pet['age'] ?> yrs</td>
                                        <td><?= htmlspecialchars($pet['breed'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($pet['owner_name'] ?? '—') ?></td>
                                        <td style="font-size:12px;"><?= htmlspecialchars($pet['owner_contact'] ?? '—') ?></td>
                                        <td><?= statusBadge($pet['status']) ?></td>
                                        <td style="font-size:12px;">
                                            <?= $last_visit['lv'] ? formatDate($last_visit['lv']) : '—' ?>
                                        </td>
                                        <td>
                                            <a href="pet_records.php?view=<?= $pet['id'] ?>"
                                               class="btn btn-teal btn-sm">View Profile</a>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:4px;">
                                                <a href="pet_records.php?view=<?= $pet['id'] ?>"
                                                   class="btn btn-gray btn-sm">Edit</a>
                                                <a href="?delete_pet=<?= $pet['id'] ?>"
                                                   onclick="return confirm('Delete this pet record?')"
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
            <?php endif; // end view_pet / list ?>

        </div>
    </div>
</div>

<?php
// Handle delete pet
if (isset($_GET['delete_pet']) && is_numeric($_GET['delete_pet'])) {
    $dpid = (int)$_GET['delete_pet'];
    mysqli_query($conn, "DELETE FROM pets WHERE id=$dpid");
    redirect('pet_records.php?success=Pet deleted.');
}
?>

<!-- Add Consultation Modal -->
<div class="modal-overlay" id="addConsultModal">
    <div class="modal" style="max-width:540px;">
        <button class="modal-close" onclick="document.getElementById('addConsultModal').classList.remove('open')">×</button>
        <h3 class="modal-title">📋 Add Consultation</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_consultation">
            <input type="hidden" name="pet_id" value="<?= $view_id ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Date of Visit *</label>
                    <input type="date" name="date_of_visit" required>
                </div>
                <div class="form-group">
                    <label>Veterinarian</label>
                    <input type="text" name="vet_name" placeholder="e.g. Dr. Ann" value="Dr. Ann">
                </div>
            </div>
            <div class="form-group">
                <label>Reason for Visit</label>
                <input type="text" name="reason_for_visit" placeholder="e.g. Loss of appetite">
            </div>
            <div class="form-group">
                <label>Diagnosis</label>
                <input type="text" name="diagnosis" placeholder="e.g. Mild gastrointestinal infection">
            </div>
            <div class="form-group">
                <label>Treatment Given</label>
                <textarea name="treatment_given" rows="2" placeholder="e.g. Prescribed antibiotics and vitamins"
                          style="resize:vertical;"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray" onclick="document.getElementById('addConsultModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">Save Consultation</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Medical History Modal -->
<div class="modal-overlay" id="editMedHistModal">
    <div class="modal" style="max-width:500px;">
        <button class="modal-close" onclick="document.getElementById('editMedHistModal').classList.remove('open')">×</button>
        <h3 class="modal-title">🏥 Edit Medical History</h3>
        <form method="POST">
            <input type="hidden" name="action" value="save_medical_history">
            <input type="hidden" name="pet_id" value="<?= $view_id ?>">
            <div class="form-group">
                <label>Clinic Name</label>
                <input type="text" name="clinic_name" placeholder="e.g. Pawprint Veterinary"
                       value="<?= htmlspecialchars($med_history['clinic_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Past Illnesses</label>
                <input type="text" name="past_illnesses" placeholder="e.g. Gastrointestinal infection"
                       value="<?= htmlspecialchars($med_history['past_illnesses'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Chronic Conditions</label>
                <input type="text" name="chronic_conditions" placeholder="e.g. None"
                       value="<?= htmlspecialchars($med_history['chronic_conditions'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Injuries/Surgeries</label>
                <input type="text" name="injuries_surgeries" placeholder="e.g. None"
                       value="<?= htmlspecialchars($med_history['injuries_surgeries'] ?? '') ?>">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-gray" onclick="document.getElementById('editMedHistModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-teal">Save History</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
function openEditMed(med) {
    document.getElementById('edit_med_id').value      = med.id;
    document.getElementById('edit_med_name').value    = med.medication_name;
    document.getElementById('edit_med_dose').value    = med.dosage       || '';
    document.getElementById('edit_med_freq').value    = med.frequency    || '';
    document.getElementById('edit_med_notes').value   = med.notes        || '';
    document.getElementById('edit_med_prescby').value = med.prescribed_by  || '';
    document.getElementById('edit_med_prescdt').value = med.prescription_date || '';
    document.getElementById('editMedModal').classList.add('open');
}
function openEditVac(vac) {
    document.getElementById('edit_vac_id').value    = vac.id;
    document.getElementById('edit_vac_name').value  = vac.vaccine_name;
    document.getElementById('edit_vac_given').value = vac.date_given || '';
    document.getElementById('edit_vac_due').value   = vac.next_due   || '';
    document.getElementById('edit_vac_notes').value = vac.notes      || '';
    document.getElementById('editVacModal').classList.add('open');
}

function openEditAllergy(alg) {
    document.getElementById('edit_allergy_id').value = alg.id;
    document.getElementById('edit_allergen').value   = alg.allergen;
    document.getElementById('edit_reaction').value   = alg.reaction || '';
    document.getElementById('editAllergyModal').classList.add('open');
}

// Close modal on outside click
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