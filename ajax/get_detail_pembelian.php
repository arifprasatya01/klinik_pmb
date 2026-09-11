<?php
// PENTING: Tidak boleh ada spasi atau baris kosong sebelum tag <?php ini!

session_start();

// Set header
header('Content-Type: text/html; charset=utf-8');

// Cek login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger"><i class="fas fa-lock me-2"></i>Silakan login terlebih dahulu</div>';
    exit;
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

// Cek koneksi database
if (!$conn) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Koneksi database gagal!</div>';
    exit;
}

// Validasi ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">ID pembelian tidak diberikan.</div>';
    exit;
}

$pembelian_id = intval($_GET['id']);

if ($pembelian_id <= 0) {
    http_response_code(400);
    echo '<div class="alert alert-danger">ID pembelian tidak valid: ' . htmlspecialchars($_GET['id']) . '</div>';
    exit;
}

// Cek apakah data pembelian ada
$query_check = "SELECT COUNT(*) as total FROM pembelian WHERE id = $pembelian_id";
$result_check = mysqli_query($conn, $query_check);
$row_check = mysqli_fetch_assoc($result_check);

if ($row_check['total'] == 0) {
    http_response_code(404);
    echo '<div class="alert alert-warning">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>Data Tidak Ditemukan</h5>
            <p>Pembelian dengan ID <strong>' . $pembelian_id . '</strong> tidak ada di database.</p>
          </div>';
    exit;
}

// Get data pembelian dengan JOIN
$query = "SELECT p.*, s.nama_supplier, s.alamat as alamat_supplier, s.no_telepon as telp_supplier,
          u.nama_lengkap as nama_user
          FROM pembelian p
          JOIN supplier s ON p.supplier_id = s.id
          LEFT JOIN users u ON p.user_id = u.id
          WHERE p.id = $pembelian_id";

$result = mysqli_query($conn, $query);

if (!$result) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Query Error: ' . mysqli_error($conn) . '</div>';
    exit;
}

$pembelian = mysqli_fetch_assoc($result);

if (!$pembelian) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Gagal fetch data pembelian.</div>';
    exit;
}

// Get detail items
$query_detail = "SELECT dp.*, o.kode_obat, o.nama_obat, o.satuan
                 FROM detail_pembelian dp
                 JOIN obat o ON dp.obat_id = o.id
                 WHERE dp.pembelian_id = $pembelian_id
                 ORDER BY o.nama_obat";

$result_detail = mysqli_query($conn, $query_detail);

// Get riwayat pembayaran
$query_bayar = "SELECT ps.*, u.nama_lengkap
                FROM pembayaran_supplier ps
                LEFT JOIN users u ON ps.user_id = u.id
                WHERE ps.pembelian_id = $pembelian_id
                ORDER BY ps.tgl_bayar DESC";

$result_bayar = mysqli_query($conn, $query_bayar);
?>

<!-- HTML OUTPUT STARTS HERE -->

<!-- Data tersembunyi untuk keperluan JS -->
<input type="hidden" id="pembelian-id" value="<?php echo $pembelian_id; ?>">
<input type="hidden" id="total-tagihan" value="<?php echo $pembelian['total_dengan_ppn']; ?>">

<div class="row mb-3">
    <div class="col-md-6">
        <h5 class="mb-3"><i class="fas fa-file-invoice me-2"></i>Informasi Pembelian</h5>
        <table class="table table-sm table-bordered">
            <tr>
                <td width="40%"><strong>No. Pembelian</strong></td>
                <td><?php echo htmlspecialchars($pembelian['no_pembelian']); ?></td>
            </tr>
            <tr>
                <td><strong>Tanggal Pembelian</strong></td>
                <td><?php echo date('d/m/Y', strtotime($pembelian['tgl_pembelian'])); ?></td>
            </tr>
            <tr>
                <td><strong>No. Faktur Supplier</strong></td>
                <td><?php echo !empty($pembelian['no_faktur']) ? htmlspecialchars($pembelian['no_faktur']) : '-'; ?></td>
            </tr>
            <tr>
                <td><strong>Jatuh Tempo</strong></td>
                <td>
                    <?php echo date('d/m/Y', strtotime($pembelian['tgl_jatuh_tempo'])); ?>
                    <span class="badge bg-info"><?php echo $pembelian['jatuh_tempo_hari']; ?> hari</span>
                    <?php
                    $today = new DateTime();
                    $jatuh_tempo = new DateTime($pembelian['tgl_jatuh_tempo']);
                    $diff = $today->diff($jatuh_tempo);
                    $days = (int)$diff->format('%R%a');
                    
                    if ($pembelian['status_pembayaran'] != 'lunas') {
                        if ($days < 0) {
                            echo '<span class="badge bg-danger ms-2">Terlambat ' . abs($days) . ' hari</span>';
                        } elseif ($days == 0) {
                            echo '<span class="badge bg-warning text-dark ms-2">Jatuh tempo hari ini</span>';
                        } elseif ($days <= 7) {
                            echo '<span class="badge bg-warning text-dark ms-2">' . $days . ' hari lagi</span>';
                        } else {
                            echo '<span class="badge bg-success ms-2">' . $days . ' hari lagi</span>';
                        }
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td><strong>Dibuat Oleh</strong></td>
                <td><?php echo isset($pembelian['nama_user']) ? htmlspecialchars($pembelian['nama_user']) : 'N/A'; ?></td>
            </tr>
            <tr>
                <td><strong>Keterangan</strong></td>
                <td><?php echo !empty($pembelian['keterangan']) ? htmlspecialchars($pembelian['keterangan']) : '-'; ?></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h5 class="mb-3"><i class="fas fa-truck me-2"></i>Informasi Supplier</h5>
        <table class="table table-sm table-bordered">
            <tr>
                <td width="40%"><strong>Nama Supplier</strong></td>
                <td><?php echo htmlspecialchars($pembelian['nama_supplier']); ?></td>
            </tr>
            <tr>
                <td><strong>Alamat</strong></td>
                <td><?php echo htmlspecialchars($pembelian['alamat_supplier']); ?></td>
            </tr>
            <tr>
                <td><strong>Telepon</strong></td>
                <td><?php echo htmlspecialchars($pembelian['telp_supplier']); ?></td>
            </tr>
        </table>
        
        <h5 class="mb-3 mt-4"><i class="fas fa-money-bill-wave me-2"></i>Status Pembayaran</h5>
        <table class="table table-sm table-bordered">
            <tr>
                <td width="40%"><strong>Total Pembelian</strong></td>
                <td class="text-end"><strong><?php echo formatRupiah($pembelian['total_pembelian']); ?></strong></td>
            </tr>
            <tr>
                <td><strong>PPN (<?php echo $pembelian['ppn_persen']; ?>%)</strong></td>
                <td class="text-end"><?php echo formatRupiah($pembelian['nilai_ppn']); ?></td>
            </tr>
            <tr class="table-primary">
                <td><strong>Total + PPN</strong></td>
                <td class="text-end"><strong><?php echo formatRupiah($pembelian['total_dengan_ppn']); ?></strong></td>
            </tr>
            <tr class="table-success">
                <td><strong>Sudah Dibayar</strong></td>
                <td class="text-end"
                    id="cell-jumlah-dibayar"
                    data-pembelian-id="<?php echo $pembelian_id; ?>"
                    data-raw-value="<?php echo $pembelian['jumlah_dibayar']; ?>"
                    ondblclick="editJumlahDibayar(this)"
                    style="cursor: pointer;"
                    title="Double click untuk edit nominal">
                    <strong><span id="text-jumlah-dibayar"><?php echo formatRupiah($pembelian['jumlah_dibayar']); ?></span></strong>
                </td>
            </tr>
            <tr class="<?php echo $pembelian['sisa_pembayaran'] > 0 ? 'table-danger' : 'table-light'; ?>" id="row-sisa-hutang">
                <td><strong>Sisa Hutang</strong></td>
                <td class="text-end"><strong><span id="text-sisa-hutang"><?php echo formatRupiah($pembelian['sisa_pembayaran']); ?></span></strong></td>
            </tr>
            <tr>
                <td><strong>Status</strong></td>
                <td>
                    <?php
                    $badge_class = 'bg-secondary';
                    $status_text = 'UNKNOWN';
                    switch($pembelian['status_pembayaran']) {
                        case 'lunas':
                            $badge_class = 'bg-success';
                            $status_text = 'LUNAS';
                            break;
                        case 'dibayar_sebagian':
                            $badge_class = 'bg-warning text-dark';
                            $status_text = 'DIBAYAR SEBAGIAN';
                            break;
                        case 'belum_bayar':
                            $badge_class = 'bg-danger';
                            $status_text = 'BELUM BAYAR';
                            break;
                    }
                    ?>
                    <span id="badge-status-pembayaran"
                          class="badge <?php echo $badge_class; ?> fs-6"
                          style="cursor: pointer;"
                          onclick="finalizeStatusPembayaran(this)"
                          title="Klik untuk update status pembayaran berdasarkan nominal yang sudah dibayar">
                        <?php echo $status_text; ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>
</div>

<hr>

<h5 class="mb-3"><i class="fas fa-box me-2"></i>Detail Barang <?php echo $result_detail ? '(' . mysqli_num_rows($result_detail) . ' item)' : ''; ?></h5>
<div class="table-responsive">
    <table class="table table-sm table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode Obat</th>
                <th width="25%">Nama Obat</th>
                <th width="8%">Jumlah</th>
                <th width="10%">HNA</th>
                <th width="8%">Diskon</th>
                <!-- <th width="8%">PPN</th> -->
                <th width="10%">Harga Beli</th>
                <th width="11%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $grand_total = 0;
            if ($result_detail && mysqli_num_rows($result_detail) > 0) {
                while ($item = mysqli_fetch_assoc($result_detail)) {
                    $grand_total += $item['subtotal'];
            ?>
            <tr>
                <td class="text-center"><?php echo $no++; ?></td>
                <td><?php echo htmlspecialchars($item['kode_obat']); ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($item['nama_obat']); ?></strong>
                    <br>
                    <small class="text-muted">
                        <?php if (!empty($item['batch_number'])) { ?>
                            <span class="badge bg-secondary">Batch: <?php echo htmlspecialchars($item['batch_number']); ?></span>
                        <?php } ?>
                        <?php if (!empty($item['expired_date'])) { ?>
                            <span class="badge bg-warning text-dark">Exp: <?php echo date('d/m/Y', strtotime($item['expired_date'])); ?></span>
                        <?php } ?>
                    </small>
                </td>
                <td class="text-center"><?php echo $item['jumlah']; ?> <?php echo htmlspecialchars($item['satuan']); ?></td>
                <td class="text-end"><?php echo formatRupiah($item['harga_beli']); ?></td>
                <td class="text-center"><?php echo $item['diskon']; ?>%</td>
                <td class="text-end"><strong><?php echo formatRupiah($item['harga_beli']); ?></strong></td>
                <td class="text-end"><strong><?php echo formatRupiah($item['subtotal']); ?></strong></td>
            </tr>
            <?php 
                }
            } else {
            ?>
            <tr>
                <td colspan="9" class="text-center text-muted py-3">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p>Tidak ada detail barang untuk pembelian ini</p>
                </td>
            </tr>
            <?php } ?>
        </tbody>
        <?php if ($grand_total > 0) { ?>
        <tfoot class="table-secondary">
            <tr>
                <th colspan="7" class="text-end">TOTAL:</th>
                <th class="text-end"><?php echo formatRupiah($grand_total); ?></th>
            </tr>
        </tfoot>
        <?php } ?>
    </table>
</div>

<?php if ($result_bayar && mysqli_num_rows($result_bayar) > 0) { ?>
<hr>
<h5 class="mb-3"><i class="fas fa-history me-2"></i>Riwayat Pembayaran (<?php echo mysqli_num_rows($result_bayar); ?> pembayaran)</h5>
<div class="table-responsive">
    <table class="table table-sm table-bordered">
        <thead class="table-light">
            <tr>
                <th>Tanggal Bayar</th>
                <th>Jumlah Bayar</th>
                <th>Metode</th>
                <th>No. Referensi</th>
                <th>Keterangan</th>
                <th>User</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($bayar = mysqli_fetch_assoc($result_bayar)) { ?>
            <tr>
                <td><?php echo date('d/m/Y', strtotime($bayar['tgl_bayar'])); ?></td>
                <td class="text-end"><strong><?php echo formatRupiah($bayar['jumlah_bayar']); ?></strong></td>
                <td><span class="badge bg-info"><?php echo ucfirst($bayar['metode_bayar']); ?></span></td>
                <td><?php echo !empty($bayar['no_referensi']) ? htmlspecialchars($bayar['no_referensi']) : '-'; ?></td>
                <td><?php echo !empty($bayar['keterangan']) ? htmlspecialchars($bayar['keterangan']) : '-'; ?></td>
                <td><?php echo isset($bayar['nama_lengkap']) ? htmlspecialchars($bayar['nama_lengkap']) : 'N/A'; ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php } ?>

<div class="mt-4 text-end no-print">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        <i class="fas fa-times me-2"></i>Tutup
    </button>
    <button type="button" class="btn btn-primary" onclick="printDetailPembelian()">
        <i class="fas fa-print me-2"></i>Print
    </button>
</div>

<script>
// ==================== FORMAT RUPIAH DI JS ====================
function formatRupiahJS(angka) {
    angka = parseFloat(angka) || 0;
    return 'Rp ' + angka.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

// ==================== EDIT JUMLAH DIBAYAR (DOUBLE CLICK) ====================
function editJumlahDibayar(td) {
    // Cegah membuat input ganda kalau sudah dalam mode edit
    if (td.querySelector('input')) return;

    const currentRaw = td.getAttribute('data-raw-value');

    td.innerHTML = '<input type="number" id="input-jumlah-dibayar" ' +
        'class="form-control form-control-sm text-end" ' +
        'value="' + currentRaw + '" min="0" step="1">';

    const input = document.getElementById('input-jumlah-dibayar');
    input.focus();
    input.select();

    input.addEventListener('blur', function () {
        saveJumlahDibayar(td, input.value, currentRaw);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            input.blur(); // trigger blur -> saveJumlahDibayar
        } else if (e.key === 'Escape') {
            restoreCellJumlahDibayar(td, currentRaw);
        }
    });
}

function restoreCellJumlahDibayar(td, rawValue) {
    td.setAttribute('data-raw-value', rawValue);
    td.innerHTML = '<strong><span id="text-jumlah-dibayar">' + formatRupiahJS(rawValue) + '</span></strong>';
}

function saveJumlahDibayar(td, newValue, fallbackValue) {
    const pembelianId = td.getAttribute('data-pembelian-id');
    let jumlah = parseFloat(newValue);

    if (isNaN(jumlah) || jumlah < 0) {
        alert('Nominal tidak valid.');
        restoreCellJumlahDibayar(td, fallbackValue);
        return;
    }

    fetch('/ajax/update_pembayaran_gudang_obat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_jumlah&pembelian_id=' + encodeURIComponent(pembelianId) +
              '&jumlah_dibayar=' + encodeURIComponent(jumlah)
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.success) {
            restoreCellJumlahDibayar(td, data.jumlah_dibayar);

            // Update sisa hutang
            const textSisa = document.getElementById('text-sisa-hutang');
            const rowSisa = document.getElementById('row-sisa-hutang');
            if (textSisa) textSisa.textContent = formatRupiahJS(data.sisa_pembayaran);
            if (rowSisa) rowSisa.className = data.sisa_pembayaran > 0 ? 'table-danger' : 'table-light';
        } else {
            alert('Gagal menyimpan: ' + (data.message || 'Terjadi kesalahan'));
            restoreCellJumlahDibayar(td, fallbackValue);
        }
    })
    .catch(function (err) {
        alert('Terjadi kesalahan koneksi: ' + err);
        restoreCellJumlahDibayar(td, fallbackValue);
    });
}

// ==================== UPDATE STATUS (KLIK BADGE) ====================
function finalizeStatusPembayaran(badge) {
    const pembelianId = document.getElementById('pembelian-id').value;

    fetch('/ajax/update_pembayaran_gudang_obat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_status&pembelian_id=' + encodeURIComponent(pembelianId)
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.success) {
            const badgeClassMap = {
                'lunas': 'bg-success',
                'dibayar_sebagian': 'bg-warning text-dark',
                'belum_bayar': 'bg-danger'
            };
            const statusTextMap = {
                'lunas': 'LUNAS',
                'dibayar_sebagian': 'DIBAYAR SEBAGIAN',
                'belum_bayar': 'BELUM BAYAR'
            };

            badge.className = 'badge fs-6 ' + (badgeClassMap[data.status] || 'bg-secondary');
            badge.textContent = statusTextMap[data.status] || 'UNKNOWN';

            // Sisa hutang bisa juga ikut ter-update dari endpoint ini
            const textSisa = document.getElementById('text-sisa-hutang');
            const rowSisa = document.getElementById('row-sisa-hutang');
            if (textSisa && typeof data.sisa_pembayaran !== 'undefined') {
                textSisa.textContent = formatRupiahJS(data.sisa_pembayaran);
                if (rowSisa) rowSisa.className = data.sisa_pembayaran > 0 ? 'table-danger' : 'table-light';
            }
        } else {
            alert('Gagal update status: ' + (data.message || 'Terjadi kesalahan'));
        }
    })
    .catch(function (err) {
        alert('Terjadi kesalahan koneksi: ' + err);
    });
}
</script>