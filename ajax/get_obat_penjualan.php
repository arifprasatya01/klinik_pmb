<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Sesi habis, silakan login ulang.']);
    exit;
}
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get obat yang stoknya > 0
// PENTING: Untuk penjualan langsung, harga = harga_beli + 20%
$query = "SELECT id, kode_obat, nama_obat, satuan, stok, harga_beli
          FROM obat 
          WHERE stok > 0 
          ORDER BY nama_obat ASC";
$result = mysqli_query($conn, $query);

$obat = array();
while ($row = mysqli_fetch_assoc($result)) {
    // Hitung harga jual untuk penjualan langsung (markup 20%)
    $harga_langsung = $row['harga_beli'] * 1.20;
    
    $obat[] = array(
        'id' => $row['id'],
        'kode' => $row['kode_obat'],
        'nama' => $row['nama_obat'],
        'satuan' => $row['satuan'],
        'stok' => $row['stok'],
        'harga_beli' => $row['harga_beli'],
        'harga' => $harga_langsung  // Harga dengan markup 20%
    );
}

header('Content-Type: application/json');
echo json_encode($obat);
?>