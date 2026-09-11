<?php
/**
 * get_antrian_medis.php
 * API Panel Operator Antrian untuk medis.php
 * Membaca dari tabel pendaftaran + pasien (bukan tabel antrian terpisah)
 *
 * GET  ?action=list   [&poli=umum|kebidanan] [&status=menunggu|diperiksa|selesai]
 * GET  ?action=stat   [&poli=umum|kebidanan]
 * POST action=panggil_next  [&poli=umum|kebidanan]  → update status → 'diperiksa'
 * POST action=panggil_by_id &id=N
 * POST action=selesai       &id=N
 */

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');
ini_set('display_errors', 0);

require_once '../config/database.php';

$db   = new Database();
$conn = $db->getConnection();

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'list';

/* ════════════════════════════════════════════
   LIST — daftar antrian hari ini
   ════════════════════════════════════════════ */
if ($action === 'list') {
    $where  = ["DATE(p.tgl_daftar) = CURDATE()"];

    $poli = isset($_GET['poli']) ? mysqli_real_escape_string($conn, trim($_GET['poli'])) : '';
    if ($poli !== '') {
        $where[] = "p.poli = '$poli'";
    }

    $status_tab = isset($_GET['status']) ? trim($_GET['status']) : '';
    if (in_array($status_tab, ['menunggu', 'diperiksa', 'selesai', 'dibatalkan'])) {
        $where[] = "p.status = '$status_tab'";
    }

    $sql = "SELECT
                p.id,
                p.no_antrian,
                p.poli,
                p.status,
                p.tgl_daftar,
                p.keluhan,
                ps.nama_lengkap,
                ps.no_rm
            FROM pendaftaran p
            JOIN pasien ps ON p.pasien_id = ps.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.tgl_daftar ASC";

    $result = mysqli_query($conn, $sql);
    $rows   = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $label_poli = $row['poli'] === 'kebidanan' ? 'Poli Kebidanan' : 'Poli Umum';
            $rows[] = [
                'id'           => $row['id'],
                'no_antrian'   => $row['no_antrian'],
                'nama_pasien'  => $row['nama_lengkap'],
                'no_rm'        => $row['no_rm'],
                'nama_unit'    => $label_poli,
                'poli'         => $row['poli'],
                'waktu_ambil'  => date('H:i', strtotime($row['tgl_daftar'])),
                'keluhan'      => $row['keluhan'],
                'status'       => $row['status'],
            ];
        }
    }

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

/* ════════════════════════════════════════════
   STAT — statistik hari ini
   ════════════════════════════════════════════ */
if ($action === 'stat') {
    $poli = isset($_GET['poli']) ? mysqli_real_escape_string($conn, trim($_GET['poli'])) : '';
    $poli_where = $poli !== '' ? "AND poli = '$poli'" : '';

    $sql = "SELECT
                SUM(status = 'menunggu')   AS menunggu,
                SUM(status = 'diperiksa')  AS dipanggil,
                SUM(status = 'selesai')    AS selesai
            FROM pendaftaran
            WHERE DATE(tgl_daftar) = CURDATE()
            AND status != 'dibatalkan'
            $poli_where";

    $result = mysqli_query($conn, $sql);
    $stat   = $result
        ? mysqli_fetch_assoc($result)
        : ['menunggu' => 0, 'dipanggil' => 0, 'selesai' => 0];

    $stat['menunggu']  = (int)($stat['menunggu']  ?? 0);
    $stat['dipanggil'] = (int)($stat['dipanggil'] ?? 0);
    $stat['selesai']   = (int)($stat['selesai']   ?? 0);

    echo json_encode(['status' => 'success', 'stat' => $stat]);
    exit;
}

/* ════════════════════════════════════════════
   PANGGIL_NEXT — panggil pasien menunggu pertama
   (Update status pendaftaran → 'diperiksa')
   ════════════════════════════════════════════ */
if ($action === 'panggil_next') {
    $poli = isset($_POST['poli']) ? mysqli_real_escape_string($conn, trim($_POST['poli'])) : '';
    $poli_where = $poli !== '' ? "AND p.poli = '$poli'" : '';

    $sql = "SELECT p.id, p.no_antrian, p.poli, ps.nama_lengkap
            FROM pendaftaran p
            JOIN pasien ps ON p.pasien_id = ps.id
            WHERE DATE(p.tgl_daftar) = CURDATE()
              AND p.status = 'menunggu'
              $poli_where
            ORDER BY p.tgl_daftar ASC
            LIMIT 1";

    $result = mysqli_query($conn, $sql);

    if (!$result || mysqli_num_rows($result) === 0) {
        echo json_encode(['status' => 'empty', 'message' => 'Tidak ada antrian yang menunggu']);
        exit;
    }

    $row        = mysqli_fetch_assoc($result);
    $pend_id    = $row['id'];
    $label_poli = $row['poli'] === 'kebidanan' ? 'Poli Kebidanan' : 'Poli Umum';

    // Update status menjadi diperiksa
    mysqli_query($conn, "UPDATE pendaftaran SET status = 'diperiksa' WHERE id = '$pend_id'");

    echo json_encode([
        'status' => 'success',
        'data'   => [
            'id'         => $pend_id,
            'no_antrian' => $row['no_antrian'],
            'nama_unit'  => $label_poli,
            'nama_pasien'=> $row['nama_lengkap'],
        ]
    ]);
    exit;
}

/* ════════════════════════════════════════════
   PANGGIL_BY_ID — panggil antrian tertentu
   ════════════════════════════════════════════ */
if ($action === 'panggil_by_id') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
        exit;
    }

    // Ambil data untuk response
    $sql    = "SELECT p.no_antrian, p.poli, ps.nama_lengkap
               FROM pendaftaran p JOIN pasien ps ON p.pasien_id = ps.id
               WHERE p.id = '$id' LIMIT 1";
    $result = mysqli_query($conn, $sql);
    $row    = $result ? mysqli_fetch_assoc($result) : null;

    $ok = mysqli_query($conn, "UPDATE pendaftaran SET status = 'diperiksa' WHERE id = '$id' AND status = 'menunggu'");

    if (mysqli_affected_rows($conn) > 0) {
        $label_poli = ($row && $row['poli'] === 'kebidanan') ? 'Poli Kebidanan' : 'Poli Umum';
        echo json_encode([
            'status' => 'success',
            'data'   => [
                'id'          => $id,
                'no_antrian'  => $row['no_antrian'] ?? '-',
                'nama_unit'   => $label_poli,
                'nama_pasien' => $row['nama_lengkap'] ?? '-',
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memanggil — antrian mungkin sudah tidak menunggu']);
    }
    exit;
}

/* ════════════════════════════════════════════
   SELESAI — tandai antrian selesai
   ════════════════════════════════════════════ */
if ($action === 'selesai') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
        exit;
    }

    mysqli_query($conn, "UPDATE pendaftaran SET status = 'selesai' WHERE id = '$id'");

    echo json_encode(mysqli_affected_rows($conn) > 0
        ? ['status' => 'success']
        : ['status' => 'error', 'message' => 'Gagal menandai selesai']);
    exit;
}

/* Fallback */
echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenal: ' . htmlspecialchars($action)]);
