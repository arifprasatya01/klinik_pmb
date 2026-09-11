<?php
// Function untuk sanitize input
function sanitize($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

// Function untuk generate No. RM
function generateNoRM() {
    global $conn;
    
    $query = "SELECT no_rm FROM pasien ORDER BY id DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        // Hapus semua tanda "-" lalu jadikan angka
        $last_no = intval(str_replace('-', '', $row['no_rm']));
        $new_no  = $last_no + 1;
    } else {
        $new_no = 1;
    }
    
    // Format XX-XX-XX (6 digit)
    $padded = str_pad($new_no, 6, '0', STR_PAD_LEFT);
    
    return substr($padded, 0, 2) . '-' .
           substr($padded, 2, 2) . '-' .
           substr($padded, 4, 2);
}

// ✅ FUNGSI YANG SUDAH DIPERBAIKI
function generateNoAntrian($poli = 'umum') {
    global $conn;

    // Tentukan prefix berdasarkan poli
    $prefix = ($poli == 'kebidanan') ? 'K' : 'U';

    // Ambil nomor urut TERAKHIR hari ini untuk poli yang sama
    $query = "SELECT no_antrian FROM pendaftaran 
              WHERE DATE(tgl_daftar) = CURDATE() 
              AND poli = '$poli'
              ORDER BY id DESC 
              LIMIT 1";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        // Ambil angka dari format "U-001" atau "K-001"
        $no = intval(substr($row['no_antrian'], 2)) + 1;
    } else {
        $no = 1;
    }

    // Format: U-001, U-002, K-001, dst.
    return $prefix . '-' . str_pad($no, 3, '0', STR_PAD_LEFT);
}

// Function untuk format Rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Function untuk format tanggal Indonesia
function formatTanggal($tanggal) {
    $bulan = array(
        1 => 'Januari',
        2 => 'Februari', 
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    );
    
    $pecahkan = explode('-', $tanggal);
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}

// Function untuk hitung umur
function hitungUmur($tgl_lahir) {
    $birthDate = new DateTime($tgl_lahir);
    $today = new DateTime('today');
    $umur = $birthDate->diff($today)->y;
    return $umur;
}

// Function untuk get status badge color
function getStatusBadge($status) {
    switch($status) {
        case 'menunggu':
            return 'bg-warning';
        case 'diperiksa':
            return 'bg-info';
        case 'selesai':
            return 'bg-success';
        case 'dibatalkan':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// Function untuk generate nomor resep
function generateNoResep() {
    global $conn;
    
    $tanggal = date('Ymd');
    
    // Hitung resep hari ini
    $query = "SELECT COUNT(*) as total FROM resep WHERE DATE(tgl_resep) = CURDATE()";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    
    $nomor_urut = $row['total'] + 1;
    
    return 'RSP-' . $tanggal . '-' . str_pad($nomor_urut, 4, '0', STR_PAD_LEFT);
}
function generateKodeObat() {
    global $conn;
    
    // Ambil kode obat terakhir
    $query = "SELECT kode_obat FROM obat ORDER BY id DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        // Ambil angka dari kode obat (contoh: OBT-000001 -> 1)
        $last_no = intval(str_replace('OBT-', '', $row['kode_obat']));
        $new_no = $last_no + 1;
    } else {
        // Jika belum ada obat, mulai dari 1
        $new_no = 1;
    }
    
    // Format: OBT-000001, OBT-000002, dst
    return 'OBT-' . str_pad($new_no, 6, '0', STR_PAD_LEFT);
}
// Function untuk cek stok obat
function cekStokObat($obat_id) {
    global $conn;
    
    $query = "SELECT stok FROM obat WHERE id = '$obat_id'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['stok'];
    }
    
    return 0;
}

// Function untuk update stok obat
function updateStokObat($obat_id, $jumlah, $operasi = 'kurang') {
    global $conn;
    
    if ($operasi == 'kurang') {
        $query = "UPDATE obat SET stok = stok - $jumlah WHERE id = '$obat_id'";
    } else {
        $query = "UPDATE obat SET stok = stok + $jumlah WHERE id = '$obat_id'";
    }
    
    return mysqli_query($conn, $query);
}

// Function untuk log aktivitas
function logAktivitas($user_id, $aktivitas, $keterangan = '') {
    global $conn;
    
    $query = "INSERT INTO log_aktivitas (user_id, aktivitas, keterangan) 
              VALUES ('$user_id', '$aktivitas', '$keterangan')";
    
    return mysqli_query($conn, $query);
}

// Function untuk validasi password
// Function untuk validasi password
function validasiPassword($password, $hash_password) {
    return md5($password) == $hash_password;
}

if (!function_exists('hashPasswordBerlapis')) {
function hashPasswordBerlapis($password) {
    $step1 = md5($password);
    $step2 = hash('sha256', $step1 . $password);
    $step3 = password_hash($step2, PASSWORD_BCRYPT, ['cost' => 12]);
    return $step3;
}
}

function verifyPasswordBerlapis($password, $hash) {
    $step1 = md5($password);
    $step2 = hash('sha256', $step1 . $password);
    return password_verify($step2, $hash);
}

function isMD5Hash($hash) {
    return (bool) preg_match('/^[a-f0-9]{32}$/i', $hash);
}

// Function untuk generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}
function generateKodeTindakan() {
    return "TDK" . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
}
?>