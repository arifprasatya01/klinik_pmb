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
$query = "SELECT id, kode_obat as kode, nama_obat as nama, satuan, stok, harga_beli
          FROM obat 
          WHERE stok > 0 
          ORDER BY nama_obat ASC";
$result = mysqli_query($conn, $query);

$obat = array();
while ($row = mysqli_fetch_assoc($result)) {
    $obat[] = $row;
}

header('Content-Type: application/json');
echo json_encode($obat);
?>