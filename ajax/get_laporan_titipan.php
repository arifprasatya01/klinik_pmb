<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'gudang' && $_SESSION['role'] != 'admin')) {
    die('Akses ditolak');
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$bulan = isset($_POST['bulan']) ? sanitize($_POST['bulan']) : date('m');
$tahun = isset($_POST['tahun']) ? sanitize($_POST['tahun']) : date('Y');

// Get data titipan sales untuk bulan yang dipilih
$query = "SELECT ts.*, s.nama_supplier 
          FROM titipan_sales ts
          JOIN supplier s ON ts.supplier_id = s.id
          WHERE MONTH(ts.tgl_faktur) = '$bulan' 
          AND YEAR(ts.tgl_faktur) = '$tahun'
          ORDER BY ts.tgl_faktur ASC, ts.no_faktur ASC";

$result = mysqli_query($conn, $query);

// Nama bulan
$nama_bulan = array(
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', 
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September', 
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
);
?>

<div id="printArea">
    <div class="text-center mb-4">
        <h4>LAPORAN TITIPAN SALES</h4>
        <h5>Periode: <?php echo $nama_bulan[$bulan] . ' ' . $tahun; ?></h5>
        <hr>
    </div>

    <?php if (mysqli_num_rows($result) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th width="5%" class="text-center">No</th>
                        <th width="12%">Tgl Faktur</th>
                        <th width="15%">No. Faktur</th>
                        <th width="20%">Supplier</th>
                        <th width="18%">Nama Sales</th>
                        <th width="15%" class="text-end">Nominal</th>
                        <th width="15%" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    $total_belum_bayar = 0;
                    $total_sudah_bayar = 0;
                    $total_keseluruhan = 0;
                    
                    while ($row = mysqli_fetch_assoc($result)): 
                        $total_keseluruhan += $row['nominal'];
                        if ($row['status_bayar'] == 'belum_bayar') {
                            $total_belum_bayar += $row['nominal'];
                        } else {
                            $total_sudah_bayar += $row['nominal'];
                        }
                    ?>
                    <tr>
                        <td class="text-center"><?php echo $no++; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($row['tgl_faktur'])); ?></td>
                        <td><strong><?php echo $row['no_faktur']; ?></strong></td>
                        <td><?php echo $row['nama_supplier']; ?></td>
                        <td><?php echo $row['nama_sales']; ?></td>
                        <td class="text-end"><?php echo formatRupiah($row['nominal']); ?></td>
                        <td class="text-center">
                            <?php if ($row['status_bayar'] == 'sudah_bayar'): ?>
                                <span class="badge bg-success">Sudah Bayar</span>
                                <?php if ($row['tgl_bayar']): ?>
                                    <br><small class="text-muted"><?php echo date('d/m/Y', strtotime($row['tgl_bayar'])); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-danger">Belum Bayar</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr class="table-secondary">
                        <td colspan="5" class="text-end"><strong>Total Sudah Dibayar:</strong></td>
                        <td class="text-end"><strong><?php echo formatRupiah($total_sudah_bayar); ?></strong></td>
                        <td></td>
                    </tr>
                    <tr class="table-warning">
                        <td colspan="5" class="text-end"><strong>Total Belum Dibayar:</strong></td>
                        <td class="text-end"><strong class="text-danger"><?php echo formatRupiah($total_belum_bayar); ?></strong></td>
                        <td></td>
                    </tr>
                    <tr class="table-info">
                        <td colspan="5" class="text-end"><strong>GRAND TOTAL:</strong></td>
                        <td class="text-end"><strong><?php echo formatRupiah($total_keseluruhan); ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <h6>Ringkasan Status Pembayaran:</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check-circle text-success me-2"></i>Sudah Dibayar: <?php echo formatRupiah($total_sudah_bayar); ?></li>
                    <li><i class="fas fa-times-circle text-danger me-2"></i>Belum Dibayar: <?php echo formatRupiah($total_belum_bayar); ?></li>
                </ul>
            </div>
            <div class="col-md-6 text-end">
                <p class="mb-1">Jakarta, <?php echo date('d F Y'); ?></p>
                <p class="mb-5">Petugas Gudang,</p>
                <p class="mb-0"><strong><u><?php echo $_SESSION['nama_lengkap']; ?></u></strong></p>
            </div>
        </div>

    <?php else: ?>
        <div class="alert alert-info text-center">
            <i class="fas fa-info-circle fa-2x mb-3"></i>
            <h5>Tidak ada data titipan sales untuk periode ini</h5>
            <p class="mb-0">Bulan: <?php echo $nama_bulan[$bulan] . ' ' . $tahun; ?></p>
        </div>
    <?php endif; ?>
</div>

<style>
@media print {
    .table {
        font-size: 12px;
    }
    .badge {
        border: 1px solid #000;
        padding: 2px 6px;
    }
}
</style>