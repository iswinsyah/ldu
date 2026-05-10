<?php
// Cegah masalah CORS dan atur agar responnya berupa JSON
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

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
$user = "u829486010_ldu"; // Username Database Bos
$pass = "PASSWORD_DATABASE_BOS"; // Ganti dengan Password Database Bos
$dbname = "u829486010_ldu"; // Nama Database Bos

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Koneksi Hostinger Gagal: " . $conn->connect_error]));
}

$sql = $data['sql'] ?? '';
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
?>