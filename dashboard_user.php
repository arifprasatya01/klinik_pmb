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

$user_id = $_SESSION['user_id'];

// ── Info User ────────────────────────────────────────────────────────────────
$query_user = "SELECT id, nama_lengkap, role FROM users WHERE id = '$user_id'";
$user       = mysqli_fetch_assoc(mysqli_query($conn, $query_user));

// ── Helper: query statistik per periode ─────────────────────────────────────
function getStats($conn, $user_id, $where_tgl) {
    $q = "SELECT
              COUNT(DISTINCT dt.pemeriksaan_id)        AS total_pasien,
              COUNT(dt.id)                             AS total_tindakan,
              SUM(dt.jumlah)                           AS total_jumlah,
              SUM(dt.subtotal)                         AS total_pendapatan,
              COALESCE(SUM(jt.nominal_jasa * dt.jumlah), 0) AS total_jasa
          FROM detail_tindakan dt
          LEFT JOIN jasa_tindakan jt ON dt.tindakan_id = jt.tindakan_id
          WHERE dt.user_id = '$user_id' AND $where_tgl";
    $r = mysqli_fetch_assoc(mysqli_query($conn, $q));
    return $r;
}

$w_hari   = "DATE(dt.created_at) = CURDATE()";
$w_minggu = "YEARWEEK(dt.created_at, 1) = YEARWEEK(CURDATE(), 1)";
$w_bulan  = "DATE_FORMAT(dt.created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
$w_semua  = "1=1";
$w_bulan_lalu = "DATE_FORMAT(dt.created_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')";

$stat_hari      = getStats($conn, $user_id, $w_hari);
$stat_minggu    = getStats($conn, $user_id, $w_minggu);
$stat_bulan     = getStats($conn, $user_id, $w_bulan);
$stat_semua     = getStats($conn, $user_id, $w_semua);
$stat_bulan_lalu = getStats($conn, $user_id, $w_bulan_lalu);

// Perbandingan bulan ini vs bulan lalu
$selisih_pasien = $stat_bulan['total_pasien'] - $stat_bulan_lalu['total_pasien'];
$selisih_jasa   = $stat_bulan['total_jasa']   - $stat_bulan_lalu['total_jasa'];
$pct_pasien = $stat_bulan_lalu['total_pasien'] > 0
    ? round(($selisih_pasien / $stat_bulan_lalu['total_pasien']) * 100, 1) : 0;
$pct_jasa = $stat_bulan_lalu['total_jasa'] > 0
    ? round(($selisih_jasa / $stat_bulan_lalu['total_jasa']) * 100, 1) : 0;

// ── Grafik: tindakan per hari (30 hari terakhir) ─────────────────────────────
$query_grafik = "SELECT
                    DATE(dt.created_at)                      AS tgl,
                    COUNT(DISTINCT dt.pemeriksaan_id)        AS pasien,
                    COALESCE(SUM(jt.nominal_jasa * dt.jumlah), 0) AS jasa
                 FROM detail_tindakan dt
                 LEFT JOIN jasa_tindakan jt ON dt.tindakan_id = jt.tindakan_id
                 WHERE dt.user_id = '$user_id'
                   AND dt.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
                 GROUP BY DATE(dt.created_at)
                 ORDER BY tgl ASC";
$result_grafik = mysqli_query($conn, $query_grafik);
$grafik_labels = [];
$grafik_pasien = [];
$grafik_jasa   = [];

// Isi semua 30 hari (termasuk yang 0)
$date_map = [];
while ($g = mysqli_fetch_assoc($result_grafik)) {
    $date_map[$g['tgl']] = $g;
}
for ($i = 29; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i days"));
    $grafik_labels[] = date('d/m', strtotime($tgl));
    $grafik_pasien[] = isset($date_map[$tgl]) ? (int)$date_map[$tgl]['pasien'] : 0;
    $grafik_jasa[]   = isset($date_map[$tgl]) ? (float)$date_map[$tgl]['jasa']  : 0;
}

// ── Top 5 Tindakan Terbanyak (bulan ini) ────────────────────────────────────
$query_top = "SELECT
                  mt.nama_tindakan,
                  mt.kode_tindakan,
                  SUM(dt.jumlah)                            AS total_dilakukan,
                  COALESCE(SUM(jt.nominal_jasa * dt.jumlah), 0) AS total_jasa
              FROM detail_tindakan dt
              JOIN master_tindakan mt ON dt.tindakan_id = mt.id
              LEFT JOIN jasa_tindakan jt ON dt.tindakan_id = jt.tindakan_id
              WHERE dt.user_id = '$user_id'
                AND DATE_FORMAT(dt.created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
              GROUP BY dt.tindakan_id, mt.nama_tindakan, mt.kode_tindakan
              ORDER BY total_dilakukan DESC
              LIMIT 5";
$result_top = mysqli_query($conn, $query_top);
$top_list   = [];
$top_max    = 1;
while ($t = mysqli_fetch_assoc($result_top)) {
    $top_list[] = $t;
    if ($t['total_dilakukan'] > $top_max) $top_max = $t['total_dilakukan'];
}

// ── Daftar Tindakan Terbaru (10 terakhir) ────────────────────────────────────
$query_terbaru = "SELECT
                      dt.created_at,
                      mt.nama_tindakan, mt.kode_tindakan,
                      dt.jumlah, dt.tarif, dt.subtotal,
                      COALESCE(jt.nominal_jasa, 0)           AS nominal_jasa,
                      COALESCE(jt.nominal_jasa * dt.jumlah, 0) AS total_jasa_baris,
                      ps.nama_lengkap AS nama_pasien, ps.no_rm
                  FROM detail_tindakan dt
                  JOIN master_tindakan mt ON dt.tindakan_id = mt.id
                  LEFT JOIN jasa_tindakan jt ON dt.tindakan_id = jt.tindakan_id
                  JOIN pemeriksaan pm ON dt.pemeriksaan_id = pm.id
                  JOIN pendaftaran pd ON pm.pendaftaran_id = pd.id
                  JOIN pasien ps      ON pd.pasien_id = ps.id
                  WHERE dt.user_id = '$user_id'
                  ORDER BY dt.created_at DESC
                  LIMIT 10";
$result_terbaru = mysqli_query($conn, $query_terbaru);
$terbaru_list   = [];
while ($t = mysqli_fetch_assoc($result_terbaru)) $terbaru_list[] = $t;

// ── Role label ───────────────────────────────────────────────────────────────
$role_map = [
    'admin'       => ['danger',  'Admin'],
    'medis'       => ['primary', 'Tenaga Medis'],
    'apoteker'    => ['success', 'Apoteker'],
    'petugas'     => ['warning', 'Petugas'],
    'pendaftaran' => ['info',    'Pendaftaran'],
];
$role_info = $role_map[$user['role']] ?? ['secondary', ucfirst($user['role'])];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Saya - Medisoft</title>
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php include 'sidebar_style.php'; ?>
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; }
        body  { background: #f0f2f8; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .col-md-10 { flex: 1; margin-left: 250px; padding: 0; }

        /* ── Profile Banner ── */
        .profile-banner {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 20px;
            padding: 28px 32px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(102,126,234,0.35);
        }
        .profile-banner::before {
            content: '';
            position: absolute; top: -40px; right: -40px;
            width: 200px; height: 200px; border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }
        .profile-banner::after {
            content: '';
            position: absolute; bottom: -60px; right: 80px;
            width: 150px; height: 150px; border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }
        .avatar-circle {
            width: 72px; height: 72px; border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; font-weight: 800; color: white;
            border: 3px solid rgba(255,255,255,0.5);
            flex-shrink: 0;
        }

        /* ── Stat Cards ── */
        .stat-card {
            border: none; border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            transition: transform .2s, box-shadow .2s;
            background: white; overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .stat-card .card-body { padding: 20px 24px; }
        .stat-icon {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: white; flex-shrink: 0;
        }
        .stat-label { font-size: 12px; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
        .stat-value { font-size: 26px; font-weight: 800; color: #1f2937; line-height: 1.1; }
        .stat-sub   { font-size: 12px; color: #6b7280; margin-top: 4px; }

        /* ── Period Tabs ── */
        .period-tabs .nav-link {
            border-radius: 10px; font-size: 13px; font-weight: 500;
            color: #6b7280; padding: 7px 16px; border: none;
        }
        .period-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white; box-shadow: 0 4px 12px rgba(102,126,234,0.3);
        }

        /* ── Cards ── */
        .dash-card {
            border: none; border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            background: white;
        }
        .dash-card .dash-card-header {
            padding: 16px 20px; border-bottom: 1px solid #f3f4f6;
            font-weight: 700; font-size: 14px; color: #374151;
            display: flex; align-items: center; justify-content: between;
        }

        /* ── Comparison Banner ── */
        .compare-box {
            border-radius: 14px; padding: 16px 20px;
            display: flex; align-items: center; gap: 14px;
        }
        .compare-box.up   { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .compare-box.down { background: #fef2f2; border: 1px solid #fecaca; }
        .compare-box.flat { background: #f8f9fa; border: 1px solid #e5e7eb; }
        .compare-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .compare-box.up   .compare-icon { background: #dcfce7; color: #16a34a; }
        .compare-box.down .compare-icon { background: #fee2e2; color: #dc2626; }
        .compare-box.flat .compare-icon { background: #e5e7eb; color: #6b7280; }

        /* ── Top Tindakan ── */
        .top-bar-wrap { margin-bottom: 14px; }
        .top-bar-label { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px; display: flex; justify-content: space-between; }
        .top-bar { height: 10px; border-radius: 6px; background: #e5e7eb; overflow: hidden; }
        .top-bar-fill { height: 100%; border-radius: 6px; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)); transition: width .8s ease; }

        /* ── Terbaru Table ── */
        .terbaru-table th { font-size: 12px; font-weight: 600; color: #6b7280; background: #f9fafb; border-bottom: 2px solid #e5e7eb; }
        .terbaru-table td { font-size: 13px; vertical-align: middle; }
        .terbaru-table tr:hover td { background: #f0f4ff; }
        .jasa-pill { background: #ecfdf5; color: #065f46; font-weight: 700; padding: 3px 10px; border-radius: 8px; font-size: 12px; }
        .tindakan-code { background: #ede9fe; color: #5b21b6; font-size: 11px; padding: 2px 8px; border-radius: 6px; font-weight: 600; }

        /* ── Chart ── */
        .chart-wrap { position: relative; height: 240px; }

        /* ── Jasa Estimate highlight ── */
        .jasa-highlight {
            background: linear-gradient(135deg, #667eea15, #764ba215);
            border: 1px solid #667eea30; border-radius: 12px;
            padding: 14px 18px; text-align: center;
        }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row m-0">
        <?php include 'sidebar.php'; ?>

            <div class="container-fluid px-4 py-4">

                <!-- ── Profile Banner ──────────────────────────────────── -->
                <div class="profile-banner mb-4">
                    <div class="d-flex align-items-center gap-4">
                        <div class="avatar-circle">
                            <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                        </div>
                        <div style="z-index:1;">
                            <div class="opacity-75 small mb-1">Selamat datang,</div>
                            <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['nama_lengkap']) ?></h4>
                            <span class="badge bg-white text-dark fw-semibold">
                                <?= $role_info[1] ?>
                            </span>
                        </div>
                        <div class="ms-auto text-end" style="z-index:1;">
                            <div class="opacity-75 small mb-1">Estimasi Jasa Bulan Ini</div>
                            <div class="fw-bold fs-4">Rp <?= number_format($stat_bulan['total_jasa'], 0, ',', '.') ?></div>
                            <div class="opacity-75 small"><?= date('F Y') ?></div>
                        </div>
                    </div>
                </div>

                <!-- ── Period Tabs + Stat Cards ─────────────────────────── -->
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-muted mb-0">Statistik Kinerja</h6>
                    <ul class="nav period-tabs gap-1" id="periodTab">
                        <li class="nav-item"><a class="nav-link active" href="#" onclick="showPeriod('hari',this)">Hari Ini</a></li>
                        <li class="nav-item"><a class="nav-link" href="#" onclick="showPeriod('minggu',this)">Minggu Ini</a></li>
                        <li class="nav-item"><a class="nav-link" href="#" onclick="showPeriod('bulan',this)">Bulan Ini</a></li>
                        <li class="nav-item"><a class="nav-link" href="#" onclick="showPeriod('semua',this)">Semua</a></li>
                    </ul>
                </div>

                <?php
                $periods = [
                    'hari'   => $stat_hari,
                    'minggu' => $stat_minggu,
                    'bulan'  => $stat_bulan,
                    'semua'  => $stat_semua,
                ];
                foreach ($periods as $key => $s):
                ?>
                <div class="period-section row g-3 mb-4" id="section-<?= $key ?>" <?= $key !== 'hari' ? 'style="display:none"' : '' ?>>
                    <!-- Pasien -->
                    <div class="col-md-3">
                        <div class="stat-card card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon" style="background:linear-gradient(135deg,#667eea,#764ba2);">
                                    <i class="fas fa-user-injured"></i>
                                </div>
                                <div>
                                    <div class="stat-label">Pasien Ditangani</div>
                                    <div class="stat-value"><?= number_format($s['total_pasien']) ?></div>
                                    <div class="stat-sub">orang</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Tindakan -->
                    <div class="col-md-3">
                        <div class="stat-card card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon" style="background:linear-gradient(135deg,#11998e,#38ef7d);">
                                    <i class="fas fa-stethoscope"></i>
                                </div>
                                <div>
                                    <div class="stat-label">Total Tindakan</div>
                                    <div class="stat-value"><?= number_format($s['total_tindakan']) ?></div>
                                    <div class="stat-sub"><?= number_format($s['total_jumlah']) ?> item</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Jasa -->
                    <div class="col-md-3">
                        <div class="stat-card card">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="stat-icon" style="background:linear-gradient(135deg,#f7971e,#ffd200);">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </div>
                                <div>
                                    <div class="stat-label">Estimasi Jasa</div>
                                    <div class="stat-value text-warning" style="font-size:18px;">Rp <?= number_format($s['total_jasa'], 0, ',', '.') ?></div>
                                    <div class="stat-sub">perkiraan diterima</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- ── Perbandingan Bulan Lalu ─────────────────────────── -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <?php
                        $cls_p = $selisih_pasien > 0 ? 'up' : ($selisih_pasien < 0 ? 'down' : 'flat');
                        $ico_p = $selisih_pasien > 0 ? 'fa-arrow-trend-up' : ($selisih_pasien < 0 ? 'fa-arrow-trend-down' : 'fa-minus');
                        $txt_p = $selisih_pasien > 0 ? "Naik $pct_pasien% dari bulan lalu" : ($selisih_pasien < 0 ? "Turun ".abs($pct_pasien)."% dari bulan lalu" : "Sama dengan bulan lalu");
                        ?>
                        <div class="compare-box <?= $cls_p ?>">
                            <div class="compare-icon"><i class="fas <?= $ico_p ?>"></i></div>
                            <div>
                                <div class="fw-bold" style="font-size:13px;">Pasien Bulan Ini vs Bulan Lalu</div>
                                <div style="font-size:22px;font-weight:800;">
                                    <?= $stat_bulan['total_pasien'] ?>
                                    <span style="font-size:14px;font-weight:500;color:#6b7280;">vs <?= $stat_bulan_lalu['total_pasien'] ?> bulan lalu</span>
                                </div>
                                <div style="font-size:12px;color:#6b7280;"><?= $txt_p ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <?php
                        $cls_j = $selisih_jasa > 0 ? 'up' : ($selisih_jasa < 0 ? 'down' : 'flat');
                        $ico_j = $selisih_jasa > 0 ? 'fa-arrow-trend-up' : ($selisih_jasa < 0 ? 'fa-arrow-trend-down' : 'fa-minus');
                        $txt_j = $selisih_jasa > 0 ? "Naik $pct_jasa% dari bulan lalu" : ($selisih_jasa < 0 ? "Turun ".abs($pct_jasa)."% dari bulan lalu" : "Sama dengan bulan lalu");
                        ?>
                        <div class="compare-box <?= $cls_j ?>">
                            <div class="compare-icon"><i class="fas <?= $ico_j ?>"></i></div>
                            <div>
                                <div class="fw-bold" style="font-size:13px;">Estimasi Jasa Bulan Ini vs Bulan Lalu</div>
                                <div style="font-size:22px;font-weight:800;">
                                    Rp <?= number_format($stat_bulan['total_jasa'], 0, ',', '.') ?>
                                    <span style="font-size:13px;font-weight:500;color:#6b7280;">vs Rp <?= number_format($stat_bulan_lalu['total_jasa'], 0, ',', '.') ?></span>
                                </div>
                                <div style="font-size:12px;color:#6b7280;"><?= $txt_j ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Grafik + Top Tindakan ──────────────────────────── -->
                <div class="row g-3 mb-4">
                    <!-- Grafik -->
                    <div class="col-md-8">
                        <div class="dash-card p-3">
                            <div class="dash-card-header d-flex justify-content-between align-items-center mb-3">
                                <span><i class="fas fa-chart-area me-2 text-primary"></i>Tindakan & Jasa (30 Hari Terakhir)</span>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="chartTindakan"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Top Tindakan -->
                    <div class="col-md-4">
                        <div class="dash-card p-3 h-100">
                            <div class="dash-card-header d-flex justify-content-between align-items-center mb-3">
                                <span><i class="fas fa-trophy me-2 text-warning"></i>Top Tindakan Bulan Ini</span>
                            </div>
                            <?php if (count($top_list) > 0): ?>
                                <?php foreach ($top_list as $idx => $t): ?>
                                <div class="top-bar-wrap">
                                    <div class="top-bar-label">
                                        <span>
                                            <span class="me-1" style="color:#9ca3af;font-size:11px;">#<?= $idx+1 ?></span>
                                            <?= htmlspecialchars($t['nama_tindakan']) ?>
                                        </span>
                                        <span class="text-primary fw-bold"><?= $t['total_dilakukan'] ?>x</span>
                                    </div>
                                    <div class="top-bar">
                                        <div class="top-bar-fill" style="width:<?= round(($t['total_dilakukan'] / $top_max) * 100) ?>%"></div>
                                    </div>
                                    <div style="font-size:11px;color:#6b7280;margin-top:2px;">
                                        Jasa: <strong class="text-success">Rp <?= number_format($t['total_jasa'], 0, ',', '.') ?></strong>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block opacity-25"></i>
                                    <small>Belum ada tindakan bulan ini</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ── Tindakan Terbaru ────────────────────────────────── -->
                <div class="dash-card mb-4">
                    <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                        <span class="fw-bold" style="font-size:14px;"><i class="fas fa-clock me-2 text-primary"></i>Tindakan Terbaru</span>
                        <span class="badge bg-light text-dark border"><?= count($terbaru_list) ?> record</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table terbaru-table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="px-3">#</th>
                                    <th>Tanggal</th>
                                    <th>Pasien</th>
                                    <th>Tindakan</th>
                                    <th class="text-center">Jml</th>
                                    <th class="text-end">Tarif</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-end">Jasa/unit</th>
                                    <th class="text-end">Total Jasa</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($terbaru_list) > 0): ?>
                                    <?php foreach ($terbaru_list as $i => $t): ?>
                                    <tr>
                                        <td class="px-3 text-muted small"><?= $i+1 ?></td>
                                        <td class="small">
                                            <?= date('d/m/Y', strtotime($t['created_at'])) ?><br>
                                            <span class="text-muted" style="font-size:11px;"><?= date('H:i', strtotime($t['created_at'])) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold small"><?= htmlspecialchars($t['nama_pasien']) ?></div>
                                            <div class="text-muted" style="font-size:11px;"><?= $t['no_rm'] ?></div>
                                        </td>
                                        <td>
                                            <span class="tindakan-code me-1"><?= htmlspecialchars($t['kode_tindakan']) ?></span>
                                            <span class="small"><?= htmlspecialchars($t['nama_tindakan']) ?></span>
                                        </td>
                                        <td class="text-center fw-bold"><?= $t['jumlah'] ?></td>
                                        <td class="text-end small text-muted">Rp <?= number_format($t['tarif'], 0, ',', '.') ?></td>
                                        <td class="text-end small">Rp <?= number_format($t['subtotal'], 0, ',', '.') ?></td>
                                        <td class="text-end">
                                            <span class="jasa-pill">Rp <?= number_format($t['nominal_jasa'], 0, ',', '.') ?></span>
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            Rp <?= number_format($t['total_jasa_baris'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-5">
                                            <i class="fas fa-inbox fa-2x d-block mb-2 opacity-25"></i>
                                            Belum ada tindakan tercatat
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /container -->
        </div><!-- /col -->
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Period Switch ────────────────────────────────────────────────────────────
function showPeriod(key, el) {
    event.preventDefault();
    document.querySelectorAll('.period-section').forEach(s => s.style.display = 'none');
    document.getElementById('section-' + key).style.display = '';
    document.querySelectorAll('#periodTab .nav-link').forEach(a => a.classList.remove('active'));
    el.classList.add('active');
}

// ── Chart ────────────────────────────────────────────────────────────────────
const labels  = <?= json_encode($grafik_labels) ?>;
const pasien  = <?= json_encode($grafik_pasien) ?>;
const jasaArr = <?= json_encode($grafik_jasa)   ?>;

const ctx = document.getElementById('chartTindakan').getContext('2d');

const gradBlue = ctx.createLinearGradient(0, 0, 0, 240);
gradBlue.addColorStop(0, 'rgba(102,126,234,0.35)');
gradBlue.addColorStop(1, 'rgba(102,126,234,0.01)');

const gradGold = ctx.createLinearGradient(0, 0, 0, 240);
gradGold.addColorStop(0, 'rgba(247,151,30,0.35)');
gradGold.addColorStop(1, 'rgba(247,151,30,0.01)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Pasien',
                data: pasien,
                borderColor: '#667eea',
                backgroundColor: gradBlue,
                borderWidth: 2.5,
                pointRadius: 3,
                pointBackgroundColor: '#667eea',
                fill: true,
                tension: 0.4,
                yAxisID: 'yPasien',
            },
            {
                label: 'Estimasi Jasa (Rp)',
                data: jasaArr,
                borderColor: '#f7971e',
                backgroundColor: gradGold,
                borderWidth: 2.5,
                pointRadius: 3,
                pointBackgroundColor: '#f7971e',
                fill: true,
                tension: 0.4,
                yAxisID: 'yJasa',
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { font: { size: 12 }, usePointStyle: true } },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        if (ctx.dataset.yAxisID === 'yJasa') {
                            return ' Jasa: Rp ' + ctx.parsed.y.toLocaleString('id-ID');
                        }
                        return ' Pasien: ' + ctx.parsed.y + ' orang';
                    }
                }
            }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 10 } },
            yPasien: {
                type: 'linear', position: 'left',
                ticks: { stepSize: 1, font: { size: 11 } },
                grid: { color: '#f3f4f6' },
                title: { display: true, text: 'Pasien', font: { size: 11 } }
            },
            yJasa: {
                type: 'linear', position: 'right',
                grid: { display: false },
                ticks: {
                    font: { size: 11 },
                    callback: v => 'Rp ' + (v >= 1000000 ? (v/1000000).toFixed(1)+'jt' : v >= 1000 ? (v/1000).toFixed(0)+'rb' : v)
                },
                title: { display: true, text: 'Jasa', font: { size: 11 } }
            }
        }
    }
});
</script>
</body>
</html>