<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Sesi habis, silakan login ulang.']);
    exit;
}
require_once '../config/database.php';
$db   = new Database();
$conn = $db->getConnection();

$keyword = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';

$query  = "SELECT id, nama_bhp, satuan, stok FROM master_bhp 
           WHERE aktif = 1 AND stok > 0 AND nama_bhp LIKE '%$keyword%' 
           ORDER BY nama_bhp LIMIT 20";
$result = mysqli_query($conn, $query);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);