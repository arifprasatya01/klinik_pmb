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

$query = "SELECT id, kode_mitra, nama_apotek 
          FROM mitra_apotek 
          WHERE status = 'aktif' 
          ORDER BY nama_apotek ASC";
$result = mysqli_query($conn, $query);

$mitra = array();
while ($row = mysqli_fetch_assoc($result)) {
    $mitra[] = $row;
}

header('Content-Type: application/json');
echo json_encode($mitra);
?>