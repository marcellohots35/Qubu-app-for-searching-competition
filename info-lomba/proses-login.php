<?php
session_start();
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? ''); // Dapat berupa email atau nomor telepon/WA
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        echo "<script>alert('Harap isi email/nomor telepon dan password!'); history.back();</script>";
        exit;
    }

    // Cari akun mahasiswa berdasarkan email atau no_wa
    $stmt = $conn->prepare("SELECT id, nama, email, password FROM mahasiswa WHERE email = ? OR no_wa = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Cek kecocokan password dengan hash yang ada di database
        if (password_verify($password, $row['password'])) {
            // Simpan informasi user ke session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['nama']    = $row['nama'];
            $_SESSION['email']   = $row['email'];

            echo "<script>alert('Login berhasil! Selamat datang, " . addslashes(htmlspecialchars($row['nama'])) . ".'); window.location.href = 'search-dospem.html';</script>";
            $stmt->close();
            $conn->close();
            exit;
        }
    }

    // Jika username atau password tidak cocok
    echo "<script>alert('Email/Nomor telepon atau password salah!'); history.back();</script>";
    $stmt->close();
    $conn->close();
}
?>
