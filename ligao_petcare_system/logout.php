<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: logout.php
// Purpose: Destroy session and redirect to login
// ============================================================
require_once 'includes/auth.php';

session_unset();
session_destroy();

header("Location: index.php");
exit();
?>