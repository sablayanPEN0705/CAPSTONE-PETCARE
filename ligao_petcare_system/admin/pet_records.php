<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: admin/pet_records.php
// Purpose: View all pets + edit medical records + documents
// ============================================================
require_once '../includes/auth.php';
requireLogin();
requireAdmin();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// ── Document upload directory (relative to this file) ────────
define('DOC_UPLOAD_DIR', '../assets/pet_documents/');
define('DOC_UPLOAD_URL', '../assets/pet_documents/');
define('DOC_MAX_SIZE',   20 * 1024 * 1024); // 20 MB
define('DOC_ALLOWED_MIME', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
]);

if (!is_dir(DOC_UPLOAD_DIR)) {
    mkdir(DOC_UPLOAD_DIR, 0755, true);
}

// ── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Upload Document ──────────────────────────────────────
    if ($action === 'upload_document') {
        $pid      = (int)$_POST['pet_id'];
        $title    = sanitize($conn, $_POST['doc_title']    ?? '');
        $doc_type = sanitize($conn, $_POST['doc_type']     ?? 'general');
        $notes    = sanitize($conn, $_POST['doc_notes']    ?? '');
        $admin_id = (int)$_SESSION['user_id'];

        if (!$pid || empty($_FILES['doc_file']['name'])) {
            redirect("pet_records.php?error=Please select a file to upload.&view=$pid");
        }

        $file    = $_FILES['doc_file'];
        $orig    = basename($file['name']);
        $size    = (int)$file['size'];
        $tmp     = $file['tmp_name'];
        $mime    = mime_content_type($tmp);

        if ($size > DOC_MAX_SIZE) {
            redirect("pet_records.php?error=File too large. Maximum size is 20 MB.&view=$pid");
        }
        if (!in_array($mime, DOC_ALLOWED_MIME)) {
            redirect("pet_records.php?error=File type not allowed. Accepted: images, PDF, Word, Excel, TXT.&view=$pid");
        }

        $ext       = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $safe_name = 'doc_' . $pid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest      = DOC_UPLOAD_DIR . $safe_name;

        if (!move_uploaded_file($tmp, $dest)) {
            redirect("pet_records.php?error=Failed to save file. Check server permissions.&view=$pid");
        }

        $title_db = $title ?: sanitize($conn, $orig);
        $orig_db  = sanitize($conn, $orig);
        $mime_db  = sanitize($conn, $mime);

        mysqli_query($conn,
            "INSERT INTO pet_documents
                (pet_id, uploaded_by, document_type, title, file_name, original_name, file_size, mime_type, notes)
             VALUES
                ($pid, $admin_id, '$doc_type', '$title_db', '$safe_name', '$orig_db', $size, '$mime_db', '$notes')"
        );

        redirect("pet_records.php?success=Document uploaded successfully.&view=$pid");
    }

    // ── Edit Document Meta ───────────────────────────────────
    if ($action === 'edit_document') {
        $doc_id   = (int)$_POST['doc_id'];
        $pid      = (int)$_POST['pet_id'];
        $title    = sanitize($conn, $_POST['doc_title'] ?? '');
        $doc_type = sanitize($conn, $_POST['doc_type']  ?? 'general');
        $notes    = sanitize($conn, $_POST['doc_notes'] ?? '');
        mysqli_query($conn,
            "UPDATE pet_documents
             SET title='$title', document_type='$doc_type', notes='$notes'
             WHERE id=$doc_id AND pet_id=$pid"
        );
        redirect("pet_records.php?success=Document updated.&view=$pid");
    }

    // ── Delete Document ──────────────────────────────────────
    if ($action === 'delete_document') {
        $doc_id = (int)$_POST['doc_id'];
        $pid    = (int)$_POST['pet_id'];
        $doc    = getRow($conn, "SELECT * FROM pet_documents WHERE id=$doc_id AND pet_id=$pid");
        if ($doc) {
            $file_path = DOC_UPLOAD_DIR . $doc['file_name'];
            if (file_exists($file_path)) unlink($file_path);
            mysqli_query($conn, "DELETE FROM pet_documents WHERE id=$doc_id AND pet_id=$pid");
        }
        redirect("pet_records.php?success=Document deleted.&view=$pid");
    }

    // ── Medication CRUD ──────────────────────────────────────
    if ($action === 'add_medication') {
        $pid     = (int)$_POST['pet_id'];
        $name    = sanitize($conn, $_POST['med_name']           ?? '');
        $dose    = sanitize($conn, $_POST['dosage']             ?? '');
        $freq    = sanitize($conn, $_POST['frequency']          ?? '');
        $note    = sanitize($conn, $_POST['notes']              ?? '');
        $prescby = sanitize($conn, $_POST['prescribed_by']      ?? '');
        $prescdt = sanitize($conn, $_POST['prescription_date']  ?? '');
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
        $name  = sanitize($conn, $_POST['vac_name']   ?? '');
        $given = sanitize($conn, $_POST['date_given'] ?? '');
        $due   = sanitize($conn, $_POST['next_due']   ?? '');
        $note  = sanitize($conn, $_POST['notes']      ?? '');
        if ($pid && $name) {
            mysqli_query($conn,
                "INSERT INTO pet_vaccines (pet_id,vaccine_name,date_given,next_due,notes)
                 VALUES ($pid,'$name'," .
                ($given ? "'$given'" : 'NULL') . "," .
                ($due   ? "'$due'"   : 'NULL') . ",'$note')");
            $pet = getRow($conn, "SELECT p.name, p.user_id FROM pets p WHERE p.id=$pid");
            if ($pet && $due) {
                $uid = (int)$pet['user_id'];
                $msg = sanitize($conn, "{$pet['name']}'s $name vaccine is due on " . formatDate($due));
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

    // ── Consultations ─────────────────────────────────────────
    if ($action === 'add_consultation') {
        $pid  = (int)$_POST['pet_id'];
        $date = sanitize($conn, $_POST['date_of_visit']    ?? '');
        $rsn  = sanitize($conn, $_POST['reason_for_visit'] ?? '');
        $diag = sanitize($conn, $_POST['diagnosis']        ?? '');
        $trt  = sanitize($conn, $_POST['treatment_given']  ?? '');
        $vet  = sanitize($conn, $_POST['vet_name']         ?? '');
        $note = sanitize($conn, $_POST['notes']            ?? '');
        if ($pid && $date) {
            mysqli_query($conn,
                "INSERT INTO pet_consultations (pet_id,date_of_visit,reason_for_visit,diagnosis,treatment_given,vet_name,notes)
                 VALUES ($pid,'$date','$rsn','$diag','$trt','$vet','$note')");
        }
        redirect("pet_records.php?success=Consultation added.&view=$pid");
    }

    if ($action === 'edit_consultation') {
        $cid  = (int)$_POST['consult_id'];
        $pid  = (int)$_POST['pet_id'];
        $date = sanitize($conn, $_POST['date_of_visit']    ?? '');
        $rsn  = sanitize($conn, $_POST['reason_for_visit'] ?? '');
        $diag = sanitize($conn, $_POST['diagnosis']        ?? '');
        $trt  = sanitize($conn, $_POST['treatment_given']  ?? '');
        $vet  = sanitize($conn, $_POST['vet_name']         ?? '');
        mysqli_query($conn,
            "UPDATE pet_consultations
             SET date_of_visit='$date', reason_for_visit='$rsn',
                 diagnosis='$diag', treatment_given='$trt', vet_name='$vet'
             WHERE id=$cid AND pet_id=$pid");
        redirect("pet_records.php?success=Consultation updated.&view=$pid");
    }

    if ($action === 'delete_consultation') {
        $cid = (int)$_POST['consult_id'];
        $pid = (int)$_POST['pet_id'];
        mysqli_query($conn, "DELETE FROM pet_consultations WHERE id=$cid AND pet_id=$pid");
        redirect("pet_records.php?success=Consultation removed.&view=$pid");
    }

    // ── Medical History ──────────────────────────────────────
    if ($action === 'edit_medical_history') {
        $mhid   = (int)$_POST['mh_id'];
        $pid    = (int)$_POST['pet_id'];
        $clinic = sanitize($conn, $_POST['clinic_name']        ?? '');
        $ill    = sanitize($conn, $_POST['past_illnesses']     ?? '');
        $chron  = sanitize($conn, $_POST['chronic_conditions'] ?? '');
        $inj    = sanitize($conn, $_POST['injuries_surgeries'] ?? '');
        mysqli_query($conn,
            "UPDATE pet_medical_history
             SET clinic_name='$clinic', past_illnesses='$ill',
                 chronic_conditions='$chron', injuries_surgeries='$inj'
             WHERE id=$mhid AND pet_id=$pid");
        redirect("pet_records.php?success=Medical history updated.&view=$pid");
    }

    if ($action === 'save_medical_history') {
        $pid    = (int)$_POST['pet_id'];
        $clinic = sanitize($conn, $_POST['clinic_name']        ?? '');
        $ill    = sanitize($conn, $_POST['past_illnesses']     ?? '');
        $chron  = sanitize($conn, $_POST['chronic_conditions'] ?? '');
        $inj    = sanitize($conn, $_POST['injuries_surgeries'] ?? '');
        $note   = sanitize($conn, $_POST['notes']              ?? '');
        if ($pid) {
            mysqli_query($conn,
                "INSERT INTO pet_medical_history (pet_id,clinic_name,past_illnesses,chronic_conditions,injuries_surgeries,notes)
                 VALUES ($pid,'$clinic','$ill','$chron','$inj','$note')");
        }
        redirect("pet_records.php?success=Medical history added.&view=$pid");
    }

    if ($action === 'delete_medical_history') {
        $mhid = (int)$_POST['mh_id'];
        $pid  = (int)$_POST['pet_id'];
        mysqli_query($conn, "DELETE FROM pet_medical_history WHERE id=$mhid AND pet_id=$pid");
        redirect("pet_records.php?success=Medical history removed.&view=$pid");
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

// Handle archive/delete/restore (GET)
if (isset($_GET['delete_pet']) && is_numeric($_GET['delete_pet'])) {
    $dpid = (int)$_GET['delete_pet'];
    mysqli_query($conn, "DELETE FROM pets WHERE id=$dpid");
    redirect('pet_records.php?success=Pet deleted.');
}
if (isset($_GET['archive_pet']) && is_numeric($_GET['archive_pet'])) {
    $apid = (int)$_GET['archive_pet'];
    mysqli_query($conn, "UPDATE pets SET archived=1, status='archived' WHERE id=$apid");
    redirect('pet_records.php?success=Pet archived.');
}
if (isset($_GET['unarchive_pet']) && is_numeric($_GET['unarchive_pet'])) {
    $uapid = (int)$_GET['unarchive_pet'];
    mysqli_query($conn, "UPDATE pets SET archived=0, status='active' WHERE id=$uapid");
    redirect('pet_records.php?success=Pet restored.');
}

// ── Stats ─────────────────────────────────────────────────────
$total_pets = countRows($conn, 'pets');
$total_dogs = countRows($conn, 'pets', "species='dog'");
$total_cats = countRows($conn, 'pets', "species='cat'");

// ── Single pet view ──────────────────────────────────────────
$view_id       = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_pet      = null;
$meds          = [];
$vaccines      = [];
$allergies     = [];
$consultations = [];
$med_histories = [];
$documents     = [];
$pet_owner     = null;

if ($view_id) {
    $view_pet = getRow($conn, "SELECT * FROM pets WHERE id=$view_id");
    if (!$view_pet) redirect('pet_records.php');
    $pid           = $view_pet['id'];
    $meds          = getRows($conn, "SELECT * FROM pet_medications    WHERE pet_id=$pid ORDER BY id DESC");
    $vaccines      = getRows($conn, "SELECT * FROM pet_vaccines        WHERE pet_id=$pid ORDER BY date_given DESC");
    $allergies     = getRows($conn, "SELECT * FROM pet_allergies       WHERE pet_id=$pid ORDER BY id DESC");
    $consultations = getRows($conn, "SELECT * FROM pet_consultations   WHERE pet_id=$pid ORDER BY date_of_visit DESC");
    $med_histories = getRows($conn, "SELECT * FROM pet_medical_history WHERE pet_id=$pid ORDER BY id DESC");
    $med_history   = !empty($med_histories) ? $med_histories[0] : null;
    $pet_owner     = getRow($conn,  "SELECT * FROM users WHERE id={$view_pet['user_id']}");
    $documents     = getRows($conn,
        "SELECT d.*, u.name AS uploader_name
         FROM pet_documents d
         LEFT JOIN users u ON d.uploaded_by = u.id
         WHERE d.pet_id=$pid
         ORDER BY d.created_at DESC");
}

// ── Pet list ─────────────────────────────────────────────────
$search        = sanitize($conn, $_GET['search']  ?? '');
$species       = sanitize($conn, $_GET['species'] ?? '');
$show_archived = isset($_GET['archived']) && $_GET['archived'] === '1';
$where         = $show_archived ? 'p.archived=1' : 'p.archived=0';
if ($search)  $where .= " AND (p.name LIKE '%$search%' OR u.name LIKE '%$search%' OR p.breed LIKE '%$search%')";
if ($species) $where .= " AND p.species='$species'";
$pets = getRows($conn,
    "SELECT p.*, u.name AS owner_name, u.contact_no AS owner_contact
     FROM pets p LEFT JOIN users u ON p.user_id = u.id
     WHERE $where ORDER BY p.name ASC");

// ── Helper functions ─────────────────────────────────────────
function docTypeLabel(string $t): string {
    return [
        'lab_result'   => '🧪 Lab Result',
        'xray'         => '🔬 X-Ray',
        'prescription' => '💊 Prescription',
        'photo'        => '📷 Photo',
        'certificate'  => '📜 Certificate',
        'invoice'      => '🧾 Invoice/Receipt',
        'other'        => '📎 Other',
        'general'      => '📄 General',
    ][$t] ?? '📄 ' . ucfirst($t);
}

function docTypeBadgeColor(string $t): array {
    return [
        'lab_result'   => ['#dbeafe','#1e40af'],
        'xray'         => ['#e0e7ff','#3730a3'],
        'prescription' => ['#d1fae5','#065f46'],
        'photo'        => ['#fce7f3','#9d174d'],
        'certificate'  => ['#fef3c7','#92400e'],
        'invoice'      => ['#fdf4ff','#7e22ce'],
        'other'        => ['#f3f4f6','#374151'],
        'general'      => ['#e0f7fa','#006064'],
    ][$t] ?? ['#f3f4f6','#374151'];
}

function isImageMime(string $mime): bool {
    return str_starts_with($mime, 'image/');
}

function fileIcon(string $mime): string {
    if (str_starts_with($mime, 'image/'))      return '🖼️';
    if ($mime === 'application/pdf')            return '📕';
    if (str_contains($mime, 'word'))            return '📘';
    if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) return '📗';
    if ($mime === 'text/plain')                 return '📄';
    return '📎';
}

function formatBytes(int $bytes): string {
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Records — Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Pet detail layout ── */
        .pet-detail-top { display: grid; grid-template-columns: 180px 1fr; gap: 20px; margin-bottom: 20px; }
        .pet-large-photo { width: 160px; height: 160px; border-radius: var(--radius); object-fit: cover; box-shadow: var(--shadow); }
        .pet-photo-placeholder { width: 160px; height: 160px; border-radius: var(--radius); background: var(--teal-light); display: flex; align-items: center; justify-content: center; font-size: 56px; box-shadow: var(--shadow); }
        .pet-info-box { background: rgba(255,255,255,0.9); border-radius: var(--radius); padding: 20px 24px; box-shadow: var(--shadow); position: relative; }
        .pet-info-box h2 { font-family: var(--font-head); font-size: 20px; font-weight: 800; margin-bottom: 14px; }
        .pet-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; }
        .pi-row { display: flex; gap: 8px; font-size: 13px; }
        .pi-label { color: var(--text-light); font-weight: 600; width: 70px; flex-shrink: 0; }
        .pi-value { font-weight: 700; color: var(--text-dark); }

        /* ── Medical record cards ── */
        .med-card { background: rgba(255,255,255,0.9); border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow); }
        .med-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .med-card-title { font-weight: 800; font-size: 14px; color: var(--blue-header); display: flex; align-items: center; gap: 6px; }
        .med-entry { padding: 10px 0; border-bottom: 1px solid var(--border); }
        .med-entry:last-of-type { border-bottom: none; }
        .med-entry-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .med-entry-name { font-weight: 700; font-size: 13px; }
        .med-entry-detail { font-size: 12px; color: var(--text-light); margin-top: 2px; }
        .med-entry-actions { display: flex; gap: 4px; flex-shrink: 0; margin-left: 8px; }
        .med-action-btn { padding: 2px 8px; font-size: 11px; border-radius: 4px; border: none; cursor: pointer; font-weight: 700; transition: var(--transition); }
        .med-edit-btn   { background: #e0f7fa; color: var(--teal-dark); }
        .med-delete-btn { background: #fee2e2; color: #dc2626; }
        .med-edit-btn:hover   { background: var(--teal); color: #fff; }
        .med-delete-btn:hover { background: #ef4444; color: #fff; }
        .vaccine-dates { display: flex; gap: 12px; font-size: 12px; margin-top: 4px; }
        .vd-item { display: flex; flex-direction: column; gap: 1px; }
        .vd-label { color: var(--text-light); font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .vd-value { font-weight: 700; color: var(--text-dark); }
        .vaccine-overdue { color: #ef4444 !important; }
        .vaccine-soon    { color: #f59e0b !important; }

        /* ══════════════════════════════════════════════════════
           DOCUMENTS SECTION
        ══════════════════════════════════════════════════════ */
        .docs-section {
            background: rgba(255,255,255,0.92);
            border-radius: var(--radius);
            padding: 22px 24px;
            box-shadow: var(--shadow);
            margin-top: 20px;
        }
        .docs-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .docs-header h3 {
            font-family: var(--font-head);
            font-weight: 800;
            font-size: 17px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .docs-count {
            background: var(--teal);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            padding: 2px 10px;
            border-radius: 20px;
        }

        /* Upload drop zone */
        .upload-zone {
            border: 2px dashed var(--teal);
            border-radius: var(--radius);
            padding: 28px 20px;
            text-align: center;
            background: rgba(0,188,212,0.04);
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 20px;
            position: relative;
        }
        .upload-zone:hover,
        .upload-zone.drag-over {
            background: rgba(0,188,212,0.10);
            border-color: var(--teal-dark);
        }
        .upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .upload-zone-icon { font-size: 36px; margin-bottom: 8px; }
        .upload-zone-text { font-size: 14px; font-weight: 700; color: var(--teal-dark); }
        .upload-zone-sub  { font-size: 12px; color: var(--text-light); margin-top: 4px; }
        .upload-zone-file-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            margin-top: 8px;
            display: none;
        }

        /* Document grid */
        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
        }
        .doc-card {
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            background: #fff;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
        }
        .doc-card:hover {
            border-color: var(--teal);
            box-shadow: 0 4px 16px rgba(0,188,212,0.15);
            transform: translateY(-2px);
        }

        /* Preview area */
        .doc-preview {
            height: 130px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fffe;
            overflow: hidden;
            position: relative;
            cursor: pointer;
        }
        .doc-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.2s;
        }
        .doc-card:hover .doc-preview img { transform: scale(1.05); }
        .doc-preview-icon {
            font-size: 48px;
            opacity: 0.7;
        }

        /* Card body */
        .doc-body { padding: 10px 12px; flex: 1; }
        .doc-title {
            font-weight: 700;
            font-size: 13px;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }
        .doc-meta { font-size: 11px; color: var(--text-light); line-height: 1.6; }
        .doc-type-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
            margin-bottom: 4px;
        }

        /* Card footer */
        .doc-footer {
            display: flex;
            gap: 6px;
            padding: 8px 12px;
            border-top: 1px solid var(--border);
            background: #fafffe;
        }
        .doc-footer a,
        .doc-footer button {
            flex: 1;
            text-align: center;
            padding: 5px 0;
            border-radius: var(--radius-sm);
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: var(--transition);
            text-decoration: none;
        }
        .doc-btn-view     { background: #e0f7fa; color: var(--teal-dark); }
        .doc-btn-download { background: #dbeafe; color: #1e40af; }
        .doc-btn-edit     { background: #fef3c7; color: #92400e; }
        .doc-btn-del      { background: #fee2e2; color: #dc2626; }
        .doc-btn-view:hover     { background: var(--teal); color: #fff; }
        .doc-btn-download:hover { background: #1e40af; color: #fff; }
        .doc-btn-edit:hover     { background: #f59e0b; color: #fff; }
        .doc-btn-del:hover      { background: #ef4444; color: #fff; }

        /* Filter bar for docs */
        .doc-filter-bar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            align-items: center;
        }
        .doc-filter-chip {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            border: 1.5px solid var(--border);
            background: rgba(255,255,255,0.8);
            color: var(--text-mid);
            cursor: pointer;
            transition: var(--transition);
        }
        .doc-filter-chip.active,
        .doc-filter-chip:hover {
            background: var(--teal);
            color: #fff;
            border-color: var(--teal);
        }

        /* Image lightbox overlay */
        .lightbox-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(0,0,0,0.88);
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .lightbox-overlay.open { display: flex; }
        .lightbox-overlay img {
            max-width: 90vw;
            max-height: 85vh;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            object-fit: contain;
        }
        .lightbox-close {
            position: absolute;
            top: 18px; right: 22px;
            font-size: 32px;
            color: #fff;
            cursor: pointer;
            line-height: 1;
            opacity: 0.8;
            background: none;
            border: none;
        }
        .lightbox-close:hover { opacity: 1; }
        .lightbox-caption {
            color: rgba(255,255,255,0.75);
            font-size: 13px;
            margin-top: 12px;
            font-weight: 600;
        }

        /* Empty doc state */
        .doc-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }
        .doc-empty-icon { font-size: 48px; margin-bottom: 10px; }
        .doc-empty h4   { font-size: 15px; font-weight: 700; color: var(--text-mid); }

        /* ── Upload modal  ── */
        .upload-preview-img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            object-fit: contain;
            display: none;
            margin: 10px auto 0;
        }
        .upload-preview-pdf {
            display: none;
            background: #fff0f0;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            font-size: 13px;
            color: #dc2626;
            font-weight: 700;
            margin-top: 10px;
        }

        /* ── Delete modal ── */
        .del-overlay { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); backdrop-filter: blur(3px); align-items: center; justify-content: center; }
        .del-overlay.open { display: flex; }
        .del-box { background: #fff; border-radius: 16px; padding: 32px 28px; max-width: 380px; width: 90%; text-align: center; box-shadow: 0 24px 60px rgba(0,0,0,0.2); animation: popIn 0.2s ease; }
        @keyframes popIn { from { opacity:0; transform: scale(0.92) translateY(10px); } to { opacity:1; transform: scale(1) translateY(0); } }
        .del-icon-wrap { width: 64px; height: 64px; border-radius: 50%; background: #fff5f5; border: 2px solid #fee2e2; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px; }
        .del-box h3 { font-family: var(--font-head); font-size: 18px; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; }
        .del-box p { font-size: 13px; color: var(--text-mid); margin-bottom: 24px; line-height: 1.6; }
        .del-actions { display: flex; gap: 10px; justify-content: center; }
        .btn-keep { padding: 10px 22px; border-radius: 8px; border: 1.5px solid var(--border); background: #fff; color: var(--text-dark); font-weight: 700; font-size: 13px; cursor: pointer; font-family: var(--font-main); }
        .btn-keep:hover { background: #f9fafb; }
        .btn-del-confirm { padding: 10px 22px; border-radius: 8px; border: none; background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; font-weight: 700; font-size: 13px; cursor: pointer; box-shadow: 0 4px 12px rgba(239,68,68,0.3); font-family: var(--font-main); transition: transform 0.15s; }
        .btn-del-confirm:hover { transform: translateY(-1px); }

        .view-all-btn { display: block; margin: 16px auto 0; padding: 8px 24px; border-radius: 20px; border: 1.5px solid var(--teal); background: #fff; color: var(--teal-dark); font-weight: 700; font-size: 12px; cursor: pointer; transition: var(--transition); }
        .view-all-btn:hover { background: var(--teal); color: #fff; }
        .hidden-row { display: none; }

        @media(max-width:700px) {
            .pet-detail-top { grid-template-columns: 1fr; }
            .medical-grid   { grid-template-columns: 1fr; }
            .doc-grid       { grid-template-columns: 1fr 1fr; }
        }
        @media(max-width:480px) {
            .doc-grid { grid-template-columns: 1fr; }
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
                PET RECORDS
            </div>
            <div class="topbar-actions">
                <a href="notifications.php" class="topbar-btn" title="Notifications">
                    🔔
                    <?php $unread_notif = getUnreadNotifications($conn, $_SESSION['user_id']); if ($unread_notif > 0): ?>
                        <span class="badge-count"><?= $unread_notif ?></span>
                    <?php endif; ?>
                </a>
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
                        <img src="../assets/css/images/pets/<?= htmlspecialchars($view_pet['photo']) ?>"
                             class="pet-large-photo" alt="<?= htmlspecialchars($view_pet['name']) ?>">
                    <?php else: ?>
                        <div class="pet-photo-placeholder">
                            <?= $view_pet['species']==='cat' ? '🐱' : '🐶' ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pet-info-box">
                    <button style="position:absolute;top:14px;right:14px;background:none;border:none;
                                   font-size:18px;cursor:pointer;color:var(--teal-dark);"
                            onclick="document.getElementById('editPetModal').classList.add('open')"
                            title="Edit">✏️</button>
                    <h2><?= strtoupper(htmlspecialchars($view_pet['name'])) ?></h2>
                    <div class="pet-info-grid">
                        <div class="pi-row"><span class="pi-label">Species:</span><span class="pi-value"><?= ucfirst($view_pet['species']) ?></span></div>
                        <div class="pi-row"><span class="pi-label">Age:</span><span class="pi-value"><?= htmlspecialchars($view_pet['age'] ?? '—') ?> years old</span></div>
                        <div class="pi-row"><span class="pi-label">Gender:</span><span class="pi-value"><?= ucfirst($view_pet['gender']) ?></span></div>
                        <div class="pi-row"><span class="pi-label">Weight:</span><span class="pi-value"><?= $view_pet['weight'] ? $view_pet['weight'].' kg' : '—' ?></span></div>
                        <div class="pi-row"><span class="pi-label">Breed:</span><span class="pi-value"><?= htmlspecialchars($view_pet['breed'] ?? '—') ?></span></div>
                        <div class="pi-row"><span class="pi-label">Color:</span><span class="pi-value"><?= htmlspecialchars($view_pet['color'] ?? '—') ?></span></div>
                    </div>
                </div>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h2 style="font-family:var(--font-head);font-weight:800;font-size:18px;">MEDICAL RECORDS</h2>
            </div>

            <!-- 3-column medical grid -->
            <div class="medical-grid">

                <!-- MEDICATIONS -->
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
                                        <div class="med-entry-name"><?= htmlspecialchars($med['medication_name']) ?></div>
                                        <div class="med-entry-detail"><?= htmlspecialchars($med['dosage']) ?><?= $med['frequency'] ? ', '.$med['frequency'] : '' ?></div>
                                        <?php if ($med['notes']): ?>
                                            <div class="med-entry-detail" style="color:var(--text-mid);">📝 <?= htmlspecialchars($med['notes']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($med['prescribed_by'] || $med['prescription_date']): ?>
                                            <div style="margin-top:6px;padding:6px 10px;background:#f0fffe;border-left:3px solid var(--teal);border-radius:0 6px 6px 0;font-size:11px;">
                                                <?php if ($med['prescribed_by']): ?><div style="color:var(--teal-dark);font-weight:700;">👨‍⚕️ <?= htmlspecialchars($med['prescribed_by']) ?></div><?php endif; ?>
                                                <?php if ($med['prescription_date']): ?><div style="color:var(--text-light);margin-top:2px;">📅 <?= date('M d, Y', strtotime($med['prescription_date'])) ?></div><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="med-entry-actions">
                                        <button class="med-action-btn med-edit-btn" onclick='openEditMed(<?= json_encode($med) ?>)'>Edit</button>
                                        <button class="med-action-btn med-delete-btn" onclick="openDelModal('medication', <?= $med['id'] ?>, <?= $view_id ?>, '<?= htmlspecialchars(addslashes($med['medication_name'])) ?>')">Del</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- VACCINES -->
                <div class="med-card">
                    <div class="med-card-header">
                        <div class="med-card-title">💉 VACCINES</div>
                        <button class="btn btn-teal btn-sm" onclick="document.getElementById('addVacModal').classList.add('open')">+ Add</button>
                    </div>
                    <?php if (empty($vaccines)): ?>
                        <p style="font-size:12px;color:var(--text-light);">No vaccine records.</p>
                    <?php else: ?>
                        <?php foreach ($vaccines as $vac):
                            $today   = date('Y-m-d');
                            $due_cls = '';
                            if ($vac['next_due']) {
                                if ($vac['next_due'] < $today) $due_cls = 'vaccine-overdue';
                                elseif ($vac['next_due'] <= date('Y-m-d', strtotime('+30 days'))) $due_cls = 'vaccine-soon';
                            }
                        ?>
                            <div class="med-entry">
                                <div class="med-entry-top">
                                    <div>
                                        <div class="med-entry-name"><?= htmlspecialchars($vac['vaccine_name']) ?></div>
                                        <div class="vaccine-dates">
                                            <div class="vd-item"><span class="vd-label">Given</span><span class="vd-value"><?= $vac['date_given'] ? date('m/d/y',strtotime($vac['date_given'])) : '—' ?></span></div>
                                            <div class="vd-item"><span class="vd-label">Next Due</span><span class="vd-value <?= $due_cls ?>"><?= $vac['next_due'] ? date('m/d/y',strtotime($vac['next_due'])) : '—' ?><?= $due_cls==='vaccine-overdue' ? ' ⚠️' : ($due_cls==='vaccine-soon' ? ' ⏰' : '') ?></span></div>
                                        </div>
                                    </div>
                                    <div class="med-entry-actions">
                                        <button class="med-action-btn med-edit-btn" onclick='openEditVac(<?= json_encode($vac) ?>)'>Edit</button>
                                        <button class="med-action-btn med-delete-btn" onclick="openDelModal('vaccine', <?= $vac['id'] ?>, <?= $view_id ?>, '<?= htmlspecialchars(addslashes($vac['vaccine_name'])) ?>')">Del</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- ALLERGIES -->
                <div class="med-card">
                    <div class="med-card-header">
                        <div class="med-card-title">🌿 ALLERGIES</div>
                        <button class="btn btn-teal btn-sm" onclick="document.getElementById('addAllergyModal').classList.add('open')">+ Add</button>
                    </div>
                    <?php if (empty($allergies)): ?>
                        <p style="font-size:12px;color:var(--text-light);">No allergy records.</p>
                    <?php else: ?>
                        <?php foreach ($allergies as $alg): ?>
                            <div class="med-entry">
                                <div class="med-entry-top">
                                    <div>
                                        <div class="med-entry-name"><?= htmlspecialchars($alg['allergen']) ?></div>
                                        <div class="med-entry-detail">Reaction: <strong><?= htmlspecialchars($alg['reaction']) ?></strong></div>
                                    </div>
                                    <div class="med-entry-actions">
                                        <button class="med-action-btn med-edit-btn" onclick='openEditAllergy(<?= json_encode($alg) ?>)'>Edit</button>
                                        <button class="med-action-btn med-delete-btn" onclick="openDelModal('allergy', <?= $alg['id'] ?>, <?= $view_id ?>, '<?= htmlspecialchars(addslashes($alg['allergen'])) ?>')">Del</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div><!-- /medical-grid -->

            <!-- PREVIOUS CONSULTATIONS -->
            <div style="background:rgba(255,255,255,0.9);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-top:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h3 style="font-family:var(--font-head);font-weight:800;font-size:17px;">📋 Previous Consultations</h3>
                    <button class="btn btn-teal btn-sm" onclick="document.getElementById('addConsultModal').classList.add('open')">+ Add</button>
                </div>
                <?php if (empty($consultations)): ?>
                    <p style="font-size:13px;color:var(--text-light);">No consultation records yet.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table style="width:100%;font-size:13px;border-collapse:collapse;">
                            <thead>
                                <tr style="background:var(--teal-light);">
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Date</th>
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Reason</th>
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Diagnosis</th>
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Treatment</th>
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Vet</th>
                                    <th style="padding:8px 12px;text-align:center;color:var(--teal-dark);font-size:12px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultations as $ci => $con): ?>
                                    <tr style="border-bottom:1px solid var(--border);" <?= $ci >= 5 ? 'class="hidden-row consult-extra"' : '' ?>>
                                        <td style="padding:8px 12px;"><?= formatDate($con['date_of_visit']) ?></td>
                                        <td style="padding:8px 12px;"><?= htmlspecialchars($con['reason_for_visit'] ?? '—') ?></td>
                                        <td style="padding:8px 12px;"><?= htmlspecialchars($con['diagnosis'] ?? '—') ?></td>
                                        <td style="padding:8px 12px;"><?= htmlspecialchars($con['treatment_given'] ?? '—') ?></td>
                                        <td style="padding:8px 12px;"><?= htmlspecialchars($con['vet_name'] ?? '—') ?></td>
                                        <td style="padding:8px 12px;text-align:center;">
                                            <div style="display:flex;gap:4px;justify-content:center;">
                                                <button class="med-action-btn med-edit-btn" onclick='openEditConsult(<?= json_encode($con) ?>)'>Edit</button>
                                                <button class="med-action-btn med-delete-btn" onclick="openDelModal('consultation', <?= $con['id'] ?>, <?= $view_id ?>, '<?= htmlspecialchars(addslashes($con['reason_for_visit'] ?? 'Consultation')) ?>')">Del</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (count($consultations) > 5): ?>
                            <button class="view-all-btn" onclick="toggleRows('consult-extra', this)">▼ View All (<?= count($consultations) ?> records)</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- MEDICAL HISTORY -->
            <div style="background:rgba(255,255,255,0.9);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-top:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h3 style="font-family:var(--font-head);font-weight:800;font-size:17px;">🏥 Medical History</h3>
                    <button class="btn btn-teal btn-sm" onclick="document.getElementById('editMedHistModal').classList.add('open')">+ Add</button>
                </div>
                <?php if (empty($med_histories)): ?>
                    <p style="font-size:13px;color:var(--text-light);">No medical history recorded yet.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table style="width:100%;font-size:13px;border-collapse:collapse;">
                            <thead>
                                <tr style="background:var(--teal-light);">
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Clinic</th>
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Past Illnesses</th>
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Chronic Conditions</th>
                                    <th style="padding:8px 12px;text-align:left;color:var(--teal-dark);font-size:12px;">Injuries/Surgeries</th>
                                    <th style="padding:8px 12px;text-align:center;color:var(--teal-dark);font-size:12px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($med_histories as $mhi => $mh): ?>
                                    <tr style="border-bottom:1px solid var(--border);" <?= $mhi >= 5 ? 'class="hidden-row mhist-extra"' : '' ?>>
                                        <td style="padding:8px 12px;"><?= htmlspecialchars($mh['clinic_name'] ?? '—') ?></td>
                                        <td style="padding:8px 12px;"><?= htmlspecialchars($mh['past_illnesses'] ?? '—') ?></td>
                                        <td style="padding:8px 12px;"><?= htmlspecialchars($mh['chronic_conditions'] ?? '—') ?></td>
                                        <td style="padding:8px 12px;"><?= htmlspecialchars($mh['injuries_surgeries'] ?? '—') ?></td>
                                        <td style="padding:8px 12px;text-align:center;">
                                            <div style="display:flex;gap:4px;justify-content:center;">
                                                <button class="med-action-btn med-edit-btn" onclick='openEditMedHist(<?= json_encode($mh) ?>)'>Edit</button>
                                                <button class="med-action-btn med-delete-btn" onclick="openDelModal('medical_history', <?= $mh['id'] ?>, <?= $view_id ?>, '<?= htmlspecialchars(addslashes($mh['clinic_name'] ?? 'Medical History')) ?>')">Del</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (count($med_histories) > 5): ?>
                            <button class="view-all-btn" onclick="toggleRows('mhist-extra', this)">▼ View All (<?= count($med_histories) ?> records)</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- VACCINATION CERTIFICATE -->
            <div style="background:rgba(255,255,255,0.9);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-top:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h3 style="font-family:var(--font-head);font-weight:800;font-size:17px;">💉 VACCINATION CERTIFICATE (Booklet)</h3>
                    <button class="btn btn-teal btn-sm" onclick="document.getElementById('addVacModal').classList.add('open')">+ Add</button>
                </div>
                <?php
                $vac_booklet = getRows($conn,
                    "SELECT v.*, COALESCE(v.notes,'') AS manufacturer
                     FROM pet_vaccines v WHERE v.pet_id=$view_id ORDER BY v.date_given ASC");
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
                                    <th style="padding:8px 10px;text-align:left;color:var(--teal-dark);font-size:11px;">Veterinarian</th>
                                    <th style="padding:8px 10px;text-align:center;color:var(--teal-dark);font-size:11px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vac_booklet as $vi => $vb): ?>
                                    <tr style="border-bottom:1px solid var(--border);<?= $vi%2===0?'background:#fafffe;':'' ?>" <?= $vi >= 5 ? 'class="hidden-row vac-extra"' : '' ?>>
                                        <td style="padding:8px 10px;text-align:center;color:var(--text-light);"><?= $vi+1 ?></td>
                                        <td style="padding:8px 10px;"><?= $vb['date_given'] ? date('m/d/y',strtotime($vb['date_given'])) : '—' ?></td>
                                        <td style="padding:8px 10px;"><?= $view_pet['weight'] ? $view_pet['weight'].' kg' : '—' ?></td>
                                        <td style="padding:8px 10px;font-weight:700;"><?= htmlspecialchars($vb['vaccine_name']) ?></td>
                                        <td style="padding:8px 10px;color:var(--text-mid);"><?= htmlspecialchars($vb['notes'] ?? '—') ?></td>
                                        <td style="padding:8px 10px;"><?= $vb['next_due'] ? date('m/d/y',strtotime($vb['next_due'])) : '—' ?></td>
                                        <td style="padding:8px 10px;">Dr. Ann</td>
                                        <td style="padding:8px 10px;text-align:center;">
                                            <div style="display:flex;gap:4px;justify-content:center;">
                                                <button class="med-action-btn med-edit-btn" onclick='openEditVac(<?= json_encode($vb) ?>)'>Edit</button>
                                                <button class="med-action-btn med-delete-btn" onclick="openDelModal('vaccine', <?= $vb['id'] ?>, <?= $view_id ?>, '<?= htmlspecialchars(addslashes($vb['vaccine_name'])) ?>')">Del</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if (count($vac_booklet) > 5): ?>
                            <button class="view-all-btn" onclick="toggleRows('vac-extra', this)">▼ View All (<?= count($vac_booklet) ?> records)</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ══════════════════════════════════════════════
                 DOCUMENTS & ATTACHMENTS SECTION
            ══════════════════════════════════════════════ -->
            <div class="docs-section">
                <div class="docs-header">
                    <h3>
                        📂 Documents &amp; Attachments
                        <?php if (!empty($documents)): ?>
                            <span class="docs-count"><?= count($documents) ?></span>
                        <?php endif; ?>
                    </h3>
                    <button class="btn btn-teal btn-sm"
                            onclick="document.getElementById('uploadDocModal').classList.add('open')">
                        ⬆️ Upload File
                    </button>
                </div>

                <!-- Document type filter chips -->
                <?php if (!empty($documents)): ?>
                    <?php
                    $doc_types_present = array_unique(array_column($documents, 'document_type'));
                    sort($doc_types_present);
                    ?>
                    <div class="doc-filter-bar">
                        <span class="doc-filter-chip active" data-type="all" onclick="filterDocs('all', this)">All</span>
                        <?php foreach ($doc_types_present as $dt): ?>
                            <span class="doc-filter-chip" data-type="<?= $dt ?>"
                                  onclick="filterDocs('<?= $dt ?>', this)">
                                <?= docTypeLabel($dt) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($documents)): ?>
                    <!-- Empty state + inline quick-upload -->
                    <div class="doc-empty">
                        <div class="doc-empty-icon">📂</div>
                        <h4>No documents uploaded yet</h4>
                        <p style="font-size:12px;margin-top:4px;">Upload lab results, X-rays, prescriptions, photos, and more.</p>
                        <button class="btn btn-teal btn-sm" style="margin-top:14px;"
                                onclick="document.getElementById('uploadDocModal').classList.add('open')">
                            ⬆️ Upload First Document
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Document grid -->
                    <div class="doc-grid" id="docGrid">
                        <?php foreach ($documents as $doc):
                            $is_img  = isImageMime($doc['mime_type']);
                            $is_pdf  = ($doc['mime_type'] === 'application/pdf');
                            $icon    = fileIcon($doc['mime_type']);
                            $file_url= DOC_UPLOAD_URL . $doc['file_name'];
                            [$bg,$fg]= docTypeBadgeColor($doc['document_type']);
                        ?>
                            <div class="doc-card" data-type="<?= $doc['document_type'] ?>">

                                <!-- Preview -->
                                <div class="doc-preview"
                                     onclick="<?= $is_img ? "openLightbox('$file_url','".htmlspecialchars(addslashes($doc['title']))."')" : "window.open('$file_url','_blank')" ?>">
                                    <?php if ($is_img): ?>
                                        <img src="<?= htmlspecialchars($file_url) ?>"
                                             alt="<?= htmlspecialchars($doc['title']) ?>"
                                             loading="lazy">
                                    <?php else: ?>
                                        <div class="doc-preview-icon"><?= $icon ?></div>
                                    <?php endif; ?>
                                    <!-- Hover overlay for non-image -->
                                    <?php if (!$is_img): ?>
                                        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
                                                    background:rgba(0,0,0,0);transition:background 0.2s;"
                                             onmouseover="this.style.background='rgba(0,0,0,0.06)'"
                                             onmouseout="this.style.background='rgba(0,0,0,0)'">
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Body -->
                                <div class="doc-body">
                                    <span class="doc-type-badge"
                                          style="background:<?= $bg ?>;color:<?= $fg ?>;">
                                        <?= docTypeLabel($doc['document_type']) ?>
                                    </span>
                                    <div class="doc-title" title="<?= htmlspecialchars($doc['title']) ?>">
                                        <?= htmlspecialchars($doc['title']) ?>
                                    </div>
                                    <div class="doc-meta">
                                        <?= formatBytes($doc['file_size']) ?>
                                        &nbsp;·&nbsp;
                                        <?= date('M j, Y', strtotime($doc['created_at'])) ?>
                                        <?php if ($doc['notes']): ?>
                                            <br><span title="<?= htmlspecialchars($doc['notes']) ?>">
                                                📝 <?= htmlspecialchars(mb_strimwidth($doc['notes'], 0, 36, '…')) ?>
                                            </span>
                                        <?php endif; ?>
                                        <br>👤 <?= htmlspecialchars($doc['uploader_name'] ?? 'Admin') ?>
                                    </div>
                                </div>

                                <!-- Footer actions -->
                                <div class="doc-footer">
                                    <?php if ($is_img): ?>
                                        <button class="doc-btn-view"
                                                onclick="openLightbox('<?= htmlspecialchars($file_url) ?>','<?= htmlspecialchars(addslashes($doc['title'])) ?>')">
                                            🔍 View
                                        </button>
                                    <?php else: ?>
                                        <a class="doc-btn-view"
                                           href="<?= htmlspecialchars($file_url) ?>" target="_blank">
                                            🔍 Open
                                        </a>
                                    <?php endif; ?>
                                    <a class="doc-btn-download"
                                       href="<?= htmlspecialchars($file_url) ?>"
                                       download="<?= htmlspecialchars($doc['original_name']) ?>">
                                        ⬇
                                    </a>
                                    <button class="doc-btn-edit"
                                            onclick='openEditDoc(<?= json_encode([
                                                "id"            => $doc["id"],
                                                "pet_id"        => $view_id,
                                                "title"         => $doc["title"],
                                                "document_type" => $doc["document_type"],
                                                "notes"         => $doc["notes"] ?? "",
                                            ]) ?>)'>
                                        ✏️
                                    </button>
                                    <button class="doc-btn-del"
                                            onclick="openDelModal('document', <?= $doc['id'] ?>, <?= $view_id ?>, '<?= htmlspecialchars(addslashes($doc['title'])) ?>')">
                                        🗑️
                                    </button>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ══ ALL MODALS ══════════════════════════════ -->

            <!-- Edit Pet -->
            <div class="modal-overlay" id="editPetModal">
                <div class="modal">
                    <button class="modal-close" onclick="document.getElementById('editPetModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">Edit Pet Info</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_pet">
                        <input type="hidden" name="pet_id" value="<?= $view_pet['id'] ?>">
                        <div class="form-group"><label>Pet Name *</label><input type="text" name="pet_name" value="<?= htmlspecialchars($view_pet['name']) ?>" required></div>
                        <div class="form-row">
                            <div class="form-group"><label>Species</label><select name="species"><option value="dog" <?= $view_pet['species']==='dog'?'selected':'' ?>>Dog</option><option value="cat" <?= $view_pet['species']==='cat'?'selected':'' ?>>Cat</option><option value="other" <?= $view_pet['species']==='other'?'selected':'' ?>>Other</option></select></div>
                            <div class="form-group"><label>Breed</label><input type="text" name="breed" value="<?= htmlspecialchars($view_pet['breed'] ?? '') ?>"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Age (yrs)</label><input type="text" name="age" value="<?= htmlspecialchars($view_pet['age'] ?? '') ?>"></div>
                            <div class="form-group"><label>Gender</label><select name="gender"><option value="male" <?= $view_pet['gender']==='male'?'selected':'' ?>>Male</option><option value="female" <?= $view_pet['gender']==='female'?'selected':'' ?>>Female</option></select></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Weight (kg)</label><input type="number" step="0.01" name="weight" value="<?= $view_pet['weight'] ?>"></div>
                            <div class="form-group"><label>Color</label><input type="text" name="color" value="<?= htmlspecialchars($view_pet['color'] ?? '') ?>"></div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('editPetModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Upload Document Modal -->
            <div class="modal-overlay" id="uploadDocModal">
                <div class="modal" style="max-width:520px;">
                    <button class="modal-close" onclick="closeUploadModal()">×</button>
                    <h3 class="modal-title">📂 Upload Document / File</h3>
                    <form method="POST" enctype="multipart/form-data" id="uploadDocForm">
                        <input type="hidden" name="action"  value="upload_document">
                        <input type="hidden" name="pet_id"  value="<?= $view_id ?>">

                        <!-- Drop Zone -->
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" name="doc_file" id="docFileInput"
                                   accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                                   onchange="handleFileSelect(this)">
                            <div class="upload-zone-icon">📂</div>
                            <div class="upload-zone-text">Click or drag &amp; drop a file here</div>
                            <div class="upload-zone-sub">Images, PDF, Word, Excel, TXT — max 20 MB</div>
                            <div class="upload-zone-file-name" id="uploadFileName"></div>
                        </div>

                        <!-- Image preview -->
                        <img id="uploadImgPreview" class="upload-preview-img" alt="Preview">
                        <!-- PDF / other preview -->
                        <div id="uploadFileInfo" class="upload-preview-pdf"></div>

                        <div class="form-group" style="margin-top:16px;">
                            <label>Title / Label</label>
                            <input type="text" name="doc_title" id="docTitleInput"
                                   placeholder="e.g. CBC Blood Test Results – April 2025">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Document Type</label>
                                <select name="doc_type" id="docTypeSelect">
                                    <option value="general">📄 General</option>
                                    <option value="lab_result">🧪 Lab Result</option>
                                    <option value="xray">🔬 X-Ray / Scan</option>
                                    <option value="prescription">💊 Prescription</option>
                                    <option value="photo">📷 Photo</option>
                                    <option value="certificate">📜 Certificate</option>
                                    <option value="invoice">🧾 Invoice / Receipt</option>
                                    <option value="other">📎 Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Notes <span style="color:var(--text-light);font-size:11px;">(optional)</span></label>
                                <input type="text" name="doc_notes"
                                       placeholder="e.g. Pre-surgery bloodwork">
                            </div>
                        </div>

                        <!-- Upload progress bar (shown during upload) -->
                        <div id="uploadProgress" style="display:none;margin-bottom:12px;">
                            <div style="height:6px;background:var(--border);border-radius:10px;overflow:hidden;">
                                <div id="uploadProgressBar"
                                     style="height:100%;background:var(--teal);width:0%;transition:width 0.3s;border-radius:10px;"></div>
                            </div>
                            <div style="font-size:12px;color:var(--text-light);margin-top:4px;text-align:center;">
                                Uploading…
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="closeUploadModal()">Cancel</button>
                            <button type="submit" class="btn btn-teal" id="uploadSubmitBtn" disabled>
                                ⬆️ Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Document Modal -->
            <div class="modal-overlay" id="editDocModal">
                <div class="modal" style="max-width:420px;">
                    <button class="modal-close" onclick="document.getElementById('editDocModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">✏️ Edit Document Info</h3>
                    <form method="POST">
                        <input type="hidden" name="action"  value="edit_document">
                        <input type="hidden" name="pet_id"  value="<?= $view_id ?>">
                        <input type="hidden" name="doc_id"  id="edit_doc_id">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="doc_title" id="edit_doc_title">
                        </div>
                        <div class="form-group">
                            <label>Document Type</label>
                            <select name="doc_type" id="edit_doc_type">
                                <option value="general">📄 General</option>
                                <option value="lab_result">🧪 Lab Result</option>
                                <option value="xray">🔬 X-Ray / Scan</option>
                                <option value="prescription">💊 Prescription</option>
                                <option value="photo">📷 Photo</option>
                                <option value="certificate">📜 Certificate</option>
                                <option value="invoice">🧾 Invoice / Receipt</option>
                                <option value="other">📎 Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <input type="text" name="doc_notes" id="edit_doc_notes">
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('editDocModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Save Changes</button>
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
                        <div class="form-group"><label>Medication Name *</label><input type="text" name="med_name" required placeholder="e.g. Amoxicillin"></div>
                        <div class="form-row">
                            <div class="form-group"><label>Dosage</label><input type="text" name="dosage" placeholder="e.g. 5 mg"></div>
                            <div class="form-group"><label>Frequency</label><input type="text" name="frequency" placeholder="e.g. Twice a Day"></div>
                        </div>
                        <div class="form-group"><label>Notes</label><textarea name="notes" rows="2" placeholder="Optional notes..."></textarea></div>
                        <div class="form-row">
                            <div class="form-group"><label>Prescribed By</label><input type="text" name="prescribed_by" placeholder="e.g. Dr. Ann" value="Dr. Ann"></div>
                            <div class="form-group"><label>Prescription Date</label><input type="date" name="prescription_date" value="<?= date('Y-m-d') ?>"></div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('addMedModal').classList.remove('open')">Cancel</button>
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
                        <input type="hidden" name="action" value="edit_medication">
                        <input type="hidden" name="pet_id" value="<?= $view_id ?>">
                        <input type="hidden" name="med_id" id="edit_med_id">
                        <div class="form-group"><label>Medication Name *</label><input type="text" name="med_name" id="edit_med_name" required></div>
                        <div class="form-row">
                            <div class="form-group"><label>Dosage</label><input type="text" name="dosage" id="edit_med_dose"></div>
                            <div class="form-group"><label>Frequency</label><input type="text" name="frequency" id="edit_med_freq"></div>
                        </div>
                        <div class="form-group"><label>Notes</label><textarea name="notes" rows="2" id="edit_med_notes"></textarea></div>
                        <div class="form-row">
                            <div class="form-group"><label>Prescribed By</label><input type="text" name="prescribed_by" id="edit_med_prescby"></div>
                            <div class="form-group"><label>Prescription Date</label><input type="date" name="prescription_date" id="edit_med_prescdt"></div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('editMedModal').classList.remove('open')">Cancel</button>
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
                        <div class="form-group"><label>Vaccine Name *</label><input type="text" name="vac_name" required placeholder="e.g. Rabies, DHPP"></div>
                        <div class="form-row">
                            <div class="form-group"><label>Date Given</label><input type="date" name="date_given"></div>
                            <div class="form-group"><label>Next Due Date</label><input type="date" name="next_due"></div>
                        </div>
                        <div class="form-group"><label>Notes / Manufacturer</label><textarea name="notes" rows="2" placeholder="e.g. Nobivac"></textarea></div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('addVacModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Add Vaccine</button>
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
                        <div class="form-group"><label>Vaccine Name *</label><input type="text" name="vac_name" id="edit_vac_name" required></div>
                        <div class="form-row">
                            <div class="form-group"><label>Date Given</label><input type="date" name="date_given" id="edit_vac_given"></div>
                            <div class="form-group"><label>Next Due Date</label><input type="date" name="next_due" id="edit_vac_due"></div>
                        </div>
                        <div class="form-group"><label>Notes</label><textarea name="notes" rows="2" id="edit_vac_notes"></textarea></div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('editVacModal').classList.remove('open')">Cancel</button>
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
                        <div class="form-group"><label>Allergen *</label><input type="text" name="allergen" required placeholder="e.g. Pollen, Bees, Chicken"></div>
                        <div class="form-group"><label>Reaction</label><input type="text" name="reaction" placeholder="e.g. Itchy nose and watery eyes"></div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('addAllergyModal').classList.remove('open')">Cancel</button>
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
                        <div class="form-group"><label>Allergen *</label><input type="text" name="allergen" id="edit_allergen" required></div>
                        <div class="form-group"><label>Reaction</label><input type="text" name="reaction" id="edit_reaction"></div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('editAllergyModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Consultation -->
            <div class="modal-overlay" id="addConsultModal">
                <div class="modal" style="max-width:540px;">
                    <button class="modal-close" onclick="document.getElementById('addConsultModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">📋 Add Consultation</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_consultation">
                        <input type="hidden" name="pet_id" value="<?= $view_id ?>">
                        <div class="form-row">
                            <div class="form-group"><label>Date of Visit *</label><input type="date" name="date_of_visit" required></div>
                            <div class="form-group"><label>Veterinarian</label><input type="text" name="vet_name" placeholder="e.g. Dr. Ann" value="Dr. Ann"></div>
                        </div>
                        <div class="form-group"><label>Reason for Visit</label><input type="text" name="reason_for_visit" placeholder="e.g. Loss of appetite"></div>
                        <div class="form-group"><label>Diagnosis</label><input type="text" name="diagnosis" placeholder="e.g. Mild gastrointestinal infection"></div>
                        <div class="form-group"><label>Treatment Given</label><textarea name="treatment_given" rows="2" style="resize:vertical;"></textarea></div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('addConsultModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Save Consultation</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Consultation -->
            <div class="modal-overlay" id="editConsultModal">
                <div class="modal" style="max-width:540px;">
                    <button class="modal-close" onclick="document.getElementById('editConsultModal').classList.remove('open')">×</button>
                    <h3 class="modal-title">📋 Edit Consultation</h3>
                    <form method="POST">
                        <input type="hidden" name="action"     value="edit_consultation">
                        <input type="hidden" name="pet_id"     value="<?= $view_id ?>">
                        <input type="hidden" name="consult_id" id="edit_consult_id">
                        <div class="form-row">
                            <div class="form-group"><label>Date of Visit *</label><input type="date" name="date_of_visit" id="edit_consult_date" required></div>
                            <div class="form-group"><label>Veterinarian</label><input type="text" name="vet_name" id="edit_consult_vet"></div>
                        </div>
                        <div class="form-group"><label>Reason for Visit</label><input type="text" name="reason_for_visit" id="edit_consult_reason"></div>
                        <div class="form-group"><label>Diagnosis</label><input type="text" name="diagnosis" id="edit_consult_diagnosis"></div>
                        <div class="form-group"><label>Treatment Given</label><textarea name="treatment_given" rows="2" id="edit_consult_treatment" style="resize:vertical;"></textarea></div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('editConsultModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add/Edit Medical History -->
            <div class="modal-overlay" id="editMedHistModal">
                <div class="modal" style="max-width:500px;">
                    <button class="modal-close" onclick="document.getElementById('editMedHistModal').classList.remove('open')">×</button>
                    <h3 class="modal-title" id="medHistModalTitle">🏥 Add Medical History</h3>
                    <form method="POST">
                        <input type="hidden" name="action"  id="medHistAction" value="save_medical_history">
                        <input type="hidden" name="pet_id"  value="<?= $view_id ?>">
                        <input type="hidden" name="mh_id"   id="edit_mh_id" value="">
                        <div class="form-group"><label>Clinic Name</label><input type="text" name="clinic_name" id="edit_mh_clinic"></div>
                        <div class="form-group"><label>Past Illnesses</label><input type="text" name="past_illnesses" id="edit_mh_illnesses"></div>
                        <div class="form-group"><label>Chronic Conditions</label><input type="text" name="chronic_conditions" id="edit_mh_chronic"></div>
                        <div class="form-group"><label>Injuries/Surgeries</label><input type="text" name="injuries_surgeries" id="edit_mh_injuries"></div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-gray" onclick="document.getElementById('editMedHistModal').classList.remove('open')">Cancel</button>
                            <button type="submit" class="btn btn-teal">Save History</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- ══ PET LIST ════════════════════════════════════ -->

            <?php $total_archived_pets = countRows($conn, 'pets', 'archived=1'); ?>
            <div class="stat-cards" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
                <div class="stat-card"><div class="stat-label">Total Pets</div><div class="stat-value"><?= $total_pets ?></div></div>
                <div class="stat-card"><div class="stat-label">Dogs</div><div class="stat-value"><?= $total_dogs ?></div></div>
                <div class="stat-card"><div class="stat-label">Cats</div><div class="stat-value"><?= $total_cats ?></div></div>
                <div class="stat-card" style="cursor:pointer;" onclick="window.location='pet_records.php?<?= $show_archived ? '' : 'archived=1' ?>'">
                    <div class="stat-label">🗄️ Archived</div><div class="stat-value"><?= $total_archived_pets ?></div>
                    <div class="stat-sub"><?= $show_archived ? 'Click to show active' : 'Click to view' ?></div>
                </div>
            </div>

            <div style="background:rgba(255,255,255,0.9);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
                <h3 style="font-family:var(--font-head);font-weight:700;font-size:17px;margin-bottom:16px;">
                    <?= $show_archived ? '🗄️ Archived Pets' : 'Pets' ?>
                    <?php if ($show_archived): ?>
                        <a href="pet_records.php" class="btn btn-gray btn-sm" style="margin-left:10px;">← Back to Active Pets</a>
                    <?php endif; ?>
                </h3>
                <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
                    <div class="search-box"><span>🔍</span><input type="text" name="search" placeholder="Search Pet..." value="<?= htmlspecialchars($search) ?>"></div>
                    <select name="species" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:13px;background:#fff;outline:none;">
                        <option value="">All Species</option>
                        <option value="dog" <?= $species==='dog'?'selected':'' ?>>🐶 Dogs</option>
                        <option value="cat" <?= $species==='cat'?'selected':'' ?>>🐱 Cats</option>
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
                                    <th>No.</th><th>Pet Name</th><th>Sex/Age</th><th>Breed</th>
                                    <th>Owner</th><th>Contact No.</th><th>Status</th><th>Last Visit</th>
                                    <th>Details</th><th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pets as $i => $pet): ?>
                                    <?php $last_visit = getRow($conn, "SELECT MAX(appointment_date) AS lv FROM appointments WHERE pet_id={$pet['id']} AND status='completed'"); ?>
                                    <tr>
                                        <td style="color:var(--text-light);"><?= $i+1 ?></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:8px;">
                                                <?php if ($pet['photo']): ?>
                                                    <img src="../assets/css/images/pets/<?= htmlspecialchars($pet['photo']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;" onerror="this.style.display='none'">
                                                <?php else: ?>
                                                    <div style="width:32px;height:32px;border-radius:50%;background:var(--teal-light);display:flex;align-items:center;justify-content:center;font-size:16px;"><?= $pet['species']==='cat' ? '🐱' : '🐶' ?></div>
                                                <?php endif; ?>
                                                <strong><?= htmlspecialchars($pet['name']) ?></strong>
                                            </div>
                                        </td>
                                        <td><?= strtoupper(substr($pet['gender'],0,1)) ?>/<?= $pet['age'] ?> yrs</td>
                                        <td><?= htmlspecialchars($pet['breed'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($pet['owner_name'] ?? '—') ?></td>
                                        <td style="font-size:12px;"><?= htmlspecialchars($pet['owner_contact'] ?? '—') ?></td>
                                        <td><?= statusBadge($pet['status']) ?></td>
                                        <td style="font-size:12px;"><?= $last_visit['lv'] ? formatDate($last_visit['lv']) : '—' ?></td>
                                        <td><a href="pet_records.php?view=<?= $pet['id'] ?>" class="btn btn-teal btn-sm">View Profile</a></td>
                                        <td>
                                            <div style="display:flex;gap:4px;">
                                                <a href="pet_records.php?view=<?= $pet['id'] ?>" class="btn btn-gray btn-sm">Edit</a>
                                                <?php if ($show_archived): ?>
                                                    <a href="pet_records.php?unarchive_pet=<?= $pet['id'] ?>" class="btn btn-teal btn-sm">Restore</a>
                                                <?php else: ?>
                                                    <button class="btn btn-red btn-sm" onclick="openDelModal('pet', <?= $pet['id'] ?>, 0, '<?= htmlspecialchars(addslashes($pet['name'])) ?>')">Archive</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; // end single pet / list ?>

        </div>
    </div>
</div>

<!-- ══ Image Lightbox ════════════════════════════════════════ -->
<div class="lightbox-overlay" id="lightboxOverlay" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">×</button>
    <img id="lightboxImg" src="" alt="">
    <div class="lightbox-caption" id="lightboxCaption"></div>
</div>

<!-- ══ Centered Delete Confirmation Modal ════════════════════ -->
<div class="del-overlay" id="delOverlay" onclick="if(event.target===this)closeDelModal()">
    <div class="del-box">
        <div class="del-icon-wrap" id="delIcon">🗑️</div>
        <h3 id="delTitle">Delete Record?</h3>
        <p id="delMsg">This record will be permanently deleted.<br>This action cannot be undone.</p>
        <div class="del-actions">
            <button class="btn-keep" onclick="closeDelModal()">Keep It</button>
            <button class="btn-del-confirm" id="delConfirmBtn" onclick="submitDelete()">✕ Yes, Delete</button>
        </div>
    </div>
</div>

<!-- Hidden delete forms -->
<form id="delFormMedication"   method="POST" style="display:none;"><input type="hidden" name="action" value="delete_medication"><input type="hidden" name="med_id" id="del_med_id"><input type="hidden" name="pet_id" id="del_med_pid"></form>
<form id="delFormVaccine"      method="POST" style="display:none;"><input type="hidden" name="action" value="delete_vaccine"><input type="hidden" name="vac_id" id="del_vac_id"><input type="hidden" name="pet_id" id="del_vac_pid"></form>
<form id="delFormAllergy"      method="POST" style="display:none;"><input type="hidden" name="action" value="delete_allergy"><input type="hidden" name="allergy_id" id="del_allergy_id"><input type="hidden" name="pet_id" id="del_allergy_pid"></form>
<form id="delFormConsultation" method="POST" style="display:none;"><input type="hidden" name="action" value="delete_consultation"><input type="hidden" name="consult_id" id="del_consult_id"><input type="hidden" name="pet_id" id="del_consult_pid"></form>
<form id="delFormMedHistory"   method="POST" style="display:none;"><input type="hidden" name="action" value="delete_medical_history"><input type="hidden" name="mh_id" id="del_mh_id"><input type="hidden" name="pet_id" id="del_mh_pid"></form>
<form id="delFormDocument"     method="POST" style="display:none;"><input type="hidden" name="action" value="delete_document"><input type="hidden" name="doc_id" id="del_doc_id"><input type="hidden" name="pet_id" id="del_doc_pid"></form>

<script src="../assets/js/main.js"></script>
<script>
// ── Edit helpers ──────────────────────────────────────────────
function openEditMed(med) {
    document.getElementById('edit_med_id').value      = med.id;
    document.getElementById('edit_med_name').value    = med.medication_name;
    document.getElementById('edit_med_dose').value    = med.dosage            || '';
    document.getElementById('edit_med_freq').value    = med.frequency         || '';
    document.getElementById('edit_med_notes').value   = med.notes             || '';
    document.getElementById('edit_med_prescby').value = med.prescribed_by     || '';
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
function openEditConsult(con) {
    document.getElementById('edit_consult_id').value        = con.id;
    document.getElementById('edit_consult_date').value      = con.date_of_visit    || '';
    document.getElementById('edit_consult_vet').value       = con.vet_name         || '';
    document.getElementById('edit_consult_reason').value    = con.reason_for_visit || '';
    document.getElementById('edit_consult_diagnosis').value = con.diagnosis        || '';
    document.getElementById('edit_consult_treatment').value = con.treatment_given  || '';
    document.getElementById('editConsultModal').classList.add('open');
}
function openEditMedHist(mh) {
    document.getElementById('medHistModalTitle').textContent = '🏥 Edit Medical History';
    document.getElementById('medHistAction').value           = 'edit_medical_history';
    document.getElementById('edit_mh_id').value             = mh.id;
    document.getElementById('edit_mh_clinic').value         = mh.clinic_name        || '';
    document.getElementById('edit_mh_illnesses').value      = mh.past_illnesses     || '';
    document.getElementById('edit_mh_chronic').value        = mh.chronic_conditions || '';
    document.getElementById('edit_mh_injuries').value       = mh.injuries_surgeries || '';
    document.getElementById('editMedHistModal').classList.add('open');
}
function openEditDoc(d) {
    document.getElementById('edit_doc_id').value    = d.id;
    document.getElementById('edit_doc_title').value = d.title;
    document.getElementById('edit_doc_type').value  = d.document_type;
    document.getElementById('edit_doc_notes').value = d.notes || '';
    document.getElementById('editDocModal').classList.add('open');
}

// ── Delete modal ─────────────────────────────────────────────
let _delType = null;
const DEL_CONFIG = {
    medication:      { icon:'💊', title:'Delete Medication?',      form:'delFormMedication',   idField:'del_med_id',      pidField:'del_med_pid' },
    vaccine:         { icon:'💉', title:'Delete Vaccine?',         form:'delFormVaccine',       idField:'del_vac_id',      pidField:'del_vac_pid' },
    allergy:         { icon:'🌿', title:'Delete Allergy?',         form:'delFormAllergy',       idField:'del_allergy_id',  pidField:'del_allergy_pid' },
    consultation:    { icon:'📋', title:'Delete Consultation?',    form:'delFormConsultation',  idField:'del_consult_id',  pidField:'del_consult_pid' },
    medical_history: { icon:'🏥', title:'Delete Medical History?', form:'delFormMedHistory',    idField:'del_mh_id',       pidField:'del_mh_pid' },
    document:        { icon:'📂', title:'Delete Document?',        form:'delFormDocument',      idField:'del_doc_id',      pidField:'del_doc_pid' },
    pet:             { icon:'🗄️', title:'Archive Pet Record?',     form: null,                  idField: null,             pidField: null },
};

function openDelModal(type, id, pid, label) {
    _delType = type;
    const cfg = DEL_CONFIG[type];
    document.getElementById('delIcon').textContent  = cfg.icon;
    document.getElementById('delTitle').textContent = cfg.title;
    const isPet = (type === 'pet');
    document.getElementById('delMsg').innerHTML = isPet
        ? `<strong>${label}</strong> will be moved to the archive.<br><span style="color:#f59e0b;font-size:12px;">You can restore it later.</span>`
        : `<strong>${label}</strong> will be permanently deleted.<br><span style="color:#ef4444;font-size:12px;">This action cannot be undone.</span>`;
    if (type === 'pet') {
        document.getElementById('delConfirmBtn').dataset.petId = id;
    } else {
        document.getElementById(cfg.idField).value  = id;
        document.getElementById(cfg.pidField).value = pid;
    }
    document.getElementById('delOverlay').classList.add('open');
}

function submitDelete() {
    const cfg = DEL_CONFIG[_delType];
    if (_delType === 'pet') {
        window.location.href = 'pet_records.php?archive_pet=' + document.getElementById('delConfirmBtn').dataset.petId;
    } else {
        document.getElementById(cfg.form).submit();
    }
}
function closeDelModal() {
    document.getElementById('delOverlay').classList.remove('open');
    _delType = null;
}

// ── Lightbox ─────────────────────────────────────────────────
function openLightbox(src, caption) {
    document.getElementById('lightboxImg').src         = src;
    document.getElementById('lightboxCaption').textContent = caption;
    document.getElementById('lightboxOverlay').classList.add('open');
}
function closeLightbox() {
    document.getElementById('lightboxOverlay').classList.remove('open');
}

// ── Document filter chips ─────────────────────────────────────
function filterDocs(type, el) {
    document.querySelectorAll('.doc-filter-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('#docGrid .doc-card').forEach(card => {
        card.style.display = (type === 'all' || card.dataset.type === type) ? '' : 'none';
    });
}

// ── Upload drop zone ─────────────────────────────────────────
function handleFileSelect(input) {
    const file = input.files[0];
    if (!file) return;

    // Show filename in zone
    document.getElementById('uploadFileName').style.display = 'block';
    document.getElementById('uploadFileName').textContent   = '📎 ' + file.name;

    // Auto-fill title if empty
    const titleEl = document.getElementById('docTitleInput');
    if (!titleEl.value) {
        titleEl.value = file.name.replace(/\.[^.]+$/, '').replace(/[_-]/g, ' ');
    }

    // Auto-set doc type for images
    const imgEl  = document.getElementById('uploadImgPreview');
    const infoEl = document.getElementById('uploadFileInfo');
    imgEl.style.display  = 'none';
    infoEl.style.display = 'none';

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => { imgEl.src = e.target.result; imgEl.style.display = 'block'; };
        reader.readAsDataURL(file);
        document.getElementById('docTypeSelect').value = 'photo';
    } else if (file.type === 'application/pdf') {
        infoEl.textContent  = '📕 PDF: ' + file.name;
        infoEl.style.display = 'block';
    } else {
        infoEl.textContent  = '📄 ' + file.name;
        infoEl.style.display = 'block';
    }

    document.getElementById('uploadSubmitBtn').disabled = false;
}

// Drag-and-drop on the zone
(function() {
    const zone = document.getElementById('uploadZone');
    if (!zone) return;
    ['dragenter','dragover'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.add('drag-over'); }));
    ['dragleave','drop'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.remove('drag-over'); }));
    zone.addEventListener('drop', ev => {
        const dt = ev.dataTransfer;
        if (dt && dt.files.length) {
            document.getElementById('docFileInput').files = dt.files;
            handleFileSelect(document.getElementById('docFileInput'));
        }
    });
})();

// Fake progress bar on submit (UX only — real progress needs XHR)
document.getElementById('uploadDocForm')?.addEventListener('submit', function() {
    const bar  = document.getElementById('uploadProgress');
    const fill = document.getElementById('uploadProgressBar');
    if (bar && fill) {
        bar.style.display = 'block';
        let w = 0;
        const t = setInterval(() => {
            w = Math.min(w + Math.random() * 18, 88);
            fill.style.width = w + '%';
            if (w >= 88) clearInterval(t);
        }, 200);
    }
    document.getElementById('uploadSubmitBtn').disabled = true;
    document.getElementById('uploadSubmitBtn').textContent = 'Uploading…';
});

function closeUploadModal() {
    document.getElementById('uploadDocModal').classList.remove('open');
    // Reset form state
    document.getElementById('uploadDocForm').reset();
    document.getElementById('uploadFileName').style.display  = 'none';
    document.getElementById('uploadImgPreview').style.display = 'none';
    document.getElementById('uploadFileInfo').style.display   = 'none';
    document.getElementById('uploadSubmitBtn').disabled = true;
    document.getElementById('uploadSubmitBtn').textContent = '⬆️ Upload';
}

// ── Reset add medical history modal ──────────────────────────
document.querySelectorAll('[onclick*="editMedHistModal"]').forEach(btn => {
    if (btn.textContent.trim() === '+ Add') {
        btn.addEventListener('click', () => {
            document.getElementById('medHistModalTitle').textContent = '🏥 Add Medical History';
            document.getElementById('medHistAction').value           = 'save_medical_history';
            ['edit_mh_id','edit_mh_clinic','edit_mh_illnesses','edit_mh_chronic','edit_mh_injuries']
                .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        });
    }
});

// ── Toggle table rows ─────────────────────────────────────────
function toggleRows(cls, btn) {
    const rows = document.querySelectorAll('.' + cls);
    const isHidden = rows[0].classList.contains('hidden-row');
    rows.forEach(r => r.classList.toggle('hidden-row', !isHidden));
    if (isHidden) {
        btn.textContent = '▲ Show Less';
    } else {
        const count = rows.length + 5;
        btn.textContent = '▼ View All (' + count + ' records)';
    }
}

// ── Escape & outside click closes all modals + lightbox ──────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeDelModal();
        closeLightbox();
        document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
});
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
});
</script>
</body>
</html>