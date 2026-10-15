<?php
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama             = trim($_POST['fullname'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $no_wa            = trim($_POST['no_wa'] ?? '');
    $prodi_nama       = trim($_POST['prodi'] ?? '');
    $minat_nama       = trim($_POST['minat'] ?? '');
    $skills           = trim($_POST['skills'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($nama) || empty($email) || empty($password)) {
        set_flash('error', 'Harap lengkapi semua field yang wajib diisi!');
        header("Location: sign-up.php");
        exit;
    }

    if ($password !== $confirm_password) {
        set_flash('error', 'Password dan konfirmasi password tidak cocok!');
        header("Location: sign-up.php");
        exit;
    }

    $stmt_cek = $conn->prepare("SELECT id FROM mahasiswa WHERE email = ?");
    $stmt_cek->bind_param("s", $email);
    $stmt_cek->execute();
    $stmt_cek->store_result();

    if ($stmt_cek->num_rows > 0) {
        set_flash('error', 'Email sudah terdaftar. Silakan gunakan email lain atau login!');
        $stmt_cek->close();
        header("Location: sign-up.php");
        exit;
    }
    $stmt_cek->close();

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

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = 'mahasiswa';

    $stmt_insert = $conn->prepare("INSERT INTO mahasiswa (nama, email, password, no_wa, prodi_id, bidang_id, role, skills) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_insert->bind_param("ssssiiss", $nama, $email, $hashed_password, $no_wa, $prodi_id, $bidang_id, $role, $skills);

    if ($stmt_insert->execute()) {
        $new_user_id = $conn->insert_id;

        tambah_notifikasi($conn, $new_user_id, "Selamat Datang di Qubu!", "Akun Anda berhasil dibuat. Lengkapi prestasi dan temukan partner tim lomba terbaikmu!");
        set_flash('success', 'Pendaftaran akun berhasil! Silakan login untuk memulai.');
        header("Location: login.php");
    } else {
        set_flash('error', 'Terjadi kesalahan saat mendaftar: ' . $stmt_insert->error);
        header("Location: sign-up.php");
    }

    $stmt_insert->close();
    $conn->close();
} else {
    header("Location: sign-up.php");
    exit;
}
?>

