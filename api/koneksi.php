<?php
// ============================================
// Smart Attendance System - Database Config
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'absensi_db');

// Timezone WIB – wajib diset agar date() menggunakan waktu Indonesia
date_default_timezone_set('Asia/Jakarta');

// Header CORS untuk akses dari mobile app
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Koneksi database gagal: ' . $conn->connect_error
        ]);
        exit();
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function sendResponse($status, $message, $data = null) {
    $response = ['status' => $status, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response);
    exit();
}
?>
