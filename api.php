<?php
// =======================================================
// API LUMBUNG DANA UMAT - VERSI BERSIH & FINAL
// =======================================================
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
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
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            die(json_encode(["status" => "error", "message" => "Tidak ada file fisik yang diterima server."]));
        }
        
        $file = $_FILES['file'];
        $original_name = $file['name'];
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $new_name = time() . '_' . rand(1000,9999) . '.' . $ext;
        
        $upload_dir = 'asset/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                die(json_encode(["status" => "error", "message" => "Gagal membuat folder asset/."]));
            }
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $file_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/" . $upload_dir . $new_name;
            
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