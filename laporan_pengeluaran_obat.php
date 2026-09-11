<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php'; // load .env via loadEnv() so kredensial sama dengan semua halaman lain

$host     = $_ENV['DB_HOST']     ?? 'localhost';
$username = $_ENV['DB_USERNAME'] ?? '';
$password = $_ENV['DB_PASSWORD'] ?? '';
$database = $_ENV['DB_DATABASE'] ?? '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Koneksi database gagal (laporan_pengeluaran_obat.php): " . $e->getMessage());
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

$tgl_awal  = isset($_GET['tgl_awal'])  ? $_GET['tgl_awal']  : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

// ─── 1. Resep ────────────────────────────────────────────────────────────────
$query_resep = "
    SELECT
        dr.id,
        r.tgl_resep          AS tanggal,
        pas.no_rm,
        pas.nama_lengkap     AS nama_pasien,
        o.kode_obat,
        o.nama_obat,
        dr.jumlah,
        o.harga_jual         AS harga_satuan,
        (dr.jumlah * o.harga_jual) AS subtotal
    FROM detail_resep dr
    INNER JOIN resep r          ON dr.resep_id        = r.id
    INNER JOIN pemeriksaan pemx ON r.pemeriksaan_id   = pemx.id
    INNER JOIN pendaftaran pend ON pemx.pendaftaran_id = pend.id
    INNER JOIN pasien pas       ON pend.pasien_id      = pas.id
    INNER JOIN obat o           ON dr.obat_id          = o.id
    WHERE r.hapus = 0
      AND DATE(r.tgl_resep) BETWEEN :tgl_awal AND :tgl_akhir
    ORDER BY r.tgl_resep ASC, pas.nama_lengkap ASC";

$stmt_resep = $conn->prepare($query_resep);
$stmt_resep->bindParam(':tgl_awal',  $tgl_awal);
$stmt_resep->bindParam(':tgl_akhir', $tgl_akhir);
$stmt_resep->execute();
$data_resep = $stmt_resep->fetchAll(PDO::FETCH_ASSOC);

// ─── 2. Penjualan Langsung ───────────────────────────────────────────────────
$query_langsung = "
    SELECT
        dp.id,
        p.tgl_penjualan        AS tanggal,
        p.id                   AS no_transaksi,
        o.kode_obat,
        o.nama_obat,
        dp.jumlah,
        dp.harga               AS harga_satuan,
        (dp.jumlah * dp.harga) AS subtotal
    FROM detail_penjualan_langsung dp
    INNER JOIN penjualan_langsung p ON dp.penjualan_id = p.id
    INNER JOIN obat o               ON dp.obat_id      = o.id
    WHERE p.status = 'lunas'
      AND DATE(p.tgl_penjualan) BETWEEN :tgl_awal2 AND :tgl_akhir2
    ORDER BY p.tgl_penjualan ASC, p.id ASC";

$stmt_langsung = $conn->prepare($query_langsung);
$stmt_langsung->bindParam(':tgl_awal2',  $tgl_awal);
$stmt_langsung->bindParam(':tgl_akhir2', $tgl_akhir);
$stmt_langsung->execute();
$data_langsung = $stmt_langsung->fetchAll(PDO::FETCH_ASSOC);

// ─── 3. BHP (Bahan Habis Pakai) ───────────────────────────────────────────────
// Catatan: detail_bhp tidak punya kolom tanggal sendiri, jadi tanggal diambil
// dari pemeriksaan.tgl_pemeriksaan (tanggal pemeriksaan berlangsung).
$query_bhp = "
    SELECT
        db.id,
        pemx.tgl_pemeriksaan  AS tanggal,
        pas.no_rm,
        pas.nama_lengkap      AS nama_pasien,
        mb.nama_bhp,
        mb.satuan,
        db.jumlah,
        db.harga              AS harga_satuan,
        db.subtotal
    FROM detail_bhp db
    INNER JOIN pemeriksaan pemx ON db.pemeriksaan_id  = pemx.id
    INNER JOIN pendaftaran pend ON pemx.pendaftaran_id = pend.id
    INNER JOIN pasien pas       ON pend.pasien_id      = pas.id
    INNER JOIN master_bhp mb    ON db.bhp_id           = mb.id
    WHERE db.hapus = 0
      AND DATE(pemx.tgl_pemeriksaan) BETWEEN :tgl_awal3 AND :tgl_akhir3
    ORDER BY pemx.tgl_pemeriksaan ASC, pas.nama_lengkap ASC";

$stmt_bhp = $conn->prepare($query_bhp);
$stmt_bhp->bindParam(':tgl_awal3',  $tgl_awal);
$stmt_bhp->bindParam(':tgl_akhir3', $tgl_akhir);
$stmt_bhp->execute();
$data_bhp = $stmt_bhp->fetchAll(PDO::FETCH_ASSOC);

// ─── 4. Total ────────────────────────────────────────────────────────────────
$total_resep       = array_sum(array_column($data_resep,    'subtotal'));
$total_langsung    = array_sum(array_column($data_langsung, 'subtotal'));
$total_bhp         = array_sum(array_column($data_bhp,      'subtotal'));
$total_keseluruhan = $total_resep + $total_langsung + $total_bhp;

function tanggal_indo($tanggal) {
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $p = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $p[2] . ' ' . $bulan[(int)$p[1]] . ' ' . $p[0];
}
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pengeluaran Obat - Healoka</title>
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link rel="shortcut icon" type="image/png" href="asset/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php include 'sidebar_style.php'; ?>
    <style>
        :root {
            --primary-color:#FF9800; --secondary-color:#F57C00;
            --light-orange:#FFE0B2; --lighter-orange:#FFF3E0;
            --green:#4CAF50; --dark-green:#388E3C;
            --light-green:#C8E6C9; --lighter-green:#E8F5E9;
            --blue:#2196F3; --dark-blue:#0b7dda;
            --purple:#9C27B0; --dark-purple:#7B1FA2;
            --light-purple:#E1BEE7; --lighter-purple:#F3E5F5;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#f8f9fa;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;}
        .container-fluid{padding:0;margin:0;}
        .row{margin:0;display:flex;}
        .col-md-2{flex:0 0 250px;max-width:250px;}
        .col-md-10{flex:1;margin-left:250px;padding:0;}
        .main-container{max-width:100%;margin:20px;background:#fff;padding:30px;box-shadow:0 2px 10px rgba(0,0,0,.05);border-radius:15px;}
        .report-header{text-align:center;margin-bottom:30px;border-bottom:3px solid #333;padding-bottom:20px;}
        .report-header h1{font-size:24px;color:#1f2937;font-weight:700;margin-bottom:5px;}
        .report-header h2{font-size:20px;color:#495057;font-weight:600;margin-bottom:10px;}
        .report-header p{font-size:14px;color:#6c757d;}
        .filter-form{margin-bottom:20px;padding:15px;background:#f8f9fa;border-radius:10px;box-shadow:0 2px 5px rgba(0,0,0,.05);}
        .filter-form label{margin-right:10px;font-weight:600;color:#495057;}
        .filter-form input[type="date"]{padding:8px 12px;margin-right:10px;border:1px solid #dee2e6;border-radius:8px;}
        .filter-form button{padding:8px 24px;background:linear-gradient(135deg,var(--primary-color),var(--secondary-color));color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:500;}
        .filter-form button:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(255,152,0,.4);}
        .action-buttons{display:flex;justify-content:space-between;margin-bottom:20px;}
        .back-btn a{padding:10px 30px;background:#6c757d;color:#fff;text-decoration:none;border-radius:10px;font-size:16px;display:inline-block;font-weight:500;}
        .back-btn a:hover{background:#5a6268;transform:translateY(-2px);}
        .print-btn button{padding:10px 30px;background:linear-gradient(135deg,var(--blue),var(--dark-blue));color:#fff;border:none;border-radius:10px;cursor:pointer;font-size:16px;font-weight:500;}
        .print-btn button:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(33,150,243,.4);}
        .periode-info{text-align:center;margin-bottom:20px;font-size:16px;color:#1f2937;font-weight:600;}

        .nav-tabs-laporan{border-bottom:2px solid #dee2e6;margin-bottom:20px;}
        .nav-tabs-laporan .nav-link{color:#495057;font-weight:600;border:none;border-bottom:3px solid transparent;border-radius:0;padding:10px 20px;}
        .nav-tabs-laporan .nav-link.active{color:var(--primary-color);border-bottom-color:var(--primary-color);background:transparent;}

        .section-title{display:flex;align-items:center;gap:10px;font-size:17px;font-weight:700;padding:10px 15px;border-radius:10px 10px 0 0;margin-bottom:0;color:#fff;}
        .section-title.resep{background:linear-gradient(135deg,var(--primary-color),var(--secondary-color));}
        .section-title.langsung{background:linear-gradient(135deg,var(--green),var(--dark-green));}
        .section-title.bhp{background:linear-gradient(135deg,var(--purple),var(--dark-purple));}
        .data-table{width:100%;border-collapse:collapse;margin-bottom:30px;}
        .data-table th{padding:11px;text-align:left;font-weight:600;border:1px solid #ddd;font-size:13px;color:#fff;}
        .data-table.resep th{background:linear-gradient(135deg,var(--primary-color),var(--secondary-color));}
        .data-table.langsung th{background:linear-gradient(135deg,var(--green),var(--dark-green));}
        .data-table.bhp th{background:linear-gradient(135deg,var(--purple),var(--dark-purple));}
        .data-table td{padding:9px;border:1px solid #ddd;font-size:13px;color:#1f2937;}
        .data-table tbody tr:hover{background:#f8f9fa;}
        .data-table tbody tr:nth-child(even){background:#fafbfc;}
        .total-row.resep td{background:var(--lighter-orange)!important;border-top:3px solid var(--primary-color)!important;}
        .total-row.langsung td{background:var(--lighter-green)!important;border-top:3px solid var(--green)!important;}
        .total-row.bhp td{background:var(--lighter-purple)!important;border-top:3px solid var(--purple)!important;}
        .total-row td{padding:13px 9px;font-size:14px;font-weight:bold;}
        .summary-section{margin-top:30px;padding:25px;background:linear-gradient(135deg,#f8f9fa,#e9ecef);border-left:5px solid var(--primary-color);border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,.05);}
        .summary-section h3{margin-bottom:15px;color:#1f2937;font-size:20px;font-weight:700;}
        .summary-section p{font-size:15px;line-height:1.8;color:#495057;margin-bottom:10px;}
        .summary-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin:20px 0;}
        .summary-card{padding:18px;border-radius:15px;text-align:center;transition:transform .3s,box-shadow .3s;}
        .summary-card:hover{transform:translateY(-5px);box-shadow:0 10px 25px rgba(0,0,0,.15);}
        .summary-card.orange{background:linear-gradient(135deg,var(--light-orange),var(--lighter-orange));border:2px solid var(--primary-color);}
        .summary-card.green{background:linear-gradient(135deg,var(--light-green),var(--lighter-green));border:2px solid var(--green);}
        .summary-card.purple{background:linear-gradient(135deg,var(--light-purple),var(--lighter-purple));border:2px solid var(--purple);}
        .summary-card h4{font-size:13px;color:#333;margin-bottom:8px;font-weight:600;}
        .summary-card .number{font-size:22px;font-weight:bold;}
        .summary-card.orange .number{color:var(--secondary-color);}
        .summary-card.green .number{color:var(--dark-green);}
        .summary-card.purple .number{color:var(--dark-purple);}
        .summary-card small{color:#666;font-size:12px;}
        .total-box-wrap{display:flex;gap:20px;justify-content:center;flex-wrap:wrap;margin-top:15px;}
        .total-box{display:inline-block;padding:15px 30px;border-radius:15px;font-size:20px;font-weight:700;box-shadow:0 4px 15px rgba(0,0,0,.2);transition:transform .3s;text-align:center;}
        .total-box:hover{transform:translateY(-3px);}
        .total-box.orange{background:linear-gradient(135deg,var(--primary-color),var(--secondary-color));color:#fff;}
        .total-box.green{background:linear-gradient(135deg,var(--green),var(--dark-green));color:#fff;}
        .total-box.purple{background:linear-gradient(135deg,var(--purple),var(--dark-purple));color:#fff;}
        .total-box.blue{background:linear-gradient(135deg,var(--blue),var(--dark-blue));color:#fff;}
        .total-box small{display:block;font-size:12px;font-weight:400;opacity:.9;margin-bottom:4px;}
        .empty-data{text-align:center;padding:40px;color:#6c757d;}
        .empty-data i{font-size:48px;display:block;margin-bottom:15px;color:#dee2e6;}
        .text-right{text-align:right;}
        .text-center{text-align:center;}
        @media print{
            .filter-form,.action-buttons,.print-btn,.back-btn,.sidebar,.col-md-2,.nav-tabs-laporan{display:none!important;}
            .col-md-10{margin-left:0!important;width:100%!important;}
            body{background:#fff;padding:0;}
            .main-container{box-shadow:none;padding:15px;margin:0;}
            .data-table th,.data-table td{font-size:11px;padding:5px;}
            *{color:#000!important;background:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
            .data-table th{background:#f0f0f0!important;color:#000!important;border:1px solid #000!important;}
            .data-table td{border:1px solid #000!important;}
            .total-row.resep td,.total-row.langsung td,.total-row.bhp td{background:#f5f5f5!important;border-top:3px solid #000!important;}
            .total-box{background:#fff!important;color:#000!important;border:3px solid #000!important;box-shadow:none!important;}
            .summary-card{background:#fff!important;border:2px solid #000!important;}
            .summary-section{background:#fff!important;border-left:4px solid #000!important;}
            .report-header{border-bottom:3px solid #000!important;}
            .section-title{background:#f0f0f0!important;color:#000!important;}
            .tab-pane{display:block!important;opacity:1!important;}
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
            <div class="main-container">

                <div class="filter-form">
                    <form method="GET" action="">
                        <label>Tanggal Awal:</label>
                        <input type="date" name="tgl_awal"  value="<?= htmlspecialchars($tgl_awal) ?>" required>
                        <label>Tanggal Akhir:</label>
                        <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($tgl_akhir) ?>" required>
                        <button type="submit"><i class="fas fa-search me-2"></i>Tampilkan</button>
                    </form>
                </div>

                <div class="action-buttons">
                    <div class="back-btn">
                        <a href="dashboard.php"><i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard</a>
                    </div>
                    <div class="print-btn">
                        <button onclick="window.print()"><i class="fas fa-print me-2"></i>Cetak Laporan</button>
                    </div>
                </div>

                <div class="report-header">
                    <h1>(Nama Klinik)</h1>
                    <h2>LAPORAN REGISTRASI KUNJUNGAN PASIEN</h2>
                    <p>(Alamat Klinik)</p>
                </div>

                <div class="periode-info">
                    <strong>Periode: <?= tanggal_indo($tgl_awal) ?> s/d <?= tanggal_indo($tgl_akhir) ?></strong>
                </div>

                <!-- TAB NAVIGASI -->
                <ul class="nav nav-tabs-laporan" id="laporanTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-obat-btn" data-bs-toggle="tab" data-bs-target="#tabPengeluaranObat" type="button">
                            <i class="fas fa-pills me-2"></i>Pengeluaran Obat
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-bhp-btn" data-bs-toggle="tab" data-bs-target="#tabPengeluaranBhp" type="button">
                            <i class="fas fa-box-open me-2"></i>Pengeluaran BHP
                        </button>
                    </li>
                </ul>

                <div class="tab-content">

                    <!-- ==================== TAB: PENGELUARAN OBAT ==================== -->
                    <div class="tab-pane fade show active" id="tabPengeluaranObat" role="tabpanel">

                        <!-- BAGIAN 1: RESEP -->
                        <div class="section-title resep">
                            <i class="fas fa-file-medical"></i> Pengeluaran Obat via Resep Dokter
                        </div>
                        <table class="data-table resep">
                            <thead>
                                <tr>
                                    <th style="width:5%">No</th>
                                    <th style="width:10%">Tanggal</th>
                                    <th style="width:10%">No. RM</th>
                                    <th style="width:15%">Nama Pasien</th>
                                    <th style="width:10%">Kode Obat</th>
                                    <th style="width:18%">Nama Obat</th>
                                    <th style="width:7%" class="text-right">Jumlah</th>
                                    <th style="width:12%" class="text-right">Harga Satuan</th>
                                    <th style="width:13%" class="text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($data_resep) > 0): $no = 1; ?>
                                    <?php foreach ($data_resep as $row): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                        <td><?= htmlspecialchars($row['no_rm']) ?></td>
                                        <td><?= htmlspecialchars($row['nama_pasien']) ?></td>
                                        <td><?= htmlspecialchars($row['kode_obat']) ?></td>
                                        <td><?= htmlspecialchars($row['nama_obat']) ?></td>
                                        <td class="text-right"><?= $row['jumlah'] ?></td>
                                        <td class="text-right"><?= format_rupiah($row['harga_satuan']) ?></td>
                                        <td class="text-right"><?= format_rupiah($row['subtotal']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row resep">
                                        <td colspan="8" class="text-right"><strong>TOTAL RESEP:</strong></td>
                                        <td class="text-right"><strong><?= format_rupiah($total_resep) ?></strong></td>
                                    </tr>
                                <?php else: ?>
                                    <tr><td colspan="9">
                                        <div class="empty-data">
                                            <i class="fas fa-file-medical-alt"></i>
                                            <strong>Tidak ada data pengeluaran obat via resep pada periode ini.</strong>
                                        </div>
                                    </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- BAGIAN 2: PENJUALAN LANGSUNG -->
                        <div class="section-title langsung">
                            <i class="fas fa-shopping-cart"></i> Penjualan Langsung (Tanpa Resep)
                        </div>
                        <table class="data-table langsung">
                            <thead>
                                <tr>
                                    <th style="width:5%">No</th>
                                    <th style="width:12%">Tanggal</th>
                                    <th style="width:13%">No. Transaksi</th>
                                    <th style="width:10%">Kode Obat</th>
                                    <th style="width:25%">Nama Obat</th>
                                    <th style="width:7%" class="text-right">Jumlah</th>
                                    <th style="width:14%" class="text-right">Harga Satuan</th>
                                    <th style="width:14%" class="text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($data_langsung) > 0): $no = 1; ?>
                                    <?php foreach ($data_langsung as $row): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                        <td><?= htmlspecialchars($row['no_transaksi']) ?></td>
                                        <td><?= htmlspecialchars($row['kode_obat']) ?></td>
                                        <td><?= htmlspecialchars($row['nama_obat']) ?></td>
                                        <td class="text-right"><?= $row['jumlah'] ?></td>
                                        <td class="text-right"><?= format_rupiah($row['harga_satuan']) ?></td>
                                        <td class="text-right"><?= format_rupiah($row['subtotal']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row langsung">
                                        <td colspan="7" class="text-right"><strong>TOTAL PENJUALAN LANGSUNG:</strong></td>
                                        <td class="text-right"><strong><?= format_rupiah($total_langsung) ?></strong></td>
                                    </tr>
                                <?php else: ?>
                                    <tr><td colspan="8">
                                        <div class="empty-data">
                                            <i class="fas fa-shopping-basket"></i>
                                            <strong>Tidak ada data penjualan langsung pada periode ini.</strong>
                                        </div>
                                    </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    </div><!-- end #tabPengeluaranObat -->

                    <!-- ==================== TAB: PENGELUARAN BHP ==================== -->
                    <div class="tab-pane fade" id="tabPengeluaranBhp" role="tabpanel">

                        <div class="section-title bhp">
                            <i class="fas fa-box-open"></i> Pengeluaran BHP (Bahan Habis Pakai)
                        </div>
                        <table class="data-table bhp">
                            <thead>
                                <tr>
                                    <th style="width:5%">No</th>
                                    <th style="width:10%">Tanggal</th>
                                    <th style="width:10%">No. RM</th>
                                    <th style="width:17%">Nama Pasien</th>
                                    <th style="width:18%">Nama BHP</th>
                                    <th style="width:8%">Satuan</th>
                                    <th style="width:7%" class="text-right">Jumlah</th>
                                    <th style="width:12%" class="text-right">Harga Satuan</th>
                                    <th style="width:13%" class="text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($data_bhp) > 0): $no = 1; ?>
                                    <?php foreach ($data_bhp as $row): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                        <td><?= htmlspecialchars($row['no_rm']) ?></td>
                                        <td><?= htmlspecialchars($row['nama_pasien']) ?></td>
                                        <td><?= htmlspecialchars($row['nama_bhp']) ?></td>
                                        <td><?= htmlspecialchars($row['satuan']) ?></td>
                                        <td class="text-right"><?= $row['jumlah'] ?></td>
                                        <td class="text-right"><?= format_rupiah($row['harga_satuan']) ?></td>
                                        <td class="text-right"><?= format_rupiah($row['subtotal']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="total-row bhp">
                                        <td colspan="8" class="text-right"><strong>TOTAL BHP:</strong></td>
                                        <td class="text-right"><strong><?= format_rupiah($total_bhp) ?></strong></td>
                                    </tr>
                                <?php else: ?>
                                    <tr><td colspan="9">
                                        <div class="empty-data">
                                            <i class="fas fa-box-open"></i>
                                            <strong>Tidak ada data pengeluaran BHP pada periode ini.</strong>
                                        </div>
                                    </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    </div><!-- end #tabPengeluaranBhp -->

                </div><!-- end .tab-content -->

                <!-- KESIMPULAN -->
                <?php if (count($data_resep) > 0 || count($data_langsung) > 0 || count($data_bhp) > 0): ?>
                <div class="summary-section">
                    <h3><i class="fas fa-chart-line me-2"></i>KESIMPULAN</h3>
                    <p>Laporan pengeluaran obat & BHP periode
                        <strong><?= tanggal_indo($tgl_awal) ?></strong> s/d
                        <strong><?= tanggal_indo($tgl_akhir) ?></strong>:
                    </p>
                    <div class="summary-grid">
                        <div class="summary-card orange">
                            <h4><i class="fas fa-file-medical me-1"></i> Pendapatan Resep</h4>
                            <div class="number" style="font-size:16px"><?= format_rupiah($total_resep) ?></div>
                            <small><?= count($data_resep) ?> item obat</small>
                        </div>
                        <div class="summary-card green">
                            <h4><i class="fas fa-shopping-cart me-1"></i> Pendapatan Langsung</h4>
                            <div class="number" style="font-size:16px"><?= format_rupiah($total_langsung) ?></div>
                            <small><?= count($data_langsung) ?> item obat</small>
                        </div>
                        <div class="summary-card purple">
                            <h4><i class="fas fa-box-open me-1"></i> Pendapatan BHP</h4>
                            <div class="number" style="font-size:16px"><?= format_rupiah($total_bhp) ?></div>
                            <small><?= count($data_bhp) ?> item BHP</small>
                        </div>
                    </div>
                    <div class="total-box-wrap">
                        <div class="total-box orange">
                            <small>Total Resep</small>
                            <?= format_rupiah($total_resep) ?>
                        </div>
                        <div class="total-box green">
                            <small>Total Langsung</small>
                            <?= format_rupiah($total_langsung) ?>
                        </div>
                        <div class="total-box purple">
                            <small>Total BHP</small>
                            <?= format_rupiah($total_bhp) ?>
                        </div>
                        <div class="total-box blue">
                            <small>TOTAL KESELURUHAN</small>
                            <?= format_rupiah($total_keseluruhan) ?>
                        </div>
                    </div>
                    <p style="margin-top:20px;">
                        Laporan ini mencakup pengeluaran obat dari resep dokter, penjualan langsung, dan bahan habis pakai (BHP).
                        Data ini dapat digunakan untuk evaluasi pendapatan apotek dan manajemen stok.
                    </p>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>