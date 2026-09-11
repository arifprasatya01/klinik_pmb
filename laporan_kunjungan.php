<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'kasir' && $_SESSION['role'] != 'petugas' && $_SESSION['role'] != 'admin')) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

$tgl_awal = mysqli_real_escape_string($conn, $tgl_awal);
$tgl_akhir = mysqli_real_escape_string($conn, $tgl_akhir);

// ─── Helper: ambil tindakan per pendaftaran_id ───────────────────────────────
function getTindakan($conn, $pendaftaran_id) {
    $pid = (int)$pendaftaran_id;
    $q = "SELECT mt.nama_tindakan, dt.jumlah, dt.tarif
          FROM detail_tindakan dt
          INNER JOIN pemeriksaan p  ON dt.pemeriksaan_id = p.id
          INNER JOIN master_tindakan mt ON dt.tindakan_id = mt.id
          WHERE p.pendaftaran_id = $pid AND dt.hapus = 0";
    $r = mysqli_query($conn, $q);
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $list[] = $row['nama_tindakan'] . ' (' . $row['jumlah'] . 'x)';
    }
    return $list ? implode(', ', $list) : '-';
}

// ─── Helper: ambil BHP per pendaftaran_id ────────────────────────────────────
function getBHP($conn, $pendaftaran_id) {
    $pid = (int)$pendaftaran_id;
    $q = "SELECT mb.nama_bhp, db2.jumlah
          FROM detail_bhp db2
          INNER JOIN pemeriksaan p ON db2.pemeriksaan_id = p.id
          INNER JOIN master_bhp mb ON db2.bhp_id = mb.id
          WHERE p.pendaftaran_id = $pid AND db2.hapus = 0";
    $r = mysqli_query($conn, $q);
    $list = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $list[] = $row['nama_bhp'] . ' (' . $row['jumlah'] . ')';
    }
    return $list ? implode(', ', $list) : '-';
}

// ─── Helper: ambil Obat per pendaftaran_id ───────────────────────────────────
function getObat($conn, $pendaftaran_id) {
    $pid = (int)$pendaftaran_id;
    $q = "SELECT o.nama_obat, dr.jumlah, dr.aturan_pakai
          FROM detail_resep dr
          INNER JOIN resep r       ON dr.resep_id = r.id
          INNER JOIN pemeriksaan p ON r.pemeriksaan_id = p.id
          INNER JOIN obat o        ON dr.obat_id = o.id
          WHERE p.pendaftaran_id = $pid AND dr.hapus = 0 AND r.hapus = 0";
    $res = mysqli_query($conn, $q);
    $list = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $list[] = $row['nama_obat'] . ' ' . $row['jumlah'] . 'x (' . $row['aturan_pakai'] . ')';
    }
    return $list ? implode(', ', $list) : '-';
}

// ─── Query UMUM ──────────────────────────────────────────────────────────────
$query_umum = "SELECT pend.id, pend.tgl_daftar, pas.no_rm,
                      pas.nama_lengkap, pas.alamat, pas.no_telepon,
                      pend.keluhan, pend.status, pas.jenis_pasien
               FROM pendaftaran pend
               INNER JOIN pasien pas ON pend.pasien_id = pas.id
               WHERE DATE(pend.tgl_daftar) BETWEEN '$tgl_awal' AND '$tgl_akhir'
               AND pas.jenis_pasien = 'umum'
               AND pend.hapus = '0' AND pend.status != 'dibatalkan'
               ORDER BY pend.tgl_daftar ASC";
$result_umum  = mysqli_query($conn, $query_umum);
$total_umum   = mysqli_num_rows($result_umum);

// ─── Query BPJS ──────────────────────────────────────────────────────────────
$query_bpjs = "SELECT pend.id, pend.tgl_daftar, pas.no_rm,
                      pas.nama_lengkap, pas.alamat, pas.no_telepon,
                      pas.no_bpjs, pend.keluhan, pend.status, pas.jenis_pasien
               FROM pendaftaran pend
               INNER JOIN pasien pas ON pend.pasien_id = pas.id
               WHERE DATE(pend.tgl_daftar) BETWEEN '$tgl_awal' AND '$tgl_akhir'
               AND pas.jenis_pasien = 'bpjs'
               AND pend.hapus = '0' AND pend.status != 'dibatalkan'
               ORDER BY pend.tgl_daftar ASC";
$result_bpjs  = mysqli_query($conn, $query_bpjs);
$total_bpjs   = mysqli_num_rows($result_bpjs);

// ─── Query ASURANSI ──────────────────────────────────────────────────────────
$query_asuransi = "SELECT pend.id, pend.tgl_daftar, pas.no_rm,
                          pas.nama_lengkap, pas.alamat, pas.no_telepon,
                          pas.nama_asuransi, pas.no_polis,
                          pend.keluhan, pend.status, pas.jenis_pasien
                   FROM pendaftaran pend
                   INNER JOIN pasien pas ON pend.pasien_id = pas.id
                   WHERE DATE(pend.tgl_daftar) BETWEEN '$tgl_awal' AND '$tgl_akhir'
                   AND pas.jenis_pasien = 'asuransi'
                   AND pend.hapus = '0' AND pend.status != 'dibatalkan'
                   ORDER BY pend.tgl_daftar ASC";
$result_asuransi  = mysqli_query($conn, $query_asuransi);
$total_asuransi   = mysqli_num_rows($result_asuransi);

// ─── Query BATAL ─────────────────────────────────────────────────────────────
$query_batal = "SELECT pend.id, pend.tgl_daftar, pas.no_rm,
                       pas.nama_lengkap, pas.jenis_pasien, pas.no_telepon,
                       pend.keluhan, pend.status
                FROM pendaftaran pend
                INNER JOIN pasien pas ON pend.pasien_id = pas.id
                WHERE DATE(pend.tgl_daftar) BETWEEN '$tgl_awal' AND '$tgl_akhir'
                AND pend.status = 'dibatalkan'
                ORDER BY pend.tgl_daftar ASC";
$result_batal = mysqli_query($conn, $query_batal);
$total_batal  = mysqli_num_rows($result_batal);

$total_seluruh = $total_umum + $total_bpjs + $total_asuransi;

function tanggal_indo($tanggal) {
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $p = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $p[2] . ' ' . $bulan[(int)$p[1]] . ' ' . $p[0];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Registrasi Kunjungan Pasien - Healoka</title>
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link rel="shortcut icon" type="image/png" href="asset/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php include 'sidebar_style.php'; ?>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background-color:#f8f9fa; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; }
        .container-fluid { padding:0; margin:0; }
        .row { margin:0; display:flex; }
        .col-md-2 { flex:0 0 250px; max-width:250px; }
        .col-md-10 { flex:1; margin-left:250px; padding:0; }

        .main-container {
            max-width:100%; margin:20px;
            background-color:white; padding:30px;
            box-shadow:0 2px 10px rgba(0,0,0,0.05); border-radius:15px;
        }

        /* Header */
        .report-header { text-align:center; margin-bottom:30px; border-bottom:3px solid #333; padding-bottom:20px; }
        .report-header h1 { font-size:24px; margin-bottom:5px; color:#1f2937; font-weight:700; }
        .report-header h2 { font-size:20px; margin-bottom:10px; color:#495057; font-weight:600; }
        .report-header p  { font-size:14px; color:#6c757d; }

        /* Filter */
        .filter-form { margin-bottom:20px; padding:15px; background-color:#f8f9fa; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.05); }
        .filter-form label { margin-right:10px; font-weight:600; color:#495057; }
        .filter-form input[type="date"] { padding:8px 12px; margin-right:10px; border:1px solid #dee2e6; border-radius:8px; transition:border-color .3s; }
        .filter-form input[type="date"]:focus { outline:none; border-color:var(--primary-color); }
        .filter-form button { padding:8px 24px; background:linear-gradient(135deg,var(--primary-color),var(--secondary-color)); color:white; border:none; border-radius:8px; cursor:pointer; font-weight:500; transition:transform .2s,box-shadow .2s; }
        .filter-form button:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(102,126,234,.4); }

        /* Action buttons */
        .action-buttons { display:flex; justify-content:space-between; margin-bottom:20px; }
        .back-btn a { padding:10px 30px; background-color:#6c757d; color:white; text-decoration:none; border-radius:10px; font-size:16px; display:inline-block; transition:all .3s; font-weight:500; }
        .back-btn a:hover { background-color:#5a6268; transform:translateY(-2px); }
        .print-btn button { padding:10px 30px; background:linear-gradient(135deg,var(--primary-color),var(--secondary-color)); color:white; border:none; border-radius:10px; cursor:pointer; font-size:16px; font-weight:500; transition:transform .2s,box-shadow .2s; }
        .print-btn button:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(102,126,234,.4); }

        .periode-info { text-align:center; margin-bottom:30px; font-size:16px; color:#1f2937; font-weight:600; }

        /* Section */
        .data-section { margin-bottom:40px; border-radius:15px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        .section-header { color:white; padding:15px 20px; margin-bottom:0; font-weight:600; }
        .section-header h3 { margin:0; font-size:18px; }
        .section-header.umum     { background:linear-gradient(135deg,#667eea,#764ba2); }
        .section-header.bpjs     { background:linear-gradient(135deg,#3b82f6,#2563eb); }
        .section-header.asuransi { background:linear-gradient(135deg,#f59e0b,#d97706); }
        .section-header.batal    { background:linear-gradient(135deg,#ef4444,#dc2626); }

        /* Table toolbar */
        .table-toolbar {
            display:flex; align-items:center; justify-content:space-between;
            flex-wrap:wrap; gap:8px; padding:12px 16px;
            background:#f8f9fa; border-bottom:1px solid #dee2e6;
        }
        .table-toolbar input[type="text"] {
            border:1px solid #dee2e6; border-radius:8px;
            padding:6px 12px 6px 32px; font-size:13px; width:220px; outline:none;
            transition:border-color .2s;
            background:white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236c757d' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat 10px center;
        }
        .table-toolbar input[type="text"]:focus { border-color:var(--primary-color); }
        .table-info { font-size:12px; color:#6c757d; }
        .table-pagination { padding:10px 16px; display:flex; justify-content:center; border-top:1px solid #dee2e6; background:#fafbfc; }

        /* Tables */
        .data-table { width:100%; border-collapse:collapse; margin-bottom:0; }
        .data-table th { background-color:#f8f9fa; color:#495057; padding:12px; text-align:left; font-weight:600; border:1px solid #dee2e6; font-size:0.85em; }
        .data-table td { padding:10px; border:1px solid #dee2e6; font-size:13px; color:#1f2937; }
        .data-table tbody tr:hover { background-color:#f8f9fa; }
        .data-table tbody tr:nth-child(even) { background-color:#fafbfc; }
        .empty-data { text-align:center; padding:40px; color:#6c757d; font-style:italic; }

        /* Detail cell (tindakan/bhp/obat) */
        .detail-cell { font-size:12px; line-height:1.6; }
        .detail-cell .badge-item {
            display:inline-block; margin:2px 2px 2px 0;
            padding:2px 7px; border-radius:20px; font-size:11px; font-weight:600;
        }
        .badge-tindakan { background:#ede9fe; color:#5b21b6; }
        .badge-bhp      { background:#dcfce7; color:#166534; }
        .badge-obat     { background:#dbeafe; color:#1e40af; }
        .badge-none     { background:#f3f4f6; color:#6b7280; }

        /* Subtotal */
        .subtotal { padding:15px 20px; border-radius:0 0 15px 15px; font-weight:600; text-align:right; color:#1f2937; border:2px solid; border-top:none; }
        .subtotal.umum     { background-color:#f0eeff; border-color:#667eea; }
        .subtotal.bpjs     { background-color:#dbeafe; border-color:#3b82f6; }
        .subtotal.asuransi { background-color:#fef3c7; border-color:#f59e0b; }
        .subtotal.batal    { background-color:#fee2e2; border-color:#ef4444; }

        /* Summary */
        .summary-section { margin-top:40px; padding:25px; background:linear-gradient(135deg,#f8f9fa,#e9ecef); border-left:5px solid var(--primary-color); border-radius:15px; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        .summary-section h3 { margin-bottom:15px; color:#1f2937; font-size:20px; font-weight:700; }
        .summary-section p  { font-size:16px; line-height:1.8; color:#495057; margin-bottom:15px; }
        .summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:15px; margin:20px 0; }
        .summary-card { padding:20px; border-radius:15px; text-align:center; color:white; transition:transform .3s,box-shadow .3s; box-shadow:0 4px 15px rgba(0,0,0,0.1); }
        .summary-card:hover { transform:translateY(-5px); box-shadow:0 10px 25px rgba(0,0,0,0.15); }
        .summary-card.umum     { background:linear-gradient(135deg,#667eea,#764ba2); }
        .summary-card.bpjs     { background:linear-gradient(135deg,#3b82f6,#2563eb); }
        .summary-card.asuransi { background:linear-gradient(135deg,#f59e0b,#d97706); }
        .summary-card.batal    { background:linear-gradient(135deg,#ef4444,#dc2626); }
        .summary-card h4   { font-size:14px; margin-bottom:10px; opacity:.9; font-weight:600; }
        .summary-card .number { font-size:2rem; font-weight:700; margin:10px 0; }
        .total-box { display:inline-block; background:linear-gradient(135deg,var(--primary-color),var(--secondary-color)); color:white; padding:15px 30px; border-radius:15px; font-size:24px; font-weight:700; margin-top:15px; box-shadow:0 4px 15px rgba(102,126,234,.4); transition:transform .3s; }
        .total-box:hover { transform:translateY(-3px); }

        /* Print */
        @media print {
            .filter-form,.action-buttons,.print-btn,.back-btn,.sidebar,.col-md-2,.table-toolbar,.table-pagination { display:none !important; }
            .col-md-10 { margin-left:0 !important; width:100% !important; }
            body { background-color:white; padding:0; }
            .main-container { box-shadow:none; padding:20px; margin:0; }
            .data-table th,.data-table td { font-size:10px; padding:5px; }
            .data-section { page-break-inside:avoid; }
            * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .section-header { border:2px solid #000 !important; }
            .data-table th { background:#f0f0f0 !important; border:1px solid #000 !important; }
            .data-table td { border:1px solid #000 !important; }
            .subtotal { background:#f5f5f5 !important; border:2px solid #000 !important; }
            .summary-section { background:white !important; border-left:4px solid #000 !important; box-shadow:none !important; }
            .report-header { border-bottom:3px solid #000 !important; }
            .badge-item { border:1px solid #999 !important; background:white !important; color:#000 !important; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        <div class="col-md-12">
            <div class="main-container">

                <!-- Filter -->
                <div class="filter-form">
                    <form method="GET" action="">
                        <label>Tanggal Awal:</label>
                        <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" required>
                        <label>Tanggal Akhir:</label>
                        <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" required>
                        <button type="submit"><i class="fas fa-search me-2"></i>Tampilkan</button>
                    </form>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <div class="back-btn"><a href="dashboard.php"><i class="fas fa-arrow-left me-2"></i>Kembali ke Dashboard</a></div>
                    <div class="print-btn"><button onclick="window.print()"><i class="fas fa-print me-2"></i>Cetak Laporan</button></div>
                </div>

                <!-- Header -->
                <div class="report-header">
                    <h1>(NamaKlinik)</h1>
                    <h2>LAPORAN REGISTRASI KUNJUNGAN PASIEN</h2>
                    <p>(Alamat Klinik)</p>
                </div>

                <div class="periode-info">
                    <strong>Periode: <?= tanggal_indo($tgl_awal) ?> s/d <?= tanggal_indo($tgl_akhir) ?></strong>
                </div>

                <!-- ══════════════════════════════════════════════ -->
                <!-- SECTION 1: PASIEN UMUM                        -->
                <!-- ══════════════════════════════════════════════ -->
                <div class="data-section">
                    <div class="section-header umum">
                        <h3><i class="fas fa-hospital me-2"></i>PASIEN UMUM</h3>
                    </div>
                    <div class="table-toolbar">
                        <input type="text" id="search-umum" placeholder="Cari pasien umum…">
                        <span id="info-umum" class="table-info"></span>
                    </div>
                    <table class="data-table" id="tbl-umum">
                        <thead>
                            <tr>
                                <th style="width:4%">No</th>
                                <th style="width:9%">Tanggal</th>
                                <th style="width:9%">No. RM</th>
                                <th style="width:14%">Nama Pasien</th>
                                <th style="width:14%">Alamat</th>
                                <th style="width:10%">No. Telepon</th>
                                <th style="width:12%">Keluhan</th>
                                <th style="width:10%">Status</th>
                                <th style="width:28%">Tindakan / BHP / Obat</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($total_umum > 0): $no=1; while ($row = mysqli_fetch_assoc($result_umum)): ?>
                        <?php
                            $tindakan = getTindakan($conn, $row['id']);
                            $bhp      = getBHP($conn, $row['id']);
                            $obat     = getObat($conn, $row['id']);
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d/m/Y', strtotime($row['tgl_daftar'])) ?></td>
                            <td><?= $row['no_rm'] ?></td>
                            <td><?= $row['nama_lengkap'] ?></td>
                            <td><?= $row['alamat'] ?></td>
                            <td><?= !empty($row['no_telepon']) ? $row['no_telepon'] : '-' ?></td>
                            <td><?= !empty($row['keluhan']) ? $row['keluhan'] : '-' ?></td>
                            <td><?= !empty($row['status']) ? $row['status'] : '-' ?></td>
                            <td class="detail-cell">
                                <?php if ($tindakan !== '-'): ?>
                                    <div><small class="fw-bold text-muted" style="font-size:10px;">TINDAKAN:</small><br>
                                    <?php foreach(explode(', ', $tindakan) as $t): ?>
                                        <span class="badge-item badge-tindakan"><?= htmlspecialchars(trim($t)) ?></span>
                                    <?php endforeach; ?></div>
                                <?php endif; ?>
                                <?php if ($bhp !== '-'): ?>
                                    <div style="margin-top:3px"><small class="fw-bold text-muted" style="font-size:10px;">BHP:</small><br>
                                    <?php foreach(explode(', ', $bhp) as $b): ?>
                                        <span class="badge-item badge-bhp"><?= htmlspecialchars(trim($b)) ?></span>
                                    <?php endforeach; ?></div>
                                <?php endif; ?>
                                <?php if ($obat !== '-'): ?>
                                    <div style="margin-top:3px"><small class="fw-bold text-muted" style="font-size:10px;">OBAT:</small><br>
                                    <?php foreach(explode(', ', $obat) as $o): ?>
                                        <span class="badge-item badge-obat"><?= htmlspecialchars(trim($o)) ?></span>
                                    <?php endforeach; ?></div>
                                <?php endif; ?>
                                <?php if ($tindakan === '-' && $bhp === '-' && $obat === '-'): ?>
                                    <span class="badge-item badge-none">Belum ada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="9" class="empty-data"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Tidak ada data pasien umum</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="table-pagination"><div id="pagin-umum"></div></div>
                    <div class="subtotal umum">Subtotal Pasien Umum: <strong><?= $total_umum ?> Pasien</strong></div>
                </div>

                <!-- ══════════════════════════════════════════════ -->
                <!-- SECTION 2: PASIEN BPJS                        -->
                <!-- ══════════════════════════════════════════════ -->
                <div class="data-section">
                    <div class="section-header bpjs">
                        <h3><i class="fas fa-id-card me-2"></i>PASIEN BPJS</h3>
                    </div>
                    <div class="table-toolbar">
                        <input type="text" id="search-bpjs" placeholder="Cari pasien BPJS…">
                        <span id="info-bpjs" class="table-info"></span>
                    </div>
                    <table class="data-table" id="tbl-bpjs">
                        <thead>
                            <tr>
                                <th style="width:4%">No</th>
                                <th style="width:9%">Tanggal</th>
                                <th style="width:9%">No. RM</th>
                                <th style="width:13%">Nama Pasien</th>
                                <th style="width:10%">No. BPJS</th>
                                <th style="width:12%">Alamat</th>
                                <th style="width:9%">No. Telepon</th>
                                <th style="width:10%">Keluhan</th>
                                <th style="width:24%">Tindakan / BHP / Obat</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($total_bpjs > 0): $no=1; while ($row = mysqli_fetch_assoc($result_bpjs)): ?>
                        <?php
                            $tindakan = getTindakan($conn, $row['id']);
                            $bhp      = getBHP($conn, $row['id']);
                            $obat     = getObat($conn, $row['id']);
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d/m/Y', strtotime($row['tgl_daftar'])) ?></td>
                            <td><?= $row['no_rm'] ?></td>
                            <td><?= $row['nama_lengkap'] ?></td>
                            <td><?= !empty($row['no_bpjs']) ? $row['no_bpjs'] : '-' ?></td>
                            <td><?= $row['alamat'] ?></td>
                            <td><?= !empty($row['no_telepon']) ? $row['no_telepon'] : '-' ?></td>
                            <td><?= !empty($row['keluhan']) ? $row['keluhan'] : '-' ?></td>
                            <td class="detail-cell">
                                <?php if ($tindakan !== '-'): ?>
                                    <div><small class="fw-bold text-muted" style="font-size:10px;">TINDAKAN:</small><br>
                                    <?php foreach(explode(', ', $tindakan) as $t): ?>
                                        <span class="badge-item badge-tindakan"><?= htmlspecialchars(trim($t)) ?></span>
                                    <?php endforeach; ?></div>
                                <?php endif; ?>
                                <?php if ($bhp !== '-'): ?>
                                    <div style="margin-top:3px"><small class="fw-bold text-muted" style="font-size:10px;">BHP:</small><br>
                                    <?php foreach(explode(', ', $bhp) as $b): ?>
                                        <span class="badge-item badge-bhp"><?= htmlspecialchars(trim($b)) ?></span>
                                    <?php endforeach; ?></div>
                                <?php endif; ?>
                                <?php if ($obat !== '-'): ?>
                                    <div style="margin-top:3px"><small class="fw-bold text-muted" style="font-size:10px;">OBAT:</small><br>
                                    <?php foreach(explode(', ', $obat) as $o): ?>
                                        <span class="badge-item badge-obat"><?= htmlspecialchars(trim($o)) ?></span>
                                    <?php endforeach; ?></div>
                                <?php endif; ?>
                                <?php if ($tindakan === '-' && $bhp === '-' && $obat === '-'): ?>
                                    <span class="badge-item badge-none">Belum ada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="9" class="empty-data"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Tidak ada data pasien BPJS</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="table-pagination"><div id="pagin-bpjs"></div></div>
                    <div class="subtotal bpjs">Subtotal Pasien BPJS: <strong><?= $total_bpjs ?> Pasien</strong></div>
                </div>

                <!-- ══════════════════════════════════════════════ -->
                <!-- SECTION 3: PASIEN ASURANSI                    -->
                <!-- ══════════════════════════════════════════════ -->
                <div class="data-section">
                    <div class="section-header asuransi">
                        <h3><i class="fas fa-shield-alt me-2"></i>PASIEN ASURANSI</h3>
                    </div>
                    <div class="table-toolbar">
                        <input type="text" id="search-asuransi" placeholder="Cari pasien asuransi…">
                        <span id="info-asuransi" class="table-info"></span>
                    </div>
                    <table class="data-table" id="tbl-asuransi">
                        <thead>
                            <tr>
                                <th style="width:4%">No</th>
                                <th style="width:8%">Tanggal</th>
                                <th style="width:8%">No. RM</th>
                                <th style="width:12%">Nama Pasien</th>
                                <th style="width:9%">Asuransi</th>
                                <th style="width:8%">No. Polis</th>
                                <th style="width:11%">Alamat</th>
                                <th style="width:8%">Telepon</th>
                                <th style="width:8%">Keluhan</th>
                                <th style="width:24%">Tindakan / BHP / Obat</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($total_asuransi > 0): $no=1; while ($row = mysqli_fetch_assoc($result_asuransi)): ?>
                        <?php
                            $tindakan = getTindakan($conn, $row['id']);
                            $bhp      = getBHP($conn, $row['id']);
                            $obat     = getObat($conn, $row['id']);
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d/m/Y', strtotime($row['tgl_daftar'])) ?></td>
                            <td><?= $row['no_rm'] ?></td>
                            <td><?= $row['nama_lengkap'] ?></td>
                            <td><?= !empty($row['nama_asuransi']) ? $row['nama_asuransi'] : '-' ?></td>
                            <td><?= !empty($row['no_polis']) ? $row['no_polis'] : '-' ?></td>
                            <td><?= $row['alamat'] ?></td>
                            <td><?= !empty($row['no_telepon']) ? $row['no_telepon'] : '-' ?></td>
                            <td><?= !empty($row['keluhan']) ? $row['keluhan'] : '-' ?></td>
                            <td class="detail-cell">
                                <?php if ($tindakan !== '-'): ?>
                                    <div><small class="fw-bold text-muted" style="font-size:10px;">TINDAKAN:</small><br>
                                    <?php foreach(explode(', ', $tindakan) as $t): ?>
                                        <span class="badge-item badge-tindakan"><?= htmlspecialchars(trim($t)) ?></span>
                                    <?php endforeach; ?></div>
                                <?php endif; ?>
                                <?php if ($bhp !== '-'): ?>
                                    <div style="margin-top:3px"><small class="fw-bold text-muted" style="font-size:10px;">BHP:</small><br>
                                    <?php foreach(explode(', ', $bhp) as $b): ?>
                                        <span class="badge-item badge-bhp"><?= htmlspecialchars(trim($b)) ?></span>
                                    <?php endforeach; ?></div>
                                <?php endif; ?>
                                <?php if ($obat !== '-'): ?>
                                    <div style="margin-top:3px"><small class="fw-bold text-muted" style="font-size:10px;">OBAT:</small><br>
                                    <?php foreach(explode(', ', $obat) as $o): ?>
                                        <span class="badge-item badge-obat"><?= htmlspecialchars(trim($o)) ?></span>
                                    <?php endforeach; ?></div>
                                <?php endif; ?>
                                <?php if ($tindakan === '-' && $bhp === '-' && $obat === '-'): ?>
                                    <span class="badge-item badge-none">Belum ada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="10" class="empty-data"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Tidak ada data pasien asuransi</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="table-pagination"><div id="pagin-asuransi"></div></div>
                    <div class="subtotal asuransi">Subtotal Pasien Asuransi: <strong><?= $total_asuransi ?> Pasien</strong></div>
                </div>

                <!-- ══════════════════════════════════════════════ -->
                <!-- SECTION 4: DIBATALKAN                         -->
                <!-- ══════════════════════════════════════════════ -->
                <div class="data-section">
                    <div class="section-header batal">
                        <h3><i class="fas fa-times-circle me-2"></i>KUNJUNGAN DIBATALKAN</h3>
                    </div>
                    <div class="table-toolbar">
                        <input type="text" id="search-batal" placeholder="Cari kunjungan dibatalkan…">
                        <span id="info-batal" class="table-info"></span>
                    </div>
                    <table class="data-table" id="tbl-batal">
                        <thead>
                            <tr>
                                <th style="width:5%">No</th>
                                <th style="width:10%">Tanggal</th>
                                <th style="width:10%">No. RM</th>
                                <th style="width:20%">Nama Pasien</th>
                                <th style="width:13%">Jenis Pasien</th>
                                <th style="width:13%">No. Telepon</th>
                                <th style="width:29%">Keluhan</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($total_batal > 0): $no=1; while ($row = mysqli_fetch_assoc($result_batal)): ?>
                        <?php
                            $badge = match($row['jenis_pasien']) {
                                'bpjs'     => ['bg'=>'#dbeafe','color'=>'#1e40af','label'=>'BPJS'],
                                'asuransi' => ['bg'=>'#fef3c7','color'=>'#92400e','label'=>'Asuransi'],
                                default    => ['bg'=>'#d1fae5','color'=>'#065f46','label'=>'Umum'],
                            };
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d/m/Y', strtotime($row['tgl_daftar'])) ?></td>
                            <td><?= $row['no_rm'] ?></td>
                            <td><?= $row['nama_lengkap'] ?></td>
                            <td>
                                <span style="background:<?= $badge['bg'] ?>;color:<?= $badge['color'] ?>;
                                    font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;">
                                    <?= $badge['label'] ?>
                                </span>
                            </td>
                            <td><?= !empty($row['no_telepon']) ? $row['no_telepon'] : '-' ?></td>
                            <td><?= !empty($row['keluhan']) ? $row['keluhan'] : '-' ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7" class="empty-data"><i class="fas fa-check-circle fa-2x mb-2 d-block text-success"></i>Tidak ada kunjungan yang dibatalkan</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="table-pagination"><div id="pagin-batal"></div></div>
                    <div class="subtotal batal">Subtotal Dibatalkan: <strong><?= $total_batal ?> Kunjungan</strong></div>
                </div>

                <!-- ══════════════════════════════════════════════ -->
                <!-- KESIMPULAN                                     -->
                <!-- ══════════════════════════════════════════════ -->
                <div class="summary-section">
                    <h3><i class="fas fa-chart-bar me-2"></i>KESIMPULAN</h3>
                    <p>Berdasarkan data registrasi kunjungan pasien pada periode
                       <strong><?= tanggal_indo($tgl_awal) ?></strong> sampai dengan
                       <strong><?= tanggal_indo($tgl_akhir) ?></strong>:</p>

                    <div class="summary-grid">
                        <div class="summary-card umum">
                            <h4>PASIEN UMUM</h4>
                            <div class="number"><?= $total_umum ?></div>
                            <small>Pasien</small>
                        </div>
                        <div class="summary-card bpjs">
                            <h4>PASIEN BPJS</h4>
                            <div class="number"><?= $total_bpjs ?></div>
                            <small>Pasien</small>
                        </div>
                        <div class="summary-card asuransi">
                            <h4>PASIEN ASURANSI</h4>
                            <div class="number"><?= $total_asuransi ?></div>
                            <small>Pasien</small>
                        </div>
                        <div class="summary-card batal">
                            <h4>DIBATALKAN</h4>
                            <div class="number"><?= $total_batal ?></div>
                            <small>Kunjungan</small>
                        </div>
                    </div>

                    <p style="margin-top:20px;text-align:center;">
                        <strong>Total Keseluruhan Kunjungan Pasien:</strong>
                    </p>
                    <div style="text-align:center;">
                        <div class="total-box"><?= $total_seluruh ?> Pasien</div>
                    </div>
                    <p style="margin-top:20px;">
                        Data ini menunjukkan distribusi kunjungan pasien berdasarkan jenis pembayaran.
                        Informasi ini dapat digunakan sebagai bahan evaluasi pelayanan klinik dan
                        perencanaan strategi pelayanan kesehatan di masa mendatang.
                    </p>
                </div>

            </div><!-- /main-container -->
        </div><!-- /col-md-10 -->
    </div><!-- /row -->
</div><!-- /container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ROWS_PER_PAGE = 10;

function initTableSearch(tableId, searchInputId, infoId, paginId) {
    const tbody = document.querySelector('#' + tableId + ' tbody');
    if (!tbody) return;
    const allRows = Array.from(tbody.querySelectorAll('tr:not(.empty-placeholder)'));
    let filtered = [...allRows];
    let currentPage = 1;

    const searchInput = document.getElementById(searchInputId);
    const infoEl      = document.getElementById(infoId);
    const paginEl     = document.getElementById(paginId);

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            filtered = q ? allRows.filter(r => r.textContent.toLowerCase().includes(q)) : [...allRows];
            currentPage = 1;
            render();
        });
    }

    function render() {
        const total = filtered.length;
        const pages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
        currentPage  = Math.min(currentPage, pages);
        const start  = (currentPage - 1) * ROWS_PER_PAGE;
        const end    = start + ROWS_PER_PAGE;

        allRows.forEach(r => r.style.display = 'none');
        filtered.forEach((r, i) => { r.style.display = (i >= start && i < end) ? '' : 'none'; });

        let placeholder = tbody.querySelector('.empty-placeholder');
        if (total === 0) {
            if (!placeholder) {
                placeholder = document.createElement('tr');
                placeholder.className = 'empty-placeholder';
                const cols = document.querySelectorAll('#' + tableId + ' thead th').length;
                placeholder.innerHTML = `<td colspan="${cols}" class="empty-data">
                    <i class="fas fa-search fa-2x mb-2 d-block"></i>
                    Data tidak ditemukan
                </td>`;
                tbody.appendChild(placeholder);
            }
            placeholder.style.display = '';
        } else {
            if (placeholder) placeholder.style.display = 'none';
        }

        if (infoEl) {
            infoEl.textContent = total === 0
                ? 'Tidak ada data'
                : `Menampilkan ${start + 1}–${Math.min(end, total)} dari ${total} data`;
        }

        if (paginEl) renderPagination(paginEl, pages, currentPage, p => { currentPage = p; render(); });
    }

    render();
}

function renderPagination(el, pages, current, onClick) {
    el.innerHTML = '';
    if (pages <= 1) return;
    const ul = document.createElement('ul');
    ul.className = 'pagination pagination-sm mb-0';

    function btn(label, page, disabled, active) {
        const li = document.createElement('li');
        li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
        const a = document.createElement('a');
        a.className = 'page-link'; a.href = '#'; a.innerHTML = label;
        a.addEventListener('click', e => { e.preventDefault(); if (!disabled && !active) onClick(page); });
        li.appendChild(a); ul.appendChild(li);
    }

    btn('&laquo;', current - 1, current === 1, false);
    const lo = Math.max(1, current - 2);
    const hi = Math.min(pages, current + 2);
    if (lo > 1) { btn('1', 1, false, false); if (lo > 2) btn('…', null, true, false); }
    for (let p = lo; p <= hi; p++) btn(p, p, false, p === current);
    if (hi < pages) { if (hi < pages - 1) btn('…', null, true, false); btn(pages, pages, false, false); }
    btn('&raquo;', current + 1, current === pages, false);

    el.appendChild(ul);
}

initTableSearch('tbl-umum',     'search-umum',     'info-umum',     'pagin-umum');
initTableSearch('tbl-bpjs',     'search-bpjs',     'info-bpjs',     'pagin-bpjs');
initTableSearch('tbl-asuransi', 'search-asuransi', 'info-asuransi', 'pagin-asuransi');
initTableSearch('tbl-batal',    'search-batal',    'info-batal',    'pagin-batal');
</script>
</body>
</html>