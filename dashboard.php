<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$role = $_SESSION['role'];
$nama = $_SESSION['nama_lengkap'];

// Proses Validasi Pembayaran Mitra
if (isset($_POST['validasi_pembayaran'])) {
    $tagihan_id = sanitize($_POST['tagihan_id']);
    $tgl_validasi = date('Y-m-d H:i:s');
    $validasi_oleh = $_SESSION['user_id'];
    
    $query_update = "UPDATE tagihan_mitra SET 
                     status_bayar = 'lunas',
                     tgl_validasi = ?,
                     validasi_oleh = ?
                     WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query_update);
    mysqli_stmt_bind_param($stmt, "sii", $tgl_validasi, $validasi_oleh, $tagihan_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $success = "Pembayaran berhasil divalidasi!";
    } else {
        $error = "Gagal validasi pembayaran!";
    }
    mysqli_stmt_close($stmt);
}

// Proses Update Status Expired
if (isset($_POST['update_status_expired'])) {
    $detail_pembelian_id = sanitize($_POST['detail_pembelian_id']);
    $query_update = "UPDATE detail_pembelian SET status_expired = 1 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query_update);
    mysqli_stmt_bind_param($stmt, "i", $detail_pembelian_id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Status expired berhasil diperbarui!";
    } else {
        $_SESSION['error'] = "Gagal memperbarui status expired!";
    }
    mysqli_stmt_close($stmt);
    header("Location: dashboard.php");
    exit();
}

// ===================== STATISTIK =====================

// Penjualan Hari Ini
$query_harian = "SELECT 
    (SELECT COALESCE(SUM(total_setelah_diskon), 0) FROM pembayaran WHERE status = 'lunas' AND DATE(tgl_bayar) = CURDATE()) 
    + (SELECT COALESCE(SUM(COALESCE(total_setelah_diskon, total_bayar)), 0) FROM penjualan_langsung WHERE status = 'lunas' AND DATE(tgl_penjualan) = CURDATE()) 
    AS total";
$result_harian = mysqli_query($conn, $query_harian);
$penjualan_harian = mysqli_fetch_assoc($result_harian)['total'];

// Penjualan Bulan Ini
$query_bulanan = "SELECT 
    (SELECT COALESCE(SUM(total_setelah_diskon), 0) FROM pembayaran 
     WHERE status ='lunas' AND tgl_bayar >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
     AND tgl_bayar < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01'))
    + (SELECT COALESCE(SUM(total_bayar), 0) FROM penjualan_langsung 
       WHERE status='lunas' AND tgl_penjualan >= DATE_FORMAT(CURDATE(), '%Y-%m-01') 
       AND tgl_penjualan < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')) AS total";
$result_bulanan = mysqli_query($conn, $query_bulanan);
$penjualan_bulanan = mysqli_fetch_assoc($result_bulanan)['total'];

// Total Hutang Supplier
$query_hutang = "SELECT COALESCE(SUM(sisa_pembayaran), 0) as total FROM pembelian WHERE status_pembayaran != 'lunas'";
$result_hutang = mysqli_query($conn, $query_hutang);
$total_hutang = mysqli_fetch_assoc($result_hutang)['total'];

// ===================== KUNJUNGAN PASIEN =====================

// Kunjungan Pasien Hari Ini (yang sudah diperiksa = status selesai)
$query_kunjungan_hari = "SELECT COUNT(*) as total 
                         FROM pemeriksaan 
                         WHERE DATE(tgl_pemeriksaan) = CURDATE() 
                         AND status = 'selesai'";
$result_kunjungan_hari = mysqli_query($conn, $query_kunjungan_hari);
$kunjungan_hari_ini = mysqli_fetch_assoc($result_kunjungan_hari)['total'];

// Kunjungan Pasien Bulan Ini
$query_kunjungan_bulan = "SELECT COUNT(*) as total 
                          FROM pemeriksaan 
                          WHERE MONTH(tgl_pemeriksaan) = MONTH(CURDATE()) 
                          AND YEAR(tgl_pemeriksaan) = YEAR(CURDATE())
                          AND status = 'selesai'";
$result_kunjungan_bulan = mysqli_query($conn, $query_kunjungan_bulan);
$kunjungan_bulan_ini = mysqli_fetch_assoc($result_kunjungan_bulan)['total'];

// Breakdown kunjungan hari ini per jenis pasien
$query_kunjungan_jenis = "SELECT ps.jenis_pasien, COUNT(*) as total
                          FROM pemeriksaan pm
                          JOIN pendaftaran p ON pm.pendaftaran_id = p.id
                          JOIN pasien ps ON p.pasien_id = ps.id
                          WHERE DATE(pm.tgl_pemeriksaan) = CURDATE()
                          AND pm.status = 'selesai'
                          GROUP BY ps.jenis_pasien";
$result_kunjungan_jenis = mysqli_query($conn, $query_kunjungan_jenis);
$kunjungan_per_jenis = [];
while ($row = mysqli_fetch_assoc($result_kunjungan_jenis)) {
    $kunjungan_per_jenis[$row['jenis_pasien']] = $row['total'];
}

// Kunjungan 7 hari terakhir untuk chart
$query_kunjungan_chart = "SELECT DATE(tgl_pemeriksaan) as tanggal, COUNT(*) as total
                          FROM pemeriksaan
                          WHERE tgl_pemeriksaan >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                          AND status = 'selesai'
                          GROUP BY DATE(tgl_pemeriksaan)
                          ORDER BY tanggal ASC";
$result_kunjungan_chart = mysqli_query($conn, $query_kunjungan_chart);
$kunjungan_chart_labels = [];
$kunjungan_chart_data = [];
while ($row = mysqli_fetch_assoc($result_kunjungan_chart)) {
    $kunjungan_chart_labels[] = date('d/m', strtotime($row['tanggal']));
    $kunjungan_chart_data[] = (int)$row['total'];
}

// ===================== TINDAKAN PER USER (BULAN INI) =====================
$query_tindakan_user = "SELECT 
    u.id as user_id,
    u.nama_lengkap,
    u.role,
    COUNT(dt.id)                                    AS total_tindakan,
    SUM(dt.jumlah)                                  AS total_item,
    SUM(dt.subtotal)                                AS total_nominal,
    COALESCE(SUM(jt.nominal_jasa * dt.jumlah), 0)  AS total_jasa,
    SUM(dt.subtotal) - COALESCE(SUM(jt.nominal_jasa * dt.jumlah), 0) AS sisa_klinik
FROM detail_tindakan dt
JOIN users u ON dt.user_id = u.id
LEFT JOIN jasa_tindakan jt ON dt.tindakan_id = jt.tindakan_id
WHERE MONTH(dt.created_at) = MONTH(CURDATE())
  AND YEAR(dt.created_at)  = YEAR(CURDATE())
  AND dt.hapus = 0
GROUP BY dt.user_id, u.nama_lengkap, u.role
ORDER BY total_nominal DESC";
$result_tindakan_user = mysqli_query($conn, $query_tindakan_user);

$rekap_tindakan_user = [];
$grand_nominal = $grand_jasa = $grand_klinik = 0;
while ($row = mysqli_fetch_assoc($result_tindakan_user)) {
    $rekap_tindakan_user[] = $row;
    $grand_nominal += $row['total_nominal'];
    $grand_jasa    += $row['total_jasa'];
    $grand_klinik  += $row['sisa_klinik'];
}
// ===================== DATA LAINNYA =====================

// Data Hutang Supplier per minggu
$current_day = date('N');
$senin_minggu_ini = date('Y-m-d', strtotime('-' . ($current_day - 1) . ' days'));
$minggu_minggu_ini = date('Y-m-d', strtotime('+' . (7 - $current_day) . ' days'));

$query_hutang_detail = "SELECT 
    p.id, p.no_pembelian, p.tgl_pembelian, p.tgl_jatuh_tempo, p.jatuh_tempo_hari,
    p.total_dengan_ppn, p.jumlah_dibayar, p.sisa_pembayaran, p.status_pembayaran,
    s.nama_supplier,
    DATE_FORMAT(p.tgl_jatuh_tempo, '%W') as hari,
    DATE_FORMAT(p.tgl_jatuh_tempo, '%d/%m/%Y') as tanggal_formatted,
    DATE_FORMAT(p.tgl_pembelian, '%d/%m/%Y') as tgl_pembelian_formatted,
    DATEDIFF(p.tgl_jatuh_tempo, CURDATE()) as sisa_hari
FROM pembelian p
JOIN supplier s ON p.supplier_id = s.id
WHERE DATE(p.tgl_jatuh_tempo) BETWEEN '$senin_minggu_ini' AND '$minggu_minggu_ini'
ORDER BY p.tgl_jatuh_tempo ASC, p.no_pembelian ASC";
$result_hutang_detail = mysqli_query($conn, $query_hutang_detail);

$hutang_per_hari = [];
$hari_indonesia = [
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
];
while ($row = mysqli_fetch_assoc($result_hutang_detail)) {
    $hari = $hari_indonesia[$row['hari']];
    $hutang_per_hari[$hari][] = $row;
}

// Obat Kadaluarsa
$query_kadaluarsa = "SELECT dp.id as detail_pembelian_id, o.nama_obat,
    dp.expired_date as tgl_kadaluarsa, dp.jumlah as stok,
    DATEDIFF(dp.expired_date, CURDATE()) as hari_tersisa,
    p.no_pembelian, s.nama_supplier
    FROM detail_pembelian dp
    JOIN obat o ON dp.obat_id = o.id
    JOIN pembelian p ON dp.pembelian_id = p.id
    JOIN supplier s ON p.supplier_id = s.id
    WHERE dp.expired_date IS NOT NULL 
    AND dp.expired_date >= CURDATE()
    AND dp.expired_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
    AND dp.jumlah > 0 AND o.stok > 0 AND dp.status_expired = 0
    ORDER BY dp.expired_date ASC LIMIT 10";
$result_kadaluarsa = mysqli_query($conn, $query_kadaluarsa);
$jumlah_kadaluarsa = mysqli_num_rows($result_kadaluarsa);

// Pasien Baru Bulan Ini
$query_pasien_baru = "SELECT COUNT(*) as total FROM pasien WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
$result_pasien_baru = mysqli_query($conn, $query_pasien_baru);
$pasien_baru = mysqli_fetch_assoc($result_pasien_baru)['total'];

// Pendaftaran Hari Ini
$query_pendaftaran_hari = "SELECT COUNT(*) as total FROM pendaftaran WHERE DATE(tgl_daftar) = CURDATE()";
$result_pendaftaran_hari = mysqli_query($conn, $query_pendaftaran_hari);
$pendaftaran_hari_ini = mysqli_fetch_assoc($result_pendaftaran_hari)['total'];

// Obat Stok Minimum
$query_stok_min = "SELECT COUNT(*) as total FROM obat WHERE stok <= stok_minimum";
$result_stok_min = mysqli_query($conn, $query_stok_min);
$obat_stok_minimum = mysqli_fetch_assoc($result_stok_min)['total'];

// Resep Menunggu
$query_resep = "SELECT COUNT(*) as total FROM resep WHERE status = 'menunggu' AND status = 0";
$result_resep = mysqli_query($conn, $query_resep);
$resep_menunggu = mysqli_fetch_assoc($result_resep)['total'];

// Pembayaran Belum Lunas
$query_belum_bayar = "SELECT COUNT(*) as total FROM resep r LEFT JOIN pembayaran p ON r.id = p.resep_id WHERE r.status = 'selesai' AND p.id IS NULL";
$result_belum_bayar = mysqli_query($conn, $query_belum_bayar);
$belum_bayar = mysqli_fetch_assoc($result_belum_bayar)['total'];

// // Tagihan Mitra
// $query_mitra = "SELECT m.id, m.kode_mitra, m.nama_apotek, m.nama_pemilik, m.telepon,
//                 COALESCE(SUM(CASE WHEN t.status_bayar = 'belum_bayar' THEN t.nominal ELSE 0 END), 0) as total_tagihan,
//                 COUNT(CASE WHEN t.status_bayar = 'belum_bayar' THEN 1 END) as jumlah_tagihan
//                 FROM mitra_apotek m LEFT JOIN tagihan_mitra t ON m.id = t.mitra_id
//                 WHERE m.status = 'aktif' GROUP BY m.id ORDER BY total_tagihan DESC LIMIT 4";
// $result_mitra = mysqli_query($conn, $query_mitra)

// ===================== DAFTAR PASIEN HARI INI (LENGKAP) =====================
$query_pasien_hari_ini = "
    SELECT 
        pn.no_antrian,
        ps.nama_lengkap,
        ps.jenis_pasien,
        pm.tgl_pemeriksaan,
        pm.id AS pemeriksaan_id,
        pn.id AS pendaftaran_id
    FROM pemeriksaan pm
    JOIN pendaftaran pn ON pm.pendaftaran_id = pn.id
    JOIN pasien ps ON pn.pasien_id = ps.id
    WHERE DATE(pm.tgl_pemeriksaan) = CURDATE()
    AND pm.status = 'selesai'
    ORDER BY pn.no_antrian DESC
";
$result_pasien_hari_ini = mysqli_query($conn, $query_pasien_hari_ini);
$daftar_pasien_hari_ini = [];
while ($row = mysqli_fetch_assoc($result_pasien_hari_ini)) {
    $pid = $row['pemeriksaan_id'];

    // Ambil tindakan
$q_tindakan = "SELECT t.nama_tindakan, dt.jumlah,
               dt.subtotal AS subtotal_tindakan,
               COALESCE(jt.nominal_jasa, 0) AS nominal_jasa,
               COALESCE(jt.nominal_jasa * dt.jumlah, 0) AS subtotal_jasa
               FROM detail_tindakan dt
               JOIN master_tindakan t ON dt.tindakan_id = t.id
               LEFT JOIN jasa_tindakan jt ON t.id = jt.tindakan_id
               WHERE dt.pemeriksaan_id = $pid AND dt.hapus = 0";
    $r_tindakan = mysqli_query($conn, $q_tindakan);
    $tindakan_list = [];
    while ($t = mysqli_fetch_assoc($r_tindakan)) $tindakan_list[] = $t;

    // Ambil obat dari resep
$q_obat = "SELECT o.nama_obat, dr.jumlah, dr.aturan_pakai AS signa,
           o.harga_jual harga_satuan,
           (dr.jumlah * o.harga_jual) AS subtotal_obat
           FROM resep r
           JOIN detail_resep dr ON r.id = dr.resep_id
           JOIN obat o ON dr.obat_id = o.id
           WHERE r.pemeriksaan_id = $pid AND r.hapus = 0";
    $r_obat = mysqli_query($conn, $q_obat);
    $obat_list = [];
    while ($o = mysqli_fetch_assoc($r_obat)) $obat_list[] = $o; 
    // Ambil jasa bidan via pembayaran → resep → pemeriksaan
    $q_jasa_bidan = "SELECT jb.jumlah AS jasa_bidan, jb.status_batal_bayar
                     FROM jasa_bidan jb
                     JOIN pembayaran p ON jb.pembayaran_id = p.id
                     JOIN resep r ON p.resep_id = r.id
                     WHERE r.pemeriksaan_id = $pid
                     AND jb.status_batal_bayar = 0
                     LIMIT 1";
    $r_jasa_bidan = mysqli_query($conn, $q_jasa_bidan);
    $jasa_bidan   = mysqli_fetch_assoc($r_jasa_bidan);
    $row['jasa_bidan'] = $jasa_bidan ? $jasa_bidan['jasa_bidan'] : 0;

    $row['tindakan'] = $tindakan_list;
    $row['obat']     = $obat_list;

    // Akumulasi total
$total_harga_tindakan = array_sum(array_column($tindakan_list, 'subtotal_tindakan'));
$total_jasa_tindakan  = array_sum(array_column($tindakan_list, 'subtotal_jasa'));
$total_harga_obat     = array_sum(array_column($obat_list, 'subtotal_obat'));

    $row['tindakan'] = $tindakan_list;
    $row['obat']     = $obat_list;
    $row['total_harga_tindakan'] = $total_harga_tindakan;
$row['total_jasa_tindakan']  = $total_jasa_tindakan;
$row['total_harga_obat']     = $total_harga_obat;
    $daftar_pasien_hari_ini[] = $row;
}

// Chart Penjualan 7 Hari
$query_chart = "SELECT DATE(tgl_bayar) as tanggal, SUM(total_bayar) as total FROM pembayaran WHERE tgl_bayar >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(tgl_bayar) ORDER BY tanggal ASC";
$result_chart = mysqli_query($conn, $query_chart);
$chart_labels = [];
$chart_data = [];
while ($row = mysqli_fetch_assoc($result_chart)) {
    $chart_labels[] = date('d/m', strtotime($row['tanggal']));
    $chart_data[] = $row['total'];
}

// Transaksi Terakhir
$query_transaksi = "SELECT p.*, ps.nama_lengkap, pn.no_antrian FROM pembayaran p
                    JOIN resep r ON p.resep_id = r.id JOIN pemeriksaan pm ON r.pemeriksaan_id = pm.id
                    JOIN pendaftaran pn ON pm.pendaftaran_id = pn.id JOIN pasien ps ON pn.pasien_id = ps.id
                    WHERE r.hapus=0 AND p.status='lunas'
                    ORDER BY p.tgl_bayar DESC LIMIT 5";
$result_transaksi = mysqli_query($conn, $query_transaksi);

// Top 5 Obat Terlaris
$query_top_obat = "SELECT nama_obat, SUM(total_terjual) AS total_terjual FROM (
    SELECT o.nama_obat, dr.jumlah AS total_terjual FROM detail_resep dr JOIN obat o ON dr.obat_id = o.id JOIN resep r ON dr.resep_id = r.id WHERE MONTH(r.tgl_resep) = MONTH(CURDATE()) AND YEAR(r.tgl_resep) = YEAR(CURDATE())
    UNION ALL
    SELECT o.nama_obat, dp.jumlah AS total_terjual FROM detail_penjualan_langsung dp JOIN obat o ON dp.obat_id = o.id JOIN penjualan_langsung p ON dp.penjualan_id = p.id WHERE MONTH(p.tgl_penjualan) = MONTH(CURDATE()) AND YEAR(p.tgl_penjualan) = YEAR(CURDATE())
) x GROUP BY nama_obat ORDER BY total_terjual DESC LIMIT 5";
$result_top_obat = mysqli_query($conn, $query_top_obat);

// Session messages
if (isset($_SESSION['success'])) { $success = $_SESSION['success']; unset($_SESSION['success']); }
if (isset($_SESSION['error'])) { $error = $_SESSION['error']; unset($_SESSION['error']); }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Healoka</title>
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link rel="shortcut icon" type="image/png" href="asset/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php include 'sidebar_style.php'; ?>
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
        .container-fluid { padding: 0; margin: 0; }
        .row { margin: 0; }
        .col-md-2 { flex: 0 0 250px; max-width: 250px; }
        .col-md-10 { flex: 1; margin-left: 250px; padding: 0; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stat-card { border: none; border-radius: 15px; transition: transform 0.3s, box-shadow 0.3s; overflow: hidden; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .stat-card .card-body { padding: 25px; }
        .stat-icon { width: 70px; height: 70px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 30px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .card-header { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; border-radius: 15px 15px 0 0 !important; padding: 15px 20px; font-weight: 600; }
        .table { margin-bottom: 0; }
        .table thead th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; font-weight: 600; font-size: 0.85em; }
        .badge { padding: 6px 12px; border-radius: 8px; font-weight: 500; }
        .page-title { color: #1f2937; font-weight: 700; margin-bottom: 20px; }
        .stat-value { font-size: 2rem; font-weight: 700; margin: 10px 0; }
        .stat-label { font-size: 0.9rem; color: #6c757d; margin-bottom: 5px; }
        .gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .gradient-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .gradient-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .gradient-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .gradient-info { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .gradient-teal { background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); }
        .gradient-rose { background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); }

        /* Kunjungan Card */
        .kunjungan-card { border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .kunjungan-main { background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); color: white; padding: 25px; }
        .kunjungan-sub { background: white; padding: 15px 20px; }
        .kunjungan-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 0.82em; font-weight: 600; }

        /* Mitra */
        .mitra-card { border-left: 4px solid #667eea; transition: all 0.3s; }
        .mitra-card:hover { border-left-color: #764ba2; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .mitra-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .mitra-name { font-weight: 700; font-size: 1.1rem; color: #1f2937; margin-bottom: 5px; }
        .mitra-code { color: #6b7280; font-size: 0.85rem; }
        .mitra-amount { font-size: 1.5rem; font-weight: 700; color: #ef4444; }
        .mitra-info { display: flex; justify-content: space-between; padding-top: 15px; border-top: 1px solid #e5e7eb; margin-top: 15px; }
        .modal-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .tagihan-item { padding: 15px; border-bottom: 1px solid #e5e7eb; transition: background-color 0.2s; }
        .tagihan-item:hover { background-color: #f9fafb; }
        .tagihan-item:last-child { border-bottom: none; }
        .total-section { background-color: #f3f4f6; padding: 20px; border-radius: 10px; margin-top: 20px; }
        .kadaluarsa-item { padding: 12px; border-bottom: 1px solid #e5e7eb; transition: background-color 0.2s; }
        .kadaluarsa-item:hover { background-color: #fef3c7; }
        .kadaluarsa-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
            
            <div class="container-fluid px-4">
                <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <h2 class="page-title"><i class="fas fa-chart-line me-2"></i>Dashboard Statistik</h2>
                <p class="text-muted mb-4"><i class="fas fa-calendar-day me-2"></i><?= date('l, d F Y') ?></p>
                
                <!-- ===== ROW 1: PENJUALAN & HUTANG ===== -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="stat-label mb-0">Pendapatan Hari Ini</p>
                                        <h3 class="stat-value text-success mb-0"><?= formatRupiah($penjualan_harian) ?></h3>
                                        <small class="text-muted"><i class="fas fa-calendar-day me-1"></i><?= date('d/m/Y') ?></small>
                                    </div>
                                    <div class="stat-icon gradient-success text-white"><i class="fas fa-money-bill-wave"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="stat-label mb-0">Penjualan Bulan Ini</p>
                                        <h3 class="stat-value text-primary mb-0"><?= formatRupiah($penjualan_bulanan) ?></h3>
                                        <small class="text-muted"><i class="fas fa-calendar-alt me-1"></i><?= date('F Y') ?></small>
                                    </div>
                                    <div class="stat-icon gradient-primary text-white"><i class="fas fa-chart-line"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#modalHutangSupplier">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="stat-label mb-0">Hutang Supplier</p>
                                        <h3 class="stat-value text-danger mb-0"><?= formatRupiah($total_hutang) ?></h3>
                                        <small class="text-muted"><i class="fas fa-exclamation-triangle me-1"></i>Belum Lunas</small>
                                    </div>
                                    <div class="stat-icon gradient-danger text-white"><i class="fas fa-file-invoice-dollar"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#modalKadaluarsa">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="stat-label mb-0">Obat Akan Kadaluarsa</p>
                                        <h3 class="stat-value text-warning mb-0"><?= number_format($jumlah_kadaluarsa) ?></h3>
                                        <small class="text-muted"><i class="fas fa-exclamation-circle me-1"></i>Dalam 6 Bulan</small>
                                    </div>
                                    <div class="stat-icon gradient-warning text-white"><i class="fas fa-pills"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== ROW 2: KUNJUNGAN PASIEN ===== -->
                <div class="row g-4 mb-4">
                    <!-- Kunjungan Hari Ini -->
                    <div class="col-md-6">
                        <div class="kunjungan-card">
                            <div class="kunjungan-main">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-1 opacity-75" style="font-size:0.9em;">
                                            <i class="fas fa-calendar-day me-2"></i>Kunjungan Pasien Hari Ini
                                        </p>
                                        <h2 class="mb-1" style="font-size:2.5rem; font-weight:700;">
                                            <?= number_format($kunjungan_hari_ini) ?>
                                            <small style="font-size:1rem; font-weight:400;">pasien</small>
                                        </h2>
                                        <small class="opacity-75"><?= date('l, d F Y') ?></small>
                                    </div>
                                    <div style="font-size:4rem; opacity:0.2;">
                                        <i class="fas fa-user-injured"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="kunjungan-sub">
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="kunjungan-badge" style="background:#d1fae5; color:#065f46;">
                                        <i class="fas fa-user"></i>
                                        Umum: <?= $kunjungan_per_jenis['umum'] ?? 0 ?>
                                    </span>
                                    <span class="kunjungan-badge" style="background:#dbeafe; color:#1e40af;">
                                        <i class="fas fa-id-card"></i>
                                        BPJS: <?= $kunjungan_per_jenis['bpjs'] ?? 0 ?>
                                    </span>
                                    <span class="kunjungan-badge" style="background:#fef3c7; color:#92400e;">
                                        <i class="fas fa-shield-alt"></i>
                                        Asuransi: <?= $kunjungan_per_jenis['asuransi'] ?? 0 ?>
                                    </span>
                                    <span class="kunjungan-badge ms-auto" style="background:#f3f4f6; color:#374151;">
                                        <i class="fas fa-clipboard-list"></i>
                                        Daftar hari ini: <?= $pendaftaran_hari_ini ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kunjungan Bulan Ini -->
                    <div class="col-md-6">
                        <div class="kunjungan-card">
                            <div class="kunjungan-main" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-1 opacity-75" style="font-size:0.9em;">
                                            <i class="fas fa-calendar-alt me-2"></i>Kunjungan Pasien Bulan Ini
                                        </p>
                                        <h2 class="mb-1" style="font-size:2.5rem; font-weight:700;">
                                            <?= number_format($kunjungan_bulan_ini) ?>
                                            <small style="font-size:1rem; font-weight:400;">pasien</small>
                                        </h2>
                                        <small class="opacity-75"><?= date('F Y') ?></small>
                                    </div>
                                    <div style="font-size:4rem; opacity:0.2;">
                                        <i class="fas fa-hospital-user"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="kunjungan-sub">
                                <div class="d-flex gap-2 flex-wrap align-items-center">
                                    <span class="kunjungan-badge" style="background:#ede9fe; color:#4c1d95;">
                                        <i class="fas fa-user-plus"></i>
                                        Pasien Baru Bulan Ini: <?= $pasien_baru ?>
                                    </span>
                                    <span class="kunjungan-badge" style="background:#fee2e2; color:#991b1b;">
                                        <i class="fas fa-money-check-alt"></i>
                                        Belum Bayar: <?= $belum_bayar ?>
                                    </span>
                                    <span class="kunjungan-badge" style="background:#fef3c7; color:#92400e;">
                                        <i class="fas fa-prescription"></i>
                                        Resep Menunggu: <?= $resep_menunggu ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ===== TINDAKAN PER USER ===== -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-md me-2"></i>Rekap Tindakan Per Tenaga Medis — <?= date('F Y') ?></span>
                <span class="badge bg-white text-dark"><?= count($rekap_tindakan_user) ?> user</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="px-3">#</th>
                                <th>Nama</th>
                                <th>Role</th>
                                <th class="text-center">Tindakan</th>
                                <th class="text-center">Item</th>
                                <th class="text-end">Nominal Tindakan</th>
                                <th class="text-end">Total Jasa</th>
                                <th class="text-end">Sisa Klinik</th>
                                <th class="text-center">% Jasa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rekap_tindakan_user) > 0): ?>
                                <?php
                                $role_badge = [
                                    'medis'    => 'primary',
                                    'petugas'  => 'warning',
                                    'apoteker' => 'success',
                                    'admin'    => 'danger',
                                ];
                                foreach ($rekap_tindakan_user as $i => $u):
                                    $pct_jasa = $u['total_nominal'] > 0
                                        ? round(($u['total_jasa'] / $u['total_nominal']) * 100, 1) : 0;
                                    $badge = $role_badge[$u['role']] ?? 'secondary';
                                ?>
                                <tr>
                                    <td class="px-3 text-muted"><?= $i + 1 ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($u['nama_lengkap']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $badge ?>">
                                            <?= ucfirst($u['role']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center fw-bold"><?= number_format($u['total_tindakan']) ?></td>
                                    <td class="text-center text-muted"><?= number_format($u['total_item']) ?></td>
                                    <td class="text-end fw-semibold">
                                        <?= formatRupiah($u['total_nominal']) ?>
                                    </td>
                                    <td class="text-end">
                                        <span style="background:#ecfdf5;color:#065f46;font-weight:700;padding:3px 10px;border-radius:8px;font-size:12px;">
                                            <?= formatRupiah($u['total_jasa']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold text-primary">
                                        <?= formatRupiah($u['sisa_klinik']) ?>
                                    </td>
                                    <td class="text-center">
                                        <div style="font-size:11px;color:#6b7280;margin-bottom:3px;"><?= $pct_jasa ?>%</div>
                                        <div style="height:6px;background:#e5e7eb;border-radius:4px;">
                                            <div style="height:100%;width:<?= min($pct_jasa,100) ?>%;background:linear-gradient(90deg,#667eea,#764ba2);border-radius:4px;"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-2x d-block mb-2 opacity-25"></i>
                                        Belum ada data tindakan bulan ini
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if (count($rekap_tindakan_user) > 0): ?>
                        <tfoot style="background:#f8f9fa;font-weight:700;">
                            <tr>
                                <td colspan="5" class="px-3 text-end">TOTAL BULAN INI</td>
                                <td class="text-end"><?= formatRupiah($grand_nominal) ?></td>
                                <td class="text-end" style="color:#065f46;"><?= formatRupiah($grand_jasa) ?></td>
                                <td class="text-end text-primary"><?= formatRupiah($grand_klinik) ?></td>
                                <td class="text-center">
                                    <?php $pct_total = $grand_nominal > 0 ? round(($grand_jasa/$grand_nominal)*100,1) : 0; ?>
                                    <small><?= $pct_total ?>% jasa</small>
                                </td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- Ringkasan bawah -->
                <?php if (count($rekap_tindakan_user) > 0): ?>
                <div class="row g-3 p-3" style="border-top:1px solid #f3f4f6;">
                    <div class="col-md-4">
                        <div style="background:#f0f4ff;border-radius:12px;padding:14px 18px;text-align:center;">
                            <div style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;">Total Nominal Tindakan</div>
                            <div style="font-size:20px;font-weight:800;color:#1f2937;"><?= formatRupiah($grand_nominal) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="background:#f0fdf4;border-radius:12px;padding:14px 18px;text-align:center;">
                            <div style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;">Total Jasa Tenaga Medis</div>
                            <div style="font-size:20px;font-weight:800;color:#065f46;"><?= formatRupiah($grand_jasa) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div style="background:linear-gradient(135deg,#667eea15,#764ba215);border:1px solid #667eea30;border-radius:12px;padding:14px 18px;text-align:center;">
                            <div style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;">Sisa Untung Klinik</div>
                            <div style="font-size:20px;font-weight:800;color:#667eea;"><?= formatRupiah($grand_klinik) ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===== DAFTAR PASIEN HARI INI ===== -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-clipboard-list me-2"></i>Daftar Pasien Hari Ini — <?= date('d F Y') ?></span>
                <span class="badge bg-white text-dark"><?= count($daftar_pasien_hari_ini) ?> pasien</span>
            </div>
           <div class="card-body p-0">
                <?php if (count($daftar_pasien_hari_ini) > 0): ?>
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-hover align-top mb-0">
                        <thead style="position: sticky; top: 0; z-index: 1; background: #f8f9fa;">
                            <tr>
                                <th class="px-3" style="width:80px;">Antrian</th>
                                <th style="width:180px;">Nama Pasien</th>
                                <th style="width:90px;">Jenis</th>
                                <th>Tindakan</th>
<th>Obat Diberikan</th>
<th style="text-align:right;width:130px;">Total Obat</th>
<th style="text-align:right;width:130px;">Jasa Medis</th>
<th style="text-align:right;width:130px;">Total Tindakan</th>
<th style="text-align:right;width:120px;">Jasa Tindakan</th>
                                
                            </tr>
                        </thead>
                        <tbody>
    <?php foreach ($daftar_pasien_hari_ini as $pasien):
        $jenis = $pasien['jenis_pasien'];
        $jenis_badge = match($jenis) {
            'bpjs'     => ['bg' => '#dbeafe', 'color' => '#1e40af', 'label' => 'BPJS'],
            'asuransi' => ['bg' => '#fef3c7', 'color' => '#92400e', 'label' => 'Asuransi'],
            default    => ['bg' => '#d1fae5', 'color' => '#065f46', 'label' => 'Umum'],
        };
    ?>
    <tr>
        <!-- Antrian -->
        <td class="px-3 text-center">
            <span style="display:inline-flex;align-items:center;justify-content:center;
                width:38px;height:38px;border-radius:50%;
                background:linear-gradient(135deg,#667eea,#764ba2);
                color:white;font-weight:700;font-size:0.9rem;">
                <?= $pasien['no_antrian'] ?>
            </span>
        </td>

        <!-- Nama Pasien -->
        <td>
            <div class="fw-semibold" style="color:#1f2937;">
                <?= htmlspecialchars($pasien['nama_lengkap']) ?>
            </div>
            <small class="text-muted">
                <?= date('H:i', strtotime($pasien['tgl_pemeriksaan'])) ?> WIB
            </small>
        </td>

        <!-- Jenis -->
        <td>
            <span style="background:<?= $jenis_badge['bg'] ?>;color:<?= $jenis_badge['color'] ?>;
                font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;">
                <?= $jenis_badge['label'] ?>
            </span>
        </td>

        <!-- Tindakan -->
        <td>
            <?php if (count($pasien['tindakan']) > 0): ?>
            <ul class="mb-0" style="list-style:none;padding-left:0;">
                <?php foreach ($pasien['tindakan'] as $t): ?>
                <li style="font-size:0.85rem;margin-bottom:3px;">
                    <i class="fas fa-stethoscope me-1" style="color:#667eea;font-size:0.75rem;"></i>
                    <?= htmlspecialchars($t['nama_tindakan']) ?>
                    <?php if ($t['jumlah'] > 1): ?>
                        <span class="badge bg-secondary ms-1" style="font-size:10px;"><?= $t['jumlah'] ?>x</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <span class="text-muted" style="font-size:0.8rem;font-style:italic;">—</span>
            <?php endif; ?>
        </td>

        <!-- Obat Diberikan -->
        <td>
            <?php if (count($pasien['obat']) > 0): ?>
            <ul class="mb-0" style="list-style:none;padding-left:0;">
                <?php foreach ($pasien['obat'] as $o): ?>
                <li style="font-size:0.83rem;margin-bottom:4px;">
                    <i class="fas fa-capsules me-1" style="color:#10b981;font-size:0.73rem;"></i>
                    <strong><?= htmlspecialchars($o['nama_obat']) ?></strong>
                    <span style="background:#f0fdf4;color:#065f46;padding:1px 7px;
                        border-radius:6px;font-size:11px;margin-left:4px;">
                        <?= $o['jumlah'] ?> pcs
                    </span>
                    <?php if (!empty($o['signa'])): ?>
                    <span style="background:#eff6ff;color:#1d4ed8;padding:1px 7px;
                        border-radius:6px;font-size:10px;margin-left:2px;">
                        <?= htmlspecialchars($o['signa']) ?>
                    </span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <span class="text-muted" style="font-size:0.8rem;font-style:italic;">Tidak ada resep</span>
            <?php endif; ?>
        </td>

        <!-- Total Harga Obat -->
        <td style="text-align:right;vertical-align:top;padding-top:10px;">
            <?php if ($pasien['total_harga_obat'] > 0): ?>
            <span style="background:#eff6ff;color:#1d4ed8;font-weight:700;
                         padding:3px 10px;border-radius:8px;font-size:12px;white-space:nowrap;">
                <?= formatRupiah($pasien['total_harga_obat']) ?>
            </span>
            <?php else: ?>
            <span style="color:#9ca3af;font-size:0.8rem;">—</span>
            <?php endif; ?>
        </td>

        <!-- Jasa Bidan -->
        <td style="text-align:right;vertical-align:top;padding-top:10px;">
            <?php if ($pasien['jasa_bidan'] > 0): ?>
            <span style="background:#fdf4ff;color:#7e22ce;font-weight:700;
                         padding:3px 10px;border-radius:8px;font-size:12px;white-space:nowrap;">
                <?= formatRupiah($pasien['jasa_bidan']) ?>
            </span>
            <?php else: ?>
            <span style="color:#9ca3af;font-size:0.8rem;">—</span>
            <?php endif; ?>
        </td>
        <!-- Total Harga Tindakan -->
        <td style="text-align:right;vertical-align:top;padding-top:10px;">
            <?php if ($pasien['total_harga_tindakan'] > 0): ?>
            <span style="background:#fef3c7;color:#92400e;font-weight:700;
                         padding:3px 10px;border-radius:8px;font-size:12px;white-space:nowrap;">
                <?= formatRupiah($pasien['total_harga_tindakan']) ?>
            </span>
            <?php else: ?>
            <span style="color:#9ca3af;font-size:0.8rem;">—</span>
            <?php endif; ?>
        </td>

        <!-- Jasa Tindakan -->
        <td style="text-align:right;vertical-align:top;padding-top:10px;">
            <?php if ($pasien['total_jasa_tindakan'] > 0): ?>
            <span style="background:#ecfdf5;color:#065f46;font-weight:700;
                         padding:3px 10px;border-radius:8px;font-size:12px;white-space:nowrap;">
                <?= formatRupiah($pasien['total_jasa_tindakan']) ?>
            </span>
            <?php else: ?>
            <span style="color:#9ca3af;font-size:0.8rem;">—</span>
            <?php endif; ?>
        </td>
        

    </tr>
    <?php endforeach; ?>
</tbody>

                    </table>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-user-injured fa-3x mb-3 opacity-25"></i>
                    <p>Belum ada pasien yang diperiksa hari ini</p>
                </div>
                      <?php endif; ?>
            </div>
        </div>
    </div>
</div>
                

                <!-- ===== SECONDARY STATS ===== -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div><p class="stat-label mb-1">Pendaftaran Hari Ini</p><h4 class="mb-0"><?= $pendaftaran_hari_ini ?></h4></div>
                                    <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-user-plus"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div><p class="stat-label mb-1">Resep Menunggu</p><h4 class="mb-0"><?= $resep_menunggu ?></h4></div>
                                    <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-prescription"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div><p class="stat-label mb-1">Belum Dibayar</p><h4 class="mb-0"><?= $belum_bayar ?></h4></div>
                                    <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-money-check-alt"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div><p class="stat-label mb-1">Stok Minimum</p><h4 class="mb-0"><?= $obat_stok_minimum ?></h4></div>
                                    <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-box"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ===== CHART AREA ===== -->
                <div class="row">
                    <!-- Chart Penjualan -->
                    <div class="col-md-5 mb-4">
                        <div class="card">
                            <div class="card-header"><i class="fas fa-chart-area me-2"></i>Penjualan 7 Hari Terakhir</div>
                            <div class="card-body"><canvas id="salesChart" height="120"></canvas></div>
                        </div>
                    </div>

                    <!-- Chart Kunjungan -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header" style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);">
                                <i class="fas fa-chart-bar me-2"></i>Kunjungan 7 Hari Terakhir
                            </div>
                            <div class="card-body"><canvas id="kunjunganChart" height="120"></canvas></div>
                        </div>
                    </div>
                    
                    <!-- Top Obat -->
                    <div class="col-md-3 mb-4">
                        <div class="card">
                            <div class="card-header"><i class="fas fa-star me-2"></i>Top 5 Obat Bulan Ini</div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($result_top_obat) > 0): ?>
                                    <div class="list-group list-group-flush">
                                        <?php $no = 1; while ($obat = mysqli_fetch_assoc($result_top_obat)): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <div>
                                                <span class="badge bg-primary rounded-circle me-2"><?= $no++ ?></span>
                                                <strong><?= $obat['nama_obat'] ?></strong>
                                            </div>
                                            <span class="badge bg-success"><?= $obat['total_terjual'] ?></span>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2"></i><p>Belum ada data</p></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ===== TRANSAKSI TERAKHIR ===== -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><i class="fas fa-history me-2"></i>Transaksi Terakhir</div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>No. Antrian</th><th>Pasien</th><th>Tanggal</th>
                                                <th>Total</th><th>Metode</th><th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (mysqli_num_rows($result_transaksi) > 0): ?>
                                                <?php while ($trx = mysqli_fetch_assoc($result_transaksi)): ?>
                                                <tr>
                                                    <td><strong><?= $trx['no_antrian'] ?></strong></td>
                                                    <td><?= $trx['nama_lengkap'] ?></td>
                                                    <td><?= date('d/m/Y H:i', strtotime($trx['tgl_bayar'])) ?></td>
                                                    <td><strong><?= formatRupiah($trx['jumlah_bayar']) ?></strong></td>
                                                    <td><span class="badge bg-info"><?= ucfirst($trx['metode_bayar']) ?></span></td>
                                                    <td><span class="badge bg-success">Lunas</span></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2"></i><p>Belum ada transaksi</p></td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODAL HUTANG SUPPLIER ===== -->
<div class="modal fade" id="modalHutangSupplier" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <h5 class="modal-title text-white"><i class="fas fa-file-invoice-dollar me-2"></i>Jatuh Tempo Minggu Ini (<?= date('d/m/Y', strtotime($senin_minggu_ini)) ?> - <?= date('d/m/Y', strtotime($minggu_minggu_ini)) ?>)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-4">
                    <div class="row">
                        <div class="col-md-6"><i class="fas fa-info-circle me-2"></i><strong>Keterangan:</strong></div>
                        <div class="col-md-6 text-end">
                            <span class="badge bg-success me-2"><i class="fas fa-check me-1"></i>Lunas</span>
                            <span class="badge bg-warning me-2"><i class="fas fa-clock me-1"></i>Dibayar Sebagian</span>
                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Belum Bayar</span>
                        </div>
                    </div>
                </div>
                <div class="accordion" id="accordionHutang">
                    <?php 
                    $hari_urutan = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
                    $accordion_index = 0;
                    foreach ($hari_urutan as $hari):
                        $accordion_index++;
                        $collapse_id = 'collapseHari' . $accordion_index;
                        $total_hari = $jumlah_faktur = $jumlah_lunas = $jumlah_belum = 0;
                        if (isset($hutang_per_hari[$hari])) {
                            foreach ($hutang_per_hari[$hari] as $faktur) {
                                $jumlah_faktur++;
                                $total_hari += $faktur['total_dengan_ppn'];
                                if ($faktur['status_pembayaran'] == 'lunas') $jumlah_lunas++;
                                else $jumlah_belum++;
                            }
                        }
                        $is_today = ($hari == $hari_indonesia[date('l')]);
                        $show_class = $is_today ? 'show' : '';
                        $collapsed_class = $is_today ? '' : 'collapsed';
                    ?>
                    <div class="accordion-item mb-2" style="border:1px solid #dee2e6; border-radius:10px;">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $collapsed_class ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>">
                                <div class="w-100">
                                    <div class="row align-items-center">
                                        <div class="col-md-3"><strong><i class="fas fa-calendar-day me-2 text-primary"></i><?= $hari ?></strong><?php if ($is_today): ?><span class="badge bg-primary ms-2">Hari Ini</span><?php endif; ?></div>
                                        <div class="col-md-3"><small class="text-muted">Total Penerimaan</small><br><strong class="text-primary"><?= formatRupiah($total_hari) ?></strong></div>
                                        <div class="col-md-3"><small class="text-muted">Jumlah Faktur</small><br><strong><?= $jumlah_faktur ?> Faktur</strong></div>
                                        <div class="col-md-3 text-end"><span class="badge bg-success me-1"><?= $jumlah_lunas ?> Lunas</span><span class="badge bg-danger"><?= $jumlah_belum ?> Belum</span></div>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="<?= $collapse_id ?>" class="accordion-collapse collapse <?= $show_class ?>" data-bs-parent="#accordionHutang">
                            <div class="accordion-body">
                                <?php if (isset($hutang_per_hari[$hari]) && count($hutang_per_hari[$hari]) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>No</th><th>No. Pembelian</th><th>Tgl Pembelian</th>
                                                    <th>Jatuh Tempo</th><th>Sisa Hari</th><th>Supplier</th>
                                                    <th class="text-end">Total</th><th class="text-end">Dibayar</th>
                                                    <th class="text-end">Sisa</th><th class="text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $no = 1; foreach ($hutang_per_hari[$hari] as $faktur):
                                                    $row_class = $badge_class = $status_text = '';
                                                    switch($faktur['status_pembayaran']) {
                                                        case 'lunas': $row_class='table-success'; $badge_class='bg-success'; $status_text='Lunas'; break;
                                                        case 'dibayar_sebagian': $row_class='table-warning'; $badge_class='bg-warning'; $status_text='Sebagian'; break;
                                                        default: $row_class='table-danger'; $badge_class='bg-danger'; $status_text='Belum Bayar';
                                                    }
                                                    $sisa_hari = $faktur['sisa_hari'];
                                                    if ($sisa_hari < 0) { $badge_hari='bg-danger'; $icon_hari='fa-exclamation-triangle'; $text_hari='Terlambat '.abs($sisa_hari).' hari'; }
                                                    elseif ($sisa_hari == 0) { $badge_hari='bg-warning'; $icon_hari='fa-clock'; $text_hari='Hari Ini'; }
                                                    elseif ($sisa_hari <= 3) { $badge_hari='bg-warning'; $icon_hari='fa-hourglass-half'; $text_hari=$sisa_hari.' hari lagi'; }
                                                    else { $badge_hari='bg-info'; $icon_hari='fa-calendar-check'; $text_hari=$sisa_hari.' hari lagi'; }
                                                ?>
                                                <tr class="<?= $row_class ?>">
                                                    <td><?= $no++ ?></td>
                                                    <td><strong><?= $faktur['no_pembelian'] ?></strong></td>
                                                    <td><?= $faktur['tgl_pembelian_formatted'] ?></td>
                                                    <td><strong class="text-primary"><?= $faktur['tanggal_formatted'] ?></strong></td>
                                                    <td class="text-center"><span class="badge <?= $badge_hari ?>"><i class="fas <?= $icon_hari ?> me-1"></i><?= $text_hari ?></span></td>
                                                    <td><?= htmlspecialchars($faktur['nama_supplier']) ?></td>
                                                    <td class="text-end"><strong><?= formatRupiah($faktur['total_dengan_ppn']) ?></strong></td>
                                                    <td class="text-end"><?= formatRupiah($faktur['jumlah_dibayar']) ?></td>
                                                    <td class="text-end"><strong class="<?= $faktur['status_pembayaran'] != 'lunas' ? 'text-danger' : '' ?>"><?= formatRupiah($faktur['sisa_pembayaran']) ?></strong></td>
                                                    <td class="text-center"><span class="badge <?= $badge_class ?>"><?= $status_text ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr><th colspan="6" class="text-end">TOTAL <?= strtoupper($hari) ?>:</th><th class="text-end"><?= formatRupiah($total_hari) ?></th><th colspan="3"></th></tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2"></i><p>Tidak ada penerimaan barang pada hari <?= $hari ?></p></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php
                $total_minggu = $total_lunas_minggu = $total_belum_lunas_minggu = 0;
                foreach ($hutang_per_hari as $hari_data) {
                    foreach ($hari_data as $faktur) {
                        $total_minggu += $faktur['total_dengan_ppn'];
                        if ($faktur['status_pembayaran'] == 'lunas') $total_lunas_minggu += $faktur['total_dengan_ppn'];
                        else $total_belum_lunas_minggu += $faktur['sisa_pembayaran'];
                    }
                }
                ?>
                <div class="card mt-4" style="border:2px solid #ef4444;">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8"><h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Total Jatuh Tempo Minggu Ini</h5></div>
                            <div class="col-md-4 text-end">
                                <h3 class="mb-0 text-primary"><?= formatRupiah($total_minggu) ?></h3>
                                <div class="mt-2">
                                    <small class="text-success me-2"><i class="fas fa-check-circle"></i> Lunas: <?= formatRupiah($total_lunas_minggu) ?></small>
                                    <small class="text-danger"><i class="fas fa-exclamation-circle"></i> Sisa: <?= formatRupiah($total_belum_lunas_minggu) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Tutup</button></div>
        </div>
    </div>
</div>

<!-- ===== MODAL KADALUARSA ===== -->
<div class="modal fade" id="modalKadaluarsa" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <h5 class="modal-title text-white"><i class="fas fa-pills me-2"></i>Obat Akan Kadaluarsa (6 Bulan Ke Depan)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($jumlah_kadaluarsa > 0): ?>
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i><strong>Perhatian!</strong> Terdapat <?= $jumlah_kadaluarsa ?> obat yang akan kadaluarsa dalam 6 bulan ke depan.</div>
                    <?php mysqli_data_seek($result_kadaluarsa, 0); $no = 1; while ($obat = mysqli_fetch_assoc($result_kadaluarsa)):
                        $hari_tersisa = $obat['hari_tersisa'];
                        if ($hari_tersisa <= 30) { $badge_class='bg-danger'; $icon_class='fa-exclamation-circle'; }
                        elseif ($hari_tersisa <= 60) { $badge_class='bg-warning'; $icon_class='fa-exclamation-triangle'; }
                        else { $badge_class='bg-info'; $icon_class='fa-info-circle'; }
                    ?>
                    <div class="kadaluarsa-item">
                        <div class="row align-items-center">
                            <div class="col-md-1 text-center"><strong><?= $no++ ?></strong></div>
                            <div class="col-md-4"><strong><?= htmlspecialchars($obat['nama_obat']) ?></strong></div>
                            <div class="col-md-2 text-center"><small class="text-muted d-block">Kadaluarsa</small><strong><?= date('d/m/Y', strtotime($obat['tgl_kadaluarsa'])) ?></strong></div>
                            <div class="col-md-3 text-end"><span class="badge <?= $badge_class ?>"><i class="fas <?= $icon_class ?> me-1"></i><?= $hari_tersisa ?> hari lagi</span></div>
                            <div class="col-md-2 text-end">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="detail_pembelian_id" value="<?= $obat['detail_pembelian_id'] ?>">
                                    <button type="submit" name="update_status_expired" class="btn btn-danger btn-sm" onclick="return confirm('Tandai obat ini sebagai expired?')"><i class="fas fa-ban me-1"></i>Expired</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-5"><i class="fas fa-check-circle fa-3x mb-3 text-success"></i><p>Tidak ada obat yang akan kadaluarsa dalam 6 bulan ke depan</p></div>
                <?php endif; ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Tutup</button></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    setTimeout(function() { $('.alert').fadeOut('slow'); }, 5000);

    // Chart Penjualan
    new Chart(document.getElementById('salesChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Penjualan',
                data: <?= json_encode($chart_data) ?>,
                borderColor: 'rgb(102, 126, 234)',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4, fill: true,
                pointBackgroundColor: 'rgb(102, 126, 234)',
                pointBorderColor: '#fff', pointHoverRadius: 7, pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: function(v) { return 'Rp ' + v.toLocaleString('id-ID'); } }, grid: { color: 'rgba(0,0,0,0.05)' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Chart Kunjungan
    new Chart(document.getElementById('kunjunganChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($kunjungan_chart_labels) ?>,
            datasets: [{
                label: 'Kunjungan',
                data: <?= json_encode($kunjungan_chart_data) ?>,
                backgroundColor: 'rgba(20, 184, 166, 0.7)',
                borderColor: 'rgb(13, 148, 136)',
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) { return context.parsed.y + ' pasien'; }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.05)' } },
                x: { grid: { display: false } }
            }
        }
    });
</script>
</body>
</html>