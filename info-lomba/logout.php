<?php
require_once 'koneksi.php';

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

session_start();
$_SESSION['flash_success'] = "Anda berhasil keluar (logout). Sampai jumpa kembali!";

header("Location: login.php");
exit;
?>

