<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'kasir' && $_SESSION['role'] != 'admin' && $_SESSION['role'] != 'petugas')) {
    header("Location: index.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';
$print_nota_id = 0;
$print_nota_type = '';

// Proses Penjualan Grosir
if (isset($_POST['penjualan_grosir'])) {
    $mitra_id = sanitize($_POST['mitra_id']);
    $obat_items = json_decode($_POST['obat_items_grosir'], true);
    $metode_bayar = sanitize($_POST['metode_bayar_grosir']);
    $jatuh_tempo = isset($_POST['jatuh_tempo']) ? sanitize($_POST['jatuh_tempo']) : null;
    $total_bayar = sanitize($_POST['total_bayar_grosir']);
    $diskon = isset($_POST['diskon_grosir']) ? sanitize($_POST['diskon_grosir']) : 0;
    $total_setelah_diskon = $total_bayar - $diskon;
    $jumlah_bayar = isset($_POST['jumlah_bayar_grosir']) ? sanitize($_POST['jumlah_bayar_grosir']) : 0;
    $kembalian = $jumlah_bayar - $total_setelah_diskon;
    $kasir_id = $_SESSION['user_id'];
    $keterangan = isset($_POST['keterangan_grosir']) ? sanitize($_POST['keterangan_grosir']) : '';
    
    if ($metode_bayar != 'hutang' && $jumlah_bayar < $total_setelah_diskon) {
        $error = "Jumlah bayar kurang dari total!";
    } else {
        mysqli_query($conn, "START TRANSACTION");
        
        $status_bayar = ($metode_bayar == 'hutang') ? 'belum_lunas' : 'lunas';
        
        $query = "INSERT INTO penjualan_grosir (mitra_id, kasir_id, total_bayar, diskon, total_setelah_diskon, metode_bayar, status_bayar, jatuh_tempo, jumlah_bayar, kembalian, keterangan) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iidddsssdds", $mitra_id, $kasir_id, $total_bayar, $diskon, $total_setelah_diskon, $metode_bayar, $status_bayar, $jatuh_tempo, $jumlah_bayar, $kembalian, $keterangan);
        $insert_penjualan = mysqli_stmt_execute($stmt);
        
        if ($insert_penjualan) {
            $penjualan_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            
            $detail_success = true;
            foreach ($obat_items as $item) {
                $obat_id = $item['obat_id'];
                $jumlah = $item['jumlah'];
                $harga_beli = $item['harga_beli'];
                $harga_jual = $item['harga_jual'];
                $subtotal = $item['subtotal'];
                
                $query_detail = "INSERT INTO detail_penjualan_grosir (penjualan_id, obat_id, jumlah, harga_beli, harga_jual, subtotal) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_detail = mysqli_prepare($conn, $query_detail);
                mysqli_stmt_bind_param($stmt_detail, "iiiddd", $penjualan_id, $obat_id, $jumlah, $harga_beli, $harga_jual, $subtotal);
                
                if (!mysqli_stmt_execute($stmt_detail)) {
                    $detail_success = false;
                    mysqli_stmt_close($stmt_detail);
                    break;
                }
                mysqli_stmt_close($stmt_detail);
                
                $query_update = "UPDATE obat SET stok = stok - ? WHERE id = ?";
                $stmt_update = mysqli_prepare($conn, $query_update);
                mysqli_stmt_bind_param($stmt_update, "ii", $jumlah, $obat_id);
                
                if (!mysqli_stmt_execute($stmt_update)) {
                    $detail_success = false;
                    mysqli_stmt_close($stmt_update);
                    break;
                }
                mysqli_stmt_close($stmt_update);
            }
            
            if ($detail_success) {
                $tgl_tagihan = date('Y-m-d');
                $status_tagihan = ($metode_bayar == 'hutang') ? 'belum_bayar' : 'lunas';
                $keterangan_tagihan = "Penjualan Grosir #" . str_pad($penjualan_id, 5, '0', STR_PAD_LEFT);
                
                if ($keterangan) {
                    $keterangan_tagihan .= " - " . $keterangan;
                }
                
                if ($status_tagihan == 'lunas') {
                    $query_tagihan = "INSERT INTO tagihan_mitra (mitra_id, tgl_tagihan, nominal, keterangan, status_bayar, tgl_validasi, validasi_oleh) 
                                      VALUES (?, ?, ?, ?, ?, NOW(), ?)";
                    $stmt_tagihan = mysqli_prepare($conn, $query_tagihan);
                    mysqli_stmt_bind_param($stmt_tagihan, "isdssi", $mitra_id, $tgl_tagihan, $total_setelah_diskon, $keterangan_tagihan, $status_tagihan, $kasir_id);
                } else {
                    $query_tagihan = "INSERT INTO tagihan_mitra (mitra_id, tgl_tagihan, nominal, keterangan, status_bayar) 
                                      VALUES (?, ?, ?, ?, ?)";
                    $stmt_tagihan = mysqli_prepare($conn, $query_tagihan);
                    mysqli_stmt_bind_param($stmt_tagihan, "isdss", $mitra_id, $tgl_tagihan, $total_setelah_diskon, $keterangan_tagihan, $status_tagihan);
                }
                
                if (!mysqli_stmt_execute($stmt_tagihan)) {
                    $detail_success = false;
                }
                mysqli_stmt_close($stmt_tagihan);
            }
            
            if ($detail_success) {
                mysqli_query($conn, "COMMIT");
                $success_msg = "Penjualan grosir berhasil!";
                if ($diskon > 0) {
                    $success_msg .= " Diskon: " . formatRupiah($diskon) . ".";
                }
                if ($metode_bayar == 'hutang') {
                    $success_msg .= " Status: HUTANG - Jatuh tempo: " . date('d/m/Y', strtotime($jatuh_tempo)) . ". Tagihan telah dibuat untuk mitra.";
                } else {
                    $success_msg .= " Kembalian: " . formatRupiah($kembalian) . ". Tagihan telah dibuat dan divalidasi.";
                }
                $success = $success_msg;
                $print_nota_id = $penjualan_id;
                $print_nota_type = 'grosir';
            } else {
                mysqli_query($conn, "ROLLBACK");
                $error = "Gagal memproses penjualan grosir atau membuat tagihan!";
            }
        } else {
            mysqli_query($conn, "ROLLBACK");
            $error = "Gagal memproses penjualan grosir!";
            if (isset($stmt)) mysqli_stmt_close($stmt);
        }
    }
}
// Proses Batal Validasi Resep
if (isset($_POST['batal_validasi_resep'])) {
    $pembayaran_id = sanitize($_POST['pembayaran_id']);
    $resep_id      = sanitize($_POST['resep_id_batal']);

    mysqli_query($conn, "START TRANSACTION");

    $query_detail = "SELECT obat_id, jumlah FROM detail_resep 
                     WHERE resep_id = '$resep_id' AND obat_id IS NOT NULL AND obat_id > 0";
    $result_detail = mysqli_query($conn, $query_detail);
    $stok_ok = true;
    while ($row = mysqli_fetch_assoc($result_detail)) {
        if (!mysqli_query($conn, "UPDATE obat SET stok = stok + {$row['jumlah']} WHERE id = {$row['obat_id']}")) {
            $stok_ok = false; break;
        }
    }

    $upd = mysqli_query($conn, "UPDATE pembayaran SET status = 'belum_bayar' WHERE id = '$pembayaran_id'");

    if ($upd && $stok_ok) {
        mysqli_query($conn, "COMMIT");
        $success = "Validasi pembayaran berhasil dibatalkan!";
    } else {
        mysqli_query($conn, "ROLLBACK");
        $error = "Gagal membatalkan validasi!";
    }
}

// Proses Batal Penjualan Langsung
if (isset($_POST['batal_penjualan_langsung'])) {
    $penjualan_id = sanitize($_POST['penjualan_langsung_id']);

    mysqli_query($conn, "START TRANSACTION");

    $query_detail = "SELECT obat_id, jumlah FROM detail_penjualan_langsung 
                     WHERE penjualan_id = '$penjualan_id'";
    $result_detail = mysqli_query($conn, $query_detail);
    $stok_ok = true;
    while ($row = mysqli_fetch_assoc($result_detail)) {
        if (!mysqli_query($conn, "UPDATE obat SET stok = stok + {$row['jumlah']} WHERE id = {$row['obat_id']}")) {
            $stok_ok = false; break;
        }
    }

    $upd = mysqli_query($conn, "UPDATE penjualan_langsung SET status = 'belum_bayar' WHERE id = '$penjualan_id'");

    if ($upd && $stok_ok) {
        mysqli_query($conn, "COMMIT");
        $success = "Penjualan langsung berhasil dibatalkan!";
    } else {
        mysqli_query($conn, "ROLLBACK");
        $error = "Gagal membatalkan penjualan langsung!";
    }
}
// Proses Penjualan Langsung
if (isset($_POST['penjualan_langsung'])) {
    $obat_items = json_decode($_POST['obat_items'], true);
    $metode_bayar = sanitize($_POST['metode_bayar_langsung']);
    $jumlah_bayar = sanitize($_POST['jumlah_bayar_langsung']);
    $total_bayar = sanitize($_POST['total_bayar_langsung']);
    $diskon = isset($_POST['diskon_langsung']) ? sanitize($_POST['diskon_langsung']) : 0;
    $total_setelah_diskon = $total_bayar - $diskon;
    $kembalian = $jumlah_bayar - $total_setelah_diskon;
    $kasir_id = $_SESSION['user_id'];
    $nama_pembeli = sanitize($_POST['nama_pembeli']);
    
    if ($jumlah_bayar < $total_setelah_diskon) {
        $error = "Jumlah bayar kurang dari total!";
    } else {
        mysqli_query($conn, "START TRANSACTION");
        
        $keterangan = "Penjualan Langsung - " . $nama_pembeli;
        $query = "INSERT INTO penjualan_langsung (kasir_id, nama_pembeli, total_bayar, diskon, total_setelah_diskon, metode_bayar, jumlah_bayar, kembalian, keterangan,status) 
                  VALUES ('$kasir_id', '$nama_pembeli', '$total_bayar', '$diskon', '$total_setelah_diskon', '$metode_bayar', '$jumlah_bayar', '$kembalian', '$keterangan','lunas')";
        $insert_penjualan = mysqli_query($conn, $query);
        
        if ($insert_penjualan) {
            $penjualan_id = mysqli_insert_id($conn);
            
            $detail_success = true;
            foreach ($obat_items as $item) {
                $obat_id = $item['obat_id'];
                $jumlah = $item['jumlah'];
                $harga = $item['harga'];
                $subtotal = $item['subtotal'];
                
                $query_detail = "INSERT INTO detail_penjualan_langsung (penjualan_id, obat_id, jumlah, harga, subtotal) 
                                VALUES ('$penjualan_id', '$obat_id', '$jumlah', '$harga', '$subtotal')";
                if (!mysqli_query($conn, $query_detail)) {
                    $detail_success = false;
                    break;
                }
                
                $query_update = "UPDATE obat SET stok = stok - $jumlah WHERE id = $obat_id";
                if (!mysqli_query($conn, $query_update)) {
                    $detail_success = false;
                    break;
                }
            }
            
            if ($detail_success) {
                mysqli_query($conn, "COMMIT");
                $success = "Penjualan langsung berhasil! Kembalian: " . formatRupiah($kembalian);
                $print_nota_id = $penjualan_id;
                $print_nota_type = 'langsung';
            } else {
                mysqli_query($conn, "ROLLBACK");
                $error = "Gagal memproses penjualan!";
            }
        } else {
            mysqli_query($conn, "ROLLBACK");
            $error = "Gagal memproses penjualan!";
        }
    }
}

// Proses Pembayaran / Validasi
// if (isset($_POST['bayar'])) {
//     $resep_id = sanitize($_POST['resep_id']);
//     $jenis_pasien = sanitize($_POST['jenis_pasien']);
//     $total_bayar = sanitize($_POST['total_bayar']);
//     $diskon = isset($_POST['diskon']) ? sanitize($_POST['diskon']) : 0;
//     $total_setelah_diskon = sanitize($_POST['total_setelah_diskon']);
//     $kasir_id = $_SESSION['user_id'];
    
//     if ($jenis_pasien == 'umum') {
//         $metode_bayar = sanitize($_POST['metode_bayar']);
//         $jumlah_bayar = sanitize($_POST['jumlah_bayar']);
//         $kembalian = $jumlah_bayar - $total_setelah_diskon;
//         $keterangan = 'Umum';
        
//         if ($jumlah_bayar < $total_setelah_diskon) {
//             $error = "Jumlah bayar kurang dari total!";
//         } else {
//             mysqli_query($conn, "START TRANSACTION");
            
//             $query = "INSERT INTO pembayaran (resep_id, kasir_id, total_bayar, diskon, total_setelah_diskon, metode_bayar, jumlah_bayar, kembalian, keterangan) 
//                       VALUES ('$resep_id', '$kasir_id', '$total_bayar', '$diskon', '$total_setelah_diskon', '$metode_bayar', '$jumlah_bayar', '$kembalian', '$keterangan')";
//             $insert_bayar = mysqli_query($conn, $query);
            
//             $query_detail = "SELECT obat_id, jumlah FROM detail_resep WHERE resep_id = '$resep_id' ";
//             $result_detail = mysqli_query($conn, $query_detail);
            
//             $stok_success = true;
//             while ($row = mysqli_fetch_assoc($result_detail)) {
//                 if ($row['obat_id'] && $row['obat_id'] > 0) {
//                     $query_update = "UPDATE obat SET stok = stok - {$row['jumlah']} WHERE id = {$row['obat_id']}";
//                     if (!mysqli_query($conn, $query_update)) {
//                         $stok_success = false;
//                         break;
//                     }
//                 }
//             }
            
//             if ($insert_bayar && $stok_success) {
//                 mysqli_query($conn, "COMMIT");
//                 $msg = "Pembayaran berhasil! Kembalian: " . formatRupiah($kembalian);
//                 if ($diskon > 0) {
//                     $msg .= " (Diskon: " . formatRupiah($diskon) . ")";
//                 }
//                 $success = $msg;
//                 $print_nota_id = $resep_id;
//                 $print_nota_type = 'resep';
//             } else {
//                 mysqli_query($conn, "ROLLBACK");
//                 $error = "Gagal memproses pembayaran!";
//             }
//         }
//     } else {
//         $no_kartu = sanitize($_POST['no_kartu']);
//         $keterangan = isset($_POST['keterangan']) ? sanitize($_POST['keterangan']) : '';
        
//         if ($diskon > 0) {
//             $keterangan .= ($keterangan ? ' | ' : '') . 'Diskon: ' . formatRupiah($diskon);
//         }
//         $keterangan .= ($keterangan ? ' | ' : '') . 'Harga sudah termasuk markup 35% untuk obat';
        
//         mysqli_query($conn, "START TRANSACTION");
        
//         $query = "INSERT INTO pembayaran (resep_id, kasir_id, total_bayar, diskon, total_setelah_diskon, metode_bayar, jumlah_bayar, kembalian, keterangan) 
//                   VALUES ('$resep_id', '$kasir_id', '$total_bayar', '$diskon', '$total_setelah_diskon', '$jenis_pasien', '0', '0', '$keterangan')";
//         $insert_bayar = mysqli_query($conn, $query);
        
//         $query_detail = "SELECT obat_id, jumlah FROM detail_resep WHERE resep_id = '$resep_id' AND (jenis_item = 'obat' OR obat_id IS NOT NULL)";
//         $result_detail = mysqli_query($conn, $query_detail);
        
//         $stok_success = true;
//         while ($row = mysqli_fetch_assoc($result_detail)) {
//             if ($row['obat_id'] && $row['obat_id'] > 0) {
//                 $query_update = "UPDATE obat SET stok = stok - {$row['jumlah']} WHERE id = {$row['obat_id']}";
//                 if (!mysqli_query($conn, $query_update)) {
//                     $stok_success = false;
//                     break;
//                 }
//             }
//         }
        
//         if ($insert_bayar && $stok_success) {
//             mysqli_query($conn, "COMMIT");
//             $msg = "Validasi " . strtoupper($jenis_pasien) . " berhasil! No. Kartu: $no_kartu";
//             if ($diskon > 0) {
//                 $msg .= " (Diskon: " . formatRupiah($diskon) . ")";
//             }
//             $success = $msg;
//             $print_nota_id = $resep_id;
//             $print_nota_type = 'resep';
//         } else {
//             mysqli_query($conn, "ROLLBACK");
//             $error = "Gagal memproses validasi!";
//         }
//     }
// }

if (isset($_POST['bayar'])) {
    $resep_id = sanitize($_POST['resep_id']);
    $jenis_pasien = sanitize($_POST['jenis_pasien']);
    $total_bayar = sanitize($_POST['total_bayar']);
    $diskon = isset($_POST['diskon']) ? sanitize($_POST['diskon']) : 0;
    $bulat = isset($_POST['bulat']) ? sanitize($_POST['bulat']) : 0;
    
    $total_setelah_diskon = sanitize($_POST['total_setelah_diskon']);
    $total_setelah_bulat  = isset($_POST['total_setelah_bulat']) ? sanitize($_POST['total_setelah_bulat']) : $total_setelah_diskon;
    
    $total_final = ($bulat > 0) ? $total_setelah_bulat : $total_setelah_diskon;
    
$kasir_id = $_SESSION['user_id'];

$pemeriksaan_id = isset($_POST['pemeriksaan_id']) ? (int)$_POST['pemeriksaan_id'] : null;
$resep_id = ($resep_id > 0) ? $resep_id : null;

if ($jenis_pasien == 'umum') {
        $metode_bayar = sanitize($_POST['metode_bayar']);
        $jumlah_bayar = sanitize($_POST['jumlah_bayar']);
        $kembalian = $jumlah_bayar - $total_final;
        $keterangan = 'Umum';
        
        if ($jumlah_bayar < $total_final) {
            $error = "Jumlah bayar kurang dari total!";
        } else {
            mysqli_query($conn, "START TRANSACTION");
            
//             $query = "INSERT INTO pembayaran (resep_id, kasir_id, total_bayar, diskon, total_setelah_diskon, metode_bayar, jumlah_bayar, kembalian, keterangan) 
//           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
// $stmt_bayar = mysqli_prepare($conn, $query);
// mysqli_stmt_bind_param($stmt_bayar, "iidddsdds", $resep_id, $kasir_id, $total_bayar, $diskon, $total_final, $metode_bayar, $jumlah_bayar, $kembalian, $keterangan);
$pemeriksaan_id = isset($_POST['pemeriksaan_id']) ? intval($_POST['pemeriksaan_id']) : null;

$query = "INSERT INTO pembayaran (resep_id, pemeriksaan_id, kasir_id, total_bayar, diskon, total_setelah_diskon, metode_bayar, jumlah_bayar, kembalian, keterangan) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt_bayar = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt_bayar, "iiidddsdds", $resep_id, $pemeriksaan_id, $kasir_id, $total_bayar, $diskon, $total_final, $metode_bayar, $jumlah_bayar, $kembalian, $keterangan);


$insert_bayar = mysqli_stmt_execute($stmt_bayar);
$pembayaran_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt_bayar); // ← langsung setelah INSERT pembayaran
            
            $query_detail = "SELECT obat_id, jumlah FROM detail_resep WHERE resep_id = '$resep_id'";
            $result_detail = mysqli_query($conn, $query_detail);
            
            $stok_success = true;
            while ($row = mysqli_fetch_assoc($result_detail)) {
                if ($row['obat_id'] && $row['obat_id'] > 0) {
                    $query_update = "UPDATE obat SET stok = stok - {$row['jumlah']} WHERE id = {$row['obat_id']}";
                    if (!mysqli_query($conn, $query_update)) {
                        $stok_success = false;
                        break;
                    }
                }
            }
            
            if ($insert_bayar && $stok_success) {
                
                $jasa_bidan_success = true;
                if ($bulat > 0) {
                    $query_jasa = "INSERT INTO jasa_bidan (pembayaran_id, jumlah) VALUES ('$pembayaran_id', '$bulat')";
                    if (!mysqli_query($conn, $query_jasa)) {
                        $jasa_bidan_success = false;
                    }
                }
                
                if ($jasa_bidan_success) {
                    mysqli_query($conn, "COMMIT");
                    $msg = "Pembayaran berhasil! Kembalian: " . formatRupiah($kembalian);
                    if ($diskon > 0) {
                        $msg .= " (Diskon: " . formatRupiah($diskon) . ")";
                    }
                    if ($bulat > 0) {
                        $msg .= " (Jasa Bidan: " . formatRupiah($bulat) . ")";
                    }
                    $success = $msg;
                    $print_nota_id = $resep_id;
                    $print_nota_type = 'resep';
                } else {
                    mysqli_query($conn, "ROLLBACK");
                    $error = "Gagal menyimpan data jasa bidan!";
                }

            } else {
                mysqli_query($conn, "ROLLBACK");
                $error = "Gagal memproses pembayaran!";
            }
        }

    } else {
        // ===== BLOK BPJS / ASURANSI =====
        $no_kartu = sanitize($_POST['no_kartu']);
        $keterangan = isset($_POST['keterangan']) ? sanitize($_POST['keterangan']) : '';
        
        if ($diskon > 0) {
            $keterangan .= ($keterangan ? ' | ' : '') . 'Diskon: ' . formatRupiah($diskon);
        }
        if ($bulat > 0) {
            $keterangan .= ($keterangan ? ' | ' : '') . 'Jasa Bidan: ' . formatRupiah($bulat);
        }
        $keterangan .= ($keterangan ? ' | ' : '') . 'Harga sudah termasuk markup 35% untuk obat';
        
        mysqli_query($conn, "START TRANSACTION");
        
    $pemeriksaan_id = isset($_POST['pemeriksaan_id']) ? intval($_POST['pemeriksaan_id']) : null;

$query = "INSERT INTO pembayaran (resep_id, pemeriksaan_id, kasir_id, total_bayar, diskon, total_setelah_diskon, metode_bayar, jumlah_bayar, kembalian, keterangan) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt_bayar = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt_bayar, "iiidddsdds", $resep_id, $pemeriksaan_id, $kasir_id, $total_bayar, $diskon, $total_final, $metode_bayar, $jumlah_bayar, $kembalian, $keterangan);

$insert_bayar = mysqli_stmt_execute($stmt_bayar);
$pembayaran_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt_bayar); // ← langsung setelah INSERT pembayaran
        
        $query_detail = "SELECT obat_id, jumlah FROM detail_resep WHERE resep_id = '$resep_id' AND (jenis_item = 'obat' OR obat_id IS NOT NULL)";
        $result_detail = mysqli_query($conn, $query_detail);
        
        $stok_success = true;
        while ($row = mysqli_fetch_assoc($result_detail)) {
            if ($row['obat_id'] && $row['obat_id'] > 0) {
                $query_update = "UPDATE obat SET stok = stok - {$row['jumlah']} WHERE id = {$row['obat_id']}";
                if (!mysqli_query($conn, $query_update)) {
                    $stok_success = false;
                    break;
                }
            }
        }
        
        if ($insert_bayar && $stok_success) {

            $jasa_bidan_success = true;
            if ($bulat > 0) {
                $query_jasa = "INSERT INTO jasa_bidan (pembayaran_id, jumlah) VALUES ('$pembayaran_id', '$bulat')";
                if (!mysqli_query($conn, $query_jasa)) {
                    $jasa_bidan_success = false;
                }
            }

            if ($jasa_bidan_success) {
                mysqli_query($conn, "COMMIT");
                $msg = "Validasi " . strtoupper($jenis_pasien) . " berhasil! No. Kartu: $no_kartu";
                if ($diskon > 0) {
                    $msg .= " (Diskon: " . formatRupiah($diskon) . ")";
                }
                if ($bulat > 0) {
                    $msg .= " (Jasa Bidan: " . formatRupiah($bulat) . ")";
                }
                $success = $msg;
                $print_nota_id = $resep_id;
                $print_nota_type = 'resep';
            } else {
                mysqli_query($conn, "ROLLBACK");
                $error = "Gagal menyimpan data jasa bidan!";
            }

        } else {
            mysqli_query($conn, "ROLLBACK");
            $error = "Gagal memproses validasi!";
        }
    }
}

// BENAR - filter hanya yang lunas di ON, bukan WHERE
$query_belum = "SELECT 
                    pm.id as pemeriksaan_id,
                    pm.diagnosa,
                    pm.status as status_pemeriksaan,
                    p.no_antrian,
                    p.id as pendaftaran_id,
                    ps.nama_lengkap,
                    ps.no_rm,
                    ps.jenis_pasien,
                    ps.no_bpjs,
                    ps.nama_asuransi,
                    ps.no_polis,
                    r.id as resep_id,
                    r.tgl_resep,
                    COALESCE(r.tgl_resep, pm.tgl_pemeriksaan) as waktu_tampil
                FROM pemeriksaan pm
                JOIN pendaftaran p ON pm.pendaftaran_id = p.id
                JOIN pasien ps ON p.pasien_id = ps.id
                LEFT JOIN (
                    SELECT DISTINCT pemeriksaan_id 
                    FROM detail_tindakan 
                    WHERE hapus = 0
                ) dt ON dt.pemeriksaan_id = pm.id
                LEFT JOIN resep r ON r.pemeriksaan_id = pm.id 
                    AND (r.hapus IS NULL OR r.hapus = 0) 
                    AND r.status = 'selesai'
                -- JOIN pembayaran cek dari DUA sisi: resep_id ATAU pemeriksaan_id
                LEFT JOIN pembayaran pb ON pb.status = 'lunas'
                    AND (
                        (pb.resep_id IS NOT NULL AND pb.resep_id = r.id)
                        OR
                        (pb.pemeriksaan_id IS NOT NULL AND pb.pemeriksaan_id = pm.id)
                    )
                WHERE pm.status = 'selesai'
                AND pb.id IS NULL
                ORDER BY waktu_tampil ASC";
$result_belum = mysqli_query($conn, $query_belum);

// Get Pembayaran Hari Ini
// GANTI SELURUH $query_lunas dengan ini:
$query_lunas = "SELECT 
                    pb.*,
                    COALESCE(r.id, 0) as resep_id,
                    p.no_antrian,
                    ps.nama_lengkap,
                    ps.no_rm,
                    ps.jenis_pasien
                FROM pembayaran pb
                LEFT JOIN resep r ON pb.resep_id = r.id
                LEFT JOIN pemeriksaan pm_r ON r.pemeriksaan_id = pm_r.id
                LEFT JOIN pemeriksaan pm_p ON pb.pemeriksaan_id = pm_p.id
                LEFT JOIN pendaftaran p ON p.id = COALESCE(pm_r.pendaftaran_id, pm_p.pendaftaran_id)
                LEFT JOIN pasien ps ON p.pasien_id = ps.id
                WHERE DATE(pb.tgl_bayar) = CURDATE()
                AND pb.status = 'lunas'
                AND p.id IS NOT NULL
                ORDER BY pb.tgl_bayar DESC";
$result_lunas = mysqli_query($conn, $query_lunas);

// Debug sementara - hapus setelah masalah selesai
if (!$result_lunas) {
    die("Query lunas error: " . mysqli_error($conn));
}

// Total Pendapatan                
// Total Pendapatan
$query_total = "SELECT COALESCE(SUM(COALESCE(pb.total_setelah_diskon, pb.total_bayar)), 0) as total 
                FROM pembayaran pb
                WHERE pb.status='lunas' AND DATE(pb.tgl_bayar) = CURDATE() 
                AND pb.metode_bayar IN ('tunai', 'qris', 'transfer')";
$result_total = mysqli_query($conn, $query_total);
$row_total = mysqli_fetch_assoc($result_total);
$total_pendapatan = isset($row_total['total']) ? $row_total['total'] : 0;

// Total Penjualan Langsung
$query_penjualan = "SELECT COALESCE(SUM(COALESCE(total_setelah_diskon, total_bayar)), 0) as total 
                    FROM penjualan_langsung
                    WHERE DATE(tgl_penjualan) = CURDATE()
                    AND status = 'lunas'";  // ← TAMBAH INI
$result_penjualan = mysqli_query($conn, $query_penjualan);
$row_penjualan = mysqli_fetch_assoc($result_penjualan);
$total_penjualan_langsung = isset($row_penjualan['total']) ? $row_penjualan['total'] : 0;

// Get Penjualan Langsung Hari Ini
$query_penjualan_list = "SELECT pl.*, u.nama_lengkap as kasir
                         FROM penjualan_langsung pl
                         JOIN users u ON pl.kasir_id = u.id
                         WHERE DATE(pl.tgl_penjualan) = CURDATE()
                         AND pl.status = 'lunas'     -- ← TAMBAH INI
                         ORDER BY pl.tgl_penjualan DESC";
$result_penjualan_list = mysqli_query($conn, $query_penjualan_list);
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir - Healoka</title>
    <link rel="icon" type="image/png" href="asset/img/logo.png">
    <link rel="shortcut icon" type="image/png" href="asset/img/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <?php include 'sidebar_style.php'; ?>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container-fluid { padding: 0; margin: 0; }
        .row { margin: 0; display: flex; }
        .col-md-2 { flex: 0 0 250px; max-width: 250px; }
        .col-md-10 { flex: 1; margin-left: 250px; padding: 0; }
        .navbar { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white; border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px; font-weight: 600;
        }
        .table thead th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #495057; font-weight: 600; font-size: 0.9em; }
        .badge { padding: 6px 12px; border-radius: 8px; font-weight: 500; }
        .btn { border-radius: 8px; padding: 8px 16px; font-weight: 500; }
        .form-control, .form-select { border-radius: 8px; border: 1px solid #e0e0e0; }
        .pendapatan-card {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white; border-radius: 15px; padding: 25px; margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3);
        }
        .penjualan-card {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white; border-radius: 15px; padding: 25px; margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(245, 158, 11, 0.3);
        }
        .modal-content { border-radius: 15px; }
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white; border-radius: 15px 15px 0 0;
        }
        .obat-detail { background: #f8f9fa; border-radius: 8px; padding: 12px; margin-bottom: 8px; }
        .total-section { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; padding: 20px; margin-top: 15px; }
        .payment-input { font-size: 1.2em; font-weight: 600; padding: 12px; }
        .badge-umum { background-color: #10b981; }
        .badge-bpjs { background-color: #3b82f6; }
        .badge-asuransi { background-color: #f59e0b; }
        .cart-item { background: #f8f9fa; border-radius: 8px; padding: 12px; margin-bottom: 10px; border-left: 4px solid var(--primary-color); }
        .select2-container--bootstrap-5 .select2-selection { min-height: 45px; padding: 8px; }
        .discount-section { background: #fff3cd; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #ffc107; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
            
            <div class="container-fluid px-4">
                <?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" id="alertSuccess" role="alert">
    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<script>
    setTimeout(function() {
        let el = document.getElementById('alertSuccess');
        if (el) {
            el.classList.remove('show');
            el.classList.add('fade');
            setTimeout(() => el.remove(), 500);
        }
    }, 3000);
</script>
                <?php if ($print_nota_id > 0): ?>
                <script>
                    <?php if ($print_nota_type == 'langsung'): ?>
                    window.open('print_nota.php?id=<?php echo $print_nota_id; ?>', '_blank', 'width=800,height=600');
                    <?php elseif ($print_nota_type == 'grosir'): ?>
                    window.open('print_nota_grosir.php?id=<?php echo $print_nota_id; ?>', '_blank', 'width=800,height=600');
                    <?php else: ?>
                    window.open('print_nota_resep.php?id=<?php echo $print_nota_id; ?>', '_blank', 'width=800,height=600');
                    <?php endif; ?>
                </script>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" id="alertError" role="alert">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="d-flex align-items-center m-3">
                        <button type="button" class="btn btn-success me-3" id="btnPenjualanLangsung">
                            <i class="fas fa-cart-plus me-2"></i>Penjualan Langsung
                        </button>
                    </div>
                    <div class="col-md-6">
                        <div class="pendapatan-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 opacity-75">Pendapatan Pasien Umum Hari Ini</h6>
                                    <h2 class="mb-0"><?php echo formatRupiah($total_pendapatan); ?></h2>
                                    <small class="opacity-75"><?php echo date('l, d F Y'); ?></small>
                                </div>
                                <div><i class="fas fa-hospital fa-4x opacity-25"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="penjualan-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 opacity-75">Penjualan Langsung Hari Ini</h6>
                                    <h2 class="mb-0"><?php echo formatRupiah($total_penjualan_langsung); ?></h2>
                                    <small class="opacity-75"><?php echo date('l, d F Y'); ?></small>
                                </div>
                                <div><i class="fas fa-shopping-cart fa-4x opacity-25"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>Belum Validasi / Bayar
                                <span class="badge bg-light text-dark float-end"><?php echo mysqli_num_rows($result_belum); ?></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                    <table class="table table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th width="15%">No. Antrian</th>
                                                <th width="35%">Pasien</th>
                                                <th width="15%">Jenis Penjamin</th>
                                                <th width="15%">Waktu</th>
                                                <th width="20%">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (mysqli_num_rows($result_belum) > 0): ?>
                                                <?php while ($row = mysqli_fetch_assoc($result_belum)): ?>
                                                <tr>
                                                    <td><strong><?php echo $row['no_antrian']; ?></strong></td>
                                                    <td>
                                                        <strong><?php echo $row['nama_lengkap']; ?></strong><br>
                                                        <small class="text-muted"><?php echo $row['no_rm']; ?></small>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badge_jenis = '';
                                                        switch($row['jenis_pasien']) {
                                                            case 'umum': $badge_jenis = 'badge-umum'; break;
                                                            case 'bpjs': $badge_jenis = 'badge-bpjs'; break;
                                                            case 'asuransi': $badge_jenis = 'badge-asuransi'; break;
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badge_jenis; ?>">
                                                            <?php echo strtoupper($row['jenis_pasien']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
    <small>
        <?php echo date('d/m/Y', strtotime($row['waktu_tampil'])); ?><br>
        <?php echo date('H:i', strtotime($row['waktu_tampil'])); ?>
    </small>
</td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary w-100" 
    onclick="prosesBayar(
        <?php echo $row['resep_id'] ?? 0; ?>,
        '<?php echo $row['jenis_pasien']; ?>',
        '<?php echo $row['no_bpjs'] ?? ''; ?>',
        '<?php echo $row['nama_asuransi'] ?? ''; ?>',
        '<?php echo $row['no_polis'] ?? ''; ?>',
        <?php echo $row['pemeriksaan_id']; ?>
    )">
                                                            <i class="fas fa-<?php echo $row['jenis_pasien'] == 'umum' ? 'money-bill' : 'check'; ?> me-1"></i>
                                                            <?php echo $row['jenis_pasien'] == 'umum' ? 'Bayar' : 'Validasi'; ?>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-5">
                                                        <i class="fas fa-check-circle fa-3x mb-3 d-block"></i>
                                                        <p class="mb-0">Semua pembayaran sudah divalidasi</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success">
                                <i class="fas fa-check-circle me-2"></i>Sudah Divalidasi / Bayar Hari Ini
                                <span class="badge bg-light text-dark float-end">
                                    <?php echo mysqli_num_rows($result_lunas) + mysqli_num_rows($result_penjualan_list); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                                    <table class="table table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th width="12%">No/Tipe</th>
                                                <th width="28%">Pasien/Pembeli</th>
                                                <th width="15%">Jenis Penjamin</th>
                                                <th width="18%">Total</th>
                                                <th width="12%">Waktu</th>
                                                <th width="15%">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $has_data = false;
                                            if (mysqli_num_rows($result_lunas) > 0): 
                                                $has_data = true;
                                                while ($row = mysqli_fetch_assoc($result_lunas)): 
                                                    $total_display = isset($row['total_setelah_diskon']) && $row['total_setelah_diskon'] > 0 
                                                        ? $row['total_setelah_diskon'] 
                                                        : $row['total_bayar'];
                                            ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo $row['no_antrian']; ?></strong><br>
                                                        <small class="badge badge-secondary" style="background-color: #6c757d; font-size: 0.7em;">RESEP</small>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo $row['nama_lengkap']; ?></strong><br>
                                                        <small class="text-muted"><?php echo $row['no_rm']; ?></small>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $badge_jenis = '';
                                                        switch($row['jenis_pasien']) {
                                                            case 'umum': $badge_jenis = 'badge-umum'; break;
                                                            case 'bpjs': $badge_jenis = 'badge-bpjs'; break;
                                                            case 'asuransi': $badge_jenis = 'badge-asuransi'; break;
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badge_jenis; ?>">
                                                            <?php echo strtoupper($row['jenis_pasien']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['jenis_pasien'] == 'umum'): ?>
                                                        <span class="badge bg-success"><?php echo formatRupiah($total_display); ?></span>
                                                        <?php if (isset($row['diskon']) && $row['diskon'] > 0): ?>
                                                            <br><small class="text-muted">Diskon: <?php echo formatRupiah($row['diskon']); ?></small>
                                                        <?php endif; ?>
                                                        <?php else: ?>
                                                        <span class="badge bg-info">Ditanggung</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
    <small>
        <?php echo date('d/m/Y', strtotime($row['tgl_bayar'])); ?><br>
        <?php echo date('H:i', strtotime($row['tgl_bayar'])); ?>
    </small>
</td>
                                                    <td>
    <div class="d-flex gap-1">
        <button type="button" class="btn btn-sm btn-info" 
                onclick="printNotaResep(<?php echo $row['resep_id']; ?>)" 
                title="Print Nota">
            <i class="fas fa-print"></i>
        </button>
        <form method="POST" style="display:inline;" 
              onsubmit="return confirm('Batalkan validasi ini? Stok obat akan dikembalikan.')">
            <input type="hidden" name="batal_validasi_resep" value="1">
            <input type="hidden" name="pembayaran_id" value="<?php echo $row['id']; ?>">
            <input type="hidden" name="resep_id_batal" value="<?php echo $row['resep_id']; ?>">
            <button type="submit" class="btn btn-sm btn-danger" title="Batal Validasi">
                <i class="fas fa-times"></i>
            </button>
        </form>
    </div>
</td>
                                                </tr>
                                            <?php 
                                                endwhile; 
                                            endif;
                                            
                                            if (mysqli_num_rows($result_penjualan_list) > 0):
                                                $has_data = true;
                                                while ($row = mysqli_fetch_assoc($result_penjualan_list)):
                                                    $total_display = isset($row['total_setelah_diskon']) && $row['total_setelah_diskon'] > 0 
                                                        ? $row['total_setelah_diskon'] 
                                                        : $row['total_bayar'];
                                            ?>
                                                <tr class="table-warning">
                                                    <td>
                                                        <strong>PL-<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></strong><br>
                                                        <small class="badge badge-warning" style="background-color: #ffc107; color: #000; font-size: 0.7em;">LANGSUNG</small>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo $row['nama_pembeli']; ?></strong><br>
                                                        <small class="text-muted">Kasir: <?php echo $row['kasir']; ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning text-dark">
                                                            <?php echo strtoupper($row['metode_bayar']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success"><?php echo formatRupiah($total_display); ?></span>
                                                        <?php if (isset($row['diskon']) && $row['diskon'] > 0): ?>
                                                            <br><small class="text-muted">Diskon: <?php echo formatRupiah($row['diskon']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
    <small>
        <?php echo date('d/m/Y', strtotime($row['tgl_penjualan'])); ?><br>
        <?php echo date('H:i', strtotime($row['tgl_penjualan'])); ?>
    </small>
</td>
                                                    <td>
    <div class="d-flex gap-1">
        <button type="button" class="btn btn-sm btn-info" 
                onclick="printNota(<?php echo $row['id']; ?>)" 
                title="Print Nota">
            <i class="fas fa-print"></i>
        </button>
        <form method="POST" style="display:inline;" 
              onsubmit="return confirm('Batalkan penjualan ini? Stok obat akan dikembalikan.')">
            <input type="hidden" name="batal_penjualan_langsung" value="1">
            <input type="hidden" name="penjualan_langsung_id" value="<?php echo $row['id']; ?>">
            <button type="submit" class="btn btn-sm btn-danger" title="Batal Penjualan">
                <i class="fas fa-times"></i>
            </button>
        </form>
    </div>
</td>
                                                </tr>
                                            <?php 
                                                endwhile;
                                            endif;
                                            
                                            if (!$has_data):
                                            ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-5">
                                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                        <p class="mb-0">Belum ada transaksi hari ini</p>
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
        </div>
    </div>
    </div>

<!-- ===================== MODAL PEMBAYARAN RESEP ===================== -->
<div class="modal fade" id="modalPembayaran" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-cash-register me-2"></i>Proses <span id="title_jenis">Pembayaran</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formPembayaran">
                <div class="modal-body" id="pembayaranContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================== MODAL PENJUALAN LANGSUNG ===================== -->
<div class="modal fade" id="modalPenjualanLangsung" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-cart-plus me-2"></i>Penjualan Langsung</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formPenjualanLangsung">
                <input type="hidden" name="penjualan_langsung" value="1">
                <input type="hidden" name="obat_items" id="obat_items">
                <input type="hidden" name="diskon_langsung" id="diskon_langsung" value="0">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nama Pembeli</label>
                                <input type="text" class="form-control" name="nama_pembeli" id="nama_pembeli" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Pilih Obat</label>
                                <select class="form-select" id="select_obat" style="width: 100%">
                                    <option value="">-- Pilih Obat --</option>
                                </select>
                            </div>
                            <div class="row" id="form_tambah_obat" style="display: none;">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Jumlah</label>
                                        <input type="number" class="form-control" id="jumlah_obat" min="1" value="1">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Harga Satuan</label>
                                        <input type="text" class="form-control" id="harga_obat" readonly>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <button type="button" class="btn btn-primary w-100" onclick="tambahKeKeranjang()">
                                        <i class="fas fa-plus me-2"></i>Tambah ke Keranjang
                                    </button>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Stok Tersedia:</strong> <span id="stok_obat">-</span>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <h6 class="fw-bold mb-3">Keranjang Belanja</h6>
                            <div id="cart_items" style="max-height: 300px; overflow-y: auto; min-height: 200px;">
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-shopping-cart fa-3x mb-2"></i>
                                    <p>Keranjang masih kosong</p>
                                </div>
                            </div>
                            <div class="total-section mt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <strong>Subtotal:</strong>
                                    <strong id="subtotal_belanja">Rp 0</strong>
                                </div>
                                <div class="discount-section">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="apply_discount_langsung" onchange="toggleDiscount('langsung')">
                                        <label class="form-check-label fw-bold" for="apply_discount_langsung">
                                            <i class="fas fa-tag me-1"></i> Berikan Diskon
                                        </label>
                                    </div>
                                    <div id="discount_input_langsung" style="display: none;">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <select class="form-select form-select-sm" id="discount_type_langsung" onchange="hitungTotalLangsung()">
                                                    <option value="persen">%</option>
                                                    <option value="nominal">Rp</option>
                                                </select>
                                            </div>
                                            <div class="col-md-8">
                                                <input type="number" class="form-control form-control-sm" id="discount_amount_langsung" min="0" value="0" onkeyup="hitungTotalLangsung()" placeholder="Nilai diskon">
                                            </div>
                                        </div>
                                        <small class="text-muted" id="discount_info_langsung"></small>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <strong class="text-success">Total Belanja:</strong>
                                    <strong class="text-success" id="total_belanja">Rp 0</strong>
                                </div>
                                <input type="hidden" name="total_bayar_langsung" id="total_bayar_langsung" value="0">
                                <hr>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Metode Pembayaran</label>
                                    <select class="form-select" name="metode_bayar_langsung" id="metode_bayar_langsung" required>
                                        <option value="tunai">Tunai</option>
                                        <option value="qris">QRIS</option>
                                        <option value="transfer">Transfer</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Jumlah Bayar</label>
                                    <input type="number" class="form-control payment-input" name="jumlah_bayar_langsung" id="jumlah_bayar_langsung" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Kembalian</label>
                                    <div class="form-control bg-light" style="font-size: 1.2em; font-weight: 600;">
                                        <span id="kembalian_langsung">Rp 0</span>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success w-100 btn-lg" id="btn_bayar_langsung" disabled>
                                    <i class="fas fa-check-circle me-2"></i>Proses Pembayaran
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================== MODAL PENJUALAN GROSIR ===================== -->
<div class="modal fade" id="modalPenjualanGrosir" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: white;">
                <h5 class="modal-title"><i class="fas fa-boxes me-2"></i>Penjualan Grosir (Harga Beli + 15%)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formPenjualanGrosir">
                <input type="hidden" name="penjualan_grosir" value="1">
                <input type="hidden" name="obat_items_grosir" id="obat_items_grosir">
                <input type="hidden" name="diskon_grosir" id="diskon_grosir" value="0">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Pilih Mitra Apotek <span class="text-danger">*</span></label>
                                <select class="form-select" name="mitra_id" id="select_mitra" style="width: 100%" required>
                                    <option value="">-- Pilih Mitra --</option>
                                </select>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Harga Grosir:</strong> Harga Beli + 15% (lebih murah dari penjualan langsung)
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Pilih Obat</label>
                                <select class="form-select" id="select_obat_grosir" style="width: 100%">
                                    <option value="">-- Pilih Obat --</option>
                                </select>
                            </div>
                            <div class="row" id="form_tambah_obat_grosir" style="display: none;">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Jumlah</label>
                                        <input type="number" class="form-control" id="jumlah_obat_grosir" min="1" value="1">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Harga Beli</label>
                                        <input type="text" class="form-control" id="harga_beli_display" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Harga Jual (+15%)</label>
                                        <input type="text" class="form-control" id="harga_jual_grosir_display" readonly>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <button type="button" class="btn btn-primary w-100" onclick="tambahKeKeranjangGrosir()">
                                        <i class="fas fa-plus me-2"></i>Tambah ke Keranjang
                                    </button>
                                </div>
                            </div>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-warehouse me-2"></i>
                                <strong>Stok Tersedia:</strong> <span id="stok_obat_grosir">-</span>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <h6 class="fw-bold mb-3">Keranjang Belanja</h6>
                            <div id="cart_items_grosir" style="max-height: 300px; overflow-y: auto; min-height: 200px;">
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-shopping-cart fa-3x mb-2"></i>
                                    <p>Keranjang masih kosong</p>
                                </div>
                            </div>
                            <div class="total-section mt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <strong>Subtotal:</strong>
                                    <strong id="subtotal_belanja_grosir">Rp 0</strong>
                                </div>
                                <div class="discount-section">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="apply_discount_grosir" onchange="toggleDiscount('grosir')">
                                        <label class="form-check-label fw-bold" for="apply_discount_grosir">
                                            <i class="fas fa-tag me-1"></i> Berikan Diskon
                                        </label>
                                    </div>
                                    <div id="discount_input_grosir" style="display: none;">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <select class="form-select form-select-sm" id="discount_type_grosir" onchange="hitungTotalGrosir()">
                                                    <option value="persen">%</option>
                                                    <option value="nominal">Rp</option>
                                                </select>
                                            </div>
                                            <div class="col-md-8">
                                                <input type="number" class="form-control form-control-sm" id="discount_amount_grosir" min="0" value="0" onkeyup="hitungTotalGrosir()" placeholder="Nilai diskon">
                                            </div>
                                        </div>
                                        <small class="text-muted" id="discount_info_grosir"></small>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <strong class="text-success" style="font-size: 1.2em;">Total:</strong>
                                    <strong class="text-success" style="font-size: 1.2em;" id="total_belanja_grosir">Rp 0</strong>
                                </div>
                                <input type="hidden" name="total_bayar_grosir" id="total_bayar_grosir" value="0">
                                <hr>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Metode Pembayaran</label>
                                    <select class="form-select" name="metode_bayar_grosir" id="metode_bayar_grosir" required>
                                        <option value="tunai">Tunai</option>
                                        <option value="transfer">Transfer</option>
                                        <option value="hutang">Hutang</option>
                                    </select>
                                </div>
                                <div id="jatuh_tempo_section" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Jatuh Tempo</label>
                                        <input type="date" class="form-control" name="jatuh_tempo" id="jatuh_tempo">
                                    </div>
                                </div>
                                <div id="pembayaran_section_grosir">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Jumlah Bayar</label>
                                        <input type="number" class="form-control payment-input" name="jumlah_bayar_grosir" id="jumlah_bayar_grosir" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Kembalian</label>
                                        <div class="form-control bg-light" style="font-size: 1.1em; font-weight: 600;">
                                            <span id="kembalian_grosir">Rp 0</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Keterangan</label>
                                    <textarea class="form-control" name="keterangan_grosir" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success w-100 btn-lg" id="btn_simpan_grosir" disabled>
                                    <i class="fas fa-check-circle me-2"></i>Proses Transaksi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================== SCRIPTS (urutan benar, jQuery sekali saja) ===================== -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // ===================== STATE =====================
    let cart = [];
    let obatData = {};
    let cartGrosir = [];
    let obatDataGrosir = {};

    // ===================== READY (satu blok saja) =====================
    $(document).ready(function () {

        // ---- Tombol buka modal ----
        $('#btnPenjualanLangsung').on('click', function () {
            penjualanLangsung();
        });
        $('#btnPenjualanGrosir').on('click', function () {
            penjualanGrosir();
        });

        // ---- Select2 Penjualan Langsung ----
        $('#select_obat').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#modalPenjualanLangsung'),
            placeholder: '-- Pilih Obat --',
            width: '100%'
        });

        $('#select_obat').on('change', function () {
            let obat_id = $(this).val();
            if (obat_id) {
                let obat = obatData.find(o => o.id == obat_id);
                if (obat) {
                    $('#form_tambah_obat').show();
                    $('#harga_obat').val(formatRupiah(obat.harga));
                    $('#stok_obat').text(obat.stok + ' ' + obat.satuan);
                    $('#jumlah_obat').val(1).attr('max', obat.stok);
                }
            } else {
                $('#form_tambah_obat').hide();
                $('#stok_obat').text('-');
            }
        });

        $('#jumlah_bayar_langsung').on('input', function () {
            hitungKembalianLangsung();
        });

        $('#metode_bayar_langsung').on('change', function () {
            if ($(this).val() != 'tunai') {
                let total = parseFloat($('#total_bayar_langsung').val()) || 0;
                $('#jumlah_bayar_langsung').val(total).attr('readonly', true);
                hitungKembalianLangsung();
            } else {
                $('#jumlah_bayar_langsung').val('').attr('readonly', false).focus();
            }
        });

        // ---- Select2 Penjualan Grosir ----
        $('#select_mitra, #select_obat_grosir').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#modalPenjualanGrosir'),
            width: '100%'
        });

        $('#select_obat_grosir').on('change', function () {
            let obat_id = $(this).val();
            if (obat_id) {
                let obat = obatDataGrosir.find(o => o.id == obat_id);
                if (obat) {
                    let harga_beli = parseFloat(obat.harga_beli);
                    let harga_jual = harga_beli * 1.15;
                    $('#form_tambah_obat_grosir').show();
                    $('#harga_beli_display').val(formatRupiah(harga_beli));
                    $('#harga_jual_grosir_display').val(formatRupiah(harga_jual));
                    $('#stok_obat_grosir').text(obat.stok + ' ' + obat.satuan);
                    $('#jumlah_obat_grosir').val(1).attr('max', obat.stok);
                }
            } else {
                $('#form_tambah_obat_grosir').hide();
                $('#stok_obat_grosir').text('-');
            }
        });

        $('#metode_bayar_grosir').on('change', function () {
            if ($(this).val() == 'hutang') {
                $('#jatuh_tempo_section').show();
                $('#pembayaran_section_grosir').hide();
                $('#jatuh_tempo').attr('required', true);
                $('#jumlah_bayar_grosir').attr('required', false).val(0);
            } else {
                $('#jatuh_tempo_section').hide();
                $('#pembayaran_section_grosir').show();
                $('#jatuh_tempo').attr('required', false);
                $('#jumlah_bayar_grosir').attr('required', true);
                if ($(this).val() != 'tunai') {
                    let total = parseFloat($('#total_bayar_grosir').val()) || 0;
                    $('#jumlah_bayar_grosir').val(total);
                    hitungKembalianGrosir();
                }
            }
        });

        $('#jumlah_bayar_grosir').on('input', function () {
            hitungKembalianGrosir();
        });

        // ---- Pembayaran resep: init setelah AJAX load ----
        $(document).on('input', '#jumlah_bayar', function () {
            hitungKembalian();
        });
        $(document).on('change', '#metode_bayar', function () {
            if ($(this).val() != 'tunai') {
                let total = parseFloat($('#total_setelah_diskon').val()) || 0;
                $('#jumlah_bayar').val(total).attr('readonly', true);
                hitungKembalian();
            } else {
                $('#jumlah_bayar').val('').attr('readonly', false).focus();
            }
        });
    });

    // ===================== FUNGSI UMUM =====================
    function formatRupiah(angka) {
        let number = Math.round(angka);
        return 'Rp ' + number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function printNota(penjualan_id) {
        window.open('print_nota.php?id=' + penjualan_id, '_blank', 'width=800,height=600');
    }

    function printNotaResep(resep_id) {
        window.open('print_nota_resep.php?id=' + resep_id, '_blank', 'width=800,height=600');
    }

    function toggleDiscount(type) {
        if (type === 'resep') {
            if ($('#apply_discount').is(':checked')) {
                $('#discount_input').show();

        $('#discount_amount').val('');

                $('#discount_amount').focus();
            } else {
                $('#discount_input').hide();
                $('#discount_amount').val(0);
                hitungTotal();
            }
        } else if (type === 'langsung') {
            if ($('#apply_discount_langsung').is(':checked')) {
                $('#discount_input_langsung').show();
                $('#discount_amount_langsung').val('');
                $('#discount_amount_langsung').focus();
            } else {
                $('#discount_input_langsung').hide();
                $('#discount_amount_langsung').val(0);
                hitungTotalLangsung();
            }
        } else if (type === 'grosir') {
            if ($('#apply_discount_grosir').is(':checked')) {
                $('#discount_input_grosir').show();
                $('#discount_amount_grosir').val('');
                $('#discount_amount_grosir').focus();
            } else {
                $('#discount_input_grosir').hide();
                $('#discount_amount_grosir').val(0);
                hitungTotalGrosir();
            }
        }
    }
    
    function togglePembulatan() {
    if ($('#apply_pembulatan').is(':checked')) {
        $('#pembulatan_input').show();
                // kosongkan nilai biar tidak ada 0
        $('#pembulatan_nominal').val('');

        $('#pembulatan_nominal').focus();
    } else {
        $('#pembulatan_input').hide();
        $('#pembulatan_nominal').val(0);
        pembulatan();
    }
}

    // ===================== FUNGSI RESEP =====================
    function prosesBayar(resep_id, jenis_pasien, no_bpjs, nama_asuransi, no_polis, pemeriksaan_id) {
    $.ajax({
        url: 'ajax/get_pembayaran.php',
        method: 'POST',
        data: { resep_id, jenis_pasien, no_bpjs, nama_asuransi, no_polis, pemeriksaan_id },
            success: function (response) {
                $('#pembayaranContent').html(response);
                $('#title_jenis').text(jenis_pasien == 'umum' ? 'Pembayaran' : 'Validasi ' + jenis_pasien.toUpperCase());
                $('#modalPembayaran').modal('show');
            },
            error: function () { alert('Gagal memuat data'); }
        });
    }

    function hitungTotal() {
        let total = parseFloat($('#total_bayar').val()) || 0;
        let persenDiskon = parseFloat($('#discount_amount').val()) || 0;
        if (persenDiskon > 100) { persenDiskon = 100; $('#discount_amount').val(persenDiskon); }
        let diskon = total * (persenDiskon / 100);
        let totalSetelahDiskon = total - diskon;
        $('#diskon').val(diskon);
        $('#total_setelah_diskon').val(totalSetelahDiskon);
        $('#total_display').text(formatRupiah(totalSetelahDiskon));
        if (persenDiskon > 0) {
            $('#discount_display').html('<small class="text-warning">Diskon ' + persenDiskon + '% (' + formatRupiah(diskon) + ')</small>');
        } else {
            $('#discount_display').html('');
        }
        hitungKembalian();
    }

    // SESUDAH - pakai total terbesar antara diskon dan bulat
function hitungKembalian() {
    let totalDiskon = parseFloat($('#total_setelah_diskon').val()) || 0;
    let totalBulat  = parseFloat($('#total_setelah_bulat').val()) || 0;
    
    // Pakai yang lebih besar (karena jasa bidan menambah total)
    let total = (totalBulat > totalDiskon) ? totalBulat : totalDiskon;
    
    let bayar = parseFloat($('#jumlah_bayar').val()) || 0;
    let kembalian = bayar - total;
    
    if (kembalian >= 0) {
        $('#kembalian').val(kembalian.toFixed(0));
        $('#kembalian_text').text(formatRupiah(kembalian)).removeClass('text-danger').addClass('text-success');
    } else {
        $('#kembalian').val(0);
        $('#kembalian_text').text('Kurang: ' + formatRupiah(Math.abs(kembalian))).removeClass('text-success').addClass('text-danger');
    }
}

    // ===================== FUNGSI PENJUALAN LANGSUNG =====================
    function penjualanLangsung() {
        cart = [];
        updateCart();
        $('#nama_pembeli').val('');
        $('#apply_discount_langsung').prop('checked', false);
        $('#discount_input_langsung').hide();
        $('#discount_amount_langsung').val(0);
        $('#discount_type_langsung').val('persen');

        $.ajax({
            url: 'ajax/get_obat_penjualan.php',
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                obatData = response;
                let options = '<option value="">-- Pilih Obat --</option>';
                response.forEach(function (obat) {
                    options += `<option value="${obat.id}">[${obat.kode}] ${obat.nama} - Stok: ${obat.stok} ${obat.satuan} - ${formatRupiah(obat.harga)}</option>`;
                });
                $('#select_obat').html(options).val('').trigger('change');
                $('#modalPenjualanLangsung').modal('show');
            },
            error: function () { alert('Gagal memuat data obat'); }
        });
    }

    function tambahKeKeranjang() {
        let obat_id = $('#select_obat').val();
        let jumlah = parseInt($('#jumlah_obat').val());
        if (!obat_id) { alert('Pilih obat terlebih dahulu'); return; }
        let obat = obatData.find(o => o.id == obat_id);
        if (jumlah > obat.stok) { alert('Jumlah melebihi stok tersedia!'); return; }
        let existingIndex = cart.findIndex(item => item.obat_id == obat_id);
        if (existingIndex >= 0) {
            cart[existingIndex].jumlah += jumlah;
            cart[existingIndex].subtotal = cart[existingIndex].jumlah * cart[existingIndex].harga;
        } else {
            cart.push({ obat_id: obat.id, nama: obat.nama, jumlah, harga: parseFloat(obat.harga), satuan: obat.satuan, subtotal: jumlah * parseFloat(obat.harga) });
        }
        updateCart();
        $('#select_obat').val('').trigger('change');
        $('#form_tambah_obat').hide();
    }

    function updateCart() {
        let html = '';
        let total = 0;
        if (cart.length === 0) {
            html = `<div class="text-center text-muted py-5"><i class="fas fa-shopping-cart fa-3x mb-2"></i><p>Keranjang masih kosong</p></div>`;
            $('#btn_bayar_langsung').prop('disabled', true);
        } else {
            cart.forEach((item, index) => {
                total += item.subtotal;
                html += `<div class="cart-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <strong>${item.nama}</strong><br>
                            <small class="text-muted">${item.jumlah} ${item.satuan} x ${formatRupiah(item.harga)}</small>
                        </div>
                        <div class="text-end">
                            <strong class="text-success">${formatRupiah(item.subtotal)}</strong><br>
                            <button type="button" class="btn btn-sm btn-danger" onclick="hapusDariCart(${index})"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>`;
            });
            $('#btn_bayar_langsung').prop('disabled', false);
        }
        $('#cart_items').html(html);
        $('#subtotal_belanja').text(formatRupiah(total));
        $('#obat_items').val(JSON.stringify(cart));
        hitungTotalLangsung();
    }

    function hitungTotalLangsung() {
        let subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
        let discountType = $('#discount_type_langsung').val();
        let discountValue = parseFloat($('#discount_amount_langsung').val()) || 0;
        let diskon = 0;
        if (discountType === 'persen') {
            if (discountValue > 100) { discountValue = 100; $('#discount_amount_langsung').val(100); }
            diskon = subtotal * (discountValue / 100);
            $('#discount_info_langsung').text(discountValue + '% = ' + formatRupiah(diskon));
        } else {
            if (discountValue > subtotal) { discountValue = subtotal; $('#discount_amount_langsung').val(subtotal); }
            diskon = discountValue;
            $('#discount_info_langsung').text('Diskon: ' + formatRupiah(diskon));
        }
        let total = subtotal - diskon;
        $('#total_belanja').text(formatRupiah(total));
        $('#total_bayar_langsung').val(total);
        $('#diskon_langsung').val(diskon);
        hitungKembalianLangsung();
    }

    function hitungKembalianLangsung() {
        let total = parseFloat($('#total_bayar_langsung').val()) || 0;
        let bayar = parseFloat($('#jumlah_bayar_langsung').val()) || 0;
        let kembalian = bayar - total;
        if (kembalian >= 0) {
            $('#kembalian_langsung').text(formatRupiah(kembalian)).removeClass('text-danger').addClass('text-success');
        } else {
            $('#kembalian_langsung').text('Kurang: ' + formatRupiah(Math.abs(kembalian))).removeClass('text-success').addClass('text-danger');
        }
    }

    function hapusDariCart(index) {
        cart.splice(index, 1);
        updateCart();
    }

    // ===================== FUNGSI PENJUALAN GROSIR =====================
    function penjualanGrosir() {
        cartGrosir = [];
        updateCartGrosir();
        $('#apply_discount_grosir').prop('checked', false);
        $('#discount_input_grosir').hide();
        $('#discount_amount_grosir').val(0);
        $('#discount_type_grosir').val('persen');
        $('#metode_bayar_grosir').val('tunai').trigger('change');

        $.ajax({
            url: 'ajax/get_mitra_aktif.php',
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                let options = '<option value="">-- Pilih Mitra --</option>';
                response.forEach(function (mitra) {
                    options += `<option value="${mitra.id}">[${mitra.kode_mitra}] ${mitra.nama_apotek}</option>`;
                });
                $('#select_mitra').html(options).val('').trigger('change');
            },
            error: function () { alert('Gagal memuat data mitra.'); }
        });

        $.ajax({
            url: 'ajax/get_obat_grosir.php',
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                obatDataGrosir = response;
                let options = '<option value="">-- Pilih Obat --</option>';
                response.forEach(function (obat) {
                    let hargaGrosir = parseFloat(obat.harga_beli) * 1.15;
                    options += `<option value="${obat.id}">[${obat.kode}] ${obat.nama} - Stok: ${obat.stok} ${obat.satuan} - ${formatRupiah(hargaGrosir)}</option>`;
                });
                $('#select_obat_grosir').html(options).val('').trigger('change');
                $('#modalPenjualanGrosir').modal('show');
            },
            error: function () { alert('Gagal memuat data obat'); }
        });
    }

    function tambahKeKeranjangGrosir() {
        let obat_id = $('#select_obat_grosir').val();
        let jumlah = parseInt($('#jumlah_obat_grosir').val());
        if (!$('#select_mitra').val()) { alert('Pilih mitra terlebih dahulu!'); return; }
        if (!obat_id) { alert('Pilih obat terlebih dahulu!'); return; }
        let obat = obatDataGrosir.find(o => o.id == obat_id);
        if (jumlah > obat.stok) { alert('Jumlah melebihi stok tersedia!'); return; }
        let harga_beli = parseFloat(obat.harga_beli);
        let harga_jual = harga_beli * 1.15;
        let existingIndex = cartGrosir.findIndex(item => item.obat_id == obat_id);
        if (existingIndex >= 0) {
            cartGrosir[existingIndex].jumlah += jumlah;
            cartGrosir[existingIndex].subtotal = cartGrosir[existingIndex].jumlah * harga_jual;
        } else {
            cartGrosir.push({ obat_id: obat.id, nama: obat.nama, jumlah, harga_beli, harga_jual, satuan: obat.satuan, subtotal: jumlah * harga_jual });
        }
        updateCartGrosir();
        $('#select_obat_grosir').val('').trigger('change');
        $('#form_tambah_obat_grosir').hide();
    }

    function updateCartGrosir() {
        let html = '';
        let total = 0;
        if (cartGrosir.length === 0) {
            html = `<div class="text-center text-muted py-5"><i class="fas fa-shopping-cart fa-3x mb-2"></i><p>Keranjang masih kosong</p></div>`;
            $('#btn_simpan_grosir').prop('disabled', true);
        } else {
            cartGrosir.forEach((item, index) => {
                total += item.subtotal;
                html += `<div class="cart-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <strong>${item.nama}</strong><br>
                            <small class="text-muted">${item.jumlah} ${item.satuan} x ${formatRupiah(item.harga_jual)}</small><br>
                            <small class="text-info">Modal: ${formatRupiah(item.harga_beli)}</small>
                        </div>
                        <div class="text-end">
                            <strong class="text-success">${formatRupiah(item.subtotal)}</strong><br>
                            <button type="button" class="btn btn-sm btn-danger" onclick="hapusDariCartGrosir(${index})"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>`;
            });
            $('#btn_simpan_grosir').prop('disabled', false);
        }
        $('#cart_items_grosir').html(html);
        $('#subtotal_belanja_grosir').text(formatRupiah(total));
        $('#obat_items_grosir').val(JSON.stringify(cartGrosir));
        hitungTotalGrosir();
    }

    function hitungTotalGrosir() {
        let subtotal = cartGrosir.reduce((sum, item) => sum + item.subtotal, 0);
        let discountType = $('#discount_type_grosir').val();
        let discountValue = parseFloat($('#discount_amount_grosir').val()) || 0;
        let diskon = 0;
        if (discountType === 'persen') {
            if (discountValue > 100) { discountValue = 100; $('#discount_amount_grosir').val(100); }
            diskon = subtotal * (discountValue / 100);
            $('#discount_info_grosir').text(discountValue + '% = ' + formatRupiah(diskon));
        } else {
            if (discountValue > subtotal) { discountValue = subtotal; $('#discount_amount_grosir').val(subtotal); }
            diskon = discountValue;
            $('#discount_info_grosir').text('Diskon: ' + formatRupiah(diskon));
        }
        let total = subtotal - diskon;
        $('#total_belanja_grosir').text(formatRupiah(total));
        $('#total_bayar_grosir').val(total);
        $('#diskon_grosir').val(diskon);
        hitungKembalianGrosir();
    }

    function hitungKembalianGrosir() {
        let total = parseFloat($('#total_bayar_grosir').val()) || 0;
        let bayar = parseFloat($('#jumlah_bayar_grosir').val()) || 0;
        let kembalian = bayar - total;
        if (kembalian >= 0) {
            $('#kembalian_grosir').text(formatRupiah(kembalian)).removeClass('text-danger').addClass('text-success');
        } else {
            $('#kembalian_grosir').text('Kurang: ' + formatRupiah(Math.abs(kembalian))).removeClass('text-success').addClass('text-danger');
        }
    }

    function hapusDariCartGrosir(index) {
        cartGrosir.splice(index, 1);
        updateCartGrosir();
    }
        setTimeout(function() {
        let el = document.getElementById('alertError');
        if (el) {
            el.classList.remove('show');
            el.classList.add('fade');
            setTimeout(() => el.remove(), 500);
        }
    }, 3000);
</script>
</body>
</html>