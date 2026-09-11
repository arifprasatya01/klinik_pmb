<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

// Get data penjualan grosir
$query = "SELECT pg.*, ma.kode_mitra, ma.nama_apotek, ma.alamat as alamat_mitra, 
          ma.telepon as telepon_mitra, u.nama_lengkap as kasir
          FROM penjualan_grosir pg
          JOIN mitra_apotek ma ON pg.mitra_id = ma.id
          JOIN users u ON pg.kasir_id = u.id
          WHERE pg.id = '$id'";
$result = mysqli_query($conn, $query);
$penjualan = mysqli_fetch_assoc($result);

if (!$penjualan) {
    die("Data tidak ditemukan!");
}

// Get detail obat
$query_detail = "SELECT dpg.*, o.nama_obat, o.satuan
                 FROM detail_penjualan_grosir dpg
                 JOIN obat o ON dpg.obat_id = o.id
                 WHERE dpg.penjualan_id = '$id'";
$result_detail = mysqli_query($conn, $query_detail);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Penjualan Grosir</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            padding: 20px;
            font-size: 12px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .header h1 {
            font-size: 20px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 11px;
            line-height: 1.4;
        }
        
        .nota-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 11px;
        }
        
        .mitra-info {
            background: #f0f0f0;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        
        .mitra-info h3 {
            font-size: 13px;
            margin-bottom: 5px;
            border-bottom: 1px solid #999;
            padding-bottom: 3px;
        }
        
        .mitra-info p {
            font-size: 11px;
            line-height: 1.5;
            margin: 3px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        table th {
            background: #333;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        
        table td {
            padding: 6px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .total-section {
            margin-top: 20px;
            border-top: 2px solid #000;
            padding-top: 10px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 12px;
        }
        
        .total-row.grand-total {
            font-size: 16px;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .payment-info {
            background: #f9f9f9;
            padding: 10px;
            margin-top: 15px;
            border-radius: 5px;
        }
        
        .payment-info p {
            margin: 5px 0;
            font-size: 11px;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px dashed #999;
            text-align: center;
            font-size: 11px;
        }
        
        .signature {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        
        .signature div {
            text-align: center;
            width: 45%;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 60px;
            padding-top: 5px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #000;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="report-header" style="text-align: center;">
    <h1>KLINIK PMB IIS</h1>
    <p>Dusun Tamelang RT 16 RW 7 Bengle Majalaya</p>
</div>
        
        <!-- Nota Info -->
        <div class="nota-info">
            <div>
                <strong>FAKTUR PENJUALAN GROSIR</strong><br>
                No: <strong>GR-<?php echo str_pad($penjualan['id'], 5, '0', STR_PAD_LEFT); ?></strong>
            </div>
            <div style="text-align: right;">
                Tanggal: <strong><?php echo date('d/m/Y H:i', strtotime($penjualan['tgl_penjualan'])); ?></strong><br>
                Kasir: <strong><?php echo $penjualan['kasir']; ?></strong>
            </div>
        </div>
        
        <!-- Mitra Info -->
        <div class="mitra-info">
            <h3>Informasi Mitra</h3>
            <p><strong>Kode:</strong> <?php echo $penjualan['kode_mitra']; ?></p>
            <p><strong>Nama Apotek:</strong> <?php echo $penjualan['nama_apotek']; ?></p>
            <p><strong>Alamat:</strong> <?php echo $penjualan['alamat_mitra']; ?></p>
            <p><strong>Telepon:</strong> <?php echo $penjualan['telepon_mitra']; ?></p>
        </div>
        
        <!-- Detail Obat -->
        <table>
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="40%">Nama Obat</th>
                    <th width="15%" class="text-center">Jumlah</th>
                    <th width="20%" class="text-right">Harga Satuan</th>
                    <th width="20%" class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                $total = 0;
                while ($detail = mysqli_fetch_assoc($result_detail)): 
                    $total += $detail['subtotal'];
                ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td>
                        <strong><?php echo $detail['nama_obat']; ?></strong><br>
                        <small style="color: #666;">Harga Beli: <?php echo formatRupiah($detail['harga_beli']); ?></small>
                    </td>
                    <td class="text-center"><?php echo $detail['jumlah'] . ' ' . $detail['satuan']; ?></td>
                    <td class="text-right"><?php echo formatRupiah($detail['harga_jual']); ?></td>
                    <td class="text-right"><strong><?php echo formatRupiah($detail['subtotal']); ?></strong></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <!-- Total Section -->
        <div class="total-section">
            <div class="total-row grand-total">
                <span>TOTAL BAYAR:</span>
                <span><?php echo formatRupiah($penjualan['total_bayar']); ?></span>
            </div>
        </div>
        
        <!-- Payment Info -->
        <div class="payment-info">
            <p>
                <strong>Metode Pembayaran:</strong> 
                <span class="badge badge-<?php echo $penjualan['metode_bayar'] == 'hutang' ? 'warning' : 'success'; ?>">
                    <?php echo strtoupper($penjualan['metode_bayar']); ?>
                </span>
            </p>
            <p>
                <strong>Status Pembayaran:</strong> 
                <span class="badge badge-<?php echo $penjualan['status_bayar'] == 'lunas' ? 'success' : 'danger'; ?>">
                    <?php echo strtoupper($penjualan['status_bayar']); ?>
                </span>
            </p>
            
            <?php if ($penjualan['metode_bayar'] == 'hutang'): ?>
                <p><strong>Jatuh Tempo:</strong> <?php echo date('d/m/Y', strtotime($penjualan['jatuh_tempo'])); ?></p>
            <?php else: ?>
                <p><strong>Jumlah Bayar:</strong> <?php echo formatRupiah($penjualan['jumlah_bayar']); ?></p>
                <p><strong>Kembalian:</strong> <?php echo formatRupiah($penjualan['kembalian']); ?></p>
            <?php endif; ?>
            
            <?php if ($penjualan['keterangan']): ?>
            <p><strong>Keterangan:</strong> <?php echo $penjualan['keterangan']; ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Signature -->
        <div class="signature">
            <div>
                <p>Penerima,</p>
                <div class="signature-line">
                    ( _________________ )
                </div>
            </div>
            <div>
                <p>Hormat Kami,</p>
                <div class="signature-line">
                    <strong><?php echo $penjualan['kasir']; ?></strong>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>Harga Grosir: Harga Beli + 15%</strong></p>
            <p>Terima kasih atas kepercayaan Anda!</p>
            <p style="margin-top: 10px; font-size: 10px; color: #666;">
                Dicetak pada: <?php echo date('d/m/Y H:i:s'); ?>
            </p>
        </div>
        
        <!-- Print Button -->
        <div class="no-print" style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" style="padding: 10px 30px; font-size: 14px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px;">
                <i class="fas fa-print"></i> Print Nota
            </button>
            <button onclick="window.close()" style="padding: 10px 30px; font-size: 14px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 5px; margin-left: 10px;">
                Tutup
            </button>
        </div>
    </div>
    
    <script>
        // Auto print saat halaman dimuat (opsional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>