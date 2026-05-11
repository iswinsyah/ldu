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
        
        $upload_dir = 'uploads/';
        // Gunakan file_exists seperti di kode Bos
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                die(json_encode(["status" => "error", "message" => "Gagal membuat folder uploads/."]));
            }
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_name)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $base_url = rtrim(dirname($_SERVER['PHP_SELF']), '/');
            $file_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . $base_url . "/" . $upload_dir . $new_name;
            
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