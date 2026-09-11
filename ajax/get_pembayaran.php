<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Sesi habis, silakan login ulang.']);
    exit;
}
require_once '../config/database.php';
require_once '../includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$resep_id       = isset($_POST['resep_id']) ? (int)$_POST['resep_id'] : 0;
$pemeriksaan_id = isset($_POST['pemeriksaan_id']) ? (int)$_POST['pemeriksaan_id'] : 0;
$jenis_pasien   = $_POST['jenis_pasien'];
$no_bpjs        = isset($_POST['no_bpjs']) ? $_POST['no_bpjs'] : '';
$nama_asuransi  = isset($_POST['nama_asuransi']) ? $_POST['nama_asuransi'] : '';
$no_polis       = isset($_POST['no_polis']) ? $_POST['no_polis'] : '';

// Ambil pemeriksaan_id dari resep HANYA jika resep_id ada tapi pemeriksaan_id tidak dikirim
if ($resep_id > 0 && !$pemeriksaan_id) {
    $query_pm = "SELECT pemeriksaan_id FROM resep WHERE id = ?";
    $stmt_pm = mysqli_prepare($conn, $query_pm);
    mysqli_stmt_bind_param($stmt_pm, "i", $resep_id);
    mysqli_stmt_execute($stmt_pm);
    $result_pm = mysqli_stmt_get_result($stmt_pm);
    $row_pm = mysqli_fetch_assoc($result_pm);
    $pemeriksaan_id = $row_pm['pemeriksaan_id'];
    mysqli_stmt_close($stmt_pm);
}

// ===================== OBAT =====================
$query = "SELECT dr.*, o.nama_obat, o.satuan, o.harga_jual
          FROM detail_resep dr
          JOIN obat o ON dr.obat_id = o.id
          WHERE dr.resep_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $resep_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$items = [];
$total_obat_tanpa_markup = 0;
$total_obat_dengan_markup = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $harga_dasar = $row['harga_jual'];
    $harga_final = $harga_dasar;
    $has_markup = ($jenis_pasien == 'bpjs' || $jenis_pasien == 'asuransi');

    $subtotal = $harga_final * $row['jumlah'];
    $subtotal_dasar = $harga_dasar * $row['jumlah'];

    $total_obat_tanpa_markup += $subtotal_dasar;
    $total_obat_dengan_markup += $subtotal;

    $items[] = [
        'nama'        => $row['nama_obat'],
        'jumlah'      => $row['jumlah'],
        'satuan'      => $row['satuan'],
        'aturan_pakai'=> $row['aturan_pakai'],
        'harga'       => $harga_final,
        'harga_dasar' => $harga_dasar,
        'subtotal'    => $subtotal,
        'markup'      => $has_markup
    ];
}
mysqli_stmt_close($stmt);

$nilai_markup = $total_obat_dengan_markup - $total_obat_tanpa_markup;

// ===================== TINDAKAN =====================
$tindakan_items = [];
$total_tindakan = 0;

if ($pemeriksaan_id) {
    $query_tindakan = "SELECT dt.*, mt.nama_tindakan
                       FROM detail_tindakan dt
                       JOIN master_tindakan mt ON dt.tindakan_id = mt.id
                       WHERE dt.pemeriksaan_id = ? AND dt.hapus='0'";
    $stmt_t = mysqli_prepare($conn, $query_tindakan);
    mysqli_stmt_bind_param($stmt_t, "i", $pemeriksaan_id);
    mysqli_stmt_execute($stmt_t);
    $result_t = mysqli_stmt_get_result($stmt_t);

    while ($row_t = mysqli_fetch_assoc($result_t)) {
        $total_tindakan += $row_t['subtotal'];
        $tindakan_items[] = [
            'nama'       => $row_t['nama_tindakan'],
            'jumlah'     => $row_t['jumlah'],
            'tarif'      => $row_t['tarif'],
            'subtotal'   => $row_t['subtotal'],
            'keterangan' => $row_t['keterangan']
        ];
    }
    mysqli_stmt_close($stmt_t);
}

// ===================== GRAND TOTAL =====================
$total_bayar = $total_obat_dengan_markup + $total_tindakan;
?>

<input type="hidden" name="bayar" value="1">
<input type="hidden" name="resep_id" value="<?php echo $resep_id; ?>">
<input type="hidden" name="pemeriksaan_id" value="<?php echo $pemeriksaan_id; ?>">
<input type="hidden" name="jenis_pasien" value="<?php echo $jenis_pasien; ?>">
<input type="hidden" name="total_bayar" id="total_bayar" value="<?php echo $total_bayar; ?>">
<input type="hidden" name="diskon" id="diskon" value="0">
<input type="hidden" name="bulat" id="bulat" value="0">
<input type="hidden" name="total_setelah_diskon" id="total_setelah_diskon" value="<?php echo $total_bayar; ?>">
<input type="hidden" name="total_setelah_bulat" id="total_setelah_bulat" value="<?php echo $total_bayar; ?>">


<div class="row">
    <div class="col-md-12">

        <?php if ($jenis_pasien == 'bpjs' || $jenis_pasien == 'asuransi'): ?>
        <div class="alert alert-warning">
            <i class="fas fa-percentage me-2"></i>
            <strong>Catatan:</strong> Harga obat sudah termasuk markup 35% untuk <?php echo strtoupper($jenis_pasien); ?>.
        </div>
        <?php endif; ?>

        <!-- ===== TABEL OBAT ===== -->
        <div class="alert alert-info py-2">
            <i class="fas fa-pills me-2"></i><strong>Detail Obat / Resep</strong>
        </div>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Nama Obat</th>
                        <th width="15%">Aturan Pakai</th>
                        <th width="10%">Jumlah</th>
                        <th width="18%">Harga</th>
                        <th width="18%">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($items) > 0): ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong><?php echo $item['nama']; ?></strong>
                                <?php if ($item['markup']): ?>
                                    <br><small class="text-warning">
                                        <i class="fas fa-arrow-up"></i> Harga dasar: <?php echo formatRupiah($item['harga_dasar']); ?> + 35%
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo $item['aturan_pakai']; ?></small></td>
                            <td><?php echo $item['jumlah'] . ' ' . $item['satuan']; ?></td>
                            <td><?php echo formatRupiah($item['harga']); ?></td>
                            <td><strong><?php echo formatRupiah($item['subtotal']); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if ($jenis_pasien == 'bpjs' || $jenis_pasien == 'asuransi'): ?>
                        <tr class="table-light">
                            <td colspan="4" class="text-end"><strong>Subtotal Obat (Harga Dasar):</strong></td>
                            <td><strong><?php echo formatRupiah($total_obat_tanpa_markup); ?></strong></td>
                        </tr>
                        <tr class="table-light">
                            <td colspan="4" class="text-end"><strong>Markup 35%:</strong></td>
                            <td><strong class="text-warning"><?php echo formatRupiah($nilai_markup); ?></strong></td>
                        </tr>
                        <?php endif; ?>

                        <tr class="table-secondary">
                            <td colspan="4" class="text-end"><strong>Subtotal Obat:</strong></td>
                            <td><strong class="text-primary"><?php echo formatRupiah($total_obat_dengan_markup); ?></strong></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">Tidak ada resep obat</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ===== TABEL TINDAKAN ===== -->
        <div class="alert alert-success py-2">
            <i class="fas fa-hand-holding-medical me-2"></i><strong>Detail Tindakan Medis</strong>
        </div>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Nama Tindakan</th>
                        <th width="10%">Jumlah</th>
                        <th width="20%">Tarif</th>
                        <th width="20%">Subtotal</th>
                        <th width="20%">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tindakan_items) > 0): ?>
                        <?php foreach ($tindakan_items as $t): ?>
                        <tr>
                            <td><strong><?php echo $t['nama']; ?></strong></td>
                            <td><?php echo $t['jumlah']; ?></td>
                            <td><?php echo formatRupiah($t['tarif']); ?></td>
                            <td><strong><?php echo formatRupiah($t['subtotal']); ?></strong></td>
                            <td><small><?php echo $t['keterangan'] ?: '-'; ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-secondary">
                            <td colspan="3" class="text-end"><strong>Subtotal Tindakan:</strong></td>
                            <td colspan="2"><strong class="text-primary"><?php echo formatRupiah($total_tindakan); ?></strong></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">Tidak ada tindakan medis</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ===== GRAND TOTAL ===== -->
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered">
                <tbody>
                    <tr class="table-light">
                        <td class="text-end"><strong>Total Obat:</strong></td>
                        <td width="25%"><strong><?php echo formatRupiah($total_obat_dengan_markup); ?></strong></td>
                    </tr>
                    <tr class="table-light">
                        <td class="text-end"><strong>Total Tindakan:</strong></td>
                        <td><strong><?php echo formatRupiah($total_tindakan); ?></strong></td>
                    </tr>
                    <tr class="table-success">
                        <td class="text-end"><strong>GRAND TOTAL:</strong></td>
                        <td><strong class="text-success" style="font-size: 1.1em;"><?php echo formatRupiah($total_bayar); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ===== PEMBULATAN ===== -->
        <div class="discount-section">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="apply_pembulatan" onchange="togglePembulatan()">
                <label class="form-check-label fw-bold" for="apply_discount">
                    <i class="fas fa-tag me-1"></i> Input Jasa Bidan
                </label>
            </div>
            <div id="pembulatan_input" style="display: none;">
                <div class="row g-2">
                    <!--<div class="col-md-3">-->
                    <!--    <select class="form-select form-select-sm" id="discount_type" onchange="hitungTotal()">-->
                    <!--        <option value="persen">%</option>-->
                    <!--        <option value="nominal">Rp</option>-->
                    <!--    </select>-->
                    <!--</div>-->
                    <div class="col-md-3">
                        <input class="tagar tagar-sm" type="text" value="Rp" disabled>
                    </div>
                    <div class="col-md-9">
                        <input type="number" class="form-control form-control-sm" id="pembulatan_nominal" min="0" value="0" onkeyup="pembulatan()" placeholder="Nilai pembulatan">
                    </div>
                </div>
                <small class="text-muted" id="pembulatan_info"></small>
            </div>
        </div>
        
        <div class="discount-section">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="apply_discount" onchange="toggleDiscount('resep')">
                <label class="form-check-label fw-bold" for="apply_discount">
                    <i class="fas fa-tag me-1"></i> Berikan Diskon
                </label>
            </div>
            <div id="discount_input" style="display: none;">
                <div class="row g-2">
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" id="discount_type" onchange="hitungTotal()">
                            <option value="persen">%</option>
                            <option value="nominal">Rp</option>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <input type="number" class="form-control form-control-sm" id="discount_amount" min="0" value="0" onkeyup="hitungTotal()" placeholder="Nilai diskon">
                    </div>
                </div>
                <small class="text-muted" id="discount_info"></small>
            </div>
        </div>

        <div class="total-section mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">
                    <?php echo ($jenis_pasien == 'umum') ? 'Total Bayar:' : 'Total yang Ditanggung ' . strtoupper($jenis_pasien) . ':'; ?>
                </h5>
                <h4 class="mb-0 <?php echo ($jenis_pasien == 'umum') ? 'text-success' : 'text-primary'; ?>" id="total_display">
                    <?php echo formatRupiah($total_bayar); ?>
                </h4>
            </div>
            <div id="discount_display"></div>
            <div id="pembulatan_display"></div>
        </div>

        <?php if ($jenis_pasien == 'umum'): ?>
        <!-- ===== PEMBAYARAN UMUM ===== -->
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Metode Pembayaran <span class="text-danger">*</span></label>
                    <select class="form-select" name="metode_bayar" id="metode_bayar" required>
                        <option value="tunai">Tunai</option>
                        <option value="qris">QRIS</option>
                        <option value="transfer">Transfer</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">Jumlah Bayar <span class="text-danger">*</span></label>
                    <input type="number" class="form-control payment-input" name="jumlah_bayar" id="jumlah_bayar" required>
                </div>
            </div>
        </div>
        <div class="alert alert-light border">
            <div class="d-flex justify-content-between align-items-center">
                <strong>Kembalian:</strong>
                <h5 class="mb-0" id="kembalian_text">Rp 0</h5>
            </div>
            <input type="hidden" name="kembalian" id="kembalian" value="0">
        </div>
        <button type="submit" class="btn btn-success w-100 btn-lg">
            <i class="fas fa-check-circle me-2"></i>Proses Pembayaran
        </button>

        <?php else: ?>
        <!-- ===== VALIDASI BPJS/ASURANSI ===== -->
        <div class="mb-3">
            <label class="form-label fw-bold">
                No. Kartu <?php echo strtoupper($jenis_pasien); ?> <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" name="no_kartu"
                   value="<?php echo $jenis_pasien == 'bpjs' ? $no_bpjs : $no_polis; ?>" required>
            <?php if ($jenis_pasien == 'asuransi'): ?>
                <small class="text-muted">Asuransi: <?php echo $nama_asuransi; ?></small>
            <?php endif; ?>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Keterangan</label>
            <textarea class="form-control" name="keterangan" rows="2" placeholder="Catatan tambahan (opsional)"></textarea>
        </div>
        <button type="submit" class="btn btn-primary w-100 btn-lg">
            <i class="fas fa-check-circle me-2"></i>Validasi <?php echo strtoupper($jenis_pasien); ?>
        </button>
        <?php endif; ?>

    </div>
</div>

<script>
function hitungTotal() {
    let total = parseFloat($('#total_bayar').val()) || 0;
    let discountType = $('#discount_type').val();
    let discountValue = parseFloat($('#discount_amount').val()) || 0;
    let diskon = 0;

    if (discountType === 'persen') {
        if (discountValue > 100) { discountValue = 100; $('#discount_amount').val(discountValue); }
        diskon = total * (discountValue / 100);
        $('#discount_info').text(discountValue + '% = ' + formatRupiah(diskon));
    } else {
        if (discountValue > total) { discountValue = total; $('#discount_amount').val(discountValue); }
        diskon = discountValue;
        $('#discount_info').text('Diskon: ' + formatRupiah(diskon));
    }

    let totalSetelahDiskon = total - diskon;
    $('#diskon').val(diskon);
    $('#total_setelah_diskon').val(totalSetelahDiskon);

    // Pertimbangkan jasa bidan yang sudah ada
    let bulat = parseFloat($('#bulat').val()) || 0;
    let totalFinal = totalSetelahDiskon + bulat;
    $('#total_setelah_bulat').val(totalFinal);
    $('#total_display').text(formatRupiah(totalFinal));

    if (diskon > 0) {
        $('#discount_display').html('<small class="text-warning"><i class="fas fa-tag me-1"></i>Diskon: ' + formatRupiah(diskon) + '</small>');
    } else {
        $('#discount_display').html('');
    }

    hitungKembalian();
}

function pembulatan() {
    let total = parseFloat($('#total_bayar').val()) || 0;
    let pembulatanValue = parseFloat($('#pembulatan_nominal').val()) || 0;
    let bulat = pembulatanValue;
    $('#pembulatan_info').text('Jasa Bidan: ' + formatRupiah(bulat));

    // Pertimbangkan diskon yang sudah ada
    let diskon = parseFloat($('#diskon').val()) || 0;
    let totalSetelahDiskon = total - diskon;
    let totalFinal = totalSetelahDiskon + bulat;

    $('#bulat').val(bulat);
    $('#total_setelah_bulat').val(totalFinal);
    $('#total_display').text(formatRupiah(totalFinal));

    if (bulat > 0) {
        $('#pembulatan_display').html('<small class="text-warning"><i class="fas fa-tag me-1"></i>Jasa Bidan: ' + formatRupiah(bulat) + '</small>');
    } else {
        $('#pembulatan_display').html('');
    }

    hitungKembalian();
}

function formatRupiah(angka) {
    let number = Math.round(angka);
    return 'Rp ' + number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}
</script>