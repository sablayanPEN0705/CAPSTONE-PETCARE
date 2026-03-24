<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: includes/db.php
// Purpose: Database connection using MySQLi
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Default XAMPP username
define('DB_PASS', '');            // Default XAMPP password (empty)
define('DB_NAME', 'capstone_db');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("
        <div style='
            font-family: Arial, sans-serif;
            background: #fee2e2;
            border: 1px solid #ef4444;
            color: #991b1b;
            padding: 20px;
            margin: 20px;
            border-radius: 8px;
        '>
            <strong>Database Connection Failed!</strong><br>
            Error: " . mysqli_connect_error() . "<br><br>
            Please make sure:
            <ul>
                <li>XAMPP MySQL service is running</li>
                <li>You have imported the <code>database.sql</code> file in phpMyAdmin</li>
                <li>Database name is <code>ligao_petcare</code></li>
            </ul>
        </div>
    ");
}

// Set character set to UTF-8
mysqli_set_charset($conn, "utf8mb4");

// ============================================================
// Helper Functions
// ============================================================

/**
 * Sanitize input to prevent SQL injection / XSS
 */
function sanitize($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

/**
 * Get a single row from the database
 */
function getRow($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

/**
 * Get multiple rows from the database
 */
function getRows($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    $rows = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Count rows
 */
function countRows($conn, $table, $where = '') {
    $sql = "SELECT COUNT(*) as total FROM $table";
    if ($where) $sql .= " WHERE $where";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

/**
 * Format date to readable format
 */
function formatDate($date) {
    if (!$date) return 'N/A';
    return date('F d, Y', strtotime($date));
}

/**
 * Format time to readable format
 */
function formatTime($time) {
    if (!$time) return 'N/A';
    return date('h:i A', strtotime($time));
}

/**
 * Format currency in Philippine Peso
 */
function formatPeso($amount) {
    return '₱' . number_format($amount, 2);
}

/**
 * Get status badge HTML
 */
function statusBadge($status) {
    $colors = [
        'pending'       => '#f59e0b',
        'confirmed'     => '#3b82f6',
        'completed'     => '#10b981',
        'cancelled'     => '#ef4444',
        'paid'          => '#10b981',
        'overdue'       => '#ef4444',
        'active'        => '#10b981',
        'inactive'      => '#6b7280',
        'available'     => '#10b981',
        'not_available' => '#ef4444',
        'in_stock'      => '#10b981',
        'low_stock'     => '#f59e0b',
        'out_of_stock'  => '#ef4444',
    ];
    $color = $colors[$status] ?? '#6b7280';
    $label = ucwords(str_replace('_', ' ', $status));
    return "<span class='badge' style='background:$color;color:#fff;padding:3px 10px;border-radius:20px;font-size:12px;'>$label</span>";
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require login — redirect to index if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('../index.php');
    }
}

/**
 * Require admin — redirect if not admin
 */
function requireAdmin() {
    if (!isAdmin()) {
        redirect('../index.php');
    }
}

/**
 * Get unread message count for current user
 */
function getUnreadMessages($conn, $user_id) {
    $user_id = (int)$user_id;
    $result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM messages WHERE receiver_id = $user_id AND is_read = 0");
    $row = mysqli_fetch_assoc($result);
    return $row['cnt'] ?? 0;
}

/**
 * Get unread notification count for current user
 */
function getUnreadNotifications($conn, $user_id) {
    $user_id = (int)$user_id;
    $result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = $user_id AND is_read = 0");
    $row = mysqli_fetch_assoc($result);
    return $row['cnt'] ?? 0;
}
?>