<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db   = new Database();
$conn = $db->getConnection();

$success = '';
$error   = '';

// ── Simpan nominal jasa ──────────────────────────────────────────────────────
if (isset($_POST['simpan_jasa'])) {
    $ids    = $_POST['tindakan_id'];
    $jasas  = $_POST['nominal_jasa'];
    $kets   = $_POST['keterangan'];

    $berhasil = true;
    foreach ($ids as $i => $tid) {
        $tid    = (int)$tid;
        $jasa   = str_replace(['.', ','], ['', '.'], $jasas[$i]);
        $jasa   = (float)$jasa;
        $ket    = sanitize($kets[$i]);

        // Upsert
        $q = "INSERT INTO jasa_tindakan (tindakan_id, nominal_jasa, keterangan)
              VALUES ('$tid', '$jasa', '$ket')
              ON DUPLICATE KEY UPDATE nominal_jasa = '$jasa', keterangan = '$ket'";
        if (!mysqli_query($conn, $q)) {
            $berhasil = false;
        }
    }

    if ($berhasil) {
        $success = "Nominal jasa berhasil disimpan!";
    } else {
        $error = "Gagal menyimpan nominal jasa: " . mysqli_error($conn);
    }
}

// ── Ambil semua tindakan + jasa ──────────────────────────────────────────────
$query = "SELECT mt.id, mt.kode_tindakan, mt.nama_tindakan, mt.tarif,
                 COALESCE(jt.nominal_jasa, 0) AS nominal_jasa,
                 COALESCE(jt.keterangan, '')  AS ket_jasa
          FROM master_tindakan mt
          LEFT JOIN jasa_tindakan jt ON mt.id = jt.tindakan_id
          WHERE mt.aktif = 1
          ORDER BY mt.kode_tindakan ASC";
$result   = mysqli_query($conn, $query);
$tindakan = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tindakan[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setting Jasa Tindakan - Healoka</title>
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php include 'sidebar_style.php'; ?>
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; }
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; margin: 0; }
        .col-md-10 { flex: 1; margin-left: 250px; padding: 0; }
        .card { border: none; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white; border-radius: 16px 16px 0 0 !important; font-weight: 600; padding: 14px 20px;
        }
        .table thead th { background: #f8f9fa; font-weight: 600; font-size: 13px; }
        .jasa-input { max-width: 160px; text-align: right; font-weight: 600; }
        .persen-info { font-size: 12px; color: #888; }
        .tarif-badge { background: #e3f2fd; color: #1565c0; border-radius: 8px; padding: 3px 10px; font-size: 13px; font-weight: 600; }
    </style>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row m-0">
        <?php include 'sidebar.php'; ?>
            <div class="container-fluid px-4 py-4">

                <!-- Header -->
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="fas fa-cog me-2 text-primary"></i>Setting Nominal Jasa Tindakan</h4>
                        <small class="text-muted">Atur nominal jasa yang diterima staff per tindakan</small>
                    </div>
                    <a href="finance.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Kembali ke Finance
                    </a>
                </div>

                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-list me-2"></i>Daftar Tindakan & Nominal Jasa</span>
                        <span class="badge bg-white text-dark"><?= count($tindakan) ?> tindakan</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="px-3">#</th>
                                        <th>Kode</th>
                                        <th>Nama Tindakan</th>
                                        <th class="text-end">Tarif Tindakan</th>
                                        <th class="text-center">% dari Tarif</th>
                                        <th class="text-end">Nominal Jasa (Rp)</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tindakan as $i => $t): ?>
                                    <tr>
                                        <td class="px-3 text-muted small"><?= $i + 1 ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($t['kode_tindakan']) ?></span></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($t['nama_tindakan']) ?></td>
                                        <td class="text-end">
                                            <span class="tarif-badge">Rp <?= number_format($t['tarif'], 0, ',', '.') ?></span>
                                            <input type="hidden" name="tindakan_id[]" value="<?= $t['id'] ?>">
                                        </td>
                                        <td class="text-center persen-info" id="persen_<?= $t['id'] ?>">
                                            <?php
                                            $pct = $t['tarif'] > 0 ? round(($t['nominal_jasa'] / $t['tarif']) * 100, 1) : 0;
                                            echo $pct . '%';
                                            ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="input-group input-group-sm justify-content-end">
                                                <span class="input-group-text">Rp</span>
                                                <input type="text"
                                                       name="nominal_jasa[]"
                                                       class="form-control jasa-input"
                                                       value="<?= number_format($t['nominal_jasa'], 0, ',', '.') ?>"
                                                       data-tarif="<?= $t['tarif'] ?>"
                                                       data-id="<?= $t['id'] ?>"
                                                       oninput="updatePersen(this)"
                                                       placeholder="0">
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text"
                                                   name="keterangan[]"
                                                   class="form-control form-control-sm"
                                                   value="<?= htmlspecialchars($t['ket_jasa']) ?>"
                                                   placeholder="Opsional...">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3 px-4">
                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Nominal jasa akan dihitung × jumlah tindakan yang dilakukan</small>
                        <button type="submit" name="simpan_jasa" class="btn btn-primary fw-bold px-4">
                            <i class="fas fa-save me-2"></i>Simpan Semua
                        </button>
                    </div>
                </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updatePersen(input) {
    var tarif  = parseFloat(input.dataset.tarif) || 0;
    var id     = input.dataset.id;
    var val    = input.value.replace(/\./g, '').replace(',', '.') || '0';
    var jasa   = parseFloat(val) || 0;
    var pct    = tarif > 0 ? ((jasa / tarif) * 100).toFixed(1) : '0.0';
    document.getElementById('persen_' + id).textContent = pct + '%';
}
</script>
</body>
</html>