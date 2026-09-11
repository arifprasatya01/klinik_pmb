<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'apoteker', 'medis', 'petugas'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db   = new Database();
$conn = $db->getConnection();

// ── Filter (sama dengan finance.php) ────────────────────────────────────────
$filter_user    = isset($_GET['user_id'])    ? (int)$_GET['user_id']   : 0;
$filter_bulan   = isset($_GET['bulan'])      ? sanitize($_GET['bulan']) : date('Y-m');
$filter_mode    = isset($_GET['mode'])       ? sanitize($_GET['mode'])  : 'bulanan';

if ($filter_mode === 'harian') {
    $tgl_dari   = isset($_GET['tgl_dari'])   ? sanitize($_GET['tgl_dari'])   : date('Y-m-d');
    $tgl_sampai = isset($_GET['tgl_sampai']) ? sanitize($_GET['tgl_sampai']) : date('Y-m-d');
    $where_tgl  = "DATE(dt.created_at) BETWEEN '$tgl_dari' AND '$tgl_sampai'";
    $label_period = date('d-m-Y', strtotime($tgl_dari)) . ' sd ' . date('d-m-Y', strtotime($tgl_sampai));
} else {
    $tgl_dari   = $filter_bulan . '-01';
    $tgl_sampai = date('Y-m-t', strtotime($tgl_dari));
    $where_tgl  = "DATE_FORMAT(dt.created_at, '%Y-%m') = '$filter_bulan'";
    $label_period = date('F Y', strtotime($tgl_dari));
}

$where_user = $filter_user ? "AND dt.user_id = '$filter_user'" : "";

// ── Rekap per User ────────────────────────────────────────────────────────────
$query_rekap = "SELECT
                    u.nama_lengkap, u.role,
                    COUNT(dt.id)           AS total_tindakan,
                    SUM(dt.jumlah)         AS total_jumlah,
                    SUM(dt.subtotal)       AS total_pendapatan,
                    SUM(jt.nominal_jasa * dt.jumlah) AS total_jasa
                FROM detail_tindakan dt
                JOIN users u            ON dt.user_id = u.id
                JOIN jasa_tindakan jt   ON dt.tindakan_id = jt.tindakan_id
                WHERE $where_tgl $where_user
                GROUP BY u.id, u.nama_lengkap, u.role
                ORDER BY total_jasa DESC";
$result_rekap = mysqli_query($conn, $query_rekap);
$rekap_list   = [];
while ($r = mysqli_fetch_assoc($result_rekap)) $rekap_list[] = $r;

// ── Detail ────────────────────────────────────────────────────────────────────
$query_detail = "SELECT
                    dt.created_at,
                    u.nama_lengkap AS nama_user, u.role,
                    mt.kode_tindakan, mt.nama_tindakan,
                    dt.jumlah, dt.tarif, dt.subtotal,
                    jt.nominal_jasa,
                    (jt.nominal_jasa * dt.jumlah) AS total_jasa_baris,
                    ps.nama_lengkap AS nama_pasien, ps.no_rm
                FROM detail_tindakan dt
                JOIN users u            ON dt.user_id = u.id
                JOIN master_tindakan mt ON dt.tindakan_id = mt.id
                JOIN jasa_tindakan jt   ON dt.tindakan_id = jt.tindakan_id
                JOIN pemeriksaan pm     ON dt.pemeriksaan_id = pm.id
                JOIN pendaftaran pd     ON pm.pendaftaran_id = pd.id
                JOIN pasien ps          ON pd.pasien_id = ps.id
                WHERE $where_tgl $where_user
                ORDER BY u.nama_lengkap ASC, dt.created_at DESC";
$result_detail = mysqli_query($conn, $query_detail);
$detail_list   = [];
$grand_pend    = 0;
$grand_jasa    = 0;
while ($d = mysqli_fetch_assoc($result_detail)) {
    $detail_list[] = $d;
    $grand_pend   += $d['subtotal'];
    $grand_jasa   += $d['total_jasa_baris'];
}

// ── Output Excel (HTML table trick, compatible semua browser) ─────────────────
$filename = 'Finance_Jasa_' . str_replace(' ', '_', $label_period) . '.xls';

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
?>
<html>
<head><meta charset="UTF-8"></head>
<body>

<!-- SHEET: Rekap -->
<table border="1" cellpadding="4" cellspacing="0">
    <tr>
        <td colspan="6" style="font-weight:bold;font-size:14pt;background:#667EEA;color:white;">
            LAPORAN FINANCE JASA TINDAKAN
        </td>
    </tr>
    <tr>
        <td colspan="6">Periode: <?= $label_period ?></td>
    </tr>
    <tr><td colspan="6"></td></tr>

    <!-- REKAP SECTION -->
    <tr style="background:#667EEA;color:white;font-weight:bold;">
        <td colspan="6">REKAP PER STAFF</td>
    </tr>
    <tr style="background:#E8EAF6;font-weight:bold;">
        <td>#</td>
        <td>Nama Staff</td>
        <td>Role</td>
        <td align="center">Jumlah Tindakan</td>
        <td align="right">Total Pendapatan (Rp)</td>
        <td align="right">Total Jasa (Rp)</td>
    </tr>
    <?php foreach ($rekap_list as $i => $r): ?>
    <tr>
        <td align="center"><?= $i + 1 ?></td>
        <td><?= htmlspecialchars($r['nama_lengkap']) ?></td>
        <td><?= $r['role'] ?></td>
        <td align="center"><?= $r['total_tindakan'] ?></td>
        <td align="right"><?= number_format($r['total_pendapatan'], 0, ',', '.') ?></td>
        <td align="right" style="font-weight:bold;color:#1B5E20;"><?= number_format($r['total_jasa'], 0, ',', '.') ?></td>
    </tr>
    <?php endforeach; ?>
    <tr style="font-weight:bold;background:#E8F5E9;">
        <td colspan="4" align="right">GRAND TOTAL</td>
        <td align="right"><?= number_format($grand_pend, 0, ',', '.') ?></td>
        <td align="right" style="color:#1B5E20;"><?= number_format($grand_jasa, 0, ',', '.') ?></td>
    </tr>

    <tr><td colspan="6"></td></tr>
    <tr><td colspan="6"></td></tr>

    <!-- DETAIL SECTION -->
    <tr style="background:#F06292;color:white;font-weight:bold;">
        <td colspan="6">DETAIL TINDAKAN PER STAFF</td>
    </tr>
    <tr style="background:#FCE4EC;font-weight:bold;">
        <td>#</td>
        <td>Tanggal</td>
        <td>Staff</td>
        <td>Pasien (No. RM)</td>
        <td>Kode</td>
        <td>Nama Tindakan</td>
    </tr>
    <tr style="background:#FCE4EC;font-weight:bold;">
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td align="center">Jml</td>
        <td align="right">Tarif | Subtotal | Nominal Jasa | Total Jasa</td>
    </tr>
    <?php
    $prev_user = ''; $sub_pend2 = 0; $sub_jasa2 = 0;
    foreach ($detail_list as $i => $d):
        if ($d['nama_user'] !== $prev_user && $prev_user !== ''):
    ?>
    <tr style="background:#E8EAF6;font-weight:bold;">
        <td colspan="5" align="right">Subtotal <?= htmlspecialchars($prev_user) ?></td>
        <td align="right"><?= number_format($sub_pend2,0,',','.') ?> | — | — | <?= number_format($sub_jasa2,0,',','.') ?></td>
    </tr>
    <?php $sub_pend2 = 0; $sub_jasa2 = 0; endif;
          $prev_user = $d['nama_user'];
          $sub_pend2 += $d['subtotal'];
          $sub_jasa2 += $d['total_jasa_baris'];
    ?>
    <tr>
        <td align="center"><?= $i + 1 ?></td>
        <td><?= date('d/m/Y', strtotime($d['created_at'])) ?></td>
        <td><?= htmlspecialchars($d['nama_user']) ?> (<?= $d['role'] ?>)</td>
        <td><?= htmlspecialchars($d['nama_pasien']) ?> (<?= $d['no_rm'] ?>)</td>
        <td align="center"><?= htmlspecialchars($d['kode_tindakan']) ?></td>
        <td>
            <?= htmlspecialchars($d['nama_tindakan']) ?> |
            Jml: <?= $d['jumlah'] ?> |
            Rp <?= number_format($d['tarif'],0,',','.') ?> |
            Sub: Rp <?= number_format($d['subtotal'],0,',','.') ?> |
            Jasa/unit: Rp <?= number_format($d['nominal_jasa'],0,',','.') ?> |
            Total Jasa: Rp <?= number_format($d['total_jasa_baris'],0,',','.') ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if ($prev_user): ?>
    <tr style="background:#E8EAF6;font-weight:bold;">
        <td colspan="5" align="right">Subtotal <?= htmlspecialchars($prev_user) ?></td>
        <td align="right"><?= number_format($sub_pend2,0,',','.') ?> | — | — | <?= number_format($sub_jasa2,0,',','.') ?></td>
    </tr>
    <?php endif; ?>
    <tr style="background:#212121;color:white;font-weight:bold;">
        <td colspan="5" align="right">GRAND TOTAL</td>
        <td align="right">
            Pendapatan: Rp <?= number_format($grand_pend,0,',','.') ?> |
            Jasa: Rp <?= number_format($grand_jasa,0,',','.') ?>
        </td>
    </tr>
</table>

</body>
</html>