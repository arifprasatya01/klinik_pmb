<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config/database.php';
require_once 'includes/security.php';   // ← Security terpusat

Security::init();   // Set headers + session security

// Jika sudah login, langsung redirect
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard');
    exit;
}

$error = '';

// Tampilkan pesan logout (dari forceLogout)
if (!empty($_COOKIE['_logout_msg'])) {
    $error = urldecode($_COOKIE['_logout_msg']);
    setcookie('_logout_msg', '', time() - 1, '/');
}

// ============================================================
// PROSES LOGIN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    // 1. Verifikasi CSRF
    Security::verifyCsrf();

    // 2. Rate limiting berbasis IP
    Security::checkRateLimit($_SERVER['REMOTE_ADDR']);

    // 3. Inisialisasi DB
    $db   = new Database();
    $conn = $db->getConnection();

    // 4. Ambil input (password JANGAN di-escape sebelum hash)
    $username = Security::escape($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // 5. Query pakai prepared statement
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user   = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $login_valid = false;

    if ($user) {
        $hash_db = $user['password'];

        // Jalur 1: Hash lama MD5 — auto-upgrade saat login
        if (Security::isMd5Hash($hash_db)) {
            if (md5($password) === $hash_db) {
                $login_valid = true;
                $hash_baru   = Security::hashPassword($password);
                $uid         = (int)$user['id'];
                $stmt2 = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt2, 'si', $hash_baru, $uid);
                mysqli_stmt_execute($stmt2);
                mysqli_stmt_close($stmt2);
            }
        }
        // Jalur 2: Hash berlapis bcrypt
        else {
            $login_valid = Security::verifyPassword($password, $hash_db);
        }
    }

    if ($login_valid) {
        // Reset rate limit setelah login sukses
        Security::resetRateLimit($_SERVER['REMOTE_ADDR']);

        // Regenerate session ID (cegah session fixation)
        session_regenerate_id(true);

        $_SESSION['user_id']      = $user['id'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['role']         = $user['role'];
        $_SESSION['login_time']   = time();
        $_SESSION['login_ip']     = $_SERVER['REMOTE_ADDR'];
        $_SESSION['_fingerprint'] = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
        $_SESSION['_last_activity']   = time();
        $_SESSION['_last_regenerate'] = time();

        // Log login berhasil (prepared statement)
        $ip  = $_SERVER['REMOTE_ADDR'];
        $uid = $user['id'];
        $stmt3 = mysqli_prepare($conn, "INSERT INTO login_log (user_id, ip_address, status, created_at) VALUES (?, ?, 'success', NOW())");
        mysqli_stmt_bind_param($stmt3, 'is', $uid, $ip);
        mysqli_stmt_execute($stmt3);
        mysqli_stmt_close($stmt3);

        switch ($user['role']) {
            case 'petugas':  header('Location: dashboard_user'); break;
            case 'apoteker': header('Location: apoteker');        break;
            case 'medis':    header('Location: dokter');          break;
            default:         header('Location: dashboard');
        }
        exit;

    } else {
        // Log login gagal
        if ($user) {
            $ip  = $_SERVER['REMOTE_ADDR'];
            $uid = $user['id'];
            $stmt4 = mysqli_prepare($conn, "INSERT INTO login_log (user_id, ip_address, status, created_at) VALUES (?, ?, 'failed', NOW())");
            mysqli_stmt_bind_param($stmt4, 'is', $uid, $ip);
            mysqli_stmt_execute($stmt4);
            mysqli_stmt_close($stmt4);
        }
        $error = 'Username atau password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Medisoft</title>
    <!-- CSRF meta untuk AJAX (opsional di halaman login, tapi best-practice) -->
    <?= Security::csrfMeta() ?>
    <link rel="icon" type="image/png" href="/asset/img/logo.png">
    <link rel="shortcut icon" type="image/png" href="/asset/img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container { max-width: 450px; margin: 0 auto; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 30px;
            text-align: center;
        }
        .card-body { padding: 40px; }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            border-color: #667eea;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: transform 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .logo-icon { font-size: 60px; margin-bottom: 15px; }
        .input-group-text {
            background: transparent;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <div class="logo-icon">
                    <i class="fas fa-hospital"></i>
                </div>
                <h3 class="mb-0">Sistem Informasi Klinik</h3>
                <p class="mb-0 mt-2">Silakan login untuk melanjutkan</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= Security::esc($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" action="" autocomplete="off">
                    <!-- CSRF Token -->
                    <?= Security::csrfField() ?>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" name="username" required autofocus autocomplete="username">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" name="password" id="inputPassword" required autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary" id="togglePwd" tabindex="-1">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="login" class="btn btn-primary btn-login w-100">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle tampilkan/sembunyikan password
document.getElementById('togglePwd').addEventListener('click', function () {
    const input = document.getElementById('inputPassword');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});
</script>
</body>
</html>
