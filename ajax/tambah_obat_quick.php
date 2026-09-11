<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'petugas' && $_SESSION['role'] != 'admin')) {
    http_response_code(403);
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_obat = sanitize($_POST['nama_obat']);
    $satuan    = sanitize($_POST['satuan']);

    if (empty($nama_obat)) {
        echo json_encode(['success' => false, 'message' => 'Nama obat tidak boleh kosong']);
        exit();
    }

    $kode_obat = generateKodeObat();

    $query = "INSERT INTO obat (kode_obat, nama_obat, satuan, harga_beli, harga_jual, stok, stok_minimum)
              VALUES ('$kode_obat', '$nama_obat', '$satuan', 0, 0, 0, 5)";

    if (mysqli_query($conn, $query)) {
        $new_id = mysqli_insert_id($conn);

        // Ambil semua obat terbaru untuk refresh dropdown
        $result = mysqli_query($conn, "SELECT id, kode_obat, nama_obat, satuan, harga_beli, harga_jual, stok FROM obat ORDER BY nama_obat");
        $obat_list = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $obat_list[] = $row;
        }

        echo json_encode([
            'success'   => true,
            'new_id'    => $new_id,
            'nama_obat' => $nama_obat,
            'obat_list' => $obat_list
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
}
?>