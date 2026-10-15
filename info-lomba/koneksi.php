<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host     = "localhost";
$username = "root";
$password = "";
$database = "Qubu";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function get_logged_in_user($conn) {
    if (!is_logged_in()) {
        return null;
    }
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT m.*, p.nama_prodi, b.nama_bidang,
               (SELECT COUNT(*) FROM prestasi pr WHERE pr.mahasiswa_id = m.id) AS total_prestasi
        FROM mahasiswa m
        LEFT JOIN prodi p ON m.prodi_id = p.id
        LEFT JOIN bidang_minat b ON m.bidang_id = b.id
        WHERE m.id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
    return $user;
}

function require_login($redirect_url = 'login.php') {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = "Silakan login terlebih dahulu untuk mengakses halaman ini.";
        header("Location: " . $redirect_url);
        exit;
    }
}

function require_admin($redirect_url = 'index.php') {
    require_login();
    if (!is_admin()) {
        $_SESSION['flash_error'] = "Akses ditolak! Halaman ini hanya untuk Administrator.";
        header("Location: " . $redirect_url);
        exit;
    }
}

function get_unread_count($conn, $user_id) {
    if (!$user_id) return 0;

    $stmt_notif = $conn->prepare("SELECT COUNT(*) AS total FROM notifikasi WHERE mahasiswa_id = ? AND dibaca = 0");
    $stmt_notif->bind_param("i", $user_id);
    $stmt_notif->execute();
    $res_notif = $stmt_notif->get_result()->fetch_assoc();
    $notif_count = $res_notif['total'] ?? 0;
    $stmt_notif->close();

    $stmt_und = $conn->prepare("SELECT COUNT(*) AS total FROM undangan WHERE penerima_id = ? AND status = 'menunggu'");
    $stmt_und->bind_param("i", $user_id);
    $stmt_und->execute();
    $res_und = $stmt_und->get_result()->fetch_assoc();
    $und_count = $res_und['total'] ?? 0;
    $stmt_und->close();

    return (int)($notif_count + $und_count);
}

function tambah_notifikasi($conn, $mahasiswa_id, $judul, $pesan, $link = null) {
    $stmt = $conn->prepare("INSERT INTO notifikasi (mahasiswa_id, judul, pesan, link) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $mahasiswa_id, $judul, $pesan, $link);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function set_flash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function get_flash($type) {
    if (isset($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return null;
}
?>

