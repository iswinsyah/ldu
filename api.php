<?php
// =======================================================
// API LUMBUNG DANA UMAT - VERSI BERSIH & FINAL
// =======================================================
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Jawab sinyal Preflight dari browser (CORS) WAJIB UNTUK WEB LIVE
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
mysqli_report(MYSQLI_REPORT_OFF);

$TOKEN_RAHASIA = "LduBerkah999!";

// 1. PENDETEKSI JENIS PAKET (JSON vs FORM DATA)
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
$data = [];
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents("php://input"), true);
} else {
    $data = $_POST; // Tangkap dari FormData (admin.html upload)
}

// 2. VERIFIKASI KEAMANAN
if (!isset($data['token']) || $data['token'] !== $TOKEN_RAHASIA) {
    die(json_encode(["status" => "error", "message" => "Akses Ditolak! Kunci rahasia salah."]));
}

// 3. KREDENSIAL DATABASE HOSTINGER
$host = "localhost";
$user = "u829486010_ldu";
$pass = "LDUKotaBatu2026!";
$dbname = "u829486010_ldu";

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        die(json_encode(["status" => "error", "message" => "Koneksi Hostinger Gagal: " . $conn->connect_error]));
    }
    
    // 4. JALUR KHUSUS: UPLOAD GAMBAR FISIK
    if (isset($data['action']) && $data['action'] === 'upload_media') {
        if (!isset($_FILES['file'])) {
            die(json_encode(["status" => "error", "message" => "Tidak ada file fisik yang diterima server."]));
        }
        
        $file = $_FILES['file'];
        $file_error = $file['error'];
        
        // Pengecekan error native PHP
        if ($file_error !== 0) {
            die(json_encode(["status" => "error", "message" => "Error upload PHP kode: " . $file_error]));
        }

        $original_name = $file['name'];
        // Terapkan strtolower seperti di kode Bos
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        // Batasi ekstensi (keamanan)
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_ext, $allowed_ext)) {
            die(json_encode(["status" => "error", "message" => "Ekstensi file tidak diizinkan."]));
        }

        $new_name = uniqid('media_') . '.' . $file_ext;
        
        $upload_dir = __DIR__ . '/uploads/';
        // Gunakan file_exists seperti di kode Bos
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                die(json_encode(["status" => "error", "message" => "Gagal membuat folder: " . $upload_dir]));
            }
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $base_url = rtrim(dirname($_SERVER['PHP_SELF']), '/');
            $file_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $base_url . "/uploads/" . $new_name;
            
            $stmt = $conn->prepare("INSERT INTO data_media (waktu, nama_file, url) VALUES (NOW(), ?, ?)");
            if (!$stmt) {
                die(json_encode(["status" => "error", "message" => "SQL Gagal: " . $conn->error]));
            }
            $stmt->bind_param("ss", $original_name, $file_url);
            if (!$stmt->execute()) {
                die(json_encode(["status" => "error", "message" => "Simpan DB Gagal: " . $stmt->error]));
            }
            $stmt->close();
            
            die(json_encode(["status" => "success", "url" => $file_url]));
        } else {
            die(json_encode(["status" => "error", "message" => "Server gagal memindahkan file."]));
        }
    }

    // =======================================================
    // 4.1 JALUR KHUSUS: TRACKING VISITOR (MATA LDU)
    // =======================================================
    if (isset($data['action']) && $data['action'] === 'track_visitor') {
        // Otomatis buat tabel jika belum ada
        $conn->query("CREATE TABLE IF NOT EXISTS `data_trafik` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `waktu` DATETIME,
            `halaman` VARCHAR(255),
            `ip` VARCHAR(100),
            `kota` VARCHAR(100),
            `negara` VARCHAR(100),
            `browser` VARCHAR(100),
            `os` VARCHAR(100),
            `perangkat` VARCHAR(100),
            `sumber` VARCHAR(255),
            `keyword` VARCHAR(255)
        )");

        $stmt = $conn->prepare("INSERT INTO data_trafik (waktu, halaman, ip, kota, negara, browser, os, perangkat, sumber, keyword) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $h = $data['halaman'] ?? ''; $i = $data['ip'] ?? ''; $k = $data['kota'] ?? ''; $n = $data['negara'] ?? '';
            $b = $data['browser'] ?? ''; $o = $data['os'] ?? ''; $p = $data['perangkat'] ?? ''; 
            $s = $data['sumber'] ?? ''; $kw = $data['keyword'] ?? '';
            
            $stmt->bind_param("sssssssss", $h, $i, $k, $n, $b, $o, $p, $s, $kw);
            $stmt->execute();
            $stmt->close();
        }
        die(json_encode(["status" => "success"]));
    }

    // =======================================================
    // 4.2 JALUR KHUSUS: DASHBOARD STATISTIK
    // =======================================================
    if (isset($data['action']) && $data['action'] === 'get_stats') {
        $stats = [];
        
        // 1 & 2. Data Kunjungan dan IP Unik
        $stats['kunjungan']['harian'] = $conn->query("SELECT COUNT(*) FROM data_trafik WHERE DATE(waktu) = CURDATE()")->fetch_row()[0] ?? 0;
        $stats['kunjungan']['pekanan'] = $conn->query("SELECT COUNT(*) FROM data_trafik WHERE YEARWEEK(waktu, 1) = YEARWEEK(CURDATE(), 1)")->fetch_row()[0] ?? 0;
        $stats['kunjungan']['bulanan'] = $conn->query("SELECT COUNT(*) FROM data_trafik WHERE MONTH(waktu) = MONTH(CURDATE()) AND YEAR(waktu) = YEAR(CURDATE())")->fetch_row()[0] ?? 0;
        $stats['kunjungan']['tahunan'] = $conn->query("SELECT COUNT(*) FROM data_trafik WHERE YEAR(waktu) = YEAR(CURDATE())")->fetch_row()[0] ?? 0;
        $stats['kunjungan']['total'] = $conn->query("SELECT COUNT(*) FROM data_trafik")->fetch_row()[0] ?? 0;
        $stats['ip_unik'] = $conn->query("SELECT COUNT(DISTINCT ip) FROM data_trafik")->fetch_row()[0] ?? 0;
        
        // Chart Tren Kunjungan Harian (7 Hari Terakhir)
        $res = $conn->query("SELECT DATE(waktu) as tgl, COUNT(*) as jml FROM data_trafik WHERE waktu >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(waktu) ORDER BY tgl ASC");
        $labels = []; $counts = []; if ($res) { while($r = $res->fetch_assoc()) { $labels[] = $r['tgl']; $counts[] = $r['jml']; } }
        $stats['chart_traffic'] = ['labels' => $labels, 'data' => $counts];
        
        // 3. Asal Pengunjung (Kota)
        $res = $conn->query("SELECT kota, COUNT(*) as jml FROM data_trafik GROUP BY kota ORDER BY jml DESC LIMIT 6");
        $kota = []; if($res) { while($r = $res->fetch_assoc()) { $kota[] = $r; } }
        $stats['asal_kota'] = $kota;
        
        // 4 & 5 & 6. Browser, OS, dan Sumber Trafik
        $res = $conn->query("SELECT browser, COUNT(*) as jml FROM data_trafik GROUP BY browser");
        $l = []; $d = []; if($res) { while($r = $res->fetch_assoc()) { $l[] = $r['browser']; $d[] = $r['jml']; } } $stats['chart_browser'] = ['labels' => $l, 'data' => $d];
        $res = $conn->query("SELECT os, COUNT(*) as jml FROM data_trafik GROUP BY os");
        $l = []; $d = []; if($res) { while($r = $res->fetch_assoc()) { $l[] = $r['os']; $d[] = $r['jml']; } } $stats['chart_os'] = ['labels' => $l, 'data' => $d];
        $res = $conn->query("SELECT perangkat, COUNT(*) as jml FROM data_trafik GROUP BY perangkat");
        $l = []; $d = []; if($res) { while($r = $res->fetch_assoc()) { $l[] = $r['perangkat']; $d[] = $r['jml']; } } $stats['chart_perangkat'] = ['labels' => $l, 'data' => $d];
        $res = $conn->query("SELECT sumber, COUNT(*) as jml FROM data_trafik GROUP BY sumber ORDER BY jml DESC LIMIT 5");
        $l = []; $d = []; if($res) { while($r = $res->fetch_assoc()) { $l[] = $r['sumber']; $d[] = $r['jml']; } } $stats['chart_sumber'] = ['labels' => $l, 'data' => $d];
        
        // 7. Keyword
        $res = $conn->query("SELECT keyword, COUNT(*) as jml FROM data_trafik WHERE keyword != '' AND keyword IS NOT NULL GROUP BY keyword ORDER BY jml DESC LIMIT 5");
        $kw = []; if($res) { while($r = $res->fetch_assoc()) { $kw[] = $r; } }
        $stats['keyword'] = $kw;
        
        die(json_encode(["status" => "success", "data" => $stats]));
    }

    // =======================================================
    // 4.3 JALUR KHUSUS: GET DATA DONATUR
    // =======================================================
    if (isset($data['action']) && $data['action'] === 'get_donatur') {
        $conn->query("CREATE TABLE IF NOT EXISTS `data_donatur` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `waktu` DATETIME,
            `nama` VARCHAR(150),
            `whatsapp` VARCHAR(50),
            `total_donasi` DECIMAL(15,2) DEFAULT 0,
            `frekuensi_donasi` INT DEFAULT 1,
            `kategori` VARCHAR(50)
        )");

        // Otomatis tambahkan kolom baru jika belum ada di database (Kompatibel untuk semua versi MySQL)
        $colCheck1 = $conn->query("SHOW COLUMNS FROM `data_donatur` LIKE 'gender'");
        if ($colCheck1 && $colCheck1->num_rows === 0) {
            $conn->query("ALTER TABLE `data_donatur` ADD COLUMN `gender` VARCHAR(10) DEFAULT '-' AFTER `whatsapp`");
        }
        $colCheck2 = $conn->query("SHOW COLUMNS FROM `data_donatur` LIKE 'program'");
        if ($colCheck2 && $colCheck2->num_rows === 0) {
            $conn->query("ALTER TABLE `data_donatur` ADD COLUMN `program` VARCHAR(255) DEFAULT '-' AFTER `gender`");
        }

        // Pagination & Pencarian
        $page = isset($data['page']) ? max(1, intval($data['page'])) : 1;
        $limit = 100;
        $offset = ($page - 1) * $limit;
        
        $search = isset($data['search']) ? $conn->real_escape_string($data['search']) : '';
        $where = "";
        if (!empty($search)) {
            $where = "WHERE nama LIKE '%$search%' OR whatsapp LIKE '%$search%' OR program LIKE '%$search%'";
        }

        $res = $conn->query("SELECT * FROM data_donatur $where ORDER BY waktu DESC, id DESC LIMIT $limit OFFSET $offset");
        $donaturs = [];
        if ($res) { while($r = $res->fetch_assoc()) { $donaturs[] = $r; } }
        
        $summary = [];
        $summary['total_filtered'] = $conn->query("SELECT COUNT(*) FROM data_donatur $where")->fetch_row()[0] ?? 0;
        $summary['total_pages'] = ceil($summary['total_filtered'] / $limit);
        $summary['total'] = $conn->query("SELECT COUNT(*) FROM data_donatur")->fetch_row()[0] ?? 0;
        $summary['kecil_jarang'] = $conn->query("SELECT COUNT(*) FROM data_donatur WHERE kategori = 'Kecil Jarang'")->fetch_row()[0] ?? 0;
        $summary['kecil_rutin'] = $conn->query("SELECT COUNT(*) FROM data_donatur WHERE kategori = 'Kecil Rutin'")->fetch_row()[0] ?? 0;
        $summary['besar_jarang'] = $conn->query("SELECT COUNT(*) FROM data_donatur WHERE kategori = 'Besar Jarang'")->fetch_row()[0] ?? 0;
        $summary['besar_rutin'] = $conn->query("SELECT COUNT(*) FROM data_donatur WHERE kategori = 'Besar Rutin'")->fetch_row()[0] ?? 0;

        die(json_encode(["status" => "success", "data" => $donaturs, "summary" => $summary]));
    }

    // =======================================================
    // 4.4 JALUR KHUSUS: IMPORT CSV DONATUR
    // =======================================================
    if (isset($data['action']) && $data['action'] === 'import_donatur') {
        if (!isset($_FILES['file_csv'])) {
            die(json_encode(["status" => "error", "message" => "Tidak ada file CSV yang diterima."]));
        }
        
        // JURUS ANTI-ERROR: Deteksi garis baru dari berbagai jenis OS / Google Sheets
        ini_set('auto_detect_line_endings', true);
        $file = $_FILES['file_csv']['tmp_name'];
        
        $handle = fopen($file, "r");
        if ($handle !== FALSE) {
            // JURUS SUPER DETEKSI PEMISAH KOLOM (Koma, Titik Koma, atau Tab)
            $header_line = fgets($handle);
            rewind($handle); // Kembalikan pointer ke awal file
            
            $delimiters = [',', ';', "\t"];
            $delimiter = ',';
            $max_cols = 0;
            foreach ($delimiters as $delim) {
                $cols = count(str_getcsv($header_line, $delim));
                if ($cols > $max_cols) {
                    $max_cols = $cols;
                    $delimiter = $delim;
                }
            }

            // JURUS MATA ELANG: Baca Header untuk mencari posisi kolom secara otomatis!
            $headers = fgetcsv($handle, 0, $delimiter);
            // Remove UTF-8 BOM if present in first header cell
            if (isset($headers[0]) && substr($headers[0], 0, 3) === "\xEF\xBB\xBF") {
                $headers[0] = substr($headers[0], 3);
            }

            // Initialize column indices to -1 (not found)
            $c_wa = $c_nama = $c_gender = $c_tgl = $c_jumlah = $c_frek = $c_prog = -1;
            
            if ($headers) {
                foreach ($headers as $i => $h) {
                    $hl = preg_replace('/[^a-z0-9]/i', '', strtolower($h));
                    if (strpos($hl, 'wa') !== false || strpos($hl, 'whatsapp') !== false) { $c_wa = $i; }
                    elseif (strpos($hl, 'nama') !== false) { $c_nama = $i; }
                    elseif (strpos($hl, 'gender') !== false || strpos($hl, 'jk') !== false || $hl === 'lp') { $c_gender = $i; }
                    elseif (strpos($hl, 'tgl') !== false || strpos($hl, 'tanggal') !== false) { $c_tgl = $i; }
                    elseif (strpos($hl, 'frek') !== false || strpos($hl, 'frekuensi') !== false) { $c_frek = $i; }
                    elseif (strpos($hl, 'program') !== false || strpos($hl, 'campaign') !== false) { $c_prog = $i; }
                    elseif (strpos($hl, 'jumlah') !== false || strpos($hl, 'nominal') !== false || $hl === 'donasi') { $c_jumlah = $i; }
                }
            }

            // Set fallback defaults if columns not detected
            if ($c_wa === -1) $c_wa = 1;
            if ($c_nama === -1) $c_nama = 2;
            if ($c_gender === -1) $c_gender = 3;
            if ($c_tgl === -1) $c_tgl = 4;
            if ($c_jumlah === -1) $c_jumlah = 5;
            if ($c_frek === -1) $c_frek = 6;
            if ($c_prog === -1) $c_prog = 7;

            $stmt = $conn->prepare("INSERT INTO data_donatur (waktu, nama, whatsapp, gender, total_donasi, frekuensi_donasi, program, kategori) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $count = 0;
            
            $conn->autocommit(FALSE); // Optimasi kecepatan tinggi
            
            while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                if (empty(array_filter($row))) continue;

                $whatsapp = ($c_wa !== -1 && isset($row[$c_wa])) ? trim($row[$c_wa]) : '';
                $nama = ($c_nama !== -1 && isset($row[$c_nama])) ? trim($row[$c_nama]) : 'Hamba Allah';
                
                // Skip jika data penting kosong
                if (empty($whatsapp) && empty($nama)) continue;
                if(empty(trim($nama))) $nama = 'Hamba Allah';

                $gender = ($c_gender !== -1 && isset($row[$c_gender])) ? trim($row[$c_gender]) : '-';
                
                // JURUS PARSING TANGGAL SUPER SAKTI
                $raw_waktu = ($c_tgl !== -1 && isset($row[$c_tgl])) ? trim($row[$c_tgl]) : '';
                $waktu = date('Y-m-d H:i:s');
                if (!empty($raw_waktu)) {
                    $clean_waktu = preg_replace('/[^0-9a-zA-Z\s\-\/\.]/', '', $raw_waktu);
                    $indo_months = ['januari'=>'01', 'jan'=>'01', 'februari'=>'02', 'feb'=>'02', 'maret'=>'03', 'mar'=>'03', 'april'=>'04', 'apr'=>'04', 'mei'=>'05', 'may'=>'05', 'juni'=>'06', 'jun'=>'06', 'juli'=>'07', 'jul'=>'07', 'agustus'=>'08', 'agu'=>'08', 'aug'=>'08', 'september'=>'09', 'sep'=>'09', 'oktober'=>'10', 'okt'=>'10', 'oct'=>'10', 'november'=>'11', 'nov'=>'11', 'desember'=>'12', 'des'=>'12', 'dec'=>'12'];
                    foreach($indo_months as $id => $en) {
                        if(stripos($clean_waktu, $id) !== false) {
                            $clean_waktu = str_ireplace($id, "-$en-", $clean_waktu);
                        }
                    }
                    $clean_waktu = trim(preg_replace('/-+/', '-', preg_replace('/[\s\/\.]+/', '-', $clean_waktu)), '-');
                    $parts = explode('-', $clean_waktu);
                    if (count($parts) >= 3) {
                        $p1 = intval($parts[0]); $p2 = intval($parts[1]); $p3 = intval($parts[2]);
                        $year = $month = $day = 0;
                        if ($p1 > 1000) { $year = $p1; $month = $p2; $day = $p3; }
                        else if ($p3 > 1000) { $year = $p3; $month = $p2; $day = $p1; }
                        else if ($p3 >= 0 && $p3 < 100) { $year = 2000 + $p3; $month = $p2; $day = $p1; }
                        if ($month > 12 && $day <= 12) { $tmp = $month; $month = $day; $day = $tmp; }
                        if ($year > 0 && $month > 0 && $month <= 12 && $day > 0 && $day <= 31) {
                            $waktu = sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day);
                        }
                    }
                }
                
                // JURUS PARSING ANGKA ANTI BOCOR
                $jumlah_str = ($c_jumlah !== -1 && isset($row[$c_jumlah])) ? trim($row[$c_jumlah]) : '0';
                if (stripos($jumlah_str, 'E') !== false) { $total_donasi = floatval($jumlah_str); } 
                else {
                    $jumlah_str = preg_replace('/[^0-9,\.-]/', '', $jumlah_str);
                    if (preg_match('/,\d{1,2}$/', $jumlah_str)) { $jumlah_str = preg_replace('/,\d{1,2}$/', '', $jumlah_str); }
                    $total_donasi = floatval(preg_replace('/[^0-9]/', '', $jumlah_str));
                }
                
                $frekuensi = ($c_frek !== -1 && isset($row[$c_frek])) ? intval(preg_replace('/[^0-9]/', '', $row[$c_frek])) : 1;
                $frekuensi = $frekuensi <= 0 ? 1 : $frekuensi;
                
                $program = ($c_prog !== -1 && isset($row[$c_prog])) ? trim($row[$c_prog]) : '-';
                if (strpos($program, '#') === 0) $program = '-';
                
                $kategori = "Kecil Jarang";
                if ($total_donasi >= 500000 && $frekuensi >= 3) { $kategori = "Besar Rutin"; }
                else if ($total_donasi >= 500000 && $frekuensi < 3) { $kategori = "Besar Jarang"; }
                else if ($total_donasi < 500000 && $frekuensi >= 3) { $kategori = "Kecil Rutin"; }
                
                $stmt->bind_param("ssssdiss", $waktu, $nama, $whatsapp, $gender, $total_donasi, $frekuensi, $program, $kategori);
                $stmt->execute();
                $count++;
            }
            $conn->commit();
            fclose($handle);
            $stmt->close();
            ini_set('auto_detect_line_endings', false); 
            die(json_encode(["status" => "success", "message" => "Alhamdulillah! $count data donatur berhasil diimport & dikategorikan!"]));
        }
    }

    // =======================================================
    // 4.4b JALUR KHUSUS: KOSONGKAN DATA DONATUR (RESET)
    // =======================================================
    if (isset($data['action']) && $data['action'] === 'clear_donatur') {
        if ($conn->query("TRUNCATE TABLE data_donatur")) {
            die(json_encode(["status" => "success", "message" => "Seluruh data donasi berhasil dikosongkan/di-reset!"]));
        } else {
            die(json_encode(["status" => "error", "message" => "Gagal mengosongkan tabel: " . $conn->error]));
        }
    }

    // =======================================================
    // 4.4c JALUR KHUSUS: HAPUS DATA DONASI PER BARIS
    // =======================================================
    if (isset($data['action']) && $data['action'] === 'delete_donatur') {
        $id = $data['id'] ?? '';
        $stmt = $conn->prepare("DELETE FROM data_donatur WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            die(json_encode(["status" => "success", "message" => "Data donasi berhasil dihapus!"]));
        } else {
            die(json_encode(["status" => "error", "message" => "Gagal menghapus data: " . $stmt->error]));
        }
    }

    // =======================================================
    // 4.4d JALUR KHUSUS: HAPUS DATA DONASI MASAL (BULK)
    // =======================================================
    if (isset($data['action']) && $data['action'] === 'delete_donatur_bulk') {
        $ids = $data['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            die(json_encode(["status" => "error", "message" => "Tidak ada data yang dipilih."]));
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        
        $stmt = $conn->prepare("DELETE FROM data_donatur WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$ids);
        if ($stmt->execute()) {
            die(json_encode(["status" => "success", "message" => count($ids) . " data donasi berhasil dihapus!"]));
        } else {
            die(json_encode(["status" => "error", "message" => "Gagal menghapus data masal: " . $stmt->error]));
        }
    }

    // =======================================================
    // 4.4e JALUR KHUSUS: TAMBAH DATA DONASI MANUAL
    // =======================================================
    if (isset($data['action']) && $data['action'] === 'add_donatur_manual') {
        $nama = $data['nama'] ?? 'Hamba Allah';
        if(empty(trim($nama))) $nama = 'Hamba Allah';
        $whatsapp = $data['whatsapp'] ?? '';
        $gender = $data['gender'] ?? '-';
        $waktu = $data['waktu'] ?? date('Y-m-d H:i:s');
        $total_donasi = floatval($data['jumlah'] ?? 0);
        $frekuensi = intval($data['frek'] ?? 1);
        $program = $data['program'] ?? '-';
        
        $kategori = "Kecil Jarang";
        if ($total_donasi >= 500000 && $frekuensi >= 3) { $kategori = "Besar Rutin"; }
        else if ($total_donasi >= 500000 && $frekuensi < 3) { $kategori = "Besar Jarang"; }
        else if ($total_donasi < 500000 && $frekuensi >= 3) { $kategori = "Kecil Rutin"; }
        
        $stmt = $conn->prepare("INSERT INTO data_donatur (waktu, nama, whatsapp, gender, total_donasi, frekuensi_donasi, program, kategori) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdiss", $waktu, $nama, $whatsapp, $gender, $total_donasi, $frekuensi, $program, $kategori);
        
        if ($stmt->execute()) { die(json_encode(["status" => "success", "message" => "Mantap! Data donasi manual berhasil masuk tabel!"])); } 
        else { die(json_encode(["status" => "error", "message" => "Gagal menyimpan data: " . $stmt->error])); }
    }

    // =======================================================
    // 4.5 JALUR KHUSUS: AI AGENT (GEMINI API VIA PHP)
    // =======================================================
    if (isset($data['action']) && $data['action'] === 'call_ai') {
        // PASTE URL WEB APP GOOGLE APPS SCRIPT BOS DI SINI
        $GAS_WEB_APP_URL = "https://script.google.com/macros/s/AKfycbztcWAAM2eWTSUIJ8jM2m-o72_sA8NXyG7Ck_QxYTin8sBbwf0ab6EzEMNEXe087NY/exec";

        $prompt = $data['prompt'] ?? '';
        if (empty($prompt)) {
            die(json_encode(["status" => "error", "message" => "Prompt kosong."]));
        }

        $postData = json_encode([ "prompt" => $prompt, "token" => $TOKEN_RAHASIA ]);

        $ch = curl_init($GAS_WEB_APP_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // WAJIB: Ikuti pantulan (redirect) URL dari server Google
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Anti-Error SSL saat dites di jaringan Localhost (XAMPP/Laragon)

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            die(json_encode(["status" => "error", "message" => "Gagal menghubungi server GAS: " . $err]));
        }

        // Response dari GAS sudah berformat JSON sukses/error siap pakai
        die($response);
    }

    // =======================================================
    // 4.4f JALUR KHUSUS: EDIT DATA DONASI MANUAL
    // =======================================================
    if (isset($data['action']) && $data['action'] === 'edit_donatur') {
        $id = $data['id'] ?? '';
        $nama = $data['nama'] ?? 'Hamba Allah';
        if(empty(trim($nama))) $nama = 'Hamba Allah';
        $whatsapp = $data['whatsapp'] ?? '';
        $gender = $data['gender'] ?? '-';
        $waktu = $data['waktu'] ?? date('Y-m-d H:i:s');
        $total_donasi = floatval($data['jumlah'] ?? 0);
        $frekuensi = intval($data['frek'] ?? 1);
        $program = $data['program'] ?? '-';
        
        $kategori = "Kecil Jarang";
        if ($total_donasi >= 500000 && $frekuensi >= 3) { $kategori = "Besar Rutin"; }
        else if ($total_donasi >= 500000 && $frekuensi < 3) { $kategori = "Besar Jarang"; }
        else if ($total_donasi < 500000 && $frekuensi >= 3) { $kategori = "Kecil Rutin"; }
        
        $stmt = $conn->prepare("UPDATE data_donatur SET waktu=?, nama=?, whatsapp=?, gender=?, total_donasi=?, frekuensi_donasi=?, program=?, kategori=? WHERE id=?");
        $stmt->bind_param("ssssdissi", $waktu, $nama, $whatsapp, $gender, $total_donasi, $frekuensi, $program, $kategori, $id);
        
        if ($stmt->execute()) { die(json_encode(["status" => "success", "message" => "Mantap! Data donasi berhasil diperbarui!"])); } 
        else { die(json_encode(["status" => "error", "message" => "Gagal memperbarui data: " . $stmt->error])); }
    }

    // =======================================================
    // 4.6 JALUR KHUSUS: MANAJEMEN PROGRAM DONASI
    // =======================================================
    if (isset($data['action']) && in_array($data['action'], ['get_program', 'save_program', 'delete_program'])) {
        $conn->query("CREATE TABLE IF NOT EXISTS `data_program` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `waktu` DATETIME,
            `judul` VARCHAR(255),
            `target_dana` DECIMAL(15,2) DEFAULT 0,
            `terkumpul` DECIMAL(15,2) DEFAULT 0,
            `url_gambar` VARCHAR(255),
            `artikel` TEXT,
            `status` VARCHAR(50) DEFAULT 'Aktif'
        )");

        if ($data['action'] === 'get_program') {
            $res = $conn->query("SELECT * FROM data_program ORDER BY id DESC");
            $programs = [];
            if ($res) { while($r = $res->fetch_assoc()) { $programs[] = $r; } }
            die(json_encode(["status" => "success", "data" => $programs]));
        }

        if ($data['action'] === 'save_program') {
            $id = $data['id'] ?? ''; $judul = $data['judul'] ?? ''; $target = $data['target_dana'] ?? 0;
            $gambar = $data['url_gambar'] ?? ''; $artikel = $data['artikel'] ?? '';

            if (empty($id)) {
                $stmt = $conn->prepare("INSERT INTO data_program (waktu, judul, target_dana, url_gambar, artikel, status) VALUES (NOW(), ?, ?, ?, ?, 'Aktif')");
                $stmt->bind_param("sdss", $judul, $target, $gambar, $artikel);
            } else {
                $stmt = $conn->prepare("UPDATE data_program SET judul=?, target_dana=?, url_gambar=?, artikel=? WHERE id=?");
                $stmt->bind_param("sdssi", $judul, $target, $gambar, $artikel, $id);
            }
            if ($stmt->execute()) { die(json_encode(["status" => "success", "message" => "Program berhasil disimpan!"])); } 
            else { die(json_encode(["status" => "error", "message" => "Gagal menyimpan program: " . $stmt->error])); }
        }

        if ($data['action'] === 'delete_program') {
            $id = $data['id'] ?? '';
            $stmt = $conn->prepare("DELETE FROM data_program WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) { die(json_encode(["status" => "success", "message" => "Program berhasil dihapus!"])); } 
            else { die(json_encode(["status" => "error", "message" => "Gagal menghapus program: " . $stmt->error])); }
        }
    }

    // =======================================================
    // 4.7 JALUR KHUSUS: TRANSAKSI DONASI PENDING (DARI FORM DEPAN)
    // =======================================================
    if (isset($data['action']) && in_array($data['action'], ['submit_donasi_pending', 'get_donasi_pending', 'verify_donasi_pending', 'delete_donasi_pending'])) {
        $conn->query("CREATE TABLE IF NOT EXISTS `data_donasi_pending` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `waktu` DATETIME,
            `panggilan` VARCHAR(50),
            `nama` VARCHAR(150),
            `whatsapp` VARCHAR(50),
            `nominal` DECIMAL(15,2) DEFAULT 0,
            `status` VARCHAR(50) DEFAULT 'Pending'
        )");

        if ($data['action'] === 'submit_donasi_pending') {
            $panggilan = $data['panggilan'] ?? 'Bapak/Ibu';
            $nama = $data['nama'] ?? 'Hamba Allah';
            $whatsapp = $data['wa'] ?? '';
            $nominal = floatval($data['nominal'] ?? 0);
            
            $stmt = $conn->prepare("INSERT INTO data_donasi_pending (waktu, panggilan, nama, whatsapp, nominal, status) VALUES (NOW(), ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("sssd", $panggilan, $nama, $whatsapp, $nominal);
            if ($stmt->execute()) { die(json_encode(["status" => "success", "message" => "Donasi pending berhasil dicatat!"])); } 
            else { die(json_encode(["status" => "error", "message" => "Gagal mencatat donasi: " . $stmt->error])); }
        }

        if ($data['action'] === 'get_donasi_pending') {
            $res = $conn->query("SELECT * FROM data_donasi_pending WHERE status='Pending' ORDER BY id DESC");
            $pending = []; if ($res) { while($r = $res->fetch_assoc()) { $pending[] = $r; } }
            die(json_encode(["status" => "success", "data" => $pending]));
        }

        if ($data['action'] === 'verify_donasi_pending') {
            $id = $data['id'] ?? '';
            $res = $conn->query("SELECT * FROM data_donasi_pending WHERE id='$id' AND status='Pending'");
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $gender = ($row['panggilan'] == 'Bapak' || $row['panggilan'] == 'Mas') ? 'L' : (($row['panggilan'] == 'Ibu' || $row['panggilan'] == 'Mbak') ? 'P' : '-');
                $kategori = ($row['nominal'] >= 500000) ? 'Besar Jarang' : 'Kecil Jarang';
                $waktu = date('Y-m-d H:i:s');

                $conn->autocommit(FALSE);
                try {
                    $conn->query("UPDATE data_donasi_pending SET status='Lunas' WHERE id='$id'");
                    $stmt = $conn->prepare("INSERT INTO data_donatur (waktu, nama, whatsapp, gender, total_donasi, frekuensi_donasi, program, kategori) VALUES (?, ?, ?, ?, ?, 1, 'Donasi Umum (Form Web)', ?)");
                    $stmt->bind_param("ssssds", $waktu, $row['nama'], $row['whatsapp'], $gender, $row['nominal'], $kategori);
                    $stmt->execute();
                    $conn->commit();
                    die(json_encode(["status" => "success", "message" => "Donasi diverifikasi & masuk ke tabel Data Donatur Lunas!"]));
                } catch (Exception $e) { $conn->rollback(); die(json_encode(["status" => "error", "message" => "Gagal verifikasi: " . $e->getMessage()])); }
            } else { die(json_encode(["status" => "error", "message" => "Data tidak ditemukan / sudah lunas."])); }
        }

        if ($data['action'] === 'delete_donasi_pending') {
            $id = $data['id'] ?? '';
            $stmt = $conn->prepare("DELETE FROM data_donasi_pending WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) { die(json_encode(["status" => "success", "message" => "Data pending berhasil dihapus!"])); } 
            else { die(json_encode(["status" => "error", "message" => "Gagal menghapus data: " . $stmt->error])); }
        }
    }

    // 5. JALUR KHUSUS: EKSEKUSI SQL DARI GOOGLE APPS SCRIPT
    $sql = isset($data['sql_base64']) ? base64_decode($data['sql_base64']) : ($data['sql'] ?? '');
    $params = $data['params'] ?? [];
    
    if (empty($sql)) {
        die(json_encode(["status" => "error", "message" => "Tidak ada perintah SQL atau upload yang dijalankan."]));
    }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $types = str_repeat('s', count($params)); 
            $stmt->bind_param($types, ...$params);
        }
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result) {
                $rows = [];
                while ($row = $result->fetch_row()) { $rows[] = $row; }
                echo json_encode(["status" => "success", "data" => $rows]);
            } else {
                echo json_encode(["status" => "success", "affected" => $stmt->affected_rows]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal Eksekusi: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "SQL Prepare Gagal: " . $conn->error]);
    }
    $conn->close();
} catch (Throwable $e) {
    die(json_encode(["status" => "error", "message" => "Terjadi Error Fatal PHP: " . $e->getMessage()]));
}
?>