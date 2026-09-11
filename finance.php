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

// ── Filter ──────────────────────────────────────────────────────────────────
$filter_user   = isset($_GET['user_id'])    ? (int)$_GET['user_id']      : 0;
$filter_bulan  = isset($_GET['bulan'])      ? sanitize($_GET['bulan'])    : date('Y-m');
$filter_mode   = isset($_GET['mode'])       ? sanitize($_GET['mode'])     : 'bulanan'; // harian | bulanan

if ($filter_mode === 'harian') {
    $tgl_dari = isset($_GET['tgl_dari']) ? sanitize($_GET['tgl_dari']) : date('Y-m-d');
    $tgl_sampai = isset($_GET['tgl_sampai']) ? sanitize($_GET['tgl_sampai']) : date('Y-m-d');
    $where_tgl = "DATE(dt.created_at) BETWEEN '$tgl_dari' AND '$tgl_sampai'";
} else {
    $tgl_dari   = $filter_bulan . '-01';
    $tgl_sampai = date('Y-m-t', strtotime($tgl_dari));
    $where_tgl  = "DATE_FORMAT(dt.created_at, '%Y-%m') = '$filter_bulan'";
}

$where_user = $filter_user ? "AND dt.user_id = '$filter_user'" : "";

// ── Daftar User untuk dropdown ───────────────────────────────────────────────
$query_users = "SELECT id, nama_lengkap, role FROM users ORDER BY nama_lengkap ASC";
$result_users = mysqli_query($conn, $query_users);
$users_list = [];
while ($u = mysqli_fetch_assoc($result_users)) {
    $users_list[] = $u;
}

// ── Rekap per User ───────────────────────────────────────────────────────────
$query_rekap = "SELECT
                    u.id AS user_id,
                    u.nama_lengkap,
                    u.role,
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
$grand_jasa   = 0;
$grand_pendapatan = 0;
while ($r = mysqli_fetch_assoc($result_rekap)) {
    $rekap_list[]      = $r;
    $grand_jasa       += $r['total_jasa'];
    $grand_pendapatan += $r['total_pendapatan'];
}

// ── Detail Tindakan per User ─────────────────────────────────────────────────
$query_detail = "SELECT
                    dt.id,
                    dt.created_at,
                    u.nama_lengkap   AS nama_user,
                    u.role,
                    mt.kode_tindakan,
                    mt.nama_tindakan,
                    dt.jumlah,
                    dt.tarif,
                    dt.subtotal,
                    jt.nominal_jasa,
                    (jt.nominal_jasa * dt.jumlah) AS total_jasa_baris,
                    pm.diagnosa,
                    ps.nama_lengkap  AS nama_pasien,
                    ps.no_rm
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
while ($d = mysqli_fetch_assoc($result_detail)) {
    $detail_list[] = $d;
}

// ── Role badge color ─────────────────────────────────────────────────────────
function roleBadge($role) {
    $map = [
        'admin'       => 'danger',
        'medis'       => 'primary',
        'apoteker'    => 'success',
        'petugas'     => 'warning',
        'pendaftaran' => 'info',
    ];
    return $map[$role] ?? 'secondary';
}
    // ── PROSES INPUT PENGELUARAN MANUAL ──────────────────────────────────────────
if (isset($_POST['tambah_pengeluaran'])) {
    $tgl   = sanitize($_POST['tgl_transaksi']);
    $ket   = sanitize($_POST['keterangan']);
    $nom   = sanitize($_POST['nominal']);
    $kat   = sanitize($_POST['kategori']);
    $uid   = $_SESSION['user_id'];
    mysqli_query($conn, "INSERT INTO pengeluaran_manual (tgl_transaksi, keterangan, nominal, kategori, user_id)
                         VALUES ('$tgl','$ket','$nom','$kat','$uid')");
}

if (isset($_POST['hapus_pengeluaran'])) {
    $hid = (int)$_POST['hapus_id'];
    mysqli_query($conn, "DELETE FROM pengeluaran_manual WHERE id = $hid");
}

// ── FILTER BUKU KAS ───────────────────────────────────────────────────────────
$kas_bulan  = isset($_GET['kas_bulan'])  ? sanitize($_GET['kas_bulan'])  : date('Y-m');
$kas_dari   = $kas_bulan . '-01';
$kas_sampai = date('Y-m-t', strtotime($kas_dari));

// ── PENDAPATAN ────────────────────────────────────────────────────────────────
// 1. Pembayaran resep (tunai/qris/transfer)
$q_pend1 = "SELECT DATE(tgl_bayar) AS tgl,
                   CONCAT('Pembayaran Resep - ', ps.nama_lengkap) AS ket,
                   total_setelah_diskon AS nominal,
                   'Pendapatan Pasien' AS kategori
            FROM pembayaran pb
            JOIN resep r  ON pb.resep_id = r.id
            JOIN pemeriksaan pm ON r.pemeriksaan_id = pm.id
            JOIN pendaftaran pd ON pm.pendaftaran_id = pd.id
            JOIN pasien ps ON pd.pasien_id = ps.id
            WHERE pb.status = 'lunas'
              
              AND DATE(pb.tgl_bayar) BETWEEN '$kas_dari' AND '$kas_sampai'";

// 2. Penjualan langsung
$q_pend2 = "SELECT DATE(tgl_penjualan) AS tgl,
                   CONCAT('Penjualan Langsung - ', nama_pembeli) AS ket,
                   total_bayar AS nominal,
                   'Penjualan Langsung' AS kategori
            FROM penjualan_langsung
            WHERE status = 'lunas'
              AND DATE(tgl_penjualan) BETWEEN '$kas_dari' AND '$kas_sampai'";

// 3. Pendapatan tindakan
$q_pend3 = "SELECT DATE(dt.created_at) AS tgl,
                   CONCAT('Tindakan - ', ps.nama_lengkap) AS ket,
                   dt.subtotal AS nominal,
                   'Pendapatan Tindakan' AS kategori
            FROM detail_tindakan dt
            JOIN pemeriksaan pm ON dt.pemeriksaan_id = pm.id
            JOIN pendaftaran pd ON pm.pendaftaran_id = pd.id
            JOIN pasien ps ON pd.pasien_id = ps.id
            WHERE dt.hapus = 0
              AND DATE(dt.created_at) BETWEEN '$kas_dari' AND '$kas_sampai'";
// ── PENGELUARAN ───────────────────────────────────────────────────────────────
// 1. Bayar hutang supplier
$q_kel1 = "SELECT DATE(ps2.tgl_bayar) AS tgl,
                  CONCAT('Bayar Supplier - ', s.nama_supplier, ' (', p.no_pembelian, ')') AS ket,
                  ps2.jumlah_bayar AS nominal,
                  'Pembayaran Supplier' AS kategori
           FROM pembayaran_supplier ps2
           JOIN pembelian p ON ps2.pembelian_id = p.id
           JOIN supplier s ON p.supplier_id = s.id
           WHERE DATE(ps2.tgl_bayar) BETWEEN '$kas_dari' AND '$kas_sampai'";

// 2. Pengeluaran manual
$q_kel2 = "SELECT tgl_transaksi AS tgl, keterangan AS ket, nominal, kategori
           FROM pengeluaran_manual
           WHERE DATE(tgl_transaksi) BETWEEN '$kas_dari' AND '$kas_sampai'";

// ── GABUNG & SORT ─────────────────────────────────────────────────────────────
$kas_rows = [];

foreach ([$q_pend1, $q_pend2, $q_pend3] as $q) {
    $res = mysqli_query($conn, $q);
    while ($row = mysqli_fetch_assoc($res)) {
        $kas_rows[] = ['tgl' => $row['tgl'], 'ket' => $row['ket'],
                       'nominal' => $row['nominal'], 'kategori' => $row['kategori'],
                       'tipe' => 'pendapatan'];
    }
}

foreach ([$q_kel1, $q_kel2] as $q) {
    $res = mysqli_query($conn, $q);
    while ($row = mysqli_fetch_assoc($res)) {
        $kas_rows[] = ['tgl' => $row['tgl'], 'ket' => $row['ket'],
                       'nominal' => $row['nominal'], 'kategori' => $row['kategori'],
                       'tipe' => 'pengeluaran'];
    }
}

// Sort by tanggal ASC
usort($kas_rows, fn($a, $b) => strcmp($b['tgl'], $a['tgl']));

// Hitung saldo berjalan
$total_masuk = $total_keluar = $saldo = 0;
foreach ($kas_rows as &$r) {
    if ($r['tipe'] === 'pendapatan') {
        $saldo += $r['nominal'];
        $total_masuk += $r['nominal'];
    } else {
        $saldo -= $r['nominal'];
        $total_keluar += $r['nominal'];
    }
    $r['saldo'] = $saldo;
}
unset($r);

// Ambil daftar pengeluaran manual untuk ditampilkan (bisa dihapus)
$res_manual = mysqli_query($conn, "SELECT pm.*, u.nama_lengkap FROM pengeluaran_manual pm
                                   JOIN users u ON pm.user_id = u.id
                                   WHERE DATE(pm.tgl_transaksi) BETWEEN '$kas_dari' AND '$kas_sampai'
                                   ORDER BY tgl_transaksi DESC");
$manual_list = [];
while ($row = mysqli_fetch_assoc($res_manual)) $manual_list[] = $row;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Jasa Tindakan - Healoka</title>
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php include 'sidebar_style.php'; ?>
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; }
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .col-md-10 { flex: 1; margin-left: 250px; padding: 0; }

        /* Stat Cards */
        .stat-card {
            border: none; border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform .2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon {
            width: 56px; height: 56px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: white;
        }

        /* Filter Card */
        .filter-card { border: none; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .filter-card .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white; border-radius: 16px 16px 0 0; font-weight: 600; padding: 14px 20px;
        }

        /* Rekap User Cards */
        .user-rekap-card {
            border: none; border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            transition: all .2s;
        }
        .user-rekap-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
        .user-avatar {
            width: 46px; height: 46px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 16px; flex-shrink: 0;
        }

        /* Detail Table */
        .detail-card { border: none; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .detail-card .card-header {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: white; border-radius: 16px 16px 0 0; font-weight: 600; padding: 14px 20px;
        }
        .table thead th { background: #f8f9fa; font-weight: 600; font-size: 13px; border-bottom: 2px solid #dee2e6; }
        .table tbody tr:hover { background: #f0f4ff; }
        .jasa-badge { background: #e8f5e9; color: #2e7d32; font-weight: 700; padding: 4px 10px; border-radius: 8px; }

        /* Grand total banner */
        .grand-banner {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 16px; color: white; padding: 20px 28px;
            box-shadow: 0 6px 20px rgba(102,126,234,0.35);
        }

        /* Tab styling */
        .nav-pills .nav-link { border-radius: 10px; font-weight: 500; color: #666; }
        .nav-pills .nav-link.active { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }

        @media print {
            .no-print { display: none !important; }
            .col-md-2, nav { display: none !important; }
            .col-md-10 { margin-left: 0 !important; }
        }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row m-0">
        <?php include 'sidebar.php'; ?>

            <div class="container-fluid px-4 py-4">

                <!-- ── Page Header ──────────────────────────────────────── -->
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="fas fa-hand-holding-usd me-2 text-primary"></i>Finance Jasa Tindakan</h4>
                        <small class="text-muted">Laporan pembagian jasa tindakan per staff</small>
                    </div>
                    <div class="d-flex gap-2 no-print">
                        <a href="setting_jasa.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-cog me-1"></i>Setting Nominal Jasa
                        </a>
                        <a href="export_finance.php?<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel me-1"></i>Export Excel
                        </a>
                        <button onclick="window.print()" class="btn btn-secondary btn-sm">
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                    </div>
                </div>

                <!-- ── Filter ────────────────────────────────────────────── -->
                <div class="card filter-card mb-4 no-print">
                    <div class="card-header"><i class="fas fa-filter me-2"></i>Filter Laporan</div>
                    <div class="card-body">
                        <form method="GET" action="finance.php">
                            <!-- Mode tabs -->
                            <div class="mb-3">
                                <ul class="nav nav-pills gap-2">
                                    <li class="nav-item">
                                        <a class="nav-link <?= $filter_mode === 'bulanan' ? 'active' : '' ?>"
                                           href="#" onclick="setMode('bulanan')">
                                            <i class="fas fa-calendar-alt me-1"></i>Bulanan
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?= $filter_mode === 'harian' ? 'active' : '' ?>"
                                           href="#" onclick="setMode('harian')">
                                            <i class="fas fa-calendar-day me-1"></i>Harian / Rentang
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?= $filter_mode === 'kas' ? 'active' : '' ?>"
                                           href="#" onclick="setMode('kas')">
                                            <i class="fas fa-book me-1"></i>Buku Kas
                                        </a>
                                    </li>
                                </ul>
                                <input type="hidden" name="mode" id="inputMode" value="<?= $filter_mode ?>">
                            </div>

                            <div class="row g-3 align-items-end">
                                <!-- Bulanan -->
                                <div class="col-md-3" id="fieldBulan" <?= $filter_mode === 'harian' ? 'style="display:none"' : '' ?>>
                                    <label class="form-label fw-semibold small">Bulan</label>
                                    <input type="month" name="bulan" class="form-control"
                                           value="<?= $filter_bulan ?>">
                                </div>

                                <!-- Harian -->
                                <div class="col-md-2" id="fieldDari" <?= $filter_mode !== 'harian' ? 'style="display:none"' : '' ?>>
                                    <label class="form-label fw-semibold small">Dari Tanggal</label>
                                    <input type="date" name="tgl_dari" class="form-control"
                                           value="<?= $tgl_dari ?>">
                                </div>
                                <div class="col-md-2" id="fieldSampai" <?= $filter_mode !== 'harian' ? 'style="display:none"' : '' ?>>
                                    <label class="form-label fw-semibold small">Sampai Tanggal</label>
                                    <input type="date" name="tgl_sampai" class="form-control"
                                           value="<?= $tgl_sampai ?>">
                                </div>

                                <!-- Filter User -->
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Staff / User</label>
                                    <select name="user_id" class="form-select">
                                        <option value="0">— Semua Staff —</option>
                                        <?php foreach ($users_list as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['nama_lengkap']) ?> (<?= $u['role'] ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-1"></i>Tampilkan
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($filter_mode !== 'kas'): ?>
                <!-- ── Periode Label ──────────────────────────────────────── -->
                <div class="mb-3">
                    <span class="badge bg-light text-dark border fs-6 fw-normal">
                        <i class="fas fa-calendar me-2 text-primary"></i>
                        <?php if ($filter_mode === 'harian'): ?>
                            <?= date('d M Y', strtotime($tgl_dari)) ?> — <?= date('d M Y', strtotime($tgl_sampai)) ?>
                        <?php else: ?>
                            <?= date('F Y', strtotime($tgl_dari)) ?>
                        <?php endif; ?>
                    </span>
                </div>

                <!-- ── Grand Total Banner ─────────────────────────────────── -->
                <div class="grand-banner mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="opacity-75 small mb-1">Total Pendapatan Klinik</div>
                        <div class="fs-4 fw-bold">Rp <?= number_format($grand_pendapatan, 0, ',', '.') ?></div>
                    </div>
                    <div class="text-end">
                        <div class="opacity-75 small mb-1">Total Jasa Staff</div>
                        <div class="fs-4 fw-bold">Rp <?= number_format($grand_jasa, 0, ',', '.') ?></div>
                    </div>
                    <div class="text-end">
                        <div class="opacity-75 small mb-1">Jumlah Staff Terlibat</div>
                        <div class="fs-4 fw-bold"><?= count($rekap_list) ?> Orang</div>
                    </div>
                </div>

                <!-- ── Rekap Per User ─────────────────────────────────────── -->
                <?php if (count($rekap_list) > 0): ?>
                <h5 class="fw-bold mb-3"><i class="fas fa-users me-2 text-primary"></i>Rekap Per Staff</h5>
                <div class="row g-3 mb-4">
                    <?php foreach ($rekap_list as $r): ?>
                    <div class="col-md-4">
                        <div class="card user-rekap-card p-3">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="user-avatar">
                                    <?= strtoupper(substr($r['nama_lengkap'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($r['nama_lengkap']) ?></div>
                                    <span class="badge bg-<?= roleBadge($r['role']) ?> mt-1"><?= $r['role'] ?></span>
                                </div>
                            </div>
                            <div class="row g-2 text-center">
                                <div class="col-4">
                                    <div class="bg-light rounded-3 p-2">
                                        <div class="fw-bold text-primary"><?= $r['total_tindakan'] ?></div>
                                        <div class="text-muted" style="font-size:11px;">Tindakan</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded-3 p-2">
                                        <div class="fw-bold text-info"><?= $r['total_jumlah'] ?></div>
                                        <div class="text-muted" style="font-size:11px;">Jumlah</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded-3 p-2">
                                        <div class="fw-bold text-success" style="font-size:12px;">
                                            Rp <?= number_format($r['total_pendapatan'], 0, ',', '.') ?>
                                        </div>
                                        <div class="text-muted" style="font-size:11px;">Pendapatan</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 p-3 rounded-3 text-center"
                                 style="background: linear-gradient(135deg,#667eea15,#764ba215); border: 1px solid #667eea30;">
                                <div class="text-muted small">Total Jasa Diterima</div>
                                <div class="fw-bold fs-5 text-primary">
                                    Rp <?= number_format($r['total_jasa'], 0, ',', '.') ?>
                                </div>
                            </div>
                            <!-- Link filter ke detail user ini -->
                            <a href="finance.php?mode=<?= $filter_mode ?>&bulan=<?= $filter_bulan ?>&tgl_dari=<?= $tgl_dari ?>&tgl_sampai=<?= $tgl_sampai ?>&user_id=<?= $r['user_id'] ?>"
                               class="btn btn-sm btn-outline-primary w-100 mt-2 no-print">
                                <i class="fas fa-list me-1"></i>Lihat Detail
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Tidak ada data jasa tindakan pada periode ini.</div>
                <?php endif; ?>

                <!-- ── Tabel Detail ───────────────────────────────────────── -->
                <!-- ── Tabel Detail ───────────────────────────────────────── -->
<?php if (count($detail_list) > 0): ?>
<div class="card detail-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table me-2"></i>Detail Tindakan per Staff</span>
        <span class="badge bg-white text-dark" id="recordCount"><?= count($detail_list) ?> record</span>
    </div>
    <div class="card-body">

        <!-- ── Search & Per-Page Controls ── -->
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3 no-print">
            <!-- Search -->
            <div class="input-group" style="max-width:360px;">
                <span class="input-group-text bg-white border-end-0">
                    <i class="fas fa-search text-muted small"></i>
                </span>
                <input type="text" id="searchInput" class="form-control border-start-0 ps-0"
                       placeholder="Cari staff, pasien, tindakan, kode...">
                <button class="btn btn-outline-secondary btn-sm" id="clearSearch" style="display:none;"
                        onclick="clearSearch()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <!-- Per page -->
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted mb-0">Tampilkan:</label>
                <select id="perPageSelect" class="form-select form-select-sm" style="width:80px;">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="0">Semua</option>
                </select>
                <span class="small text-muted">baris</span>
            </div>
        </div>

        <!-- Info hasil filter -->
        <div id="searchInfo" class="small text-muted mb-2" style="display:none;"></div>

    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="tblDetail">
                <thead>
                    <tr>
                        <th class="px-3">#</th>
                        <th>Tanggal</th>
                        <th>Staff</th>
                        <th>Pasien</th>
                        <th>Kode</th>
                        <th>Nama Tindakan</th>
                        <th class="text-center">Jml</th>
                        <th class="text-end">Tarif</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Nominal Jasa</th>
                        <th class="text-end">Total Jasa</th>
                    </tr>
                </thead>
                <tbody id="tblBody">
                    <?php
                    $prev_user = '';
                    $sub_jasa  = 0;
                    $sub_pend  = 0;
                    foreach ($detail_list as $i => $d):
                        if ($d['nama_user'] !== $prev_user):
                            if ($prev_user !== ''):
                    ?>
                    <!-- Subtotal row — tandai sebagai subtotal agar JS bisa skip saat filter -->
                    <tr class="table-primary fw-bold subtotal-row" data-subtotal="1">
                        <td colspan="8" class="text-end px-3">Subtotal <?= htmlspecialchars($prev_user) ?></td>
                        <td class="text-end">Rp <?= number_format($sub_pend, 0, ',', '.') ?></td>
                        <td></td>
                        <td class="text-end text-primary">Rp <?= number_format($sub_jasa, 0, ',', '.') ?></td>
                    </tr>
                    <?php
                            $sub_jasa = 0; $sub_pend = 0;
                            endif;
                            $prev_user = $d['nama_user'];
                        endif;
                        $sub_jasa += $d['total_jasa_baris'];
                        $sub_pend += $d['subtotal'];
                    ?>
                    <tr class="data-row"
                        data-search="<?= strtolower(htmlspecialchars(
                            $d['nama_user'].' '.$d['nama_pasien'].' '.$d['no_rm'].' '.
                            $d['kode_tindakan'].' '.$d['nama_tindakan'].' '.$d['role']
                        )) ?>">
                        <td class="px-3 text-muted small row-num"><?= $i + 1 ?></td>
                        <td class="small"><?= date('d/m/Y', strtotime($d['created_at'])) ?></td>
                        <td>
                            <div class="fw-semibold small"><?= htmlspecialchars($d['nama_user']) ?></div>
                            <span class="badge bg-<?= roleBadge($d['role']) ?>" style="font-size:10px;"><?= $d['role'] ?></span>
                        </td>
                        <td>
                            <div class="small fw-semibold"><?= htmlspecialchars($d['nama_pasien']) ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= $d['no_rm'] ?></div>
                        </td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($d['kode_tindakan']) ?></span></td>
                        <td class="small"><?= htmlspecialchars($d['nama_tindakan']) ?></td>
                        <td class="text-center"><?= $d['jumlah'] ?></td>
                        <td class="text-end small">Rp <?= number_format($d['tarif'], 0, ',', '.') ?></td>
                        <td class="text-end small">Rp <?= number_format($d['subtotal'], 0, ',', '.') ?></td>
                        <td class="text-end">
                            <span class="jasa-badge">Rp <?= number_format($d['nominal_jasa'], 0, ',', '.') ?></span>
                        </td>
                        <td class="text-end fw-bold text-success">
                            Rp <?= number_format($d['total_jasa_baris'], 0, ',', '.') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Last subtotal -->
                    <?php if ($prev_user): ?>
                    <tr class="table-primary fw-bold subtotal-row" data-subtotal="1">
                        <td colspan="8" class="text-end px-3">Subtotal <?= htmlspecialchars($prev_user) ?></td>
                        <td class="text-end">Rp <?= number_format($sub_pend, 0, ',', '.') ?></td>
                        <td></td>
                        <td class="text-end text-primary">Rp <?= number_format($sub_jasa, 0, ',', '.') ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="table-dark fw-bold">
                        <td colspan="8" class="text-end px-3">GRAND TOTAL</td>
                        <td class="text-end">Rp <?= number_format($grand_pendapatan, 0, ',', '.') ?></td>
                        <td></td>
                        <td class="text-end text-warning">Rp <?= number_format($grand_jasa, 0, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- ── Pagination bar ── -->
        <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-3 border-top no-print"
             id="paginationWrapper">
            <div class="small text-muted" id="paginationInfo"></div>
            <nav>
                <ul class="pagination pagination-sm mb-0 gap-1" id="paginationList"></ul>
            </nav>
        </div>

    </div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php if ($filter_mode === 'kas'): ?>
<!-- ── BUKU KAS ──
<div id="sectionBukuKas">

    <!-- Filter bulan kas -->
    <div class="card filter-card mb-4 no-print">
        <div class="card-header"><i class="fas fa-book me-2"></i>Buku Kas</div>
        <div class="card-body">
            <form method="GET" action="finance.php" class="row g-3 align-items-end">
                <input type="hidden" name="mode" value="kas">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Bulan</label>
                    <input type="month" name="kas_bulan" class="form-control" value="<?= $kas_bulan ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Tampilkan
                    </button>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#modalTambahPengeluaran">
                        <i class="fas fa-plus me-1"></i>Input Pengeluaran Manual
                    </button>
                </div>
                <div class="col-md-2">
                    <button onclick="window.print()" type="button" class="btn btn-secondary w-100 no-print">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary banner -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="p-4 rounded-3 text-white" style="background:linear-gradient(135deg,#10b981,#059669);">
                <div class="small opacity-75 mb-1">Total Pendapatan</div>
                <div class="fs-4 fw-bold">Rp <?= number_format($total_masuk, 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 rounded-3 text-white" style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                <div class="small opacity-75 mb-1">Total Pengeluaran</div>
                <div class="fs-4 fw-bold">Rp <?= number_format($total_keluar, 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-4 rounded-3 text-white" style="background:linear-gradient(135deg,#667eea,#764ba2);">
                <div class="small opacity-75 mb-1">Saldo Akhir</div>
                <div class="fs-4 fw-bold">Rp <?= number_format($total_masuk - $total_keluar, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- Tabel Buku Kas -->
    <div class="card mb-4" style="border:none;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">
        <div class="card-header d-flex justify-content-between align-items-center"
             style="background:linear-gradient(135deg,#10b981,#059669);color:white;border-radius:16px 16px 0 0;">
            <span><i class="fas fa-book me-2"></i>Buku Kas — <?= date('F Y', strtotime($kas_dari)) ?></span>
            <span class="badge bg-white text-dark"><?= count($kas_rows) ?> transaksi</span>
        </div>
        <div class="card-body p-0">
            <!-- Search & Per-Page Controls Buku Kas -->
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3 no-print px-3 pt-3">
                <div class="input-group" style="max-width:360px;">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-search text-muted small"></i>
                    </span>
                    <input type="text" id="kasSearchInput" class="form-control border-start-0 ps-0"
                           placeholder="Cari keterangan, kategori...">
                    <button class="btn btn-outline-secondary btn-sm" id="kasClearSearch" style="display:none;"
                            onclick="clearKasSearch()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted mb-0">Tampilkan:</label>
                    <select id="kasPerPageSelect" class="form-select form-select-sm" style="width:80px;">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="0">Semua</option>
                    </select>
                    <span class="small text-muted">baris</span>
                </div>
            </div>
            <div id="kasSearchInfo" class="small text-muted mb-2 px-3" style="display:none;"></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="tblKas">
                    <thead>
                        <tr>
                            <th class="px-3" style="width:40px;">#</th>
                            <th style="width:100px;">Tanggal</th>
                            <th>Keterangan</th>
                            <th style="width:120px;">Kategori</th>
                            <th class="text-end" style="width:150px;">Pendapatan</th>
                            <th class="text-end" style="width:150px;">Pengeluaran</th>
                            <th class="text-end" style="width:160px;">Saldo Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($kas_rows) > 0): ?>
                            <?php foreach ($kas_rows as $i => $r): ?>
                            <tr class="kas-data-row <?= $r['tipe'] === 'pendapatan' ? '' : 'table-danger bg-opacity-10' ?>"
    data-search="<?= strtolower(htmlspecialchars($r['ket'].' '.$r['kategori'].' '.$r['tipe'])) ?>">
                                <td class="px-3 text-muted small kas-row-num"><?= $i + 1 ?></td>
                                <td class="small"><?= date('d/m/Y', strtotime($r['tgl'])) ?></td>
                                <td>
                                    <div class="small fw-semibold"><?= htmlspecialchars($r['ket']) ?></div>
                                    <span class="badge" style="font-size:10px;background:<?= $r['tipe']==='pendapatan'?'#d1fae5;color:#065f46':'#fee2e2;color:#991b1b' ?>">
                                        <?= $r['tipe'] === 'pendapatan' ? '↑ Masuk' : '↓ Keluar' ?>
                                    </span>
                                </td>
                                <td><span class="badge bg-light text-dark border" style="font-size:10px;"><?= htmlspecialchars($r['kategori']) ?></span></td>
                                <td class="text-end fw-semibold text-success">
                                    <?= $r['tipe'] === 'pendapatan' ? 'Rp '.number_format($r['nominal'],0,',','.') : '' ?>
                                </td>
                                <td class="text-end fw-semibold text-danger">
                                    <?= $r['tipe'] === 'pengeluaran' ? 'Rp '.number_format($r['nominal'],0,',','.') : '' ?>
                                </td>
                                <td class="text-end fw-bold <?= $r['saldo'] >= 0 ? 'text-primary' : 'text-danger' ?>">
                                    Rp <?= number_format($r['saldo'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="fas fa-book-open fa-2x d-block mb-2 opacity-25"></i>
                                    Tidak ada transaksi pada periode ini
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot style="background:#f8f9fa;font-weight:700;">
                        <tr>
                            <td colspan="4" class="px-3 text-end">TOTAL</td>
                            <td class="text-end text-success">Rp <?= number_format($total_masuk, 0, ',', '.') ?></td>
                            <td class="text-end text-danger">Rp <?= number_format($total_keluar, 0, ',', '.') ?></td>
                            <td class="text-end text-primary">Rp <?= number_format($total_masuk - $total_keluar, 0, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <!-- Pagination Buku Kas -->
        <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-3 border-top no-print"
             id="kasPaginationWrapper">
            <div class="small text-muted" id="kasPaginationInfo"></div>
            <nav>
                <ul class="pagination pagination-sm mb-0 gap-1" id="kasPaginationList"></ul>
            </nav>
        </div>
    </div>
</div>

    <!-- Tabel Pengeluaran Manual (bisa dihapus) -->
    <?php if (count($manual_list) > 0): ?>
    <div class="card mb-4" style="border:none;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">
        <div class="card-header" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border-radius:16px 16px 0 0;">
            <i class="fas fa-edit me-2"></i>Pengeluaran Manual — Bisa Dihapus
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th class="px-3">#</th>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th>Kategori</th>
                        <th class="text-end">Nominal</th>
                        <th>Diinput Oleh</th>
                        <th class="no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($manual_list as $i => $m): ?>
                    <tr>
                        <td class="px-3"><?= $i+1 ?></td>
                        <td><?= date('d/m/Y', strtotime($m['tgl_transaksi'])) ?></td>
                        <td><?= htmlspecialchars($m['keterangan']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($m['kategori']) ?></span></td>
                        <td class="text-end text-danger fw-bold">Rp <?= number_format($m['nominal'],0,',','.') ?></td>
                        <td><small><?= htmlspecialchars($m['nama_lengkap']) ?></small></td>
                        <td class="no-print">
                            <form method="POST" onsubmit="return confirm('Hapus pengeluaran ini?')">
                                <input type="hidden" name="hapus_id" value="<?= $m['id'] ?>">
                                <input type="hidden" name="mode" value="kas">
                                <input type="hidden" name="kas_bulan" value="<?= $kas_bulan ?>">
                                <button type="submit" name="hapus_pengeluaran" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /sectionBukuKas -->
<?php endif; ?>   <!-- ← tambahkan ini -->

<!-- Modal Input Pengeluaran Manual -->
<div class="modal fade" id="modalTambahPengeluaran" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border-radius:16px 16px 0 0;">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Input Pengeluaran Manual</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="mode" value="kas">
                <input type="hidden" name="kas_bulan" value="<?= $kas_bulan ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tgl_transaksi" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Keterangan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="keterangan" placeholder="Contoh: Beli ATK, Bayar listrik..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nominal <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="nominal" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kategori</label>
                        <select class="form-select" name="kategori">
                            <option value="Umum">Umum</option>
                            <option value="Operasional">Operasional</option>
                            <option value="Gaji">Gaji</option>
                            <option value="Utilitas">Utilitas (Listrik/Air/Internet)</option>
                            <option value="Pemeliharaan">Pemeliharaan</option>
                            <option value="Lain-lain">Lain-lain</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_pengeluaran" class="btn btn-danger">
                        <i class="fas fa-save me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
            </div><!-- /container -->
        </div><!-- /col-md-10 -->
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setMode(mode) {
    document.getElementById('inputMode').value = mode;

    // Tampilkan/sembunyikan field filter sesuai mode
    document.getElementById('fieldBulan').style.display  = (mode === 'bulanan') ? '' : 'none';
    document.getElementById('fieldDari').style.display   = (mode === 'harian')  ? '' : 'none';
    document.getElementById('fieldSampai').style.display = (mode === 'harian')  ? '' : 'none';

    // Langsung submit form agar PHP yang render konten yang benar
    document.querySelector('form[action="finance.php"]').submit();
}
</script>
<script>
/* ══════════════════════════════════════════════
   SEARCH + PAGINATION — Detail Tindakan
   ══════════════════════════════════════════════ */
(function () {
    const searchInput   = document.getElementById('searchInput');
    const clearBtn      = document.getElementById('clearSearch');
    const perPageSelect = document.getElementById('perPageSelect');
    const searchInfo    = document.getElementById('searchInfo');
    const recordCount   = document.getElementById('recordCount');
    const paginationInfo= document.getElementById('paginationInfo');
    const paginationList= document.getElementById('paginationList');

    if (!searchInput) return; // halaman tanpa tabel detail

    // Kumpulkan semua data-row (bukan subtotal)
    const allDataRows = Array.from(document.querySelectorAll('#tblBody tr.data-row'));
    // Subtotal rows akan disembunyikan saat search aktif
    const subtotalRows = Array.from(document.querySelectorAll('#tblBody tr.subtotal-row'));

    let filteredRows = [...allDataRows];
    let currentPage  = 1;
    let perPage      = parseInt(perPageSelect.value);
    let searchQuery  = '';

    // ── Fungsi utama render ──────────────────────────
    function render() {
        perPage = parseInt(perPageSelect.value);
        const isSearching = searchQuery.trim() !== '';

        // Filter
        filteredRows = allDataRows.filter(tr =>
            !isSearching || tr.dataset.search.includes(searchQuery.trim().toLowerCase())
        );

        const totalFiltered = filteredRows.length;
        const totalAll      = allDataRows.length;

        // Sembunyikan subtotal row saat ada pencarian agar tidak membingungkan
        subtotalRows.forEach(tr => {
            tr.style.display = isSearching ? 'none' : '';
        });

        // Sembunyikan semua data-row dulu
        allDataRows.forEach(tr => tr.style.display = 'none');

        // Pagination logic
        const showAll   = perPage === 0;
        const totalPages= showAll ? 1 : Math.ceil(totalFiltered / perPage);

        // Clamp current page
        if (currentPage > totalPages) currentPage = totalPages || 1;

        const start = showAll ? 0 : (currentPage - 1) * perPage;
        const end   = showAll ? totalFiltered : Math.min(start + perPage, totalFiltered);
        const pageRows = filteredRows.slice(start, end);

        // Tampilkan baris yang masuk halaman ini + re-number
        pageRows.forEach((tr, idx) => {
            tr.style.display = '';
            const numCell = tr.querySelector('.row-num');
            if (numCell) numCell.textContent = start + idx + 1;
        });

        // Update badge count
        if (recordCount) {
            recordCount.textContent = isSearching
                ? `${totalFiltered} / ${totalAll} record`
                : `${totalAll} record`;
        }

        // Info teks
        if (isSearching) {
            searchInfo.style.display = '';
            searchInfo.innerHTML = totalFiltered > 0
                ? `<i class="fas fa-filter me-1 text-primary"></i>Menampilkan <strong>${totalFiltered}</strong> dari <strong>${totalAll}</strong> record yang cocok dengan "<strong>${escHtml(searchQuery)}</strong>"`
                : `<i class="fas fa-exclamation-circle me-1 text-warning"></i>Tidak ada hasil untuk "<strong>${escHtml(searchQuery)}</strong>"`;
            clearBtn.style.display = '';
        } else {
            searchInfo.style.display = 'none';
            clearBtn.style.display   = 'none';
        }

        // Pagination info
        if (totalFiltered === 0) {
            paginationInfo.textContent = 'Tidak ada data';
        } else {
            paginationInfo.textContent =
                `Menampilkan ${start + 1}–${end} dari ${totalFiltered} baris`;
        }

        // Build pagination buttons
        renderPagination(totalPages, showAll);
    }

    // ── Render tombol pagination ────────────────────
    function renderPagination(totalPages, showAll) {
        paginationList.innerHTML = '';
        if (showAll || totalPages <= 1) return;

        const range = buildPageRange(currentPage, totalPages);

        // Prev
        appendPageBtn('&laquo;', currentPage - 1, currentPage === 1);

        range.forEach(p => {
            if (p === '...') {
                const li = document.createElement('li');
                li.className = 'page-item disabled';
                li.innerHTML = '<span class="page-link">…</span>';
                paginationList.appendChild(li);
            } else {
                appendPageBtn(p, p, false, p === currentPage);
            }
        });

        // Next
        appendPageBtn('&raquo;', currentPage + 1, currentPage === totalPages);
    }

    function appendPageBtn(label, targetPage, disabled, active = false) {
        const li  = document.createElement('li');
        li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
        const a   = document.createElement('a');
        a.className = 'page-link';
        a.href = '#tblDetail';
        a.innerHTML = label;
        if (!disabled) {
            a.addEventListener('click', e => {
                e.preventDefault();
                currentPage = targetPage;
                render();
                // Scroll ke tabel
                document.getElementById('tblDetail').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
        li.appendChild(a);
        paginationList.appendChild(li);
    }

    // Buat range halaman yang rapi: 1 … 4 5 6 … 20
    function buildPageRange(cur, total) {
        if (total <= 7) return Array.from({length: total}, (_, i) => i + 1);
        const pages = new Set([1, total, cur]);
        for (let i = cur - 1; i <= cur + 1; i++) if (i > 0 && i <= total) pages.add(i);
        const sorted = [...pages].sort((a, b) => a - b);
        const result = [];
        sorted.forEach((p, idx) => {
            if (idx > 0 && p - sorted[idx - 1] > 1) result.push('...');
            result.push(p);
        });
        return result;
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Event listeners ─────────────────────────────
    let debounceTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            searchQuery  = searchInput.value;
            currentPage  = 1;
            render();
        }, 250);
    });

    perPageSelect.addEventListener('change', () => {
        currentPage = 1;
        render();
    });

    // ── Init ─────────────────────────────────────────
    render();

    // Expose clearSearch ke global scope
    window.clearSearch = function () {
        searchInput.value = '';
        searchQuery = '';
        currentPage = 1;
        render();
    };
})();
</script>
<script>
(function () {
    const searchInput    = document.getElementById('kasSearchInput');
    const clearBtn       = document.getElementById('kasClearSearch');
    const perPageSelect  = document.getElementById('kasPerPageSelect');
    const searchInfo     = document.getElementById('kasSearchInfo');
    const paginationInfo = document.getElementById('kasPaginationInfo');
    const paginationList = document.getElementById('kasPaginationList');

    if (!searchInput) return;

    const allRows = Array.from(document.querySelectorAll('#tblKas tbody tr.kas-data-row'));

    let filteredRows = [...allRows];
    let currentPage  = 1;
    let perPage      = parseInt(perPageSelect.value);
    let searchQuery  = '';

    function render() {
        perPage = parseInt(perPageSelect.value);
        const isSearching = searchQuery.trim() !== '';

        filteredRows = allRows.filter(tr =>
            !isSearching || tr.dataset.search.includes(searchQuery.trim().toLowerCase())
        );

        const totalFiltered = filteredRows.length;
        const totalAll      = allRows.length;

        allRows.forEach(tr => tr.style.display = 'none');

        const showAll    = perPage === 0;
        const totalPages = showAll ? 1 : Math.ceil(totalFiltered / perPage);
        if (currentPage > totalPages) currentPage = totalPages || 1;

        const start    = showAll ? 0 : (currentPage - 1) * perPage;
        const end      = showAll ? totalFiltered : Math.min(start + perPage, totalFiltered);
        const pageRows = filteredRows.slice(start, end);

        pageRows.forEach((tr, idx) => {
            tr.style.display = '';
            const numCell = tr.querySelector('.kas-row-num');
            if (numCell) numCell.textContent = start + idx + 1;
        });

        if (isSearching) {
            searchInfo.style.display = '';
            searchInfo.innerHTML = totalFiltered > 0
                ? `<i class="fas fa-filter me-1 text-primary"></i>Menampilkan <strong>${totalFiltered}</strong> dari <strong>${totalAll}</strong> record cocok dengan "<strong>${escHtml(searchQuery)}</strong>"`
                : `<i class="fas fa-exclamation-circle me-1 text-warning"></i>Tidak ada hasil untuk "<strong>${escHtml(searchQuery)}</strong>"`;
            clearBtn.style.display = '';
        } else {
            searchInfo.style.display = 'none';
            clearBtn.style.display   = 'none';
        }

        paginationInfo.textContent = totalFiltered === 0
            ? 'Tidak ada data'
            : `Menampilkan ${start + 1}–${end} dari ${totalFiltered} baris`;

        renderPagination(totalPages, showAll);
    }

    function renderPagination(totalPages, showAll) {
        paginationList.innerHTML = '';
        if (showAll || totalPages <= 1) return;

        const range = buildPageRange(currentPage, totalPages);
        appendPageBtn('&laquo;', currentPage - 1, currentPage === 1);
        range.forEach(p => {
            if (p === '...') {
                const li = document.createElement('li');
                li.className = 'page-item disabled';
                li.innerHTML = '<span class="page-link">…</span>';
                paginationList.appendChild(li);
            } else {
                appendPageBtn(p, p, false, p === currentPage);
            }
        });
        appendPageBtn('&raquo;', currentPage + 1, currentPage === totalPages);
    }

    function appendPageBtn(label, targetPage, disabled, active = false) {
        const li = document.createElement('li');
        li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#tblKas';
        a.innerHTML = label;
        if (!disabled) {
            a.addEventListener('click', e => {
                e.preventDefault();
                currentPage = targetPage;
                render();
                document.getElementById('tblKas').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
        li.appendChild(a);
        paginationList.appendChild(li);
    }

    function buildPageRange(cur, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        const pages = new Set([1, total, cur]);
        for (let i = cur - 1; i <= cur + 1; i++) if (i > 0 && i <= total) pages.add(i);
        const sorted = [...pages].sort((a, b) => a - b);
        const result = [];
        sorted.forEach((p, idx) => {
            if (idx > 0 && p - sorted[idx - 1] > 1) result.push('...');
            result.push(p);
        });
        return result;
    }

    function escHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    let debounceTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            searchQuery = searchInput.value;
            currentPage = 1;
            render();
        }, 250);
    });

    perPageSelect.addEventListener('change', () => {
        currentPage = 1;
        render();
    });

    render();

    window.clearKasSearch = function () {
        searchInput.value = '';
        searchQuery = '';
        currentPage = 1;
        render();
    };
})();
</script>
</body>
</html>