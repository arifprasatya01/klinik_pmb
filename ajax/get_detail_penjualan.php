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

$penjualan_id = isset($_GET['id']) ? sanitize($_GET['id']) : 0;

// Get data penjualan
$query = "SELECT pl.*, u.nama_lengkap as nama_kasir
          FROM penjualan_langsung pl
          LEFT JOIN users u ON pl.kasir_id = u.id
          WHERE pl.id = '$penjualan_id'";
$result = mysqli_query($conn, $query);
$penjualan = mysqli_fetch_assoc($result);

if (!$penjualan) {
    echo '<div class="alert alert-danger">Data tidak ditemukan!</div>';
    exit;
}

// Get detail items
$query_detail = "SELECT dpl.*, o.kode_obat, o.nama_obat, o.satuan
                 FROM detail_penjualan_langsung dpl
                 JOIN obat o ON dpl.obat_id = o.id
                 WHERE dpl.penjualan_id = '$penjualan_id'
                 ORDER BY dpl.id ASC";
$result_detail = mysqli_query($conn, $query_detail);

$no_nota = 'NT-' . date('Ymd', strtotime($penjualan['tgl_penjualan'])) . '-' . str_pad($penjualan_id, 4, '0', STR_PAD_LEFT);
?>

<div class="row mb-3">
    <div class="col-md-6">
        <table class="table table-sm">
            <tr>
                <td width="40%"><strong>No. Nota</strong></td>
                <td><?php echo $no_nota; ?></td>
            </tr>
            <tr>
                <td><strong>Tanggal</strong></td>
                <td><?php echo date('d/m/Y H:i:s', strtotime($penjualan['tgl_penjualan'])); ?></td>
            </tr>
            <tr>
                <td><strong>Nama Pembeli</strong></td>
                <td><?php echo $penjualan['nama_pembeli']; ?></td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <table class="table table-sm">
            <tr>
                <td width="40%"><strong>Kasir</strong></td>
                <td><?php echo $penjualan['nama_kasir']; ?></td>
            </tr>
            <tr>
                <td><strong>Metode Bayar</strong></td>
                <td><span class="badge bg-info"><?php echo strtoupper($penjualan['metode_bayar']); ?></span></td>
            </tr>
            <tr>
                <td><strong>Keterangan</strong></td>
                <td><?php echo $penjualan['keterangan']; ?></td>
            </tr>
        </table>
    </div>
</div>

<h6 class="border-bottom pb-2 mb-3">Detail Pembelian</h6>

<div class="table-responsive">
    <table class="table table-bordered table-sm">
        <thead class="table-light">
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode</th>
                <th width="35%">Nama Obat</th>
                <th width="15%">Jumlah</th>
                <th width="15%">Harga</th>
                <th width="15%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while ($item = mysqli_fetch_assoc($result_detail)): 
            ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><?php echo $item['kode_obat']; ?></td>
                <td><?php echo $item['nama_obat']; ?></td>
                <td><?php echo $item['jumlah'] . ' ' . $item['satuan']; ?></td>
                <td class="text-end"><?php echo formatRupiah($item['harga']); ?></td>
                <td class="text-end"><strong><?php echo formatRupiah($item['subtotal']); ?></strong></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot class="table-light">
            <tr>
                <th colspan="5" class="text-end">Total Belanja:</th>
                <th class="text-end"><?php echo formatRupiah($penjualan['total_bayar']); ?></th>
            </tr>
            <tr>
                <th colspan="5" class="text-end">Jumlah Bayar:</th>
                <th class="text-end"><?php echo formatRupiah($penjualan['jumlah_bayar']); ?></th>
            </tr>
            <tr class="table-success">
                <th colspan="5" class="text-end">Kembalian:</th>
                <th class="text-end"><strong><?php echo formatRupiah($penjualan['kembalian']); ?></strong></th>
            </tr>
        </tfoot>
    </table>
</div>

<div class="text-center mt-3">
    <button class="btn btn-primary" onclick="printNota(<?php echo $penjualan_id; ?>)">
        <i class="fas fa-print me-2"></i>Print Nota
    </button>
    <button class="btn btn-secondary" data-bs-dismiss="modal">
        <i class="fas fa-times me-2"></i>Tutup
    </button>
</div>