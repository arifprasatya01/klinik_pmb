<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Sesi habis, silakan login ulang.']);
    exit;
}
require_once '../config/database.php';
require_once '../includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

if (isset($_POST['no_rm'])) {
    $no_rm = sanitize($_POST['no_rm']);
    
    // Get pasien_id
    $query = "SELECT id FROM pasien WHERE no_rm = '$no_rm'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $pasien = mysqli_fetch_assoc($result);
        $pasien_id = $pasien['id'];
        
        // Get riwayat pemeriksaan
        $query = "SELECT 
                    pm.id,
                    pm.tgl_pemeriksaan,
                    pm.diagnosa,
                    pm.catatan,
                    p.keluhan,
                    u.nama_lengkap as nama_dokter,
                    DATE_FORMAT(pm.tgl_pemeriksaan, '%d %M %Y') as tanggal_formatted,
                    DATE_FORMAT(pm.tgl_pemeriksaan, '%H:%i') as jam
                  FROM pemeriksaan pm
                  JOIN pendaftaran p ON pm.pendaftaran_id = p.id
                  LEFT JOIN users u ON pm.dokter_id = u.id
                  WHERE p.pasien_id = '$pasien_id' 
                  AND pm.status = 'selesai'
                  ORDER BY pm.tgl_pemeriksaan DESC
                  LIMIT 20";
        
        $result = mysqli_query($conn, $query);
        
        if ($result) {
            $riwayat = array();
            
            while ($row = mysqli_fetch_assoc($result)) {
                $pemeriksaan_id = $row['id'];
                
                // Get resep obat
                $query_resep = "SELECT 
                                dr.jumlah,
                                dr.aturan_pakai,
                                o.nama_obat
                              FROM resep r
                              JOIN detail_resep dr ON r.id = dr.resep_id
                              JOIN obat o ON dr.obat_id = o.id
                              WHERE r.pemeriksaan_id = '$pemeriksaan_id'
                              AND (r.hapus IS NULL OR r.hapus = 0)";
                
                $result_resep = mysqli_query($conn, $query_resep);
                $resep = array();
                while ($r = mysqli_fetch_assoc($result_resep)) {
                    $resep[] = $r;
                }
                
                // Get tindakan
                $query_tindakan = "SELECT 
                                    dt.jumlah,
                                    dt.tarif,
                                    dt.keterangan,
                                    mt.nama_tindakan
                                  FROM detail_tindakan dt
                                  JOIN master_tindakan mt ON dt.tindakan_id = mt.id
                                  WHERE dt.pemeriksaan_id = '$pemeriksaan_id'";
                
                $result_tindakan = mysqli_query($conn, $query_tindakan);
                $tindakan = array();
                while ($t = mysqli_fetch_assoc($result_tindakan)) {
                    $tindakan[] = $t;
                }
                
                $row['resep'] = $resep;
                $row['tindakan'] = $tindakan;
                $riwayat[] = $row;
            }
            
            echo json_encode(array(
                'success' => true,
                'data' => $riwayat
            ));
        } else {
            echo json_encode(array(
                'success' => false,
                'message' => 'Gagal mengambil riwayat: ' . mysqli_error($conn)
            ));
        }
    } else {
        echo json_encode(array(
            'success' => false,
            'message' => 'Pasien tidak ditemukan'
        ));
    }
} else {
    echo json_encode(array(
        'success' => false,
        'message' => 'Parameter tidak lengkap'
    ));
}
?>