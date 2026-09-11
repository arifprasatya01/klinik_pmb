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

// Proses Tambah/Edit Mitra
if (isset($_POST['simpan'])) {
    $id = isset($_POST['id']) ? sanitize($_POST['id']) : '';
    $kode_mitra = sanitize($_POST['kode_mitra']);
    $nama_apotek = sanitize($_POST['nama_apotek']);
    $nama_pemilik = sanitize($_POST['nama_pemilik']);
    $alamat = sanitize($_POST['alamat']);
    $telepon = sanitize($_POST['telepon']);
    $email = sanitize($_POST['email']);
    $status = sanitize($_POST['status']);
    
    if ($id) {
        // Update
        $query = "UPDATE mitra_apotek SET 
                  kode_mitra = ?,
                  nama_apotek = ?,
                  nama_pemilik = ?,
                  alamat = ?,
                  telepon = ?,
                  email = ?,
                  status = ?
                  WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssssssi", $kode_mitra, $nama_apotek, $nama_pemilik, $alamat, $telepon, $email, $status, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Data mitra berhasil diupdate!";
        } else {
            $error = "Gagal update data mitra!";
        }
        mysqli_stmt_close($stmt);
    } else {
        // Insert
        $query = "INSERT INTO mitra_apotek (kode_mitra, nama_apotek, nama_pemilik, alamat, telepon, email, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssssss", $kode_mitra, $nama_apotek, $nama_pemilik, $alamat, $telepon, $email, $status);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Data mitra berhasil ditambahkan!";
        } else {
            $error = "Gagal menambahkan data mitra!";
        }
        mysqli_stmt_close($stmt);
    }
}

// Proses Hapus
if (isset($_GET['hapus'])) {
    $id = sanitize($_GET['hapus']);
    $query = "DELETE FROM mitra_apotek WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $success = "Data mitra berhasil dihapus!";
    } else {
        $error = "Gagal menghapus data mitra!";
    }
    mysqli_stmt_close($stmt);
}

// Get semua mitra
$query_mitra = "SELECT * FROM mitra_apotek ORDER BY kode_mitra ASC";
$result_mitra = mysqli_query($conn, $query_mitra);

// Get data untuk edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id = sanitize($_GET['edit']);
    $query_edit = "SELECT * FROM mitra_apotek WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query_edit);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result_edit = mysqli_stmt_get_result($stmt);
    $edit_data = mysqli_fetch_assoc($result_edit);
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Mitra Apotek - Medisoft</title>
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
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .content-area {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 0;
        }
        
        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        
        .layout-container {
            display: flex;
            gap: 0;
            padding: 30px;
            height: calc(100vh - 70px);
        }
        
        .form-section {
            width: 400px;
            flex-shrink: 0;
        }
        
        .table-section {
            flex: 1;
            margin-left: 20px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            background: white;
            height: 100%;
            overflow: auto;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 25px;
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea.form-control {
            resize: none;
        }
        
        .btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #718096;
            border: none;
        }
        
        .table-responsive {
            border-radius: 0;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            color: #4a5568;
            font-weight: 600;
            font-size: 13px;
            padding: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            font-size: 14px;
            color: #2d3748;
        }
        
        .table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }
        
        .table tbody tr:hover {
            background-color: #f7fafc;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
        }
        
        .badge.bg-success {
            background-color: #48bb78 !important;
        }
        
        .badge.bg-secondary {
            background-color: #a0aec0 !important;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 6px;
        }
        
        .btn-warning {
            background-color: #ffc107;
            border: none;
            color: #000;
        }
        
        .btn-danger {
            background-color: #ef4444;
            border: none;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px 20px;
            font-size: 14px;
        }
        
        .text-muted {
            color: #718096 !important;
        }
        
        .badge-counter {
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php include 'sidebar.php'; ?>
        
            <!-- Alert Messages -->
            <?php if ($success): ?>
            <div class="px-4 pt-3">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="px-4 pt-3">
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Main Layout -->
            <div class="layout-container">
                <!-- Form Section -->
                <div class="form-section">
                    <div class="card">
                        <div class="card-header">
                            <span>
                                <i class="fas fa-<?php echo $edit_data ? 'edit' : 'plus-circle'; ?> me-2"></i>
                                <?php echo $edit_data ? 'Edit Mitra' : 'Tambah Mitra'; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <?php if ($edit_data): ?>
                                <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label">Kode Mitra <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="kode_mitra" 
                                           value="<?php echo $edit_data ? htmlspecialchars($edit_data['kode_mitra']) : ''; ?>" 
                                           placeholder="APT001" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nama Apotek <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama_apotek" 
                                           value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama_apotek']) : ''; ?>" 
                                           placeholder="Nama apotek" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nama Pemilik <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama_pemilik" 
                                           value="<?php echo $edit_data ? htmlspecialchars($edit_data['nama_pemilik']) : ''; ?>" 
                                           placeholder="Nama pemilik" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Alamat <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="alamat" rows="3" 
                                              placeholder="Alamat lengkap" required><?php echo $edit_data ? htmlspecialchars($edit_data['alamat']) : ''; ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Telepon <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="telepon" 
                                           value="<?php echo $edit_data ? htmlspecialchars($edit_data['telepon']) : ''; ?>" 
                                           placeholder="08xxxxxxxxxx" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo $edit_data ? htmlspecialchars($edit_data['email']) : ''; ?>" 
                                           placeholder="email@apotek.com">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" name="status" required>
                                        <option value="aktif" <?php echo ($edit_data && $edit_data['status'] == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="nonaktif" <?php echo ($edit_data && $edit_data['status'] == 'nonaktif') ? 'selected' : ''; ?>>Non-Aktif</option>
                                    </select>
                                </div>
                                
                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" name="simpan" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Simpan
                                    </button>
                                    <?php if ($edit_data): ?>
                                    <a href="master_mitra.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Batal
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Table Section -->
                <div class="table-section">
                    <div class="card">
                        <div class="card-header">
                            <span>
                                <i class="fas fa-list me-2"></i>Daftar Mitra Apotek
                            </span>
                            <span class="badge-counter">
                                Total: <?php echo mysqli_num_rows($result_mitra); ?> Mitra
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 10%;">Kode</th>
                                            <th style="width: 28%;">Nama Apotek</th>
                                            <th style="width: 18%;">Pemilik</th>
                                            <th style="width: 15%;">Telepon</th>
                                            <th style="width: 12%;" class="text-center">Status</th>
                                            <th style="width: 17%;" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result_mitra) > 0): ?>
                                            <?php while ($row = mysqli_fetch_assoc($result_mitra)): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($row['kode_mitra']); ?></strong></td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($row['nama_apotek']); ?></strong>
                                                    </div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars(substr($row['alamat'], 0, 35)) . (strlen($row['alamat']) > 35 ? '...' : ''); ?>
                                                    </small>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['nama_pemilik']); ?></td>
                                                <td>
                                                    <i class="fas fa-phone me-1"></i>
                                                    <?php echo htmlspecialchars($row['telepon']); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($row['status'] == 'aktif'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check-circle me-1"></i>Aktif
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="badge bg-secondary">Non-Aktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="master_mitra.php?edit=<?php echo $row['id']; ?>" 
                                                       class="btn btn-sm btn-warning me-1" 
                                                       title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="master_mitra.php?hapus=<?php echo $row['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Yakin ingin menghapus mitra <?php echo htmlspecialchars($row['nama_apotek']); ?>?')" 
                                                       title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-5">
                                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                                    <p class="text-muted mb-0">Belum ada data mitra apotek</p>
                                                    <small class="text-muted">Tambahkan mitra baru menggunakan form di sebelah kiri</small>
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
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    </script>
</body>
</html>