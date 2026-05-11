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
        $res = $conn->query("SELECT sumber, COUNT(*) as jml FROM data_trafik GROUP BY sumber ORDER BY jml DESC LIMIT 5");
        $l = []; $d = []; if($res) { while($r = $res->fetch_assoc()) { $l[] = $r['sumber']; $d[] = $r['jml']; } } $stats['chart_sumber'] = ['labels' => $l, 'data' => $d];
        
        // 7. Keyword
        $res = $conn->query("SELECT keyword, COUNT(*) as jml FROM data_trafik WHERE keyword != '' AND keyword IS NOT NULL GROUP BY keyword ORDER BY jml DESC LIMIT 5");
        $kw = []; if($res) { while($r = $res->fetch_assoc()) { $kw[] = $r; } }
        $stats['keyword'] = $kw;
        
        die(json_encode(["status" => "success", "data" => $stats]));
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