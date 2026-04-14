<?php
// ============================================================
// File: includes/get_pets.php
// Purpose: AJAX endpoint — return pets for a given user_id
// ============================================================
require_once 'auth.php';
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode([]);
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) {
    echo json_encode([]);
    exit();
}

$pets = getRows($conn,
    "SELECT id, name, species, breed FROM pets WHERE user_id=$user_id ORDER BY name ASC");

header('Content-Type: application/json');
echo json_encode($pets);
?>