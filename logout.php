<?php
/**
 * logout.php — versi aman
 * ────────────────────────────────────────────────────────────
 * Harus diakses via POST + CSRF untuk mencegah CSRF logout attack.
 * Tetap mendukung GET untuk backward-compat (sidebar link lama).
 */

require_once 'includes/security.php';

Security::init();

// Jika POST, verifikasi CSRF dulu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCsrf();
}

// Log logout (opsional — aktifkan jika tabel login_log sudah ada)
if (!empty($_SESSION['user_id'])) {
    try {
        require_once 'config/database.php';
        $db   = new Database();
        $conn = $db->getConnection();
        $uid  = (int)$_SESSION['user_id'];
        $ip   = $_SERVER['REMOTE_ADDR'];
        $stmt = mysqli_prepare($conn, "INSERT INTO login_log (user_id, ip_address, status, created_at) VALUES (?, ?, 'logout', NOW())");
        mysqli_stmt_bind_param($stmt, 'is', $uid, $ip);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } catch (Throwable $e) {
        // Jangan sampai error logging menghentikan proses logout
    }
}

Security::forceLogout();
