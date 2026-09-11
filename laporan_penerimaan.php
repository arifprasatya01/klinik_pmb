<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'gudang')) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

// Ambil parameter periode
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

// Sanitize input
$tgl_awal = mysqli_real_escape_string($conn, $tgl_awal);
$tgl_akhir = mysqli_real_escape_string($conn, $tgl_akhir);

// Query data penerimaan obat per supplier
$query = "SELECT 
            p.id,
            p.no_pembelian,
            p.tgl_pembelian,
            s.kode_supplier,
            s.nama_supplier,
            p.total_dengan_ppn AS total_pembelian,
            p.status_pembayaran
          FROM pembelian p
          INNER JOIN supplier s ON p.supplier_id = s.id
          WHERE DATE(p.tgl_pembelian) BETWEEN '$tgl_awal' AND '$tgl_akhir'
          ORDER BY s.nama_supplier ASC, p.tgl_pembelian ASC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Error query: " . mysqli_error($conn));
}

// Group data by supplier
$data_per_supplier = [];
$total_keseluruhan = 0;
$total_transaksi = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $supplier = $row['nama_supplier'];
    if (!isset($data_per_supplier[$supplier])) {
        $data_per_supplier[$supplier] = [
            'kode_supplier' => $row['kode_supplier'],
            'items' => [],
            'total' => 0,
            'jumlah_transaksi' => 0
        ];
    }
    $data_per_supplier[$supplier]['items'][] = $row;
    $data_per_supplier[$supplier]['total'] += $row['total_pembelian'];
    $data_per_supplier[$supplier]['jumlah_transaksi']++;
    $total_keseluruhan += $row['total_pembelian'];
    $total_transaksi++;
}

// Fungsi format tanggal Indonesia
function tanggal_indo($tanggal) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $pecahkan = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}

// Fungsi status pembayaran
function status_bayar($status) {
    $status_label = [
        'belum_bayar' => '<span style="color: #f44336; font-weight: bold;">Belum Bayar</span>',
        'dibayar_sebagian' => '<span style="color: #FF9800; font-weight: bold;">Dibayar Sebagian</span>',
        'lunas' => '<span style="color: #4CAF50; font-weight: bold;">Lunas</span>'
    ];
    return isset($status_label[$status]) ? $status_label[$status] : $status;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pembelian Obat Supplier - Healoka</title>
    
        <!-- Favicon -->
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link rel="shortcut icon" type="image/png" href ="asset/img/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <?php include 'sidebar_style.php'; ?>
    
    <style>
        /* ========== CSS Variables ========== */
        :root {
            --primary-color: #9C27B0;
            --secondary-color: #7B1FA2;
            --light-purple: #E1BEE7;
            --lighter-purple: #F3E5F5;
        }
        
        /* ========== Global Styles ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* ========== Layout Structure ========== */
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
        
        /* ========== Main Container ========== */
        .main-container {
            max-width: 100%;
            margin: 20px;
            background-color: white;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 15px;
        }
        
        /* ========== Header ========== */
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #333;
            padding-bottom: 20px;
        }
        
        .report-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
            color: #1f2937;
            font-weight: 700;
        }
        
        .report-header h2 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #495057;
            font-weight: 600;
        }
        
        .report-header p {
            font-size: 14px;
            color: #6c757d;
        }
        
        /* ========== Filter Form ========== */
        .filter-form {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .filter-form label {
            margin-right: 10px;
            font-weight: 600;
            color: #495057;
        }
        
        .filter-form input[type="date"] {
            padding: 8px 12px;
            margin-right: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            transition: border-color 0.3s;
        }
        
        .filter-form input[type="date"]:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .filter-form button {
            padding: 8px 24px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .filter-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(156, 39, 176, 0.4);
        }
        
        /* ========== Action Buttons ========== */
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .back-btn a {
            padding: 10px 30px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 16px;
            display: inline-block;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .back-btn a:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        
        .print-btn button {
            padding: 10px 30px;
            background: linear-gradient(135deg, #2196F3 0%, #0b7dda 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .print-btn button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
        }
        
        /* ========== Periode Info ========== */
        .periode-info {
            text-align: center;
            margin-bottom: 30px;
            font-size: 16px;
            color: #1f2937;
            font-weight: 600;
        }
        
        /* ========== Supplier Section ========== */
        .supplier-section {
            margin-bottom: 40px;
            border: 2px solid var(--primary-color);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            page-break-inside: avoid;
        }
        
        .supplier-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 15px 20px;
            font-size: 18px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .supplier-code {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        /* ========== Table Styles ========== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background-color: var(--light-purple);
            color: #333;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #ddd;
            font-size: 14px;
        }
        
        .data-table td {
            padding: 10px;
            border: 1px solid #ddd;
            font-size: 13px;
            color: #1f2937;
        }
        
        .data-table tbody tr {
            transition: background-color 0.2s;
        }
        
        .data-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .data-table tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }
        
        /* ========== Subtotal Row ========== */
        .subtotal-row {
            background-color: var(--lighter-purple) !important;
            font-weight: bold;
        }
        
        .subtotal-row td {
            border-top: 3px solid var(--primary-color) !important;
            padding: 15px 10px;
            font-size: 14px;
        }
        
        /* ========== Total Section ========== */
        .total-keseluruhan {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-top: 30px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(156, 39, 176, 0.4);
        }
        
        .total-keseluruhan h3 {
            font-size: 18px;
            margin-bottom: 15px;
            opacity: 0.9;
        }
        
        .total-keseluruhan .amount {
            font-size: 36px;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        /* ========== Summary Table ========== */
        .summary-table {
            margin-top: 30px;
        }
        
        .summary-table h3 {
            margin-bottom: 15px;
            color: #333;
            padding-left: 10px;
            border-left: 4px solid var(--primary-color);
            font-size: 20px;
            font-weight: 700;
        }
        
        .summary-table table th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }
        
        .transaction-badge {
            background: var(--light-purple);
            padding: 5px 15px;
            border-radius: 15px;
            font-weight: bold;
            display: inline-block;
        }
        
        /* ========== Summary Cards Grid ========== */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        
        .summary-card {
            background: linear-gradient(135deg, var(--light-purple) 0%, #CE93D8 100%);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            border: 2px solid var(--primary-color);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(156, 39, 176, 0.3);
        }
        
        .summary-card h4 {
            font-size: 14px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .summary-card .number {
            font-size: 28px;
            font-weight: bold;
            color: var(--secondary-color);
        }
        
        .summary-card small {
            color: #666;
        }
        
        /* ========== Summary Section ========== */
        .summary-section {
            margin-top: 30px;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 5px solid var(--primary-color);
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .summary-section h3 {
            margin-bottom: 15px;
            color: #1f2937;
            font-size: 20px;
            font-weight: 700;
        }
        
        .summary-section p {
            font-size: 16px;
            line-height: 1.8;
            color: #495057;
            margin-bottom: 15px;
        }
        
        /* ========== Empty Data ========== */
        .empty-data {
            text-align: center;
            padding: 50px;
            color: #6c757d;
        }
        
        .empty-data i {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
            color: #dee2e6;
        }
        
        .empty-data h3 {
            color: #495057;
            margin-bottom: 10px;
        }
        
        .empty-data p {
            color: #6c757d;
        }
        
        /* ========== Utility Classes ========== */
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        /* ========== Print Styles ========== */
        @media print {
            .filter-form, 
            .action-buttons,
            .print-btn, 
            .back-btn,
            .sidebar,
            .col-md-2 {
                display: none !important;
            }
            
            .col-md-10 {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            body {
                background-color: white;
                padding: 0;
            }
            
            .main-container {
                box-shadow: none;
                padding: 20px;
                margin: 0;
            }
            
            .data-table th, 
            .data-table td {
                font-size: 11px;
                padding: 6px;
            }
            
            .supplier-section {
                page-break-inside: avoid;
            }
            
            /* Hitam Putih untuk Print */
            * {
                color: #000 !important;
                background: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .supplier-header {
                background: white !important;
                color: #000 !important;
                border: 2px solid #000 !important;
                border-bottom: none !important;
            }
            
            .supplier-code {
                background: #f0f0f0 !important;
                color: #000 !important;
                border: 1px solid #000 !important;
            }
            
            .supplier-section {
                border: 2px solid #000 !important;
            }
            
            .data-table th {
                background: #f0f0f0 !important;
                color: #000 !important;
                border: 1px solid #000 !important;
            }
            
            .data-table td {
                border: 1px solid #000 !important;
            }
            
            .subtotal-row {
                background: #f5f5f5 !important;
            }
            
            .subtotal-row td {
                border-top: 3px solid #000 !important;
            }
            
            .total-keseluruhan {
                background: white !important;
                color: #000 !important;
                border: 3px solid #000 !important;
                box-shadow: none !important;
            }
            
            .total-keseluruhan .amount {
                color: #000 !important;
                text-shadow: none !important;
            }
            
            .summary-card {
                background: white !important;
                border: 2px solid #000 !important;
                color: #000 !important;
            }
            
            .summary-card .number {
                color: #000 !important;
            }
            
            .summary-table table th {
                background: #f0f0f0 !important;
                color: #000 !important;
            }
            
            .summary-section {
                background: white !important;
                border-left: 4px solid #000 !important;
            }
            
            .report-header {
                border-bottom: 3px solid #000 !important;
            }
            
            .transaction-badge {
                background: #f0f0f0 !important;
                border: 1px solid #000 !important;
            }
            
            /* Status Pembayaran */
            span[style*="color"] {
                color: #000 !important;
                font-weight: bold !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>
            
            <!-- Main Content -->
            
                <div class="main-container">
                    
                    <!-- Form Filter -->
                    <div class="filter-form">
                        <form method="GET" action="">
                            <label>Tanggal Awal:</label>
                            <input type="date" name="tgl_awal" value="<?php echo $tgl_awal; ?>" required>
                            
                            <label>Tanggal Akhir:</label>
                            <input type="date" name="tgl_akhir" value="<?php echo $tgl_akhir; ?>" required>
                            
                            <button type="submit"><i class="fas fa-search me-2"></i>Tampilkan</button>
                        </form>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <div class="back-btn">
                            <a href="dashboard.php"><i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard</a>
                        </div>
                        <div class="print-btn">
                            <button onclick="window.print()"><i class="fas fa-print me-2"></i>Cetak Laporan</button>
                        </div>
                    </div>

                    <!-- Header Laporan -->
                    <div class="report-header">
                        <h1>KLINIK PMB IIS</h1>
                        <h2>LAPORAN REGISTRASI KUNJUNGAN PASIEN</h2>
                        <p>Dusun Tamelang RT 16 RW 7 Bengle Majalaya</p>
                    </div>

                    <!-- Info Periode -->
                    <div class="periode-info">
                        <strong>Periode: <?php echo tanggal_indo($tgl_awal); ?> s/d <?php echo tanggal_indo($tgl_akhir); ?></strong>
                    </div>

                    <?php if (count($data_per_supplier) > 0): ?>
                        <!-- Data Per Supplier -->
                        <?php foreach ($data_per_supplier as $nama_supplier => $data): ?>
                        <div class="supplier-section">
                            <div class="supplier-header">
                                <span><i class="fas fa-box me-2"></i><?php echo $nama_supplier; ?></span>
                                <span class="supplier-code"><?php echo $data['kode_supplier']; ?></span>
                            </div>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;" class="text-center">No</th>
                                        <th style="width: 20%;">No. Pembelian</th>
                                        <th style="width: 15%;" class="text-center">Tanggal</th>
                                        <th style="width: 20%;" class="text-center">Status Pembayaran</th>
                                        <th style="width: 20%;" class="text-right">Total Pembelian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; ?>
                                    <?php foreach ($data['items'] as $item): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $no++; ?></td>
                                        <td><?php echo $item['no_pembelian']; ?></td>
                                        <td class="text-center"><?php echo date('d/m/Y', strtotime($item['tgl_pembelian'])); ?></td>
                                        <td class="text-center"><?php echo status_bayar($item['status_pembayaran']); ?></td>
                                        <td class="text-right"><strong><?php echo formatRupiah($item['total_pembelian']); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="subtotal-row">
                                        <td colspan="4" class="text-right">
                                            <strong>SUBTOTAL <?php echo strtoupper($nama_supplier); ?>:</strong>
                                        </td>
                                        <td class="text-right">
                                            <strong style="font-size: 15px;"><?php echo formatRupiah($data['total']); ?></strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php endforeach; ?>

                        <!-- Total Keseluruhan -->
                        <div class="total-keseluruhan">
                            <h3><i class="fas fa-money-bill-wave me-2"></i>TOTAL KESELURUHAN PENERIMAAN OBAT</h3>
                            <div class="amount"><?php echo formatRupiah($total_keseluruhan); ?></div>
                        </div>

                        <!-- Ringkasan Per Supplier -->
                        <div class="summary-table">
                            <h3><i class="fas fa-chart-bar me-2"></i>RINGKASAN PER SUPPLIER</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;" class="text-center">No</th>
                                        <th style="width: 15%;">Kode Supplier</th>
                                        <th style="width: 35%;">Nama Supplier</th>
                                        <th style="width: 20%;" class="text-center">Jumlah Transaksi</th>
                                        <th style="width: 25%;" class="text-right">Total Pembelian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; ?>
                                    <?php foreach ($data_per_supplier as $nama_supplier => $data): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $no++; ?></td>
                                        <td><?php echo $data['kode_supplier']; ?></td>
                                        <td><strong><?php echo $nama_supplier; ?></strong></td>
                                        <td class="text-center">
                                            <span class="transaction-badge">
                                                <?php echo $data['jumlah_transaksi']; ?> transaksi
                                            </span>
                                        </td>
                                        <td class="text-right"><strong><?php echo formatRupiah($data['total']); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Kesimpulan -->
                        <div class="summary-section">
                            <h3><i class="fas fa-clipboard-check me-2"></i>KESIMPULAN</h3>
                            <p>
                                Berdasarkan laporan penerimaan obat pada periode 
                                <strong><?php echo tanggal_indo($tgl_awal); ?></strong> sampai dengan 
                                <strong><?php echo tanggal_indo($tgl_akhir); ?></strong>, berikut adalah ringkasan:
                            </p>
                            
                            <div class="summary-grid">
                                <div class="summary-card">
                                    <h4>Total Supplier</h4>
                                    <div class="number"><?php echo count($data_per_supplier); ?></div>
                                    <small>Supplier</small>
                                </div>
                                <div class="summary-card">
                                    <h4>Total Transaksi</h4>
                                    <div class="number"><?php echo $total_transaksi; ?></div>
                                    <small>Transaksi</small>
                                </div>
                                <div class="summary-card">
                                    <h4>Total Pembelian</h4>
                                    <div class="number" style="font-size: 20px;"><?php echo formatRupiah($total_keseluruhan); ?></div>
                                </div>
                            </div>
                            
                            <p style="margin-top: 15px;">
                                Laporan ini mencakup semua pembelian obat dari berbagai supplier yang telah diterima oleh klinik. 
                                Data ini dapat digunakan untuk evaluasi pengeluaran, manajemen hubungan dengan supplier, 
                                dan perencanaan pengadaan obat di masa mendatang.
                            </p>
                        </div>

                    <?php else: ?>
                        <div class="empty-data">
                            <i class="fas fa-box-open"></i>
                            <h3>Tidak ada data penerimaan obat pada periode ini</h3>
                            <p>Silakan pilih periode lain atau tambahkan data pembelian obat.</p>
                        </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>