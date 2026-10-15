<?php
// ============================================
// Smart Attendance System - Register API
// ============================================
require_once 'koneksi.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$nama     = trim($input['nama'] ?? '');
$nim      = trim($input['nim'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($nama) || empty($nim) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Semua kolom wajib diisi!']);
    exit;
}

$conn = getConnection();

// Cek apakah NIM sudah terdaftar sebelumnya
$stmtCek = $conn->prepare("SELECT id FROM users WHERE nim = ?");
$stmtCek->bind_param("s", $nim);
$stmtCek->execute();
$stmtCek->store_result();

if ($stmtCek->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'NIM sudah terdaftar! Silakan gunakan NIM lain.']);
    $stmtCek->close();
    $conn->close();
    exit;
}
$stmtCek->close();

// Hash password sebelum disimpan ke database
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Masukkan data mahasiswa baru ke tabel users
$stmtInsert = $conn->prepare("INSERT INTO users (nama, nim, password) VALUES (?, ?, ?)");
$stmtInsert->bind_param("sss", $nama, $nim, $hashedPassword);

if ($stmtInsert->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Mahasiswa berhasil ditambahkan!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal mendaftarkan akun: ' . $conn->error]);
}

$stmtInsert->close();
$conn->close();
?>