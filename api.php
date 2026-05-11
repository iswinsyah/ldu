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

        // Otomatis tambahkan kolom baru jika belum ada di database
        $conn->query("ALTER TABLE `data_donatur` ADD COLUMN `gender` VARCHAR(10) DEFAULT '-' AFTER `whatsapp`");
        $conn->query("ALTER TABLE `data_donatur` ADD COLUMN `program` VARCHAR(255) DEFAULT '-' AFTER `gender`");

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

            // Buang baris pertama (Header Judul Kolom)
            fgetcsv($handle, 0, $delimiter);

            $stmt = $conn->prepare("INSERT INTO data_donatur (waktu, nama, whatsapp, gender, total_donasi, frekuensi_donasi, program, kategori) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $count = 0;
            
            $conn->autocommit(FALSE); // Optimasi kecepatan tinggi untuk 13.000 data
            
            while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                if (empty(array_filter($row))) continue; // Lewati baris kosong

                // Format Baru Excel: 0:No | 1:WhatsApp | 2:Nama | 3:L/P | 4:Tgl | 5:Jumlah | 6:Frekuensi | 7:Program
                $whatsapp = isset($row[1]) ? trim($row[1]) : '';
                $nama = isset($row[2]) ? trim($row[2]) : 'Hamba Allah';
                if(empty(trim($nama))) $nama = 'Hamba Allah';
                $gender = isset($row[3]) ? trim($row[3]) : '-';
                
                // Terjemahkan nama bulan Indonesia ke Inggris agar PHP paham
                $raw_waktu = isset($row[4]) ? str_replace('/', '-', trim($row[4])) : '';
                $indo_months = ['januari'=>'jan', 'februari'=>'feb', 'maret'=>'mar', 'april'=>'apr', 'mei'=>'may', 'juni'=>'jun', 'juli'=>'jul', 'agustus'=>'aug', 'september'=>'sep', 'oktober'=>'oct', 'november'=>'nov', 'desember'=>'dec'];
                $en_waktu = str_ireplace(array_keys($indo_months), array_values($indo_months), str_replace('/', '-', $raw_waktu));
                $waktu = (!empty(trim($en_waktu)) && strtotime($en_waktu)) ? date('Y-m-d H:i:s', strtotime($en_waktu)) : date('Y-m-d H:i:s');
                
                $total_donasi = isset($row[5]) ? floatval(preg_replace('/[^0-9]/', '', $row[5])) : 0;
                $frekuensi = isset($row[6]) ? intval($row[6]) : 1;
                $frekuensi = $frekuensi <= 0 ? 1 : $frekuensi; // Minimal 1 kali donasi
                $program = isset($row[7]) ? trim($row[7]) : '-';
                
                // AI Pelabelan RFM Otomatis
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
            ini_set('auto_detect_line_endings', false); // Kembalikan settingan default
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
    // 4.5 JALUR KHUSUS: AI AGENT (GEMINI API VIA PHP)
    // =======================================================
    if (isset($data['action']) && $data['action'] === 'call_ai') {
        // TULIS API KEY GEMINI BOS DI SINI (DAPATKAN DARI GOOGLE AI STUDIO)
        $GEMINI_API_KEY = "AIzaSyCMSLHbNvcZlG5NdydU37bijTwwcDcyZnQ"; 
        
        if (empty($GEMINI_API_KEY) || $GEMINI_API_KEY === "MASUKKAN_API_KEY_GEMINI_BOS_DISINI") {
            die(json_encode(["status" => "error", "message" => "API Key Gemini belum diisi di dalam file api.php Bos!"]));
        }

        $prompt = $data['prompt'] ?? '';
        if (empty($prompt)) {
            die(json_encode(["status" => "error", "message" => "Prompt kosong."]));
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $GEMINI_API_KEY;
        $postData = json_encode([ "contents" => [ ["parts" => [["text" => $prompt]]] ] ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            die(json_encode(["status" => "error", "message" => "Gagal menghubungi server Google API: " . $err]));
        }

        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            die(json_encode(["status" => "success", "data" => $result['candidates'][0]['content']['parts'][0]['text']]));
        } else {
            $errorMsg = $result['error']['message'] ?? "Format balasan tidak dikenali.";
            die(json_encode(["status" => "error", "message" => "Gemini API Error: " . $errorMsg]));
        }
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