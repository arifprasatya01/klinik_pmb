<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Sesi habis, silakan login ulang.']);
    exit;
}
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

if (isset($_POST['opname_id'])) {
    $opname_id = mysqli_real_escape_string($conn, $_POST['opname_id']);
    
    // Get stock opname info
    $query = "SELECT so.*, u.nama_lengkap
              FROM stock_opname so
              JOIN users u ON so.user_id = u.id
              WHERE so.id = '$opname_id'";
    $result = mysqli_query($conn, $query);
    $opname = mysqli_fetch_assoc($result);
    
    // Get detail
    $query_detail = "SELECT d.*, o.kode_obat, o.nama_obat, o.satuan
                     FROM detail_stock_opname d
                     JOIN obat o ON d.obat_id = o.id
                     WHERE d.stock_opname_id = '$opname_id'
                     ORDER BY o.nama_obat ASC";
    $result_detail = mysqli_query($conn, $query_detail);
    
    if ($opname):
?>
    <div class="mb-3">
        <table class="table table-bordered">
            <tr>
                <th width="200">ID Stock Opname</th>
                <td><strong>#<?php echo $opname['id']; ?></strong></td>
            </tr>
            <tr>
                <th>Tanggal Mulai</th>
                <td><?php echo date('d/m/Y H:i', strtotime($opname['tanggal_mulai'])); ?></td>
            </tr>
            <tr>
                <th>Tanggal Selesai</th>
                <td><?php echo date('d/m/Y H:i', strtotime($opname['tanggal_selesai'])); ?></td>
            </tr>
            <tr>
                <th>Petugas</th>
                <td><?php echo $opname['nama_lengkap']; ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td><span class="badge bg-success">Selesai</span></td>
            </tr>
            <?php if (!empty($opname['catatan'])): ?>
            <tr>
                <th>Catatan</th>
                <td><?php echo $opname['catatan']; ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <h6 class="mb-3"><i class="fas fa-list me-2"></i>Detail Obat</h6>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode Obat</th>
                    <th>Nama Obat</th>
                    <th>Satuan</th>
                    <th>Stok Sistem</th>
                    <th>Stok Fisik</th>
                    <th>Selisih</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                $total_selisih = 0;
                while ($row = mysqli_fetch_assoc($result_detail)): 
                    $total_selisih += abs($row['selisih']);
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><span class="badge bg-secondary"><?php echo $row['kode_obat']; ?></span></td>
                    <td><strong><?php echo $row['nama_obat']; ?></strong></td>
                    <td><?php echo $row['satuan']; ?></td>
                    <td><span class="badge bg-info"><?php echo $row['stok_sistem']; ?></span></td>
                    <td><span class="badge bg-primary"><?php echo $row['stok_fisik']; ?></span></td>
                    <td>
                        <span class="<?php echo $row['selisih'] >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                            <?php echo $row['selisih'] >= 0 ? '+' : ''; ?><?php echo $row['selisih']; ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr class="table-light">
                    <th colspan="6" class="text-end">Total Selisih (Absolut):</th>
                    <th><span class="badge bg-warning"><?php echo $total_selisih; ?></span></th>
                </tr>
            </tfoot>
        </table>
    </div>
<?php 
    else:
        echo '<div class="alert alert-danger">Data tidak ditemukan</div>';
    endif;
}
?>