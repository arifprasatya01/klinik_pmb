<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'gudang' && $_SESSION['role'] != 'petugas' && $_SESSION['role'] != 'admin')) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Tambah Supplier
if (isset($_POST['tambah_supplier'])) {
    $kode = "SUP" . str_pad(rand(1, 9999), 3, '0', STR_PAD_LEFT);
    $nama = sanitize($_POST['nama_supplier']);
    $alamat = sanitize($_POST['alamat']);
    $telp = sanitize($_POST['no_telepon']);
    $email = sanitize($_POST['email']);
    $kontak = sanitize($_POST['kontak_person']);
    $query = "INSERT INTO supplier (kode_supplier, nama_supplier, alamat, no_telepon, email, kontak_person) 
              VALUES ('$kode', '$nama', '$alamat', '$telp', '$email', '$kontak')";
    if (mysqli_query($conn, $query)) {
        $success = "Supplier berhasil ditambahkan dengan kode: $kode";
    } else {
        $error = "Gagal menambahkan supplier: " . mysqli_error($conn);
    }
}

// Tambah Obat
if (isset($_POST['tambah_obat'])) {
    $kode_obat = generateKodeObat();
    $nama_obat = sanitize($_POST['nama_obat']);
    $satuan = sanitize($_POST['satuan']);
    $harga_beli = sanitize($_POST['harga_beli']);
    $harga_jual = sanitize($_POST['harga_jual']);
    $stok = sanitize($_POST['stok']);
    $stok_minimum = sanitize($_POST['stok_minimum']);
    $query = "INSERT INTO obat (kode_obat, nama_obat, satuan, harga_beli, harga_jual, stok, stok_minimum) 
              VALUES ('$kode_obat', '$nama_obat', '$satuan', '$harga_beli', '$harga_jual', '$stok', '$stok_minimum')";
    if (mysqli_query($conn, $query)) {
        $obat_id = mysqli_insert_id($conn);
        mysqli_query($conn, "INSERT INTO stok_log (obat_id, user_id, jenis, jumlah, keterangan) 
                      VALUES ('$obat_id', '{$_SESSION['user_id']}', 'masuk', '$stok', 'Stok awal')");
        $success = "Obat berhasil ditambahkan dengan kode: $kode_obat";
    } else {
        $error = "Gagal menambahkan obat: " . mysqli_error($conn);
    }
}

// Update Stok Obat Manual
if (isset($_POST['update_stok'])) {
    $obat_id = sanitize($_POST['obat_id']);
    $jenis = sanitize($_POST['jenis']);
    $jumlah = sanitize($_POST['jumlah']);
    $keterangan = sanitize($_POST['keterangan']);
    $op = ($jenis == 'masuk') ? '+' : '-';
    $query = "UPDATE obat SET stok = stok $op $jumlah WHERE id = '$obat_id'";
    if (mysqli_query($conn, $query)) {
        mysqli_query($conn, "INSERT INTO stok_log (obat_id, user_id, jenis, jumlah, keterangan) 
                      VALUES ('$obat_id', '{$_SESSION['user_id']}', '$jenis', '$jumlah', '$keterangan')");
        $success = "Stok berhasil diupdate";
    } else {
        $error = "Gagal update stok: " . mysqli_error($conn);
    }
}

// Edit Obat
if (isset($_POST['edit_obat'])) {
    $obat_id = sanitize($_POST['obat_id']);
    $nama_obat = sanitize($_POST['nama_obat']);
    $satuan = sanitize($_POST['satuan']);
    $harga_beli = sanitize($_POST['harga_beli']);
    $harga_jual = sanitize($_POST['harga_jual']);
    $stok_minimum = sanitize($_POST['stok_minimum']);
    $query = "UPDATE obat SET nama_obat='$nama_obat', satuan='$satuan', 
              harga_beli='$harga_beli', harga_jual='$harga_jual', 
              stok_minimum='$stok_minimum' WHERE id='$obat_id'";
    if (mysqli_query($conn, $query)) {
        $success = "Obat berhasil diupdate";
    } else {
        $error = "Gagal update obat: " . mysqli_error($conn);
    }
}

// Tambah BHP
if (isset($_POST['tambah_bhp'])) {
    $kode_bhp    = "BHP" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $nama        = sanitize($_POST['nama_bhp']);
    $satuan      = sanitize($_POST['satuan_bhp']);
    $stok        = sanitize($_POST['stok_awal_bhp']);
    $harga       = sanitize($_POST['harga_bhp']);
    $stok_min    = sanitize($_POST['stok_minimum_bhp'] ?? 5);
    $query = "INSERT INTO master_bhp (kode_bhp, nama_bhp, satuan, stok, harga, stok_minimum, aktif) 
              VALUES ('$kode_bhp', '$nama', '$satuan', '$stok', '$harga', '$stok_min', 1)";
    if (mysqli_query($conn, $query)) {
        $success = "BHP berhasil ditambahkan dengan kode: $kode_bhp";
    } else {
        $error = "Gagal: " . mysqli_error($conn);
    }
}

// Edit BHP
if (isset($_POST['edit_bhp'])) {
    $bhp_id   = sanitize($_POST['bhp_id']);
    $nama     = sanitize($_POST['nama_bhp']);
    $satuan   = sanitize($_POST['satuan_bhp']);
    $harga    = sanitize($_POST['harga_bhp']);
    $stok_min = sanitize($_POST['stok_minimum_bhp'] ?? 5);
    $query = "UPDATE master_bhp SET nama_bhp='$nama', satuan='$satuan', harga='$harga', stok_minimum='$stok_min' WHERE id='$bhp_id'";
    if (mysqli_query($conn, $query)) {
        $success = "BHP berhasil diupdate";
    } else {
        $error = "Gagal: " . mysqli_error($conn);
    }
}

// Update Stok BHP Manual
if (isset($_POST['update_stok_bhp'])) {
    $bhp_id = sanitize($_POST['bhp_id']);
    $jenis  = sanitize($_POST['jenis']);
    $jumlah = sanitize($_POST['jumlah']);
    $op     = ($jenis == 'masuk') ? '+' : '-';
    $query = "UPDATE master_bhp SET stok = stok $op $jumlah WHERE id='$bhp_id'";
    if (mysqli_query($conn, $query)) {
        $success = "Stok BHP berhasil diupdate";
    } else {
        $error = "Gagal: " . mysqli_error($conn);
    }
}

// Penerimaan Barang Obat
if (isset($_POST['simpan_pembelian'])) {
    $no_pembelian    = "PB" . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    $supplier_id     = sanitize($_POST['supplier_id']);
    $tgl_pembelian   = sanitize($_POST['tgl_pembelian']);
    $jatuh_tempo_hari = sanitize($_POST['jatuh_tempo_hari']);
    $tgl_jatuh_tempo = sanitize($_POST['tgl_jatuh_tempo']);
    $no_faktur       = sanitize($_POST['no_faktur']);
    $keterangan      = sanitize($_POST['keterangan']);
    $ppn_persen      = sanitize($_POST['ppn_persen']);
    $obat_ids        = $_POST['obat_id'];
    $jumlahs         = $_POST['jumlah'];
    $hnas            = $_POST['hna'];
    $diskon_persens  = $_POST['diskon_persen'];
    $ppn_item_persens = $_POST['ppn_item_persen'];
    $harga_belis     = $_POST['harga_beli'];
    $harga_juals     = $_POST['harga_jual'];
    $batch_numbers   = $_POST['batch_number'];
    $expired_dates   = $_POST['expired_date'];
    $status_ed       = '0';

    $total_pembelian = 0;
    for ($i = 0; $i < count($obat_ids); $i++) {
        if (!empty($obat_ids[$i])) {
            $total_pembelian += $jumlahs[$i] * $harga_belis[$i];
        }
    }
    $nilai_ppn       = ($total_pembelian * $ppn_persen) / 100;
    $total_dengan_ppn = $total_pembelian + $nilai_ppn;

    $query = "INSERT INTO pembelian (no_pembelian, supplier_id, tgl_pembelian, jatuh_tempo_hari, tgl_jatuh_tempo, no_faktur, total_pembelian, ppn_persen, nilai_ppn, total_dengan_ppn, sisa_pembayaran, user_id, keterangan) 
              VALUES ('$no_pembelian','$supplier_id','$tgl_pembelian','$jatuh_tempo_hari','$tgl_jatuh_tempo','$no_faktur','$total_pembelian','$ppn_persen','$nilai_ppn','$total_dengan_ppn','$total_dengan_ppn','{$_SESSION['user_id']}','$keterangan')";

    if (mysqli_query($conn, $query)) {
        $pembelian_id = mysqli_insert_id($conn);
        for ($i = 0; $i < count($obat_ids); $i++) {
            if (!empty($obat_ids[$i])) {
                $obat_id      = sanitize($obat_ids[$i]);
                $jumlah       = sanitize($jumlahs[$i]);
                $hna          = sanitize($hnas[$i]);
                $diskon_persen = sanitize($diskon_persens[$i]);
                $ppn_item_persen = sanitize($ppn_item_persens[$i]);
                $harga_beli   = sanitize($harga_belis[$i]);
                $harga_jual   = sanitize($harga_juals[$i]);
                $batch_number = sanitize($batch_numbers[$i]);
                $expired_date = sanitize($expired_dates[$i]);
                $subtotal     = $jumlah * $harga_beli;
                mysqli_query($conn, "INSERT INTO detail_pembelian (pembelian_id, obat_id, jumlah, hna, diskon, harga_beli, expired_date, subtotal, status_expired) 
                                VALUES ('$pembelian_id','$obat_id','$jumlah','$hna','$diskon_persen','$harga_beli','$expired_date','$subtotal','$status_ed')");
                mysqli_query($conn, "UPDATE obat SET stok=stok+$jumlah, harga_beli='$harga_beli', harga_jual='$harga_jual' WHERE id='$obat_id'");
                $batch_info = !empty($batch_number) ? " - Batch: $batch_number" : "";
                mysqli_query($conn, "INSERT INTO stok_log (obat_id, user_id, jenis, jumlah, keterangan) 
                              VALUES ('$obat_id','{$_SESSION['user_id']}','masuk','$jumlah','Pembelian #$no_pembelian$batch_info - Exp: $expired_date')");
            }
        }
        $success = "Penerimaan barang berhasil dengan No. $no_pembelian. Total: " . formatRupiah($total_dengan_ppn) . ". Jatuh Tempo: " . date('d/m/Y', strtotime($tgl_jatuh_tempo)) . " ($jatuh_tempo_hari hari)";
    } else {
        $error = "Gagal menyimpan penerimaan barang: " . mysqli_error($conn);
    }
}

// Penerimaan BHP
if (isset($_POST['simpan_pembelian_bhp'])) {
    $no_pembelian  = "PB-BHP" . date('Ymd') . str_pad(rand(1,999), 3, '0', STR_PAD_LEFT);
    $supplier_id   = sanitize($_POST['supplier_id_bhp']);
    $tgl_pembelian = sanitize($_POST['tgl_pembelian_bhp']);
    $tgl_jatuh     = sanitize($_POST['tgl_jatuh_tempo_bhp']);
    $hari_jatuh    = sanitize($_POST['jatuh_tempo_hari_bhp']);
    $no_faktur     = sanitize($_POST['no_faktur_bhp']);
    $keterangan    = sanitize($_POST['keterangan_bhp']);
    $ppn_persen    = sanitize($_POST['ppn_persen_bhp']);
    $bhp_ids       = $_POST['bhp_id_item'];
    $jumlahs       = $_POST['jumlah_bhp'];
    $harga_belis   = $_POST['harga_beli_bhp'];

    $total = 0;
    for ($i = 0; $i < count($bhp_ids); $i++) {
        if (!empty($bhp_ids[$i])) { $total += $jumlahs[$i] * $harga_belis[$i]; }
    }
    $nilai_ppn        = ($total * $ppn_persen) / 100;
    $total_dengan_ppn = $total + $nilai_ppn;

    $query = "INSERT INTO pembelian_bhp (no_pembelian, supplier_id, tgl_pembelian, tgl_jatuh_tempo, jatuh_tempo_hari, no_faktur, total_pembelian, ppn_persen, nilai_ppn, total_dengan_ppn, sisa_pembayaran, user_id, keterangan)
              VALUES ('$no_pembelian','$supplier_id','$tgl_pembelian','$tgl_jatuh','$hari_jatuh','$no_faktur','$total','$ppn_persen','$nilai_ppn','$total_dengan_ppn','$total_dengan_ppn','{$_SESSION['user_id']}','$keterangan')";
    if (mysqli_query($conn, $query)) {
        $pb_id = mysqli_insert_id($conn);
        for ($i = 0; $i < count($bhp_ids); $i++) {
            if (!empty($bhp_ids[$i])) {
                $bid = sanitize($bhp_ids[$i]);
                $jml = sanitize($jumlahs[$i]);
                $harga = sanitize($harga_belis[$i]);
                $subtotal = $jml * $harga;
                mysqli_query($conn, "INSERT INTO detail_pembelian_bhp (pembelian_bhp_id, bhp_id, jumlah, harga_beli, subtotal) VALUES ('$pb_id','$bid','$jml','$harga','$subtotal')");
                mysqli_query($conn, "UPDATE master_bhp SET stok=stok+$jml, harga='$harga' WHERE id='$bid'");
            }
        }
        $success = "Penerimaan BHP berhasil dengan No. $no_pembelian";
    } else {
        $error = "Gagal: " . mysqli_error($conn);
    }
}

// ===== QUERIES =====
$query_obat = "SELECT * FROM obat WHERE status='aktif'ORDER BY nama_obat";
$result_obat = mysqli_query($conn, $query_obat);

$result_obat_js = mysqli_query($conn, "SELECT id, kode_obat, nama_obat, satuan, harga_beli, harga_jual, stok FROM obat ORDER BY nama_obat");
$obat_array = [];
while ($row = mysqli_fetch_assoc($result_obat_js)) {
    $obat_array[] = [
        'id' => $row['id'], 'kode_obat' => $row['kode_obat'],
        'nama_obat' => htmlspecialchars($row['nama_obat'], ENT_QUOTES, 'UTF-8'),
        'satuan' => htmlspecialchars($row['satuan'], ENT_QUOTES, 'UTF-8'),
        'harga_beli' => $row['harga_beli'], 'harga_jual' => $row['harga_jual'], 'stok' => $row['stok']
    ];
}

$query_supplier = "SELECT * FROM supplier WHERE status = 'aktif' ORDER BY nama_supplier";
$result_supplier = mysqli_query($conn, $query_supplier);

$query_minimum = "SELECT * FROM obat WHERE stok <= stok_minimum AND status='aktif' ORDER BY stok ASC";
$result_minimum = mysqli_query($conn, $query_minimum);

$query_pembelian = "SELECT p.*, s.nama_supplier FROM pembelian p JOIN supplier s ON p.supplier_id = s.id ORDER BY p.created_at DESC LIMIT 20";
$result_pembelian = mysqli_query($conn, $query_pembelian);

$query_pembelian_bhp = "SELECT pb.*, s.nama_supplier FROM pembelian_bhp pb JOIN supplier s ON pb.supplier_id = s.id ORDER BY pb.created_at DESC LIMIT 20";
$result_pembelian_bhp = mysqli_query($conn, $query_pembelian_bhp);

$result_master_bhp = mysqli_query($conn, "SELECT * FROM master_bhp WHERE aktif=1 ORDER BY nama_bhp");
$bhp_array = [];
while ($row = mysqli_fetch_assoc($result_master_bhp)) { $bhp_array[] = $row; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gudang Obat - Healoka</title>
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link rel="shortcut icon" type="image/png" href="asset/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <?php include 'sidebar_style.php'; ?>
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
        .container-fluid { padding: 0; margin: 0; }
        .row { margin: 0; display: flex; }
        .col-md-2 { flex: 0 0 250px; max-width: 250px; }
        .col-md-10 { flex: 1; margin-left: 250px; padding: 0; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card-header { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; border-radius: 15px 15px 0 0 !important; padding: 15px 20px; font-weight: 600; }
        .select2-container, .select2-container--open, .select2-dropdown { z-index: 9999 !important; }
        .select2-search__field { width: 100% !important; }
        .table thead th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; font-weight: 600; font-size: 0.9em; }
        .badge { padding: 6px 12px; border-radius: 8px; font-weight: 500; }
        .btn { border-radius: 8px; padding: 8px 16px; font-weight: 500; }
        .form-control, .form-select { border-radius: 8px; border: 1px solid #e0e0e0; }
        .modal-content { border-radius: 15px; }
        .modal-header { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; border-radius: 15px 15px 0 0; }
        .stok-low { background-color: #fee2e2; }
        .nav-tabs .nav-link { border-radius: 10px 10px 0 0; margin-right: 5px; }
        .nav-tabs .nav-link.active { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; border: none; }
        .harga-beli-input { background-color: #fff3cd !important; font-weight: bold !important; color: #856404 !important; }
        .setelah-diskon-input { background-color: #e9ecef !important; }
        .pagination .page-link { border-radius: 8px; margin: 0 2px; color: var(--primary-color); }
        .pagination .page-item.active .page-link { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); border-color: var(--primary-color); }
        .pagination-info { font-size: 0.85em; color: #6c757d; }
        #modalTambahObatCepat { z-index: 1065 !important; }
        #modalTambahObatCepat .modal-dialog { margin-top: 80px; }
        @media print { .no-print { display: none !important; } body * { visibility: hidden; } #printArea, #printArea * { visibility: visible; } #printArea { position: absolute; left: 0; top: 0; width: 100%; } }
    </style>
</head>
<body>
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

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" id="gudangTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#obat" type="button"><i class="fas fa-pills me-2"></i>Master Data</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#supplier" type="button"><i class="fas fa-truck me-2"></i>Supplier</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pembelian" type="button"><i class="fas fa-shopping-cart me-2"></i>Pembelian Obat</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#bhptab" type="button"><i class="fas fa-box-open me-2"></i>Pembelian BHP</button>
    </li>
</ul>

<div class="tab-content">

<!-- ======== TAB MASTER DATA ======== -->
<div class="tab-pane fade show active" id="obat" role="tabpanel">
    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#subtab-obat" type="button"><i class="fas fa-pills me-2"></i>Data Obat</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#subtab-bhp" type="button"><i class="fas fa-box-open me-2"></i>Data BHP</button></li>
    </ul>
    <div class="tab-content">

        <!-- Sub-Tab Obat -->
        <div class="tab-pane fade show active" id="subtab-obat">
            <div class="row mb-3">
                <div class="col-md-4">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahObat"><i class="fas fa-plus me-2"></i>Tambah Obat Baru</button>
                </div>
                <div class="col-md-4">
                    <div class="input-group"><span class="input-group-text bg-white"><i class="fas fa-search"></i></span><input type="text" class="form-control" id="searchObat" placeholder="Cari nama obat..."></div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-pills me-2"></i>Daftar Obat</div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead><tr><th>No</th><th>Nama Obat</th><th>Satuan</th><th>Harga Beli</th><th>Harga Jual</th><th>Stok</th><th>Stok Min</th><th>Aksi</th></tr></thead>
                                    <tbody>
                                    <?php if (mysqli_num_rows($result_obat) > 0): $no = 0; while ($row = mysqli_fetch_assoc($result_obat)): $no++; ?>
                                    <tr class="<?php echo ($row['stok'] <= $row['stok_minimum']) ? 'stok-low' : ''; ?>">
                                        <td><?php echo $no; ?></td>
                                        <td><?php echo $row['nama_obat']; ?></td>
                                        <td><?php echo $row['satuan']; ?></td>
                                        <td><small><?php echo formatRupiah($row['harga_beli']); ?></small></td>
                                        <td><strong><?php echo formatRupiah($row['harga_jual']); ?></strong></td>
                                        <td><span class="badge <?php echo ($row['stok'] <= $row['stok_minimum']) ? 'bg-danger' : 'bg-success'; ?>"><?php echo $row['stok']; ?></span></td>
                                        <td><?php echo $row['stok_minimum']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick='editObat(<?php echo json_encode($row); ?>)'><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-info" onclick="updateStok(<?php echo $row['id']; ?>,'<?php echo addslashes($row['nama_obat']); ?>')"><i class="fas fa-boxes"></i></button>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Belum ada data obat</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="pagination-info" id="paginationInfo"></div>
                                    <nav><ul class="pagination pagination-sm mb-0" id="paginationObat"></ul></nav>
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="mb-0 text-muted small text-nowrap">Tampilkan:</label>
                                        <select class="form-select form-select-sm" id="perPageObat" style="width:75px;">
                                            <option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option><option value="99999">Semua</option>
                                        </select>
                                        <span class="text-muted small text-nowrap">/ hal</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-danger"><i class="fas fa-exclamation-triangle me-2"></i>Stok Minimum</div>
                        <div class="card-body" style="max-height:400px;overflow-y:auto;">
                            <?php if (mysqli_num_rows($result_minimum) > 0): ?>
                            <div class="list-group">
                                <?php while ($row = mysqli_fetch_assoc($result_minimum)): ?>
                                <div class="list-group-item"><div class="d-flex justify-content-between align-items-center"><div><strong><?php echo $row['nama_obat']; ?></strong><br><small class="text-muted"><?php echo $row['kode_obat']; ?></small></div><span class="badge bg-danger"><?php echo $row['stok']; ?></span></div></div>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-center text-muted py-4"><i class="fas fa-check-circle fa-2x mb-2 d-block"></i>Semua stok aman</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- end subtab-obat -->

        <!-- Sub-Tab BHP -->
        <div class="tab-pane fade" id="subtab-bhp">
            <div class="row mb-3">
                <div class="col-md-4">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahBHP"><i class="fas fa-plus me-2"></i>Tambah BHP Baru</button>
                </div>
                <div class="col-md-4">
                    <div class="input-group"><span class="input-group-text bg-white"><i class="fas fa-search"></i></span><input type="text" class="form-control" id="searchBHP" placeholder="Cari nama BHP..."></div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-box-open me-2"></i>Daftar BHP</div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm" id="tabelBHP">
                                    <thead><tr><th>No</th><th>Kode</th><th>Nama BHP</th><th>Satuan</th><th>Harga</th><th>Stok</th><th>Stok Min</th><th>Aksi</th></tr></thead>
                                    <tbody>
                                    <?php
                                    $q_bhp = mysqli_query($conn, "SELECT * FROM master_bhp WHERE aktif=1 ORDER BY nama_bhp");
                                    if (mysqli_num_rows($q_bhp) > 0): $nb = 0; while ($row = mysqli_fetch_assoc($q_bhp)): $nb++;
                                        $stok_min_bhp = isset($row['stok_minimum']) ? $row['stok_minimum'] : 5;
                                    ?>
                                    <tr class="<?php echo ($row['stok'] <= $stok_min_bhp) ? 'stok-low' : ''; ?>">
                                        <td><?php echo $nb; ?></td>
                                        <td><small class="text-muted"><?php echo $row['kode_bhp']; ?></small></td>
                                        <td><?php echo $row['nama_bhp']; ?></td>
                                        <td><?php echo $row['satuan']; ?></td>
                                        <td><strong><?php echo formatRupiah($row['harga']); ?></strong></td>
                                        <td><span class="badge <?php echo ($row['stok'] <= $stok_min_bhp) ? 'bg-danger' : 'bg-success'; ?>"><?php echo $row['stok']; ?></span></td>
                                        <td><?php echo $stok_min_bhp; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick='editBHP(<?php echo json_encode($row); ?>)'><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-info" onclick="updateStokBHP(<?php echo $row['id']; ?>,'<?php echo addslashes($row['nama_bhp']); ?>')"><i class="fas fa-boxes"></i></button>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Belum ada data BHP</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-danger"><i class="fas fa-exclamation-triangle me-2"></i>Stok BHP Minimum</div>
                        <div class="card-body" style="max-height:400px;overflow-y:auto;">
                            <?php $q_bmin = mysqli_query($conn, "SELECT * FROM master_bhp WHERE aktif=1 AND stok <= stok_minimum ORDER BY stok ASC"); ?>
                            <?php if (mysqli_num_rows($q_bmin) > 0): ?>
                            <div class="list-group">
                                <?php while ($row = mysqli_fetch_assoc($q_bmin)): ?>
                                <div class="list-group-item"><div class="d-flex justify-content-between align-items-center"><div><strong><?php echo $row['nama_bhp']; ?></strong><br><small class="text-muted"><?php echo $row['satuan']; ?></small></div><span class="badge bg-danger"><?php echo $row['stok']; ?></span></div></div>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <div class="text-center text-muted py-4"><i class="fas fa-check-circle fa-2x mb-2 d-block"></i>Semua stok BHP aman</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- end subtab-bhp -->

    </div><!-- end sub tab-content -->
</div><!-- end tab obat -->

<!-- ======== TAB SUPPLIER ======== -->
<div class="tab-pane fade" id="supplier" role="tabpanel">
    <div class="row mb-3"><div class="col-md-6">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahSupplier"><i class="fas fa-plus me-2"></i>Tambah Supplier</button>
    </div></div>
    <div class="card">
        <div class="card-header"><i class="fas fa-truck me-2"></i>Daftar Supplier</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Kode</th><th>Nama Supplier</th><th>Alamat</th><th>Telepon</th><th>Email</th><th>Kontak Person</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php $ra = mysqli_query($conn, "SELECT * FROM supplier ORDER BY nama_supplier");
                    if (mysqli_num_rows($ra) > 0): while ($row = mysqli_fetch_assoc($ra)): ?>
                    <tr>
                        <td><strong><?php echo $row['kode_supplier']; ?></strong></td>
                        <td><?php echo $row['nama_supplier']; ?></td>
                        <td><small><?php echo $row['alamat']; ?></small></td>
                        <td><?php echo $row['no_telepon']; ?></td>
                        <td><small><?php echo $row['email']; ?></small></td>
                        <td><?php echo $row['kontak_person']; ?></td>
                        <td><span class="badge <?php echo ($row['status']=='aktif') ? 'bg-success' : 'bg-secondary'; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Belum ada data supplier</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div><!-- end tab supplier -->

<!-- ======== TAB PEMBELIAN OBAT ======== -->
<div class="tab-pane fade" id="pembelian" role="tabpanel">
    <div class="row mb-3"><div class="col-md-6">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPenerimaanBarang"><i class="fas fa-truck-loading me-2"></i>Penerimaan Barang Baru</button>
    </div></div>
    <div class="card">
        <div class="card-header"><i class="fas fa-history me-2"></i>Riwayat Penerimaan Barang</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead><tr><th>No. Pembelian</th><th>Tanggal</th><th>Supplier</th><th>Total</th><th>Dibayar</th><th>Sisa</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php if (mysqli_num_rows($result_pembelian) > 0): while ($row = mysqli_fetch_assoc($result_pembelian)): ?>
                    <tr>
                        <td><strong><?php echo $row['no_pembelian']; ?></strong></td>
                        <td><?php echo date('d/m/Y', strtotime($row['tgl_pembelian'])); ?></td>
                        <td><?php echo $row['nama_supplier']; ?></td>
                        <td><?php echo formatRupiah($row['total_pembelian']); ?></td>
                        <td><?php echo formatRupiah($row['jumlah_dibayar']); ?></td>
                        <td><?php echo formatRupiah($row['sisa_pembayaran']); ?></td>
                        <td><?php $bc=['lunas'=>'bg-success','dibayar_sebagian'=>'bg-warning','belum_bayar'=>'bg-danger']; ?>
                            <span class="badge <?php echo $bc[$row['status_pembayaran']] ?? 'bg-secondary'; ?>"><?php echo str_replace('_',' ',ucwords($row['status_pembayaran'],'_')); ?></span>
                        </td>
                        <td><button class="btn btn-sm btn-info" onclick="lihatDetailPembelian(<?php echo $row['id']; ?>)"><i class="fas fa-eye"></i></button></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Belum ada riwayat</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div><!-- end tab pembelian -->

<!-- ======== TAB PEMBELIAN BHP ======== -->
<div class="tab-pane fade" id="bhptab" role="tabpanel">
    <div class="row mb-3"><div class="col-md-6">
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalPenerimaanBHP"><i class="fas fa-truck-loading me-2"></i>Penerimaan BHP Baru</button>
    </div></div>
    <div class="card">
        <div class="card-header"><i class="fas fa-history me-2"></i>Riwayat Penerimaan BHP</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead><tr><th>No. Pembelian</th><th>Tanggal</th><th>Supplier</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (mysqli_num_rows($result_pembelian_bhp) > 0): while ($row = mysqli_fetch_assoc($result_pembelian_bhp)): ?>
                    <tr>
                        <td><strong><?php echo $row['no_pembelian']; ?></strong></td>
                        <td><?php echo date('d/m/Y', strtotime($row['tgl_pembelian'])); ?></td>
                        <td><?php echo $row['nama_supplier']; ?></td>
                        <td><?php echo formatRupiah($row['total_dengan_ppn']); ?></td>
                        <td><span class="badge <?php echo $row['status_pembayaran']=='lunas' ? 'bg-success' : 'bg-danger'; ?>"><?php echo ucfirst($row['status_pembayaran']); ?></span></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada riwayat</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div><!-- end tab bhp -->

</div><!-- end tab-content -->
</div><!-- end container -->
</div><!-- end row -->
</div><!-- end container-fluid -->

<!-- ===== MODALS ===== -->

<!-- Modal Tambah Supplier -->
<div class="modal fade" id="modalTambahSupplier" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus me-2"></i>Tambah Supplier</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<form method="POST"><div class="modal-body">
    <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Nama Supplier <span class="text-danger">*</span></label><input type="text" class="form-control" name="nama_supplier" required></div><div class="col-md-6 mb-3"><label class="form-label">No. Telepon <span class="text-danger">*</span></label><input type="text" class="form-control" name="no_telepon" required></div></div>
    <div class="mb-3"><label class="form-label">Alamat <span class="text-danger">*</span></label><textarea class="form-control" name="alamat" rows="2" required></textarea></div>
    <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div><div class="col-md-6 mb-3"><label class="form-label">Kontak Person</label><input type="text" class="form-control" name="kontak_person"></div></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="tambah_supplier" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div></form>
</div></div></div>

<!-- Modal Tambah Obat -->
<div class="modal fade" id="modalTambahObat" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus me-2"></i>Tambah Obat Baru</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<form method="POST"><div class="modal-body">
    <div class="mb-3"><label class="form-label">Nama Obat <span class="text-danger">*</span></label><input type="text" class="form-control" name="nama_obat" required></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Satuan <span class="text-danger">*</span></label><select class="form-select" name="satuan" required><option value="">-- Pilih --</option><option>Strip</option><option>Box</option><option>Botol</option><option>Tube</option><option>Ampul</option><option>Vial</option><option>Kaplet</option><option>Tablet</option><option>CC</option></select></div>
        <div class="col-md-6 mb-3"><label class="form-label">Stok Awal <span class="text-danger">*</span></label><input type="number" class="form-control" name="stok" min="0" required></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Harga Beli <span class="text-danger">*</span></label><input type="number" class="form-control" name="harga_beli" min="0" step="0.01" required></div>
        <div class="col-md-6 mb-3"><label class="form-label">Harga Jual <span class="text-danger">*</span></label><input type="number" class="form-control" name="harga_jual" min="0" step="0.01" required></div>
    </div>
    <div class="mb-3"><label class="form-label">Stok Minimum <span class="text-danger">*</span></label><input type="number" class="form-control" name="stok_minimum" min="0" required></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="tambah_obat" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div></form>
</div></div></div>

<!-- Modal Edit Obat -->
<div class="modal fade" id="modalEditObat" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Obat</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<form method="POST"><div class="modal-body">
    <input type="hidden" name="obat_id" id="edit_obat_id">
    <div class="mb-3"><label class="form-label">Nama Obat <span class="text-danger">*</span></label><input type="text" class="form-control" name="nama_obat" id="edit_nama_obat" required></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Satuan <span class="text-danger">*</span></label><select class="form-select" name="satuan" id="edit_satuan" required><option>Strip</option><option>Box</option><option>Botol</option><option>Tube</option><option>Ampul</option><option>Vial</option><option>Kaplet</option><option>Tablet</option></select></div>
        <div class="col-md-6 mb-3"><label class="form-label">Stok Minimum <span class="text-danger">*</span></label><input type="number" class="form-control" name="stok_minimum" id="edit_stok_minimum" min="0" required></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Harga Beli <span class="text-danger">*</span></label><input type="number" class="form-control" name="harga_beli" id="edit_harga_beli" min="0" step="0.01" required></div>
        <div class="col-md-6 mb-3"><label class="form-label">Harga Jual <span class="text-danger">*</span></label><input type="number" class="form-control" name="harga_jual" id="edit_harga_jual" min="0" step="0.01" required></div>
    </div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="edit_obat" class="btn btn-warning"><i class="fas fa-save me-2"></i>Update</button></div></form>
</div></div></div>

<!-- Modal Update Stok Obat -->
<div class="modal fade" id="modalUpdateStok" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-boxes me-2"></i>Update Stok Manual</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<form method="POST"><div class="modal-body">
    <input type="hidden" name="obat_id" id="stok_obat_id">
    <div class="alert alert-info"><strong>Obat:</strong> <span id="stok_nama_obat"></span></div>
    <div class="mb-3"><label class="form-label">Jenis <span class="text-danger">*</span></label><select class="form-select" name="jenis" required><option value="masuk">Stok Masuk</option><option value="keluar">Stok Keluar</option></select></div>
    <div class="mb-3"><label class="form-label">Jumlah <span class="text-danger">*</span></label><input type="number" class="form-control" name="jumlah" min="1" required></div>
    <div class="mb-3"><label class="form-label">Keterangan <span class="text-danger">*</span></label><textarea class="form-control" name="keterangan" rows="2" required placeholder="Alasan update stok..."></textarea></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="update_stok" class="btn btn-info"><i class="fas fa-save me-2"></i>Update Stok</button></div></form>
</div></div></div>

<!-- Modal Tambah BHP -->
<div class="modal fade" id="modalTambahBHP" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus me-2"></i>Tambah BHP Baru</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<form method="POST"><div class="modal-body">
    <div class="mb-3"><label class="form-label">Nama BHP <span class="text-danger">*</span></label><input type="text" class="form-control" name="nama_bhp" required></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Satuan <span class="text-danger">*</span></label><input type="text" class="form-control" name="satuan_bhp" required placeholder="Pcs, Box, Roll..."></div>
        <div class="col-md-6 mb-3"><label class="form-label">Stok Awal</label><input type="number" class="form-control" name="stok_awal_bhp" min="0" value="0"></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Harga</label><input type="number" class="form-control" name="harga_bhp" min="0" value="0"></div>
        <div class="col-md-6 mb-3"><label class="form-label">Stok Minimum</label><input type="number" class="form-control" name="stok_minimum_bhp" min="0" value="5"></div>
    </div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="tambah_bhp" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div></form>
</div></div></div>

<!-- Modal Edit BHP -->
<div class="modal fade" id="modalEditBHP" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit BHP</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<form method="POST"><div class="modal-body">
    <input type="hidden" name="bhp_id" id="edit_bhp_id">
    <div class="mb-3"><label class="form-label">Kode BHP</label><input type="text" class="form-control" id="edit_kode_bhp" readonly style="background:#e9ecef;"></div>
    <div class="mb-3"><label class="form-label">Nama BHP <span class="text-danger">*</span></label><input type="text" class="form-control" name="nama_bhp" id="edit_nama_bhp" required></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Satuan <span class="text-danger">*</span></label><input type="text" class="form-control" name="satuan_bhp" id="edit_satuan_bhp" required></div>
        <div class="col-md-6 mb-3"><label class="form-label">Harga</label><input type="number" class="form-control" name="harga_bhp" id="edit_harga_bhp" min="0"></div>
    </div>
    <div class="mb-3"><label class="form-label">Stok Minimum</label><input type="number" class="form-control" name="stok_minimum_bhp" id="edit_stok_minimum_bhp" min="0" value="5"></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="edit_bhp" class="btn btn-warning"><i class="fas fa-save me-2"></i>Update</button></div></form>
</div></div></div>

<!-- Modal Update Stok BHP -->
<div class="modal fade" id="modalUpdateStokBHP" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-boxes me-2"></i>Update Stok BHP</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<form method="POST"><div class="modal-body">
    <input type="hidden" name="bhp_id" id="stok_bhp_id">
    <div class="alert alert-info"><strong>BHP:</strong> <span id="stok_nama_bhp"></span></div>
    <div class="mb-3"><label class="form-label">Jenis <span class="text-danger">*</span></label><select class="form-select" name="jenis" required><option value="masuk">Stok Masuk</option><option value="keluar">Stok Keluar</option></select></div>
    <div class="mb-3"><label class="form-label">Jumlah <span class="text-danger">*</span></label><input type="number" class="form-control" name="jumlah" min="1" required></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="update_stok_bhp" class="btn btn-info"><i class="fas fa-save me-2"></i>Update Stok</button></div></form>
</div></div></div>

<!-- Modal Detail Pembelian -->
<div class="modal fade" id="modalDetailPembelian" tabindex="-1">
<div class="modal-dialog modal-xl"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-eye me-2"></i>Detail Pembelian</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="detailPembelianContent"></div>
</div></div></div>

<!-- Modal Penerimaan Barang -->
<div class="modal fade" id="modalPenerimaanBarang" tabindex="-1">
<div class="modal-dialog modal-xl" style="max-width:95%;">
<div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-truck-loading me-2"></i>Penerimaan Barang Baru</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<form method="POST" id="formPenerimaanBarang"><div class="modal-body">
    <div class="row mb-3">
        <div class="col-md-3"><label class="form-label">Tanggal Pembelian <span class="text-danger">*</span></label><input type="date" class="form-control" name="tgl_pembelian" id="tgl_pembelian" value="<?php echo date('Y-m-d'); ?>" onchange="updateJatuhTempoFromHari()" required></div>
        <div class="col-md-3"><label class="form-label">Supplier <span class="text-danger">*</span></label><select class="form-select select2" name="supplier_id" id="supplier_id" required><option value="">-- Pilih Supplier --</option><?php mysqli_data_seek($result_supplier,0); while($row=mysqli_fetch_assoc($result_supplier)): ?><option value="<?php echo $row['id']; ?>"><?php echo $row['nama_supplier']; ?></option><?php endwhile; ?></select></div>
        <div class="col-md-2"><label class="form-label">Jatuh Tempo (Hari) <span class="text-danger">*</span></label><input type="number" class="form-control" name="jatuh_tempo_hari" id="jatuh_tempo_hari" value="30" min="0" onchange="updateJatuhTempoFromHari()" required></div>
        <div class="col-md-2"><label class="form-label">Tanggal Jatuh Tempo <span class="text-danger">*</span></label><input type="date" class="form-control" name="tgl_jatuh_tempo" id="tgl_jatuh_tempo" onchange="updateHariFromTanggal()" required style="background-color:#fff3cd;font-weight:bold;"></div>
        <div class="col-md-2"><label class="form-label">PPN Total (%) <span class="text-danger">*</span></label><input type="number" class="form-control" name="ppn_persen" id="ppn_total" value="11" min="0" max="100" step="0.01" onchange="hitungTotal()" required></div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6"><label class="form-label">No. Faktur/Invoice Supplier</label><input type="text" class="form-control" name="no_faktur" placeholder="Nomor faktur dari supplier"></div>
        <div class="col-md-6"><label class="form-label">Keterangan</label><textarea class="form-control" name="keterangan" rows="1" placeholder="Catatan pembelian (opsional)"></textarea></div>
    </div>
    <hr>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6><i class="fas fa-list me-2"></i>Daftar Barang</h6>
        <div>
            <button type="button" class="btn btn-sm btn-warning me-2" onclick="bukaModalObatCepat()"><i class="fas fa-capsules me-1"></i>Obat Baru</button>
            <button type="button" class="btn btn-sm btn-primary" onclick="tambahBarangBaru()"><i class="fas fa-plus me-1"></i>Tambah Barang</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead class="table-light"><tr><th style="width:18%;">Obat</th><th style="width:6%;">Jumlah</th><th style="width:10%;">HNA</th><th style="width:7%;">Diskon (%)</th><th style="width:10%;">Setelah Diskon</th><th style="width:7%;">PPN (%)</th><th style="width:10%;">Harga Beli</th><th style="width:10%;">Harga Jual</th><th style="width:9%;">Batch/Lot</th><th style="width:9%;">Expired Date</th><th style="width:4%;">Aksi</th></tr></thead>
            <tbody id="containerBarang"></tbody>
        </table>
    </div>
    <hr>
    <div class="row">
        <div class="col-md-8"><div class="alert alert-info mb-0"><div class="row"><div class="col-md-6"><i class="fas fa-calendar-alt me-2"></i><strong>Pembayaran Jatuh Tempo:</strong><br><span id="displayJatuhTempoText" class="fs-5 text-primary">-</span></div><div class="col-md-6"><i class="fas fa-hourglass-half me-2"></i><strong>Sisa Waktu:</strong><br><span id="displaySisaHari" class="fs-6">-</span></div></div></div></div>
        <div class="col-md-4">
            <table class="table table-sm mb-0">
                <tr><td><strong>Subtotal HNA:</strong></td><td class="text-end" id="displaySubtotal">Rp 0</td></tr>
                <tr><td><strong>Total Diskon:</strong></td><td class="text-end text-success" id="displayTotalDiskon">Rp 0</td></tr>
                <tr><td><strong>Setelah Diskon:</strong></td><td class="text-end" id="displaySetelahDiskon">Rp 0</td></tr>
                <tr><td><strong>Total PPN:</strong></td><td class="text-end" id="displayPPN">Rp 0</td></tr>
                <tr class="table-success"><td><strong>GRAND TOTAL:</strong></td><td class="text-end"><strong id="displayTotal">Rp 0</strong></td></tr>
            </table>
        </div>
    </div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="simpan_pembelian" class="btn btn-success"><i class="fas fa-save me-2"></i>Simpan Penerimaan</button></div>
</form></div></div></div>

<!-- Modal Tambah Obat Cepat -->
<div class="modal fade" id="modalTambahObatCepat" tabindex="-1" data-bs-backdrop="false" style="z-index:1065;">
<div class="modal-dialog modal-sm"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-capsules me-2"></i>Tambah Obat Baru</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="alert alert-info py-2 px-3" style="font-size:13px;"><i class="fas fa-info-circle me-1"></i>Harga beli & jual akan diisi dari form penerimaan barang.</div>
    <div class="mb-3"><label class="form-label">Nama Obat <span class="text-danger">*</span></label><input type="text" class="form-control" id="quickNamaObat" placeholder="Contoh: Paracetamol 500mg"><div id="quickObatError" class="text-danger mt-1" style="font-size:13px;display:none;"></div></div>
    <div class="mb-3"><label class="form-label">Satuan <span class="text-danger">*</span></label><select class="form-select" id="quickSatuan"><option>Strip</option><option>Box</option><option>Botol</option><option>Tube</option><option>Ampul</option><option>Vial</option><option>Kaplet</option><option>Tablet</option></select></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-success btn-sm" id="btnSimpanObatCepat"><i class="fas fa-save me-1"></i>Simpan & Pilih</button></div>
</div></div></div>

<!-- Modal Penerimaan BHP -->
<div class="modal fade" id="modalPenerimaanBHP" tabindex="-1">
<div class="modal-dialog modal-xl" style="max-width:95%;">
<div class="modal-content">
<div class="modal-header"><h5 class="modal-title"><i class="fas fa-box-open me-2"></i>Penerimaan BHP Baru</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<form method="POST" id="formPenerimaanBHP"><div class="modal-body">
    <div class="row mb-3">
        <div class="col-md-3"><label class="form-label">Tanggal <span class="text-danger">*</span></label><input type="date" class="form-control" name="tgl_pembelian_bhp" value="<?php echo date('Y-m-d'); ?>" required></div>
        <div class="col-md-3"><label class="form-label">Supplier <span class="text-danger">*</span></label><select class="form-select select2" name="supplier_id_bhp" required><option value="">-- Pilih Supplier --</option><?php $qs=mysqli_query($conn,"SELECT * FROM supplier WHERE status='aktif' ORDER BY nama_supplier"); while($s=mysqli_fetch_assoc($qs)): ?><option value="<?php echo $s['id']; ?>"><?php echo $s['nama_supplier']; ?></option><?php endwhile; ?></select></div>
        <div class="col-md-2"><label class="form-label">Jatuh Tempo (Hari)</label><input type="number" class="form-control" name="jatuh_tempo_hari_bhp" value="30" min="0" id="hari_jt_bhp" onchange="updateJTBHP()"></div>
        <div class="col-md-2"><label class="form-label">Tgl Jatuh Tempo</label><input type="date" class="form-control" name="tgl_jatuh_tempo_bhp" id="tgl_jt_bhp"></div>
        <div class="col-md-2"><label class="form-label">PPN (%)</label><input type="number" class="form-control" name="ppn_persen_bhp" value="11" min="0" max="100" id="ppn_bhp" onchange="hitungTotalBHP()"></div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6"><label class="form-label">No. Faktur</label><input type="text" class="form-control" name="no_faktur_bhp"></div>
        <div class="col-md-6"><label class="form-label">Keterangan</label><input type="text" class="form-control" name="keterangan_bhp"></div>
    </div>
    <hr>
    <div class="d-flex justify-content-between align-items-center mb-3"><h6><i class="fas fa-list me-2"></i>Daftar BHP</h6><button type="button" class="btn btn-sm btn-warning" onclick="tambahBHPItem()"><i class="fas fa-plus me-1"></i>Tambah BHP</button></div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead class="table-light"><tr><th style="width:35%;">Nama BHP</th><th style="width:15%;">Jumlah</th><th style="width:20%;">Harga Beli</th><th style="width:20%;">Subtotal</th><th style="width:10%;">Aksi</th></tr></thead>
            <tbody id="containerBHPItem"></tbody>
        </table>
    </div>
    <hr>
    <div class="row justify-content-end"><div class="col-md-4">
        <table class="table table-sm mb-0">
            <tr><td><strong>Subtotal:</strong></td><td class="text-end" id="bhpSubtotal">Rp 0</td></tr>
            <tr><td><strong>PPN:</strong></td><td class="text-end" id="bhpPPN">Rp 0</td></tr>
            <tr class="table-success"><td><strong>GRAND TOTAL:</strong></td><td class="text-end"><strong id="bhpTotal">Rp 0</strong></td></tr>
        </table>
    </div></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="simpan_pembelian_bhp" class="btn btn-warning"><i class="fas fa-save me-2"></i>Simpan Penerimaan BHP</button></div>
</form></div></div></div>

<!-- ===== SCRIPTS ===== -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
var obatData = <?php echo json_encode($obat_array, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
var bhpDataGudang = <?php echo json_encode($bhp_array); ?>;
var barangCounter = 0, bhpItemCounter = 0, activeBarangCounter = null, isUpdating = false;

// ===== SELECT2 =====
function initSelect2(selector, dropdownParent) {
    var $el = (typeof selector==='string') ? $(selector) : selector;
    if ($el.hasClass('select2-hidden-accessible')) { $el.select2('destroy'); }
    $el.select2({ theme:'bootstrap-5', dropdownParent: dropdownParent ? $(dropdownParent) : $('body'), width:'100%', placeholder:'-- Pilih --', allowClear:true });
    $el.on('select2:open', function() { setTimeout(function(){ var f=document.querySelector('.select2-container--open .select2-search__field'); if(f) f.focus(); },50); });
}

// ===== JATUH TEMPO =====
function updateJatuhTempoFromHari() {
    if (isUpdating) return; isUpdating = true;
    var tgl=$('#tgl_pembelian').val(), hari=parseInt($('#jatuh_tempo_hari').val())||0;
    if (tgl && hari>=0) {
        var d=new Date(tgl); d.setDate(d.getDate()+hari);
        var jt=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
        $('#tgl_jatuh_tempo').val(jt); updateDisplayJT(d, hari);
    }
    isUpdating = false;
}
function updateHariFromTanggal() {
    if (isUpdating) return; isUpdating = true;
    var tgl=$('#tgl_pembelian').val(), jt=$('#tgl_jatuh_tempo').val();
    if (tgl && jt) {
        var diff=Math.ceil((new Date(jt)-new Date(tgl))/(1000*60*60*24));
        if (diff>=0) { $('#jatuh_tempo_hari').val(diff); updateDisplayJT(new Date(jt),diff); }
        else { alert('Tanggal jatuh tempo tidak boleh lebih awal!'); updateJatuhTempoFromHari(); }
    }
    isUpdating = false;
}
function updateDisplayJT(d, hari) {
    $('#displayJatuhTempoText').html('<strong class="text-primary">'+d.toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'})+'</strong><br><small class="text-muted">('+hari+' hari dari tanggal pembelian)</small>');
    var today=new Date(); today.setHours(0,0,0,0); var dt=new Date(d); dt.setHours(0,0,0,0);
    var sisa=Math.ceil((dt-today)/(1000*60*60*24));
    var cls=sisa<0?'bg-danger':(sisa<=7?'bg-warning text-dark':(sisa<=30?'bg-info':'bg-success'));
    var txt=sisa<0?'Terlambat '+Math.abs(sisa)+' hari':(sisa===0?'Jatuh tempo hari ini':sisa+' hari lagi');
    $('#displaySisaHari').html('<span class="badge '+cls+' fs-6">'+txt+'</span>');
}

// ===== PENERIMAAN BARANG =====
function tambahBarangBaru() {
    barangCounter++;
    var optHTML='<option value="">-- Pilih Obat --</option>';
    for (var i=0;i<obatData.length;i++) {
        var o=obatData[i];
        optHTML+='<option value="'+o.id+'" data-harga-beli="'+o.harga_beli+'" data-harga-jual="'+o.harga_jual+'">'+o.nama_obat+' - '+o.satuan+'</option>';
    }
    var c=barangCounter;
    var html='<tr class="barang-item" id="barang_'+c+'">'
        +'<td><select class="form-select form-select-sm select2-barang" name="obat_id[]" required>'+optHTML+'</select></td>'
        +'<td><input type="number" class="form-control form-control-sm text-center jumlah-input" name="jumlah[]" min="1" value="1" onchange="hitungHargaBeli('+c+')" required></td>'
        +'<td><input type="number" class="form-control form-control-sm hna-input" name="hna[]" min="0" step="0.01" value="0" onchange="hitungHargaBeli('+c+')" required></td>'
        +'<td><input type="number" class="form-control form-control-sm diskon-input" name="diskon_persen[]" min="0" max="100" step="0.01" value="0" onchange="hitungHargaBeli('+c+')"></td>'
        +'<td><input type="number" class="form-control form-control-sm setelah-diskon-input" readonly style="background:#e9ecef;" value="0"></td>'
        +'<td><input type="number" class="form-control form-control-sm ppn-input" name="ppn_item_persen[]" min="0" max="100" step="0.01" value="11" onchange="hitungHargaBeli('+c+')"></td>'
        +'<td><input type="number" class="form-control form-control-sm harga-beli-input" name="harga_beli[]" readonly value="0" required></td>'
        +'<td><input type="number" class="form-control form-control-sm harga-jual-input" name="harga_jual[]" min="0" step="0.01" value="0" required></td>'
        +'<td><input type="text" class="form-control form-control-sm" name="batch_number[]" placeholder="Batch/Lot"></td>'
        +'<td><input type="date" class="form-control form-control-sm" name="expired_date[]" required></td>'
        +'<td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="hapusBarang('+c+')"><i class="fas fa-trash"></i></button></td>'
        +'</tr>';
    $('#containerBarang').append(html);
    var $sel=$('#barang_'+c+' .select2-barang');
    initSelect2($sel,'#modalPenerimaanBarang');
    $sel.on('select2:select',function(){updateHargaBeli(c);});
}
function updateHargaBeli(c) {
    var row=$('#barang_'+c), sel=row.find('select[name="obat_id[]"]');
    var hb=sel.find(':selected').data('harga-beli'), hj=sel.find(':selected').data('harga-jual');
    if (hb) { row.find('.hna-input').val(hb); hitungHargaBeli(c); }
    if (hj) { row.find('.harga-jual-input').val(hj); }
}
function hitungHargaBeli(c) {
    var row=$('#barang_'+c);
    var hna=parseFloat(row.find('.hna-input').val())||0;
    var disk=parseFloat(row.find('.diskon-input').val())||0;
    var ppn=parseFloat(row.find('.ppn-input').val())||0;
    var sd=hna-(hna*disk/100), hb=sd+(sd*ppn/100);
    row.find('.setelah-diskon-input').val(sd.toFixed(2));
    row.find('.harga-beli-input').val(hb.toFixed(2));
    if (parseFloat(row.find('.harga-jual-input').val())===0) row.find('.harga-jual-input').val((hb*1.2).toFixed(2));
    hitungTotal();
}
function hapusBarang(c) { if(confirm('Hapus item ini?')){ $('#barang_'+c).remove(); hitungTotal(); } }
function hitungTotal() {
    var shna=0,tdisk=0,ssd=0,tppn=0,grand=0;
    $('.barang-item').each(function(){
        var jml=parseFloat($(this).find('.jumlah-input').val())||0;
        var hna=parseFloat($(this).find('.hna-input').val())||0;
        var disk=parseFloat($(this).find('.diskon-input').val())||0;
        var sd=parseFloat($(this).find('.setelah-diskon-input').val())||0;
        var hb=parseFloat($(this).find('.harga-beli-input').val())||0;
        shna+=jml*hna; tdisk+=jml*(hna*disk/100); ssd+=jml*sd; tppn+=jml*(hb-sd); grand+=jml*hb;
    });
    $('#displaySubtotal').text('Rp '+shna.toLocaleString('id-ID',{minimumFractionDigits:2}));
    $('#displayTotalDiskon').text('Rp '+tdisk.toLocaleString('id-ID',{minimumFractionDigits:2}));
    $('#displaySetelahDiskon').text('Rp '+ssd.toLocaleString('id-ID',{minimumFractionDigits:2}));
    $('#displayPPN').text('Rp '+tppn.toLocaleString('id-ID',{minimumFractionDigits:2}));
    $('#displayTotal').text('Rp '+grand.toLocaleString('id-ID',{minimumFractionDigits:2}));
}

// ===== PENERIMAAN BHP =====
function tambahBHPItem() {
    bhpItemCounter++;
    var c=bhpItemCounter;
    var opts='<option value="">-- Pilih BHP --</option>';
    for (var i=0;i<bhpDataGudang.length;i++) {
        var b=bhpDataGudang[i];
        opts+='<option value="'+b.id+'" data-harga="'+b.harga+'">'+b.nama_bhp+' ('+b.satuan+') - Stok: '+b.stok+'</option>';
    }
    var html='<tr id="bhpitem_'+c+'">'
        +'<td><select class="form-select form-select-sm bhp-gudang-select" name="bhp_id_item[]">'+opts+'</select></td>'
        +'<td><input type="number" class="form-control form-control-sm bhp-jml" name="jumlah_bhp[]" min="1" value="1" onchange="hitungTotalBHP()"></td>'
        +'<td><input type="number" class="form-control form-control-sm bhp-harga" name="harga_beli_bhp[]" min="0" value="0" onchange="hitungTotalBHP()"></td>'
        +'<td><input type="number" class="form-control form-control-sm bhp-subtotal" readonly style="background:#e9ecef;" value="0"></td>'
        +'<td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="hapusBHPItem('+c+')"><i class="fas fa-trash"></i></button></td>'
        +'</tr>';
    $('#containerBHPItem').append(html);
    var $s=$('#bhpitem_'+c+' .bhp-gudang-select');
    initSelect2($s,'#modalPenerimaanBHP');
    $s.on('select2:select',function(){autohargaBHP(c);});
}
function autohargaBHP(c) {
    var row=$('#bhpitem_'+c);
    var h=row.find('.bhp-gudang-select option:selected').data('harga')||0;
    row.find('.bhp-harga').val(h); hitungTotalBHP();
}
function hapusBHPItem(c) { $('#bhpitem_'+c).remove(); hitungTotalBHP(); }
function hitungTotalBHP() {
    var sub=0;
    $('#containerBHPItem tr').each(function(){
        var jml=parseFloat($(this).find('.bhp-jml').val())||0;
        var h=parseFloat($(this).find('.bhp-harga').val())||0;
        var s=jml*h; $(this).find('.bhp-subtotal').val(s.toFixed(2)); sub+=s;
    });
    var ppn=(sub*(parseFloat($('#ppn_bhp').val())||0))/100, total=sub+ppn;
    $('#bhpSubtotal').text('Rp '+sub.toLocaleString('id-ID'));
    $('#bhpPPN').text('Rp '+ppn.toLocaleString('id-ID'));
    $('#bhpTotal').text('Rp '+total.toLocaleString('id-ID'));
}
function updateJTBHP() {
    var tgl=$('input[name="tgl_pembelian_bhp"]').val(), hari=parseInt($('#hari_jt_bhp').val())||0;
    if (tgl) { var d=new Date(tgl); d.setDate(d.getDate()+hari); $('#tgl_jt_bhp').val(d.toISOString().split('T')[0]); }
}

// ===== TAMBAH OBAT CEPAT =====
function bukaModalObatCepat() {
    var last=null;
    $('#containerBarang .barang-item').each(function(){ last=parseInt(this.id.replace('barang_','')); });
    activeBarangCounter=last;
    $('#quickNamaObat').val(''); $('#quickObatError').hide();
    new bootstrap.Modal(document.getElementById('modalTambahObatCepat'),{backdrop:false,keyboard:true}).show();
    setTimeout(function(){$('#quickNamaObat').focus();},300);
}
function refreshAllObatDropdowns(list) {
    obatData=list;
    var html='<option value="">-- Pilih Obat --</option>';
    for (var i=0;i<list.length;i++) {
        var o=list[i]; html+='<option value="'+o.id+'" data-harga-beli="'+o.harga_beli+'" data-harga-jual="'+o.harga_jual+'">'+o.nama_obat+' - '+o.satuan+'</option>';
    }
    $('#containerBarang .select2-barang').each(function(){
        var $s=$(this), cv=$s.val();
        if ($s.hasClass('select2-hidden-accessible')) $s.select2('destroy');
        $s.html(html); initSelect2($s,'#modalPenerimaanBarang');
        if (cv) $s.val(cv).trigger('change');
    });
}
$('#btnSimpanObatCepat').on('click',function(){
    var nama=$('#quickNamaObat').val().trim(), satuan=$('#quickSatuan').val();
    if (!nama) { $('#quickObatError').text('Nama obat tidak boleh kosong.').show(); $('#quickNamaObat').focus(); return; }
    $('#quickObatError').hide();
    var $btn=$(this); $btn.prop('disabled',true).html('<i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...');
    $.ajax({
        url:'ajax/tambah_obat_quick.php', type:'POST', data:{nama_obat:nama,satuan:satuan}, dataType:'json',
        success:function(res){
            if (res.success) {
                refreshAllObatDropdowns(res.obat_list);
                if (activeBarangCounter!==null) {
                    var $sel=$('#barang_'+activeBarangCounter+' .select2-barang');
                    if ($sel.length) { $sel.val(res.new_id).trigger('change'); updateHargaBeli(activeBarangCounter); }
                }
                $('#quickNamaObat').val('');
                bootstrap.Modal.getInstance(document.getElementById('modalTambahObatCepat')).hide();
                var t=$('<div class="alert alert-success alert-dismissible fade show position-fixed" style="top:80px;right:20px;z-index:9999;min-width:280px;"><i class="fas fa-check-circle me-2"></i><strong>'+res.nama_obat+'</strong> berhasil ditambahkan!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                $('body').append(t); setTimeout(function(){t.alert('close');},3000);
            } else { $('#quickObatError').text(res.message||'Gagal menyimpan.').show(); }
        },
        error:function(){ $('#quickObatError').text('Terjadi kesalahan. Coba lagi.').show(); },
        complete:function(){ $btn.prop('disabled',false).html('<i class="fas fa-save me-1"></i>Simpan & Pilih'); }
    });
});
$('#quickNamaObat').on('keydown',function(e){ if(e.key==='Enter') $('#btnSimpanObatCepat').click(); });

// ===== EDIT & STOK =====
function editObat(data) {
    $('#edit_obat_id').val(data.id); $('#edit_nama_obat').val(data.nama_obat);
    $('#edit_satuan').val(data.satuan); $('#edit_harga_beli').val(data.harga_beli);
    $('#edit_harga_jual').val(data.harga_jual); $('#edit_stok_minimum').val(data.stok_minimum);
    new bootstrap.Modal(document.getElementById('modalEditObat')).show();
}
function updateStok(id, nama) {
    $('#stok_obat_id').val(id); $('#stok_nama_obat').text(nama);
    new bootstrap.Modal(document.getElementById('modalUpdateStok')).show();
}
function editBHP(data) {
    $('#edit_bhp_id').val(data.id);
    $('#edit_kode_bhp').val(data.kode_bhp);
    $('#edit_nama_bhp').val(data.nama_bhp);
    $('#edit_satuan_bhp').val(data.satuan);
    $('#edit_harga_bhp').val(data.harga);
    $('#edit_stok_minimum_bhp').val(data.stok_minimum || 5);
    new bootstrap.Modal(document.getElementById('modalEditBHP')).show();
}
function updateStokBHP(id, nama) {
    $('#stok_bhp_id').val(id); $('#stok_nama_bhp').text(nama);
    new bootstrap.Modal(document.getElementById('modalUpdateStokBHP')).show();
}
function lihatDetailPembelian(id) {
    var modal=new bootstrap.Modal(document.getElementById('modalDetailPembelian')); modal.show();
    $('#detailPembelianContent').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Memuat data...</p></div>');
    $.ajax({
        url:'ajax/get_detail_pembelian.php', type:'GET', data:{id:id}, dataType:'html',
        success:function(res){ $('#detailPembelianContent').html(res); },
        error:function(xhr){ $('#detailPembelianContent').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Gagal memuat data. Error '+xhr.status+'</div><div class="text-end"><button class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>'); }
    });
}



// ===== PAGINATION OBAT =====
var currentPage=1, perPage=25, allRows=[];
function initPagination() { allRows=$('#subtab-obat .table tbody tr').toArray(); renderPage(1); }
function renderPage(page) {
    currentPage=page;
    var kw=$('#searchObat').val().toLowerCase();
    var filtered=allRows.filter(function(r){ return kw==='' || $(r).text().toLowerCase().indexOf(kw)>-1; });
    var total=filtered.length, totalPages=Math.ceil(total/perPage)||1;
    if (currentPage>totalPages) currentPage=1;
    var start=(currentPage-1)*perPage, end=start+perPage;
    $(allRows).hide(); filtered.slice(start,end).forEach(function(r){$(r).show();});
    $('#paginationInfo').text('Menampilkan '+(total===0?0:start+1)+' - '+Math.min(end,total)+' dari '+total+' obat');
    var ul=$('#paginationObat'); ul.empty();
    ul.append('<li class="page-item '+(currentPage===1?'disabled':'')+'"><a class="page-link" href="#" onclick="renderPage('+(currentPage-1)+');return false;">&laquo;</a></li>');
    var sp=Math.max(1,currentPage-2), ep=Math.min(totalPages,sp+4);
    if (ep-sp<4) sp=Math.max(1,ep-4);
    if (sp>1){ ul.append('<li class="page-item"><a class="page-link" href="#" onclick="renderPage(1);return false;">1</a></li>'); if(sp>2) ul.append('<li class="page-item disabled"><span class="page-link">...</span></li>'); }
    for (var p=sp;p<=ep;p++) ul.append('<li class="page-item '+(p===currentPage?'active':'')+'"><a class="page-link" href="#" onclick="renderPage('+p+');return false;">'+p+'</a></li>');
    if (ep<totalPages){ if(ep<totalPages-1) ul.append('<li class="page-item disabled"><span class="page-link">...</span></li>'); ul.append('<li class="page-item"><a class="page-link" href="#" onclick="renderPage('+totalPages+');return false;">'+totalPages+'</a></li>'); }
    ul.append('<li class="page-item '+(currentPage===totalPages?'disabled':'')+'"><a class="page-link" href="#" onclick="renderPage('+(currentPage+1)+');return false;">&raquo;</a></li>');
}
// ===== SEARCH MASTER DATA =====
$('#searchObat').on('input', function() {
    var val = $(this).val().toLowerCase();
    if ($('#subtab-bhp').hasClass('show active')) {
        $('#tabelBHP tbody tr').each(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
        });
    } else {
        allRows = $('#subtab-obat .table tbody tr').toArray();
        renderPage(1);
    }
});

// Reset search saat pindah sub-tab
$('button[data-bs-target="#subtab-obat"], button[data-bs-target="#subtab-bhp"]').on('shown.bs.tab', function() {
    $('#searchObat').val('').trigger('input');
    allRows = $('#subtab-obat .table tbody tr').toArray();
    renderPage(1);
});

$('#perPageObat').on('change',function(){ perPage=parseInt($(this).val()); renderPage(1); });

// ===== MODAL EVENTS =====
$('#modalPenerimaanBarang').on('shown.bs.modal',function(){
    initSelect2('#modalPenerimaanBarang #supplier_id','#modalPenerimaanBarang');
    if ($('#containerBarang .barang-item').length===0) tambahBarangBaru();
    updateJatuhTempoFromHari();
});
$('#modalPenerimaanBarang').on('hidden.bs.modal',function(){
    barangCounter=0; $('#containerBarang').empty(); $('#formPenerimaanBarang')[0].reset();
    $('#displayJatuhTempoText').text('-'); $('#displaySisaHari').text('-'); hitungTotal();
});
$('#modalPenerimaanBHP').on('shown.bs.modal',function(){
    initSelect2('#modalPenerimaanBHP select[name="supplier_id_bhp"]','#modalPenerimaanBHP');
    if ($('#containerBHPItem tr').length===0) tambahBHPItem();
    updateJTBHP();
});
$('#modalPenerimaanBHP').on('hidden.bs.modal',function(){
    bhpItemCounter=0; $('#containerBHPItem').empty(); $('#formPenerimaanBHP')[0].reset(); hitungTotalBHP();
});

$(document).ready(function(){ initPagination(); });
</script>
</body>
</html>