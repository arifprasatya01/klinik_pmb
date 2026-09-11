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

$pemeriksaan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$autoprint      = isset($_GET['autoprint']) && $_GET['autoprint'] == '1';

if (!$pemeriksaan_id) die("ID Pemeriksaan tidak valid.");

// ── Data Pemeriksaan ─────────────────────────────────────────────────────────
$query = "SELECT
    pm.id, pm.diagnosa, pm.catatan, pm.tgl_pemeriksaan, pm.status,
    ps.no_rm, ps.nik, ps.nama_lengkap, ps.tgl_lahir, ps.jenis_kelamin, ps.jenis_pasien,
    ps.alamat, ps.no_telepon,
    p.no_antrian, p.keluhan, p.tgl_daftar,
    u.nama_lengkap AS nama_dokter
FROM pemeriksaan pm
JOIN pendaftaran p  ON pm.pendaftaran_id = p.id
JOIN pasien ps      ON p.pasien_id = ps.id
JOIN users u        ON pm.dokter_id = u.id
WHERE pm.id = $pemeriksaan_id
LIMIT 1";
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) === 0) die("Data tidak ditemukan.");
$data = mysqli_fetch_assoc($result);

// ── Resep ────────────────────────────────────────────────────────────────────
$resep_list = [];
$res_resep = mysqli_query($conn,
    "SELECT dr.jumlah, dr.aturan_pakai, o.nama_obat
     FROM resep r
     JOIN detail_resep dr ON r.id = dr.resep_id
     JOIN obat o          ON dr.obat_id = o.id
     WHERE r.pemeriksaan_id = $pemeriksaan_id AND r.hapus = 0");
while ($row = mysqli_fetch_assoc($res_resep)) $resep_list[] = $row;

// ── Tindakan ─────────────────────────────────────────────────────────────────
$tindakan_list = [];
$res_tindakan = mysqli_query($conn,
    "SELECT mt.nama_tindakan, mt.kode_tindakan, dt.jumlah, dt.tarif, dt.keterangan
     FROM detail_tindakan dt
     JOIN master_tindakan mt ON dt.tindakan_id = mt.id
     WHERE dt.pemeriksaan_id = $pemeriksaan_id AND dt.hapus = 0");
while ($row = mysqli_fetch_assoc($res_tindakan)) $tindakan_list[] = $row;

// ── Helper ───────────────────────────────────────────────────────────────────
$umur       = (new DateTime($data['tgl_lahir']))->diff(new DateTime())->y;
$jaminan    = ['umum'=>'Umum','bpjs'=>'BPJS PBI','asuransi'=>'Asuransi'][$data['jenis_pasien']] ?? ucfirst($data['jenis_pasien']);
$tgl_masuk  = date('d-m-Y H:i:s', strtotime($data['tgl_pemeriksaan']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Resume Medis — <?= htmlspecialchars($data['nama_lengkap']) ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size:11px; color:#000; background:#fff; }

        @page { size: A4; margin: 10mm 12mm 10mm 12mm; }

        @media print {
            .no-print { display:none !important; }
            body { background:#fff; }
        }
        @media screen {
            body { background:#e5e7eb; padding:20px; }
            .page {
                width:210mm; min-height:297mm;
                margin:0 auto; background:#fff;
                padding:10mm 12mm;
                box-shadow:0 4px 24px rgba(0,0,0,0.18);
            }
        }

        /* Tombol */
        .no-print { position:fixed; top:16px; right:16px; display:flex; gap:8px; z-index:999; }
        .btn-print {
            background:#667eea; color:#fff; border:none;
            padding:10px 22px; border-radius:8px;
            font-size:13px; font-weight:700; cursor:pointer;
        }
        .btn-back {
            background:#6b7280; color:#fff; border:none;
            padding:10px 18px; border-radius:8px;
            font-size:13px; font-weight:600; cursor:pointer;
            text-decoration:none; display:inline-block;
        }

        /* Kop */
        .kop { display:flex; align-items:center; gap:14px; border-bottom:3px double #000; padding-bottom:8px; }
        .kop-logo img { width:72px; height:72px; object-fit:contain; flex-shrink:0; }
        .kop-logo-placeholder {
            width:72px; height:72px; border:2px solid #000;
            display:flex; align-items:center; justify-content:center;
            font-size:9px; color:#555; text-align:center; flex-shrink:0;
        }
        .kop-text { flex:1; text-align:center; }
        .kop-text .rs-nama { font-size:15px; font-weight:900; text-transform:uppercase; }
        .kop-text .rs-alamat { font-size:10px; margin-top:3px; line-height:1.5; }

        .doc-no { text-align:right; font-size:10px; font-weight:700; margin-bottom:3px; }

        .judul {
            text-align:center; font-weight:800; font-size:12px;
            border:1.5px solid #000; padding:5px;
            text-transform:uppercase; letter-spacing:1px;
        }

        /* Tabel Identitas */
        .tbl-id { width:100%; border-collapse:collapse; font-size:10.5px; }
        .tbl-id th, .tbl-id td { border:1px solid #000; padding:4px 6px; }
        .tbl-id th { background:#f3f4f6; font-weight:700; font-size:9.5px; text-align:center; white-space:nowrap; }
        .tbl-id td { font-weight:600; }

        /* Tabel Utama */
        .tbl { width:100%; border-collapse:collapse; font-size:10.5px; }
        .tbl td { border:1px solid #000; padding:5px 7px; vertical-align:top; }
        .lbl { font-style:italic; font-weight:700; font-size:10px; }

        /* Tabel Resep */
        .tbl-obat { width:100%; border-collapse:collapse; margin-top:4px; font-size:10px; }
        .tbl-obat th { background:#e5e7eb; border:1px solid #000; padding:3px 5px; font-weight:700; text-align:center; }
        .tbl-obat td { border:1px solid #000; padding:3px 5px; }

        /* Tabel Tindakan */
        .tbl-td { width:100%; border-collapse:collapse; margin-top:4px; font-size:10px; }
        .tbl-td th { background:#e5e7eb; border:1px solid #000; padding:3px 5px; font-weight:700; text-align:center; }
        .tbl-td td { border:1px solid #000; padding:3px 5px; }

        .ttd-area { height:60px; }
        .ttd-nama { font-weight:700; font-size:11px; border-top:1px solid #000; padding-top:3px; margin-top:4px; }
    </style>
</head>
<body>

<div class="no-print">
    <a href="javascript:window.close()" class="btn-back">✕ Tutup</a>
    <button class="btn-print" onclick="window.print()">🖨️ Cetak</button>
</div>

<div class="page">

    <div class="doc-no">RM.LAMP-126</div>

    <!-- KOP -->
    <div class="kop">
        <div class="kop-logo">
            <?php $logo = 'asset/img/logo.png'; ?>
            <?php if (file_exists($logo)): ?>
                <img src="<?= $logo ?>" alt="Logo">
            <?php else: ?>
                <div class="kop-logo-placeholder">LOGO<br>KLINIK</div>
            <?php endif; ?>
        </div>
        <div class="kop-text">
            <div class="rs-nama">KLINIK PMB IIS</div>
            <div class="rs-alamat">
                Dusun Tamelang RT 16 RW 7 Bengle Majalaya&nbsp;
            </div>
        </div>
    </div>

    <!-- JUDUL -->
    <div class="judul">Resume Medis Rawat Jalan / IGD *)</div>

    <!-- IDENTITAS -->
    <table class="tbl-id">
        <thead>
            <tr>
                <th style="width:80px;">No. RM</th>
                <th>Nama Pasien</th>
                <th style="width:95px;">Tgl. Lahir</th>
                <th style="width:95px;">Jenis Kelamin</th>
                <th style="width:75px;">Jaminan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($data['no_rm']) ?></td>
                <td><?= htmlspecialchars(strtoupper($data['nama_lengkap'])) ?></td>
                <td><?= date('d-m-Y', strtotime($data['tgl_lahir'])) ?></td>
                <td><?= $data['jenis_kelamin']=='L' ? 'L/P' : 'L/P' ?></td>
                <td><?= htmlspecialchars(strtoupper($jaminan)) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- BADAN UTAMA -->
    <table class="tbl">

        <!-- Baris 1: Poliklinik | Riwayat Masuk | Pemeriksaan Fisik -->
        <tr>
            <td style="width:30%;">
                <div class="lbl">POLIKLINIK / IGD*)</div><br>
                Rawat Darurat (IGD)<br><br>
                Tgl. Masuk : <em>(<?= $tgl_masuk ?>)</em>
            </td>
            <td style="width:40%;">
                <div class="lbl">Riwayat Masuk :</div>
                <div style="min-height:55px; padding-top:4px;">
                    <?= nl2br(htmlspecialchars($data['keluhan'])) ?>
                </div>
            </td>
            <td style="width:30%;">
                <div class="lbl">Pemeriksaan Fisik :</div>
                <div style="min-height:55px; padding-top:4px;">
                    <?= nl2br(htmlspecialchars($data['catatan'] ?? '')) ?>
                </div>
            </td>
        </tr>

        <!-- Baris 2: Hasil Penunjang | Diagnosa + Tindakan -->
        <tr>
            <td colspan="2">
                <div class="lbl">Hasil Penunjang :</div>
                <div style="min-height:55px;"></div>
            </td>
            <td>
                <div class="lbl"><strong>Diagnosa:</strong></div>
                <div style="margin-top:4px;">
                    <div class="lbl" style="font-size:9.5px;">Utama :</div>
                    <div style="min-height:20px; padding:2px 0 4px; border-bottom:1px solid #ccc;">
                        <?= htmlspecialchars($data['diagnosa'] ?? '') ?>
                    </div>
                    <div class="lbl" style="font-size:9.5px; margin-top:4px;">Tambahan :</div>
                    <div style="min-height:18px; padding:2px 0 4px; border-bottom:1px solid #ccc;"></div>
                </div>
                <div style="margin-top:6px;">
                    <div class="lbl"><strong>Tindakan / Prosedur :</strong></div>
                    <?php if (count($tindakan_list) > 0): ?>
                    <table class="tbl-td">
                        <thead><tr><th>#</th><th>Tindakan</th><th>Jml</th><th>Ket</th></tr></thead>
                        <tbody>
                            <?php foreach ($tindakan_list as $i => $t): ?>
                            <tr>
                                <td style="text-align:center;"><?= $i+1 ?></td>
                                <td><?= htmlspecialchars($t['nama_tindakan']) ?></td>
                                <td style="text-align:center;"><?= $t['jumlah'] ?></td>
                                <td><?= htmlspecialchars($t['keterangan'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="min-height:28px;"></div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>

        <!-- Baris 3: Obat | Pesan Penting -->
        <tr>
            <td colspan="2">
                <div class="lbl">Obat / Terapi yang diberikan di RS :</div>
                <?php if (count($resep_list) > 0): ?>
                <table class="tbl-obat">
                    <thead><tr><th width="5%">#</th><th width="55%">Nama Obat</th><th width="10%">Jml</th><th width="30%">Aturan Pakai</th></tr></thead>
                    <tbody>
                        <?php foreach ($resep_list as $i => $r): ?>
                        <tr>
                            <td style="text-align:center;"><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($r['nama_obat']) ?></td>
                            <td style="text-align:center;"><?= $r['jumlah'] ?></td>
                            <td><?= htmlspecialchars($r['aturan_pakai']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="min-height:40px; font-style:italic; color:#888; font-size:10px; padding-top:4px;">—</div>
                <?php endif; ?>
            </td>
            <td>
                <div class="lbl"><strong>Pesan Penting :</strong></div>
                <div style="min-height:28px; border-bottom:1px solid #ccc; margin-bottom:6px;"></div>
                <div class="lbl" style="font-size:9.5px;">Penyakit :</div>
                <div style="min-height:18px;"></div>
            </td>
        </tr>

        <!-- Baris 4: Keadaan Keluar -->
        <tr>
            <td colspan="2">
                <div class="lbl"><strong>Keadaan Pasien Saat Keluar :</strong></div>
                <div style="min-height:28px;"></div>
                <div class="lbl">Lain-lain :</div>
                <div style="min-height:22px;"></div>
            </td>
            <td>
                <div class="lbl">Gizi / Nutrisi</div>
                <div style="min-height:22px; border-bottom:1px solid #ccc; margin-bottom:6px;"></div>
                <div class="lbl">Keadaan Darurat :</div>
                <div style="min-height:22px;"></div>
            </td>
        </tr>

        <!-- Baris 5: TTD -->
        <tr>
            <td colspan="2" style="text-align:center; padding:10px;">
                <div class="lbl" style="text-align:left;"><strong>Tanda tangan Pasien / Keluarga :</strong></div>
                <div class="ttd-area"></div>
                <div class="ttd-nama"><?= htmlspecialchars(strtoupper($data['nama_lengkap'])) ?></div>
            </td>
            <td style="text-align:center; padding:10px;">
                <div class="lbl"><strong>Dokter Pemeriksa / DPJP :</strong></div>
                <div class="ttd-area"></div>
                <div class="ttd-nama"><?= htmlspecialchars($data['nama_dokter']) ?>, dr.</div>
            </td>
        </tr>

    </table>

    <div style="text-align:center; margin-top:6px; font-size:9px; color:#888;">1/1</div>

</div>

<script>
<?php if ($autoprint): ?>
window.addEventListener('load', function() {
    setTimeout(function() { window.print(); }, 600);
});
<?php endif; ?>
</script>
</body>
</html>