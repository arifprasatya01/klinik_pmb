<?php
date_default_timezone_set('Asia/Jakarta');

// ============================================================
// Load konfigurasi dari file .env (di luar public_html)
// ============================================================
function loadEnv($path) {
    if (!file_exists($path)) {
        die("File konfigurasi tidak ditemukan.");
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // skip komentar
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Sesuaikan path ini dengan lokasi file .env kamu
loadEnv(__DIR__ . '/../.env');

// ============================================================
// Class Database
// ============================================================
class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    public $conn;

    public function __construct() {
        $this->host     = $_ENV['DB_HOST']     ?? '';
        $this->username = $_ENV['DB_USERNAME'] ?? '';
        $this->password = $_ENV['DB_PASSWORD'] ?? '';
        $this->database = $_ENV['DB_DATABASE'] ?? '';

        $this->conn = mysqli_connect(
            $this->host,
            $this->username,
            $this->password,
            $this->database
        );

        if (!$this->conn) {
            // Jangan tampilkan error ke browser!
            error_log("Koneksi database gagal: " . mysqli_connect_error());
            die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
        }

        mysqli_set_charset($this->conn, "utf8");
        mysqli_query($this->conn, "SET time_zone = '+7:00'");
    }

    public function getConnection() {
        return $this->conn;
    }

    public function close() {
        mysqli_close($this->conn);
    }
}
?>