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

if (isset($_POST['pemeriksaan_id'])) {
    $pemeriksaan_id = mysqli_real_escape_string($conn, $_POST['pemeriksaan_id']);
    
    // Get data pemeriksaan
    $query = "SELECT pm.*, p.no_antrian, p.keluhan, p.pemeriksaan_fisik, ps.nama_lengkap, ps.no_rm, ps.tgl_lahir, ps.jenis_kelamin,
              TIMESTAMPDIFF(YEAR, ps.tgl_lahir, CURDATE()) as umur
              FROM pemeriksaan pm
              JOIN pendaftaran p ON pm.pendaftaran_id = p.id
              JOIN pasien ps ON p.pasien_id = ps.id
              WHERE pm.id = '$pemeriksaan_id'";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
        
        // Get resep
        $query_resep = "SELECT dr.*, o.nama_obat, o.satuan
                        FROM resep r
                        JOIN detail_resep dr ON r.id = dr.resep_id
                        JOIN obat o ON dr.obat_id = o.id
                        WHERE r.pemeriksaan_id = '$pemeriksaan_id' AND (r.hapus IS NULL OR r.hapus = 0)";
        $result_resep = mysqli_query($conn, $query_resep);
        $resep = mysqli_fetch_all($result_resep, MYSQLI_ASSOC);
        
        // Get tindakan
        $query_tindakan = "SELECT dt.*, mt.nama_tindakan, mt.kode_tindakan
                          FROM detail_tindakan dt
                          JOIN master_tindakan mt ON dt.tindakan_id = mt.id
                          WHERE dt.pemeriksaan_id = '$pemeriksaan_id' AND hapus=0" ;
        $result_tindakan = mysqli_query($conn, $query_tindakan);
        $tindakan = mysqli_fetch_all($result_tindakan, MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'resep' => $resep,
            'tindakan' => $tindakan
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak ditemukan'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter tidak lengkap'
    ]);
}
// Query detail BHP
$query_bhp = "SELECT db.*, mb.nama_bhp, mb.satuan
              FROM detail_bhp db
              JOIN master_bhp mb ON db.bhp_id = mb.id
              WHERE db.pemeriksaan_id = '$pemeriksaan_id' AND db.hapus = 0";
$result_bhp = mysqli_query($conn, $query_bhp);
$bhp = [];
while ($row = mysqli_fetch_assoc($result_bhp)) {
    $bhp[] = $row;
}
$response['bhp'] = $bhp;
?>