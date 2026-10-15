<?php
// ============================================
// Smart Attendance System - Delete User API
// ============================================
require_once 'koneksi.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID mahasiswa tidak valid.']);
    exit;
}

$conn = getConnection();

// Hapus user berdasarkan ID
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Mahasiswa berhasil dihapus dari sistem.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Mahasiswa tidak ditemukan di database.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>