<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db   = new Database();
$conn = $db->getConnection();

// ── Filter ──────────────────────────────────────────────────────────────────
$f_rm     = isset($_GET['no_rm'])    ? trim($_GET['no_rm'])    : '';
$f_nik    = isset($_GET['nik'])      ? trim($_GET['nik'])      : '';
$f_nama   = isset($_GET['nama'])     ? trim($_GET['nama'])     : '';
$f_tgl_dari = isset($_GET['tgl_dari']) ? trim($_GET['tgl_dari']) : '';
$f_tgl_sampai = isset($_GET['tgl_sampai']) ? trim($_GET['tgl_sampai']) : '';

$has_filter = ($f_rm || $f_nik || $f_nama || $f_tgl_dari || $f_tgl_sampai);

// ── Query ────────────────────────────────────────────────────────────────────
$hasil = [];
$total_hasil = 0;

if ($has_filter) {
    $where = "WHERE pm.hapus IS NULL OR pm.hapus = 0 ";

    if ($f_rm) {
        $rm_esc = mysqli_real_escape_string($conn, $f_rm);
        $where .= "AND ps.no_rm LIKE '%$rm_esc%' ";
    }
    if ($f_nik) {
        $nik_esc = mysqli_real_escape_string($conn, $f_nik);
        $where .= "AND ps.nik LIKE '%$nik_esc%' ";
    }
    if ($f_nama) {
        $nama_esc = mysqli_real_escape_string($conn, $f_nama);
        $where .= "AND ps.nama_lengkap LIKE '%$nama_esc%' ";
    }
    if ($f_tgl_dari) {
        $tgl_dari_esc = mysqli_real_escape_string($conn, $f_tgl_dari);
        $where .= "AND DATE(pm.tgl_pemeriksaan) >= '$tgl_dari_esc' ";
    }
    if ($f_tgl_sampai) {
        $tgl_sampai_esc = mysqli_real_escape_string($conn, $f_tgl_sampai);
        $where .= "AND DATE(pm.tgl_pemeriksaan) <= '$tgl_sampai_esc' ";
    }

    $query = "SELECT
        pm.id           AS pemeriksaan_id,
        pm.tgl_pemeriksaan,
        pm.diagnosa,
        pm.status,
        ps.no_rm,
        ps.nik,
        ps.nama_lengkap,
        ps.tgl_lahir,
        ps.jenis_kelamin,
        ps.jenis_pasien,
        p.no_antrian,
        p.keluhan,
        u.nama_lengkap  AS nama_dokter,
        TIMESTAMPDIFF(YEAR, ps.tgl_lahir, CURDATE()) AS umur,
        (SELECT COUNT(*) FROM resep r
            JOIN detail_resep dr ON r.id = dr.resep_id
            WHERE r.pemeriksaan_id = pm.id AND r.hapus = 0) AS jml_resep,
        (SELECT COUNT(*) FROM detail_tindakan dt
            WHERE dt.pemeriksaan_id = pm.id AND dt.hapus = 0) AS jml_tindakan
    FROM pemeriksaan pm
    JOIN pendaftaran p  ON pm.pendaftaran_id = p.id
    JOIN pasien ps      ON p.pasien_id = ps.id
    JOIN users u        ON pm.dokter_id = u.id
    $where
    ORDER BY pm.tgl_pemeriksaan DESC
    LIMIT 100";

    $res = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($res)) {
        $hasil[] = $row;
    }
    $total_hasil = count($hasil);
}

// ── Jaminan label ────────────────────────────────────────────────────────────
function jaminanLabel($j) {
    return ['umum'=>'Umum','bpjs'=>'BPJS','asuransi'=>'Asuransi'][$j] ?? ucfirst($j);
}
function jaminanBadge($j) {
    return ['umum'=>'secondary','bpjs'=>'primary','asuransi'=>'info'][$j] ?? 'secondary';
}
function statusBadge($s) {
    return ['selesai'=>'success','proses'=>'warning','batal'=>'danger'][$s] ?? 'secondary';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Resume Medis - Healoka</title>
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php include 'sidebar_style.php'; ?>
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; }

        body { background: #f0f2f8; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .col-md-10 { flex: 1; margin-left: 250px; padding: 0; }

        /* ── Filter Card ── */
        .filter-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            padding: 24px 28px 20px;
            margin-bottom: 24px;
        }
        .filter-card .filter-title {
            font-size: 14px; font-weight: 700; color: #374151;
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        .filter-icon {
            width: 32px; height: 32px; border-radius: 8px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 13px;
        }
        .form-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .4px; }
        .form-control, .form-select {
            border-radius: 10px; border: 1.5px solid #e5e7eb;
            font-size: 13px; padding: 9px 12px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102,126,234,0.12);
        }
        .btn-cari {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: #fff; border: none; border-radius: 10px;
            padding: 9px 24px; font-weight: 600; font-size: 13px;
            transition: opacity .2s;
        }
        .btn-cari:hover { opacity: .88; color: #fff; }
        .btn-reset {
            background: #f3f4f6; color: #6b7280; border: 1.5px solid #e5e7eb;
            border-radius: 10px; padding: 9px 18px; font-size: 13px; font-weight: 600;
        }
        .btn-reset:hover { background: #e5e7eb; }

        /* ── Result Stats ── */
        .result-bar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 14px;
        }
        .result-count {
            font-size: 13px; font-weight: 700; color: #374151;
        }
        .result-count span { color: var(--primary-color); }

        /* ── Table ── */
        .result-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            overflow: hidden;
        }
        .result-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .result-table thead th {
            background: #f8f9fa; padding: 11px 14px;
            font-size: 11px; font-weight: 700; color: #6b7280;
            text-transform: uppercase; letter-spacing: .4px;
            border-bottom: 2px solid #e5e7eb; white-space: nowrap;
        }
        .result-table tbody td {
            padding: 12px 14px; border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        .result-table tbody tr:hover td { background: #f5f7ff; }
        .result-table tbody tr:last-child td { border-bottom: none; }

        .pasien-nama { font-weight: 700; color: #1f2937; font-size: 13px; }
        .pasien-meta { font-size: 11px; color: #9ca3af; margin-top: 2px; }
        .diagnosa-text {
            max-width: 220px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            color: #374151;
            font-size: 12px;
        }
        .dokter-text { font-size: 12px; color: #374151; }

        /* ── Action Buttons ── */
        .btn-cetak {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff; border: none; border-radius: 8px;
            padding: 7px 14px; font-size: 12px; font-weight: 600;
            white-space: nowrap; transition: opacity .2s;
        }
        .btn-cetak:hover { opacity: .85; color: #fff; }
        .btn-preview {
            background: #f0f4ff; color: var(--primary-color);
            border: 1.5px solid #c7d2fe; border-radius: 8px;
            padding: 6px 12px; font-size: 12px; font-weight: 600;
            white-space: nowrap; transition: all .2s;
        }
        .btn-preview:hover { background: var(--primary-color); color: #fff; }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 60px 20px; color: #9ca3af;
        }
        .empty-icon {
            font-size: 48px; margin-bottom: 14px; opacity: .3;
        }
        .empty-title { font-size: 15px; font-weight: 700; color: #6b7280; margin-bottom: 6px; }
        .empty-sub   { font-size: 13px; }

        /* ── Info chips ── */
        .chip {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600;
        }
        .chip-resep   { background: #ecfdf5; color: #065f46; }
        .chip-tindakan { background: #ede9fe; color: #5b21b6; }

        /* ── Page Header ── */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 16px; padding: 22px 28px;
            color: #fff; margin-bottom: 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .page-header h4 { font-weight: 800; margin: 0; }
        .page-header p  { margin: 4px 0 0; opacity: .8; font-size: 13px; }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row m-0">
        <?php include 'sidebar.php'; ?>

            <div class="container-fluid px-4 py-4">

                <!-- ── Page Header ── -->
                <div class="page-header">
                    <div>
                        <h4><i class="fas fa-print me-2"></i>Cetak Resume Medis</h4>
                        <p>Cari pasien dan cetak dokumen resume medis rawat jalan / IGD</p>
                    </div>
                    <div style="font-size:42px; opacity:.15;">
                        <i class="fas fa-file-medical-alt"></i>
                    </div>
                </div>

                <!-- ── Filter Card ── -->
                <div class="filter-card">
                    <div class="filter-title">
                        <div class="filter-icon"><i class="fas fa-search"></i></div>
                        Filter Pencarian
                    </div>
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">No. Rekam Medis</label>
                                <input type="text" class="form-control" name="no_rm"
                                    value="<?= htmlspecialchars($f_rm) ?>"
                                    placeholder="cth: 85-21-67">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">NIK</label>
                                <input type="text" class="form-control" name="nik"
                                    value="<?= htmlspecialchars($f_nik) ?>"
                                    placeholder="Nomor Induk Kependudukan">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Nama Pasien</label>
                                <input type="text" class="form-control" name="nama"
                                    value="<?= htmlspecialchars($f_nama) ?>"
                                    placeholder="Ketik sebagian nama...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tanggal Dari</label>
                                <input type="date" class="form-control" name="tgl_dari"
                                    value="<?= htmlspecialchars($f_tgl_dari) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tanggal Sampai</label>
                                <input type="date" class="form-control" name="tgl_sampai"
                                    value="<?= htmlspecialchars($f_tgl_sampai) ?>">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-cari">
                                <i class="fas fa-search me-1"></i>Cari Data
                            </button>
                            <a href="cetak_resume.php" class="btn btn-reset">
                                <i class="fas fa-redo me-1"></i>Reset
                            </a>
                            <?php if ($has_filter && $total_hasil > 0): ?>
                            <button type="button" class="btn btn-outline-success ms-auto"
                                onclick="cetakSemua()">
                                <i class="fas fa-print me-1"></i>Cetak Semua (<?= $total_hasil ?>)
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- ── Hasil ── -->
                <?php if (!$has_filter): ?>
                    <!-- Belum ada pencarian -->
                    <div class="result-card">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-search"></i></div>
                            <div class="empty-title">Gunakan filter untuk mencari pasien</div>
                            <div class="empty-sub">
                                Masukkan minimal satu kriteria pencarian di atas,<br>
                                lalu klik <strong>Cari Data</strong>
                            </div>
                        </div>
                    </div>

                <?php elseif ($total_hasil === 0): ?>
                    <!-- Tidak ditemukan -->
                    <div class="result-card">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-file-medical"></i></div>
                            <div class="empty-title">Data tidak ditemukan</div>
                            <div class="empty-sub">Coba ubah kata kunci atau rentang tanggal pencarian.</div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Tabel Hasil -->
                    <div class="result-bar">
                        <div class="result-count">
                            Ditemukan <span><?= $total_hasil ?></span> data pemeriksaan
                        </div>
                        <small class="text-muted">Maks. 100 hasil ditampilkan</small>
                    </div>

                    <div class="result-card">
                        <div class="table-responsive">
                            <table class="result-table">
                                <thead>
                                    <tr>
                                        <th width="3%">#</th>
                                        <th>Pasien</th>
                                        <th>No. RM / NIK</th>
                                        <th>Jaminan</th>
                                        <th>Tgl. Pemeriksaan</th>
                                        <th>Dokter</th>
                                        <th>Diagnosa</th>
                                        <th>Isi</th>
                                        <th>Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hasil as $i => $row): ?>
                                    <tr>
                                        <td class="text-muted"><?= $i + 1 ?></td>

                                        <!-- Pasien -->
                                        <td>
                                            <div class="pasien-nama">
                                                <?= htmlspecialchars($row['nama_lengkap']) ?>
                                            </div>
                                            <div class="pasien-meta">
                                                <?= $row['jenis_kelamin'] == 'L' ? '♂ Laki-laki' : '♀ Perempuan' ?>
                                                · <?= $row['umur'] ?> th
                                                · <?= date('d/m/Y', strtotime($row['tgl_lahir'])) ?>
                                            </div>
                                        </td>

                                        <!-- RM / NIK -->
                                        <td>
                                            <div style="font-weight:700;font-size:12px;color:#374151;">
                                                <?= htmlspecialchars($row['no_rm']) ?>
                                            </div>
                                            <div class="pasien-meta">
                                                <?= $row['nik'] ? htmlspecialchars($row['nik']) : '<em>NIK -</em>' ?>
                                            </div>
                                        </td>

                                        <!-- Jaminan -->
                                        <td>
                                            <span class="badge bg-<?= jaminanBadge($row['jenis_pasien']) ?>">
                                                <?= jaminanLabel($row['jenis_pasien']) ?>
                                            </span>
                                        </td>

                                        <!-- Tanggal -->
                                        <td>
                                            <div style="font-weight:600;font-size:12px;">
                                                <?= date('d/m/Y', strtotime($row['tgl_pemeriksaan'])) ?>
                                            </div>
                                            <div class="pasien-meta">
                                                <?= date('H:i', strtotime($row['tgl_pemeriksaan'])) ?> WIB
                                            </div>
                                        </td>

                                        <!-- Dokter -->
                                        <td>
                                            <div class="dokter-text">
                                                <i class="fas fa-user-md me-1 text-primary" style="font-size:10px;"></i>
                                                <?= htmlspecialchars($row['nama_dokter']) ?>
                                            </div>
                                        </td>

                                        <!-- Diagnosa -->
                                        <td>
                                            <div class="diagnosa-text">
                                                <?= $row['diagnosa']
                                                    ? htmlspecialchars($row['diagnosa'])
                                                    : '<em class="text-muted">Belum diisi</em>' ?>
                                            </div>
                                        </td>

                                        <!-- Isi (chip resep & tindakan) -->
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <span class="chip chip-resep">
                                                    <i class="fas fa-pills"></i>
                                                    <?= $row['jml_resep'] ?> Obat
                                                </span>
                                                <span class="chip chip-tindakan">
                                                    <i class="fas fa-stethoscope"></i>
                                                    <?= $row['jml_tindakan'] ?> Tindakan
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Status -->
                                        <td>
                                            <span class="badge bg-<?= statusBadge($row['status']) ?>">
                                                <?= ucfirst($row['status']) ?>
                                            </span>
                                        </td>

                                        <!-- Aksi -->
                                        <td class="text-center">
                                            <div class="d-flex gap-1 justify-content-center">
                                                <a href="cetak_resume_medis.php?id=<?= $row['pemeriksaan_id'] ?>"
                                                   target="_blank"
                                                   class="btn btn-preview"
                                                   title="Preview">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="cetak_resume_medis.php?id=<?= $row['pemeriksaan_id'] ?>&autoprint=1"
                                                   target="_blank"
                                                   class="btn btn-cetak"
                                                   title="Cetak Langsung">
                                                    <i class="fas fa-print me-1"></i>Cetak
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Footer Tabel -->
                        <div style="padding:12px 16px;border-top:1px solid #f3f4f6;
                                    display:flex;align-items:center;justify-content:space-between;
                                    background:#fafafa;">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Klik <strong>👁 Preview</strong> untuk melihat sebelum cetak,
                                atau <strong>🖨 Cetak</strong> untuk langsung cetak.
                            </small>
                            <small class="text-muted">Total: <strong><?= $total_hasil ?></strong> record</small>
                        </div>
                    </div>

                <?php endif; ?>

            </div><!-- /container -->
        </div><!-- /col-md-10 -->
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Auto-focus filter pertama ─────────────────────────────────────────────
document.querySelector('input[name="no_rm"]')?.focus();

// ── Enter di field filter langsung submit ─────────────────────────────────
document.querySelectorAll('.filter-card input').forEach(function(el) {
    el.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            el.closest('form').submit();
        }
    });
});

// ── Cetak Semua ───────────────────────────────────────────────────────────
function cetakSemua() {
    const ids = <?= json_encode(array_column($hasil, 'pemeriksaan_id')) ?>;
    if (ids.length === 0) return;

    if (!confirm('Akan membuka ' + ids.length + ' tab cetak sekaligus.\nLanjutkan?')) return;

    ids.forEach(function(id, i) {
        setTimeout(function() {
            window.open('cetak_resume_medis.php?id=' + id + '&autoprint=1', '_blank');
        }, i * 600); // Delay 600ms antar tab agar tidak kena blokir browser
    });
}
</script>
</body>
</html>