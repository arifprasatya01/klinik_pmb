<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'kasir' && $_SESSION['role'] != 'admin')) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

// Filter tanggal
$tanggal_dari = isset($_GET['dari']) ? $_GET['dari'] : date('Y-m-d');
$tanggal_sampai = isset($_GET['sampai']) ? $_GET['sampai'] : date('Y-m-d');

// Get data penjualan langsung
$query = "SELECT pl.*, u.nama_lengkap as nama_kasir
          FROM penjualan_langsung pl
          LEFT JOIN users u ON pl.kasir_id = u.id
          WHERE DATE(pl.tgl_penjualan) BETWEEN '$tanggal_dari' AND '$tanggal_sampai'
          ORDER BY pl.tgl_penjualan DESC";
$result = mysqli_query($conn, $query);

// Hitung total
$query_total = "SELECT 
                    COUNT(*) as total_transaksi,
                    COALESCE(SUM(total_bayar), 0) as total_pendapatan
                FROM penjualan_langsung
                WHERE DATE(tgl_penjualan) BETWEEN '$tanggal_dari' AND '$tanggal_sampai'";
$result_total = mysqli_query($conn, $query_total);
$row_total = mysqli_fetch_assoc($result_total);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Penjualan Langsung - Sistem Klinik</title>
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
        
        .stats-card {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <div class="col-md-10" style="margin-left: 250px;">
                <nav class="navbar navbar-light bg-white mb-4 shadow-sm">
                    <div class="container-fluid">
                        <span class="navbar-brand mb-0 h1">Riwayat Penjualan Langsung</span>
                        <div>
                            <span class="me-3">
                                <i class="fas fa-user-circle fa-lg text-primary"></i>
                                <strong class="ms-2"><?php echo $_SESSION['nama_lengkap']; ?></strong>
                            </span>
                            <span class="badge bg-primary"><?php echo ucfirst($_SESSION['role']); ?></span>
                        </div>
                    </div>
                </nav>
                
                <div class="container-fluid px-4">
                    <!-- Stats -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stats-card">
                                <h6 class="mb-1 opacity-75">Total Transaksi</h6>
                                <h2 class="mb-0"><?php echo $row_total['total_transaksi']; ?> Transaksi</h2>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stats-card">
                                <h6 class="mb-1 opacity-75">Total Pendapatan</h6>
                                <h2 class="mb-0"><?php echo formatRupiah($row_total['total_pendapatan']); ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Dari Tanggal</label>
                                    <input type="date" class="form-control" name="dari" value="<?php echo $tanggal_dari; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Sampai Tanggal</label>
                                    <input type="date" class="form-control" name="sampai" value="<?php echo $tanggal_sampai; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-2"></i>Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Table -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-shopping-cart me-2"></i>Daftar Transaksi
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>No. Nota</th>
                                            <th>Tanggal</th>
                                            <th>Pembeli</th>
                                            <th>Kasir</th>
                                            <th>Total</th>
                                            <th>Metode</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result) > 0): ?>
                                            <?php while ($row = mysqli_fetch_assoc($result)): 
                                                $no_nota = 'NT-' . date('Ymd', strtotime($row['tgl_penjualan'])) . '-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
                                            ?>
                                            <tr>
                                                <td><strong><?php echo $no_nota; ?></strong></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($row['tgl_penjualan'])); ?></td>
                                                <td><?php echo $row['nama_pembeli']; ?></td>
                                                <td><?php echo $row['nama_kasir']; ?></td>
                                                <td><span class="badge bg-success"><?php echo formatRupiah($row['total_bayar']); ?></span></td>
                                                <td><span class="badge bg-info"><?php echo strtoupper($row['metode_bayar']); ?></span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" onclick="printNota(<?php echo $row['id']; ?>)" title="Print Nota">
                                                        <i class="fas fa-print me-1"></i>Print
                                                    </button>
                                                    <button class="btn btn-sm btn-info" onclick="lihatDetail(<?php echo $row['id']; ?>)" title="Lihat Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    Tidak ada transaksi pada periode ini
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
    
    <!-- Modal Detail -->
    <div class="modal fade" id="modalDetail" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Detail Transaksi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printNota(penjualan_id) {
            var printWindow = window.open('print_nota.php?id=' + penjualan_id, '_blank', 'width=800,height=600');
            
            if (!printWindow) {
                alert('Popup diblokir! Silakan aktifkan popup untuk browser Anda.');
                window.location.href = 'print_nota.php?id=' + penjualan_id;
            }
        }
        
        function lihatDetail(penjualan_id) {
            $.ajax({
                url: 'ajax/get_detail_penjualan.php',
                method: 'GET',
                data: { id: penjualan_id },
                success: function(response) {
                    $('#detailContent').html(response);
                    $('#modalDetail').modal('show');
                },
                error: function() {
                    alert('Gagal memuat detail transaksi');
                }
            });
        }
    </script>
</body>
</html>