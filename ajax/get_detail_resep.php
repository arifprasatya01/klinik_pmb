<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Akses ditolak</div>';
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$db   = new Database();
$conn = $db->getConnection();

$resep_id = sanitize($_POST['resep_id']);

// ── Info Resep ──────────────────────────────────────────────────────────────
$query = "SELECT r.*, p.no_antrian, pm.diagnosa, pm.id AS pemeriksaan_id,
                 ps.nama_lengkap, ps.no_rm, ps.alamat,
                 TIMESTAMPDIFF(YEAR, ps.tgl_lahir, CURDATE()) AS umur,
                 d.nama_lengkap AS nama_dokter
          FROM resep r
          JOIN pemeriksaan pm ON r.pemeriksaan_id = pm.id
          JOIN pendaftaran p  ON pm.pendaftaran_id = p.id
          JOIN pasien ps      ON p.pasien_id = ps.id
          LEFT JOIN users d  ON pm.dokter_id = d.id
          WHERE r.id = '$resep_id'";
$result = mysqli_query($conn, $query);
$resep  = mysqli_fetch_assoc($result);

if (!$resep) {
    echo '<div class="alert alert-danger">Data resep tidak ditemukan</div>';
    exit();
}

$pemeriksaan_id = $resep['pemeriksaan_id'];

// ── Detail Obat ─────────────────────────────────────────────────────────────
$query_obat = "SELECT dr.*, o.nama_obat, o.satuan, o.harga_jual
               FROM detail_resep dr
               JOIN obat o ON dr.obat_id = o.id
               WHERE dr.resep_id = '$resep_id'";
$result_obat = mysqli_query($conn, $query_obat);
$obat_list   = [];
$total_obat  = 0;
while ($row = mysqli_fetch_assoc($result_obat)) {
    $subtotal_obat  = $row['jumlah'] * $row['harga_jual'];
    $row['subtotal_obat'] = $subtotal_obat;
    $total_obat    += $subtotal_obat;
    $obat_list[]    = $row;
}

// ── Detail Tindakan ─────────────────────────────────────────────────────────
$query_tindakan = "SELECT dt.*, mt.nama_tindakan, mt.kode_tindakan
                   FROM detail_tindakan dt
                   JOIN master_tindakan mt ON dt.tindakan_id = mt.id
                   WHERE dt.pemeriksaan_id = '$pemeriksaan_id' AND dt.hapus='0'";
$result_tindakan = mysqli_query($conn, $query_tindakan);
$tindakan_list   = [];
$total_tindakan  = 0;
while ($row = mysqli_fetch_assoc($result_tindakan)) {
    $total_tindakan += $row['subtotal'];
    $tindakan_list[] = $row;
}

$grand_total = $total_obat + $total_tindakan;

// ── Status Badge ────────────────────────────────────────────────────────────
$status_map = [
    'menunggu' => ['warning', 'Menunggu'],
    'diproses' => ['primary', 'Diproses'],
    'selesai'  => ['success', 'Selesai'],
];
$st    = $status_map[$resep['status']] ?? ['secondary', ucfirst($resep['status'])];
$badge = "<span class='badge bg-{$st[0]}'>{$st[1]}</span>";
?>

<!-- Info Pasien -->
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card border-0 bg-light rounded-3 p-3">
            <h6 class="fw-bold text-primary mb-2"><i class="fas fa-user me-2"></i>Informasi Pasien</h6>
            <table class="table table-sm table-borderless mb-0 small">
                <tr><td class="text-muted" width="40%">Nama</td><td><strong><?= htmlspecialchars($resep['nama_lengkap']) ?></strong></td></tr>
                <tr><td class="text-muted">No. RM</td><td><?= htmlspecialchars($resep['no_rm']) ?></td></tr>
                <tr><td class="text-muted">Umur</td><td><?= $resep['umur'] ?> tahun</td></tr>
                <tr><td class="text-muted">Alamat</td><td><?= htmlspecialchars($resep['alamat']) ?></td></tr>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 bg-light rounded-3 p-3">
            <h6 class="fw-bold text-primary mb-2"><i class="fas fa-file-medical me-2"></i>Informasi Resep</h6>
            <table class="table table-sm table-borderless mb-0 small">
                <tr><td class="text-muted" width="40%">No. Antrian</td><td><?= htmlspecialchars($resep['no_antrian']) ?></td></tr>
                <tr><td class="text-muted">Tanggal</td><td><?= date('d/m/Y H:i', strtotime($resep['tgl_resep'])) ?></td></tr>
                <tr><td class="text-muted">Diagnosa</td><td><?= htmlspecialchars($resep['diagnosa']) ?></td></tr>
                <tr><td class="text-muted">Status</td><td><?= $badge ?></td></tr>
            </table>
        </div>
    </div>
</div>

<!-- ── Tabel Obat ─────────────────────────────────────────────────────────── -->
<h6 class="fw-bold text-dark mb-2"><i class="fas fa-pills me-2 text-primary"></i>Daftar Obat</h6>
<?php if (count($obat_list) > 0): ?>
<div class="table-responsive mb-3">
    <table class="table table-sm table-hover align-middle border rounded-3 overflow-hidden">
        <thead class="table-primary">
            <tr>
                <th>#</th>
                <th>Nama Obat</th>
                <th class="text-center">Jumlah</th>
                <th class="text-center">Satuan</th>
                <th>Aturan Pakai</th>
                <th class="text-end">Harga Satuan</th>
                <th class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($obat_list as $i => $obat): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($obat['nama_obat']) ?></strong></td>
                <td class="text-center"><?= $obat['jumlah'] ?></td>
                <td class="text-center"><?= htmlspecialchars($obat['satuan']) ?></td>
                <td><?= htmlspecialchars($obat['aturan_pakai']) ?></td>
                <td class="text-end">Rp <?= number_format($obat['harga_jual'], 0, ',', '.') ?></td>
                <td class="text-end fw-semibold">Rp <?= number_format($obat['subtotal_obat'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="6" class="text-end fw-bold text-primary">Total Obat</td>
                <td class="text-end fw-bold text-primary">Rp <?= number_format($total_obat, 0, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php else: ?>
<div class="alert alert-warning py-2 small"><i class="fas fa-info-circle me-2"></i>Tidak ada obat dalam resep ini.</div>
<?php endif; ?>

<!-- ── Tabel Tindakan (readonly) ──────────────────────────────────────────── -->
<h6 class="fw-bold text-dark mb-2"><i class="fas fa-stethoscope me-2 text-success"></i>Daftar Tindakan</h6>
<?php if (count($tindakan_list) > 0): ?>
<div class="table-responsive mb-3">
    <table class="table table-sm table-hover align-middle border rounded-3 overflow-hidden">
        <thead class="table-success">
            <tr>
                <th>#</th>
                <th>Kode</th>
                <th>Nama Tindakan</th>
                <th class="text-center">Jumlah</th>
                <th class="text-end">Tarif</th>
                <th class="text-end">Subtotal</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tindakan_list as $i => $t): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($t['kode_tindakan']) ?></span></td>
                <td><strong><?= htmlspecialchars($t['nama_tindakan']) ?></strong></td>
                <td class="text-center"><?= $t['jumlah'] ?></td>
                <td class="text-end">Rp <?= number_format($t['tarif'], 0, ',', '.') ?></td>
                <td class="text-end fw-semibold">Rp <?= number_format($t['subtotal'], 0, ',', '.') ?></td>
                <td class="text-muted small"><?= htmlspecialchars($t['keterangan'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="5" class="text-end fw-bold text-success">Total Tindakan</td>
                <td class="text-end fw-bold text-success">Rp <?= number_format($total_tindakan, 0, ',', '.') ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
<?php else: ?>
<div class="alert alert-light border py-2 small"><i class="fas fa-info-circle me-2 text-muted"></i>Tidak ada tindakan tercatat.</div>
<?php endif; ?>

<!-- ── Grand Total ────────────────────────────────────────────────────────── -->
<div class="card border-0 rounded-3 mt-1" style="background: linear-gradient(135deg,#667eea,#764ba2);">
    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
        <span class="text-white fw-bold fs-6"><i class="fas fa-receipt me-2"></i>Grand Total</span>
        <span class="text-white fw-bold fs-5">Rp <?= number_format($grand_total, 0, ',', '.') ?></span>
    </div>
</div>