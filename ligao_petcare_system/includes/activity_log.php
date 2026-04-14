<?php
// ============================================================
// Ligao Petcare & Veterinary Clinic
// File: includes/activity_log.php
// Purpose: Centralised activity-logging helper
//
// Usage:
//   require_once '../includes/activity_log.php';
//   logActivity($conn, $user_id, 'user', 'login', 'auth', 'User logged in.');
//   logActivity($conn, $admin_id, 'admin', 'delete_staff', 'settings',
//               "Deleted staff account ID 7 (Dr. Ann).", ['staff_id' => 7]);
// ============================================================

/**
 * Write one activity row to the activity_logs table.
 *
 * @param mysqli  $conn        Active DB connection
 * @param int     $user_id     Actor's user ID
 * @param string  $role        'admin' | 'staff' | 'user'
 * @param string  $action      Snake_case verb, e.g. 'update_profile'
 * @param string  $module      Feature area, e.g. 'appointments'
 * @param string  $description Human-readable sentence
 * @param array   $meta        Optional key-value pairs stored as JSON
 */
function logActivity(
    $conn,
    int    $user_id,
    string $role,
    string $action,
    string $module,
    string $description,
    array  $meta = []
): void {
    $ip  = _getClientIP();
    $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);

    $user_id     = (int) $user_id;
    $role        = mysqli_real_escape_string($conn, $role);
    $action      = mysqli_real_escape_string($conn, $action);
    $module      = mysqli_real_escape_string($conn, $module);
    $description = mysqli_real_escape_string($conn, $description);
    $ip          = mysqli_real_escape_string($conn, $ip);
    $ua          = mysqli_real_escape_string($conn, $ua);
    $meta_json   = !empty($meta)
        ? mysqli_real_escape_string($conn, json_encode($meta, JSON_UNESCAPED_UNICODE))
        : 'NULL';

    $meta_sql = ($meta_json === 'NULL') ? 'NULL' : "'$meta_json'";

    mysqli_query($conn,
        "INSERT INTO activity_logs
            (user_id, role, action, module, description, ip_address, user_agent, meta)
         VALUES
            ($user_id, '$role', '$action', '$module', '$description', '$ip', '$ua', $meta_sql)"
    );
}

/** Resolve the real client IP (handles proxies). */
function _getClientIP(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

/**
 * Convenience wrapper – automatically determines role from session.
 * Requires $_SESSION['user_id'] and $_SESSION['role'] (or defaults to 'user').
 */
function logUserActivity(
    $conn,
    string $action,
    string $module,
    string $description,
    array  $meta = []
): void {
    $uid  = (int) ($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? 'user';
    if ($uid > 0) {
        logActivity($conn, $uid, $role, $action, $module, $description, $meta);
    }
}