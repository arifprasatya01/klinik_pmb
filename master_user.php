<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db   = new Database();
$conn = $db->getConnection();

$success = '';
$error   = '';

// ============================================================
// FUNGSI HASH BERLAPIS (sama seperti di index.php)
// ============================================================
function hashPasswordBerlapis($password) {
    $step1 = md5($password);
    $step2 = hash('sha256', $step1 . $password);
    $step3 = password_hash($step2, PASSWORD_BCRYPT, ['cost' => 12]);
    return $step3;
}

// ============================================================
// Tambah User
// ============================================================
if (isset($_POST['tambah_user'])) {
    $username     = sanitize($_POST['username']);
    $nama_lengkap = sanitize($_POST['nama_lengkap']);
    $role         = sanitize($_POST['role']);

    // Validasi panjang password
    if (strlen($_POST['password']) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        // ✅ Hash berlapis
        $password = hashPasswordBerlapis($_POST['password']);

        // Cek username sudah ada
        $check = "SELECT id FROM users WHERE username = '$username'";
        if (mysqli_num_rows(mysqli_query($conn, $check)) > 0) {
            $error = "Username sudah digunakan!";
        } else {
            $query = "INSERT INTO users (username, password, nama_lengkap, role)
                      VALUES ('$username', '$password', '$nama_lengkap', '$role')";
            if (mysqli_query($conn, $query)) {
                $success = "User berhasil ditambahkan";
            } else {
                $error = "Gagal menambahkan user: " . mysqli_error($conn);
            }
        }
    }
}

// ============================================================
// Edit User (username, nama, role — TANPA password)
// ============================================================
if (isset($_POST['edit_user'])) {
    $user_id      = sanitize($_POST['user_id']);
    $username     = sanitize($_POST['username']);
    $nama_lengkap = sanitize($_POST['nama_lengkap']);
    $role         = sanitize($_POST['role']);

    // Cek username dipakai user lain
    $check = "SELECT id FROM users WHERE username = '$username' AND id != '$user_id'";
    if (mysqli_num_rows(mysqli_query($conn, $check)) > 0) {
        $error = "Username sudah digunakan oleh user lain!";
    } else {
        $query = "UPDATE users SET username = '$username', nama_lengkap = '$nama_lengkap', role = '$role'
                  WHERE id = '$user_id'";
        if (mysqli_query($conn, $query)) {
            $success = "User berhasil diupdate";
        } else {
            $error = "Gagal update user: " . mysqli_error($conn);
        }
    }
}

// ============================================================
// Reset Password — ✅ pakai hash berlapis
// ============================================================
if (isset($_POST['reset_password'])) {
    $user_id = sanitize($_POST['user_id']);

    if (strlen($_POST['password_baru']) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        // ✅ Hash berlapis
        $password_baru = hashPasswordBerlapis($_POST['password_baru']);

        $query = "UPDATE users SET password = '$password_baru' WHERE id = '$user_id'";
        if (mysqli_query($conn, $query)) {
            $success = "Password berhasil direset";
        } else {
            $error = "Gagal reset password: " . mysqli_error($conn);
        }
    }
}

// ============================================================
// Hapus User
// ============================================================
if (isset($_POST['hapus_user'])) {
    $user_id = sanitize($_POST['user_id']);

    if ($user_id == $_SESSION['user_id']) {
        $error = "Tidak bisa menghapus akun sendiri!";
    } else {
        $query = "DELETE FROM users WHERE id = '$user_id'";
        if (mysqli_query($conn, $query)) {
            $success = "User berhasil dihapus";
        } else {
            $error = "Gagal menghapus user: " . mysqli_error($conn);
        }
    }
}

// ============================================================
// Get All Users
// ============================================================
$query_users  = "SELECT *
FROM users
WHERE id NOT IN (1,2,3)
ORDER BY created_at DESC;";
$result_users = mysqli_query($conn, $query_users);

// Count by role
$query_count    = "SELECT role, COUNT(*) as total FROM users GROUP BY role";
$result_count   = mysqli_query($conn, $query_count);
$count_by_role  = [];
while ($row = mysqli_fetch_assoc($result_count)) {
    $count_by_role[$row['role']] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master User - Healoka</title>
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link rel="shortcut icon" type="image/png" href="asset/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php include 'sidebar_style.php'; ?>
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
        .container-fluid { padding: 0; margin: 0; }
        .row { margin: 0; display: flex; }
        .col-md-2 { flex: 0 0 250px; max-width: 250px; }
        .col-md-10 { flex: 1; margin-left: 250px; padding: 0; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white; border-radius: 15px 15px 0 0 !important; padding: 15px 20px; font-weight: 600;
        }
        .table thead th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; font-weight: 600; }
        .badge { padding: 6px 12px; border-radius: 8px; font-weight: 500; }
        .btn { border-radius: 8px; padding: 8px 16px; font-weight: 500; }
        .form-control, .form-select { border-radius: 8px; border: 1px solid #e0e0e0; }
        .modal-content { border-radius: 15px; }
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white; border-radius: 15px 15px 0 0;
        }
        .stat-card { border: none; border-radius: 15px; transition: transform 0.3s, box-shadow 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .stat-icon { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; }

        /* Password strength indicator */
        .strength-bar { height: 5px; border-radius: 3px; transition: all .3s; background: #e0e0e0; margin-top: 6px; }
        .strength-bar .fill { height: 100%; border-radius: 3px; transition: all .3s; width: 0; }
        .strength-text { font-size: 11px; margin-top: 3px; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <div class="container-fluid px-4">
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Total User</p>
                                        <h3 class="mb-0"><?= mysqli_num_rows($result_users) ?></h3>
                                    </div>
                                    <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-users"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Medis</p>
                                        <h3 class="mb-0"><?= $count_by_role['medis'] ?? 0 ?></h3>
                                    </div>
                                    <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-user-md"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Apoteker</p>
                                        <h3 class="mb-0"><?= $count_by_role['apoteker'] ?? 0 ?></h3>
                                    </div>
                                    <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-pills"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Staff</p>
                                        <h3 class="mb-0">
                                            <?= ($count_by_role['pendaftaran'] ?? 0) + ($count_by_role['petugas'] ?? 0) + ($count_by_role['kasir'] ?? 0) + ($count_by_role['gudang'] ?? 0) ?>
                                        </h3>
                                    </div>
                                    <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-user-tie"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
                            <i class="fas fa-user-plus me-2"></i>Tambah User
                        </button>
                    </div>
                </div>

                <!-- User Table -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-users me-2"></i>Daftar User</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Nama Lengkap</th>
                                        <th>Role</th>
                                        <th>Keamanan Password</th>
                                        <th>Dibuat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Reset pointer
                                    mysqli_data_seek($result_users, 0);
                                    if (mysqli_num_rows($result_users) > 0):
                                        while ($row = mysqli_fetch_assoc($result_users)):
                                        $badge_class = match($row['role']) {
                                            'admin'       => 'bg-danger',
                                            'medis'       => 'bg-success',
                                            'apoteker'    => 'bg-info',
                                            'kasir'       => 'bg-warning',
                                            'pendaftaran' => 'bg-primary',
                                            'petugas'     => 'bg-secondary',
                                            'gudang'      => 'bg-dark',
                                            default       => 'bg-secondary',
                                        };
                                        // Deteksi tipe hash
                                        $is_md5 = (bool) preg_match('/^[a-f0-9]{32}$/i', $row['password']);
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                        <td><span class="badge <?= $badge_class ?>"><?= ucfirst($row['role']) ?></span></td>
                                        <td>
                                            <?php if ($is_md5): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>MD5 (Perlu Migrasi)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-shield-alt me-1"></i>Aman (3 Lapis)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick='editUser(<?= json_encode($row) ?>)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="resetPassword(<?= $row['id'] ?>, '<?= addslashes($row['nama_lengkap']) ?>')">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-danger" onclick="hapusUser(<?= $row['id'] ?>, '<?= addslashes($row['username']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>Belum ada data user
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Modal Tambah User ─────────────────────────────────────────────── -->
    <div class="modal fade" id="modalTambahUser" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Tambah User Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" required>
                            <small class="text-muted">Username untuk login</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="tambah_password"
                                       required minlength="6" oninput="cekKekuatan(this, 'bar_tambah', 'txt_tambah')">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePass('tambah_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="strength-bar"><div class="fill" id="bar_tambah"></div></div>
                            <div class="strength-text text-muted" id="txt_tambah"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_lengkap" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" required>
                                <option value="">-- Pilih Role --</option>
                                <option value="admin">Admin</option>
                                <option value="pendaftaran">Pendaftaran</option>
                                <option value="medis">Medis</option>
                                <option value="apoteker">Apoteker</option>
                                <option value="kasir">Kasir</option>
                                <option value="gudang">Gudang</option>
                                <option value="petugas">Petugas</option>
                            </select>
                        </div>
                        <div class="alert alert-info py-2 small">
                            <i class="fas fa-shield-alt me-2"></i>
                            Password akan dienkripsi <strong>3 lapis</strong>: MD5 → SHA256 → bcrypt
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_user" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Modal Edit User ───────────────────────────────────────────────── -->
    <div class="modal fade" id="modalEditUser" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" id="edit_username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_lengkap" id="edit_nama_lengkap" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="admin">Admin</option>
                                <option value="pendaftaran">Pendaftaran</option>
                                <option value="medis">Medis</option>
                                <option value="apoteker">Apoteker</option>
                                <option value="kasir">Kasir</option>
                                <option value="gudang">Gudang</option>
                                <option value="petugas">Petugas</option>
                            </select>
                        </div>
                        <div class="alert alert-info py-2 small">
                            <i class="fas fa-info-circle me-2"></i>
                            Untuk mengubah password, gunakan tombol <strong>Reset Password</strong>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit_user" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Modal Reset Password ──────────────────────────────────────────── -->
    <div class="modal fade" id="modalResetPassword" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="reset_user_id">
                        <div class="alert alert-warning py-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Reset password untuk: <strong id="reset_nama_user"></strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password_baru" id="reset_password_input"
                                       required minlength="6" oninput="cekKekuatan(this, 'bar_reset', 'txt_reset')">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePass('reset_password_input', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="strength-bar"><div class="fill" id="bar_reset"></div></div>
                            <div class="strength-text text-muted" id="txt_reset"></div>
                        </div>
                        <div class="alert alert-info py-2 small">
                            <i class="fas fa-shield-alt me-2"></i>
                            Password baru akan dienkripsi <strong>3 lapis</strong>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="reset_password" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Modal Hapus User ──────────────────────────────────────────────── -->
    <div class="modal fade" id="modalHapusUser" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title text-white"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="hapus_user_id">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Hapus user: <strong id="hapus_username"></strong>?
                        </div>
                        <small class="text-muted">Data yang sudah dihapus tidak dapat dikembalikan!</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="hapus_user" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Hapus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editUser(data) {
        $('#edit_user_id').val(data.id);
        $('#edit_username').val(data.username);
        $('#edit_nama_lengkap').val(data.nama_lengkap);
        $('#edit_role').val(data.role);
        $('#modalEditUser').modal('show');
    }

    function resetPassword(id, nama) {
        $('#reset_user_id').val(id);
        $('#reset_nama_user').text(nama);
        $('#reset_password_input').val('');
        $('#bar_reset').css({width:'0',background:''});
        $('#txt_reset').text('');
        $('#modalResetPassword').modal('show');
    }

    function hapusUser(id, username) {
        $('#hapus_user_id').val(id);
        $('#hapus_username').text(username);
        $('#modalHapusUser').modal('show');
    }

    // ── Toggle show/hide password ─────────────────────────────────────────
    function togglePass(inputId, btn) {
        var inp = document.getElementById(inputId);
        var icon = btn.querySelector('i');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            inp.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // ── Password strength indicator ───────────────────────────────────────
    function cekKekuatan(input, barId, txtId) {
        var val = input.value;
        var score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        var bar  = document.getElementById(barId);
        var txt  = document.getElementById(txtId);
        var pct  = (score / 5) * 100;
        var color, label;

        if (score <= 1)      { color = '#dc3545'; label = 'Sangat Lemah'; }
        else if (score === 2) { color = '#fd7e14'; label = 'Lemah'; }
        else if (score === 3) { color = '#ffc107'; label = 'Sedang'; }
        else if (score === 4) { color = '#20c997'; label = 'Kuat'; }
        else                  { color = '#198754'; label = 'Sangat Kuat'; }

        bar.style.width    = pct + '%';
        bar.style.background = color;
        txt.textContent    = label;
        txt.style.color    = color;
    }
    </script>
</body>
</html>