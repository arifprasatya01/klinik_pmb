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

// ── Info Resep (untuk dapat pemeriksaan_id) ─────────────────────────────────
$query = "SELECT r.id, r.pemeriksaan_id
          FROM resep r
          WHERE r.id = '$resep_id'";
$result = mysqli_query($conn, $query);
$resep  = mysqli_fetch_assoc($result);

if (!$resep) {
    echo '<div class="alert alert-danger">Data resep tidak ditemukan</div>';
    exit();
}

$pemeriksaan_id = $resep['pemeriksaan_id'];

// ── Detail Obat ─────────────────────────────────────────────────────────────
$query_obat = "SELECT dr.id AS detail_id, dr.jumlah, dr.aturan_pakai,
                      o.nama_obat, o.satuan, o.harga_jual, o.stok
               FROM detail_resep dr
               JOIN obat o ON dr.obat_id = o.id
               WHERE dr.resep_id = '$resep_id'";
$result_obat = mysqli_query($conn, $query_obat);
$obat_list   = [];
$total_obat  = 0;
while ($row = mysqli_fetch_assoc($result_obat)) {
    $row['subtotal_obat'] = $row['jumlah'] * $row['harga_jual'];
    $total_obat          += $row['subtotal_obat'];
    $obat_list[]          = $row;
}

// ── Detail Tindakan ─────────────────────────────────────────────────────────
$query_tindakan = "SELECT dt.*, mt.nama_tindakan, mt.kode_tindakan
                   FROM detail_tindakan dt
                   JOIN master_tindakan mt ON dt.tindakan_id = mt.id
                   WHERE dt.pemeriksaan_id = '$pemeriksaan_id'";
$result_tindakan = mysqli_query($conn, $query_tindakan);
$tindakan_list   = [];
$total_tindakan  = 0;
while ($row = mysqli_fetch_assoc($result_tindakan)) {
    $total_tindakan += $row['subtotal'];
    $tindakan_list[] = $row;
}
?>

<form method="POST" action="apoteker.php" id="formEditJumlah">
    <input type="hidden" name="resep_id" value="<?= $resep_id ?>">

    <!-- ── Daftar Obat (Editable) ─────────────────────────────────────────── -->
    <h6 class="fw-bold mb-2 text-dark"><i class="fas fa-pills me-2 text-primary"></i>Daftar Obat <span class="badge bg-primary ms-1" style="font-size:11px;">Dapat Diedit</span></h6>

    <?php if (count($obat_list) > 0): ?>
    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle border rounded-3 overflow-hidden" id="tblObatEdit">
            <thead class="table-primary">
                <tr>
                    <th>#</th>
                    <th>Nama Obat</th>
                    <th>Aturan Pakai</th>
                    <th class="text-center">Jumlah</th>
                    <th class="text-end">Harga Satuan</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($obat_list as $i => $obat): ?>
                <tr data-harga="<?= $obat['harga_jual'] ?>">
                    <td><?= $i + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($obat['nama_obat']) ?></strong><br>
                        <small class="text-muted">Stok: <?= $obat['stok'] ?> <?= htmlspecialchars($obat['satuan']) ?></small>
                        <input type="hidden" name="detail_id[]" value="<?= $obat['detail_id'] ?>">
                    </td>
                    <td><small class="text-muted"><?= htmlspecialchars($obat['aturan_pakai']) ?></small></td>
                    <td class="text-center">
                        <input type="number"
                               name="jumlah_baru[]"
                               class="form-control form-control-sm jumlah-input text-center fw-bold"
                               value="<?= $obat['jumlah'] ?>"
                               min="1"
                               max="<?= $obat['stok'] ?>"
                               style="width:80px;margin:auto;"
                               onchange="hitungSubtotal(this)">
                    </td>
                    <td class="text-end text-muted small">Rp <?= number_format($obat['harga_jual'], 0, ',', '.') ?></td>
                    <td class="text-end fw-semibold subtotal-cell">Rp <?= number_format($obat['subtotal_obat'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td colspan="5" class="text-end fw-bold text-primary">Total Obat</td>
                    <td class="text-end fw-bold text-primary" id="totalObatEdit">Rp <?= number_format($total_obat, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php else: ?>
    <div class="alert alert-warning py-2 small mb-3"><i class="fas fa-info-circle me-2"></i>Tidak ada obat dalam resep ini.</div>
    <?php endif; ?>

    <!-- ── Daftar Tindakan (Readonly) ─────────────────────────────────────── -->
    <h6 class="fw-bold mb-2 text-dark"><i class="fas fa-stethoscope me-2 text-success"></i>Daftar Tindakan <span class="badge bg-secondary ms-1" style="font-size:11px;">Hanya Lihat</span></h6>

    <?php if (count($tindakan_list) > 0): ?>
    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle border rounded-3 overflow-hidden">
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
                    <td>
                        <strong><?= htmlspecialchars($t['nama_tindakan']) ?></strong>
                        <!-- readonly visual indicator -->
                        <i class="fas fa-lock fa-xs text-muted ms-1" title="Tidak dapat diedit"></i>
                    </td>
                    <td class="text-center">
                        <input type="text"
                               class="form-control form-control-sm text-center fw-bold"
                               value="<?= $t['jumlah'] ?>"
                               readonly
                               style="width:60px;margin:auto;background:#f8f9fa;border:1px dashed #ccc;cursor:not-allowed;">
                    </td>
                    <td class="text-end text-muted small">
                        <input type="text"
                               class="form-control form-control-sm text-end"
                               value="Rp <?= number_format($t['tarif'], 0, ',', '.') ?>"
                               readonly
                               style="background:#f8f9fa;border:1px dashed #ccc;cursor:not-allowed;min-width:110px;">
                    </td>
                    <td class="text-end fw-semibold">
                        <input type="text"
                               class="form-control form-control-sm text-end fw-semibold"
                               value="Rp <?= number_format($t['subtotal'], 0, ',', '.') ?>"
                               readonly
                               style="background:#f8f9fa;border:1px dashed #ccc;cursor:not-allowed;min-width:120px;">
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($t['keterangan'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td colspan="5" class="text-end fw-bold text-success">Total Tindakan</td>
                    <td class="text-end fw-bold text-success">
                        <input type="text"
                               class="form-control form-control-sm text-end fw-bold text-success"
                               value="Rp <?= number_format($total_tindakan, 0, ',', '.') ?>"
                               readonly
                               style="background:#f0fff4;border:1px dashed #28a745;cursor:not-allowed;min-width:120px;">
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php else: ?>
    <div class="alert alert-light border py-2 small mb-3"><i class="fas fa-info-circle me-2 text-muted"></i>Tidak ada tindakan tercatat.</div>
    <?php endif; ?>

    <!-- ── Grand Total ──────────────────────────────────────────────────────── -->
    <div class="card border-0 rounded-3 mb-3" style="background: linear-gradient(135deg,#667eea,#764ba2);">
        <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
            <span class="text-white fw-bold"><i class="fas fa-receipt me-2"></i>Grand Total</span>
            <span class="text-white fw-bold fs-5" id="grandTotalEdit">
                Rp <?= number_format($total_obat + $total_tindakan, 0, ',', '.') ?>
            </span>
        </div>
    </div>

    <!-- ── Tombol Simpan ────────────────────────────────────────────────────── -->
    <?php if (count($obat_list) > 0): ?>
    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>Batal
        </button>
        <button type="submit" name="edit_jumlah_obat" class="btn btn-warning fw-bold"
                onclick="return confirm('Simpan perubahan jumlah obat?')">
            <i class="fas fa-save me-1"></i>Simpan Perubahan
        </button>
    </div>
    <?php endif; ?>
</form>

<script>
// ── Hitung ulang subtotal & grand total saat jumlah diubah ──────────────────
var totalTindakan = <?= $total_tindakan ?>;

function hitungSubtotal(input) {
    var row     = input.closest('tr');
    var harga   = parseFloat(row.dataset.harga) || 0;
    var jumlah  = parseInt(input.value) || 0;
    var subtotal = harga * jumlah;

    row.querySelector('.subtotal-cell').textContent =
        'Rp ' + subtotal.toLocaleString('id-ID');

    hitungTotalObat();
}

function hitungTotalObat() {
    var totalObat = 0;
    document.querySelectorAll('#tblObatEdit tbody tr').forEach(function(row) {
        var jumlah = parseInt(row.querySelector('input[name="jumlah_baru[]"]').value) || 0;
        var harga  = parseFloat(row.dataset.harga) || 0;
        totalObat += jumlah * harga;
    });

    document.getElementById('totalObatEdit').textContent =
        'Rp ' + totalObat.toLocaleString('id-ID');

    var grand = totalObat + totalTindakan;
    document.getElementById('grandTotalEdit').textContent =
        'Rp ' + grand.toLocaleString('id-ID');
}
</script>