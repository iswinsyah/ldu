<?php
// Cegah masalah CORS dan atur agar responnya berupa JSON
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// Matikan auto-exception mysqli bawaan PHP 8 agar error tidak jadi blank screen
mysqli_report(MYSQLI_REPORT_OFF);

// =======================================================
// 1. TOKEN RAHASIA (Ibarat gembok baru untuk API ini)
// =======================================================
$TOKEN_RAHASIA = "LduBerkah999!";

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['token']) || $data['token'] !== $TOKEN_RAHASIA) {
    die(json_encode(["status" => "error", "message" => "Akses Ditolak! Kunci rahasia salah."]));
}

// =======================================================
// 2. KREDENSIAL DATABASE (Sekarang bebas pakai simbol rumit!)
// =======================================================
$host = "localhost"; // Biarkan localhost karena 1 server
$user = "u829486010_ldu"; 

// !!! SANGAT PENTING: GANTI TULISAN DI BAWAH INI DENGAN PASSWORD DATABASE ASLI BOS !!!
$pass = "LDUKotaBatu2026!"; 

$dbname = "u829486010_ldu"; 

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        die(json_encode(["status" => "error", "message" => "Koneksi Hostinger Gagal: " . $conn->connect_error]));
    }
    
    // =======================================================
    // BLOK KHUSUS UPLOAD MEDIA (SIMPAN KE FOLDER & DATABASE)
    // =======================================================
    if (isset($data['action']) && $data['action'] === 'upload_media') {
        $base64_string = $data['file_base64'];
        $original_name = $data['filename'];
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
        // Beri nama unik agar tidak tertimpa jika ada file kembar
        $new_name = time() . '_' . rand(1000,9999) . '.' . $ext;
        
        $upload_dir = 'media/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                die(json_encode(["status" => "error", "message" => "Gagal membuat folder media/ di server."]));
            }
        }
        
        // Bersihkan teks "data:image/png;base64," di depan string
        if (strpos($base64_string, ',') !== false) {
            $base64_string = explode(',', $base64_string)[1];
        }
        
        $decoded = base64_decode($base64_string);
        if (file_put_contents($upload_dir . $new_name, $decoded) === false) {
            die(json_encode(["status" => "error", "message" => "Gagal menyimpan file fisik ke folder media/."]));
        }
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $file_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/" . $upload_dir . $new_name;
        
        $stmt = $conn->prepare("INSERT INTO data_media (waktu, nama_file, url) VALUES (NOW(), ?, ?)");
        if (!$stmt) {
            die(json_encode(["status" => "error", "message" => "SQL Prepare Gagal: " . $conn->error]));
        }
        $stmt->bind_param("ss", $original_name, $file_url);
        if (!$stmt->execute()) {
            die(json_encode(["status" => "error", "message" => "SQL Insert Gagal: " . $stmt->error]));
        }
        $stmt->close();
        
        die(json_encode(["status" => "success", "url" => $file_url]));
    }

    // Otomatis decode base64 dari Google untuk menembus Firewall Hostinger
    $sql = isset($data['sql_base64']) ? base64_decode($data['sql_base64']) : ($data['sql'] ?? '');
    $params = $data['params'] ?? [];
    
    if (empty($sql)) {
        die(json_encode(["status" => "error", "message" => "Query kosong."]));
    }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $types = str_repeat('s', count($params)); // Anggap semua parameter sebagai teks agar stabil
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
    // Tangkap error fatal apapun dan kembalikan sebagai JSON
    die(json_encode(["status" => "error", "message" => "Terjadi Error Fatal PHP: " . $e->getMessage()]));
}
?>