<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'apoteker' && $_SESSION['role'] != 'admin'&& $_SESSION['role'] != 'petugas') ) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Proses Resep
if (isset($_POST['proses_resep'])) {
    $resep_id = sanitize($_POST['resep_id']);
    
    $query = "UPDATE resep SET status = 'diproses' WHERE id = '$resep_id'";
    if (mysqli_query($conn, $query)) {
        header("Location: apoteker.php?print_etiket=$resep_id");
        exit();
    }
}

// Selesaikan Resep
if (isset($_POST['selesai_resep'])) {
    $resep_id = sanitize($_POST['resep_id']);
    
    $query = "SELECT obat_id, jumlah FROM detail_resep WHERE resep_id = '$resep_id'";
    $result = mysqli_query($conn, $query);
    
    $berhasil = true;
    while ($row = mysqli_fetch_assoc($result)) {
        $obat_id = $row['obat_id'];
        $jumlah  = $row['jumlah'];
        
        $query_update = "UPDATE obat SET stok = stok - $jumlah WHERE id = '$obat_id'";
        if (!mysqli_query($conn, $query_update)) {
            $berhasil = false;
        }
        
        $query_log = "INSERT INTO stok_log (obat_id, user_id, jenis, jumlah, keterangan) 
                      VALUES ('$obat_id', '{$_SESSION['user_id']}', 'keluar', '$jumlah', 'Resep #$resep_id')";
        mysqli_query($conn, $query_log);
    }
    
    if ($berhasil) {
        $query = "UPDATE resep SET status = 'selesai' WHERE id = '$resep_id'";
        mysqli_query($conn, $query);
        $success = "Resep selesai diproses dan stok telah dikurangi";
    } else {
        $error = "Gagal memproses resep";
    }
}

// =====================================================
// BATALKAN SELESAI → kembalikan ke Sedang Diproses
// =====================================================
if (isset($_POST['batalkan_selesai'])) {
    $resep_id = sanitize($_POST['resep_id']);

    // Kembalikan stok obat
    $query = "SELECT obat_id, jumlah FROM detail_resep WHERE resep_id = '$resep_id'";
    $result = mysqli_query($conn, $query);

    $berhasil = true;
    while ($row = mysqli_fetch_assoc($result)) {
        $obat_id = $row['obat_id'];
        $jumlah  = $row['jumlah'];

        // Tambah kembali stok
        $query_restore = "UPDATE obat SET stok = stok + $jumlah WHERE id = '$obat_id'";
        if (!mysqli_query($conn, $query_restore)) {
            $berhasil = false;
        }

        // Log pembalikan stok
        $query_log = "INSERT INTO stok_log (obat_id, user_id, jenis, jumlah, keterangan)
                      VALUES ('$obat_id', '{$_SESSION['user_id']}', 'masuk', '$jumlah', 'Batalkan Resep #$resep_id')";
        mysqli_query($conn, $query_log);
    }

    if ($berhasil) {
        $query_status = "UPDATE resep SET status = 'diproses' WHERE id = '$resep_id'";
        mysqli_query($conn, $query_status);
        $success = "Resep berhasil dibatalkan dan dikembalikan ke Sedang Diproses. Stok obat telah dipulihkan.";
    } else {
        $error = "Gagal membatalkan resep";
    }
}

// =====================================================
// EDIT JUMLAH OBAT
// =====================================================
if (isset($_POST['edit_jumlah_obat'])) {
    $resep_id      = sanitize($_POST['resep_id']);
    $detail_ids    = $_POST['detail_id'];
    $jumlah_baru   = $_POST['jumlah_baru'];

    $berhasil = true;
    foreach ($detail_ids as $i => $detail_id) {
        $detail_id  = sanitize($detail_id);
        $jml        = (int)$jumlah_baru[$i];

        if ($jml <= 0) {
            $berhasil = false;
            $error = "Jumlah obat harus lebih dari 0";
            break;
        }

        $q = "UPDATE detail_resep SET jumlah = $jml WHERE id = '$detail_id' AND resep_id = '$resep_id'";
        if (!mysqli_query($conn, $q)) {
            $berhasil = false;
        }
    }

    if ($berhasil && !$error) {
        $success = "Jumlah obat berhasil diperbarui";
    } elseif (!$error) {
        $error = "Gagal memperbarui jumlah obat";
    }
}

// Check if print etiket
$print_etiket = isset($_GET['print_etiket']) ? sanitize($_GET['print_etiket']) : null;
if ($print_etiket) {
    $query_print = "SELECT r.*, p.no_antrian, pm.diagnosa, ps.nama_lengkap, ps.no_rm, ps.alamat,
                    TIMESTAMPDIFF(YEAR, ps.tgl_lahir, CURDATE()) as umur
                    FROM resep r
                    JOIN pemeriksaan pm ON r.pemeriksaan_id = pm.id
                    JOIN pendaftaran p ON pm.pendaftaran_id = p.id
                    JOIN pasien ps ON p.pasien_id = ps.id
                    WHERE r.id = '$print_etiket'";
    $result_print = mysqli_query($conn, $query_print);
    $data_print   = mysqli_fetch_assoc($result_print);
    
    $query_obat_print = "SELECT dr.*, o.nama_obat, o.satuan
                         FROM detail_resep dr
                         JOIN obat o ON dr.obat_id = o.id
                         WHERE dr.resep_id = '$print_etiket'";
    $result_obat_print = mysqli_query($conn, $query_obat_print);
}

// Get Resep Menunggu
$query_menunggu = "SELECT r.*, p.no_antrian, pm.diagnosa, ps.nama_lengkap, ps.no_rm
                   FROM resep r
                   JOIN pemeriksaan pm ON r.pemeriksaan_id = pm.id
                   JOIN pendaftaran p ON pm.pendaftaran_id = p.id
                   JOIN pasien ps ON p.pasien_id = ps.id
                   WHERE r.status = 'menunggu' AND r.hapus='0'
                   ORDER BY r.tgl_resep ASC";
$result_menunggu = mysqli_query($conn, $query_menunggu);

// Get Resep Diproses
$query_diproses = "SELECT r.*, p.no_antrian, pm.diagnosa, ps.nama_lengkap, ps.no_rm
                   FROM resep r
                   JOIN pemeriksaan pm ON r.pemeriksaan_id = pm.id
                   JOIN pendaftaran p ON pm.pendaftaran_id = p.id
                   JOIN pasien ps ON p.pasien_id = ps.id
                   WHERE r.status = 'diproses' 
                   AND r.hapus=0
                   ORDER BY r.tgl_resep ASC";
$result_diproses = mysqli_query($conn, $query_diproses);

// Get Resep Selesai Hari Ini
$query_selesai = "SELECT r.*, p.no_antrian, pm.diagnosa, ps.nama_lengkap, ps.no_rm
                  FROM resep r
                  JOIN pemeriksaan pm ON r.pemeriksaan_id = pm.id
                  JOIN pendaftaran p ON pm.pendaftaran_id = p.id
                  JOIN pasien ps ON p.pasien_id = ps.id
                  WHERE r.status = 'selesai' AND DATE(r.tgl_resep) = CURDATE()
                  ORDER BY r.tgl_resep DESC";
$result_selesai = mysqli_query($conn, $query_selesai);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apotek - Healoka</title>
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link rel="shortcut icon" type="image/png" href ="asset/img/logo.png">
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

        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
            font-weight: 600;
        }
        
        .badge { padding: 6px 12px; border-radius: 8px; font-weight: 500; }
        .btn { border-radius: 8px; padding: 8px 16px; font-weight: 500; }
        
        .resep-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .resep-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .modal-content { border-radius: 15px; }
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        /* Modal Edit Jumlah */
        .modal-header-edit {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .obat-detail { background: #f8f9fa; border-radius: 8px; padding: 10px; margin-bottom: 10px; }

        /* SEARCH STYLES */
        .search-box { position: relative; margin-bottom: 15px; }
        .search-box input {
            padding-left: 40px;
            padding-right: 40px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .search-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .search-icon {
            position: absolute; left: 15px; top: 50%;
            transform: translateY(-50%); color: #999; pointer-events: none;
        }
        .clear-search {
            position: absolute; right: 15px; top: 50%;
            transform: translateY(-50%); cursor: pointer; color: #999;
            display: none; transition: color 0.2s;
        }
        .clear-search.show { display: block; }
        .clear-search:hover { color: #333; }
        .search-result-info {
            color: #666; font-size: 13px; margin-bottom: 10px;
            padding: 8px 12px; background: #e7f3ff;
            border-left: 3px solid var(--primary-color);
            border-radius: 5px; display: none;
        }
        .search-result-info.show { display: block; }
        .empty-state { text-align: center; padding: 40px 20px; color: #999; }
        .empty-state i { font-size: 48px; margin-bottom: 15px; opacity: 0.5; }
        .resep-card.hidden { display: none; }

        /* Edit jumlah obat input */
        .jumlah-input {
            width: 90px;
            display: inline-block;
            text-align: center;
            font-weight: 600;
        }

        /* Etiket Styles */
        .etiket-container { page-break-after: always; }
        .etiket {
            width: 5cm; height: 4cm; border: 2px solid #000;
            padding: 5px; margin: 10px auto; background: white;
            font-family: 'Courier New', monospace; font-size: 8px; box-sizing: border-box;
        }
        .etiket-header { text-align: center; border-bottom: 1px solid #000; padding-bottom: 2px; margin-bottom: 3px; }
        .etiket-header h4 { margin: 0; font-weight: bold; font-size: 10px; }
        .etiket-header p { margin: 0; font-size: 6px; line-height: 1; }
        .etiket-body { margin: 3px 0; }
        .etiket-pasien { margin-bottom: 3px; padding-bottom: 2px; border-bottom: 1px dashed #000; }
        .etiket-pasien p { margin: 1px 0; font-size: 7px; line-height: 1.1; }
        .etiket-obat { margin-bottom: 3px; padding: 3px; border: 1px solid #000; background: #f9f9f9; }
        .etiket-obat-nama { font-weight: bold; font-size: 8px; margin-bottom: 2px; line-height: 1.1; }
        .etiket-obat div { font-size: 6px; }
        .etiket-aturan {
            font-size: 7px; font-weight: bold; background: #fff;
            padding: 3px; border: 1px solid #000; margin-top: 2px; line-height: 1.2;
        }
        .etiket-footer {
            text-align: center; margin-top: 3px; padding-top: 2px;
            border-top: 1px dashed #000; font-size: 5px; line-height: 1.1;
        }

        @media print {
            @page { size: 5cm 4cm; margin: 0; }
            body * { visibility: hidden; }
            .etiket-container, .etiket-container * { visibility: visible; }
            .etiket-container { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
            .etiket { border: 2px solid #000; page-break-after: always; margin: 0; width: 5cm; height: 4cm; }
        }
    </style>
</head>
<body>
    <?php if ($print_etiket && $data_print): ?>
    <!-- Etiket Print Area -->
    <div class="etiket-container">
        <div class="text-center mb-3 no-print">
            <button onclick="window.print()" class="btn btn-primary btn-lg">
                <i class="fas fa-print me-2"></i>Cetak Etiket
            </button>
            <a href="apoteker.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
        
        <?php while ($obat = mysqli_fetch_assoc($result_obat_print)): ?>
        <div class="etiket">
            <div class="etiket-header">
                <h4>KLINIK SEHAT</h4>
                <p>Jl. Kesehatan No. 123, Karawang</p>
                <p>Telp: (0267) 123456</p>
            </div>
            <div class="etiket-body">
                <div class="etiket-pasien">
                    <p><strong>No. RM:</strong> <?php echo $data_print['no_rm']; ?></p>
                    <p><strong>Nama:</strong> <?php echo $data_print['nama_lengkap']; ?></p>
                    <p><strong>Umur:</strong> <?php echo $data_print['umur']; ?> tahun</p>
                    <p><strong>Tanggal:</strong> <?php echo date('d/m/Y', strtotime($data_print['tgl_resep'])); ?></p>
                </div>
                <div class="etiket-obat">
                    <div class="etiket-obat-nama"><?php echo strtoupper($obat['nama_obat']); ?></div>
                    <div>Jumlah: <?php echo $obat['jumlah']; ?> <?php echo $obat['satuan']; ?></div>
                </div>
                <div class="etiket-aturan">
                    ATURAN PAKAI:<br>
                    <?php echo strtoupper($obat['aturan_pakai']); ?>
                </div>
            </div>
            <div class="etiket-footer">
                <p>Simpan obat di tempat sejuk dan kering</p>
                <p>Jauhkan dari jangkauan anak-anak</p>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <?php else: ?>
    <!-- Normal Apotek View -->
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
                
                <div class="container-fluid px-4">
                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="row px-4">
                    <!-- ======================== -->
                    <!-- Kolom: Resep Menunggu    -->
                    <!-- ======================== -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-hourglass-half me-2"></i>Resep Menunggu
                                <span class="badge bg-light text-dark float-end" id="countBadge"><?php echo mysqli_num_rows($result_menunggu); ?></span>
                            </div>
                            <div class="card-body">
                                <div class="search-box">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Cari nama pasien atau No. RM...">
                                    <i class="fas fa-times clear-search" id="clearBtn"></i>
                                </div>
                                <div class="search-result-info" id="searchInfo">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <span id="searchInfoText"></span>
                                </div>
                                <div style="max-height: 550px; overflow-y: auto;" id="resepContainer">
                                    <?php if (mysqli_num_rows($result_menunggu) > 0): ?>
                                        <?php while ($row = mysqli_fetch_assoc($result_menunggu)): ?>
                                        <div class="resep-card"
                                             data-nama="<?php echo strtolower($row['nama_lengkap']); ?>"
                                             data-norm="<?php echo strtolower(str_replace('-', '', $row['no_rm'])); ?>">
                                            <h6 class="mb-1"><strong><?php echo $row['nama_lengkap']; ?></strong></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-id-card me-1"></i><?php echo $row['no_rm']; ?> |
                                                <i class="fas fa-ticket-alt ms-2 me-1"></i><?php echo $row['no_antrian']; ?>
                                            </small>
                                            <p class="mb-2 mt-2"><small><strong>Diagnosa:</strong> <?php echo $row['diagnosa']; ?></small></p>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-info flex-fill"
                                                        onclick="lihatResep(<?php echo $row['id']; ?>)">
                                                    <i class="fas fa-eye me-1"></i>Lihat
                                                </button>
                                                <button type="button" class="btn btn-sm btn-secondary flex-fill"
                                                        onclick="editJumlahObat(<?php echo $row['id']; ?>, '<?php echo addslashes($row['nama_lengkap']); ?>')">
                                                    <i class="fas fa-edit me-1"></i>Edit
                                                </button>
                                                <form method="POST" style="flex: 1;">
                                                    <input type="hidden" name="resep_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" name="proses_resep" class="btn btn-sm btn-warning w-100">
                                                        <i class="fas fa-print me-1"></i>Proses
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                        <div class="empty-state" id="emptyState" style="display: none;">
                                            <i class="fas fa-inbox"></i>
                                            <p class="mb-0">Tidak ada resep menunggu</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2"></i>
                                            <p>Tidak ada resep menunggu</p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="empty-state" id="noResults" style="display: none;">
                                        <i class="fas fa-search"></i>
                                        <p class="mb-0">Tidak ada hasil pencarian</p>
                                        <small class="text-muted">Coba kata kunci lain</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ======================== -->
                    <!-- Kolom: Sedang Diproses   -->
                    <!-- ======================== -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-warning">
                                <i class="fas fa-spinner me-2"></i>Sedang Diproses
                                <span class="badge bg-light text-dark float-end"><?php echo mysqli_num_rows($result_diproses); ?></span>
                            </div>
                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                <?php if (mysqli_num_rows($result_diproses) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result_diproses)): ?>
                                    <div class="resep-card border-warning">
                                        <h6 class="mb-1"><strong><?php echo $row['nama_lengkap']; ?></strong></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-id-card me-1"></i><?php echo $row['no_rm']; ?> |
                                            <i class="fas fa-ticket-alt ms-2 me-1"></i><?php echo $row['no_antrian']; ?>
                                        </small>
                                        <p class="mb-2 mt-2"><small><strong>Diagnosa:</strong> <?php echo $row['diagnosa']; ?></small></p>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-info flex-fill"
                                                    onclick="lihatResep(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-eye me-1"></i>Lihat
                                            </button>
                                            <button type="button" class="btn btn-sm btn-secondary flex-fill"
                                                    onclick="editJumlahObat(<?php echo $row['id']; ?>, '<?php echo addslashes($row['nama_lengkap']); ?>')">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="resep_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="selesai_resep" class="btn btn-sm btn-success w-100"
                                                        onclick="return confirm('Yakin resep sudah selesai disiapkan?')">
                                                    <i class="fas fa-check me-1"></i>Selesai
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>Tidak ada resep diproses</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ======================== -->
                    <!-- Kolom: Selesai Hari Ini  -->
                    <!-- ======================== -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-success">
                                <i class="fas fa-check-circle me-2"></i>Selesai Hari Ini
                                <span class="badge bg-light text-dark float-end"><?php echo mysqli_num_rows($result_selesai); ?></span>
                            </div>
                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                <?php if (mysqli_num_rows($result_selesai) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result_selesai)): ?>
                                    <div class="resep-card border-success">
                                        <h6 class="mb-1"><strong><?php echo $row['nama_lengkap']; ?></strong></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-id-card me-1"></i><?php echo $row['no_rm']; ?> |
                                            <i class="fas fa-ticket-alt ms-2 me-1"></i><?php echo $row['no_antrian']; ?>
                                        </small>
                                        <p class="mb-2 mt-2"><small><strong>Diagnosa:</strong> <?php echo $row['diagnosa']; ?></small></p>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-info flex-fill"
                                                    onclick="lihatResep(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-eye me-1"></i>Lihat
                                            </button>
                                            <!-- ✅ TOMBOL BATALKAN SELESAI -->
                                            <form method="POST" style="flex: 1;"
                                                  onsubmit="return confirm('Batalkan status selesai? Stok obat akan dikembalikan dan resep masuk ke Sedang Diproses.')">
                                                <input type="hidden" name="resep_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="batalkan_selesai" class="btn btn-sm btn-danger w-100">
                                                    <i class="fas fa-undo me-1"></i>Batalkan
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>Belum ada resep selesai hari ini</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div><!-- end .row -->
            </div><!-- end col-md-10 -->
        </div><!-- end .row -->
    </div><!-- end container-fluid -->

    <!-- ================================ -->
    <!-- Modal: Detail Resep              -->
    <!-- ================================ -->
    <div class="modal fade" id="modalDetailResep" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-prescription me-2"></i>Detail Resep</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailResepContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- ================================ -->
    <!-- Modal: Edit Jumlah Obat ✅ NEW   -->
    <!-- ================================ -->
    <div class="modal fade" id="modalEditJumlah" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header modal-header-edit">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Jumlah Obat
                        <small class="d-block fw-normal mt-1" id="editPasienNama" style="font-size:13px;opacity:.85;"></small>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editJumlahContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Memuat data obat...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    // ===== LIHAT DETAIL RESEP =====
    function lihatResep(resep_id) {
        $.ajax({
            url: 'ajax/get_detail_resep.php',
            method: 'POST',
            data: { resep_id: resep_id },
            success: function(response) {
                $('#detailResepContent').html(response);
                $('#modalDetailResep').modal('show');
            },
            error: function() {
                alert('Gagal memuat detail resep');
            }
        });
    }

    // ===== EDIT JUMLAH OBAT ✅ =====
    function editJumlahObat(resep_id, nama_pasien) {
        $('#editPasienNama').text(nama_pasien);
        $('#editJumlahContent').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Memuat data obat...</p>
            </div>
        `);
        $('#modalEditJumlah').modal('show');

        $.ajax({
            url: 'ajax/get_detail_resep_edit.php',
            method: 'POST',
            data: { resep_id: resep_id },
            success: function(response) {
                $('#editJumlahContent').html(response);
            },
            error: function() {
                $('#editJumlahContent').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>Gagal memuat data obat
                    </div>
                `);
            }
        });
    }

    <?php if ($print_etiket): ?>
    window.onload = function() {
        setTimeout(function() { window.print(); }, 500);
    };
    <?php endif; ?>

    // ===== SEARCH RESEP MENUNGGU =====
    $(document).ready(function() {
        const searchInput  = $('#searchInput');
        const clearBtn     = $('#clearBtn');
        const searchInfo   = $('#searchInfo');
        const searchInfoText = $('#searchInfoText');
        const noResults    = $('#noResults');
        const emptyState   = $('#emptyState');
        const countBadge   = $('#countBadge');
        const resepContainer = $('#resepContainer');
        const resepCards   = resepContainer.find('.resep-card');
        const totalCount   = resepCards.length;

        function searchResep() {
            const searchTerm = searchInput.val().toLowerCase().trim();
            const searchTermClean = searchTerm.replace(/[-\s]/g, '');
            let visibleCount = 0;

            if (searchTerm === '') {
                resepCards.removeClass('hidden');
                clearBtn.removeClass('show');
                searchInfo.removeClass('show');
                noResults.hide();
                if (totalCount === 0) { emptyState.show(); } else { emptyState.hide(); }
                visibleCount = totalCount;
            } else {
                clearBtn.addClass('show');
                emptyState.hide();
                resepCards.each(function() {
                    const card = $(this);
                    const nama = card.data('nama');
                    const norm = card.data('norm');
                    if (nama.includes(searchTerm) || norm.includes(searchTermClean)) {
                        card.removeClass('hidden'); visibleCount++;
                    } else {
                        card.addClass('hidden');
                    }
                });
                if (visibleCount > 0) {
                    const resultText = visibleCount === totalCount
                        ? `Menampilkan semua ${totalCount} resep`
                        : `Ditemukan ${visibleCount} dari ${totalCount} resep`;
                    searchInfoText.text(resultText);
                    searchInfo.addClass('show');
                    noResults.hide();
                } else {
                    searchInfo.removeClass('show');
                    noResults.show();
                }
            }
            countBadge.text(visibleCount);
        }

        let searchTimeout;
        searchInput.on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(searchResep, 300);
        });
        clearBtn.on('click', function() { searchInput.val(''); searchResep(); searchInput.focus(); });
        searchInput.on('keypress', function(e) {
            if (e.which === 13) { e.preventDefault(); clearTimeout(searchTimeout); searchResep(); }
        });
        $(document).on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') { e.preventDefault(); searchInput.focus().select(); }
            if (e.key === 'Escape' && searchInput.is(':focus')) {
                if (searchInput.val() !== '') { searchInput.val(''); searchResep(); } else { searchInput.blur(); }
            }
        });
    });
    </script>
</body>
</html>