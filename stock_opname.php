<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'gudang' && $_SESSION['role'] != 'admin'&& $_SESSION['role'] != 'petugas')) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';
$is_admin = ($_SESSION['role'] == 'admin');
$current_user_id = $_SESSION['user_id'];

// ============================================================
// Buat Stock Opname Baru (hanya gudang)
// ============================================================
if (isset($_POST['buat_opname']) && !$is_admin) {
    $no_opname = "SO" . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    $tgl_opname = sanitize($_POST['tgl_opname']);
    $keterangan = sanitize($_POST['keterangan']);
    
    $query = "INSERT INTO stock_opname (no_opname, tgl_opname, user_id, keterangan) 
              VALUES ('$no_opname', '$tgl_opname', '$current_user_id', '$keterangan')";
    
    if (mysqli_query($conn, $query)) {
        $opname_id = mysqli_insert_id($conn);
        
        $query_obat = "SELECT id, stok FROM obat ORDER BY nama_obat";
        $result_obat = mysqli_query($conn, $query_obat);
        
        while ($obat = mysqli_fetch_assoc($result_obat)) {
            $query_detail = "INSERT INTO detail_stock_opname (opname_id, obat_id, stok_sistem, stok_fisik_total, selisih) 
                            VALUES ('$opname_id', '{$obat['id']}', '{$obat['stok']}', 0, 0)";
            mysqli_query($conn, $query_detail);
        }
        
        header("Location: stock_opname.php?id=$opname_id");
        exit();
    } else {
        $error = "Gagal membuat stock opname: " . mysqli_error($conn);
    }
}

// ============================================================
// Update Stock Opname (draft, hanya gudang/petugas)
// Masing-masing petugas isi stok fisik mereka sendiri
// ============================================================
if (isset($_POST['update_opname']) && !$is_admin) {
    $opname_id = sanitize($_POST['opname_id']);
    $detail_ids = $_POST['detail_id'];
    $stok_fisiks = $_POST['stok_fisik'];
    $keterangans = $_POST['keterangan'];
    
    for ($i = 0; $i < count($detail_ids); $i++) {
        $detail_id    = sanitize($detail_ids[$i]);
        $stok_fisik_raw = trim($stok_fisiks[$i]);
        $keterangan   = sanitize($keterangans[$i]);
        
        // Ambil stok_sistem dan obat_id
        $query_detail = "SELECT stok_sistem, obat_id FROM detail_stock_opname WHERE id = '$detail_id'";
        $result_detail = mysqli_query($conn, $query_detail);
        $row_detail    = mysqli_fetch_assoc($result_detail);
        $stok_sistem   = $row_detail['stok_sistem'];
        $obat_id       = $row_detail['obat_id'];
        
        // Cek row petugas untuk USER YANG LOGIN + obat ini
        $query_cek = "SELECT id FROM detail_stock_opname_petugas 
                      WHERE opname_id = '$opname_id' AND obat_id = '$obat_id' AND petugas_id = '$current_user_id'";
        $result_cek = mysqli_query($conn, $query_cek);
        $row_cek    = mysqli_fetch_assoc($result_cek);
        
        // FIX: Perbaikan validasi stok fisik
        if ($stok_fisik_raw !== '' && $stok_fisik_raw !== null) {
            $stok_fisik = (int)$stok_fisik_raw;
            
            if ($stok_fisik >= 0) {
                if ($row_cek) {
                    // Sudah ada -> UPDATE
                    $query_petugas = "UPDATE detail_stock_opname_petugas 
                                     SET stok_fisik  = '$stok_fisik',
                                         keterangan  = '$keterangan'
                                     WHERE opname_id = '$opname_id' AND obat_id = '$obat_id' AND petugas_id = '$current_user_id'";
                } else {
                    // Belum ada -> INSERT
                    $query_petugas = "INSERT INTO detail_stock_opname_petugas (opname_id, obat_id, petugas_id, stok_fisik, keterangan) 
                                     VALUES ('$opname_id', '$obat_id', '$current_user_id', '$stok_fisik', '$keterangan')";
                }
                mysqli_query($conn, $query_petugas);
            }
        } else {
            // Dikosongkan -> hapus row petugas ini kalau ada
            if ($row_cek) {
                mysqli_query($conn, "DELETE FROM detail_stock_opname_petugas 
                                     WHERE opname_id = '$opname_id' AND obat_id = '$obat_id' AND petugas_id = '$current_user_id'");
            }
        }
        
        // Hitung ulang TOTAL dari SEMUA petugas untuk obat ini
        $query_sum = "SELECT COALESCE(SUM(stok_fisik), 0) as total 
                      FROM detail_stock_opname_petugas 
                      WHERE opname_id = '$opname_id' AND obat_id = '$obat_id'";
        $result_sum   = mysqli_query($conn, $query_sum);
        $row_sum      = mysqli_fetch_assoc($result_sum);
        $total_fisik  = $row_sum['total'];
        $selisih      = $total_fisik - $stok_sistem;
        
        // Update detail_stock_opname dengan total & selisih
        mysqli_query($conn, "UPDATE detail_stock_opname 
                            SET stok_fisik_total = '$total_fisik',
                                selisih          = '$selisih'
                            WHERE id = '$detail_id'");
    }
    
    header("Location: stock_opname.php?id=$opname_id&success=update&clear=1");
    exit();
}

// ============================================================
// Simpan & Terapkan (HANYA ADMIN)
// ============================================================
if (isset($_POST['simpan_opname']) && $is_admin) {
    $opname_id = sanitize($_POST['opname_id']);
    
    // Cek ada stok fisik yang diisi dari petugas
    $query_check = "SELECT COUNT(*) as total FROM detail_stock_opname 
                    WHERE opname_id = '$opname_id' AND stok_fisik_total > 0";
    $result_check = mysqli_query($conn, $query_check);
    $row_check    = mysqli_fetch_assoc($result_check);
    
    if ($row_check['total'] == 0) {
        $error = "Harap isi stok fisik terlebih dahulu!";
    } else {
        mysqli_autocommit($conn, FALSE);
        
        try {
            // Ambil semua detail opname
            $query_details = "SELECT dso.* FROM detail_stock_opname dso
                             WHERE dso.opname_id = '$opname_id'";
            $result_details = mysqli_query($conn, $query_details);
            
            while ($detail = mysqli_fetch_assoc($result_details)) {
                $obat_id         = $detail['obat_id'];
                $stok_fisik_total = $detail['stok_fisik_total'];
                $stok_sistem     = $detail['stok_sistem'];
                $selisih         = $stok_fisik_total - $stok_sistem;
                
                // Update stok obat
                mysqli_query($conn, "UPDATE obat SET stok = '$stok_fisik_total' WHERE id = '$obat_id'");
                
                // Log jika ada selisih
                if ($selisih != 0) {
                    $jenis       = ($selisih > 0) ? 'masuk' : 'keluar';
                    $jumlah      = abs($selisih);
                    $ket_log     = "Stock Opname - " . (($selisih > 0) ? "Kelebihan" : "Kekurangan") . " $jumlah";
                    mysqli_query($conn, "INSERT INTO stok_log (obat_id, user_id, jenis, jumlah, keterangan) 
                                        VALUES ('$obat_id', '$current_user_id', '$jenis', '$jumlah', '$ket_log')");
                }
            }
            
            mysqli_query($conn, "UPDATE stock_opname SET status = 'selesai' WHERE id = '$opname_id'");
            
            mysqli_commit($conn);
            mysqli_autocommit($conn, TRUE);
            
            header("Location: stock_opname.php?id=$opname_id&success=simpan");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            mysqli_autocommit($conn, TRUE);
            $error = "Gagal menyimpan stock opname: " . $e->getMessage();
        }
    }
}

// ============================================================
// Success message dari URL
// ============================================================
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'update') {
        $success = "Stok fisik Anda berhasil disimpan!";
    } elseif ($_GET['success'] == 'simpan') {
        $success = "Stock Opname berhasil diselesaikan dan stok telah diupdate!";
    }
}

// ============================================================
// Ambil data Stock Opname
// ============================================================
$opname_id      = isset($_GET['id']) ? sanitize($_GET['id']) : null;
$current_opname = null;
$details        = array();
$petugas_per_obat = array();

if ($opname_id) {
    // Header opname
    $query_opname = "SELECT so.*, u.nama_lengkap 
                    FROM stock_opname so
                    JOIN users u ON so.user_id = u.id
                    WHERE so.id = '$opname_id'";
    $result_opname  = mysqli_query($conn, $query_opname);
    $current_opname = mysqli_fetch_assoc($result_opname);
    
    if ($current_opname) {
        // Cek apakah ada parameter clear (untuk kosongkan input setelah save)
        $clear_input = isset($_GET['clear']) && $_GET['clear'] == '1';
        
        // Detail utama + data petugas yang SEDANG LOGIN (untuk isi value input)
        // stok_fisik_total sudah ada di detail_stock_opname (hasil SUM semua petugas)
        if ($clear_input) {
            // Jangan ambil my_stok_fisik dan my_keterangan (biar kosong)
            $query_details = "SELECT dso.*,
                             o.kode_obat, o.nama_obat, o.satuan,
                             NULL AS my_stok_fisik,
                             NULL AS my_keterangan
                             FROM detail_stock_opname dso
                             JOIN obat o ON dso.obat_id = o.id
                             WHERE dso.opname_id = '$opname_id'
                             ORDER BY o.nama_obat";
        } else {
            // Ambil data petugas yang login (untuk edit)
            $query_details = "SELECT dso.*,
                             o.kode_obat, o.nama_obat, o.satuan,
                             dsop.stok_fisik       AS my_stok_fisik,
                             dsop.keterangan       AS my_keterangan
                             FROM detail_stock_opname dso
                             JOIN obat o ON dso.obat_id = o.id
                             LEFT JOIN detail_stock_opname_petugas dsop 
                                 ON dsop.opname_id = dso.opname_id 
                                AND dsop.obat_id   = dso.obat_id 
                                AND dsop.petugas_id = '$current_user_id'
                             WHERE dso.opname_id = '$opname_id'
                             ORDER BY o.nama_obat";
        }
        $result_details = mysqli_query($conn, $query_details);
        
        while ($row = mysqli_fetch_assoc($result_details)) {
            $details[] = $row;
        }
        
        // Ambil breakdown semua petugas per obat (untuk kolom "Petugas" dan expand)
        $query_petugas = "SELECT dsop.obat_id, dsop.stok_fisik, dsop.keterangan, u.nama_lengkap as petugas_nama
                         FROM detail_stock_opname_petugas dsop
                         JOIN users u ON dsop.petugas_id = u.id
                         WHERE dsop.opname_id = '$opname_id'
                         ORDER BY dsop.obat_id, u.nama_lengkap";
        $result_petugas = mysqli_query($conn, $query_petugas);
        
        while ($row_p = mysqli_fetch_assoc($result_petugas)) {
            $petugas_per_obat[$row_p['obat_id']][] = $row_p;
        }
    }
}

// ============================================================
// Riwayat Stock Opname (halaman list)
// ============================================================
$query_riwayat = "SELECT so.*, u.nama_lengkap,
                  (SELECT COUNT(*) FROM detail_stock_opname WHERE opname_id = so.id AND selisih != 0) as jumlah_selisih
                  FROM stock_opname so
                  JOIN users u ON so.user_id = u.id
                  ORDER BY so.created_at DESC
                  LIMIT 20";
$result_riwayat = mysqli_query($conn, $query_riwayat);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Opname - Healoka</title>
    <link rel="shortcut icon" type="image/png" href ="asset/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php include 'sidebar_style.php'; ?>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; padding: 0;
        }
        .container-fluid { padding: 0; margin: 0; }
        .row { margin: 0; display: flex; }
        .col-md-2 { flex: 0 0 250px; max-width: 250px; }
        .col-md-10 { flex: 1; margin-left: 250px; padding: 0; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }

        .card {
            border: none; border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white; border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px; font-weight: 600;
        }
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: #495057; font-weight: 600; font-size: 0.9em;
        }
        .badge { padding: 6px 12px; border-radius: 8px; font-weight: 500; }
        .btn { border-radius: 8px; padding: 8px 16px; font-weight: 500; }
        .form-control { border-radius: 8px; border: 1px solid #e0e0e0; }
        .modal-content { border-radius: 15px; }
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white; border-radius: 15px 15px 0 0;
        }

        .selisih-positif { background-color: #d1fae5; }
        .selisih-negatif { background-color: #fee2e2; }
        .input-stok-fisik { font-weight: bold; font-size: 1em; }

        .search-box { position: relative; margin-bottom: 20px; }
        .search-box input { padding-left: 40px; }
        .search-box i {
            position: absolute; left: 15px; top: 50%;
            transform: translateY(-50%); color: #6c757d;
        }

        /* Sub-row petugas */
        .sub-row-petugas { display: none; }
        .sub-row-petugas.show { display: table-row; }
        .sub-row-petugas td { background-color: #f0f4ff; font-size: 0.88em; padding: 6px 12px; }
        .btn-expand {
            background: none; border: none;
            color: var(--primary-color); padding: 0 4px;
            cursor: pointer; font-size: 0.85em;
        }
        .btn-expand:hover { text-decoration: underline; }
        .btn-expand i { transition: transform 0.2s; }
        .btn-expand.open i { transform: rotate(90deg); }

        /* Badge petugas di kolom */
        .petugas-badge {
            display: inline-block;
            background: #eef0ff; color: #667eea;
            border-radius: 12px; padding: 2px 8px;
            font-size: 0.78em; margin: 1px 1px 1px 0;
        }

        @media print {
            .no-print { display: none !important; }
            .sidebar { display: none !important; }
            .col-md-10 { width: 100% !important; max-width: 100% !important; margin-left: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
            body { background: white !important; }
            .sub-row-petugas { display: table-row !important; }
            .btn-expand { display: none !important; }
            /* Header cetak tampil di setiap halaman */
@page {
    margin-top: 95px;
    margin-bottom: 20px;
}
.print-header {
    display: block !important;
    position: fixed;
    top: 0; left: 0; right: 0;
    background: white;
    border-bottom: 3px solid #000;
    padding: 8px 20px;
    text-align: center;
    z-index: 9999;
}
.print-header h2  { font-size: 15px; font-weight: 700; margin: 0; }
.print-header p   { font-size: 11px; margin: 2px 0 0 0; }
.print-header .info-row {
    font-size: 11px;
    display: flex;
    justify-content: space-between;
    margin-top: 5px;
    border-top: 1px solid #ccc;
    padding-top: 4px;
}
        }
    </style>
</head>
<body>
    <div class="print-header" style="display:none;">
        <div class="info-row">
        <span><strong>No. Opname:</strong> <?php echo $current_opname['no_opname']; ?></span>
        <span><strong>Tanggal:</strong> <?php echo date('d F Y', strtotime($current_opname['tgl_opname'])); ?></span>
        <span><strong>Koordinator:</strong> <?php echo $current_opname['nama_lengkap']; ?></span>
        <span><strong>Status:</strong> <?php echo ucfirst($current_opname['status']); ?></span>
    </div>
    </div>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        

            <div class="container-fluid px-4">
                <!-- Alerts -->
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- ================================================ -->
                <!-- HALAMAN LIST                                      -->
                <!-- ================================================ -->
                <?php if (!$opname_id): ?>

                <div class="row mb-3 no-print">
                    <div class="col-md-6">
                        <?php if (!$is_admin): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalBuatOpname">
                            <i class="fas fa-plus me-2"></i>Buat Stock Opname Baru
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="fas fa-history me-2"></i>Riwayat Stock Opname</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>No. Opname</th><th>Tanggal</th><th>Koordinator</th>
                                        <th>Jumlah Selisih</th><th>Status</th><th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result_riwayat) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result_riwayat)): ?>
                                    <tr>
                                        <td><strong><?php echo $row['no_opname']; ?></strong></td>
                                        <td><?php echo date('d/m/Y', strtotime($row['tgl_opname'])); ?></td>
                                        <td><?php echo $row['nama_lengkap']; ?></td>
                                        <td>
                                            <?php if ($row['jumlah_selisih'] > 0): ?>
                                                <span class="badge bg-warning"><?php echo $row['jumlah_selisih']; ?> item</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Sesuai</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $row['status']=='selesai' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="stock_opname.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye me-1"></i>Detail
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2"></i>
                                            <p>Belum ada riwayat stock opname</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ================================================ -->
                <!-- HALAMAN DETAIL OPNAME                             -->
                <!-- ================================================ -->
                <?php else: ?>
                <?php if ($current_opname): ?>

                <!-- Tombol atas -->
                <div class="row mb-3 no-print">
                    <div class="col-md-6">
                        <a href="stock_opname.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="button" class="btn btn-info" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clipboard-check me-2"></i>Stock Opname: <?php echo $current_opname['no_opname']; ?>
                        <?php if ($is_admin && $current_opname['status']=='selesai'): ?>
                            <span class="badge bg-info ms-2"><i class="fas fa-eye me-1"></i>View Only</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <!-- Info Header -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p class="mb-2"><strong>No. Opname:</strong> <?php echo $current_opname['no_opname']; ?></p>
                                <p class="mb-2"><strong>Tanggal:</strong> <?php echo date('d F Y', strtotime($current_opname['tgl_opname'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2"><strong>Koordinator:</strong> <?php echo $current_opname['nama_lengkap']; ?></p>
                                <p class="mb-2"><strong>Status:</strong>
                                    <span class="badge <?php echo $current_opname['status']=='selesai' ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo ucfirst($current_opname['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <?php if ($current_opname['keterangan']): ?>
                        <div class="alert alert-info">
                            <strong>Keterangan:</strong> <?php echo $current_opname['keterangan']; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Search -->
                        <div class="search-box no-print">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchObat" class="form-control"
                                   placeholder="Cari nama obat atau kode obat..." autocomplete="off">
                        </div>

                        <!-- FORM -->
                        <form method="POST" id="formOpname">
                            <input type="hidden" name="opname_id" value="<?php echo $opname_id; ?>">

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="tableOpname">
                                    <thead>
                                        <tr>
                                            <th width="4%">No</th>
                                            <th width="10%">Kode</th>
                                            <th width="22%">Nama Obat</th>
                                            <th width="9%">Stok Sistem</th>
                                            <?php if (!$is_admin && $current_opname['status']=='draft'): ?>
                                                <!-- Gudang/Petugas draft: tampilkan kolom input pribadi + total -->
                                                <th width="10%">Stok Saya</th>
                                                <th width="10%">Total Fisik</th>
                                            <?php else: ?>
                                                <!-- Admin atau selesai: cukup total -->
                                                <th width="11%">Total Fisik</th>
                                            <?php endif; ?>
                                            <th width="9%">Selisih</th>
                                            <th width="26%">Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $no = 1;
                                    foreach ($details as $detail):
                                        $selisih_class = '';
                                        if ($detail['selisih'] > 0)      $selisih_class = 'selisih-positif';
                                        elseif ($detail['selisih'] < 0)  $selisih_class = 'selisih-negatif';

                                        $ada_petugas = isset($petugas_per_obat[$detail['obat_id']]) && count($petugas_per_obat[$detail['obat_id']]) > 0;
                                    ?>
                                    <!-- ROW UTAMA -->
                                    <tr class="<?php echo $selisih_class; ?> data-row"
                                        data-kode="<?php echo strtolower($detail['kode_obat']); ?>"
                                        data-nama="<?php echo strtolower($detail['nama_obat']); ?>">
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo $detail['kode_obat']; ?></td>

                                        <!-- Nama obat + tombol expand petugas -->
                                        <td>
                                            <?php echo $detail['nama_obat']; ?> (<?php echo $detail['satuan']; ?>)
                                            <?php if ($ada_petugas): ?>
                                            <br>
                                            <button type="button" class="btn-expand" onclick="togglePetugas('<?php echo $detail['obat_id']; ?>')">
                                                <i class="fas fa-caret-right" id="icon-<?php echo $detail['obat_id']; ?>"></i>
                                                <?php echo count($petugas_per_obat[$detail['obat_id']]); ?> petugas
                                            </button>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Stok Sistem -->
                                        <td class="text-center">
                                            <strong><?php echo $detail['stok_sistem']; ?></strong>
                                            <?php if (!$is_admin && $current_opname['status']=='draft'): ?>
                                            <input type="hidden" name="detail_id[]" value="<?php echo $detail['id']; ?>">
                                            <?php endif; ?>
                                        </td>

                                        <!-- Kolom "Stok Saya" — hanya gudang/petugas + draft -->
                                        <?php if (!$is_admin && $current_opname['status']=='draft'): ?>
                                        <td>
                                            <input type="number"
                                                   class="form-control form-control-sm input-stok-fisik stok-fisik"
                                                   name="stok_fisik[]"
                                                   value="<?php echo ($detail['my_stok_fisik'] !== null) ? $detail['my_stok_fisik'] : ''; ?>"
                                                   min="0"
                                                   placeholder="—"
                                                   data-sistem="<?php echo $detail['stok_sistem']; ?>"
                                                   data-row="<?php echo $detail['id']; ?>">
                                        </td>
                                        <?php endif; ?>

                                        <!-- Total Fisik (dari SUM semua petugas) -->
                                        <td class="text-center total-fisik" data-row="<?php echo $detail['id']; ?>">
                                            <strong><?php echo $detail['stok_fisik_total']; ?></strong>
                                        </td>

                                        <!-- Selisih -->
                                        <td class="text-center selisih-value" data-row="<?php echo $detail['id']; ?>">
                                            <strong class="<?php echo $detail['selisih']>0 ? 'text-success' : ($detail['selisih']<0 ? 'text-danger' : ''); ?>">
                                                <?php echo $detail['selisih']>0 ? '+' : ''; ?><?php echo $detail['selisih']; ?>
                                            </strong>
                                        </td>

                                        <!-- Keterangan -->
                                        <td>
                                            <?php if (!$is_admin && $current_opname['status']=='draft'): ?>
                                                <!-- Gudang/Petugas draft: input keterangan pribadi -->
                                                <input type="text" class="form-control form-control-sm"
                                                       name="keterangan[]"
                                                       value="<?php echo ($detail['my_keterangan'] !== null) ? $detail['my_keterangan'] : ''; ?>"
                                                       placeholder="Keterangan...">
                                            <?php elseif ($ada_petugas): ?>
                                                <!-- Tampilkan keterangan per petugas -->
                                                <?php foreach ($petugas_per_obat[$detail['obat_id']] as $p): ?>
                                                    <small>
                                                        <strong><?php echo $p['petugas_nama']; ?>:</strong>
                                                        <?php echo $p['keterangan'] ? $p['keterangan'] : '-'; ?>
                                                    </small><br>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- SUB-ROW BREAKDOWN PETUGAS -->
                                    <?php if ($ada_petugas): ?>
                                    <tr class="sub-row-petugas" id="sub-<?php echo $detail['obat_id']; ?>">
                                        <td colspan="3"></td>
                                        <td colspan="<?php echo (!$is_admin && $current_opname['status']=='draft') ? 5 : 4; ?>" style="padding:0;">
                                            <table class="table table-sm mb-0">
                                                <thead>
                                                    <tr style="background-color:#e8eeff;">
                                                        <th style="width:35%;font-size:0.82em;color:#495057;">Petugas</th>
                                                        <th style="width:20%;font-size:0.82em;color:#495057;text-align:center;">Stok Fisik</th>
                                                        <th style="width:45%;font-size:0.82em;color:#495057;">Keterangan</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $sum_sub = 0;
                                                    foreach ($petugas_per_obat[$detail['obat_id']] as $p):
                                                        $sum_sub += $p['stok_fisik'];
                                                    ?>
                                                    <tr>
                                                        <td><i class="fas fa-user me-1 text-primary"></i><?php echo $p['petugas_nama']; ?></td>
                                                        <td class="text-center"><strong><?php echo $p['stok_fisik']; ?></strong></td>
                                                        <td><?php echo $p['keterangan'] ? $p['keterangan'] : '-'; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <!-- Total -->
                                                    <tr style="background-color:#dce6ff;font-weight:600;">
                                                        <td>Total</td>
                                                        <td class="text-center"><?php echo $sum_sub; ?></td>
                                                        <td></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    <?php endif; ?>

                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Info pencarian -->
                            <div class="alert alert-info no-print" id="searchInfo" style="display:none;">
                                <i class="fas fa-info-circle me-2"></i>
                                Menampilkan <strong id="resultCount">0</strong> dari <?php echo count($details); ?> item
                            </div>

                            <!-- Tombol aksi -->
                            <div class="row mt-4 no-print">
                                <div class="col-md-12 text-end">
                                    <?php if (!$is_admin && $current_opname['status']=='draft'): ?>
                                        <!-- Gudang/Petugas: hanya bisa simpan stok mereka -->
                                        <button type="submit" name="update_opname" class="btn btn-warning">
                                            <i class="fas fa-save me-2"></i>Simpan Stok Saya
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($is_admin && $current_opname['status']=='draft'): ?>
                                        <!-- Admin: bisa menyelesaikan opname -->
                                        <button type="button" class="btn btn-success" onclick="simpanOpname()">
                                            <i class="fas fa-check me-2"></i>Selesaikan Opname
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php endif; ?>
                <?php endif; ?>
            </div><!-- end container -->
        </div><!-- end col-md-10 -->
    </div><!-- end row -->
</div><!-- end container-fluid -->

<!-- Modal Buat Opname (gudang only) -->
<?php if (!$is_admin): ?>
<div class="modal fade" id="modalBuatOpname" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Buat Stock Opname Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tanggal Opname <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tgl_opname" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="3" placeholder="Keterangan stock opname..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="buat_opname" class="btn btn-primary">
                        <i class="fas fa-check me-2"></i>Buat Stock Opname
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== SEARCH =====
$(document).ready(function() {
    $('#searchObat').on('keyup', function() {
        var searchText = $(this).val().toLowerCase().trim();
        var allRows    = $('.data-row');
        var visibleCount = 0;

        if (searchText === '') {
            allRows.show();
            $('.sub-row-petugas').removeClass('show');
            $('.btn-expand').removeClass('open');
            $('#searchInfo').hide();
            visibleCount = allRows.length;
        } else {
            allRows.each(function() {
                var kode = $(this).attr('data-kode') || '';
                var nama = $(this).attr('data-nama') || '';

                if (kode.indexOf(searchText) !== -1 || nama.indexOf(searchText) !== -1) {
                    $(this).show();
                    visibleCount++;
                    $(this).next('.sub-row-petugas').addClass('show');
                } else {
                    $(this).hide();
                    $(this).next('.sub-row-petugas').removeClass('show');
                }
            });
            $('#resultCount').text(visibleCount);
            $('#searchInfo').show();
        }
        updateRowNumbers();
    });
});

function updateRowNumbers() {
    var no = 1;
    $('.data-row:visible').each(function() {
        $(this).find('td:first').text(no++);
    });
}

// ===== TOGGLE SUB-ROW =====
function togglePetugas(obatId) {
    var subRow = $('#sub-' + obatId);
    var btn    = subRow.prev().find('.btn-expand');
    subRow.toggleClass('show');
    btn.toggleClass('open');
}

// ===== HITUNG SELISIH REAL-TIME (gudang/petugas draft) =====
// Menyimpan nilai awal saat page load
$(document).ready(function() {
    $('.stok-fisik').each(function() {
        var initialValue = $(this).val();
        // Simpan nilai awal (value attribute saat page load)
        $(this).data('initial-value', initialValue !== '' ? parseInt(initialValue) : 0);
    });
});

// Update perhitungan saat input berubah
$(document).on('input', '.stok-fisik', function() {
    var row        = $(this).closest('tr');
    var rowId      = $(this).data('row');
    var stokSistem = parseInt($(this).data('sistem'));
    var stokSaya   = $(this).val() !== '' ? parseInt($(this).val()) : 0;

    // Ambil total fisik dari server (sum semua petugas)
    var totalCell     = row.find('.total-fisik[data-row="' + rowId + '"] strong');
    var totalDariServer = parseInt(totalCell.data('original')) || 0;
    
    // Simpan nilai original dari server jika belum ada
    if (totalCell.data('original') === undefined) {
        totalCell.data('original', parseInt(totalCell.text()) || 0);
        totalDariServer = parseInt(totalCell.text()) || 0;
    }

    // Ambil nilai awal input ini (saat page load)
    var nilaiAwal = $(this).data('initial-value') || 0;

    // Hitung total baru: total dari server - kontribusi lama user ini + kontribusi baru
    var totalBaru = totalDariServer - nilaiAwal + stokSaya;
    totalCell.text(totalBaru);

    // Hitung selisih
    var selisih = totalBaru - stokSistem;

    // Update tampilan selisih
    var selisihCell = row.find('.selisih-value[data-row="' + rowId + '"] strong');
    selisihCell.text((selisih > 0 ? '+' : '') + selisih);

    selisihCell.removeClass('text-success text-danger');
    row.removeClass('selisih-positif selisih-negatif');

    if (selisih > 0) {
        selisihCell.addClass('text-success');
        row.addClass('selisih-positif');
    } else if (selisih < 0) {
        selisihCell.addClass('text-danger');
        row.addClass('selisih-negatif');
    }
});

// ===== SIMPAN OPNAME (hanya admin) =====
function simpanOpname() {
    if (confirm('PERHATIAN!\n\nSetelah diselesaikan:\n1. Stok sistem akan diupdate dari total fisik\n2. Data semua petugas sudah tercatat\n3. Tidak bisa diubah lagi\n\nLanjutkan?')) {
        $('<input>').attr({ type:'hidden', name:'simpan_opname', value:'1' }).appendTo('#formOpname');
        $('#formOpname').submit();
    }
}
</script>
</body>
</html>