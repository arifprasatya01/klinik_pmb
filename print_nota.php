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

$penjualan_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

// Get data penjualan
$query = "SELECT pl.*, u.nama_lengkap as kasir 
          FROM penjualan_langsung pl
          JOIN users u ON pl.kasir_id = u.id
          WHERE pl.id = '$penjualan_id'";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    die("Data tidak ditemukan!");
}

$penjualan = mysqli_fetch_assoc($result);

// Get detail penjualan
$query_detail = "SELECT dpl.*, o.nama_obat, o.satuan, o.kode_obat
                 FROM detail_penjualan_langsung dpl
                 JOIN obat o ON dpl.obat_id = o.id
                 WHERE dpl.penjualan_id = '$penjualan_id'
                 ORDER BY o.nama_obat ASC";
$result_detail = mysqli_query($conn, $query_detail);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Penjualan - <?php echo $penjualan['id']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', 'Courier', monospace;
            font-size: 13px;
            font-weight: 700;
            padding: 20px;
            max-width: 80mm;
            margin: 0 auto;
            color: #000;
            background: #fff;
            line-height: 1.4;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .header h2 {
            font-size: 18px;
            font-weight: 900;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }
        
        .header p {
            font-size: 12px;
            font-weight: 600;
            margin: 2px 0;
        }
        
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        
        .divider-double {
            border-top: 3px double #000;
            margin: 10px 0;
        }
        
        .info-table {
            width: 100%;
            margin: 10px 0;
        }
        
        .info-table td {
            padding: 3px 0;
            font-weight: 700;
        }
        
        .info-table td:first-child {
            width: 35%;
        }
        
        .info-table td:nth-child(2) {
            width: 5%;
            text-align: center;
        }
        
        .info-table td:last-child {
            width: 60%;
        }
        
        .items-header {
            width: 100%;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            margin: 10px 0 5px 0;
            padding: 8px 0;
        }
        
        .items-header-row {
            display: flex;
            font-weight: 900;
            font-size: 12px;
        }
        
        .col-item { 
            width: 38%; 
            text-align: left;
            word-wrap: break-word;
            word-break: break-word;
            padding-right: 10px;
        }
        .col-qty { 
            width: 16%; 
            text-align: center;
            white-space: nowrap;
            padding: 0 8px;
        }
        .col-price { 
            width: 23%; 
            text-align: right;
            white-space: nowrap;
            padding-right: 12px;
            font-size: 12px;
        }
        .col-total { 
            width: 23%; 
            text-align: right;
            white-space: nowrap;
            font-size: 12px;
        }
        
        .item-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px dotted #999;
            font-weight: 700;
            align-items: flex-start;
        }
        
        .item-name {
            font-weight: 800;
            margin-bottom: 2px;
            line-height: 1.3;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
        }
        
        .item-code {
            font-size: 10px;
            color: #666;
            font-weight: 600;
            display: block;
            margin-top: 2px;
        }
        
        .total-section {
            margin: 10px 0;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-weight: 800;
        }
        
        .total-row.subtotal {
            border-top: 1px dashed #000;
            padding-top: 8px;
        }
        
        .total-row.grand {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 8px 0;
            font-size: 15px;
            font-weight: 900;
            margin: 5px 0;
        }
        
        .payment-section {
            margin: 10px 0;
        }
        
        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-weight: 800;
        }
        
        .payment-row.change {
            border-top: 1px dashed #000;
            padding-top: 8px;
            margin-top: 5px;
            font-size: 14px;
        }
        
        .footer {
            text-align: center;
            margin-top: 15px;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }
        
        .footer p {
            font-weight: 700;
            margin: 3px 0;
            font-size: 11px;
        }
        
        .footer .notice {
            font-weight: 900;
            margin-top: 10px;
            font-size: 10px;
        }
        
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .no-print button {
            padding: 12px 30px;
            font-size: 14px;
            cursor: pointer;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            transition: all 0.3s;
        }
        
        .no-print button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-print {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-close {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            margin-left: 10px;
        }
        
        @media print {
            body {
                padding: 5px;
            }
            
            .no-print {
                display: none !important;
            }
            
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print"></i> Cetak Nota
        </button>
        <button onclick="window.close()" class="btn-close">
            <i class="fas fa-times"></i> Tutup
        </button>
    </div>
    
    <!-- HEADER -->
    <div class="report-header" style="text-align: center;">
    <h1>KLINIK PMB IIS</h1>
    <p>Dusun Tamelang RT 16 RW 7 Bengle Majalaya</p>
</div>
    
    <div class="divider-double"></div>
    
    <!-- INFO TRANSAKSI -->
    <table class="info-table">
        <tr>
            <td>No. Transaksi</td>
            <td>:</td>
            <td>PL-<?php echo str_pad($penjualan['id'], 6, '0', STR_PAD_LEFT); ?></td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>:</td>
            <td><?php echo date('d/m/Y H:i', strtotime($penjualan['tgl_penjualan'])); ?></td>
        </tr>
        <tr>
            <td>Kasir</td>
            <td>:</td>
            <td><?php echo $penjualan['kasir']; ?></td>
        </tr>
        <tr>
            <td>Pembeli</td>
            <td>:</td>
            <td><?php echo $penjualan['nama_pembeli']; ?></td>
        </tr>
    </table>
    
    <div class="divider-double"></div>
    
    <!-- HEADER ITEMS -->
    <div class="items-header">
        <div class="items-header-row">
            <div class="col-item">Item</div>
            <div class="col-qty">Qty</div>
            <div class="col-price">Harga</div>
            <div class="col-total">Subtotal</div>
        </div>
    </div>
    
    <!-- DETAIL ITEMS -->
    <?php 
    $total = 0;
    while ($detail = mysqli_fetch_assoc($result_detail)): 
        $total += $detail['subtotal'];
    ?>
    <div class="item-row">
        <div class="col-item">
            <div class="item-name"><?php echo $detail['nama_obat']; ?></div>
            <div class="item-code"><?php echo $detail['satuan']; ?></div>
        </div>
        <div class="col-qty"><?php echo $detail['jumlah']; ?></div>
        <div class="col-price"><?php echo number_format($detail['harga'], 0, ',', '.'); ?></div>
        <div class="col-total"><?php echo number_format($detail['subtotal'], 0, ',', '.'); ?></div>
    </div>
    <?php endwhile; ?>
    
    <div class="divider-double"></div>
    
    <!-- TOTAL -->
    <div class="total-section">
        <div class="total-row subtotal">
            <span>Subtotal:</span>
            <span>Rp <?php echo number_format($penjualan['total_bayar'], 0, ',', '.'); ?></span>
        </div>
        <div class="total-row grand">
            <span>TOTAL:</span>
            <span>Rp <?php echo number_format($penjualan['total_bayar'], 0, ',', '.'); ?></span>
        </div>
    </div>
    
    <div class="divider-double"></div>
    
    <!-- PEMBAYARAN -->
    <div class="payment-section">
        <div class="payment-row">
            <span>Metode Bayar:</span>
            <span><?php echo strtoupper($penjualan['metode_bayar']); ?></span>
        </div>
        <div class="payment-row">
            <span>Jumlah Bayar:</span>
            <span>Rp <?php echo number_format($penjualan['jumlah_bayar'], 0, ',', '.'); ?></span>
        </div>
        <div class="payment-row change">
            <span>Kembalian:</span>
            <span>Rp <?php echo number_format($penjualan['kembalian'], 0, ',', '.'); ?></span>
        </div>
    </div>
    
    <div class="divider-double"></div>
    
    <!-- FOOTER -->
    <div class="footer">
        <p>Terima kasih atas kunjungan Anda</p>
        <p>Semoga lekas sembuh</p>
        <p class="notice">*** NOTA INI ADALAH BUKTI PEMBAYARAN SAH ***</p>
    </div>
    
    <script>
        // Auto print saat halaman dibuka (optional)
        window.onload = function() {
            // Uncomment baris berikut jika ingin auto print
            // setTimeout(function() { window.print(); }, 300);
        }
    </script>
</body>
</html>