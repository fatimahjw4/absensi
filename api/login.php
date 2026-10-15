<?php
// ============================================
// Smart Attendance System - API Login
// ============================================
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Method tidak diizinkan');
}

// Ambil raw body
$rawBody = file_get_contents('php://input');

// Coba parse sebagai JSON
$jsonInput = json_decode($rawBody, true);

// Coba parse sebagai query string / form-data
$queryInput = [];
parse_str(ltrim($rawBody, '?'), $queryInput);

// Prioritas: JSON → $_POST (form-data) → query string di body
$nim      = trim($jsonInput['nim']      ?? $_POST['nim']      ?? $queryInput['nim']      ?? '');
$password = trim($jsonInput['password'] ?? $_POST['password'] ?? $queryInput['password'] ?? '');

if (empty($nim) || empty($password)) {
    sendResponse('error', 'NIM dan password wajib diisi');
}

$conn = getConnection();

// Ambil data user berdasarkan NIM saja terlebih dahulu
$stmt = $conn->prepare("SELECT id, nama, nim, password FROM users WHERE nim = ?");
$stmt->bind_param('s', $nim);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    sendResponse('error', 'NIM atau password salah');
}

$user = $result->fetch_assoc();
$conn->close();

$storedPassword = $user['password'];
$isPasswordValid = false;

// Cek apakah password menggunakan format hash modern (password_verify) atau MD5 lama
if (password_needs_rehash($storedPassword, PASSWORD_DEFAULT) || strlen($storedPassword) > 32) {
    // Jika formatnya hash modern
    $isPasswordValid = password_verify($password, $storedPassword);
} else {
    // Fallback untuk MD5 lama (jika ada data lama)
    $isPasswordValid = ($storedPassword === md5($password));
}

if (!$isPasswordValid) {
    sendResponse('error', 'NIM atau password salah');
}

// Jika valid, kirim respon sukses
sendResponse('success', 'Login berhasil', [
    'user_id' => (int)$user['id'],
    'nama'    => $user['nama'],
    'nim'     => $user['nim']
]);
?>