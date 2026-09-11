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

$term = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
$term_no_dash = str_replace('-', '', $term);

$query = "SELECT id, no_rm, nama_lengkap, jenis_pasien, no_bpjs, nama_asuransi, 
                 no_polis, nik, alamat, kepala_keluarga, tgl_lahir, jenis_kelamin, no_telepon
          FROM pasien 
          WHERE no_rm LIKE '%$term%' 
             OR REPLACE(no_rm, '-', '') LIKE '%$term_no_dash%'
             OR nama_lengkap LIKE '%$term%'
             OR nik LIKE '%$term%'
             OR kepala_keluarga LIKE '%$term%'
          ORDER BY id ASC
          LIMIT 20";

$result = mysqli_query($conn, $query);
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $label = $row['no_rm'] . ' - ' . $row['nama_lengkap'];
    if ($row['kepala_keluarga']) $label .= ' - ' . $row['kepala_keluarga'];
    if ($row['alamat'])          $label .= ' - ' . $row['alamat'];
    $label .= ' (' . strtoupper($row['jenis_pasien']) . ')';

    $data[] = [
        'id'        => $row['id'],
        'text'      => $label,
        'jenis'     => $row['jenis_pasien'],
        'nobpjs'    => $row['no_bpjs'],
        'asuransi'  => $row['nama_asuransi'],
        'polis'     => $row['no_polis'],
        'norm'      => str_replace('-', '', $row['no_rm']),
        'nik'       => $row['nik'],
        'alamat'    => $row['alamat'],
        'kk'        => $row['kepala_keluarga'] ?? '',
        'tgllahir'  => $row['tgl_lahir'],
        'jk'        => $row['jenis_kelamin'],
        'telp'      => $row['no_telepon'],
        'nama'      => $row['nama_lengkap'],
    ];
}

header('Content-Type: application/json');
echo json_encode(['results' => $data]);