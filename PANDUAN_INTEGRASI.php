<?php
/**
 * ============================================================
 *  PANDUAN INTEGRASI SECURITY — TEMPLATE HEADER HALAMAN
 * ============================================================
 *
 *  Salin blok ini ke bagian PALING ATAS setiap file .php kamu
 *  (ganti blok session_start() + require_once yang lama).
 *
 *  Ganti [ROLE_YANG_BOLEH] dengan role yang sesuai, misalnya:
 *    Security::requireRole(['admin', 'kasir']);
 *    Security::requireRole(['admin', 'petugas']);
 *    Security::requireRole(['admin']);
 *
 *  Untuk halaman yang butuh login tapi semua role boleh:
 *    Security::requireLogin();
 *
 * ============================================================
 */

// ────────────────────────────────────────────────────────────
//  BLOK KEAMANAN — PASANG DI PALING ATAS SETIAP HALAMAN
// ────────────────────────────────────────────────────────────
require_once 'config/database.php';
require_once 'includes/security.php';   // ← satu file, semua fitur

Security::init();                        // headers + session guard
Security::requireRole(['admin', 'kasir']); // ganti sesuai halaman

$db   = new Database();
$conn = $db->getConnection();

$role = $_SESSION['role'];
$nama = $_SESSION['nama_lengkap'];

// ────────────────────────────────────────────────────────────
//  CONTOH HANDLER POST AMAN (untuk form yang ada di halaman ini)
// ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_data'])) {

    Security::verifyCsrf(); // ← wajib, sebelum apapun

    // Ambil & bersihkan input
    $nama_input   = Security::clean($_POST['nama']   ?? '');
    $jumlah_input = Security::int($_POST['jumlah']   ?? '');
    $tanggal_input = Security::date($_POST['tanggal'] ?? '');

    if ($jumlah_input === null) {
        $error = 'Jumlah harus berupa angka.';
    } elseif ($tanggal_input === null) {
        $error = 'Format tanggal tidak valid.';
    } else {
        // Selalu pakai prepared statement
        $stmt = mysqli_prepare($conn, "INSERT INTO tabel_kamu (nama, jumlah, tanggal) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sis', $nama_input, $jumlah_input, $tanggal_input);

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Data berhasil disimpan.';
        } else {
            $error = 'Gagal menyimpan data.';
        }
        mysqli_stmt_close($stmt);
    }
}

// ────────────────────────────────────────────────────────────
//  DI <head> HTML — tambah csrfMeta() untuk AJAX
// ────────────────────────────────────────────────────────────
/*
<head>
    ...
    <?= Security::csrfMeta() ?>   ← untuk AJAX via security.js
    ...
    <script src="asset/js/security.js"></script>  ← load security.js
</head>
*/

// ────────────────────────────────────────────────────────────
//  DI FORM HTML — tambah csrfField()
// ────────────────────────────────────────────────────────────
/*
<form method="POST">
    <?= Security::csrfField() ?>   ← hidden input csrf_token
    ...
</form>
*/

// ────────────────────────────────────────────────────────────
//  OUTPUT VARIABEL KE HTML — selalu pakai e() atau Security::esc()
// ────────────────────────────────────────────────────────────
/*
<td><?= e($row['nama_pasien']) ?></td>
<td><?= Security::esc($row['alamat']) ?></td>
*/

// ────────────────────────────────────────────────────────────
//  DI AJAX HANDLER (.php di folder ajax/) — template minimal
// ────────────────────────────────────────────────────────────
/*
<?php
require_once '../includes/security.php';
require_once '../config/database.php';

Security::init();
Security::requireLogin();              // atau requireRole()
Security::requireAjax();
Security::verifyCsrf(null, true);     // true = balas JSON error

header('Content-Type: application/json');

// ... logika ajax kamu ...
*/

// ────────────────────────────────────────────────────────────
//  LINK LOGOUT DI SIDEBAR — ganti ke form POST
// ────────────────────────────────────────────────────────────
/*
<!-- JANGAN pakai <a href="logout.php"> — rentan CSRF logout attack -->
<!-- GANTI dengan: -->
<form method="POST" action="logout.php" class="d-inline">
    <?= Security::csrfField() ?>
    <button type="submit" class="btn btn-link nav-link text-danger p-0">
        <i class="fas fa-sign-out-alt"></i> Logout
    </button>
</form>
*/

echo "// File ini adalah panduan integrasi — tidak dieksekusi langsung.\n";
