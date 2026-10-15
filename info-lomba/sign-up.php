<?php
require_once 'koneksi.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$error_msg = get_flash('error');
$success_msg = get_flash('success');

$prodi_res = $conn->query("SELECT * FROM prodi ORDER BY nama_prodi ASC");
$minat_res = $conn->query("SELECT * FROM bidang_minat ORDER BY nama_bidang ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qubu - Sign-up Pendaftaran Mahasiswa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .alert-box {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .signup-container {
            width: 90%;
            max-width: 480px;
            margin: 40px auto 60px auto;
            background: var(--surface);
            border-radius: 32px;
            padding: 40px 32px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.03);
        }
    </style>
</head>
<body class="auth-page">

    <nav class="navbar">
        <div class="nav-brand">
            <a href="index.php">Qubu<span>.</span></a>
        </div>

        <div class="nav-right">
            <ul class="nav-links">
                <li><a href="index.php" class="nav-link">Info Lomba</a></li>
                <li><a href="cari-partner.php" class="nav-link">Cari Partner</a></li>
                <li><a href="search-dospem.php" class="nav-link">Info Pembimbing</a></li>
            </ul>

            <div class="nav-auth">
                <a href="sign-up.php" class="nav-signup" style="color: var(--brand); font-weight: 700;">Sign up</a>
                <a href="login.php" class="nav-login-btn">Log in</a>
            </div>
        </div>
    </nav>

    <div class="signup-container">
        <header class="login-header">
            <h1>Buat Akun Qubu</h1>
            <p style="color: var(--muted); font-size: 13.5px; margin-top: 4px;">Daftar dan temukan rekan tim kompetisi terbaikmu</p>
        </header>

        <?php if ($error_msg): ?>
            <div class="alert-box alert-danger">
                <span>⚠️</span> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
            <div class="alert-box alert-success">
                <span>✅</span> <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>

        <form action="proses-register.php" method="POST" class="login-form">
            <div class="form-group">
                <label style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">Nama Lengkap</label>
                <input
                    type="text"
                    id="fullname"
                    name="fullname"
                    placeholder="Contoh: Budi Pratama"
                    required
                >
            </div>

            <div class="form-group">
                <label style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">Email Mahasiswa / Kampus</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="nama@student.unsika.ac.id"
                    required
                >
            </div>

            <div class="form-group">
                <label style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">Nomor WhatsApp (Untuk Koordinasi Tim)</label>
                <input
                    type="text"
                    id="no_wa"
                    name="no_wa"
                    placeholder="081234567890"
                    required
                >
            </div>

            <div class="form-group">
                <label style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">Program Studi</label>
                <select id="prodi" name="prodi" class="form-select" required>
                    <option value="" disabled selected>Pilih Program Studi</option>
                    <?php if ($prodi_res): ?>
                        <?php while ($p = $prodi_res->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($p['nama_prodi']) ?>"><?= htmlspecialchars($p['nama_prodi']) ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">Bidang Minat Lomba Utama</label>
                <select id="minat" name="minat" class="form-select" required>
                    <option value="" disabled selected>Pilih Bidang Minat Utama</option>
                    <?php if ($minat_res): ?>
                        <?php while ($m = $minat_res->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($m['nama_bidang']) ?>"><?= htmlspecialchars($m['nama_bidang']) ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">Skill & Keahlian (Pisahkan dengan koma)</label>
                <input
                    type="text"
                    id="skills"
                    name="skills"
                    placeholder="Contoh: Figma, UI/UX, React, Python, Machine Learning"
                >
            </div>

            <div class="form-group">
                <label style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Minimal 6 karakter"
                    minlength="6"
                    required
                >
            </div>

            <div class="form-group">
                <label style="font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">Konfirmasi Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Ulangi password"
                    minlength="6"
                    required
                >
            </div>

            <button type="submit" class="btn-dark" style="margin-top: 10px;">Daftar Akun Sekarang</button>
            <a href="login.php" class="btn-dark" style="background: transparent; color: var(--ink); border: 1.5px solid var(--line); box-shadow: none;">Sudah punya akun? Log-in</a>
        </form>
    </div>

</body>
</html>

