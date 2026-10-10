<?php
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama             = trim($_POST['fullname'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $prodi_nama       = trim($_POST['prodi'] ?? '');
    $minat_nama       = trim($_POST['minat'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validasi input kosong
    if (empty($nama) || empty($email) || empty($password)) {
        echo "<script>alert('Harap lengkapi semua field yang wajib diisi!'); history.back();</script>";
        exit;
    }

    // Validasi kesamaan password
    if ($password !== $confirm_password) {
        echo "<script>alert('Password dan konfirmasi password tidak cocok!'); history.back();</script>";
        exit;
    }

    // Cek apakah email sudah terdaftar
    $stmt_cek = $conn->prepare("SELECT id FROM mahasiswa WHERE email = ?");
    $stmt_cek->bind_param("s", $email);
    $stmt_cek->execute();
    $stmt_cek->store_result();

    if ($stmt_cek->num_rows > 0) {
        echo "<script>alert('Email sudah terdaftar. Silakan gunakan email lain atau login!'); history.back();</script>";
        $stmt_cek->close();
        exit;
    }
    $stmt_cek->close();

    // Dapatkan atau simpan ID prodi
    $prodi_id = null;
    if (!empty($prodi_nama)) {
        $stmt_p = $conn->prepare("SELECT id FROM prodi WHERE nama_prodi = ?");
        $stmt_p->bind_param("s", $prodi_nama);
        $stmt_p->execute();
        $res_p = $stmt_p->get_result();
        if ($row_p = $res_p->fetch_assoc()) {
            $prodi_id = $row_p['id'];
        } else {
            $stmt_ins_p = $conn->prepare("INSERT INTO prodi (nama_prodi) VALUES (?)");
            $stmt_ins_p->bind_param("s", $prodi_nama);
            $stmt_ins_p->execute();
            $prodi_id = $conn->insert_id;
            $stmt_ins_p->close();
        }
        $stmt_p->close();
    }

    // Dapatkan atau simpan ID bidang minat
    $bidang_id = null;
    if (!empty($minat_nama)) {
        $stmt_m = $conn->prepare("SELECT id FROM bidang_minat WHERE nama_bidang = ?");
        $stmt_m->bind_param("s", $minat_nama);
        $stmt_m->execute();
        $res_m = $stmt_m->get_result();
        if ($row_m = $res_m->fetch_assoc()) {
            $bidang_id = $row_m['id'];
        } else {
            $stmt_ins_m = $conn->prepare("INSERT INTO bidang_minat (nama_bidang) VALUES (?)");
            $stmt_ins_m->bind_param("s", $minat_nama);
            $stmt_ins_m->execute();
            $bidang_id = $conn->insert_id;
            $stmt_ins_m->close();
        }
        $stmt_m->close();
    }

    // Hash password untuk keamanan
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Simpan data mahasiswa
    $stmt_insert = $conn->prepare("INSERT INTO mahasiswa (nama, email, password, prodi_id, bidang_id) VALUES (?, ?, ?, ?, ?)");
    $stmt_insert->bind_param("sssii", $nama, $email, $hashed_password, $prodi_id, $bidang_id);

    if ($stmt_insert->execute()) {
        echo "<script>alert('Pendaftaran berhasil! Silakan log in.'); window.location.href = 'login.html';</script>";
    } else {
        echo "<script>alert('Terjadi kesalahan saat mendaftar: " . addslashes($stmt_insert->error) . "'); history.back();</script>";
    }

    $stmt_insert->close();
    $conn->close();
}
?>
