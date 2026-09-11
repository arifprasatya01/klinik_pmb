<?php
/**
 * ============================================================
 *  SECURITY.PHP — Pustaka Keamanan Terpusat
 *  Klinik PMBIIS | Healoka
 * ============================================================
 *
 *  Cara pakai di setiap halaman:
 *    require_once 'includes/security.php';
 *    Security::init();                    // wajib paling atas
 *    Security::requireLogin();            // halaman butuh login
 *    Security::requireRole(['admin']);    // halaman butuh role tertentu
 *
 *  Di form HTML:
 *    <?= Security::csrfField() ?>
 *
 *  Di handler POST:
 *    Security::verifyCsrf();
 *
 *  Di AJAX handler:
 *    Security::requireAjax();
 *    Security::verifyCsrf($_POST['csrf_token'] ?? '');
 * ============================================================
 */

class Security
{
    // ─── Konstanta ──────────────────────────────────────────
    const SESSION_LIFETIME   = 7200;       // 2 jam (detik)
    const CSRF_TOKEN_LENGTH  = 32;
    const RATE_LIMIT_MAX     = 10;         // max attempt login
    const RATE_LIMIT_WINDOW  = 900;        // 15 menit (detik)
    const RATE_LIMIT_LOCKOUT = 1800;       // 30 menit lockout (detik)
    const BCRYPT_COST        = 12;

    // ─── Init ────────────────────────────────────────────────
    /**
     * Panggil di paling atas setiap halaman PHP.
     * - Set security headers
     * - Start / validasi session
     * - Regenerate session ID berkala
     */
    public static function init(): void
    {
        // Keamanan transport
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure',   isset($_SERVER['HTTPS']) ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime',  self::SESSION_LIFETIME);
            session_start();
        }

        // Regenerasi ID session setiap 30 menit (cegah session hijacking)
        if (!isset($_SESSION['_last_regenerate'])) {
            $_SESSION['_last_regenerate'] = time();
        } elseif (time() - $_SESSION['_last_regenerate'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_last_regenerate'] = time();
        }

        // Validasi IP & User-Agent konsisten (deteksi session hijacking)
        if (isset($_SESSION['user_id'])) {
            $ip_fp = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
            if (!isset($_SESSION['_fingerprint'])) {
                $_SESSION['_fingerprint'] = $ip_fp;
            } elseif ($_SESSION['_fingerprint'] !== $ip_fp) {
                self::forceLogout('Session tidak valid.');
            }
        }

        // Cek timeout session idle
        if (isset($_SESSION['_last_activity'])) {
            if (time() - $_SESSION['_last_activity'] > self::SESSION_LIFETIME) {
                self::forceLogout('Session habis, silakan login ulang.');
            }
        }
        if (isset($_SESSION['user_id'])) {
            $_SESSION['_last_activity'] = time();
        }

        // HTTP Security Headers
        self::setSecurityHeaders();
    }

    // ─── Security Headers ────────────────────────────────────
    public static function setSecurityHeaders(): void
    {
        if (headers_sent()) return;

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        // Content-Security-Policy — sesuaikan jika kamu pakai CDN lain
        header(
            "Content-Security-Policy: " .
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com; " .
            "style-src  'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
            "font-src   'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; " .
            "img-src    'self' data:; " .
            "connect-src 'self';"
        );
    }

    // ─── Auth Guard ──────────────────────────────────────────
    /**
     * Pastikan user sudah login.
     * Jika tidak, redirect ke index.php.
     */
    public static function requireLogin(): void
    {
        if (empty($_SESSION['user_id'])) {
            self::redirectLogin();
        }
    }

    /**
     * Pastikan user punya role yang diizinkan.
     * @param string[] $roles  Daftar role yang boleh akses (e.g. ['admin','kasir'])
     */
    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
            http_response_code(403);
            die('<h3>403 – Akses Ditolak</h3><p>Kamu tidak punya izin mengakses halaman ini.</p>');
        }
    }

    /**
     * Pastikan request datang via AJAX (header X-Requested-With).
     */
    public static function requireAjax(): void
    {
        $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($xrw) !== 'xmlhttprequest') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Request tidak valid.']);
            exit;
        }
    }

    /**
     * Paksa logout: hapus session, redirect ke login.
     */
    public static function forceLogout(string $msg = ''): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        session_destroy();
        if ($msg) {
            // Simpan pesan ke cookie sementara supaya bisa ditampilkan di login
            setcookie('_logout_msg', urlencode($msg), time() + 10, '/', '', false, true);
        }
        self::redirectLogin();
    }

    private static function redirectLogin(): void
    {
        header('Location: https://dummy-klinik.healoka.com/');
        exit;
    }

    // ─── CSRF ────────────────────────────────────────────────
    /**
     * Ambil atau buat CSRF token untuk session ini.
     */
    public static function getCsrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(self::CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Output hidden input field CSRF untuk form HTML.
     * Pemakaian: <?= Security::csrfField() ?>
     */
    public static function csrfField(): string
    {
        $token = self::getCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Output CSRF token sebagai meta tag (berguna untuk AJAX).
     * Taruh di <head>: <?= Security::csrfMeta() ?>
     * Lalu di JS: const CSRF = document.querySelector('meta[name=csrf-token]').content;
     */
    public static function csrfMeta(): string
    {
        $token = self::getCsrfToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Verifikasi CSRF token.
     * Panggil di awal handler POST/AJAX.
     * @param string|null $token  Token dari $_POST['csrf_token'] atau header. Null = ambil otomatis.
     * @param bool $isJson        Jika true, kirim JSON error (untuk AJAX). Default false.
     */
    public static function verifyCsrf(?string $token = null, bool $isJson = false): void
    {
        // Baca token: parameter > POST > header (untuk AJAX)
        if ($token === null) {
            $token = $_POST['csrf_token']
                ?? $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? '';
        }

        $valid = isset($_SESSION['_csrf_token'])
            && hash_equals($_SESSION['_csrf_token'], $token);

        if (!$valid) {
            if ($isJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Token CSRF tidak valid. Refresh halaman dan coba lagi.']);
                exit;
            }
            http_response_code(403);
            die('<h3>403 – Token CSRF Tidak Valid</h3><p>Refresh halaman dan ulangi aksi.</p>');
        }
    }

    // ─── Rate Limiting (berbasis file/session) ────────────────
    /**
     * Rate limit berbasis IP untuk endpoint login.
     * Gunakan sebelum proses login: Security::checkRateLimit($_SERVER['REMOTE_ADDR']);
     *
     * Data disimpan di file tmp supaya bertahan antar request tanpa DB.
     * Butuh folder /tmp yang writable (default di Linux/XAMPP).
     */
    public static function checkRateLimit(string $key): void
    {
        $file = sys_get_temp_dir() . '/rl_' . md5($key) . '.json';
        $now  = time();
        $data = [];

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?? [];
        }

        // Hapus entri lama di luar window
        $data['attempts'] = array_filter(
            $data['attempts'] ?? [],
            fn($t) => ($now - $t) < self::RATE_LIMIT_WINDOW
        );

        // Cek lockout
        if (isset($data['lockout_until']) && $now < $data['lockout_until']) {
            $sisa = ceil(($data['lockout_until'] - $now) / 60);
            http_response_code(429);
            die("<h3>429 – Terlalu Banyak Percobaan</h3><p>Tunggu {$sisa} menit sebelum mencoba lagi.</p>");
        }

        // Cek batas attempt
        if (count($data['attempts']) >= self::RATE_LIMIT_MAX) {
            $data['lockout_until'] = $now + self::RATE_LIMIT_LOCKOUT;
            file_put_contents($file, json_encode($data), LOCK_EX);
            http_response_code(429);
            $menit = self::RATE_LIMIT_LOCKOUT / 60;
            die("<h3>429 – Akun Dikunci Sementara</h3><p>Terlalu banyak percobaan. Coba lagi dalam {$menit} menit.</p>");
        }

        // Catat attempt ini
        $data['attempts'][] = $now;
        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Reset rate limit (panggil setelah login berhasil).
     */
    public static function resetRateLimit(string $key): void
    {
        $file = sys_get_temp_dir() . '/rl_' . md5($key) . '.json';
        if (file_exists($file)) unlink($file);
    }

    // ─── Input Sanitization ──────────────────────────────────
    /**
     * Sanitasi string input umum.
     * Tidak cocok untuk password — jangan pernah escape password sebelum hash.
     */
    public static function clean(string $input): string
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitasi untuk query — hanya sebagai fallback.
     * Selalu lebih baik pakai prepared statement.
     * @param \mysqli $conn
     */
    public static function escape(\mysqli $conn, string $input): string
    {
        return mysqli_real_escape_string($conn, trim($input));
    }

    /**
     * Validasi & filter integer.
     * Kembalikan null jika bukan integer valid.
     */
    public static function int(?string $val): ?int
    {
        if ($val === null || $val === '') return null;
        $filtered = filter_var($val, FILTER_VALIDATE_INT);
        return $filtered !== false ? (int)$filtered : null;
    }

    /**
     * Validasi email.
     */
    public static function email(string $val): ?string
    {
        $filtered = filter_var(trim($val), FILTER_VALIDATE_EMAIL);
        return $filtered !== false ? $filtered : null;
    }

    /**
     * Validasi tanggal format Y-m-d.
     */
    public static function date(string $val): ?string
    {
        $d = DateTime::createFromFormat('Y-m-d', $val);
        return ($d && $d->format('Y-m-d') === $val) ? $val : null;
    }

    // ─── Password ────────────────────────────────────────────
    /**
     * Hash password berlapis (MD5 → SHA256 → bcrypt).
     * Konsisten dengan implementasi di index.php.
     */
    public static function hashPassword(string $password): string
    {
        $step1 = md5($password);
        $step2 = hash('sha256', $step1 . $password);
        return password_hash($step2, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    /**
     * Verifikasi password berlapis.
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        $step1 = md5($password);
        $step2 = hash('sha256', $step1 . $password);
        return password_verify($step2, $hash);
    }

    /**
     * Deteksi hash MD5 lama (32 karakter hex).
     */
    public static function isMd5Hash(string $hash): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/i', $hash);
    }

    // ─── XSS Output ──────────────────────────────────────────
    /**
     * Aman output ke HTML. Pakai ini alih-alih echo langsung.
     * Alias: e($val) tersedia di bawah.
     */
    public static function esc(mixed $val): string
    {
        return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // ─── Upload File ─────────────────────────────────────────
    /**
     * Validasi file upload.
     * @param array  $file       Elemen dari $_FILES['field']
     * @param array  $allowedExt Ekstensi yang diizinkan (e.g. ['jpg','png','pdf'])
     * @param int    $maxBytes   Ukuran maksimum (default 5 MB)
     * @return array ['ok'=>bool, 'error'=>string, 'ext'=>string]
     */
    public static function validateUpload(array $file, array $allowedExt = ['jpg','jpeg','png','pdf'], int $maxBytes = 5242880): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload gagal (kode ' . $file['error'] . ').', 'ext' => ''];
        }
        if ($file['size'] > $maxBytes) {
            return ['ok' => false, 'error' => 'Ukuran file melebihi ' . ($maxBytes / 1048576) . ' MB.', 'ext' => ''];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return ['ok' => false, 'error' => 'Tipe file tidak diizinkan. Hanya: ' . implode(', ', $allowedExt), 'ext' => ''];
        }

        // Validasi MIME via finfo (lebih terpercaya dari ekstensi)
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $mimeMap = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'pdf'  => ['application/pdf'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'xls'  => ['application/vnd.ms-excel'],
        ];

        if (isset($mimeMap[$ext]) && !in_array($mimeReal, $mimeMap[$ext], true)) {
            return ['ok' => false, 'error' => 'Konten file tidak sesuai dengan ekstensinya.', 'ext' => ''];
        }

        return ['ok' => true, 'error' => '', 'ext' => $ext];
    }

    /**
     * Buat nama file aman (tanpa path traversal, tanpa karakter berbahaya).
     */
    public static function safeFilename(string $original, string $ext): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $base);
        $base = substr($base, 0, 60);
        return $base . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    }
}

// ─── Shortcut global ─────────────────────────────────────────
/**
 * Shortcut untuk Security::esc() — output HTML aman.
 * Pemakaian: <?= e($variabel) ?>
 */
if (!function_exists('e')) {
    function e(mixed $val): string {
        return Security::esc($val);
    }
}
