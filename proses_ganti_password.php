<?php
/**
 * proses_ganti_password.php — versi aman
 * Menggantikan versi lama; semua logic sama, security ditambah.
 */

date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'includes/security.php';

Security::init();
Security::requireLogin();
Security::requireAjax();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method tidak valid.']);
    exit;
}

// Verifikasi CSRF (kirim JSON error jika gagal)
Security::verifyCsrf(null, true);

$db   = new Database();
$conn = $db->getConnection();

$password_lama = $_POST['password_lama']       ?? '';
$password_baru = $_POST['password_baru']       ?? '';
$konfirmasi    = $_POST['konfirmasi_password'] ?? '';
$user_id       = (int)$_SESSION['user_id'];

// ── Validasi input ────────────────────────────────────────
if (empty($password_lama) || empty($password_baru) || empty($konfirmasi)) {
    echo json_encode(['status' => 'error', 'message' => 'Semua field wajib diisi.']);
    exit;
}
if (strlen($password_baru) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'Password baru minimal 8 karakter.']);
    exit;
}
if ($password_baru !== $konfirmasi) {
    echo json_encode(['status' => 'error', 'message' => 'Konfirmasi password tidak cocok.']);
    exit;
}

// ── Ambil hash dari DB ────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'User tidak ditemukan.']);
    exit;
}

// ── Verifikasi password lama ──────────────────────────────
$hash_db    = $user['password'];
$valid_lama = Security::isMd5Hash($hash_db)
    ? (md5($password_lama) === $hash_db)
    : Security::verifyPassword($password_lama, $hash_db);

if (!$valid_lama) {
    echo json_encode(['status' => 'error', 'message' => 'Password lama tidak sesuai.']);
    exit;
}

// ── Simpan password baru dengan hash berlapis ─────────────
$hash_baru = Security::hashPassword($password_baru);
$stmt2     = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt2, 'si', $hash_baru, $user_id);

if (mysqli_stmt_execute($stmt2)) {
    mysqli_stmt_close($stmt2);
    echo json_encode(['status' => 'success', 'message' => 'Password berhasil diubah.']);
} else {
    mysqli_stmt_close($stmt2);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan password, coba lagi.']);
}
exit;
