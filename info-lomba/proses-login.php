<?php
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        set_flash('error', 'Harap isi email/nomor telepon dan password!');
        header("Location: login.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT id, nama, email, password, role FROM mahasiswa WHERE email = ? OR no_wa = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        if (password_verify($password, $row['password'])) {

            $_SESSION['user_id'] = $row['id'];
            $_SESSION['nama']    = $row['nama'];
            $_SESSION['email']   = $row['email'];
            $_SESSION['role']    = $row['role'];

            set_flash('success', 'Selamat datang kembali, ' . htmlspecialchars($row['nama']) . '!');

            if ($row['role'] === 'admin') {
                header("Location: admin-lomba.php");
            } else {
                header("Location: index.php");
            }
            $stmt->close();
            $conn->close();
            exit;
        }
    }

    set_flash('error', 'Email/Nomor telepon atau password yang Anda masukkan salah!');
    header("Location: login.php");
    $stmt->close();
    $conn->close();
    exit;
} else {
    header("Location: login.php");
    exit;
}
?>

