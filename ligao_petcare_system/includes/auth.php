<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: includes/auth.php
// Purpose: Session start + authentication helpers
// Include this at the TOP of every page
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
?>