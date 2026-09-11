<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'pendaftaran' && $_SESSION['role'] != 'admin' && $_SESSION['role'] != 'petugas')) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Tambah Pasien Baru
if (isset($_POST['tambah_pasien'])) {
    $no_rm = generateNoRM();
    $nama = sanitize($_POST['nama_lengkap']);
    $tgl_lahir = sanitize($_POST['tgl_lahir']);
    $nik = sanitize($_POST['nik']); // PINDAHKAN KE ATAS
    $jk = sanitize($_POST['jenis_kelamin']);
    $jenis_pasien = sanitize($_POST['jenis_pasien']);
    $alamat = sanitize($_POST['alamat']);
    $telp = sanitize($_POST['no_telepon']);
    
    // Data tambahan sesuai jenis pasien
    $no_bpjs = '';
    $nama_asuransi = '';
    $no_polis = '';
    $nama_kk = sanitize($_POST['kepala_keluarga'] ?? '');
    
    if ($jenis_pasien == 'bpjs') {
        $no_bpjs = sanitize($_POST['no_bpjs']);
    } elseif ($jenis_pasien == 'asuransi') {
        $nama_asuransi = sanitize($_POST['nama_asuransi']);
        $no_polis = sanitize($_POST['no_polis']);
    }
    
    // PERBAIKI QUERY - Sesuaikan urutan kolom dan value
$query = "INSERT INTO pasien (no_rm, nama_lengkap, tgl_lahir, nik, jenis_kelamin, jenis_pasien, no_bpjs, nama_asuransi, no_polis, alamat, kepala_keluarga, no_telepon) 
          VALUES ('$no_rm', '$nama', '$tgl_lahir', '$nik', '$jk', '$jenis_pasien', '$no_bpjs', '$nama_asuransi', '$no_polis', '$alamat', '$nama_kk', '$telp')";
    
    if (mysqli_query($conn, $query)) {
        $success = "Pasien berhasil ditambahkan dengan No. RM: $no_rm dan NIK: $nik";
    } else {
        $error = "Gagal menambahkan pasien: " . mysqli_error($conn);
    }
}

// Edit Pasien
if (isset($_POST['edit_pasien'])) {
    $edit_id      = sanitize($_POST['edit_pasien_id']);
    $nama         = sanitize($_POST['edit_nama_lengkap']);
    $tgl_lahir    = sanitize($_POST['edit_tgl_lahir']);
    $nik          = sanitize($_POST['edit_nik']);
    $jk           = sanitize($_POST['edit_jenis_kelamin']);
    $jenis_pasien = sanitize($_POST['edit_jenis_pasien']);
    $alamat       = sanitize($_POST['edit_alamat']);
    $telp         = sanitize($_POST['edit_no_telepon']);
    $kk           = sanitize($_POST['edit_kepala_keluarga'] ?? '');
    $no_bpjs      = '';
    $nama_asuransi = '';
    $no_polis     = '';

    if ($jenis_pasien == 'bpjs') {
        $no_bpjs = sanitize($_POST['edit_no_bpjs']);
    } elseif ($jenis_pasien == 'asuransi') {
        $nama_asuransi = sanitize($_POST['edit_nama_asuransi']);
        $no_polis      = sanitize($_POST['edit_no_polis']);
    }

    $query = "UPDATE pasien SET 
                nama_lengkap='$nama', tgl_lahir='$tgl_lahir', nik='$nik',
                jenis_kelamin='$jk', jenis_pasien='$jenis_pasien',
                no_bpjs='$no_bpjs', nama_asuransi='$nama_asuransi', no_polis='$no_polis',
                alamat='$alamat', kepala_keluarga='$kk', no_telepon='$telp'
              WHERE id='$edit_id'";

    if (mysqli_query($conn, $query)) {
        $success = "Data pasien berhasil diperbarui.";
    } else {
        $error = "Gagal memperbarui data: " . mysqli_error($conn);
    }
}

// Daftar Pasien
if (isset($_POST['daftar_pasien'])) {
    $pasien_id = sanitize($_POST['pasien_id']);
    $keluhan = sanitize($_POST['keluhan']);
    $pemeriksaan_fisik = sanitize($_POST['pem_fisik']);
    $poli = sanitize($_POST['poli']);
    $no_antrian = generateNoAntrian($poli);
    $petugas_id = $_SESSION['user_id'];

    $poli_label = ($poli == 'kebidanan') ? 'Poli Kebidanan' : 'Poli Umum';

    $query = "INSERT INTO pendaftaran (no_antrian, pasien_id, keluhan, pemeriksaan_fisik, poli, petugas_id) 
              VALUES ('$no_antrian', '$pasien_id', '$keluhan', '$pemeriksaan_fisik', '$poli', '$petugas_id')";
    
    if (mysqli_query($conn, $query)) {
        $success = "Pendaftaran berhasil ke $poli_label dengan No. Antrian: <strong>$no_antrian</strong>";
    } else {
        $error = "Gagal mendaftarkan pasien: " . mysqli_error($conn);
    }
}

// Batal Berkunjung
if (isset($_POST['batal_berkunjung'])) {
    $pendaftaran_id = sanitize($_POST['pendaftaran_id']);
    $password = sanitize($_POST['password']);
    $alasan = sanitize($_POST['alasan_batal']);
    
    // Verifikasi password user
    $user_id = $_SESSION['user_id'];
    $query_user = "SELECT password FROM users WHERE id = '$user_id'";
    $result_user = mysqli_query($conn, $query_user);
    $user = mysqli_fetch_assoc($result_user);
    
    if (isMD5Hash($user['password'])) {
        $valid = (md5($password) == $user['password']);
    } else {
        $valid = verifyPasswordBerlapis($password, $user['password']);
    }

    if ($valid) {
        $query = "UPDATE pendaftaran SET status = 'dibatalkan', keterangan = '$alasan' WHERE id = '$pendaftaran_id'";
        
        if (mysqli_query($conn, $query)) {
            $success = "Pendaftaran berhasil dibatalkan.";
        } else {
            $error = "Gagal membatalkan pendaftaran: " . mysqli_error($conn);
        }
    } else {
        $error = "Password salah! Tidak dapat membatalkan pendaftaran.";
    }
}

// Get Data Pasien
$query_pasien = "SELECT * FROM pasien ORDER BY id DESC";
// $query_pasien = "SELECT * FROM pasien ORDER BY id ASC LIMIT 10";
$result_pasien = mysqli_query($conn, $query_pasien);


// Get Data Pendaftaran Hari Ini
$query_pendaftaran = "SELECT p.*, ps.nama_lengkap, ps.no_rm, ps.jenis_pasien, ps.nik, ps.kepala_keluarga, ps.alamat
                      FROM pendaftaran p 
                      JOIN pasien ps ON p.pasien_id = ps.id 
                      WHERE DATE(p.tgl_daftar) = CURDATE() 
                      ORDER BY p.tgl_daftar DESC";
$result_pendaftaran = mysqli_query($conn, $query_pendaftaran);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran - Healoka</title>
        <!-- Favicon -->
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
        
        .container-fluid {
            padding: 0;
            margin: 0;
        }
        
        .row {
            margin: 0;
            display: flex;
        }
        
        .col-md-2 {
            flex: 0 0 250px;
            max-width: 250px;
        }
        
        .col-md-10 {
            flex: 1;
            margin-left: 250px;
            padding: 0;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
        
        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
        }
        
        .page-title {
            color: #1f2937;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .modal-content {
            border-radius: 15px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .jenis-pasien-group {
            display: none;
        }
        
        .jenis-pasien-group.active {
            display: block;
        }
        
        .badge-umum {
            background-color: #10b981;
        }
        
        .badge-bpjs {
            background-color: #3b82f6;
        }
        
        .badge-asuransi {
            background-color: #f59e0b;
        }

        .badge-poli-umum {
            background-color: #0ea5e9;
        }

        .badge-poli-kebidanan {
            background-color: #ec4899;
        }
        
        .input-group-umur {
            position: relative;
        }
        
        .info-umur {
            background-color: #e0f2fe;
            border-left: 4px solid #0284c7;
            padding: 10px;
            margin-top: 10px;
            border-radius: 5px;
            display: none;
        }
        
        .info-umur.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
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
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mt-4">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahPasien">
                                <i class="fas fa-user-plus me-2"></i>Tambah Pasien Baru
                            </button>
                        </div>
                        <div class="col-md-6 text-end mt-4">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalDaftarPasien">
                                <i class="fas fa-clipboard-list me-2"></i>Daftarkan Pasien
                            </button>
                        </div>
                    </div>
                    
                    <!-- Pendaftaran Hari Ini -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-list me-2"></i>Pendaftaran Hari Ini
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>No. Antrian</th>
                                            <th>No. RM</th>
                                            <th>Nama Pasien</th>
                                            <th>Kepala Keluarga</th>
                                            <th>Alamat</th>
                                            <th>Poli</th>
                                            <th>Jenis Pasien</th>
                                            <th>Waktu Daftar</th>
                                            <th>Keluhan</th>
                                            <th>Pemeriksaan Fisik</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result_pendaftaran) > 0): ?>
                                            <?php while ($row = mysqli_fetch_assoc($result_pendaftaran)): ?>
                                            <tr>
                                                <td><strong><?php echo $row['no_antrian']; ?></strong></td>
                                                <td><?php echo $row['no_rm']; ?></td>
                                                <td><?php echo $row['nama_lengkap']; ?></td>
                                                <td><?php echo $row['kepala_keluarga'] ?? '-'; ?></td>
<td><?php echo $row['alamat'] ?? '-'; ?></td>
                                                <td>
                                                    <?php
                                                    $badge_poli = ($row['poli'] == 'kebidanan') ? 'badge-poli-kebidanan' : 'badge-poli-umum';
                                                    $label_poli = ($row['poli'] == 'kebidanan') ? 'Kebidanan' : 'Umum';
                                                    ?>
                                                    <span class="badge <?php echo $badge_poli; ?>">
                                                        <?php echo $label_poli; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $badge_jenis = '';
                                                    switch($row['jenis_pasien']) {
                                                        case 'umum': $badge_jenis = 'badge-umum'; break;
                                                        case 'bpjs': $badge_jenis = 'badge-bpjs'; break;
                                                        case 'asuransi': $badge_jenis = 'badge-asuransi'; break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $badge_jenis; ?>">
                                                        <?php echo strtoupper($row['jenis_pasien']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('H:i', strtotime($row['tgl_daftar'])); ?></td>
                                                <td><?php echo $row['keluhan']; ?></td>
                                                <td><?php echo $row['pemeriksaan_fisik']; ?></td>
                                                <td>
                                                    <?php
                                                    $badge_class = '';
                                                    switch($row['status']) {
                                                        case 'menunggu': $badge_class = 'bg-warning'; break;
                                                        case 'diperiksa': $badge_class = 'bg-info'; break;
                                                        case 'selesai': $badge_class = 'bg-success'; break;
                                                        case 'dibatalkan': $badge_class = 'bg-danger'; break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($row['status'] == 'menunggu'): ?>
                                                    <button class="btn btn-danger btn-sm" 
                                                            onclick="batalBerkunjung(<?php echo $row['id']; ?>, '<?php echo $row['no_antrian']; ?>', '<?php echo $row['nama_lengkap']; ?>')">
                                                        <i class="fas fa-times"></i> Batal
                                                    </button>
                                                    <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">
                                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                                    <p>Belum ada pendaftaran hari ini</p>
                                                </td>
                                            </tr>
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
    
    <!-- Modal Tambah Pasien -->
    <div class="modal fade" id="modalTambahPasien" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Tambah Pasien Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama_lengkap" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">NIK<span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="nik"id="nik" placeholder="Masukkan nik">
                                <!-- <small class="text-muted">Isi umur atau tanggal lahir</small> -->
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Umur (Tahun) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="umur" placeholder="Contoh: 020911 → 02thn 09bln 11hr" maxlength="6">
<small class="text-muted">Ketik 6 angka (TTBBHH) atau pilih tanggal lahir</small>
                            </div>
                        </div>
                        <div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Nama Kepala Keluarga</label>
        <input type="text" class="form-control" name="kepala_keluarga" placeholder="Opsional">
    </div>
</div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="tgl_lahir" id="tgl_lahir" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                <select class="form-select" name="jenis_kelamin" required>
                                    <option value="">Pilih...</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="info-umur" id="info_umur">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Informasi:</strong> <span id="info_umur_text"></span>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Pasien <span class="text-danger">*</span></label>
                                <select class="form-select" name="jenis_pasien" id="jenis_pasien" required>
                                    <option value="">Pilih...</option>
                                    <option value="umum">Umum</option>
                                    <option value="bpjs">BPJS</option>
                                    <option value="asuransi">Asuransi</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">No. Telepon <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="no_telepon" required>
                            </div>
                        </div>
                        
                        <!-- Field untuk BPJS -->
                        <div id="field_bpjs" class="jenis-pasien-group mb-3">
                            <label class="form-label">No. BPJS <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="no_bpjs" placeholder="Masukkan No. BPJS">
                        </div>
                        
                        <!-- Field untuk Asuransi -->
                        <div id="field_asuransi" class="jenis-pasien-group">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nama Asuransi <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama_asuransi" placeholder="Contoh: Prudential, Allianz">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">No. Polis <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="no_polis" placeholder="Masukkan No. Polis">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Alamat <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="alamat" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_pasien" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
<!-- Modal Daftar Pasien -->
<div class="modal fade" id="modalDaftarPasien" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clipboard-list me-2"></i>Daftarkan Pasien</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <!-- Kolom Kiri -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary mb-3"><i class="fas fa-user me-2"></i>Data Pasien</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Pilih Pasien <span class="text-danger">*</span></label>
                                <select class="form-select select2" name="pasien_id" id="pilih_pasien" required>
                                    <option value="">-- Pilih Pasien atau Ketik No. RM --</option>
                                    <?php mysqli_data_seek($result_pasien, 0); ?>
                                    <?php while ($row = mysqli_fetch_assoc($result_pasien)): ?>
                                        <option value="<?php echo $row['id']; ?>" 
        data-jenis="<?php echo $row['jenis_pasien']; ?>"
        data-nobpjs="<?php echo $row['no_bpjs']; ?>"
        data-asuransi="<?php echo $row['nama_asuransi']; ?>"
        data-polis="<?php echo $row['no_polis']; ?>"
        data-norm="<?php echo str_replace('-', '', $row['no_rm']); ?>"
        data-nik="<?php echo $row['nik']; ?>"
        data-alamat="<?php echo htmlspecialchars($row['alamat']); ?>"
        data-kk="<?php echo htmlspecialchars($row['kepala_keluarga'] ?? ''); ?>"
data-tgllahir="<?php echo $row['tgl_lahir']; ?>"
data-jk="<?php echo $row['jenis_kelamin']; ?>"
data-telp="<?php echo $row['no_telepon']; ?>"><?php echo $row['no_rm']; ?> - <?php echo $row['nama_lengkap']; ?> - <?php echo $row['kepala_keluarga']; ?> - <?php echo $row['alamat']; ?> (<?php echo strtoupper($row['jenis_pasien']); ?>)</option>
                                            
                                    <?php endwhile; ?>
                                </select>
                                <small class="text-muted">Ketik nomor RM atau nama pasien untuk mencari</small>
                            </div>

                            <!-- Info Pasien Terpilih -->
<div id="info_pasien" class="card border-info" style="display:none;">
    <div class="card-body py-3">

        <!-- Mode View -->
        <div id="mode_view">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <strong class="text-info"><i class="fas fa-user-circle me-1"></i> Info Pasien</strong>
                <button type="button" class="btn btn-sm btn-outline-warning" id="btn_edit_pasien">
                    <i class="fas fa-pen me-1"></i>Edit Data
                </button>
            </div>
            <p class="mb-1"><i class="fas fa-user me-2 text-info"></i><strong>Nama:</strong> <span id="info_nama"></span></p>
            <p class="mb-1"><i class="fas fa-id-badge me-2 text-info"></i><strong>Jenis Pasien:</strong> <span id="info_jenis" class="badge bg-info"></span></p>
            <p class="mb-1" id="baris_detail"><i class="fas fa-file-alt me-2 text-secondary"></i><span id="info_detail"></span></p>
            <p class="mb-1" id="baris_kk" style="display:none"><i class="fas fa-home me-2 text-secondary"></i><strong>Kepala Keluarga:</strong> <span id="info_kk"></span></p>
            <p class="mb-0" id="baris_alamat" style="display:none"><i class="fas fa-map-marker-alt me-2 text-secondary"></i><strong>Alamat:</strong> <span id="info_alamat"></span></p>
        </div>

        <!-- Mode Edit -->
        <div id="mode_edit" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <strong class="text-warning"><i class="fas fa-pen me-1"></i> Edit Data Pasien</strong>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_batal_edit">
                    <i class="fas fa-times me-1"></i>Batal
                </button>
            </div>
            <input type="hidden" id="edit_pasien_id">
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label form-label-sm">Nama Lengkap</label>
                    <input type="text" class="form-control form-control-sm" name="edit_nama_lengkap" id="edit_nama_lengkap">
                </div>
                <div class="col-md-6">
                    <label class="form-label form-label-sm">NIK</label>
                    <input type="number" class="form-control form-control-sm" name="edit_nik" id="edit_nik">
                </div>
                <div class="col-md-6">
                    <label class="form-label form-label-sm">Tanggal Lahir</label>
                    <input type="date" class="form-control form-control-sm" name="edit_tgl_lahir" id="edit_tgl_lahir">
                </div>
                <div class="col-md-6">
                    <label class="form-label form-label-sm">Jenis Kelamin</label>
                    <select class="form-select form-select-sm" name="edit_jenis_kelamin" id="edit_jenis_kelamin">
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label form-label-sm">Jenis Pasien</label>
                    <select class="form-select form-select-sm" name="edit_jenis_pasien" id="edit_jenis_pasien">
                        <option value="umum">Umum</option>
                        <option value="bpjs">BPJS</option>
                        <option value="asuransi">Asuransi</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label form-label-sm">No. Telepon</label>
                    <input type="text" class="form-control form-control-sm" name="edit_no_telepon" id="edit_no_telepon">
                </div>
                <div class="col-12" id="edit_field_bpjs" style="display:none;">
                    <label class="form-label form-label-sm">No. BPJS</label>
                    <input type="text" class="form-control form-control-sm" name="edit_no_bpjs" id="edit_no_bpjs">
                </div>
                <div class="col-12" id="edit_field_asuransi" style="display:none;">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Nama Asuransi</label>
                            <input type="text" class="form-control form-control-sm" name="edit_nama_asuransi" id="edit_nama_asuransi">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">No. Polis</label>
                            <input type="text" class="form-control form-control-sm" name="edit_no_polis" id="edit_no_polis">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label form-label-sm">Kepala Keluarga</label>
                    <input type="text" class="form-control form-control-sm" name="edit_kepala_keluarga" id="edit_kepala_keluarga">
                </div>
                <div class="col-md-6">
                    <label class="form-label form-label-sm">Alamat</label>
                    <textarea class="form-control form-control-sm" name="edit_alamat" id="edit_alamat" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-3 text-end">
                <button type="button" id="btn_simpan_edit" class="btn btn-warning btn-sm">
                    <i class="fas fa-save me-1"></i>Simpan Perubahan
                </button>
            </div>
        </div>

    </div>
</div>
                        </div>

                        <!-- Kolom Kanan -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-success mb-3"><i class="fas fa-hospital me-2"></i>Data Kunjungan</h6>

                            <div class="mb-3">
                                <label class="form-label">Tujuan Poli <span class="text-danger">*</span></label>
                                <select class="form-select" name="poli" id="pilih_poli" required>
                                    <option value="">-- Pilih Poli --</option>
                                    <option value="umum">🏥 Poli Umum</option>
                                    <option value="kebidanan">🌸 Poli Kebidanan</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Keluhan</label>
                                <textarea class="form-control" name="keluhan" rows="3" placeholder="Jelaskan keluhan pasien..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Pemeriksaan Fisik</label>
                                <textarea class="form-control" name="pem_fisik" rows="3" placeholder="Input pemeriksaan fisik pasien..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="daftar_pasien" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Daftarkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Modal Batal Berkunjung -->
    <div class="modal fade" id="modalBatalBerkunjung" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Batalkan Pendaftaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Perhatian!</strong> Anda akan membatalkan pendaftaran pasien.
                        </div>
                        
                        <input type="hidden" name="pendaftaran_id" id="batal_pendaftaran_id">
                        
                        <div class="mb-3">
                            <label class="form-label"><strong>No. Antrian:</strong></label>
                            <p class="form-control-plaintext" id="batal_no_antrian"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><strong>Nama Pasien:</strong></label>
                            <p class="form-control-plaintext" id="batal_nama_pasien"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Alasan Pembatalan <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="alasan_batal" rows="3" required 
                                      placeholder="Masukkan alasan pembatalan..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password Anda <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required 
                                   placeholder="Masukkan password untuk konfirmasi">
                            <small class="text-muted">Password diperlukan untuk keamanan</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" name="batal_berkunjung" class="btn btn-danger">
                            <i class="fas fa-times-circle me-2"></i>Ya, Batalkan
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
        $(document).ready(function() {

    // Auto hide alert PHP setelah 3 detik
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() { $(this).remove(); });
    }, 3000);

    // Initialize Select2 with custom matcher
    // Initialize Select2 with custom matcher
// Initialize Select2 with custom matcher
    $('#pilih_pasien').select2({
    theme: 'bootstrap-5',
    width: '100%',
    dropdownParent: $('#modalDaftarPasien'),

    placeholder: '-- Ketik Nama / No RM / NIK --',
    minimumInputLength: 1,

    language: {
        inputTooShort: function () {
            return 'Ketik untuk mencari pasien';
        },
        noResults: function () {
            return 'Pasien tidak ditemukan';
        }
    },

    matcher: function(params, data) {

        // KOSONGKAN sebelum mengetik
        if ($.trim(params.term) === '') {
            return null;
        }

        if (typeof data.text === 'undefined') return null;

        var term = params.term.toLowerCase();
        var text = data.text.toLowerCase();
        var $option = $(data.element);

        var nik = ($option.data('nik') || '').toString().toLowerCase();
        var normNoDash = ($option.data('norm') || '').toString();
        var kk = ($option.data('kk') || '').toString().toLowerCase();
        var termNoDash = term.replace(/-/g, '');

        if (
            text.indexOf(term) > -1 ||
            nik.indexOf(term) > -1 ||
            normNoDash.indexOf(termNoDash) > -1 ||
            kk.indexOf(term) > -1
        ) {
            return data;
        }

        return null;
    }
});

    $(document).on('select2:open', function() {
        document.querySelector('.select2-search__field').focus();
    });
            
            // Toggle field berdasarkan jenis pasien
            $('#jenis_pasien').on('change', function() {
                var jenis = $(this).val();
                
                $('.jenis-pasien-group').removeClass('active');
                $('.jenis-pasien-group input').prop('required', false);
                
                if (jenis == 'bpjs') {
                    $('#field_bpjs').addClass('active');
                    $('#field_bpjs input').prop('required', true);
                } else if (jenis == 'asuransi') {
                    $('#field_asuransi').addClass('active');
                    $('#field_asuransi input').prop('required', true);
                }
            });
            
// Show info pasien saat dipilih
$('#pilih_pasien').on('change', function() {
    var selected = $(this).find(':selected');
    var jenis = selected.data('jenis');

    $('#mode_view').show();
    $('#mode_edit').hide();

    if (jenis) {
        var info_text = '';
        if (jenis == 'bpjs') {
            info_text = 'No. BPJS: ' + selected.data('nobpjs');
        } else if (jenis == 'asuransi') {
            info_text = 'Asuransi: ' + selected.data('asuransi') + ' | No. Polis: ' + selected.data('polis');
        } else {
            info_text = 'Pasien Umum';
        }

        var kk     = selected.data('kk') || '';
        var alamat = selected.data('alamat') || '';
        var nama   = selected.text().split(' - ')[1] || '';

        $('#info_nama').text(nama);
        $('#info_jenis').text(jenis.toUpperCase());
        $('#info_detail').html(info_text);

        if (kk)     { $('#info_kk').text(kk);        $('#baris_kk').show();     }
        else        { $('#baris_kk').hide(); }
        if (alamat) { $('#info_alamat').text(alamat); $('#baris_alamat').show(); }
        else        { $('#baris_alamat').hide(); }

        $('#info_pasien').fadeIn();
    } else {
        $('#info_pasien').fadeOut();
    }
});

// Buka mode edit
$('#btn_edit_pasien').on('click', function() {
    var selected = $('#pilih_pasien').find(':selected');

    $('#edit_pasien_id').val(selected.val());
    $('#edit_nama_lengkap').val(selected.text().split(' - ')[1] || '');
    $('#edit_nik').val(selected.data('nik'));
    $('#edit_tgl_lahir').val(selected.data('tgllahir'));
    $('#edit_jenis_kelamin').val(selected.data('jk'));
    $('#edit_jenis_pasien').val(selected.data('jenis')).trigger('change');
    $('#edit_no_telepon').val(selected.data('telp'));
    $('#edit_no_bpjs').val(selected.data('nobpjs'));
    $('#edit_nama_asuransi').val(selected.data('asuransi'));
    $('#edit_no_polis').val(selected.data('polis'));
    $('#edit_kepala_keluarga').val(selected.data('kk'));
    $('#edit_alamat').val(selected.data('alamat'));

    $('#mode_view').hide();
    $('#mode_edit').show();
});

// Batal edit
// Batal edit
$('#btn_batal_edit').on('click', function() {
    $('#mode_edit').hide();
    $('#mode_view').show();
});

// Simpan edit — salin ke form hidden lalu submit
$('#btn_simpan_edit').on('click', function() {
    $('#real_edit_pasien_id').val($('#edit_pasien_id').val());
    $('#real_edit_nama_lengkap').val($('#edit_nama_lengkap').val());
    $('#real_edit_nik').val($('#edit_nik').val());
    $('#real_edit_tgl_lahir').val($('#edit_tgl_lahir').val());
    $('#real_edit_jenis_kelamin').val($('#edit_jenis_kelamin').val());
    $('#real_edit_jenis_pasien').val($('#edit_jenis_pasien').val());
    $('#real_edit_no_telepon').val($('#edit_no_telepon').val());
    $('#real_edit_no_bpjs').val($('#edit_no_bpjs').val());
    $('#real_edit_nama_asuransi').val($('#edit_nama_asuransi').val());
    $('#real_edit_no_polis').val($('#edit_no_polis').val());
    $('#real_edit_kepala_keluarga').val($('#edit_kepala_keluarga').val());
    $('#real_edit_alamat').val($('#edit_alamat').val());

    var formData = $('#form_edit_pasien').serialize();

    $.ajax({
        url: '',
        type: 'POST',
        data: formData + '&edit_pasien=1',
        success: function(response) {
            var namaBaru    = $('#edit_nama_lengkap').val();
            var kkBaru      = $('#edit_kepala_keluarga').val();
            var alamatBaru  = $('#edit_alamat').val();
            var jenisBaru   = $('#edit_jenis_pasien').val();
            var nikBaru     = $('#edit_nik').val();
            var tglBaru     = $('#edit_tgl_lahir').val();
            var jkBaru      = $('#edit_jenis_kelamin').val();
            var telpBaru    = $('#edit_no_telepon').val();
            var nobpjsBaru  = $('#edit_no_bpjs').val();
            var asuransiBaru = $('#edit_nama_asuransi').val();
            var polisBaru   = $('#edit_no_polis').val();

            var selected = $('#pilih_pasien').find(':selected');
            var noRM     = selected.attr('data-norm') || '';

            // Update data-* di option
            selected.attr('data-kk', kkBaru).data('kk', kkBaru);
            selected.attr('data-alamat', alamatBaru).data('alamat', alamatBaru);
            selected.attr('data-jenis', jenisBaru).data('jenis', jenisBaru);
            selected.attr('data-nik', nikBaru).data('nik', nikBaru);
            selected.attr('data-tgllahir', tglBaru).data('tgllahir', tglBaru);
            selected.attr('data-jk', jkBaru).data('jk', jkBaru);
            selected.attr('data-telp', telpBaru).data('telp', telpBaru);
            selected.attr('data-nobpjs', nobpjsBaru).data('nobpjs', nobpjsBaru);
            selected.attr('data-asuransi', asuransiBaru).data('asuransi', asuransiBaru);
            selected.attr('data-polis', polisBaru).data('polis', polisBaru);

            // Update teks option
            selected.text(noRM + ' - ' + namaBaru + ' - ' + kkBaru + ' - ' + alamatBaru + ' (' + jenisBaru.toUpperCase() + ')');

            // Refresh info pasien di mode view
            $('#info_nama').text(namaBaru);
            $('#info_jenis').text(jenisBaru.toUpperCase());

            if (jenisBaru == 'bpjs') {
                $('#info_detail').html('No. BPJS: ' + nobpjsBaru);
            } else if (jenisBaru == 'asuransi') {
                $('#info_detail').html('Asuransi: ' + asuransiBaru + ' | No. Polis: ' + polisBaru);
            } else {
                $('#info_detail').html('Pasien Umum');
            }

            if (kkBaru)     { $('#info_kk').text(kkBaru);       $('#baris_kk').show();     }
            else            { $('#baris_kk').hide(); }
            if (alamatBaru) { $('#info_alamat').text(alamatBaru); $('#baris_alamat').show(); }
            else            { $('#baris_alamat').hide(); }

            // Notif sukses
            var alertHtml = '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                        '<i class="fas fa-check-circle me-2"></i>Data pasien berhasil diperbarui.' +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            $('.container-fluid.px-4').prepend(alertHtml);
            setTimeout(function() {
                $('.alert-success').fadeOut('slow', function() { $(this).remove(); });
            }, 3000);

            $('#mode_edit').hide();
            $('#mode_view').show();
        },
        error: function() {
            var alert = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                        '<i class="fas fa-exclamation-circle me-2"></i>Gagal menyimpan perubahan.' +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            $('.container-fluid.px-4').prepend(alert);
            setTimeout(function() {
                $('.alert-danger').fadeOut('slow', function() { $(this).remove(); });
            }, 3000);
        }
    });
});

// Toggle BPJS/Asuransi di form edit
$('#edit_jenis_pasien').on('change', function() {
    var jenis = $(this).val();
    $('#edit_field_bpjs').hide();
    $('#edit_field_asuransi').hide();
    if (jenis == 'bpjs')           $('#edit_field_bpjs').show();
    else if (jenis == 'asuransi')  $('#edit_field_asuransi').show();
});
            // ========== FITUR UMUR DAN TANGGAL LAHIR ==========
            
            // Fungsi untuk menghitung umur dari tanggal lahir
            // Fungsi untuk menghitung umur dari tanggal lahir (tahun, bulan, hari)
function hitungUmur(tglLahir) {
    var today = new Date();
    var birthDate = new Date(tglLahir);

    var years = today.getFullYear() - birthDate.getFullYear();
    var months = today.getMonth() - birthDate.getMonth();
    var days = today.getDate() - birthDate.getDate();

    if (days < 0) {
        months--;
        var lastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
        days += lastMonth.getDate();
    }

    if (months < 0) {
        years--;
        months += 12;
    }

    return { years: years, months: months, days: days };
}
            
            // Fungsi untuk menghitung tanggal lahir dari umur
            function hitungTglLahir(umur) {
                var today = new Date();
                var tahunLahir = today.getFullYear() - umur;
                var bulanLahir = String(today.getMonth() + 1).padStart(2, '0');
                var hariLahir = String(today.getDate()).padStart(2, '0');
                
                return tahunLahir + '-' + bulanLahir + '-' + hariLahir;
            }
            
            // Event ketika umur diisi
            $('#umur').on('input', function() {
    var input = $(this).val().replace(/\D/g, '');

    if (input.length === 6) {
        var tahun = parseInt(input.substring(0, 2));
        var bulan = parseInt(input.substring(2, 4));
        var hari  = parseInt(input.substring(4, 6));

        if (bulan > 12) bulan = 12;
        if (hari > 31)  hari  = 31;

        var today = new Date();
        var tgl = new Date(
            today.getFullYear() - tahun,
            today.getMonth() - bulan,
            today.getDate() - hari
        );
        var yyyy = tgl.getFullYear();
        var mm   = String(tgl.getMonth() + 1).padStart(2, '0');
        var dd   = String(tgl.getDate()).padStart(2, '0');

        $('#tgl_lahir').val(yyyy + '-' + mm + '-' + dd);

        var bagian = [];
        if (tahun > 0) bagian.push(tahun + ' tahun');
        if (bulan > 0) bagian.push(bulan + ' bulan');
        if (hari > 0)  bagian.push(hari + ' hari');
        if (bagian.length === 0) bagian.push('0 hari');

        $('#info_umur_text').text('Umur: ' + bagian.join(', ') + ' → Tgl lahir: ' + dd + '/' + mm + '/' + yyyy);
        $('#info_umur').addClass('show');

    } else if (input.length === 0) {
        $('#tgl_lahir').val('');
        $('#info_umur').removeClass('show');
    }
});
            
            // Event ketika tanggal lahir dipilih
$('#tgl_lahir').on('change', function() {
    var tglLahir = $(this).val();

    if (tglLahir) {
        var umur = hitungUmur(tglLahir);

        if (umur.years >= 0 && umur.years <= 150) {
            var bagian = [];
            if (umur.years > 0)  bagian.push(umur.years + ' tahun');
            if (umur.months > 0) bagian.push(umur.months + ' bulan');
            if (umur.days > 0)   bagian.push(umur.days + ' hari');
            if (bagian.length === 0) bagian.push('0 hari');

            $('#umur').val(bagian.join(' '));
            $('#info_umur_text').text('Umur pasien: ' + bagian.join(', '));
            $('#info_umur').addClass('show');
        } else {
            $('#info_umur_text').text('Tanggal lahir tidak valid');
            $('#info_umur').addClass('show');
        }
    }
});
$('#umur').on('input', function() {
    var input = $(this).val().replace(/\D/g, '');

    if (input.length === 2) {
        // Hanya tahun
        var tahun = parseInt(input);
        var bulan = 0;
        var hari  = 0;

        var today = new Date();
        var tgl = new Date(today.getFullYear() - tahun, today.getMonth(), today.getDate());
        var yyyy = tgl.getFullYear();
        var mm   = String(tgl.getMonth() + 1).padStart(2, '0');
        var dd   = String(tgl.getDate()).padStart(2, '0');

        $('#tgl_lahir').val(yyyy + '-' + mm + '-' + dd);

        var teks = tahun > 0 ? tahun + ' tahun' : '0 tahun';
        $('#info_umur_text').text('Umur: ' + teks + ' → Tgl lahir: ' + dd + '/' + mm + '/' + yyyy);
        $('#info_umur').addClass('show');

    } else if (input.length === 4) {
        // Tahun dan bulan
        var tahun = parseInt(input.substring(0, 2));
        var bulan = parseInt(input.substring(2, 4));

        if (bulan > 12) bulan = 12;

        var today = new Date();
        var tgl = new Date(today.getFullYear() - tahun, today.getMonth() - bulan, today.getDate());
        var yyyy = tgl.getFullYear();
        var mm   = String(tgl.getMonth() + 1).padStart(2, '0');
        var dd   = String(tgl.getDate()).padStart(2, '0');

        $('#tgl_lahir').val(yyyy + '-' + mm + '-' + dd);

        var bagian = [];
        if (tahun > 0) bagian.push(tahun + ' tahun');
        if (bulan > 0) bagian.push(bulan + ' bulan');
        if (bagian.length === 0) bagian.push('0 hari');

        $('#info_umur_text').text('Umur: ' + bagian.join(', ') + ' → Tgl lahir: ' + dd + '/' + mm + '/' + yyyy);
        $('#info_umur').addClass('show');

    } else if (input.length === 6) {
        // Tahun, bulan, hari
        var tahun = parseInt(input.substring(0, 2));
        var bulan = parseInt(input.substring(2, 4));
        var hari  = parseInt(input.substring(4, 6));

        if (bulan > 12) bulan = 12;
        if (hari > 31)  hari  = 31;

        var today = new Date();
        var tgl = new Date(today.getFullYear() - tahun, today.getMonth() - bulan, today.getDate() - hari);
        var yyyy = tgl.getFullYear();
        var mm   = String(tgl.getMonth() + 1).padStart(2, '0');
        var dd   = String(tgl.getDate()).padStart(2, '0');

        $('#tgl_lahir').val(yyyy + '-' + mm + '-' + dd);

        var bagian = [];
        if (tahun > 0) bagian.push(tahun + ' tahun');
        if (bulan > 0) bagian.push(bulan + ' bulan');
        if (hari > 0)  bagian.push(hari + ' hari');
        if (bagian.length === 0) bagian.push('0 hari');

        $('#info_umur_text').text('Umur: ' + bagian.join(', ') + ' → Tgl lahir: ' + dd + '/' + mm + '/' + yyyy);
        $('#info_umur').addClass('show');

    } else if (input.length === 0) {
        $('#tgl_lahir').val('');
        $('#info_umur').removeClass('show');
    }
});
            
            // Reset form ketika modal ditutup
            $('#modalTambahPasien').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
                $('.jenis-pasien-group').removeClass('active');
                $('.jenis-pasien-group input').prop('required', false);
                $('#info_umur').removeClass('show');
            });
        });
        
        function batalBerkunjung(id, noAntrian, namaPasien) {
            $('#batal_pendaftaran_id').val(id);
            $('#batal_no_antrian').text(noAntrian);
            $('#batal_nama_pasien').text(namaPasien);
            $('#modalBatalBerkunjung').modal('show');
        }
        
    </script>
    <!-- Form Edit Pasien (di luar modal) -->
<form method="POST" id="form_edit_pasien">
    <input type="hidden" name="edit_pasien_id"      id="real_edit_pasien_id">
    <input type="hidden" name="edit_nama_lengkap"   id="real_edit_nama_lengkap">
    <input type="hidden" name="edit_nik"            id="real_edit_nik">
    <input type="hidden" name="edit_tgl_lahir"      id="real_edit_tgl_lahir">
    <input type="hidden" name="edit_jenis_kelamin"  id="real_edit_jenis_kelamin">
    <input type="hidden" name="edit_jenis_pasien"   id="real_edit_jenis_pasien">
    <input type="hidden" name="edit_no_telepon"     id="real_edit_no_telepon">
    <input type="hidden" name="edit_no_bpjs"        id="real_edit_no_bpjs">
    <input type="hidden" name="edit_nama_asuransi"  id="real_edit_nama_asuransi">
    <input type="hidden" name="edit_no_polis"       id="real_edit_no_polis">
    <input type="hidden" name="edit_kepala_keluarga" id="real_edit_kepala_keluarga">
    <input type="hidden" name="edit_alamat"         id="real_edit_alamat">
    <button type="submit" name="edit_pasien" id="real_btn_submit_edit" style="display:none;"></button>
</form>
</body>
</html>