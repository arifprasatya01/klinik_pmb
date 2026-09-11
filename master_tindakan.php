<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Tambah Tindakan
if (isset($_POST['tambah_tindakan'])) {
    $nama_tindakan = sanitize($_POST['nama_tindakan']);
    $kategori = sanitize($_POST['kategori']);
    $tarif = sanitize($_POST['tarif']);
    $keterangan = sanitize($_POST['keterangan']);
    
    // Generate kode tindakan otomatis
    $prefix = '';
    switch($kategori) {
        case 'Pemeriksaan': $prefix = 'PEM'; break;
        case 'Tindakan Medis': $prefix = 'TND'; break;
        case 'Laboratorium': $prefix = 'LAB'; break;
        case 'Radiologi': $prefix = 'RAD'; break;
        case 'Konsultasi': $prefix = 'KON'; break;
        default: $prefix = 'OTH';
    }
    
    // Get last number for this prefix
    $query_last = "SELECT kode_tindakan FROM master_tindakan WHERE kode_tindakan LIKE '$prefix%' ORDER BY kode_tindakan DESC LIMIT 1";
    $result_last = mysqli_query($conn, $query_last);
    
    if (mysqli_num_rows($result_last) > 0) {
        $last_code = mysqli_fetch_assoc($result_last)['kode_tindakan'];
        $last_number = intval(substr($last_code, strlen($prefix)));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    $kode_tindakan = $prefix . str_pad($new_number, 3, '0', STR_PAD_LEFT);
    
    $query = "INSERT INTO master_tindakan (kode_tindakan, nama_tindakan, tarif, keterangan) 
              VALUES ('$kode_tindakan', '$nama_tindakan', '$tarif', '$keterangan')";
    
    if (mysqli_query($conn, $query)) {
        $success = "Tindakan berhasil ditambahkan dengan kode: $kode_tindakan";
    } else {
        $error = "Gagal menambahkan tindakan: " . mysqli_error($conn);
    }
}

// Edit Tindakan
if (isset($_POST['edit_tindakan'])) {
    $tindakan_id = sanitize($_POST['tindakan_id']);
    $nama_tindakan = sanitize($_POST['nama_tindakan']);
    $tarif = sanitize($_POST['tarif']);
    $keterangan = sanitize($_POST['keterangan']);
    
    $query = "UPDATE master_tindakan SET 
              nama_tindakan = '$nama_tindakan', 
              tarif = '$tarif', 
              keterangan = '$keterangan' 
              WHERE id = '$tindakan_id'";
    
    if (mysqli_query($conn, $query)) {
        $success = "Tindakan berhasil diupdate";
    } else {
        $error = "Gagal update tindakan: " . mysqli_error($conn);
    }
}

// Hapus Tindakan
if (isset($_POST['hapus_tindakan'])) {
    $tindakan_id = sanitize($_POST['tindakan_id']);
    
    // Check if tindakan is used in transaksi
    $check = "SELECT id FROM detail_tindakan WHERE tindakan_id = '$tindakan_id' LIMIT 1";
    $result_check = mysqli_query($conn, $check);
    
    if (mysqli_num_rows($result_check) > 0) {
        $error = "Tindakan tidak bisa dihapus karena sudah digunakan dalam transaksi!";
    } else {
        $query = "DELETE FROM master_tindakan WHERE id = '$tindakan_id'";
        
        if (mysqli_query($conn, $query)) {
            $success = "Tindakan berhasil dihapus";
        } else {
            $error = "Gagal menghapus tindakan: " . mysqli_error($conn);
        }
    }
}

// Get All Tindakan
$query_tindakan = "SELECT * FROM master_tindakan ORDER BY kode_tindakan ASC";
$result_tindakan = mysqli_query($conn, $query_tindakan);

// Count and get kategori from kode prefix
$total_pemeriksaan = 0;
$total_tindakan_medis = 0;
$total_laboratorium = 0;
$total_radiologi = 0;

mysqli_data_seek($result_tindakan, 0);
while ($row = mysqli_fetch_assoc($result_tindakan)) {
    $prefix = substr($row['kode_tindakan'], 0, 3);
    switch($prefix) {
        case 'PEM': $total_pemeriksaan++; break;
        case 'TND': $total_tindakan_medis++; break;
        case 'LAB': $total_laboratorium++; break;
        case 'RAD': $total_radiologi++; break;
    }
}

// Total tarif
$query_total = "SELECT SUM(tarif) as total_tarif FROM master_tindakan";
$result_total = mysqli_query($conn, $query_total);
$total_tarif = mysqli_fetch_assoc($result_total)['total_tarif'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Tindakan - Healoka</title>
        <!-- Favicon -->
    <link rel="icon" type="image/png" href="asset/img/logo.png">
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
        
      
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .modal-content {
            border-radius: 15px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .stat-card {
            border: none;
            border-radius: 15px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
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
                    
                    <!-- Statistics -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1">Total Tindakan</p>
                                            <h3 class="mb-0"><?php echo mysqli_num_rows($result_tindakan); ?></h3>
                                        </div>
                                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                            <i class="fas fa-procedures"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1">Pemeriksaan</p>
                                            <h3 class="mb-0"><?php echo $total_pemeriksaan; ?></h3>
                                        </div>
                                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                                            <i class="fas fa-stethoscope"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1">Tindakan Medis</p>
                                            <h3 class="mb-0"><?php echo $total_tindakan_medis; ?></h3>
                                        </div>
                                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                                            <i class="fas fa-syringe"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1">Laboratorium</p>
                                            <h3 class="mb-0"><?php echo $total_laboratorium; ?></h3>
                                        </div>
                                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                            <i class="fas fa-flask"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahTindakan">
                                <i class="fas fa-plus me-2"></i>Tambah Tindakan
                            </button>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="input-group" style="max-width: 300px; float: right;">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="searchInput" placeholder="Cari tindakan...">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tindakan Table -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-list me-2"></i>Daftar Tindakan
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tableTindakan">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Nama Tindakan</th>
                                            <th>Kategori</th>
                                            <th>Tarif</th>
                                            <th>Keterangan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result_tindakan) > 0): ?>
                                            <?php 
                                            mysqli_data_seek($result_tindakan, 0);
                                            while ($row = mysqli_fetch_assoc($result_tindakan)): 
                                                // Determine kategori from kode prefix
                                                $prefix = substr($row['kode_tindakan'], 0, 3);
                                                $kategori = '';
                                                $badge_class = '';
                                                
                                                switch($prefix) {
                                                    case 'PEM': 
                                                        $kategori = 'Pemeriksaan'; 
                                                        $badge_class = 'bg-success'; 
                                                        break;
                                                    case 'TND': 
                                                        $kategori = 'Tindakan Medis'; 
                                                        $badge_class = 'bg-info'; 
                                                        break;
                                                    case 'LAB': 
                                                        $kategori = 'Laboratorium'; 
                                                        $badge_class = 'bg-warning'; 
                                                        break;
                                                    case 'RAD': 
                                                        $kategori = 'Radiologi'; 
                                                        $badge_class = 'bg-primary'; 
                                                        break;
                                                    case 'KON': 
                                                        $kategori = 'Konsultasi'; 
                                                        $badge_class = 'bg-secondary'; 
                                                        break;
                                                    default: 
                                                        $kategori = 'Lainnya'; 
                                                        $badge_class = 'bg-dark';
                                                }
                                            ?>
                                            <tr>
                                                <td><strong><?php echo $row['kode_tindakan']; ?></strong></td>
                                                <td><?php echo $row['nama_tindakan']; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php echo $kategori; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong class="text-primary">
                                                        Rp <?php echo number_format($row['tarif'], 0, ',', '.'); ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo $row['keterangan'] ? $row['keterangan'] : '-'; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning" onclick='editTindakan(<?php echo json_encode($row); ?>)'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="hapusTindakan(<?php echo $row['id']; ?>, '<?php echo addslashes($row['nama_tindakan']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                                    <p>Belum ada data tindakan</p>
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
    
    <!-- Modal Tambah Tindakan -->
    <div class="modal fade" id="modalTambahTindakan" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Tambah Tindakan Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>Kode tindakan akan dibuat otomatis berdasarkan kategori yang dipilih</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" name="kategori" required>
                                <option value="">-- Pilih Kategori --</option>
                                <option value="Pemeriksaan">Pemeriksaan (PEM)</option>
                                <option value="Tindakan Medis">Tindakan Medis (TND)</option>
                                <option value="Laboratorium">Laboratorium (LAB)</option>
                                <option value="Radiologi">Radiologi (RAD)</option>
                                <option value="Konsultasi">Konsultasi (KON)</option>
                                <option value="Lainnya">Lainnya (OTH)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Tindakan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_tindakan" required placeholder="Contoh: Pemeriksaan Umum">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tarif (Rp) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="tarif" required min="0" step="1000" placeholder="Contoh: 50000">
                            <small class="text-muted">Tarif dalam Rupiah</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea class="form-control" name="keterangan" rows="3" placeholder="Keterangan tambahan (opsional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_tindakan" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit Tindakan -->
    <div class="modal fade" id="modalEditTindakan" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Tindakan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="tindakan_id" id="edit_tindakan_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Kode Tindakan</label>
                            <input type="text" class="form-control" id="edit_kode_tindakan" readonly disabled>
                            <small class="text-muted">Kode tindakan tidak dapat diubah</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Tindakan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_tindakan" id="edit_nama_tindakan" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tarif (Rp) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="tarif" id="edit_tarif" required min="0" step="1000">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea class="form-control" name="keterangan" id="edit_keterangan" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit_tindakan" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Hapus Tindakan -->
    <div class="modal fade" id="modalHapusTindakan" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title text-white"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="tindakan_id" id="hapus_tindakan_id">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Apakah Anda yakin ingin menghapus tindakan: <strong id="hapus_nama_tindakan"></strong>?
                        </div>
                        <p class="mb-0"><small class="text-muted">Data tindakan yang sudah dihapus tidak dapat dikembalikan!</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="hapus_tindakan" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Hapus Tindakan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editTindakan(data) {
            $('#edit_tindakan_id').val(data.id);
            $('#edit_kode_tindakan').val(data.kode_tindakan);
            $('#edit_nama_tindakan').val(data.nama_tindakan);
            $('#edit_tarif').val(data.tarif);
            $('#edit_keterangan').val(data.keterangan);
            $('#modalEditTindakan').modal('show');
        }
        
        function hapusTindakan(id, nama) {
            $('#hapus_tindakan_id').val(id);
            $('#hapus_nama_tindakan').text(nama);
            $('#modalHapusTindakan').modal('show');
        }
        
        // Search functionality
        $(document).ready(function(){
            $("#searchInput").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("#tableTindakan tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
        });
    </script>
</body>
</html>