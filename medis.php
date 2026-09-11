<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'medis' && $_SESSION['role'] != 'admin' && $_SESSION['role'] != 'petugas')) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';

unset($_SESSION['success']);
unset($_SESSION['error']);

// Get Data Obat
$query_obat = "SELECT * FROM obat WHERE stok > 0 ORDER BY nama_obat";
$result_obat = mysqli_query($conn, $query_obat);

// Get Data BHP
$query_bhp = "SELECT * FROM master_bhp WHERE aktif = 1 AND stok > 0 ORDER BY nama_bhp";
$result_bhp = mysqli_query($conn, $query_bhp);

// Get Data Tindakan
$query_tindakan = "SELECT * FROM master_tindakan WHERE aktif = 1 ORDER BY nama_tindakan";
$result_tindakan = mysqli_query($conn, $query_tindakan);

// Get Data Pendaftaran Hari Ini
$pemeriksaan_fisik = '';

if (isset($_POST['pemeriksaan_id'])) {
    $pemeriksaan_id = sanitize($_POST['pemeriksaan_id']);
    $query_pemeriksaanfisik = "SELECT p.pemeriksaan_fisik
                       FROM pendaftaran p
                       JOIN pemeriksaan pm ON pm.pendaftaran_id = p.id
                       WHERE pm.id = '$pemeriksaan_id'
                       LIMIT 1";
    $result_pemeriksaanfisik = mysqli_query($conn, $query_pemeriksaanfisik);
    if ($result_pemeriksaanfisik && mysqli_num_rows($result_pemeriksaanfisik) > 0) {
        $row_pemeriksaanfisik = mysqli_fetch_assoc($result_pemeriksaanfisik);
        $pemeriksaan_fisik = $row_pemeriksaanfisik['pemeriksaan_fisik'];
    }
}

if (isset($_POST['batalkan_pemeriksaan'])) {
    $user_id = $_SESSION['user_id'];
    $password = $_POST['password_verifikasi'];
    $pemeriksaan_id = sanitize($_POST['pemeriksaan_id_batal']);

    $query_cek_resep = "SELECT id, status FROM resep 
                        WHERE pemeriksaan_id = '$pemeriksaan_id' 
                        AND status IN ('diproses', 'selesai') 
                        AND hapus = 0";
    $result_cek_resep = mysqli_query($conn, $query_cek_resep);

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    $step1        = md5($password);
    $step2        = hash('sha256', $step1 . $password);
    $login_valid  = $user && password_verify($step2, $user['password']);

    if ($login_valid) {
        $query = "UPDATE pemeriksaan SET status = 'proses' WHERE id = '$pemeriksaan_id'";
        if (mysqli_query($conn, $query)) {
            $query      = "SELECT pendaftaran_id FROM pemeriksaan WHERE id = '$pemeriksaan_id'";
            $result_pend = mysqli_query($conn, $query);
            $row_pend   = mysqli_fetch_assoc($result_pend);

            mysqli_query($conn, "UPDATE pendaftaran SET status = 'diperiksa' WHERE id = '{$row_pend['pendaftaran_id']}'");
            mysqli_query($conn, "UPDATE detail_tindakan SET hapus = '1' WHERE pemeriksaan_id = '$pemeriksaan_id'");
            mysqli_query($conn, "UPDATE resep SET hapus = 1 WHERE pemeriksaan_id = '$pemeriksaan_id'");
            mysqli_query($conn, "UPDATE detail_bhp SET hapus = 1 WHERE pemeriksaan_id = '$pemeriksaan_id'");

            $success = "Pemeriksaan berhasil dibatalkan. Pasien dikembalikan ke status diperiksa.";
        } else {
            $error = "Gagal membatalkan pemeriksaan!";
        }
    } else {
        $error = "Password salah! Tidak dapat membatalkan pemeriksaan.";
    }
}

// Mulai Pemeriksaan
if (isset($_POST['mulai_periksa'])) {
    $pendaftaran_id = sanitize($_POST['pendaftaran_id']);
    $dokter_id = $_SESSION['user_id'];
    
    $query = "UPDATE pendaftaran SET status = 'diperiksa' WHERE id = '$pendaftaran_id'";
    mysqli_query($conn, $query);
    
    $query = "INSERT INTO pemeriksaan (pendaftaran_id, dokter_id, diagnosa, catatan, hapus) VALUES ('$pendaftaran_id', '$dokter_id', '', '', '0')";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Pemeriksaan dimulai";
        header("Location: medis.php");
        exit();
    }
}

// Simpan Diagnosa dan Resep
if (isset($_POST['simpan_diagnosa'])) {
    $pemeriksaan_id = sanitize($_POST['pemeriksaan_id']);
    $diagnosa = sanitize($_POST['diagnosa']);
    $catatan = sanitize($_POST['catatan']);
    $obat_ids = $_POST['obat_id'] ?? [];
    $jumlahs = $_POST['jumlah'] ?? [];
    $aturan_pakais = $_POST['aturan_pakai'] ?? [];
    
    $tindakan_ids = isset($_POST['tindakan_id']) ? $_POST['tindakan_id'] : [];
    $tindakan_jumlahs = isset($_POST['tindakan_jumlah']) ? $_POST['tindakan_jumlah'] : [];
    $tindakan_keterangans = isset($_POST['tindakan_keterangan']) ? $_POST['tindakan_keterangan'] : [];
    
    $user_id_login = $_SESSION['user_id'];
    
    $query = "UPDATE pemeriksaan SET diagnosa = '$diagnosa', catatan = '$catatan', status = 'selesai' WHERE id = '$pemeriksaan_id'";
    mysqli_query($conn, $query);
    
    $ada_obat = array_filter($obat_ids);
    if (count($ada_obat) > 0) {
        $query = "INSERT INTO resep (pemeriksaan_id, hapus) VALUES ('$pemeriksaan_id', '0')";
        mysqli_query($conn, $query);
        $resep_id = mysqli_insert_id($conn);

        for ($i = 0; $i < count($obat_ids); $i++) {
            if (!empty($obat_ids[$i])) {
                $obat_id = sanitize($obat_ids[$i]);
                $jumlah  = sanitize($jumlahs[$i]);
                $aturan  = sanitize($aturan_pakais[$i]);
                
                $query = "INSERT INTO detail_resep (resep_id, obat_id, jumlah, aturan_pakai, hapus) 
                          VALUES ('$resep_id', '$obat_id', '$jumlah', '$aturan', '0')";
                mysqli_query($conn, $query);
            }
        }
    }
    
    for ($i = 0; $i < count($tindakan_ids); $i++) {
        if (!empty($tindakan_ids[$i])) {
            $tindakan_id  = sanitize($tindakan_ids[$i]);
            $tindakan_jumlah = sanitize($tindakan_jumlahs[$i]);
            $tindakan_ket = sanitize($tindakan_keterangans[$i]);
            
            $query_tarif  = "SELECT tarif FROM master_tindakan WHERE id = '$tindakan_id'";
            $result_tarif = mysqli_query($conn, $query_tarif);
            $row_tarif    = mysqli_fetch_assoc($result_tarif);
            $tarif        = $row_tarif['tarif'];
            $subtotal     = $tarif * $tindakan_jumlah;
            
            $query = "INSERT INTO detail_tindakan (pemeriksaan_id, tindakan_id, jumlah, tarif, subtotal, keterangan, user_id, hapus) 
                      VALUES ('$pemeriksaan_id', '$tindakan_id', '$tindakan_jumlah', '$tarif', '$subtotal', '$tindakan_ket', '$user_id_login', '0')";
            mysqli_query($conn, $query);
        }
    }

    $bhp_ids        = $_POST['bhp_id']          ?? [];
    $bhp_jumlahs    = $_POST['bhp_jumlah']       ?? [];
    $bhp_keterangans= $_POST['bhp_keterangan']   ?? [];

    for ($i = 0; $i < count($bhp_ids); $i++) {
        if (!empty($bhp_ids[$i])) {
            $bhp_id     = sanitize($bhp_ids[$i]);
            $bhp_jumlah = sanitize($bhp_jumlahs[$i]);
            $bhp_ket    = sanitize($bhp_keterangans[$i]);

            $query_harga_bhp  = "SELECT harga FROM master_bhp WHERE id = '$bhp_id'";
            $result_harga_bhp = mysqli_query($conn, $query_harga_bhp);
            $row_harga_bhp    = mysqli_fetch_assoc($result_harga_bhp);
            $harga_bhp        = $row_harga_bhp['harga'];
            $subtotal_bhp     = $harga_bhp * $bhp_jumlah;

            $query = "INSERT INTO detail_bhp 
                      (pemeriksaan_id, bhp_id, jumlah, harga, subtotal, keterangan, hapus) 
                      VALUES 
                      ('$pemeriksaan_id', '$bhp_id', '$bhp_jumlah', '$harga_bhp', '$subtotal_bhp', '$bhp_ket', '0')";
            mysqli_query($conn, $query);
        }
    }

    $query  = "SELECT pendaftaran_id FROM pemeriksaan WHERE id = '$pemeriksaan_id'";
    $result = mysqli_query($conn, $query);
    $row    = mysqli_fetch_assoc($result);
    $query  = "UPDATE pendaftaran SET status = 'selesai' WHERE id = '{$row['pendaftaran_id']}'";
    mysqli_query($conn, $query);
    
    $success = "Diagnosa, resep, dan tindakan berhasil disimpan";
}

$filter_poli_medis = isset($_GET['poli_medis']) ? mysqli_real_escape_string($conn, $_GET['poli_medis']) : '';
$dokter_id = $_SESSION['user_id'];

$poli_where_p  = $filter_poli_medis ? "AND p.poli = '$filter_poli_medis'" : "";
$poli_where_pf = $filter_poli_medis ? "AND poli = '$filter_poli_medis'" : "";

$query_menunggu = "SELECT p.*, ps.nama_lengkap, ps.no_rm, ps.tgl_lahir, ps.jenis_kelamin,
                   TIMESTAMPDIFF(YEAR, ps.tgl_lahir, CURDATE()) as umur
                   FROM pendaftaran p 
                   JOIN pasien ps ON p.pasien_id = ps.id 
                   WHERE p.status = 'menunggu' 
                   $poli_where_p
                   AND DATE(p.tgl_daftar) = CURDATE()
                   ORDER BY p.tgl_daftar ASC";
$result_menunggu = mysqli_query($conn, $query_menunggu);

$query_diperiksa = "SELECT pm.*, p.no_antrian, p.keluhan, p.poli, 
                    ps.nama_lengkap, ps.no_rm, ps.tgl_lahir, ps.jenis_kelamin, 
                    p.pemeriksaan_fisik,
                    TIMESTAMPDIFF(YEAR, ps.tgl_lahir, CURDATE()) as umur
                    FROM pemeriksaan pm
                    JOIN pendaftaran p ON pm.pendaftaran_id = p.id
                    JOIN pasien ps ON p.pasien_id = ps.id
                    WHERE pm.status = 'proses'
                    AND DATE(pm.tgl_pemeriksaan) = CURDATE()
                    ORDER BY pm.tgl_pemeriksaan DESC";
$result_diperiksa = mysqli_query($conn, $query_diperiksa);

$query_selesai = "SELECT pm.*, p.no_antrian, p.keluhan, p.pemeriksaan_fisik, p.poli, ps.nama_lengkap, ps.no_rm, ps.tgl_lahir, ps.jenis_kelamin,
                  TIMESTAMPDIFF(YEAR, ps.tgl_lahir, CURDATE()) as umur,
                  DATE_FORMAT(pm.tgl_pemeriksaan, '%H:%i') as jam_pemeriksaan
                  FROM pemeriksaan pm
                  JOIN pendaftaran p ON pm.pendaftaran_id = p.id
                  JOIN pasien ps ON p.pasien_id = ps.id
                  WHERE pm.status = 'selesai' 
                  AND pm.hapus = 0
                  AND DATE(pm.tgl_pemeriksaan) = CURDATE()
                  $poli_where_p
                  ORDER BY pm.tgl_pemeriksaan DESC";
$result_selesai = mysqli_query($conn, $query_selesai);

$query_obat = "SELECT * FROM obat WHERE stok > 0 ORDER BY nama_obat";
$result_obat = mysqli_query($conn, $query_obat);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemeriksaan - Healoka</title>
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link rel="shortcut icon" type="image/png" href="asset/img/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <?php include 'sidebar_style.php'; ?>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .container-fluid { padding: 0; margin: 0; }
        .row { margin: 0; display: flex; }
        .col-md-2 { flex: 0 0 250px; max-width: 250px; }
        .col-md-10 { flex: 1; margin-left: 250px; padding: 0; }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .card-header.bg-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
        }
        
        .card-body.scrollable {
            overflow-y: auto;
            max-height: calc(100vh - 220px);
            min-height: 120px;
        }

        .card-body.scrollable::-webkit-scrollbar { width: 5px; }
        .card-body.scrollable::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .card-body.scrollable::-webkit-scrollbar-thumb { background: #c5c5c5; border-radius: 10px; }
        .card-body.scrollable::-webkit-scrollbar-thumb:hover { background: #999; }

        .count-badge {
            background: rgba(255,255,255,0.25);
            color: #fff;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 12px;
            font-weight: 700;
            margin-left: 8px;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
            font-weight: 600;
        }
        
        .badge { padding: 6px 12px; border-radius: 8px; font-weight: 500; }
        .badge-poli-umum { background-color: #0ea5e9; color: #fff; }
        .badge-poli-kebidanan { background-color: #ec4899; color: #fff; }
        
        .btn { border-radius: 8px; padding: 8px 16px; font-weight: 500; }
        .form-control, .form-select { border-radius: 8px; border: 1px solid #e0e0e0; }
        
        .patient-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }

        /* ==== INPUT PICKER + TABLE LIST (Obat/Tindakan/BHP) ==== */
        .picker-box {
            background: #fff;
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        .picker-box .row.g-2 {
            flex-wrap: nowrap;
        }
        .picker-box .row.g-2 > [class*="col-"] {
            flex: 1 1 auto;
            min-width: 0;
        }
        .picker-box .row.g-2 > .col-md-2:last-child,
        .picker-box .row.g-2 > .col-md-3:last-child {
            flex: 0 0 auto;
            width: auto;
        }
        .item-list-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.92rem;
        }
        .item-list-table thead th {
            background-color: #f1f3f9;
            font-weight: 600;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #495057;
            padding: 8px 10px;
            border-bottom: 2px solid #dee2e6;
            text-align: center !important;
        }
        .item-list-table tbody td {
            padding: 6px 8px;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
            text-align: center;
        }
        .item-list-table tbody tr:last-child td { border-bottom: none; }
        .item-list-table .cell-input {
            border: 1px solid transparent;
            background: transparent;
            border-radius: 6px;
            padding: 5px 8px;
            width: 100%;
            transition: all .15s;
            text-align: center;
        }
        .item-list-table .cell-input:hover {
            border-color: #dee2e6;
            background: #fafbfc;
        }
        .item-list-table .cell-input:focus {
            border-color: var(--primary-color);
            background: #fff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102,126,234,.12);
        }
        .item-list-empty {
            text-align: center;
            color: #adb5bd;
            padding: 22px 10px;
            font-size: 0.88rem;
        }
        .item-list-wrap {
            border: 1px solid #eee;
            border-radius: 10px;
            overflow: hidden;
        }
        .btn-row-remove {
            width: 30px;
            height: 30px;
            padding: 0;
            border-radius: 6px;
        }
        /* ========================================================= */
        
        .modal-content { border-radius: 15px; }
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .info-label { font-weight: 600; color: #6c757d; font-size: 0.9em; }
        .info-value { font-size: 1.1em; color: #212529; }
        
        .patient-card { transition: all 0.3s; }
        .patient-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .border-success-custom { border-left: 4px solid #28a745 !important; }

        .select2-container { width: 100% !important; z-index: 1; }
        .select2-dropdown { z-index: 9999 !important; }
        #pickerObat, #pickerTindakan, #pickerBhp { position: relative; z-index: 1; }
        .select2-results__options { max-height: 250px !important; overflow-y: auto; }
        .modal-body { max-height: calc(100vh - 200px); overflow-y: auto; }

        @media (max-width: 768px) {
            .modal-body { max-height: calc(100vh - 150px); }
            .card-body.scrollable { max-height: 350px; }
            .item-list-table { font-size: 0.82rem; }
        }
        @media (max-width: 992px) { .modal-xl { max-width: 95%; } }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <div class="container-fluid px-4">
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="row mb-3 align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0 fw-bold text-secondary"><i></i></h5>
                    </div>
                    <div class="col-md-6 d-flex gap-2 justify-content-end align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <label class="mb-0 small fw-semibold text-muted text-nowrap">
                                <i class="fas fa-filter me-1"></i>Poli:
                            </label>
                            <select class="form-select form-select-sm" style="width:auto;"
                                    onchange="window.location.href='medis.php?poli_medis='+this.value">
                                <option value="" <?= $filter_poli_medis === '' ? 'selected' : '' ?>>Semua</option>
                                <option value="umum"      <?= $filter_poli_medis === 'umum'      ? 'selected' : '' ?>> Umum</option>
                                <option value="kebidanan" <?= $filter_poli_medis === 'kebidanan' ? 'selected' : '' ?>> Kebidanan</option>
                            </select>
                        </div>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalPanggilAntrian">
                            <i class="fas fa-bullhorn me-2"></i>Panel Antrian
                        </button>
                    </div>
                </div>

                <div class="row">
                    <!-- Pasien Menunggu -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-clock me-2"></i>Pasien Menunggu
                                <span class="count-badge"><?= mysqli_num_rows($result_menunggu) ?></span>
                            </div>
                            <div class="card-body scrollable">
                                <?php if (mysqli_num_rows($result_menunggu) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result_menunggu)): ?>
                                    <div class="border rounded p-3 mb-3 patient-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <strong><?= $row['nama_lengkap'] ?></strong>
                                                    <?php
                                                    $bp = ($row['poli'] ?? '') === 'kebidanan' ? 'badge-poli-kebidanan' : 'badge-poli-umum';
                                                    $lp = ($row['poli'] ?? '') === 'kebidanan' ? '🏥 Kebidanan' : '🏥 Umum';
                                                    ?>
                                                    <span class="badge <?= $bp ?>" style="font-size:10px;"><?= $lp ?></span>
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-id-card me-1"></i><?= $row['no_rm'] ?> |
                                                    <i class="fas fa-ticket-alt ms-2 me-1"></i><?= $row['no_antrian'] ?>
                                                </small>
                                                <p class="mb-1 mt-2"><small><strong>Keluhan:</strong> <?= $row['keluhan'] ?></small></p>
                                            </div>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="pendaftaran_id" value="<?= $row['id'] ?>">
                                                <button type="submit" name="mulai_periksa" 
                                                        class="btn btn-sm btn-primary btn-periksa"
                                                        data-no="<?= $row['no_antrian'] ?>"
                                                        data-poli="<?= $row['poli'] ?? 'umum' ?>"
                                                        data-nama="<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>">
                                                    <i class="fas fa-stethoscope me-1"></i>Periksa
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>Tidak ada pasien menunggu<?= $filter_poli_medis ? ' di poli ini' : '' ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sedang Diperiksa -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-user-md me-2"></i>Sedang Diperiksa
                                <span class="count-badge"><?= mysqli_num_rows($result_diperiksa) ?></span>
                            </div>
                            <div class="card-body scrollable">
                                <?php if (mysqli_num_rows($result_diperiksa) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result_diperiksa)): ?>
                                    <div class="border rounded p-3 mb-3 border-info patient-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <strong><?= $row['nama_lengkap'] ?></strong>
                                                    <?php
                                                    $bp2 = ($row['poli'] ?? '') === 'kebidanan' ? 'badge-poli-kebidanan' : 'badge-poli-umum';
                                                    $lp2 = ($row['poli'] ?? '') === 'kebidanan' ? ' Kebidanan' : ' Umum';
                                                    ?>
                                                    <span class="badge <?= $bp2 ?>" style="font-size:10px;"><?= $lp2 ?></span>
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-id-card me-1"></i><?= $row['no_rm'] ?> |
                                                    <i class="fas fa-venus-mars ms-2 me-1"></i><?= $row['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?> |
                                                    <i class="fas fa-birthday-cake ms-2 me-1"></i><?= $row['umur'] ?> th
                                                </small>
                                                <p class="mb-1 mt-2"><small><strong>Keluhan:</strong> <?= $row['keluhan'] ?></small></p>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-success btn-diagnosa"
                                                data-id="<?= $row['id'] ?>"
                                                data-nama="<?= htmlspecialchars(strip_tags($row['nama_lengkap'] ?? ''), ENT_QUOTES) ?>"
                                                data-norms="<?= htmlspecialchars(strip_tags($row['no_rm'] ?? ''), ENT_QUOTES) ?>"
                                                data-keluhan="<?= htmlspecialchars(preg_replace('/[\r\n\t]+/', ' ', $row['keluhan'] ?? ''), ENT_QUOTES) ?>"
                                                data-pf="<?= htmlspecialchars(preg_replace('/[\r\n\t]+/', ' ', $row['pemeriksaan_fisik'] ?? ''), ENT_QUOTES) ?>"
                                                data-jk="<?= htmlspecialchars($row['jenis_kelamin'] ?? '', ENT_QUOTES) ?>"
                                                data-umur="<?= (int)($row['umur'] ?? 0) ?>">
                                                <i class="fas fa-file-medical me-1"></i>Diagnosa
                                            </button>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>Tidak ada pasien sedang diperiksa<?= $filter_poli_medis ? ' di poli ini' : '' ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pasien Selesai -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-success">
                                <i class="fas fa-check-circle me-2"></i>Pasien Selesai (Hari Ini)
                                <span class="count-badge"><?= mysqli_num_rows($result_selesai) ?></span>
                            </div>
                            <div class="card-body scrollable">
                                <?php if (mysqli_num_rows($result_selesai) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result_selesai)): ?>
                                    <div class="border rounded p-3 mb-3 border-success-custom patient-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <strong><?= $row['nama_lengkap'] ?></strong>
                                                    <?php
                                                    $bp3 = ($row['poli'] ?? '') === 'kebidanan' ? 'badge-poli-kebidanan' : 'badge-poli-umum';
                                                    $lp3 = ($row['poli'] ?? '') === 'kebidanan' ? ' Kebidanan' : ' Umum';
                                                    ?>
                                                    <span class="badge <?= $bp3 ?>" style="font-size:10px;"><?= $lp3 ?></span>
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-id-card me-1"></i><?= $row['no_rm'] ?> |
                                                    <i class="fas fa-clock ms-2 me-1"></i><?= $row['jam_pemeriksaan'] ?>
                                                </small>
                                                <p class="mb-1 mt-2"><small><strong>Diagnosa:</strong> <?= substr($row['diagnosa'], 0, 50) ?>...</small></p>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-warning" 
                                                    onclick="lihatDetail(<?= $row['id'] ?>)">
                                                <i class="fas fa-eye me-1"></i>Lihat Detail
                                            </button>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>Belum ada pasien selesai<?= $filter_poli_medis ? ' di poli ini' : '' ?> hari ini</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Diagnosa -->
    <div class="modal fade" id="modalDiagnosa" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-medical me-2"></i>Form Diagnosa & Resep</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="formDiagnosa">
                        <input type="hidden" name="pemeriksaan_id" id="pemeriksaan_id">
                        <input type="hidden" id="diagnosa_pasien_no_rm">
                        
                        <div class="patient-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <span class="info-label">Nama Pasien:</span><br>
                                        <span class="info-value" id="info_nama"></span>
                                    </div>
                                    <div class="mb-2">
                                        <span class="info-label">No. Rekam Medis:</span><br>
                                        <span class="info-value" id="info_no_rm"></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <span class="info-label">Jenis Kelamin / Umur:</span><br>
                                        <span class="info-value" id="info_jk_umur"></span>
                                    </div>
                                    <div class="mb-2">
                                        <span class="info-label">Keluhan:</span><br>
                                        <span class="info-value" id="info_keluhan"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <ul class="nav nav-tabs mb-3 mt-3" id="diagnosaTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="form-tab" data-bs-toggle="tab" data-bs-target="#formDiagnosaTab" type="button">
                                    <i class="fas fa-edit me-2"></i>Input Diagnosa & Resep
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="riwayat-tab" data-bs-toggle="tab" data-bs-target="#riwayatTab" type="button" onclick="loadRiwayatPasien()">
                                    <i class="fas fa-history me-2"></i>Riwayat Pasien
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="diagnosaTabsContent">
                            <div class="tab-pane fade show active" id="formDiagnosaTab" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label"><strong>Pemeriksaan Fisik</strong></label>
                                    <textarea class="form-control" name="pemeriksaan_fisik" id="input_pemeriksaanfisik" rows="3" readonly></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><strong>Diagnosa <span class="text-danger">*</span></strong></label>
                                    <textarea class="form-control" name="diagnosa" id="input_diagnosa" rows="3" required placeholder="Tuliskan diagnosa..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><strong>Catatan Pemeriksaan</strong></label>
                                    <textarea class="form-control" name="catatan" id="input_catatan" rows="2" placeholder="Catatan tambahan..."></textarea>
                                </div>
                                <hr>

                                <!-- ===================== TINDAKAN MEDIS ===================== -->
                                <div class="section-tindakan mb-4">
                                    <h6 class="mb-3"><i class="fas fa-hand-holding-medical me-2"></i>Tindakan Medis</h6>
                                    <div class="picker-box">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-7">
                                                <label class="form-label small mb-1">Pilih Tindakan</label>
                                                <select class="form-select" id="pickerTindakanSelect" style="width:100%;">
                                                    <option value="">-- Pilih Tindakan --</option>
                                                    <?php
                                                    if ($result_tindakan) {
                                                        mysqli_data_seek($result_tindakan, 0);
                                                        while ($t = mysqli_fetch_assoc($result_tindakan)):
                                                    ?>
                                                    <option value="<?= $t['id'] ?>" data-tarif="<?= $t['tarif'] ?>" data-nama="<?= htmlspecialchars($t['nama_tindakan'], ENT_QUOTES) ?>">
                                                        <?= htmlspecialchars($t['nama_tindakan']) ?> (Rp <?= number_format($t['tarif'], 0, ',', '.') ?>)
                                                    </option>
                                                    <?php endwhile; } ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Jumlah</label>
                                                <input type="number" class="form-control" id="pickerTindakanJumlah" min="1" value="1">
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" class="btn btn-success text-nowrap" onclick="addTindakanRow()">
                                                    <i class="fas fa-plus me-1"></i>Tambah
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="item-list-wrap">
                                        <table class="item-list-table mb-0">
                                            <thead>
                                                <tr>
                                                    <th width="5%">No</th>
                                                    <th width="40%">Nama Tindakan</th>
                                                    <th width="15%">Jumlah</th>
                                                    <th width="18%">Tarif</th>
                                                    <th width="17%">Subtotal</th>
                                                    <th width="5%"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyTindakan"></tbody>
                                        </table>
                                        <div id="emptyTindakan" class="item-list-empty">Belum ada tindakan ditambahkan</div>
                                    </div>
                                </div>
                                <hr>

                                <!-- ===================== OBAT & BHP TABS ===================== -->
                                <div class="section-obat mb-3">
                                    <ul class="nav nav-tabs mb-3" id="obatBhpTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="tab-obat" data-bs-toggle="tab"
                                                    data-bs-target="#tabObat" type="button">
                                                <i class="fas fa-pills me-2"></i>Resep Obat
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="tab-bhp" data-bs-toggle="tab"
                                                    data-bs-target="#tabBhp" type="button">
                                                <i class="fas fa-box-open me-2"></i>BHP
                                            </button>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="obatBhpTabsContent">

                                        <!-- TAB OBAT -->
                                        <div class="tab-pane fade show active" id="tabObat" role="tabpanel">
                                            <div class="picker-box" id="pickerObat">
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-5">
                                                        <label class="form-label small mb-1">Nama Obat</label>
                                                        <select class="form-select" id="pickerObatSelect" style="width:100%;"></select>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label small mb-1">Jumlah</label>
                                                        <input type="number" class="form-control" id="pickerObatJumlah" min="1" value="1">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small mb-1">Signa (Aturan Pakai)</label>
                                                        <input type="text" class="form-control" id="pickerObatSigna" placeholder="cth: 3x sehari">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <button type="button" class="btn btn-primary text-nowrap" onclick="addObatRow()">
                                                            <i class="fas fa-plus me-1"></i>Tambah
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="item-list-wrap">
                                                <table class="item-list-table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th width="5%">No</th>
                                                            <th width="35%">Nama Obat</th>
                                                            <th width="15%">Jumlah</th>
                                                            <th width="40%">Signa</th>
                                                            <th width="5%"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="tbodyObat"></tbody>
                                                </table>
                                                <div id="emptyObat" class="item-list-empty">Belum ada obat ditambahkan</div>
                                            </div>
                                        </div>

                                        <!-- TAB BHP -->
                                        <div class="tab-pane fade" id="tabBhp" role="tabpanel">
                                            <div class="picker-box">
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-7">
                                                        <label class="form-label small mb-1">Pilih BHP</label>
                                                        <select class="form-select" id="pickerBhpSelect" style="width:100%;">
                                                            <option value="">-- Pilih BHP --</option>
                                                            <?php
                                                            if ($result_bhp) {
                                                                mysqli_data_seek($result_bhp, 0);
                                                                while ($b = mysqli_fetch_assoc($result_bhp)):
                                                            ?>
                                                            <option value="<?= $b['id'] ?>" data-harga="<?= $b['harga'] ?>" data-nama="<?= htmlspecialchars($b['nama_bhp'], ENT_QUOTES) ?>">
                                                                <?= htmlspecialchars($b['nama_bhp']) ?> (<?= htmlspecialchars($b['satuan']) ?>) - Stok: <?= $b['stok'] ?>
                                                            </option>
                                                            <?php endwhile; } ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small mb-1">Jumlah</label>
                                                        <input type="number" class="form-control" id="pickerBhpJumlah" min="1" value="1">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <button type="button" class="btn btn-warning text-nowrap" onclick="addBhpRow()">
                                                            <i class="fas fa-plus me-1"></i>Tambah
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="item-list-wrap">
                                                <table class="item-list-table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th width="5%">No</th>
                                                            <th width="40%">Nama BHP</th>
                                                            <th width="15%">Jumlah</th>
                                                            <th width="18%">Harga</th>
                                                            <th width="17%">Subtotal</th>
                                                            <th width="5%"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="tbodyBhp"></tbody>
                                                </table>
                                                <div id="emptyBhp" class="item-list-empty">Belum ada BHP ditambahkan</div>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <!-- ===== HIDDEN INPUTS (HARUS DI DALAM FORM, DI SINI) ===== -->
                                <div id="containerTindakanHidden"></div>
                                <div id="containerObatHidden"></div>
                                <div id="containerBhpHidden"></div>
                                <!-- ========================================================= -->

                            </div><!-- end #formDiagnosaTab -->

                            <div class="tab-pane fade" id="riwayatTab" role="tabpanel">
                                <div id="riwayatAccordion" style="max-height: 500px; overflow-y: auto;">
                                    <div class="text-center py-5">
                                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Klik tab untuk memuat riwayat</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="simpan_diagnosa" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>Simpan Diagnosa & Resep
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Pemeriksaan -->
    <div class="modal fade" id="modalDetail" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title text-white"><i class="fas fa-file-medical-alt me-2"></i>Detail Pemeriksaan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="detail_pemeriksaan_id">
                    <div class="patient-info">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2"><span class="info-label">Nama Pasien:</span><br><span class="info-value" id="detail_nama"></span></div>
                                <div class="mb-2"><span class="info-label">No. Rekam Medis:</span><br><span class="info-value" id="detail_no_rm"></span></div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2"><span class="info-label">Jenis Kelamin / Umur:</span><br><span class="info-value" id="detail_jk_umur"></span></div>
                                <div class="mb-2"><span class="info-label">Keluhan:</span><br><span class="info-value" id="detail_keluhan"></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Pemeriksaan Fisik</strong></label>
                        <textarea class="form-control" id="detail_pemeriksaanfisik" rows="3" readonly></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong><i class="fas fa-diagnoses me-2"></i>Diagnosa</strong></label>
                        <div class="border rounded p-3 bg-light"><p id="detail_diagnosa" class="mb-0"></p></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong><i class="fas fa-notes-medical me-2"></i>Catatan Pemeriksaan</strong></label>
                        <div class="border rounded p-3 bg-light"><p id="detail_catatan" class="mb-0"></p></div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <h6><i class="fas fa-pills me-2"></i>Resep Obat</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr><th width="5%">No</th><th width="45%">Nama Obat</th><th width="15%">Jumlah</th><th width="35%">Aturan Pakai</th></tr>
                                </thead>
                                <tbody id="detail_resep"></tbody>
                            </table>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <h6><i class="fas fa-hand-holding-medical me-2"></i>Tindakan Medis</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr><th width="5%">No</th><th width="40%">Nama Tindakan</th><th width="15%">Jumlah</th><th width="20%">Tarif</th><th width="20%">Keterangan</th></tr>
                                </thead>
                                <tbody id="detail_tindakan"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-danger" onclick="konfirmasiBatal()">
                        <i class="fas fa-undo me-2"></i>Batalkan Pemeriksaan
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Verifikasi Password untuk Batal -->
    <div class="modal fade" id="modalVerifikasiBatal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title text-white"><i class="fas fa-lock me-2"></i>Verifikasi Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formVerifikasiBatal">
                    <div class="modal-body">
                        <input type="hidden" name="pemeriksaan_id_batal" id="pemeriksaan_id_batal">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Perhatian!</strong> Anda akan membatalkan pemeriksaan ini dan mengembalikan pasien ke status "Sedang Diperiksa". 
                            Masukkan password untuk konfirmasi.
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Password</strong></label>
                            <input type="password" class="form-control" name="password_verifikasi" required placeholder="Masukkan password Anda">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="batalkan_pemeriksaan" class="btn btn-danger">
                            <i class="fas fa-check me-2"></i>Ya, Batalkan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    /* =========================================================
       STATE: array per kategori sebagai sumber kebenaran tunggal.
       Render ulang tabel + hidden inputs setiap ada perubahan.
       ========================================================= */
    let obatItems     = [];
    let tindakanItems = [];
    let bhpItems      = [];
    let obatRowSeq = 0, tindakanRowSeq = 0, bhpRowSeq = 0;

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID').format(angka || 0);
    }

    /* ─────────────────── RENDER: TINDAKAN ─────────────────── */
    function renderTindakanTable() {
        const tbody = $('#tbodyTindakan').empty();
        $('#emptyTindakan').toggle(tindakanItems.length === 0);
        $('#containerTindakanHidden').empty();

        tindakanItems.forEach(function(item, idx) {
            const rowId = item.rowId;
            const subtotal = item.tarif * item.jumlah;
            tbody.append(
                '<tr data-row="' + rowId + '">'
                + '<td class="text-center">' + (idx + 1) + '</td>'
                + '<td>' + $('<div>').text(item.nama).html() + '</td>'
                + '<td><input type="number" class="cell-input text-center" min="1" value="' + item.jumlah + '" onchange="updateTindakanField(' + rowId + ', \'jumlah\', this.value)"></td>'
                + '<td class="text-center">Rp ' + formatRupiah(item.tarif) + '</td>'
                + '<td class="text-center fw-semibold">Rp ' + formatRupiah(subtotal) + '</td>'
                + '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-row-remove" onclick="removeTindakanRow(' + rowId + ')"><i class="fas fa-trash"></i></button></td>'
                + '</tr>'
            );
            // Hidden inputs DI DALAM form (containerTindakanHidden sudah di dalam form)
            $('#containerTindakanHidden').append(
                '<input type="hidden" name="tindakan_id[]" value="' + item.tindakan_id + '">'
                + '<input type="hidden" name="tindakan_jumlah[]" value="' + item.jumlah + '">'
                + '<input type="hidden" name="tindakan_keterangan[]" value="">'
            );
        });
    }

    function addTindakanRow() {
        const sel = $('#pickerTindakanSelect');
        const tindakanId = sel.val();
        if (!tindakanId) { alert('Pilih tindakan terlebih dahulu'); return; }
        const opt    = sel.find(':selected');
        const nama   = opt.data('nama');
        const tarif  = parseFloat(opt.data('tarif')) || 0;
        const jumlah = parseInt($('#pickerTindakanJumlah').val()) || 1;

        const existing = tindakanItems.find(function(t) { return t.tindakan_id == tindakanId; });
        if (existing) {
            existing.jumlah = parseInt(existing.jumlah) + jumlah;
        } else {
            tindakanItems.push({ rowId: ++tindakanRowSeq, tindakan_id: tindakanId, nama: nama, jumlah: jumlah, tarif: tarif });
        }
        renderTindakanTable();
        sel.val('').trigger('change');
        $('#pickerTindakanJumlah').val(1);
    }

    function updateTindakanField(rowId, field, value) {
        const item = tindakanItems.find(function(t) { return t.rowId === rowId; });
        if (!item) return;
        item[field] = field === 'jumlah' ? (parseInt(value) || 1) : value;
        renderTindakanTable();
    }

    function removeTindakanRow(rowId) {
        tindakanItems = tindakanItems.filter(function(t) { return t.rowId !== rowId; });
        renderTindakanTable();
    }

    /* ─────────────────── RENDER: OBAT ─────────────────── */
    function renderObatTable() {
        const tbody = $('#tbodyObat').empty();
        $('#emptyObat').toggle(obatItems.length === 0);
        $('#containerObatHidden').empty();

        obatItems.forEach(function(item, idx) {
            const rowId = item.rowId;
            tbody.append(
                '<tr data-row="' + rowId + '">'
                + '<td class="text-center">' + (idx + 1) + '</td>'
                + '<td>' + $('<div>').text(item.nama).html() + '</td>'
                + '<td><input type="number" class="cell-input text-center" min="1" value="' + item.jumlah + '" onchange="updateObatField(' + rowId + ', \'jumlah\', this.value)"></td>'
                + '<td><input type="text" class="cell-input" value="' + $('<div>').text(item.signa).html() + '" onchange="updateObatField(' + rowId + ', \'signa\', this.value)" placeholder="cth: 3x sehari"></td>'
                + '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-row-remove" onclick="removeObatRow(' + rowId + ')"><i class="fas fa-trash"></i></button></td>'
                + '</tr>'
            );
            $('#containerObatHidden').append(
                '<input type="hidden" name="obat_id[]" value="' + item.obat_id + '">'
                + '<input type="hidden" name="jumlah[]" value="' + item.jumlah + '">'
                + '<input type="hidden" name="aturan_pakai[]" value="' + $('<div>').text(item.signa).html() + '">'
            );
        });
    }

    function addObatRow() {
        const sel = $('#pickerObatSelect');
        const obatId = sel.val();
        if (!obatId) { alert('Pilih obat terlebih dahulu'); return; }
        const nama   = sel.find(':selected').text();
        const jumlah = parseInt($('#pickerObatJumlah').val()) || 1;
        const signa  = $('#pickerObatSigna').val().trim();
        if (!signa) { alert('Isi signa / aturan pakai'); return; }

        const existing = obatItems.find(function(o) { return o.obat_id == obatId; });
        if (existing) {
            existing.jumlah = parseInt(existing.jumlah) + jumlah;
        } else {
            obatItems.push({ rowId: ++obatRowSeq, obat_id: obatId, nama: nama, jumlah: jumlah, signa: signa });
        }
        renderObatTable();
        sel.val(null).trigger('change');
        $('#pickerObatJumlah').val(1);
        $('#pickerObatSigna').val('');
    }

    function updateObatField(rowId, field, value) {
        const item = obatItems.find(function(o) { return o.rowId === rowId; });
        if (!item) return;
        item[field] = field === 'jumlah' ? (parseInt(value) || 1) : value;
        renderObatTable();
    }

    function removeObatRow(rowId) {
        obatItems = obatItems.filter(function(o) { return o.rowId !== rowId; });
        renderObatTable();
    }

    /* ─────────────────── RENDER: BHP ─────────────────── */
    function renderBhpTable() {
        const tbody = $('#tbodyBhp').empty();
        $('#emptyBhp').toggle(bhpItems.length === 0);
        $('#containerBhpHidden').empty();

        bhpItems.forEach(function(item, idx) {
            const rowId = item.rowId;
            const subtotal = item.harga * item.jumlah;
            tbody.append(
                '<tr data-row="' + rowId + '">'
                + '<td class="text-center">' + (idx + 1) + '</td>'
                + '<td>' + $('<div>').text(item.nama).html() + '</td>'
                + '<td><input type="number" class="cell-input text-center" min="1" value="' + item.jumlah + '" onchange="updateBhpField(' + rowId + ', \'jumlah\', this.value)"></td>'
                + '<td class="text-center">Rp ' + formatRupiah(item.harga) + '</td>'
                + '<td class="text-center fw-semibold">Rp ' + formatRupiah(subtotal) + '</td>'
                + '<td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-row-remove" onclick="removeBhpRow(' + rowId + ')"><i class="fas fa-trash"></i></button></td>'
                + '</tr>'
            );
            $('#containerBhpHidden').append(
                '<input type="hidden" name="bhp_id[]" value="' + item.bhp_id + '">'
                + '<input type="hidden" name="bhp_jumlah[]" value="' + item.jumlah + '">'
                + '<input type="hidden" name="bhp_keterangan[]" value="">'
            );
        });
    }

    function addBhpRow() {
        const sel = $('#pickerBhpSelect');
        const bhpId = sel.val();
        if (!bhpId) { alert('Pilih BHP terlebih dahulu'); return; }
        const opt    = sel.find(':selected');
        const nama   = opt.data('nama');
        const harga  = parseFloat(opt.data('harga')) || 0;
        const jumlah = parseInt($('#pickerBhpJumlah').val()) || 1;

        const existing = bhpItems.find(function(b) { return b.bhp_id == bhpId; });
        if (existing) {
            existing.jumlah = parseInt(existing.jumlah) + jumlah;
        } else {
            bhpItems.push({ rowId: ++bhpRowSeq, bhp_id: bhpId, nama: nama, jumlah: jumlah, harga: harga });
        }
        renderBhpTable();
        sel.val('').trigger('change');
        $('#pickerBhpJumlah').val(1);
    }

    function updateBhpField(rowId, field, value) {
        const item = bhpItems.find(function(b) { return b.rowId === rowId; });
        if (!item) return;
        item[field] = field === 'jumlah' ? (parseInt(value) || 1) : value;
        renderBhpTable();
    }

    function removeBhpRow(rowId) {
        bhpItems = bhpItems.filter(function(b) { return b.rowId !== rowId; });
        renderBhpTable();
    }

    /* ─────────────────── MODAL DETAIL ─────────────────── */
    function lihatDetail(pemeriksaan_id) {
        $.ajax({
            url: 'ajax/get_detail_pemeriksaan.php',
            type: 'POST',
            data: { pemeriksaan_id: pemeriksaan_id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#detail_pemeriksaan_id').val(pemeriksaan_id);
                    $('#detail_nama').text(response.data.nama_lengkap);
                    $('#detail_no_rm').text(response.data.no_rm);
                    $('#detail_jk_umur').text(
                        (response.data.jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan') +
                        ' / ' + response.data.umur + ' tahun'
                    );
                    $('#detail_keluhan').text(response.data.keluhan);
                    $('#detail_pemeriksaanfisik').val(response.data.pemeriksaan_fisik || '-');
                    $('#detail_diagnosa').text(response.data.diagnosa || '-');
                    $('#detail_catatan').text(response.data.catatan   || '-');

                    var resepHtml = '';
                    if (response.resep && response.resep.length > 0) {
                        $.each(response.resep, function(i, item) {
                            resepHtml += '<tr><td class="text-center">' + (i+1) + '</td>'
                                + '<td>' + item.nama_obat + '</td>'
                                + '<td class="text-center">' + item.jumlah + '</td>'
                                + '<td>' + item.aturan_pakai + '</td></tr>';
                        });
                    } else {
                        resepHtml = '<tr><td colspan="4" class="text-center text-muted">Tidak ada resep obat</td></tr>';
                    }
                    $('#detail_resep').html(resepHtml);

                    var tindakanHtml = '';
                    if (response.tindakan && response.tindakan.length > 0) {
                        $.each(response.tindakan, function(i, item) {
                            tindakanHtml += '<tr><td class="text-center">' + (i+1) + '</td>'
                                + '<td>' + item.nama_tindakan + '</td>'
                                + '<td class="text-center">' + item.jumlah + '</td>'
                                + '<td>Rp ' + formatRupiah(item.tarif) + '</td>'
                                + '<td>' + (item.keterangan || '-') + '</td></tr>';
                        });
                    } else {
                        tindakanHtml = '<tr><td colspan="5" class="text-center text-muted">Tidak ada tindakan</td></tr>';
                    }
                    $('#detail_tindakan').html(tindakanHtml);

                    $('#modalDetail').modal('show');
                } else {
                    alert('Gagal memuat data: ' + response.message);
                }
            },
            error: function() { alert('Terjadi kesalahan saat memuat data.'); }
        });
    }

    function konfirmasiBatal() {
        var pemeriksaan_id = $('#detail_pemeriksaan_id').val();
        $('#pemeriksaan_id_batal').val(pemeriksaan_id);
        $('#modalDetail').modal('hide');
        setTimeout(function() { $('#modalVerifikasiBatal').modal('show'); }, 500);
    }

    /* ─────────────────── MODAL DIAGNOSA ─────────────────── */
    function openDiagnosa(pemeriksaan_id, nama, no_rm, keluhan, pemeriksaan_fisik, jk, umur) {
        $('#pemeriksaan_id').val(pemeriksaan_id);
        $('#diagnosa_pasien_no_rm').val(no_rm);
        $('#info_nama').text(nama);
        $('#info_no_rm').text(no_rm);
        $('#info_keluhan').text(keluhan);
        $('#info_jk_umur').text((jk == 'L' ? 'Laki-laki' : 'Perempuan') + ' / ' + umur + ' tahun');
        $('#input_pemeriksaanfisik').val(pemeriksaan_fisik);
        $('#input_diagnosa').val('');
        $('#input_catatan').val('');

        // reset semua state
        obatItems = []; tindakanItems = []; bhpItems = [];
        obatRowSeq = 0; tindakanRowSeq = 0; bhpRowSeq = 0;
        renderObatTable(); renderTindakanTable(); renderBhpTable();

        $('#pickerTindakanSelect').val('').trigger('change');
        $('#pickerTindakanJumlah').val(1);
        $('#pickerBhpSelect').val('').trigger('change');
        $('#pickerBhpJumlah').val(1);
        $('#pickerObatSelect').val(null).trigger('change');
        $('#pickerObatJumlah').val(1);
        $('#pickerObatSigna').val('');

        $('#form-tab').tab('show');
        $('#riwayatAccordion').html(
            '<div class="text-center py-5">'
            + '<i class="fas fa-history fa-3x text-muted mb-3"></i>'
            + '<p class="text-muted">Klik tab untuk memuat riwayat</p></div>'
        );
        $('#modalDiagnosa').modal('show');
    }

    /* ─────────────────── RIWAYAT ─────────────────── */
    function loadRiwayatPasien() {
        var no_rm = $('#diagnosa_pasien_no_rm').val();
        if (!no_rm) {
            $('#riwayatAccordion').html('<div class="alert alert-warning">Data pasien tidak tersedia</div>');
            return;
        }
        $('#riwayatAccordion').html(
            '<div class="text-center py-5">'
            + '<div class="spinner-border text-primary" role="status"></div>'
            + '<p class="mt-2 text-muted">Memuat riwayat...</p></div>'
        );
        $.ajax({
            url: 'ajax/get_riwayat_pasien.php',
            type: 'POST',
            data: { no_rm: no_rm },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    var html = '<div class="accordion" id="accordionRiwayat">';
                    $.each(response.data, function(i, item) {
                        var aid = 'collapseRiwayat' + i;
                        html += '<div class="accordion-item mb-3" style="border:1px solid #dee2e6;border-radius:10px;">'
                            + '<h2 class="accordion-header"><button class="accordion-button collapsed" type="button" '
                            + 'data-bs-toggle="collapse" data-bs-target="#' + aid + '">'
                            + '<div class="w-100"><div class="d-flex justify-content-between align-items-center">'
                            + '<span><i class="fas fa-calendar-alt me-2 text-primary"></i><strong>' + item.tanggal_formatted + '</strong></span>'
                            + '<span class="badge bg-info">' + item.jam + '</span></div>'
                            + '<small class="text-muted"><i class="fas fa-user-md me-1"></i>' + (item.nama_dokter || 'Dokter') + '</small>'
                            + '</div></button></h2>'
                            + '<div id="' + aid + '" class="accordion-collapse collapse" data-bs-parent="#accordionRiwayat">'
                            + '<div class="accordion-body">'
                            + '<div class="mb-2"><strong>Keluhan:</strong><div class="border rounded p-2 bg-light">' + item.keluhan + '</div></div>'
                            + '<div class="mb-2"><strong>Diagnosa:</strong><div class="border rounded p-2 bg-light">' + (item.diagnosa || '-') + '</div></div>';

                        if (item.tindakan && item.tindakan.length > 0) {
                            html += '<div class="mb-2"><strong>Tindakan:</strong><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>No</th><th>Tindakan</th><th>Jumlah</th><th>Tarif</th></tr></thead><tbody>';
                            $.each(item.tindakan, function(j, t) {
                                html += '<tr><td>' + (j+1) + '</td><td>' + t.nama_tindakan + '</td><td>' + t.jumlah + '</td><td>Rp ' + formatRupiah(t.tarif) + '</td></tr>';
                            });
                            html += '</tbody></table></div>';
                        }
                        if (item.resep && item.resep.length > 0) {
                            html += '<div class="mb-2"><strong>Resep:</strong><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>No</th><th>Obat</th><th>Jumlah</th><th>Aturan</th></tr></thead><tbody>';
                            $.each(item.resep, function(j, r) {
                                html += '<tr><td>' + (j+1) + '</td><td>' + r.nama_obat + '</td><td>' + r.jumlah + '</td><td>' + r.aturan_pakai + '</td></tr>';
                            });
                            html += '</tbody></table></div>';
                        }
                        html += '</div></div></div>';
                    });
                    html += '</div>';
                    $('#riwayatAccordion').html(html);
                } else {
                    $('#riwayatAccordion').html('<div class="text-center py-5"><i class="fas fa-inbox fa-3x text-muted mb-3"></i><p class="text-muted">Belum ada riwayat</p></div>');
                }
            },
            error: function() {
                $('#riwayatAccordion').html('<div class="alert alert-danger">Gagal memuat riwayat</div>');
            }
        });
    }

    /* ─────────────────── PANEL ANTRIAN ─────────────────── */
    document.querySelectorAll('.btn-periksa').forEach(function(btn) {
        btn.addEventListener('click', function() {
            sessionStorage.setItem('panggilAntrian', JSON.stringify({
                no  : this.dataset.no,
                poli: this.dataset.poli,
                nama: this.dataset.nama
            }));
        });
    });

    (function() {
        var raw = sessionStorage.getItem('panggilAntrian');
        if (!raw) return;
        sessionStorage.removeItem('panggilAntrian');
        var data;
        try { data = JSON.parse(raw); } catch(e) { return; }

        var el = document.createElement('div');
        el.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;padding:14px 20px;'
            + 'background:#10b981;color:#fff;border-radius:10px;font-weight:600;font-size:14px;'
            + 'box-shadow:0 4px 12px rgba(0,0,0,.2);opacity:0;transition:opacity .3s;max-width:340px;';
        el.innerHTML = '<i class="fas fa-bullhorn me-2"></i>Memanggil <strong>' + data.no + '</strong> – ' + data.nama;
        document.body.appendChild(el);
        setTimeout(function() { el.style.opacity = 1; }, 10);
        setTimeout(function() { el.style.opacity = 0; setTimeout(function() { el.remove(); }, 300); }, 4000);
    })();

    /* ─────────────────── INIT ─────────────────── */
    $(document).ready(function() {
        $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

        $('#pickerBhpSelect').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#modalDiagnosa'),
            width: '100%'
        });
        $('#pickerTindakanSelect').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#modalDiagnosa'),
            width: '100%'
        });

        $('#pickerObatSelect').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#modalDiagnosa'),
            width: '100%',
            placeholder: 'Ketik nama obat...',
            minimumInputLength: 2,
            ajax: {
                url: 'ajax/search_obat.php',
                type: 'GET',
                dataType: 'json',
                delay: 300,
                data: function(params) { return { q: params.term }; },
                processResults: function(data) {
                    return {
                        results: $.map(data, function(item) {
                            return { id: item.id, text: item.nama_obat + ' (Stok: ' + item.stok + ')' };
                        })
                    };
                },
                cache: true
            }
        });

        $(document).on('select2:open', function() {
            var field = document.querySelector('.select2-container--open .select2-search__field');
            if (field) field.focus();
        });
    });

    $(document).on('click', '.btn-diagnosa', function() {
        var btn = $(this);
        openDiagnosa(
            btn.data('id'),
            btn.data('nama'),
            btn.data('norms'),
            btn.data('keluhan'),
            btn.data('pf'),
            btn.data('jk'),
            btn.data('umur')
        );
    });
    </script>

    <?php include 'antrian/modal_panggil_medis.php'; ?>
</body>
</html>