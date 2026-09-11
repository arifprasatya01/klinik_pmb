<?php
// PENTING: Tidak boleh ada spasi atau baris kosong sebelum tag <?php ini!

session_start();
header('Content-Type: application/json; charset=utf-8');

// Cek login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit;
}

require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
    exit;
}

$action        = $_POST['action'] ?? '';
$pembelian_id  = intval($_POST['pembelian_id'] ?? 0);

if ($pembelian_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID pembelian tidak valid']);
    exit;
}

// Ambil data pembelian terbaru dari DB
$stmt = mysqli_prepare($conn, "SELECT total_dengan_ppn, jumlah_dibayar FROM pembelian WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $pembelian_id);
mysqli_stmt_execute($stmt);
$result_row = mysqli_stmt_get_result($stmt);
$pembelian  = mysqli_fetch_assoc($result_row);

if (!$pembelian) {
    echo json_encode(['success' => false, 'message' => 'Data pembelian tidak ditemukan']);
    exit;
}

$total = (float) $pembelian['total_dengan_ppn'];

// ==================== ACTION: UPDATE JUMLAH DIBAYAR ====================
if ($action === 'update_jumlah') {
    $jumlah_dibayar = floatval($_POST['jumlah_dibayar'] ?? 0);

    if ($jumlah_dibayar < 0) {
        echo json_encode(['success' => false, 'message' => 'Nominal tidak boleh negatif']);
        exit;
    }

    $sisa = $total - $jumlah_dibayar;
    if ($sisa < 0) $sisa = 0;

    // Catatan: hanya update nominal & sisa hutang di sini.
    // Status pembayaran sengaja TIDAK diubah otomatis di langkah ini —
    // baru berubah saat badge status di-klik (action=update_status).
    $stmt = mysqli_prepare($conn, "UPDATE pembelian SET jumlah_dibayar = ?, sisa_pembayaran = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ddi", $jumlah_dibayar, $sisa, $pembelian_id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success'         => true,
            'jumlah_dibayar'  => $jumlah_dibayar,
            'sisa_pembayaran' => $sisa
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
    exit;
}

// ==================== ACTION: UPDATE STATUS PEMBAYARAN ====================
if ($action === 'update_status') {
    $jumlah_dibayar = (float) $pembelian['jumlah_dibayar'];

    if ($jumlah_dibayar <= 0) {
        $status = 'belum_bayar';
    } elseif ($jumlah_dibayar >= $total) {
        $status = 'lunas';
    } else {
        $status = 'dibayar_sebagian';
    }

    $sisa = $total - $jumlah_dibayar;
    if ($sisa < 0) $sisa = 0;

    $stmt = mysqli_prepare($conn, "UPDATE pembelian SET status_pembayaran = ?, sisa_pembayaran = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "sdi", $status, $sisa, $pembelian_id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success'         => true,
            'status'          => $status,
            'sisa_pembayaran' => $sisa
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
    exit;
}

// Action tidak dikenali
echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);