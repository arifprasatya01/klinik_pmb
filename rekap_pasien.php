<?php


session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db   = new Database();
$conn = $db->getConnection();

$role       = $_SESSION['role'];
$user_id    = $_SESSION['user_id'];
$nama_login = $_SESSION['nama_lengkap'] ?? '';

// Hanya admin dan petugas yang boleh akses
if (!in_array($role, ['admin', 'petugas'])) {
    header("Location: index.php");
    exit();
}

// -- Filter dari GET -----------------------------------------------------------
$filter_tgl_dari   = (isset($_GET['tgl_dari'])      && $_GET['tgl_dari']      !== '') ? $_GET['tgl_dari']      : date('Y-m-01');
$filter_tgl_sampai = (isset($_GET['tgl_sampai'])    && $_GET['tgl_sampai']    !== '') ? $_GET['tgl_sampai']    : date('Y-m-d');
$filter_status     = isset($_GET['status'])          ? $_GET['status']          : '';
$filter_poli       = isset($_GET['poli'])             ? $_GET['poli']             : '';
$filter_jenis      = isset($_GET['jenis_pasien'])    ? $_GET['jenis_pasien']    : '';
// Hanya admin boleh filter per petugas
$filter_petugas    = ($role === 'admin' && isset($_GET['petugas_id'])) ? (int)$_GET['petugas_id'] : 0;

// -- Build WHERE ---------------------------------------------------------------
$where_parts = [
    "DATE(pd.tgl_daftar) BETWEEN '$filter_tgl_dari' AND '$filter_tgl_sampai'"
];

// Role petugas: paksa hanya data miliknya
if ($role === 'petugas') {
    $where_parts[] = "pd.petugas_id = $user_id";
} elseif ($role === 'admin' && $filter_petugas > 0) {
    $where_parts[] = "pd.petugas_id = $filter_petugas";
}

if ($filter_status !== '') $where_parts[] = "pd.status = '"         . mysqli_real_escape_string($conn, $filter_status) . "'";
if ($filter_poli   !== '') $where_parts[] = "pd.poli = '"           . mysqli_real_escape_string($conn, $filter_poli)   . "'";
if ($filter_jenis  !== '') $where_parts[] = "ps.jenis_pasien = '"   . mysqli_real_escape_string($conn, $filter_jenis)  . "'";

$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

// -- Query Utama ---------------------------------------------------------------
$query_rekap = "
SELECT
        pd.id               AS pendaftaran_id,
        pd.no_antrian,
        pd.tgl_daftar,
        pd.keluhan,
        pd.poli,
        pd.status,
        ps.no_rm,
        ps.nama_lengkap     AS nama_pasien,
        ps.jenis_kelamin,
        ps.jenis_pasien,
        ps.tgl_lahir,
        ps.no_telepon,
        u_petugas.nama_lengkap AS nama_petugas,
        u_petugas.id           AS petugas_id,
        pm.diagnosa,
        pm.tgl_pemeriksaan,
        pm.id               AS pemeriksaan_id,
        u_dokter.nama_lengkap  AS nama_dokter,
        py.total_bayar,
        py.metode_bayar,
        py.status           AS status_bayar,
        py.tgl_bayar
    FROM pendaftaran pd
    JOIN pasien ps            ON pd.pasien_id      = ps.id
    LEFT JOIN users u_petugas ON pd.petugas_id     = u_petugas.id
    LEFT JOIN pemeriksaan pm  ON pm.pendaftaran_id = pd.id
    LEFT JOIN users u_dokter  ON pm.dokter_id      = u_dokter.id
		LEFT JOIN resep r ON pm.id = r.pemeriksaan_id
    RIGHT JOIN pembayaran py   ON py.resep_id = r.id
    $where_sql
    AND py.status_hapus='0'
    ORDER BY pd.tgl_daftar DESC, pd.id DESC
";

$result_rekap = mysqli_query($conn, $query_rekap);
$data_rekap   = [];
while ($row = mysqli_fetch_assoc($result_rekap)) $data_rekap[] = $row;

// -- Statistik -----------------------------------------------------------------
$total_kunjungan  = count($data_rekap);
$total_selesai    = count(array_filter($data_rekap, fn($r) => $r['status'] === 'selesai'));
$total_proses     = count(array_filter($data_rekap, fn($r) => in_array($r['status'], ['menunggu','diperiksa'])));
$total_pendapatan = array_sum(array_column(
    array_filter($data_rekap, fn($r) => $r['status_bayar'] === 'lunas'), 'total_bayar'
));
$total_bpjs  = count(array_filter($data_rekap, fn($r) => $r['jenis_pasien'] === 'bpjs'));
$total_umum  = count(array_filter($data_rekap, fn($r) => $r['jenis_pasien'] === 'umum'));

// -- Daftar petugas (admin) ----------------------------------------------------
$list_petugas = [];
if ($role === 'admin') {
    $qp = mysqli_query($conn, "SELECT id, nama_lengkap FROM users WHERE role = 'petugas' ORDER BY nama_lengkap");
    while ($r = mysqli_fetch_assoc($qp)) $list_petugas[] = $r;
}

// -- Rekap per petugas ---------------------------------------------------------
$query_per_petugas = "
    SELECT
        u.nama_lengkap      AS nama_petugas,
        COUNT(pd.id)        AS total,
        SUM(pd.status = 'selesai') AS selesai,
        SUM(pd.status NOT IN ('selesai','batal')) AS proses,
        COALESCE(SUM(CASE WHEN py.status='lunas' THEN py.total_bayar ELSE 0 END),0) AS pendapatan
    FROM pendaftaran pd
    JOIN pasien ps              ON pd.pasien_id    = ps.id
    LEFT JOIN users u           ON pd.petugas_id   = u.id
    LEFT JOIN pemeriksaan pm    ON pm.pendaftaran_id = pd.id
		LEFT JOIN resep r ON pm.pendaftaran_id = r.pemeriksaan_id
    LEFT JOIN pembayaran py     ON py.resep_id = r.id
    $where_sql
    GROUP BY pd.petugas_id, u.nama_lengkap
    ORDER BY total DESC
";
$data_per_petugas = [];
$res_pp = mysqli_query($conn, $query_per_petugas);
while ($r = mysqli_fetch_assoc($res_pp)) $data_per_petugas[] = $r;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

        <!-- Favicon -->
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link rel="shortcut icon" type="image/png" href ="asset/img/logo.png">
<title>Rekap Pasien - Healoka</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<?php include 'sidebar_style.php'; ?>
<style>
:root { --grad: linear-gradient(135deg,#667eea 0%,#764ba2 100%); }

/* Stat cards */
.stat-card { border:none; border-radius:14px; padding:20px 22px; position:relative; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,.1); }
.stat-card::after { content:''; position:absolute; right:-16px; top:-16px; width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,.15); }
.stat-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; margin-bottom:12px; background:rgba(255,255,255,.25); color:#fff; }
.stat-number { font-size:1.9rem; font-weight:800; color:#fff; line-height:1.1; }
.stat-label { font-size:.78rem; color:rgba(255,255,255,.85); margin-top:4px; font-weight:500; }
.bg-g1 { background:linear-gradient(135deg,#667eea,#764ba2); }
.bg-g2 { background:linear-gradient(135deg,#11998e,#38ef7d); }
.bg-g3 { background:linear-gradient(135deg,#f7971e,#ffd200); }
.bg-g4 { background:linear-gradient(135deg,#1fa2ff,#12d8fa); }
.bg-g5 { background:linear-gradient(135deg,#a18cd1,#fbc2eb); }
.bg-g6 { background:linear-gradient(135deg,#ee0979,#ff6a00); }

/* Filter */
.filter-wrap { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.07); border:none; }
.filter-wrap .card-header { background:var(--grad); border-radius:14px 14px 0 0; color:#fff; font-weight:700; padding:13px 20px; }

/* Table */
.tbl-wrap { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.07); overflow:hidden; }
.tbl-wrap .card-header { background:#fff; border-bottom:2px solid #f1f5f9; padding:15px 20px; font-weight:700; color:#2d3748; }
.table thead th { background:#f8fafc; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#718096; border-bottom:2px solid #e2e8f0; padding:10px 12px; white-space:nowrap; }
.table tbody td { font-size:.875rem; padding:10px 12px; vertical-align:middle; }
.table tbody tr:hover { background:#f7f8ff; }

/* Badges */
.bs { padding:4px 10px; border-radius:20px; font-size:.75rem; font-weight:600; display:inline-flex; align-items:center; gap:3px; }
.bs-selesai  { background:#d1fae5; color:#065f46; }
.bs-proses   { background:#dbeafe; color:#1e40af; }
.bs-menunggu { background:#fef3c7; color:#92400e; }
.bs-batal    { background:#fee2e2; color:#991b1b; }
.bs-umum     { background:#e0e7ff; color:#3730a3; }
.bs-bpjs     { background:#d1fae5; color:#065f46; }
.bs-asuransi { background:#fce7f3; color:#9d174d; }
.bs-lunas    { background:#d1fae5; color:#065f46; }
.bs-belum    { background:#fee2e2; color:#991b1b; }

/* Petugas cards */
.pt-card { border-radius:12px; border:1px solid #e2e8f0; padding:14px 16px; background:#fff; transition:.2s; }
.pt-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.1); border-color:#667eea; }
.pt-ava { width:40px; height:40px; border-radius:50%; background:var(--grad); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; font-size:.9rem; flex-shrink:0; }
.mini-stat { text-align:center; min-width:38px; }
.mini-num { font-size:1.1rem; font-weight:800; color:#2d3748; }
.mini-lbl { font-size:.65rem; color:#a0aec0; }
.dv { width:1px; background:#e2e8f0; height:32px; }

/* Role banner */
.role-banner { border-radius:10px; padding:10px 16px; font-size:.85rem; font-weight:500; display:flex; align-items:center; gap:8px; }
.banner-admin   { background:#ede9fe; color:#5b21b6; border-left:4px solid #7c3aed; }
.banner-petugas { background:#dbeafe; color:#1e40af; border-left:4px solid #3b82f6; }

/* Print */
@media print {
    .sidebar, .no-print, .desktop-topnav { display:none!important; }
    .main-content { margin-left:0!important; }
}
</style>
</head>
<body>
<div class="container-fluid px-0">
<div class="row g-0">

<?php include 'sidebar.php'; ?>

<div class="main-content px-3 px-md-4 py-4" style="margin-left:0;">

    <!-- Judul -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 no-print">
        <div>
            <h5 class="mb-0 fw-bold"><i class="fas fa-clipboard-list me-2" style="color:#667eea"></i>Rekap Pasien</h5>
            <small class="text-muted">
                <?php if ($role === 'admin'): ?>Rekap seluruh petugas
                <?php else: ?>Rekap pasien Anda: <strong><?= htmlspecialchars($nama_login) ?></strong><?php endif; ?>
            </small>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-print me-1"></i>Cetak
            </button>
        </div>
    </div>

    <!-- Banner role -->
    <div class="role-banner <?= $role === 'admin' ? 'banner-admin' : 'banner-petugas' ?> mb-3 no-print">
        <i class="fas fa-<?= $role === 'admin' ? 'shield-alt' : 'user-circle' ?>"></i>
        <?php if ($role === 'admin'): ?>
            <span>Login sebagai <strong>Admin</strong> � Menampilkan rekap semua petugas. Gunakan filter "Petugas" untuk melihat per individu.</span>
        <?php else: ?>
            <span>Login sebagai <strong>Petugas</strong> � Hanya menampilkan pasien yang Anda daftarkan.</span>
        <?php endif; ?>
    </div>

    <!-- FILTER -->
    <div class="filter-wrap card mb-3 no-print">
        <div class="card-header"><i class="fas fa-filter me-2"></i>Filter</div>
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold small mb-1">Dari Tanggal</label>
                    <input type="date" name="tgl_dari" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_tgl_dari) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold small mb-1">Sampai Tanggal</label>
                    <input type="date" name="tgl_sampai" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_tgl_sampai) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="menunggu"  <?= $filter_status==='menunggu'  ?'selected':'' ?>>Menunggu</option>
                        <option value="diperiksa" <?= $filter_status==='diperiksa' ?'selected':'' ?>>Diperiksa</option>
                        <option value="selesai"   <?= $filter_status==='selesai'   ?'selected':'' ?>>Selesai</option>
                        <option value="batal"     <?= $filter_status==='batal'     ?'selected':'' ?>>Batal</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold small mb-1">Jenis Pasien</label>
                    <select name="jenis_pasien" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="umum"     <?= $filter_jenis==='umum'     ?'selected':'' ?>>Umum</option>
                        <option value="bpjs"     <?= $filter_jenis==='bpjs'     ?'selected':'' ?>>BPJS</option>
                        <option value="asuransi" <?= $filter_jenis==='asuransi' ?'selected':'' ?>>Asuransi</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold small mb-1">Poli</label>
                    <select name="poli" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="umum"      <?= $filter_poli==='umum'      ?'selected':'' ?>>Umum</option>
                        <option value="kebidanan" <?= $filter_poli==='kebidanan' ?'selected':'' ?>>Kebidanan</option>
                    </select>
                </div>
                <?php if ($role === 'admin'): ?>
                <div class="col-6 col-md-2">
                    <label class="form-label fw-semibold small mb-1">
                        <i class="fas fa-user-nurse me-1"></i>Filter Petugas
                    </label>
                    <select name="petugas_id" class="form-select form-select-sm">
                        <option value="">Semua Petugas</option>
                        <?php foreach ($list_petugas as $pt): ?>
                        <option value="<?= $pt['id'] ?>" <?= $filter_petugas==$pt['id']?'selected':'' ?>>
                            <?= htmlspecialchars($pt['nama_lengkap']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-12 d-flex gap-2 justify-content-end">
                    <button type="submit" class="btn btn-sm btn-primary px-4">
                        <i class="fas fa-search me-1"></i>Tampilkan
                    </button>
                    <a href="rekap_pasien.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- STATISTIK -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card bg-g1 h-100">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?= number_format($total_kunjungan) ?></div>
                <div class="stat-label">Total Kunjungan</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card bg-g2 h-100">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?= number_format($total_selesai) ?></div>
                <div class="stat-label">Selesai</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card bg-g3 h-100">
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-number"><?= number_format($total_proses) ?></div>
                <div class="stat-label">Sedang Proses</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card bg-g4 h-100">
                <div class="stat-icon"><i class="fas fa-id-card"></i></div>
                <div class="stat-number"><?= number_format($total_bpjs) ?></div>
                <div class="stat-label">Pasien BPJS</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card bg-g5 h-100">
                <div class="stat-icon"><i class="fas fa-user"></i></div>
                <div class="stat-number"><?= number_format($total_umum) ?></div>
                <div class="stat-label">Pasien Umum</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card bg-g6 h-100">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-number" style="font-size:1.1rem">
                    Rp <?= number_format($total_pendapatan, 0, ',', '.') ?>
                </div>
                <div class="stat-label">Total Pendapatan Lunas</div>
            </div>
        </div>
    </div>

    <!-- REKAP PER PETUGAS -->
    <?php if (!empty($data_per_petugas)): ?>
    <div class="tbl-wrap card mb-3">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="fas fa-user-nurse" style="color:#667eea"></i>
            <?php if ($role === 'admin'): ?>
                Rekap Per Petugas
                <span class="badge" style="background:#ede9fe;color:#5b21b6"><?= count($data_per_petugas) ?> petugas</span>
            <?php else: ?>
                Rekap Kinerja Anda
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($data_per_petugas as $p): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="pt-card d-flex align-items-center gap-3">
                        <div class="pt-ava"><?= strtoupper(substr($p['nama_petugas'] ?? 'P', 0, 1)) ?></div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-bold text-truncate"><?= htmlspecialchars($p['nama_petugas'] ?? 'Tidak diketahui') ?></div>
                            <small class="text-muted">
                                <?= date('d/m/Y', strtotime($filter_tgl_dari)) ?> � <?= date('d/m/Y', strtotime($filter_tgl_sampai)) ?>
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <div class="mini-stat">
                                <div class="mini-num text-primary"><?= $p['total'] ?></div>
                                <div class="mini-lbl">Total</div>
                            </div>
                            <div class="dv"></div>
                            <div class="mini-stat">
                                <div class="mini-num text-success"><?= $p['selesai'] ?></div>
                                <div class="mini-lbl">Selesai</div>
                            </div>
                            <div class="dv"></div>
                            <div class="mini-stat">
                                <div class="mini-num text-warning"><?= $p['proses'] ?></div>
                                <div class="mini-lbl">Proses</div>
                            </div>
                            <div class="dv"></div>
                            <div class="mini-stat">
                                <div class="mini-num text-danger" style="font-size:.85rem">
                                    <?= 'Rp' . number_format($p['pendapatan'], 0, ',', '.') ?>
                                </div>
                                <div class="mini-lbl">Pendapatan</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TABEL DETAIL -->
    <div class="tbl-wrap card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span>
                <i class="fas fa-table me-2" style="color:#667eea"></i>Detail Kunjungan
                <span class="badge" style="background:#e0e7ff;color:#3730a3"><?= number_format($total_kunjungan) ?> data</span>
            </span>
            <input type="text" id="searchInput" class="form-control form-control-sm no-print"
                   placeholder="?? Cari nama / no. RM..." style="width:210px"
                   oninput="cariTabel(this.value)">
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="mainTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>No. Antrian</th>
                        <th>Tanggal</th>
                        <th>No. RM</th>
                        <th>Nama Pasien</th>
                        <th>JK</th>
                        <th>Jenis</th>
                        <th>Poli</th>
                        <th>Keluhan</th>
                        <th>Diagnosa</th>
                        <th>Dokter</th>
                        <?php if ($role === 'admin'): ?>
                        <th style="background:#f5f3ff">Petugas</th>
                        <?php endif; ?>
                        <th>Status</th>
                        <th>Bayar</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                <?php if (empty($data_rekap)): ?>
                    <tr>
                        <td colspan="<?= $role==='admin' ? 15 : 14 ?>" class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-2x d-block mb-2 opacity-25"></i>
                            Tidak ada data pada periode ini
                        </td>
                    </tr>
                <?php else: ?>
                <?php foreach ($data_rekap as $i => $r): ?>
                    <tr>
                        <td class="text-muted small"><?= $i + 1 ?></td>
                        <td>
                            <span class="badge bg-light text-dark border fw-bold" style="font-family:monospace;font-size:.8rem">
                                <?= htmlspecialchars($r['no_antrian']) ?>
                            </span>
                        </td>
                        <td class="small text-nowrap">
                            <?= date('d/m/Y', strtotime($r['tgl_daftar'])) ?>
                            <div class="text-muted"><?= date('H:i', strtotime($r['tgl_daftar'])) ?></div>
                        </td>
                        <td>
                            <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:.8rem">
                                <?= htmlspecialchars($r['no_rm']) ?>
                            </code>
                        </td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($r['nama_pasien']) ?></div>
                            <?php if ($r['tgl_lahir']): ?>
                            <small class="text-muted"><?= (new DateTime($r['tgl_lahir']))->diff(new DateTime())->y ?> th</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge rounded-pill <?= $r['jenis_kelamin']==='L' ? 'bg-primary' : 'bg-danger' ?>">
                                <?= htmlspecialchars($r['jenis_kelamin']) ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $jp = $r['jenis_pasien'];
                            [$jc, $jl] = match($jp) {
                                'bpjs'     => ['bs-bpjs',     'BPJS'],
                                'asuransi' => ['bs-asuransi', 'Asuransi'],
                                default    => ['bs-umum',     'Umum'],
                            };
                            ?>
                            <span class="bs <?= $jc ?>"><?= $jl ?></span>
                        </td>
                        <td class="small text-capitalize"><?= htmlspecialchars($r['poli']) ?></td>
                        <td>
                            <span class="small text-muted" style="max-width:120px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                  title="<?= htmlspecialchars($r['keluhan'] ?? '') ?>">
                                <?= htmlspecialchars($r['keluhan'] ?? '�') ?>
                            </span>
                        </td>
                        <td>
                            <span class="small" style="max-width:120px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                  title="<?= htmlspecialchars($r['diagnosa'] ?? '') ?>">
                                <?= htmlspecialchars($r['diagnosa'] ?? '�') ?>
                            </span>
                        </td>
                        <td class="small"><?= htmlspecialchars($r['nama_dokter'] ?? '�') ?></td>

                        <?php if ($role === 'admin'): ?>
                        <!-- Kolom Petugas � hanya muncul untuk admin -->
                        <td style="background:#faf5ff">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.7rem;font-weight:700;flex-shrink:0">
                                    <?= strtoupper(substr($r['nama_petugas'] ?? 'P', 0, 1)) ?>
                                </div>
                                <span class="small fw-semibold text-truncate" style="max-width:90px">
                                    <?= htmlspecialchars($r['nama_petugas'] ?? 'Tidak diketahui') ?>
                                </span>
                            </div>
                        </td>
                        <?php endif; ?>

                        <td>
                            <?php
                            $st = $r['status'];
                            [$sc, $sl] = match($st) {
                                'selesai'   => ['bs-selesai',  '<i class="fas fa-check me-1"></i>Selesai'],
                                'diperiksa' => ['bs-proses',   '<i class="fas fa-stethoscope me-1"></i>Diperiksa'],
                                'menunggu'  => ['bs-menunggu', '<i class="fas fa-clock me-1"></i>Menunggu'],
                                'batal'     => ['bs-batal',    '<i class="fas fa-times me-1"></i>Batal'],
                                default     => ['bs-menunggu', ucfirst($st)],
                            };
                            ?>
                            <span class="bs <?= $sc ?>"><?= $sl ?></span>
                        </td>
                        <td>
                            <?php if ($r['status_bayar'] === 'lunas'): ?>
                                <span class="bs bs-lunas"><i class="fas fa-check me-1"></i>Lunas</span>
                            <?php elseif ($r['total_bayar']): ?>
                                <span class="bs bs-belum"><i class="fas fa-clock me-1"></i>Belum</span>
                            <?php else: ?>
                                <span class="text-muted small">�</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-semibold small text-nowrap">
                            <?= $r['total_bayar'] ? formatRupiah($r['total_bayar']) : '�' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 no-print"
             style="border-top:2px solid #f1f5f9">
            <span class="text-muted small">
                Periode: <strong><?= date('d M Y', strtotime($filter_tgl_dari)) ?></strong>
                s/d <strong><?= date('d M Y', strtotime($filter_tgl_sampai)) ?></strong>
                <?php if ($role === 'petugas'): ?>
                &nbsp;�&nbsp; Petugas: <strong><?= htmlspecialchars($nama_login) ?></strong>
                <?php endif; ?>
            </span>
            <span class="text-muted small">
                Total lunas: <strong class="text-success">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></strong>
            </span>
        </div>
    </div>

</div><!-- /main-content -->
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function cariTabel(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#mainTable tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
document.getElementById('searchInput')?.addEventListener('keydown', e => {
    if (e.key === 'Escape') { e.target.value = ''; cariTabel(''); }
});
</script>
</body>
</html>